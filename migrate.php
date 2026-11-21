<?php
// Non-destructive, repeatable CLI migration. No HTTP writes.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
require_once __DIR__.'/lib/migration.php';
$config=require __DIR__.'/config/database.php';
$pdo=new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',$config['host'],$config['port'],$config['database']),$config['username'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
migrateCics($pdo);
echo "CICS migration complete. Legacy records preserved; public scope uses the separately seeded synthetic CICS dataset.\n";
