<?php
$f = 'app/binary/exports/qb_export_2026-09-16_143318.json';
$raw = file_get_contents($f);
$raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
$json = json_decode($raw, true);

$invoices = $json['invoices'] ?? [];

$taxFieldCounts = 0;
$totalSep = 0;
foreach ($invoices as $inv) {
    $d = $inv['Date'] ?? '';
    if (strpos($d, '2026-09') === 0) {
        $totalSep++;
        $stt = floatval($inv['sales_tax_total'] ?? 0);
        $sti = trim($inv['sales_tax_item'] ?? '');
        if ($stt > 0 || !empty($sti)) {
            $taxFieldCounts++;
            echo "  Invoice {$inv['Num']}: sales_tax_total={$stt}, sales_tax_item={$sti}\n";
        }
    }
}
echo "September 2026 invoices with sales_tax_total > 0: $taxFieldCounts out of $totalSep\n";
