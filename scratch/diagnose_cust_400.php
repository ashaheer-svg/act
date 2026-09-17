<?php
$url = 'https://act.active.lk/api/sync.php';
$apiKey = '5c36ac8a4928a3feda2ef93d7e6c90ff5183d97ac1982867';

function postJson($data) {
    global $url, $apiKey;
    $json = json_encode($data);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-API-KEY: ' . $apiKey
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, $resp, strlen($json)];
}

// Test 1: Minimal customer
$c1 = [
    'list_id' => '1',
    'name' => 'Test Corp',
    'customer_type' => 'End Customer'
];
list($code, $resp, $len) = postJson(['customers' => array_fill(0, 50, $c1)]);
echo "50 Minimal customers ($len B): HTTP $code\n";

// Test 2: Add fields one by one to see which triggers 400
$fields = [
    'company_name' => 'Test Corporation',
    'contact_name' => 'John Doe',
    'email' => 'test@example.com',
    'phone' => '+94 11 2345678',
    'alt_phone' => '+94 77 1234567',
    'fax' => '+94 11 9876543',
    'bill_address' => "Line 1\nLine 2",
    'bill_city' => 'Colombo',
    'sales_rep' => 'AS',
    'balance' => 1000.0,
    'total_balance' => 1000.0,
    'credit_limit' => 5000.0,
    'terms' => 'Net 30',
    'resale_number' => '12345-7000',
    'vat_number' => '123456789-7000',
    'tin_number' => '987654321',
    'is_vat_registered' => true,
    'tax_item_ref' => 'VAT 18%',
    'notes' => "Multi-line note\nWith special chars & symbols < > %"
];

$testCust = $c1;
foreach ($fields as $k => $v) {
    $testCust[$k] = $v;
    list($code, $resp, $len) = postJson(['customers' => array_fill(0, 10, $testCust)]);
    echo "Added $k ($len B): HTTP $code\n";
    if ($code === 400) {
        echo "--> Triggered 400 on $k!\n";
        break;
    }
}
