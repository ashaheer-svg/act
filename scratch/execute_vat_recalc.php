<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

echo "=== Starting recalculateHistoricalVat() ===\n";
$start = microtime(true);
$updated = $db->recalculateHistoricalVat();
$duration = round(microtime(true) - $start, 2);
echo "Recalculation completed. Updated rows: $updated in $duration seconds.\n\n";

echo "=== Verifying Post-2024 Invoices in sales table ===\n";
$q = $pdo->query("
    SELECT 
        vat_treatment,
        COUNT(DISTINCT invoice_number) as inv_count,
        COUNT(*) as row_count,
        SUM(base_value) as sum_base,
        SUM(vat_component) as sum_vat,
        SUM(total_amount) as sum_gross
    FROM sales
    WHERE invoice_date >= '2024-01-01'
    GROUP BY vat_treatment
");
while ($r = $q->fetch(PDO::FETCH_ASSOC)) {
    echo sprintf(
        "Treatment: %-15s | Invoices: %4d | Rows: %5d | Gross: %14.2f | Base: %14.2f | VAT: %12.2f\n",
        $r['vat_treatment'],
        $r['inv_count'],
        $r['row_count'],
        $r['sum_gross'],
        $r['sum_base'],
        $r['sum_vat']
    );
}

echo "\n=== Verifying Post-2024 in invoice_items table ===\n";
$qItems = $pdo->query("
    SELECT 
        vat_treatment,
        COUNT(DISTINCT invoice_number) as inv_count,
        COUNT(*) as item_count,
        SUM(base_value) as sum_base,
        SUM(vat_component) as sum_vat,
        SUM(total_amount) as sum_gross
    FROM invoice_items
    WHERE invoice_date >= '2024-01-01'
    GROUP BY vat_treatment
");
while ($r = $qItems->fetch(PDO::FETCH_ASSOC)) {
    echo sprintf(
        "Treatment: %-15s | Invoices: %4d | Items: %5d | Gross: %14.2f | Base: %14.2f | VAT: %12.2f\n",
        $r['vat_treatment'],
        $r['inv_count'],
        $r['item_count'],
        $r['sum_gross'],
        $r['sum_base'],
        $r['sum_vat']
    );
}

echo "\n=== Verifying Total Post-2024 Combined Numbers ===\n";
$totals = $pdo->query("
    SELECT 
        COUNT(DISTINCT invoice_number) as total_invoices,
        SUM(base_value) as total_base,
        SUM(vat_component) as total_vat,
        SUM(total_amount) as total_gross
    FROM sales
    WHERE invoice_date >= '2024-01-01'
")->fetch(PDO::FETCH_ASSOC);

echo sprintf(
    "Total Invoices: %d | Gross: LKR %s | Base: LKR %s | VAT: LKR %s\n",
    $totals['total_invoices'],
    number_format($totals['total_gross'], 2),
    number_format($totals['total_base'], 2),
    number_format($totals['total_vat'], 2)
);

$reconciliation = abs($totals['total_gross'] - ($totals['total_base'] + $totals['total_vat']));
echo "Gross vs (Base + VAT) difference: LKR " . number_format($reconciliation, 2) . "\n";
