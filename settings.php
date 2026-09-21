<?php
/**
 * Settings & Configuration Controller
 * Active Solutions BI Platform
 * 
 * Modular Dispatcher: Delegates to modules/settings/{$tab}.php
 */

require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/Auth.php';
require_once 'classes/Validator.php';

$db = new Database(DATABASE_PATH);
$auth = new Auth($db);
$auth->requireLogin();

$user = $auth->getCurrentUser();
$message = '';
$messageType = '';

// Initialize database settings
$db->initializeSettings();

// Supported settings sections
$tabs = [
    'system' => ['title' => 'System Setup', 'icon' => 'icon-settings', 'admin_only' => false],
    'team'   => ['title' => 'Access & Team', 'icon' => 'icon-users', 'admin_only' => false],
    'tax'    => ['title' => 'Tax & VAT Rules', 'icon' => 'icon-file-text', 'admin_only' => false],
    'sync'   => ['title' => 'QuickBooks Sync', 'icon' => 'icon-refresh-cw', 'admin_only' => false],
    'reps'   => ['title' => 'Sales Reps', 'icon' => 'icon-user-check', 'admin_only' => false],
    'ai'     => ['title' => 'AI Engine', 'icon' => 'icon-cpu', 'admin_only' => true],
    'danger' => ['title' => 'Maintenance & Sort', 'icon' => 'icon-alert-triangle', 'admin_only' => true]
];

// Determine active tab
$activeTab = trim($_GET['tab'] ?? 'system');

// Handle common aliases or hash anchors
if (in_array($activeTab, ['users', 'security', 'access', 'manage_users'])) {
    $activeTab = 'team';
}
if (!array_key_exists($activeTab, $tabs)) {
    $activeTab = 'system';
}

// Check admin restriction on tab
if (!empty($tabs[$activeTab]['admin_only']) && !$auth->isAdmin()) {
    $activeTab = 'system';
}

// Module file to dispatch
$tabModuleFile = __DIR__ . "/modules/settings/{$activeTab}.php";

// Execute module logic & capture output
ob_start();
if (file_exists($tabModuleFile)) {
    require $tabModuleFile;
} else {
    echo '<div class="card"><p>Settings module not found.</p></div>';
}
$tabContentHtml = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($tabs[$activeTab]['title']); ?> - Settings - Activity</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="docs/lucide-font/lucide.css">
    <link rel="stylesheet" href="layout.css?v=1.0.3">
    <style>
        .settings-nav-bar {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 14px;
            overflow-x: auto;
            align-items: center;
        }
        .settings-tab-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 16px;
            border-radius: 8px;
            font-size: 13.5px;
            font-weight: 600;
            color: #475569;
            text-decoration: none;
            transition: all 0.15s ease;
            white-space: nowrap;
            background: #ffffff;
            border: 1px solid var(--border-color);
        }
        .settings-tab-link:hover {
            background: #f1f5f9;
            color: #0f172a;
            border-color: #cbd5e1;
        }
        .settings-tab-link.active {
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }
        .btn-danger-link {
            background: none;
            border: none;
            color: var(--danger);
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            padding: 0;
        }
        .btn-danger-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php require_once 'includes/sidebar.php'; ?>

        <main class="main-wrapper">
            <?php $searchPlaceholder = 'Search settings...'; require_once 'includes/header.php'; ?>

            <div class="content-body">
                <?php if (!empty($message)): ?>
                <div class="message <?php echo htmlspecialchars($messageType); ?>" style="margin-bottom: 20px;">
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <?php endif; ?>

                <!-- Settings Sub-Navigation Tabs Bar -->
                <div class="settings-nav-bar">
                    <?php foreach ($tabs as $tKey => $tConfig): ?>
                        <?php if (!empty($tConfig['admin_only']) && !$auth->isAdmin()) continue; ?>
                        <a href="settings.php?tab=<?php echo $tKey; ?>" 
                           class="settings-tab-link <?php echo $activeTab === $tKey ? 'active' : ''; ?>">
                            <i class="<?php echo $tConfig['icon']; ?>"></i>
                            <span><?php echo htmlspecialchars($tConfig['title']); ?></span>
                        </a>
                    <?php endforeach; ?>

                    <?php if ($auth->isAdmin()): ?>
                    <a href="rbac.php" class="settings-tab-link" style="margin-left: auto; background: #ede9fe; color: #6d28d9; border-color: #c4b5fd;">
                        <i class="icon-shield"></i>
                        <span>RBAC Matrix &rarr;</span>
                    </a>
                    <?php endif; ?>
                </div>

                <!-- Rendered Active Tab Content -->
                <div class="tab-content-container">
                    <?php echo $tabContentHtml; ?>
                </div>
            </div>
        </main>
    </div>

    <?php require_once 'includes/layout_js.php'; ?>
    <script>
    // Handle historical #hash anchors seamlessly
    window.addEventListener('DOMContentLoaded', () => {
        if (window.location.hash) {
            const h = window.location.hash.replace('#', '').toLowerCase();
            const currentTab = "<?php echo $activeTab; ?>";
            if (h === 'rbac') {
                window.location.href = 'rbac.php';
            } else if (['system', 'team', 'tax', 'sync', 'reps', 'ai', 'danger', 'users', 'security'].includes(h)) {
                const target = ['users', 'security', 'access'].includes(h) ? 'team' : h;
                if (target !== currentTab) {
                    window.location.href = 'settings.php?tab=' + target;
                }
            }
        }
    });
    </script>
</body>
</html>
