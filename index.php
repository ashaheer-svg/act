<?php
require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/Auth.php';
require_once 'classes/Reports.php';

$db = new Database(DATABASE_PATH);
$auth = new Auth($db);
$auth->requireLogin();
$reports = new Reports($db);

$user = $auth->getCurrentUser();

// Load dynamic settings
$currency = $db->getSetting('currency_symbol', '$');
$vatRate = $db->getSetting('vat_rate', '0.18');

// Get date range for reports
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');
$reportType = $_GET['report'] ?? 'dashboard';

$dateFrom = "$year-$month-01";
$dateTo = date('Y-m-t', strtotime($dateFrom));

// Get dashboard data with error handling
try {
    $summary = $reports->getDashboardSummary($dateFrom, $dateTo);
    $topCustomers = $reports->getTopCustomers(5, $dateFrom, $dateTo);
    $topProducts = $reports->getTopProducts(5, $dateFrom, $dateTo);
    $categoryAnalysis = $reports->getSalesByCategory($dateFrom, $dateTo);
    $concentration = $reports->getCustomerConcentration($dateFrom, $dateTo);
    $vatSummary = $reports->getVATSummary($dateFrom, $dateTo);
} catch (Exception $e) {
    error_log("Dashboard Error: " . $e->getMessage());
    $error = "Unable to load dashboard data: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Activity</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="docs/lucide-font/lucide.css">
    <link rel="stylesheet" href="layout.css?v=1.0.1">
</head>
<body>
    <div class="app-container">
        <?php require_once 'includes/sidebar.php'; ?>

        <!-- Main Wrapper -->
        <main class="main-wrapper">
            <?php $searchPlaceholder = 'Search analytics...'; require_once 'includes/header.php'; ?>

            <div class="content-body">
                <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 30px;">
                    <div>
                        <h1 style="font-size: 32px; font-weight: 800; letter-spacing: -1px; margin-bottom: 8px;">Overview Dashboard</h1>
                        <p style="color: var(--text-muted); font-size: 16px;">Sales and VAT performance metrics for <?php echo date('F Y', strtotime("$year-$month-01")); ?></p>
                    </div>
                    
                    <div style="display: flex; gap: 12px; background: white; padding: 6px; border-radius: var(--radius-lg); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
                        <select id="yearSelect" class="custom-select" style="border: none; background: none; width: auto;" onchange="applyFilters()">
                            <option value="2024" <?php echo $year == '2024' ? 'selected' : ''; ?>>2024</option>
                            <option value="2025" <?php echo $year == '2025' ? 'selected' : ''; ?>>2025</option>
                            <option value="2026" <?php echo $year == '2026' ? 'selected' : ''; ?>>2026</option>
                        </select>
                        <select id="monthSelect" class="custom-select" style="border: none; background: none; width: auto;" onchange="applyFilters()">
                            <?php for($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?>" <?php echo $month == str_pad($m, 2, '0', STR_PAD_LEFT) ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <div class="dashboard-grid">
                    <?php if (($summary['total_invoices'] ?? 0) == 0): ?>
                    <div class="card" style="grid-column: 1 / -1; text-align: center; padding: 80px 20px; border: 2px dashed var(--border-color); background: #fcfdfe;">
                        <div style="font-size: 60px; margin-bottom: 20px;">📂</div>
                        <h2 style="margin-bottom: 12px;">No sales data available yet</h2>
                        <p style="color: var(--text-muted); margin-bottom: 30px; font-size: 16px;">Start by uploading your first sales CSV file to see the analytics.</p>
                        <a href="upload.php" class="btn-primary" style="text-decoration: none; display: inline-block; width: auto; padding: 14px 40px;">Upload Your First File</a>
                    </div>
                    <?php else: ?>
                    <div class="card">
                        <div class="card-label">Total Revenue (Base)</div>
                        <div class="card-value" style="color: var(--sidebar-active);"><?php echo htmlspecialchars($currency); ?><?php echo number_format($summary['total_revenue_base'] ?? 0, 0); ?></div>
                        <div class="card-footer"><span>📄</span> <?php echo $summary['total_invoices'] ?? 0; ?> invoices</div>
                    </div>

                    <div class="card">
                        <div class="card-label">Total After VAT</div>
                        <div class="card-value"><?php echo htmlspecialchars($currency); ?><?php echo number_format($summary['total_amount'] ?? 0, 0); ?></div>
                        <div class="card-footer">Revenue to collect</div>
                    </div>

                    <div class="card" style="background: #f0fdf4; border-color: #bbf7d0;">
                        <div class="card-label" style="color: #166534;">VAT Collected</div>
                        <div class="card-value" style="color: #15803d;"><?php echo htmlspecialchars($currency); ?><?php echo number_format($summary['total_vat'] ?? 0, 0); ?></div>
                        <div class="card-footer" style="color: #166534;">To be remitted</div>
                    </div>

                    <div class="card" style="background: #fffbeb; border-color: #fde68a;">
                        <div class="card-label" style="color: #92400e;">Total Payments</div>
                        <div class="card-value" style="color: #b45309;"><?php echo htmlspecialchars($currency); ?><?php echo number_format($summary['total_payments_received'] ?? 0, 0); ?></div>
                        <div class="card-footer" style="color: #d97706;">Historical Collection</div>
                    </div>

                    <div class="card" style="background: #fef2f2; border-color: #fecaca;">
                        <div class="card-label" style="color: #991b1b;">Total Outstanding</div>
                        <div class="card-value" style="color: #dc2626;"><?php echo htmlspecialchars($currency); ?><?php echo number_format($summary['total_outstanding'] ?? 0, 0); ?></div>
                        <div class="card-footer" style="color: #ef4444;">Accounts Receivable</div>
                    </div>

                    <div class="card">
                        <div class="card-label">Unique Customers</div>
                        <div class="card-value"><?php echo $summary['unique_customers'] ?? 0; ?></div>
                        <div class="card-footer">Customer base active</div>
                    </div>

                    <div class="card" style="grid-column: span 2;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h4 style="font-weight: 700;">Top 5 Customers</h4>
                            <span style="font-size: 12px; color: var(--text-muted);">By Revenue</span>
                        </div>
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="text-align: left; border-bottom: 1px solid var(--border-color);">
                                        <th style="padding: 12px 8px; font-size: 12px; color: var(--text-muted);">Customer</th>
                                        <th style="padding: 12px 8px; font-size: 12px; color: var(--text-muted);">Orders</th>
                                        <th style="padding: 12px 8px; font-size: 12px; color: var(--text-muted); text-align: right;">Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($topCustomers as $customer): ?>
                                    <tr style="border-bottom: 1px solid #f8fafc;">
                                        <td style="padding: 12px 8px; font-size: 14px; font-weight: 600;"><?php echo htmlspecialchars(substr($customer['customer_name'], 0, 35)); ?></td>
                                        <td style="padding: 12px 8px; font-size: 14px;"><?php echo $customer['invoice_count']; ?></td>
                                        <td style="padding: 12px 8px; font-size: 14px; text-align: right; font-weight: 700; color: var(--sidebar-active);">
                                            <?php echo htmlspecialchars($currency); ?><?php echo number_format($customer['total_revenue'], 0); ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-label">Top Category</div>
                        <div class="card-value" style="font-size: 24px;"><?php echo !empty($categoryAnalysis) ? htmlspecialchars($categoryAnalysis[0]['category']) : 'N/A'; ?></div>
                        <div class="card-footer">Largest sales share</div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <?php require_once 'includes/layout_js.php'; ?>
    <script>
        function applyFilters() {
            const year = document.getElementById('yearSelect').value;
            const month = document.getElementById('monthSelect').value;
            window.location.href = `index.php?year=${year}&month=${month}`;
        }
    </script>
</body>
</html>

