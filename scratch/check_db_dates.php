<?php
echo "=== Checking data/sales_bi.db ===\n";
$db = new PDO('sqlite:data/sales_bi.db');
$salesDates = $db->query("SELECT MIN(invoice_date) as min_d, MAX(invoice_date) as max_d, COUNT(*) as cnt, COUNT(DISTINCT invoice_number) as inv_cnt FROM sales")->fetch(PDO::FETCH_ASSOC);
print_r($salesDates);

echo "\n=== Checking backups/remote_sales_bi_before_update.db ===\n";
if (file_exists('backups/remote_sales_bi_before_update.db')) {
    $dbBackup = new PDO('sqlite:backups/remote_sales_bi_before_update.db');
    $backupDates = $dbBackup->query("SELECT MIN(invoice_date) as min_d, MAX(invoice_date) as max_d, COUNT(*) as cnt, COUNT(DISTINCT invoice_number) as inv_cnt FROM sales")->fetch(PDO::FETCH_ASSOC);
    print_r($backupDates);
}
