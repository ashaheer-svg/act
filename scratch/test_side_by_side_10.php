<?php
/**
 * Head-to-Head Comparison: Local Deterministic Engine vs. Gemini 3.6 Flash AI
 *
 * Compares extraction across 10 diverse representative invoices covering:
 * - Multi-unit hardware with serials (Qty 20, Qty 6, Qty 2)
 * - Software licenses (ESET, Acronis)
 * - Maintenance Agreements (MA / Extended Warranty)
 * - Services & Configuration charges
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/DataSorter.php';
require_once __DIR__ . '/../classes/AiExtractor.php';

$db = new Database(DATABASE_PATH);
$sorter = new DataSorter($db);
$aiExtractor = new AiExtractor($db);

$invoices = [
    'AS000001', // Hardware: 20x BDCOM switches with 20 serials & 1-year warranty
    'AS000004', // Hardware: Synology DS425+ NAS + 2x 8TB Seagate HDDs (2 serials) + 3Y warranty
    'AS008832', // Hardware: 2x 4TB Seagate Barracuda HDDs (2 serials) + 2Y warranty
    'AS008835', // Hardware: 2x 8TB Enterprise HDDs (2 serials)
    'AS008836', // Hardware: Synology RS1221RP+ + 6x Toshiba HDDs (6 serials)
    'AS008895', // Software: ESET Endpoint Security License (26 seats, 1-year date range)
    'AS009036', // Software: Acronis Cyber Backup Standard + Essentials (multiple licenses)
    'AS008899', // Maintenance Agreement: Synology NAS + 4x 8TB HDDs (5 serials, covered dates)
    'AS008850', // Maintenance Agreement: IT Backup System MA for 1 Year (Synology + 2 HDDs)
    'AS000005'  // Mixed: DrayTek Router + BDCOM Switch + Configuration Charges
];

echo "======================================================================\n";
echo "   Side-by-Side Comparison: Local Engine vs Gemini 3.6 Flash AI\n";
echo "   Testing 10 Representative Commercial Invoices\n";
echo "======================================================================\n\n";

$results = [];

foreach ($invoices as $idx => $inv) {
    echo "Processing Invoice #" . $inv . " (" . ($idx + 1) . "/10)...\n";
    $lines = $db->fetchAll("
        SELECT id, invoice_type, invoice_date, invoice_number, customer_name,
               item_description, tax_code, quantity, total_amount, base_value,
               vat_component, applied_tax_rate, vat_treatment, product_category
        FROM sales
        WHERE invoice_number = ?
        ORDER BY id ASC
    ", [$inv]);

    // 1. Run Local Engine
    $t0 = microtime(true);
    $localData = $sorter->sortInvoice($inv);
    $localMs = round((microtime(true) - $t0) * 1000, 2);

    // 2. Run AI Engine (Gemini 3.6 Flash)
    $t0 = microtime(true);
    $aiSuccess = false;
    $aiData = null;
    $aiError = null;

    try {
        $method = new ReflectionMethod('AiExtractor', 'buildInvoiceExtractionPrompt');
        $prompt = $method->invoke($aiExtractor, $lines);

        $callMethod = new ReflectionMethod('AiExtractor', 'callProviderRaw');
        $rawResponse = $callMethod->invoke($aiExtractor, $prompt);

        $parseMethod = new ReflectionMethod('AiExtractor', 'parseJsonResponse');
        $aiData = $parseMethod->invoke($aiExtractor, $rawResponse);
        $aiSuccess = true;
    } catch (Exception $e) {
        $aiError = $e->getMessage();
    }
    $aiMs = round((microtime(true) - $t0) * 1000, 2);

    $results[$inv] = [
        'invoice' => $inv,
        'customer' => $lines[0]['customer_name'],
        'date' => $lines[0]['invoice_date'],
        'local' => [
            'duration_ms' => $localMs,
            'products' => $localData['products']
        ],
        'ai' => [
            'success' => $aiSuccess,
            'duration_ms' => $aiMs,
            'error' => $aiError,
            'products' => $aiData['products'] ?? []
        ]
    ];

    echo "  Local: {$localMs}ms | AI: " . ($aiSuccess ? "{$aiMs}ms" : "FAILED ({$aiError})") . "\n";
    sleep(1);
}

// Generate Comparative Table
echo "\n======================================================================\n";
echo "   DETAILED COMPARISON TABLE\n";
echo "======================================================================\n";

foreach ($results as $inv => $r) {
    echo "\n----------------------------------------------------------------------\n";
    echo "INVOICE #{$inv} | Cust: {$r['customer']} | Date: {$r['date']}\n";
    echo "Speed: Local = {$r['local']['duration_ms']} ms | AI = {$r['ai']['duration_ms']} ms\n";
    
    // Local products summary
    $locSerials = [];
    $locWarranties = [];
    $locSubs = [];
    foreach ($r['local']['products'] as $p) {
        foreach ($p['serials'] as $s) $locSerials[] = $s;
        if (!empty($p['warranty']['duration_months'])) $locWarranties[] = "{$p['warranty']['duration_months']}M ({$p['warranty']['expiry_date']})";
        if ($p['subscription']) $locSubs[] = "{$p['subscription']['software_name']} ({$p['subscription']['license_seats']} seats, {$p['subscription']['period_start_date']} to {$p['subscription']['period_end_date']})";
    }

    // AI products summary
    $aiSerials = [];
    $aiWarranties = [];
    $aiSubs = [];
    if ($r['ai']['success']) {
        foreach ($r['ai']['products'] as $p) {
            foreach ($p['serials'] ?? [] as $s) $aiSerials[] = $s;
            if (!empty($p['warranty']['duration_months'])) $aiWarranties[] = "{$p['warranty']['duration_months']}M (" . ($p['warranty']['expiry_date'] ?? 'N/A') . ")";
            if (!empty($p['subscription']['software_name'])) $aiSubs[] = "{$p['subscription']['software_name']} (" . ($p['subscription']['license_seats'] ?? 1) . " seats, " . ($p['subscription']['period_start_date'] ?? 'N/A') . " to " . ($p['subscription']['period_end_date'] ?? 'N/A') . ")";
        }
    }

    echo "  [LOCAL ENGINE]\n";
    echo "    - Products (" . count($r['local']['products']) . "): " . implode(', ', array_map(fn($p) => "{$p['product_name']} [{$p['product_type']}]", $r['local']['products'])) . "\n";
    echo "    - Serials (" . count($locSerials) . "): " . (empty($locSerials) ? 'None' : implode(', ', array_slice($locSerials, 0, 5)) . (count($locSerials) > 5 ? '...' : '')) . "\n";
    echo "    - Warranty: " . (empty($locWarranties) ? 'None' : implode(', ', $locWarranties)) . "\n";
    echo "    - Sub/MA: " . (empty($locSubs) ? 'None' : implode('; ', $locSubs)) . "\n";

    echo "  [GEMINI 3.6 FLASH AI]\n";
    if ($r['ai']['success']) {
        echo "    - Products (" . count($r['ai']['products']) . "): " . implode(', ', array_map(fn($p) => "{$p['product_name']} [{$p['product_type']}]", $r['ai']['products'])) . "\n";
        echo "    - Serials (" . count($aiSerials) . "): " . (empty($aiSerials) ? 'None' : implode(', ', array_slice($aiSerials, 0, 5)) . (count($aiSerials) > 5 ? '...' : '')) . "\n";
        echo "    - Warranty: " . (empty($aiWarranties) ? 'None' : implode(', ', $aiWarranties)) . "\n";
        echo "    - Sub/MA: " . (empty($aiSubs) ? 'None' : implode('; ', $aiSubs)) . "\n";
    } else {
        echo "    - AI Extraction FAILED: {$r['ai']['error']}\n";
    }
}
