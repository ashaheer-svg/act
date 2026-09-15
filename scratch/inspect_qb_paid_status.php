<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

echo "=== SALES BREAKDOWN BY YEAR (PAID vs UNPAID) ===\n";
$stmt = $pdo->query("
    SELECT 
        strftime('%Y', invoice_date) as yr,
        COUNT(DISTINCT invoice_number) as total_invoices,
        COUNT(*) as total_lines,
        COUNT(DISTINCT CASE WHEN (paid_date IS NULL OR paid_date = '') AND total_amount > 0 THEN invoice_number END) as unpaid_invoices,
        SUM(CASE WHEN (paid_date IS NULL OR paid_date = '') AND total_amount > 0 THEN total_amount ELSE 0 END) as unpaid_amount,
        COUNT(DISTINCT CASE WHEN (paid_date IS NOT NULL AND paid_date != '') THEN invoice_number END) as paid_invoices,
        SUM(CASE WHEN (paid_date IS NOT NULL AND paid_date != '') THEN total_amount ELSE 0 END) as paid_amount
    FROM sales
    GROUP BY strftime('%Y', invoice_date)
    ORDER BY yr ASC
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    printf(
        "Year: %s | Invoices: %5d | Unpaid Inv: %5d (LKR %14s) | Paid Inv: %5d (LKR %14s)\n",
        $r['yr'],
        $r['total_invoices'],
        $r['unpaid_invoices'],
        number_format($r['unpaid_amount'], 2),
        $r['paid_invoices'],
        number_format($r['paid_amount'], 2)
    );
}

echo "\n=== SOURCES / UPLOAD LOGS / IMPORT SCRIPT INFO ===\n";
try {
    $uploads = $pdo->query("SELECT * FROM uploads ORDER BY id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
    echo "Uploads table records: " . count($uploads) . "\n";
    foreach ($uploads as $u) {
        echo " - ID: {$u['id']}, File: {$u['filename']}, Date: {$u['uploaded_at']}, Rows: {$u['row_count']}\n";
    }
} catch (Exception $e) {
    echo "Uploads table error: " . $e->getMessage() . "\n";
}

// Check columns in sales table
echo "\n=== SALES TABLE COLUMNS ===\n";
$cols = $pdo->query("PRAGMA table_info(sales)")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    echo " - " . $c['name'] . " (" . $c['type'] . ")\n";
}
