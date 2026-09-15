<?php
/**
 * Product, Brand & Category Mapping Center
 * Activity Sales BI - Master Catalog, Brand & Category Taxonomy, Extracted Line Items & Rental Fleet Ledger
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

// Handle AJAX or POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $isAjax = !empty($_POST['ajax']) || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

    // 1. Save Mapping Rule
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

    // 2. Delete Mapping Rule
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

    // 3. Save Master Brand
    if ($action === 'save_brand') {
        try {
            $id = $db->saveBrand($_POST);
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'id' => $id, 'name' => $_POST['name']]);
                exit;
            }
            $message = 'Master brand saved successfully.';
            $messageType = 'success';
            $db->logActivity($user['id'], 'BRAND_SAVED', "Saved master brand: " . ($_POST['name'] ?? ''));
        } catch (Exception $e) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                exit;
            }
            $message = 'Error saving brand: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    // 4. Delete Master Brand
    if ($action === 'delete_brand') {
        try {
            $id = (int)($_POST['id'] ?? 0);
            $db->deleteBrand($id);
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                exit;
            }
            $message = 'Brand deleted and tagged products reset to Other.';
            $messageType = 'success';
            $db->logActivity($user['id'], 'BRAND_DELETED', "Deleted brand ID #$id");
        } catch (Exception $e) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                exit;
            }
            $message = 'Error deleting brand: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    // 5. Save Master Category
    if ($action === 'save_category') {
        try {
            $id = $db->saveCategory($_POST);
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'id' => $id, 'name' => $_POST['name']]);
                exit;
            }
            $message = 'Master category saved successfully.';
            $messageType = 'success';
            $db->logActivity($user['id'], 'CATEGORY_SAVED', "Saved master category: " . ($_POST['name'] ?? ''));
        } catch (Exception $e) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                exit;
            }
            $message = 'Error saving category: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    // 6. Delete Master Category
    if ($action === 'delete_category') {
        try {
            $id = (int)($_POST['id'] ?? 0);
            $db->deleteCategory($id);
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                exit;
            }
            $message = 'Category deleted and tagged products reset to Other / Unassigned.';
            $messageType = 'success';
            $db->logActivity($user['id'], 'CATEGORY_DELETED', "Deleted category ID #$id");
        } catch (Exception $e) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                exit;
            }
            $message = 'Error deleting category: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    // 7. Inline Single Product Brand/Category Assignment (AJAX)
    if ($action === 'assign_product') {
        try {
            $itemId = (int)($_POST['item_id'] ?? 0);
            $brand = isset($_POST['brand']) ? trim($_POST['brand']) : null;
            $category = isset($_POST['category']) ? trim($_POST['category']) : null;
            $reports->assignProductBrandCategory($itemId, $brand, $category);
            
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'item_id' => $itemId]);
                exit;
            }
            $message = 'Product updated successfully.';
            $messageType = 'success';
        } catch (Exception $e) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                exit;
            }
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    // 8. Bulk Product Brand/Category Assignment
    if ($action === 'bulk_assign_products') {
        try {
            $rawIds = $_POST['item_ids'] ?? [];
            if (is_string($rawIds)) {
                $rawIds = explode(',', $rawIds);
            }
            $brand = trim($_POST['brand'] ?? '');
            $category = trim($_POST['category'] ?? '');
            
            $updatedCount = $reports->bulkAssignProducts($rawIds, $brand ?: null, $category ?: null);
            
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'updated' => $updatedCount]);
                exit;
            }
            $message = "Successfully assigned brand/category to $updatedCount selected products.";
            $messageType = 'success';
            $db->logActivity($user['id'], 'BULK_PRODUCT_ASSIGNED', "Bulk assigned $updatedCount products");
        } catch (Exception $e) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                exit;
            }
            $message = 'Error during bulk assignment: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    // 9. Auto-Classify Extracted Products with Rules
    if ($action === 'auto_classify') {
        try {
            $count = $reports->autoClassifyExtractedProducts();
            $message = "Auto-classification complete! Classified $count products based on active mapping rules.";
            $messageType = 'success';
            $db->logActivity($user['id'], 'AUTO_CLASSIFICATION_RUN', "Auto-classified $count products");
        } catch (Exception $e) {
            $message = 'Error during auto-classification: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    // 10. Re-Sync Rentals
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
if (!in_array($activeTab, ['mappings', 'products', 'taxonomy', 'rentals', 'unmapped'])) {
    $activeTab = 'mappings';
}

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 50;

// Master Brands and Categories
$masterBrands = $db->getBrands();
$masterCategories = $db->getCategories();

// Common Filters
$filters = [
    'search' => trim($_GET['search'] ?? ''),
    'commercial_type' => $_GET['commercial_type'] ?? 'ALL',
    'match_type' => $_GET['match_type'] ?? 'ALL',
    'status' => $_GET['status'] ?? ($activeTab === 'products' ? 'UNASSIGNED' : 'ALL'),
    'brand' => $_GET['brand'] ?? 'ALL',
    'category' => $_GET['category'] ?? 'ALL',
    'product_type' => $_GET['product_type'] ?? 'ALL'
];

// Fetch Data based on active tab
$mappingData = ($activeTab === 'mappings') ? $reports->getProductMappings($filters, $page, $limit) : ['rules' => [], 'total' => 0, 'pages' => 1, 'kpis' => []];
$rentalData = ($activeTab === 'rentals') ? $reports->getRentalFleet($filters, $page, $limit) : ['deployments' => [], 'total' => 0, 'pages' => 1];
$unmappedData = ($activeTab === 'unmapped') ? $reports->getUnmappedDescriptions(60) : [];
$extractedData = ($activeTab === 'products') ? $reports->getExtractedProducts($filters, $page, $limit) : ['items' => [], 'total' => 0, 'pages' => 1, 'kpis' => []];

// Summary KPIs
$mappingKpis = $reports->getProductMappings([], 1, 1)['kpis'] ?? [];
$rentalSummary = $reports->getRentalSummary();
$productKpis = ($activeTab === 'products') ? $extractedData['kpis'] : ($reports->getExtractedProducts([], 1, 1)['kpis'] ?? []);

$currency = $db->getSetting('currency_symbol', 'LKR ');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product & Brand/Category Mapping Center - Activity Sales BI</title>
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
            transition: all 0.15s ease;
        }
        .filter-select-sm:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.1);
        }
        .search-input-sm {
            height: 28px;
            padding: 0 10px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            font-size: 11px;
            width: 220px;
            outline: none;
            transition: all 0.15s ease;
        }
        .search-input-sm:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.1);
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
            gap: 5px;
            border: 1px solid var(--border-color);
            background: #ffffff;
            color: var(--text-main);
            text-decoration: none;
            transition: all 0.15s ease;
            white-space: nowrap;
        }
        .btn-cmd:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }
        .btn-cmd-primary {
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
        }
        .btn-cmd-primary:hover {
            background: #1d4ed8;
            border-color: #1d4ed8;
            color: #ffffff;
        }
        .btn-cmd-emerald {
            background: #059669;
            color: #ffffff;
            border-color: #059669;
        }
        .btn-cmd-emerald:hover {
            background: #047857;
            color: #ffffff;
        }
        .btn-cmd-purple {
            background: #7c3aed;
            color: #ffffff;
            border-color: #7c3aed;
        }
        .btn-cmd-purple:hover {
            background: #6d28d9;
            color: #ffffff;
        }
        .action-icon-btn {
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 3px 6px;
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
        .color-badge-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            flex-shrink: 0;
        }
        .color-badge-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 2px 7px;
            border-radius: 12px;
            font-size: 10.5px;
            font-weight: 600;
            background: #f1f5f9;
            color: #334155;
            border: 1px solid #e2e8f0;
        }
        .bulk-bar {
            position: sticky;
            top: 0;
            z-index: 15;
            background: #1e293b;
            color: #f8fafc;
            padding: 8px 14px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            animation: slideDown 0.2s ease;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .quick-flash {
            animation: flashGreen 0.7s ease;
        }
        @keyframes flashGreen {
            0% { background-color: #d1fae5; }
            100% { background-color: transparent; }
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
                                <span>Mapping Rules</span>
                                <span class="pm-count-badge"><?= $mappingKpis['total_rules'] ?? 0 ?></span>
                            </a>
                            <a href="product_mapping.php?tab=products" class="pm-nav-tab <?= $activeTab === 'products' ? 'active' : '' ?>">
                                <i class="icon-box" style="font-size: 12px;"></i>
                                <span>Extracted Products</span>
                                <span class="pm-count-badge" style="<?= ($productKpis['unassigned_items'] ?? 0) > 0 ? 'background: #ea580c; color: #fff;' : '' ?>" title="<?= number_format($productKpis['unassigned_items'] ?? 0) ?> items need brand/category assignment">
                                    <?= ($productKpis['unassigned_items'] ?? 0) > 0 ? number_format($productKpis['unassigned_items']) . ' unassigned' : number_format($productKpis['total_items'] ?? 0) ?>
                                </span>
                            </a>
                            <a href="product_mapping.php?tab=taxonomy" class="pm-nav-tab <?= $activeTab === 'taxonomy' ? 'active' : '' ?>">
                                <i class="icon-tag" style="font-size: 12px;"></i>
                                <span>Brands & Categories</span>
                                <span class="pm-count-badge"><?= count($masterBrands) ?> / <?= count($masterCategories) ?></span>
                            </a>
                            <a href="product_mapping.php?tab=rentals" class="pm-nav-tab <?= $activeTab === 'rentals' ? 'active' : '' ?>">
                                <i class="icon-repeat" style="font-size: 12px;"></i>
                                <span>Rental Fleet</span>
                                <span class="pm-count-badge" style="<?= ($rentalSummary['active_count'] ?? 0) > 0 ? 'background: #7c3aed; color: #fff;' : '' ?>"><?= $rentalSummary['total_rentals'] ?? 0 ?></span>
                            </a>
                            <a href="product_mapping.php?tab=unmapped" class="pm-nav-tab <?= $activeTab === 'unmapped' ? 'active' : '' ?>">
                                <i class="icon-list-filter" style="font-size: 12px;"></i>
                                <span>Raw Descriptions</span>
                                <span class="pm-count-badge"><?= count($unmappedData) ?></span>
                            </a>
                        </div>
                    </div>

                    <div class="cmd-right">
                        <?php if ($activeTab === 'products'): ?>
                        <form method="POST" style="margin: 0; display: inline-flex;">
                            <input type="hidden" name="action" value="auto_classify">
                            <button type="submit" class="btn-cmd btn-cmd-purple" title="Automatically classify unassigned items using pattern rules and brand heuristics">
                                <i class="icon-zap" style="font-size: 11px;"></i>
                                <span>Auto-Classify with Rules</span>
                            </button>
                        </form>
                        <?php elseif ($activeTab === 'taxonomy'): ?>
                        <button type="button" class="btn-cmd btn-cmd-primary" onclick="openBrandModal()">
                            <i class="icon-plus" style="font-size: 11px;"></i>
                            <span>New Brand</span>
                        </button>
                        <button type="button" class="btn-cmd btn-cmd-emerald" onclick="openCategoryModal()">
                            <i class="icon-plus" style="font-size: 11px;"></i>
                            <span>New Category</span>
                        </button>
                        <?php elseif ($activeTab === 'rentals'): ?>
                        <form method="POST" style="margin: 0; display: inline-flex;">
                            <input type="hidden" name="action" value="resync_rentals">
                            <button type="submit" class="btn-cmd" title="Re-sort rental invoices from QuickBooks sales lines">
                                <i class="icon-refresh-cw" style="font-size: 11px;"></i>
                                <span>Re-Sync Rentals</span>
                            </button>
                        </form>
                        <?php else: ?>
                        <button type="button" class="btn-cmd btn-cmd-primary" onclick="openRuleModal()">
                            <i class="icon-plus" style="font-size: 12px;"></i>
                            <span>New Mapping Rule</span>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Contextual Metrics Ribbon -->
                <div class="metrics-strip">
                    <?php if ($activeTab === 'products'): ?>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Total Extracted Items</span>
                        <span class="metric-pill-val"><?= number_format($productKpis['total_items'] ?? 0) ?></span>
                        <span class="metric-pill-sub">Consolidated commercial products</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Unassigned / Other</span>
                        <span class="metric-pill-val" style="color: <?= ($productKpis['unassigned_items'] ?? 0) > 0 ? '#ea580c' : '#15803d' ?>;">
                            <?= number_format($productKpis['unassigned_items'] ?? 0) ?>
                        </span>
                        <span class="metric-pill-sub">Pending brand or category assignment</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Unassigned Portfolio Value</span>
                        <span class="metric-pill-val" style="color: #ea580c;"><?= $currency . number_format($productKpis['unassigned_gross'] ?? 0, 0) ?></span>
                        <span class="metric-pill-sub">Volume requiring categorization</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Active Master Brands</span>
                        <span class="metric-pill-val" style="color: var(--primary);"><?= number_format($productKpis['total_brands_count'] ?? count($masterBrands)) ?></span>
                        <span class="metric-pill-sub">In operational catalog</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Active Master Categories</span>
                        <span class="metric-pill-val" style="color: #059669;"><?= number_format($productKpis['total_categories_count'] ?? count($masterCategories)) ?></span>
                        <span class="metric-pill-sub">Product segmentation groups</span>
                    </div>

                    <?php elseif ($activeTab === 'taxonomy'): ?>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Registered Brands</span>
                        <span class="metric-pill-val" style="color: var(--primary);"><?= count($masterBrands) ?></span>
                        <span class="metric-pill-sub">Hardware & software makers</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Registered Categories</span>
                        <span class="metric-pill-val" style="color: #059669;"><?= count($masterCategories) ?></span>
                        <span class="metric-pill-sub">Strategic commercial verticals</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Tagged Products</span>
                        <span class="metric-pill-val"><?= number_format(($productKpis['total_items'] ?? 0) - ($productKpis['unassigned_items'] ?? 0)) ?></span>
                        <span class="metric-pill-sub">Fully classified invoice lines</span>
                    </div>
                    <div class="metric-pill">
                        <span class="metric-pill-label">Portfolio Lifetime Volume</span>
                        <span class="metric-pill-val"><?= $currency . number_format($productKpis['total_gross'] ?? 0, 0) ?></span>
                        <span class="metric-pill-sub">Gross all-time revenue</span>
                    </div>

                    <?php elseif ($activeTab === 'rentals'): ?>
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
                                    <th>Category</th>
                                    <th>VAT Default</th>
                                    <th>Notes</th>
                                    <th style="width: 60px;" class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($mappingData['rules'])): ?>
                                <tr>
                                    <td colspan="11" style="text-align: center; padding: 35px 0; color: var(--text-muted);">
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
                                            <span class="badge badge-rental"><i class="icon-repeat" style="font-size: 9px;"></i> Rental</span>
                                            <?php elseif ($rule['commercial_type'] === 'OUTRIGHT_SALE'): ?>
                                            <span class="badge badge-sale"><i class="icon-box" style="font-size: 9px;"></i> Sale</span>
                                            <?php elseif ($rule['commercial_type'] === 'SOFTWARE'): ?>
                                            <span class="badge badge-license"><i class="icon-shield-check" style="font-size: 9px;"></i> Software</span>
                                            <?php elseif ($rule['commercial_type'] === 'MAINTENANCE'): ?>
                                            <span class="badge badge-amc"><i class="icon-wrench" style="font-size: 9px;"></i> AMC/MA</span>
                                            <?php else: ?>
                                            <span class="badge badge-service"><?= htmlspecialchars($rule['commercial_type']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="color: var(--text-secondary); font-size: 11px;">
                                            <?= htmlspecialchars($rule['brand'] ?: '—') ?>
                                        </td>
                                        <td style="color: var(--text-secondary); font-size: 11px;">
                                            <?= htmlspecialchars($rule['category'] ?: ($rule['product_category'] ?: '—')) ?>
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
                        <div>Showing page <?= $page ?> of <?= $mappingData['pages'] ?> (<?= number_format($mappingData['total']) ?> rules)</div>
                        <div class="pagination-pages">
                            <?php for ($p = 1; $p <= $mappingData['pages']; $p++): ?>
                            <a href="product_mapping.php?tab=mappings&page=<?= $p ?>&search=<?= urlencode($filters['search']) ?>&commercial_type=<?= urlencode($filters['commercial_type']) ?>&match_type=<?= urlencode($filters['match_type']) ?>" class="page-link <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- TAB 2: EXTRACTED PRODUCTS ASSIGNMENT -->
                <?php if ($activeTab === 'products'): ?>
                <div class="card" style="padding: 0; overflow: hidden;">
                    <!-- Filter Toolbar -->
                    <form method="GET" action="product_mapping.php" class="toolbar" style="border-radius: 0; border: none; border-bottom: 1px solid var(--border-color); flex-wrap: wrap; gap: 6px;">
                        <input type="hidden" name="tab" value="products">
                        <div class="filter-group" style="flex-wrap: wrap; gap: 6px;">
                            <input type="text" name="search" class="search-input-sm" placeholder="Search product name, customer, invoice #..." value="<?= htmlspecialchars($filters['search']) ?>" style="width: 200px;">
                            
                            <!-- Assignment Status -->
                            <select name="status" class="filter-select-sm" style="font-weight: 700; color: <?= $filters['status'] === 'UNASSIGNED' ? '#ea580c' : '#0f172a' ?>;">
                                <option value="UNASSIGNED" <?= $filters['status'] === 'UNASSIGNED' ? 'selected' : '' ?>>⚠️ Needs Assignment (Unassigned/Other)</option>
                                <option value="ALL" <?= $filters['status'] === 'ALL' ? 'selected' : '' ?>>All Extracted Products (10.7k)</option>
                                <option value="ASSIGNED" <?= $filters['status'] === 'ASSIGNED' ? 'selected' : '' ?>>✅ Assigned Only</option>
                            </select>

                            <!-- Brand Filter -->
                            <select name="brand" class="filter-select-sm">
                                <option value="ALL">All Brands</option>
                                <?php foreach ($masterBrands as $b): ?>
                                <option value="<?= htmlspecialchars($b['name']) ?>" <?= $filters['brand'] === $b['name'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($b['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>

                            <!-- Category Filter -->
                            <select name="category" class="filter-select-sm">
                                <option value="ALL">All Categories</option>
                                <?php foreach ($masterCategories as $c): ?>
                                <option value="<?= htmlspecialchars($c['name']) ?>" <?= $filters['category'] === $c['name'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>

                            <!-- Product Type Filter -->
                            <select name="product_type" class="filter-select-sm">
                                <option value="ALL">All Types</option>
                                <option value="HARDWARE" <?= $filters['product_type'] === 'HARDWARE' ? 'selected' : '' ?>>HARDWARE</option>
                                <option value="SERVICE" <?= $filters['product_type'] === 'SERVICE' ? 'selected' : '' ?>>SERVICE</option>
                                <option value="SOFTWARE" <?= $filters['product_type'] === 'SOFTWARE' ? 'selected' : '' ?>>SOFTWARE</option>
                                <option value="MAINTENANCE" <?= $filters['product_type'] === 'MAINTENANCE' ? 'selected' : '' ?>>MAINTENANCE</option>
                                <option value="RENTAL" <?= $filters['product_type'] === 'RENTAL' ? 'selected' : '' ?>>RENTAL</option>
                                <option value="ACCESSORY" <?= $filters['product_type'] === 'ACCESSORY' ? 'selected' : '' ?>>ACCESSORY</option>
                            </select>

                            <button type="submit" class="btn-cmd">
                                <i class="icon-filter" style="font-size: 11px;"></i>
                                <span>Filter</span>
                            </button>

                            <?php if (!empty($filters['search']) || $filters['status'] !== 'UNASSIGNED' || $filters['brand'] !== 'ALL' || $filters['category'] !== 'ALL' || $filters['product_type'] !== 'ALL'): ?>
                            <a href="product_mapping.php?tab=products&status=UNASSIGNED" class="btn-cmd" style="color: var(--text-muted);">Reset</a>
                            <?php endif; ?>
                        </div>

                        <div style="font-size: 11px; color: var(--text-muted); white-space: nowrap;">
                            Showing <?= count($extractedData['items']) ?> of <?= number_format($extractedData['total']) ?> products
                        </div>
                    </form>

                    <!-- Sticky Bulk Action Bar (Visible when items selected) -->
                    <div id="bulkActionBar" class="bulk-bar" style="display: none; margin: 8px 12px;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <span style="font-weight: 700; font-size: 12px;" id="selectedCountText">0 items selected</span>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <select id="bulkBrandSelect" class="filter-select-sm" style="background: #334155; color: #fff; border-color: #475569;">
                                    <option value="">-- Assign Brand --</option>
                                    <?php foreach ($masterBrands as $b): ?>
                                    <option value="<?= htmlspecialchars($b['name']) ?>"><?= htmlspecialchars($b['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>

                                <select id="bulkCategorySelect" class="filter-select-sm" style="background: #334155; color: #fff; border-color: #475569;">
                                    <option value="">-- Assign Category --</option>
                                    <?php foreach ($masterCategories as $c): ?>
                                    <option value="<?= htmlspecialchars($c['name']) ?>"><?= htmlspecialchars($c['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>

                                <button type="button" class="btn-cmd btn-cmd-primary" onclick="applyBulkAssignment()">
                                    <i class="icon-check-check" style="font-size: 12px;"></i>
                                    <span>Apply to Selected</span>
                                </button>
                            </div>
                        </div>
                        <button type="button" class="action-icon-btn" onclick="clearProductSelections()" style="color: #cbd5e1;" title="Deselect All">
                            <i class="icon-x"></i>
                        </button>
                    </div>

                    <!-- Products Table -->
                    <div style="overflow-x: auto;">
                        <table class="dense-table" style="width: 100%;">
                            <thead>
                                <tr>
                                    <th style="width: 32px;" class="text-center">
                                        <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this)">
                                    </th>
                                    <th style="width: 110px;">Invoice & Date</th>
                                    <th>Customer</th>
                                    <th>Extracted Product Name</th>
                                    <th style="width: 90px;">Type</th>
                                    <th style="width: 155px;">Assigned Brand</th>
                                    <th style="width: 195px;">Assigned Category</th>
                                    <th class="text-right" style="width: 105px;">Total Amount</th>
                                    <th style="width: 65px;" class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($extractedData['items'])): ?>
                                <tr>
                                    <td colspan="9" style="text-align: center; padding: 45px 0; color: var(--text-muted);">
                                        <i class="icon-check-circle" style="font-size: 28px; color: #10b981; display: block; margin-bottom: 8px;"></i>
                                        <?= $filters['status'] === 'UNASSIGNED' ? 'Great news! No unassigned products found matching your filter criteria.' : 'No extracted products found.' ?>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($extractedData['items'] as $item): 
                                        $isUnassigned = ($item['brand'] === 'Other' || $item['category'] === 'Other / Unassigned');
                                    ?>
                                    <tr id="row-item-<?= $item['id'] ?>" class="<?= $isUnassigned ? 'row-unassigned' : '' ?>">
                                        <td class="text-center">
                                            <input type="checkbox" class="product-item-checkbox" value="<?= $item['id'] ?>" onchange="updateBulkBar()">
                                        </td>
                                        <td>
                                            <a href="reports.php?type=invoices&search=<?= urlencode($item['invoice_number']) ?>" style="font-weight: 700; color: var(--primary); text-decoration: none; font-size: 11px;">
                                                #<?= htmlspecialchars($item['invoice_number']) ?>
                                            </a>
                                            <div style="font-size: 10px; color: var(--text-muted); font-variant-numeric: tabular-nums;">
                                                <?= htmlspecialchars($item['invoice_date']) ?>
                                            </div>
                                        </td>
                                        <td style="font-size: 11px; color: #1e293b; max-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($item['customer_name']) ?>">
                                            <?= htmlspecialchars($item['customer_name']) ?>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; color: #0f172a; font-size: 11.5px;"><?= htmlspecialchars($item['clean_product_name']) ?></div>
                                            <div style="font-size: 10px; color: var(--text-muted);">Qty: <?= (float)$item['quantity'] ?> &times; <?= $currency . number_format($item['unit_price'] ?? 0, 2) ?></div>
                                        </td>
                                        <td>
                                            <span class="badge" style="font-size: 9.5px; font-weight: 700; background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0;">
                                                <?= htmlspecialchars($item['product_type']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <select class="filter-select-sm" style="width: 100%; font-weight: 600; font-size: 10.5px; border-color: <?= $item['brand'] === 'Other' ? '#f59e0b' : '#cbd5e1' ?>;" onchange="quickAssign(<?= $item['id'] ?>, this.value, null, this)">
                                                <?php foreach ($masterBrands as $b): ?>
                                                <option value="<?= htmlspecialchars($b['name']) ?>" <?= $item['brand'] === $b['name'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($b['name']) ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <select class="filter-select-sm" style="width: 100%; font-weight: 600; font-size: 10.5px; border-color: <?= $item['category'] === 'Other / Unassigned' ? '#f59e0b' : '#cbd5e1' ?>;" onchange="quickAssign(<?= $item['id'] ?>, null, this.value, this)">
                                                <?php foreach ($masterCategories as $c): ?>
                                                <option value="<?= htmlspecialchars($c['name']) ?>" <?= $item['category'] === $c['name'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($c['name']) ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td class="text-right" style="font-variant-numeric: tabular-nums; font-weight: 700; color: #0f172a;">
                                            <?= $currency . number_format($item['total_amount'], 2) ?>
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn-cmd" style="padding: 2px 6px; font-size: 10px;" onclick='createRuleFromProduct(<?= json_encode($item) ?>)' title="Create Pattern Mapping Rule from this product">
                                                <i class="icon-plus-circle" style="font-size: 10px;"></i>
                                                <span>Rule</span>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($extractedData['pages'] > 1): ?>
                    <div class="pagination-container">
                        <div>Showing page <?= $page ?> of <?= $extractedData['pages'] ?> (<?= number_format($extractedData['total']) ?> products)</div>
                        <div class="pagination-pages">
                            <?php for ($p = 1; $p <= $extractedData['pages']; $p++): ?>
                            <a href="product_mapping.php?tab=products&page=<?= $p ?>&status=<?= urlencode($filters['status']) ?>&brand=<?= urlencode($filters['brand']) ?>&category=<?= urlencode($filters['category']) ?>&product_type=<?= urlencode($filters['product_type']) ?>&search=<?= urlencode($filters['search']) ?>" class="page-link <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- TAB 3: TAXONOMY MASTER MANAGEMENT (BRANDS & CATEGORIES) -->
                <?php if ($activeTab === 'taxonomy'): ?>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <!-- Left: Master Brands Card -->
                    <div class="card" style="padding: 0; overflow: hidden;">
                        <div style="padding: 10px 14px; background: #f8fafc; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between;">
                            <div>
                                <span style="font-size: 12px; font-weight: 700; color: #0f172a;">Master Brands Directory</span>
                                <span style="font-size: 11px; color: var(--text-muted); margin-left: 6px;">(<?= count($masterBrands) ?> brands)</span>
                            </div>
                            <button type="button" class="btn-cmd btn-cmd-primary" onclick="openBrandModal()">
                                <i class="icon-plus" style="font-size: 11px;"></i>
                                <span>Add Brand</span>
                            </button>
                        </div>

                        <div style="overflow-x: auto;">
                            <table class="dense-table" style="width: 100%;">
                                <thead>
                                    <tr>
                                        <th>Brand Name</th>
                                        <th style="width: 60px;">Code</th>
                                        <th class="text-right" style="width: 75px;">Products</th>
                                        <th class="text-right" style="width: 120px;">Lifetime Sales</th>
                                        <th style="width: 65px;" class="text-center">Status</th>
                                        <th style="width: 55px;" class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($masterBrands as $b): ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 6px;">
                                                <span class="color-badge-dot" style="background-color: <?= htmlspecialchars($b['color'] ?: '#2563eb') ?>;"></span>
                                                <span style="font-weight: 700; color: #0f172a;"><?= htmlspecialchars($b['name']) ?></span>
                                            </div>
                                            <?php if (!empty($b['description'])): ?>
                                            <div style="font-size: 10px; color: var(--text-muted); margin-top: 2px;"><?= htmlspecialchars($b['description']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <code style="font-size: 10px; font-weight: 700; padding: 1px 4px; background: #f1f5f9; border-radius: 3px;"><?= htmlspecialchars($b['code'] ?: '—') ?></code>
                                        </td>
                                        <td class="text-right" style="font-variant-numeric: tabular-nums; font-weight: 600;">
                                            <a href="product_mapping.php?tab=products&status=ALL&brand=<?= urlencode($b['name']) ?>" style="text-decoration: none; color: var(--primary);">
                                                <?= number_format($b['assigned_product_count'] ?? 0) ?>
                                            </a>
                                        </td>
                                        <td class="text-right" style="font-variant-numeric: tabular-nums; font-weight: 600; color: #0f172a;">
                                            <?= $currency . number_format($b['lifetime_revenue'] ?? 0, 0) ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge" style="background: <?= ($b['is_active'] ?? 1) ? '#ecfdf5' : '#f1f5f9' ?>; color: <?= ($b['is_active'] ?? 1) ? '#065f46' : '#64748b' ?>; font-size: 9.5px; font-weight: 700;">
                                                <?= ($b['is_active'] ?? 1) ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div style="display: inline-flex; align-items: center; gap: 2px;">
                                                <button type="button" class="action-icon-btn" onclick='editBrand(<?= json_encode($b) ?>)' title="Edit Brand">
                                                    <i class="icon-edit-2"></i>
                                                </button>
                                                <?php if ($b['name'] !== 'Other'): ?>
                                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete brand <?= htmlspecialchars($b['name']) ?>? Tagged products will be reset to Other.');" style="margin: 0; display: inline;">
                                                    <input type="hidden" name="action" value="delete_brand">
                                                    <input type="hidden" name="id" value="<?= $b['id'] ?>">
                                                    <button type="submit" class="action-icon-btn delete" title="Delete Brand">
                                                        <i class="icon-trash-2"></i>
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Right: Master Categories Card -->
                    <div class="card" style="padding: 0; overflow: hidden;">
                        <div style="padding: 10px 14px; background: #f8fafc; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between;">
                            <div>
                                <span style="font-size: 12px; font-weight: 700; color: #0f172a;">Master Categories Directory</span>
                                <span style="font-size: 11px; color: var(--text-muted); margin-left: 6px;">(<?= count($masterCategories) ?> categories)</span>
                            </div>
                            <button type="button" class="btn-cmd btn-cmd-emerald" onclick="openCategoryModal()">
                                <i class="icon-plus" style="font-size: 11px;"></i>
                                <span>Add Category</span>
                            </button>
                        </div>

                        <div style="overflow-x: auto;">
                            <table class="dense-table" style="width: 100%;">
                                <thead>
                                    <tr>
                                        <th>Category Name</th>
                                        <th style="width: 60px;">Code</th>
                                        <th class="text-right" style="width: 75px;">Products</th>
                                        <th class="text-right" style="width: 120px;">Lifetime Sales</th>
                                        <th style="width: 65px;" class="text-center">Status</th>
                                        <th style="width: 55px;" class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($masterCategories as $c): ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 6px;">
                                                <span class="color-badge-dot" style="background-color: <?= htmlspecialchars($c['color'] ?: '#059669') ?>;"></span>
                                                <span style="font-weight: 700; color: #0f172a;"><?= htmlspecialchars($c['name']) ?></span>
                                            </div>
                                            <?php if (!empty($c['description'])): ?>
                                            <div style="font-size: 10px; color: var(--text-muted); margin-top: 2px;"><?= htmlspecialchars($c['description']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <code style="font-size: 10px; font-weight: 700; padding: 1px 4px; background: #f1f5f9; border-radius: 3px;"><?= htmlspecialchars($c['code'] ?: '—') ?></code>
                                        </td>
                                        <td class="text-right" style="font-variant-numeric: tabular-nums; font-weight: 600;">
                                            <a href="product_mapping.php?tab=products&status=ALL&category=<?= urlencode($c['name']) ?>" style="text-decoration: none; color: #059669;">
                                                <?= number_format($c['assigned_product_count'] ?? 0) ?>
                                            </a>
                                        </td>
                                        <td class="text-right" style="font-variant-numeric: tabular-nums; font-weight: 600; color: #0f172a;">
                                            <?= $currency . number_format($c['lifetime_revenue'] ?? 0, 0) ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge" style="background: <?= ($c['is_active'] ?? 1) ? '#ecfdf5' : '#f1f5f9' ?>; color: <?= ($c['is_active'] ?? 1) ? '#065f46' : '#64748b' ?>; font-size: 9.5px; font-weight: 700;">
                                                <?= ($c['is_active'] ?? 1) ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div style="display: inline-flex; align-items: center; gap: 2px;">
                                                <button type="button" class="action-icon-btn" onclick='editCategory(<?= json_encode($c) ?>)' title="Edit Category">
                                                    <i class="icon-edit-2"></i>
                                                </button>
                                                <?php if ($c['name'] !== 'Other / Unassigned'): ?>
                                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete category <?= htmlspecialchars($c['name']) ?>? Tagged products will be reset to Other / Unassigned.');" style="margin: 0; display: inline;">
                                                    <input type="hidden" name="action" value="delete_category">
                                                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                                    <button type="submit" class="action-icon-btn delete" title="Delete Category">
                                                        <i class="icon-trash-2"></i>
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- TAB 4: RENTAL FLEET LEDGER -->
                <?php if ($activeTab === 'rentals'): ?>
                <div class="card" style="padding: 0; overflow: hidden;">
                    <!-- Toolbar & Filters -->
                    <form method="GET" action="product_mapping.php" class="toolbar" style="border-radius: 0; border: none; border-bottom: 1px solid var(--border-color);">
                        <input type="hidden" name="tab" value="rentals">
                        <div class="filter-group">
                            <input type="text" name="search" class="search-input-sm" placeholder="Search customer, system, serial..." value="<?= htmlspecialchars($filters['search']) ?>">
                            
                            <select name="status" class="filter-select-sm">
                                <option value="ALL">All Statuses</option>
                                <option value="ACTIVE" <?= $filters['status'] === 'ACTIVE' ? 'selected' : '' ?>>Active Billed (&le;35d)</option>
                                <option value="OVERDUE" <?= $filters['status'] === 'OVERDUE' ? 'selected' : '' ?>>Overdue Renewal (36-60d)</option>
                                <option value="SUSPENDED" <?= $filters['status'] === 'SUSPENDED' ? 'selected' : '' ?>>Suspended / Dormant (&gt;60d)</option>
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
                            Showing <?= count($rentalData['deployments']) ?> of <?= number_format($rentalData['total']) ?> rentals
                        </div>
                    </form>

                    <!-- Fleet Table -->
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

                <!-- TAB 5: UNMAPPED SALES QUEUE -->
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
                                        <button type="button" class="btn-cmd btn-cmd-primary" style="height: 24px; padding: 0 8px; font-size: 10.5px;" onclick="quickMap(<?= htmlspecialchars(json_encode($item['description'])) ?>)">
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

    <!-- MODAL 1: MAPPING RULE MODAL -->
    <div id="ruleModalOverlay" class="pm-modal-overlay">
        <div class="pm-modal">
            <div class="pm-modal-header">
                <span class="pm-modal-title" id="modalHeaderTitle">Add Product Mapping Rule</span>
                <button type="button" class="action-icon-btn" onclick="closeRuleModal()">
                    <i class="icon-x"></i>
                </button>
            </div>
            <form id="ruleForm" method="POST" action="product_mapping.php?tab=mappings">
                <input type="hidden" name="action" value="save_mapping_rule">
                <input type="hidden" name="id" id="formRuleId" value="">

                <div class="pm-modal-body">
                    <!-- Pattern -->
                    <div style="grid-column: span 2;">
                        <label style="font-size: 11px; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">
                            Match Pattern (Keyword or Regex) <span style="color: #dc2626;">*</span>
                        </label>
                        <input type="text" name="pattern" id="formPattern" required class="search-input-sm" style="width: 100%; font-family: monospace;" placeholder="e.g. DS3018xs%Rent or IronWolf 8TB">
                    </div>

                    <!-- Match Type -->
                    <div>
                        <label style="font-size: 11px; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">Match Method</label>
                        <select name="match_type" id="formMatchType" class="filter-select-sm" style="width: 100%;">
                            <option value="CONTAINS">CONTAINS (Sub-string search)</option>
                            <option value="EXACT">EXACT (Full string match)</option>
                            <option value="REGEX">REGEX (Regular Expression)</option>
                        </select>
                    </div>

                    <!-- Priority -->
                    <div>
                        <label style="font-size: 11px; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">Execution Priority</label>
                        <input type="number" name="priority" id="formPriority" value="10" min="1" max="99" class="search-input-sm" style="width: 100%;">
                        <span style="font-size: 10px; color: var(--text-muted);">Lower number = evaluated first</span>
                    </div>

                    <!-- Master SKU -->
                    <div>
                        <label style="font-size: 11px; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">Standard Master SKU</label>
                        <input type="text" name="master_sku" id="formMasterSku" class="search-input-sm" style="width: 100%; text-transform: uppercase;" placeholder="e.g. SYN-DS3018XS">
                    </div>

                    <!-- Brand -->
                    <div>
                        <label style="font-size: 11px; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">Brand / Manufacturer</label>
                        <select name="brand" id="formBrand" class="filter-select-sm" style="width: 100%;">
                            <option value="">-- Select Brand --</option>
                            <?php foreach ($masterBrands as $b): ?>
                            <option value="<?= htmlspecialchars($b['name']) ?>"><?= htmlspecialchars($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Canonical Product Name -->
                    <div style="grid-column: span 2;">
                        <label style="font-size: 11px; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">
                            Canonical Product Name <span style="color: #dc2626;">*</span>
                        </label>
                        <input type="text" name="canonical_name" id="formCanonicalName" required class="search-input-sm" style="width: 100%; font-weight: 600;" placeholder="e.g. Synology DiskStation DS923+">
                    </div>

                    <!-- Category -->
                    <div>
                        <label style="font-size: 11px; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">Master Category</label>
                        <select name="category" id="formCategory" class="filter-select-sm" style="width: 100%;">
                            <option value="">-- Select Category --</option>
                            <?php foreach ($masterCategories as $c): ?>
                            <option value="<?= htmlspecialchars($c['name']) ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
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
                    <div>
                        <label style="font-size: 11px; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">Internal Notes / Terms</label>
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

    <!-- MODAL 2: MASTER BRAND MODAL -->
    <div id="brandModalOverlay" class="pm-modal-overlay">
        <div class="pm-modal" style="max-width: 440px;">
            <div class="pm-modal-header">
                <span class="pm-modal-title" id="brandModalTitle">Add Master Brand</span>
                <button type="button" class="action-icon-btn" onclick="closeBrandModal()">
                    <i class="icon-x"></i>
                </button>
            </div>
            <form id="brandForm" method="POST" action="product_mapping.php?tab=taxonomy">
                <input type="hidden" name="action" value="save_brand">
                <input type="hidden" name="id" id="brandFormId" value="">

                <div class="pm-modal-body" style="grid-template-columns: 1fr;">
                    <div>
                        <label style="font-size: 11px; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">
                            Brand Name <span style="color: #dc2626;">*</span>
                        </label>
                        <input type="text" name="name" id="brandFormName" required class="search-input-sm" style="width: 100%;" placeholder="e.g. Synology, APC, Ubiquiti">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                        <div>
                            <label style="font-size: 11px; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">Short Code</label>
                            <input type="text" name="code" id="brandFormCode" class="search-input-sm" style="width: 100%; text-transform: uppercase;" placeholder="e.g. SYN, APC">
                        </div>
                        <div>
                            <label style="font-size: 11px; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">Badge Color</label>
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <input type="color" name="color" id="brandFormColor" value="#2563eb" style="width: 32px; height: 28px; border: 1px solid #cbd5e1; border-radius: 4px; cursor: pointer; padding: 1px;">
                                <span id="brandColorHex" style="font-size: 11px; color: var(--text-muted); font-family: monospace;">#2563eb</span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label style="font-size: 11px; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">Description / Scope</label>
                        <input type="text" name="description" id="brandFormDesc" class="search-input-sm" style="width: 100%;" placeholder="e.g. Enterprise NAS and surveillance equipment">
                    </div>

                    <div>
                        <label style="font-size: 11px; color: #334155; display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
                            <input type="checkbox" name="is_active" id="brandFormActive" value="1" checked>
                            <span style="font-weight: 600;">Active in Catalog & Dropdowns</span>
                        </label>
                    </div>
                </div>

                <div class="pm-modal-footer">
                    <button type="button" class="btn-cmd" onclick="closeBrandModal()">Cancel</button>
                    <button type="submit" class="btn-cmd btn-cmd-primary">
                        <i class="icon-check" style="font-size: 11px;"></i>
                        <span>Save Brand</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 3: MASTER CATEGORY MODAL -->
    <div id="categoryModalOverlay" class="pm-modal-overlay">
        <div class="pm-modal" style="max-width: 440px;">
            <div class="pm-modal-header">
                <span class="pm-modal-title" id="categoryModalTitle">Add Master Category</span>
                <button type="button" class="action-icon-btn" onclick="closeCategoryModal()">
                    <i class="icon-x"></i>
                </button>
            </div>
            <form id="categoryForm" method="POST" action="product_mapping.php?tab=taxonomy">
                <input type="hidden" name="action" value="save_category">
                <input type="hidden" name="id" id="catFormId" value="">

                <div class="pm-modal-body" style="grid-template-columns: 1fr;">
                    <div>
                        <label style="font-size: 11px; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">
                            Category Name <span style="color: #dc2626;">*</span>
                        </label>
                        <input type="text" name="name" id="catFormName" required class="search-input-sm" style="width: 100%;" placeholder="e.g. Network Switches & Routers">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                        <div>
                            <label style="font-size: 11px; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">Short Code</label>
                            <input type="text" name="code" id="catFormCode" class="search-input-sm" style="width: 100%; text-transform: uppercase;" placeholder="e.g. NET, NAS">
                        </div>
                        <div>
                            <label style="font-size: 11px; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">Badge Color</label>
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <input type="color" name="color" id="catFormColor" value="#059669" style="width: 32px; height: 28px; border: 1px solid #cbd5e1; border-radius: 4px; cursor: pointer; padding: 1px;">
                                <span id="catColorHex" style="font-size: 11px; color: var(--text-muted); font-family: monospace;">#059669</span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label style="font-size: 11px; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">Description / Scope</label>
                        <input type="text" name="description" id="catFormDesc" class="search-input-sm" style="width: 100%;" placeholder="e.g. Managed PoE switches, routers and SFP modules">
                    </div>

                    <div>
                        <label style="font-size: 11px; color: #334155; display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
                            <input type="checkbox" name="is_active" id="catFormActive" value="1" checked>
                            <span style="font-weight: 600;">Active in Catalog & Dropdowns</span>
                        </label>
                    </div>
                </div>

                <div class="pm-modal-footer">
                    <button type="button" class="btn-cmd" onclick="closeCategoryModal()">Cancel</button>
                    <button type="submit" class="btn-cmd btn-cmd-emerald">
                        <i class="icon-check" style="font-size: 11px;"></i>
                        <span>Save Category</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        /* ── Mapping Rules Modal Logic ── */
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
            document.getElementById('formCategory').value = rule.category || rule.product_category || '';
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
            
            var descLower = rawDesc.toLowerCase();
            if (descLower.indexOf('synology') !== -1) {
                document.getElementById('formBrand').value = 'Synology';
                document.getElementById('formCategory').value = 'NAS & Storage Servers';
            } else if (descLower.indexOf('seagate') !== -1 || descLower.indexOf('ironwolf') !== -1 || descLower.indexOf('exos') !== -1) {
                document.getElementById('formBrand').value = 'Seagate';
                document.getElementById('formCategory').value = 'Enterprise Hard Drives';
            } else if (descLower.indexOf('draytek') !== -1 || descLower.indexOf('vigor') !== -1) {
                document.getElementById('formBrand').value = 'DrayTek';
                document.getElementById('formCategory').value = 'Network Switches & Routers';
            } else if (descLower.indexOf('bdcom') !== -1) {
                document.getElementById('formBrand').value = 'BDCOM';
                document.getElementById('formCategory').value = 'Network Switches & Routers';
            } else if (descLower.indexOf('acronis') !== -1) {
                document.getElementById('formBrand').value = 'Acronis';
                document.getElementById('formCategory').value = 'Cloud Backup & Cyber Protect';
            } else if (descLower.indexOf('eset') !== -1) {
                document.getElementById('formBrand').value = 'ESET';
                document.getElementById('formCategory').value = 'Antivirus & Endpoint Security';
            }

            if (descLower.indexOf('rent') !== -1 || descLower.indexOf('lease') !== -1 || descLower.indexOf('hire') !== -1) {
                document.getElementById('formCommercialType').value = 'RENTAL';
                document.getElementById('formPriority').value = 1;
                document.getElementById('formCategory').value = 'Hardware Rental Fleet';
            } else if (descLower.indexOf('maintenance') !== -1 || descLower.indexOf(' amc') !== -1) {
                document.getElementById('formCommercialType').value = 'MAINTENANCE';
                document.getElementById('formPriority').value = 5;
                document.getElementById('formCategory').value = 'SLA & Maintenance Contracts';
            } else {
                document.getElementById('formCommercialType').value = 'OUTRIGHT_SALE';
                document.getElementById('formPriority').value = 10;
            }

            document.getElementById('modalHeaderTitle').innerText = 'Quick Map Rule for: ' + rawDesc.substring(0, 30) + '...';
        }

        function createRuleFromProduct(item) {
            openRuleModal();
            document.getElementById('formPattern').value = item.clean_product_name;
            document.getElementById('formCanonicalName').value = item.clean_product_name;
            document.getElementById('formBrand').value = item.brand !== 'Other' ? item.brand : '';
            document.getElementById('formCategory').value = item.category !== 'Other / Unassigned' ? item.category : '';
            
            var pType = item.product_type || '';
            if (pType === 'RENTAL') {
                document.getElementById('formCommercialType').value = 'RENTAL';
            } else if (pType === 'MAINTENANCE') {
                document.getElementById('formCommercialType').value = 'MAINTENANCE';
            } else if (pType === 'SOFTWARE') {
                document.getElementById('formCommercialType').value = 'SOFTWARE';
            } else if (pType === 'SERVICE') {
                document.getElementById('formCommercialType').value = 'SERVICE';
            } else {
                document.getElementById('formCommercialType').value = 'OUTRIGHT_SALE';
            }
            document.getElementById('modalHeaderTitle').innerText = 'Create Mapping Rule from Product';
        }

        /* ── Master Brand Modal Logic ── */
        function openBrandModal() {
            document.getElementById('brandFormId').value = '';
            document.getElementById('brandForm').reset();
            document.getElementById('brandFormColor').value = '#2563eb';
            document.getElementById('brandColorHex').innerText = '#2563eb';
            document.getElementById('brandFormActive').checked = true;
            document.getElementById('brandModalTitle').innerText = 'Add Master Brand';
            document.getElementById('brandModalOverlay').classList.add('show');
        }

        function closeBrandModal() {
            document.getElementById('brandModalOverlay').classList.remove('show');
        }

        function editBrand(b) {
            document.getElementById('brandFormId').value = b.id || '';
            document.getElementById('brandFormName').value = b.name || '';
            document.getElementById('brandFormCode').value = b.code || '';
            document.getElementById('brandFormColor').value = b.color || '#2563eb';
            document.getElementById('brandColorHex').innerText = b.color || '#2563eb';
            document.getElementById('brandFormDesc').value = b.description || '';
            document.getElementById('brandFormActive').checked = (b.is_active != 0);
            document.getElementById('brandModalTitle').innerText = 'Edit Brand: ' + b.name;
            document.getElementById('brandModalOverlay').classList.add('show');
        }

        document.getElementById('brandFormColor').addEventListener('input', function() {
            document.getElementById('brandColorHex').innerText = this.value;
        });

        /* ── Master Category Modal Logic ── */
        function openCategoryModal() {
            document.getElementById('catFormId').value = '';
            document.getElementById('categoryForm').reset();
            document.getElementById('catFormColor').value = '#059669';
            document.getElementById('catColorHex').innerText = '#059669';
            document.getElementById('catFormActive').checked = true;
            document.getElementById('categoryModalTitle').innerText = 'Add Master Category';
            document.getElementById('categoryModalOverlay').classList.add('show');
        }

        function closeCategoryModal() {
            document.getElementById('categoryModalOverlay').classList.remove('show');
        }

        function editCategory(c) {
            document.getElementById('catFormId').value = c.id || '';
            document.getElementById('catFormName').value = c.name || '';
            document.getElementById('catFormCode').value = c.code || '';
            document.getElementById('catFormColor').value = c.color || '#059669';
            document.getElementById('catColorHex').innerText = c.color || '#059669';
            document.getElementById('catFormDesc').value = c.description || '';
            document.getElementById('catFormActive').checked = (c.is_active != 0);
            document.getElementById('categoryModalTitle').innerText = 'Edit Category: ' + c.name;
            document.getElementById('categoryModalOverlay').classList.add('show');
        }

        document.getElementById('catFormColor').addEventListener('input', function() {
            document.getElementById('catColorHex').innerText = this.value;
        });

        /* ── Inline Single Product Assignment (AJAX) ── */
        function quickAssign(itemId, brandVal, catVal, selectEl) {
            var originalBorder = selectEl.style.borderColor;
            selectEl.style.borderColor = '#2563eb';
            
            var fd = new FormData();
            fd.append('action', 'assign_product');
            fd.append('ajax', '1');
            fd.append('item_id', itemId);
            if (brandVal !== null) fd.append('brand', brandVal);
            if (catVal !== null) fd.append('category', catVal);

            fetch('product_mapping.php', {
                method: 'POST',
                body: fd
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    var row = document.getElementById('row-item-' + itemId);
                    if (row) {
                        row.classList.add('quick-flash');
                        setTimeout(function() { row.classList.remove('quick-flash'); }, 1000);
                    }
                    selectEl.style.borderColor = '#10b981';
                    setTimeout(function() { selectEl.style.borderColor = '#cbd5e1'; }, 1500);
                } else {
                    alert('Error updating: ' + (data.error || 'Server error'));
                    selectEl.style.borderColor = '#dc2626';
                }
            })
            .catch(err => {
                alert('Network error: ' + err.message);
                selectEl.style.borderColor = '#dc2626';
            });
        }

        /* ── Bulk Assignment Logic ── */
        function toggleSelectAll(masterCb) {
            var checkboxes = document.querySelectorAll('.product-item-checkbox');
            checkboxes.forEach(function(cb) {
                cb.checked = masterCb.checked;
            });
            updateBulkBar();
        }

        function updateBulkBar() {
            var checked = document.querySelectorAll('.product-item-checkbox:checked');
            var bulkBar = document.getElementById('bulkActionBar');
            var countText = document.getElementById('selectedCountText');
            if (checked.length > 0) {
                bulkBar.style.display = 'flex';
                countText.innerText = checked.length + ' item' + (checked.length > 1 ? 's' : '') + ' selected';
            } else {
                bulkBar.style.display = 'none';
                var masterCb = document.getElementById('selectAllCheckbox');
                if (masterCb) masterCb.checked = false;
            }
        }

        function clearProductSelections() {
            var checkboxes = document.querySelectorAll('.product-item-checkbox');
            checkboxes.forEach(function(cb) { cb.checked = false; });
            var masterCb = document.getElementById('selectAllCheckbox');
            if (masterCb) masterCb.checked = false;
            updateBulkBar();
        }

        function applyBulkAssignment() {
            var checked = document.querySelectorAll('.product-item-checkbox:checked');
            if (checked.length === 0) {
                alert('Please select at least one product first.');
                return;
            }

            var brand = document.getElementById('bulkBrandSelect').value;
            var category = document.getElementById('bulkCategorySelect').value;

            if (!brand && !category) {
                alert('Please choose a Brand, a Category, or both to assign.');
                return;
            }

            var ids = [];
            checked.forEach(function(cb) { ids.push(cb.value); });

            if (!confirm('Assign ' + (brand ? 'Brand: ' + brand + ' ' : '') + (category ? 'Category: ' + category : '') + ' to ' + ids.length + ' selected product(s)?')) {
                return;
            }

            var fd = new FormData();
            fd.append('action', 'bulk_assign_products');
            fd.append('ajax', '1');
            fd.append('item_ids', ids.join(','));
            if (brand) fd.append('brand', brand);
            if (category) fd.append('category', category);

            fetch('product_mapping.php', {
                method: 'POST',
                body: fd
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    // Reload current page with filters preserved to see changes
                    window.location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Failed to bulk assign'));
                }
            })
            .catch(err => {
                alert('Network error: ' + err.message);
            });
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target === document.getElementById('ruleModalOverlay')) closeRuleModal();
            if (event.target === document.getElementById('brandModalOverlay')) closeBrandModal();
            if (event.target === document.getElementById('categoryModalOverlay')) closeCategoryModal();
        };
    </script>
    <?php require_once 'includes/layout_js.php'; ?>
</body>
</html>
