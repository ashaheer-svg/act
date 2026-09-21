<?php
/**
 * Report Controller: Customer Performance Matrix (Archived Pivot)
 * Active Solutions BI Platform
 */

if (!defined('DATABASE_PATH') && !isset($db)) {
    exit('Direct access not permitted.');
}

// Data Preparation
$customerPivot = $reports->getCustomerYearlyPivot($year, $brand, $customer_type, $rep_code);
$reportTitle = "Customer Performance Matrix - $year" . ($brand ? " ($brand)" : "");
