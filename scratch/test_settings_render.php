<?php
require_once 'config.php';
require_once 'classes/Database.php';

$db = new Database(DATABASE_PATH);
$rules = $db->getTaxRules();
echo "Tax rules in DB: " . count($rules) . "\n";
echo "AI Provider: " . $db->getSetting('ai_provider', 'gemini') . "\n";
echo "AI Model: " . $db->getSetting('ai_model', 'gemini-1.5-flash') . "\n";
echo "VAT Rate: " . $db->getSetting('vat_rate', '0.18') . "\n";
