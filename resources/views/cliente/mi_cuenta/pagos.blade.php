{{-- resources/views/cliente/mi_cuenta/pagos.blade.php (P360 · Mi cuenta · Pagos v2.0 · Fetch real + tabla) --}}

@php
  // Endpoint JSON (MiCuentaController@pagos)
  $pagosUrl = route('cliente.mi_cuenta.pagos');
@endphp

<style>
  /* Modal base (neutral, no pisa tu theme) */
  .p360-modal-backdrop{
    position: fixed; inset: 0;
    background: rgba(15, 23, 42, .55);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    padding: 18px;
  }
  .p360-modal-backdrop.is-open{ display:flex; }

  .p360-modal{
    width: min(980px, calc(100vw - 24px));
    border-radius: 16px;
    background: #fff;
    box-shadow: 0 20px 60px rgba(0,0,0,.35);
    overflow: hidden;
  }
  .p360-modal-header{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap: 12px;
    padding: 18px 18px 12px 18px;
    border-bottom: 1px solid rgba(15,23,42,.08);
  }
  .p360-modal-title{ font-weight: 800; color:#0f172a; font-size: 15px; margin:0; }
  .p360-modal-sub{ color:#64748b; font-size: 12px; margin-top: 3px; }
  .p360-icon-btn{
    width: 34px; height: 34px;
    border-radius: 999px;
    border: 1px solid rgba(15,23,42,.12);
    background: #fff;
    cursor: pointer;
    display:flex; align-items:center; justify-content:center;
  }
  .p360-icon-btn:hover{ background: rgba(15,23,42,.04); }

  .p360-modal-body{ padding: 14px 18px 16px 18px; }
  .p360-card{
    border: 1px solid rgba(15,23,42,.10);
    border-radius: 14px;
    padding: 12px 12px;
    background: #fff;
  }
  .p360-row{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap: 12px;
  }
  .p360-row h4{ margin:0; font-size: 13px; font-weight: 800; color:#0f172a; }
  .p360-row p{ margin:2px 0 0 0; font-size: 12px; color:#64748b; }
  .p360-btn{
    border: 1px solid rgba(15,23,42,.12);
    background: #fff;
    border-radius: 999px;
    padding: 8px 12px;
    font-weight: 700;
    font-size: 12px;
    cursor: pointer;
    color:#0f172a;
  }
  .p360-btn:hover{ background: rgba(15,23,42,.04); }
  .p360-btn[disabled]{ opacity:.6; cursor:not-allowed; }

  .p360-divider{ height: 10px; }

  .p360-empty{
    margin-top: 12px;
    border: 1px dashed rgba(15,23,42,.18);
    border-radius: 12px;
    padding: 12px;
    color:#0f172a;
    background: rgba(15,23,42,.02);
    font-size: 13px;
    font-weight: 700;
  }

  /* Tabla */
  .p360-table-wrap{
    margin-top: 12px;
    border: 1px solid rgba(15,23,42,.10);
    border-radius: 12px;
    overflow: hidden;
  }
  .p360-table{
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
  }
  .p360-table thead th{
    text-align: left;
    padding: 10px 10px;
    background: rgba(15,23,42,.03);
    color:#0f172a;
    font-weight: 800;
    border-bottom: 1px solid rgba(15,23,42,.08);
    white-space: nowrap;
  }
  .p360-table tbody td{
    padding: 10px 10px;
    border-bottom: 1px solid rgba(15,23,42,.06);
    color:#0f172a;
    vertical-align: top;
  }
  .p360-table tbody tr:hover{ background: rgba(15,23,42,.02); }
  .p360-muted{ color:#64748b; }

  .p360-badge{
    display:inline-flex;
    align-items:center;
    gap: 6px;
    padding: 3px 10px;
    border-radius: 999px;
    border: 1px solid rgba(15,23,42,.12);
    font-weight: 800;
    font-size: 11px;
    white-space: nowrap;
  }
  .p360-badge.ok{ background: rgba(34,197,94,.10); border-color: rgba(34,197,94,.22); color:#166534; }
  .p360-badge.bad{ background: rgba(239,68,68,.10); border-color: rgba(239,68,68,.22); color:#991b1b; }
  .p360-badge.neutral{ background: rgba(15,23,42,.03); border-color: rgba(15,23,42,.10); color:#0f172a; }

  .p360-links a{
    display:inline-block;
    margin-right: 8px;
    font-weight: 800;
    color:#0f172a;
    text-decoration: none;
    border-bottom: 1px dashed rgba(15,23,42,.35);
  }
  .p360-links a:hover{ border-bottom-style: solid; }

  .p360-modal-footer{
    display:flex;
    justify-content:flex-end;
    gap: 10px;
    padding: 12px 18px 16px 18px;
    border-top: 1px solid rgba(15,23,42,.08);
    background: #fff;
  }

  /* Responsive: colapsa tabla */
  @media (max-width: 760px){
    .p360-table thead{ display:none; }
    .p360-table, .p360-table tbody, .p360-table tr, .p360-table td{
      display:block; width:100%;
    }
    .p360-table tr{ border-bottom: 1px solid rgba(15,23,42,.08); }
    .p360-table tbody td{
      border:0;
      padding: 8px 12px;
    }
    .p360-table tbody td::before{
      content: attr(data-label);
      display:block;
      color:#64748b;
      font-weight: 800;
      font-size: 11px;
      margin-bottom: 3px;
      text-transform: uppercase;
      letter-spacing: .02em;
    }
  }
</style>

{{-- Trigger opcional:
     Usa cualquier botón/ícono con data-open="p360-pagos-modal" para abrir.
--}}
<div class="p360-modal-backdrop" id="p360PagosBackdrop" aria-hidden="true">
  <div class="p360-modal" role="dialog" aria-modal="true" aria-labelledby="p360PagosTitle">
    <div class="p360-modal-header">
      <div>
        <h3 class="p360-modal-title" id="p360PagosTitle">Mis pagos</h3>
        <div class="p360-modal-sub">Listado de todos los pagos y compras realizados en tu cuenta.</div>
      </div>
      <button type="button" class="p360-icon-btn" data-close="p360-pagos-modal" aria-label="Cerrar">
        ✕
      </button>
    </div>

    <div class="p360-modal-body">
      <div class="p360-card">
        <div class="p360-row">
          <div>
            <h4>Historial</h4>
            <p>Incluye suscripción, módulos, consumos y otros cargos.</p>
          </div>

          <button type="button" class="p360-btn" id="p360PagosRefreshBtn">
            Actualizar
          </button>
        </div>

        <div class="p360-divider"></div>

        <div id="p360PagosMsg" class="p360-empty" style="display:none;"></div>

        <div id="p360PagosEmpty" class="p360-empty">
          Aún no tienes pagos registrados.
        </div>

        <div id="p360PagosTableWrap" class="p360-table-wrap" style="display:none;">
          <table class="p360-table" aria-describedby="p360PagosTitle">
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Concepto</th>
                <th>Periodo</th>
                <th>Monto</th>
                <th>Método</th>
                <th>Estatus</th>
                <th>Referencia</th>
                <th>Comprobantes</th>
              </tr>
            </thead>
            <tbody id="p360PagosTbody"></tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="p360-modal-footer">
      <button type="button" class="p360-btn" data-close="p360-pagos-modal">Cerrar</button>
    </div>
  </div>
</div>

<script>
(function(){
  'use strict';

  const BACKDROP_ID = 'p360PagosBackdrop';
  const backdrop = document.getElementById(BACKDROP_ID);
  if (!backdrop) return;

  const refreshBtn = document.getElementById('p360PagosRefreshBtn');
  const tbody      = document.getElementById('p360PagosTbody');
  const tableWrap  = document.getElementById('p360PagosTableWrap');
  const emptyBox   = document.getElementById('p360PagosEmpty');
  const msgBox     = document.getElementById('p360PagosMsg');

  const URL = @json($pagosUrl);

  function openModal(){
    backdrop.classList.add('is-open');
    backdrop.setAttribute('aria-hidden', 'false');
    // Carga automática al abrir
    loadPayments();
  }

  function closeModal(){
    backdrop.classList.remove('is-open');
    backdrop.setAttribute('aria-hidden', 'true');
  }

  function money(amount, currency){
    const cur = (currency || 'MXN').toUpperCase();
    const num = (amount === null || amount === undefined || amount === '') ? null : Number(amount);
    if (Number.isFinite(num)){
      try{
        return new Intl.NumberFormat('es-MX', { style: 'currency', currency: cur }).format(num);
      }catch(e){
        return (cur + ' ' + num.toFixed(2));
      }
    }
    return (amount || '—');
  }

  function badge(status){
    const s = (status || '—').toString().toUpperCase();
    let cls = 'neutral';
    if (['PAID','PAGADO','SUCCEEDED','SUCCESS','COMPLETED','CONFIRMED','CAPTURED','OK'].includes(s)) cls = 'ok';
    if (['FAILED','CANCELED','CANCELLED','REJECTED','ERROR','REFUNDED'].includes(s)) cls = 'bad';
    return `<span class="p360-badge ${cls}">${escapeHtml(s)}</span>`;
  }

  function escapeHtml(str){
    return (str || '').toString()
      .replaceAll('&','&amp;')
      .replaceAll('<','&lt;')
      .replaceAll('>','&gt;')
      .replaceAll('"','&quot;')
      .replaceAll("'","&#039;");
  }

  function renderRows(rows){
    tbody.innerHTML = '';

    if (!Array.isArray(rows) || rows.length === 0){
      tableWrap.style.display = 'none';
      emptyBox.style.display = 'block';
      return;
    }

    emptyBox.style.display = 'none';
    tableWrap.style.display = 'block';

    const frag = document.createDocumentFragment();

    rows.forEach(r => {
      const date      = (r.date || '—');
      const concept   = (r.concept || 'Pago');
      const period    = (r.period || '—');
      const amount    = money(r.amount, r.currency);
      const method    = (r.method || '—');
      const status    = badge(r.status || '—');
      const reference = (r.reference || r.id || '—');

      const invoice = (r.invoice || '').toString().trim();
      const receipt = (r.receipt || '').toString().trim();

      let links = '—';
      const parts = [];
      if (invoice) parts.push(`<a href="${escapeHtml(invoice)}" target="_blank" rel="noopener">Factura</a>`);
      if (receipt) parts.push(`<a href="${escapeHtml(receipt)}" target="_blank" rel="noopener">Recibo</a>`);
      if (parts.length) links = `<span class="p360-links">${parts.join('')}</span>`;

      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td data-label="Fecha">${escapeHtml(date)}</td>
        <td data-label="Concepto">${escapeHtml(concept)}</td>
        <td data-label="Periodo"><span class="p360-muted">${escapeHtml(period)}</span></td>
        <td data-label="Monto"><strong>${escapeHtml(amount)}</strong></td>
        <td data-label="Método"><span class="p360-muted">${escapeHtml(method)}</span></td>
        <td data-label="Estatus">${status}</td>
        <td data-label="Referencia"><span class="p360-muted">${escapeHtml(reference)}</span></td>
        <td data-label="Comprobantes">${links}</td>
      `;
      frag.appendChild(tr);
    });

    tbody.appendChild(frag);
  }

  function setMsg(text, kind){
    msgBox.style.display = text ? 'block' : 'none';
    msgBox.textContent = text || '';
    // kind: 'error'|'info'
    if (kind === 'error'){
      msgBox.style.borderStyle = 'solid';
      msgBox.style.borderColor = 'rgba(239,68,68,.35)';
      msgBox.style.background  = 'rgba(239,68,68,.06)';
    } else {
      msgBox.style.borderStyle = 'dashed';
      msgBox.style.borderColor = 'rgba(15,23,42,.18)';
      msgBox.style.background  = 'rgba(15,23,42,.02)';
    }
  }

  async function loadPayments(){
    if (!refreshBtn) return;

    refreshBtn.disabled = true;
    const oldText = refreshBtn.textContent;
    refreshBtn.textContent = 'Cargando...';
    setMsg('', 'info');

    try{
      const res = await fetch(URL + '?limit=200', {
        method: 'GET',
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin'
      });

      if (!res.ok){
        throw new Error('HTTP ' + res.status);
      }

      const data = await res.json();
      if (!data || data.ok !== true){
        throw new Error((data && data.error) ? data.error : 'Respuesta inválida');
      }

      renderRows(Array.isArray(data.rows) ? data.rows : []);
      if (Array.isArray(data.rows) && data.rows.length){
        setMsg('', 'info');
      }
    }catch(err){
      console.error('[P360 Pagos] error', err);
      renderRows([]);
      setMsg('No se pudo cargar el historial de pagos. Revisa el log del sistema.', 'error');
    }finally{
      refreshBtn.disabled = false;
      refreshBtn.textContent = oldText;
    }
  }

  // Abrir: cualquier elemento con data-open="p360-pagos-modal"
  document.addEventListener('click', function(ev){
    const t = ev.target.closest('[data-open="p360-pagos-modal"]');
    if (t){
      ev.preventDefault();
      openModal();
      return;
    }

    const c = ev.target.closest('[data-close="p360-pagos-modal"]');
    if (c){
      ev.preventDefault();
      closeModal();
      return;
    }
  });

  // Click fuera del modal
  backdrop.addEventListener('click', function(ev){
    if (ev.target === backdrop){
      closeModal();
    }
  });

  // Escape
  document.addEventListener('keydown', function(ev){
    if (ev.key === 'Escape' && backdrop.classList.contains('is-open')){
      closeModal();
    }
  });

  // Botón actualizar
  if (refreshBtn){
    refreshBtn.addEventListener('click', function(ev){
      ev.preventDefault();
      loadPayments();
    });
  }

  // Exponer helpers por si lo abres desde otro JS
  window.P360PagosModal = window.P360PagosModal || {
    open: openModal,
    close: closeModal,
    reload: loadPayments
  };
})();
</script>
