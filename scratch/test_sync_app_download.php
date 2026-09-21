<?php
require_once 'config.php';
require_once 'classes/Database.php';

$baseDir = dirname(__DIR__);

// Test 1: Check if SalesBISync.zip exists
$zipPath = $baseDir . '/app/binary/SalesBISync.zip';
echo "SalesBISync.zip exists: " . (file_exists($zipPath) ? "YES (" . round(filesize($zipPath)/1048576, 2) . " MB)" : "NO") . "\n";

// Test 2: Check SalesBISync.exe
$exePath = $baseDir . '/app/binary/SalesBISync.exe';
echo "SalesBISync.exe exists: " . (file_exists($exePath) ? "YES (" . round(filesize($exePath)/1048576, 2) . " MB)" : "NO") . "\n";

// Test 3: Check sidebar menu item
$sidebar = file_get_contents($baseDir . '/includes/sidebar.php');
if (strpos($sidebar, 'sync_app.php') !== false && strpos($sidebar, 'Download Sync App') !== false) {
    echo "Sidebar menu item: OK\n";
} else {
    echo "Sidebar menu item: FAILED\n";
}

// Test 4: Check settings button
$settings = file_get_contents($baseDir . '/settings.php');
if (strpos($settings, 'sync_app.php') !== false) {
    echo "Settings button: OK\n";
} else {
    echo "Settings button: FAILED\n";
}
