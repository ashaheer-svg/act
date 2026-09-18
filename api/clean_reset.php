<?php
/**
 * Clean Reset API Endpoint (Protected by X-API-KEY)
 * Backs up the database and wipes all transaction and customer data
 * leaving users, settings, tax rules, and master configurations intact.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$db->initialize();
$db->initializeSettings();

// Authenticate API Key
$headers = function_exists('getallheaders') ? getallheaders() : [];
$apiKey = $headers['X-API-KEY'] ?? $headers['x-api-key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';

$configuredKey = $db->getSetting('api_secret_key');
if (empty($configuredKey) || empty($apiKey) || !hash_equals($configuredKey, $apiKey)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Invalid API key.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed. Use POST to execute reset.']);
    exit;
}

try {
    // 1. Safety Backup
    $dbPath = DATABASE_PATH;
    $backupPath = __DIR__ . '/../data/sales_bi_backup_pre_reset_' . date('Ymd_His') . '.db';
    if (file_exists($dbPath)) {
        copy($dbPath, $backupPath);
    }

    // 2. Wipe transaction, customer, and extracted asset tables
    $pdo = $db->getConnection();
    $pdo->exec("PRAGMA foreign_keys = OFF;");
    
    $tablesToWipe = [
        'hardware_assets',
        'software_subscriptions',
        'invoice_items',
        'sales',
        'payments',
        'customer_profiles',
        'import_logs',
        'activity_log'
    ];

    $wipeSummary = [];
    foreach ($tablesToWipe as $table) {
        $countBefore = $db->fetch("SELECT count(*) as c FROM $table")['c'] ?? 0;
        $pdo->exec("DELETE FROM $table");
        $wipeSummary[$table] = (int)$countBefore;
    }

    // Reset sqlite sequence for wiped tables
    foreach ($tablesToWipe as $table) {
        $pdo->exec("DELETE FROM sqlite_sequence WHERE name = '$table'");
    }

    $pdo->exec("PRAGMA foreign_keys = ON;");

    // Reset last sync cursor
    $db->setSetting('last_qb_sync', 'Never');
    $db->setSetting('last_qb_sync_summary', 'Database reset performed at ' . date('Y-m-d H:i:s'));

    // Optimize database file
    $pdo->exec("VACUUM");

    echo json_encode([
        'success' => true,
        'message' => 'Database successfully wiped and reset to clean state.',
        'backup_created' => basename($backupPath),
        'records_cleared' => $wipeSummary,
        'timestamp' => date('c')
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Reset failed: ' . $e->getMessage()
    ]);
}
