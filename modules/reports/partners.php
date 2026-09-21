<?php
/**
 * Report Controller: Partner vs. End-Customer Cohort Analysis
 * Active Solutions BI Platform
 */

if (!defined('DATABASE_PATH') && !isset($db)) {
    exit('Direct access not permitted.');
}

$cohortData = $reports->getPartnerCohortAnalysis();
$reportTitle = 'Partner vs. End-Customer Cohort Analysis';
