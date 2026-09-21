<?php
$f = 'app/binary/exports/qb_export_2026-09-16_143318.json';
$fp = fopen($f, 'r');
$start = fread($fp, 2000);
fclose($fp);
echo substr($start, 0, 1000);
