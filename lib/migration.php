<?php
function migrateCics(PDO $pdo): void {
    foreach(explode(';',file_get_contents(__DIR__.'/../sql/cics.sql')) as $sql) if(trim($sql)) $pdo->exec($sql);
    foreach(['academic_records','sete_summary'] as $table) {
        $has=$pdo->query("SHOW COLUMNS FROM $table LIKE 'program_id'")->fetch();
        if(!$has) $pdo->exec("ALTER TABLE $table ADD program_id INT DEFAULT NULL, ADD public_synthetic TINYINT NOT NULL DEFAULT 0, ADD INDEX idx_cics_scope(program_id,public_synthetic), ADD FOREIGN KEY (program_id) REFERENCES programs(id)");
    }
    if($pdo->query("SELECT COUNT(*) FROM schema_migrations WHERE version='cics-v1'")->fetchColumn()) { reconcileCics($pdo); return; }
    $pdo->beginTransaction();
    try {
        $insertProgram=$pdo->prepare('INSERT INTO programs(code,title,curriculum_note) VALUES(?,?,?) ON DUPLICATE KEY UPDATE code=VALUES(code)');
        foreach(['BSIT'=>'Information Technology','BSCS'=>'Computer Science','BSIS'=>'Information Systems'] as $code=>$title) $insertProgram->execute([$code,'Bachelor of Science in '.$title,'Illustrative configurable category; not a verified campus offering. Subjects are synthetic, not an official curriculum.']);
        // Map only exact compatible department labels. All original rows remain private archives.
        foreach(['Computer Science'=>'BSCS','Information Technology'=>'BSIT'] as $department=>$code) {
            foreach(['academic_records','sete_summary'] as $table) {
                $st=$pdo->prepare("UPDATE $table SET program_id=(SELECT id FROM programs WHERE code=?) WHERE department=? AND program_id IS NULL");$st->execute([$code,$department]);
            }
        }
        seedCics($pdo);
        $pdo->exec("INSERT INTO schema_migrations(version) VALUES('cics-v1')");
        $pdo->commit();
        reconcileCics($pdo);
    } catch(Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); throw $e; }
}
function reconcileCics(PDO $pdo): void {
    if($pdo->query("SELECT COUNT(*) FROM schema_migrations WHERE version='cics-v1-grade-averages'")->fetchColumn()) return;
    $pdo->beginTransaction();
    try {
        // Synthetic period averages must ignore pending grades, matching detailed records.
        $pdo->exec('UPDATE academic_records a JOIN (SELECT e.academic_record_id,COALESCE(SUM(g.grade*s.units)/NULLIF(SUM(CASE WHEN g.grade IS NOT NULL THEN s.units ELSE 0 END),0),0) average_grade FROM enrollments e JOIN subject_grades g ON g.enrollment_id=e.id JOIN curriculum_subjects c ON c.id=g.curriculum_subject_id JOIN subjects s ON s.id=c.subject_id GROUP BY e.academic_record_id) detail ON detail.academic_record_id=a.id SET a.avg_grade=detail.average_grade WHERE a.public_synthetic=1');
        $pdo->exec("INSERT INTO schema_migrations(version) VALUES('cics-v1-grade-averages')");
        $pdo->commit();
    } catch(Throwable $e){$pdo->rollBack();throw $e;}
}
function seedCics(PDO $pdo): void {
    $periodInsert=$pdo->prepare('INSERT INTO academic_periods(academic_year,semester) VALUES(?,?) ON DUPLICATE KEY UPDATE academic_year=VALUES(academic_year)');
    foreach(['2024-2025','2025-2026','2026-2027'] as $year) foreach(['1st Semester','2nd Semester'] as $sem) $periodInsert->execute([$year,$sem]);
    $periods=$pdo->query('SELECT * FROM academic_periods ORDER BY academic_year,semester')->fetchAll();
    $subjectInsert=$pdo->prepare('INSERT INTO subjects(code,title,units) VALUES(?,?,3)');
    $assignment=$pdo->prepare('INSERT INTO curriculum_subjects(program_id,subject_id,year_level,semester) VALUES(?,?,?,?)');
    $topics=[['Computing Foundations','Programming Fundamentals'],['Data Structures','Database Systems'],['Software Engineering','Networks and Security'],['Applied Research','Capstone Studio']];
    $subjectIds=[];
    foreach($topics as $level=>$pair) foreach(['1st Semester','2nd Semester'] as $si=>$sem) foreach($pair as $ti=>$topic) {
        $code='SYN-'.($level+1).($si+1).($ti+1);$subjectInsert->execute([$code,$topic.' '.($si===0?'I':'II')]);$subjectIds[$level+1][$sem][]=(int)$pdo->lastInsertId();
    }
    $record=$pdo->prepare('INSERT INTO academic_records(student_code,academic_year,semester,department,units_attempted,units_passed,avg_grade,attendance_rate,grade_completed,program_id,public_synthetic) VALUES(?,?,?,?,?,?,?,?,?,?,1)');
    $student=$pdo->prepare('INSERT INTO students(public_code,program_id) VALUES(?,?)');
    $enroll=$pdo->prepare('INSERT INTO enrollments(student_id,period_id,program_id,year_level,academic_record_id) VALUES(?,?,?,?,?)');
    $gradeInsert=$pdo->prepare('INSERT INTO subject_grades(enrollment_id,curriculum_subject_id,grade,attendance_rate,completed) VALUES(?,?,?,?,?)');
    $faculty=$pdo->prepare('INSERT INTO faculty(display_name,department) VALUES(?,?)');
    $eval=$pdo->prepare('INSERT INTO sete_summary(faculty_id,academic_year,semester,department,criterion,score_total,response_count,invitations,program_id,public_synthetic) VALUES(?,?,?,?,?,?,?,?,?,1)');
    foreach($pdo->query('SELECT * FROM programs ORDER BY id')->fetchAll() as $pi=>$program) {
        $curriculum=[];
        foreach($subjectIds as $level=>$sems) foreach($sems as $sem=>$ids) foreach($ids as $sid){$assignment->execute([$program['id'],$sid,$level,$sem]);$curriculum[$level][$sem][]=(int)$pdo->lastInsertId();}
        foreach($periods as $periodIndex=>$period) {
            foreach(range(1,4) as $level) foreach(range(1,18) as $n) {
                // Period-specific cohorts avoid implying real student longitudinal histories.
                $code=sprintf('SYN-%s-%d-%d-%02d',$program['code'],$period['id'],$level,$n);
                $student->execute([$code,$program['id']]);$studentId=(int)$pdo->lastInsertId();
                $grades=[];$attendance=[];$completed=[];$passed=0;
                foreach($curriculum[$level][$period['semester']] as $j=>$cid){$g=round(min(5,1.25+(($n*7+$level*3+$pi*5+$j*11+$periodIndex*2)%31)/10),2);$att=65+(($n*3+$j*7+$pi*2+$periodIndex)%36);$done=(($n+$j+$periodIndex)%17)!==0;$grades[]=$g;$attendance[]=$att;$completed[]=$done;if($done&&$g<=3)$passed+=3;}
                $record->execute([$code,$period['academic_year'],$period['semester'],$program['title'],count($grades)*3,$passed,array_sum($grades)/count($grades),array_sum($attendance)/count($attendance),(int)min($completed),$program['id']]);$rid=(int)$pdo->lastInsertId();
                $enroll->execute([$studentId,$period['id'],$program['id'],$level,$rid]);$eid=(int)$pdo->lastInsertId();
                foreach($curriculum[$level][$period['semester']] as $j=>$cid) $gradeInsert->execute([$eid,$cid,$completed[$j]?$grades[$j]:null,$attendance[$j],(int)$completed[$j]]);
            }
        }
        foreach(range(1,3) as $fi){$faculty->execute(['Synthetic instructor '.$program['code'].' '.$fi,$program['title']]);$fid=(int)$pdo->lastInsertId();foreach($periods as $i=>$period){$invites=72;$responses=43+(($i*3+$pi*4+$fi*2)%25);foreach(['Clarity','Engagement','Assessment','Support'] as $ci=>$criterion){$score=round(3.4+(($i+$pi+$fi+$ci)%12)/10,2);$eval->execute([$fid,$period['academic_year'],$period['semester'],$program['title'],$criterion,$score*$responses,$responses,$invites,$program['id']]);}}}
    }
    $pdo->exec("INSERT INTO dataset_imports(filename,data_class,row_count,status) VALUES('Built-in CICS seed','synthetic',1296,'completed')");
}
