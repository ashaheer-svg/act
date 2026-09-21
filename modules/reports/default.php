<?php
/**
 * Report Controller: Fallback / Default Report
 * Active Solutions BI Platform
 */

if (!defined('DATABASE_PATH') && !isset($db)) {
    exit('Direct access not permitted.');
}

$reportData = [];
$reportTitle = 'Report Details';
