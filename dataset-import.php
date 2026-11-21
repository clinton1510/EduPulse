<?php
// Mutation endpoint always checks authorization and CSRF on the server.
require __DIR__.'/lib/analytics.php';require __DIR__.'/lib/formats.php';require __DIR__.'/lib/datasets.php';
requireAdmin();if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('POST required');}csrf_check();$result=handleDataset($db);header('Content-Type: application/json');echo js($result);
