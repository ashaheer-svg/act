<?php
/**
 * Shared Sidebar Component
 * 
 * Determines active nav item by comparing the current script name
 * against a defined route map. Pages that "belong" to Settings
 * (customers, profit_entry, upload, users) highlight the Settings item.
 */

$currentPage = basename($_SERVER['PHP_SELF']);

// Map each file to its parent nav item
$navMap = [
    'index.php'        => 'dashboard',
    'reports.php'      => 'reports',
    'settings.php'     => 'settings',
    'customers.php'    => 'settings',
    'profit_entry.php' => 'settings',
    'upload.php'       => 'settings',
    'users.php'        => 'settings',
];

$activeSection = $navMap[$currentPage] ?? '';

function navClass(string $section, string $activeSection): string {
    return 'nav-item' . ($section === $activeSection ? ' active' : '');
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
        <a href="index.php" class="<?= navClass('dashboard', $activeSection) ?>">
            <i class="icon-layout-dashboard"></i>
            <span>Dashboard</span>
        </a>
        <a href="reports.php" class="<?= navClass('reports', $activeSection) ?>">
            <i class="icon-bar-chart-2"></i>
            <span>Reporting</span>
        </a>
        <a href="settings.php#general" class="<?= navClass('settings', $activeSection) ?>">
            <i class="icon-settings"></i>
            <span>Settings</span>
        </a>
    </nav>
    <div class="sidebar-footer">
        <form method="POST" action="logout.php" style="margin: 0;">
            <button type="submit" class="nav-item" style="background: none; border: none; width: 100%; cursor: pointer;">
                <i class="icon-log-out"></i>
                <span>Logout</span>
            </button>
        </form>
    </div>
</aside>
