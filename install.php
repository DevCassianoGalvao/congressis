<?php
/**
 * Instalador do Congressis Mini-CRM.
 * IMPORTANTE: Delete este arquivo após a instalação bem-sucedida!
 */

// Garantir que erros apareçam durante a instalação
error_reporting(E_ALL);
ini_set('display_errors', 1);

$config_path = __DIR__ . '/config/config.php';
$step        = 'form'; // form | check | done | error
$errors      = [];
$success_msg = '';

// ── Verificar se já foi instalado ──
$already_installed = file_exists($config_path) && filesize($config_path) > 100;

// ── Verificar requisitos ──
function check_requirements(): array {
    $req = [];
    $req['PHP >= 8.0'] = version_compare(PHP_VERSION, '8.0.0', '>=');
    $req['PDO']        = extension_loaded('pdo');
    $req['PDO_MySQL']  = extension_loaded('pdo_mysql');
    $req['JSON']       = extension_loaded('json');
    $req['config/ gravável']          = is_writable(__DIR__ . '/config')          || !file_exists(__DIR__ . '/config');
    $req['logs/ gravável']            = is_writable(__DIR__ . '/logs')            || !file_exists(__DIR__ . '/logs');
    $req['uploads/ gravável']         = is_writable(__DIR__ . '/uploads')         || !file_exists(__DIR__ . '/uploads');
    $req['Extensão GD (imagens)']     = extension_loaded('gd');
    $req['Extensão fileinfo (MIME)']  = extension_loaded('fileinfo');
    return $req;
}

$requirements = check_requirements();
$all_ok = !in_array(false, $requirements, true);

// ── SQL de criação das tabelas ──
$create_sql = "
CREATE TABLE IF NOT EXISTS `leads` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome`         VARCHAR(150) NOT NULL,
  `email`        VARCHAR(150) NOT NULL,
  `telefone`     VARCHAR(20)  NOT NULL DEFAULT '',
  `cpf`          CHAR(11)     NULL,
  `cep`          CHAR(8)      NULL,
  `cnpj`         CHAR(14)     NULL,
  `utm_source`   VARCHAR(100) NOT NULL DEFAULT '',
  `utm_medium`   VARCHAR(100) NOT NULL DEFAULT '',
  `utm_campaign` VARCHAR(100) NOT NULL DEFAULT '',
  `utm_content`  VARCHAR(100) NOT NULL DEFAULT '',
  `utm_term`     VARCHAR(100) NOT NULL DEFAULT '',
  `ip`           VARCHAR(45)  NOT NULL DEFAULT '',
  `user_agent`   VARCHAR(255) NOT NULL DEFAULT '',
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status`       ENUM('lead','comprou') NOT NULL DEFAULT 'lead',
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `site_scripts` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `location`       ENUM('head','body_start','footer') NOT NULL,
  `script_content` TEXT,
  `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by`     VARCHAR(100) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_location` (`location`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rate_limit` (
  `ip`           VARCHAR(45) NOT NULL,
  `attempts`     INT         NOT NULL DEFAULT 1,
  `last_attempt` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `admin_users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`      VARCHAR(100) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sponsors` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category`   ENUM('parceiros','patrocinio','apoio','expositores') NOT NULL,
  `name`       VARCHAR(150) NOT NULL,
  `logo_path`  VARCHAR(255) NOT NULL,
  `url`        VARCHAR(255) DEFAULT NULL,
  `sort_order` INT          NOT NULL DEFAULT 0,
  `active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_category_order` (`category`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `speakers` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(150) NOT NULL,
  `subtitle`   VARCHAR(255) NOT NULL DEFAULT '',
  `quote`      TEXT,
  `photo_path` VARCHAR(255) NOT NULL DEFAULT '',
  `flag`       VARCHAR(5)   DEFAULT NULL,
  `sort_order` INT          NOT NULL DEFAULT 0,
  `active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

// ── Processar formulário ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $all_ok) {
    $db_host     = trim($_POST['db_host']     ?? 'localhost');
    $db_name     = trim($_POST['db_name']     ?? '');
    $db_user     = trim($_POST['db_user']     ?? '');
    $db_pass     = $_POST['db_pass']          ?? '';
    $app_domain  = rtrim(trim($_POST['app_domain'] ?? ''), '/');
    $admin_user  = trim($_POST['admin_user']  ?? '');
    $admin_pass  = $_POST['admin_pass']       ?? '';
    $admin_pass2 = $_POST['admin_pass2']      ?? '';

    // Validações
    if (!$db_name)    $errors[] = 'Nome do banco é obrigatório.';
    if (!$db_user)    $errors[] = 'Usuário do banco é obrigatório.';
    if (!$admin_user) $errors[] = 'Usuário admin é obrigatório.';
    if (strlen($admin_pass) < 8) $errors[] = 'Senha admin deve ter ao menos 8 caracteres.';
    if ($admin_pass !== $admin_pass2) $errors[] = 'As senhas não coincidem.';

    if (empty($errors)) {
        // Testar conexão
        try {
            $pdo = new PDO(
                "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
                $db_user,
                $db_pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            $errors[] = 'Falha na conexão com o banco: ' . $e->getMessage();
        }

        if (empty($errors)) {
            // Criar tabelas
            foreach (array_filter(array_map('trim', explode(';', $create_sql))) as $sql) {
                if ($sql) $pdo->exec($sql . ';');
            }

            // Adicionar colunas novas com segurança (idempotente)
            $alter_columns = [
                'cpf'  => "ALTER TABLE `leads` ADD COLUMN `cpf`  CHAR(11)  NULL AFTER `telefone`",
                'cep'  => "ALTER TABLE `leads` ADD COLUMN `cep`  CHAR(8)   NULL AFTER `cpf`",
                'cnpj' => "ALTER TABLE `leads` ADD COLUMN `cnpj` CHAR(14)  NULL AFTER `cep`",
            ];
            foreach ($alter_columns as $col => $alter_sql) {
                $exists = $pdo->query(
                    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE()
                       AND TABLE_NAME   = 'leads'
                       AND COLUMN_NAME  = '$col'"
                )->fetchColumn();
                if (!$exists) $pdo->exec($alter_sql);
            }

            // Inserir scripts padrão
            $pdo->exec("
                INSERT IGNORE INTO site_scripts (location, script_content, updated_by)
                VALUES ('head','','install'), ('body_start','','install'), ('footer','','install')
            ");

            // Criar admin
            $hash = password_hash($admin_pass, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare(
                'INSERT INTO admin_users (username, password_hash) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)'
            );
            $stmt->execute([$admin_user, $hash]);

            // Gerar config.php
            $config_content = '<?php' . PHP_EOL
                . '// Gerado pelo install.php em ' . date('Y-m-d H:i:s') . PHP_EOL
                . "define('DB_HOST',         " . var_export($db_host,    true) . ");" . PHP_EOL
                . "define('DB_NAME',         " . var_export($db_name,    true) . ");" . PHP_EOL
                . "define('DB_USER',         " . var_export($db_user,    true) . ");" . PHP_EOL
                . "define('DB_PASS',         " . var_export($db_pass,    true) . ");" . PHP_EOL
                . "define('APP_DOMAIN',      " . var_export($app_domain, true) . ");" . PHP_EOL
                . "define('SESSION_TIMEOUT', 7200);" . PHP_EOL
                . "error_reporting(0);" . PHP_EOL
                . "ini_set('display_errors', 0);" . PHP_EOL
                . "ini_set('log_errors', 1);" . PHP_EOL
                . "ini_set('error_log', __DIR__ . '/../logs/php_errors.log');" . PHP_EOL;

            // Criar pastas de uploads e proteger com .htaccess
            $htaccess_content =
                "<FilesMatch \"\.(php|php3|php4|php5|phtml|pl|py|jsp|asp|sh|cgi)$\">\n"
                . "    Order allow,deny\n"
                . "    Deny from all\n"
                . "</FilesMatch>\n"
                . "Options -Indexes\n"
                . "<IfModule mod_headers.c>\n"
                . "    Header set X-Content-Type-Options nosniff\n"
                . "</IfModule>\n";

            foreach (['uploads/sponsors', 'uploads/speakers'] as $dir) {
                $full = __DIR__ . '/' . $dir;
                if (!is_dir($full)) mkdir($full, 0755, true);
                if (!file_exists($full . '/.htaccess')) {
                    file_put_contents($full . '/.htaccess', $htaccess_content);
                }
            }

            if (!file_put_contents($config_path, $config_content)) {
                $errors[] = 'Não foi possível gravar config/config.php. Verifique as permissões da pasta.';
            } else {
                $step = 'done';
                // Auto-deletar o instalador
                @unlink(__FILE__);
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
<title>Instalação — Congressis Mini-CRM</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,sans-serif;background:#13251B;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem 1rem}
.card{background:#fff;border-radius:16px;padding:2.4rem 2rem;width:100%;max-width:560px;box-shadow:0 20px 60px rgba(0,0,0,.4)}
h1{font-size:1.3rem;color:#13251B;margin-bottom:.3rem}
.sub{font-size:.9rem;color:#5A5A50;margin-bottom:1.6rem}
.req-list{list-style:none;margin-bottom:1.4rem}
.req-list li{display:flex;align-items:center;gap:.5rem;padding:.35rem 0;border-bottom:1px solid #f0ede6;font-size:.9rem}
.req-list li .icon{font-size:1rem;width:20px;text-align:center}
.ok{color:#1a6640}.fail{color:#c0392b}
label{display:block;font-size:.85rem;font-weight:500;color:#2B2B27;margin-bottom:.4rem;margin-top:1rem}
input{width:100%;padding:.55rem .85rem;border:1px solid #ddd;border-radius:8px;font-size:.95rem}
input:focus{outline:none;border-color:#B8893B;box-shadow:0 0 0 3px rgba(184,137,59,.15)}
.btn{display:block;width:100%;padding:.65rem;background:#13251B;color:#fff;border:none;border-radius:8px;font-size:1rem;font-weight:600;cursor:pointer;margin-top:1.4rem}
.btn:hover{background:#2E4F3E}
.alert{padding:.7rem 1rem;border-radius:8px;font-size:.88rem;margin-bottom:1rem}
.alert-danger{background:#fce8e6;color:#8c1d13;border:1px solid #f4b7b2}
.alert-success{background:#e6f4ea;color:#1a6640;border:1px solid #a8d5b5}
.alert-warning{background:#fff4e0;color:#7a5000;border:1px solid #f5d89a}
.checklist{list-style:none}
.checklist li{padding:.4rem 0;border-bottom:1px solid #f0ede6;font-size:.9rem;color:#2B2B27}
.checklist li::before{content:'□ ';font-size:1rem}
.done-icon{font-size:3rem;text-align:center;margin-bottom:1rem}
h2{color:#13251B;font-size:1.1rem;margin-bottom:.5rem}
.section{margin-bottom:1.4rem}
</style>
</head>
<body>
<div class="card">

<?php if ($step === 'done'): ?>
  <div class="done-icon">✅</div>
  <h1 style="text-align:center">Instalação concluída!</h1>
  <p class="sub" style="text-align:center">O Mini-CRM do Congressis está instalado e pronto para uso.</p>

  <div class="alert alert-warning">
    <strong>🔴 AÇÃO OBRIGATÓRIA:</strong> O arquivo <code>install.php</code> foi deletado automaticamente.
    Se ainda existir no servidor, <strong>delete manualmente agora.</strong>
  </div>

  <h2>Próximos passos</h2>
  <ul class="checklist">
    <li>Acessar o painel: <strong>/admin/</strong></li>
    <li>Verificar que config/config.php está inacessível via browser</li>
    <li>Testar envio do formulário da LP</li>
    <li>Configurar scripts de pixel no painel</li>
    <li>Confirmar que logs/ está inacessível via browser</li>
    <li>Ativar HTTPS (SSL) no cPanel</li>
    <li>Configurar backup automático do banco no cPanel</li>
  </ul>

  <a href="/admin/" class="btn" style="display:block;text-align:center;text-decoration:none;margin-top:1.4rem">
    Ir para o painel →
  </a>

<?php elseif ($already_installed && $step === 'form'): ?>
  <h1>⚠ Já instalado</h1>
  <p class="sub">O arquivo <code>config/config.php</code> já existe. A instalação já foi feita.</p>
  <div class="alert alert-warning">
    Delete este arquivo (<code>install.php</code>) do servidor imediatamente.
  </div>
  <a href="/admin/" class="btn" style="display:block;text-align:center;text-decoration:none">Ir para o painel</a>

<?php else: ?>
  <h1>Instalação — Congressis Mini-CRM</h1>
  <p class="sub">Preencha as informações para configurar o banco de dados e criar o usuário admin.</p>

  <!-- Requisitos -->
  <ul class="req-list">
    <?php foreach ($requirements as $name => $ok): ?>
      <li>
        <span class="icon <?= $ok ? 'ok' : 'fail' ?>"><?= $ok ? '✓' : '✗' ?></span>
        <span><?= htmlspecialchars($name) ?></span>
        <?php if (!$ok): ?><strong class="fail" style="margin-left:auto">Faltando</strong><?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>

  <?php if (!$all_ok): ?>
    <div class="alert alert-danger">Corrija os requisitos acima antes de prosseguir.</div>
  <?php endif; ?>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
      <?php foreach ($errors as $err): ?>
        <div><?= htmlspecialchars($err) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($all_ok): ?>
  <form method="POST">
    <div class="section">
      <h2>Banco de dados</h2>
      <label>Host do banco</label>
      <input type="text" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>" placeholder="localhost">
      <label>Nome do banco</label>
      <input type="text" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>" required>
      <label>Usuário do banco</label>
      <input type="text" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>" required>
      <label>Senha do banco</label>
      <input type="password" name="db_pass" value="">
    </div>

    <div class="section">
      <h2>Domínio</h2>
      <label>URL do site (sem barra final)</label>
      <input type="text" name="app_domain" value="<?= htmlspecialchars($_POST['app_domain'] ?? 'https://congressis.com.br') ?>" placeholder="https://congressis.com.br">
    </div>

    <div class="section">
      <h2>Usuário administrador</h2>
      <label>Usuário admin</label>
      <input type="text" name="admin_user" value="<?= htmlspecialchars($_POST['admin_user'] ?? '') ?>" required>
      <label>Senha admin (mín. 8 caracteres)</label>
      <input type="password" name="admin_pass" required>
      <label>Confirmar senha admin</label>
      <input type="password" name="admin_pass2" required>
    </div>

    <button type="submit" class="btn" <?= $all_ok ? '' : 'disabled' ?>>Instalar →</button>
  </form>
  <?php endif; ?>

<?php endif; ?>

</div>
</body>
</html>
