<?php
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(__DIR__ . '/../data/sales_bi.db');
$pdo = $db->getConnection();

echo "=== STARTING END CUSTOMER BACKFILL MIGRATION ===\n";

$stmt = $pdo->query("
    SELECT invoice_number, item_description 
    FROM sales 
    WHERE item_description LIKE '%End Customer%' 
       OR item_description LIKE '%End Customers%'
");

$invoiceEndCustomerMap = [];
$regex = '/\(?\s*End\s+Customers?\s*[:\-\s]\s*(.*?)\)?$/i';

while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $inv = trim($r['invoice_number']);
    $desc = trim($r['item_description']);

    if (preg_match($regex, $desc, $m)) {
        $extracted = trim($m[1], " \t\n\r\0\x0B()\"'-:;");
        if (!empty($extracted)) {
            $invoiceEndCustomerMap[$inv] = $extracted;
        }
    }
}

echo "Found " . count($invoiceEndCustomerMap) . " invoices with End Customer information.\n";

$pdo->beginTransaction();
try {
    $updateSalesStmt = $pdo->prepare("UPDATE sales SET end_customer = ? WHERE invoice_number = ?");
    $updateItemsStmt = $pdo->prepare("UPDATE invoice_items SET end_customer = ? WHERE invoice_number = ?");
    $updateHwStmt = $pdo->prepare("UPDATE hardware_assets SET end_customer = ? WHERE invoice_number = ?");
    $updateSubStmt = $pdo->prepare("UPDATE software_subscriptions SET end_customer = ? WHERE invoice_number = ?");

    $salesUpdated = 0;
    $hwUpdated = 0;
    $itemsUpdated = 0;
    $subsUpdated = 0;

    foreach ($invoiceEndCustomerMap as $inv => $endCust) {
        $updateSalesStmt->execute([$endCust, $inv]);
        $salesUpdated += $updateSalesStmt->rowCount();

        $updateItemsStmt->execute([$endCust, $inv]);
        $itemsUpdated += $updateItemsStmt->rowCount();

        $updateHwStmt->execute([$endCust, $inv]);
        $hwUpdated += $updateHwStmt->rowCount();

        $updateSubStmt->execute([$endCust, $inv]);
        $subsUpdated += $updateSubStmt->rowCount();
    }

    $pdo->commit();

    echo "=== MIGRATION COMPLETED SUCCESSFULLY ===\n";
    echo "Sales lines updated: $salesUpdated\n";
    echo "Invoice items updated: $itemsUpdated\n";
    echo "Hardware assets linked to End Customer: $hwUpdated\n";
    echo "Software subscriptions linked to End Customer: $subsUpdated\n";

    // Verification check
    $vSales = $pdo->query("SELECT count(DISTINCT invoice_number) FROM sales WHERE end_customer IS NOT NULL AND end_customer != ''")->fetchColumn();
    $vHw = $pdo->query("SELECT count(*) FROM hardware_assets WHERE end_customer IS NOT NULL AND end_customer != ''")->fetchColumn();
    echo "Verified distinct invoices with End Customer in sales: $vSales\n";
    echo "Verified hardware assets with End Customer: $vHw\n";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
}
