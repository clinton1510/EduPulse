<?php
require __DIR__.'/lib/analytics.php';require __DIR__.'/lib/formats.php';require __DIR__.'/lib/reports.php';
$f=filters($db,$_GET);$type=scalarParam($_GET,'type','academic');if(!isset(reportTypes()[$type])){http_response_code(400);exit('Unknown report type.');}
$format=scalarParam($_GET,'format','csv');if(!in_array($format,['csv','xlsx','pdf','print'],true)){http_response_code(400);exit('Unsupported report format.');}
$rows=reportRows($db,$f,$type);$filename='edupulse-'.$type.'-'.date('Ymd');
if($format==='print'){
 require __DIR__.'/lib/ui.php';head(reportTypes()[$type]);echo '<body class="print-report"><main style="padding:30px;max-width:1400px;margin:auto"><div class="report-actions"><a class="btn-secondary" href="dashboard.php?'.h(filterQuery($f,['module'=>'reports'])).'">Back to reports</a><button class="btn-primary" type="button" data-print>Print report</button></div><h1>'.h(reportTypes()[$type]).'</h1><table><tbody>';foreach($rows as $row){echo '<tr>';foreach($row as $cell)echo '<td>'.h($cell).'</td>';echo '</tr>';}echo '</tbody></table></main></body></html>';exit;
}
header('Content-Disposition: attachment; filename="'.$filename.'.'.$format.'"');
if($format==='xlsx'){header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');echo xlsxWrite($rows);}
elseif($format==='pdf'){header('Content-Type: application/pdf');echo pdfWrite($rows);}
else{header('Content-Type: text/csv; charset=UTF-8');$out=fopen('php://output','w');foreach($rows as $row){$safe=array_map(fn($v)=>preg_match('/^[=+@\-\t\r]/',(string)$v)?"'".$v:$v,$row);fputcsv($out,$safe,',','"','');}fclose($out);}
