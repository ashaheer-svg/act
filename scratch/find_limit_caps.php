<?php
$lines = file('classes/Reports.php');
foreach ($lines as $i => $l) {
    if (preg_match('/\$limit\s*=\s*(?:max|min|\(int\))/i', $l)) {
        echo ($i + 1) . ": " . trim($l) . "\n";
    }
}
