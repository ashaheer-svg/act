<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

$pdo->exec("DROP VIEW IF EXISTS contract_periods");
$pdo->exec("
    CREATE VIEW contract_periods AS
    SELECT 
        ss.id,
        ss.software_name as contract_reference,
        ss.customer_name,
        ss.invoice_number,
        COALESCE(ss.edition_tier, 'Annual Maintenance') as service_type,
        COALESCE(ss.period_start_date, date(ii.invoice_date)) as start_date,
        COALESCE(ss.period_end_date, date(ii.invoice_date, '+1 year')) as end_date,
        COALESCE(ss.renewal_opportunity_value, ii.total_amount, 0) as contract_value,
        'Service / Software Agreement' as notes
    FROM software_subscriptions ss
    LEFT JOIN invoice_items ii ON ss.invoice_item_id = ii.id
    UNION ALL
    SELECT 
        ii.id + 1000000 as id,
        ii.clean_product_name as contract_reference,
        ii.customer_name,
        ii.invoice_number,
        'AMC / Support Contract' as service_type,
        date(ii.invoice_date) as start_date,
        date(ii.invoice_date, '+1 year') as end_date,
        ii.total_amount as contract_value,
        'Annual Service Maintenance' as notes
    FROM invoice_items ii
    WHERE ii.product_type = 'SERVICE_AMC'
      AND NOT EXISTS (SELECT 1 FROM software_subscriptions ss2 WHERE ss2.invoice_item_id = ii.id)
");

echo "contract_periods view created successfully!\n";
$cnt = $pdo->query("SELECT COUNT(*) FROM contract_periods")->fetchColumn();
echo "Total contracts in view: $cnt\n";
