<?php
require_once 'config.php';
require_once 'classes/Database.php';

$db = new Database(DATABASE_PATH);

// Reset tax_rules so the new default sequence rules are cleanly seeded
$db->execute("DELETE FROM tax_rules");

echo "Triggering schema sync and VAT recalculation...\n";
$start = microtime(true);
$count = $db->recalculateHistoricalVat();
$duration = round(microtime(true) - $start, 2);

echo "Successfully recalculated {$count} sales lines in {$duration}s.\n\n";

echo "=== SEEDED TAX RULES ===\n";
$rules = $db->getTaxRules();
foreach ($rules as $r) {
    echo sprintf("#%d: %-25s | Rate: %-5s | Inv Range: %-10s to %-10s | Date: %s to %s\n",
        $r['id'], $r['tax_name'], ($r['tax_rate']*100).'%', 
        $r['invoice_range_start'] ?? 'ANY', $r['invoice_range_end'] ?? 'ANY',
        $r['effective_from'] ?? 'ANY', $r['effective_to'] ?? 'ANY'
    );
}

echo "\n=== SAMPLE 2021-2023 EXEMPT INVOICES (AS008212 - AS010020) ===\n";
$sampleExempt = $db->fetchAll("
    SELECT invoice_number, invoice_date, total_amount, base_value, vat_component, applied_tax_rate, vat_treatment
    FROM sales 
    WHERE invoice_number IN ('AS008832', 'AS009461', 'AS010020') AND total_amount > 0
    LIMIT 3
");
print_r($sampleExempt);

echo "\n=== SAMPLE 2024-2026 INVOICES (AS010021 - AS011260: 18% INCLUSIVE) ===\n";
$sample18 = $db->fetchAll("
    SELECT invoice_number, invoice_date, total_amount, base_value, vat_component, applied_tax_rate, vat_treatment
    FROM sales 
    WHERE invoice_number IN ('AS010021', 'AS011260') AND total_amount > 0
    LIMIT 3
");
print_r($sample18);

echo "\n=== SAMPLE NEW SEQUENCE INVOICES (AS000001 - AS000102: 18% INCLUSIVE) ===\n";
$sampleNew = $db->fetchAll("
    SELECT invoice_number, invoice_date, total_amount, base_value, vat_component, applied_tax_rate, vat_treatment
    FROM sales 
    WHERE invoice_number IN ('AS000001', 'AS000102') AND total_amount > 0
    LIMIT 3
");
print_r($sampleNew);
