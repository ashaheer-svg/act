<?php
/**
 * Local script to execute credit memo applications on production
 */

ini_set('memory_limit', '1024M');
$file = __DIR__ . '/../app/exports/16-9/qb_export_2026-09-16_143318.json';
$content = preg_replace('/^\xEF\xBB\xBF/', '', file_get_contents($file));
$data = json_decode($content, true);

$uniqueCms = [];
foreach ($data['credit_memos'] as $cm) {
    $num = trim($cm['Num'] ?? '');
    $customer = trim($cm['Name'] ?? '');
    $date = date('Y-m-d', strtotime(str_replace('/', '-', $cm['Date'] ?? '')));
    $lineAmt = floatval(str_replace(',', '', $cm['Amount'] ?? 0));

    if (empty($num)) continue;

    if (!isset($uniqueCms[$num])) {
        $uniqueCms[$num] = [
            'num' => $num,
            'customer' => $customer,
            'date' => $date,
            'total_amount' => 0.0,
            'linked_invoices' => [],
            'serials' => []
        ];
    }

    $uniqueCms[$num]['total_amount'] += $lineAmt;

    // Check applied_to_invoice / linked_txns on this line
    $appInv = trim($cm['applied_to_invoice'] ?? '');
    $appAmt = abs(floatval(str_replace(',', '', $cm['applied_amount'] ?? 0)));
    if (!empty($appInv) && $appAmt > 0) {
        $uniqueCms[$num]['linked_invoices'][$appInv] = $appAmt;
    }

    if (!empty($cm['linked_txns'])) {
        foreach ($cm['linked_txns'] as $lk) {
            $ref = trim($lk['ref_number'] ?? '');
            $amt = abs(floatval($lk['amount'] ?? 0));
            $type = trim($lk['txn_type'] ?? '');
            if (strcasecmp($type, 'Invoice') === 0 && !empty($ref) && $amt > 0) {
                $uniqueCms[$num]['linked_invoices'][$ref] = $amt;
            }
        }
    }

    $desc = trim($cm['Description'] ?? '');
    if (preg_match_all('/(?:S\/N|Serial|SN)[:\s]*([A-Z0-9\-_]{6,})/i', $desc, $matches)) {
        foreach ($matches[1] as $sn) {
            $uniqueCms[$num]['serials'][] = trim($sn);
        }
    }
}

echo "Prepared " . count($uniqueCms) . " unique credit memos for application.\n";

$payload = [
    'credit_memos' => array_values($uniqueCms)
];

$json = json_encode($payload);
$b64 = base64_encode(gzdeflate($json, 9));
$wrapped = json_encode(['compressed_payload' => $b64]);

$config = json_decode(file_get_contents(__DIR__ . '/../app/binary/config.json'), true);
$apiKey = $config['api_key'];
$url = 'https://act.active.lk/api/apply_credit_memos.php';

echo "Sending payload to $url...\n";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $wrapped,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'X-API-KEY: ' . $apiKey
    ],
    CURLOPT_TIMEOUT => 60
]);

$start = microtime(true);
$res = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$dt = round(microtime(true) - $start, 2);
unset($ch);

echo "HTTP Code: $httpCode (took {$dt}s)\n";
echo "Response:\n";
echo $res . "\n";
