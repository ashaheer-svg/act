<?php
require_once 'config.php';
require_once 'classes/Database.php';

$f = 'app/binary/exports/qb_export_2026-09-16_143318.json';
$raw = file_get_contents($f);
$raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
$json = json_decode($raw, true);

$invoices = $json['invoices'] ?? [];
$cms = $json['credit_memos'] ?? [];

$db = new Database(DATABASE_PATH);

echo "Loaded " . count($invoices) . " invoice lines and " . count($cms) . " credit memo lines.\n\n";

// Broad search for ANY line item that could be a VAT line item:
// Check Item column, Description column, and Amount.
$vatCandidateLines = [];
$vatLinesByInvoice = [];
$invoicesByTaxRule = [];

foreach ($invoices as $idx => $inv) {
    $num = trim($inv['Num'] ?? '');
    $date = trim($inv['Date'] ?? '');
    $item = trim($inv['Item'] ?? '');
    $desc = trim($inv['Description'] ?? '');
    $amt = floatval($inv['Amount'] ?? 0);
    $salesTaxTotal = floatval($inv['sales_tax_total'] ?? 0);
    $salesTaxItem = trim($inv['sales_tax_item'] ?? '');
    $salesTaxRate = floatval($inv['sales_tax_rate'] ?? 0);
    
    // Determine tax rule for this invoice
    $rule = $db->getTaxRuleForInvoice($num, $date);
    $rate = $rule['rate'];
    $isLiablePeriod = ($rate > 0);
    
    $invKey = $num . '_' . $date;
    if (!isset($invoicesByTaxRule[$invKey])) {
        $invoicesByTaxRule[$invKey] = [
            'num' => $num,
            'date' => $date,
            'rate' => $rate,
            'rule_name' => $rule['name'],
            'is_liable' => $isLiablePeriod,
            'sales_tax_total' => $salesTaxTotal,
            'sales_tax_item' => $salesTaxItem,
            'sales_tax_rate' => $salesTaxRate,
            'lines' => [],
            'has_vat_line' => false
        ];
    }
    $invoicesByTaxRule[$invKey]['lines'][] = $inv;
    
    // Check if this line itself is a VAT line candidate:
    // Regex matches VAT, Value Added Tax, N% VAT, or Item = 'VAT' or Item = 'Tax'
    $isVatMatch = false;
    $matchedReason = '';
    
    if (preg_match('/^(VAT|Value Added Tax|\d+%\s*VAT)/i', $desc)) {
        $isVatMatch = true;
        $matchedReason = "desc_starts_with_vat";
    } elseif (preg_match('/^(VAT|Value Added Tax|\d+%\s*VAT)/i', $item)) {
        $isVatMatch = true;
        $matchedReason = "item_starts_with_vat";
    } elseif (preg_match('/\b(VAT\s*\d+%|\d+%\s*VAT|Value Added Tax)\b/i', $desc)) {
        $isVatMatch = true;
        $matchedReason = "desc_contains_vat_pattern";
    } elseif (strcasecmp($item, 'VAT') === 0) {
        $isVatMatch = true;
        $matchedReason = "item_is_vat";
    }
    
    if ($isVatMatch) {
        $vatCandidateLines[] = [
            'num' => $num,
            'date' => $date,
            'rate' => $rate,
            'is_liable' => $isLiablePeriod,
            'item' => $item,
            'desc' => $desc,
            'amount' => $amt,
            'reason' => $matchedReason,
            'sales_tax_total' => $salesTaxTotal
        ];
        if ($amt > 0) {
            $invoicesByTaxRule[$invKey]['has_vat_line'] = true;
        }
    }
}

echo "=== 1. ALL LINES IN JSON MATCHING VAT CANDIDATE PATTERN ===\n";
echo "Found " . count($vatCandidateLines) . " line item matches:\n";
foreach ($vatCandidateLines as $v) {
    echo sprintf(
        "Invoice: %-10s | Date: %s | LiableRate: %4.2f | Amt: %10.2f | Item: %-15s | Desc: %-40s | Match: %s\n",
        $v['num'],
        $v['date'],
        $v['rate'],
        $v['amount'],
        substr($v['item'], 0, 15),
        substr($v['desc'], 0, 40),
        $v['reason']
    );
}

echo "\n=== 2. SUMMARY BY VAT LIABILITY PERIOD ACROSS ALL UNIQUE INVOICES ===\n";
$liableCount = 0;
$exemptCount = 0;
$liableWithVatLine = 0;
$liableWithoutVatLine = 0;
$liableWithTaxFooter = 0;
$liableWithoutTaxFooter = 0;

foreach ($invoicesByTaxRule as $invKey => $data) {
    if ($data['is_liable']) {
        $liableCount++;
        if ($data['has_vat_line']) {
            $liableWithVatLine++;
        } else {
            $liableWithoutVatLine++;
        }
        if ($data['sales_tax_total'] > 0) {
            $liableWithTaxFooter++;
        } else {
            $liableWithoutTaxFooter++;
        }
    } else {
        $exemptCount++;
    }
}

echo "Total Unique Invoices in JSON: " . count($invoicesByTaxRule) . "\n";
echo "  - Invoices in VAT-Liable Periods (Rate > 0%): $liableCount\n";
echo "      * Invoices WITH a separate positive VAT line item: $liableWithVatLine\n";
echo "      * Invoices WITHOUT a separate positive VAT line item: $liableWithoutVatLine\n";
echo "      * Invoices WITH a QuickBooks Sales Tax Footer (> 0): $liableWithTaxFooter\n";
echo "      * Invoices WITHOUT a QuickBooks Sales Tax Footer (= 0): $liableWithoutTaxFooter\n";
echo "  - Invoices in VAT-Exempt Periods (Rate = 0%): $exemptCount\n";
