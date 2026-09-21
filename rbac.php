<?php
/**
 * RBAC Permissions Matrix - Role-Based Access Control
 * Activity Sales BI - Granular Module & Report Permission Management
 */

require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/Auth.php';

$db = new Database(DATABASE_PATH);
$auth = new Auth($db);
$auth->requireAdmin(); // RBAC matrix is exclusively for administrators

$user = $auth->getCurrentUser();
$message = '';
$messageType = '';

// Handle AJAX or POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $isAjax = !empty($_POST['ajax']) || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

    if ($action === 'toggle_report_permission') {
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        $reportKey = trim($_POST['report_key'] ?? '');
        $allowed = !empty($_POST['is_allowed']) ? 1 : 0;
        
        $success = $auth->setUserReportPermission($targetUserId, $reportKey, $allowed);
        $targetUser = $db->fetch("SELECT username FROM users WHERE id = ?", [$targetUserId]);
        $targetName = $targetUser['username'] ?? "ID #$targetUserId";
        $db->logActivity($user['id'], 'RBAC_PERMISSION_TOGGLED', "User '$targetName' permission for '$reportKey' set to " . ($allowed ? 'ALLOWED' : 'RESTRICTED'));

        if ($isAjax) {
            while (ob_get_level()) { ob_end_clean(); }
            header('Content-Type: application/json');
            echo json_encode(['success' => (bool)$success, 'user_id' => $targetUserId, 'report_key' => $reportKey, 'is_allowed' => $allowed]);
            exit;
        }
        $message = "Report permission updated successfully.";
        $messageType = 'success';
    }

    if ($action === 'toggle_category_permissions') {
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        $category = trim($_POST['category'] ?? '');
        $allowed = !empty($_POST['is_allowed']) ? 1 : 0;

        try {
            $auth->toggleCategoryPermissions($targetUserId, $category, $allowed);
            $newPerms = $auth->getUserReportPermissions($targetUserId);
            $targetUser = $db->fetch("SELECT username FROM users WHERE id = ?", [$targetUserId]);
            $targetName = $targetUser['username'] ?? "ID #$targetUserId";
            $db->logActivity($user['id'], 'RBAC_CATEGORY_TOGGLED', "Set category '$category' to " . ($allowed ? 'ALLOWED' : 'RESTRICTED') . " for user '$targetName'");

            if ($isAjax) {
                while (ob_get_level()) { ob_end_clean(); }
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'user_id' => $targetUserId, 'category' => $category, 'is_allowed' => $allowed, 'permissions' => $newPerms]);
                exit;
            }
            $message = "Category permissions updated successfully.";
            $messageType = 'success';
        } catch (Exception $e) {
            if ($isAjax) {
                while (ob_get_level()) { ob_end_clean(); }
                header('Content-Type: application/json', true, 400);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                exit;
            }
            $message = "Error: " . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'clone_user_permissions') {
        $sourceUserId = (int)($_POST['source_user_id'] ?? 0);
        $targetUserId = (int)($_POST['target_user_id'] ?? 0);

        try {
            if ($sourceUserId === $targetUserId) {
                throw new Exception("Source and target user cannot be the same.");
            }

            $auth->cloneUserPermissions($sourceUserId, $targetUserId);
            $newPerms = $auth->getUserReportPermissions($targetUserId);
            $sourceUser = $db->fetch("SELECT username FROM users WHERE id = ?", [$sourceUserId]);
            $targetUser = $db->fetch("SELECT username FROM users WHERE id = ?", [$targetUserId]);
            $sName = $sourceUser['username'] ?? "ID #$sourceUserId";
            $tName = $targetUser['username'] ?? "ID #$targetUserId";
            $db->logActivity($user['id'], 'RBAC_PERMISSIONS_CLONED', "Cloned permissions from '$sName' to '$tName'");

            if ($isAjax) {
                while (ob_get_level()) { ob_end_clean(); }
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'user_id' => $targetUserId, 'permissions' => $newPerms, 'source_username' => $sName]);
                exit;
            }
            $message = "Permissions cloned successfully.";
            $messageType = 'success';
        } catch (Exception $e) {
            if ($isAjax) {
                while (ob_get_level()) { ob_end_clean(); }
                header('Content-Type: application/json', true, 400);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                exit;
            }
            $message = "Error cloning permissions: " . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'apply_user_preset') {
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        $preset = trim($_POST['preset'] ?? '');

        try {
            $auth->applyUserPreset($targetUserId, $preset);
            $newPerms = $auth->getUserReportPermissions($targetUserId);
            $targetUser = $db->fetch("SELECT username FROM users WHERE id = ?", [$targetUserId]);
            $targetName = $targetUser['username'] ?? "ID #$targetUserId";
            $db->logActivity($user['id'], 'RBAC_PRESET_APPLIED', "Applied preset '$preset' to user '$targetName'");

            if ($isAjax) {
                while (ob_get_level()) { ob_end_clean(); }
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'user_id' => $targetUserId, 'preset' => $preset, 'permissions' => $newPerms]);
                exit;
            }
            $message = "Preset '" . ucfirst(str_replace('_', ' ', $preset)) . "' applied successfully.";
            $messageType = 'success';
        } catch (Exception $e) {
            if ($isAjax) {
                while (ob_get_level()) { ob_end_clean(); }
                header('Content-Type: application/json', true, 400);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                exit;
            }
            $message = "Error applying preset: " . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Data fetching
$systemUsers = $db->fetchAll("SELECT id, username, role, created_at FROM users ORDER BY created_at DESC");
$reportDefinitions = Auth::getReportDefinitions();
$allUserPermissions = [];
foreach ($systemUsers as $su) {
    $allUserPermissions[$su['id']] = $auth->getUserReportPermissions($su['id']);
}

// Audit trail: Recent RBAC activity logs
$recentRbacLogs = [];
try {
    $recentRbacLogs = $db->fetchAll("
        SELECT al.id, al.user_id, u.username as admin_user, al.action, al.description as details, al.activity_date as created_at
        FROM activity_log al
        LEFT JOIN users u ON al.user_id = u.id
        WHERE al.action LIKE 'RBAC_%'
        ORDER BY al.activity_date DESC
        LIMIT 15
    ");
} catch (Exception $e) {
    $recentRbacLogs = [];
}

// Statistics
$totalUsersCount = count($systemUsers);
$adminCount = count(array_filter($systemUsers, fn($u) => $u['role'] === 'admin'));
$configurableUsersCount = $totalUsersCount - $adminCount;
$totalReportsCount = count($reportDefinitions);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RBAC Report Permissions Matrix - Activity</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="docs/lucide-font/lucide.css">
    <link rel="stylesheet" href="layout.css?v=1.0.2">
    <style>
        /* RBAC Page Specific Layout & Styling */
        .rbac-header-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 24px 28px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            border-left: 5px solid #7c3aed;
        }
        .rbac-header-flex {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 16px;
        }
        .rbac-title-area h1 {
            font-size: 22px;
            font-weight: 800;
            color: var(--text-main);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .badge-rbac {
            font-size: 11px;
            font-weight: 700;
            color: #6d28d9;
            background: #ede9fe;
            padding: 4px 10px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge-status {
            font-size: 11px;
            font-weight: 700;
            color: #16a34a;
            background: #dcfce7;
            padding: 4px 10px;
            border-radius: 20px;
            text-transform: uppercase;
        }
        .rbac-desc {
            color: var(--text-muted);
            font-size: 13.5px;
            margin-top: 6px;
            margin-bottom: 0;
            max-width: 840px;
            line-height: 1.55;
        }
        .rbac-btn-group {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        /* Metric Summary Cards */
        .rbac-metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .metric-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 16px 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .metric-icon-wrap {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }
        .metric-icon-purple { background: #ede9fe; color: #7c3aed; }
        .metric-icon-blue { background: #dbeafe; color: #2563eb; }
        .metric-icon-green { background: #dcfce7; color: #16a34a; }
        .metric-icon-amber { background: #fef3c7; color: #d97706; }
        .metric-content {
            display: flex;
            flex-direction: column;
        }
        .metric-label {
            font-size: 11.5px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
        }
        .metric-value {
            font-size: 20px;
            font-weight: 800;
            color: var(--text-main);
            margin-top: 2px;
        }
        .metric-sub {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 1px;
        }

        /* Filter Controls Card */
        .rbac-controls-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 14px 18px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }
        .category-pills {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .cat-pill {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            background: #f8fafc;
            color: #475569;
            border: 1px solid var(--border-color);
            transition: all 0.15s;
        }
        .cat-pill:hover {
            background: #e2e8f0;
            color: #0f172a;
        }
        .cat-pill.active {
            background: #7c3aed;
            color: white;
            border-color: #7c3aed;
        }
        .rbac-search-box {
            padding: 8px 14px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 13px;
            width: 280px;
            outline: none;
            background: #ffffff;
            transition: border-color 0.15s;
        }
        .rbac-search-box:focus {
            border-color: #7c3aed;
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
        }

        /* Matrix Table Component */
        .matrix-table-wrap {
            overflow-x: auto;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            background: #ffffff;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
        }
        .matrix-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            text-align: left;
        }
        .matrix-table th {
            background: #f8fafc;
            padding: 14px 16px;
            font-weight: 700;
            color: #475569;
            border-bottom: 2px solid var(--border-color);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: sticky;
            top: 0;
            z-index: 5;
        }
        .matrix-table td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }
        .matrix-table tr:hover td {
            background: #fdfbf7;
        }
        .cat-header-row td {
            background: #f1f5f9 !important;
            font-weight: 800;
            color: #1e293b;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.75px;
            padding: 10px 16px;
            border-top: 1px solid #cbd5e1;
        }
        .report-info {
            display: flex;
            flex-direction: column;
        }
        .report-name {
            font-weight: 700;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .report-desc {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 2px;
            line-height: 1.4;
        }
        .report-key-tag {
            font-family: 'JetBrains Mono', monospace;
            font-size: 10.5px;
            background: #f1f5f9;
            color: #475569;
            padding: 2px 6px;
            border-radius: 4px;
            margin-left: 6px;
            border: 1px solid #e2e8f0;
        }
        .user-col-header {
            text-align: center;
            min-width: 155px;
        }
        .user-col-cell {
            text-align: center;
        }
        .user-role-badge {
            font-size: 10px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 12px;
            text-transform: uppercase;
            display: inline-block;
            margin-top: 2px;
        }
        .role-admin { background: #fee2e2; color: #991b1b; }
        .role-accounts { background: #e0e7ff; color: #3730a3; }
        .role-viewer { background: #f1f5f9; color: #475569; }

        /* Modern Toggle Switch */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
            cursor: pointer;
            margin: 0 auto;
        }
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: #cbd5e1;
            transition: 0.2s;
            border-radius: 24px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.2s;
            border-radius: 50%;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        input:checked + .slider {
            background-color: #16a34a;
        }
        input:checked + .slider:before {
            transform: translateX(20px);
        }
        .lock-indicator {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            font-weight: 700;
            color: #16a34a;
            background: #dcfce7;
            padding: 3px 8px;
            border-radius: 12px;
        }
        .preset-bar {
            margin-top: 6px;
            display: flex;
            gap: 3px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn-preset {
            font-size: 9.5px;
            padding: 2px 5px;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            cursor: pointer;
            color: #475569;
            font-weight: 600;
            transition: all 0.15s;
        }
        .btn-preset:hover {
            background: #e2e8f0;
            color: #0f172a;
        }

        /* Category Bulk Action Buttons */
        .btn-cat-bulk {
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 700;
            border: 1px solid transparent;
            transition: all 0.15s;
        }
        .btn-cat-on {
            background: #dcfce7;
            color: #166534;
            border-color: #bbf7d0;
        }
        .btn-cat-on:hover { background: #bbf7d0; }
        .btn-cat-off {
            background: #fee2e2;
            color: #991b1b;
            border-color: #fecaca;
        }
        .btn-cat-off:hover { background: #fecaca; }

        /* RBAC Toast Banner */
        .rbac-toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: #0f172a;
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            box-shadow: var(--shadow-md);
            display: none;
            align-items: center;
            gap: 10px;
            z-index: 1000;
        }

        /* Clone Permissions Modal */
        .clone-modal-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(2px);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .clone-modal-box {
            background: #ffffff;
            border-radius: 12px;
            width: 420px;
            max-width: 90%;
            padding: 24px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            border: 1px solid var(--border-color);
        }
    </style>
</head>
<body class="has-slim-sidebar">
    <div class="app-container">
        <?php require_once 'includes/sidebar.php'; ?>

        <main class="main-wrapper">
            <?php $searchPlaceholder = 'Search permissions & modules...'; require_once 'includes/header.php'; ?>

            <div class="content-body">
                <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <?php endif; ?>

                <!-- RBAC Hero Header Card -->
                <div class="rbac-header-card">
                    <div class="rbac-header-flex">
                        <div class="rbac-title-area">
                            <h1>
                                <span>🛡️ Granular Report Permissions Matrix</span>
                                <span class="badge-rbac">RBAC Engine v2.1</span>
                                <span class="badge-status">Zero-SQL In-Memory Cache</span>
                            </h1>
                            <p class="rbac-desc">
                                Configure granular visibility and route authorization for individual team members across all 33 BI modules and operational actions. 
                                Invoice editing is now strictly separated from view access. Changes synchronize immediately with O(1) in-memory session acceleration.
                            </p>
                        </div>
                        <div class="rbac-btn-group">
                            <button type="button" class="btn" style="background: #ffffff; border: 1px solid var(--border-color); color: var(--text-main); font-size: 13px; font-weight: 600;" onclick="exportRbacMatrix()">
                                <i class="icon-file-text"></i>
                                <span>Export Matrix (CSV)</span>
                            </button>
                            <a href="settings.php#team" class="btn btn-primary" style="font-size: 13px; font-weight: 600; text-decoration: none;">
                                <i class="icon-users"></i>
                                <span>Manage Users & Team</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- KPI Metric Highlights -->
                <div class="rbac-metrics-grid">
                    <div class="metric-card">
                        <div class="metric-icon-wrap metric-icon-purple">
                            <i class="icon-users"></i>
                        </div>
                        <div class="metric-content">
                            <span class="metric-label">System Users</span>
                            <span class="metric-value"><?php echo $totalUsersCount; ?></span>
                            <span class="metric-sub"><?php echo $configurableUsersCount; ?> Configurable (<?php echo $adminCount; ?> Admins)</span>
                        </div>
                    </div>

                    <div class="metric-card">
                        <div class="metric-icon-wrap metric-icon-blue">
                            <i class="icon-shield"></i>
                        </div>
                        <div class="metric-content">
                            <span class="metric-label">Protected Modules</span>
                            <span class="metric-value"><?php echo $totalReportsCount; ?></span>
                            <span class="metric-sub">Across 4 Functional Categories</span>
                        </div>
                    </div>

                    <div class="metric-card">
                        <div class="metric-icon-wrap metric-icon-green">
                            <i class="icon-sliders"></i>
                        </div>
                        <div class="metric-content">
                            <span class="metric-label">Security Engine</span>
                            <span class="metric-value">Active</span>
                            <span class="metric-sub">Separated Read &amp; Edit Actions</span>
                        </div>
                    </div>

                    <div class="metric-card">
                        <div class="metric-icon-wrap metric-icon-amber">
                            <i class="icon-lock"></i>
                        </div>
                        <div class="metric-content">
                            <span class="metric-label">Admin Policy</span>
                            <span class="metric-value">Global Bypass</span>
                            <span class="metric-sub">Admins retain root override</span>
                        </div>
                    </div>
                </div>

                <!-- Filter & Search Controls -->
                <div class="rbac-controls-card">
                    <div class="category-pills">
                        <button type="button" class="cat-pill active" onclick="filterRbacCategory('all', this)">All Modules (<?php echo count($reportDefinitions); ?>)</button>
                        <button type="button" class="cat-pill" onclick="filterRbacCategory('core', this)">Core Reports (6)</button>
                        <button type="button" class="cat-pill" onclick="filterRbacCategory('analytics', this)">Analytics &amp; BI (11)</button>
                        <button type="button" class="cat-pill" onclick="filterRbacCategory('operations', this)">Operations &amp; Tools (7)</button>
                        <button type="button" class="cat-pill" onclick="filterRbacCategory('archived', this)">Archived Reports (9)</button>
                    </div>
                    <div>
                        <input type="text" class="rbac-search-box" id="rbacSearchInput" onkeyup="filterRbacReports()" placeholder="🔍 Search reports or module keys...">
                    </div>
                </div>

                <!-- Permissions Grid Table -->
                <div class="matrix-table-wrap">
                    <table class="matrix-table" id="rbacMatrixTable">
                        <thead>
                            <tr>
                                <th style="width: 36%;">Report / Module Name</th>
                                <?php foreach ($systemUsers as $su): 
                                    $roleClass = $su['role'] === 'admin' ? 'role-admin' : ($su['role'] === 'accounts' ? 'role-accounts' : 'role-viewer');
                                ?>
                                <th class="user-col-header" data-username="<?php echo htmlspecialchars($su['username']); ?>">
                                    <div style="font-weight: 700; color: var(--text-main); font-size: 13px;"><?php echo htmlspecialchars($su['username']); ?></div>
                                    <span class="user-role-badge <?php echo $roleClass; ?>"><?php echo strtoupper($su['role']); ?></span>
                                    <?php if ($su['role'] !== 'admin'): ?>
                                    <div class="preset-bar">
                                        <button type="button" class="btn-preset" title="Assign All Reports" onclick="applyRbacPreset(<?php echo $su['id']; ?>, 'all')">All</button>
                                        <button type="button" class="btn-preset" title="Finance Preset" onclick="applyRbacPreset(<?php echo $su['id']; ?>, 'finance')">Fin</button>
                                        <button type="button" class="btn-preset" title="Sales Preset" onclick="applyRbacPreset(<?php echo $su['id']; ?>, 'sales')">Sales</button>
                                        <button type="button" class="btn-preset" title="Executive Preset" onclick="applyRbacPreset(<?php echo $su['id']; ?>, 'executive')">Exec</button>
                                        <button type="button" class="btn-preset" title="Reset to Role Default" onclick="applyRbacPreset(<?php echo $su['id']; ?>, 'role_default')">Default</button>
                                        <button type="button" class="btn-preset" title="Remove All" onclick="applyRbacPreset(<?php echo $su['id']; ?>, 'none')">None</button>
                                        <button type="button" class="btn-preset" style="background: #ede9fe; color: #6d28d9; border-color: #c4b5fd;" title="Copy permissions from another user" onclick="openCloneModal(<?php echo $su['id']; ?>, '<?php echo htmlspecialchars($su['username']); ?>')">Clone</button>
                                    </div>
                                    <?php else: ?>
                                    <div style="margin-top: 6px;">
                                        <span class="lock-indicator">✓ Superadmin</span>
                                    </div>
                                    <?php endif; ?>
                                </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $categories = [
                                'core' => 'Core Business Reports',
                                'analytics' => 'Active Analytics & Intelligence',
                                'operations' => 'Operations & Financial Tools',
                                'archived' => 'Archived Reports & Legacy Modules'
                            ];

                            foreach ($categories as $catKey => $catTitle): 
                                $catReports = array_filter($reportDefinitions, function($r) use ($catKey) {
                                    return $r['category'] === $catKey;
                                });
                                if (empty($catReports)) continue;
                            ?>
                            <tr class="cat-header-row" data-cat="<?php echo $catKey; ?>">
                                <td>
                                    <div style="display: flex; align-items: center; justify-content: space-between;">
                                        <span><?php echo htmlspecialchars($catTitle); ?> (<?php echo count($catReports); ?> Modules)</span>
                                    </div>
                                </td>
                                <?php foreach ($systemUsers as $su): ?>
                                <td class="user-col-cell">
                                    <?php if ($su['role'] !== 'admin'): ?>
                                    <div style="display: flex; gap: 4px; justify-content: center;">
                                        <button type="button" class="btn-cat-bulk btn-cat-on" title="Grant all <?php echo htmlspecialchars($catTitle); ?> to <?php echo htmlspecialchars($su['username']); ?>" onclick="toggleCategoryPerms(<?php echo $su['id']; ?>, '<?php echo $catKey; ?>', 1)">+ All</button>
                                        <button type="button" class="btn-cat-bulk btn-cat-off" title="Revoke all <?php echo htmlspecialchars($catTitle); ?> for <?php echo htmlspecialchars($su['username']); ?>" onclick="toggleCategoryPerms(<?php echo $su['id']; ?>, '<?php echo $catKey; ?>', 0)">- None</button>
                                    </div>
                                    <?php else: ?>
                                    <span style="font-size: 11px; opacity: 0.7;">✓ All</span>
                                    <?php endif; ?>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php foreach ($catReports as $k => $def): ?>
                            <tr data-cat="<?php echo $catKey; ?>" data-name="<?php echo htmlspecialchars(strtolower($def['name'] . ' ' . $def['desc'])); ?>" data-key="<?php echo htmlspecialchars($k); ?>">
                                <td>
                                    <div class="report-info">
                                        <div class="report-name">
                                            <span><?php echo htmlspecialchars($def['name']); ?></span>
                                            <span class="report-key-tag"><?php echo htmlspecialchars($k); ?></span>
                                        </div>
                                        <div class="report-desc"><?php echo htmlspecialchars($def['desc']); ?></div>
                                    </div>
                                </td>
                                <?php foreach ($systemUsers as $su): ?>
                                <td class="user-col-cell">
                                    <?php if ($su['role'] === 'admin'): ?>
                                        <span class="lock-indicator" title="Admins retain unrestricted access to all reports">✓ Full Access</span>
                                    <?php else: 
                                        $isAllowed = !empty($allUserPermissions[$su['id']][$k]);
                                    ?>
                                        <label class="toggle-switch">
                                            <input type="checkbox" 
                                                   data-user-id="<?php echo $su['id']; ?>" 
                                                   data-report-key="<?php echo htmlspecialchars($k); ?>" 
                                                   <?php echo $isAllowed ? 'checked' : ''; ?> 
                                                   onchange="toggleRbacPerm(<?php echo $su['id']; ?>, '<?php echo htmlspecialchars($k); ?>', this)">
                                            <span class="slider"></span>
                                        </label>
                                    <?php endif; ?>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Recent RBAC Audit Activity Log -->
                <div class="card" style="margin-top: 30px; border-left: 4px solid #3b82f6;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                        <h3 style="margin: 0; display: flex; align-items: center; gap: 8px; font-size: 15px;">
                            <span>📋 Recent Permission Activity & Audit Trail</span>
                            <span style="font-size: 10px; font-weight: 700; color: #2563eb; background: #dbeafe; padding: 2px 8px; border-radius: 12px;">SECURITY AUDIT</span>
                        </h3>
                        <span style="font-size: 12px; color: var(--text-muted);">Last 15 Security Actions</span>
                    </div>
                    <div style="overflow-x: auto;">
                        <table class="table" style="width: 100%; font-size: 12.5px;">
                            <thead>
                                <tr>
                                    <th style="text-align: left; padding: 8px 12px;">Timestamp</th>
                                    <th style="text-align: left; padding: 8px 12px;">Admin</th>
                                    <th style="text-align: left; padding: 8px 12px;">Action Type</th>
                                    <th style="text-align: left; padding: 8px 12px;">Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentRbacLogs)): ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 20px;">No recent RBAC events logged yet.</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($recentRbacLogs as $log): ?>
                                <tr>
                                    <td style="padding: 8px 12px; color: var(--text-muted); border-bottom: 1px solid var(--border-color); font-family: monospace; font-size: 11px;">
                                        <?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?>
                                    </td>
                                    <td style="padding: 8px 12px; font-weight: 600; border-bottom: 1px solid var(--border-color);">
                                        <?php echo htmlspecialchars($log['admin_user'] ?? 'System'); ?>
                                    </td>
                                    <td style="padding: 8px 12px; border-bottom: 1px solid var(--border-color);">
                                        <span style="font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 4px; background: #f1f5f9; color: #334155; font-family: monospace;">
                                            <?php echo htmlspecialchars($log['action']); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 8px 12px; color: var(--text-main); border-bottom: 1px solid var(--border-color);">
                                        <?php echo htmlspecialchars($log['details']); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- Clone Permissions Modal Dialog -->
    <div id="cloneModalOverlay" class="clone-modal-overlay" style="display: none;">
        <div class="clone-modal-box">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                <h3 style="margin: 0; font-size: 16px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                    <span>👥 Clone Permissions Blueprint</span>
                </h3>
                <button type="button" onclick="closeCloneModal()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: var(--text-muted);">&times;</button>
            </div>
            <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 16px; line-height: 1.5;">
                Copy all report and module authorizations from a selected user to <strong id="cloneTargetUsername" style="color: var(--text-main);">Target User</strong>. Existing permissions will be overwritten.
            </p>
            <input type="hidden" id="cloneTargetUserId" value="">
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 6px;">Copy Permissions From:</label>
                <select id="cloneSourceUserId" class="form-control" style="width: 100%; padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 13px;">
                    <?php foreach ($systemUsers as $su): ?>
                    <option value="<?php echo $su['id']; ?>"><?php echo htmlspecialchars($su['username']); ?> (<?php echo strtoupper($su['role']); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn" style="background: #f1f5f9; border: 1px solid var(--border-color); font-size: 13px;" onclick="closeCloneModal()">Cancel</button>
                <button type="button" class="btn btn-primary" style="font-size: 13px;" onclick="submitClonePermissions()">Confirm &amp; Clone</button>
            </div>
        </div>
    </div>

    <!-- Notification Toast Banner -->
    <div class="rbac-toast" id="rbacToast">
        <span id="rbacToastIcon">✓</span>
        <span id="rbacToastMsg">Permissions updated successfully!</span>
    </div>

    <?php require_once 'includes/layout_js.php'; ?>
    <script>
    function filterRbacCategory(cat, btn) {
        document.querySelectorAll('.cat-pill').forEach(p => p.classList.remove('active'));
        if (btn) btn.classList.add('active');

        const rows = document.querySelectorAll('#rbacMatrixTable tbody tr');
        rows.forEach(r => {
            if (cat === 'all' || r.dataset.cat === cat) {
                r.style.display = '';
            } else {
                r.style.display = 'none';
            }
        });
    }

    function filterRbacReports() {
        const q = (document.getElementById('rbacSearchInput').value || '').toLowerCase().trim();
        const rows = document.querySelectorAll('#rbacMatrixTable tbody tr');
        rows.forEach(r => {
            if (r.classList.contains('cat-header-row')) {
                r.style.display = q === '' ? '' : 'none';
                return;
            }
            const text = ((r.dataset.name || '') + ' ' + (r.dataset.key || '')).toLowerCase();
            r.style.display = (q === '' || text.includes(q)) ? '' : 'none';
        });
    }

    function showRbacToast(msg, isSuccess = true) {
        const toast = document.getElementById('rbacToast');
        const icon = document.getElementById('rbacToastIcon');
        const text = document.getElementById('rbacToastMsg');
        if (!toast) return;

        text.innerText = msg;
        icon.innerText = isSuccess ? '✓' : '⚠️';
        toast.style.background = isSuccess ? '#0f172a' : '#dc2626';
        toast.style.display = 'flex';
        
        if (window._rbacToastTimeout) clearTimeout(window._rbacToastTimeout);
        window._rbacToastTimeout = setTimeout(() => { toast.style.display = 'none'; }, 2500);
    }

    function toggleRbacPerm(userId, reportKey, checkbox) {
        const isAllowed = checkbox.checked ? 1 : 0;
        checkbox.disabled = true;

        const formData = new FormData();
        formData.append('action', 'toggle_report_permission');
        formData.append('user_id', userId);
        formData.append('report_key', reportKey);
        formData.append('is_allowed', isAllowed);
        formData.append('ajax', '1');

        fetch('rbac.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            checkbox.disabled = false;
            if (data && data.success) {
                showRbacToast(`Permission '${reportKey}' ${isAllowed ? 'enabled' : 'disabled'}`);
            } else {
                checkbox.checked = !checkbox.checked;
                showRbacToast(`Error: ${data.error || 'Failed to update'}`, false);
            }
        })
        .catch(err => {
            checkbox.disabled = false;
            checkbox.checked = !checkbox.checked;
            showRbacToast('Connection error updating permission', false);
        });
    }

    function toggleCategoryPerms(userId, catKey, isAllowed) {
        const formData = new FormData();
        formData.append('action', 'toggle_category_permissions');
        formData.append('user_id', userId);
        formData.append('category', catKey);
        formData.append('is_allowed', isAllowed);
        formData.append('ajax', '1');

        fetch('rbac.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            if (data && data.success && data.permissions) {
                const checkboxes = document.querySelectorAll(`input[data-user-id="${userId}"]`);
                checkboxes.forEach(cb => {
                    const key = cb.getAttribute('data-report-key');
                    if (data.permissions.hasOwnProperty(key)) {
                        cb.checked = !!data.permissions[key];
                    }
                });
                showRbacToast(`Category '${catKey.toUpperCase()}' ${isAllowed ? 'enabled' : 'disabled'}!`);
            } else {
                showRbacToast(`Error: ${data.error || 'Failed to update category'}`, false);
            }
        })
        .catch(err => {
            showRbacToast('Connection error updating category', false);
        });
    }

    function applyRbacPreset(userId, preset) {
        const formData = new FormData();
        formData.append('action', 'apply_user_preset');
        formData.append('user_id', userId);
        formData.append('preset', preset);
        formData.append('ajax', '1');

        fetch('rbac.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            if (data && data.success && data.permissions) {
                const checkboxes = document.querySelectorAll(`input[data-user-id="${userId}"]`);
                checkboxes.forEach(cb => {
                    const key = cb.getAttribute('data-report-key');
                    if (data.permissions.hasOwnProperty(key)) {
                        cb.checked = !!data.permissions[key];
                    }
                });
                showRbacToast(`Applied '${preset.toUpperCase().replace('_', ' ')}' preset successfully!`);
            } else {
                showRbacToast(`Error applying preset: ${data.error || 'Server error'}`, false);
            }
        })
        .catch(err => {
            showRbacToast('Connection error applying preset', false);
        });
    }

    function openCloneModal(targetUserId, username) {
        document.getElementById('cloneTargetUserId').value = targetUserId;
        document.getElementById('cloneTargetUsername').innerText = username;
        // Pre-select first option that is not target
        const select = document.getElementById('cloneSourceUserId');
        for (let i = 0; i < select.options.length; i++) {
            if (select.options[i].value != targetUserId) {
                select.selectedIndex = i;
                break;
            }
        }
        document.getElementById('cloneModalOverlay').style.display = 'flex';
    }

    function closeCloneModal() {
        document.getElementById('cloneModalOverlay').style.display = 'none';
    }

    function submitClonePermissions() {
        const targetUserId = document.getElementById('cloneTargetUserId').value;
        const sourceUserId = document.getElementById('cloneSourceUserId').value;

        if (!targetUserId || !sourceUserId) return;
        if (targetUserId === sourceUserId) {
            alert("Please select a different user to copy from.");
            return;
        }

        const formData = new FormData();
        formData.append('action', 'clone_user_permissions');
        formData.append('source_user_id', sourceUserId);
        formData.append('target_user_id', targetUserId);
        formData.append('ajax', '1');

        fetch('rbac.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            closeCloneModal();
            if (data && data.success && data.permissions) {
                const checkboxes = document.querySelectorAll(`input[data-user-id="${targetUserId}"]`);
                checkboxes.forEach(cb => {
                    const key = cb.getAttribute('data-report-key');
                    if (data.permissions.hasOwnProperty(key)) {
                        cb.checked = !!data.permissions[key];
                    }
                });
                showRbacToast(`Cloned permissions from ${data.source_username || 'User'}!`);
            } else {
                showRbacToast(`Error: ${data.error || 'Failed to clone permissions'}`, false);
            }
        })
        .catch(err => {
            closeCloneModal();
            showRbacToast('Connection error cloning permissions', false);
        });
    }

    function exportRbacMatrix() {
        const table = document.getElementById('rbacMatrixTable');
        if (!table) return;

        let csv = [];
        // Header
        const ths = table.querySelectorAll('thead th');
        let headerRow = [];
        ths.forEach(th => {
            headerRow.push('"' + (th.dataset.username || th.innerText.split('\n')[0]).replace(/"/g, '""') + '"');
        });
        csv.push(headerRow.join(','));

        // Body rows
        const trs = table.querySelectorAll('tbody tr');
        trs.forEach(tr => {
            if (tr.classList.contains('cat-header-row')) {
                csv.push('"' + tr.innerText.trim().replace(/"/g, '""') + '"');
                return;
            }
            let row = [];
            const key = tr.dataset.key || '';
            const nameEl = tr.querySelector('.report-name span');
            const reportName = nameEl ? nameEl.innerText : key;
            row.push('"' + reportName.replace(/"/g, '""') + ' (' + key + ')"');

            const cells = tr.querySelectorAll('td.user-col-cell');
            cells.forEach(c => {
                const lock = c.querySelector('.lock-indicator');
                if (lock) {
                    row.push('"Allowed (Superadmin)"');
                } else {
                    const cb = c.querySelector('input[type="checkbox"]');
                    row.push(cb && cb.checked ? '"Allowed"' : '"Restricted"');
                }
            });
            csv.push(row.join(','));
        });

        const blob = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.setAttribute('download', 'rbac_permissions_matrix_' + new Date().toISOString().slice(0,10) + '.csv');
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
    </script>
</body>
</html>
