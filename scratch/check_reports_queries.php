<?php
$content = file_get_contents('classes/Reports.php');

preg_match_all('/FROM\s+sales\s+WHERE\s+(.*?)(?:GROUP|ORDER|LIMIT|;|\")/is', $content, $matches);
echo "Found " . count($matches[1]) . " queries FROM sales WHERE ...\n";
foreach (array_slice($matches[1], 0, 10) as $idx => $m) {
    echo "Query " . ($idx+1) . ": " . trim(preg_replace('/\s+/', ' ', $m)) . "\n\n";
}
