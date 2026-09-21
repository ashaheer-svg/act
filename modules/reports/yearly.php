<?php
/**
 * Report Controller: Yearly Performance
 * Active Solutions BI Platform
 */

if (!defined('DATABASE_PATH') && !isset($db)) {
    exit('Direct access not permitted.');
}

$reportData = $reports->getYearlySales($year);
$reportTitle = 'Yearly Performance - ' . ($reportData['period'] ?? $year);
