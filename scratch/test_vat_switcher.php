<?php
require_once 'config.php';
require_once 'classes/Database.php';

$db = new Database(DATABASE_PATH);

// Pick an invoice in taxable era (e.g. 2017) that is VAT_INCLUSIVE
$inv = $db->fetch("SELECT invoice_number, total_amount, base_value, vat_component, vat_treatment, manual_vat_override FROM sales WHERE vat_treatment = 'VAT_INCLUSIVE' AND applied_tax_rate > 0 LIMIT 1");
if (!$inv) {
    die("No VAT_INCLUSIVE invoice found.\n");
}

$num = $inv['invoice_number'];
echo "Original state for $num: Total={$inv['total_amount']}, Base={$inv['base_value']}, VAT={$inv['vat_component']}, Treatment={$inv['vat_treatment']}, Manual={$inv['manual_vat_override']}\n";

// Switch to PLUS_VAT
$res1 = $db->switchInvoiceVatMode($num, 'PLUS_VAT');
echo "Switched to PLUS_VAT: Total={$res1['new_total']}, Base={$res1['new_base']}, VAT={$res1['new_vat']}\n";

$check1 = $db->fetch("SELECT total_amount, base_value, vat_component, vat_treatment, manual_vat_override FROM sales WHERE invoice_number = ? LIMIT 1", [$num]);
echo "DB state after PLUS_VAT: Total={$check1['total_amount']}, Base={$check1['base_value']}, VAT={$check1['vat_component']}, Treatment={$check1['vat_treatment']}, Manual={$check1['manual_vat_override']}\n";

// Switch back to VAT_INCLUSIVE
$res2 = $db->switchInvoiceVatMode($num, 'VAT_INCLUSIVE');
echo "Switched back to VAT_INCLUSIVE: Total={$res2['new_total']}, Base={$res2['new_base']}, VAT={$res2['new_vat']}\n";

$check2 = $db->fetch("SELECT total_amount, base_value, vat_component, vat_treatment, manual_vat_override FROM sales WHERE invoice_number = ? LIMIT 1", [$num]);
echo "DB state after VAT_INCLUSIVE: Total={$check2['total_amount']}, Base={$check2['base_value']}, VAT={$check2['vat_component']}, Treatment={$check2['vat_treatment']}, Manual={$check2['manual_vat_override']}\n";

// Reset manual_vat_override back to 0 for this test invoice
$db->execute("UPDATE sales SET manual_vat_override = 0 WHERE invoice_number = ?", [$num]);
echo "Reset manual_vat_override to 0 for clean test.\n";
