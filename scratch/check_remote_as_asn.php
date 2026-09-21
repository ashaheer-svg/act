<?php
$ftpUser = "activeftp";
$ftpPass = "active***me";
$ftpBase = "ftp://active.lk:21/act";

$probeFile = "probe_as_asn.php";
$probeCode = <<<'PHP'
<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes/Database.php';

$db = new Database(DATABASE_PATH);

// 1. Check 2026 AS000001 - AS000102 in sales
$as2026 = $db->fetchAll("
    SELECT 
        invoice_number, invoice_date, customer_name, 
        ROUND(SUM(total_amount), 2) as gross,
        MAX(paid_date) as paid_date, MAX(days_to_pay) as days_to_pay
    FROM sales
    WHERE invoice_date >= '2026-01-01' AND invoice_number BETWEEN 'AS000001' AND 'AS000102'
    GROUP BY invoice_number
    ORDER BY invoice_number ASC
");

// 2. Check ASN invoices in sales
$asn2026 = $db->fetchAll("
    SELECT 
        invoice_number, invoice_date, customer_name, 
        ROUND(SUM(total_amount), 2) as gross,
        MAX(paid_date) as paid_date, MAX(days_to_pay) as days_to_pay
    FROM sales
    WHERE invoice_number LIKE 'ASN%'
    GROUP BY invoice_number
    ORDER BY invoice_number ASC
");

// 3. Check payments table for AS000001-AS000102
$payAs = $db->fetchAll("
    SELECT * FROM payments 
    WHERE (invoice_num BETWEEN 'AS000001' AND 'AS000102') 
       OR (reference_num BETWEEN 'AS000001' AND 'AS000102')
");

// 4. Compare AS payments vs ASN payments
$asWithPayments = [];
foreach ($as2026 as $a) {
    if (!empty($a['paid_date'])) {
        $asWithPayments[] = $a;
    }
}

// Also check if any payments in payments table reference AS and what ASN has
header('Content-Type: application/json');
echo json_encode([
    'as_2026_count' => count($as2026),
    'as_sample' => array_slice($as2026, 0, 5),
    'asn_count' => count($asn2026),
    'asn_sample' => array_slice($asn2026, 0, 5),
    'payments_as_count' => count($payAs),
    'payments_as' => $payAs,
    'as_with_paid_date_count' => count($asWithPayments),
    'as_with_paid_date' => $asWithPayments
], JSON_PRETTY_PRINT);
PHP;

file_put_contents(__DIR__ . "/probe_as_asn_temp.php", $probeCode);
exec("curl.exe --ftp-ssl-control -s -S -k --user \"$ftpUser:$ftpPass\" -T \"" . __DIR__ . "/probe_as_asn_temp.php\" \"$ftpBase/$probeFile\" 2>&1");

$ch = curl_init("https://act.active.lk/$probeFile");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$resp = curl_exec($ch);
unset($ch);

echo "Remote Production Database State:\n$resp\n";

exec("curl.exe --ftp-ssl-control -s -S -k --user \"$ftpUser:$ftpPass\" -Q \"DELE /act/$probeFile\" \"$ftpBase/\" 2>&1");
@unlink(__DIR__ . "/probe_as_asn_temp.php");
