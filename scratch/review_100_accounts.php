<?php
require_once 'config.php';
$db = new PDO('sqlite:data/sales_bi.db');

echo "================================================================================\n";
echo "           REVIEW OF 100 SORTED INVOICES & CUSTOMER ACCOUNTS\n";
echo "================================================================================\n\n";

// 1. Overall Metrics
$totalSortedInvoices = $db->query("SELECT COUNT(DISTINCT invoice_number) FROM invoice_items")->fetchColumn();
$totalItems = $db->query("SELECT COUNT(*) FROM invoice_items")->fetchColumn();
$totalHardware = $db->query("SELECT COUNT(*) FROM hardware_assets")->fetchColumn();
$hardwareWithSerials = $db->query("SELECT COUNT(*) FROM hardware_assets WHERE serial_number IS NOT NULL AND serial_number != '' AND serial_number != 'UNASSIGNED'")->fetchColumn();
$activeWarranties = $db->query("SELECT COUNT(*) FROM hardware_assets WHERE warranty_status = 'ACTIVE'")->fetchColumn();
$expiredWarranties = $db->query("SELECT COUNT(*) FROM hardware_assets WHERE warranty_status = 'EXPIRED'")->fetchColumn();
$totalSubs = $db->query("SELECT COUNT(*) FROM software_subscriptions")->fetchColumn();

echo "KEY METRICS:\n";
echo "  • Sorted Invoices:         {$totalSortedInvoices}\n";
echo "  • Commercial Line Items:   {$totalItems}\n";
echo "  • Hardware Assets Tracked: {$totalHardware} ({$hardwareWithSerials} with discrete serial numbers)\n";
echo "  • Warranty Status:         {$activeWarranties} Active | {$expiredWarranties} Expired\n";
echo "  • Software & MAs Tracked:  {$totalSubs}\n\n";

// 2. Customer Accounts Summary
$accounts = $db->query("
    SELECT 
        s.customer_name,
        COUNT(DISTINCT s.invoice_number) as invoice_count,
        SUM(DISTINCT s.total_amount) as total_revenue,
        (SELECT COUNT(*) FROM hardware_assets ha WHERE ha.customer_name = s.customer_name) as hw_count,
        (SELECT COUNT(*) FROM software_subscriptions ss WHERE ss.customer_name = s.customer_name) as sub_count
    FROM sales s
    INNER JOIN invoice_items ii ON s.invoice_number = ii.invoice_number
    GROUP BY s.customer_name
    ORDER BY total_revenue DESC
")->fetchAll(PDO::FETCH_ASSOC);

echo "--------------------------------------------------------------------------------\n";
echo sprintf("%-40s | %-8s | %-15s | %-8s | %-8s\n", "Customer / Account Name", "Invoices", "Revenue (LKR)", "Hardware", "Subs/MAs");
echo "--------------------------------------------------------------------------------\n";

foreach ($accounts as $acc) {
    echo sprintf(
        "%-40s | %8d | %15s | %8d | %8d\n",
        substr($acc['customer_name'], 0, 40),
        $acc['invoice_count'],
        number_format($acc['total_revenue'], 2),
        $acc['hw_count'],
        $acc['sub_count']
    );
}

echo "\n================================================================================\n";
echo "           SOFTWARE SUBSCRIPTIONS & MAINTENANCE AGREEMENTS (MA)\n";
echo "================================================================================\n";

$subs = $db->query("
    SELECT invoice_number, customer_name, software_name, license_seats, period_start_date, period_end_date, renewal_opportunity_value, renewal_status
    FROM software_subscriptions
    ORDER BY period_end_date DESC
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($subs as $s) {
    echo sprintf(
        "[%s] %-30s | %-35s | Seats: %2d | %s to %s | LKR %10s | %s\n",
        $s['invoice_number'],
        substr($s['customer_name'], 0, 30),
        substr($s['software_name'], 0, 35),
        $s['license_seats'],
        $s['period_start_date'],
        $s['period_end_date'],
        number_format($s['renewal_opportunity_value'], 2),
        $s['renewal_status']
    );
}

echo "\n================================================================================\n";
echo "           SAMPLE OF HARDWARE ASSETS WITH DISCRETE SERIALS\n";
echo "================================================================================\n";

$hw = $db->query("
    SELECT invoice_number, customer_name, brand, model_sku, serial_number, warranty_months, warranty_expiry_date, warranty_status
    FROM hardware_assets
    WHERE serial_number != 'UNASSIGNED'
    ORDER BY invoice_number DESC
    LIMIT 25
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($hw as $h) {
    echo sprintf(
        "[%s] %-25s | %-8s %-18s | S/N: %-18s | %2d M | Exp: %s [%s]\n",
        $h['invoice_number'],
        substr($h['customer_name'], 0, 25),
        $h['brand'],
        substr($h['model_sku'], 0, 18),
        $h['serial_number'],
        $h['warranty_months'],
        $h['warranty_expiry_date'],
        $h['warranty_status']
    );
}

echo "\n================================================================================\n";
echo "           CATEGORIZATION BREAKDOWN (INVOICE ITEMS)\n";
echo "================================================================================\n";

$catBreakdown = $db->query("
    SELECT product_type, COUNT(*) as cnt, SUM(total_amount) as total_val
    FROM invoice_items
    GROUP BY product_type
    ORDER BY cnt DESC
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($catBreakdown as $cat) {
    echo sprintf(
        "  • %-15s: %4d line items | Total Amount: LKR %15s\n",
        $cat['product_type'],
        $cat['cnt'],
        number_format($cat['total_val'], 2)
    );
}
