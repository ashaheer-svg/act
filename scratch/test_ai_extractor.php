<?php
require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/AiExtractor.php';

$db = new Database(DATABASE_PATH);
$extractor = new AiExtractor($db);

echo "AI Extractor Configuration:\n";
print_r($extractor->getSettings());

echo "\n--- TESTING LIVE CONNECTION TO GEMINI ---\n";
$conn = $extractor->testConnection();
print_r($conn);

// Test with invoice AS000102 (Synology DS225+ and Seagate Ironwolf 4TB)
$invoiceNum = 'AS000102';
$lines = $db->fetchAll("SELECT * FROM sales WHERE invoice_number = ?", [$invoiceNum]);

echo "\nRaw Lines for $invoiceNum (Count: " . count($lines) . "):\n";
foreach ($lines as $l) {
    echo " - [{$l['tax_code']}] {$l['item_description']} | Qty: {$l['quantity']} | Total: {$l['total_amount']}\n";
}

// Simulated model response for AS000102
$mockAiJson = [
    'products' => [
        [
            'product_type' => 'HARDWARE',
            'product_name' => 'Synology DiskStation DS225+ 2Bay NAS',
            'brand' => 'Synology',
            'model_sku' => 'DS225+',
            'quantity' => 1,
            'unit_price' => 132000.00,
            'total_amount' => 132000.00,
            'raw_line_ids' => [$lines[0]['id'] ?? 1, $lines[1]['id'] ?? 2],
            'serials' => ['2580ZDRK5SJZ7'],
            'warranty' => [
                'type' => 'Standard',
                'duration_months' => 36,
                'start_date' => '2026-09-05',
                'expiry_date' => '2029-09-05',
                'notes' => 'Warranty 03 Years on NAS & HDD'
            ]
        ],
        [
            'product_type' => 'HARDWARE',
            'product_name' => '4 TB Seagate Ironwolf NAS Hard Drive',
            'brand' => 'Seagate',
            'model_sku' => 'Ironwolf 4TB',
            'quantity' => 2,
            'unit_price' => 86000.00,
            'total_amount' => 172000.00,
            'raw_line_ids' => [$lines[2]['id'] ?? 3],
            'serials' => ['WW6AG0Q1', 'WW6AG0Q2'],
            'warranty' => [
                'type' => 'Standard',
                'duration_months' => 36,
                'start_date' => '2026-09-05',
                'expiry_date' => '2029-09-05',
                'notes' => 'Warranty 03 Years on NAS & HDD'
            ]
        ]
    ]
];

// Test reflection to call persistEntities
$reflector = new ReflectionClass('AiExtractor');
$persistMethod = $reflector->getMethod('persistEntities');
$persistMethod->setAccessible(true);

$res = $persistMethod->invoke($extractor, $invoiceNum, 'Hexar Evolution (Pvt) Ltd', '2026-09-05', $mockAiJson, 100);
echo "\nEntities Created from Mock Extractor:\n";
print_r($res);

echo "\n--- VERIFYING NORMALIZED DATABASE RECORDS ---\n";
echo "1. invoice_items:\n";
$items = $db->fetchAll("SELECT * FROM invoice_items WHERE invoice_number = ?", [$invoiceNum]);
print_r($items);

echo "\n2. hardware_assets:\n";
$assets = $db->fetchAll("SELECT * FROM hardware_assets WHERE invoice_number = ?", [$invoiceNum]);
print_r($assets);
