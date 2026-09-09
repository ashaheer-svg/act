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
$currency = $db->getSetting('currency_symbol', 'LKR ');
$vatRate = $db->getSetting('vat_rate', '0.18');

// Get date range for reports
$availableYears = $reports->getAvailableYears();
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? (!empty($availableYears) ? $availableYears[0] : date('Y'));
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
    <link rel="stylesheet" href="layout.css?v=1.0.2">
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
                            <?php foreach ($availableYears as $y): ?>
                                <option value="<?php echo htmlspecialchars($y); ?>" <?php echo $year == $y ? 'selected' : ''; ?>><?php echo htmlspecialchars($y); ?></option>
                            <?php endforeach; ?>
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

                <div class="dashboard-grid" id="dashboardGrid">
                    <?php if (($summary['total_invoices'] ?? 0) == 0): ?>
                    <div class="card" style="grid-column: 1 / -1; text-align: center; padding: 80px 20px; border: 2px dashed var(--border-color); background: #fcfdfe;">
                        <div style="font-size: 60px; margin-bottom: 20px;">📂</div>
                        <h2 style="margin-bottom: 12px;">No sales data available yet</h2>
                        <p style="color: var(--text-muted); margin-bottom: 30px; font-size: 16px;">Start by uploading your first sales CSV file to see the analytics.</p>
                        <a href="upload.php" class="btn-primary" style="text-decoration: none; display: inline-block; width: auto; padding: 14px 40px;">Upload Your First File</a>
                    </div>
                    <?php else: ?>
                    <!-- Executive Financial Methodology & Standards Guide -->
                    <div class="methodology-card" style="grid-column: 1 / -1; margin-bottom: 8px;">
                        <div class="methodology-header" onclick="toggleIndexMethodology()" title="Click to view or hide calculation logic & standards">
                            <div class="methodology-header-title">
                                <i class="icon-calculator" style="color: var(--sidebar-active);"></i>
                                <span>Executive Metric Standards & Financial Calculation Guide</span>
                                <span class="methodology-badge">LKR Standard • IRD RAMIS Tax Model</span>
                            </div>
                            <div class="methodology-action">
                                <span class="methodology-toggle-text" id="indexMethodologyText">Show Logic & Formulas</span>
                                <span class="methodology-toggle-icon" id="indexMethodologyIcon">▶</span>
                            </div>
                        </div>
                        <div class="methodology-body collapsed" id="indexMethodologyBody">
                            <div class="methodology-grid">
                                <div class="methodology-col">
                                    <div class="methodology-col-label usage"><i class="icon-target"></i> Business Usage</div>
                                    <p>Provides C-suite and finance directors with an authoritative view of commercial turnover, statutory tax commitments, and cash realization velocity across all active customer accounts in Sri Lanka Rupees (LKR).</p>
                                </div>
                                <div class="methodology-col">
                                    <div class="methodology-col-label validity"><i class="icon-shield-check"></i> Scope & Validity</div>
                                    <p>Reconciled against QuickBooks commercial billing from 2021 to present. 0-value placeholder rows and header descriptions are filtered out; valid serialized warranty replacements and physical dispatches are preserved.</p>
                                </div>
                                <div class="methodology-col">
                                    <div class="methodology-col-label calc"><i class="icon-calculator"></i> Calculation Method & Formulas</div>
                                    <p>• <strong>Net Base:</strong> <span class="methodology-formula">Σ base_value = total_amount / 1.18</span></p>
                                    <p>• <strong>Gross Billed:</strong> <span class="methodology-formula">Σ total_amount</span> (Inc. 18% VAT)</p>
                                    <p>• <strong>Outstanding AR:</strong> <span class="methodology-formula">Gross Billed - Total Payments</span></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card" title="Formula: Σ base_value = total_amount / 1.18 (Excl. 18% VAT)">
                        <div class="card-label">Total Revenue (Base)</div>
                        <div class="card-value" style="color: var(--sidebar-active);"><?php echo htmlspecialchars($currency); ?><?php echo number_format($summary['total_revenue_base'] ?? 0, 0); ?></div>
                        <div class="kpi-formula-sub"><code>Formula:</code> Σ base_value (Excl. 18% VAT)</div>
                        <div class="card-footer" style="margin-top: 8px;"><span>📄</span> <?php echo $summary['total_invoices'] ?? 0; ?> valid billing lines</div>
                    </div>

                    <div class="card" title="Formula: Σ total_amount (Total Legal Commercial Claim)">
                        <div class="card-label">Total After VAT (Gross)</div>
                        <div class="card-value"><?php echo htmlspecialchars($currency); ?><?php echo number_format($summary['total_amount'] ?? 0, 0); ?></div>
                        <div class="kpi-formula-sub"><code>Formula:</code> Σ total_amount (Legal Receivable)</div>
                        <div class="card-footer" style="margin-top: 8px;">Full commercial claim</div>
                    </div>

                    <div class="card" style="background: #f0fdf4; border-color: #bbf7d0;" title="Formula: Gross Billed - Base Net Revenue">
                        <div class="card-label" style="color: #166534;">VAT Collected (18%)</div>
                        <div class="card-value" style="color: #15803d;"><?php echo htmlspecialchars($currency); ?><?php echo number_format($summary['total_vat'] ?? 0, 0); ?></div>
                        <div class="kpi-formula-sub" style="color: #166534;"><code>Formula:</code> Gross Billed - Base Net</div>
                        <div class="card-footer" style="color: #166534; margin-top: 8px;">Statutory IRD liability</div>
                    </div>

                    <div class="card" style="background: #fffbeb; border-color: #fde68a;" title="Formula: Σ confirmed bank deposits and received payments">
                        <div class="card-label" style="color: #92400e;">Total Payments Received</div>
                        <div class="card-value" style="color: #b45309;"><?php echo htmlspecialchars($currency); ?><?php echo number_format($summary['total_payments_received'] ?? 0, 0); ?></div>
                        <div class="kpi-formula-sub" style="color: #92400e;"><code>Formula:</code> Σ confirmed bank payments</div>
                        <div class="card-footer" style="color: #d97706; margin-top: 8px;">Realized treasury cash</div>
                    </div>

                    <div class="card" style="background: #fef2f2; border-color: #fecaca;" title="Formula: Gross Billed - Total Received Payments">
                        <div class="card-label" style="color: #991b1b;">Total Outstanding (AR)</div>
                        <div class="card-value" style="color: #dc2626;"><?php echo htmlspecialchars($currency); ?><?php echo number_format($summary['total_outstanding'] ?? 0, 0); ?></div>
                        <div class="kpi-formula-sub" style="color: #991b1b;"><code>Formula:</code> Gross Billed - Total Received</div>
                        <div class="card-footer" style="color: #ef4444; margin-top: 8px;">Active credit exposure</div>
                    </div>

                    <div class="card" title="Scope: Count of unique billing customer entities">
                        <div class="card-label">Active Customers</div>
                        <div class="card-value"><?php echo $summary['unique_customers'] ?? 0; ?></div>
                        <div class="kpi-formula-sub"><code>Scope:</code> Distinct purchasing entities</div>
                        <div class="card-footer" style="margin-top: 8px;">Corporate & partner accounts</div>
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

        function toggleIndexMethodology() {
            const body = document.getElementById('indexMethodologyBody');
            const icon = document.getElementById('indexMethodologyIcon');
            const text = document.getElementById('indexMethodologyText');
            const grid = document.getElementById('dashboardGrid');
            if (!body) return;
            const isCollapsed = body.classList.contains('collapsed');
            if (isCollapsed) {
                body.classList.remove('collapsed');
                if (icon) {
                    icon.classList.add('expanded');
                    icon.textContent = '▼';
                }
                if (text) text.textContent = 'Hide Logic & Formulas';
                if (grid) grid.classList.add('show-formulas');
            } else {
                body.classList.add('collapsed');
                if (icon) {
                    icon.classList.remove('expanded');
                    icon.textContent = '▶';
                }
                if (text) text.textContent = 'Show Logic & Formulas';
                if (grid) grid.classList.remove('show-formulas');
            }
        }
    </script>
</body>
</html>

