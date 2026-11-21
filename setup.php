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
    require_once __DIR__.'/lib/migration.php';
    migrateCics($pdo);
    exit("Existing records preserved. CICS migration is current.\n");
}
require_once __DIR__.'/lib/migration.php';
migrateCics($pdo);
exit("EduPulse CICS installed with synthetic academic data. Open index.php.\n");
