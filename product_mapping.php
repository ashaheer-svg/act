<?php
/**
 * Product & Rental Mapping Center
 * Activity Sales BI - Master SKU Catalog, Automated Commercial Rules & Recurring Rental Fleet Ledger
 */

require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/Auth.php';
require_once 'classes/Reports.php';

$db = new Database(DATABASE_PATH);
$auth = new Auth($db);
$auth->requireLogin();
$user = $auth->getCurrentUser();
$reports = new Reports($db);

$message = '';
$messageType = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_mapping_rule') {
        try {
            $ruleId = $reports->saveProductMapping($_POST);
            $resort = !empty($_POST['auto_resort']) || ($_POST['commercial_type'] ?? '') === 'RENTAL';
            
            if ($resort) {
                $reports->reSortInvoices();
                $message = 'Mapping rule saved and matching invoices re-sorted successfully.';
            } else {
                $message = 'Mapping rule saved successfully.';
            }
            $messageType = 'success';
            $db->logActivity($user['id'], 'PRODUCT_MAPPING_SAVED', "Saved rule ID #$ruleId: " . ($_POST['canonical_name'] ?? ''));
        } catch (Exception $e) {
            $message = 'Error saving rule: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'delete_mapping_rule') {
        try {
            $ruleId = (int)($_POST['rule_id'] ?? 0);
            if ($ruleId > 0) {
                $reports->deleteProductMapping($ruleId);
                $message = "Rule #$ruleId deleted successfully.";
                $messageType = 'success';
                $db->logActivity($user['id'], 'PRODUCT_MAPPING_DELETED', "Deleted rule ID #$ruleId");
            }
        } catch (Exception $e) {
            $message = 'Error deleting rule: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'resync_rentals') {
        try {
            $count = $reports->reSortInvoices();
            $message = "Rental fleet ledger re-synced! Processed $count rental-related invoices.";
            $messageType = 'success';
            $db->logActivity($user['id'], 'RENTAL_LEDGER_RESYNCED', "Re-sorted $count rental invoices");
        } catch (Exception $e) {
            $message = 'Error re-syncing rentals: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Active Tab & Filters
$activeTab = $_GET['tab'] ?? 'mappings';
if (!in_array($activeTab, ['mappings', 'rentals', 'unmapped'])) {
    $activeTab = 'mappings';
}

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 50;

// Filters
$filters = [
    'search' => trim($_GET['search'] ?? ''),
    'commercial_type' => $_GET['commercial_type'] ?? 'ALL',
    'match_type' => $_GET['match_type'] ?? 'ALL',
    'status' => $_GET['status'] ?? 'ALL'
];

// Fetch Data
$mappingData = $reports->getProductMappings($filters, $activeTab === 'mappings' ? $page : 1, $limit);
$rentalData = $reports->getRentalFleet($filters, $activeTab === 'rentals' ? $page : 1, $limit);
$unmappedData = $reports->getUnmappedDescriptions(60);

$mappingKpis = $mappingData['kpis'];
$rentalSummary = $reports->getRentalSummary();

$currency = $db->getSetting('currency_symbol', 'LKR ');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product & Rental Mapping Center - Activity Sales BI</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="docs/lucide-font/lucide.css">
    <link rel="stylesheet" href="layout.css?v=1.0.4">
    <style>
        .filter-select-sm {
            height: 28px;
            padding: 0 8px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            font-size: 11px;
            color: var(--text-main);
            background: #ffffff;
            outline: none;
        }
        .filter-select-sm:focus {
            border-color: var(--primary);
        }
        .search-input-sm {
            height: 28px;
            padding: 0 10px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            font-size: 11px;
            width: 220px;
            outline: none;
        }
        .search-input-sm:focus {
            border-color: var(--primary);
        }
        .btn-cmd {
            height: 28px;
            padding: 0 10px;
            border-radius: var(--radius-sm);
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border: 1px solid var(--border-color);
            background: #ffffff;
            color: var(--text-main);
            text-decoration: none;
            transition: all 0.15s ease;
        }
        .btn-cmd:hover {
            background: #f1f5f9;
        }
        .btn-cmd-primary {
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
        }
        .btn-cmd-primary:hover {
            background: #1d4ed8;
        }
        .btn-cmd-purple {
            background: #7c3aed;
            color: #ffffff;
            border-color: #7c3aed;
        }
        .btn-cmd-purple:hover {
            background: #6d28d9;
        }
        .action-icon-btn {
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 3px 5px;
            border-radius: 4px;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.15s ease;
        }
        .action-icon-btn:hover {
            background: #e2e8f0;
            color: var(--text-main);
        }
        .action-icon-btn.delete:hover {
            background: #fee2e2;
            color: #dc2626;
        }
        .pagination-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 12px;
            border-top: 1px solid var(--border-color);
            background: #ffffff;
            font-size: 11px;
            color: var(--text-muted);
        }
        .pagination-pages {
            display: flex;
            align-items: center;
            gap: 3px;
        }
        .page-link {
            padding: 3px 8px;
            border: 1px solid var(--border-color);
            border-radius: 3px;
            text-decoration: none;
            color: var(--text-main);
            font-weight: 600;
        }
        .page-link.active {
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php require_once 'includes/sidebar.php'; ?>
        
        <main class="main-wrapper">
            <?php 
                $searchPlaceholder = 'Search rules, master SKUs, canonical names, serials...'; 
                require_once 'includes/header.php'; 
            ?>

            <div class="content-body" style="padding: 12px 16px;">
                <?php if ($message): ?>
                <div class="message <?= $messageType ?>" style="margin-bottom: 12px; padding: 10px 14px; border-radius: 6px; font-size: 12px; font-weight: 600; display: flex; align-items: center; gap: 8px; <?= $messageType === 'success' ? 'background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0;' : 'background: #fef2f2; color: #991b1b; border: 1px solid #fecaca;' ?>">
                    <i class="<?= $messageType === 'success' ? 'icon-check-circle' : 'icon-alert-triangle' ?>"></i>
                    <span><?= htmlspecialchars($message) ?></span>
                </div>
                <?php endif; ?>

                <!-- Command Bar & Navigation Tabs -->
                <div class="command-bar" style="margin-bottom: 10px; height: 42px;">
                    <div class="cmd-left">
                        <div class="pm-nav-tabs">
                            <a href="product_mapping.php?tab=mappings" class="pm-nav-tab <?= $activeTab === 'mappings' ? 'active' : '' ?>">
                                <i class="icon-sliders" style="font-size: 12px;"></i>
                                <span>Product Mapping Rules</span>
                                <span class="pm-count-badge"><?= $mappingKpis['total_rules'] ?? 0 ?></span>
                            </a>
                            <a href="product_mapping.php?tab=rentals" class="pm-nav-tab <?= $activeTab === 'rentals' ? 'active' : '' ?>">
                                <i class="icon-repeat" style="font-size: 12px;"></i>
                                <span>Rental Fleet & Billing</span>
                                <span class="pm-count-badge" style="<?= ($rentalSummary['active_count'] ?? 0) > 0 ? 'background: #7c3aed; color: #fff;' : '' ?>"><?= $rentalSummary['total_rentals'] ?? 0 ?></span>
                            </a>
                            <a href="product_mapping.php?tab=unmapped" class="pm-nav-tab <?= $activeTab === 'unmapped' ? 'active' : '' ?>">
                                <i class="icon-list-filter" style="font-size: 12px;"></i>
                                <span>Unmapped Sales Queue</span>
                                <span class="pm-count-badge"><?= count($unmappedData) ?></span>
                            </a>
                        </div>
                    </div>

                    <div class="cmd-right">
                        <form method="POST" style="margin: 0; display: inline-flex;">
                            <input type="hidden" name="action" value="resync_rentals">
                            <button type="submit" class="btn-cmd" title="Re-sort rental invoices from QuickBooks sales lines">
                                <i class="icon-refresh-cw" style="font-size: 11px;"></i>
                                <span>Re-Sync Rentals</span>
                            </button>
                        </form>
                        <button type="button" class="btn-cmd btn-cmd-primary" onclick="openRuleModal()">
                            <i class="icon-plus" style="font-size: 12px;"></i>
                            <span>New Mapping Rule</span>
                        </button>
                    </div>
                </div>

                <!-- Contextual Metrics Ribbon -->
                <div class="metrics-strip">
                    <?php if ($activeTab === 'rentals'): ?>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Active Rental Units</span>
                        <span class="metric-pill-val" style="color: #7c3aed;"><?= number_format($rentalSummary['active_count'] ?? 0) ?></span>
                        <span class="metric-pill-sub">MRR: <?= $currency . number_format($rentalSummary['active_mrr'] ?? 0, 2) ?></span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Overdue Renewals (>35d)</span>
                        <span class="metric-pill-val" style="color: <?= ($rentalSummary['overdue_count'] ?? 0) > 0 ? '#dc2626' : '#64748b' ?>;"><?= number_format($rentalSummary['overdue_count'] ?? 0) ?></span>
                        <span class="metric-pill-sub">Pending: <?= $currency . number_format($rentalSummary['overdue_mrr'] ?? 0, 2) ?></span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Tracked Rental Assets</span>
                        <span class="metric-pill-val"><?= number_format($rentalSummary['rental_hardware_units'] ?? 0) ?></span>
                        <span class="metric-pill-sub">Physical S/N units deployed</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Rental Lifetime Revenue</span>
                        <span class="metric-pill-val"><?= $currency . number_format($rentalSummary['total_rental_volume'] ?? 0, 0) ?></span>
                        <span class="metric-pill-sub"><?= number_format($rentalSummary['total_rental_customers'] ?? 0) ?> corporate clients</span>
                    </div>
                    <?php else: ?>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Active Mapping Rules</span>
                        <span class="metric-pill-val"><?= number_format($mappingKpis['total_rules'] ?? 0) ?></span>
                        <span class="metric-pill-sub">Automated catalog mappings</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Rental Fleet Rules</span>
                        <span class="metric-pill-val" style="color: #7c3aed;"><?= number_format($mappingKpis['rental_rules'] ?? 0) ?></span>
                        <span class="metric-pill-sub">Hardware-as-a-Service rules</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Outright Sale Rules</span>
                        <span class="metric-pill-val" style="color: #2563eb;"><?= number_format($mappingKpis['sale_rules'] ?? 0) ?></span>
                        <span class="metric-pill-sub">Hardware & license products</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Standard Master SKUs</span>
                        <span class="metric-pill-val"><?= number_format($mappingKpis['distinct_skus'] ?? 0) ?></span>
                        <span class="metric-pill-sub">Canonical inventory codes</span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- TAB 1: PRODUCT MAPPING RULES -->
                <?php if ($activeTab === 'mappings'): ?>
                <div class="card" style="padding: 0; overflow: hidden;">
                    <!-- Filter Toolbar -->
                    <form method="GET" action="product_mapping.php" class="toolbar" style="border-radius: 0; border: none; border-bottom: 1px solid var(--border-color);">
                        <input type="hidden" name="tab" value="mappings">
                        <div class="filter-group">
                            <input type="text" name="search" class="search-input-sm" placeholder="Search pattern, SKU, canonical..." value="<?= htmlspecialchars($filters['search']) ?>">
                            
                            <select name="commercial_type" class="filter-select-sm">
                                <option value="ALL">All Commercial Types</option>
                                <option value="RENTAL" <?= $filters['commercial_type'] === 'RENTAL' ? 'selected' : '' ?>>Rental Fleet (Lease)</option>
                                <option value="OUTRIGHT_SALE" <?= $filters['commercial_type'] === 'OUTRIGHT_SALE' ? 'selected' : '' ?>>Outright Sale</option>
                                <option value="SOFTWARE" <?= $filters['commercial_type'] === 'SOFTWARE' ? 'selected' : '' ?>>Software License</option>
                                <option value="MAINTENANCE" <?= $filters['commercial_type'] === 'MAINTENANCE' ? 'selected' : '' ?>>Maintenance (MA/AMC)</option>
                                <option value="SERVICE" <?= $filters['commercial_type'] === 'SERVICE' ? 'selected' : '' ?>>Service / Professional</option>
                            </select>

                            <select name="match_type" class="filter-select-sm">
                                <option value="ALL">All Match Types</option>
                                <option value="CONTAINS" <?= $filters['match_type'] === 'CONTAINS' ? 'selected' : '' ?>>CONTAINS (Wildcard)</option>
                                <option value="EXACT" <?= $filters['match_type'] === 'EXACT' ? 'selected' : '' ?>>EXACT</option>
                                <option value="REGEX" <?= $filters['match_type'] === 'REGEX' ? 'selected' : '' ?>>REGEX</option>
                            </select>

                            <button type="submit" class="btn-cmd">
                                <i class="icon-filter" style="font-size: 11px;"></i>
                                <span>Filter</span>
                            </button>

                            <?php if (!empty($filters['search']) || $filters['commercial_type'] !== 'ALL' || $filters['match_type'] !== 'ALL'): ?>
                            <a href="product_mapping.php?tab=mappings" class="btn-cmd" style="color: var(--text-muted);">Reset</a>
                            <?php endif; ?>
                        </div>

                        <div style="font-size: 11px; color: var(--text-muted);">
                            Showing <?= count($mappingData['rules']) ?> of <?= number_format($mappingData['total']) ?> rules
                        </div>
                    </form>

                    <!-- Rules Table -->
                    <div style="overflow-x: auto;">
                        <table class="dense-table" style="width: 100%;">
                            <thead>
                                <tr>
                                    <th style="width: 45px;" class="text-center">Pri</th>
                                    <th>Match Pattern</th>
                                    <th style="width: 80px;">Type</th>
                                    <th>Master SKU</th>
                                    <th>Canonical Product Name</th>
                                    <th>Classification</th>
                                    <th>Brand</th>
                                    <th>VAT Default</th>
                                    <th>Notes</th>
                                    <th style="width: 60px;" class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($mappingData['rules'])): ?>
                                <tr>
                                    <td colspan="10" style="text-align: center; padding: 35px 0; color: var(--text-muted);">
                                        No product mapping rules found matching your filters.
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($mappingData['rules'] as $rule): ?>
                                    <tr>
                                        <td class="text-center" style="font-weight: 700; color: var(--text-muted); font-variant-numeric: tabular-nums;">
                                            <?= (int)$rule['priority'] ?>
                                        </td>
                                        <td>
                                            <code class="raw-pill" style="font-weight: 600; color: #0f172a;"><?= htmlspecialchars($rule['pattern']) ?></code>
                                        </td>
                                        <td>
                                            <span style="font-size: 10px; font-weight: 700; color: var(--text-secondary);"><?= htmlspecialchars($rule['match_type']) ?></span>
                                        </td>
                                        <td>
                                            <?php if (!empty($rule['master_sku'])): ?>
                                            <span class="badge-sku"><?= htmlspecialchars($rule['master_sku']) ?></span>
                                            <?php else: ?>
                                            <span style="color: var(--text-muted); font-size: 10px;">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-weight: 600; color: #0f172a;">
                                            <?= htmlspecialchars($rule['canonical_name']) ?>
                                        </td>
                                        <td>
                                            <?php if ($rule['commercial_type'] === 'RENTAL'): ?>
                                            <span class="badge badge-rental"><i class="icon-repeat" style="font-size: 9px;"></i> Rental Fleet</span>
                                            <?php elseif ($rule['commercial_type'] === 'OUTRIGHT_SALE'): ?>
                                            <span class="badge badge-sale"><i class="icon-box" style="font-size: 9px;"></i> Sale</span>
                                            <?php elseif ($rule['commercial_type'] === 'SOFTWARE'): ?>
                                            <span class="badge badge-license"><i class="icon-shield-check" style="font-size: 9px;"></i> Software</span>
                                            <?php elseif ($rule['commercial_type'] === 'MAINTENANCE'): ?>
                                            <span class="badge badge-amc"><i class="icon-wrench" style="font-size: 9px;"></i> AMC / MA</span>
                                            <?php else: ?>
                                            <span class="badge badge-service"><?= htmlspecialchars($rule['commercial_type']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="color: var(--text-secondary); font-size: 11px;">
                                            <?= htmlspecialchars($rule['brand'] ?: '—') ?>
                                        </td>
                                        <td style="font-size: 10.5px; color: var(--text-muted);">
                                            <?= htmlspecialchars($rule['default_vat_treatment'] ?: 'DEFAULT') ?>
                                        </td>
                                        <td style="font-size: 10.5px; color: var(--text-muted); max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            <?= htmlspecialchars($rule['notes'] ?: '—') ?>
                                        </td>
                                        <td class="text-center">
                                            <div style="display: inline-flex; align-items: center; gap: 2px;">
                                                <button type="button" class="action-icon-btn" onclick='editRule(<?= json_encode($rule) ?>)' title="Edit Rule">
                                                    <i class="icon-edit-2"></i>
                                                </button>
                                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this mapping rule?');" style="margin: 0; display: inline;">
                                                    <input type="hidden" name="action" value="delete_mapping_rule">
                                                    <input type="hidden" name="rule_id" value="<?= $rule['id'] ?>">
                                                    <button type="submit" class="action-icon-btn delete" title="Delete Rule">
                                                        <i class="icon-trash-2"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($mappingData['pages'] > 1): ?>
                    <div class="pagination-container">
                        <div>Showing page <?= $page ?> of <?= $mappingData['pages'] ?></div>
                        <div class="pagination-pages">
                            <?php for ($p = 1; $p <= $mappingData['pages']; $p++): ?>
                            <a href="product_mapping.php?tab=mappings&page=<?= $p ?>&search=<?= urlencode($filters['search']) ?>&commercial_type=<?= urlencode($filters['commercial_type']) ?>" class="page-link <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- TAB 2: RENTAL FLEET & RECURRING BILLING TRACKER -->
                <?php if ($activeTab === 'rentals'): ?>
                <div class="card" style="padding: 0; overflow: hidden;">
                    <!-- Filter Toolbar -->
                    <form method="GET" action="product_mapping.php" class="toolbar" style="border-radius: 0; border: none; border-bottom: 1px solid var(--border-color);">
                        <input type="hidden" name="tab" value="rentals">
                        <div class="filter-group">
                            <input type="text" name="search" class="search-input-sm" placeholder="Search customer, invoice, serial, product..." value="<?= htmlspecialchars($filters['search']) ?>">
                            
                            <select name="status" class="filter-select-sm">
                                <option value="ALL">All Statuses</option>
                                <option value="ACTIVE" <?= $filters['status'] === 'ACTIVE' ? 'selected' : '' ?>>Active (< 35 Days)</option>
                                <option value="OVERDUE" <?= $filters['status'] === 'OVERDUE' ? 'selected' : '' ?>>Overdue Billing (35–60 Days)</option>
                                <option value="SUSPENDED" <?= $filters['status'] === 'SUSPENDED' ? 'selected' : '' ?>>Suspended (> 60 Days)</option>
                            </select>

                            <button type="submit" class="btn-cmd">
                                <i class="icon-filter" style="font-size: 11px;"></i>
                                <span>Filter</span>
                            </button>

                            <?php if (!empty($filters['search']) || $filters['status'] !== 'ALL'): ?>
                            <a href="product_mapping.php?tab=rentals" class="btn-cmd" style="color: var(--text-muted);">Reset</a>
                            <?php endif; ?>
                        </div>

                        <div style="font-size: 11px; color: var(--text-muted);">
                            Showing <?= count($rentalData['deployments']) ?> of <?= number_format($rentalData['total']) ?> rental records
                        </div>
                    </form>

                    <!-- Rental Deployments Table -->
                    <div style="overflow-x: auto;">
                        <table class="dense-table" style="width: 100%;">
                            <thead>
                                <tr>
                                    <th>Customer & Invoice</th>
                                    <th style="width: 95px;">Last Invoiced</th>
                                    <th>Deployed Hardware System</th>
                                    <th>Assigned Serial Numbers</th>
                                    <th class="text-right" style="width: 110px;">Monthly Rent</th>
                                    <th style="width: 75px;">Tax Treat</th>
                                    <th style="width: 85px;" class="text-center">Billing Status</th>
                                    <th>Period / Notes</th>
                                    <th style="width: 50px;" class="text-center">Audit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($rentalData['deployments'])): ?>
                                <tr>
                                    <td colspan="9" style="text-align: center; padding: 35px 0; color: var(--text-muted);">
                                        No rental fleet records found. Click "Re-Sync Rentals" above to process rental invoices from sales lines.
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($rentalData['deployments'] as $rent): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 700; color: #0f172a;"><?= htmlspecialchars($rent['customer_name']) ?></div>
                                            <a href="reports.php?type=invoices&search=<?= urlencode($rent['invoice_number']) ?>" style="font-size: 10px; color: var(--primary); text-decoration: none; font-weight: 600;">
                                                #<?= htmlspecialchars($rent['invoice_number']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <div style="font-variant-numeric: tabular-nums; font-size: 11px;"><?= htmlspecialchars($rent['invoice_date']) ?></div>
                                            <div style="font-size: 10px; color: var(--text-muted);"><?= (int)$rent['days_since_billed'] ?>d ago</div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($rent['clean_product_name']) ?></div>
                                            <?php if (!empty($rent['brand_category'])): ?>
                                            <span style="font-size: 10px; color: var(--text-muted);"><?= htmlspecialchars($rent['brand_category']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                                $serials = array_filter(array_map('trim', explode(',', $rent['serial_numbers'] ?? '')));
                                                if (empty($serials)): 
                                            ?>
                                            <span style="color: var(--text-muted); font-size: 10px;">No serial logged</span>
                                            <?php else: ?>
                                                <div style="display: flex; flex-wrap: wrap; gap: 2px; max-width: 320px;">
                                                    <?php foreach (array_slice($serials, 0, 4) as $sn): ?>
                                                    <span class="serial-tag"><?= htmlspecialchars($sn) ?></span>
                                                    <?php endforeach; ?>
                                                    <?php if (count($serials) > 4): ?>
                                                    <span class="serial-tag" style="background: #e2e8f0; color: #475569;" title="<?= htmlspecialchars(implode(', ', array_slice($serials, 4))) ?>">+<?= count($serials) - 4 ?> more</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-right" style="font-variant-numeric: tabular-nums; font-weight: 700; color: #0f172a;">
                                            <?= $currency . number_format($rent['unit_price'], 2) ?>
                                        </td>
                                        <td>
                                            <span style="font-size: 9.5px; font-weight: 700; color: var(--text-muted);"><?= htmlspecialchars($rent['vat_treatment']) ?></span>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($rent['rental_status'] === 'ACTIVE'): ?>
                                            <span class="badge badge-active"><i class="icon-check" style="font-size: 8px;"></i> Active</span>
                                            <?php elseif ($rent['rental_status'] === 'OVERDUE'): ?>
                                            <span class="badge badge-overdue"><i class="icon-clock" style="font-size: 8px;"></i> Overdue</span>
                                            <?php else: ?>
                                            <span class="badge badge-suspended">Suspended</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size: 10.5px; color: var(--text-muted); max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            <?= htmlspecialchars($rent['rental_period_notes'] ?: '—') ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="reports.php?type=invoices&search=<?= urlencode($rent['invoice_number']) ?>" class="action-icon-btn" title="View Full Invoice Lines">
                                                <i class="icon-external-link"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($rentalData['pages'] > 1): ?>
                    <div class="pagination-container">
                        <div>Showing page <?= $page ?> of <?= $rentalData['pages'] ?></div>
                        <div class="pagination-pages">
                            <?php for ($p = 1; $p <= $rentalData['pages']; $p++): ?>
                            <a href="product_mapping.php?tab=rentals&page=<?= $p ?>&search=<?= urlencode($filters['search']) ?>&status=<?= urlencode($filters['status']) ?>" class="page-link <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- TAB 3: UNMAPPED SALES QUEUE -->
                <?php if ($activeTab === 'unmapped'): ?>
                <div class="card" style="padding: 0; overflow: hidden;">
                    <div style="padding: 10px 14px; background: #f8fafc; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <span style="font-size: 12px; font-weight: 700; color: #0f172a;">High Frequency Raw Invoice Descriptions</span>
                            <p style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">
                                Raw QuickBooks sales line descriptions ranked by frequency and volume. Click "Quick Map" to generate a standardized catalog rule.
                            </p>
                        </div>
                        <span style="font-size: 11px; color: var(--text-muted); font-weight: 600;">Top <?= count($unmappedData) ?> items</span>
                    </div>

                    <div style="overflow-x: auto;">
                        <table class="dense-table" style="width: 100%;">
                            <thead>
                                <tr>
                                    <th>Raw Sales Line Description</th>
                                    <th style="width: 60px;" class="text-right">Occurrences</th>
                                    <th style="width: 120px;" class="text-right">Total Volume</th>
                                    <th style="width: 90px;">Last Seen</th>
                                    <th>Sample Customer</th>
                                    <th style="width: 95px;" class="text-center">Rule Status</th>
                                    <th style="width: 85px;" class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($unmappedData as $item): ?>
                                <tr>
                                    <td>
                                        <code class="raw-pill" title="<?= htmlspecialchars($item['description']) ?>"><?= htmlspecialchars($item['description']) ?></code>
                                    </td>
                                    <td class="text-right" style="font-weight: 700; font-variant-numeric: tabular-nums;">
                                        <?= number_format($item['occ_count']) ?>
                                    </td>
                                    <td class="text-right" style="font-variant-numeric: tabular-nums; font-weight: 600; color: #0f172a;">
                                        <?= $currency . number_format($item['total_volume'], 2) ?>
                                    </td>
                                    <td style="font-size: 10.5px; color: var(--text-muted); font-variant-numeric: tabular-nums;">
                                        <?= htmlspecialchars($item['last_seen']) ?>
                                    </td>
                                    <td style="font-size: 11px; color: var(--text-secondary); max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <?= htmlspecialchars($item['sample_customer'] ?: '—') ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($item['is_mapped']): ?>
                                        <span class="badge badge-active" title="<?= htmlspecialchars($item['mapped_rule']['canonical_name'] ?? '') ?>"><i class="icon-check" style="font-size: 8px;"></i> Mapped</span>
                                        <?php else: ?>
                                        <span class="badge badge-overdue" style="background: #fef3c7; color: #92400e; border-color: #fde68a;">Needs Rule</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn-cmd" onclick="quickMap(<?= htmlspecialchars(json_encode($item['description'])) ?>)" style="height: 24px; padding: 0 8px; font-size: 10.5px;">
                                            <i class="icon-wand" style="font-size: 10px;"></i>
                                            <span>Quick Map</span>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </main>
    </div>

    <!-- Modal: Rule Builder / Quick-Map -->
    <div class="pm-modal-overlay" id="ruleModalOverlay">
        <div class="pm-modal-box">
            <div class="pm-modal-header">
                <div class="pm-modal-title">
                    <i class="icon-sliders" style="color: var(--primary);"></i>
                    <span id="modalHeaderTitle">Add Product Mapping Rule</span>
                </div>
                <button type="button" class="pm-modal-close" onclick="closeRuleModal()">
                    <i class="icon-x"></i>
                </button>
            </div>

            <form method="POST" action="product_mapping.php?tab=<?= $activeTab ?>" id="ruleForm">
                <input type="hidden" name="action" value="save_mapping_rule">
                <input type="hidden" name="id" id="formRuleId" value="">

                <div class="pm-modal-body" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <!-- Pattern -->
                    <div style="grid-column: span 2;">
                        <label style="font-size: 11px; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">
                            Matching Pattern <span style="color: #dc2626;">*</span>
                        </label>
                        <input type="text" name="pattern" id="formPattern" required class="search-input-sm" style="width: 100%; font-family: monospace;" placeholder="e.g. DS3018xs*Rent* or HAT3300-4T">
                        <span style="font-size: 10px; color: var(--text-muted); margin-top: 3px; display: block;">Supports * or % as wildcard characters for flexible matching.</span>
                    </div>

                    <!-- Match Type -->
                    <div>
                        <label style="font-size: 11px; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">Match Type</label>
                        <select name="match_type" id="formMatchType" class="filter-select-sm" style="width: 100%;">
                            <option value="CONTAINS">CONTAINS (Wildcard)</option>
                            <option value="EXACT">EXACT</option>
                            <option value="REGEX">REGEX (Regular Expression)</option>
                        </select>
                    </div>

                    <!-- Priority -->
                    <div>
                        <label style="font-size: 11px; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">Evaluation Priority</label>
                        <input type="number" name="priority" id="formPriority" value="10" min="1" max="100" class="search-input-sm" style="width: 100%;">
                        <span style="font-size: 10px; color: var(--text-muted); margin-top: 2px; display: block;">Lower numbers evaluated first (1 = highest).</span>
                    </div>

                    <!-- Master SKU -->
                    <div>
                        <label style="font-size: 11px; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">Master SKU Code</label>
                        <input type="text" name="master_sku" id="formMasterSku" class="search-input-sm" style="width: 100%; font-family: monospace;" placeholder="e.g. SYN-RENT-DS3018XS">
                    </div>

                    <!-- Brand -->
                    <div>
                        <label style="font-size: 11px; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">Brand / Manufacturer</label>
                        <input type="text" name="brand" id="formBrand" class="search-input-sm" style="width: 100%;" placeholder="e.g. Synology, Seagate, DrayTek">
                    </div>

                    <!-- Canonical Product Name -->
                    <div style="grid-column: span 2;">
                        <label style="font-size: 11px; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">
                            Canonical Product Name <span style="color: #dc2626;">*</span>
                        </label>
                        <input type="text" name="canonical_name" id="formCanonicalName" required class="search-input-sm" style="width: 100%; font-weight: 600;" placeholder="e.g. Synology DS3018xs (Rental Deployment)">
                    </div>

                    <!-- Commercial Classification -->
                    <div>
                        <label style="font-size: 11px; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">Commercial Classification</label>
                        <select name="commercial_type" id="formCommercialType" class="filter-select-sm" style="width: 100%; font-weight: 600;">
                            <option value="OUTRIGHT_SALE">Outright Sale</option>
                            <option value="RENTAL" style="color: #7c3aed; font-weight: 700;">Rental Fleet (Recurring Lease)</option>
                            <option value="SOFTWARE">Software License / SaaS</option>
                            <option value="MAINTENANCE">Maintenance Agreement (MA/AMC)</option>
                            <option value="SERVICE">Professional Service</option>
                        </select>
                    </div>

                    <!-- Default VAT Treatment -->
                    <div>
                        <label style="font-size: 11px; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">Default VAT Treatment</label>
                        <select name="default_vat_treatment" id="formVatTreatment" class="filter-select-sm" style="width: 100%;">
                            <option value="DEFAULT">DEFAULT (Auto by Customer Profile)</option>
                            <option value="PLUS_VAT">PLUS_VAT (Exclusive of VAT)</option>
                            <option value="VAT_INCLUSIVE">VAT_INCLUSIVE (Inclusive of VAT)</option>
                            <option value="VAT_EXEMPT">VAT_EXEMPT (0% Exemption)</option>
                        </select>
                    </div>

                    <!-- Notes -->
                    <div style="grid-column: span 2;">
                        <label style="font-size: 11px; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">Internal Notes / Commercial Terms</label>
                        <input type="text" name="notes" id="formNotes" class="search-input-sm" style="width: 100%;" placeholder="e.g. Enterprise monthly rental contract terms">
                    </div>

                    <!-- Immediate Re-sort Checkbox -->
                    <div style="grid-column: span 2; padding-top: 4px;">
                        <label style="font-size: 11px; color: #334155; display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
                            <input type="checkbox" name="auto_resort" value="1" checked>
                            <span style="font-weight: 600;">Re-sort and update matching historical invoices immediately</span>
                        </label>
                    </div>
                </div>

                <div class="pm-modal-footer">
                    <button type="button" class="btn-cmd" onclick="closeRuleModal()">Cancel</button>
                    <button type="submit" class="btn-cmd btn-cmd-primary">
                        <i class="icon-check" style="font-size: 11px;"></i>
                        <span>Save Mapping Rule</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openRuleModal() {
            document.getElementById('formRuleId').value = '';
            document.getElementById('ruleForm').reset();
            document.getElementById('modalHeaderTitle').innerText = 'Add Product Mapping Rule';
            document.getElementById('ruleModalOverlay').classList.add('show');
        }

        function closeRuleModal() {
            document.getElementById('ruleModalOverlay').classList.remove('show');
        }

        function editRule(rule) {
            document.getElementById('formRuleId').value = rule.id || '';
            document.getElementById('formPattern').value = rule.pattern || '';
            document.getElementById('formMatchType').value = rule.match_type || 'CONTAINS';
            document.getElementById('formPriority').value = rule.priority || 10;
            document.getElementById('formMasterSku').value = rule.master_sku || '';
            document.getElementById('formBrand').value = rule.brand || '';
            document.getElementById('formCanonicalName').value = rule.canonical_name || '';
            document.getElementById('formCommercialType').value = rule.commercial_type || 'OUTRIGHT_SALE';
            document.getElementById('formVatTreatment').value = rule.default_vat_treatment || 'DEFAULT';
            document.getElementById('formNotes').value = rule.notes || '';
            document.getElementById('modalHeaderTitle').innerText = 'Edit Mapping Rule #' + rule.id;
            document.getElementById('ruleModalOverlay').classList.add('show');
        }

        function quickMap(rawDesc) {
            openRuleModal();
            document.getElementById('formPattern').value = rawDesc;
            document.getElementById('formCanonicalName').value = rawDesc;
            
            // Auto detect brand
            var descLower = rawDesc.toLowerCase();
            if (descLower.indexOf('synology') !== -1) {
                document.getElementById('formBrand').value = 'Synology';
            } else if (descLower.indexOf('seagate') !== -1 || descLower.indexOf('ironwolf') !== -1 || descLower.indexOf('exos') !== -1) {
                document.getElementById('formBrand').value = 'Seagate';
            } else if (descLower.indexOf('draytek') !== -1 || descLower.indexOf('vigor') !== -1) {
                document.getElementById('formBrand').value = 'DrayTek';
            }

            // Auto detect commercial type
            if (descLower.indexOf('rent') !== -1 || descLower.indexOf('lease') !== -1 || descLower.indexOf('hire') !== -1) {
                document.getElementById('formCommercialType').value = 'RENTAL';
                document.getElementById('formPriority').value = 1;
            } else if (descLower.indexOf('license') !== -1 || descLower.indexOf('subscription') !== -1) {
                document.getElementById('formCommercialType').value = 'SOFTWARE';
                document.getElementById('formPriority').value = 5;
            } else if (descLower.indexOf('maintenance') !== -1 || descLower.indexOf(' amc') !== -1) {
                document.getElementById('formCommercialType').value = 'MAINTENANCE';
                document.getElementById('formPriority').value = 5;
            } else {
                document.getElementById('formCommercialType').value = 'OUTRIGHT_SALE';
                document.getElementById('formPriority').value = 10;
            }

            document.getElementById('modalHeaderTitle').innerText = 'Quick Map Rule for: ' + rawDesc.substring(0, 30) + '...';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            var overlay = document.getElementById('ruleModalOverlay');
            if (event.target === overlay) {
                closeRuleModal();
            }
        };
    </script>
    <?php require_once 'includes/layout_js.php'; ?>
</body>
</html>
