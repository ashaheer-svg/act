<?php
/**
 * Settings Module: Access, Team & Security
 * Active Solutions BI Platform
 */

if (!defined('DATABASE_PATH') && !isset($db)) {
    exit('Direct access not permitted.');
}

// Handle POST actions for Team & Security
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($new !== $confirm) {
            $message = 'New passwords do not match';
            $messageType = 'error';
        } else if (strlen($new) < 6) {
            $message = 'Password must be at least 6 characters';
            $messageType = 'error';
        } else {
            $result = $auth->changePassword($user['id'], $current, $new);
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'error';
        }
    }

    if ($action === 'create_user') {
        $auth->requireAdmin();
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'viewer';
        $preset = $_POST['permission_preset'] ?? 'role_default';

        if (empty($username) || empty($password)) {
            $message = 'Username and password are required';
            $messageType = 'error';
        } else {
            try {
                if ($auth->register($username, $password, $role)) {
                    $newUser = $db->fetch("SELECT id FROM users WHERE username = ?", [$username]);
                    if ($newUser && $role !== 'admin' && !empty($preset) && $preset !== 'role_default') {
                        $auth->applyUserPreset($newUser['id'], $preset);
                    }
                    $message = "User '$username' created successfully with " . ($preset === 'role_default' ? 'standard role defaults' : "'" . ucfirst($preset) . "' preset") . ".";
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
        $auth->requireAdmin();
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId == $user['id']) {
            $message = 'You cannot delete your own account';
            $messageType = 'error';
        } else {
            $db->execute("DELETE FROM users WHERE id = ?", [$userId]);
            $db->execute("DELETE FROM user_report_permissions WHERE user_id = ?", [$userId]);
            $message = 'User deleted successfully';
            $messageType = 'success';
            $db->logActivity($user['id'], 'USER_DELETED', "Deleted user ID: $userId");
        }
    }
}

// Fetch system users
$systemUsers = $db->fetchAll("SELECT id, username, role, created_at FROM users ORDER BY created_at DESC");
?>

<!-- Account Security -->
<div class="card">
    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 25px;">
        <div style="width: 40px; height: 40px; background: #fee2e2; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #ef4444; font-size: 20px;">🔒</div>
        <div>
            <h2 style="margin: 0;">Account Security</h2>
            <p style="color: var(--text-muted); font-size: 13px; margin: 0;">Update your personal password and credentials.</p>
        </div>
    </div>

    <form method="POST">
        <input type="hidden" name="action" value="change_password">
        <div class="form-group" style="max-width: 400px;">
            <label>Current Password</label>
            <input type="password" name="current_password" class="form-control" required>
        </div>
        <div class="form-group" style="max-width: 400px;">
            <label>New Password</label>
            <input type="password" name="new_password" class="form-control" required placeholder="Min 6 characters">
        </div>
        <div class="form-group" style="max-width: 400px;">
            <label>Confirm New Password</label>
            <input type="password" name="confirm_password" class="form-control" required>
        </div>
        <div style="margin-top: 30px;">
            <button type="submit" class="btn btn-primary">Update Password</button>
        </div>
    </form>
</div>

<?php if ($auth->isAdmin()): ?>
<div style="display: grid; grid-template-columns: 320px 1fr; gap: 30px; margin-top: 30px;">
    <!-- Add New User Card -->
    <div class="card" style="height: fit-content;">
        <h3>Add New User</h3>
        <form method="POST" style="margin-top: 20px;">
            <input type="hidden" name="action" value="create_user">
            
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" class="form-control" required autocomplete="off">
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" class="form-control" required autocomplete="new-password">
            </div>

            <div class="form-group">
                <label>Role</label>
                <select name="role" class="form-control">
                    <option value="viewer">Viewer (Read-only)</option>
                    <option value="accounts">Accounts (Finance & Operations)</option>
                    <option value="admin">Administrator (Full Access)</option>
                </select>
            </div>

            <div class="form-group">
                <label>Initial Permission Blueprint</label>
                <select name="permission_preset" class="form-control">
                    <option value="role_default">Standard Role Default</option>
                    <option value="sales">Sales Representative Blueprint</option>
                    <option value="finance">Finance &amp; Accounts Blueprint</option>
                    <option value="executive">Executive Management Blueprint</option>
                    <option value="all">Full Module Access (All 33 Modules)</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">Create Account</button>
        </form>
    </div>

    <!-- System Users Table -->
    <div class="card">
        <h2>System Users</h2>
        <table class="table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="text-align: left; padding: 10px;">Username</th>
                    <th style="text-align: left; padding: 10px;">Role</th>
                    <th style="text-align: left; padding: 10px;">Created At</th>
                    <th style="text-align: right; padding: 10px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($systemUsers as $u): ?>
                <tr>
                    <td style="padding: 10px; border-bottom: 1px solid var(--border-color); font-weight: 600;"><?php echo htmlspecialchars($u['username']); ?></td>
                    <td style="padding: 10px; border-bottom: 1px solid var(--border-color);">
                        <span style="font-size: 11px; font-weight: 700; color: #7c3aed; background: #f5f3ff; padding: 4px 10px; border-radius: 20px; text-transform: uppercase;">
                            <?php echo strtoupper($u['role']); ?>
                        </span>
                    </td>
                    <td style="padding: 10px; border-bottom: 1px solid var(--border-color); color: var(--text-muted); font-size: 12px;">
                        <?php echo date('Y-m-d', strtotime($u['created_at'])); ?>
                    </td>
                    <td style="padding: 10px; border-bottom: 1px solid var(--border-color); text-align: right;">
                        <?php if ($u['id'] != $user['id']): ?>
                        <form method="POST" onsubmit="return confirm('Delete this user account?');" style="display: inline;">
                            <input type="hidden" name="action" value="delete_user">
                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                            <button type="submit" class="btn-danger-link" style="background: none; border: none; color: var(--danger); cursor: pointer; font-weight: 600;">Remove</button>
                        </form>
                        <?php else: ?>
                        <span style="font-size: 12px; color: var(--text-muted); font-style: italic;">(You)</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Granular Report Permissions (RBAC) Link Card -->
<div class="card" style="margin-top: 30px; border-left: 4px solid #7c3aed; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; padding: 22px 26px;">
    <div>
        <h3 style="margin: 0 0 6px 0; display: flex; align-items: center; gap: 10px; font-size: 16px;">
            <span>🛡️ Granular Report Permissions (RBAC)</span>
            <span style="font-size: 11px; font-weight: 700; color: #6d28d9; background: #ede9fe; padding: 3px 8px; border-radius: 20px; text-transform: uppercase;">Dedicated Page</span>
        </h3>
        <p style="color: var(--text-muted); font-size: 13.5px; margin: 0; max-width: 650px; line-height: 1.5;">
            Granular module authorization, category bulk toggles, permission cloning, and 1-click user presets are managed on the dedicated RBAC Permissions matrix.
        </p>
    </div>
    <a href="rbac.php" class="btn btn-primary" style="white-space: nowrap; display: inline-flex; align-items: center; gap: 8px; font-weight: 600; text-decoration: none; padding: 10px 18px;">
        <i class="icon-shield"></i>
        <span>Open RBAC Matrix &rarr;</span>
    </a>
</div>
<?php endif; ?>
