<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_auth();
set_security_headers();

$page_title   = 'Gerador UTM';
$current_page = 'utm';
include __DIR__ . '/includes/header.php';
?>

<div class="card">
  <div class="card-title">Monte links rastreados para campanhas</div>

  <form id="utmForm" autocomplete="off">
    <div class="form-group">
      <label for="url">URL de destino *</label>
      <input type="url" name="url" id="url" class="form-control"
             placeholder="https://congressis.com.br/" required>
      <div class="alert alert-warning" id="utmWarn" style="display:none;margin-top:.5rem;padding:.5rem .8rem;font-size:.85rem">
        ⚠ A URL deve começar com <strong>https://</strong>
      </div>
    </div>

    <div class="utm-grid">
      <div class="form-group">
        <label for="utm_source">Origem (utm_source) *</label>
        <input type="text" name="utm_source" id="utm_source" class="form-control"
               placeholder="google, facebook, instagram">
      </div>
      <div class="form-group">
        <label for="utm_medium">Mídia (utm_medium) *</label>
        <input type="text" name="utm_medium" id="utm_medium" class="form-control"
               placeholder="cpc, social, email">
      </div>
      <div class="form-group">
        <label for="utm_campaign">Campanha (utm_campaign) *</label>
        <input type="text" name="utm_campaign" id="utm_campaign" class="form-control"
               placeholder="congressis_agosto_2026">
      </div>
      <div class="form-group">
        <label for="utm_content">Conteúdo (utm_content) <span style="color:var(--ink-soft)">opcional</span></label>
        <input type="text" name="utm_content" id="utm_content" class="form-control"
               placeholder="banner_a, botao_hero">
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <label for="utm_term">Termo (utm_term) <span style="color:var(--ink-soft)">opcional</span></label>
        <input type="text" name="utm_term" id="utm_term" class="form-control"
               placeholder="saude integrativa nova friburgo">
      </div>
    </div>
  </form>

  <div class="utm-result">
    <input type="text" id="utmOutput" readonly placeholder="O link gerado aparece aqui…">
    <button class="btn btn-primary" id="utmCopy">Copiar</button>
  </div>
  <p class="utm-hint">
    Use esse link em anúncios, posts, e-mails e botões. O formulário da LP salva automaticamente esses dados no lead.
  </p>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
