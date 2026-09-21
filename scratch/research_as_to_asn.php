<?php
require 'config.php';
require 'classes/Database.php';

$db = new Database(DATABASE_PATH);

echo "=== 1. 2026 INVOICES IN AS000001 -> AS000102 RANGE ===\n";
$as2026 = $db->fetchAll("
    SELECT 
        invoice_number, 
        invoice_date, 
        customer_name, 
        COUNT(*) as line_count,
        ROUND(SUM(total_amount), 2) as gross_total,
        MAX(paid_date) as paid_date,
        MAX(days_to_pay) as days_to_pay
    FROM sales
    WHERE invoice_date >= '2026-01-01'
      AND invoice_number BETWEEN 'AS000001' AND 'AS000102'
    GROUP BY invoice_number
    ORDER BY invoice_number ASC
");
echo "Count of 2026 AS invoices found: " . count($as2026) . "\n";
foreach (array_slice($as2026, 0, 5) as $r) {
    echo "  {$r['invoice_number']} | Date: {$r['invoice_date']} | Cust: {$r['customer_name']} | Lines: {$r['line_count']} | Gross: {$r['gross_total']} | Paid: {$r['paid_date']}\n";
}
if (count($as2026) > 5) {
    echo "  ... and " . (count($as2026) - 5) . " more.\n";
}

echo "\n=== 2. COMPARE WITH CORRESPONDING ASN000xxx INVOICES ===\n";
$matched = 0;
$paymentsToTransfer = [];
foreach ($as2026 as $as) {
    $asnNumber = 'ASN' . substr($as['invoice_number'], 2); // e.g. AS000001 -> ASN000001
    $asn = $db->fetch("
        SELECT 
            invoice_number, 
            invoice_date, 
            customer_name, 
            COUNT(*) as line_count,
            ROUND(SUM(total_amount), 2) as gross_total,
            MAX(paid_date) as paid_date,
            MAX(days_to_pay) as days_to_pay
        FROM sales
        WHERE invoice_number = ?
        GROUP BY invoice_number
    ", [$asnNumber]);

    if ($asn) {
        $matched++;
        $hasAsPayment = !empty($as['paid_date']);
        $hasAsnPayment = !empty($asn['paid_date']);
        if ($hasAsPayment && !$hasAsnPayment) {
            $paymentsToTransfer[] = [
                'as_num' => $as['invoice_number'],
                'asn_num' => $asnNumber,
                'cust' => $as['customer_name'],
                'paid_date' => $as['paid_date'],
                'days_to_pay' => $as['days_to_pay'],
                'as_gross' => $as['gross_total'],
                'asn_gross' => $asn['gross_total']
            ];
        }
    } else {
        echo "  WARNING: No matching {$asnNumber} found for {$as['invoice_number']} ({$as['customer_name']})!\n";
    }
}
echo "Total matched ASN invoices: $matched / " . count($as2026) . "\n";
echo "Invoices where AS has payment recorded but ASN does NOT: " . count($paymentsToTransfer) . "\n";
foreach ($paymentsToTransfer as $pt) {
    echo "  {$pt['as_num']} -> {$pt['asn_num']} | Cust: {$pt['cust']} | Paid Date: {$pt['paid_date']} | Days: {$pt['days_to_pay']} | AS Gross: {$pt['as_gross']} | ASN Gross: {$pt['asn_gross']}\n";
}

echo "\n=== 3. CHECK OTHER TABLES FOR 2026 AS000001-AS000102 REFERENCES ===\n";
$tables = ['invoice_items', 'hardware_assets', 'software_subscriptions', 'payments'];
foreach ($tables as $t) {
    $exists = $db->fetch("SELECT name FROM sqlite_master WHERE type='table' AND name=?", [$t]);
    if ($exists) {
        // Find if any rows match AS000001-AS000102 with 2026 dates (or just matching invoice_number)
        $col = ($t === 'payments') ? 'invoice_number' : 'invoice_number';
        $cnt = $db->fetch("SELECT COUNT(*) as c FROM $t WHERE $col BETWEEN 'AS000001' AND 'AS000102'")['c'] ?? 0;
        echo "Table '$t': $cnt rows with invoice_number between AS000001 and AS000102\n";
    } else {
        echo "Table '$t' does not exist.\n";
    }
}

echo "\n=== 4. CHECK LEGACY 2009-2013 AS000001-AS004000 (MUST BE PROTECTED) ===\n";
$legacyCnt = $db->fetch("
    SELECT COUNT(DISTINCT invoice_number) as c 
    FROM sales 
    WHERE invoice_date < '2026-01-01' 
      AND invoice_number BETWEEN 'AS000001' AND 'AS000102'
")['c'] ?? 0;
echo "Legacy pre-2026 invoices with numbers AS000001-AS000102: $legacyCnt (Must NOT be touched!)\n";
