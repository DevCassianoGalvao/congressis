<?php
// Helpers gerais do painel

function set_security_headers(): void {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:;");
}

function sanitize_text(string $value): string {
    return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
}

function sanitize_digits(string $value): string {
    return preg_replace('/\D/', '', $value);
}

function get_client_ip(): string {
    $keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}

function write_log(string $message): void {
    $log_path = __DIR__ . '/../../logs/admin.log';
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    file_put_contents($log_path, $line, FILE_APPEND | LOCK_EX);
}

// Rate limiting por IP (tabela rate_limit)
function check_rate_limit(PDO $pdo, string $ip, int $max = 3, int $window = 600): bool {
    // Limpar registros antigos
    $pdo->prepare('DELETE FROM rate_limit WHERE last_attempt < DATE_SUB(NOW(), INTERVAL ? SECOND)')
        ->execute([$window]);

    $stmt = $pdo->prepare('SELECT attempts, last_attempt FROM rate_limit WHERE ip = ?');
    $stmt->execute([$ip]);
    $row = $stmt->fetch();

    if ($row) {
        if ((int)$row['attempts'] >= $max) {
            return false; // Bloqueado
        }
        $pdo->prepare('UPDATE rate_limit SET attempts = attempts + 1, last_attempt = NOW() WHERE ip = ?')
            ->execute([$ip]);
    } else {
        $pdo->prepare('INSERT INTO rate_limit (ip, attempts, last_attempt) VALUES (?, 1, NOW())')
            ->execute([$ip]);
    }
    return true;
}

// Rate limiting para login (usa $_SESSION para não depender do banco)
function check_login_rate_limit(): bool {
    $key = 'login_attempts';
    $time_key = 'login_first_attempt';
    $max = 5;
    $window = 900; // 15 minutos

    if (empty($_SESSION[$time_key])) {
        $_SESSION[$time_key] = time();
        $_SESSION[$key] = 0;
    }

    // Resetar janela se expirou
    if (time() - (int)$_SESSION[$time_key] > $window) {
        $_SESSION[$key] = 0;
        $_SESSION[$time_key] = time();
    }

    if ((int)$_SESSION[$key] >= $max) {
        return false;
    }

    $_SESSION[$key]++;
    return true;
}

function reset_login_rate_limit(): void {
    unset($_SESSION['login_attempts'], $_SESSION['login_first_attempt']);
}

function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function paginate(int $total, int $per_page, int $current_page): array {
    $total_pages = max(1, (int)ceil($total / $per_page));
    $current_page = max(1, min($current_page, $total_pages));
    $offset = ($current_page - 1) * $per_page;
    return [
        'total'       => $total,
        'per_page'    => $per_page,
        'current'     => $current_page,
        'total_pages' => $total_pages,
        'offset'      => $offset,
    ];
}
