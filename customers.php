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
$currency = $db->getSetting('currency_symbol', 'LKR ');

$message = '';
$error = '';

// Search, Sort and Pagination
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'lifetime_revenue';
$dir = $_GET['dir'] ?? 'DESC';
$itemsPerPage = 25;

$totalCustomers = $db->countCustomers($search);
$totalPages = ceil($totalCustomers / $itemsPerPage);

$pageNum = isset($_GET['page']) ? max(1, min((int)$_GET['page'], max(1, $totalPages))) : 1;
$offset = ($pageNum - 1) * $itemsPerPage;

// Handle Updates
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'bulk_update') {
        $names = $_POST['customer_names'] ?? [];
        $type = $_POST['bulk_type'] ?? '';
        if (!empty($names) && !empty($type)) {
            $db->bulkUpdateCustomerProfiles($names, $type);
            $message = count($names) . " customers updated.";
        }
    } elseif ($_POST['action'] === 'update_type') {
        $db->updateCustomerType($_POST['customer_name'], $_POST['customer_type']);
        $message = "Customer updated.";
    }
}

$customers = $db->getCustomerProfiles($itemsPerPage, $offset, $search, $sort, $dir);

// Pagination & Sort URL Helpers
$queryParams = $_GET;
function getSortUrl($col, $currentSort, $currentDir, $params) {
    $params['sort'] = $col;
    $params['dir'] = ($currentSort === $col && $currentDir === 'ASC') ? 'DESC' : 'ASC';
    $params['page'] = 1; // Reset to page 1 on sort
    return '?' . http_build_query($params);
}

function getPageUrl($p, $params) {
    $params['page'] = $p;
    return '?' . http_build_query($params);
}

function sortIcon($col, $currentSort, $currentDir) {
    if ($currentSort !== $col) return '<i class="icon-chevrons-up-down" style="font-size: 10px; opacity: 0.3;"></i>';
    return $currentDir === 'ASC' ? '<i class="icon-chevron-up" style="color: var(--primary);"></i>' : '<i class="icon-chevron-down" style="color: var(--primary);"></i>';
}
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
    <link rel="stylesheet" href="layout.css?v=1.1.1">
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
        
        .sort-header { cursor: pointer; color: inherit; text-decoration: none; display: flex; align-items: center; gap: 8px; justify-content: inherit; width: 100%; }
        .sort-header:hover { color: var(--primary); }
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

                    <div class="card" style="padding: 0; overflow: hidden;">
                        <table class="table" style="margin: 0;">
                            <thead style="background: #f8fafc;">
                                <tr>
                                    <th class="checkbox-cell"><input type="checkbox" id="masterBox" onchange="toggleMaster(this)"></th>
                                    <th>
                                        <a href="<?= getSortUrl('customer_name', $sort, $dir, $queryParams) ?>" class="sort-header">
                                            Customer Name <?= sortIcon('customer_name', $sort, $dir) ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="<?= getSortUrl('customer_type', $sort, $dir, $queryParams) ?>" class="sort-header">
                                            Type <?= sortIcon('customer_type', $sort, $dir) ?>
                                        </a>
                                    </th>
                                    <th style="text-align: right;">
                                        <a href="<?= getSortUrl('lifetime_invoices', $sort, $dir, $queryParams) ?>" class="sort-header" style="justify-content: flex-end;">
                                            Invoices <?= sortIcon('lifetime_invoices', $sort, $dir) ?>
                                        </a>
                                    </th>
                                    <th style="text-align: right;">
                                        <a href="<?= getSortUrl('lifetime_revenue', $sort, $dir, $queryParams) ?>" class="sort-header" style="justify-content: flex-end;">
                                            Revenue <?= sortIcon('lifetime_revenue', $sort, $dir) ?>
                                        </a>
                                    </th>
                                    <th style="text-align: center;">
                                        <a href="<?= getSortUrl('is_verified', $sort, $dir, $queryParams) ?>" class="sort-header" style="justify-content: center;">
                                            Verified <?= sortIcon('is_verified', $sort, $dir) ?>
                                        </a>
                                    </th>
                                    <th style="text-align: right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($customers as $c): ?>
                                <tr>
                                    <td class="checkbox-cell"><input type="checkbox" name="customer_names[]" value="<?php echo htmlspecialchars($c['customer_name']); ?>" class="row-check" onchange="refreshSelection()"></td>
                                    <td style="font-weight: 700;">
                                        <div><?php echo htmlspecialchars($c['customer_name']); ?></div>
                                        <?php if (!empty($c['company_name']) && $c['company_name'] !== $c['customer_name']): ?>
                                            <div style="font-size: 11px; font-weight: 500; color: var(--text-muted);"><?php echo htmlspecialchars($c['company_name']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($c['phone']) || !empty($c['email'])): ?>
                                            <div style="font-size: 11px; font-weight: 400; color: var(--text-muted); margin-top: 2px;">
                                                <?php if (!empty($c['phone'])): ?><span title="Phone">📞 <?php echo htmlspecialchars($c['phone']); ?></span> <?php endif; ?>
                                                <?php if (!empty($c['email'])): ?><span title="Email" style="margin-left: 6px;">✉️ <?php echo htmlspecialchars($c['email']); ?></span><?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge <?php echo ($c['customer_type'] ?? '') === 'Partner' ? 'badge-partner' : 'badge-end'; ?>"><?php echo htmlspecialchars($c['customer_type'] ?? 'End Customer'); ?></span></td>
                                    <td style="text-align: right;"><?php echo $c['lifetime_invoices']; ?></td>
                                    <td style="text-align: right; font-weight: 700; color: var(--primary);"><?php echo $currency . number_format($c['lifetime_revenue'] ?? 0, 0); ?></td>
                                    <td style="text-align: center;">
                                        <?php if (isset($c['is_verified']) && $c['is_verified']): ?><i class="icon-check-circle" style="color: var(--success);"></i><?php else: ?><i class="icon-help-circle" style="color: var(--text-muted);"></i><?php endif; ?>
                                    </td>
                                    <td style="text-align: right;">
                                        <button type="button" class="btn btn-outline" style="padding: 5px 10px; font-size: 12px;" onclick="doFlip('<?php echo addslashes($c['customer_name']); ?>', '<?php echo ($c['customer_type'] ?? '') === 'Partner' ? 'End Customer' : 'Partner'; ?>')">Switch</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <?php if ($totalPages > 1): ?>
                        <div class="pager-container">
                            <a href="<?= getPageUrl(1, $queryParams) ?>" class="pager-link <?php echo $pageNum == 1 ? 'disabled' : ''; ?>">First</a>
                            <a href="<?= getPageUrl(max(1, $pageNum-1), $queryParams) ?>" class="pager-link <?php echo $pageNum <= 1 ? 'disabled' : ''; ?>">Previous</a>
                            <?php for($i=1; $i<=$totalPages; $i++): ?>
                                <?php if ($totalPages < 10 || ($i > $pageNum - 3 && $i < $pageNum + 3)): ?>
                                    <a href="<?= getPageUrl($i, $queryParams) ?>" class="pager-link <?php echo $i == $pageNum ? 'active' : ''; ?>"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            <a href="<?= getPageUrl(min($totalPages, $pageNum+1), $queryParams) ?>" class="pager-link <?php echo $pageNum >= $totalPages ? 'disabled' : ''; ?>">Next</a>
                            <a href="<?= getPageUrl($totalPages, $queryParams) ?>" class="pager-link <?php echo $pageNum == $totalPages ? 'disabled' : ''; ?>">Last</a>
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
            for (var i = 0; i < checks.length; i++) { if (checks[i].checked) count++; }
            document.getElementById('countDisplay').innerText = count + ' items selected';
            document.getElementById('submitBtn').disabled = (count === 0);
        }
        function toggleMaster(master) {
            var checks = document.querySelectorAll('.row-check');
            for (var i = 0; i < checks.length; i++) { checks[i].checked = master.checked; }
            refreshSelection();
        }
        function doFlip(name, type) {
            document.getElementById('singleName').value = name;
            document.getElementById('singleType').value = type;
            document.getElementById('singleForm').submit();
        }
        if (window.lucide) lucide.createIcons();
    </script>
</body>
</html>
