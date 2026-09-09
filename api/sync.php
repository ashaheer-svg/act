<?php
/**
 * QuickBooks Desktop Automated Sync API Endpoint
 *
 * Receives read-only extracted invoices (with full line item descriptions & serial numbers)
 * and payment records from the local Windows SalesBISync tool.
 */

header('Content-Type: application/json; charset=utf-8');

// Ensure root config and classes are loaded
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$db->initialize();
$db->initializeSettings();

// Authenticate API Key
$headers = function_exists('getallheaders') ? getallheaders() : [];
$apiKey = $headers['X-API-KEY'] ?? $headers['x-api-key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';

$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (empty($apiKey) && !empty($authHeader)) {
    if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
        $apiKey = $matches[1];
    }
}

if (empty($apiKey)) {
    $apiKey = $_POST['api_key'] ?? $_GET['api_key'] ?? '';
}

$configuredKey = $db->getSetting('api_secret_key');

if (empty($configuredKey) || empty($apiKey) || !hash_equals($configuredKey, $apiKey)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Invalid or missing API key.']);
    exit;
}

// Health Check & Status via GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $salesCount = $db->fetch("SELECT count(*) as c FROM sales")['c'] ?? 0;
    $payCount = $db->fetch("SELECT count(*) as c FROM payments")['c'] ?? 0;
    $custCount = $db->fetch("SELECT count(*) as c FROM customer_profiles")['c'] ?? 0;
    $itemCount = $db->fetch("SELECT count(*) as c FROM invoice_items")['c'] ?? 0;
    $hwCount = $db->fetch("SELECT count(*) as c FROM hardware_assets")['c'] ?? 0;
    $subCount = $db->fetch("SELECT count(*) as c FROM software_subscriptions")['c'] ?? 0;
    $lastSync = $db->getSetting('last_qb_sync', 'Never');
    $lastSummary = $db->getSetting('last_qb_sync_summary', 'No sync recorded');

    echo json_encode([
        'success' => true,
        'status' => 'online',
        'service' => 'QuickBooks Desktop Sync API',
        'version' => defined('APP_VERSION') ? APP_VERSION : '1.0.0',
        'last_sync' => $lastSync,
        'last_sync_summary' => $lastSummary,
        'database_records' => [
            'sales_invoices' => (int)$salesCount,
            'payments' => (int)$payCount,
            'customer_profiles' => (int)$custCount,
            'invoice_items' => (int)$itemCount,
            'hardware_assets' => (int)$hwCount,
            'software_subscriptions' => (int)$subCount
        ]
    ]);
    exit;
}

// Method check for Data Ingestion
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed. POST required for sync data.']);
    exit;
}

// Parse JSON Body
$rawInput = file_get_contents('php://input');
$payload = json_decode($rawInput, true);

if (!$payload || !is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

$invoices = $payload['invoices'] ?? [];
$payments = $payload['payments'] ?? [];
$customers = $payload['customers'] ?? [];

$invoicesImported = 0;
$invoicesSkipped = 0;
$paymentsImported = 0;
$customersImported = 0;
$errors = [];

try {
    $db->syncSchema();
    $db->beginTransaction();

    // 1. Process Customers First (Ensure VAT registration status is available for invoices)
    if (!empty($customers)) {
        $db->ensureCustomerProfileColumns();

        $custUpsertStmt = "
            INSERT INTO customer_profiles (
                customer_name, customer_type, company_name, contact_name, email, phone, alt_phone, fax,
                bill_address, bill_city, bill_state, bill_zip, bill_country,
                sales_rep, current_balance, total_balance, credit_limit, terms, account_number,
                resale_number, vat_number, tin_number, is_vat_registered, tax_item_ref, tax_code_ref,
                is_active, qb_list_id, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
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
                customer_type = CASE WHEN customer_profiles.is_verified = 1 THEN customer_profiles.customer_type ELSE (CASE WHEN excluded.customer_type != '' THEN excluded.customer_type ELSE customer_profiles.customer_type END) END,
                updated_at = CURRENT_TIMESTAMP
        ";

        foreach ($customers as $c) {
            $name = trim($c['name'] ?? $c['Name'] ?? $c['full_name'] ?? '');
            if (empty($name)) continue;

            $type = trim($c['customer_type'] ?? $c['CustomerType'] ?? 'End Customer');
            $company = trim($c['company_name'] ?? $c['CompanyName'] ?? '');
            $contact = trim($c['contact_name'] ?? $c['ContactName'] ?? '');
            if (empty($contact)) {
                $firstName = trim($c['first_name'] ?? $c['FirstName'] ?? '');
                $lastName = trim($c['last_name'] ?? $c['LastName'] ?? '');
                $contact = trim("$firstName $lastName");
            }
            $email = trim($c['email'] ?? $c['Email'] ?? '');
            $phone = trim($c['phone'] ?? $c['Phone'] ?? '');
            $altPhone = trim($c['alt_phone'] ?? $c['AltPhone'] ?? '');
            $fax = trim($c['fax'] ?? $c['Fax'] ?? '');
            $billAddr = trim($c['bill_address'] ?? $c['BillAddress'] ?? '');
            $billCity = trim($c['bill_city'] ?? $c['BillCity'] ?? '');
            $billState = trim($c['bill_state'] ?? $c['BillState'] ?? '');
            $billZip = trim($c['bill_zip'] ?? $c['BillZip'] ?? '');
            $billCountry = trim($c['bill_country'] ?? $c['BillCountry'] ?? '');
            $salesRep = trim($c['sales_rep'] ?? $c['SalesRep'] ?? '');
            $balance = floatval($c['balance'] ?? $c['Balance'] ?? 0);
            $totBalance = floatval($c['total_balance'] ?? $c['TotalBalance'] ?? $balance);
            $creditLimit = floatval($c['credit_limit'] ?? $c['CreditLimit'] ?? 0);
            $terms = trim($c['terms'] ?? $c['Terms'] ?? '');
            $acctNum = trim($c['account_number'] ?? $c['AccountNumber'] ?? '');
            $isActive = !empty($c['is_active']) ? 1 : 0;
            $listId = trim($c['list_id'] ?? $c['ListID'] ?? '');

            $resaleNum = trim($c['resale_number'] ?? $c['ResaleNumber'] ?? '');
            $vatNum = trim($c['vat_number'] ?? $c['VatNumber'] ?? '');
            $tinNum = trim($c['tin_number'] ?? $c['TinNumber'] ?? '');
            $isVat = !empty($c['is_vat_registered']) ? 1 : 0;
            $taxItemRef = trim($c['tax_item_ref'] ?? $c['TaxItemRef'] ?? '');
            $taxCodeRef = trim($c['tax_code_ref'] ?? $c['TaxCodeRef'] ?? '');

            // Auto-detect VAT/TIN from text if not explicitly provided
            if (empty($vatNum) && empty($tinNum)) {
                $fullSearch = "$resaleNum $billAddr $billCity $billState $billZip $company " . ($c['notes'] ?? '');
                if (preg_match('/(?:VAT|SVAT)\s*(?:No\.?|#|Reg(?:istration)?)?\s*[:.-]?\s*([0-9]{9}(?:-[0-9]{4})?|[0-9A-Z\-\/]{7,})/i', $fullSearch, $m)) {
                    $vatNum = trim($m[1]);
                    $isVat = 1;
                } elseif (preg_match('/\b([0-9]{9}-7000)\b/', $fullSearch, $m)) {
                    $vatNum = trim($m[1]);
                    $isVat = 1;
                }
                if (preg_match('/(?:TIN)\s*(?:No\.?|#)?\s*[:.-]?\s*([0-9]{9}|[0-9A-Z\-\/]{7,})/i', $fullSearch, $m)) {
                    $tinNum = trim($m[1]);
                    if (empty($vatNum) && !empty($tinNum)) {
                        $isVat = 1;
                    }
                }
            }

            $db->execute($custUpsertStmt, [
                $name, $type, $company, $contact, $email, $phone, $altPhone, $fax,
                $billAddr, $billCity, $billState, $billZip, $billCountry,
                $salesRep, $balance, $totBalance, $creditLimit, $terms, $acctNum,
                $resaleNum, $vatNum, $tinNum, $isVat, $taxItemRef, $taxCodeRef,
                $isActive, $listId
            ]);
            $customersImported++;
        }
    }

    // 2. Process Invoices
    if (!empty($invoices)) {
        // Preload customer VAT registration cache
        $custProfiles = $db->fetchAll("SELECT customer_name, is_vat_registered FROM customer_profiles");
        $custVatMap = [];
        foreach ($custProfiles as $cp) {
            $custVatMap[$cp['customer_name']] = (int)($cp['is_vat_registered'] ?? 0);
        }

        $insertInvoiceStmt = "
            INSERT INTO sales (
                invoice_type, invoice_date, invoice_number, customer_name,
                item_description, tax_code, quantity, qb_amount,
                base_value, vat_component, applied_tax_rate, total_amount,
                product_category, sales_rep_code, po_number, memo, qb_txn_id, vat_treatment,
                subtotal, sales_tax_total, sales_tax_rate, sales_tax_item, customer_tax_code,
                applied_amount, balance_remaining, is_paid, is_pending, due_date, ship_date, terms, unit_price
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";

        foreach ($invoices as $inv) {
            $num = trim($inv['Num'] ?? $inv['invoice_number'] ?? '');
            $customer = trim($inv['Name'] ?? $inv['customer_name'] ?? '');
            
            // Item description: preserve full description including serial numbers
            $itemDesc = trim($inv['Description'] ?? $inv['Item'] ?? $inv['item_description'] ?? 'Item');
            $rawAmount = $inv['Amount'] ?? $inv['amount'] ?? 0;
            $cleanAmount = floatval(str_replace(',', '', $rawAmount));

            if (empty($num) || empty($customer)) {
                $invoicesSkipped++;
                continue;
            }

            // Check duplicate
            $existing = $db->fetch(
                "SELECT id FROM sales WHERE invoice_number = ? AND customer_name = ? AND item_description = ? AND qb_amount = ? LIMIT 1",
                [$num, $customer, $itemDesc, $cleanAmount]
            );

            if ($existing) {
                $invoicesSkipped++;
                continue;
            }

            // Date parsing
            $rawDate = $inv['Date'] ?? $inv['invoice_date'] ?? date('Y-m-d');
            $date = date('Y-m-d', strtotime(str_replace('/', '-', $rawDate)));

            // Dynamic VAT calculation based on invoice sequence range & customer registration
            $taxCode = trim($inv['Sales Tax Code'] ?? $inv['tax_code'] ?? 'Taxable Sales');
            $rule = $db->getTaxRuleForInvoice($num, $date);
            $rate = $rule['rate'];
            $isVatReg = $custVatMap[$customer] ?? 0;

            if ($rate <= 0 || $cleanAmount == 0 || stripos($taxCode, 'Non') !== false || stripos($taxCode, 'Zero') !== false || stripos($taxCode, 'Exempt') !== false) {
                $base = $cleanAmount;
                $vat = 0.00;
                $total = $cleanAmount;
                $appliedRate = 0.00;
                $vatTreatment = 'VAT_EXEMPT';
            } else {
                $isVatLine = (bool)preg_match('/^(VAT|Value Added Tax|\d+%\s*VAT)/i', $itemDesc);
                if ($isVatLine) {
                    $base = 0.00;
                    $vat = $cleanAmount;
                    $total = $cleanAmount;
                    $appliedRate = $rate;
                    $vatTreatment = 'VAT_EXCLUSIVE_BREAKUP';
                } elseif ($isVatReg == 1) {
                    // Customer IS VAT-Registered: Line is Net Base, VAT is +18% on top (PLUS_VAT)
                    $base = $cleanAmount;
                    $vat = round($cleanAmount * $rate, 2);
                    $total = round($base + $vat, 2);
                    $appliedRate = $rate;
                    $vatTreatment = 'PLUS_VAT';
                } else {
                    // Customer is NOT VAT-Registered: Invoice is VAT-inclusive (no separate VAT breakdown)
                    $total = $cleanAmount;
                    $base = round($cleanAmount / (1 + $rate), 2);
                    $vat = round($total - $base, 2);
                    $appliedRate = $rate;
                    $vatTreatment = 'VAT_INCLUSIVE';
                }
            }

            $qty = floatval($inv['Qty'] ?? $inv['quantity'] ?? 1);
            $category = trim($inv['Product Category'] ?? $inv['category'] ?? '');
            $rep = trim($inv['Rep'] ?? $inv['sales_rep_code'] ?? '');
            $poNumber = trim($inv['PONumber'] ?? $inv['po_number'] ?? '');
            $memo = trim($inv['Memo'] ?? $inv['memo'] ?? '');
            $txnId = trim($inv['QBTxnID'] ?? $inv['qb_txn_id'] ?? '');

            // Expanded invoice tax & financial footers
            $subtotal = floatval($inv['subtotal'] ?? 0);
            $salesTaxTotal = floatval($inv['sales_tax_total'] ?? 0);
            $salesTaxRate = floatval($inv['sales_tax_rate'] ?? 0);
            $salesTaxItem = trim($inv['sales_tax_item'] ?? '');
            $customerTaxCode = trim($inv['customer_tax_code'] ?? '');
            $appliedAmount = floatval($inv['applied_amount'] ?? 0);
            $balanceRemaining = floatval($inv['balance_remaining'] ?? 0);
            $isPaid = !empty($inv['is_paid']) ? 1 : 0;
            $isPending = !empty($inv['is_pending']) ? 1 : 0;
            $dueDate = trim($inv['due_date'] ?? '');
            $shipDate = trim($inv['ship_date'] ?? '');
            $terms = trim($inv['terms'] ?? '');
            $unitPrice = floatval($inv['unit_price'] ?? ($qty > 0 ? ($cleanAmount / $qty) : $cleanAmount));

            // Fallback category mapping from product mappings table
            if (empty($category)) {
                $mapping = $db->fetch("SELECT product_category FROM product_mappings WHERE item_description = ?", [$itemDesc]);
                if ($mapping) {
                    $category = $mapping['product_category'];
                }
            }

            $db->execute($insertInvoiceStmt, [
                $inv['Type'] ?? 'Invoice',
                $date,
                $num,
                $customer,
                $itemDesc,
                $taxCode,
                $qty,
                $cleanAmount,
                $base,
                $vat,
                $appliedRate,
                $total,
                $category,
                $rep,
                $poNumber,
                $memo,
                $txnId,
                $vatTreatment,
                $subtotal,
                $salesTaxTotal,
                $salesTaxRate,
                $salesTaxItem,
                $customerTaxCode,
                $appliedAmount,
                $balanceRemaining,
                $isPaid,
                $isPending,
                $dueDate,
                $shipDate,
                $terms,
                $unitPrice
            ]);

            $invoicesImported++;
        }
    }

    // 3. Process Payments & Settlements
    if (!empty($payments)) {
        foreach ($payments as $pay) {
            $customer = trim($pay['customer_name'] ?? $pay['Name'] ?? '');
            $rawDate = $pay['payment_date'] ?? $pay['Date'] ?? date('Y-m-d');
            $payDate = date('Y-m-d', strtotime(str_replace('/', '-', $rawDate)));
            $ref = trim($pay['reference_num'] ?? $pay['RefNumber'] ?? '');
            $amount = floatval(str_replace(',', '', $pay['amount'] ?? $pay['Amount'] ?? 0));
            $invoiceNum = trim($pay['invoice_num'] ?? $pay['AppliedToInvoice'] ?? '');

            $payMethod = trim($pay['payment_method'] ?? '');
            $depositAccount = trim($pay['deposit_to_account'] ?? '');
            $payMemo = trim($pay['memo'] ?? '');
            $unusedPay = floatval($pay['unused_payment'] ?? 0);

            if (empty($customer) || $amount <= 0) {
                continue;
            }

            $db->execute(
                "INSERT INTO payments (customer_name, payment_date, reference_num, amount, invoice_num, payment_method, deposit_account, memo, unused_payment) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$customer, $payDate, $ref, $amount, $invoiceNum, $payMethod, $depositAccount, $payMemo, $unusedPay]
            );

            // If matched to an invoice, compute days to pay and mark settled
            if (!empty($invoiceNum)) {
                $matchedInvoice = $db->fetch(
                    "SELECT invoice_date FROM sales WHERE invoice_number = ? AND customer_name = ? LIMIT 1",
                    [$invoiceNum, $customer]
                );

                if ($matchedInvoice) {
                    $invTime = strtotime($matchedInvoice['invoice_date']);
                    $payTime = strtotime($payDate);
                    $days = round(($payTime - $invTime) / (60 * 60 * 24));
                    if ($days < 0) $days = 0;

                    $db->execute(
                        "UPDATE sales SET paid_date = ?, days_to_pay = ?, is_paid = 1 WHERE invoice_number = ? AND customer_name = ?",
                        [$payDate, $days, $invoiceNum, $customer]
                    );
                }
            }

            $paymentsImported++;
        }
    }

    // Ensure all invoice customer names exist in customer profiles
    $db->syncCustomerProfiles();

    // Update settings: last sync timestamp & count
    $now = date('Y-m-d H:i:s');
    $db->setSetting('last_qb_sync', $now);
    $db->setSetting('last_qb_sync_summary', "Imported $invoicesImported invoices, $paymentsImported payments, $customersImported customers at $now");

    // Audit log
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $db->logActivity(
        1,
        'QB_API_SYNC',
        "Sync completed: $invoicesImported invoices imported ($invoicesSkipped skipped), $paymentsImported payments, $customersImported customers updated",
        $clientIp
    );

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Sync completed successfully.',
        'imported_invoices' => $invoicesImported,
        'skipped_invoices' => $invoicesSkipped,
        'imported_payments' => $paymentsImported,
        'imported_customers' => $customersImported,
        'sync_timestamp' => $now
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Sync failed: ' . $e->getMessage()
    ]);
}
