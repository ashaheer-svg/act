<?php
require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/Auth.php';

$db = new Database(DATABASE_PATH);
$auth = new Auth($db);
$auth->requireAdmin();

$user = $auth->getCurrentUser();
$message = '';
$messageType = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_user') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'viewer';

        if (empty($username) || empty($password)) {
            $message = 'Username and password are required';
            $messageType = 'error';
        } else {
            try {
                if ($auth->register($username, $password, $role)) {
                    $message = "User '$username' created successfully";
                    $messageType = 'success';
                } else {
                    $message = "Username '$username' already exists";
                    $messageType = 'error';
                }
            } catch (Exception $e) {
                $message = 'Error: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }

    if ($action === 'delete_user') {
        $userId = $_POST['user_id'] ?? 0;
        if ($userId == $user['id']) {
            $message = 'You cannot delete your own account';
            $messageType = 'error';
        } else {
            $db->execute("DELETE FROM users WHERE id = ?", [$userId]);
            $message = 'User deleted successfully';
            $messageType = 'success';
        }
    }
}

$users = $db->fetchAll("SELECT id, username, role, created_at FROM users ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - Activity</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --secondary: #fb923c;
            --bg: #f1f5f9;
            --sidebar-bg: #ffffff;
            --card-bg: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --error: #ef4444;
            --success: #10b981;
            --radius-lg: 20px;
            --radius-md: 12px;
            --shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--bg);
            color: var(--text-main);
            line-height: 1.5;
        }

        /* --- Header --- */
        .header {
            background: white;
            padding: 0 40px;
            height: 80px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 800;
            font-size: 22px;
            letter-spacing: -0.5px;
        }

        .logo-icon {
            width: 32px;
            height: 32px;
            background: var(--primary);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
        }

        .top-nav {
            display: flex;
            gap: 30px;
        }

        .top-nav-item {
            text-decoration: none;
            color: var(--text-muted);
            font-size: 14px;
            font-weight: 500;
            padding-bottom: 5px;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
        }

        .top-nav-item:hover, .top-nav-item.active {
            color: var(--text-main);
            border-bottom-color: var(--primary);
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-profile {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: white;
            box-shadow: 0 4px 10px rgba(99, 102, 241, 0.2);
            transition: all 0.2s;
        }

        /* --- User Dropdown --- */
        .user-dropdown {
            position: relative;
            cursor: pointer;
        }
        .user-trigger {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 6px;
            padding-right: 12px;
            border-radius: 12px;
            transition: all 0.2s;
        }
        .user-trigger:hover { background: #f8fafc; }
        .user-info-brief {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
        }
        .user-name { font-size: 13px; font-weight: 700; color: var(--text-main); }
        .user-role { font-size: 10px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        
        .dropdown-menu {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            width: 220px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);
            border: 1px solid var(--border);
            padding: 8px;
            display: none;
            z-index: 1000;
            transform-origin: top right;
            animation: dropdownFade 0.2s ease;
        }
        .dropdown-menu.active { display: block; }
        
        @keyframes dropdownFade {
            from { opacity: 0; transform: translateY(-10px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .dropdown-header {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 8px;
        }
        .dropdown-header strong { display: block; font-size: 14px; color: var(--text-main); }
        .dropdown-header span { font-size: 11px; color: var(--text-muted); }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 16px;
            text-decoration: none;
            color: var(--text-main);
            font-size: 13px;
            font-weight: 500;
            border-radius: 10px;
            transition: all 0.2s;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            cursor: pointer;
        }
        .dropdown-item:hover { background: #f8fafc; color: var(--primary); }
        .dropdown-item.logout-link:hover { background: #fef2f2; color: var(--error); }
        .dropdown-divider { height: 1px; background: var(--border); margin: 8px 0; }

        /* --- Layout --- */
        .container {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 30px;
            padding: 30px 40px;
            max-width: 1600px;
            margin: 0 auto;
        }

        /* --- Sidebar --- */
        .sidebar {
            background: white;
            border-radius: var(--radius-lg);
            padding: 30px;
            box-shadow: var(--shadow);
            height: fit-content;
            position: sticky;
            top: 110px;
        }

        .sidebar h3 { font-size: 18px; font-weight: 700; margin-bottom: 25px; }

        /* --- Main Content --- */
        .main-content {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 30px;
            box-shadow: var(--shadow);
        }

        .card h2 { font-size: 24px; font-weight: 800; margin-bottom: 25px; letter-spacing: -0.5px; }

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 14px; font-weight: 600; margin-bottom: 8px; color: var(--text-main); }
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border-radius: var(--radius-md);
            border: 1px solid var(--border);
            font-size: 14px;
            font-family: inherit;
        }
        .form-control:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1); }

        .btn {
            padding: 12px 24px;
            border-radius: var(--radius-md);
            border: none;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
        }
        .btn-primary { background: var(--primary); color: white; width: 100%; }
        .btn-primary:hover { background: var(--primary-hover); }
        .btn-danger-link { background: none; color: var(--error); padding: 0; font-size: 13px; font-weight: 600; text-decoration: underline; }

        .message {
            padding: 20px;
            border-radius: 16px;
            margin-bottom: 25px;
            font-weight: 500;
        }
        .message.success { background: #ecfdf5; color: #065f46; border: 1px solid #d1fae5; }
        .message.error { background: #fef2f2; color: #991b1b; border: 1px solid #fee2e2; }

        .role-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .role-admin { background: #e0e7ff; color: var(--primary); }
        .role-accounts { background: #dcfce7; color: #166534; }
        .role-viewer { background: #f1f5f9; color: var(--text-muted); }

        .table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
        }
        .table th { text-align: left; font-size: 11px; text-transform: uppercase; color: var(--text-muted); padding: 0 15px 5px 15px; }
        .table td { background: #f8fafc; padding: 18px 15px; font-size: 14px; }
        .table tr td:first-child { border-top-left-radius: 10px; border-bottom-left-radius: 10px; font-weight: 600; }
        .table tr td:last-child { border-top-right-radius: 10px; border-bottom-right-radius: 10px; }

        /* --- Settings Nav (Tabs) --- */
        .settings-nav {
            background: white;
            border-radius: var(--radius-lg);
            padding: 15px 25px;
            box-shadow: var(--shadow);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }
        .settings-nav-links { display: flex; gap: 10px; align-items: center; }
        .tab-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            border-radius: 10px;
            border: none;
            background: none;
            color: var(--text-muted);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }
        .tab-btn:hover { background: #f8fafc; color: var(--text-main); }
        .tab-btn.active { background: #eef2ff; color: var(--primary); font-weight: 700; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo-container">
            <div class="logo-icon">A</div>
            Activity
        </div>
        
        <div class="top-nav">
            <a href="index.php" class="top-nav-item">Dashboard</a>
            <a href="reports.php" class="top-nav-item">Reporting</a>
            <a href="settings.php" class="top-nav-item active">Settings</a>
        </div>

        <div class="header-actions">
            <div class="user-dropdown">
                <div class="user-trigger" onclick="toggleUserDropdown()">
                    <div class="user-profile">
                        <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                    </div>
                    <div class="user-info-brief">
                        <span class="user-name"><?php echo htmlspecialchars($user['username']); ?></span>
                        <span class="user-role"><?php echo ucfirst($user['role']); ?></span>
                    </div>
                    <div style="font-size: 10px; color: var(--text-muted); margin-left: 4px;">▼</div>
                </div>
                
                <div class="dropdown-menu" id="userDropdown">
                    <div class="dropdown-header">
                        <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                        <span><?php echo ucfirst($user['role']); ?> Management Account</span>
                    </div>
                    <a href="settings.php#security" class="dropdown-item">🔒 Change Password</a>
                    <?php if ($auth->isAdmin()): ?>
                    <a href="users.php" class="dropdown-item">👥 Manage Users</a>
                    <?php endif; ?>
                    <div class="dropdown-divider"></div>
                    <form method="POST" action="logout.php" style="margin: 0;">
                        <button type="submit" class="dropdown-item logout-link">🚪 Logout</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="container" style="display: block; max-width: 1600px; padding-bottom: 0;">
        <div class="settings-nav">
            <div class="settings-nav-links">
                <a href="settings.php#general" class="tab-btn">⚙️ General</a>
                <a href="settings.php#team" class="tab-btn">👥 Sales Team</a>
                <a href="settings.php#rationalize" class="tab-btn">🏷️ Product Mapping</a>
                <?php if ($auth->isAdmin()): ?>
                <a href="settings.php#tax" class="tab-btn">🏦 Tax & History</a>
                <?php endif; ?>
                <div style="width: 1px; height: 24px; background: var(--border); margin: 0 10px;"></div>
                <a href="profit_entry.php" class="tab-btn">💰 Profit Entry</a>
                <a href="customers.php" class="tab-btn">🏢 Customers</a>
                <a href="upload.php" class="tab-btn">📁 Data Upload</a>
                <?php if ($auth->isAdmin()): ?>
                <a href="users.php" class="tab-btn active">👤 User Mgmt</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="container" style="padding-top: 0;">
        <div class="sidebar">
            <h3>Add New User</h3>
            <form method="POST" style="margin-top: 20px;">
                <input type="hidden" name="action" value="create_user">
                
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>Role</label>
                    <select name="role" class="form-control">
                        <option value="viewer">Viewer (Read-only)</option>
                        <option value="accounts">Accounts (Finance & CRM)</option>
                        <option value="admin">Administrator (Full Access)</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">Create Account</button>
            </form>
        </div>

        <div class="main-content">
            <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>

            <div class="card">
                <h2>System Users</h2>
                <table class="table">
                    <thead>
                        <tr><th>Username</th><th>Role</th><th>Created At</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($users as $u): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($u['username']); ?></td>
                            <td>
                                <span class="role-badge role-<?php echo $u['role']; ?>">
                                    <?php echo strtoupper($u['role']); ?>
                                </span>
                            </td>
                            <td style="color: var(--text-muted); font-size: 12px;">
                                <?php echo date('Y-m-d', strtotime($u['created_at'])); ?>
                            </td>
                            <td>
                                <?php if ($u['id'] != $user['id']): ?>
                                <form method="POST" onsubmit="return confirm('Delete this user account?');">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                    <button type="submit" class="btn-danger-link">Remove</button>
                                </form>
                                <?php else: ?>
                                <span style="font-size: 12px; color: var(--text-muted); italic;">(You)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script>
        function toggleUserDropdown() {
            document.getElementById('userDropdown').classList.toggle('active');
        }

        window.onclick = function(event) {
            if (!event.target.closest('.user-dropdown')) {
                const dropdowns = document.getElementsByClassName("dropdown-menu");
                for (let i = 0; i < dropdowns.length; i++) {
                    dropdowns[i].classList.remove('active');
                }
            }
        }
    </script>
</body>
</html>
