<?php
$config = json_decode(file_get_contents('app/binary/config.json'), true);
$url = 'https://act.active.lk/api/audit_data.php';

// Let's add a check in audit_data.php for custom GP and verified customer types
