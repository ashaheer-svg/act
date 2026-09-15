<?php
/**
 * Warranty & Serial Lookup Shortcut
 * Redirects seamlessly to the interactive Warranty & Serial Lifecycle Intelligence section
 */
$queryString = !empty($_SERVER['QUERY_STRING']) ? '&' . $_SERVER['QUERY_STRING'] : '';
header('Location: reports.php?type=warranties' . $queryString);
exit;
