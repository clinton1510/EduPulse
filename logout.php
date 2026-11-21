<?php
require __DIR__.'/config/app.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('POST required');}
csrf_check();$_SESSION=[];session_destroy();header('Location: index.php');exit;
