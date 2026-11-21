<?php
function adminConfigured(): bool {return strlen((string)getenv('EDUPULSE_ADMIN_KEY'))>=32;}
function adminAllowed(): bool {return adminConfigured()&&isset($_SESSION['admin_until'])&&$_SESSION['admin_until']>time()&&hash_equals((string)($_SESSION['admin_key_hash']??''),hash('sha256',(string)getenv('EDUPULSE_ADMIN_KEY')));}
function requireAdmin(): void {if(!adminAllowed()){http_response_code(403);exit('Dataset administration is disabled or requires server-side authorization.');}}
function importColumns(): array{return ['student_code','program','academic_year','semester','year_level','subject_code','grade','attendance_rate','completed'];}
function validateDataset(PDO $db,array $rows): array {
 if(count($rows)<2||count($rows)>2001)throw new RuntimeException('Provide a header and between 1 and 2,000 data rows.');
 $header=array_map(fn($x)=>trim((string)$x),array_shift($rows));$header[0]=preg_replace('/^\xEF\xBB\xBF/','',$header[0]);if($header!==importColumns())throw new RuntimeException('Columns must match the template exactly.');
 $programs=array_column(queryAll($db,'SELECT id,code FROM programs WHERE active=1'),'id','code');$periods=[];foreach(queryAll($db,'SELECT * FROM academic_periods') as $p)$periods[$p['academic_year'].'|'.$p['semester']]=$p['id'];
 $curriculum=[];foreach(queryAll($db,'SELECT c.id,c.program_id,c.year_level,c.semester,s.code,s.units FROM curriculum_subjects c JOIN subjects s ON s.id=c.subject_id') as $c)$curriculum[$c['program_id'].'|'.$c['year_level'].'|'.$c['semester'].'|'.$c['code']]=$c;
 $seen=[];$valid=[];$errors=[];$groups=[];
 foreach($rows as $i=>$values){$rowNo=$i+2;try{
  if(count($values)!==count($header))throw new RuntimeException('Incorrect number of columns.');$r=array_combine($header,array_map(fn($v)=>trim((string)$v),$values));
  foreach($r as $key=>$value)if($value===''&&$key!=='grade')throw new RuntimeException('Missing '.$key.'.');
  if(!preg_match('/^[A-Za-z0-9_-]{1,40}$/',$r['student_code']))throw new RuntimeException('Student code must be a pseudonymous code (no names).');
  if(!isset($programs[$r['program']]))throw new RuntimeException('Unknown or inactive CICS program.');$pid=$programs[$r['program']];
  $period=$periods[$r['academic_year'].'|'.$r['semester']]??null;if(!$period)throw new RuntimeException('Unknown academic period.');
  if(!in_array($r['year_level'],['1','2','3','4'],true))throw new RuntimeException('Invalid year level.');
  $c=$curriculum[$pid.'|'.$r['year_level'].'|'.$r['semester'].'|'.$r['subject_code']]??null;if(!$c)throw new RuntimeException('Subject is not assigned to this program, year level and semester.');
  if(!in_array($r['completed'],['0','1'],true))throw new RuntimeException('Completed must be 0 or 1.');
  if($r['completed']==='1'&&(!is_numeric($r['grade'])||!is_finite((float)$r['grade'])||(float)$r['grade']<1||(float)$r['grade']>5))throw new RuntimeException('Completed grades must be between 1 and 5.');
  if($r['completed']==='0'&&$r['grade']!=='')throw new RuntimeException('Pending grades must be blank.');
  if(!is_numeric($r['attendance_rate'])||!is_finite((float)$r['attendance_rate'])||(float)$r['attendance_rate']<0||(float)$r['attendance_rate']>100)throw new RuntimeException('Attendance must be 0 to 100.');
  $group=$r['student_code'].'|'.$pid.'|'.$period;$key=$group.'|'.$c['id'];if(isset($seen[$key]))throw new RuntimeException('Duplicate student/period/subject row.');$seen[$key]=true;
  if(isset($groups[$group])&&$groups[$group]!==$r['year_level'])throw new RuntimeException('Student year level is inconsistent within a period.');$groups[$group]=$r['year_level'];
  $exists=queryAll($db,'SELECT id FROM students WHERE public_code=?',[$r['student_code']]);if($exists)throw new RuntimeException('Student code already exists. Imports only add new cohorts; existing data is never overwritten.');
  $r['_program_id']=$pid;$r['_period_id']=$period;$r['_curriculum_id']=$c['id'];$r['_units']=$c['units'];$valid[]=$r;
 }catch(RuntimeException $e){$errors[]='Row '.$rowNo.': '.$e->getMessage();}}
 return ['rows'=>$valid,'errors'=>$errors,'total'=>count($rows)];
}
function commitDataset(PDO $db,array $rows,string $class,string $filename): int {
 // Re-validate at confirmation time, not only at preview time.
 $plain=[importColumns()];foreach($rows as $r)$plain[]=array_map(fn($key)=>$r[$key],importColumns());$result=validateDataset($db,$plain);if($result['errors'])throw new RuntimeException(implode(' ',$result['errors']));$rows=$result['rows'];
 $synthetic=$class==='synthetic'?1:0;$grouped=[];foreach($rows as $r)$grouped[$r['student_code'].'|'.$r['_program_id'].'|'.$r['_period_id']][]=$r;
 $db->beginTransaction();try{
  $students=[];
  foreach($grouped as $group){$r=$group[0];$code=$r['student_code'];if(!isset($students[$code])){$st=$db->prepare('INSERT INTO students(public_code,program_id,synthetic) VALUES(?,?,?)');$st->execute([$code,$r['_program_id'],$synthetic]);$students[$code]=[(int)$db->lastInsertId(),$r['_program_id']];}elseif($students[$code][1]!==$r['_program_id'])throw new RuntimeException('A student code cannot belong to multiple programs.');
   $units=0;$passed=0;$gradeSum=0;$gradedUnits=0;$att=0;$completed=1;foreach($group as $g){$u=(int)$g['_units'];$units+=$u;if($g['completed']==='1'){$gradeSum+=(float)$g['grade']*$u;$gradedUnits+=$u;if((float)$g['grade']<=3)$passed+=$u;}else$completed=0;$att+=(float)$g['attendance_rate']*$u;}
   // Pending-only period average is 0; pending grade completion remains clearly flagged separately.
   $st=$db->prepare('INSERT INTO academic_records(student_code,academic_year,semester,department,units_attempted,units_passed,avg_grade,attendance_rate,grade_completed,program_id,public_synthetic) VALUES(?,?,?,?,?,?,?,?,?,?,?)');$st->execute([$code,$r['academic_year'],$r['semester'],$r['program'],$units,$passed,$gradedUnits?$gradeSum/$gradedUnits:0,$att/$units,$completed,$r['_program_id'],$synthetic]);$rid=(int)$db->lastInsertId();
   $st=$db->prepare('INSERT INTO enrollments(student_id,period_id,program_id,year_level,academic_record_id) VALUES(?,?,?,?,?)');$st->execute([$students[$code][0],$r['_period_id'],$r['_program_id'],$r['year_level'],$rid]);$eid=(int)$db->lastInsertId();
   $st=$db->prepare('INSERT INTO subject_grades(enrollment_id,curriculum_subject_id,grade,attendance_rate,completed) VALUES(?,?,?,?,?)');foreach($group as $g)$st->execute([$eid,$g['_curriculum_id'],$g['grade']===''?null:$g['grade'],$g['attendance_rate'],$g['completed']]);
  }
  $st=$db->prepare('INSERT INTO dataset_imports(filename,data_class,row_count,status) VALUES(?,?,?,?)');$st->execute([$filename,$class,count($rows),'completed']);$id=(int)$db->lastInsertId();$db->commit();return $id;
 }catch(Throwable $e){$db->rollBack();throw $e;}
}
function handleDataset(PDO $db): array {
 $feedback=[];
 if($_SERVER['REQUEST_METHOD']!=='POST')return $feedback;
 csrf_check();$action=scalarParam($_POST,'action');
 if($action==='admin-unlock'){
  if(!adminConfigured()){http_response_code(403);return ['error'=>'Administration is disabled on this server.'];}
  if(($_SESSION['admin_lock_until']??0)>time()){http_response_code(429);return ['error'=>'Too many attempts. Wait 15 minutes.'];}
  $key=scalarParam($_POST,'admin_key');if(!hash_equals((string)getenv('EDUPULSE_ADMIN_KEY'),$key)){$_SESSION['admin_attempts']=($_SESSION['admin_attempts']??0)+1;if($_SESSION['admin_attempts']>=5)$_SESSION['admin_lock_until']=time()+900;http_response_code(403);return ['error'=>'Invalid administration key.'];}
  session_regenerate_id(true);$_SESSION['admin_until']=time()+1800;$_SESSION['admin_key_hash']=hash('sha256',$key);$_SESSION['admin_attempts']=0;return ['message'=>'Administration unlocked for 30 minutes.'];
 }
 requireAdmin();
 if($action==='admin-lock'){unset($_SESSION['admin_until'],$_SESSION['admin_key_hash'],$_SESSION['dataset_preview']);return ['message'=>'Administration locked.'];}
 try{
  if($action==='preview'){
   unset($_SESSION['dataset_preview']);$class=scalarParam($_POST,'data_class');if(!in_array($class,['synthetic','institutional'],true))throw new RuntimeException('Choose a data classification.');if($class==='institutional'&&scalarParam($_POST,'authorized')!=='1')throw new RuntimeException('Confirm institutional authorization before import.');
   $file=$_FILES['dataset']??null;if(!$file||$file['error']!==UPLOAD_ERR_OK)throw new RuntimeException('Upload failed. Choose a CSV or XLSX file.');if($file['size']>2000000)throw new RuntimeException('Maximum file size is 2 MB.');$name=basename($file['name']);$ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));$mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
   if(!in_array($ext,['csv','xlsx'],true))throw new RuntimeException('Only CSV and XLSX workbooks are supported.');
   if($ext==='csv'){if(!in_array($mime,['text/plain','text/csv','application/csv','application/octet-stream'],true))throw new RuntimeException('File content is not CSV.');$handle=fopen($file['tmp_name'],'r');$rows=[];while(($row=fgetcsv($handle,0,',','"',''))!==false){if($row===[null])continue;$rows[]=$row;if(count($rows)>2001)break;}fclose($handle);}
   else{if(!in_array($mime,['application/zip','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/octet-stream'],true))throw new RuntimeException('File content is not XLSX.');$rows=xlsxRead(file_get_contents($file['tmp_name']));}
   $preview=validateDataset($db,$rows);$preview['filename']=substr($name,0,180);$preview['class']=$class;
   if(!$preview['errors']){$_SESSION['dataset_preview']=$preview;$_SESSION['dataset_preview']['expires']=time()+900;}
   return ['preview'=>$preview];
  }
  if($action==='confirm'){$pending=$_SESSION['dataset_preview']??null;if(!$pending||$pending['expires']<time())throw new RuntimeException('Preview expired. Upload and validate the dataset again.');$id=commitDataset($db,$pending['rows'],$pending['class'],$pending['filename']);unset($_SESSION['dataset_preview']);return ['message'=>'Import #'.$id.' completed. '.($pending['class']==='synthetic'?'Synthetic records are available in analytics.':'Institutional records remain private and are excluded from every public analytics view and export.')];}
  throw new RuntimeException('Unknown dataset operation.');
 }catch(RuntimeException $e){return ['error'=>$e->getMessage()];}catch(Throwable $e){return ['error'=>'Import could not be completed. No partial records were saved. Check the file for duplicate codes or inconsistent records.'];}
}
