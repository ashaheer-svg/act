<?php
/**
 * Shared Slimline Sidebar Component (180px <-> 50px Collapsible)
 *
 * Auto-detects the current page and highlights the correct nav item.
 * Supports tooltips in collapsed 50px rail dock mode.
 */

$currentPage = basename($_SERVER['PHP_SELF']);
$currentType = $_GET['type'] ?? '';

$isInvoiceReport  = ($currentPage === 'reports.php' && ($currentType === 'invoices' || $currentType === ''));
$isWarrantyReport = ($currentPage === 'reports.php' && $currentType === 'warranties');
$isRenewalReport  = ($currentPage === 'reports.php' && $currentType === 'renewals');
$isOtherReports   = ($currentPage === 'reports.php' && !in_array($currentType, ['invoices', 'warranties', 'renewals']));

// Operations group open state
$opsPages = ['customers.php', 'profit_entry.php', 'upload.php', 'import_legacy_qb.php', 'product_mapping.php'];
$opsOpen = in_array($currentPage, $opsPages);

// Settings group open state
$settingsOpen = ($currentPage === 'settings.php');
?>
<aside class="sidebar" id="mainSidebar">
    <div class="sidebar-header">
        <a href="index.php" class="logo-container" title="Activity Sales BI">
            <div class="logo-icon"><i class="icon-activity"></i></div>
            <span>ACTIVITY | BI</span>
        </a>
        <button type="button" class="sidebar-collapse-toggle" id="sidebarCollapseBtn" onclick="toggleSidebar()" title="Toggle Sidebar (180px / 50px)">
            <i class="icon-chevrons-left" id="sidebarToggleIcon"></i>
        </button>
    </div>

    <nav class="sidebar-nav">
        <!-- Group 1: Intelligence -->
        <div class="sb-group-title">Intelligence</div>
        
        <a href="index.php" class="nav-item <?= ($currentPage === 'index.php') ? 'active' : '' ?>" data-title="Dashboard">
            <i class="icon-layout-dashboard"></i>
            <span>Dashboard</span>
        </a>

        <a href="reports.php" class="nav-item <?= $isOtherReports ? 'active' : '' ?>" data-title="Reporting">
            <i class="icon-bar-chart-2"></i>
            <span>Performance</span>
        </a>

        <a href="explorer.php" class="nav-item <?= ($currentPage === 'explorer.php') ? 'active' : '' ?>" data-title="Data Explorer">
            <i class="icon-database"></i>
            <span>Explorer</span>
        </a>

        <!-- Group 2: Assets & CRM -->
        <div class="sb-group-title">Assets & Ledgers</div>

        <a href="reports.php?type=invoices" class="nav-item <?= $isInvoiceReport ? 'active' : '' ?>" data-title="Invoices">
            <i class="icon-file-text"></i>
            <span>Invoices</span>
            <span class="sb-badge">12.4k</span>
        </a>

        <a href="reports.php?type=warranties" class="nav-item <?= $isWarrantyReport ? 'active' : '' ?>" data-title="Hardware Assets">
            <i class="icon-shield"></i>
            <span>Hardware S/N</span>
            <span class="sb-badge">7.8k</span>
        </a>

        <a href="reports.php?type=renewals" class="nav-item <?= $isRenewalReport ? 'active' : '' ?>" data-title="SaaS & Renewals">
            <i class="icon-refresh-cw"></i>
            <span>SaaS / MA</span>
            <span class="sb-badge">393</span>
        </a>

        <a href="customers.php" class="nav-item <?= ($currentPage === 'customers.php') ? 'active' : '' ?>" data-title="Customers">
            <i class="icon-building-2"></i>
            <span>Customers</span>
        </a>

        <!-- Group 3: Operations & System -->
        <div class="sb-group-title">Operations</div>

        <div class="nav-group <?= $opsOpen ? 'open' : '' ?>">
            <button class="nav-item nav-group-toggle" onclick="toggleNavGroup(this)" type="button" data-title="Operations">
                <i class="icon-briefcase"></i>
                <span>Operations</span>
                <i class="icon-chevron-right nav-group-arrow"></i>
            </button>
            <div class="sub-nav">
                <a href="upload.php" class="sub-nav-item <?= ($currentPage === 'upload.php') ? 'active' : '' ?>" data-title="Upload">
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
                <a href="product_mapping.php" class="sub-nav-item <?= ($currentPage === 'product_mapping.php') ? 'active' : '' ?>" data-title="Product & Rental Mapping">
                    <i class="icon-layers"></i>
                    <span>Product & Rental</span>
                </a>
            </div>
        </div>

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
        <form method="POST" action="logout.php" style="margin: 0;">
            <button type="submit" class="nav-item" data-title="Logout" style="background: none; border: none; width: 100%; cursor: pointer; text-align: left;">
                <i class="icon-log-out"></i>
                <span>Logout</span>
            </button>
        </form>
    </div>
</aside>
