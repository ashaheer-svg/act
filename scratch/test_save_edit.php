<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';

$db = new Database(DATABASE_PATH);
$auth = new Auth($db);

$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['role'] = 'admin';
$_SESSION['last_activity'] = time();

// Fetch ASN000104 first item
$item = $db->fetch("SELECT id, clean_product_name, brand, category, base_value, gross_profit FROM invoice_items WHERE invoice_number = 'ASN000104' LIMIT 1");
if (!$item) {
    die("Item not found\n");
}

echo "Current Item: {$item['clean_product_name']} | Brand: {$item['brand']} | GP: {$item['gross_profit']}\n";

$payload = [
    'invoice_number' => 'ASN000104',
    'header' => [
        'end_customer' => 'Test Hospital Network',
        'sales_rep_code' => 'DIR',
        'po_number' => 'PO-TEST-99',
        'paid_date' => '2026-03-15',
        'vat_treatment' => 'PLUS_VAT',
        'recalc_vat' => 0,
        'memo' => 'Test memo audit'
    ],
    'items' => [
        [
            'id' => $item['id'],
            'clean_product_name' => $item['clean_product_name'],
            'brand' => 'Synology',
            'category' => 'NAS & Storage Servers',
            'product_type' => 'HARDWARE',
            'unit_cost' => 150000.00,
            'gross_profit' => 45000.00,
            'end_customer' => 'Test Hospital Network'
        ]
    ],
    'assets' => [],
    'new_assets' => [],
    'delete_assets' => [],
    'subscriptions' => []
];

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['action'] = 'save_invoice_full';
$_POST['payload'] = json_encode($payload);

ob_start();
include __DIR__ . '/../invoice_edit.php';
$resp = ob_get_clean();

echo "Response from save: $resp\n";

$updatedItem = $db->fetch("SELECT id, brand, category, unit_cost, gross_profit, end_customer FROM invoice_items WHERE id = ?", [$item['id']]);
echo "Updated Item in DB:\n";
print_r($updatedItem);

$updatedHeader = $db->fetch("SELECT end_customer, po_number, memo, sales_rep_code FROM sales WHERE invoice_number = 'ASN000104' LIMIT 1");
echo "Updated Sales Header in DB:\n";
print_r($updatedHeader);
