<?php
/**
 * Shared Slimline Sidebar Component (180px <-> 50px Collapsible)
 *
 * Auto-detects the current page and highlights the correct nav item.
 * Supports tooltips in collapsed 50px rail dock mode.
 */

$currentPage = basename($_SERVER['PHP_SELF']);
$currentType = $_GET['type'] ?? '';
$currentView = $_GET['view'] ?? 'overview';

$isInvoiceReport = ($currentPage === 'reports.php' && in_array($currentType, ['invoices', '']));
$isUnpaidReport = ($currentPage === 'reports.php' && $currentType === 'unpaid_invoices');
$isWarrantyReport = ($currentPage === 'reports.php' && $currentType === 'warranties');

$analyticsTypes = ['monthly', 'ltv', 'churn', 'eol', 'contracts', 'rental_roi', 'brand_growth', 'dso_trends', 'tax_audit'];
$analyticsOpen = ($currentPage === 'reports.php' && in_array($currentType, $analyticsTypes));

// Operations group open state
$opsPages = ['profit_entry.php', 'upload.php', 'import_legacy_qb.php', 'product_mapping.php'];
$opsOpen = in_array($currentPage, $opsPages);

// Archives group open state
$archiveTypes = ['yearly', 'matrix', 'renewals', 'aging'];
$archivesOpen = ($currentPage === 'reports.php' && in_array($currentType, $archiveTypes)) 
             || in_array($currentPage, ['index.php', 'explorer.php', 'customers.php', 'customer_report.php']);

// Settings group open state
$settingsOpen = ($currentPage === 'settings.php');
?>
<aside class="sidebar" id="mainSidebar">
    <!-- Activity Brand Header with Collapsible Toggle Trigger -->
    <div class="sidebar-brand">
        <div class="brand-info">
            <i class="icon-activity brand-icon"></i>
            <span class="brand-text">ACTIVITY</span>
            <span class="brand-badge">BI</span>
        </div>
        <button type="button" class="sidebar-collapse-btn" id="sidebarCollapseBtn" onclick="toggleSidebar()" title="Collapse navigation rail (Ctrl+B)">
            <i class="icon-chevrons-left"></i>
        </button>
    </div>

    <!-- Direct Core Actions -->
    <div class="sidebar-nav">
        <!-- Direct Primary Reports (Single Click) -->
        <a href="reports.php?type=invoices" class="nav-item <?= $isInvoiceReport ? 'active' : '' ?>" data-title="Invoices">
            <i class="icon-file-text"></i>
            <span>Invoices</span>
            <span class="sb-badge">12.4k</span>
        </a>

        <a href="reports.php?type=unpaid_invoices" class="nav-item <?= $isUnpaidReport ? 'active' : '' ?>" data-title="Unpaid Invoices">
            <i class="icon-alert-circle"></i>
            <span>Unpaid Invoices</span>
            <span class="sb-badge red">76</span>
        </a>

        <a href="reports.php?type=warranties" class="nav-item <?= $isWarrantyReport ? 'active' : '' ?>" data-title="Warranty Lookup">
            <i class="icon-shield"></i>
            <span>Warranty Lookup</span>
            <span class="sb-badge">12.3k</span>
        </a>

        <div class="sb-divider"></div>

        <!-- Analytics & Intelligence Accordion Group -->
        <div class="nav-group <?= $analyticsOpen ? 'open' : '' ?>">
            <button class="nav-item nav-group-toggle" onclick="toggleNavGroup(this)" type="button" data-title="Analytics & BI">
                <i class="icon-bar-chart-3"></i>
                <span>Analytics & BI</span>
                <span class="sb-badge">11</span>
                <i class="icon-chevron-right nav-group-arrow"></i>
            </button>
            <div class="sub-nav">
                <a href="reports.php?type=monthly&view=overview" class="sub-nav-item <?= ($currentPage === 'reports.php' && $currentType === 'monthly' && ($currentView ?? 'overview') === 'overview') ? 'active' : '' ?>" data-title="Monthly Sales Matrix">
                    <i class="icon-calendar"></i>
                    <span>Monthly Matrix</span>
                </a>
                <a href="reports.php?type=monthly&view=customer" class="sub-nav-item <?= ($currentPage === 'reports.php' && $currentType === 'monthly' && ($currentView ?? '') === 'customer') ? 'active' : '' ?>" data-title="Customer Monthly Matrix">
                    <i class="icon-users"></i>
                    <span>Customer Matrix</span>
                </a>
                <a href="reports.php?type=monthly&view=rep" class="sub-nav-item <?= ($currentPage === 'reports.php' && $currentType === 'monthly' && ($currentView ?? '') === 'rep') ? 'active' : '' ?>" data-title="Sales Rep Monthly Matrix">
                    <i class="icon-award"></i>
                    <span>Sales Rep Matrix</span>
                </a>
                <a href="reports.php?type=ltv" class="sub-nav-item <?= ($currentPage === 'reports.php' && $currentType === 'ltv') ? 'active' : '' ?>" data-title="Customer LTV">
                    <i class="icon-award"></i>
                    <span>Customer LTV</span>
                </a>
                <a href="reports.php?type=churn" class="sub-nav-item <?= ($currentPage === 'reports.php' && $currentType === 'churn') ? 'active' : '' ?>" data-title="Churn Risk">
                    <i class="icon-user-x"></i>
                    <span>Churn Risk</span>
                </a>
                <a href="reports.php?type=eol" class="sub-nav-item <?= ($currentPage === 'reports.php' && $currentType === 'eol') ? 'active' : '' ?>" data-title="Hardware EOL">
                    <i class="icon-cpu"></i>
                    <span>Hardware EOL</span>
                </a>
                <a href="reports.php?type=contracts" class="sub-nav-item <?= ($currentPage === 'reports.php' && in_array($currentType, ['contracts', 'expiring_contracts'])) ? 'active' : '' ?>" data-title="Expiring Contracts">
                    <i class="icon-shield-check"></i>
                    <span>Expiring Contracts</span>
                </a>
                <a href="reports.php?type=rental_roi" class="sub-nav-item <?= ($currentPage === 'reports.php' && $currentType === 'rental_roi') ? 'active' : '' ?>" data-title="Rental Fleet">
                    <i class="icon-repeat"></i>
                    <span>Rental Fleet</span>
                </a>
                <a href="reports.php?type=brand_growth" class="sub-nav-item <?= ($currentPage === 'reports.php' && $currentType === 'brand_growth') ? 'active' : '' ?>" data-title="Brand & Category">
                    <i class="icon-trending-up"></i>
                    <span>Brand & Category</span>
                </a>
                <a href="reports.php?type=dso_trends" class="sub-nav-item <?= ($currentPage === 'reports.php' && $currentType === 'dso_trends') ? 'active' : '' ?>" data-title="DSO & Capital">
                    <i class="icon-dollar-sign"></i>
                    <span>DSO & Capital</span>
                </a>
                <a href="reports.php?type=tax_audit" class="sub-nav-item <?= ($currentPage === 'reports.php' && $currentType === 'tax_audit') ? 'active' : '' ?>" data-title="Tax & IRD Audit">
                    <i class="icon-percent"></i>
                    <span>Tax & IRD Audit</span>
                </a>
            </div>
        </div>

        <!-- Operations Accordion Group -->
        <div class="nav-group <?= $opsOpen ? 'open' : '' ?>">
            <button class="nav-item nav-group-toggle" onclick="toggleNavGroup(this)" type="button" data-title="Operations">
                <i class="icon-briefcase"></i>
                <span>Operations</span>
                <span class="sb-badge">4</span>
                <i class="icon-chevron-right nav-group-arrow"></i>
            </button>
            <div class="sub-nav">
                <a href="upload.php" class="sub-nav-item <?= ($currentPage === 'upload.php') ? 'active' : '' ?>" data-title="Data Upload">
                    <i class="icon-folder-up"></i>
                    <span>Data Upload</span>
                </a>
                <a href="import_legacy_qb.php" class="sub-nav-item <?= ($currentPage === 'import_legacy_qb.php') ? 'active' : '' ?>" data-title="Legacy QB">
                    <i class="icon-archive"></i>
                    <span>Legacy QB</span>
                </a>
                <a href="profit_entry.php" class="sub-nav-item <?= ($currentPage === 'profit_entry.php') ? 'active' : '' ?>" data-title="Profit Entry">
                    <i class="icon-dollar-sign"></i>
                    <span>Profit Entry</span>
                </a>
                <a href="product_mapping.php" class="sub-nav-item <?= ($currentPage === 'product_mapping.php') ? 'active' : '' ?>" data-title="Product & Rental">
                    <i class="icon-layers"></i>
                    <span>Product & Rental</span>
                </a>
            </div>
        </div>

        <!-- Archives Accordion Group -->
        <div class="nav-group <?= $archivesOpen ? 'open' : '' ?>">
            <button class="nav-item nav-group-toggle" onclick="toggleNavGroup(this)" type="button" data-title="Archived Reports">
                <i class="icon-archive"></i>
                <span>Archived Reports</span>
                <span class="sb-badge">7</span>
                <i class="icon-chevron-right nav-group-arrow"></i>
            </button>
            <div class="sub-nav">
                <a href="index.php" class="sub-nav-item <?= ($currentPage === 'index.php') ? 'active' : '' ?>" data-title="Dashboard (Archived)">
                    <i class="icon-layout-dashboard"></i>
                    <span>Dashboard</span>
                </a>
                <a href="reports.php?type=yearly" class="sub-nav-item <?= ($currentPage === 'reports.php' && $currentType === 'yearly') ? 'active' : '' ?>" data-title="Performance (Archived)">
                    <i class="icon-bar-chart-2"></i>
                    <span>Performance</span>
                </a>
                <a href="reports.php?type=matrix" class="sub-nav-item <?= ($currentPage === 'reports.php' && $currentType === 'matrix') ? 'active' : '' ?>" data-title="Customer Matrix (Archived)">
                    <i class="icon-grid"></i>
                    <span>Customer Matrix</span>
                </a>
                <a href="reports.php?type=renewals" class="sub-nav-item <?= ($currentPage === 'reports.php' && $currentType === 'renewals') ? 'active' : '' ?>" data-title="SaaS & Renewals (Archived)">
                    <i class="icon-refresh-cw"></i>
                    <span>SaaS / Renewals</span>
                </a>
                <a href="reports.php?type=aging" class="sub-nav-item <?= ($currentPage === 'reports.php' && $currentType === 'aging') ? 'active' : '' ?>" data-title="Aging & Collections (Archived)">
                    <i class="icon-clock"></i>
                    <span>Aging & Debt</span>
                </a>
                <a href="customers.php" class="sub-nav-item <?= ($currentPage === 'customers.php') ? 'active' : '' ?>" data-title="Customers (Archived)">
                    <i class="icon-building-2"></i>
                    <span>Customers</span>
                </a>
                <a href="explorer.php" class="sub-nav-item <?= ($currentPage === 'explorer.php') ? 'active' : '' ?>" data-title="Data Explorer (Archived)">
                    <i class="icon-database"></i>
                    <span>Data Explorer</span>
                </a>
            </div>
        </div>

        <!-- Settings Accordion Group -->
        <div class="nav-group <?= $settingsOpen ? 'open' : '' ?>">
            <button class="nav-item nav-group-toggle" onclick="toggleNavGroup(this)" type="button" data-title="Settings">
                <i class="icon-settings"></i>
                <span>Settings</span>
                <i class="icon-chevron-right nav-group-arrow"></i>
            </button>
            <div class="sub-nav">
                <a href="settings.php#system" class="sub-nav-item" data-title="System Setup">
                    <i class="icon-sliders"></i>
                    <span>System Setup</span>
                </a>
                <a href="settings.php#team" class="sub-nav-item" data-title="Access & Team">
                    <i class="icon-users"></i>
                    <span>Access & Team</span>
                </a>
            </div>
        </div>
    </nav>

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
