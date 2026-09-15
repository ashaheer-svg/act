<?php
$ftpUser = "activeftp";
$ftpPass = "active***me";
$ftpBase = "ftp://active.lk:21/act";

$files = [
    'includes/sidebar.php',
    'includes/layout_js.php',
    'layout.css'
];

echo "=== Uploading modified files to $ftpBase ===\n";

$failed = [];
$uploaded = [];

foreach ($files as $f) {
    if (!file_exists($f)) {
        echo "[SKIP] Local file missing: $f\n";
        continue;
    }
    $remoteUrl = "$ftpBase/$f";
    echo "Uploading $f ... ";
    $cmd = "curl.exe --ftp-ssl-control --ftp-create-dirs -s -S -k --user \"$ftpUser:$ftpPass\" -T \"$f\" \"$remoteUrl\"";
    exec($cmd, $out, $ret);
    if ($ret === 0) {
        echo "OK\n";
        $uploaded[] = $f;
    } else {
        echo "FAILED (code $ret): " . implode("\n", $out) . "\n";
        $failed[] = $f;
    }
}

echo "\nSummary:\n";
echo "Successfully uploaded: " . count($uploaded) . " files\n";
if (!empty($failed)) {
    echo "Failed uploads: " . implode(', ', $failed) . "\n";
    exit(1);
} else {
    echo "All files uploaded successfully to production!\n";
    exit(0);
}
