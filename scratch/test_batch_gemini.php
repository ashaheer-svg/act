<?php
require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/AiExtractor.php';

$db = new Database(DATABASE_PATH);
$extractor = new AiExtractor($db);

$invoices = ['AS008832', 'AS008835', 'AS008836', 'AS008895', 'AS008899'];
$prompt = "You are an expert enterprise ERP & IT Asset Extraction AI.\n";
$prompt .= "Analyze the following 5 QuickBooks invoices and extract their Commercial Products, Hardware Assets (with discrete serials and warranties), and Software/Maintenance Agreements (with contract dates).\n\n";

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
          "product_type": "HARDWARE | SOFTWARE_LICENSE | SAAS_SUBSCRIPTION | MAINTENANCE_AGREEMENT | SERVICE_AMC | ACCESSORY_OTHER",
          "product_name": "Product name",
          "brand": "Brand",
          "model_sku": "Model",
          "quantity": 1,
          "unit_price": 100.0,
          "total_amount": 100.0,
          "serials": ["S1", "S2"],
          "warranty": {
            "duration_months": 36,
            "start_date": "YYYY-MM-DD",
            "expiry_date": "YYYY-MM-DD",
            "notes": "Clause"
          },
          "subscription": {
            "software_name": "Title",
            "license_seats": 1,
            "period_start_date": "YYYY-MM-DD",
            "period_end_date": "YYYY-MM-DD",
            "renewal_opportunity_value": 100.0
          }
        }
      ]
    }
  ]
}
SCHEMA;

$t0 = microtime(true);
$callMethod = new ReflectionMethod('AiExtractor', 'callProviderRaw');
$rawResponse = $callMethod->invoke($extractor, $prompt);
$duration = round(microtime(true) - $t0, 2);
echo "Gemini Batch Call completed in {$duration}s!\n";

$data = json_decode($rawResponse, true);
if ($data && isset($data['invoices'])) {
    echo "Successfully received " . count($data['invoices']) . " invoices in response:\n";
    foreach ($data['invoices'] as $inv) {
        echo "  - {$inv['invoice_number']}: " . count($inv['products']) . " products\n";
    }
} else {
    echo "Raw response:\n" . substr($rawResponse, 0, 500) . "\n";
}
