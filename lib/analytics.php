<?php
require_once __DIR__.'/../config/app.php';
function ready(PDO $db): void {
 try{$db->query('SELECT public_synthetic FROM academic_records LIMIT 1');$db->query('SELECT 1 FROM programs LIMIT 1');}catch(PDOException $e){http_response_code(503);exit('CICS setup required. Run php migrate.php from the project directory.');}
}
function filters(PDO $db,array $input): array {
 ready($db);
 $programs=queryAll($db,'SELECT id,code,title FROM programs WHERE active=1 ORDER BY code');
 $years=array_column(queryAll($db,'SELECT DISTINCT academic_year FROM academic_periods ORDER BY academic_year DESC'),'academic_year');
 $year=scalarParam($input,'year',$years[0]??'');if(!in_array($year,$years,true))$year=$years[0]??'';
 $sem=scalarParam($input,'semester','1st Semester');if(!in_array($sem,['1st Semester','2nd Semester'],true))$sem='1st Semester';
 $program=scalarParam($input,'program');if(!in_array($program,array_column($programs,'code'),true))$program='';
 $level=scalarParam($input,'level');if(!in_array($level,['1','2','3','4'],true))$level='';
 $args=[$sem];$where='p.active=1 AND c.semester=?';
 if($program){$where.=' AND p.code=?';$args[]=$program;}if($level){$where.=' AND c.year_level=?';$args[]=$level;}
 $subjects=queryAll($db,"SELECT DISTINCT s.id,s.code,s.title FROM curriculum_subjects c JOIN subjects s ON s.id=c.subject_id JOIN programs p ON p.id=c.program_id WHERE $where ORDER BY s.code",$args);
 $subject=scalarParam($input,'subject');if(!in_array($subject,array_map('strval',array_column($subjects,'id')),true))$subject='';
 return compact('year','sem','program','level','subject','programs','years','subjects');
}
function filterQuery(array $f,array $extra=[]): string{return http_build_query(array_merge(['year'=>$f['year'],'semester'=>$f['sem'],'program'=>$f['program'],'level'=>$f['level'],'subject'=>$f['subject']],$extra));}
function scopeSql(array $f,bool $historical=false,bool $grades=false): array {
 $where='a.public_synthetic=1 AND st.synthetic=1 AND p.active=1';$args=[];
 if(!$historical){$where.=' AND a.academic_year=? AND a.semester=?';$args=[$f['year'],$f['sem']];}
 if($f['program']){$where.=' AND p.code=?';$args[]=$f['program'];}if($f['level']){$where.=' AND e.year_level=?';$args[]=$f['level'];}
 $from='academic_records a JOIN enrollments e ON e.academic_record_id=a.id JOIN students st ON st.id=e.student_id AND st.program_id=e.program_id JOIN programs p ON p.id=e.program_id AND a.program_id=p.id';
 if($grades||$f['subject']){$from.=' JOIN subject_grades g ON g.enrollment_id=e.id JOIN curriculum_subjects c ON c.id=g.curriculum_subject_id AND c.program_id=e.program_id JOIN subjects s ON s.id=c.subject_id';if($f['subject']){$where.=' AND s.id=?';$args[]=$f['subject'];}}
 return [$from,$where,$args];
}
function academicSource(array $f,bool $historical=false): array {
 [$from,$where,$args]=scopeSql($f,$historical);
 if($f['subject'])$select='a.id,a.academic_year,a.semester,p.code,p.title,e.year_level,st.id student_id,s.units units_attempted,CASE WHEN g.completed=1 AND g.grade<=3 THEN s.units ELSE 0 END units_passed,g.grade avg_grade,g.attendance_rate,g.completed grade_completed';
 else $select='a.id,a.academic_year,a.semester,p.code,p.title,e.year_level,st.id student_id,a.units_attempted,a.units_passed,a.avg_grade,a.attendance_rate,a.grade_completed';
 return ["(SELECT $select FROM $from WHERE $where) scoped",$args];
}
function academicSummary(PDO $db,array $f): array {
 [$source,$args]=academicSource($f);
 return queryAll($db,"SELECT COUNT(*) records,COUNT(DISTINCT student_id) population,SUM(units_attempted) units,SUM(units_passed) passed_units,ROUND(100*SUM(units_passed)/NULLIF(SUM(units_attempted),0),1) pass_rate,SUM(CASE WHEN avg_grade>3 OR attendance_rate<75 THEN 1 ELSE 0 END) flagged,ROUND(100*AVG(CASE WHEN avg_grade>3 OR attendance_rate<75 THEN 1 ELSE 0 END),1) risk_rate,ROUND(100*AVG(grade_completed),1) completion_rate,ROUND(AVG(avg_grade),2) average_grade FROM $source",$args)[0];
}
function seteSource(array $f,bool $historical=false): array {
 $where='v.public_synthetic=1 AND p.active=1';$args=[];
 if(!$historical){$where.=' AND v.academic_year=? AND v.semester=?';$args=[$f['year'],$f['sem']];}if($f['program']){$where.=' AND p.code=?';$args[]=$f['program'];}
 if($f['level']||$f['subject'])$where.=' AND 1=0';
 return ['sete_summary v JOIN programs p ON p.id=v.program_id',$where,$args];
}
function seteSummary(PDO $db,array $f): array {
 [$from,$where,$args]=seteSource($f);
 return queryAll($db,"SELECT ROUND(SUM(v.score_total)/NULLIF(SUM(v.response_count),0),2) sete_score,ROUND(100*SUM(CASE WHEN criterion='Clarity' THEN response_count ELSE 0 END)/NULLIF(SUM(CASE WHEN criterion='Clarity' THEN invitations ELSE 0 END),0),1) response_rate,SUM(CASE WHEN criterion='Clarity' THEN response_count ELSE 0 END) respondents FROM $from WHERE $where",$args)[0];
}
function metrics(array $a,array $s): array {
 $definitions=[['Course Pass Rate',$a['pass_rate'],'%',85,'>='],['Students at Risk',$a['risk_rate'],'%',15,'<='],['Teaching Effectiveness',$s['sete_score'],' / 5',4,'>='],['SETE Response Rate',$s['response_rate'],'%',70,'>='],['Grade Completion',$a['completion_rate'],'%',95,'>=']];
 return array_map(function($m){[$label,$value,$unit,$target,$op]=$m;return ['label'=>$label,'raw'=>$value,'value'=>$value===null?'No data':$value.$unit,'target'=>$op.' '.$target.$unit,'low'=>$value!==null&&($op==='>='?$value<$target:$value>$target)];},$definitions);
}
function programRows(PDO $db,array $f): array {
 [$source,$args]=academicSource($f);
 return queryAll($db,"SELECT code AS Program,COUNT(*) AS Records,COUNT(DISTINCT student_id) AS Population,ROUND(100*SUM(units_passed)/NULLIF(SUM(units_attempted),0),1) AS `Pass rate (%)`,SUM(CASE WHEN avg_grade>3 OR attendance_rate<75 THEN 1 ELSE 0 END) AS `Support flags`,ROUND(100*AVG(grade_completed),1) AS `Completion (%)` FROM $source GROUP BY code ORDER BY code",$args);
}
function subjectRows(PDO $db,array $f): array {
 [$from,$where,$args]=scopeSql($f,false,true);
 return queryAll($db,"SELECT s.id,s.code AS Subject,s.title AS Title,GROUP_CONCAT(DISTINCT p.code ORDER BY p.code SEPARATOR ', ') AS Programs,c.year_level AS `Year level`,s.units AS Units,COUNT(*) AS Enrolled,ROUND(100*SUM(CASE WHEN g.completed=1 AND g.grade<=3 THEN 1 ELSE 0 END)/COUNT(*),1) AS `Passing (%)`,ROUND(100*SUM(CASE WHEN g.completed=1 AND g.grade>3 THEN 1 ELSE 0 END)/COUNT(*),1) AS `Failing (%)`,SUM(g.completed=0) AS Pending,ROUND(AVG(g.grade),2) AS `Average grade`,SUM(CASE WHEN g.grade>3 OR g.attendance_rate<75 THEN 1 ELSE 0 END) AS `Support flags` FROM $from WHERE $where GROUP BY s.id,s.code,s.title,c.year_level,s.units ORDER BY s.code",$args);
}
function trends(PDO $db,array $f): array {
 [$source,$args]=academicSource($f,true);
 return queryAll($db,"SELECT academic_year,semester,ROUND(100*SUM(units_passed)/NULLIF(SUM(units_attempted),0),1) pass_rate,ROUND(100*AVG(CASE WHEN avg_grade>3 OR attendance_rate<75 THEN 1 ELSE 0 END),1) risk_rate FROM $source GROUP BY academic_year,semester ORDER BY academic_year,semester",$args);
}
function seteRows(PDO $db,array $f,string $group='p.code'): array {
 if(!in_array($group,['p.code','criterion','v.academic_year,v.semester'],true))throw new InvalidArgumentException('Invalid grouping');
 [$from,$where,$args]=seteSource($f,$group==='v.academic_year,v.semester');
 return queryAll($db,"SELECT $group,ROUND(SUM(score_total)/NULLIF(SUM(response_count),0),2) score,ROUND(100*SUM(CASE WHEN criterion='Clarity' THEN response_count ELSE 0 END)/NULLIF(SUM(CASE WHEN criterion='Clarity' THEN invitations ELSE 0 END),0),1) participation FROM $from WHERE $where GROUP BY $group ORDER BY $group",$args);
}
function distribution(PDO $db,array $f,string $type): array {
 if($type==='grades'){[$from,$where,$args]=scopeSql($f,false,true);return queryAll($db,"SELECT CASE WHEN g.completed=0 THEN 'Pending' WHEN g.grade<=1.75 THEN '1.00-1.75' WHEN g.grade<=2.5 THEN '1.76-2.50' WHEN g.grade<=3 THEN '2.51-3.00' ELSE 'Above 3.00' END AS Band,COUNT(*) AS Records FROM $from WHERE $where GROUP BY Band ORDER BY Band",$args);}
 [$source,$args]=academicSource($f);return queryAll($db,"SELECT year_level AS `Year level`,COUNT(DISTINCT student_id) AS Students FROM $source GROUP BY year_level ORDER BY year_level",$args);
}
function chartData(PDO $db,array $f): array {
 $trend=trends($db,$f);$st=seteRows($db,$f,'v.academic_year,v.semester');$map=[];foreach($st as $row)$map[$row['academic_year'].'|'.$row['semester']]=$row;
 $pr=programRows($db,$f);$sr=subjectRows($db,$f);$te=seteRows($db,$f);$grades=distribution($db,$f,'grades');
 [$source,$args]=academicSource($f,true);$years=queryAll($db,"SELECT academic_year label,ROUND(100*SUM(units_passed)/NULLIF(SUM(units_attempted),0),1) value FROM $source GROUP BY academic_year ORDER BY academic_year",$args);
 return ['periods'=>array_map(fn($r)=>$r['academic_year'].' · '.str_replace(' Semester','',$r['semester']),$trend),'passRates'=>array_map(fn($r)=>(float)$r['pass_rate'],$trend),'seteScores'=>array_map(fn($r)=>isset($map[$r['academic_year'].'|'.$r['semester']])?(float)$map[$r['academic_year'].'|'.$r['semester']]['score']:null,$trend),'departments'=>array_column($te,'code'),'departmentScores'=>array_map('floatval',array_column($te,'score')),'riskDepartments'=>array_column($pr,'Program'),'riskValues'=>array_map('intval',array_column($pr,'Support flags')),'programs'=>array_column($pr,'Program'),'programPass'=>array_map('floatval',array_column($pr,'Pass rate (%)')),'subjects'=>array_column($sr,'Subject'),'subjectPass'=>array_map('floatval',array_column($sr,'Passing (%)')),'gradeBands'=>array_column($grades,'Band'),'gradeCounts'=>array_map('intval',array_column($grades,'Records')),'yearLabels'=>array_column($years,'label'),'yearPass'=>array_map('floatval',array_column($years,'value')),'riskRates'=>array_map(fn($r)=>(float)$r['risk_rate'],$trend),'responsePeriods'=>array_map(fn($r)=>$r['academic_year'].' · '.str_replace(' Semester','',$r['semester']),$st),'responses'=>array_map(fn($r)=>(float)$r['participation'],$st)];
}
