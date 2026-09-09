<?php
require_once 'config.php';
$db = new PDO('sqlite:data/sales_bi.db');

echo "================================================================================\n";
echo "           GRAND TOTALS ACROSS ENTIRE SALES DATABASE\n";
echo "================================================================================\n\n";

$totSalesInvs = $db->query("SELECT COUNT(DISTINCT invoice_number) FROM sales WHERE total_amount > 0")->fetchColumn();
$totSortedInvs = $db->query("SELECT COUNT(DISTINCT invoice_number) FROM invoice_items")->fetchColumn();
$totItems = $db->query("SELECT COUNT(*) FROM invoice_items")->fetchColumn();
$totHw = $db->query("SELECT COUNT(*) FROM hardware_assets")->fetchColumn();
$hwSerials = $db->query("SELECT COUNT(*) FROM hardware_assets WHERE serial_number IS NOT NULL AND serial_number != '' AND serial_number != 'UNASSIGNED'")->fetchColumn();
$activeWarranties = $db->query("SELECT COUNT(*) FROM hardware_assets WHERE warranty_status = 'ACTIVE'")->fetchColumn();
$expiring30 = $db->query("SELECT COUNT(*) FROM hardware_assets WHERE warranty_status = 'EXPIRING_30D'")->fetchColumn();
$expiring60 = $db->query("SELECT COUNT(*) FROM hardware_assets WHERE warranty_status = 'EXPIRING_60D'")->fetchColumn();
$expiring90 = $db->query("SELECT COUNT(*) FROM hardware_assets WHERE warranty_status = 'EXPIRING_90D'")->fetchColumn();
$expiredHw = $db->query("SELECT COUNT(*) FROM hardware_assets WHERE warranty_status = 'EXPIRED'")->fetchColumn();

$totSubs = $db->query("SELECT COUNT(*) FROM software_subscriptions")->fetchColumn();
$activeSubs = $db->query("SELECT COUNT(*) FROM software_subscriptions WHERE renewal_status = 'ACTIVE' OR renewal_status = 'UPCOMING'")->fetchColumn();
$expiredSubs = $db->query("SELECT COUNT(*) FROM software_subscriptions WHERE renewal_status = 'EXPIRED'")->fetchColumn();

$totRevenue = $db->query("SELECT SUM(total_amount) FROM invoice_items")->fetchColumn();

echo "DATABASE PROGRESS:\n";
echo "  • Total Invoices in Sales Ledger:    " . number_format($totSalesInvs) . "\n";
echo "  • Total Invoices Normalized:         " . number_format($totSortedInvs) . " (" . round(($totSortedInvs / max(1, $totSalesInvs)) * 100, 1) . "%)\n";
echo "  • Total Commercial Items:            " . number_format($totItems) . "\n";
echo "  • Total Normalized Billed Value:     LKR " . number_format($totRevenue, 2) . "\n\n";

echo "HARDWARE & WARRANTY ASSET REGISTRY:\n";
echo "  • Total Hardware Units:              " . number_format($totHw) . "\n";
echo "  • Units with Discrete Serials:       " . number_format($hwSerials) . " (" . round(($hwSerials / max(1, $totHw)) * 100, 1) . "%)\n";
echo "  • Active Warranties:                 " . number_format($activeWarranties) . "\n";
echo "  • Expiring in 30 Days:               " . number_format($expiring30) . "\n";
echo "  • Expiring in 60 Days:               " . number_format($expiring60) . "\n";
echo "  • Expiring in 90 Days:               " . number_format($expiring90) . "\n";
echo "  • Expired Units:                     " . number_format($expiredHw) . "\n\n";

echo "SOFTWARE & MAINTENANCE AGREEMENT REGISTRY:\n";
echo "  • Total Subscriptions & MAs:         " . number_format($totSubs) . "\n";
echo "  • Active / Upcoming Renewals:        " . number_format($activeSubs) . "\n";
echo "  • Expired / Past Period Contracts:   " . number_format($expiredSubs) . "\n\n";

echo "CATEGORY BREAKDOWN:\n";
$cats = $db->query("
    SELECT product_type, COUNT(*) as cnt, SUM(total_amount) as total_val
    FROM invoice_items
    GROUP BY product_type
    ORDER BY cnt DESC
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($cats as $c) {
    echo sprintf("  • %-15s: %5s items | LKR %16s\n", $c['product_type'], number_format($c['cnt']), number_format($c['total_val'], 2));
}

echo "\nTOP HARDWARE BRANDS TRACKED:\n";
$brands = $db->query("
    SELECT brand, COUNT(*) as cnt
    FROM hardware_assets
    WHERE brand IS NOT NULL AND brand != ''
    GROUP BY brand
    ORDER BY cnt DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($brands as $b) {
    echo sprintf("  • %-20s: %5s units\n", $b['brand'], number_format($b['cnt']));
}

echo "\nTOP SOFTWARE & SERVICE CATEGORIES:\n";
$subTypes = $db->query("
    SELECT 
        CASE 
            WHEN software_name LIKE '%Acronis%' THEN 'Acronis Backup / Cyber Protect'
            WHEN software_name LIKE '%Maintenance%' OR software_name LIKE '%MA%' OR software_name LIKE '%AMC%' THEN 'Maintenance Agreements (MA)'
            WHEN software_name LIKE '%ESET%' THEN 'ESET Antivirus / Security'
            WHEN software_name LIKE '%Synalyze%' THEN 'Synalyze It! Licenses'
            WHEN software_name LIKE '%MailStore%' THEN 'MailStore Archiving'
            ELSE 'Other Software / SaaS'
        END as cat,
        COUNT(*) as cnt,
        SUM(renewal_opportunity_value) as total_opp_val
    FROM software_subscriptions
    GROUP BY cat
    ORDER BY cnt DESC
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($subTypes as $st) {
    echo sprintf("  • %-35s: %4s contracts | Pipeline Value: LKR %14s\n", $st['cat'], number_format($st['cnt']), number_format($st['total_opp_val'], 2));
}
