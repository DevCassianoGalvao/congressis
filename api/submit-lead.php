<?php
/**
 * Endpoint de captura de lead da landing page.
 * Aceita apenas POST com Content-Type: application/json.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../admin/includes/db.php';
require_once __DIR__ . '/../admin/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
set_security_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido.']);
    exit;
}

$ct = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($ct, 'application/json') === false) {
    http_response_code(415);
    echo json_encode(['error' => 'Content-Type deve ser application/json.']);
    exit;
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (!empty(APP_DOMAIN) && $origin !== '' && rtrim($origin, '/') !== rtrim(APP_DOMAIN, '/')) {
    http_response_code(403);
    echo json_encode(['error' => 'Origem não permitida.']);
    exit;
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'JSON inválido.']);
    exit;
}

// Honeypot
if (!empty($data['website'])) {
    echo json_encode(['success' => true]);
    exit;
}

// Rate limiting
$ip = get_client_ip();
try {
    $pdo = get_db();
    if (!check_rate_limit($pdo, $ip, 3, 600)) {
        http_response_code(429);
        echo json_encode(['error' => 'Muitas tentativas. Aguarde alguns minutos.']);
        exit;
    }
} catch (Throwable $e) {
    error_log('Rate limit error: ' . $e->getMessage());
}

// ── Validação ──
$errors = [];

$nome     = trim($data['nome']     ?? '');
$email    = trim($data['email']    ?? '');
$telefone = trim($data['telefone'] ?? '');
$cpf_raw  = preg_replace('/\D/', '', $data['cpf']  ?? '');
$cep_raw  = preg_replace('/\D/', '', $data['cep']  ?? '');
$cnpj_raw = preg_replace('/\D/', '', $data['cnpj'] ?? '');

if (strlen($nome) < 2 || strlen($nome) > 150) {
    $errors[] = 'Nome deve ter entre 2 e 150 caracteres.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
    $errors[] = 'E-mail inválido.';
}
$tel_digits = preg_replace('/\D/', '', $telefone);
if (strlen($tel_digits) < 10 || strlen($tel_digits) > 15) {
    $errors[] = 'Telefone inválido (inclua DDD).';
}

// CPF — dígito verificador
if (strlen($cpf_raw) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf_raw)) {
    $errors[] = 'CPF inválido.';
} else {
    $s = 0;
    for ($i = 0; $i < 9; $i++) $s += (int)$cpf_raw[$i] * (10 - $i);
    $r = ($s * 10) % 11; if ($r === 10 || $r === 11) $r = 0;
    if ($r !== (int)$cpf_raw[9]) {
        $errors[] = 'CPF inválido.';
    } else {
        $s = 0;
        for ($i = 0; $i < 10; $i++) $s += (int)$cpf_raw[$i] * (11 - $i);
        $r = ($s * 10) % 11; if ($r === 10 || $r === 11) $r = 0;
        if ($r !== (int)$cpf_raw[10]) $errors[] = 'CPF inválido.';
    }
}

// CNPJ — opcional, mas se preenchido valida
if ($cnpj_raw !== '' && (strlen($cnpj_raw) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj_raw))) {
    $errors[] = 'CNPJ inválido.';
} elseif ($cnpj_raw !== '' && strlen($cnpj_raw) === 14) {
    $weights1 = [5,4,3,2,9,8,7,6,5,4,3,2];
    $weights2 = [6,5,4,3,2,9,8,7,6,5,4,3,2];
    $s = 0;
    for ($i = 0; $i < 12; $i++) $s += (int)$cnpj_raw[$i] * $weights1[$i];
    $r = $s % 11; $d1 = $r < 2 ? 0 : 11 - $r;
    $s = 0;
    for ($i = 0; $i < 13; $i++) $s += (int)$cnpj_raw[$i] * $weights2[$i];
    $r = $s % 11; $d2 = $r < 2 ? 0 : 11 - $r;
    if ($d1 !== (int)$cnpj_raw[12] || $d2 !== (int)$cnpj_raw[13]) {
        $errors[] = 'CNPJ inválido.';
    }
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['error' => implode(' ', $errors)]);
    exit;
}

// ── Sanitização ──
$nome_safe  = sanitize_text($nome);
$email_safe = filter_var($email, FILTER_SANITIZE_EMAIL);
$ua         = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

$utm_fields = ['utm_source','utm_medium','utm_campaign','utm_content','utm_term'];
$utms = [];
foreach ($utm_fields as $field) {
    $utms[$field] = sanitize_text(substr($data[$field] ?? '', 0, 100));
}

// ── Inserir no banco ──
try {
    $stmt = $pdo->prepare(
        'INSERT INTO leads
         (nome, email, telefone, cpf, cep, cnpj, utm_source, utm_medium, utm_campaign, utm_content, utm_term, ip, user_agent, created_at, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), "lead")'
    );
    $stmt->execute([
        $nome_safe,
        $email_safe,
        $tel_digits,
        $cpf_raw ?: null,
        $cep_raw ?: null,
        $cnpj_raw ?: null,
        $utms['utm_source'],
        $utms['utm_medium'],
        $utms['utm_campaign'],
        $utms['utm_content'],
        $utms['utm_term'],
        $ip,
        $ua,
    ]);

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    error_log('submit-lead insert error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao registrar. Tente novamente.']);
}
