<?php
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

echo "Total Unique Credit Memos: " . count($uniqueCms) . "\n";
$sumCmAmounts = 0;
$sumLinkedAmounts = 0;
$linkedCount = 0;

foreach ($uniqueCms as $num => $cm) {
    $sumCmAmounts += $cm['total_amount'];
    if (!empty($cm['linked_invoices'])) {
        $linkedCount++;
        foreach ($cm['linked_invoices'] as $inv => $amt) {
            $sumLinkedAmounts += $amt;
        }
    }
}

echo "Sum of Credit Memo amounts: LKR " . number_format($sumCmAmounts, 2) . "\n";
echo "Credit Memos linked to invoices: $linkedCount / " . count($uniqueCms) . "\n";
echo "Sum of Linked Invoice settlements: LKR " . number_format($sumLinkedAmounts, 2) . "\n\n";

echo "Listing All 50 Credit Memos:\n";
$n = 0;
foreach ($uniqueCms as $num => $cm) {
    $n++;
    $linkStr = '';
    if (!empty($cm['linked_invoices'])) {
        foreach ($cm['linked_invoices'] as $inv => $amt) {
            $linkStr .= " -> Settled Inv $inv (LKR " . number_format($amt, 2) . ")";
        }
    } else {
        $linkStr = " -> [Unlinked / Standalone Credit]";
    }
    $snStr = !empty($cm['serials']) ? " | Serials: " . implode(', ', array_unique($cm['serials'])) : "";
    echo sprintf("%2d. CM #%-12s (%s) %-35s Total: %10s%s%s\n",
        $n,
        $num,
        $cm['date'],
        substr($cm['customer'], 0, 35),
        number_format($cm['total_amount'], 2),
        $linkStr,
        $snStr
    );
}
