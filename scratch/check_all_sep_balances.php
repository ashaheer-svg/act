<?php
$f = 'app/binary/exports/qb_export_2026-09-16_143318.json';
$raw = file_get_contents($f);
$raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
$json = json_decode($raw, true);

$invoices = $json['invoices'] ?? [];

$all = [];
foreach ($invoices as $inv) {
    $num = $inv['Num'] ?? '';
    if (strpos($inv['Date'] ?? '', '2026-09') === 0 && !isset($all[$num])) {
        $sub = floatval($inv['subtotal'] ?? 0);
        $tax = floatval($inv['sales_tax_total'] ?? 0);
        $bal = floatval($inv['balance_remaining'] ?? 0);
        $app = floatval($inv['applied_amount'] ?? 0);
        $tot = $bal + $app;
        $all[$num] = [
            'subtotal' => $sub,
            'tax' => $tax,
            'sub_plus_tax' => $sub + $tax,
            'balance_remaining' => $bal,
            'applied' => $app,
            'total_invoice_value' => $tot,
            'tax_item' => $inv['sales_tax_item'] ?? '',
            'tax_rate' => $inv['sales_tax_rate'] ?? 0
        ];
    }
}

echo "=== CHECKING BALANCE AND TAX TOTALS FOR ALL SEP 2026 INVOICES ===\n";
foreach ($all as $num => $info) {
    echo sprintf(
        "%-10s | Subtotal: %10.2f | Tax: %9.2f | Sub+Tax: %10.2f | TotalValue: %10.2f | Match: %s | TaxItem: %s (%s%%)\n",
        $num,
        $info['subtotal'],
        $info['tax'],
        $info['sub_plus_tax'],
        $info['total_invoice_value'],
        abs($info['sub_plus_tax'] - $info['total_invoice_value']) < 0.05 ? 'YES' : 'NO',
        $info['tax_item'],
        $info['tax_rate']
    );
}
