<?php
/**
 * Shared Sidebar Component
 *
 * Auto-detects the current page and highlights the correct nav item.
 */

$currentPage = basename($_SERVER['PHP_SELF']);

// Map each page to its section (used for parent highlighting)
$sectionMap = [
    'index.php'           => 'dashboard',
    'reports.php'         => 'reports',
    'customers.php'       => 'operations',
    'profit_entry.php'    => 'operations',
    'upload.php'          => 'operations',
    'product_mapping.php' => 'operations',
    'settings.php'        => 'settings',
];

// Map each page/hash to its sub-nav key
$subPageMap = [
    'customers.php'       => 'customers',
    'profit_entry.php'    => 'profit',
    'upload.php'          => 'upload',
    'product_mapping.php' => 'mapping',
    'settings.php'        => 'system', // Defaults to system tab in settings
];

$activeSection  = $sectionMap[$currentPage]  ?? '';
$activeSubPage  = $subPageMap[$currentPage]  ?? '';
$settingsOpen   = ($activeSection === 'settings');
$opsOpen        = ($activeSection === 'operations');

function navClass(string $section, string $activeSection, string $extra = ''): string {
    $cls = 'nav-item' . ($section === $activeSection ? ' active' : '');
    return $cls . ($extra ? " $extra" : '');
}

function subNavClass(string $key, string $activeSubPage): string {
    return 'sub-nav-item' . ($key === $activeSubPage ? ' active' : '');
}
?>
<aside class="sidebar" id="mainSidebar">
    <div class="sidebar-header">
        <div class="logo-container">
            <div class="logo-icon"><i class="icon-bar-chart-2"></i></div>
            <span>SYNC | ANALYTICS</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <!-- Dashboard -->
        <a href="index.php" class="<?= navClass('dashboard', $activeSection) ?>">
            <i class="icon-layout-dashboard"></i>
            <span>Dashboard</span>
        </a>

        <!-- Reporting -->
        <a href="reports.php" class="<?= navClass('reports', $activeSection) ?>">
            <i class="icon-bar-chart-2"></i>
            <span>Reporting</span>
        </a>

        <!-- Operations group -->
        <div class="nav-group <?= $opsOpen ? 'open' : '' ?>">
            <button class="nav-item nav-group-toggle" onclick="toggleNavGroup(this)" type="button">
                <i class="icon-briefcase"></i>
                <span>Operations</span>
                <i class="icon-chevron-right nav-group-arrow"></i>
            </button>
            <div class="sub-nav">
                <a href="upload.php" class="<?= subNavClass('upload', $activeSubPage) ?>">
                    <i class="icon-folder-up"></i>
                    <span>Data Upload</span>
                </a>
                <a href="profit_entry.php" class="<?= subNavClass('profit', $activeSubPage) ?>">
                    <i class="icon-dollar-sign"></i>
                    <span>Profit Entry</span>
                </a>
                <a href="customers.php" class="<?= subNavClass('customers', $activeSubPage) ?>">
                    <i class="icon-building-2"></i>
                    <span>Customers</span>
                </a>
                <a href="product_mapping.php" class="<?= subNavClass('mapping', $activeSubPage) ?>">
                    <i class="icon-git-branch"></i>
                    <span>Product Mapping</span>
                </a>
            </div>
        </div>

        <!-- Settings group -->
        <div class="nav-group <?= $settingsOpen ? 'open' : '' ?>">
            <button class="nav-item nav-group-toggle" onclick="toggleNavGroup(this)" type="button">
                <i class="icon-settings"></i>
                <span>Settings</span>
                <i class="icon-chevron-right nav-group-arrow"></i>
            </button>
            <div class="sub-nav">
                <a href="settings.php#system" class="<?= subNavClass('system', $activeSubPage) ?>">
                    <i class="icon-sliders"></i>
                    <span>System Setup</span>
                </a>
                <a href="settings.php#team" class="<?= subNavClass('team', $activeSubPage) ?>">
                    <i class="icon-users"></i>
                    <span>Access & Team</span>
                </a>
            </div>
        </div>
    </nav>

    <div class="sidebar-footer">
        <form method="POST" action="logout.php" style="margin: 0;">
            <button type="submit" class="nav-item" style="background: none; border: none; width: 100%; cursor: pointer; text-align: left;">
                <i class="icon-log-out"></i>
                <span>Logout</span>
            </button>
        </form>
    </div>
</aside>
