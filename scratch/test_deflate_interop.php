<?php
$original = '{"test":"361 Degrees (Pvt) Ltd"}';

// Test raw deflate
$deflated = gzdeflate($original);
$b64 = base64_encode($deflated);

$decomp = gzinflate(base64_decode($b64));
echo "Roundtrip raw deflate: " . ($decomp === $original ? "SUCCESS" : "FAIL") . "\n";
