/* Congressis Admin — JS */
(function () {
  'use strict';

  // ── Sidebar mobile ──
  const burger   = document.getElementById('burgerBtn');
  const sidebar  = document.querySelector('.sidebar');
  const overlay  = document.getElementById('sidebarOverlay');

  function openSidebar() {
    sidebar && sidebar.classList.add('open');
    overlay && overlay.classList.add('show');
    document.body.style.overflow = 'hidden';
  }
  function closeSidebar() {
    sidebar && sidebar.classList.remove('open');
    overlay && overlay.classList.remove('show');
    document.body.style.overflow = '';
  }

  burger  && burger.addEventListener('click', openSidebar);
  overlay && overlay.addEventListener('click', closeSidebar);

  // ── Fechar sidebar ao clicar em link (mobile) ──
  document.querySelectorAll('.nav-menu a').forEach(function (a) {
    a.addEventListener('click', closeSidebar);
  });

  // ── Delete lead dialog ──
  const deleteDialog = document.getElementById('deleteDialog');
  const deleteForm   = document.getElementById('deleteForm');
  const deleteIdInput = document.getElementById('deleteLeadId');
  const cancelDelete  = document.getElementById('cancelDelete');

  document.querySelectorAll('[data-delete-lead]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const id = btn.dataset.deleteLead;
      if (deleteIdInput) deleteIdInput.value = id;
      if (deleteDialog) deleteDialog.showModal();
    });
  });

  cancelDelete && cancelDelete.addEventListener('click', function () {
    deleteDialog && deleteDialog.close();
  });

  deleteDialog && deleteDialog.addEventListener('click', function (e) {
    if (e.target === deleteDialog) deleteDialog.close();
  });

  // ── Status toggle ──
  document.querySelectorAll('.status-toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const id     = btn.dataset.leadId;
      const status = btn.dataset.status;
      const csrf   = btn.dataset.csrf;
      const next   = status === 'lead' ? 'comprou' : 'lead';

      fetch((window._BP||'') + '/admin/toggle-status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id, status: next, csrf_token: csrf })
      })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.success) {
          btn.textContent = next === 'lead' ? 'lead' : 'comprou';
          btn.dataset.status = next;
          btn.className = 'status-toggle ' + next;
          // Atualizar badge na mesma linha
          const badge = document.querySelector('[data-badge-id="' + id + '"]');
          if (badge) {
            badge.textContent = next;
            badge.className = 'badge badge-' + next;
          }
        }
      })
      .catch(function () {
        alert('Erro ao atualizar status. Tente novamente.');
      });
    });
  });

  // ── Auto-fechar alertas após 5s ──
  document.querySelectorAll('.alert').forEach(function (el) {
    setTimeout(function () {
      el.style.opacity = '0';
      el.style.transition = 'opacity .4s';
      setTimeout(function () { el.remove(); }, 400);
    }, 5000);
  });

  // ── UTM Generator ──
  const utmForm = document.getElementById('utmForm');
  if (utmForm) {
    const utmOutput = document.getElementById('utmOutput');
    const utmCopy   = document.getElementById('utmCopy');
    const utmWarn   = document.getElementById('utmWarn');

    function buildUtm() {
      const base = utmForm.querySelector('[name="url"]').value.trim();
      if (!base) { if (utmOutput) utmOutput.value = ''; return; }

      if (utmWarn) {
        utmWarn.style.display = base && !base.startsWith('https://') ? '' : 'none';
      }

      const params = new URLSearchParams();
      ['utm_source','utm_medium','utm_campaign','utm_content','utm_term'].forEach(function (p) {
        const el  = utmForm.querySelector('[name="' + p + '"]');
        const val = el ? el.value.trim() : '';
        if (val) params.set(p, val);
      });

      const qs = params.toString();
      if (utmOutput) utmOutput.value = base + (qs ? '?' + qs : '');
    }

    utmForm.querySelectorAll('input').forEach(function (inp) {
      inp.addEventListener('input', buildUtm);
    });

    utmCopy && utmCopy.addEventListener('click', function () {
      if (!utmOutput || !utmOutput.value) return;
      navigator.clipboard.writeText(utmOutput.value).then(function () {
        const original = utmCopy.textContent;
        utmCopy.textContent = 'Copiado ✓';
        setTimeout(function () { utmCopy.textContent = original; }, 2000);
      });
    });
  }

})();
