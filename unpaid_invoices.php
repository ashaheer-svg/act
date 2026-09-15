<?php
/**
 * Unpaid Invoices Sorted by Customer Shortcut
 * Redirects seamlessly to the executive Unpaid Invoices & Receivables Ledger
 */
$queryString = !empty($_SERVER['QUERY_STRING']) ? '&' . $_SERVER['QUERY_STRING'] : '';
header('Location: reports.php?type=unpaid_invoices' . $queryString);
exit;
