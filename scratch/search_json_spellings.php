<?php
echo "=== SEARCHING RAW EXPORT JSON FOR SPELLING VARIATIONS ===\n";

$jsonFiles = glob('*.json');
$jsonFiles = array_merge($jsonFiles, glob('data/*.json'));

foreach ($jsonFiles as $jf) {
    if (filesize($jf) > 100 * 1024 * 1024) continue; // skip massive files if any
    echo "\nScanning $jf (" . round(filesize($jf)/1024/1024, 2) . " MB)...\n";
    $content = file_get_contents($jf);

    foreach (['Sinology', 'sinology', 'BECOME', 'Become', 'become', 'Macronis', 'macronis', 'Synology', 'BDCOM', 'Acronis'] as $word) {
        $count = substr_count($content, $word);
        if ($count > 0) {
            echo "  '$word': $count occurrences\n";
        }
    }
}
