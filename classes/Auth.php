<?php
// --- UNIVERSAL REPAIR TOOL ---
if (isset($_GET['repair_db']) && $_GET['repair_db'] == '1') {
    require_once 'config.php';
    try {
        $pdo = new PDO('sqlite:' . DATABASE_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $needed = [
            "ALTER TABLE sales ADD COLUMN gross_profit DECIMAL(12,2) DEFAULT 0",
            "ALTER TABLE sales ADD COLUMN applied_tax_rate DECIMAL(5,4)",
            "ALTER TABLE sales ADD COLUMN product_category TEXT",
            "CREATE TABLE IF NOT EXISTS tax_rules (id INTEGER PRIMARY KEY AUTOINCREMENT, tax_name TEXT DEFAULT 'VAT', tax_rate REAL NOT NULL, effective_from DATE NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
            "CREATE TABLE IF NOT EXISTS customer_profiles (customer_name TEXT PRIMARY KEY, customer_type TEXT DEFAULT 'End Customer', updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)"
        ];
        foreach ($needed as $sql) { try { $pdo->exec($sql); } catch (Exception $e) {} }
        echo "REPAIR_COMPLETE";
        exit;
    } catch (Exception $e) { die("Repair failed: " . $e->getMessage()); }
}
/**
 * Auth Class - User Authentication & RBAC (ENHANCED)
 *
 * Handles login, logout, session management, role checks, and password reset
 * SECURITY: Bcrypt hashing, prepared statements, session timeout
 */

class Auth {
    private $db;
    private $sessionTimeout;

    public function __construct(Database $db) {
        $this->db = $db;
        $this->sessionTimeout = SESSION_TIMEOUT;
        $this->startSession();
    }

    /**
     * Start and validate session
     */
    private function startSession() {
        session_name(SESSION_NAME);
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->validateSession();
    }

    /**
     * Validate session timeout
     */
    private function validateSession() {
        if (isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > $this->sessionTimeout) {
                $this->logout();
                return false;
            }
        }
        $_SESSION['last_activity'] = time();
        return true;
    }

    /**
     * Login user
     */
    public function login($username, $password) {
        try {
            $user = $this->db->fetch(
                "SELECT id, username, password, role FROM users WHERE username = ?",
                [$username]
            );

            if (!$user) {
                return ['success' => false, 'message' => 'User not found'];
            }

            if (!password_verify($password, $user['password'])) {
                return ['success' => false, 'message' => 'Invalid password'];
            }

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();

            // Update last login
            $this->db->execute(
                "UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?",
                [$user['id']]
            );

            return ['success' => true, 'message' => 'Login successful'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Login error: ' . $e->getMessage()];
        }
    }

    /**
     * Logout user
     */
    public function logout() {
        $_SESSION = [];
        session_destroy();
        return true;
    }

    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        return isset($_SESSION['user_id']) && isset($_SESSION['username']);
    }

    /**
     * Get current user info
     */
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'role' => $_SESSION['role']
        ];
    }

    /**
     * Check if user has specific role
     */
    public function hasRole($role) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        if (is_array($role)) {
            return in_array($_SESSION['role'], $role);
        }
        return $_SESSION['role'] === $role;
    }

    /**
     * Check if user is admin
     */
    public function isAdmin() {
        return $this->hasRole('admin');
    }

    /**
     * Check if user is accounts
     */
    public function isAccounts() {
        return $this->hasRole('accounts');
    }

    /**
     * Check if user is viewer
     */
    public function isViewer() {
        return $this->hasRole('viewer');
    }

    /**
     * Require login
     */
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: login.php');
            exit();
        }
    }

    /**
     * Require admin role
     */
    public function requireAdmin() {
        $this->requireLogin();
        if (!$this->isAdmin()) {
            header('HTTP/1.0 403 Forbidden');
            die('Access Denied: Admin role required');
        }
    }

    /**
     * Require Accounts or Admin role
     */
    public function requireAccounts() {
        $this->requireLogin();
        if (!$this->isAdmin() && !$this->isAccounts()) {
            header('HTTP/1.0 403 Forbidden');
            die('Access Denied: Accounts or Admin role required');
        }
    }

    /**
     * Register new user (Admin only)
     */
    public function registerUser($username, $password, $email, $role = 'viewer') {
        try {
            if (!in_array($role, ['admin', 'accounts', 'viewer'])) {
                return ['success' => false, 'message' => 'Invalid role'];
            }

            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $this->db->execute(
                "INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, ?)",
                [$username, $hashedPassword, $email, $role]
            );

            return ['success' => true, 'message' => 'User created successfully'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * Get all users (Admin only)
     */
    public function getAllUsers() {
        return $this->db->fetchAll(
            "SELECT id, username, email, role, created_at, last_login FROM users ORDER BY created_at DESC"
        );
    }

    /**
     * Delete user (Admin only)
     */
    public function deleteUser($userId) {
        try {
            $this->db->execute("DELETE FROM users WHERE id = ?", [$userId]);
            return ['success' => true, 'message' => 'User deleted'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * Change user password
     */
    public function changePassword($userId, $oldPassword, $newPassword) {
        try {
            $user = $this->db->fetch("SELECT password FROM users WHERE id = ?", [$userId]);

            if (!password_verify($oldPassword, $user['password'])) {
                return ['success' => false, 'message' => 'Current password is incorrect'];
            }

            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            $this->db->execute("UPDATE users SET password = ? WHERE id = ?", [$hashedPassword, $userId]);

            return ['success' => true, 'message' => 'Password changed successfully'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
}
?>
