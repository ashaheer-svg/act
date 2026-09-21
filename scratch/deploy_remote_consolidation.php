<?php
$ftpUser = "activeftp";
$ftpPass = "active***me";
$ftpBase = "ftp://active.lk:21/act";

$probeFile = "run_prod_consolidation.php";
$probeCode = <<<'PHP'
<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

$pdo->beginTransaction();
$log = [];

try {
    // 1. Remap payments
    $stmtPayInv = $pdo->prepare("
        UPDATE payments
        SET invoice_num = ('ASN' || SUBSTR(invoice_num, 3))
        WHERE invoice_num BETWEEN 'AS000001' AND 'AS000102'
          AND payment_date >= '2026-01-01'
    ");
    $stmtPayInv->execute();
    $remappedPayInv = $stmtPayInv->rowCount();
    $log['remapped_payments_invoice_num'] = $remappedPayInv;

    $stmtPayRef = $pdo->prepare("
        UPDATE payments
        SET reference_num = ('ASN' || SUBSTR(reference_num, 3))
        WHERE reference_num BETWEEN 'AS000001' AND 'AS000102'
          AND payment_date >= '2026-01-01'
    ");
    $stmtPayRef->execute();
    $remappedPayRef = $stmtPayRef->rowCount();
    $log['remapped_payments_reference_num'] = $remappedPayRef;

    // 2. Reconcile settlement dates for ASN invoices
    $stmtSettle = $pdo->prepare("
        UPDATE sales
        SET paid_date = (
            SELECT MIN(payment_date) FROM payments 
            WHERE payments.customer_name = sales.customer_name 
              AND (payments.invoice_num = sales.invoice_number OR payments.reference_num = sales.invoice_number)
        ),
        days_to_pay = (
            SELECT CAST((julianday(MIN(payment_date)) - julianday(sales.invoice_date)) AS INTEGER)
            FROM payments 
            WHERE payments.customer_name = sales.customer_name 
              AND (payments.invoice_num = sales.invoice_number OR payments.reference_num = sales.invoice_number)
        ),
        is_paid = 1
        WHERE invoice_number LIKE 'ASN%'
          AND EXISTS (
            SELECT 1 FROM payments 
            WHERE payments.customer_name = sales.customer_name 
              AND (payments.invoice_num = sales.invoice_number OR payments.reference_num = sales.invoice_number)
          )
    ");
    $stmtSettle->execute();
    $log['settled_sales_rows'] = $stmtSettle->rowCount();

    $pdo->exec("UPDATE sales SET days_to_pay = 0 WHERE days_to_pay < 0 AND invoice_number LIKE 'ASN%'");

    // 3. Delete stray 2026 AS invoices
    $findAs = $pdo->query("
        SELECT DISTINCT invoice_number 
        FROM sales 
        WHERE invoice_number BETWEEN 'AS000001' AND 'AS000102'
          AND invoice_date >= '2026-01-01'
    ")->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($findAs)) {
        $inClause = implode("','", $findAs);
        $pdo->exec("DELETE FROM hardware_assets WHERE invoice_number IN ('$inClause')");
        $pdo->exec("DELETE FROM software_subscriptions WHERE invoice_number IN ('$inClause')");
        $pdo->exec("DELETE FROM invoice_items WHERE invoice_number IN ('$inClause') AND invoice_date >= '2026-01-01'");
        $deletedSales = $pdo->exec("DELETE FROM sales WHERE invoice_number IN ('$inClause') AND invoice_date >= '2026-01-01'");
        $log['deleted_stray_2026_as_sales'] = $deletedSales;
    } else {
        $log['deleted_stray_2026_as_sales'] = 0;
    }

    // 4. Retire obsolete tax rule
    $retired = $pdo->exec("
        DELETE FROM tax_rules 
        WHERE invoice_range_start = 'AS000001' 
          AND invoice_range_end = 'AS000102' 
          AND effective_from >= '2026-01-01'
    ");
    $log['retired_tax_rules'] = $retired;

    $pdo->commit();
    $log['status'] = 'SUCCESS';

} catch (Exception $e) {
    $pdo->rollBack();
    $log['status'] = 'ERROR';
    $log['error'] = $e->getMessage();
}

// 5. Recalculate historical VAT
$log['recalculated_vat_rows'] = $db->recalculateHistoricalVat();

// 6. Verification
$log['as_2026_count'] = (int)$pdo->query("
    SELECT COUNT(DISTINCT invoice_number) 
    FROM sales 
    WHERE invoice_number BETWEEN 'AS000001' AND 'AS000102' 
      AND invoice_date >= '2026-01-01'
")->fetchColumn();

$asnStats = $pdo->query("
    SELECT 
        COUNT(DISTINCT invoice_number) as total_asn,
        COUNT(DISTINCT CASE WHEN paid_date IS NOT NULL AND paid_date != '' THEN invoice_number END) as paid_asn,
        COUNT(DISTINCT CASE WHEN paid_date IS NULL OR paid_date = '' THEN invoice_number END) as unpaid_asn,
        ROUND(SUM(total_amount), 2) as gross_total
    FROM sales 
    WHERE invoice_number LIKE 'ASN%'
")->fetch(PDO::FETCH_ASSOC);

$log['asn_stats'] = $asnStats;

header('Content-Type: application/json');
echo json_encode($log, JSON_PRETTY_PRINT);
PHP;

file_put_contents(__DIR__ . "/probe_consolidation_temp.php", $probeCode);
exec("curl.exe --ftp-ssl-control -s -S -k --user \"$ftpUser:$ftpPass\" -T \"" . __DIR__ . "/probe_consolidation_temp.php\" \"$ftpBase/$probeFile\" 2>&1");

$ch = curl_init("https://act.active.lk/$probeFile");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
$resp = curl_exec($ch);
unset($ch);

echo "Production Consolidation Result:\n$resp\n";

exec("curl.exe --ftp-ssl-control -s -S -k --user \"$ftpUser:$ftpPass\" -Q \"DELE /act/$probeFile\" \"$ftpBase/\" 2>&1");
@unlink(__DIR__ . "/probe_consolidation_temp.php");
