<?php
$content = file_get_contents('reports.php');
$lines = explode("\n", $content);
foreach ($lines as $num => $line) {
    if (strpos($line, 'exportReportToPdf') !== false || strpos($line, 'Export PDF') !== false || strpos($line, 'Export CSV') !== false) {
        echo ($num + 1) . ": " . trim($line) . "\n";
    }
}
