<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_auth();
set_security_headers();

$pdo = get_db();
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$UPLOAD_DIR      = __DIR__ . '/../uploads/speakers/';
$UPLOAD_URL_BASE = 'uploads/speakers/';

if (!is_dir($UPLOAD_DIR)) {
    mkdir($UPLOAD_DIR, 0755, true);
}

// ── DELETAR ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Token CSRF inválido.'];
        admin_redirect('/admin/speakers.php');
    }
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $row = $pdo->prepare('SELECT photo_path FROM speakers WHERE id = ?');
        $row->execute([$id]);
        $sp = $row->fetch(PDO::FETCH_ASSOC);
        if ($sp) {
            if (str_starts_with($sp['photo_path'], 'uploads/speakers/')) {
                $file = __DIR__ . '/../' . $sp['photo_path'];
                if (file_exists($file)) @unlink($file);
            }
            $pdo->prepare('DELETE FROM speakers WHERE id = ?')->execute([$id]);
            write_log('Speaker #' . $id . ' removido por ' . get_current_user_name());
            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Palestrante removido.'];
        }
    }
    admin_redirect('/admin/speakers.php');
}

// ── EDITAR (salvar) ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Token CSRF inválido.'];
        admin_redirect('/admin/speakers.php');
    }
    $id       = (int)($_POST['id'] ?? 0);
    $name     = trim($_POST['name'] ?? '');
    $subtitle = trim($_POST['subtitle'] ?? '');
    $quote    = trim($_POST['quote'] ?? '');
    $sort     = (int)($_POST['sort_order'] ?? 0);

    if ($id > 0 && $name) {
        $f = $_FILES['photo'] ?? null;
        if ($f && $f['error'] === UPLOAD_ERR_OK) {
            [$ok, $err, $filename] = validate_and_save_sponsor_logo($f, $UPLOAD_DIR);
            if (!$ok) {
                $_SESSION['flash'] = ['type' => 'danger', 'msg' => $err];
                admin_redirect('/admin/speakers.php?edit=' . $id);
            }
            $old = $pdo->prepare('SELECT photo_path FROM speakers WHERE id = ?');
            $old->execute([$id]);
            $old_sp = $old->fetch();
            if ($old_sp && str_starts_with($old_sp['photo_path'], 'uploads/speakers/')) {
                $old_file = __DIR__ . '/../' . $old_sp['photo_path'];
                if (file_exists($old_file)) @unlink($old_file);
            }
            $photo_path = $UPLOAD_URL_BASE . $filename;
            $pdo->prepare(
                'UPDATE speakers SET name=?, subtitle=?, quote=?, photo_path=?, sort_order=? WHERE id=?'
            )->execute([$name, $subtitle ?: null, $quote ?: null, $photo_path, $sort, $id]);
        } else {
            $pdo->prepare(
                'UPDATE speakers SET name=?, subtitle=?, quote=?, sort_order=? WHERE id=?'
            )->execute([$name, $subtitle ?: null, $quote ?: null, $sort, $id]);
        }
        write_log('Speaker #' . $id . ' atualizado por ' . get_current_user_name());
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Palestrante atualizado.'];
    }
    admin_redirect('/admin/speakers.php');
}

// ── ADICIONAR ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $err = null;
    if (!verify_csrf($_POST['csrf_token'] ?? '')) $err = 'Token CSRF inválido.';

    $name     = trim($_POST['name'] ?? '');
    $subtitle = trim($_POST['subtitle'] ?? '');
    $quote    = trim($_POST['quote'] ?? '');
    $sort     = (int)($_POST['sort_order'] ?? 0);

    if (!$err && !$name) $err = 'Nome é obrigatório.';

    if (!$err) {
        $f = $_FILES['photo'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
            $err = 'A foto é obrigatória.';
        } else {
            [$ok, $file_err, $filename] = validate_and_save_sponsor_logo($f, $UPLOAD_DIR);
            if (!$ok) {
                $err = $file_err;
            } else {
                $pdo->prepare(
                    'INSERT INTO speakers (name, subtitle, quote, photo_path, sort_order, active)
                     VALUES (?, ?, ?, ?, ?, 1)'
                )->execute([$name, $subtitle ?: null, $quote ?: null, $UPLOAD_URL_BASE . $filename, $sort]);
                write_log('Speaker "' . $name . '" adicionado por ' . get_current_user_name());
                $_SESSION['flash'] = ['type' => 'success', 'msg' => '"' . htmlspecialchars($name, ENT_QUOTES) . '" adicionado.'];
            }
        }
    }
    if ($err) $_SESSION['flash'] = ['type' => 'danger', 'msg' => $err];
    admin_redirect('/admin/speakers.php');
}

// ── Carregar ──────────────────────────────────────────────────────
$speakers = $pdo->query(
    'SELECT * FROM speakers ORDER BY sort_order ASC, id ASC'
)->fetchAll(PDO::FETCH_ASSOC);

$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$edit_sp = null;
foreach ($speakers as $sp) {
    if ((int)$sp['id'] === $edit_id) { $edit_sp = $sp; break; }
}

$csrf       = generate_csrf();
$page_title = 'Palestrantes';
$current_page = 'speakers';
include __DIR__ . '/includes/header.php';
?>

<?php if ($flash): ?>
  <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div>
<?php endif; ?>

<!-- ── Formulário de edição ──────────────────────────────── -->
<?php if ($edit_sp): ?>
<div class="card" style="margin-bottom:1.4rem;border-color:var(--gold)">
  <div class="card-header-row" style="margin-bottom:1rem">
    <h2 style="font-size:1rem;font-weight:600;color:var(--green)">Editar palestrante</h2>
    <a href="<?= admin_url('/admin/speakers.php') ?>" class="btn btn-outline btn-sm">Cancelar</a>
  </div>
  <form method="POST" action="<?= admin_url('/admin/speakers.php') ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="edit">
    <input type="hidden" name="id"     value="<?= (int)$edit_sp['id'] ?>">

    <div class="spk-edit-grid">
      <div class="spk-edit-photo">
        <img src="<?= e(admin_url('/' . ltrim($edit_sp['photo_path'], '/'))) ?>"
             alt="<?= e($edit_sp['name']) ?>" class="spk-edit-preview" id="editPreview">
        <label class="btn btn-outline btn-sm" style="margin-top:.7rem;cursor:pointer;display:inline-flex">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" width="14" height="14"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          Trocar foto
          <input type="file" name="photo" accept="image/jpeg,image/png,image/svg+xml,image/webp"
                 style="display:none" onchange="previewImg(this,'editPreview')">
        </label>
        <p class="form-hint">JPG, PNG, SVG, WEBP · máx 2MB<br>Deixe vazio para manter a atual</p>
      </div>

      <div class="spk-edit-fields">
        <div class="form-group">
          <label>Nome <span class="req">*</span></label>
          <input type="text" name="name" class="form-control" required
                 value="<?= e($edit_sp['name']) ?>">
        </div>
        <div class="form-group">
          <label>Subtítulo / Credenciais</label>
          <textarea name="subtitle" class="form-control" rows="3"
                    placeholder="Ex.: Especialista em Harmonização Orofacial · Pós em..."><?= e($edit_sp['subtitle'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
          <label>Citação / Tema da palestra</label>
          <textarea name="quote" class="form-control" rows="2"
                    placeholder="Ex.: Odontologia e estética sob a ótica da saúde integrativa."><?= e($edit_sp['quote'] ?? '') ?></textarea>
        </div>
        <div class="form-group" style="max-width:140px">
          <label>Ordem</label>
          <input type="number" name="sort_order" class="form-control"
                 value="<?= (int)$edit_sp['sort_order'] ?>" min="0" step="10">
        </div>
        <div style="margin-top:.8rem">
          <button type="submit" class="btn btn-primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polyline points="20 6 9 17 4 12"/></svg>
            Salvar alterações
          </button>
        </div>
      </div>
    </div>
  </form>
</div>
<?php endif; ?>

<!-- ── Lista de palestrantes ─────────────────────────────── -->
<div class="card" style="margin-bottom:1.4rem">
  <div class="card-header-row" style="margin-bottom:.8rem">
    <h2 style="font-size:1rem;font-weight:600;color:var(--green)">Palestrantes</h2>
    <span style="font-size:.83rem;color:var(--ink-soft)"><?= count($speakers) ?> cadastrado(s)</span>
  </div>

  <?php if (empty($speakers)): ?>
    <p style="color:var(--ink-soft);font-size:.9rem;padding:.5rem 0">Nenhum palestrante ainda.</p>
  <?php else: ?>
    <div class="speaker-list">
      <?php foreach ($speakers as $sp): ?>
      <div class="spk-row <?= (int)$sp['id'] === $edit_id ? 'spk-row--active' : '' ?>">
        <div class="spk-thumb">
          <img src="<?= e(admin_url('/' . ltrim($sp['photo_path'], '/'))) ?>"
               alt="<?= e($sp['name']) ?>">
        </div>
        <div class="spk-info">
          <span class="spk-name"><?= e($sp['name']) ?></span>
          <?php if ($sp['subtitle']): ?>
          <span class="spk-sub"><?= e(mb_strimwidth($sp['subtitle'], 0, 80, '…')) ?></span>
          <?php endif; ?>
          <?php if ($sp['quote']): ?>
          <span class="spk-quote">"<?= e(mb_strimwidth($sp['quote'], 0, 70, '…')) ?>"</span>
          <?php endif; ?>
        </div>
        <span class="spk-order">ordem <?= (int)$sp['sort_order'] ?></span>
        <div class="spk-actions">
          <a href="<?= admin_url('/admin/speakers.php?edit=') . (int)$sp['id'] ?>"
             class="btn btn-outline btn-sm">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" width="13" height="13"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg>
            Editar
          </a>
          <form method="POST" action="<?= admin_url('/admin/speakers.php') ?>"
                onsubmit="return confirm('Remover <?= e(addslashes($sp['name'])) ?>?')"
                style="margin:0">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$sp['id'] ?>">
            <button type="submit" class="icon-btn icon-btn-delete" title="Remover">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
            </button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- ── Formulário de adição ──────────────────────────────── -->
<div class="card">
  <div class="card-header-row" style="margin-bottom:1rem">
    <h2 style="font-size:1rem;font-weight:600;color:var(--green)">Adicionar palestrante</h2>
  </div>
  <form method="POST" action="<?= admin_url('/admin/speakers.php') ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add">

    <div class="spk-edit-grid">
      <div class="spk-edit-photo">
        <div class="spk-add-preview-wrap">
          <img src="" alt="" class="spk-edit-preview" id="addPreview" style="display:none">
          <div class="spk-add-placeholder" id="addPlaceholder">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" width="36" height="36" style="color:var(--ink-soft)"><circle cx="12" cy="8" r="4"/><path d="M4 20a8 8 0 0 1 16 0"/></svg>
          </div>
        </div>
        <label class="btn btn-outline btn-sm" style="margin-top:.7rem;cursor:pointer;display:inline-flex">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" width="14" height="14"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          Escolher foto <span class="req">*</span>
          <input type="file" name="photo" accept="image/jpeg,image/png,image/svg+xml,image/webp"
                 required style="display:none"
                 onchange="previewImg(this,'addPreview','addPlaceholder')">
        </label>
        <p class="form-hint">JPG, PNG, SVG, WEBP · máx 2MB</p>
      </div>

      <div class="spk-edit-fields">
        <div class="form-group">
          <label>Nome <span class="req">*</span></label>
          <input type="text" name="name" class="form-control" required
                 placeholder="Ex.: Dra. Mariana Costa">
        </div>
        <div class="form-group">
          <label>Subtítulo / Credenciais</label>
          <textarea name="subtitle" class="form-control" rows="3"
                    placeholder="Ex.: Especialista em Harmonização Orofacial · Pós em Saúde Integrativa"></textarea>
        </div>
        <div class="form-group">
          <label>Citação / Tema da palestra</label>
          <textarea name="quote" class="form-control" rows="2"
                    placeholder="Ex.: Integrando ciência e natureza no cuidado da saúde."></textarea>
        </div>
        <div class="form-group" style="max-width:140px">
          <label>Ordem</label>
          <input type="number" name="sort_order" class="form-control"
                 value="<?= (count($speakers) + 1) * 10 ?>" min="0" step="10">
          <p class="form-hint">Menor = aparece primeiro</p>
        </div>
        <div style="margin-top:.8rem">
          <button type="submit" class="btn btn-primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" width="15" height="15"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Adicionar palestrante
          </button>
        </div>
      </div>
    </div>
  </form>
</div>

<script>
function previewImg(input, previewId, placeholderId) {
  const file = input.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = function(e) {
    const img = document.getElementById(previewId);
    img.src = e.target.result;
    img.style.display = 'block';
    if (placeholderId) {
      const ph = document.getElementById(placeholderId);
      if (ph) ph.style.display = 'none';
    }
  };
  reader.readAsDataURL(file);
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
