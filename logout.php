<?php
require __DIR__ . '/config/app.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
csrf_check();
$_SESSION = [];
session_destroy();
header('Location: login.php');
