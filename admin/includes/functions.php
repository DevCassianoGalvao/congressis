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

/**
 * Valida e salva um logo de apoiador enviado via upload.
 * Retorna [bool $ok, ?string $error, ?string $filename].
 */
function validate_and_save_sponsor_logo(array $file, string $upload_dir): array {
    $allowed_ext   = ['jpg', 'jpeg', 'png', 'svg', 'webp'];
    $allowed_mimes = ['image/jpeg', 'image/png', 'image/svg+xml', 'image/webp'];

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_ext, true)) {
        return [false, 'Extensão não permitida. Use: jpg, png, svg, webp.', null];
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        return [false, 'Arquivo muito grande. Máximo 2 MB.', null];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowed_mimes, true)) {
        return [false, 'Tipo de arquivo inválido. Envie uma imagem real (jpg, png, svg, webp).', null];
    }

    $new_filename = 'sponsor_' . uniqid() . '_' . time() . '.' . $ext;
    $dest         = $upload_dir . $new_filename;

    if ($ext === 'svg') {
        $svg = file_get_contents($file['tmp_name']);
        $svg = preg_replace('/<script[\s\S]*?<\/script>/i', '', $svg);
        $svg = preg_replace('/\bon\w+\s*=/i', 'data-removed=', $svg);
        if (file_put_contents($dest, $svg) === false) {
            return [false, 'Erro ao salvar o arquivo SVG.', null];
        }
    } else {
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            return [false, 'Erro ao mover o arquivo enviado.', null];
        }
        $img = @imagecreatefromstring(file_get_contents($dest));
        if ($img === false) {
            @unlink($dest);
            return [false, 'Imagem corrompida ou inválida.', null];
        }
        $saved = match ($ext) {
            'png'  => imagepng($img,  $dest, 9),
            'webp' => imagewebp($img, $dest, 90),
            default => imagejpeg($img, $dest, 90),
        };
        imagedestroy($img);
        if (!$saved) {
            @unlink($dest);
            return [false, 'Erro ao reprocessar a imagem.', null];
        }
    }

    return [true, null, $new_filename];
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
