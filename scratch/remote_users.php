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
    <link rel="stylesheet" href="layout.css?v=1.0.2">
</head>
<body>
    <div class="app-container">
        <?php require_once 'includes/sidebar.php'; ?>

        <!-- Main Wrapper -->
        <main class="main-wrapper">
            <?php $searchPlaceholder = 'Search users...'; require_once 'includes/header.php'; ?>

            <div class="content-body">

                <div class="container" style="display: grid; grid-template-columns: 320px 1fr; gap: 30px; padding: 0; max-width: none; margin: 0;">
        <div class="card" style="height: fit-content;">
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
            </div><!-- .content-body -->
        </main><!-- .main-wrapper -->
    </div><!-- .app-container -->

    <?php require_once 'includes/layout_js.php'; ?>
</body>
</html>
