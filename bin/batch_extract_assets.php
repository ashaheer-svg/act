<?php
/**
 * Batch Asset & Entity Extractor Runner
 *
 * Runs the pluggable AiExtractor across all invoices in the sales ledger.
 *
 * Usage:
 * CLI: php bin/batch_extract_assets.php [--limit=50] [--force] [--invoice=AS000102]
 * Web: Accessible via settings or admin interface with progress display.
 */

// Determine environment
$isCli = (php_sapi_name() === 'cli');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/AiExtractor.php';

$db = new Database(DATABASE_PATH);
$extractor = new AiExtractor($db);
$settings = $extractor->getSettings();

if (!$settings['has_key'] && empty($settings['custom_endpoint'])) {
    $msg = "Error: No AI API Key is configured. Please configure your Google Gemini API Key in Settings.\n";
    if ($isCli) {
        echo $msg;
        exit(1);
    } else {
        die($msg);
    }
}

// Parse CLI arguments
$options = [];
if ($isCli) {
    $options = getopt('', ['limit::', 'force', 'invoice::']);
} else {
    // Auth check if web
    require_once __DIR__ . '/../classes/Auth.php';
    $auth = new Auth($db);
    $auth->requireAdmin();
    $options = [
        'limit' => $_GET['limit'] ?? 25,
        'force' => isset($_GET['force']),
        'invoice' => $_GET['invoice'] ?? null
    ];
}

$limit = isset($options['limit']) ? intval($options['limit']) : 50;
$force = isset($options['force']);
$singleInvoice = $options['invoice'] ?? null;

echo $isCli ? "" : "<pre style='font-family: monospace; background: #0f172a; color: #38bdf8; padding: 20px; border-radius: 10px;'>";
echo "========================================================\n";
echo "   AI Asset & Subscription Batch Extractor\n";
echo "   Provider: " . strtoupper($settings['provider']) . " | Model: {$settings['model']}\n";
echo "========================================================\n\n";

// Query candidate invoices
if (!empty($singleInvoice)) {
    $invoices = [['invoice_number' => trim($singleInvoice)]];
} else {
    if ($force) {
        $invoices = $db->fetchAll("
            SELECT DISTINCT invoice_number 
            FROM sales 
            WHERE total_amount > 0
            ORDER BY invoice_date DESC 
            LIMIT ?
        ", [$limit]);
    } else {
        // Only invoices not yet extracted into invoice_items
        $invoices = $db->fetchAll("
            SELECT DISTINCT s.invoice_number 
            FROM sales s
            LEFT JOIN invoice_items ii ON s.invoice_number = ii.invoice_number
            WHERE s.total_amount > 0 AND ii.id IS NULL
            ORDER BY s.invoice_date DESC 
            LIMIT ?
        ", [$limit]);
    }
}

$totalCandidates = count($invoices);
echo "Invoices queued for extraction: $totalCandidates\n";
echo "--------------------------------------------------------\n";

if ($totalCandidates === 0) {
    echo "No pending invoices found. All invoices have already been extracted!\n";
    if (!$isCli) echo "</pre>";
    exit(0);
}

$processed = 0;
$successCount = 0;
$failCount = 0;
$startTime = microtime(true);

foreach ($invoices as $invRow) {
    $invNum = $invRow['invoice_number'];
    $processed++;

    echo sprintf("[%d/%d] Processing Invoice #%-12s ... ", $processed, $totalCandidates, $invNum);

    try {
        $result = $extractor->extractInvoice($invNum);
        $duration = $result['duration_seconds'];
        $items = $result['entities_created']['items'];
        $assets = $result['entities_created']['hardware_assets'];
        $subs = $result['entities_created']['subscriptions'];

        echo "SUCCESS ({$duration}s) | Items: $items, Serials: $assets, Subs: $subs\n";
        $successCount++;
    } catch (Exception $e) {
        echo "FAILED: " . $e->getMessage() . "\n";
        $failCount++;
    }

    // Rate limiting delay (1.2s to respect API rate limits)
    if ($processed < $totalCandidates) {
        usleep(1200000);
    }
}

$totalDuration = round(microtime(true) - $startTime, 2);
echo "--------------------------------------------------------\n";
echo "Batch Completed in {$totalDuration}s\n";
echo "Processed: $processed | Succeeded: $successCount | Failed: $failCount\n";
echo "========================================================\n";

if (!$isCli) echo "</pre>";
