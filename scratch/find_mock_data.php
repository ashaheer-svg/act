<?php
$config = json_decode(file_get_contents('app/binary/config.json'), true);
$url = 'https://act.active.lk/api/audit_data.php?inspect_all_tables=1';

// Let's enhance api/audit_data.php to inspect every table in the database
