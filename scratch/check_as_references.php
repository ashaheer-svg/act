<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

echo "=== CHECKING PAYMENTS AND RELATED TABLES FOR AS000xxx ===\n\n";

$asInvs = $pdo->query("
    SELECT DISTINCT s.invoice_number, 'ASN' || SUBSTR(s.invoice_number, 3) as asn_inv
    FROM sales s
    WHERE s.invoice_number LIKE 'AS000%'
      AND strftime('%Y', s.invoice_date) = '2026'
      AND EXISTS (SELECT 1 FROM sales s2 WHERE s2.invoice_number = ('ASN' || SUBSTR(s.invoice_number, 3)))
")->fetchAll(PDO::FETCH_ASSOC);

echo "Total matching duplicate pairs: " . count($asInvs) . "\n";

$payCount = 0;
$itemsCount = 0;
$hwCount = 0;
$subCount = 0;

foreach ($asInvs as $pair) {
    $as = $pair['invoice_number'];
    $asn = $pair['asn_inv'];
    
    $p = $pdo->query("SELECT COUNT(*) FROM payments WHERE invoice_num = '$as'")->fetchColumn();
    $payCount += $p;
    
    $it = $pdo->query("SELECT COUNT(*) FROM invoice_items WHERE invoice_number = '$as'")->fetchColumn();
    $itemsCount += $it;
    
    $hw = $pdo->query("SELECT COUNT(*) FROM hardware_assets WHERE invoice_number = '$as'")->fetchColumn();
    $hwCount += $hw;
    
    $sub = $pdo->query("SELECT COUNT(*) FROM software_subscriptions WHERE invoice_number = '$as'")->fetchColumn();
    $subCount += $sub;
}

echo "References to AS000xxx:\n";
echo "  Payments: $payCount\n";
echo "  Invoice Items: $itemsCount\n";
echo "  Hardware Assets: $hwCount\n";
echo "  Software Subscriptions: $subCount\n";
