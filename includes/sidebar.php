<?php
/**
 * Shared Slimline Sidebar Component (180px <-> 50px Collapsible)
 *
 * Restructured Menu (Option 1: 4 Focused Functional Domains)
 * Featuring Logo Concept 2: HexaMetrics (Single-Colour Vector Line Logo)
 * Archived Reports completely removed.
 */

$currentPage = basename($_SERVER['PHP_SELF']);
$currentType = $_GET['type'] ?? '';
$currentView = $_GET['view'] ?? 'overview';
$currentTab = $_GET['tab'] ?? '';

$isInvoiceReport = ($currentPage === 'reports.php' && in_array($currentType, ['invoices', '']));
$isUnpaidReport = ($currentPage === 'reports.php' && $currentType === 'unpaid_invoices');
$isWarrantyReport = ($currentPage === 'reports.php' && $currentType === 'warranties');

// Analytics group open state (7 items)
$analyticsTypes = ['monthly', 'ltv', 'churn', 'eol', 'contracts', 'expiring_contracts', 'rental_roi', 'brand_growth'];
$analyticsOpen = ($currentPage === 'reports.php' && in_array($currentType, $analyticsTypes));

// Finance & Tax group open state (4 items)
$financePages = ['profit_entry.php', 'vat_review.php'];
$financeTypes = ['tax_audit', 'dso_trends'];
$financeOpen = in_array($currentPage, $financePages) || ($currentPage === 'reports.php' && in_array($currentType, $financeTypes));

// Operations group open state (4 items)
$opsPages = ['upload.php', 'import_legacy_qb.php', 'product_mapping.php', 'sync_app.php'];
$opsOpen = in_array($currentPage, $opsPages);

// Settings group open state (3 items)
$settingsOpen = in_array($currentPage, ['settings.php', 'rbac.php']);

// Permissions resolution
if (!isset($auth) && isset($db)) {
    $auth = new Auth($db);
}

// Core Pinned Items
$canDashboard = !isset($auth) || $auth->canAccessReport('dashboard');
$canInvoices = !isset($auth) || $auth->canAccessReport('invoices');
$canUnpaid = !isset($auth) || $auth->canAccessReport('unpaid_invoices');
$canWarranties = !isset($auth) || $auth->canAccessReport('warranties');
$canCustomers = !isset($auth) || $auth->canAccessReport('customers');

// Analytics Items (7)
$canMonthlyOverview = !isset($auth) || $auth->canAccessReport('monthly_overview');
$canLtv = !isset($auth) || $auth->canAccessReport('ltv');
$canChurn = !isset($auth) || $auth->canAccessReport('churn');
$canEol = !isset($auth) || $auth->canAccessReport('eol');
$canContracts = !isset($auth) || $auth->canAccessReport('contracts');
$canRentalRoi = !isset($auth) || $auth->canAccessReport('rental_roi');
$canBrandGrowth = !isset($auth) || $auth->canAccessReport('brand_growth');

$analyticsCount = ($canMonthlyOverview ? 1 : 0) + ($canLtv ? 1 : 0) + ($canChurn ? 1 : 0) 
    + ($canEol ? 1 : 0) + ($canContracts ? 1 : 0) + ($canRentalRoi ? 1 : 0) + ($canBrandGrowth ? 1 : 0);

// Finance & Tax Items (4)
$canProfitEntry = !isset($auth) || $auth->canAccessReport('profit_entry');
$canDso = !isset($auth) || $auth->canAccessReport('dso_trends');
$canTaxAudit = !isset($auth) || $auth->canAccessReport('tax_audit');
$canVatReview = !isset($auth) || $auth->canAccessReport('vat_review');

$financeCount = ($canProfitEntry ? 1 : 0) + ($canDso ? 1 : 0) + ($canTaxAudit ? 1 : 0) + ($canVatReview ? 1 : 0);

// Operations Items (4)
$canUpload = !isset($auth) || $auth->canAccessReport('upload');
$canProductMapping = !isset($auth) || $auth->canAccessReport('product_mapping');
$canLegacyQb = !isset($auth) || $auth->canAccessReport('import_legacy_qb');
$canSyncApp = !isset($auth) || $auth->canAccessReport('sync_app');

$opsCount = ($canUpload ? 1 : 0) + ($canProductMapping ? 1 : 0) + ($canLegacyQb ? 1 : 0) + ($canSyncApp ? 1 : 0);
?>
<aside class="sidebar" id="mainSidebar">
    <!-- Activity Brand Header with Logo Concept 2 (HexaMetrics) -->
    <div class="sidebar-brand">
        <a href="index.php" class="brand-info" style="text-decoration:none;">
            <svg class="brand-logo-svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                <path d="M2 17l10 5 10-5"></path>
                <path d="M2 12l10 5 10-5"></path>
            </svg>
            <span class="brand-text">ACTIVITY</span>
            <span class="brand-badge">BI</span>
        </a>
        <button type="button" class="sidebar-collapse-btn" id="sidebarCollapseBtn" onclick="toggleSidebar()" title="Collapse navigation rail (Ctrl+B)">
            <i class="icon-chevrons-left"></i>
        </button>
    </div>

    <!-- Navigation List -->
    <nav class="sidebar-nav">
        <!-- Core Pinned Direct Actions -->
        <?php if ($canDashboard): ?>
        <a href="index.php" class="nav-item <?= ($currentPage === 'index.php') ? 'active' : '' ?>" data-title="Dashboard">
            <i class="icon-layout-dashboard"></i>
            <span>Dashboard</span>
        </a>
        <?php endif; ?>

        <?php if ($canInvoices): ?>
        <a href="reports.php?type=invoices" class="nav-item <?= $isInvoiceReport ? 'active' : '' ?>" data-title="Commercial Invoices">
            <i class="icon-file-text"></i>
            <span>Invoices</span>
            <span class="sb-badge">12.4k</span>
        </a>
        <?php endif; ?>

        <?php if ($canUnpaid): ?>
        <a href="reports.php?type=unpaid_invoices" class="nav-item <?= $isUnpaidReport ? 'active' : '' ?>" data-title="Unpaid Invoices">
            <i class="icon-alert-circle"></i>
            <span>Unpaid Invoices</span>
            <span class="sb-badge red">76</span>
        </a>
        <?php endif; ?>

        <?php if ($canWarranties): ?>
        <a href="reports.php?type=warranties" class="nav-item <?= $isWarrantyReport ? 'active' : '' ?>" data-title="Warranties">
            <i class="icon-shield"></i>
            <span>Warranties</span>
            <span class="sb-badge">12.3k</span>
        </a>
        <?php endif; ?>

        <?php if ($canCustomers): ?>
        <a href="customers.php" class="nav-item <?= in_array($currentPage, ['customers.php', 'customer_report.php']) ? 'active' : '' ?>" data-title="Customers">
            <i class="icon-users"></i>
            <span>Customers</span>
        </a>
        <?php endif; ?>

        <div class="sb-divider"></div>

        <!-- 1. Analytics & BI Accordion Group (7 Items) -->
        <?php if ($analyticsCount > 0): ?>
        <div class="nav-group <?= $analyticsOpen ? 'open' : '' ?>">
            <button class="nav-item nav-group-toggle" onclick="toggleNavGroup(this)" type="button" data-title="Analytics & BI">
                <i class="icon-bar-chart-3"></i>
                <span>Analytics &amp; BI</span>
                <span class="sb-badge"><?= $analyticsCount; ?></span>
                <i class="icon-chevron-right nav-group-arrow"></i>
            </button>
            <div class="sub-nav">
                <?php if ($canMonthlyOverview): ?>
                <a href="reports.php?type=monthly&view=overview" class="sub-nav-item <?= ($currentPage === 'reports.php' && $currentType === 'monthly') ? 'active' : '' ?>" data-title="Monthly Sales Matrix">
                    <i class="icon-calendar"></i>
                    <span>Monthly Matrix</span>
                </a>
                <?php endif; ?>
                <?php if ($canLtv): ?>
                <a href="reports.php?type=ltv" class="sub-nav-item <?= ($currentPage === 'reports.php' && $currentType === 'ltv') ? 'active' : '' ?>" data-title="Customer LTV">
                    <i class="icon-award"></i>
                    <span>Customer LTV</span>
                </a>
                <?php endif; ?>
                <?php if ($canChurn): ?>
                <a href="reports.php?type=churn" class="sub-nav-item <?= ($currentPage === 'reports.php' && $currentType === 'churn') ? 'active' : '' ?>" data-title="Churn Risk">
                    <i class="icon-user-x"></i>
                    <span>Churn Risk</span>
                </a>
                <?php endif; ?>
                <?php if ($canEol): ?>
                <a href="reports.php?type=eol" class="sub-nav-item <?= ($currentPage === 'reports.php' && $currentType === 'eol') ? 'active' : '' ?>" data-title="Hardware EOL">
                    <i class="icon-cpu"></i>
                    <span>Hardware EOL</span>
                </a>
                <?php endif; ?>
                <?php if ($canContracts): ?>
                <a href="reports.php?type=contracts" class="sub-nav-item <?= ($currentPage === 'reports.php' && in_array($currentType, ['contracts', 'expiring_contracts'])) ? 'active' : '' ?>" data-title="Expiring Contracts">
                    <i class="icon-shield-check"></i>
                    <span>Expiring Contracts</span>
                </a>
                <?php endif; ?>
                <?php if ($canRentalRoi): ?>
                <a href="reports.php?type=rental_roi" class="sub-nav-item <?= ($currentPage === 'reports.php' && $currentType === 'rental_roi') ? 'active' : '' ?>" data-title="Rental Fleet">
                    <i class="icon-repeat"></i>
                    <span>Rental Fleet</span>
                </a>
                <?php endif; ?>
                <?php if ($canBrandGrowth): ?>
                <a href="reports.php?type=brand_growth" class="sub-nav-item <?= ($currentPage === 'reports.php' && $currentType === 'brand_growth') ? 'active' : '' ?>" data-title="Brand & Category">
                    <i class="icon-trending-up"></i>
                    <span>Brand &amp; Category</span>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- 2. Finance & Tax Governance Accordion Group (4 Items) -->
        <?php if ($financeCount > 0): ?>
        <div class="nav-group <?= $financeOpen ? 'open' : '' ?>">
            <button class="nav-item nav-group-toggle" onclick="toggleNavGroup(this)" type="button" data-title="Finance & Tax">
                <i class="icon-dollar-sign"></i>
                <span>Finance &amp; Tax</span>
                <span class="sb-badge"><?= $financeCount; ?></span>
                <i class="icon-chevron-right nav-group-arrow"></i>
            </button>
            <div class="sub-nav">
                <?php if ($canProfitEntry): ?>
                <a href="profit_entry.php" class="sub-nav-item <?= ($currentPage === 'profit_entry.php') ? 'active' : '' ?>" data-title="Profit Entry & Costs">
                    <i class="icon-dollar-sign"></i>
                    <span>Profit &amp; GP Entry</span>
                </a>
                <?php endif; ?>
                <?php if ($canDso): ?>
                <a href="reports.php?type=dso_trends" class="sub-nav-item <?= ($currentPage === 'reports.php' && $currentType === 'dso_trends') ? 'active' : '' ?>" data-title="DSO & Working Capital">
                    <i class="icon-credit-card"></i>
                    <span>DSO &amp; Capital</span>
                </a>
                <?php endif; ?>
                <?php if ($canTaxAudit): ?>
                <a href="reports.php?type=tax_audit" class="sub-nav-item <?= ($currentPage === 'reports.php' && $currentType === 'tax_audit') ? 'active' : '' ?>" data-title="Tax & IRD Audit">
                    <i class="icon-percent"></i>
                    <span>Tax &amp; IRD Audit</span>
                </a>
                <?php endif; ?>
                <?php if ($canVatReview): ?>
                <a href="vat_review.php" class="sub-nav-item <?= ($currentPage === 'vat_review.php') ? 'active' : '' ?>" data-title="VAT Review & Switcher">
                    <i class="icon-sliders"></i>
                    <span>VAT Review</span>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- 3. Operations & Tools Accordion Group (4 Items) -->
        <?php if ($opsCount > 0): ?>
        <div class="nav-group <?= $opsOpen ? 'open' : '' ?>">
            <button class="nav-item nav-group-toggle" onclick="toggleNavGroup(this)" type="button" data-title="Operations">
                <i class="icon-briefcase"></i>
                <span>Operations</span>
                <span class="sb-badge"><?= $opsCount; ?></span>
                <i class="icon-chevron-right nav-group-arrow"></i>
            </button>
            <div class="sub-nav">
                <?php if ($canUpload): ?>
                <a href="upload.php" class="sub-nav-item <?= ($currentPage === 'upload.php') ? 'active' : '' ?>" data-title="Data Upload Center">
                    <i class="icon-folder-up"></i>
                    <span>Data Upload</span>
                </a>
                <?php endif; ?>
                <?php if ($canProductMapping): ?>
                <a href="product_mapping.php" class="sub-nav-item <?= ($currentPage === 'product_mapping.php') ? 'active' : '' ?>" data-title="Product & Rental Taxonomy">
                    <i class="icon-layers"></i>
                    <span>Product Taxonomy</span>
                </a>
                <?php endif; ?>
                <?php if ($canLegacyQb): ?>
                <a href="import_legacy_qb.php" class="sub-nav-item <?= ($currentPage === 'import_legacy_qb.php') ? 'active' : '' ?>" data-title="Legacy QB Import">
                    <i class="icon-archive"></i>
                    <span>Legacy QB</span>
                </a>
                <?php endif; ?>
                <?php if ($canSyncApp): ?>
                <a href="sync_app.php" class="sub-nav-item <?= ($currentPage === 'sync_app.php') ? 'active' : '' ?>" data-title="Download Sync App">
                    <i class="icon-cloud-download"></i>
                    <span>Download Sync</span>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- 4. Settings Accordion Group (3 Items) -->
        <div class="nav-group <?= $settingsOpen ? 'open' : '' ?>">
            <button class="nav-item nav-group-toggle" onclick="toggleNavGroup(this)" type="button" data-title="Settings">
                <i class="icon-settings"></i>
                <span>Settings</span>
                <i class="icon-chevron-right nav-group-arrow"></i>
            </button>
            <div class="sub-nav">
                <a href="settings.php?tab=system" class="sub-nav-item <?= ($currentPage === 'settings.php' && ($currentTab === 'system' || $currentTab === '')) ? 'active' : '' ?>" data-title="System Setup">
                    <i class="icon-sliders"></i>
                    <span>System Setup</span>
                </a>
                <a href="settings.php?tab=team" class="sub-nav-item <?= ($currentPage === 'settings.php' && $currentTab === 'team') ? 'active' : '' ?>" data-title="Access & Team">
                    <i class="icon-users"></i>
                    <span>Access &amp; Team</span>
                </a>
                <?php if (!isset($auth) || $auth->isAdmin()): ?>
                <a href="rbac.php" class="sub-nav-item <?= ($currentPage === 'rbac.php') ? 'active' : '' ?>" data-title="RBAC Permissions">
                    <i class="icon-shield"></i>
                    <span>RBAC Permissions</span>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
        <button type="button" class="nav-item sidebar-footer-toggle" id="sidebarFooterToggleBtn" onclick="toggleSidebar()" data-title="Collapse" style="background: none; border: none; width: 100%; cursor: pointer; text-align: left; color: var(--sidebar-text);">
            <i class="icon-chevrons-left" id="sidebarFooterToggleIcon"></i>
            <span>Collapse</span>
        </button>
        <form method="POST" action="logout.php" style="margin: 0;">
            <button type="submit" class="nav-item" data-title="Logout" style="background: none; border: none; width: 100%; cursor: pointer; text-align: left;">
                <i class="icon-log-out"></i>
                <span>Logout</span>
            </button>
        </form>
    </div>
    <?php require_once __DIR__ . '/layout_js.php'; ?>
</aside>
