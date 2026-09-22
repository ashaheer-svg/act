<?php
/**
 * Historical Invoice VAT Audit & Manual Review Tool
 * Allows administrators to audit invoices across statutory tax eras and
 * easily switch individual invoices or bulk selections between VAT-Inclusive and VAT+ (Pre-Tax Base).
 */

require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/Auth.php';

$db = new Database(DATABASE_PATH);
$auth = new Auth($db);
$auth->requireReportAccess('vat_review');
$user = $auth->getCurrentUser();

$currency = $db->getSetting('currency_symbol', 'LKR ');

// ── AJAX Handler ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json');

    $action = $_POST['action'];

    try {
        if ($action === 'switch_single') {
            $invNum = trim($_POST['invoice_number'] ?? '');
            $targetMode = trim($_POST['target_mode'] ?? 'VAT_INCLUSIVE');
            $res = $db->switchInvoiceVatMode($invNum, $targetMode);
            echo json_encode($res);
            exit;
        }

        if ($action === 'switch_bulk') {
            $invoices = json_decode($_POST['invoices'] ?? '[]', true);
            $targetMode = trim($_POST['target_mode'] ?? 'VAT_INCLUSIVE');
            if (empty($invoices)) {
                throw new Exception("No invoices selected.");
            }
            $updatedCount = 0;
            foreach ($invoices as $invNum) {
                $db->switchInvoiceVatMode(trim($invNum), $targetMode);
                $updatedCount++;
            }
            echo json_encode(['success' => true, 'updated_count' => $updatedCount, 'target_mode' => $targetMode]);
            exit;
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// ── Filters & Querying ──
$selectedEra = $_GET['era'] ?? 'taxable';
$selectedMode = $_GET['mode'] ?? 'all';
$search = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['p'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

$whereClauses = ["invoice_type != 'Credit Memo'"];
$params = [];

if ($search !== '') {
    $whereClauses[] = "(invoice_number LIKE ? OR customer_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($selectedMode !== 'all') {
    $whereClauses[] = "vat_treatment = ?";
    $params[] = $selectedMode;
}

switch ($selectedEra) {
    case '2024_2026':
        $whereClauses[] = "invoice_date >= '2024-01-01'";
        break;
    case '2019_2022':
        $whereClauses[] = "invoice_date BETWEEN '2019-12-01' AND '2022-05-31'";
        break;
    case '2016_2019':
        $whereClauses[] = "invoice_date BETWEEN '2016-11-01' AND '2019-11-30'";
        break;
    case '2009_2014':
        $whereClauses[] = "invoice_date BETWEEN '2009-01-01' AND '2014-12-31'";
        break;
    case 'exempt_eras':
        $whereClauses[] = "(invoice_date BETWEEN '2015-01-01' AND '2016-10-31' OR invoice_date BETWEEN '2021-04-01' AND '2023-12-31')";
        break;
    case 'taxable':
    default:
        $whereClauses[] = "applied_tax_rate > 0";
        break;
    case 'all_history':
        // No era filter
        break;
}

$whereSql = implode(' AND ', $whereClauses);

// Summary statistics
$stats = $db->fetch("
    SELECT 
        COUNT(DISTINCT invoice_number) as total_invoices,
        SUM(total_amount) as sum_total,
        SUM(base_value) as sum_base,
        SUM(vat_component) as sum_vat,
        COUNT(DISTINCT CASE WHEN vat_treatment = 'VAT_INCLUSIVE' THEN invoice_number END) as inclusive_invoices,
        COUNT(DISTINCT CASE WHEN vat_treatment = 'PLUS_VAT' THEN invoice_number END) as plus_vat_invoices,
        COUNT(DISTINCT CASE WHEN vat_treatment = 'VAT_EXEMPT' THEN invoice_number END) as exempt_invoices
    FROM sales
    WHERE $whereSql
", $params);

// Fetch invoices aggregated
$invoicesQuery = "
    SELECT 
        invoice_number,
        invoice_date,
        customer_name,
        applied_tax_rate,
        vat_treatment,
        sales_tax_total,
        sales_tax_item,
        MAX(COALESCE(manual_vat_override, 0)) as is_manual,
        SUM(qb_amount) as total_qb_amount,
        SUM(base_value) as total_base_value,
        SUM(vat_component) as total_vat_component,
        SUM(total_amount) as total_amount,
        COUNT(*) as line_count
    FROM sales
    WHERE $whereSql
    GROUP BY invoice_number
    ORDER BY invoice_date DESC, id DESC
    LIMIT ? OFFSET ?
";
$queryParams = array_merge($params, [$limit, $offset]);
$invoices = $db->fetchAll($invoicesQuery, $queryParams);

$totalInvoicesCount = (int)($stats['total_invoices'] ?? 0);
$totalPages = ceil($totalInvoicesCount / $limit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VAT Invoice Review & Switcher | Active Solutions</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="docs/lucide-font/lucide.css">
    <link rel="stylesheet" href="layout.css?v=1.0.3">
    <style>
        .vat-review-page { padding: 4px 0 24px; max-width: 100%; margin: 0; }
        .era-pills {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .era-pill {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
            transition: all 0.15s;
        }
        .era-pill:hover {
            background: #e2e8f0;
            color: #0f172a;
        }
        .era-pill.active {
            background: #2563eb;
            color: #ffffff;
            border-color: #2563eb;
            box-shadow: 0 2px 6px rgba(37,99,235,0.25);
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .stat-val {
            font-size: 20px;
            font-weight: 800;
            color: #0f172a;
            font-family: 'JetBrains Mono', monospace;
        }
        .stat-label {
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-top: 4px;
        }
        .bulk-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 16px;
            margin-bottom: 16px;
        }
        .vat-table {
            width: 100%;
            border-collapse: collapse;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            font-size: 12.5px;
        }
        .vat-table th {
            background: #f8fafc;
            padding: 10px 12px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: #475569;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
        }
        .vat-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .vat-table tr:hover td {
            background: #f8fafc;
        }
        .badge-inc {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            display: inline-block;
        }
        .badge-plus {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #bfdbfe;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            display: inline-block;
        }
        .badge-ex {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            display: inline-block;
        }
        .act-btn {
            height: 24px;
            padding: 0 8px;
            font-size: 11px;
            font-weight: 600;
            border-radius: 4px;
            border: 1px solid;
            cursor: pointer;
            background: #ffffff;
            transition: all 0.15s;
        }
        .act-btn-inc {
            color: #059669;
            border-color: #a7f3d0;
            background: #ecfdf5;
        }
        .act-btn-inc:hover {
            background: #059669;
            color: #ffffff;
        }
        .act-btn-plus {
            color: #2563eb;
            border-color: #bfdbfe;
            background: #eff6ff;
        }
        .act-btn-plus:hover {
            background: #2563eb;
            color: #ffffff;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php require_once 'includes/sidebar.php'; ?>

        <main class="main-wrapper">
            <?php $searchPlaceholder = 'Search invoices or customers...'; require_once 'includes/header.php'; ?>

            <div class="content-body">
                <div class="vat-review-page">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
                    <div>
                        <h1 style="font-size: 22px; font-weight: 800; color: #0f172a; margin: 0;">⚡ VAT Invoice Audit &amp; Switcher</h1>
                        <p style="font-size: 13px; color: #64748b; margin: 4px 0 0 0;">
                            Audit statutory VAT extraction and easily toggle historical invoices between <strong>VAT-Inclusive</strong> and <strong>VAT+ (Pre-Tax Base)</strong>.
                        </p>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <a href="settings.php" class="cmd-btn" style="text-decoration: none;">&larr; Return to Settings</a>
                    </div>
                </div>

                <!-- Era Selection Filter Pills -->
                <div class="era-pills">
                    <a href="?era=taxable&mode=<?= urlencode($selectedMode); ?>" class="era-pill <?= $selectedEra === 'taxable' ? 'active' : ''; ?>">All Taxable Periods (>0%)</a>
                    <a href="?era=2024_2026&mode=<?= urlencode($selectedMode); ?>" class="era-pill <?= $selectedEra === '2024_2026' ? 'active' : ''; ?>">2024–2026 (18% Statutory)</a>
                    <a href="?era=2019_2022&mode=<?= urlencode($selectedMode); ?>" class="era-pill <?= $selectedEra === '2019_2022' ? 'active' : ''; ?>">2019–2022 (8% Statutory)</a>
                    <a href="?era=2016_2019&mode=<?= urlencode($selectedMode); ?>" class="era-pill <?= $selectedEra === '2016_2019' ? 'active' : ''; ?>">2016–2019 (15% Statutory)</a>
                    <a href="?era=2009_2014&mode=<?= urlencode($selectedMode); ?>" class="era-pill <?= $selectedEra === '2009_2014' ? 'active' : ''; ?>">2009–2014 (12% Statutory)</a>
                    <a href="?era=exempt_eras&mode=<?= urlencode($selectedMode); ?>" class="era-pill <?= $selectedEra === 'exempt_eras' ? 'active' : ''; ?>">Exempt Eras (0%)</a>
                    <a href="?era=all_history&mode=<?= urlencode($selectedMode); ?>" class="era-pill <?= $selectedEra === 'all_history' ? 'active' : ''; ?>">All Eras (2009–2026)</a>
                </div>

                <!-- KPI Metric Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-val"><?= number_format($totalInvoicesCount); ?></div>
                        <div class="stat-label">Invoices in Scope</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-val" style="color: #059669;"><?= number_format($stats['inclusive_invoices'] ?? 0); ?></div>
                        <div class="stat-label">VAT-Inclusive Invoices</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-val" style="color: #2563eb;"><?= number_format($stats['plus_vat_invoices'] ?? 0); ?></div>
                        <div class="stat-label">VAT+ Pre-Tax Invoices</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-val"><?= $currency; ?><?= number_format($stats['sum_total'] ?? 0, 0); ?></div>
                        <div class="stat-label">Total Invoiced Volume</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-val" style="color: #d97706;"><?= $currency; ?><?= number_format($stats['sum_vat'] ?? 0, 0); ?></div>
                        <div class="stat-label">Extracted VAT Liability</div>
                    </div>
                </div>

                <!-- Search & Filter Controls -->
                <form method="GET" style="display: flex; gap: 12px; margin-bottom: 16px; align-items: center;">
                    <input type="hidden" name="era" value="<?= htmlspecialchars($selectedEra); ?>">
                    <input type="text" name="q" value="<?= htmlspecialchars($search); ?>" placeholder="Search Invoice # or Customer..." style="padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; width: 300px;">
                    <select name="mode" onchange="this.form.submit()" style="padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;">
                        <option value="all" <?= $selectedMode === 'all' ? 'selected' : ''; ?>>All Modes</option>
                        <option value="VAT_INCLUSIVE" <?= $selectedMode === 'VAT_INCLUSIVE' ? 'selected' : ''; ?>>VAT-Inclusive Only</option>
                        <option value="PLUS_VAT" <?= $selectedMode === 'PLUS_VAT' ? 'selected' : ''; ?>>VAT+ Pre-Tax Only</option>
                        <option value="VAT_EXEMPT" <?= $selectedMode === 'VAT_EXEMPT' ? 'selected' : ''; ?>>Exempt Only</option>
                    </select>
                    <button type="submit" class="cmd-btn" style="height: 34px;">Filter</button>
                    <?php if (!empty($search) || $selectedMode !== 'all'): ?>
                        <a href="?era=<?= htmlspecialchars($selectedEra); ?>" style="font-size: 12px; color: #64748b;">Clear Filters</a>
                    <?php endif; ?>
                </form>

                <!-- Bulk Selection Controls Bar -->
                <div class="bulk-bar">
                    <div style="display: flex; align-items: center; gap: 10px; font-size: 13px; font-weight: 600;">
                        <input type="checkbox" id="selectAllChks" onclick="toggleAllCheckboxes(this)" style="cursor: pointer; width: 16px; height: 16px;">
                        <span id="selectedCountText">0 selected</span>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <button type="button" class="act-btn act-btn-inc" onclick="bulkSwitch('VAT_INCLUSIVE')">
                            ⚡ Set Selected as VAT-Inclusive
                        </button>
                        <button type="button" class="act-btn act-btn-plus" onclick="bulkSwitch('PLUS_VAT')">
                            ⚡ Set Selected as VAT+
                        </button>
                    </div>
                </div>

                <!-- Invoices Table -->
                <div style="overflow-x: auto;">
                    <table class="vat-table">
                        <thead>
                            <tr>
                                <th style="width: 36px; text-align: center;"></th>
                                <th>Invoice #</th>
                                <th>Date</th>
                                <th>Customer Name</th>
                                <th style="text-align: right;">QB Raw Amount</th>
                                <th style="text-align: right;">Base Value</th>
                                <th style="text-align: right;">VAT Component</th>
                                <th style="text-align: right;">Total Amount</th>
                                <th>Rate</th>
                                <th>Current Treatment</th>
                                <th style="text-align: center;">Quick Switcher</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($invoices)): ?>
                                <tr>
                                    <td colspan="11" style="text-align: center; color: #64748b; padding: 40px 0;">
                                        No invoices found matching the selected filters.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($invoices as $inv): 
                                    $num = $inv['invoice_number'];
                                    $ratePct = floatval($inv['applied_tax_rate']) * 100;
                                    $treat = $inv['vat_treatment'];
                                ?>
                                <tr id="row_<?= htmlspecialchars($num); ?>">
                                    <td style="text-align: center;">
                                        <input type="checkbox" class="inv-chk" value="<?= htmlspecialchars($num); ?>" onchange="updateSelectedCount()" style="cursor: pointer;">
                                    </td>
                                    <td>
                                        <a href="invoice_edit.php?inv=<?= urlencode($num); ?>" style="font-weight: 700; color: #2563eb; text-decoration: none; font-family: 'JetBrains Mono', monospace;" title="Open in invoice editor">
                                            <?= htmlspecialchars($num); ?> &rarr;
                                        </a>
                                    </td>
                                    <td style="color: #64748b; font-size: 12px;"><?= htmlspecialchars($inv['invoice_date']); ?></td>
                                    <td style="font-weight: 600; max-width: 240px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <?= htmlspecialchars($inv['customer_name']); ?>
                                    </td>
                                    <td style="text-align: right; font-family: 'JetBrains Mono', monospace;">
                                        <?= number_format($inv['total_qb_amount'], 2); ?>
                                    </td>
                                    <td style="text-align: right; font-family: 'JetBrains Mono', monospace; font-weight: 600;">
                                        <?= number_format($inv['total_base_value'], 2); ?>
                                    </td>
                                    <td style="text-align: right; font-family: 'JetBrains Mono', monospace; color: #d97706;">
                                        <?= number_format($inv['total_vat_component'], 2); ?>
                                    </td>
                                    <td style="text-align: right; font-family: 'JetBrains Mono', monospace; font-weight: 700; color: #0f172a;">
                                        <?= number_format($inv['total_amount'], 2); ?>
                                    </td>
                                    <td style="font-size: 11px; font-weight: 600; color: #475569;">
                                        <?= $ratePct > 0 ? ($ratePct . '%') : '0%'; ?>
                                    </td>
                                    <td>
                                        <?php if ($treat === 'VAT_INCLUSIVE'): ?>
                                            <span class="badge-inc">VAT-Inclusive</span>
                                        <?php elseif ($treat === 'PLUS_VAT'): ?>
                                            <span class="badge-plus">VAT+</span>
                                        <?php else: ?>
                                            <span class="badge-ex">Exempt</span>
                                        <?php endif; ?>
                                        <?php if (!empty($inv['is_manual'])): ?>
                                            <span style="display: block; margin-top: 4px; font-size: 10px; color: #4338ca; font-weight: 600;">⚡ Manual</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center; white-space: nowrap;">
                                        <?php if ($treat === 'VAT_INCLUSIVE'): ?>
                                            <button type="button" class="act-btn act-btn-plus" onclick="switchRow('<?= htmlspecialchars($num); ?>', 'PLUS_VAT')" title="Switch to calculate VAT on top of base">
                                                Switch to VAT+
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="act-btn act-btn-inc" onclick="switchRow('<?= htmlspecialchars($num); ?>', 'VAT_INCLUSIVE')" title="Switch so invoice price includes VAT">
                                                Switch to Inclusive
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; font-size: 13px;">
                    <div style="color: #64748b;">
                        Showing page <strong><?= $page; ?></strong> of <strong><?= $totalPages; ?></strong> (<?= number_format($totalInvoicesCount); ?> invoices)
                    </div>
                    <div style="display: flex; gap: 6px;">
                        <?php if ($page > 1): ?>
                            <a href="?era=<?= urlencode($selectedEra); ?>&mode=<?= urlencode($selectedMode); ?>&q=<?= urlencode($search); ?>&p=<?= $page - 1; ?>" class="cmd-btn">&larr; Prev</a>
                        <?php endif; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="?era=<?= urlencode($selectedEra); ?>&mode=<?= urlencode($selectedMode); ?>&q=<?= urlencode($search); ?>&p=<?= $page + 1; ?>" class="cmd-btn">Next &rarr;</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <?php require_once 'includes/layout_js.php'; ?>

    <script>
        function toggleAllCheckboxes(master) {
            const chks = document.querySelectorAll('.inv-chk');
            chks.forEach(c => c.checked = master.checked);
            updateSelectedCount();
        }

        function updateSelectedCount() {
            const count = document.querySelectorAll('.inv-chk:checked').length;
            document.getElementById('selectedCountText').innerText = count + ' selected';
        }

        function switchRow(invNum, targetMode) {
            const modeLabel = targetMode.replace('_', ' ');
            if (!confirm('Switch invoice #' + invNum + ' to ' + modeLabel + '?')) return;

            const fd = new FormData();
            fd.append('action', 'switch_single');
            fd.append('invoice_number', invNum);
            fd.append('target_mode', targetMode);

            fetch('vat_review.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    if (res && res.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + (res.error || 'Failed to switch'));
                    }
                })
                .catch(e => alert('Network error: ' + e.message));
        }

        function bulkSwitch(targetMode) {
            const selected = [];
            document.querySelectorAll('.inv-chk:checked').forEach(c => selected.push(c.value));
            if (selected.length === 0) {
                alert('Please select at least one invoice.');
                return;
            }
            const modeLabel = targetMode.replace('_', ' ');
            if (!confirm('Switch ' + selected.length + ' invoices to ' + modeLabel + '?')) return;

            const fd = new FormData();
            fd.append('action', 'switch_bulk');
            fd.append('invoices', JSON.stringify(selected));
            fd.append('target_mode', targetMode);

            fetch('vat_review.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    if (res && res.success) {
                        alert('Successfully updated ' + res.updated_count + ' invoices!');
                        location.reload();
                    } else {
                        alert('Error: ' + (res.error || 'Failed to bulk switch'));
                    }
                })
                .catch(e => alert('Network error: ' + e.message));
        }
    </script>
</body>
</html>
