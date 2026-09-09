<?php
require_once 'config.php';
$db = new PDO('sqlite:data/sales_bi.db');

echo "================================================================================\n";
echo "           DETAILED AUDIT OF THE 100 CURRENTLY SORTED INVOICES\n";
echo "================================================================================\n\n";

// Invoices sorted list
$sortedInvoices = $db->query("SELECT DISTINCT invoice_number FROM invoice_items ORDER BY invoice_number ASC")->fetchAll(PDO::FETCH_COLUMN);

echo "Sorted Invoices Count: " . count($sortedInvoices) . " (From {$sortedInvoices[0]} to " . end($sortedInvoices) . ")\n\n";

// Group by Customer Account in these 100 invoices ONLY
$accounts = $db->query("
    SELECT 
        s.customer_name,
        COUNT(DISTINCT s.invoice_number) as invoice_count,
        SUM(DISTINCT s.total_amount) as total_revenue
    FROM sales s
    WHERE s.invoice_number IN (SELECT DISTINCT invoice_number FROM invoice_items)
      AND s.total_amount > 0
    GROUP BY s.customer_name
    ORDER BY total_revenue DESC
")->fetchAll(PDO::FETCH_ASSOC);

echo "Total Unique Customer Accounts: " . count($accounts) . "\n\n";

echo sprintf("%-42s | %-8s | %-16s | %-8s | %-8s\n", "Customer / Account Name", "Invoices", "Revenue (LKR)", "Hardware", "Subs/MAs");
echo str_repeat('-', 90) . "\n";

$totRev = 0;
$totHw = 0;
$totSubs = 0;

foreach ($accounts as $acc) {
    $cust = $acc['customer_name'];
    $hwCount = $db->query("SELECT COUNT(*) FROM hardware_assets WHERE customer_name = " . $db->quote($cust) . " AND invoice_number IN (SELECT DISTINCT invoice_number FROM invoice_items)")->fetchColumn();
    $subCount = $db->query("SELECT COUNT(*) FROM software_subscriptions WHERE customer_name = " . $db->quote($cust) . " AND invoice_number IN (SELECT DISTINCT invoice_number FROM invoice_items)")->fetchColumn();
    
    $totRev += $acc['total_revenue'];
    $totHw += $hwCount;
    $totSubs += $subCount;
    
    echo sprintf(
        "%-42s | %8d | %16s | %8d | %8d\n",
        substr($cust, 0, 42),
        $acc['invoice_count'],
        number_format($acc['total_revenue'], 2),
        $hwCount,
        $subCount
    );
}

echo str_repeat('-', 90) . "\n";
echo sprintf("%-42s | %8d | %16s | %8d | %8d\n\n", "TOTAL (100 INVOICES)", count($sortedInvoices), number_format($totRev, 2), $totHw, $totSubs);

// Detailed Breakdown of Top 10 Accounts
echo "================================================================================\n";
echo "           DEEP DIVE: TOP 10 ACCOUNTS IN SORTED DATASET\n";
echo "================================================================================\n";

foreach (array_slice($accounts, 0, 10) as $acc) {
    $cust = $acc['customer_name'];
    echo "\n>>> ACCOUNT: $cust\n";
    echo "    Total Billed: LKR " . number_format($acc['total_revenue'], 2) . " across {$acc['invoice_count']} invoice(s)\n";
    
    // Invoices
    $invs = $db->query("
        SELECT DISTINCT invoice_number, invoice_date, total_amount
        FROM sales
        WHERE customer_name = " . $db->quote($cust) . "
          AND invoice_number IN (SELECT DISTINCT invoice_number FROM invoice_items)
          AND total_amount > 0
        ORDER BY invoice_number ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "    Invoices:\n";
    foreach ($invs as $inv) {
        echo "      • Inv #{$inv['invoice_number']} ({$inv['invoice_date']}): LKR " . number_format($inv['total_amount'], 2) . "\n";
    }
    
    // Hardware Assets
    $hw = $db->query("
        SELECT invoice_number, brand, model_sku, serial_number, warranty_months, warranty_expiry_date, warranty_status
        FROM hardware_assets
        WHERE customer_name = " . $db->quote($cust) . "
          AND invoice_number IN (SELECT DISTINCT invoice_number FROM invoice_items)
        ORDER BY invoice_number ASC, id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($hw)) {
        echo "    Hardware Assets Tracked (" . count($hw) . "):\n";
        foreach (array_slice($hw, 0, 6) as $h) {
            echo "      - [{$h['invoice_number']}] {$h['brand']} {$h['model_sku']} | S/N: {$h['serial_number']} | Exp: {$h['warranty_expiry_date']} ({$h['warranty_months']}M) [{$h['warranty_status']}]\n";
        }
        if (count($hw) > 6) {
            echo "      ... and " . (count($hw) - 6) . " more units.\n";
        }
    }
    
    // Subscriptions
    $subs = $db->query("
        SELECT invoice_number, software_name, license_seats, period_start_date, period_end_date, renewal_opportunity_value, renewal_status
        FROM software_subscriptions
        WHERE customer_name = " . $db->quote($cust) . "
          AND invoice_number IN (SELECT DISTINCT invoice_number FROM invoice_items)
        ORDER BY invoice_number ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($subs)) {
        echo "    Software & Maintenance Agreements (" . count($subs) . "):\n";
        foreach ($subs as $s) {
            echo "      - [{$s['invoice_number']}] {$s['software_name']} | Seats: {$s['license_seats']} | {$s['period_start_date']} to {$s['period_end_date']} | OppVal: LKR " . number_format($s['renewal_opportunity_value'], 2) . " [{$s['renewal_status']}]\n";
        }
    }
}
