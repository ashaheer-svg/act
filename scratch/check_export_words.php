<?php
$f = 'app/binary/exports/qb_export_2026-09-16_143318.json';
echo "Scanning $f (" . round(filesize($f)/1024/1024, 2) . " MB)...\n";
$content = file_get_contents($f);
foreach (['Sinology', 'sinology', 'BECOME', 'Become', 'become', 'Macronis', 'macronis', 'Synology', 'BDCOM', 'Acronis'] as $w) {
    echo $w . ': ' . substr_count($content, $w) . "\n";
}

// Let's also check if there are other misspellings like 'Acronis', 'Macronis', etc.
preg_match_all('/[a-zA-Z0-9_\-\.\/]*cronis[a-zA-Z0-9_\-\.\/]*/i', $content, $mCronis);
echo "\nCronis matches unique:\n";
print_r(array_unique($mCronis[0]));

preg_match_all('/[a-zA-Z0-9_\-\.\/]*nology[a-zA-Z0-9_\-\.\/]*/i', $content, $mNology);
echo "\nNology matches unique:\n";
print_r(array_unique($mNology[0]));

preg_match_all('/[a-zA-Z0-9_\-\.\/]*dcom[a-zA-Z0-9_\-\.\/]*/i', $content, $mDcom);
echo "\nDcom matches unique:\n";
print_r(array_unique($mDcom[0]));
