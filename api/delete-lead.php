<?php
require_once __DIR__ . '/../admin/includes/auth.php';
require_once __DIR__ . '/../admin/includes/db.php';
require_once __DIR__ . '/../admin/includes/functions.php';
set_security_headers();

// ── Exigir autenticação ──
if (!is_authenticated()) {
    http_response_code(403);
    // Se for formulário POST (não AJAX), redirecionar
    if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
        header('Location: ' . admin_url('/admin/login.php'));
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Não autenticado.']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Método não permitido.']);
    exit;
}

// ── Validar CSRF ──
$csrf = $_POST['csrf_token'] ?? '';
if (!verify_csrf($csrf)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Token CSRF inválido.']);
    exit;
}

// ── Validar ID ──
$id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ID inválido.']);
    exit;
}

// ── Executar exclusão ──
try {
    $pdo  = get_db();
    $stmt = $pdo->prepare('DELETE FROM leads WHERE id = ?');
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Lead não encontrado.'];
    } else {
        write_log('Lead #' . $id . ' excluído por ' . get_current_user_name() . ' (IP: ' . get_client_ip() . ')');
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Lead excluído com sucesso.'];
    }
} catch (Throwable $e) {
    error_log('delete-lead error: ' . $e->getMessage());
    $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Erro ao excluir. Tente novamente.'];
}

// Sempre redireciona de volta para leads após DELETE via form
header('Location: ' . admin_url('/admin/leads.php'));
exit;
