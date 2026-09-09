<?php
require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/AiExtractor.php';

$db = new Database(DATABASE_PATH);
$extractor = new AiExtractor($db);

$invoices = ['AS008832', 'AS008835', 'AS008836'];
$prompt = "You are an expert enterprise ERP & IT Asset Extraction AI.\n";
$prompt .= "Analyze the following 3 QuickBooks invoices and extract their Commercial Products, Hardware Assets (with discrete serials and warranties), and Software/Maintenance Agreements (with contract dates).\n\n";

foreach ($invoices as $invNum) {
    $lines = $db->fetchAll("SELECT id, invoice_number, invoice_date, customer_name, item_description, quantity, total_amount FROM sales WHERE invoice_number = ? ORDER BY id ASC", [$invNum]);
    $prompt .= "=== INVOICE: $invNum | Date: {$lines[0]['invoice_date']} | Customer: {$lines[0]['customer_name']} ===\n";
    foreach ($lines as $idx => $l) {
        $desc = str_replace(["\r\n", "\r", "\n"], " \\n ", trim($l['item_description']));
        $prompt .= "  Line " . ($idx+1) . " [ID: {$l['id']}]: Qty: {$l['quantity']} | Amount: {$l['total_amount']} | Desc: $desc\n";
    }
    $prompt .= "\n";
}

$prompt .= <<<SCHEMA
Return ONLY a valid JSON object strictly matching this schema:
{
  "invoices": [
    {
      "invoice_number": "AS...",
      "products": [
        {
          "product_type": "HARDWARE",
          "product_name": "Product name",
          "brand": "Brand",
          "model_sku": "Model",
          "quantity": 1,
          "unit_price": 100.0,
          "total_amount": 100.0,
          "serials": ["S1"],
          "warranty": {
            "duration_months": 36,
            "start_date": "YYYY-MM-DD",
            "expiry_date": "YYYY-MM-DD",
            "notes": "Clause"
          },
          "subscription": null
        }
      ]
    }
  ]
}
SCHEMA;

$callMethod = new ReflectionMethod('AiExtractor', 'callProviderRaw');
$rawResponse = $callMethod->invoke($extractor, $prompt);

echo "Raw length: " . strlen($rawResponse) . "\n";
$data = json_decode($rawResponse, true);
echo "JSON error: " . json_last_error_msg() . "\n";
if ($data && isset($data['invoices'])) {
    echo "Decoded " . count($data['invoices']) . " invoices successfully!\n";
    foreach ($data['invoices'] as $inv) {
        echo "  Invoice #{$inv['invoice_number']}: " . count($inv['products']) . " products\n";
        foreach ($inv['products'] as $p) {
            echo "    - {$p['product_name']} | Serials (" . count($p['serials']) . "): " . implode(', ', $p['serials']) . "\n";
        }
    }
}
