<?php
require_once 'config.php';
require_once 'classes/Database.php';
$db = new Database(DATABASE_PATH);
$lines = $db->fetchAll("SELECT id, item_description, total_amount, base_value, vat_component, applied_tax_rate FROM sales WHERE invoice_number = 'AS009461'");
print_r($lines);
