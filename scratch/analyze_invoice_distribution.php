<?php
require_once 'config.php';
$db = new PDO('sqlite:data/sales_bi.db');

$invoices = $db->query("
    SELECT 
        invoice_number, 
        COUNT(*) as total_lines,
        SUM(CASE WHEN total_amount > 0 THEN 1 ELSE 0 END) as commercial_lines,
        SUM(CASE WHEN total_amount = 0 THEN 1 ELSE 0 END) as zero_lines,
        GROUP_CONCAT(item_description, ' ||| ') as full_text
    FROM sales
    GROUP BY invoice_number
")->fetchAll(PDO::FETCH_ASSOC);

$totalInv = count($invoices);
$withSN = 0;
$withWarranty = 0;
$withSoftware = 0;
$hardwareKeywords = ['synology', 'nas', 'hdd', 'hard drive', 'seagate', 'toshiba', 'western digital', 'wd', 'qnap', 'diskstation', 'rackstation', 'ssd', 'ironwolf', 'barracuda', 'skyhawk'];
$softwareKeywords = ['license', 'licence', 'subscription', 'acronis', 'eset', 'endpoint', 'antivirus', 'mailstore', 'microsoft', 'office 365', 'saas'];

$hardwareInvoices = 0;
$softwareInvoices = 0;
$bothInvoices = 0;
$neitherInvoices = 0;

foreach ($invoices as $inv) {
    $text = strtolower($inv['full_text']);
    $hasHw = false;
    foreach ($hardwareKeywords as $hwk) {
        if (strpos($text, $hwk) !== false) {
            $hasHw = true;
            break;
        }
    }
    $hasSw = false;
    foreach ($softwareKeywords as $swk) {
        if (strpos($text, $swk) !== false) {
            $hasSw = true;
            break;
        }
    }

    if (strpos($text, 's/n') !== false || strpos($text, 'sn ') !== false || strpos($text, 'serial') !== false) {
        $withSN++;
    }
    if (strpos($text, 'warranty') !== false || strpos($text, 'expiry') !== false) {
        $withWarranty++;
    }

    if ($hasHw && $hasSw) $bothInvoices++;
    elseif ($hasHw) $hardwareInvoices++;
    elseif ($hasSw) $softwareInvoices++;
    else $neitherInvoices++;
}

echo "Total Invoices Analyzed: $totalInv\n";
echo "Invoices with S/N patterns:      $withSN (" . round($withSN/$totalInv*100, 1) . "%)\n";
echo "Invoices with Warranty patterns: $withWarranty (" . round($withWarranty/$totalInv*100, 1) . "%)\n";
echo "Hardware-focused Invoices:       $hardwareInvoices (" . round($hardwareInvoices/$totalInv*100, 1) . "%)\n";
echo "Software/SaaS-focused Invoices:  $softwareInvoices (" . round($softwareInvoices/$totalInv*100, 1) . "%)\n";
echo "Invoices with Both HW and SW:    $bothInvoices (" . round($bothInvoices/$totalInv*100, 1) . "%)\n";
echo "Other (Services/General):        $neitherInvoices (" . round($neitherInvoices/$totalInv*100, 1) . "%)\n";
