<?php
require_once __DIR__ . '/test_customer_batches.php';

for ($c = 1; $c <= 25; $c += 2) {
    testBatch($c);
}
