<?php
$f = 'app/binary/exports/qb_export_2026-09-16_143318.json';
$raw = file_get_contents($f);
$raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
$json = json_decode($raw, true);

$cms = $json['credit_memos'] ?? [];
$byNum = [];
foreach ($cms as $cm) {
    $num = trim($cm['Num'] ?? '');
    if (!isset($byNum[$num])) {
        $byNum[$num] = [
            'num' => $num,
            'date' => $cm['Date'] ?? '',
            'customer' => $cm['Name'] ?? '',
            'amount' => 0,
            'memo' => $cm['Memo'] ?? '',
            'linked_txns' => $cm['linked_txns'] ?? []
        ];
    }
    $byNum[$num]['amount'] += floatval($cm['Amount'] ?? 0);
}

echo "=== BREAKDOWN OF ALL 51 CREDIT MEMOS LINKING ===\n";
$linkedToInvoiceCount = 0;
$linkedToCheckCount = 0;
$noLinkCount = 0;

foreach ($byNum as $num => $cm) {
    $links = $cm['linked_txns'];
    $hasInvLink = false;
    $hasCheckLink = false;
    $invRefs = [];
    foreach ($links as $l) {
        if (($l['txn_type'] ?? '') === 'Invoice' && !empty($l['ref_number'])) {
            $hasInvLink = true;
            $invRefs[] = "{$l['ref_number']} (" . abs(floatval($l['amount'])) . ")";
        } elseif (($l['txn_type'] ?? '') === 'Check') {
            $hasCheckLink = true;
        }
    }
    
    if ($hasInvLink) {
        $linkedToInvoiceCount++;
        echo sprintf("CM %-12s | %s | %-30s | Amt: %10.2f | Linked Invoices: %s\n", $num, $cm['date'], substr($cm['customer'], 0, 30), $cm['amount'], implode(', ', $invRefs));
    } elseif ($hasCheckLink) {
        $linkedToCheckCount++;
        echo sprintf("CM %-12s | %s | %-30s | Amt: %10.2f | REFUND CHECK\n", $num, $cm['date'], substr($cm['customer'], 0, 30), $cm['amount']);
    } else {
        $noLinkCount++;
        echo sprintf("CM %-12s | %s | %-30s | Amt: %10.2f | UNLINKED (Memo: %s)\n", $num, $cm['date'], substr($cm['customer'], 0, 30), $cm['amount'], $cm['memo']);
    }
}

echo "\nSummary:\n";
echo "Directly Linked to Invoices: $linkedToInvoiceCount\n";
echo "Settled via Refund Check: $linkedToCheckCount\n";
echo "Unlinked / Standalone: $noLinkCount\n";
