<?php
$ch = curl_init('https://act.active.lk/api/audit_data.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['X-API-KEY: 5c36ac8a4928a3feda2ef93d7e6c90ff5183d97ac1982867'],
    CURLOPT_SSL_VERIFYPEER => false
]);
$res = json_decode(curl_exec($ch), true);
$sum = $res['audit']['database_summary'] ?? [];

echo "=== DATABASE SUMMARY ===\n";
echo "Invoices Gross Total: LKR " . number_format($sum['invoices_gross']['total_amount'] ?? 0, 2) . "\n";
echo "Credit Memos Total  : LKR " . number_format($sum['credit_memos']['total_credit_amount'] ?? 0, 2) . "\n";
echo "Net Billed Revenue  : LKR " . number_format($sum['net_commercial_totals']['net_billed_revenue'] ?? 0, 2) . "\n";
echo "Net Base Revenue    : LKR " . number_format($sum['net_commercial_totals']['net_base_revenue'] ?? 0, 2) . "\n";
echo "Net VAT Total       : LKR " . number_format($sum['net_commercial_totals']['net_vat'] ?? 0, 2) . "\n\n";

echo "=== REPORTS VERIFICATION ===\n";
print_r($sum['reports_net_verification'] ?? []);

echo "\n=== PAYMENTS & SETTLEMENTS ===\n";
echo "Total Payments Recorded: " . ($sum['payments']['total_payment_rows'] ?? 0) . " rows\n";
echo "Total Payments Received: LKR " . number_format($sum['payments']['total_payments_received'] ?? 0, 2) . "\n";
echo "CM Settlements Applied : " . ($res['audit']['credit_memo_application_audit']['credit_memos_in_payments']['total_cm_payment_rows'] ?? 0) . " rows\n";
echo "CM Settled Value       : LKR " . number_format($res['audit']['credit_memo_application_audit']['credit_memos_in_payments']['total_cm_settlement_amount'] ?? 0, 2) . "\n";
