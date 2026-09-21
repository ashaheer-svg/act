<?php
/**
 * Report Controller: Aging & Collections Report
 * Active Solutions BI Platform
 */

if (!defined('DATABASE_PATH') && !isset($db)) {
    exit('Direct access not permitted.');
}

$bracket = $_GET['bracket'] ?? 'all';
$status = $_GET['status'] ?? 'all';
$sortBy = $_GET['sort'] ?? 'invoice_number';
$agingData = $reports->getAgingReport($bracket, $status, $sortBy);
$reportTitle = 'Aging & Collections Report';
