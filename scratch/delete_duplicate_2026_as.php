<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

echo "=== DELETING DUPLICATE 2026 AS INVOICES THAT HAVE A CORRESPONDING ASN ===\n\n";

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

// Target invoices: 2026 AS invoices with corresponding ASN
$sql = "
    SELECT DISTINCT s.invoice_number, ('ASN' || SUBSTR(s.invoice_number, 3)) as asn_number
    FROM sales s
    WHERE s.invoice_number LIKE 'AS000%'
      AND strftime('%Y', s.invoice_date) = '2026'
      AND EXISTS (
          SELECT 1 FROM sales s2 
          WHERE s2.invoice_number = ('ASN' || SUBSTR(s.invoice_number, 3))
      )
    ORDER BY s.invoice_number ASC
";

$targetInvoices = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
echo "Found " . count($targetInvoices) . " duplicate 2026 AS invoices to delete:\n";
$invNumbers = array_column($targetInvoices, 'invoice_number');
echo "Range: " . reset($invNumbers) . " to " . end($invNumbers) . "\n\n";

$pdo->beginTransaction();

try {
    $placeholders = implode(',', array_fill(0, count($invNumbers), '?'));
    
    // 1. Delete from hardware_assets (child of invoice_items)
    $stmtHw = $pdo->prepare("DELETE FROM hardware_assets WHERE invoice_number IN ($placeholders)");
    $stmtHw->execute($invNumbers);
    $deletedHw = $stmtHw->rowCount();
    echo "1. Deleted from hardware_assets: $deletedHw rows\n";
    
    // 2. Delete from software_subscriptions (child of invoice_items)
    $stmtSub = $pdo->prepare("DELETE FROM software_subscriptions WHERE invoice_number IN ($placeholders)");
    $stmtSub->execute($invNumbers);
    $deletedSub = $stmtSub->rowCount();
    echo "2. Deleted from software_subscriptions: $deletedSub rows\n";
    
    // 3. Delete from invoice_items
    $stmtItems = $pdo->prepare("DELETE FROM invoice_items WHERE invoice_number IN ($placeholders)");
    $stmtItems->execute($invNumbers);
    $deletedItems = $stmtItems->rowCount();
    echo "3. Deleted from invoice_items: $deletedItems rows\n";
    
    // 4. Delete from sales
    $stmtSales = $pdo->prepare("DELETE FROM sales WHERE invoice_number IN ($placeholders)");
    $stmtSales->execute($invNumbers);
    $deletedSales = $stmtSales->rowCount();
    echo "4. Deleted from sales: $deletedSales rows\n";
    
    // 5. Delete duplicate payments referencing AS
    $stmtPay = $pdo->prepare("DELETE FROM payments WHERE invoice_num IN ($placeholders)");
    $stmtPay->execute($invNumbers);
    $deletedPay = $stmtPay->rowCount();
    echo "5. Deleted duplicate payments: $deletedPay rows\n";
    
    // Reconcile settlement dates
    $pdo->exec("
        UPDATE sales
        SET paid_date = (
            SELECT MIN(payment_date) FROM payments 
            WHERE payments.customer_name = sales.customer_name 
              AND (payments.invoice_num = sales.invoice_number OR payments.reference_num = sales.invoice_number)
        )
        WHERE paid_date IS NULL
          AND EXISTS (
            SELECT 1 FROM payments 
            WHERE payments.customer_name = sales.customer_name 
              AND (payments.invoice_num = sales.invoice_number OR payments.reference_num = sales.invoice_number)
          )
    ");
    
    $pdo->exec("
        UPDATE sales
        SET days_to_pay = CAST((julianday(paid_date) - julianday(invoice_date)) AS INTEGER)
        WHERE paid_date IS NOT NULL
    ");
    
    $pdo->commit();
    
    // Recalculate historical VAT
    echo "\n6. Recalculating historical VAT...\n";
    $db->recalculateHistoricalVat();
    
    echo "\n=== CLEANUP COMPLETED SUCCESSFULLY ===\n\n";
    
    // Summary of 2026 data
    $summary2026 = $pdo->query("
        SELECT 
            COUNT(DISTINCT invoice_number) as invs,
            COUNT(*) as lines,
            ROUND(SUM(base_value), 2) as base,
            ROUND(SUM(vat_component), 2) as vat,
            ROUND(SUM(total_amount), 2) as gross
        FROM sales
        WHERE strftime('%Y', invoice_date) = '2026'
    ")->fetch(PDO::FETCH_ASSOC);
    
    echo "Clean 2026 Stats:\n";
    print_r($summary2026);
    
    // Overall database stats
    $overall = $pdo->query("
        SELECT 
            MIN(invoice_date) as min_date,
            MAX(invoice_date) as max_date,
            COUNT(DISTINCT invoice_number) as total_invoices,
            COUNT(*) as total_lines,
            ROUND(SUM(total_amount), 2) as grand_gross
        FROM sales
    ")->fetch(PDO::FETCH_ASSOC);
    
    echo "\nConsolidated 2009-2026 Stats:\n";
    print_r($overall);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
