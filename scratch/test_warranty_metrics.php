<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database(DATABASE_PATH);
$pdo = $db->getConnection();

$metrics = [
    'total_serials' => $pdo->query("SELECT COUNT(DISTINCT serial_number) FROM hardware_assets WHERE serial_number IS NOT NULL AND serial_number != '' AND serial_number != 'UNASSIGNED'")->fetchColumn(),
    'active' => $pdo->query("SELECT COUNT(*) FROM hardware_assets WHERE warranty_expiry_date > DATE('now', '+60 days') AND serial_number != '' AND serial_number != 'UNASSIGNED'")->fetchColumn(),
    'expiring_soon' => $pdo->query("SELECT COUNT(*) FROM hardware_assets WHERE warranty_expiry_date >= DATE('now') AND warranty_expiry_date <= DATE('now', '+60 days') AND serial_number != '' AND serial_number != 'UNASSIGNED'")->fetchColumn(),
    'expired' => $pdo->query("SELECT COUNT(*) FROM hardware_assets WHERE warranty_expiry_date < DATE('now') AND serial_number != '' AND serial_number != 'UNASSIGNED'")->fetchColumn(),
    'maintenance_contracts' => $pdo->query("SELECT COUNT(*) FROM software_subscriptions WHERE edition_tier LIKE '%Maintenance%' OR software_name LIKE '%Maintenance%' OR software_name LIKE '%SLA%'")->fetchColumn()
];

print_r($metrics);
