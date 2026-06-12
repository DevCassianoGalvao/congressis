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

function send_lead_notification(array $lead): bool {
    if (!defined('BREVO_API_KEY') || BREVO_API_KEY === 'SUA_API_KEY_AQUI') {
        return false; // Não configurado ainda
    }

    $payload = [
        'sender' => [
            'email' => BREVO_SENDER_EMAIL,
            'name'  => BREVO_SENDER_NAME,
        ],
        'to' => [[
            'email' => BREVO_NOTIFY_EMAIL,
            'name'  => BREVO_NOTIFY_NAME,
        ]],
        'subject'     => '🎉 Novo lead no Congressis: ' . $lead['nome'],
        'htmlContent' => build_lead_email_html($lead),
    ];

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'accept: application/json',
            'api-key: ' . BREVO_API_KEY,
            'content-type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT    => 10,
    ]);

    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 201) {
        write_log('[Brevo] Falha ao enviar e-mail. HTTP ' . $http_code . ' — ' . $response);
        return false;
    }
    return true;
}

function build_lead_email_html(array $lead): string {
    $nome     = htmlspecialchars($lead['nome']      ?? '');
    $email    = htmlspecialchars($lead['email']     ?? '');
    $telefone = htmlspecialchars($lead['telefone']  ?? '—');
    $cpf_raw  = $lead['cpf']  ?? '';
    $cep_raw  = $lead['cep']  ?? '';
    $cnpj_raw = $lead['cnpj'] ?? '';
    $utm      = htmlspecialchars($lead['utm_source'] ?? '—');
    $data     = date('d/m/Y \à\s H:i');

    // Formatar CPF, CEP, CNPJ
    $cpf_fmt  = strlen($cpf_raw)  === 11 ? substr($cpf_raw,0,3).'.'.substr($cpf_raw,3,3).'.'.substr($cpf_raw,6,3).'-'.substr($cpf_raw,9,2)  : ($cpf_raw  ?: '—');
    $cep_fmt  = strlen($cep_raw)  === 8  ? substr($cep_raw,0,5).'-'.substr($cep_raw,5)                                                        : ($cep_raw  ?: '—');
    $cnpj_fmt = strlen($cnpj_raw) === 14 ? substr($cnpj_raw,0,2).'.'.substr($cnpj_raw,2,3).'.'.substr($cnpj_raw,5,3).'/'.substr($cnpj_raw,8,4).'-'.substr($cnpj_raw,12) : ($cnpj_raw ?: '—');

    $wa_num = preg_replace('/\D/', '', $telefone);

    return "
    <div style='font-family:Georgia,serif;max-width:520px;margin:0 auto;background:#faf7ef;border-radius:8px;overflow:hidden;'>
        <div style='background:#13251b;padding:28px 32px;text-align:center;'>
            <p style='color:#c9a24b;letter-spacing:.15em;font-size:12px;margin:0 0 6px;font-family:Arial,sans-serif;text-transform:uppercase;'>Novo Lead</p>
            <h1 style='color:#f6f2e9;font-size:22px;margin:0;'>Congressis – Saúde Integrativa na Serra</h1>
        </div>
        <div style='padding:28px 32px;'>
            <p style='color:#6f7a6b;font-size:13px;margin:0 0 20px;font-family:Arial,sans-serif;'>Recebido em {$data}</p>
            <table style='width:100%;border-collapse:collapse;font-family:Arial,sans-serif;font-size:14px;'>
                <tr><td style='padding:10px 0;border-bottom:1px solid #e4dcc8;color:#6f7a6b;width:35%;'>Nome</td><td style='padding:10px 0;border-bottom:1px solid #e4dcc8;color:#2b2b27;font-weight:bold;'>{$nome}</td></tr>
                <tr><td style='padding:10px 0;border-bottom:1px solid #e4dcc8;color:#6f7a6b;'>E-mail</td><td style='padding:10px 0;border-bottom:1px solid #e4dcc8;color:#2b2b27;'><a href='mailto:{$email}' style='color:#b8893b;'>{$email}</a></td></tr>
                <tr><td style='padding:10px 0;border-bottom:1px solid #e4dcc8;color:#6f7a6b;'>Celular</td><td style='padding:10px 0;border-bottom:1px solid #e4dcc8;color:#2b2b27;'><a href='https://wa.me/55{$wa_num}' style='color:#b8893b;'>{$telefone}</a></td></tr>
                <tr><td style='padding:10px 0;border-bottom:1px solid #e4dcc8;color:#6f7a6b;'>CPF</td><td style='padding:10px 0;border-bottom:1px solid #e4dcc8;color:#2b2b27;'>{$cpf_fmt}</td></tr>
                <tr><td style='padding:10px 0;border-bottom:1px solid #e4dcc8;color:#6f7a6b;'>CEP</td><td style='padding:10px 0;border-bottom:1px solid #e4dcc8;color:#2b2b27;'>{$cep_fmt}</td></tr>
                <tr><td style='padding:10px 0;border-bottom:1px solid #e4dcc8;color:#6f7a6b;'>CNPJ</td><td style='padding:10px 0;border-bottom:1px solid #e4dcc8;color:#2b2b27;'>{$cnpj_fmt}</td></tr>
                <tr><td style='padding:10px 0;color:#6f7a6b;'>Origem (UTM)</td><td style='padding:10px 0;color:#2b2b27;'>{$utm}</td></tr>
            </table>
            <div style='margin-top:24px;text-align:center;'>
                <a href='https://wa.me/55{$wa_num}' style='display:inline-block;background:#25d366;color:white;padding:12px 24px;border-radius:6px;text-decoration:none;font-family:Arial,sans-serif;font-size:14px;font-weight:bold;margin-right:8px;'>💬 WhatsApp</a>
                <a href='mailto:{$email}' style='display:inline-block;background:#13251b;color:#f6f2e9;padding:12px 24px;border-radius:6px;text-decoration:none;font-family:Arial,sans-serif;font-size:14px;font-weight:bold;'>✉️ E-mail</a>
            </div>
        </div>
        <div style='background:#13251b;padding:16px 32px;text-align:center;'>
            <p style='color:#6f7a6b;font-size:12px;margin:0;font-family:Arial,sans-serif;'>© 2026 Congressis – Saúde Integrativa na Serra</p>
        </div>
    </div>
    ";
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
