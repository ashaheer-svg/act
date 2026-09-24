<?php
/**
 * Auth Class - User Authentication & RBAC (ENHANCED)
 *
 * Handles login, logout, session management, role checks, and password reset
 * SECURITY: Bcrypt hashing, prepared statements, session timeout
 */

class Auth {
    private $db;
    private $sessionTimeout;
    private $userPermsCache = [];

    /**
     * Invalidate internal permission cache for a specific user or all users
     */
    public function invalidatePermissionsCache($userId = null) {
        if ($userId === null) {
            $this->userPermsCache = [];
        } else {
            unset($this->userPermsCache[$userId]);
        }
    }

    public function __construct(Database $db) {
        $this->db = $db;
        $this->sessionTimeout = SESSION_TIMEOUT;
        $this->startSession();
    }

    /**
     * Start and validate session
     */
    private function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
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
            if (isset($_GET['ajax_invoice_details']) || isset($_GET['ajax_customer_history']) || isset($_GET['ajax_customer_payment_profile']) || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
                while (ob_get_level()) { ob_end_clean(); }
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Your session has expired. Please refresh the page and log in again.']);
                exit();
            }
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
    public function register($username, $password, $role = 'viewer', $email = '') {
        try {
            // Check if user already exists
            $existing = $this->db->fetch("SELECT id FROM users WHERE username = ?", [$username]);
            if ($existing) {
                return false;
            }

            if (!in_array($role, ['admin', 'accounts', 'viewer'])) {
                throw new Exception('Invalid role specified');
            }

            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $this->db->execute(
                "INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, ?)",
                [$username, $hashedPassword, $email, $role]
            );

            return true;
        } catch (Exception $e) {
            throw $e;
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

            return ['success' => true, 'message' => 'Password updated successfully'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * Initialize report permissions table
     */
    public function initReportPermissionsSchema() {
        try {
            $this->db->execute("
                CREATE TABLE IF NOT EXISTS report_permissions (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    report_key TEXT NOT NULL,
                    is_allowed INTEGER NOT NULL DEFAULT 1,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    UNIQUE(user_id, report_key)
                )
            ");
            $this->db->execute("CREATE INDEX IF NOT EXISTS idx_rep_perm_user ON report_permissions(user_id)");
        } catch (Exception $e) {
            // Ignore if exists
        }
    }

    /**
     * Canonical 32-Report Catalog with Metadata
     */
    public static function getReportDefinitions() {
        return [
            // Core Reports
            'invoices' => [
                'key' => 'invoices',
                'name' => 'Commercial Invoices',
                'category' => 'core',
                'category_label' => 'Core Reports',
                'desc' => 'Primary line-item sales invoice ledger with base and tax totals',
                'icon' => 'icon-file-text',
                'url' => 'reports.php?type=invoices',
                'default_roles' => ['admin', 'accounts', 'viewer']
            ],
            'unpaid_invoices' => [
                'key' => 'unpaid_invoices',
                'name' => 'Unpaid Invoices',
                'category' => 'core',
                'category_label' => 'Core Reports',
                'desc' => 'Outstanding customer receivables and overdue balances',
                'icon' => 'icon-alert-circle',
                'url' => 'reports.php?type=unpaid_invoices',
                'default_roles' => ['admin', 'accounts']
            ],
            'warranties' => [
                'key' => 'warranties',
                'name' => 'Warranty & Serials',
                'category' => 'core',
                'category_label' => 'Core Reports',
                'desc' => 'Serial number lookup, warranty status, and hardware history',
                'icon' => 'icon-shield',
                'url' => 'reports.php?type=warranties',
                'default_roles' => ['admin', 'accounts', 'viewer']
            ],
            'customers' => [
                'key' => 'customers',
                'name' => 'Customer Directory',
                'category' => 'core',
                'category_label' => 'Core Reports',
                'desc' => 'Customer accounts master directory with spend and credit metrics',
                'icon' => 'icon-building-2',
                'url' => 'customers.php',
                'default_roles' => ['admin', 'accounts', 'viewer']
            ],
            'customer_report' => [
                'key' => 'customer_report',
                'name' => 'Customer History Audit',
                'category' => 'core',
                'category_label' => 'Core Reports',
                'desc' => 'Deep-dive account ledger with invoice chronology and settlements',
                'icon' => 'icon-users',
                'url' => 'customer_report.php',
                'default_roles' => ['admin', 'accounts', 'viewer']
            ],
            'explorer' => [
                'key' => 'explorer',
                'name' => 'Data Explorer',
                'category' => 'core',
                'category_label' => 'Core Reports',
                'desc' => 'Interactive ad-hoc search and custom sales query filter',
                'icon' => 'icon-database',
                'url' => 'explorer.php',
                'default_roles' => ['admin', 'accounts']
            ],

            // Analytics & BI
            'monthly_overview' => [
                'key' => 'monthly_overview',
                'name' => 'Monthly Sales Matrix',
                'category' => 'analytics',
                'category_label' => 'Analytics & BI',
                'desc' => '12-month calendar and fiscal year rolling revenue grid',
                'icon' => 'icon-calendar',
                'url' => 'reports.php?type=monthly&view=overview',
                'default_roles' => ['admin', 'accounts', 'viewer']
            ],
            'monthly_customer' => [
                'key' => 'monthly_customer',
                'name' => 'Customer Monthly Matrix',
                'category' => 'analytics',
                'category_label' => 'Analytics & BI',
                'desc' => 'Month-by-month spend matrix for every customer account',
                'icon' => 'icon-users',
                'url' => 'reports.php?type=monthly&view=customer',
                'default_roles' => ['admin', 'accounts', 'viewer']
            ],
            'monthly_rep' => [
                'key' => 'monthly_rep',
                'name' => 'Sales Rep Monthly Matrix',
                'category' => 'analytics',
                'category_label' => 'Analytics & BI',
                'desc' => 'Sales representative monthly revenue attribution matrix',
                'icon' => 'icon-award',
                'url' => 'reports.php?type=monthly&view=rep',
                'default_roles' => ['admin', 'accounts', 'viewer']
            ],
            'ltv' => [
                'key' => 'ltv',
                'name' => 'Customer LTV',
                'category' => 'analytics',
                'category_label' => 'Analytics & BI',
                'desc' => 'Customer lifetime value and account relationship ranking',
                'icon' => 'icon-award',
                'url' => 'reports.php?type=ltv',
                'default_roles' => ['admin', 'accounts', 'viewer']
            ],
            'churn' => [
                'key' => 'churn',
                'name' => 'Account Churn Risk',
                'category' => 'analytics',
                'category_label' => 'Analytics & BI',
                'desc' => 'Dormant account detector and customer churn warning engine',
                'icon' => 'icon-user-x',
                'url' => 'reports.php?type=churn',
                'default_roles' => ['admin', 'accounts', 'viewer']
            ],
            'eol' => [
                'key' => 'eol',
                'name' => 'Hardware EOL',
                'category' => 'analytics',
                'category_label' => 'Analytics & BI',
                'desc' => 'Hardware age, expired warranties, and tech refresh pipeline',
                'icon' => 'icon-cpu',
                'url' => 'reports.php?type=eol',
                'default_roles' => ['admin', 'accounts', 'viewer']
            ],
            'contracts' => [
                'key' => 'contracts',
                'name' => 'Expiring Contracts & MA',
                'category' => 'analytics',
                'category_label' => 'Analytics & BI',
                'desc' => 'Software subscriptions, Maintenance Agreements, and renewals',
                'icon' => 'icon-shield-check',
                'url' => 'reports.php?type=contracts',
                'default_roles' => ['admin', 'accounts', 'viewer']
            ],
            'rental_roi' => [
                'key' => 'rental_roi',
                'name' => 'Rental Fleet ROI',
                'category' => 'analytics',
                'category_label' => 'Analytics & BI',
                'desc' => 'Rental fleet asset utilization, billing, and capital returns',
                'icon' => 'icon-repeat',
                'url' => 'reports.php?type=rental_roi',
                'default_roles' => ['admin', 'accounts']
            ],
            'brand_growth' => [
                'key' => 'brand_growth',
                'name' => 'Brand & Category Matrix',
                'category' => 'analytics',
                'category_label' => 'Analytics & BI',
                'desc' => 'Revenue trends across Synology, BDCOM, Acronis, and categories',
                'icon' => 'icon-trending-up',
                'url' => 'reports.php?type=brand_growth',
                'default_roles' => ['admin', 'accounts', 'viewer']
            ],
            'dso_trends' => [
                'key' => 'dso_trends',
                'name' => 'DSO & Working Capital',
                'category' => 'analytics',
                'category_label' => 'Analytics & BI',
                'desc' => 'Days Sales Outstanding, liquidity trends, and payment speeds',
                'icon' => 'icon-dollar-sign',
                'url' => 'reports.php?type=dso_trends',
                'default_roles' => ['admin', 'accounts']
            ],
            'tax_audit' => [
                'key' => 'tax_audit',
                'name' => 'Tax & IRD Audit (18%)',
                'category' => 'analytics',
                'category_label' => 'Analytics & BI',
                'desc' => 'Statutory VAT liability, taxable sales, and IRD return audit',
                'icon' => 'icon-percent',
                'url' => 'reports.php?type=tax_audit',
                'default_roles' => ['admin', 'accounts']
            ],
            'unlinked_payments' => [
                'key' => 'unlinked_payments',
                'name' => 'Unlinked Payments Audit',
                'category' => 'analytics',
                'category_label' => 'Analytics & BI',
                'desc' => 'Audit customer bank receipts and cheque credits unallocated to invoices',
                'icon' => 'icon-link-2',
                'url' => 'reports.php?type=unlinked_payments',
                'default_roles' => ['admin', 'accounts']
            ],

            // Operations & Tools
            'profit_entry' => [
                'key' => 'profit_entry',
                'name' => 'Profit Entry & Costs',
                'category' => 'operations',
                'category_label' => 'Operations & Tools',
                'desc' => 'Commercial unit costs, gross profit margins, and adjustments',
                'icon' => 'icon-dollar-sign',
                'url' => 'profit_entry.php',
                'default_roles' => ['admin', 'accounts']
            ],
            'upload' => [
                'key' => 'upload',
                'name' => 'Data Upload Center',
                'category' => 'operations',
                'category_label' => 'Operations & Tools',
                'desc' => 'Manual CSV & Excel upload for sales ledgers and bank records',
                'icon' => 'icon-folder-up',
                'url' => 'upload.php',
                'default_roles' => ['admin', 'accounts']
            ],
            'import_legacy_qb' => [
                'key' => 'import_legacy_qb',
                'name' => 'Legacy QB Import',
                'category' => 'operations',
                'category_label' => 'Operations & Tools',
                'desc' => 'Historical QuickBooks archive and company file importer',
                'icon' => 'icon-archive',
                'url' => 'import_legacy_qb.php',
                'default_roles' => ['admin']
            ],
            'product_mapping' => [
                'key' => 'product_mapping',
                'name' => 'Product & Rental Taxonomy',
                'category' => 'operations',
                'category_label' => 'Operations & Tools',
                'desc' => 'Commercial brand classification, model SKUs, and asset mapping',
                'icon' => 'icon-layers',
                'url' => 'product_mapping.php',
                'default_roles' => ['admin', 'accounts']
            ],
            'vat_review' => [
                'key' => 'vat_review',
                'name' => 'VAT Review & Switcher',
                'category' => 'operations',
                'category_label' => 'Operations & Tools',
                'desc' => 'Audit statutory tax eras and switch invoices between VAT modes',
                'icon' => 'icon-sliders',
                'url' => 'vat_review.php',
                'default_roles' => ['admin', 'accounts']
            ],
            'sync_app' => [
                'key' => 'sync_app',
                'name' => 'Download Sync App',
                'category' => 'operations',
                'category_label' => 'Operations & Tools',
                'desc' => 'Download Windows desktop client and inspect REST sync API',
                'icon' => 'icon-cloud-download',
                'url' => 'sync_app.php',
                'default_roles' => ['admin', 'accounts']
            ],
            'edit_invoices' => [
                'key' => 'edit_invoices',
                'name' => 'Invoice Line-Item Editor',
                'category' => 'operations',
                'category_label' => 'Operations & Tools',
                'desc' => 'Modify invoice line items, unit costs, pricing, serials, and statutory VAT mode',
                'icon' => 'icon-edit',
                'url' => 'invoice_edit.php',
                'default_roles' => ['admin', 'accounts']
            ],

            // Archived Reports
            'dashboard' => [
                'key' => 'dashboard',
                'name' => 'Executive Dashboard',
                'category' => 'archived',
                'category_label' => 'Archived Reports',
                'desc' => 'Archived executive overview metrics and quick charts',
                'icon' => 'icon-layout-dashboard',
                'url' => 'index.php',
                'default_roles' => ['admin', 'accounts', 'viewer']
            ],
            'yearly' => [
                'key' => 'yearly',
                'name' => 'Yearly Performance',
                'category' => 'archived',
                'category_label' => 'Archived Reports',
                'desc' => 'Annual multi-year financial comparison and volume trends',
                'icon' => 'icon-bar-chart-2',
                'url' => 'reports.php?type=yearly',
                'default_roles' => ['admin', 'accounts', 'viewer']
            ],
            'quarterly' => [
                'key' => 'quarterly',
                'name' => 'Quarterly Sales',
                'category' => 'archived',
                'category_label' => 'Archived Reports',
                'desc' => 'Quarter-by-quarter comparison across past financial years',
                'icon' => 'icon-bar-chart-3',
                'url' => 'reports.php?type=quarterly',
                'default_roles' => ['admin', 'accounts', 'viewer']
            ],
            'matrix' => [
                'key' => 'matrix',
                'name' => 'Customer Matrix Pivot',
                'category' => 'archived',
                'category_label' => 'Archived Reports',
                'desc' => 'Archived matrix pivot table for customer spend',
                'icon' => 'icon-grid',
                'url' => 'reports.php?type=matrix',
                'default_roles' => ['admin', 'accounts']
            ],
            'renewals' => [
                'key' => 'renewals',
                'name' => 'SaaS Renewals',
                'category' => 'archived',
                'category_label' => 'Archived Reports',
                'desc' => 'Archived subscription and support contract tracker',
                'icon' => 'icon-refresh-cw',
                'url' => 'reports.php?type=renewals',
                'default_roles' => ['admin', 'accounts']
            ],
            'aging' => [
                'key' => 'aging',
                'name' => 'Aging & Debt Collection',
                'category' => 'archived',
                'category_label' => 'Archived Reports',
                'desc' => 'Archived accounts receivable aging breakdown (30/60/90 days)',
                'icon' => 'icon-clock',
                'url' => 'reports.php?type=aging',
                'default_roles' => ['admin', 'accounts']
            ],
            'stock' => [
                'key' => 'stock',
                'name' => 'Stock Movement',
                'category' => 'archived',
                'category_label' => 'Archived Reports',
                'desc' => 'Archived hardware velocity and item turnover',
                'icon' => 'icon-package',
                'url' => 'reports.php?type=stock',
                'default_roles' => ['admin', 'accounts']
            ],
            'partners' => [
                'key' => 'partners',
                'name' => 'Partner Cohorts',
                'category' => 'archived',
                'category_label' => 'Archived Reports',
                'desc' => 'Archived reseller partner retention and performance groups',
                'icon' => 'icon-users',
                'url' => 'reports.php?type=partners',
                'default_roles' => ['admin', 'accounts']
            ],
            'credit' => [
                'key' => 'credit',
                'name' => 'Credit Health',
                'category' => 'archived',
                'category_label' => 'Archived Reports',
                'desc' => 'Archived customer credit risk scoring model',
                'icon' => 'icon-shield',
                'url' => 'reports.php?type=credit',
                'default_roles' => ['admin', 'accounts']
            ]
        ];
    }

    /**
     * Normalize incoming report key
     */
    public static function normalizeReportKey($key, $view = '') {
        $key = trim($key);
        if ($key === 'monthly') {
            if ($view === 'customer') return 'monthly_customer';
            if ($view === 'rep') return 'monthly_rep';
            return 'monthly_overview';
        }
        if ($key === 'expiring_contracts') return 'contracts';
        if ($key === 'index.php') return 'dashboard';
        return $key;
    }

    /**
     * Check if a user can run or access a specific report/module
     */
    public function canAccessReport($reportKey, $userId = null, $view = '') {
        $reportKey = self::normalizeReportKey($reportKey, $view);

        if ($userId === null) {
            if (!$this->isLoggedIn()) {
                return false;
            }
            $userId = (int)$_SESSION['user_id'];
            $role = $_SESSION['role'] ?? 'viewer';
        } else {
            $u = $this->db->fetch("SELECT role FROM users WHERE id = ?", [$userId]);
            if (!$u) return false;
            $role = $u['role'];
        }

        // Administrators always have full, irrevocable access
        if ($role === 'admin') {
            return true;
        }

        // Populate in-memory permission map for this user if not cached
        if (!isset($this->userPermsCache[$userId])) {
            $this->initReportPermissionsSchema();
            $rows = $this->db->fetchAll(
                "SELECT report_key, is_allowed FROM report_permissions WHERE user_id = ?",
                [$userId]
            );
            $cached = [];
            foreach ($rows as $r) {
                $cached[$r['report_key']] = ((int)$r['is_allowed']) === 1;
            }
            $this->userPermsCache[$userId] = $cached;
        }

        // Check explicit permission record from in-memory cache
        if (isset($this->userPermsCache[$userId][$reportKey])) {
            return $this->userPermsCache[$userId][$reportKey];
        }

        // Fallback to default role preset
        $catalog = self::getReportDefinitions();
        if (isset($catalog[$reportKey])) {
            return in_array($role, $catalog[$reportKey]['default_roles']);
        }

        return false;
    }

    /**
     * Require permission to run report, or terminate with 403
     */
    public function requireReportAccess($reportKey, $view = '') {
        $this->requireLogin();
        if (!$this->canAccessReport($reportKey, null, $view)) {
            $catalog = self::getReportDefinitions();
            $normKey = self::normalizeReportKey($reportKey, $view);
            $reportName = $catalog[$normKey]['name'] ?? ucfirst($reportKey);

            if (isset($_GET['ajax_invoice_details']) || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
                while (ob_get_level()) { ob_end_clean(); }
                header('Content-Type: application/json', true, 403);
                echo json_encode(['error' => "Access Restricted: You do not have permission to view $reportName."]);
                exit;
            }

            http_response_code(403);
            require_once __DIR__ . '/../includes/access_restricted_card.php';
            exit;
        }
    }

    /**
     * Get all report permissions for a given user
     */
    public function getUserReportPermissions($userId) {
        $this->initReportPermissionsSchema();
        $rows = $this->db->fetchAll(
            "SELECT report_key, is_allowed FROM report_permissions WHERE user_id = ?",
            [$userId]
        );

        $permMap = [];
        foreach ($rows as $r) {
            $permMap[$r['report_key']] = ((int)$r['is_allowed']) === 1;
        }

        $u = $this->db->fetch("SELECT role FROM users WHERE id = ?", [$userId]);
        $role = $u ? $u['role'] : 'viewer';
        $catalog = self::getReportDefinitions();

        $result = [];
        foreach ($catalog as $key => $def) {
            if ($role === 'admin') {
                $result[$key] = true;
            } elseif (isset($permMap[$key])) {
                $result[$key] = $permMap[$key];
            } else {
                $result[$key] = in_array($role, $def['default_roles']);
            }
        }
        return $result;
    }

    /**
     * Set a single report permission
     */
    public function setUserReportPermission($userId, $reportKey, $allowed) {
        $this->initReportPermissionsSchema();
        $isAllowed = $allowed ? 1 : 0;
        $res = (bool)$this->db->execute("
            INSERT INTO report_permissions (user_id, report_key, is_allowed, updated_at)
            VALUES (?, ?, ?, CURRENT_TIMESTAMP)
            ON CONFLICT(user_id, report_key) DO UPDATE SET is_allowed = excluded.is_allowed, updated_at = CURRENT_TIMESTAMP
        ", [$userId, $reportKey, $isAllowed]);
        $this->invalidatePermissionsCache($userId);
        return $res;
    }

    /**
     * Set multiple report permissions for a user in a single batch
     */
    public function setUserReportPermissionsBatch($userId, array $reportKeysAllowed) {
        $this->initReportPermissionsSchema();
        $catalog = self::getReportDefinitions();

        $this->db->execute("DELETE FROM report_permissions WHERE user_id = ?", [$userId]);
        $stmt = $this->db->getConnection()->prepare("
            INSERT INTO report_permissions (user_id, report_key, is_allowed) VALUES (?, ?, ?)
        ");
        $this->db->getConnection()->beginTransaction();
        try {
            foreach ($catalog as $k => $def) {
                $isAllowed = in_array($k, $reportKeysAllowed) ? 1 : 0;
                $stmt->execute([$userId, $k, $isAllowed]);
            }
            $this->db->getConnection()->commit();
            $this->invalidatePermissionsCache($userId);
            return true;
        } catch (Exception $e) {
            $this->db->getConnection()->rollBack();
            throw $e;
        }
    }

    /**
     * Apply a quick role-based permission preset to a user
     */
    public function applyUserPreset($userId, $preset) {
        if ($preset === 'role_default') {
            $this->initReportPermissionsSchema();
            $this->db->execute("DELETE FROM report_permissions WHERE user_id = ?", [$userId]);
            $this->invalidatePermissionsCache($userId);
            return true;
        }

        $catalog = self::getReportDefinitions();
        $allowedKeys = [];

        switch ($preset) {
            case 'all':
                $allowedKeys = array_keys($catalog);
                break;
            case 'none':
                $allowedKeys = [];
                break;
            case 'finance':
                $allowedKeys = ['invoices', 'edit_invoices', 'unpaid_invoices', 'tax_audit', 'unlinked_payments', 'dso_trends', 'profit_entry', 'vat_review', 'customers', 'customer_report', 'aging', 'credit', 'upload'];
                break;
            case 'sales':
                $allowedKeys = ['invoices', 'warranties', 'monthly_overview', 'monthly_customer', 'monthly_rep', 'contracts', 'brand_growth', 'customers', 'customer_report', 'eol'];
                break;
            case 'executive':
                $allowedKeys = ['dashboard', 'monthly_overview', 'monthly_customer', 'monthly_rep', 'ltv', 'churn', 'brand_growth', 'dso_trends', 'yearly', 'quarterly'];
                break;
            default:
                throw new InvalidArgumentException("Unknown preset: $preset");
        }

        return $this->setUserReportPermissionsBatch($userId, $allowedKeys);
    }

    /**
     * Clone all permissions from source user to target user
     */
    public function cloneUserPermissions($sourceUserId, $targetUserId) {
        $this->initReportPermissionsSchema();
        $this->db->execute("DELETE FROM report_permissions WHERE user_id = ?", [$targetUserId]);
        $sourceRows = $this->db->fetchAll("SELECT report_key, is_allowed FROM report_permissions WHERE user_id = ?", [$sourceUserId]);
        if (!empty($sourceRows)) {
            $stmt = $this->db->getConnection()->prepare("INSERT INTO report_permissions (user_id, report_key, is_allowed) VALUES (?, ?, ?)");
            foreach ($sourceRows as $sr) {
                $stmt->execute([$targetUserId, $sr['report_key'], $sr['is_allowed']]);
            }
        }
        $this->invalidatePermissionsCache($targetUserId);
        return true;
    }

    /**
     * Toggle all permissions within a category for a user
     */
    public function toggleCategoryPermissions($userId, $categoryKey, $isAllowed) {
        $catalog = self::getReportDefinitions();
        $catReports = array_filter($catalog, fn($r) => $r['category'] === $categoryKey);
        $allowedVal = $isAllowed ? 1 : 0;
        foreach ($catReports as $k => $def) {
            $this->setUserReportPermission($userId, $k, $allowedVal);
        }
        $this->invalidatePermissionsCache($userId);
        return true;
    }

    /**
     * Helper check if user can edit commercial invoices
     */
    public function canEditInvoices($userId = null) {
        return $this->canAccessReport('edit_invoices', $userId);
    }
}

