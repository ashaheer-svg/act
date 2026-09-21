<?php
$lines = file('reports.php');
foreach ($lines as $i => $l) {
    if (stripos($l, '$limit') !== false || stripos($l, 'renderPaginationRail') !== false) {
        echo ($i + 1) . ": " . trim($l) . "\n";
    }
}
