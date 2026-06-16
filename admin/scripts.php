<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_auth();
set_security_headers();

$pdo     = get_db();
$success = false;
$error   = '';

// ── Salvar scripts ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Token CSRF inválido. Recarregue a página.';
    } else {
        $locations = ['head', 'body_start', 'footer'];
        try {
            foreach ($locations as $loc) {
                // Conteúdo do admin — NÃO sanitizar com htmlspecialchars (são tags legítimas).
                // Apenas trim. A responsabilidade é do admin autenticado.
                $content = trim($_POST[$loc] ?? '');
                $pdo->prepare(
                    'INSERT INTO site_scripts (location, script_content, updated_at, updated_by)
                     VALUES (?, ?, NOW(), ?)
                     ON DUPLICATE KEY UPDATE
                       script_content = VALUES(script_content),
                       updated_at     = VALUES(updated_at),
                       updated_by     = VALUES(updated_by)'
                )->execute([$loc, $content, get_current_user_name()]);
            }
            write_log('Scripts atualizados por ' . get_current_user_name());
            $success = true;
        } catch (Throwable $e) {
            error_log('scripts.php save error: ' . $e->getMessage());
            $error = 'Erro ao salvar. Tente novamente.';
        }
    }
}

// ── Carregar scripts atuais ──
$scripts = ['head' => '', 'body_start' => '', 'footer' => ''];
try {
    $rows = $pdo->query("SELECT location, script_content FROM site_scripts")->fetchAll();
    foreach ($rows as $row) {
        if (isset($scripts[$row['location']])) {
            $scripts[$row['location']] = $row['script_content'];
        }
    }
} catch (Throwable $e) {
    error_log('scripts.php load error: ' . $e->getMessage());
}

$csrf         = generate_csrf();
$page_title   = 'Scripts';
$current_page = 'scripts';
include __DIR__ . '/includes/header.php';
?>

<?php if ($success): ?>
  <div class="alert alert-success">Scripts salvos com sucesso.</div>
<?php elseif ($error): ?>
  <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<div class="alert alert-warning">
  <strong>⚠ Atenção:</strong> o conteúdo salvo aqui é <strong>injetado diretamente no HTML da landing page</strong>.
  Insira apenas scripts confiáveis (Meta Pixel, GTM, analytics). Código malicioso aqui afeta todos os visitantes.
</div>

<div class="card" style="margin-bottom:1rem">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.6rem">
    <p style="font-size:.9rem;color:var(--ink-soft)">
      Os scripts são injetados na landing page via PHP include. Salve e recarregue o site para ver o efeito.
    </p>
    <a href="<?= admin_url('/') ?>" target="_blank" class="btn btn-outline btn-sm">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
      Ver site
    </a>
  </div>
</div>

<form method="POST" action="<?= admin_url('/admin/scripts.php') ?>">
  <?= csrf_field() ?>
  <div class="scripts-grid">

    <div class="script-area">
      <label>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><polyline points="4 7 4 4 20 4 20 7"/><line x1="9" y1="20" x2="15" y2="20"/><line x1="12" y1="4" x2="12" y2="20"/></svg>
        Scripts no &lt;head&gt;
      </label>
      <textarea name="head" class="form-control" rows="10"
                placeholder="<!-- Meta Pixel, GTM head, verificação de domínio -->"><?= htmlspecialchars($scripts['head'], ENT_QUOTES, 'UTF-8') ?></textarea>
      <p class="form-hint">Inserido antes do &lt;/head&gt;. Ideal para: Meta Pixel base, GTM head snippet, Google Tag.</p>
    </div>

    <div class="script-area">
      <label>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
        Início do &lt;body&gt;
      </label>
      <textarea name="body_start" class="form-control" rows="10"
                placeholder="<!-- GTM noscript, pixels que exigem body -->"><?= htmlspecialchars($scripts['body_start'], ENT_QUOTES, 'UTF-8') ?></textarea>
      <p class="form-hint">Inserido logo após &lt;body&gt;. Ideal para: GTM noscript, pixels que precisam do body.</p>
    </div>

    <div class="script-area">
      <label>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><polyline points="4 17 4 20 20 20 20 17"/><line x1="9" y1="4" x2="15" y2="4"/><line x1="12" y1="4" x2="12" y2="20"/></svg>
        Rodapé (antes de &lt;/body&gt;)
      </label>
      <textarea name="footer" class="form-control" rows="10"
                placeholder="<!-- Chat, remarketing, scripts de conversão -->"><?= htmlspecialchars($scripts['footer'], ENT_QUOTES, 'UTF-8') ?></textarea>
      <p class="form-hint">Inserido antes de &lt;/body&gt;. Ideal para: chat widget, scripts de conversão, remarketing.</p>
    </div>

  </div>

  <div style="margin-top:1.4rem;display:flex;gap:.75rem">
    <button type="submit" class="btn btn-primary">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
      Salvar configurações
    </button>
  </div>
</form>

<?php include __DIR__ . '/includes/footer.php'; ?>
