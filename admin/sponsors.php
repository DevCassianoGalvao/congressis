<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_auth();
set_security_headers();

$pdo = get_db();
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$UPLOAD_DIR      = __DIR__ . '/../uploads/sponsors/';
$UPLOAD_URL_BASE = 'uploads/sponsors/';

$categories = [
    'parceiros'   => 'Parceiros',
    'patrocinio'  => 'Patrocínio',
    'apoio'       => 'Apoio',
    'expositores' => 'Expositores',
];

// ── DELETAR logo ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Token CSRF inválido.'];
        admin_redirect('/admin/sponsors.php');
    }
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $row = $pdo->prepare('SELECT logo_path FROM sponsors WHERE id = ?');
        $row->execute([$id]);
        $sponsor = $row->fetch(PDO::FETCH_ASSOC);
        if ($sponsor) {
            $upload_real = realpath($UPLOAD_DIR);
            $file_path   = realpath($UPLOAD_DIR . basename($sponsor['logo_path']));
            if ($file_path && $upload_real && str_starts_with($file_path, $upload_real . DIRECTORY_SEPARATOR)) {
                @unlink($file_path);
            }
            $pdo->prepare('DELETE FROM sponsors WHERE id = ?')->execute([$id]);
            write_log('Sponsor #' . $id . ' removido por ' . get_current_user_name());
            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Logo removido com sucesso.'];
        }
    }
    admin_redirect('/admin/sponsors.php');
}

// ── EDITAR URL ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_url') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Token CSRF inválido.'];
        admin_redirect('/admin/sponsors.php');
    }
    $id  = (int)($_POST['id'] ?? 0);
    $url = trim($_POST['url'] ?? '');
    if ($id > 0) {
        $pdo->prepare('UPDATE sponsors SET url = ? WHERE id = ?')
            ->execute([$url ?: null, $id]);
        write_log('URL do sponsor #' . $id . ' atualizada por ' . get_current_user_name());
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Link atualizado com sucesso.'];
    }
    $cat = $_POST['cat'] ?? 'parceiros';
    admin_redirect('/admin/sponsors.php?cat=' . urlencode($cat));
    exit;
}

// ── UPLOAD logo ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload') {
    $upload_error = null;

    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $upload_error = 'Token CSRF inválido.';
    }

    $name     = trim($_POST['name']     ?? '');
    $url      = trim($_POST['url']      ?? '');
    $category = $_POST['category']      ?? '';
    $sort     = (int)($_POST['sort_order'] ?? 0);

    if (!$upload_error && !$name)                             $upload_error = 'Nome / Empresa é obrigatório.';
    if (!$upload_error && !array_key_exists($category, $categories)) $upload_error = 'Categoria inválida.';

    if (!$upload_error) {
        $f = $_FILES['logo'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
            $upload_error = 'Nenhum arquivo enviado ou erro no upload (código ' . ($f['error'] ?? '-') . ').';
        } else {
            [$ok, $err, $filename] = validate_and_save_sponsor_logo($f, $UPLOAD_DIR);
            if (!$ok) {
                $upload_error = $err;
            } else {
                $logo_path = $UPLOAD_URL_BASE . $filename;
                $pdo->prepare(
                    'INSERT INTO sponsors (category, name, logo_path, url, sort_order, active)
                     VALUES (?, ?, ?, ?, ?, 1)'
                )->execute([$category, $name, $logo_path, $url ?: null, $sort]);
                write_log('Sponsor adicionado: "' . $name . '" por ' . get_current_user_name());
                $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Logo "' . htmlspecialchars($name, ENT_QUOTES) . '" adicionado com sucesso.'];
            }
        }
    }

    if ($upload_error) {
        $_SESSION['flash'] = ['type' => 'danger', 'msg' => $upload_error];
    }
    admin_redirect('/admin/sponsors.php?cat=' . urlencode($category));
    exit;
}

// ── Carregar sponsors por categoria ───────────────────────────
$sponsors_by_cat = [];
foreach (array_keys($categories) as $cat) {
    $stmt = $pdo->prepare(
        'SELECT * FROM sponsors WHERE category = ? ORDER BY sort_order ASC, id ASC'
    );
    $stmt->execute([$cat]);
    $sponsors_by_cat[$cat] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$active_cat = $_GET['cat'] ?? 'parceiros';
if (!array_key_exists($active_cat, $categories)) $active_cat = 'parceiros';

$csrf         = generate_csrf();
$page_title   = 'Apoiadores';
$current_page = 'sponsors';
include __DIR__ . '/includes/header.php';
?>

<?php if ($flash): ?>
  <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div>
<?php endif; ?>

<!-- Tabs de categorias -->
<div class="sponsors-tabs">
  <?php foreach ($categories as $key => $label): ?>
    <a href="<?= admin_url('/admin/sponsors.php?cat=') . urlencode($key) ?>"
       class="sponsors-tab <?= $active_cat === $key ? 'active' : '' ?>">
      <?= e($label) ?>
      <span class="sponsors-tab-count"><?= count($sponsors_by_cat[$key]) ?></span>
    </a>
  <?php endforeach; ?>
</div>

<?php foreach ($categories as $key => $label):
  if ($active_cat !== $key) continue; ?>

<!-- ── Categoria: <?= $label ?> ── -->
<div class="card" style="margin-bottom:1.2rem">
  <div class="card-header-row">
    <h2 style="font-size:1rem;font-weight:600;color:var(--green)"><?= e($label) ?></h2>
    <span style="font-size:.83rem;color:var(--ink-soft)"><?= count($sponsors_by_cat[$key]) ?> logo(s)</span>
  </div>

  <?php if (empty($sponsors_by_cat[$key])): ?>
    <p style="color:var(--ink-soft);font-size:.9rem;padding:.5rem 0">Nenhum logo cadastrado nesta categoria.</p>
  <?php else: ?>
    <div class="sponsor-list">
      <?php foreach ($sponsors_by_cat[$key] as $s): ?>
        <div class="sponsor-row">
          <div class="sponsor-thumb">
            <img src="<?= e(admin_url('/' . ltrim($s['logo_path'], '/'))) ?>" alt="<?= e($s['name']) ?>">
          </div>
          <div class="sponsor-info">
            <span class="sponsor-name"><?= e($s['name']) ?></span>
            <form method="POST" action="<?= admin_url('/admin/sponsors.php?cat=') . urlencode($key) ?>"
                  style="display:flex;align-items:center;gap:.4rem;margin:0;flex:1">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="update_url">
              <input type="hidden" name="id"  value="<?= (int)$s['id'] ?>">
              <input type="hidden" name="cat" value="<?= e($key) ?>">
              <input type="url" name="url" value="<?= e($s['url'] ?? '') ?>"
                     class="form-control" placeholder="https://empresa.com.br"
                     style="font-size:.82rem;padding:.3rem .6rem;min-width:220px">
              <button type="submit" class="btn btn-sm btn-primary" title="Salvar link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polyline points="20 6 9 17 4 12"/></svg>
                Salvar
              </button>
            </form>
          </div>
          <span class="sponsor-order">ordem <?= (int)$s['sort_order'] ?></span>
          <form method="POST" action="<?= admin_url('/admin/sponsors.php') ?>"
                onsubmit="return confirm('Remover este logo?')"
                style="margin:0">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
            <button type="submit" class="icon-btn icon-btn-delete" title="Remover logo">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
            </button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Formulário de upload -->
<div class="card">
  <div class="card-header-row" style="margin-bottom:1rem">
    <h2 style="font-size:1rem;font-weight:600;color:var(--green)">Adicionar logo — <?= e($label) ?></h2>
  </div>
  <form method="POST" action="<?= admin_url('/admin/sponsors.php?cat=') . urlencode($key) ?>"
        enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="upload">
    <input type="hidden" name="category" value="<?= e($key) ?>">

    <div class="upload-grid">
      <div class="form-group">
        <label for="logo_<?= $key ?>">Arquivo de logo <span class="req">*</span></label>
        <input type="file" id="logo_<?= $key ?>" name="logo"
               accept="image/jpeg,image/png,image/svg+xml,image/webp"
               class="form-control" required>
        <p class="form-hint">JPG, PNG, SVG ou WEBP · Máx. 2 MB</p>
      </div>

      <div class="form-group">
        <label for="name_<?= $key ?>">Nome / Empresa <span class="req">*</span></label>
        <input type="text" id="name_<?= $key ?>" name="name"
               class="form-control" placeholder="Ex.: Clínica Natureza Viva" required>
      </div>

      <div class="form-group">
        <label for="url_<?= $key ?>">URL (opcional)</label>
        <input type="url" id="url_<?= $key ?>" name="url"
               class="form-control" placeholder="https://empresa.com.br">
        <p class="form-hint">Ao clicar no logo na LP, abre este link</p>
      </div>

      <div class="form-group" style="max-width:140px">
        <label for="sort_<?= $key ?>">Ordem</label>
        <input type="number" id="sort_<?= $key ?>" name="sort_order"
               class="form-control" value="<?= count($sponsors_by_cat[$key]) * 10 ?>"
               min="0" step="10">
        <p class="form-hint">Menor número = aparece primeiro</p>
      </div>
    </div>

    <div style="margin-top:1.2rem">
      <button type="submit" class="btn btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg>
        Enviar logo
      </button>
    </div>
  </form>
</div>

<?php endforeach; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
