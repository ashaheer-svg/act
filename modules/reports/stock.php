<?php
/**
 * Report Controller: Stock Movement & Inventory Velocity (FSN)
 * Active Solutions BI Platform
 */

if (!defined('DATABASE_PATH') && !isset($db)) {
    exit('Direct access not permitted.');
}

$fsn = $_GET['fsn'] ?? 'all';
$search = $_GET['search'] ?? '';
list($p, $limit, $isAll) = getReportPaginationParams(50);
$offset = ($p - 1) * $limit;
$stockResult = $reports->getStockMovementAnalysis($brand, $fsn, $search, $limit, $offset);
$stockData = $stockResult['items'];
$stockTotal = $stockResult['total'];
$stockPages = max(1, (int)ceil($stockTotal / $limit));
$reportTitle = 'Stock Movement & Inventory Velocity (FSN)';
