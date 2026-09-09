<?php
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(__DIR__ . '/../data/sales_bi.db');
$db->ensureCustomerProfileColumns();

$f1 = 'C:\Users\shahe\.gemini\antigravity-ide\brain\ef2543a4-f055-44dc-9f3b-a04596f62e76\.user_uploaded\media_1788952102531.csv';
$f2 = 'C:\Users\shahe\.gemini\antigravity-ide\brain\ef2543a4-f055-44dc-9f3b-a04596f62e76\.user_uploaded\media_1788952102565.csv';

$upsertSql = "
    INSERT INTO customer_profiles (
        customer_name, customer_type, company_name, contact_name, email, phone, alt_phone, fax,
        bill_address, bill_city, bill_state, bill_zip, bill_country,
        sales_rep, current_balance, total_balance, credit_limit, terms, account_number,
        resale_number, vat_number, tin_number, is_vat_registered, tax_item_ref, tax_code_ref,
        is_active, qb_list_id, notes, updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
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
        resale_number = CASE WHEN excluded.resale_number != '' THEN excluded.resale_number ELSE customer_profiles.resale_number END,
        vat_number = CASE WHEN excluded.vat_number != '' THEN excluded.vat_number ELSE customer_profiles.vat_number END,
        tin_number = CASE WHEN excluded.tin_number != '' THEN excluded.tin_number ELSE customer_profiles.tin_number END,
        is_vat_registered = CASE WHEN excluded.is_vat_registered = 1 THEN 1 ELSE customer_profiles.is_vat_registered END,
        tax_item_ref = CASE WHEN excluded.tax_item_ref != '' THEN excluded.tax_item_ref ELSE customer_profiles.tax_item_ref END,
        tax_code_ref = CASE WHEN excluded.tax_code_ref != '' THEN excluded.tax_code_ref ELSE customer_profiles.tax_code_ref END,
        is_active = excluded.is_active,
        qb_list_id = excluded.qb_list_id,
        notes = CASE WHEN excluded.notes != '' THEN excluded.notes ELSE customer_profiles.notes END,
        customer_type = CASE WHEN customer_profiles.is_verified = 1 THEN customer_profiles.customer_type ELSE (CASE WHEN excluded.customer_type != '' THEN excluded.customer_type ELSE customer_profiles.customer_type END) END,
        updated_at = CURRENT_TIMESTAMP
";

$stmt = $db->getConnection()->prepare($upsertSql);

function importCSV($path, $label, $stmt, $db) {
    if (!file_exists($path)) {
        echo "File $path does not exist.\n";
        return 0;
    }
    $fp = fopen($path, 'r');
    $header = fgetcsv($fp);
    $header[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $header[0]);
    
    $imported = 0;
    $db->getConnection()->beginTransaction();
    
    while (($row = fgetcsv($fp)) !== false) {
        if (empty($row[0])) continue;
        $data = array_combine($header, $row);
        $name = trim($data['FullName'] ?? $data['Name'] ?? '');
        if (empty($name)) continue;
        
        $type = trim($data['CustomerType'] ?? 'End Customer');
        $company = trim($data['CompanyName'] ?? '');
        $contact = trim($data['ContactName'] ?? '');
        $email = trim($data['Email'] ?? '');
        $phone = trim($data['Phone'] ?? '');
        $altPhone = trim($data['AltPhone'] ?? '');
        $fax = trim($data['Fax'] ?? '');
        $billAddr = trim($data['BillAddress'] ?? '');
        $billCity = trim($data['BillCity'] ?? '');
        $billState = trim($data['BillState'] ?? '');
        $billZip = trim($data['BillZip'] ?? '');
        $billCountry = trim($data['BillCountry'] ?? '');
        $salesRep = trim($data['SalesRep'] ?? '');
        $balance = (float)($data['Balance'] ?? 0);
        $totBalance = (float)($data['TotalBalance'] ?? 0);
        $creditLimit = (float)($data['CreditLimit'] ?? 0);
        $terms = trim($data['Terms'] ?? '');
        $acctNum = trim($data['AccountNumber'] ?? '');
        $resaleNum = trim($data['ResaleNumber'] ?? '');
        $vatNum = trim($data['VatNumber'] ?? '');
        $tinNum = trim($data['TinNumber'] ?? '');
        $isVat = !empty($data['IsVatRegistered']) ? 1 : 0;
        $taxItemRef = trim($data['TaxItemRef'] ?? '');
        $taxCodeRef = trim($data['TaxCodeRef'] ?? '');
        $isActive = isset($data['IsActive']) ? (int)$data['IsActive'] : 1;
        $listId = trim($data['ListID'] ?? '');
        $notes = trim($data['Notes'] ?? '');
        
        // Auto-detect VAT/TIN from text
        $fullSearch = "$resaleNum $billAddr $billCity $billState $billZip $company $notes";
        if (empty($vatNum)) {
            if (preg_match('/(?:VAT|SVAT)\s*(?:No\.?|#|Reg(?:istration)?)?\s*[:.-]?\s*([0-9]{9}(?:-[0-9]{3,4})?|[0-9A-Z\-\/]{7,})/i', $fullSearch, $m)) {
                $vatNum = trim($m[1]);
                $isVat = 1;
            } elseif (preg_match('/\b([0-9]{9}-7000?)\b/', $fullSearch, $m)) {
                $vatNum = trim($m[1]);
                $isVat = 1;
            }
        }
        if (empty($tinNum)) {
            if (preg_match('/(?:TIN)\s*(?:No\.?|#)?\s*[:.-]?\s*([0-9]{9}|[0-9A-Z\-\/]{7,})/i', $fullSearch, $m)) {
                $tinNum = trim($m[1]);
                if (empty($vatNum)) {
                    $isVat = 1;
                }
            }
        }
        if (!empty($vatNum) || !empty($tinNum)) {
            $isVat = 1;
        }
        
        $stmt->execute([
            $name, $type, $company, $contact, $email, $phone, $altPhone, $fax,
            $billAddr, $billCity, $billState, $billZip, $billCountry,
            $salesRep, $balance, $totBalance, $creditLimit, $terms, $acctNum,
            $resaleNum, $vatNum, $tinNum, $isVat, $taxItemRef, $taxCodeRef,
            $isActive, $listId, $notes
        ]);
        $imported++;
    }
    $db->getConnection()->commit();
    fclose($fp);
    echo "$label: Processed $imported records.\n";
    return $imported;
}

echo "Starting Customer Ingestion...\n";
importCSV($f1, "Old System Customers", $stmt, $db);
importCSV($f2, "New System Customers", $stmt, $db);

$tot = $db->fetch("SELECT COUNT(*) as c, SUM(is_vat_registered) as vat_c, COUNT(NULLIF(vat_number, '')) as vat_num_c, COUNT(NULLIF(tin_number, '')) as tin_c FROM customer_profiles");
echo "Total in customer_profiles: {$tot['c']} customers, {$tot['vat_c']} marked VAT registered, {$tot['vat_num_c']} with VAT numbers, {$tot['tin_c']} with TIN numbers.\n";

echo "Recalculating historical invoice VAT categorization...\n";
$recalc = $db->recalculateHistoricalVat();
echo "Recalculation complete: " . json_encode($recalc) . "\n";
