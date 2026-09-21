<?php
require_once 'config.php';
require_once 'classes/Database.php';

$db = new Database(DATABASE_PATH);
$users = $db->fetchAll('SELECT id, username, role, email FROM users');
print_r($users);
