<?php
$lines = file('classes/Reports.php');
foreach ($lines as $i => $l) {
    if (preg_match('/function\s+([a-zA-Z0-9_]*VAT[a-zA-Z0-9_]*|[a-zA-Z0-9_]*Tax[a-zA-Z0-9_]*)/i', $l, $m)) {
        echo ($i + 1) . ": " . trim($l) . "\n";
    }
}
