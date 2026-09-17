<?php
require_once __DIR__ . '/test_cust_counts.php';

foreach ([60, 75, 100, 120, 150, 200] as $cnt) {
    testFullCustCount($cnt);
}
