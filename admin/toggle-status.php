<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_auth();
header('Content-Type: application/json');
set_security_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !verify_csrf($input['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Token CSRF inválido.']);
    exit;
}

$id     = filter_var($input['id'] ?? '', FILTER_VALIDATE_INT);
$status = $input['status'] ?? '';

if (!$id || !in_array($status, ['lead', 'comprou'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Dados inválidos.']);
    exit;
}

try {
    $pdo = get_db();
    $pdo->prepare('UPDATE leads SET status = ? WHERE id = ?')->execute([$status, $id]);
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    error_log('toggle-status error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno.']);
}
