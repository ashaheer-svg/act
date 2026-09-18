<?php
$content = file_get_contents('classes/Reports.php');
preg_match_all('/function\s+([a-zA-Z0-9_]+)\s*\(/', $content, $m);
echo "Functions found in classes/Reports.php (" . count($m[1]) . "):\n";
foreach ($m[1] as $fn) {
    echo " - $fn\n";
}
