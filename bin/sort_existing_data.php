<?php
/**
 * Master Data Sorting & Entity Extraction Runner
 *
 * Runs the high-performance local DataSorter across invoices in the sales ledger.
 *
 * Usage:
 *   php bin/sort_existing_data.php [--dry-run] [--limit=100] [--force] [--invoice=AS000001]
 */

declare(strict_types=1);

$isCli = (php_sapi_name() === 'cli');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/DataSorter.php';

$db = new Database(DATABASE_PATH);
$sorter = new DataSorter($db);

// Parse CLI options
$options = [];
if ($isCli) {
    $options = getopt('', ['dry-run', 'limit::', 'force', 'invoice::', 'quiet']);
} else {
    require_once __DIR__ . '/../classes/Auth.php';
    $auth = new Auth($db);
    $auth->requireAdmin();
    $options = [
        'dry-run' => isset($_GET['dry_run']),
        'limit' => $_GET['limit'] ?? null,
        'force' => isset($_GET['force']),
        'invoice' => $_GET['invoice'] ?? null,
        'quiet' => isset($_GET['quiet'])
    ];
}

$isDryRun = isset($options['dry-run']);
$limit = isset($options['limit']) ? intval($options['limit']) : 0;
$force = isset($options['force']);
$singleInvoice = $options['invoice'] ?? null;
$quiet = isset($options['quiet']);

if (!$quiet) {
    if (!$isCli) echo "<pre style='font-family: monospace; background: #0f172a; color: #38bdf8; padding: 20px; border-radius: 10px;'>";
    echo "======================================================================\n";
    echo "   QuickBooks Master Data Sorter & Entity Extraction Engine\n";
    echo "   Mode: " . ($isDryRun ? "DRY-RUN (Preview only, no DB writes)" : "PRODUCTION (Updating relational registries)") . "\n";
    echo "   Started at: " . date('Y-m-d H:i:s') . "\n";
    echo "======================================================================\n\n";
}

// 1. Query candidate invoices
if (!empty($singleInvoice)) {
    $candidates = [['invoice_number' => trim($singleInvoice)]];
} else {
    if ($force) {
        $sql = "SELECT DISTINCT invoice_number FROM sales WHERE total_amount > 0 ORDER BY invoice_date DESC";
        if ($limit > 0) $sql .= " LIMIT $limit";
        $candidates = $db->fetchAll($sql);
    } else {
        $sql = "
            SELECT DISTINCT s.invoice_number 
            FROM sales s
            LEFT JOIN invoice_items ii ON s.invoice_number = ii.invoice_number
            WHERE s.total_amount > 0 AND ii.id IS NULL
            ORDER BY s.invoice_date DESC
        ";
        if ($limit > 0) $sql .= " LIMIT $limit";
        $candidates = $db->fetchAll($sql);
    }
}

$totalCandidates = count($candidates);

if ($totalCandidates === 0) {
    if (!$quiet) {
        echo "No pending invoices to sort! All invoices are already extracted.\n";
        echo "Use --force to re-process already sorted invoices.\n";
        if (!$isCli) echo "</pre>";
    }
    exit(0);
}

if (!$quiet) {
    echo "Invoices queued for processing: $totalCandidates\n";
    echo "----------------------------------------------------------------------\n";
}

$startTime = microtime(true);
$processed = 0;
$totalItems = 0;
$totalHardwareAssets = 0;
$totalSubscriptions = 0;
$financialMismatches = 0;

foreach ($candidates as $idx => $row) {
    $invNum = $row['invoice_number'];
    $processed++;

    try {
        $sorted = $sorter->sortInvoice($invNum);

        // Financial validation guard
        $itemsSum = 0.0;
        foreach ($sorted['products'] as $p) {
            $itemsSum += floatval($p['total_amount']);
        }
        $gross = floatval($sorted['total_gross']);
        if (abs($gross - $itemsSum) > 1.00 && $gross > 0) {
            $financialMismatches++;
        }

        if (!$isDryRun) {
            $saved = $sorter->persistSortedData($sorted);
            $totalItems += $saved['items'];
            $totalHardwareAssets += $saved['hardware_assets'];
            $totalSubscriptions += $saved['subscriptions'];
        } else {
            // Count for dry-run
            $totalItems += count($sorted['products']);
            foreach ($sorted['products'] as $p) {
                if ($p['product_type'] === 'HARDWARE' || !empty($p['serials'])) {
                    $totalHardwareAssets += max(1, count($p['serials']));
                }
                if ($p['subscription'] !== null) {
                    $totalSubscriptions++;
                }
            }
        }

        // Live progress indicator every 100 invoices or at completion
        if (!$quiet && ($processed % 100 === 0 || $processed === $totalCandidates)) {
            $percent = round(($processed / $totalCandidates) * 100, 1);
            $elapsed = round(microtime(true) - $startTime, 2);
            $rate = round($processed / max(0.001, $elapsed), 1);
            echo sprintf(
                "[%3d%%] Processed: %d/%d invoices (%s inv/s) | Items: %d | HW Assets: %d | Subs/MA: %d\n",
                $percent, $processed, $totalCandidates, $rate, $totalItems, $totalHardwareAssets, $totalSubscriptions
            );
        }
    } catch (Exception $e) {
        if (!$quiet) {
            echo "Error processing invoice #$invNum: " . $e->getMessage() . "\n";
        }
    }
}

$totalDuration = round(microtime(true) - $startTime, 3);

if (!$quiet) {
    echo "\n======================================================================\n";
    echo "   SORTING SUMMARY RESULTS\n";
    echo "======================================================================\n";
    echo "Total Invoices Processed:   $processed\n";
    echo "Total Commercial Items:     $totalItems (in invoice_items)\n";
    echo "Total Hardware Assets:      $totalHardwareAssets (in hardware_assets)\n";
    echo "Total Software/MA Records:  $totalSubscriptions (in software_subscriptions)\n";
    echo "Financial Mismatches:       $financialMismatches\n";
    echo "Total Time Elapsed:         {$totalDuration}s (" . round($processed / max(0.001, $totalDuration), 1) . " inv/sec)\n";
    echo "Status:                     " . ($isDryRun ? "DRY-RUN COMPLETE (No DB changes)" : "SUCCESSFULLY PERSISTED") . "\n";
    echo "======================================================================\n";
    if (!$isCli) echo "</pre>";
}
