<?php
/**
 * Report Controller: Software & SaaS Renewals Pipeline
 * Active Solutions BI Platform
 */

if (!defined('DATABASE_PATH') && !isset($db)) {
    exit('Direct access not permitted.');
}

// Data Preparation
$renewalStatus = $_GET['status'] ?? 'all';
$renewalSearch = $_GET['search'] ?? '';
list($p, $limit, $isAll) = getReportPaginationParams(50);
$renewalFilters = [
    'status' => $renewalStatus,
    'search' => $renewalSearch
];
$renewalResult = $reports->getRenewalsReport($renewalFilters, $p, $limit);
$renewalSubs = $renewalResult['subscriptions'];
$renewalTotal = $renewalResult['total'];
$renewalPages = $renewalResult['pages'];
$renewalKpis = $renewalResult['kpis'];
$renewalCalendar = $renewalResult['calendar'];
$reportTitle = 'Software & SaaS Renewals Pipeline';
