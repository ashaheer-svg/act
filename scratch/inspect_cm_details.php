<?php
ini_set('memory_limit', '1024M');
$file = __DIR__ . '/../app/exports/16-9/qb_export_2026-09-16_143318.json';
$content = file_get_contents($file);
$content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
echo "File size: " . strlen($content) . " bytes\n";

$data = json_decode($content, true);
if ($data === null) {
    echo "JSON decode error: " . json_last_error_msg() . "\n";
    exit;
}
echo "Top level keys:\n";
print_r(array_keys($data));
$cms = $data['credit_memos'] ?? $data['creditMemos'] ?? $data['CreditMemos'] ?? $data['credit_memo'] ?? [];



echo "Total Credit Memo lines in JSON: " . count($cms) . "\n";

$uniqueNums = [];
$sampleByNum = [];
$withLinked = 0;
$withAppliedInv = 0;
$totalAmountSum = 0;

foreach ($cms as $cm) {
    $num = trim($cm['Num'] ?? $cm['credit_memo_number'] ?? '');
    if (!empty($num)) {
        $uniqueNums[$num] = ($uniqueNums[$num] ?? 0) + 1;
    }
    if (!isset($sampleByNum[$num])) {
        $sampleByNum[$num] = $cm;
    }
    if (!empty($cm['linked_txns'])) {
        $withLinked++;
    }
    if (!empty($cm['applied_to_invoice']) || !empty($cm['AppliedToInvoice']) || !empty($cm['Applied Invoice']) || !empty($cm['applied_invoice'])) {
        $withAppliedInv++;
    }
    $amt = floatval(str_replace(',', '', $cm['Amount'] ?? $cm['amount'] ?? 0));
    $totalAmountSum += $amt;
}

echo "Unique Credit Memo Numbers: " . count($uniqueNums) . "\n";
echo "Total Amount Sum: " . number_format($totalAmountSum, 2) . "\n";
echo "Lines with linked_txns: $withLinked\n";
echo "Lines with applied_to_invoice: $withAppliedInv\n\n";

echo "Keys of first CM line:\n";
print_r(array_keys($cms[0] ?? []));

echo "\nFirst 3 Unique CM Headers:\n";
$i = 0;
foreach ($sampleByNum as $num => $cm) {
    echo "--- CM #$num ---\n";
    echo "Date: " . ($cm['Date'] ?? '') . " | Customer: " . ($cm['Name'] ?? '') . " | Amount: " . ($cm['Amount'] ?? '') . "\n";
    echo "Description: " . ($cm['Description'] ?? '') . "\n";
    echo "Memo: " . ($cm['Memo'] ?? '') . "\n";
    if (!empty($cm['linked_txns'])) {
        echo "Linked Txns:\n";
        print_r($cm['linked_txns']);
    }
    if (++$i >= 3) break;
}
