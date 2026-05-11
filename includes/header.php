<?php
/**
 * Shared Top Header Component
 * Requires $user and $auth to be defined in the calling file.
 * Optional: $searchPlaceholder (string) - placeholder for search box
 */
$searchPlaceholder = $searchPlaceholder ?? 'Search...';
?>
<header class="top-header">
    <div class="header-left">
        <button class="collapse-btn" onclick="toggleSidebar()">
            <i class="icon-menu"></i>
        </button>
        <div class="search-container">
            <i class="icon-search"></i>
            <input type="text" class="search-input" placeholder="<?= htmlspecialchars($searchPlaceholder) ?>">
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
                    <?= strtoupper(substr($user['username'], 0, 1)) ?>
                </div>
                <div class="user-info-brief">
                    <span class="user-name"><?= htmlspecialchars($user['username']) ?></span>
                    <span class="user-role"><?= ucfirst($user['role']) ?></span>
                </div>
                <i class="icon-chevron-down" style="font-size: 12px; color: var(--text-muted); margin-left: 4px;"></i>
            </div>
            <div class="dropdown-menu" id="userDropdown">
                <div class="dropdown-header">
                    <strong><?= htmlspecialchars($user['username']) ?></strong>
                    <span><?= ucfirst($user['role']) ?> Management Account</span>
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
