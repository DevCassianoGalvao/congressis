<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
if (is_authenticated()) {
    admin_redirect('/admin/leads.php');
} else {
    admin_redirect('/admin/login.php');
}
