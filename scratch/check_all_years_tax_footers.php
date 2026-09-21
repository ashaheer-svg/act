<?php
$f = 'app/binary/exports/qb_export_2026-09-16_143318.json';
$raw = file_get_contents($f);
$raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
$json = json_decode($raw, true);

$invoices = $json['invoices'] ?? [];

$byYear = [];
foreach ($invoices as $inv) {
    $num = $inv['Num'] ?? '';
    $d = $inv['Date'] ?? '';
    $yr = substr($d, 0, 4);
    if (!isset($byYear[$yr])) {
        $byYear[$yr] = [
            'total_invoices' => 0,
            'with_tax_footer' => 0,
            'without_tax_footer' => 0,
            'sample_tax_footer' => null,
            'sample_no_footer' => null
        ];
    }
    
    // Count once per invoice
    static $seen = [];
    if (isset($seen[$num . '_' . $d])) continue;
    $seen[$num . '_' . $d] = true;
    
    $byYear[$yr]['total_invoices']++;
    $tax = floatval($inv['sales_tax_total'] ?? 0);
    if ($tax > 0) {
        $byYear[$yr]['with_tax_footer']++;
        if (!$byYear[$yr]['sample_tax_footer']) {
            $byYear[$yr]['sample_tax_footer'] = [
                'num' => $num, 'date' => $d, 'sub' => $inv['subtotal'] ?? 0, 'tax' => $tax, 'tax_item' => $inv['sales_tax_item'] ?? '', 'rate' => $inv['sales_tax_rate'] ?? 0
            ];
        }
    } else {
        $byYear[$yr]['without_tax_footer']++;
        if (!$byYear[$yr]['sample_no_footer']) {
            $byYear[$yr]['sample_no_footer'] = [
                'num' => $num, 'date' => $d, 'sub' => $inv['subtotal'] ?? 0, 'tax' => $tax, 'tax_item' => $inv['sales_tax_item'] ?? '', 'rate' => $inv['sales_tax_rate'] ?? 0
            ];
        }
    }
}

ksort($byYear);
echo "=== TAX FOOTER DISTRIBUTION BY YEAR ACROSS ALL QUICKBOOKS INVOICES ===\n";
foreach ($byYear as $yr => $data) {
    echo sprintf(
        "Year %s | Total Invoices: %5d | With Tax Footer (>0): %5d | Without Tax Footer: %5d\n",
        $yr,
        $data['total_invoices'],
        $data['with_tax_footer'],
        $data['without_tax_footer']
    );
}

echo "\nSamples with tax footer:\n";
foreach ($byYear as $yr => $data) {
    if ($data['sample_tax_footer']) {
        $s = $data['sample_tax_footer'];
        echo "  Year $yr: [{$s['num']}] {$s['date']} | Sub: {$s['sub']} | Tax: {$s['tax']} ({$s['tax_item']} {$s['rate']}%)\n";
    }
}
