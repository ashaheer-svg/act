<?php
require_once 'config.php';
require_once 'classes/Database.php';
$db = new Database(DATABASE_PATH);
echo "AI Provider: " . $db->getSetting('ai_provider', 'none') . "\n";
echo "AI Model: " . $db->getSetting('ai_model', 'none') . "\n";
$key = $db->getSetting('gemini_api_key', '');
echo "Gemini Key: " . (empty($key) ? "EMPTY" : substr($key, 0, 8) . "..." . substr($key, -4)) . "\n";
