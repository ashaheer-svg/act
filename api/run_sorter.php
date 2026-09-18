<?php
/**
 * Data Sorter Execution API
 * Deterministically sorts sales invoice rows into:
 * - invoice_items
 * - hardware_assets
 * - software_subscriptions
 */

header('Content-Type: application/json; charset=utf-8');
set_time_limit(180);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/DataSorter.php';

$db = new Database(DATABASE_PATH);
$db->initialize();
$db->initializeSettings();

// Authenticate API Key
$headers = function_exists('getallheaders') ? getallheaders() : [];
$apiKey = $headers['X-API-KEY'] ?? $headers['x-api-key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';

$configuredKey = $db->getSetting('api_secret_key');
if (empty($configuredKey) || empty($apiKey) || !hash_equals($configuredKey, $apiKey)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;
$limit = isset($_GET['limit']) ? min(1000, max(1, (int)$_GET['limit'])) : 500;

// If reset requested, clean destination tables
if (isset($_GET['reset']) && $_GET['reset'] === '1' && $offset === 0) {
    $db->execute("DELETE FROM hardware_assets");
    $db->execute("DELETE FROM software_subscriptions");
    $db->execute("DELETE FROM invoice_items");
}

// Fetch distinct invoice numbers
$totalInvoices = (int)($db->fetch("
    SELECT COUNT(DISTINCT invoice_number) as c 
    FROM sales 
    WHERE invoice_type != 'Credit Memo' AND invoice_number IS NOT NULL AND TRIM(invoice_number) != ''
")['c'] ?? 0);

$invoices = $db->fetchAll("
    SELECT DISTINCT invoice_number 
    FROM sales 
    WHERE invoice_type != 'Credit Memo' AND invoice_number IS NOT NULL AND TRIM(invoice_number) != ''
    ORDER BY invoice_date DESC, id DESC
    LIMIT ? OFFSET ?
", [$limit, $offset]);

$sorter = new DataSorter($db);
$processed = 0;
$itemsCreated = 0;
$hardwareCreated = 0;
$subsCreated = 0;
$errors = [];

foreach ($invoices as $row) {
    $invNum = $row['invoice_number'];
    try {
        $sorted = $sorter->sortInvoice($invNum);
        $res = $sorter->persistSortedData($sorted);
        $processed++;
        $itemsCreated += $res['items'];
        $hardwareCreated += $res['hardware_assets'];
        $subsCreated += $res['subscriptions'];
    } catch (Throwable $e) {
        $errors[] = ['invoice' => $invNum, 'error' => $e->getMessage()];
    }
}

$nextOffset = $offset + count($invoices);
$hasMore = $nextOffset < $totalInvoices;

// Current counts in tables
$totalItemsCount = (int)($db->fetch("SELECT COUNT(*) as c FROM invoice_items")['c'] ?? 0);
$totalHardwareCount = (int)($db->fetch("SELECT COUNT(*) as c FROM hardware_assets")['c'] ?? 0);
$hardwareWithSerials = (int)($db->fetch("SELECT COUNT(*) as c FROM hardware_assets WHERE serial_number IS NOT NULL AND serial_number != '' AND serial_number != 'UNASSIGNED'")['c'] ?? 0);
$totalSubsCount = (int)($db->fetch("SELECT COUNT(*) as c FROM software_subscriptions")['c'] ?? 0);

echo json_encode([
    'success' => true,
    'total_invoices_in_db' => $totalInvoices,
    'batch_offset' => $offset,
    'batch_limit' => $limit,
    'processed_this_batch' => $processed,
    'items_created_this_batch' => $itemsCreated,
    'hardware_created_this_batch' => $hardwareCreated,
    'subs_created_this_batch' => $subsCreated,
    'next_offset' => $nextOffset,
    'has_more' => $hasMore,
    'cumulative_counts' => [
        'invoice_items' => $totalItemsCount,
        'hardware_assets' => $totalHardwareCount,
        'hardware_with_serials' => $hardwareWithSerials,
        'software_subscriptions' => $totalSubsCount
    ],
    'errors_count' => count($errors),
    'errors_sample' => array_slice($errors, 0, 5)
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
