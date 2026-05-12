<?php
require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/Auth.php';

$db = new Database(DATABASE_PATH);
$auth = new Auth($db);
$auth->requireAccounts(); // Admin or Accounts

$user = $auth->getCurrentUser();
$currency = $db->getSetting('currency_symbol', '$');

$message = '';
$error = '';

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

$customers = $db->getCustomerProfiles();
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
    <link rel="stylesheet" href="layout.css?v=1.0.2">
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

        .bulk-actions-bar {
            background: var(--primary);
            color: white;
            padding: 12px 20px;
            border-radius: var(--radius-lg);
            margin-bottom: 20px;
            display: none; /* Shown via JS */
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--shadow-md);
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from { transform: translateY(-10px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .sortable { cursor: pointer; position: relative; }
        .sortable:hover { background-color: rgba(0,0,0,0.02); }
        .sortable::after {
            content: '↕';
            font-size: 10px;
            margin-left: 5px;
            opacity: 0.3;
        }

        .checkbox-cell { width: 40px; text-align: center; }
        .checkbox-cell input { cursor: pointer; }

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
            <div class="message success"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Bulk Actions Bar -->
        <form id="bulkForm" method="POST">
            <input type="hidden" name="action" value="bulk_update">
            <div id="bulkActions" class="bulk-actions-bar">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <span id="selectionCount" style="font-weight: 700;">0 selected</span>
                    <div style="height: 20px; width: 1px; background: rgba(255,255,255,0.3);"></div>
                    <select name="bulk_type" class="type-select" style="border: none;">
                        <option value="">Select action...</option>
                        <option value="Partner">Mark as Partner</option>
                        <option value="End Customer">Mark as End Customer</option>
                    </select>
                    <button type="submit" class="btn" style="background: white; color: var(--primary); border: none; padding: 6px 15px;">Apply Bulk Action</button>
                </div>
                <button type="button" onclick="clearSelection()" style="background: none; border: none; color: white; cursor: pointer; font-size: 12px; opacity: 0.8;">Cancel Selection</button>
            </div>

            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <i class="icon-search" style="color: var(--text-muted);"></i>
                        <input type="text" id="customerSearch" class="form-control" style="width: 300px;" placeholder="Filter by name..." onkeyup="filterCustomers()">
                    </div>
                    <div style="font-size: 13px; color: var(--text-muted);">
                        Total Customers: <strong><?php echo count($customers); ?></strong>
                    </div>
                </div>
                
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
                        <?php foreach ($customers as $c): ?>
                        <tr>
                            <td class="checkbox-cell">
                                <input type="checkbox" name="customer_names[]" value="<?php echo htmlspecialchars($c['customer_name']); ?>" onchange="updateSelection()">
                            </td>
                            <td><strong><?php echo htmlspecialchars($c['customer_name']); ?></strong></td>
                            <td>
                                <span class="badge <?php echo $c['customer_type'] === 'Partner' ? 'badge-partner' : 'badge-end'; ?>">
                                    <?php echo htmlspecialchars($c['customer_type']); ?>
                                </span>
                            </td>
                            <td style="text-align: right; font-weight: 600;"><?php echo $c['lifetime_invoices']; ?></td>
                            <td style="text-align: right; font-weight: 700; color: var(--primary);">
                                <?php echo htmlspecialchars($currency); ?><?php echo number_format($c['lifetime_revenue'] ?? 0, 0); ?>
                            </td>
                            <td style="text-align: center;">
                                <?php if ($c['is_verified']): ?>
                                    <span class="badge badge-verified" title="Checked"><i class="icon-check" style="font-size: 10px; margin-right: 4px;"></i> Verified</span>
                                <?php else: ?>
                                    <span class="badge badge-unverified">Unverified</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="update_type">
                                    <input type="hidden" name="customer_name" value="<?php echo htmlspecialchars($c['customer_name']); ?>">
                                    <select name="customer_type" class="type-select" onchange="this.form.submit()">
                                        <option value="End Customer" <?php echo $c['customer_type'] === 'End Customer' ? 'selected' : ''; ?>>End Customer</option>
                                        <option value="Partner" <?php echo $c['customer_type'] === 'Partner' ? 'selected' : ''; ?>>Partner</option>
                                    </select>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
            </div><!-- .content-body -->
        </main><!-- .main-wrapper -->
    </div><!-- .app-container -->

    <?php require_once 'includes/layout_js.php'; ?>
    <script>
        function filterCustomers() {
            const input = document.getElementById('customerSearch');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('customerTable');
            const tr = table.getElementsByTagName('tr');
            for (let i = 1; i < tr.length; i++) {
                const td = tr[i].getElementsByTagName('td')[1]; // Name is in index 1 now
                if (td) {
                    const txtValue = td.textContent || td.innerText;
                    tr[i].style.display = txtValue.toLowerCase().indexOf(filter) > -1 ? "" : "none";
                }
            }
        }

        function toggleAll(source) {
            const checkboxes = document.getElementsByName('customer_names[]');
            for (let i = 0; i < checkboxes.length; i++) {
                if (checkboxes[i].parentElement.parentElement.style.display !== 'none') {
                    checkboxes[i].checked = source.checked;
                }
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
            document.getElementById('selectAll').checked = false;
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
                    
                    let valX = x.textContent.toLowerCase().replace(/[^a-z0-9.]/g, '');
                    let valY = y.textContent.toLowerCase().replace(/[^a-z0-9.]/g, '');
                    
                    // Numeric check
                    if (!isNaN(parseFloat(valX)) && !isNaN(parseFloat(valY))) {
                        valX = parseFloat(valX);
                        valY = parseFloat(valY);
                    }

                    if (dir == "asc") {
                        if (valX > valY) {
                            shouldSwitch = true;
                            break;
                        }
                    } else if (dir == "desc") {
                        if (valX < valY) {
                            shouldSwitch = true;
                            break;
                        }
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
