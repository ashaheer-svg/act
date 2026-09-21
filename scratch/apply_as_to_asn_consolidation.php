<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

echo "=== CONSOLIDATION: 2026 AS000001-AS000102 TO ASN RANGE & PAYMENT REMAPPING ===\n\n";

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

// Backup local DB if running locally
if (file_exists(DATABASE_PATH)) {
    $backupPath = dirname(DATABASE_PATH) . '/sales_bi_backup_' . date('Ymd_His') . '.db';
    @copy(DATABASE_PATH, $backupPath);
    echo "Local database backup created: $backupPath\n";
}

$pdo->beginTransaction();

try {
    // 1. Remap any payments referencing 2026 AS000001 - AS000102 to ASN
    echo "\n1. Remapping payments from AS to ASN...\n";
    $stmtPayInv = $pdo->prepare("
        UPDATE payments
        SET invoice_num = ('ASN' || SUBSTR(invoice_num, 3))
        WHERE invoice_num BETWEEN 'AS000001' AND 'AS000102'
          AND payment_date >= '2026-01-01'
    ");
    $stmtPayInv->execute();
    $remappedPayInv = $stmtPayInv->rowCount();
    echo "   Remapped payments (invoice_num): $remappedPayInv\n";

    $stmtPayRef = $pdo->prepare("
        UPDATE payments
        SET reference_num = ('ASN' || SUBSTR(reference_num, 3))
        WHERE reference_num BETWEEN 'AS000001' AND 'AS000102'
          AND payment_date >= '2026-01-01'
    ");
    $stmtPayRef->execute();
    $remappedPayRef = $stmtPayRef->rowCount();
    echo "   Remapped payments (reference_num): $remappedPayRef\n";

    // 2. Transfer payment settlements to ASN invoices in sales
    echo "\n2. Reconciling settlement dates for ASN invoices in sales...\n";
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
    $settledRows = $stmtSettle->rowCount();
    echo "   Updated settlement on $settledRows sales rows for ASN invoices\n";

    // Fix any negative days_to_pay
    $pdo->exec("UPDATE sales SET days_to_pay = 0 WHERE days_to_pay < 0 AND invoice_number LIKE 'ASN%'");

    // 3. Delete any stray 2026 AS invoices (preserving historical 2009-2013 records)
    echo "\n3. Checking and removing stray 2026 AS invoices...\n";
    $findAs = $pdo->query("
        SELECT DISTINCT invoice_number 
        FROM sales 
        WHERE invoice_number BETWEEN 'AS000001' AND 'AS000102'
          AND invoice_date >= '2026-01-01'
    ")->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($findAs)) {
        echo "   Found " . count($findAs) . " stray 2026 AS invoices to purge.\n";
        $inClause = implode("','", $findAs);
        $pdo->exec("DELETE FROM hardware_assets WHERE invoice_number IN ('$inClause')");
        $pdo->exec("DELETE FROM software_subscriptions WHERE invoice_number IN ('$inClause')");
        $pdo->exec("DELETE FROM invoice_items WHERE invoice_number IN ('$inClause') AND invoice_date >= '2026-01-01'");
        $deletedSales = $pdo->exec("DELETE FROM sales WHERE invoice_number IN ('$inClause') AND invoice_date >= '2026-01-01'");
        echo "   Deleted $deletedSales sales rows for stray 2026 AS invoices.\n";
    } else {
        echo "   No stray 2026 AS invoices found in sales (already clean).\n";
    }

    // 4. Retire obsolete tax rule ID 17 (New Seq AS 18% VAT)
    echo "\n4. Retiring obsolete tax rule for 2026 AS sequence...\n";
    $retired = $pdo->exec("
        DELETE FROM tax_rules 
        WHERE invoice_range_start = 'AS000001' 
          AND invoice_range_end = 'AS000102' 
          AND effective_from >= '2026-01-01'
    ");
    echo "   Deleted $retired obsolete tax rules.\n";

    $pdo->commit();
    echo "\nTransaction committed successfully.\n";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

// 5. Recalculate historical VAT
echo "\n5. Recalculating historical VAT...\n";
$recalc = $db->recalculateHistoricalVat();
echo "   Recalculated VAT across $recalc sales records.\n";

// 6. Verification Queries
echo "\n=== VERIFICATION AUDIT ===\n";

$as2026Check = $pdo->query("
    SELECT COUNT(DISTINCT invoice_number) as c 
    FROM sales 
    WHERE invoice_number BETWEEN 'AS000001' AND 'AS000102' 
      AND invoice_date >= '2026-01-01'
")->fetch(PDO::FETCH_ASSOC)['c'] ?? 0;
echo "1. 2026 AS invoices remaining: $as2026Check (MUST BE 0)\n";

$legacyCheck = $pdo->query("
    SELECT COUNT(DISTINCT invoice_number) as c 
    FROM sales 
    WHERE invoice_number BETWEEN 'AS000001' AND 'AS000102' 
      AND invoice_date < '2026-01-01'
")->fetch(PDO::FETCH_ASSOC)['c'] ?? 0;
echo "2. Historical 2009-2013 AS invoices preserved: $legacyCheck (PRESERVED)\n";

$asnTotal = $pdo->query("
    SELECT 
        COUNT(DISTINCT invoice_number) as total_asn,
        COUNT(DISTINCT CASE WHEN paid_date IS NOT NULL AND paid_date != '' THEN invoice_number END) as paid_asn,
        COUNT(DISTINCT CASE WHEN paid_date IS NULL OR paid_date = '' THEN invoice_number END) as unpaid_asn,
        ROUND(SUM(total_amount), 2) as gross_total,
        ROUND(SUM(base_value), 2) as base_total,
        ROUND(SUM(vat_component), 2) as vat_total
    FROM sales 
    WHERE invoice_number LIKE 'ASN%'
")->fetch(PDO::FETCH_ASSOC);

echo "3. Total canonical ASN invoices: {$asnTotal['total_asn']}\n";
echo "   - Settled (Paid): {$asnTotal['paid_asn']}\n";
echo "   - Unpaid: {$asnTotal['unpaid_asn']}\n";
echo "   - Gross Billed: LKR " . number_format((float)$asnTotal['gross_total'], 2) . "\n";
echo "   - Net Base: LKR " . number_format((float)$asnTotal['base_total'], 2) . "\n";
echo "   - 18% VAT: LKR " . number_format((float)$asnTotal['vat_total'], 2) . "\n";

$taxRulesCheck = $pdo->query("SELECT id, tax_name, invoice_range_start, invoice_range_end, effective_from FROM tax_rules ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
echo "\n4. Active Tax Rules in DB:\n";
foreach ($taxRulesCheck as $tr) {
    echo "   [ID: {$tr['id']}] {$tr['tax_name']} ({$tr['invoice_range_start']} -> {$tr['invoice_range_end']}) from {$tr['effective_from']}\n";
}

echo "\nConsolidation complete!\n";
