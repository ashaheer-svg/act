<?php
require_once 'config.php';
$db = new PDO('sqlite:data/sales_bi.db');

$totalRows = $db->query("SELECT COUNT(*) FROM sales")->fetchColumn();
$distinctInvoices = $db->query("SELECT COUNT(DISTINCT invoice_number) FROM sales")->fetchColumn();
$dateRange = $db->query("SELECT MIN(invoice_date) || ' to ' || MAX(invoice_date) FROM sales")->fetchColumn();
$zeroRows = $db->query("SELECT COUNT(*) FROM sales WHERE total_amount = 0")->fetchColumn();
$nonzeroRows = $db->query("SELECT COUNT(*) FROM sales WHERE total_amount > 0")->fetchColumn();

// Check sn patterns in description
$snRows = $db->query("SELECT COUNT(*) FROM sales WHERE item_description LIKE '%S/N%' OR item_description LIKE '%SN%' OR item_description LIKE '%Serial%'")->fetchColumn();
$warrantyRows = $db->query("SELECT COUNT(*) FROM sales WHERE item_description LIKE '%Warranty%' OR item_description LIKE '%Expiry%'")->fetchColumn();
$licenseRows = $db->query("SELECT COUNT(*) FROM sales WHERE item_description LIKE '%License%' OR item_description LIKE '%Subscription%' OR item_description LIKE '%Acronis%' OR item_description LIKE '%Eset%'")->fetchColumn();

echo "Database Summary:\n";
echo "  Total sales rows:        $totalRows\n";
echo "  Distinct invoices:       $distinctInvoices\n";
echo "  Date range:              $dateRange\n";
echo "  Non-zero commercial rows: $nonzeroRows\n";
echo "  Zero-value/metadata rows: $zeroRows\n";
echo "  Rows mentioning S/N:     $snRows\n";
echo "  Rows mentioning Warranty: $warrantyRows\n";
echo "  Rows mentioning License: $licenseRows\n";
