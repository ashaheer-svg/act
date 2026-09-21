<?php
/**
 * Report Controller: RFM Customer Segmentation & Churn Risk
 * Active Solutions BI Platform
 */

if (!defined('DATABASE_PATH') && !isset($db)) {
    exit('Direct access not permitted.');
}

$segment = $_GET['segment'] ?? 'all';
$rfmData = $reports->getRFMAnalysis($segment);
$reportTitle = 'RFM Customer Segmentation & Churn Risk';
