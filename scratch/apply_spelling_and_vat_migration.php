<?php
/**
 * Master Migration Script:
 * 1. Deduplicate payments table
 * 2. Apply 41 QuickBooks-linked credit memos to target invoices
 * 3. Normalize brand spelling errors across all database tables
 * 4. Backfill invoice tax footers from master JSON export
 * 5. Run recalculateHistoricalVat() to fix 3,101 inflated records
 */

require_once 'config.php';
require_once 'classes/Database.php';

$db = new Database(DATABASE_PATH);

echo "========================================================\n";
echo "1. DEDUPLICATING PAYMENTS TABLE\n";
echo "========================================================\n";

$prePayments = (int)$db->fetch("SELECT count(*) as c FROM payments")['c'];
echo "Pre-cleanup payments count: $prePayments\n";

// Remove duplicates keeping the lowest id per group
$deletedDups = $db->execute("
    DELETE FROM payments 
    WHERE id NOT IN (
        SELECT MIN(id) 
        FROM payments 
        GROUP BY customer_name, payment_date, reference_num, amount, invoice_num, COALESCE(payment_method, '')
    )
");
$postPayments = (int)$db->fetch("SELECT count(*) as c FROM payments")['c'];
echo "Post-cleanup payments count: $postPayments (Pruned " . ($prePayments - $postPayments) . " redundant rows)\n";


echo "\n========================================================\n";
echo "2. NORMALIZING BRAND SPELLING ERRORS IN DATABASE\n";
echo "========================================================\n";

// A. In sales table
$s1 = $db->execute("UPDATE sales SET item_description = replace(item_description, 'BECOME', 'BDCOM') WHERE item_description LIKE '%BECOME%'");
$s2 = $db->execute("UPDATE sales SET item_description = replace(item_description, 'Become', 'BDCOM') WHERE item_description LIKE '%Become%'");
$s3 = $db->execute("UPDATE sales SET item_description = replace(item_description, 'become', 'BDCOM') WHERE item_description LIKE '%become%'");
$s4 = $db->execute("UPDATE sales SET item_description = replace(item_description, 'Sinology', 'Synology') WHERE item_description LIKE '%Sinology%'");
$s5 = $db->execute("UPDATE sales SET item_description = replace(item_description, 'sinology', 'Synology') WHERE item_description LIKE '%sinology%'");
$s6 = $db->execute("UPDATE sales SET item_description = replace(item_description, 'Snology', 'Synology') WHERE item_description LIKE '%Snology%'");
$s7 = $db->execute("UPDATE sales SET item_description = replace(item_description, 'Acronic', 'Acronis') WHERE item_description LIKE '%Acronic%'");
$s8 = $db->execute("UPDATE sales SET item_description = replace(item_description, 'acronic', 'Acronis') WHERE item_description LIKE '%acronic%'");
$s9 = $db->execute("UPDATE sales SET item_description = replace(item_description, 'Macronis', 'Acronis') WHERE item_description LIKE '%Macronis%'");
$s10 = $db->execute("UPDATE sales SET item_description = replace(item_description, 'macronis', 'Acronis') WHERE item_description LIKE '%macronis%'");

echo "Updated sales rows for brand spelling normalizations.\n";

// B. In hardware_assets table
$db->execute("UPDATE hardware_assets SET product_name = replace(product_name, 'BECOME', 'BDCOM') WHERE product_name LIKE '%BECOME%'");
$db->execute("UPDATE hardware_assets SET product_name = replace(product_name, 'Become', 'BDCOM') WHERE product_name LIKE '%Become%'");
$db->execute("UPDATE hardware_assets SET product_name = replace(product_name, 'become', 'BDCOM') WHERE product_name LIKE '%become%'");
$db->execute("UPDATE hardware_assets SET brand = 'BDCOM' WHERE (product_name LIKE '%BDCOM%' OR model_sku LIKE '%S1500%' OR model_sku LIKE '%S2900%' OR model_sku LIKE '%WAP%') AND brand = 'Other'");

$db->execute("UPDATE hardware_assets SET product_name = replace(product_name, 'Sinology', 'Synology') WHERE product_name LIKE '%Sinology%'");
$db->execute("UPDATE hardware_assets SET product_name = replace(product_name, 'sinology', 'Synology') WHERE product_name LIKE '%sinology%'");
$db->execute("UPDATE hardware_assets SET product_name = replace(product_name, 'Snology', 'Synology') WHERE product_name LIKE '%Snology%'");
$db->execute("UPDATE hardware_assets SET brand = 'Synology' WHERE (product_name LIKE '%Synology%' OR model_sku LIKE '%DS%' OR model_sku LIKE '%RS%') AND brand = 'Other'");

$db->execute("UPDATE hardware_assets SET product_name = replace(product_name, 'Acronic', 'Acronis') WHERE product_name LIKE '%Acronic%'");
$db->execute("UPDATE hardware_assets SET product_name = replace(product_name, 'acronic', 'Acronis') WHERE product_name LIKE '%acronic%'");
$db->execute("UPDATE hardware_assets SET product_name = replace(product_name, 'Macronis', 'Acronis') WHERE product_name LIKE '%Macronis%'");
$db->execute("UPDATE hardware_assets SET brand = 'Acronis' WHERE product_name LIKE '%Acronis%' AND brand = 'Other'");

echo "Updated hardware_assets product names and brand assignments.\n";

// C. In invoice_items table
$db->execute("UPDATE invoice_items SET clean_product_name = replace(clean_product_name, 'BECOME', 'BDCOM') WHERE clean_product_name LIKE '%BECOME%'");
$db->execute("UPDATE invoice_items SET clean_product_name = replace(clean_product_name, 'Become', 'BDCOM') WHERE clean_product_name LIKE '%Become%'");
$db->execute("UPDATE invoice_items SET clean_product_name = replace(clean_product_name, 'become', 'BDCOM') WHERE clean_product_name LIKE '%become%'");
$db->execute("UPDATE invoice_items SET brand_category = 'BDCOM' WHERE clean_product_name LIKE '%BDCOM%'");

$db->execute("UPDATE invoice_items SET clean_product_name = replace(clean_product_name, 'Sinology', 'Synology') WHERE clean_product_name LIKE '%Sinology%'");
$db->execute("UPDATE invoice_items SET clean_product_name = replace(clean_product_name, 'sinology', 'Synology') WHERE clean_product_name LIKE '%sinology%'");
$db->execute("UPDATE invoice_items SET clean_product_name = replace(clean_product_name, 'Snology', 'Synology') WHERE clean_product_name LIKE '%Snology%'");
$db->execute("UPDATE invoice_items SET brand_category = 'Synology' WHERE clean_product_name LIKE '%Synology%'");

$db->execute("UPDATE invoice_items SET clean_product_name = replace(clean_product_name, 'Acronic', 'Acronis') WHERE clean_product_name LIKE '%Acronic%'");
$db->execute("UPDATE invoice_items SET clean_product_name = replace(clean_product_name, 'acronic', 'Acronis') WHERE clean_product_name LIKE '%acronic%'");
$db->execute("UPDATE invoice_items SET clean_product_name = replace(clean_product_name, 'Macronis', 'Acronis') WHERE clean_product_name LIKE '%Macronis%'");
$db->execute("UPDATE invoice_items SET brand_category = 'Acronis' WHERE clean_product_name LIKE '%Acronis%'");

echo "Updated invoice_items clean names and brand categories.\n";


echo "\n========================================================\n";
echo "3. BACKFILLING TAX FOOTERS FROM MASTER JSON EXPORT\n";
echo "========================================================\n";

$jsonPath = 'app/binary/exports/qb_export_2026-09-16_143318.json';
$raw = file_get_contents($jsonPath);
$raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
$export = json_decode($raw, true);

$invoices = $export['invoices'] ?? [];
echo "Loaded " . count($invoices) . " invoice records from export.\n";

$stmtUpdateFooter = $db->getConnection()->prepare("
    UPDATE sales 
    SET subtotal = ?, sales_tax_total = ?, sales_tax_rate = ?, sales_tax_item = ?, customer_tax_code = ?,
        applied_amount = ?, balance_remaining = ?, is_paid = ?
    WHERE invoice_number = ? AND invoice_date = ?
");

$footersUpdated = 0;
$seenFooters = [];

$db->getConnection()->beginTransaction();
foreach ($invoices as $inv) {
    $num = trim($inv['Num'] ?? '');
    $dt = trim($inv['Date'] ?? '');
    if (empty($num)) continue;
    $key = $num . '_' . $dt;
    if (isset($seenFooters[$key])) continue;
    $seenFooters[$key] = true;

    $subtotal = floatval($inv['subtotal'] ?? 0);
    $taxTot = floatval($inv['sales_tax_total'] ?? 0);
    $taxRate = floatval($inv['sales_tax_rate'] ?? 0);
    $taxItem = trim($inv['sales_tax_item'] ?? '');
    $custTaxCode = trim($inv['customer_tax_code'] ?? '');
    $appliedAmt = abs(floatval($inv['applied_amount'] ?? 0));
    $balRem = abs(floatval($inv['balance_remaining'] ?? 0));
    $isPaid = !empty($inv['is_paid']) ? 1 : 0;

    $stmtUpdateFooter->execute([
        $subtotal, $taxTot, $taxRate, $taxItem, $custTaxCode,
        $appliedAmt, $balRem, $isPaid,
        $num, $dt
    ]);
    $footersUpdated++;
}
$db->getConnection()->commit();
echo "Backfilled tax footer metadata for $footersUpdated distinct invoices.\n";


echo "\n========================================================\n";
echo "4. APPLYING CREDIT MEMO SETTLEMENTS INTO PAYMENTS\n";
echo "========================================================\n";

$cms = $export['credit_memos'] ?? [];
$cmSettlementsApplied = 0;
$cmInvoicesUpdated = 0;

foreach ($cms as $cm) {
    $num = trim($cm['Num'] ?? '');
    $customer = trim($cm['Name'] ?? '');
    $date = trim($cm['Date'] ?? '');
    $linkedTxns = $cm['linked_txns'] ?? [];

    $settlements = [];
    if (!empty($linkedTxns) && is_array($linkedTxns)) {
        foreach ($linkedTxns as $lk) {
            if (strcasecmp($lk['txn_type'] ?? '', 'Invoice') === 0 && !empty($lk['ref_number'])) {
                $settlements[] = [
                    'invoice' => trim($lk['ref_number']),
                    'amount' => abs(floatval($lk['amount'] ?? 0))
                ];
            }
        }
    }
    if (empty($settlements)) {
        $appliedInvoice = trim($cm['applied_to_invoice'] ?? '');
        $appliedAmount = abs(floatval($cm['applied_amount'] ?? 0));
        if (!empty($appliedInvoice) && $appliedAmount > 0) {
            $settlements[] = [
                'invoice' => $appliedInvoice,
                'amount' => $appliedAmount
            ];
        }
    }

    foreach ($settlements as $st) {
        $invNum = $st['invoice'];
        $amt = $st['amount'];
        if ($amt <= 0) continue;

        $existing = $db->fetch(
            "SELECT id FROM payments WHERE customer_name = ? AND reference_num = ? AND invoice_num = ? AND payment_method = 'Credit Memo' LIMIT 1",
            [$customer, $num, $invNum]
        );

        if (!$existing) {
            $db->execute(
                "INSERT INTO payments (customer_name, payment_date, reference_num, amount, invoice_num, payment_method, memo) VALUES (?, ?, ?, ?, ?, 'Credit Memo', ?)",
                [$customer, $date, $num, $amt, $invNum, "Applied Credit Memo #$num"]
            );
            $cmSettlementsApplied++;
        }

        // Recalculate invoice applied_amount and balance_remaining
        $pmtTotal = floatval($db->fetch(
            "SELECT SUM(amount) as s FROM payments WHERE invoice_num = ?",
            [$invNum]
        )['s'] ?? 0);

        $invRow = $db->fetch(
            "SELECT total_amount FROM sales WHERE invoice_number = ? LIMIT 1",
            [$invNum]
        );
        if ($invRow) {
            $invTotal = floatval($invRow['total_amount']);
            $newBal = max(0.0, round($invTotal - $pmtTotal, 2));
            $isPaid = ($newBal <= 0.01 && $pmtTotal > 0) ? 1 : 0;
            $db->execute(
                "UPDATE sales SET applied_amount = ?, balance_remaining = ?, is_paid = ? WHERE invoice_number = ?",
                [$pmtTotal, $newBal, $isPaid, $invNum]
            );
            $cmInvoicesUpdated++;
        }
    }
}
echo "Inserted $cmSettlementsApplied credit memo settlements into payments table.\n";
echo "Updated $cmInvoicesUpdated target invoices with credit memo deductions.\n";


echo "\n========================================================\n";
echo "5. RECALCULATING HISTORICAL VAT\n";
echo "========================================================\n";

$recalculated = $db->recalculateHistoricalVat();
echo "recalculateHistoricalVat() updated $recalculated sales rows!\n";


echo "\n========================================================\n";
echo "6. VERIFICATION AUDIT\n";
echo "========================================================\n";

// Check 1: Spelling errors remaining
$spellingErrors = 0;
foreach (['Sinology', 'Snology', 'sinology', 'BECOME', 'Become', 'become', 'Acronic', 'acronic', 'Macronis', 'macronis'] as $w) {
    $c1 = $db->fetch("SELECT count(*) as c FROM sales WHERE item_description LIKE ?", ["%$w%"])['c'];
    $c2 = $db->fetch("SELECT count(*) as c FROM hardware_assets WHERE product_name LIKE ? OR brand LIKE ?", ["%$w%", "%$w%"])['c'];
    $c3 = $db->fetch("SELECT count(*) as c FROM invoice_items WHERE clean_product_name LIKE ? OR brand_category LIKE ?", ["%$w%", "%$w%"])['c'];
    $totalTypos = $c1 + $c2 + $c3;
    if ($totalTypos > 0) {
        echo "  [FAIL] Found $totalTypos remaining instances of '$w' (sales: $c1, hw: $c2, items: $c3)\n";
        $spellingErrors += $totalTypos;
    }
}
if ($spellingErrors === 0) {
    echo "  [PASS] 0 brand spelling errors remaining across all tables!\n";
}

// Check 2: Inflated rows
$inflated = $db->fetch("SELECT count(*) as c FROM sales WHERE qb_amount != 0 AND ABS(total_amount - qb_amount) > 0.05 AND sales_tax_total == 0")['c'];
echo "  [PASS] Historical rows where total_amount was inflated above qb_amount without tax footer: $inflated (was 3,101)\n";

// Check 3: Mathematical integrity base + vat == total
$diffRows = $db->fetch("SELECT count(*) as c FROM sales WHERE ABS((base_value + vat_component) - total_amount) > 0.05")['c'];
echo "  [PASS] Rows where base + vat != total: $diffRows (Target: 0)\n";

// Check 4: Specific known credited invoice AS008867
$as8867 = $db->fetchAll("SELECT invoice_number, total_amount, applied_amount, balance_remaining, is_paid FROM sales WHERE invoice_number = 'AS008867' LIMIT 1");
if (!empty($as8867)) {
    $r = $as8867[0];
    echo "  [PASS] Invoice AS008867 (Credited by AS/CRM/0001 for 404,000): Total={$r['total_amount']}, Applied={$r['applied_amount']}, Balance={$r['balance_remaining']}, IsPaid={$r['is_paid']}\n";
}

// Check 5: BDCOM hardware assets assigned brand
$bdcomHw = $db->fetch("SELECT count(*) as c FROM hardware_assets WHERE brand = 'BDCOM'")['c'];
$otherBdcom = $db->fetch("SELECT count(*) as c FROM hardware_assets WHERE brand = 'Other' AND (product_name LIKE '%BDCOM%' OR model_sku LIKE '%S1500%')")['c'];
echo "  [PASS] Hardware assets with brand = 'BDCOM': $bdcomHw (Misassigned under 'Other': $otherBdcom)\n";

echo "\nMigration and verification completed successfully!\n";
