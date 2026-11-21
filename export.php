<?php
require __DIR__.'/config/app.php';
$user = auth();
$years = array_column(queryAll($db,'SELECT DISTINCT academic_year FROM academic_records'),'academic_year');
$sems = ['1st Semester','2nd Semester'];
$depts = array_column(queryAll($db,'SELECT DISTINCT department FROM academic_records'),'department');
$year = in_array($_GET['year']??'', $years,true)?$_GET['year']: ($years[0] ?? date('Y').'-'.(date('Y')+1));
$sem = in_array($_GET['semester']??'', $sems,true)?$_GET['semester']:$sems[0];
$dept = in_array($_GET['department']??'', $depts,true)?$_GET['department']:'All Departments';
if ($user['role']==='faculty') $dept=$user['department'];
$where='academic_year=? AND semester=?'; $params=[$year,$sem];
if ($dept!=='All Departments') {$where.=' AND department=?';$params[]=$dept;}
$a=queryAll($db,"SELECT ROUND(100*SUM(units_passed)/NULLIF(SUM(units_attempted),0),1) pass_rate, ROUND(100*AVG(CASE WHEN avg_grade > 3 OR attendance_rate < 75 THEN 1 ELSE 0 END),1) at_risk,ROUND(100*AVG(grade_completed),1) completion_rate FROM academic_records WHERE $where",$params)[0];
$evalWhere=$where; $evalParams=$params;
if($user['role']==='faculty'){$evalWhere.=' AND faculty_id=?';$evalParams[]=$user['faculty_id'];}
$s=queryAll($db,"SELECT ROUND(SUM(score_total)/NULLIF(SUM(response_count),0),2) sete_score FROM sete_summary WHERE $evalWhere",$evalParams)[0];
$r=queryAll($db,"SELECT ROUND(100*SUM(response_count)/NULLIF(SUM(invitations),0),1) rate FROM sete_summary WHERE $evalWhere AND criterion='Clarity'",$evalParams)[0];
header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename="edupulse-aggregate-report.csv"');
$out=fopen('php://output','w');
fputcsv($out,['EduPulse Aggregate KPI Report - Synthetic Data']);
fputcsv($out,['Academic Year',$year]);fputcsv($out,['Semester',$sem]);fputcsv($out,['Department',$dept]);
fputcsv($out,['KPI','Value','Proposed Target']);
foreach([['Course Pass Rate',$a['pass_rate'].'%','>=85%'],['Students at Risk',$a['at_risk'].'%','<=15%'],['Teaching Effectiveness',$s['sete_score'].'/5','>=4.0/5'],['SETE Response Rate',$r['rate'].'%','>=70%'],['Grade Completion',$a['completion_rate'].'%','>=95%']] as $row)fputcsv($out,$row);
fputcsv($out,['Note','SETE is contextual and must not be used as the sole personnel evaluation measure.']);
fclose($out);
