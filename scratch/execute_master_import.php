<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

echo "=== EXECUTING MASTER 2009-2026 INGESTION INTO data/sales_bi.db ===\n";
$start = microtime(true);

$db = new Database(DATABASE_PATH);
$db->initialize();
$db->syncSchema();

$pdo = $db->getConnection();
$pdo->beginTransaction();

try {
    // 1. Customers Importer
    function importCustomersCsv($pdo, string $csvFile): int {
        echo "Importing customers: " . basename($csvFile) . " ... ";
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
        
        $cnt = 0;
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
            $cnt++;
        }
        fclose($fh);
        echo "OK ($cnt records)\n";
        return $cnt;
    }

    // 2. Invoices Importer
    function importInvoicesCsv($db, $pdo, string $csvFile): array {
        echo "Importing invoices: " . basename($csvFile) . " ... \n";
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
            if ($imported % 5000 === 0) {
                echo "  Processed $imported lines...\n";
            }
        }
        fclose($fh);
        echo "  Finished: $imported lines processed ($skipped skipped).\n";
        return ['imported' => $imported, 'skipped' => $skipped];
    }

    // 3. Payments Importer
    function importPaymentsCsv($pdo, string $csvFile): int {
        echo "Importing payments: " . basename($csvFile) . " ... ";
        $fh = fopen($csvFile, 'r');
        $hdr = fgetcsv($fh, 0, ',', '"', '\\');
        $hdr[0] = preg_replace('/^\xEF\xBB\xBF/', '', $hdr[0]);
        
        $stmt = $pdo->prepare("
            INSERT INTO payments (customer_name, payment_date, reference_num, amount, invoice_num)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $cnt = 0;
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
            $cnt++;
        }
        fclose($fh);
        echo "OK ($cnt records)\n";
        return $cnt;
    }

    importCustomersCsv($pdo, 'Exports/qb_export_customers_2026-09-09_153906.csv');
    importCustomersCsv($pdo, 'Exports/qb_export_customers_2026-09-09_160211.csv');

    importInvoicesCsv($db, $pdo, 'Exports/qb_export_invoices_2026-09-09_153906.csv');
    importInvoicesCsv($db, $pdo, 'Exports/qb_export_invoices_2026-09-09_160211.csv');

    importPaymentsCsv($pdo, 'Exports/qb_export_payments_2026-09-09_153906.csv');
    importPaymentsCsv($pdo, 'Exports/qb_export_payments_2026-09-09_160211.csv');

    // Settle payments & calculate days_to_pay across all payments
    echo "Reconciling settlement dates from payments to invoices...\n";
    $pdo->exec("
        UPDATE sales
        SET paid_date = (
            SELECT MIN(payment_date) FROM payments 
            WHERE payments.customer_name = sales.customer_name 
              AND (payments.invoice_num = sales.invoice_number OR payments.reference_num = sales.invoice_number)
        )
        WHERE paid_date IS NULL
          AND EXISTS (
            SELECT 1 FROM payments 
            WHERE payments.customer_name = sales.customer_name 
              AND (payments.invoice_num = sales.invoice_number OR payments.reference_num = sales.invoice_number)
          )
    ");

    $pdo->exec("
        UPDATE sales
        SET days_to_pay = CAST((julianday(paid_date) - julianday(invoice_date)) AS INTEGER)
        WHERE paid_date IS NOT NULL AND days_to_pay IS NULL
    ");

    $pdo->commit();

    echo "Calculating statutory VAT & IRD treatment across whole database...\n";
    $db->recalculateHistoricalVat();

    $elapsed = round(microtime(true) - $start, 2);
    echo "=== MASTER INGESTION FINISHED IN {$elapsed}s ===\n";

    $stats = $pdo->query("SELECT MIN(invoice_date) min_d, MAX(invoice_date) max_d, COUNT(*) cnt, COUNT(DISTINCT invoice_number) invs, ROUND(SUM(total_amount),2) total FROM sales")->fetch(PDO::FETCH_ASSOC);
    print_r($stats);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
