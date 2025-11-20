<div class="sat-card sat-dl">
  <div class="sat-card-head">
    <div>
      <h3>Listado de descargas SAT</h3>
      <p>Histórico de solicitudes y paquetes generados</p>
    </div>
    <div class="sat-dl-filters">
      <input id="satDlSearch" type="text" placeholder="Buscar por ID o RFC" />
      <select id="satDlTipo">
        <option value="">Todos los tipos</option>
        <option value="emitidos">Emitidos</option>
        <option value="recibidos">Recibidos</option>
      </select>
      <select id="satDlStatus">
        <option value="">Todos los estados</option>
        <option value="pending">Pending</option>
        <option value="processing">Processing</option>
        <option value="ready">Ready</option>
        <option value="done">Done/Listo</option>
      </select>
    </div>
  </div>

  <div class="sat-dl-table-wrap">
    <table class="sat-dl-table">
      <thead>
      <tr>
        <th>ID</th>
        <th>Tipo</th>
        <th>Periodo</th>
        <th>Estado</th>
        <th>Paquete</th>
        <th>Acciones</th>
      </tr>
      </thead>
      <tbody id="satDlBody"></tbody>
    </table>
  </div>
</div>

@push('styles')
<style>
.sat-dl .sat-card-head{
  display:flex;justify-content:space-between;align-items:flex-end;gap:12px;margin-bottom:8px;
}
.sat-dl h3{margin:0;font:900 16px/1 'Poppins',system-ui;}
.sat-dl p{margin:2px 0 0;font-size:11px;color:#6b7280;}
.sat-dl-filters{display:flex;gap:6px;flex-wrap:wrap;align-items:center;}
.sat-dl-filters input,
.sat-dl-filters select{
  border-radius:999px;border:1px solid #e5e7eb;
  padding:6px 10px;font-size:12px;font-family:'Poppins',system-ui;
}
.sat-dl-table-wrap{border-radius:14px;border:1px solid #fee2e2;overflow:hidden;background:#fff;}
.sat-dl-table{width:100%;border-collapse:collapse;font-size:12px;font-family:'Poppins',system-ui;}
.sat-dl-table thead{background:#fef2f2;text-transform:uppercase;font-size:11px;letter-spacing:.08em;color:#9ca3af;}
.sat-dl-table th,
.sat-dl-table td{padding:8px 10px;border-bottom:1px solid #f3f4f6;text-align:left;vertical-align:middle;}
.sat-dl-table tbody tr:last-child td{border-bottom:0;}
.sat-badge-status{
  display:inline-flex;align-items:center;justify-content:center;
  padding:2px 8px;border-radius:999px;font-size:10px;font-weight:800;text-transform:uppercase;
}
.sat-badge-status.done{background:#dcfce7;color:#166534;}
.sat-badge-status.pending,
.sat-badge-status.processing{background:#fef3c7;color:#92400e;}
.sat-dl-btn-download{
  width:26px;height:26px;border-radius:999px;border:1px solid #e5e7eb;
  background:#eff6ff;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;
}
</style>
@endpush

@push('scripts')
<script>
(function(){
  const rows = @json($initialRows ?? []);
  const body = document.getElementById('satDlBody');
  const qInput = document.getElementById('satDlSearch');
  const tipoSel = document.getElementById('satDlTipo');
  const statusSel = document.getElementById('satDlStatus');
  const csrf = '{{ csrf_token() }}';

  function normalizeStatus(s){
    s = (s || '').toString().toLowerCase();
    if (['listo','ready'].includes(s)) return 'done';
    return s;
  }

  function render(){
    if (!body) return;
    const q = (qInput?.value || '').toLowerCase();
    const ftipo = (tipoSel?.value || '').toLowerCase();
    const fstat = (statusSel?.value || '').toLowerCase();

    body.innerHTML = '';

    rows.forEach(r => {
      const id = r.dlid || r.id || '';
      const rfc = (r.rfc || '').toString();
      const tipo = (r.tipo || '').toString().toLowerCase();
      const statusRaw = (r.estado || r.status || '').toString();
      const status = normalizeStatus(statusRaw);

      if (q && !id.toLowerCase().includes(q) && !rfc.toLowerCase().includes(q)) {
        return;
      }
      if (ftipo && tipo !== ftipo) return;
      if (fstat && status !== fstat) return;

      const tr = document.createElement('tr');

      const periodo = (r.desde || '') && (r.hasta || '')
        ? `${r.desde} → ${r.hasta}`
        : '';

      tr.innerHTML = `
        <td class="mono">${id}</td>
        <td>${tipo ? ('• ' + tipo.charAt(0).toUpperCase() + tipo.slice(1)) : ''}</td>
        <td>${periodo}</td>
        <td><span class="sat-badge-status ${status}">${status}</span></td>
        <td>${r.package_id || ''}</td>
        <td>
          <button class="sat-dl-btn-download" data-id="${id}" title="Descargar ZIP">
            ⬇
          </button>
        </td>
      `;
      body.appendChild(tr);
    });
  }

  if (qInput) qInput.addEventListener('input', render);
  if (tipoSel) tipoSel.addEventListener('change', render);
  if (statusSel) statusSel.addEventListener('change', render);

  if (body) {
    body.addEventListener('click', async (ev) => {
      const btn = ev.target.closest('.sat-dl-btn-download');
      if (!btn) return;
      const id = btn.dataset.id;
      if (!id) return;

      btn.disabled = true;

      try{
        const res = await fetch('{{ route('cliente.sat.download') }}', {
          method:'POST',
          headers:{
            'Content-Type':'application/json',
            'X-Requested-With':'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf,
          },
          body: JSON.stringify({id}),
        });
        const json = await res.json().catch(()=>null);

        if (!res.ok || !json || json.ok === false) {
          alert(json?.msg || 'No se pudo preparar el ZIP.');
          return;
        }

        if (json.zip_url) {
          window.location.href = json.zip_url;
        }
      }catch(e){
        console.error(e);
        alert('Error de conexión al descargar.');
      }finally{
        btn.disabled = false;
      }
    });
  }

  render();
})();
</script>
@endpush
