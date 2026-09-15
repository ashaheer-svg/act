<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

echo "=== EXECUTING WRITE-OFF: EITS + OLD QB INVOICES ===\n";

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

$pdo->beginTransaction();

try {
    // 1. Check before counts
    $beforeSales = $pdo->query("
        SELECT 
            COUNT(DISTINCT invoice_number) as inv_count,
            COUNT(*) as line_count,
            ROUND(SUM(total_amount), 2) as total_gross
        FROM sales
        WHERE (paid_date IS NULL OR paid_date = '')
          AND (customer_name = 'EITS' OR invoice_date < '2026-01-01')
          AND total_amount > 0
    ")->fetch(PDO::FETCH_ASSOC);

    echo "Found {$beforeSales['inv_count']} invoices ({$beforeSales['line_count']} lines) totaling LKR {$beforeSales['total_gross']} to write off.\n";

    // 2. Mark sales lines as paid
    $stmt = $pdo->prepare("
        UPDATE sales 
        SET paid_date = invoice_date,
            days_to_pay = 30
        WHERE (paid_date IS NULL OR paid_date = '')
          AND (customer_name = 'EITS' OR invoice_date < '2026-01-01')
    ");
    $stmt->execute();
    $affectedLines = $stmt->rowCount();
    echo "Updated $affectedLines sales rows with paid_date = invoice_date, days_to_pay = 30.\n";

    // 3. Reset customer_profiles balances for EITS
    $pdo->exec("
        UPDATE customer_profiles 
        SET total_balance = 0, current_balance = 0 
        WHERE customer_name = 'EITS'
    ");
    echo "Reset EITS profile balance to 0 in customer_profiles.\n";

    $pdo->commit();
    echo "Transaction committed successfully.\n\n";

    // 4. Verify post-update state
    $afterUnpaid = $pdo->query("
        SELECT 
            COUNT(DISTINCT invoice_number) as inv_count,
            COUNT(*) as line_count,
            ROUND(SUM(total_amount), 2) as total_gross,
            MIN(invoice_date) as min_date,
            MAX(invoice_date) as max_date
        FROM sales
        WHERE (paid_date IS NULL OR paid_date = '')
          AND total_amount > 0
    ")->fetch(PDO::FETCH_ASSOC);

    echo "=== POST WRITE-OFF UNPAID INVOICES IN DB ===\n";
    echo "Remaining Unpaid Invoices: {$afterUnpaid['inv_count']}\n";
    echo "Remaining Sales Lines:     {$afterUnpaid['line_count']}\n";
    echo "Remaining Total Gross:     LKR " . number_format((float)$afterUnpaid['total_gross'], 2) . "\n";
    echo "Date Range:                {$afterUnpaid['min_date']} to {$afterUnpaid['max_date']}\n";

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
