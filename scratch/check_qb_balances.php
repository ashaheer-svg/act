<?php
$f = 'app/binary/exports/qb_export_2026-09-16_143318.json';
$raw = file_get_contents($f);
$raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
$json = json_decode($raw, true);

$invoices = $json['invoices'] ?? [];

foreach ($invoices as $inv) {
    if (in_array($inv['Num'] ?? '', ['ASN000102', 'ASN000095', 'ASN000104', 'ASN000105'])) {
        echo "Invoice: " . $inv['Num'] . "\n";
        echo "  subtotal: " . ($inv['subtotal'] ?? '') . "\n";
        echo "  sales_tax_total: " . ($inv['sales_tax_total'] ?? '') . "\n";
        echo "  applied_amount: " . ($inv['applied_amount'] ?? '') . "\n";
        echo "  balance_remaining: " . ($inv['balance_remaining'] ?? '') . "\n";
        echo "  is_paid: " . (($inv['is_paid'] ?? false) ? 'true' : 'false') . "\n";
        break; // just check the first one
    }
}

// Check payments linking to ASN000102 or other September invoices
$payments = $json['payments'] ?? [];
echo "\nChecking payments for Sep 2026 invoices:\n";
foreach ($payments as $p) {
    foreach ($p['linked_txns'] ?? [] as $txn) {
        $ref = $txn['ref_number'] ?? '';
        if (strpos($ref, 'ASN000') === 0 && intval(substr($ref, 3)) >= 95) {
            echo "  Payment ref: {$p['ref_number']} | Invoice: $ref | Amount: {$txn['amount']}\n";
        }
    }
}
