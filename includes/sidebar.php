<?php
/**
 * Shared Sidebar Component
 *
 * Auto-detects the current page and highlights the correct nav item.
 * The Settings group expands automatically when on any Settings sub-page.
 */

$currentPage = basename($_SERVER['PHP_SELF']);

// Map each page to its section (used for parent highlighting)
$sectionMap = [
    'index.php'        => 'dashboard',
    'reports.php'      => 'reports',
    'settings.php'     => 'settings',
    'customers.php'    => 'settings',
    'profit_entry.php' => 'settings',
    'upload.php'       => 'settings',
    'users.php'        => 'settings',
];

// Map each settings sub-page to its sub-nav key
$subPageMap = [
    'settings.php'     => 'general',
    'customers.php'    => 'customers',
    'profit_entry.php' => 'profit',
    'upload.php'       => 'upload',
    'users.php'        => 'users',
];

$activeSection  = $sectionMap[$currentPage]  ?? '';
$activeSubPage  = $subPageMap[$currentPage]  ?? '';
$settingsOpen   = ($activeSection === 'settings');

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

        <!-- Settings group -->
        <div class="nav-group <?= $settingsOpen ? 'open' : '' ?>">
            <button class="nav-item nav-group-toggle" onclick="toggleNavGroup(this)" type="button">
                <i class="icon-settings"></i>
                <span>Settings</span>
                <i class="icon-chevron-right nav-group-arrow"></i>
            </button>
            <div class="sub-nav">
                <a href="settings.php#general" class="<?= subNavClass('general', $activeSubPage) ?>">
                    <i class="icon-sliders"></i>
                    <span>General</span>
                </a>
                <a href="settings.php#security" class="<?= subNavClass('security', $activeSubPage) ?>">
                    <i class="icon-shield"></i>
                    <span>Security</span>
                </a>
                <a href="settings.php#team" class="<?= subNavClass('team', $activeSubPage) ?>">
                    <i class="icon-users"></i>
                    <span>Sales Team</span>
                </a>
                <a href="settings.php#rationalize" class="<?= subNavClass('rationalize', $activeSubPage) ?>">
                    <i class="icon-git-branch"></i>
                    <span>Product Mapping</span>
                </a>
                <a href="customers.php" class="<?= subNavClass('customers', $activeSubPage) ?>">
                    <i class="icon-building-2"></i>
                    <span>Customers</span>
                </a>
                <a href="profit_entry.php" class="<?= subNavClass('profit', $activeSubPage) ?>">
                    <i class="icon-dollar-sign"></i>
                    <span>Profit Entry</span>
                </a>
                <a href="upload.php" class="<?= subNavClass('upload', $activeSubPage) ?>">
                    <i class="icon-folder-up"></i>
                    <span>Data Upload</span>
                </a>
                <a href="users.php" class="<?= subNavClass('users', $activeSubPage) ?>">
                    <i class="icon-user"></i>
                    <span>User Management</span>
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
