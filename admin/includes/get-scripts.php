<?php
/**
 * Injeta scripts salvos pelo painel na posição correta da LP.
 *
 * Uso na LP:
 *   No <head>:  <?php define('SCRIPT_LOC','head');       include __DIR__.'/admin/includes/get-scripts.php'; ?>
 *   No <body>:  <?php define('SCRIPT_LOC','body_start'); include __DIR__.'/admin/includes/get-scripts.php'; ?>
 *   No footer:  <?php define('SCRIPT_LOC','footer');     include __DIR__.'/admin/includes/get-scripts.php'; ?>
 *
 * ATENÇÃO: o conteúdo injetado aqui é salvo exclusivamente por administradores
 * autenticados. Nunca passar por htmlspecialchars — são tags de script legítimas.
 */

$loc = defined('SCRIPT_LOC') ? SCRIPT_LOC : null;
if (!in_array($loc, ['head', 'body_start', 'footer'], true)) {
    return;
}

try {
    require_once __DIR__ . '/db.php';
    $pdo  = get_db();
    $stmt = $pdo->prepare('SELECT script_content FROM site_scripts WHERE location = ? LIMIT 1');
    $stmt->execute([$loc]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty(trim($row['script_content']))) {
        echo $row['script_content'] . "\n";
    }
} catch (Throwable $e) {
    error_log('get-scripts.php erro: ' . $e->getMessage());
    // Falha silenciosa — não quebrar a LP
}
