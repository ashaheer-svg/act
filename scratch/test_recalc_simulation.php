<?php
require_once __DIR__ . '/../config.php';
$pdo = new PDO('sqlite:' . DATABASE_PATH);

echo "=== Simulating Post-2024 VAT Inclusive Recalculation ===\n";

$sql = "
    SELECT 
        id, invoice_number, invoice_date, customer_name, qb_amount, total_amount, base_value, vat_component,
        sales_tax_item, sales_tax_total, sales_tax_rate, vat_treatment
    FROM sales
    WHERE invoice_date >= '2024-01-01'
";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
echo "Total post-2024 sales rows: " . count($rows) . "\n";

$countPlusVat = 0;
$countInclusive = 0;
$countZeroExempt = 0;

$sumPlusVat = 0;
$sumPlusGross = 0;
$sumPlusBase = 0;

$sumInclVat = 0;
$sumInclGross = 0;
$sumInclBase = 0;

$invoicesPlus = [];
$invoicesIncl = [];
$invoicesZero = [];

foreach ($rows as $r) {
    $rawAmt = floatval(($r['qb_amount'] ?? 0) != 0 ? $r['qb_amount'] : $r['total_amount']);
    $salesTaxTotal = floatval($r['sales_tax_total'] ?? 0);
    $salesTaxRate = floatval($r['sales_tax_rate'] ?? 0);
    $salesTaxItem = trim($r['sales_tax_item'] ?? '');
    $invNum = $r['invoice_number'];
    $rate = 0.18; // Statutory 18% post-2024

    if ($rawAmt == 0) {
        $treatment = 'VAT_EXEMPT';
        $base = 0.00;
        $vat = 0.00;
        $total = 0.00;
        $countZeroExempt++;
        $invoicesZero[$invNum] = true;
    } elseif ($salesTaxTotal > 0 && strcasecmp($salesTaxItem, 'VAT') === 0) {
        // Explicit +VAT
        $effRate = ($salesTaxRate > 0) ? ($salesTaxRate / 100) : $rate;
        $treatment = 'PLUS_VAT';
        $base = $rawAmt;
        $vat = round($rawAmt * $effRate, 2);
        $total = round($base + $vat, 2);
        $countPlusVat++;
        $sumPlusBase += $base;
        $sumPlusVat += $vat;
        $sumPlusGross += $total;
        $invoicesPlus[$invNum] = true;
    } else {
        // All other post-2024 invoices are VAT_INCLUSIVE
        $treatment = 'VAT_INCLUSIVE';
        $total = $rawAmt;
        $base = round($rawAmt / (1 + $rate), 2);
        $vat = round($total - $base, 2);
        $countInclusive++;
        $sumInclBase += $base;
        $sumInclVat += $vat;
        $sumInclGross += $total;
        $invoicesIncl[$invNum] = true;
    }
}

echo "Distinct Invoices:\n";
echo "  +VAT Invoices: " . count($invoicesPlus) . "\n";
echo "  VAT Inclusive Invoices: " . count($invoicesIncl) . "\n";
echo "  Zero / Void Only Invoices: " . count(array_diff_key($invoicesZero, $invoicesPlus, $invoicesIncl)) . "\n\n";

echo sprintf("Explicit +VAT: Gross = %14.2f | Base = %14.2f | VAT = %14.2f\n", $sumPlusGross, $sumPlusBase, $sumPlusVat);
echo sprintf("VAT Inclusive: Gross = %14.2f | Base = %14.2f | VAT = %14.2f\n", $sumInclGross, $sumInclBase, $sumInclVat);
echo sprintf("TOTAL POST-2024: Gross = %14.2f | Base = %14.2f | VAT = %14.2f\n", 
    $sumPlusGross + $sumInclGross, 
    $sumPlusBase + $sumInclBase, 
    $sumPlusVat + $sumInclVat
);
