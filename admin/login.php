<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
set_security_headers();

// Já autenticado — redirecionar
if (is_authenticated()) {
    header('Location: /admin/leads.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Rate limiting de login
    if (!check_login_rate_limit()) {
        sleep(3);
        $error = 'Muitas tentativas de login. Aguarde 15 minutos.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = 'Preencha usuário e senha.';
        } else {
            try {
                $pdo  = get_db();
                $stmt = $pdo->prepare('SELECT id, username, password_hash FROM admin_users WHERE username = ? LIMIT 1');
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    // Login bem-sucedido
                    session_regenerate_id(true);
                    $_SESSION['admin_id']       = $user['id'];
                    $_SESSION['admin_username'] = $user['username'];
                    $_SESSION['last_activity']  = time();
                    reset_login_rate_limit();
                    header('Location: /admin/leads.php');
                    exit;
                } else {
                    sleep(1); // Atrasar resposta de credencial inválida
                    $error = 'Usuário ou senha inválidos.';
                }
            } catch (Throwable $e) {
                error_log('Login error: ' . $e->getMessage());
                $error = 'Erro interno. Tente novamente.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — Congressis Admin</title>
<link rel="icon" type="image/png" href="/assets/favicon-emblem.png">
<link rel="stylesheet" href="/admin/assets/admin.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <img src="/assets/logos/logo-horizontal-verde.png" alt="Congressis" class="login-logo">
    <h2>Painel Administrativo</h2>

    <?php if ($error): ?>
      <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="/admin/login.php" novalidate>
      <?= csrf_field() ?>
      <div class="form-group">
        <label for="username">Usuário</label>
        <input type="text" id="username" name="username" class="form-control"
               autocomplete="username" required autofocus
               value="<?= e($_POST['username'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="password">Senha</label>
        <input type="password" id="password" name="password" class="form-control"
               autocomplete="current-password" required>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:.5rem">
        Entrar
      </button>
    </form>
  </div>
</div>
</body>
</html>
