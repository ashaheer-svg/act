<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Reports.php';

$db = new Database(DATABASE_PATH);
$reports = new Reports($db);
$res = $reports->getUnpaidInvoicesByCustomerReport(['aging_bracket' => 'all'], 1, 50);

echo "Total Debtors: " . $res['total'] . "\n";
echo "Grand Gross Due: LKR " . number_format($res['summary']['grand_gross_due'], 2) . "\n";
echo "Total Unpaid Invoices: " . $res['summary']['total_unpaid_invoices'] . "\n";
