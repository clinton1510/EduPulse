<?php
date_default_timezone_set('Asia/Manila');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
    ]);
}
$config = require __DIR__ . '/database.php';
try {
    $db = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['host'], $config['port'], $config['database']),
        $config['username'], $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
    );
} catch (PDOException $e) {
    http_response_code(503);
    exit('Database connection failed. Check config/database.php and run: php setup.php');
}
function h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function auth(): array {
    // Compatibility helper: public workspace identity is a role label, never authorization.
    return ['name'=>'Program Chair Workspace','role'=>'public','department'=>null,'faculty_id'=>null];
}
function csrf(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_check(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '')) { http_response_code(403); exit('Invalid CSRF token'); }
}
function queryAll(PDO $db, string $sql, array $params = []): array {
    $stmt = $db->prepare($sql); $stmt->execute($params); return $stmt->fetchAll();
}
function js($value): string { return json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR); }
function scalarParam(array $source,string $key,string $default=''): string { return isset($source[$key]) && is_scalar($source[$key]) ? (string)$source[$key] : $default; }
