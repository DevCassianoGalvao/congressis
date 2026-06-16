<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
session_unset();
session_destroy();
$base = defined('BASE_PATH') ? BASE_PATH : '';
header('Location: ' . $base . '/admin/login.php');
exit;
