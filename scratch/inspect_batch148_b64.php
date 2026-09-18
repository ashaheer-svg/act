<?php
require_once __DIR__ . '/bisect_standalone.php';

$slice = array_slice($batch148, 0, 25);
$payload = [
    'source' => 'test_slice',
    'timestamp' => '2026-09-17T13:00:00Z',
    'customers' => [],
    'invoices' => $slice,
    'credit_memos' => [],
    'payments' => []
];
$b64 = base64_encode(gzdeflate(json_encode($payload)));
echo "Base64 len: " . strlen($b64) . "\n";
echo "Base64 text:\n$b64\n";

// Let's test search for common SQL words or signatures in this base64 string
$sqliWords = ['select', 'union', 'order', 'group', 'from', 'where', 'insert', 'update', 'delete', 'drop', 'degrees', 'benchmark', 'sleep', 'load_file', 'outfile', 'sysdate', 'version', 'user', 'eval', 'exec', 'system', 'passthru', 'shell'];
foreach ($sqliWords as $w) {
    if (stripos($b64, $w) !== false) {
        echo "Found word in Base64: '$w' at offset " . stripos($b64, $w) . "!\n";
    }
}
