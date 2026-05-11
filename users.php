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
    <link rel="stylesheet" href="docs/lucide-font/lucide.css">
    <link rel="stylesheet" href="layout.css?v=1.0.1">
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="mainSidebar">
            <div class="sidebar-header">
                <div class="logo-container">
                    <div class="logo-icon"><i class="icon-bar-chart-2"></i></div>
                    <span>SYNC | ANALYTICS</span>
                </div>
            </div>
            <nav class="sidebar-nav">
                <a href="index.php" class="nav-item">
                    <i class="icon-layout-dashboard"></i>
                    <span>Dashboard</span>
                </a>
                <a href="reports.php" class="nav-item">
                    <i class="icon-bar-chart-2"></i>
                    <span>Reporting</span>
                </a>
                <a href="settings.php#general" class="nav-item">
                    <i class="icon-settings"></i>
                    <span>Settings</span>
                </a>
                <a href="settings.php#security" class="nav-item">
                    <i class="icon-shield"></i>
                    <span>Security</span>
                </a>
                <a href="settings.php#team" class="nav-item active">
                    <i class="icon-users"></i>
                    <span>Team</span>
                </a>
                <a href="settings.php#rationalize" class="nav-item">
                    <i class="icon-git-branch"></i>
                    <span>Product Mapping</span>
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

        <!-- Main Wrapper -->
        <main class="main-wrapper">
            <header class="top-header">
                <div class="header-left">
                    <button class="collapse-btn" onclick="toggleSidebar()">
                        <i class="icon-menu"></i>
                    </button>
                    <div class="search-container">
                        <i class="icon-search"></i>
                        <input type="text" class="search-input" placeholder="Search users...">
                    </div>
                </div>
                <div class="header-right">
                    <button class="icon-btn">
                        <i class="icon-bell"></i>
                        <div class="notification-dot"></div>
                    </button>
                    <div class="user-dropdown">
                        <div class="user-trigger" onclick="toggleUserDropdown()">
                            <div class="user-profile" style="background: var(--sidebar-bg); border: 2px solid var(--border-color);">
                                <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                            </div>
                            <div class="user-info-brief">
                                <span class="user-name"><?php echo htmlspecialchars($user['username']); ?></span>
                                <span class="user-role"><?php echo ucfirst($user['role']); ?></span>
                            </div>
                            <i class="icon-chevron-down" style="font-size: 12px; color: var(--text-muted); margin-left: 4px;"></i>
                        </div>
                        <div class="dropdown-menu" id="userDropdown">
                            <div class="dropdown-header">
                                <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                <span><?php echo ucfirst($user['role']); ?> Management Account</span>
                            </div>
                            <a href="settings.php#security" class="dropdown-item"><i class="icon-lock"></i> Change Password</a>
                            <?php if ($auth->isAdmin()): ?>
                            <a href="users.php" class="dropdown-item"><i class="icon-users"></i> Manage Users</a>
                            <?php endif; ?>
                            <div class="dropdown-divider"></div>
                            <form method="POST" action="logout.php" style="margin: 0;">
                                <button type="submit" class="dropdown-item logout-link"><i class="icon-log-out"></i> Logout</button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <div class="content-body">
                <div class="settings-nav" style="margin-bottom: 25px; border-radius: 12px; background: white; padding: 15px; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
                    <div class="settings-nav-links">
                        <a href="settings.php#general" class="tab-btn"><i class="icon-settings"></i> General</a>
                        <a href="settings.php#security" class="tab-btn"><i class="icon-lock"></i> Security</a>
                        <a href="users.php" class="tab-btn active"><i class="icon-users"></i> Manage Users</a>
                        <a href="settings.php#rationalize" class="tab-btn"><i class="icon-git-branch"></i> Product Mapping</a>
                    </div>
                </div>

                <div class="container" style="display: grid; grid-template-columns: 320px 1fr; gap: 30px; padding: 0; max-width: none; margin: 0;">
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
        function toggleSidebar() {
            document.getElementById('mainSidebar').classList.toggle('collapsed');
            localStorage.setItem('sidebarCollapsed', document.getElementById('mainSidebar').classList.contains('collapsed'));
        }

        // Restore sidebar state
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
            document.getElementById('mainSidebar').classList.add('collapsed');
        }

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
            </div><!-- .content-body -->
        </main><!-- .main-wrapper -->
    </div><!-- .app-container -->
</body>
</html>
