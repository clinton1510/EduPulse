<?php
// Run only from the command line: php setup.php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
$config = require __DIR__ . '/config/database.php';
$pdo = new PDO(sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $config['host'], $config['port']), $config['username'], $config['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$dbname = $config['database'];
if (!preg_match('/^[a-zA-Z0-9_]+$/', $dbname)) exit("Invalid database name\n");
$pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `$dbname`");
foreach (explode(';', file_get_contents(__DIR__.'/sql/schema.sql')) as $sql) {
    if (trim($sql)) $pdo->exec($sql);
}
if ((int)$pdo->query('SELECT COUNT(*) FROM academic_records')->fetchColumn() > 0) {
    exit("Database already contains sample records. Skipping seed.\n");
}
$pdo->beginTransaction();
$departments = ['Computer Science', 'Information Technology', 'Business Administration'];
$years = ['2024-2025','2025-2026','2026-2027'];
$semesters = ['1st Semester','2nd Semester'];
$facultyStmt = $pdo->prepare('INSERT INTO faculty (display_name,department) VALUES (?,?)');
$names = ['Dr. A. Reyes','Prof. J. Cruz','Prof. L. Santos','Dr. C. Lim','Prof. R. Garcia','Prof. M. Flores','Dr. S. Ramos','Prof. K. Tan','Prof. V. Dizon'];
$facultyIds = [];
foreach ($names as $i => $name) {
 $find=$pdo->prepare('SELECT id FROM faculty WHERE display_name=? AND department=? ORDER BY id LIMIT 1');
 $find->execute([$name,$departments[intdiv($i,3)]]); $id=$find->fetchColumn();
 if (!$id) { $facultyStmt->execute([$name,$departments[intdiv($i,3)]]); $id=$pdo->lastInsertId(); }
 $facultyIds[]=(int)$id;
}
$record = $pdo->prepare('INSERT INTO academic_records (student_code,academic_year,semester,department,units_attempted,units_passed,avg_grade,attendance_rate,grade_completed) VALUES (?,?,?,?,?,?,?,?,?)');
$eval = $pdo->prepare('INSERT INTO sete_summary (faculty_id,academic_year,semester,department,criterion,score_total,response_count,invitations) VALUES (?,?,?,?,?,?,?,?)');
$criteria = ['Clarity','Engagement','Assessment','Support'];
mt_srand(405);
foreach ($years as $yi=>$year) foreach ($semesters as $si=>$sem) {
 foreach ($departments as $di=>$dept) {
    for ($j=1;$j<=80;$j++) {
      $att = mt_rand(58,100);
      $grade = round(max(1.0,min(5.0, 1.15 + (100-$att)*0.049 + mt_rand(0,130)/100)),2);
      $passUnits = ($grade <= 3.0) ? 18 : mt_rand(6,15);
      $record->execute([sprintf('STU-%d-%d-%03d',$yi,$di,$j),$year,$sem,$dept,18,$passUnits,$grade,$att,mt_rand(1,100)>8?1:0]);
    }
    for ($f=($di*3)+1;$f<=($di*3)+3;$f++) {
      $invites = 60 + mt_rand(0,30);
      $responses = (int)floor($invites * mt_rand(42,91)/100);
      foreach ($criteria as $cidx=>$criterion) {
        $avg = max(2.2,min(4.85,3.4 + $yi*.1 + $si*.06 + ($di===1?-.1:.1) + mt_rand(-50,50)/100));
        $eval->execute([$facultyIds[$f-1],$year,$sem,$dept,$criterion,round($avg*$responses,2),$responses,$invites]);
      }
    }
 }
}
$users = $pdo->prepare('INSERT INTO users (full_name,email,password_hash,role,department,faculty_id) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE email=VALUES(email)');
$users->execute(['Program Chair','chair@edupulse.local',password_hash('ChangeMe123!',PASSWORD_DEFAULT),'chair',null,null]);
$users->execute(['Faculty Member','faculty@edupulse.local',password_hash('ChangeMe123!',PASSWORD_DEFAULT),'faculty','Computer Science',$facultyIds[0]]);
$pdo->commit();
echo "EduPulse installed with synthetic data.\nLocal accounts: chair@edupulse.local and faculty@edupulse.local\nInitial password: ChangeMe123! (change/remove before deployment).\n";
