@php
  // ========== MÉTRICAS INICIALES ==========
  $k_total = $descargas->count();
  $k_done  = $descargas->where('status','done')->count();
  $k_req   = $descargas->where('status','requested')->count();
  $k_dw    = $descargas->where('status','downloading')->count();
  $k_err   = $descargas->where('status','error')->count();

  // UID único para que el JS/LS no choque con otras tarjetas
  $uid = 'hist_' . substr(md5(uniqid('',true)), 0, 6);

  // Mapa de estatus → badge
  $badge = function($st){
    return match($st){
      'done'        => 'bg-success',
      'downloading' => 'bg-info text-dark',
      'requested'   => 'bg-warning text-dark',
      'error'       => 'bg-danger',
      default       => 'bg-secondary'
    };
  };
@endphp

<div class="card sat-card" id="{{ $uid }}">
  <div class="card-header d-flex align-items-center justify-content-between">
    <span class="fw-bold">Histórico</span>
    <div class="d-flex gap-2 flex-wrap align-items-center">
      <div class="kpi"><span class="badge bg-secondary">Total</span><span class="n" data-kpi="total">{{ $k_total }}</span></div>
      <div class="kpi"><span class="badge bg-success">Listo</span><span class="n" data-kpi="done">{{ $k_done }}</span></div>
      <div class="kpi"><span class="badge bg-warning text-dark">Solicitado</span><span class="n" data-kpi="requested">{{ $k_req }}</span></div>
      <div class="kpi"><span class="badge bg-info text-dark">Bajando</span><span class="n" data-kpi="downloading">{{ $k_dw }}</span></div>
      <div class="kpi"><span class="badge bg-danger">Error</span><span class="n" data-kpi="error">{{ $k_err }}</span></div>
    </div>
  </div>

  <div class="card-body">
    @if($descargas->isEmpty())
      <div class="text-muted">Sin registros aún.</div>
    @else
      {{-- ======= CONTROLES ======= --}}
      <div class="d-flex flex-wrap gap-2 mb-2 align-items-center" data-role="filters">
        <input type="text" class="form-control" placeholder="Buscar RFC / ID / periodo / request / package…" data-f="q" style="min-width:240px">
        <select class="form-select" data-f="tipo" style="min-width:160px">
          <option value="">Tipo (todos)</option>
          <option value="emitidos">Emitidos</option>
          <option value="recibidos">Recibidos</option>
        </select>
        <select class="form-select" data-f="status" style="min-width:170px">
          <option value="">Estatus (todos)</option>
          <option value="done">done</option>
          <option value="requested">requested</option>
          <option value="downloading">downloading</option>
          <option value="error">error</option>
          <option value="pending">pending</option>
          <option value="ready">ready</option>
        </select>

        <div class="form-check ms-auto">
          <input class="form-check-input" type="checkbox" value="1" id="{{ $uid }}_withzip" data-f="onlyzip">
          <label class="form-check-label" for="{{ $uid }}_withzip">Solo con ZIP</label>
        </div>

        <div class="d-flex gap-2 mb-2">
          <input type="text" id="fSearch" class="form-control" placeholder="Buscar RFC / ID / rango">
          <select id="fTipo" class="form-select">
            <option value="">Tipo (todos)</option>
            <option value="emitidos">Emitidos</option>
            <option value="recibidos">Recibidos</option>
          </select>
          <select id="fStatus" class="form-select">
            <option value="">Estatus (todos)</option>
            <option value="done">done</option>
            <option value="requested">requested</option>
            <option value="downloading">downloading</option>
            <option value="error">error</option>
            <option value="pending">pending</option>
            <option value="ready">ready</option>
          </select>

          <!-- Nuevo -->
          <button type="button" id="btnClearFilters" class="btn btn-outline-secondary">
            Limpiar filtros
          </button>
        </div>


        <button type="button" class="btn btn-outline-secondary btn-sm" data-action="clear">Limpiar</button>
      </div>

      {{-- ======= TABLA ======= --}}
      <div class="table-responsive table-sticky" style="max-height: 520px; overflow:auto;">
        <table class="table table-sm align-middle" id="{{ $uid }}_tbl">
          <thead class="table-light sticky-top" style="z-index: 2">
            <tr>
              <th style="width:72px">ID</th>
              <th style="width:150px">RFC</th>
              <th style="width:210px">Periodo</th>
              <th style="width:120px">Tipo</th>
              <th style="width:140px">Estatus</th>
              <th style="min-width:240px">Request ID</th>
              <th style="min-width:240px">Package ID</th>
              <th style="min-width:120px">ZIP</th>
              <th style="min-width:280px">Acciones</th>
            </tr>
          </thead>
          <tbody>
            @foreach($descargas as $d)
              @php
                $df = \Illuminate\Support\Carbon::parse($d->date_from)->format('Y-m-d');
                $dt = \Illuminate\Support\Carbon::parse($d->date_to)->format('Y-m-d');
                $hasZip = (bool) $d->zip_path;
              @endphp
              <tr data-tipo="{{ $d->tipo }}" data-status="{{ $d->status }}" data-zip="{{ $hasZip ? '1' : '0' }}">
                <td class="mono">{{ $d->id }}</td>
                <td class="mono">{{ $d->rfc }}</td>
                <td><span class="mono">{{ $df }}</span> → <span class="mono">{{ $dt }}</span></td>
                <td>{{ ucfirst($d->tipo) }}</td>
                <td>
                  <span class="badge {{ $badge($d->status) }}">{{ $d->status }}</span>
                </td>
                <td class="text-truncate" style="max-width:240px;">
                  <span class="small mono" title="{{ $d->request_id }}">{{ $d->request_id }}</span>
                  @if($d->request_id)
                    <button type="button" class="btn btn-link btn-sm p-0 ms-1" data-copy="{{ $d->request_id }}">copiar</button>
                  @endif
                </td>
                <td class="text-truncate" style="max-width:240px;">
                  <span class="small mono" title="{{ $d->package_id }}">{{ $d->package_id }}</span>
                  @if($d->package_id)
                    <button type="button" class="btn btn-link btn-sm p-0 ms-1" data-copy="{{ $d->package_id }}">copiar</button>
                  @endif
                </td>
                <td class="mono">{{ $hasZip ? basename($d->zip_path) : '—' }}</td>
                <td>
                  <div class="d-flex flex-wrap gap-1">
                    @if($hasZip)
                      <a class="btn btn-outline-success btn-sm" href="{{ route('cliente.sat.zip', $d->id) }}">Descargar</a>
                    @endif
                    <form action="{{ route('cliente.sat.download') }}" method="post" class="d-flex gap-1">
                      @csrf
                      <input type="hidden" name="download_id" value="{{ $d->id }}">
                      <input type="text" name="package_id" value="{{ $d->package_id }}" placeholder="package_id" class="form-control form-control-sm mono" style="width: 200px" required>
                      <button class="btn btn-primary btn-sm">Bajar ZIP</button>
                    </form>
                  </div>
                  @if($d->error_message)
                    <div class="text-danger small mt-1">{{ $d->error_message }}</div>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>
</div>

@pushOnce('styles', 'sat-historial-css')
<style>
  .sat-card .kpi{display:inline-flex;align-items:center;gap:8px}
  .sat-card .kpi .n{font-weight:800;display:inline-block;min-width:24px;text-align:right}
  .mono{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono",monospace}
  /* highlight de filas filtradas */
  .row-dim{opacity:.45}
</style>
@endPushOnce

@pushOnce('scripts', 'sat-historial-js')
<script>
(function(){
  const root   = document.getElementById(@json($uid));
  if(!root) return;

  const LSKEY = 'p360.sat.hist.flt.' + @json($uid);
  const $     = (sel,ctx=root)=> (ctx||root).querySelector(sel);
  const $$    = (sel,ctx=root)=> Array.from((ctx||root).querySelectorAll(sel));

  const tbl   = $('#{{ $uid }}_tbl');
  const q     = $('[data-f="q"]');
  const fTipo = $('[data-f="tipo"]');
  const fStat = $('[data-f="status"]');
  const fZip  = $('[data-f="onlyzip"]');
  const btnClr= $('[data-action="clear"]');

  const kpis = {
    total: $('[data-kpi="total"]'),
    done: $('[data-kpi="done"]'),
    requested: $('[data-kpi="requested"]'),
    downloading: $('[data-kpi="downloading"]'),
    error: $('[data-kpi="error"]')
  };

  // ======= UTIL =======
  const deb = (fn,ms=180)=>{ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a),ms); }; };
  const text = (el)=> (el?.textContent || '').toLowerCase();

  function rowMatches(tr, query, tipo, status, onlyZip){
    if (onlyZip && tr.getAttribute('data-zip') !== '1') return false;
    if (tipo && tr.getAttribute('data-tipo') !== tipo) return false;
    if (status && tr.getAttribute('data-status') !== status) return false;

    if (!query) return true;
    const cells = Array.from(tr.cells);
    const hay = cells.some(td => text(td).includes(query));
    return hay;
  }

  function apply(){
    const query  = (q?.value || '').trim().toLowerCase();
    const tipo   = fTipo?.value || '';
    const status = fStat?.value || '';
    const onlyZip= !!(fZip?.checked);

    // persistir
    try{ localStorage.setItem(LSKEY, JSON.stringify({query,tipo,status,onlyZip})); }catch(_){}

    let total=0, done=0, req=0, dw=0, err=0;

    $$('tbody tr', tbl).forEach(tr=>{
      const ok = rowMatches(tr, query, tipo, status, onlyZip);
      tr.style.display = ok ? '' : 'none';
      if (ok){
        total++;
        const st = tr.getAttribute('data-status');
        if (st==='done') done++;
        else if (st==='requested') req++;
        else if (st==='downloading') dw++;
        else if (st==='error') err++;
      }
    });

    // actualizar KPIs
    if(kpis.total)       kpis.total.textContent = total;
    if(kpis.done)        kpis.done.textContent  = done;
    if(kpis.requested)   kpis.requested.textContent = req;
    if(kpis.downloading) kpis.downloading.textContent = dw;
    if(kpis.error)       kpis.error.textContent = err;
  }

  const applyDeb = deb(apply, 140);

  // ======= RESTAURAR FILTROS =======
  (function restore(){
    try{
      const raw = localStorage.getItem(LSKEY);
      if(!raw) return;
      const st = JSON.parse(raw);
      if(q && typeof st.query==='string') q.value = st.query;
      if(fTipo)  fTipo.value  = st.tipo   || '';
      if(fStat)  fStat.value  = st.status || '';
      if(fZip)   fZip.checked = !!st.onlyZip;
    }catch(_){}
  })();

  // ======= BIND =======
  q?.addEventListener('input', applyDeb);
  fTipo?.addEventListener('change', apply);
  fStat?.addEventListener('change', apply);
  fZip?.addEventListener('change', apply);

  btnClr?.addEventListener('click', ()=>{
    if(q) q.value = '';
    if(fTipo) fTipo.value = '';
    if(fStat) fStat.value = '';
    if(fZip)  fZip.checked = false;
    try{ localStorage.removeItem(LSKEY); }catch(_){}
    apply();
  });

  // Copiar al portapapeles (delegado)
  root.addEventListener('click', async (e)=>{
    const btn = e.target.closest('[data-copy]');
    if(!btn) return;
    const val = btn.getAttribute('data-copy') || '';
    try{
      await navigator.clipboard.writeText(val);
      btn.textContent = 'copiado';
      setTimeout(()=> btn.textContent='copiar', 900);
    }catch(_){}
  }, {passive:true});

  // Primera aplicación
  apply();
})();
</script>
@endPushOnce
