<?php
require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/Auth.php';

$db = new Database(DATABASE_PATH);
$db->initialize(); // Ensure schema is sync'd

$auth = new Auth($db);
$auth->requireAccounts(); 

$user = $auth->getCurrentUser();
$currency = $db->getSetting('currency_symbol', '$');

$message = '';
$error = '';

// Search and Pagination
$search = $_GET['search'] ?? '';
$itemsPerPage = 25;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
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

$totalCustomers = $db->countCustomers($search);
$totalPages = ceil($totalCustomers / $itemsPerPage);
$customers = $db->getCustomerProfiles($itemsPerPage, $offset, $search);

// Base URL for pagination links
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
    <link rel="stylesheet" href="layout.css?v=1.0.5">
    <style>
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge-partner { background: #e0f2fe; color: #0369a1; }
        .badge-end { background: #f3f4f6; color: #4b5563; }
        .badge-verified { background: #dcfce7; color: #15803d; }
        .badge-unverified { background: #fef2f2; color: #b91c1c; }

        .sortable { cursor: pointer; position: relative; }
        .sortable:hover { background-color: rgba(0,0,0,0.02); }
        .sortable::after {
            content: '↕';
            font-size: 10px;
            margin-left: 5px;
            opacity: 0.3;
        }

        .checkbox-cell { width: 40px; text-align: center; }
        .checkbox-cell input { width: 18px; height: 18px; cursor: pointer; }

        .type-select {
            padding: 6px 12px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            font-size: 13px;
            font-family: inherit;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php require_once 'includes/sidebar.php'; ?>

        <!-- Main Wrapper -->
        <main class="main-wrapper">
            <?php $searchPlaceholder = 'Search customers...'; require_once 'includes/header.php'; ?>

            <div class="content-body">

                <div class="page-header">
                    <div>
                        <h1 style="font-size: 28px; font-weight: 800; letter-spacing: -1px;">Customer Management</h1>
                        <p style="color: var(--text-muted);">Classify your customers as Partners or End Customers.</p>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="message success"><i class="icon-check-circle"></i> <?php echo $message; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="message error"><i class="icon-alert-circle"></i> <?php echo $error; ?></div>
                <?php endif; ?>

                <!-- Bulk Actions Bar -->
                <form id="bulkForm" method="POST">
                    <input type="hidden" name="action" value="bulk_update">
                    <div id="bulkActions" class="bulk-actions-bar">
                        <div style="display: flex; align-items: center; gap: 20px;">
                            <span class="selection-badge" id="selectionCount">0 selected</span>
                            <div style="height: 24px; width: 1px; background: rgba(255,255,255,0.2);"></div>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <label style="font-size: 13px; font-weight: 600;">Update Type:</label>
                                <select name="bulk_type" class="type-select" style="min-width: 160px; border: none; background: white;">
                                    <option value="">Choose classification...</option>
                                    <option value="Partner">Mark as Partner</option>
                                    <option value="End Customer">Mark as End Customer</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary" style="background: white; color: var(--primary); border: none; box-shadow: none;">
                                <i class="icon-save" style="font-size: 14px;"></i> Update Selection
                            </button>
                        </div>
                        <button type="button" onclick="clearSelection()" class="btn-outline" style="color: white; border-color: rgba(255,255,255,0.4); padding: 5px 12px; font-size: 12px; height: 32px;">
                            Cancel
                        </button>
                    </div>

                    <div class="card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <form method="GET" style="display: flex; gap: 10px;" id="searchForm">
                                    <div style="position: relative;">
                                        <i class="icon-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 14px;"></i>
                                        <input type="text" name="search" id="customerSearch" class="form-control" style="width: 320px; padding-left: 38px;" 
                                               placeholder="Search across all records..." value="<?php echo htmlspecialchars($search); ?>">
                                    </div>
                                    <button type="submit" class="btn btn-outline">Search</button>
                                    <?php if ($search): ?>
                                        <a href="customers.php" class="btn btn-outline" style="color: var(--danger);"><i class="icon-x"></i></a>
                                    <?php endif; ?>
                                </form>
                            </div>
                            <div style="display: flex; align-items: center; gap: 15px; font-size: 13px; color: var(--text-muted);">
                                <span style="background: var(--bg-main); padding: 4px 12px; border-radius: 20px;">
                                    Page <strong><?php echo $currentPage; ?></strong> of <?php echo max(1, $totalPages); ?>
                                </span>
                                <span>Found: <strong><?php echo $totalCustomers; ?></strong> records</span>
                            </div>
                        </div>
                        
                        <div style="overflow-x: auto;">
                            <table class="table" id="customerTable">
                                <thead>
                                    <tr>
                                        <th class="checkbox-cell"><input type="checkbox" id="selectAll" onclick="toggleAll(this)"></th>
                                        <th class="sortable" onclick="sortTable(1)">Customer Name</th>
                                        <th class="sortable" onclick="sortTable(2)">Type</th>
                                        <th class="sortable" onclick="sortTable(3)" style="text-align: right;">Invoices</th>
                                        <th class="sortable" onclick="sortTable(4)" style="text-align: right;">Lifetime Rev</th>
                                        <th class="sortable" onclick="sortTable(5)" style="text-align: center;">Status</th>
                                        <th style="text-align: right; width: 180px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($customers)): ?>
                                    <tr><td colspan="7" style="text-align: center; padding: 60px; color: var(--text-muted);">
                                        <i class="icon-info" style="font-size: 24px; margin-bottom: 10px; display: block;"></i>
                                        No customers matching your criteria.
                                    </td></tr>
                                    <?php endif; ?>
                                    
                                    <?php foreach ($customers as $c): ?>
                                    <tr>
                                        <td class="checkbox-cell">
                                            <input type="checkbox" name="customer_names[]" value="<?php echo htmlspecialchars($c['customer_name']); ?>" onchange="updateSelection()">
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($c['customer_name']); ?></strong></td>
                                        <td>
                                            <span class="badge <?php echo ($c['customer_type'] ?? '') === 'Partner' ? 'badge-partner' : 'badge-end'; ?>">
                                                <?php echo htmlspecialchars($c['customer_type'] ?? 'End Customer'); ?>
                                            </span>
                                        </td>
                                        <td style="text-align: right; font-weight: 600;"><?php echo $c['lifetime_invoices']; ?></td>
                                        <td style="text-align: right; font-weight: 700; color: var(--primary);">
                                            <?php echo htmlspecialchars($currency); ?><?php echo number_format($c['lifetime_revenue'] ?? 0, 0); ?>
                                        </td>
                                        <td style="text-align: center;">
                                            <?php if (isset($c['is_verified']) && $c['is_verified']): ?>
                                                <span class="badge badge-verified" title="Audited"><i class="icon-check" style="font-size: 10px; margin-right: 4px;"></i> Verified</span>
                                            <?php else: ?>
                                                <span class="badge badge-unverified">Unverified</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: right;">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="update_type">
                                                <input type="hidden" name="customer_name" value="<?php echo htmlspecialchars($c['customer_name']); ?>">
                                                <select name="customer_type" class="type-select" onchange="this.form.submit()">
                                                    <option value="End Customer" <?php echo ($c['customer_type'] ?? '') === 'End Customer' ? 'selected' : ''; ?>>End Customer</option>
                                                    <option value="Partner" <?php echo ($c['customer_type'] ?? '') === 'Partner' ? 'selected' : ''; ?>>Partner</option>
                                                </select>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination Footer -->
                        <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <a href="<?php echo $basePageUrl . max(1, $currentPage - 1); ?>" class="pagination-link <?php echo $currentPage <= 1 ? 'disabled' : ''; ?>" title="Previous Page">
                                <i class="icon-chevron-left"></i>
                            </a>
                            
                            <?php 
                            $startPage = max(1, $currentPage - 2);
                            $endPage = min($totalPages, $currentPage + 2);
                            
                            if ($startPage > 1): ?>
                                <a href="<?php echo $basePageUrl; ?>1" class="pagination-link">1</a>
                                <?php if ($startPage > 2): ?><span style="color: var(--text-muted);">...</span><?php endif; ?>
                            <?php endif; ?>

                            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <a href="<?php echo $basePageUrl . $i; ?>" class="pagination-link <?php echo $i == $currentPage ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($endPage < $totalPages): ?>
                                <?php if ($endPage < $totalPages - 1): ?><span style="color: var(--text-muted);">...</span><?php endif; ?>
                                <a href="<?php echo $basePageUrl . $totalPages; ?>" class="pagination-link"><?php echo $totalPages; ?></a>
                            <?php endif; ?>

                            <a href="<?php echo $basePageUrl . min($totalPages, $currentPage + 1); ?>" class="pagination-link <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>" title="Next Page">
                                <i class="icon-chevron-right"></i>
                            </a>
                        </div>
                        <?php elseif ($totalCustomers > 0): ?>
                        <div style="text-align: center; margin-top: 30px; color: var(--text-muted); font-size: 13px;">
                            <?php echo $search ? 'Showing all search results' : 'Showing all records'; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </form>
            </div><!-- .content-body -->
        </main><!-- .main-wrapper -->
    </div><!-- .app-container -->

    <?php require_once 'includes/layout_js.php'; ?>
    <script>
        // No client-side filtering needed with server-side search, but kept for instantaneous feedback on current page
        function filterCustomers() {
            // Optional: debounce server-side search or just keep as is
        }

        function toggleAll(source) {
            const checkboxes = document.getElementsByName('customer_names[]');
            for (let i = 0; i < checkboxes.length; i++) {
                checkboxes[i].checked = source.checked;
            }
            updateSelection();
        }

        function updateSelection() {
            const checkboxes = document.getElementsByName('customer_names[]');
            let count = 0;
            for (let i = 0; i < checkboxes.length; i++) {
                if (checkboxes[i].checked) count++;
            }

            const bar = document.getElementById('bulkActions');
            const countLabel = document.getElementById('selectionCount');
            
            if (count > 0) {
                bar.style.display = 'flex';
                countLabel.innerText = count + ' selected';
            } else {
                bar.style.display = 'none';
            }
        }

        function clearSelection() {
            const checkboxes = document.getElementsByName('customer_names[]');
            for (let i = 0; i < checkboxes.length; i++) {
                checkboxes[i].checked = false;
            }
            const selectAll = document.getElementById('selectAll');
            if (selectAll) selectAll.checked = false;
            updateSelection();
        }

        function sortTable(n) {
            const table = document.getElementById("customerTable");
            let rows, switching, i, x, y, shouldSwitch, dir, switchcount = 0;
            switching = true;
            dir = "asc"; 
            while (switching) {
                switching = false;
                rows = table.rows;
                for (i = 1; i < (rows.length - 1); i++) {
                    shouldSwitch = false;
                    x = rows[i].getElementsByTagName("TD")[n];
                    y = rows[i + 1].getElementsByTagName("TD")[n];
                    if (!x || !y) continue;

                    let valX = x.textContent.toLowerCase().replace(/[^a-z0-9.]/g, '');
                    let valY = y.textContent.toLowerCase().replace(/[^a-z0-9.]/g, '');
                    
                    if (!isNaN(parseFloat(valX)) && !isNaN(parseFloat(valY))) {
                        valX = parseFloat(valX);
                        valY = parseFloat(valY);
                    }

                    if (dir == "asc") {
                        if (valX > valY) { shouldSwitch = true; break; }
                    } else if (dir == "desc") {
                        if (valX < valY) { shouldSwitch = true; break; }
                    }
                }
                if (shouldSwitch) {
                    rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
                    switching = true;
                    switchcount ++;      
                } else {
                    if (switchcount == 0 && dir == "asc") {
                        dir = "desc";
                        switching = true;
                    }
                }
            }
        }
    </script>
</body>
</html>
