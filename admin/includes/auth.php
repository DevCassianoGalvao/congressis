<?php
// Autenticação e CSRF
require_once __DIR__ . '/../../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

function is_authenticated(): bool {
    if (empty($_SESSION['admin_id']) || empty($_SESSION['last_activity'])) {
        return false;
    }
    // Timeout de inatividade
    if (time() - (int)$_SESSION['last_activity'] > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        return false;
    }
    $_SESSION['last_activity'] = time();
    return true;
}

function require_auth(): void {
    if (!is_authenticated()) {
        $base = defined('BASE_PATH') ? BASE_PATH : '';
        header('Location: ' . $base . '/admin/login.php');
        exit;
    }
}

function generate_csrf(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(string $token): bool {
    return !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generate_csrf()) . '">';
}

function get_current_user_name(): string {
    return $_SESSION['admin_username'] ?? 'desconhecido';
}
