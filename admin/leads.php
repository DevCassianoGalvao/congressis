<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_auth();
set_security_headers();

$pdo = get_db();

// ── Parâmetros de filtro e paginação ──
$search  = trim($_GET['q']      ?? '');
$status  = trim($_GET['status'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;

// ── Mensagem flash ──
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// ── Construir WHERE dinâmico ──
$where  = [];
$params = [];

if ($search !== '') {
    $where[]  = '(nome LIKE ? OR email LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if (in_array($status, ['lead', 'comprou'], true)) {
    $where[]  = 'status = ?';
    $params[] = $status;
}
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ── Contagens ──
$total_leads    = (int)$pdo->query("SELECT COUNT(*) FROM leads WHERE status = 'lead'")->fetchColumn();
$total_comprou  = (int)$pdo->query("SELECT COUNT(*) FROM leads WHERE status = 'comprou'")->fetchColumn();
$count_stmt     = $pdo->prepare("SELECT COUNT(*) FROM leads $where_sql");
$count_stmt->execute($params);
$total_filtered = (int)$count_stmt->fetchColumn();

// ── Paginação ──
$pg = paginate($total_filtered, $per_page, $page);

// ── Buscar leads da página atual ──
$data_stmt = $pdo->prepare(
    "SELECT id, nome, email, telefone, cpf, cep, cnpj, utm_source, status, created_at
     FROM leads $where_sql
     ORDER BY created_at DESC
     LIMIT ? OFFSET ?"
);
$data_stmt->execute(array_merge($params, [$per_page, $pg['offset']]));
$leads = $data_stmt->fetchAll();

$csrf = generate_csrf();

$page_title   = 'Leads';
$current_page = 'leads';
include __DIR__ . '/includes/header.php';
?>

<?php if ($flash): ?>
  <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-row">
  <div class="stat-card">
    <div class="num"><?= $total_leads + $total_comprou ?></div>
    <div class="lbl">Total de leads</div>
  </div>
  <div class="stat-card">
    <div class="num"><?= $total_leads ?></div>
    <div class="lbl">Aguardando</div>
  </div>
  <div class="stat-card gold">
    <div class="num"><?= $total_comprou ?></div>
    <div class="lbl">Compraram</div>
  </div>
</div>

<!-- Toolbar -->
<form method="GET" action="/admin/leads.php" class="toolbar">
  <input type="text" name="q" placeholder="Buscar por nome ou e-mail…"
         value="<?= e($search) ?>" style="flex:1;min-width:200px;">
  <select name="status">
    <option value="">Todos os status</option>
    <option value="lead"    <?= $status === 'lead'    ? 'selected' : '' ?>>Lead</option>
    <option value="comprou" <?= $status === 'comprou' ? 'selected' : '' ?>>Comprou</option>
  </select>
  <button type="submit" class="btn btn-primary">Filtrar</button>
  <a href="/admin/leads.php" class="btn btn-outline">Limpar</a>
  <a href="/admin/export.php?<?= http_build_query(['q' => $search, 'status' => $status]) ?>"
     class="btn btn-gold">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
    Exportar CSV
  </a>
</form>

<!-- Tabela -->
<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Nome</th>
        <th>E-mail</th>
        <th>Telefone</th>
        <th>CPF</th>
        <th>UTM Source</th>
        <th>Status</th>
        <th>Data</th>
        <th>Ações</th>
      </tr>
    </thead>
    <tbody>
    <?php if (empty($leads)): ?>
      <tr><td colspan="9" style="text-align:center;padding:2rem;color:var(--ink-soft)">Nenhum lead encontrado.</td></tr>
    <?php else: foreach ($leads as $lead): ?>
      <tr>
        <td style="color:var(--ink-soft);font-size:.82rem"><?= (int)$lead['id'] ?></td>
        <td><strong><?= e($lead['nome']) ?></strong></td>
        <td><?= e($lead['email']) ?></td>
        <td><?= e($lead['telefone']) ?></td>
        <td style="font-size:.82rem;font-family:monospace;color:var(--ink-soft)">
          <?php if ($lead['cpf']): ?>
            <?= substr($lead['cpf'],0,3).'.'.substr($lead['cpf'],3,3).'.'.substr($lead['cpf'],6,3).'-'.substr($lead['cpf'],9,2) ?>
          <?php else: ?>—<?php endif; ?>
        </td>
        <td>
          <?php if ($lead['utm_source']): ?>
            <span style="background:#f0ede6;padding:.15rem .55rem;border-radius:6px;font-size:.8rem">
              <?= e($lead['utm_source']) ?>
            </span>
          <?php else: ?>
            <span style="color:var(--ink-soft);font-size:.82rem">—</span>
          <?php endif; ?>
        </td>
        <td>
          <span class="badge badge-<?= e($lead['status']) ?>" data-badge-id="<?= (int)$lead['id'] ?>">
            <?= e($lead['status']) ?>
          </span>
        </td>
        <td style="font-size:.83rem;color:var(--ink-soft)">
          <?= date('d/m/Y H:i', strtotime($lead['created_at'])) ?>
        </td>
        <td>
          <div class="action-cell">
            <!-- WhatsApp — logo oficial -->
            <a href="https://wa.me/55<?= e(preg_replace('/\D/','',$lead['telefone'])) ?>"
               target="_blank" rel="noopener"
               class="icon-btn icon-btn-wa" title="Chamar no WhatsApp">
              <svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg>
            </a>
            <!-- Toggle status -->
            <button class="status-toggle <?= e($lead['status']) ?>"
                    data-lead-id="<?= (int)$lead['id'] ?>"
                    data-status="<?= e($lead['status']) ?>"
                    data-csrf="<?= e($csrf) ?>"
                    title="Alternar status">
              <?php if ($lead['status'] === 'lead'): ?>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="5 12 19 12"/><polyline points="13 6 19 12 13 18"/></svg>
                comprou
              <?php else: ?>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="19 12 5 12"/><polyline points="11 6 5 12 11 18"/></svg>
                lead
              <?php endif; ?>
            </button>
            <!-- Excluir -->
            <button class="icon-btn icon-btn-delete"
                    data-delete-lead="<?= (int)$lead['id'] ?>"
                    title="Excluir lead">
              <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
            </button>
          </div>
        </td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<!-- Paginação -->
<?php if ($pg['total_pages'] > 1): ?>
<div class="pagination">
  <?php
  $base_qs = http_build_query(['q' => $search, 'status' => $status]);
  for ($i = 1; $i <= $pg['total_pages']; $i++):
    if ($i === $pg['current']): ?>
      <span class="current"><?= $i ?></span>
    <?php else: ?>
      <a href="/admin/leads.php?<?= $base_qs ?>&page=<?= $i ?>"><?= $i ?></a>
    <?php endif;
  endfor; ?>
</div>
<?php endif; ?>

<!-- Dialog de confirmação de exclusão -->
<dialog id="deleteDialog">
  <div class="dialog-title">Excluir lead</div>
  <div class="dialog-body">Tem certeza que deseja excluir este lead? Esta ação não pode ser desfeita.</div>
  <form id="deleteForm" method="POST" action="/api/delete-lead.php">
    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
    <input type="hidden" name="id" id="deleteLeadId" value="">
    <div class="dialog-actions">
      <button type="button" id="cancelDelete" class="btn btn-outline">Cancelar</button>
      <button type="submit" class="btn btn-danger">Sim, excluir</button>
    </div>
  </form>
</dialog>

<?php include __DIR__ . '/includes/footer.php'; ?>
