<?php
/**
 * Report Controller: Customer Credit Health & Risk Assessment
 * Active Solutions BI Platform
 */

if (!defined('DATABASE_PATH') && !isset($db)) {
    exit('Direct access not permitted.');
}

$creditData = $reports->getCustomerCreditScores();
$reportTitle = 'Customer Credit Health & Risk Assessment';
