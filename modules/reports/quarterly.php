<?php
/**
 * Report Controller: Quarterly Performance
 * Active Solutions BI Platform
 */

if (!defined('DATABASE_PATH') && !isset($db)) {
    exit('Direct access not permitted.');
}

$reportData = $reports->getQuarterlySales($year, $quarter);
$reportTitle = 'Quarterly Performance - ' . ($reportData['period'] ?? "Q$quarter $year");
