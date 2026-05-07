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
$allUsers = $auth->getAllUsers();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $result = $auth->registerUser(
            $_POST['username'] ?? '',
            $_POST['password'] ?? '',
            $_POST['email'] ?? '',
            $_POST['role'] ?? 'viewer'
        );
        $messageType = $result['success'] ? 'success' : 'error';
        $message = $result['message'];
        if ($result['success']) {
            $allUsers = $auth->getAllUsers();
        }
    } elseif ($action === 'delete') {
        $userId = $_POST['user_id'] ?? 0;
        if ($userId != $user['id']) {
            $result = $auth->deleteUser($userId);
            $messageType = $result['success'] ? 'success' : 'error';
            $message = $result['message'];
            if ($result['success']) {
                $allUsers = $auth->getAllUsers();
            }
        } else {
            $message = 'Cannot delete your own account';
            $messageType = 'error';
        }
    } elseif ($action === 'change_password') {
        $result = $auth->changePassword(
            $user['id'],
            $_POST['old_password'] ?? '',
            $_POST['new_password'] ?? ''
        );
        $messageType = $result['success'] ? 'success' : 'error';
        $message = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .container {
            display: flex;
            min-height: calc(100vh - 70px);
        }

        .sidebar {
            width: 250px;
            background: #2c3e50;
            color: white;
            padding: 20px;
        }

        .nav-item {
            padding: 12px 15px;
            margin: 5px 0;
            border-radius: 5px;
            text-decoration: none;
            color: #bbb;
            display: block;
        }

        .nav-item:hover, .nav-item.active {
            background: #667eea;
            color: white;
        }

        .main-content {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
        }

        .card {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .card h2 {
            margin-bottom: 20px;
            color: #333;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: 600;
            font-size: 14px;
        }

        input[type="text"],
        input[type="password"],
        input[type="email"],
        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        input:focus, select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 5px rgba(102, 126, 234, 0.3);
        }

        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #764ba2;
        }

        .btn-danger {
            background: #f44336;
            color: white;
        }

        .btn-danger:hover {
            background: #da190b;
        }

        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }

        .message.success {
            background: #e8f5e9;
            color: #2e7d32;
            border-color: #4caf50;
        }

        .message.error {
            background: #ffebee;
            color: #c33;
            border-color: #f44336;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #e9ecef;
        }

        .table td {
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
        }

        .table tr:hover {
            background: #f8f9fa;
        }

        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-admin {
            background: #e3f2fd;
            color: #667eea;
        }

        .badge-viewer {
            background: #f3e5f5;
            color: #7b1fa2;
        }

        .text-center {
            text-align: center;
        }

        .two-column {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        @media (max-width: 768px) {
            .two-column {
                grid-template-columns: 1fr;
            }
            .container {
                flex-direction: column;
            }
            .sidebar {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>👥 Manage Users</h1>
        <div style="display: flex; gap: 15px; align-items: center;">
            <span><?php echo htmlspecialchars($user['username']); ?></span>
            <div style="background: rgba(255,255,255,0.2); padding: 8px 15px; border-radius: 20px; font-size: 12px;">ADMIN</div>
            <form method="POST" action="logout.php" style="margin: 0;">
                <button type="submit" style="background: rgba(255,255,255,0.3); color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer;">Logout</button>
            </form>
        </div>
    </div>

    <div class="container">
        <div class="sidebar">
            <h3 style="margin-bottom: 20px; font-size: 14px;">Navigation</h3>
            <a href="index.php" class="nav-item">📊 Dashboard</a>
            <a href="upload.php" class="nav-item">📤 Upload Data</a>
            <a href="users.php" class="nav-item active">👥 Manage Users</a>
        </div>

        <div class="main-content">
            <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>

            <div class="two-column">
                <div class="card">
                    <h2>Create New User</h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="create">

                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" required>
                        </div>

                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email">
                        </div>

                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" required>
                        </div>

                        <div class="form-group">
                            <label for="role">Role</label>
                            <select id="role" name="role" required>
                                <option value="viewer">Viewer (Read-only access)</option>
                                <option value="admin">Admin (Full access)</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary">Create User</button>
                    </form>
                </div>

                <div class="card">
                    <h2>Change My Password</h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">

                        <div class="form-group">
                            <label for="old_password">Current Password</label>
                            <input type="password" id="old_password" name="old_password" required>
                        </div>

                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" required>
                        </div>

                        <button type="submit" class="btn btn-primary">Change Password</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <h2>All Users</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Created</th>
                            <th>Last Login</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($allUsers as $u): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($u['username']); ?></td>
                            <td><?php echo htmlspecialchars($u['email'] ?? '-'); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $u['role']; ?>">
                                    <?php echo strtoupper($u['role']); ?>
                                </span>
                            </td>
                            <td><?php echo date('Y-m-d', strtotime($u['created_at'])); ?></td>
                            <td><?php echo $u['last_login'] ? date('Y-m-d H:i', strtotime($u['last_login'])) : '-'; ?></td>
                            <td>
                                <?php if ($u['id'] != $user['id']): ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this user?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                    <button type="submit" class="btn btn-danger" style="padding: 5px 10px; font-size: 12px;">Delete</button>
                                </form>
                                <?php else: ?>
                                <span style="color: #999;">(You)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
