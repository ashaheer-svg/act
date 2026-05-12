<?php
require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/Auth.php';

$db = new Database(DATABASE_PATH);
$db->initialize(); 
$db->syncCustomerProfiles(); 

$auth = new Auth($db);
$auth->requireAccounts(); 

$user = $auth->getCurrentUser();
$currency = $db->getSetting('currency_symbol', '$');

$message = '';
$error = '';

// Search and Pagination
$search = $_GET['search'] ?? '';
$itemsPerPage = 25;

$totalCustomers = $db->countCustomers($search);
$totalPages = ceil($totalCustomers / $itemsPerPage);

// Renamed from currentPage to pageNum to avoid conflict with sidebar.php
$pageNum = isset($_GET['page']) ? max(1, min((int)$_GET['page'], max(1, $totalPages))) : 1;
$offset = ($pageNum - 1) * $itemsPerPage;

// Handle Bulk Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_update') {
    $names = $_POST['customer_names'] ?? [];
    $type = $_POST['bulk_type'] ?? '';
    
    if (!empty($names) && !empty($type)) {
        if ($db->bulkUpdateCustomerProfiles($names, $type)) {
            $message = count($names) . " customers updated to $type and marked as verified.";
        } else {
            $error = "Failed to update customers.";
        }
    }
}

// Handle Single Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_type') {
    $name = $_POST['customer_name'] ?? '';
    $type = $_POST['customer_type'] ?? 'End Customer';
    
    if ($db->updateCustomerType($name, $type)) {
        $message = "Customer '$name' updated to $type and marked as verified.";
    } else {
        $error = "Failed to update customer.";
    }
}

$customers = $db->getCustomerProfiles($itemsPerPage, $offset, $search);

$queryParams = $_GET;
unset($queryParams['page']);
$basePageUrl = '?' . http_build_query($queryParams) . (empty($queryParams) ? '' : '&') . 'page=';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Management - Activity</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="docs/lucide-font/lucide.css">
    <link rel="stylesheet" href="layout.css?v=1.1.0">
    <style>
        .badge { display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .badge-partner { background: #e0f2fe; color: #0369a1; }
        .badge-end { background: #f3f4f6; color: #4b5563; }
        .badge-verified { background: #dcfce7; color: #15803d; }
        .badge-unverified { background: #fef2f2; color: #b91c1c; }
        .checkbox-cell { width: 50px; text-align: center; vertical-align: middle; }
        .checkbox-cell input { width: 22px; height: 22px; cursor: pointer; }
        .action-panel { background: #ffffff; border: 2px solid var(--primary); border-radius: 16px; padding: 25px; margin-bottom: 30px; box-shadow: var(--shadow-lg); }
        .pager-container { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 15px; background: #f1f5f9; }
        .pager-link { padding: 8px 15px; border-radius: 8px; background: white; border: 1px solid #cbd5e1; color: #1e293b; text-decoration: none; font-weight: 700; font-size: 13px; }
        .pager-link.active { background: var(--primary); color: white; border-color: var(--primary); }
        .pager-link.disabled { opacity: 0.4; pointer-events: none; }
    </style>
</head>
<body>
    <div class="app-container">
        <?php require_once 'includes/sidebar.php'; ?>
        <main class="main-wrapper">
            <?php require_once 'includes/header.php'; ?>
            <div class="content-body">
                <div class="page-header" style="margin-bottom: 20px;">
                    <h1 style="font-size: 30px; font-weight: 900;">Customer Management</h1>
                    <p style="color: var(--text-muted);">Total Customers: <strong><?php echo $totalCustomers; ?></strong> | Page <strong><?php echo $pageNum; ?></strong> of <?php echo $totalPages; ?></p>
                </div>

                <?php if ($message): ?><div class="message success"><?php echo $message; ?></div><?php endif; ?>

                <form method="POST" id="bulkForm">
                    <input type="hidden" name="action" value="bulk_update">
                    
                    <!-- Bulk Tool -->
                    <div class="action-panel">
                        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px;">
                            <div>
                                <h3 style="margin: 0; font-size: 18px; font-weight: 800;">Bulk Update Action</h3>
                                <div id="countDisplay" style="margin-top: 5px; font-weight: 700; color: var(--primary);">0 items selected</div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <select name="bulk_type" class="form-control" style="width: 200px; height: 40px; font-weight: 600;">
                                    <option value="Partner">Set as Partner</option>
                                    <option value="End Customer">Set as End Customer</option>
                                </select>
                                <button type="submit" id="submitBtn" class="btn btn-primary" style="height: 40px; padding: 0 20px;" disabled>Apply to All Selected</button>
                            </div>
                        </div>
                    </div>

                    <!-- Table Card -->
                    <div class="card" style="padding: 0; overflow: hidden;">
                        <table class="table" style="margin: 0;">
                            <thead style="background: #f8fafc;">
                                <tr>
                                    <th class="checkbox-cell"><input type="checkbox" id="masterBox" onchange="toggleMaster(this)"></th>
                                    <th>Customer Name</th>
                                    <th>Type</th>
                                    <th style="text-align: right;">Revenue</th>
                                    <th style="text-align: center;">Verified</th>
                                    <th style="text-align: right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($customers as $c): ?>
                                <tr>
                                    <td class="checkbox-cell">
                                        <input type="checkbox" name="customer_names[]" value="<?php echo htmlspecialchars($c['customer_name']); ?>" class="row-check" onchange="refreshSelection()">
                                    </td>
                                    <td style="font-weight: 700;"><?php echo htmlspecialchars($c['customer_name']); ?></td>
                                    <td>
                                        <span class="badge <?php echo ($c['customer_type'] ?? '') === 'Partner' ? 'badge-partner' : 'badge-end'; ?>">
                                            <?php echo htmlspecialchars($c['customer_type'] ?? 'End Customer'); ?>
                                        </span>
                                    </td>
                                    <td style="text-align: right; font-weight: 700; color: var(--primary);">
                                        <?php echo $currency . number_format($c['lifetime_revenue'] ?? 0, 0); ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if (isset($c['is_verified']) && $c['is_verified']): ?>
                                            <i class="icon-check-circle" style="color: var(--success);"></i>
                                        <?php else: ?>
                                            <i class="icon-help-circle" style="color: var(--text-muted);"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: right;">
                                        <button type="button" class="btn btn-outline" style="padding: 5px 10px; font-size: 12px;" 
                                                onclick="doFlip('<?php echo addslashes($c['customer_name']); ?>', '<?php echo ($c['customer_type'] ?? '') === 'Partner' ? 'End Customer' : 'Partner'; ?>')">
                                            Switch
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <!-- Pager -->
                        <?php if ($totalPages > 1): ?>
                        <div class="pager-container">
                            <a href="<?php echo $basePageUrl; ?>1" class="pager-link <?php echo $pageNum == 1 ? 'disabled' : ''; ?>">First</a>
                            <a href="<?php echo $basePageUrl . ($pageNum - 1); ?>" class="pager-link <?php echo $pageNum <= 1 ? 'disabled' : ''; ?>">Previous</a>
                            
                            <?php for($i=1; $i<=$totalPages; $i++): ?>
                                <?php if ($totalPages < 10 || ($i > $pageNum - 3 && $i < $pageNum + 3)): ?>
                                    <a href="<?php echo $basePageUrl . $i; ?>" class="pager-link <?php echo $i == $pageNum ? 'active' : ''; ?>"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <a href="<?php echo $basePageUrl . ($pageNum + 1); ?>" class="pager-link <?php echo $pageNum >= $totalPages ? 'disabled' : ''; ?>">Next</a>
                            <a href="<?php echo $basePageUrl . $totalPages; ?>" class="pager-link <?php echo $pageNum == $totalPages ? 'disabled' : ''; ?>">Last</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </form>

                <form id="singleForm" method="POST" style="display: none;">
                    <input type="hidden" name="action" value="update_type">
                    <input type="hidden" name="customer_name" id="singleName">
                    <input type="hidden" name="customer_type" id="singleType">
                </form>
            </div>
        </main>
    </div>

    <?php require_once 'includes/layout_js.php'; ?>
    <script>
        function refreshSelection() {
            var checks = document.querySelectorAll('.row-check');
            var count = 0;
            for (var i = 0; i < checks.length; i++) {
                if (checks[i].checked) count++;
            }
            document.getElementById('countDisplay').innerText = count + ' items selected';
            document.getElementById('submitBtn').disabled = (count === 0);
        }

        function toggleMaster(master) {
            var checks = document.querySelectorAll('.row-check');
            for (var i = 0; i < checks.length; i++) {
                checks[i].checked = master.checked;
            }
            refreshSelection();
        }

        function doFlip(name, type) {
            document.getElementById('singleName').value = name;
            document.getElementById('singleType').value = type;
            document.getElementById('singleForm').submit();
        }
    </script>
</body>
</html>
