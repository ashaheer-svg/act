<?php
require_once 'config.php';
require_once 'classes/Database.php';
$db = new Database(DATABASE_PATH);
$cols = $db->fetchAll('PRAGMA table_info(sales)');
foreach ($cols as $c) {
    echo "{$c['name']} ({$c['type']})\n";
}
