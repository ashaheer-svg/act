<?php
$jsonPath = __DIR__ . '/../app/exports/qb_export_2026-09-05_125436.json';
if (!file_exists($jsonPath)) {
    die("JSON not found: $jsonPath\n");
}

$data = json_decode(file_get_contents($jsonPath), true);
echo "JSON root keys: " . implode(', ', array_keys($data)) . "\n";

if (isset($data['invoices'])) {
    echo "Found " . count($data['invoices']) . " invoices in JSON.\n";
    foreach ($data['invoices'] as $inv) {
        $ref = $inv['RefNumber'] ?? $inv['Num'] ?? $inv['InvoiceNumber'] ?? '';
        if ($ref === 'AS000064' || $ref === 'AS000072' || strpos($ref, '000064') !== false || strpos($ref, '000072') !== false) {
            echo "\n==================== INVOICE $ref ====================\n";
            echo json_encode($inv, JSON_PRETTY_PRINT) . "\n";
        }
    }
}
