<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$testDbFile = __DIR__ . '/test_full.db';
if (file_exists($testDbFile)) unlink($testDbFile);

copy(__DIR__ . '/../data/sales_bi.db', $testDbFile);

$db = new Database($testDbFile);
$db->initialize();
$db->syncSchema();

$pdo = $db->getConnection();

echo "=== INITIAL STATE IN TEST DB ===\n";
$initStats = $pdo->query("SELECT MIN(invoice_date) min_d, MAX(invoice_date) max_d, COUNT(*) cnt, COUNT(DISTINCT invoice_number) inv_cnt FROM sales")->fetch(PDO::FETCH_ASSOC);
print_r($initStats);

// Function to import customers CSV
function importCustomers($pdo, $csvFile) {
    echo "Importing customers from " . basename($csvFile) . " ...\n";
    $fh = fopen($csvFile, 'r');
    $hdr = fgetcsv($fh, 0, ',', '"', '\\');
    $hdr[0] = preg_replace('/^\xEF\xBB\xBF/', '', $hdr[0]);
    
    $stmt = $pdo->prepare("
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
            bill_address = CASE WHEN excluded.bill_address != '' THEN excluded.bill_address ELSE customer_profiles.bill_address END,
            sales_rep = CASE WHEN excluded.sales_rep != '' THEN excluded.sales_rep ELSE customer_profiles.sales_rep END,
            qb_list_id = excluded.qb_list_id
    ");
    
    $count = 0;
    while (($row = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
        if (count($row) < count($hdr)) continue;
        $c = array_combine($hdr, $row);
        $name = trim($c['Name'] ?? $c['FullName'] ?? '');
        if (empty($name)) continue;
        
        $type = trim($c['CustomerType'] ?? 'End Customer');
        if (empty($type)) $type = 'End Customer';
        
        $stmt->execute([
            $name, $type, trim($c['CompanyName'] ?? ''), trim($c['ContactName'] ?? ''),
            trim($c['Email'] ?? ''), trim($c['Phone'] ?? ''), trim($c['AltPhone'] ?? ''), trim($c['Fax'] ?? ''),
            trim($c['BillAddress'] ?? ''), trim($c['BillCity'] ?? ''), trim($c['BillState'] ?? ''), trim($c['BillZip'] ?? ''), trim($c['BillCountry'] ?? ''),
            trim($c['SalesRep'] ?? ''), (float)str_replace(',', '', $c['Balance'] ?? '0'), (float)str_replace(',', '', $c['TotalBalance'] ?? '0'),
            (float)str_replace(',', '', $c['CreditLimit'] ?? '0'), trim($c['Terms'] ?? ''), trim($c['AccountNumber'] ?? ''),
            (int)($c['IsActive'] ?? 1), trim($c['ListID'] ?? '')
        ]);
        $count++;
    }
    fclose($fh);
    echo "  Imported/Updated $count customers.\n";
}

// Function to import invoices CSV
function importInvoices($db, $pdo, $csvFile) {
    echo "Importing invoices from " . basename($csvFile) . " ...\n";
    $fh = fopen($csvFile, 'r');
    $hdr = fgetcsv($fh, 0, ',', '"', '\\');
    $hdr[0] = preg_replace('/^\xEF\xBB\xBF/', '', $hdr[0]);
    
    $stmt = $pdo->prepare("
        INSERT INTO sales (
            invoice_type, invoice_date, invoice_number, customer_name,
            item_description, tax_code, quantity, qb_amount,
            base_value, vat_component, applied_tax_rate, total_amount,
            product_category, sales_rep_code, po_number, memo, qb_txn_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON CONFLICT(invoice_number, customer_name, item_description, qb_amount) DO NOTHING
    ");
    
    $imported = 0;
    $skipped = 0;
    
    while (($row = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
        if (count($row) < count($hdr)) continue;
        $inv = array_combine($hdr, $row);
        
        $customer = trim($inv['Name'] ?? '');
        $rawAmount = $inv['Amount'] ?? '0';
        $cleanAmount = (float)str_replace(',', '', $rawAmount);
        $txnId = trim($inv['QBTxnID'] ?? '');
        $num = trim($inv['Num'] ?? '');
        
        if (empty($num)) {
            $num = !empty($txnId) ? 'OB-' . $txnId : 'OB-' . substr(md5($customer . $cleanAmount), 0, 8);
        }
        
        if (empty($customer)) {
            $skipped++;
            continue;
        }
        
        $invType = trim($inv['Type'] ?? 'Invoice');
        $rawDate = trim($inv['Date'] ?? date('Y-m-d'));
        $date = date('Y-m-d', strtotime(str_replace('/', '-', $rawDate)));
        $itemDesc = trim($inv['Description'] ?? $inv['Item'] ?? 'Item');
        if (empty($itemDesc)) $itemDesc = 'Item';
        
        $taxCode = trim($inv['Sales Tax Code'] ?? 'Tax');
        
        // Tax rule determination
        $taxRule = $db->getTaxRuleForInvoice($num, $date);
        $rate = $taxRule['rate'];
        
        if ($rate > 0) {
            $base = round($cleanAmount / (1 + $rate), 2);
            $vat = round($cleanAmount - $base, 2);
            $total = $cleanAmount;
        } else {
            $rate = 0.00;
            $base = $cleanAmount;
            $vat = 0.00;
            $total = $cleanAmount;
        }
        
        $qty = (float)($inv['Qty'] ?? 1);
        if ($qty == 0) $qty = 1.0;
        
        $stmt->execute([
            $invType, $date, $num, $customer, $itemDesc, $taxCode,
            $qty, $cleanAmount, $base, $vat, $rate, $total,
            trim($inv['Product Category'] ?? ''), trim($inv['Rep'] ?? ''), trim($inv['PONumber'] ?? ''), trim($inv['Memo'] ?? ''), $txnId
        ]);
        
        $imported++;
        if ($imported % 5000 === 0) echo "    Processed $imported lines...\n";
    }
    fclose($fh);
    echo "  Processed $imported invoice lines ($skipped skipped).\n";
}

// Function to import payments CSV
function importPayments($pdo, $csvFile) {
    echo "Importing payments from " . basename($csvFile) . " ...\n";
    $fh = fopen($csvFile, 'r');
    $hdr = fgetcsv($fh, 0, ',', '"', '\\');
    $hdr[0] = preg_replace('/^\xEF\xBB\xBF/', '', $hdr[0]);
    
    $stmt = $pdo->prepare("
        INSERT INTO payments (customer_name, payment_date, reference_num, amount, invoice_num)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $imported = 0;
    while (($row = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
        if (count($row) < count($hdr)) continue;
        $pay = array_combine($hdr, $row);
        
        $customer = trim($pay['CustomerName'] ?? '');
        $amount = (float)str_replace(',', '', $pay['Amount'] ?? '0');
        if (empty($customer) || $amount <= 0) continue;
        
        $payDate = date('Y-m-d', strtotime(str_replace('/', '-', trim($pay['PaymentDate'] ?? date('Y-m-d')))));
        $ref = trim($pay['ReferenceNum'] ?? '');
        $invNum = trim($pay['InvoiceNum'] ?? '');
        
        $stmt->execute([$customer, $payDate, $ref, $amount, $invNum]);
        $imported++;
    }
    fclose($fh);
    echo "  Imported $imported payments.\n";
}

$pdo->beginTransaction();

// 1. Customers
importCustomers($pdo, 'Exports/qb_export_customers_2026-09-09_153906.csv');
importCustomers($pdo, 'Exports/qb_export_customers_2026-09-09_160211.csv');

// 2. Invoices (Old system first, then new system)
importInvoices($db, $pdo, 'Exports/qb_export_invoices_2026-09-09_153906.csv');
importInvoices($db, $pdo, 'Exports/qb_export_invoices_2026-09-09_160211.csv');

// 3. Payments
importPayments($pdo, 'Exports/qb_export_payments_2026-09-09_153906.csv');
importPayments($pdo, 'Exports/qb_export_payments_2026-09-09_160211.csv');

$pdo->commit();

echo "\n=== FINAL STATS IN TEST DB ===\n";
$finalStats = $pdo->query("SELECT MIN(invoice_date) min_d, MAX(invoice_date) max_d, COUNT(*) cnt, COUNT(DISTINCT invoice_number) inv_cnt FROM sales")->fetch(PDO::FETCH_ASSOC);
print_r($finalStats);

$byYear = $pdo->query("SELECT strftime('%Y', invoice_date) yr, COUNT(*) lines, COUNT(DISTINCT invoice_number) invs, ROUND(SUM(total_amount),2) total FROM sales GROUP BY yr ORDER BY yr")->fetchAll(PDO::FETCH_ASSOC);
echo "Year breakdown:\n";
foreach ($byYear as $y) {
    echo sprintf("  %s: %5d invs, %6d lines, LKR %15s\n", $y['yr'], $y['invs'], $y['lines'], number_format($y['total'], 2));
}
