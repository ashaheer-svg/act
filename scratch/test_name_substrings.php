<?php
require_once __DIR__ . '/isolate_cust0_field.php';

testPayload(['name' => '361'], "Only 361");
testPayload(['name' => 'Degrees'], "Only Degrees");
testPayload(['name' => '361 Degrees'], "361 Degrees");
testPayload(['name' => 'Degrees (Pvt) Ltd'], "Degrees (Pvt) Ltd");
testPayload(['name' => '361 (Pvt) Ltd'], "361 (Pvt) Ltd");
testPayload(['name' => '361 Degrees Pvt Ltd'], "361 Degrees Pvt Ltd (no parens)");
testPayload(['name' => '361 Degrees (Pvt)'], "361 Degrees (Pvt)");
testPayload(['name' => 'Degrees (Pvt)'], "Degrees (Pvt)");
testPayload(['name' => '361 (Pvt)'], "361 (Pvt)");
testPayload(['name' => '361 Degrees (Pvt) Ltd'], "Exact full string");
