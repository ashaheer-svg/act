<?php
/**
 * Settings Module: QuickBooks Desktop Sync & Integrations
 * Active Solutions BI Platform
 */

if (!defined('DATABASE_PATH') && !isset($db)) {
    exit('Direct access not permitted.');
}

$auth->requireAccounts();

// Handle POST actions for QuickBooks Sync
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'download_sync_config') {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $dir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        $syncUrl = "$protocol://$host$dir/api/sync.php";
        $configData = [
            'server_url' => $syncUrl,
            'api_key' => $db->getSetting('api_secret_key'),
            'last_sync_date' => $db->getSetting('last_qb_sync', ''),
            'qb_company_file' => '',
            'batch_size' => 250
        ];
        while (ob_get_level()) { ob_end_clean(); }
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="config.json"');
        echo json_encode($configData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'regenerate_api_key') {
        $auth->requireAdmin();
        $newKey = bin2hex(random_bytes(24));
        $db->setSetting('api_secret_key', $newKey);
        $message = 'QuickBooks API Key has been regenerated. Update your Windows sync app config.';
        $messageType = 'success';
        $db->logActivity($user['id'], 'API_KEY_REGENERATED', 'Regenerated QuickBooks sync API key');
    }

    if ($action === 'force_sync') {
        $auth->requireAdmin();
        $syncResult = $db->syncSchema();
        if ($syncResult['success']) {
            $message = 'Database sync successful. ' . implode(' ', $syncResult['messages']);
            $messageType = 'success';
            if (empty($syncResult['messages'])) $message = 'Database is already up to date.';
        } else {
            $message = 'Database sync failed: ' . implode(' ', $syncResult['messages']);
            $messageType = 'error';
        }
    }
}

// Fetch sync config
$apiKey = $db->getSetting('api_secret_key');
$lastQbSync = $db->getSetting('last_qb_sync', '');
$lastQbSummary = $db->getSetting('last_qb_sync_summary', '');
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$dir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$syncApiUrl = "$protocol://$host$dir/api/sync.php";
?>

<!-- QuickBooks Desktop Automated Sync Card -->
<div class="card" style="border-left: 4px solid var(--primary);">
    <h2 style="display: flex; justify-content: space-between; align-items: center;">
        QuickBooks Desktop Automated Sync
        <span style="font-size: 11px; font-weight: 700; color: #166534; background: #dcfce7; padding: 4px 10px; border-radius: 20px; text-transform: uppercase;">REST API Ready</span>
    </h2>
    <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 20px;">
        Connect the local Windows <strong>SalesBISync.exe</strong> utility to extract all invoice details (including full item descriptions and serial numbers) and customer payments in read-only mode.
    </p>

    <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; margin-bottom: 20px;">
        <div style="margin-bottom: 15px;">
            <label style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">API Endpoint URL</label>
            <div style="display: flex; gap: 10px; margin-top: 5px;">
                <input type="text" readonly class="form-control" value="<?php echo htmlspecialchars($syncApiUrl); ?>" id="syncApiUrlInput" style="background: white; font-family: monospace;">
                <button type="button" class="btn" style="background: #e2e8f0; color: var(--text-main);" onclick="navigator.clipboard.writeText(document.getElementById('syncApiUrlInput').value); alert('API URL copied to clipboard!');">Copy</button>
            </div>
        </div>

        <div style="margin-bottom: 15px;">
            <label style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Secret API Key</label>
            <div style="display: flex; gap: 10px; margin-top: 5px;">
                <input type="text" readonly class="form-control" value="<?php echo htmlspecialchars($apiKey); ?>" id="syncApiKeyInput" style="background: white; font-family: monospace;">
                <button type="button" class="btn" style="background: #e2e8f0; color: var(--text-main);" onclick="navigator.clipboard.writeText(document.getElementById('syncApiKeyInput').value); alert('API Key copied to clipboard!');">Copy</button>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--border-color);">
            <div>
                <span style="font-size: 12px; color: var(--text-muted); display: block;">Last Sync Timestamp</span>
                <strong style="font-size: 14px;"><?php echo htmlspecialchars($lastQbSync ?: 'Never'); ?></strong>
            </div>
            <div>
                <span style="font-size: 12px; color: var(--text-muted); display: block;">Last Sync Status</span>
                <span style="font-size: 13px; color: var(--text-main);"><?php echo htmlspecialchars($lastQbSummary ?: 'No sync activity recorded yet'); ?></span>
            </div>
        </div>
    </div>

    <div style="display: flex; gap: 15px; flex-wrap: wrap;">
        <a href="sync_app.php" class="btn" style="background: #16a34a; color: white; display: flex; align-items: center; gap: 8px; text-decoration: none;">
            <i class="icon-cloud-download"></i> Download Windows Sync App (.zip)
        </a>

        <form method="POST" style="margin: 0;">
            <input type="hidden" name="action" value="download_sync_config">
            <button type="submit" class="btn btn-primary" style="display: flex; align-items: center; gap: 8px;">
                <i class="icon-download"></i> Download config.json Only
            </button>
        </form>

        <?php if ($auth->isAdmin()): ?>
        <form method="POST" style="margin: 0;" onsubmit="return confirm('Regenerating this key will disconnect any sync clients using the old key. Continue?');">
            <input type="hidden" name="action" value="regenerate_api_key">
            <button type="submit" class="btn" style="background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca;">
                Regenerate API Key
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($auth->isAdmin()): ?>
<div class="card" style="margin-top: 30px; border-left: 4px solid #0284c7;">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h3 style="margin: 0 0 5px 0;">Database Schema Diagnostic &amp; Auto-Repair</h3>
            <p style="font-size: 13px; color: var(--text-muted); margin: 0;">Verifies and adds any missing database columns, tables, or indexes across your sales and ledger data.</p>
        </div>
        <form method="POST" style="margin: 0;">
            <input type="hidden" name="action" value="force_sync">
            <button type="submit" class="btn" style="background: #f1f5f9; color: var(--text-main); border: 1px solid var(--border-color); font-weight: 600;">Force Sync Schema</button>
        </form>
    </div>
</div>
<?php endif; ?>
