<?php
require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/Auth.php';

$db = new Database(DATABASE_PATH);
$db->initialize(); 
$db->syncCustomerProfiles(); // Ensure we have data

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

$currentPage = isset($_GET['page']) ? max(1, min((int)$_GET['page'], max(1, $totalPages))) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

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
    <link rel="stylesheet" href="layout.css?v=1.0.7">
    <style>
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .badge-partner { background: #e0f2fe; color: #0369a1; }
        .badge-end { background: #f3f4f6; color: #4b5563; }
        .badge-verified { background: #dcfce7; color: #15803d; }
        .badge-unverified { background: #fef2f2; color: #b91c1c; }

        .checkbox-cell { width: 45px; text-align: center; }
        .checkbox-cell input { width: 20px; height: 20px; cursor: pointer; }

        .bulk-controls {
            display: flex;
            align-items: center;
            gap: 12px;
            background: #f8fafc;
            padding: 12px 20px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            margin-bottom: 20px;
            opacity: 0.5;
            pointer-events: none;
            transition: all 0.3s;
        }
        .bulk-controls.active {
            opacity: 1;
            pointer-events: auto;
            border-color: var(--primary);
            background: #f0f9ff;
        }

        .pager-container {
            display: flex;
            justify-content: center;
            gap: 5px;
            padding: 20px 0;
        }
        .pager-btn {
            padding: 8px 16px;
            border-radius: 8px;
            background: white;
            border: 1px solid var(--border-color);
            color: var(--text-main);
            text-decoration: none;
            font-weight: 700;
            font-size: 13px;
        }
        .pager-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        .pager-btn.disabled {
            opacity: 0.3;
            pointer-events: none;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php require_once 'includes/sidebar.php'; ?>

        <main class="main-wrapper">
            <?php require_once 'includes/header.php'; ?>

            <div class="content-body">
                <div class="page-header">
                    <div>
                        <h1 style="font-size: 28px; font-weight: 800; letter-spacing: -1px;">Customer Management</h1>
                        <p style="color: var(--text-muted);">Total of <strong><?php echo $totalCustomers; ?></strong> unique customers found.</p>
                    </div>
                </div>

                <?php if ($message): ?><div class="message success"><?php echo $message; ?></div><?php endif; ?>
                <?php if ($error): ?><div class="message error"><?php echo $error; ?></div><?php endif; ?>

                <form method="POST" id="mainForm">
                    <input type="hidden" name="action" value="bulk_update">
                    
                    <!-- Search & Bulk Controls -->
                    <div class="card" style="margin-bottom: 20px;">
                        <div style="display: flex; flex-direction: column; gap: 20px;">
                            <!-- Top: Search -->
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div style="position: relative; width: 400px;">
                                    <i class="icon-search" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                                    <input type="text" name="search" class="form-control" style="padding-left: 45px; height: 48px;" 
                                           placeholder="Search by customer name..." value="<?php echo htmlspecialchars($search); ?>"
                                           onchange="this.form.method='GET'; this.form.submit();">
                                </div>
                                <div style="font-weight: 700; color: var(--primary);">
                                    Page <?php echo $currentPage; ?> of <?php echo $totalPages; ?>
                                </div>
                            </div>

                            <!-- Bottom: Bulk Action Bar -->
                            <div class="bulk-controls" id="bulkBar">
                                <div style="display: flex; align-items: center; gap: 15px; flex: 1;">
                                    <span style="font-size: 14px; font-weight: 800; color: var(--primary);" id="selCount">0 selected</span>
                                    <i class="icon-arrow-right" style="color: var(--text-muted);"></i>
                                    <select name="bulk_type" class="form-control" style="width: 200px; height: 38px;">
                                        <option value="">Choose action...</option>
                                        <option value="Partner">Mark as Partner</option>
                                        <option value="End Customer">Mark as End Customer</option>
                                    </select>
                                    <button type="submit" class="btn btn-primary" style="height: 38px;">Apply to Selected</button>
                                </div>
                                <button type="button" onclick="clearAll()" class="btn btn-outline" style="height: 38px;">Clear All</button>
                            </div>
                        </div>
                    </div>

                    <!-- Table & Pagination -->
                    <div class="card">
                        <table class="table" id="custTable">
                            <thead>
                                <tr>
                                    <th class="checkbox-cell"><input type="checkbox" id="masterCheck" onclick="doToggleAll(this)"></th>
                                    <th>Customer Name</th>
                                    <th>Classification</th>
                                    <th style="text-align: right;">Invoices</th>
                                    <th style="text-align: right;">Revenue</th>
                                    <th style="text-align: center;">Status</th>
                                    <th style="text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($customers as $c): ?>
                                <tr>
                                    <td class="checkbox-cell">
                                        <input type="checkbox" name="customer_names[]" value="<?php echo htmlspecialchars($c['customer_name']); ?>" onchange="doUpdateSelection()">
                                    </td>
                                    <td style="font-weight: 700;"><?php echo htmlspecialchars($c['customer_name']); ?></td>
                                    <td>
                                        <span class="badge <?php echo ($c['customer_type'] ?? '') === 'Partner' ? 'badge-partner' : 'badge-end'; ?>">
                                            <?php echo htmlspecialchars($c['customer_type'] ?? 'End Customer'); ?>
                                        </span>
                                    </td>
                                    <td style="text-align: right;"><?php echo $c['lifetime_invoices']; ?></td>
                                    <td style="text-align: right; font-weight: 700; color: var(--primary);">
                                        <?php echo $currency . number_format($c['lifetime_revenue'] ?? 0, 0); ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if (isset($c['is_verified']) && $c['is_verified']): ?>
                                            <span class="badge badge-verified">Verified</span>
                                        <?php else: ?>
                                            <span class="badge badge-unverified">Unverified</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: right;">
                                        <button type="button" class="btn btn-outline" style="padding: 4px 8px; font-size: 11px;" 
                                                onclick="setSingle('<?php echo addslashes($c['customer_name']); ?>', '<?php echo ($c['customer_type'] ?? '') === 'Partner' ? 'End Customer' : 'Partner'; ?>')">
                                            Switch to <?php echo ($c['customer_type'] ?? '') === 'Partner' ? 'End Customer' : 'Partner'; ?>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <!-- Big Navigation Buttons -->
                        <div class="pager-container">
                            <a href="<?php echo $basePageUrl; ?>1" class="pager-btn <?php echo $currentPage == 1 ? 'disabled' : ''; ?>">First</a>
                            <a href="<?php echo $basePageUrl . ($currentPage - 1); ?>" class="pager-btn <?php echo $currentPage <= 1 ? 'disabled' : ''; ?>">Previous</a>
                            
                            <div style="display: flex; gap: 5px; margin: 0 10px;">
                                <?php for($i=1; $i<=$totalPages; $i++): ?>
                                    <?php if ($totalPages < 10 || ($i > $currentPage - 3 && $i < $currentPage + 3)): ?>
                                        <a href="<?php echo $basePageUrl . $i; ?>" class="pager-btn <?php echo $i == $currentPage ? 'active' : ''; ?>"><?php echo $i; ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </div>

                            <a href="<?php echo $basePageUrl . ($currentPage + 1); ?>" class="pager-btn <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>">Next</a>
                            <a href="<?php echo $basePageUrl . $totalPages; ?>" class="pager-btn <?php echo $currentPage == $totalPages ? 'disabled' : ''; ?>">Last (<?php echo $totalPages; ?>)</a>
                        </div>
                    </div>
                </form>

                <!-- Hidden Single Action Form -->
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
        function doToggleAll(source) {
            const checkboxes = document.getElementsByName('customer_names[]');
            checkboxes.forEach(c => c.checked = source.checked);
            doUpdateSelection();
        }

        function doUpdateSelection() {
            const checkboxes = document.getElementsByName('customer_names[]');
            let count = 0;
            checkboxes.forEach(c => { if(c.checked) count++; });

            const bar = document.getElementById('bulkBar');
            const countLabel = document.getElementById('selCount');
            
            if (count > 0) {
                bar.classList.add('active');
                countLabel.innerText = count + ' selected';
            } else {
                bar.classList.remove('active');
                countLabel.innerText = '0 selected';
            }
        }

        function clearAll() {
            document.getElementById('masterCheck').checked = false;
            doToggleAll(document.getElementById('masterCheck'));
        }

        function setSingle(name, type) {
            document.getElementById('singleName').value = name;
            document.getElementById('singleType').value = type;
            document.getElementById('singleForm').submit();
        }
    </script>
</body>
</html>
