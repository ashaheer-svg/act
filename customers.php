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

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_type') {
    $name = $_POST['customer_name'] ?? '';
    $type = $_POST['customer_type'] ?? 'End Customer';
    
    if ($db->updateCustomerType($name, $type)) {
        $message = "Customer '$name' updated to $type.";
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
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <input type="text" id="customerSearch" class="form-control" style="width: 300px;" placeholder="Search customers..." onkeyup="filterCustomers()">
            </div>
            
            <table class="table" id="customerTable">
                <thead>
                    <tr>
                        <th>Customer Name</th>
                        <th>Type</th>
                        <th style="text-align: right;">Invoices</th>
                        <th style="text-align: right;">Lifetime Rev</th>
                        <th style="text-align: right; width: 200px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $c): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($c['customer_name']); ?></td>
                        <td>
                            <span class="badge <?php echo $c['customer_type'] === 'Partner' ? 'badge-partner' : 'badge-end'; ?>">
                                <?php echo htmlspecialchars($c['customer_type']); ?>
                            </span>
                        </td>
                        <td style="text-align: right; font-weight: 600;"><?php echo $c['lifetime_invoices']; ?></td>
                        <td style="text-align: right; font-weight: 700; color: var(--primary);">
                            <?php echo htmlspecialchars($currency); ?><?php echo number_format($c['lifetime_revenue'] ?? 0, 0); ?>
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
                const td = tr[i].getElementsByTagName('td')[0];
                if (td) {
                    const txtValue = td.textContent || td.innerText;
                    tr[i].style.display = txtValue.toLowerCase().indexOf(filter) > -1 ? "" : "none";
                }
            }
        }
    </script>
</body>
</html>
