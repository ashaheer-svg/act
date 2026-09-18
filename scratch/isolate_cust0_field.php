<?php
$url = 'https://act.active.lk/api/sync.php';
$apiKey = '5c36ac8a4928a3feda2ef93d7e6c90ff5183d97ac1982867';

$cust0 = [
    "list_id" => "80000112-1617705694",
    "name" => "361 Degrees (Pvt) Ltd",
    "full_name" => "361 Degrees (Pvt) Ltd",
    "company_name" => "361 Degrees (Pvt) Ltd",
    "contact_name" => "",
    "first_name" => "",
    "last_name" => "",
    "job_title" => "",
    "email" => "",
    "phone" => "",
    "alt_phone" => "",
    "fax" => "",
    "bill_address" => "361 Degrees (Pvt) Ltd, 185/1 Sri Gnanendra Mawatha",
    "bill_address_2" => "185/1 Sri Gnanendra Mawatha",
    "bill_address_3" => "",
    "bill_address_4" => "",
    "bill_address_5" => "",
    "bill_city" => "Nawala",
    "bill_state" => "",
    "bill_zip" => "",
    "bill_country" => "",
    "ship_address" => "",
    "ship_city" => "",
    "ship_state" => "",
    "ship_zip" => "",
    "ship_country" => "",
    "customer_type" => "Retail",
    "terms" => "",
    "sales_rep" => "",
    "balance" => 0,
    "total_balance" => 0,
    "credit_limit" => 0,
    "account_number" => "",
    "resale_number" => "",
    "vat_number" => "",
    "tin_number" => "",
    "is_vat_registered" => false,
    "tax_item_ref" => "",
    "tax_code_ref" => "Tax",
    "is_active" => true,
    "notes" => ""
];

function testPayload($payload, $label) {
    global $url, $apiKey;
    $json = json_encode(['customers' => [$payload]]);
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
    echo "$label: HTTP $code\n";
    if ($code !== 200) {
        echo "Response: " . substr(strip_tags($resp), 0, 100) . "\n";
    }
}

// Test with each key removed
foreach ($cust0 as $k => $v) {
    if ($v === "" || $v === 0 || $v === false) continue;
    $copy = $cust0;
    unset($copy[$k]);
    testPayload($copy, "Without $k");
}

// Test with minimal name
testPayload(['name' => '361 Degrees (Pvt) Ltd'], "Only name");
testPayload(['name' => 'Degrees'], "Name without 361 and (Pvt)");
testPayload(['name' => '361 Degrees'], "Name without (Pvt)");
testPayload(['name' => '(Pvt) Ltd'], "Only (Pvt) Ltd");
testPayload(['bill_address' => '361 Degrees (Pvt) Ltd, 185/1 Sri Gnanendra Mawatha'], "Only bill_address");
