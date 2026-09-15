<?php
$db = new PDO('sqlite:backups/sales_bi_pre_2009_import_20260910_000713.db');
$res = $db->query("SELECT sql FROM sqlite_master WHERE name='contract_periods'")->fetch(PDO::FETCH_ASSOC);
echo "SQL in backup:\n";
print_r($res);

$tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
echo "All tables in backup:\n";
print_r($tables);
