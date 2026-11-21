<?php
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
    if (empty($_SESSION['user'])) { header('Location: login.php'); exit; }
    $name = $GLOBALS['db']->prepare('SELECT full_name FROM users WHERE id = ?');
    $name->execute([$_SESSION['user']['id']]);
    $currentName = $name->fetchColumn();
    if ($currentName !== false) $_SESSION['user']['name'] = $currentName;
    return $_SESSION['user'];
}
function csrf(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_check(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(403); exit('Invalid CSRF token'); }
}
function queryAll(PDO $db, string $sql, array $params = []): array {
    $stmt = $db->prepare($sql); $stmt->execute($params); return $stmt->fetchAll();
}
