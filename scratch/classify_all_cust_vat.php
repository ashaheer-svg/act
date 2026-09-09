<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
$db = new Database(DATABASE_PATH);

$customers = $db->fetchAll("SELECT * FROM customer_profiles");

$hasExplicitVat = [];
$hasTinOnly = [];
$noTaxInfo = [];

foreach ($customers as $c) {
    $text = ($c['bill_address'] ?? '') . ' ' . ($c['bill_city'] ?? '') . ' ' . ($c['bill_state'] ?? '') . ' ' . ($c['bill_zip'] ?? '') . ' ' . ($c['company_name'] ?? '') . ' ' . ($c['account_number'] ?? '');
    
    // Check for explicit VAT number (e.g. VAT No, VAT:, -7000)
    $isVat = false;
    $vatNumber = '';
    if (preg_match('/VAT\s*(?:No\.?|#|Registration)?\s*[:.-]?\s*([0-9A-Z\-\/]+)/i', $text, $m)) {
        $isVat = true;
        $vatNumber = trim($m[1]);
    } elseif (preg_match('/([0-9]{9}-7000)/', $text, $m)) {
        $isVat = true;
        $vatNumber = trim($m[1]);
    } elseif (preg_match('/SVAT\s*(?:No\.?|#)?\s*[:.-]?\s*([0-9A-Z\-\/]+)/i', $text, $m)) {
        $isVat = true;
        $vatNumber = 'SVAT: ' . trim($m[1]);
    }
    
    // Check for TIN
    $isTin = false;
    $tinNumber = '';
    if (preg_match('/TIN\s*(?:No\.?|#)?\s*[:.-]?\s*([0-9A-Z\-\/]+)/i', $text, $m)) {
        $isTin = true;
        $tinNumber = trim($m[1]);
    }
    
    if ($isVat) {
        $hasExplicitVat[] = [
            'name' => $c['customer_name'],
            'vat' => $vatNumber,
            'tin' => $tinNumber,
            'raw' => substr(trim($text), 0, 80)
        ];
    } elseif ($isTin) {
        $hasTinOnly[] = [
            'name' => $c['customer_name'],
            'tin' => $tinNumber,
            'raw' => substr(trim($text), 0, 80)
        ];
    } else {
        $noTaxInfo[] = $c['customer_name'];
    }
}

echo "=== Total Customers: " . count($customers) . " ===\n";
echo "1. Customers with explicit VAT Number: " . count($hasExplicitVat) . "\n";
echo "2. Customers with TIN only (no explicit VAT word): " . count($hasTinOnly) . "\n";
echo "3. Customers with no tax registration mentioned: " . count($noTaxInfo) . "\n\n";

echo "--- Sample 10 Customers with Explicit VAT Number ---\n";
foreach (array_slice($hasExplicitVat, 0, 10) as $item) {
    echo "  • {$item['name']} => VAT: {$item['vat']} (Raw: {$item['raw']})\n";
}

echo "\n--- Sample 10 Customers with TIN Only ---\n";
foreach (array_slice($hasTinOnly, 0, 10) as $item) {
    echo "  • {$item['name']} => TIN: {$item['tin']} (Raw: {$item['raw']})\n";
}

echo "\n--- What about Network Information Technologies? ---\n";
foreach ($hasTinOnly as $item) {
    if (stripos($item['name'], 'Network Information Technologies') !== false) {
        echo "  • NIT found in TIN Only: {$item['tin']} | Raw: {$item['raw']}\n";
    }
}
foreach ($hasExplicitVat as $item) {
    if (stripos($item['name'], 'Network Information Technologies') !== false) {
        echo "  • NIT found in Explicit VAT: {$item['vat']} | Raw: {$item['raw']}\n";
    }
}
