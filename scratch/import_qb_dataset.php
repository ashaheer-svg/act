<?php
/**
 * Master QuickBooks Data Ingestion Script
 *
 * Imports customers, invoices, and payments from the exported QuickBooks dataset
 * into data/sales_bi.db inside a single high-performance SQLite transaction.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

echo "=== QuickBooks Full Dataset Import ===\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n";

$db = new Database(DATABASE_PATH);
$db->initialize();
$db->initializeSettings();
$db->ensureCustomerProfileColumns();

// Paths to export files
$customersCsv = __DIR__ . '/../app/exports/qb_export_customers_2026-09-05_125436.csv';
$invoicesCsv  = __DIR__ . '/../app/exports/qb_export_invoices_2026-09-05_125436.csv';
$paymentsCsv  = __DIR__ . '/../app/exports/qb_export_payments_2026-09-05_125436.csv';

if (!file_exists($customersCsv) || !file_exists($invoicesCsv) || !file_exists($paymentsCsv)) {
    die("ERROR: One or more export CSV files not found in app/exports/\n");
}

$startTime = microtime(true);

$pdo = $db->getConnection();
$pdo->beginTransaction();

try {
    // -------------------------------------------------------------
    // Step 1: Ensure Sales Reps in sales_rep_mapping
    // -------------------------------------------------------------
    echo "\n[1/5] Registering Sales Reps...\n";
    $knownReps = [
        'AS' => 'Sales Rep AS',
        'A'  => 'Sales Rep A',
        'AD' => 'Sales Rep AD',
        'AR' => 'Sales Rep AR',
        'SM' => 'Sales Rep SM',
        'SA' => 'Sales Rep SA'
    ];
    $repStmt = $pdo->prepare("INSERT OR IGNORE INTO sales_rep_mapping (rep_code, rep_name) VALUES (?, ?)");
    foreach ($knownReps as $code => $name) {
        $repStmt->execute([$code, $name]);
    }
    echo "  Sales reps registered.\n";

    // -------------------------------------------------------------
    // Step 2: Import Customer Profiles
    // -------------------------------------------------------------
    echo "\n[2/5] Importing Customer Profiles...\n";
    $custUpsertStmt = $pdo->prepare("
        INSERT INTO customer_profiles (
            customer_name, customer_type, company_name, contact_name, email, phone, alt_phone, fax,
            bill_address, bill_city, bill_state, bill_zip, bill_country,
            sales_rep, current_balance, total_balance, credit_limit, terms, account_number,
            is_active, qb_list_id, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ON CONFLICT(customer_name) DO UPDATE SET
            company_name = CASE WHEN excluded.company_name != '' THEN excluded.company_name ELSE customer_profiles.company_name END,
            contact_name = CASE WHEN excluded.contact_name != '' THEN excluded.contact_name ELSE customer_profiles.contact_name END,
            email = CASE WHEN excluded.email != '' THEN excluded.email ELSE customer_profiles.email END,
            phone = CASE WHEN excluded.phone != '' THEN excluded.phone ELSE customer_profiles.phone END,
            alt_phone = CASE WHEN excluded.alt_phone != '' THEN excluded.alt_phone ELSE customer_profiles.alt_phone END,
            fax = CASE WHEN excluded.fax != '' THEN excluded.fax ELSE customer_profiles.fax END,
            bill_address = CASE WHEN excluded.bill_address != '' THEN excluded.bill_address ELSE customer_profiles.bill_address END,
            bill_city = CASE WHEN excluded.bill_city != '' THEN excluded.bill_city ELSE customer_profiles.bill_city END,
            bill_state = CASE WHEN excluded.bill_state != '' THEN excluded.bill_state ELSE customer_profiles.bill_state END,
            bill_zip = CASE WHEN excluded.bill_zip != '' THEN excluded.bill_zip ELSE customer_profiles.bill_zip END,
            bill_country = CASE WHEN excluded.bill_country != '' THEN excluded.bill_country ELSE customer_profiles.bill_country END,
            sales_rep = CASE WHEN excluded.sales_rep != '' THEN excluded.sales_rep ELSE customer_profiles.sales_rep END,
            current_balance = excluded.current_balance,
            total_balance = excluded.total_balance,
            credit_limit = excluded.credit_limit,
            terms = CASE WHEN excluded.terms != '' THEN excluded.terms ELSE customer_profiles.terms END,
            account_number = CASE WHEN excluded.account_number != '' THEN excluded.account_number ELSE customer_profiles.account_number END,
            is_active = excluded.is_active,
            qb_list_id = excluded.qb_list_id,
            customer_type = CASE WHEN customer_profiles.is_verified = 1 THEN customer_profiles.customer_type ELSE (CASE WHEN excluded.customer_type != '' THEN excluded.customer_type ELSE customer_profiles.customer_type END) END,
            updated_at = CURRENT_TIMESTAMP
    ");

    $fhCust = fopen($customersCsv, 'r');
    $custHdr = fgetcsv($fhCust);
    if ($custHdr) {
        $custHdr[0] = preg_replace('/^\xEF\xBB\xBF/', '', $custHdr[0]);
    }

    $customersImported = 0;
    while (($row = fgetcsv($fhCust)) !== false) {
        if (count($row) < count($custHdr)) continue;
        $c = array_combine($custHdr, $row);

        $name = trim($c['Name'] ?? $c['FullName'] ?? '');
        if (empty($name)) continue;

        $type = trim($c['CustomerType'] ?? 'End Customer');
        if (empty($type)) $type = 'End Customer';

        $company = trim($c['CompanyName'] ?? '');
        $contact = trim($c['ContactName'] ?? '');
        $email = trim($c['Email'] ?? '');
        $phone = trim($c['Phone'] ?? '');
        $altPhone = trim($c['AltPhone'] ?? '');
        $fax = trim($c['Fax'] ?? '');
        $billAddr = trim($c['BillAddress'] ?? '');
        $billCity = trim($c['BillCity'] ?? '');
        $billState = trim($c['BillState'] ?? '');
        $billZip = trim($c['BillZip'] ?? '');
        $billCountry = trim($c['BillCountry'] ?? '');
        $salesRep = trim($c['SalesRep'] ?? '');
        $balance = (float)str_replace(',', '', $c['Balance'] ?? '0');
        $totBalance = (float)str_replace(',', '', $c['TotalBalance'] ?? (string)$balance);
        $creditLimit = (float)str_replace(',', '', $c['CreditLimit'] ?? '0');
        $terms = trim($c['Terms'] ?? '');
        $acctNum = trim($c['AccountNumber'] ?? '');
        $isActive = (int)($c['IsActive'] ?? 1);
        $listId = trim($c['ListID'] ?? '');

        $custUpsertStmt->execute([
            $name, $type, $company, $contact, $email, $phone, $altPhone, $fax,
            $billAddr, $billCity, $billState, $billZip, $billCountry,
            $salesRep, $balance, $totBalance, $creditLimit, $terms, $acctNum,
            $isActive, $listId
        ]);
        $customersImported++;
    }
    fclose($fhCust);
    echo "  Successfully processed $customersImported customer profiles.\n";

    // -------------------------------------------------------------
    // Step 3: Import Invoices (17,649 lines)
    // -------------------------------------------------------------
    echo "\n[3/5] Importing Invoices & Sales Lines...\n";
    $invInsertStmt = $pdo->prepare("
        INSERT INTO sales (
            invoice_type, invoice_date, invoice_number, customer_name,
            item_description, tax_code, quantity, qb_amount,
            base_value, vat_component, applied_tax_rate, total_amount,
            product_category, sales_rep_code, po_number, memo, qb_txn_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON CONFLICT(invoice_number, customer_name, item_description, qb_amount) DO NOTHING
    ");

    $fhInv = fopen($invoicesCsv, 'r');
    $invHdr = fgetcsv($fhInv);
    if ($invHdr) {
        $invHdr[0] = preg_replace('/^\xEF\xBB\xBF/', '', $invHdr[0]);
    }

    $invoicesImported = 0;
    $invoicesSkipped = 0;
    $taxRateCache = [];
    $defaultVatRate = (float)$db->getSetting('vat_rate', '0.18');

    while (($row = fgetcsv($fhInv)) !== false) {
        if (count($row) < count($invHdr)) continue;
        $inv = array_combine($invHdr, $row);

        $customer = trim($inv['Name'] ?? '');
        $rawAmount = $inv['Amount'] ?? '0';
        $cleanAmount = (float)str_replace(',', '', $rawAmount);
        $txnId = trim($inv['QBTxnID'] ?? '');

        $num = trim($inv['Num'] ?? '');
        if (empty($num)) {
            // Opening balance or journal invoice without explicit document number
            if (!empty($txnId)) {
                $num = 'OB-' . $txnId;
            } else {
                $num = 'OB-' . substr(md5($customer . $cleanAmount), 0, 8);
            }
        }

        if (empty($customer)) {
            $invoicesSkipped++;
            continue;
        }

        $invType = trim($inv['Type'] ?? 'Invoice');
        $rawDate = trim($inv['Date'] ?? date('Y-m-d'));
        $date = date('Y-m-d', strtotime(str_replace('/', '-', $rawDate)));

        $itemDesc = trim($inv['Description'] ?? $inv['Item'] ?? 'Item');
        if (empty($itemDesc)) $itemDesc = 'Item';

        $taxCode = trim($inv['Sales Tax Code'] ?? 'Taxable Sales');

        // Calculate VAT component
        if (stripos($taxCode, 'Non') !== false || stripos($taxCode, 'Zero') !== false || stripos($taxCode, 'Exempt') !== false) {
            $rate = 0.00;
            $base = $cleanAmount;
            $vat = 0.00;
            $total = $cleanAmount;
        } else {
            if (!isset($taxRateCache[$date])) {
                $taxRateCache[$date] = $db->getTaxRateForDate($date);
            }
            $rate = $taxRateCache[$date];
            $base = round($cleanAmount / (1 + $rate), 2);
            $vat = round($cleanAmount - $base, 2);
            $total = $cleanAmount;
        }

        $qty = (float)($inv['Qty'] ?? 1);
        if ($qty == 0) $qty = 1.0;

        $category = trim($inv['Product Category'] ?? '');
        $rep = trim($inv['Rep'] ?? '');
        $poNumber = trim($inv['PONumber'] ?? '');
        $memo = trim($inv['Memo'] ?? '');

        $invInsertStmt->execute([
            $invType,
            $date,
            $num,
            $customer,
            $itemDesc,
            $taxCode,
            $qty,
            $cleanAmount,
            $base,
            $vat,
            $rate,
            $total,
            $category,
            $rep,
            $poNumber,
            $memo,
            $txnId
        ]);

        $invoicesImported++;
        if ($invoicesImported % 5000 === 0) {
            echo "    Processed $invoicesImported invoice lines...\n";
        }
    }
    fclose($fhInv);
    echo "  Successfully processed $invoicesImported invoice lines ($invoicesSkipped skipped).\n";

    // -------------------------------------------------------------
    // Step 4: Import Payments (2,703 rows)
    // -------------------------------------------------------------
    echo "\n[4/5] Importing Payments...\n";
    $payInsertStmt = $pdo->prepare("
        INSERT INTO payments (customer_name, payment_date, reference_num, amount, invoice_num)
        VALUES (?, ?, ?, ?, ?)
    ");

    $fhPay = fopen($paymentsCsv, 'r');
    $payHdr = fgetcsv($fhPay);
    if ($payHdr) {
        $payHdr[0] = preg_replace('/^\xEF\xBB\xBF/', '', $payHdr[0]);
    }

    $paymentsImported = 0;
    $paymentsLinked = 0;
    $paymentsToMatch = [];

    while (($row = fgetcsv($fhPay)) !== false) {
        if (count($row) < count($payHdr)) continue;
        $pay = array_combine($payHdr, $row);

        $customer = trim($pay['CustomerName'] ?? '');
        $rawAmount = $pay['Amount'] ?? '0';
        $amount = (float)str_replace(',', '', $rawAmount);

        if (empty($customer) || $amount <= 0) {
            continue;
        }

        $rawDate = trim($pay['PaymentDate'] ?? date('Y-m-d'));
        $payDate = date('Y-m-d', strtotime(str_replace('/', '-', $rawDate)));
        $ref = trim($pay['ReferenceNum'] ?? '');
        $invoiceNum = trim($pay['InvoiceNum'] ?? '');

        $payInsertStmt->execute([
            $customer,
            $payDate,
            $ref,
            $amount,
            $invoiceNum
        ]);

        if (!empty($invoiceNum)) {
            $paymentsToMatch[] = [
                'customer' => $customer,
                'invoice'  => $invoiceNum,
                'pay_date' => $payDate
            ];
            $paymentsLinked++;
        }

        $paymentsImported++;
    }
    fclose($fhPay);
    echo "  Successfully imported $paymentsImported payments ($paymentsLinked linked to invoice #s).\n";

    // -------------------------------------------------------------
    // Step 5: Settle Invoice Payments (paid_date & days_to_pay)
    // -------------------------------------------------------------
    echo "\n[5/5] Reconciling invoice payments and settlement dates...\n";
    $findInvStmt = $pdo->prepare("
        SELECT invoice_date FROM sales
        WHERE invoice_number = ? AND customer_name = ?
        LIMIT 1
    ");

    $updatePaidStmt = $pdo->prepare("
        UPDATE sales
        SET paid_date = ?, days_to_pay = ?
        WHERE invoice_number = ? AND customer_name = ?
          AND (paid_date IS NULL OR paid_date > ?)
    ");

    $reconciledCount = 0;
    foreach ($paymentsToMatch as $p) {
        $findInvStmt->execute([$p['invoice'], $p['customer']]);
        $invRow = $findInvStmt->fetch(PDO::FETCH_ASSOC);
        if ($invRow) {
            $invTime = strtotime($invRow['invoice_date']);
            $payTime = strtotime($p['pay_date']);
            $days = (int)round(($payTime - $invTime) / 86400);
            if ($days < 0) $days = 0;

            $updatePaidStmt->execute([
                $p['pay_date'],
                $days,
                $p['invoice'],
                $p['customer'],
                $p['pay_date']
            ]);
            $reconciledCount++;
        }
    }
    echo "  Reconciled $reconciledCount invoice settlements.\n";

    // Ensure all customers mentioned in sales are in customer_profiles
    $pdo->exec("
        INSERT OR IGNORE INTO customer_profiles (customer_name, customer_type, is_active)
        SELECT DISTINCT customer_name, 'End Customer', 1 FROM sales
    ");

    // Update settings
    $now = date('Y-m-d H:i:s');
    $summaryText = "Imported $invoicesImported invoices, $paymentsImported payments, $customersImported customer profiles at $now";
    
    $setStmt = $pdo->prepare("
        INSERT INTO settings (setting_key, setting_value, updated_at)
        VALUES (?, ?, CURRENT_TIMESTAMP)
        ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value, updated_at = CURRENT_TIMESTAMP
    ");
    $setStmt->execute(['last_qb_sync', $now]);
    $setStmt->execute(['last_qb_sync_summary', $summaryText]);

    // Commit Transaction
    $pdo->commit();

    $elapsed = round(microtime(true) - $startTime, 2);
    echo "\n=== Import Complete Successfully in {$elapsed}s ===\n";
    echo "Summary:\n";
    echo "- Customer Profiles: $customersImported\n";
    echo "- Invoices / Sales Lines: $invoicesImported\n";
    echo "- Payments: $paymentsImported (Linked: $paymentsLinked)\n";
    echo "- Invoices Reconciled: $reconciledCount\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n[ERROR] Import failed: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
