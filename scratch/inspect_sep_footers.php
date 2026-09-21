<?php
$f = 'app/binary/exports/qb_export_2026-09-16_143318.json';
$raw = file_get_contents($f);
$raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
$json = json_decode($raw, true);

$invoices = $json['invoices'] ?? [];

$sepInvoices = [];
foreach ($invoices as $inv) {
    $d = $inv['Date'] ?? '';
    if (strpos($d, '2026-09') === 0) {
        $num = $inv['Num'] ?? '';
        if (!isset($sepInvoices[$num])) {
            $sepInvoices[$num] = [
                'num' => $num,
                'date' => $d,
                'customer' => $inv['Name'] ?? '',
                'subtotal' => floatval($inv['subtotal'] ?? 0),
                'sales_tax_total' => floatval($inv['sales_tax_total'] ?? 0),
                'sales_tax_rate' => floatval($inv['sales_tax_rate'] ?? 0),
                'sales_tax_item' => $inv['sales_tax_item'] ?? '',
                'customer_tax_code' => $inv['customer_tax_code'] ?? '',
                'lines_sum' => 0.0,
                'lines' => []
            ];
        }
        $amt = floatval($inv['Amount'] ?? 0);
        $sepInvoices[$num]['lines_sum'] += $amt;
        $sepInvoices[$num]['lines'][] = [
            'item' => $inv['Item'] ?? '',
            'desc' => substr($inv['Description'] ?? '', 0, 35),
            'amount' => $amt,
            'tax_code' => $inv['Sales Tax Code'] ?? ''
        ];
    }
}

echo "=== SEPTEMBER 2026 INVOICE FOOTERS (" . count($sepInvoices) . " Invoices) ===\n\n";

$withTaxFooter = 0;
$withoutTaxFooter = 0;

foreach ($sepInvoices as $num => $data) {
    $hasTax = ($data['sales_tax_total'] > 0 || !empty($data['sales_tax_item']));
    if ($hasTax) $withTaxFooter++; else $withoutTaxFooter++;
    
    echo sprintf(
        "Invoice: %-10s | Date: %s | LinesSum: %12.2f | Subtotal: %12.2f | TaxItem: %-4s | TaxRate: %5.2f%% | TaxTotal: %10.2f | CustTaxCode: %s\n",
        $num,
        $data['date'],
        $data['lines_sum'],
        $data['subtotal'],
        $data['sales_tax_item'],
        $data['sales_tax_rate'],
        $data['sales_tax_total'],
        $data['customer_tax_code']
    );
}

echo "\nSummary:\n";
echo "Invoices with Tax/VAT Footer (sales_tax_item / sales_tax_total > 0): $withTaxFooter\n";
echo "Invoices without Tax/VAT Footer (0% or Non): $withoutTaxFooter\n";
