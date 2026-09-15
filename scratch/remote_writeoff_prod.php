<?php
/**
 * Remote Production Write-off Runner
 * Self-executing and self-deleting script for production database update.
 */
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

header('Content-Type: application/json');

$dbPath = __DIR__ . '/data/sales_bi.db';
if (!file_exists($dbPath)) {
    echo json_encode(['status' => 'error', 'message' => 'Database file not found at ' . $dbPath]);
    exit;
}

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->beginTransaction();

    // 1. Before stats
    $before = $pdo->query("
        SELECT 
            COUNT(DISTINCT invoice_number) as inv_count,
            COUNT(*) as line_count,
            ROUND(SUM(total_amount), 2) as total_gross
        FROM sales
        WHERE (paid_date IS NULL OR paid_date = '')
          AND (customer_name = 'EITS' OR invoice_date < '2026-01-01')
          AND total_amount > 0
    ")->fetch(PDO::FETCH_ASSOC);

    // 2. Mark as paid
    $stmt = $pdo->prepare("
        UPDATE sales 
        SET paid_date = invoice_date,
            days_to_pay = 30
        WHERE (paid_date IS NULL OR paid_date = '')
          AND (customer_name = 'EITS' OR invoice_date < '2026-01-01')
    ");
    $stmt->execute();
    $affectedLines = $stmt->rowCount();

    // 3. Reset customer profile
    $pdo->exec("
        UPDATE customer_profiles 
        SET total_balance = 0, current_balance = 0 
        WHERE customer_name = 'EITS'
    ");

    $pdo->commit();

    // 4. After stats
    $after = $pdo->query("
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

    echo json_encode([
        'status' => 'success',
        'written_off' => [
            'invoices_count' => (int)$before['inv_count'],
            'lines_count' => (int)$before['line_count'],
            'total_gross' => (float)$before['total_gross'],
            'affected_rows' => $affectedLines
        ],
        'remaining_unpaid' => [
            'invoices_count' => (int)$after['inv_count'],
            'lines_count' => (int)$after['line_count'],
            'total_gross' => (float)$after['total_gross'],
            'min_date' => $after['min_date'],
            'max_date' => $after['max_date']
        ]
    ], JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

// Self-delete
@unlink(__FILE__);
