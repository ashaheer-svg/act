<?php
$f = 'app/binary/exports/qb_export_2026-09-16_143318.json';
$raw = file_get_contents($f);
$raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
$json = json_decode($raw, true);

$cms = $json['credit_memos'] ?? [];
echo "Total Credit Memo rows: " . count($cms) . "\n";

$byNum = [];
$totalSettled = 0;
$withLinks = 0;
$withoutLinks = 0;

foreach ($cms as $cm) {
    $num = $cm['Num'] ?? '';
    if (!isset($byNum[$num])) {
        $byNum[$num] = [
            'num' => $num,
            'date' => $cm['Date'] ?? '',
            'customer' => $cm['Name'] ?? '',
            'amount' => 0,
            'linked_txns' => $cm['linked_txns'] ?? []
        ];
    }
    $byNum[$num]['amount'] += floatval($cm['Amount'] ?? 0);
}

echo "Total Unique Credit Memos: " . count($byNum) . "\n\n";

foreach (array_slice($byNum, 0, 10) as $num => $cm) {
    $links = $cm['linked_txns'];
    echo "Credit Memo: {$num} | Date: {$cm['date']} | Customer: {$cm['customer']} | Amount: {$cm['amount']}\n";
    if (!empty($links)) {
        $withLinks++;
        foreach ($links as $l) {
            echo "  --> Linked to Invoice: [{$l['ref_number']}] | Amount: {$l['amount']} | TxnType: {$l['txn_type']}\n";
        }
    } else {
        $withoutLinks++;
        echo "  --> NO linked_txns in JSON\n";
    }
}
