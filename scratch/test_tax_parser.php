<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
$db = new Database(DATABASE_PATH);

$customers = $db->fetchAll("SELECT * FROM customer_profiles");

$updates = [];
foreach ($customers as $c) {
    $text = ($c['bill_address'] ?? '') . ' ' . ($c['bill_city'] ?? '') . ' ' . ($c['bill_state'] ?? '') . ' ' . ($c['bill_zip'] ?? '') . ' ' . ($c['company_name'] ?? '') . ' ' . ($c['notes'] ?? '');
    
    $vatNum = '';
    $tinNum = '';
    $isVat = 0;
    
    // Check for VAT
    if (preg_match('/(?:VAT|SVAT)\s*(?:No\.?|#|Reg(?:istration)?)?\s*[:.-]?\s*([0-9]{9}(?:-[0-9]{4})?|[0-9A-Z\-\/]{7,})/i', $text, $m)) {
        $vatNum = trim($m[1]);
        $isVat = 1;
    } elseif (preg_match('/\b([0-9]{9}-7000)\b/', $text, $m)) {
        $vatNum = trim($m[1]);
        $isVat = 1;
    }
    
    // Check for TIN
    if (preg_match('/(?:TIN)\s*(?:No\.?|#)?\s*[:.-]?\s*([0-9]{9}|[0-9A-Z\-\/]{7,})/i', $text, $m)) {
        $tinNum = trim($m[1]);
        // If they have a corporate TIN in their billing address, they are a registered tax entity
        if (empty($vatNum) && !empty($tinNum)) {
            $isVat = 1; // Corporate registered tax entity
        }
    }
    
    $updates[] = [
        'name' => $c['customer_name'],
        'vat' => $vatNum,
        'tin' => $tinNum,
        'is_vat' => $isVat
    ];
}

$vatRegCount = count(array_filter($updates, fn($u) => $u['is_vat'] == 1));
$nonVatCount = count(array_filter($updates, fn($u) => $u['is_vat'] == 0));

echo "Total Customers: " . count($updates) . "\n";
echo "VAT Registered Entities (VAT or TIN): $vatRegCount\n";
echo "Non-VAT Registered / Retail: $nonVatCount\n\n";

// Check our target customers
foreach ($updates as $u) {
    if ($u['name'] === 'ARC ONE (Pvt) Ltd' || $u['name'] === 'Network Information Technologies (Pvt) Lt') {
        echo "Customer: {$u['name']} => VAT: '{$u['vat']}' | TIN: '{$u['tin']}' | IsRegistered: {$u['is_vat']}\n";
    }
}
