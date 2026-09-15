<?php
$files = [
    'classes/Reports.php',
    'reports.php',
    'includes/sidebar.php',
    'layout.css',
    'includes/layout_js.php',
    'classes/Database.php',
    'classes/DataImporter.php',
    'api/sync.php'
];

@mkdir('scratch/prod_check/classes', 0777, true);
@mkdir('scratch/prod_check/includes', 0777, true);
@mkdir('scratch/prod_check/api', 0777, true);

echo sprintf("%-25s | %-7s | %-12s | %-12s\n", "File", "Status", "Local MD5", "Prod MD5");
echo str_repeat("-", 65) . "\n";

$diffs = [];

foreach ($files as $f) {
    $prodPath = "scratch/prod_check/$f";
    $cmd = "curl.exe -s --ftp-ssl-control -k -u \"activeftp:active***me\" \"ftp://act.active.lk/$f\" -o \"$prodPath\"";
    exec($cmd);
    
    if (!file_exists($prodPath) || filesize($prodPath) === 0) {
        echo sprintf("%-25s | %-7s | %-12s | %-12s\n", $f, "MISSING", substr(md5_file($f), 0, 8), "EMPTY/FAIL");
        $diffs[] = $f;
        continue;
    }
    
    $localMd5 = md5_file($f);
    $prodMd5 = md5_file($prodPath);
    
    if ($localMd5 === $prodMd5) {
        echo sprintf("%-25s | %-7s | %-12s | %-12s\n", $f, "MATCH", substr($localMd5, 0, 8), substr($prodMd5, 0, 8));
    } else {
        echo sprintf("%-25s | %-7s | %-12s | %-12s\n", $f, "DIFF", substr($localMd5, 0, 8), substr($prodMd5, 0, 8));
        $diffs[] = $f;
    }
}

echo "\nSummary: " . count($diffs) . " files differ/missing.\n";
if (!empty($diffs)) {
    echo "Files needing upload: " . implode(', ', $diffs) . "\n";
}
