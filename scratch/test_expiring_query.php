<?php
require_once 'config.php';
require_once 'classes/Database.php';

$db = new Database(DATABASE_PATH);

$filters = [
    'range' => 'pm_90d',
    'search' => '',
    'category' => 'all',
    'status' => 'all',
    'sort' => 'expiry_asc'
];

$where = ["1=1"];
$params = [];

// Expiration date range filter
$range = $filters['range'] ?? 'pm_90d';
if ($range === 'pm_90d') {
    $where[] = "sub.period_end_date BETWEEN date('now', '-90 days') AND date('now', '+90 days')";
} elseif ($range === 'due_30d') {
    $where[] = "sub.period_end_date BETWEEN date('now') AND date('now', '+30 days')";
} elseif ($range === 'due_60d') {
    $where[] = "sub.period_end_date BETWEEN date('now') AND date('now', '+60 days')";
} elseif ($range === 'due_90d') {
    $where[] = "sub.period_end_date BETWEEN date('now') AND date('now', '+90 days')";
} elseif ($range === 'due_180d') {
    $where[] = "sub.period_end_date BETWEEN date('now') AND date('now', '+180 days')";
} elseif ($range === 'post_30d') {
    $where[] = "sub.period_end_date BETWEEN date('now', '-30 days') AND date('now', '-1 day')";
} elseif ($range === 'post_90d') {
    $where[] = "sub.period_end_date BETWEEN date('now', '-90 days') AND date('now', '-1 day')";
} elseif ($range === 'overdue') {
    $where[] = "sub.period_end_date < date('now')";
} elseif ($range === 'upcoming') {
    $where[] = "sub.period_end_date >= date('now')";
} elseif ($range === 'custom' && !empty($filters['date_from']) && !empty($filters['date_to'])) {
    $where[] = "sub.period_end_date BETWEEN ? AND ?";
    $params[] = $filters['date_from'];
    $params[] = $filters['date_to'];
}

$whereClause = implode(' AND ', $where);

$countRow = $db->fetch("SELECT COUNT(*) as c FROM software_subscriptions sub WHERE $whereClause", $params);
$total = (int)($countRow['c'] ?? 0);

$kpis = $db->fetch("
    SELECT 
        COUNT(*) as total_contracts,
        COALESCE(SUM(renewal_opportunity_value), 0) as total_opportunity_value,
        SUM(CASE WHEN period_end_date < date('now') THEN 1 ELSE 0 END) as overdue_count,
        COALESCE(SUM(CASE WHEN period_end_date < date('now') THEN renewal_opportunity_value ELSE 0 END), 0) as overdue_value,
        SUM(CASE WHEN period_end_date >= date('now') AND period_end_date <= date('now', '+30 days') THEN 1 ELSE 0 END) as due_30d_count,
        COALESCE(SUM(CASE WHEN period_end_date >= date('now') AND period_end_date <= date('now', '+30 days') THEN renewal_opportunity_value ELSE 0 END), 0) as due_30d_value,
        SUM(CASE WHEN period_end_date > date('now', '+30 days') AND period_end_date <= date('now', '+90 days') THEN 1 ELSE 0 END) as due_90d_count,
        COALESCE(SUM(CASE WHEN period_end_date > date('now', '+30 days') AND period_end_date <= date('now', '+90 days') THEN renewal_opportunity_value ELSE 0 END), 0) as due_90d_value
    FROM software_subscriptions sub
    WHERE $whereClause
", $params);

echo "Total in pm_90d window: $total\n";
print_r($kpis);

$rows = $db->fetchAll("
    SELECT 
        sub.id,
        sub.invoice_number,
        sub.customer_name,
        sub.end_customer,
        sub.software_name,
        sub.edition_tier,
        sub.license_seats,
        sub.period_start_date,
        sub.period_end_date,
        sub.term_months,
        sub.renewal_opportunity_value,
        CAST(ROUND(julianday(sub.period_end_date) - julianday('now')) AS INTEGER) as days_remaining,
        CASE 
            WHEN sub.period_end_date < date('now') THEN 'OVERDUE'
            WHEN julianday(sub.period_end_date) - julianday('now') <= 30 THEN 'DUE_SOON'
            ELSE 'ACTIVE'
        END as dynamic_status,
        CASE
            WHEN sub.software_name LIKE '%Acronis%' OR sub.edition_tier LIKE '%Acronis%' THEN 'Acronis'
            WHEN sub.software_name LIKE '%Maintenance%' OR sub.edition_tier LIKE '%Maintenance%' OR sub.software_name LIKE '%SLA%' THEN 'MA / SLA'
            WHEN sub.software_name LIKE '%Hosting%' OR sub.software_name LIKE '%Domain%' OR sub.edition_tier LIKE '%Hosting%' THEN 'Hosting'
            ELSE 'License / SaaS'
        END as category_badge
    FROM software_subscriptions sub
    WHERE $whereClause
    ORDER BY sub.period_end_date ASC, sub.id DESC
    LIMIT 5
", $params);

echo "\nSample 5 rows:\n";
foreach ($rows as $r) {
    echo "{$r['period_end_date']} ({$r['days_remaining']}d) [{$r['dynamic_status']}] [{$r['category_badge']}] {$r['invoice_number']} - {$r['customer_name']} | {$r['software_name']} | LKR " . number_format($r['renewal_opportunity_value']) . "\n";
}
