<?php
require __DIR__.'/config/app.php';require __DIR__.'/lib/formats.php';require __DIR__.'/lib/datasets.php';requireAdmin();
$rows=[importColumns(),['NEW-SYN-001','BSIT','2026-2027','1st Semester','1','SYN-111','2.25','90','1']];
if(scalarParam($_GET,'format')==='xlsx'){header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');header('Content-Disposition: attachment; filename="edupulse-import-template.xlsx"');echo xlsxWrite($rows);}else{header('Content-Type: text/csv');header('Content-Disposition: attachment; filename="edupulse-import-template.csv"');$out=fopen('php://output','w');foreach($rows as $row)fputcsv($out,$row,',','"','');}
