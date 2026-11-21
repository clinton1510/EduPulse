<?php
require __DIR__ . '/config/app.php';
if (!empty($_SESSION['user'])) { header('Location: index.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([trim($_POST['email'] ?? '')]);
    $user = $stmt->fetch();
    if ($user && password_verify($_POST['password'] ?? '', $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = ['id'=>$user['id'], 'name'=>$user['full_name'], 'role'=>$user['role'], 'department'=>$user['department'], 'faculty_id'=>$user['faculty_id']];
        header('Location: index.php'); exit;
    }
    $error = 'Invalid email or password.';
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sign In · EduPulse</title><link rel="stylesheet" href="assets/style.css"></head>
<body class="login-page"><main class="login-card"><div class="brand brand-login"><span class="brand-mark">◈</span><span>Edu<span class="brand-accent">Pulse</span></span></div><p class="muted">Academic Intelligence & Teaching Effectiveness</p><h1>Welcome back</h1><p class="muted">Sign in to access your analytics workspace.</p><?php if ($error): ?><p class="error" role="alert"><?=h($error)?></p><?php endif; ?><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><label>Email address<input type="email" name="email" required autocomplete="username" placeholder="chair@edupulse.local"></label><label>Password<input type="password" name="password" required autocomplete="current-password" placeholder="••••••••"></label><button class="btn-primary" type="submit">Sign in →</button></form><p class="hint">Demo: chair@edupulse.local / ChangeMe123!</p><p class="hint">Synthetic demonstration data only. Not for personnel decisions.</p></main></body></html>
