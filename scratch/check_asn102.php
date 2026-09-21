<?php
$f = 'app/binary/exports/qb_export_2026-09-16_143318.json';
$raw = file_get_contents($f);
$raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
$json = json_decode($raw, true);

$invoices = $json['invoices'] ?? [];
foreach ($invoices as $inv) {
    if (($inv['Num'] ?? '') === 'ASN000102') {
        echo "Line:\n";
        echo "  Item: " . ($inv['Item'] ?? '') . "\n";
        echo "  Desc: " . substr($inv['Description'] ?? '', 0, 40) . "\n";
        echo "  Amount: " . ($inv['Amount'] ?? '') . "\n";
        echo "  subtotal: " . ($inv['subtotal'] ?? '') . "\n";
        echo "  sales_tax_total: " . ($inv['sales_tax_total'] ?? '') . "\n";
        echo "  sales_tax_rate: " . ($inv['sales_tax_rate'] ?? '') . "\n";
        echo "  sales_tax_item: " . ($inv['sales_tax_item'] ?? '') . "\n";
    }
}
