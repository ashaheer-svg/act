<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

$asInvs = $pdo->query("
    SELECT DISTINCT s.invoice_number, 'ASN' || SUBSTR(s.invoice_number, 3) as asn_inv
    FROM sales s
    WHERE s.invoice_number LIKE 'AS000%'
      AND strftime('%Y', s.invoice_date) = '2026'
      AND EXISTS (SELECT 1 FROM sales s2 WHERE s2.invoice_number = ('ASN' || SUBSTR(s.invoice_number, 3)))
")->fetchAll(PDO::FETCH_ASSOC);

$migratedPayments = 0;
$alreadyExists = 0;

foreach ($asInvs as $pair) {
    $as = $pair['invoice_number'];
    $asn = $pair['asn_inv'];
    
    $asPays = $pdo->query("SELECT * FROM payments WHERE invoice_num = '$as'")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($asPays as $p) {
        $check = $pdo->query("SELECT COUNT(*) FROM payments WHERE invoice_num = '$asn' AND amount = {$p['amount']}")->fetchColumn();
        if ($check > 0) {
            $alreadyExists++;
        } else {
            $migratedPayments++;
        }
    }
}

echo "Payments already on ASN: $alreadyExists\n";
echo "Payments on AS needing re-link to ASN: $migratedPayments\n";
