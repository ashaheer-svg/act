<?php
ini_set('memory_limit', '1024M');
$file = __DIR__ . '/../app/exports/16-9/qb_export_2026-09-16_143318.json';
$content = file_get_contents($file);
$content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
$data = json_decode($content, true);

$cms = $data['credit_memos'] ?? [];
echo "Total Credit Memo lines: " . count($cms) . "\n";

$linksByCm = [];
$serialsByCm = [];

foreach ($cms as $cm) {
    $num = trim($cm['Num'] ?? $cm['credit_memo_number'] ?? '');
    $customer = trim($cm['Name'] ?? $cm['customer_name'] ?? '');
    $desc = trim($cm['Description'] ?? $cm['Item'] ?? '');
    $date = $cm['Date'] ?? '';

    if (empty($num)) continue;

    if (!isset($linksByCm[$num])) {
        $linksByCm[$num] = [
            'customer' => $customer,
            'date' => $date,
            'total_cm_amount' => 0,
            'linked_invoices' => [],
            'serials' => []
        ];
    }

    $rawAmount = floatval(str_replace(',', '', $cm['Amount'] ?? 0));
    $linksByCm[$num]['total_cm_amount'] += $rawAmount;

    // Check direct applied_to_invoice
    $appInv = trim($cm['applied_to_invoice'] ?? $cm['AppliedToInvoice'] ?? '');
    $appAmt = abs(floatval(str_replace(',', '', $cm['applied_amount'] ?? $cm['AppliedAmount'] ?? 0)));
    if (!empty($appInv) && $appAmt > 0) {
        $linksByCm[$num]['linked_invoices'][$appInv] = ($linksByCm[$num]['linked_invoices'][$appInv] ?? 0) + $appAmt;
    }

    // Check linked_txns
    $linked = $cm['linked_txns'] ?? [];
    if (!empty($linked)) {
        foreach ($linked as $lk) {
            $ref = trim($lk['ref_number'] ?? '');
            $amt = abs(floatval($lk['amount'] ?? 0));
            $type = trim($lk['txn_type'] ?? '');
            if (!empty($ref) && $amt > 0) {
                // If the link is to an invoice
                $linksByCm[$num]['linked_invoices'][$ref] = max($linksByCm[$num]['linked_invoices'][$ref] ?? 0, $amt);
            }
        }
    }

    // Check for serials in description
    if (preg_match_all('/(?:S\/N|Serial|SN)[:\s]*([A-Z0-9\-_]{6,})/i', $desc, $matches)) {
        foreach ($matches[1] as $sn) {
            $linksByCm[$num]['serials'][] = trim($sn);
        }
    }
}

echo "Unique Credit Memos with Num: " . count($linksByCm) . "\n";

$withLinks = 0;
$totalLinkedAmount = 0;
foreach ($linksByCm as $num => $info) {
    if (!empty($info['linked_invoices'])) {
        $withLinks++;
        foreach ($info['linked_invoices'] as $inv => $amt) {
            $totalLinkedAmount += $amt;
        }
    }
}

echo "Credit Memos with linked invoices: $withLinks / " . count($linksByCm) . "\n";
echo "Total Linked Settlement Amount: LKR " . number_format($totalLinkedAmount, 2) . "\n\n";

echo "First 10 Credit Memos with their Links:\n";
$i = 0;
foreach ($linksByCm as $num => $info) {
    echo "CM #$num ({$info['date']}) | Cust: {$info['customer']} | CM Total: " . number_format($info['total_cm_amount'], 2) . "\n";
    if (!empty($info['linked_invoices'])) {
        foreach ($info['linked_invoices'] as $inv => $amt) {
            echo "   -> Applied to Invoice $inv : LKR " . number_format($amt, 2) . "\n";
        }
    } else {
        echo "   -> [NO LINKED INVOICE FOUND]\n";
    }
    if (!empty($info['serials'])) {
        echo "   -> Serials: " . implode(', ', array_unique($info['serials'])) . "\n";
    }
    if (++$i >= 10) break;
}
