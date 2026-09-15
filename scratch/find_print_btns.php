<?php
$content = file_get_contents('reports.php');
$lines = explode("\n", $content);
foreach ($lines as $num => $line) {
    if (strpos($line, 'printCurrentReport') !== false) {
        echo ($num + 1) . ": " . trim($line) . "\n";
    }
}
