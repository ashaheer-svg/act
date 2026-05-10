<?php
require_once 'config.php';
require_once 'classes/Database.php';

$db = new Database(DATABASE_PATH);
$results = $db->fetchAll("SELECT customer_name, invoice_number, total_amount, paid_date, days_to_pay FROM sales WHERE paid_date IS NOT NULL LIMIT 10");

echo "Settled Invoices Check:\n";
echo "----------------------\n";
if (empty($results)) {
    echo "No settled invoices found yet.\n";
} else {
    foreach ($results as $row) {
        printf("%-30s | %-10s | %10.2f | %-10s | %d days\n", 
            $row['customer_name'], 
            $row['invoice_number'], 
            $row['total_amount'], 
            $row['paid_date'], 
            $row['days_to_pay']
        );
    }
}
?>
