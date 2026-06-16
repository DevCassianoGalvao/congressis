<?php
// Copie este arquivo para config.php e preencha com os dados reais.
// NUNCA commite config.php no git — ele está no .gitignore.

define('DB_HOST',         'localhost');
define('DB_NAME',         'nome_do_banco');
define('DB_USER',         'usuario_mysql');
define('DB_PASS',         'senha_mysql');
define('APP_DOMAIN',      'https://congressis.com.br'); // sem barra final
define('SESSION_TIMEOUT', 7200); // 2 horas em segundos

// Brevo — notificação de leads por e-mail (opcional)
define('BREVO_API_KEY',      'SUA_API_KEY_AQUI');
define('BREVO_SENDER_EMAIL', 'remetente@verificado.com');
define('BREVO_SENDER_NAME',  'Congressis – Saúde Integrativa na Serra');
define('BREVO_NOTIFY_EMAIL', 'congressis.br@gmail.com');
define('BREVO_NOTIFY_NAME',  'Adryelle Huber Puga');

// Nível de log (E_ALL em dev, 0 em prod)
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
