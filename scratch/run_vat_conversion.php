<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

echo "=== 1. Ensuring Tax Rule 16 covers full ASN range ===\n";
$pdo->exec("UPDATE tax_rules SET invoice_range_end = 'ASN999999' WHERE tax_name LIKE '%ASN%'");
$asnRule = $pdo->query("SELECT * FROM tax_rules WHERE tax_name LIKE '%ASN%'")->fetch(PDO::FETCH_ASSOC);
print_r($asnRule);

echo "\n=== 2. Running recalculateHistoricalVat() ===\n";
$start = microtime(true);
$updated = $db->recalculateHistoricalVat();
$duration = round(microtime(true) - $start, 2);
echo "Recalculation completed in {$duration}s. Updated sales rows: $updated\n";

echo "\n=== 3. Post-Recalculation Sales vat_treatment Breakdown ===\n";
$salesTreat = $pdo->query("
    SELECT vat_treatment, count(*) as count, round(sum(total_amount), 2) as total, round(sum(base_value), 2) as base, round(sum(vat_component), 2) as vat
    FROM sales 
    GROUP BY vat_treatment
")->fetchAll(PDO::FETCH_ASSOC);
print_r($salesTreat);

echo "\n=== 4. Post-Recalculation invoice_items vat_treatment Breakdown ===\n";
$itemsTreat = $pdo->query("
    SELECT vat_treatment, count(*) as count, round(sum(total_amount), 2) as total, round(sum(base_value), 2) as base, round(sum(vat_component), 2) as vat
    FROM invoice_items 
    GROUP BY vat_treatment
")->fetchAll(PDO::FETCH_ASSOC);
print_r($itemsTreat);

echo "\n=== 5. Verifying ASN000108 & ASN000104 ===\n";
$sample = $pdo->query("
    SELECT invoice_number, customer_name, sum(base_value) as base, sum(vat_component) as vat, sum(total_amount) as total, max(vat_treatment) as treat
    FROM sales 
    WHERE invoice_number IN ('ASN000108', 'ASN000104')
    GROUP BY invoice_number
")->fetchAll(PDO::FETCH_ASSOC);
print_r($sample);

echo "\n=== 6. Math Integrity Check across entire database ===\n";
$integrity = $pdo->query("
    SELECT 
        round(sum(total_amount), 2) as grand_total,
        round(sum(base_value), 2) as grand_base,
        round(sum(vat_component), 2) as grand_vat,
        round(sum(base_value) + sum(vat_component), 2) as base_plus_vat,
        round(sum(total_amount) - (sum(base_value) + sum(vat_component)), 2) as delta
    FROM sales
")->fetch(PDO::FETCH_ASSOC);
print_r($integrity);
