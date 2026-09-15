<?php
$f1 = 'C:\Users\shahe\.gemini\antigravity-ide\brain\ef2543a4-f055-44dc-9f3b-a04596f62e76\.user_uploaded\media_1788952102531.csv';
$f2 = 'C:\Users\shahe\.gemini\antigravity-ide\brain\ef2543a4-f055-44dc-9f3b-a04596f62e76\.user_uploaded\media_1788952102565.csv';

function getCustomers($path) {
    $fp = fopen($path, 'r');
    $header = fgetcsv($fp);
    $header[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $header[0]);
    $list = [];
    while (($row = fgetcsv($fp)) !== false) {
        if (empty($row[0])) continue;
        $d = array_combine($header, $row);
        $name = trim($d['FullName'] ?? $d['Name'] ?? '');
        if (!empty($name)) {
            $list[$name] = $d;
        }
    }
    fclose($fp);
    return $list;
}

$c1 = getCustomers($f1); // Old
$c2 = getCustomers($f2); // New

$exactMatches = 0;
$oldOnly = [];
$newOnly = [];

foreach ($c1 as $name => $d) {
    if (isset($c2[$name])) {
        $exactMatches++;
    } else {
        $oldOnly[$name] = $d;
    }
}

foreach ($c2 as $name => $d) {
    if (!isset($c1[$name])) {
        $newOnly[$name] = $d;
    }
}

echo "Old System Customers: " . count($c1) . "\n";
echo "New System Customers: " . count($c2) . "\n";
echo "Exact Name Matches: $exactMatches\n";
echo "Only in Old: " . count($oldOnly) . "\n";
echo "Only in New: " . count($newOnly) . "\n";

// Check near matches (e.g. case-insensitive or punctuation differences)
$nearMatches = [];
$c2Norm = [];
foreach ($c2 as $name => $d) {
    $clean = strtolower(preg_replace('/[^a-z0-9]/i', '', $name));
    $c2Norm[$clean] = $name;
}

foreach ($oldOnly as $name => $d) {
    $clean = strtolower(preg_replace('/[^a-z0-9]/i', '', $name));
    if (isset($c2Norm[$clean])) {
        $nearMatches[] = ["Old" => $name, "New" => $c2Norm[$clean]];
    }
}

echo "\nNear Matches (Same company, slight punctuation/casing/suffix differences): " . count($nearMatches) . "\n";
echo "First 15 Near Matches:\n";
foreach (array_slice($nearMatches, 0, 15) as $nm) {
    echo "  Old: '{$nm['Old']}' <---> New: '{$nm['New']}'\n";
}
