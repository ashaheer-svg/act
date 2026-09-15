<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes/Database.php';

header('Content-Type: application/json');

try {
    $db = new Database(DATABASE_PATH);
    $db->initialize();
    
    // Step 1: Ensure columns and indexes exist
    $db->syncSchema();

    // Step 2: Backfill End Customers from sales line descriptions
    $rows = $db->fetchAll("SELECT id, invoice_number, item_description FROM sales WHERE item_description LIKE '%End Customer%' OR item_description LIKE '%End Customers%'");

    $pattern = '/\(?\s*End\s+Customers?\s*[:\-\s]\s*(.*?)\)?$/i';

    $invoicesFound = [];
    $salesUpdated = 0;
    $itemsUpdated = 0;
    $hwUpdated = 0;
    $subsUpdated = 0;

    $db->beginTransaction();

    foreach ($rows as $r) {
        $desc = trim($r['item_description']);
        if (preg_match($pattern, $desc, $matches)) {
            $endCustomer = trim(trim($matches[1]), "()\"' \t\n\r");
            $invNum = trim($r['invoice_number']);

            if (!empty($endCustomer) && !empty($invNum)) {
                $invoicesFound[$invNum] = $endCustomer;
            }
        }
    }

    foreach ($invoicesFound as $invNum => $endCustomer) {
        $sRes = $db->execute("UPDATE sales SET end_customer = ? WHERE invoice_number = ? AND (end_customer IS NULL OR end_customer = '')", [$endCustomer, $invNum]);
        $salesUpdated += $sRes->rowCount();

        $iRes = $db->execute("UPDATE invoice_items SET end_customer = ? WHERE invoice_number = ? AND (end_customer IS NULL OR end_customer = '')", [$endCustomer, $invNum]);
        $itemsUpdated += $iRes->rowCount();

        $hRes = $db->execute("UPDATE hardware_assets SET end_customer = ? WHERE invoice_number = ? AND (end_customer IS NULL OR end_customer = '')", [$endCustomer, $invNum]);
        $hwUpdated += $hRes->rowCount();

        $subRes = $db->execute("UPDATE software_subscriptions SET end_customer = ? WHERE invoice_number = ? AND (end_customer IS NULL OR end_customer = '')", [$endCustomer, $invNum]);
        $subsUpdated += $subRes->rowCount();
    }

    $db->commit();

    echo json_encode([
        'success' => true,
        'unique_invoices_matched' => count($invoicesFound),
        'sales_rows_updated' => $salesUpdated,
        'invoice_items_updated' => $itemsUpdated,
        'hardware_assets_updated' => $hwUpdated,
        'software_subscriptions_updated' => $subsUpdated,
        'sample_matches' => array_slice($invoicesFound, 0, 10, true)
    ]);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
