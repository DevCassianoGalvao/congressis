<?php
require_once __DIR__ . '/includes/auth.php';
if (is_authenticated()) {
    header('Location: /admin/leads.php');
} else {
    header('Location: /admin/login.php');
}
exit;
