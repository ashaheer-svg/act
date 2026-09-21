<?php
/**
 * Report Controller: Sales Rep Performance & Collection Health
 * Active Solutions BI Platform
 */

if (!defined('DATABASE_PATH') && !isset($db)) {
    exit('Direct access not permitted.');
}

$repsData = $reports->getSalesRepPerformance($year);
$reportTitle = 'Sales Rep Performance & Collection Health';
