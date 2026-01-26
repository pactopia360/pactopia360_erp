{{-- resources/views/cliente/sat/_partials/rfcs/_scripts.blade.php --}}

@push('scripts')
<script>
(function(){
  console.log('[SAT:RFC] init manejadores RFC/Alias/Panel/Eliminar/Invitar');

  // ============================================================
  // Refresh duro (cache-bust) para Mis RFCs
  // ============================================================
  function hardRefresh() {
    try {
      const u = new URL(window.location.href);
      u.searchParams.set('_ts', String(Date.now()));
      window.location.href = u.toString();
    } catch (e) {
      window.location.reload();
    }
  }

  const btnRfcRefresh = document.getElementById('btnRfcPageRefresh');
  if (btnRfcRefresh) {
    btnRfcRefresh.addEventListener('click', function (ev) {
      ev.preventDefault();
      hardRefresh();
    });
  }

  function csrfToken(){
    return (window.P360_SAT && window.P360_SAT.csrf) ? window.P360_SAT.csrf : '{{ csrf_token() }}';
  }

  function satToast(msg, kind='info') {
    try {
      if (window.P360 && typeof window.P360.toast === 'function') {
        if (kind === 'error' && window.P360.toast.error) return window.P360.toast.error(msg);
        if (kind === 'success' && window.P360.toast.success) return window.P360.toast.success(msg);
        return window.P360.toast(msg);
      }
    } catch(e) {}
    alert(msg);
  }

  // ============================================================
  // RFC siempre en mayúsculas
  // ============================================================
  document.querySelectorAll('.rfcs-neo .js-rfc-upper').forEach(input => {
    input.addEventListener('input', () => {
      input.value = (input.value || '').toUpperCase();
    });
  });

  // ============================================================
  // Alias visible -> sync a inputs ocultos + submit AJAX
  // ============================================================
  document.querySelectorAll('.rfcs-neo .js-alias-form').forEach(form => {
    const visible = form.querySelector('[data-field="alias-visible"]');
    if(!visible) return;

    const syncHidden = () => {
      const v = String(visible.value || '').trim();
      form.querySelectorAll('input[name="alias"],input[name="nombre"],input[name="razon_social"]').forEach(h => {
        h.value = v;
      });
      // dataset para filtros
      const row = form.closest('.sat-rfcs-row');
      if (row) row.dataset.alias = v;
    };

    visible.addEventListener('input', syncHidden);
    syncHidden();

    form.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const url = (form.dataset.url || '').trim();
      if (!url) return satToast('Ruta no configurada: rtAlias', 'error');

      syncHidden();

      const rfc = String(form.querySelector('input[name="rfc"]')?.value || '').trim().toUpperCase();
      const alias = String(form.querySelector('input[name="alias"]')?.value || '').trim();

      if (!rfc) return satToast('RFC inválido.', 'error');

      try{
        const fd = new FormData();
        fd.append('rfc', rfc);
        fd.append('alias', alias);
        fd.append('nombre', alias);
        fd.append('razon_social', alias);

        const btn = form.querySelector('button[type="submit"]');
        if (btn) btn.disabled = true;

        const res = await fetch(url, {
          method: 'POST',
          headers: {
            'X-Requested-With':'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept':'application/json',
          },
          body: fd,
        });

        const data = await res.json().catch(() => ({}));
        if (!res.ok || data.ok === false) throw new Error(data.msg || data.message || 'No se pudo guardar el nombre.');

        satToast(data.msg || 'Nombre actualizado.', 'success');
      } catch(e){
        satToast(e?.message || 'Error al guardar el nombre.', 'error');
      } finally {
        const btn = form.querySelector('button[type="submit"]');
        if (btn) btn.disabled = false;
      }
    });
  });

  // ============================================================
  // Escudo -> abrir/cerrar panel CSD (delegado)
  // ============================================================
  document.addEventListener('click', function(e){
    const btn = e.target.closest('.rfcs-neo [data-open-panel]');
    if (!btn) return;

    const panelId = btn.dataset.openPanel;
    if (!panelId) return;

    const panel = document.getElementById(panelId);
    if (!panel) return;

    const isOpen = !panel.hasAttribute('hidden');
    if (isOpen) {
      panel.setAttribute('hidden','');
      panel.setAttribute('aria-hidden','true');
    } else {
      panel.removeAttribute('hidden');
      panel.setAttribute('aria-hidden','false');
    }
  });

  // ============================================================
  // Aplicar RFC (AJAX)
  // ============================================================
  document.querySelectorAll('.rfcs-neo .js-rfc-apply').forEach(btn => {
    btn.addEventListener('click', async (ev) => {
      ev.preventDefault();

      const row = btn.closest('.sat-rfcs-row');
      const f   = row ? row.querySelector('.js-rfc-form') : null;
      if (!f) return;

      const url = (f.dataset.url || '').trim();
      const rfc = (f.querySelector('input[name="rfc"]')?.value || '').trim().toUpperCase();

      if (!url) return satToast('Ruta no configurada: rtRfcReg', 'error');
      if (!rfc) return satToast('RFC inválido.', 'error');

      try{
        btn.disabled = true;

        const fd = new FormData();
        fd.append('rfc', rfc);

        const res = await fetch(url, {
          method: 'POST',
          headers: {
            'X-Requested-With':'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept':'application/json',
          },
          body: fd,
        });

        const data = await res.json().catch(() => ({}));
        if (!res.ok || data.ok === false) throw new Error(data.msg || data.message || 'No se pudo aplicar el RFC.');

        if (row) row.dataset.rfc = rfc; // para filtros
        satToast(data.msg || 'RFC actualizado.', 'success');
      } catch(e){
        satToast(e?.message || 'Error al aplicar RFC.', 'error');
      } finally {
        btn.disabled = false;
      }
    });
  });

  // ============================================================
  // Eliminar RFC (POST con _method DELETE)
  // ============================================================
  document.querySelectorAll('.rfcs-neo .js-rfc-delete').forEach(btn => {
    btn.addEventListener('click', async () => {
      const url = (btn.dataset.url || '').trim();
      const rfc = String(btn.dataset.rfc || '').trim().toUpperCase();

      if (!url || url === '#') return;
      if (!rfc) return satToast('RFC inválido.', 'error');

      if (!confirm('¿Eliminar el RFC '+ rfc +'?\n\nEsta acción no se puede deshacer.')) return;

      try{
        btn.disabled = true;

        const fd = new FormData();
        fd.append('rfc', rfc);
        fd.append('_method','DELETE');

        const res = await fetch(url, {
          method:'POST',
          headers:{
            'X-Requested-With':'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
          },
          body: fd,
        });

        const rawText = await res.text();
        let j = {};
        try { j = JSON.parse(rawText); } catch(_) {}

        if (!res.ok || j.ok === false){
          throw new Error(j.msg || 'No se pudo eliminar el RFC.');
        }

        const row = btn.closest('.sat-rfcs-row');
        if (row){
          const rowId = row.dataset.row || '';
          const panel = rowId ? document.getElementById(rowId + '_panel') : null;
          if (panel) panel.remove();
          row.remove();
        }

        satToast(j.msg || 'RFC eliminado.', 'success');
      } catch(e){
        satToast(e?.message || 'Error de conexión al eliminar el RFC.', 'error');
      } finally {
        btn.disabled = false;
      }
    });
  });

  // ============================================================
  // Filtros RFCs
  // ============================================================
  const searchInput = document.getElementById('rfcFilterSearch');
  const selStatus   = document.getElementById('rfcFilterStatus');
  const selCsd      = document.getElementById('rfcFilterCsd');

  function applyRfcFilters() {
    const q    = (searchInput?.value || '').trim().toLowerCase();
    const st   = (selStatus?.value || '').trim().toLowerCase(); // ok|warn|''
    const csd  = (selCsd?.value || '').trim(); // '1'|'0'|''

    const rows = Array.from(document.querySelectorAll('.sat-rfcs-row'));
    rows.forEach((tr) => {
      const rfc    = String(tr.dataset.rfc || '').toLowerCase();
      const alias  = String(tr.dataset.alias || '').toLowerCase();
      const rowSt  = String(tr.dataset.status || '').toLowerCase();
      const hasCsd = String(tr.dataset.hasCsd || '0');

      let ok = true;

      if (q) {
        const hay = (rfc + ' ' + alias);
        if (!hay.includes(q)) ok = false;
      }
      if (ok && st) {
        if (rowSt !== st) ok = false;
      }
      if (ok && csd !== '') {
        if (hasCsd !== csd) ok = false;
      }

      tr.style.display = ok ? '' : 'none';

      // Ocultar panel asociado si se filtra la fila
      const rowId = tr.dataset.row || '';
      const panel = rowId ? document.getElementById(rowId + '_panel') : null;
      if (panel) {
        if (!ok) {
          panel.setAttribute('hidden','');
          panel.setAttribute('aria-hidden','true');
          panel.style.display = 'none';
        } else {
          panel.style.display = '';
        }
      }
    });
  }

  if (searchInput) searchInput.addEventListener('input', applyRfcFilters);
  if (selStatus)   selStatus.addEventListener('change', applyRfcFilters);
  if (selCsd)      selCsd.addEventListener('change', applyRfcFilters);
  applyRfcFilters();

  // ============================================================
  // Registro externo: modal + envío
  // ============================================================
  const btnInviteOpen = document.getElementById('btnExternalRfcInviteOpen');
  const modalInvite   = document.getElementById('modalExternalRfcInvite');
  const inpEmail      = document.getElementById('externalInviteEmail');
  const inpNote       = document.getElementById('externalInviteNote');
  const btnSend       = document.getElementById('btnExternalInviteSend');
  const elResult      = document.getElementById('externalInviteResult');

  function openInviteModal() {
    if (!modalInvite) return satToast('Modal no encontrado (modalExternalRfcInvite).', 'error');
    modalInvite.style.display = 'flex';
    if (elResult) elResult.textContent = '—';
    try { if (inpEmail) inpEmail.focus(); } catch(e) {}
  }
  function closeInviteModal() {
    if (!modalInvite) return;
    modalInvite.style.display = 'none';
  }

  if (btnInviteOpen) {
    btnInviteOpen.addEventListener('click', function(ev){
      ev.preventDefault();
      openInviteModal();
    });
  }

  document.addEventListener('click', function(ev){
    const b = ev.target.closest('[data-close="modal-external-rfc"]');
    if (!b) return;
    ev.preventDefault();
    closeInviteModal();
  }, true);

  if (modalInvite) {
    modalInvite.addEventListener('click', function(ev){
      if (ev.target === modalInvite) closeInviteModal();
    });
  }

  function validEmail(v) {
    v = String(v || '').trim();
    if (!v) return false;
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
  }

  async function postJson(url, payload) {
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify(payload || {}),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || data.ok === false) throw new Error(data.msg || data.message || 'Solicitud fallida.');
    return data;
  }

  if (btnSend) {
    btnSend.addEventListener('click', async function(){
      const email = String(inpEmail?.value || '').trim();
      const note  = String(inpNote?.value || '').trim();

      if (!validEmail(email)) return satToast('Escribe un correo válido.', 'error');

      const ROUTES = (window.P360_SAT && window.P360_SAT.routes) ? window.P360_SAT.routes : {};
      const url = (ROUTES.externalRfcInvite || '').trim();

      if (!url) {
        if (elResult) elResult.textContent = 'Ruta no configurada: routes.externalRfcInvite (pendiente backend).';
        return satToast('Falta configurar la ruta del envío (externalRfcInvite).', 'error');
      }

      btnSend.disabled = true;
      if (elResult) elResult.textContent = 'Enviando liga…';

      try {
        const res = await postJson(url, { email, note });
        const link = res.link || (res.data && res.data.link) || null;

        if (elResult) {
          elResult.textContent = link
            ? ('Liga generada y enviada. (debug) ' + link)
            : (res.msg || res.message || 'Liga enviada correctamente.');
        }

        satToast('Invitación enviada.', 'success');
      } catch (e) {
        if (elResult) elResult.textContent = e?.message || 'Error al enviar la liga.';
        satToast(e?.message || 'No se pudo enviar la liga.', 'error');
      } finally {
        btnSend.disabled = false;
      }
    });
  }
})();
</script>
@endpush
