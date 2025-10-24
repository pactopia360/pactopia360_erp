{{-- resources/views/cliente/home.blade.php · v6 compacto/pro + mes/año separados --}}
@extends('layouts.client')
@section('title','Facturación · Pactopia360')

@php
  $periodoActual = \Carbon\Carbon::now()->isoFormat('MMMM YYYY');

  // Defaults seguros por si no vienen del controlador
  $kpis   = $kpis   ?? ['total'=>0,'delta'=>0,'pagados'=>0,'pagados_cnt'=>0,'pendientes'=>0,'cancelados'=>0,'cancelados_cnt'=>0,'vencidos'=>0];
  $series = $series ?? ['labels'=>[],'series'=>['line_facturacion'=>[],'line_cancelados'=>[],'bar_q'=>[10,24,22,14]]];
  $s      = $summary ?? null;

  // Badge (solo en la tarjeta de Plan, NO en el header para evitar duplicados)
  $planKey   = strtoupper($plan ?? ($s['plan'] ?? 'FREE'));
  $isPro     = $planKey === 'PRO';
  $planBadge = $isPro
    ? '<span class="badge status sm" style="--bdg-bg:#E6F3FB;--bdg-bd:#B7DCF2;--bdg-fg:#0B3A55">PRO</span>'
    : '<span class="badge status sm" style="--bdg-bg:#FFF5E5;--bdg-bd:#F4D7A6;--bdg-fg:#5A3E08">FREE</span>';

  // Para selects separados de mes y año
  $now = \Carbon\Carbon::now();
  $monthNow = (int) $now->format('m');
  $yearNow  = (int) $now->format('Y');
@endphp

@push('styles')
<style>
/* ===== Escala y tipografía sutil ===== */
:root{
  --fs-xs:.78rem; --fs-sm:.86rem; --fs-md:.95rem; --fs-lg:1.15rem;
  --c-text:#1f2937; --c-soft:#6b7280; --c-line:#e5e7eb; --c-card:#ffffff;
  --c-muted:#f8fafc; --c-brand:#2563eb; --c-pos:#16a34a; --c-neg:#dc2626;
  --rad:12px; --pad:14px; --shadow:0 1px 0 rgba(0,0,0,.03), 0 4px 14px rgba(0,0,0,.06);
}
.page-header{display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:12px}
.page-title{font-size:1.05rem; font-weight:600; color:var(--c-text); margin:0}
.header-actions{display:flex; align-items:center; gap:8px}

.searchbar{
  height:34px; min-width:280px; padding:0 12px; border:1px solid var(--c-line);
  background:#fff; border-radius:999px; font-size:var(--fs-sm); color:var(--c-text);
}

/* ===== Botones (un solo sistema de estilos) ===== */
.btn{
  --bg:#fff; --bd:var(--c-line); --fg:var(--c-text);
  display:inline-flex; align-items:center; gap:8px; height:34px; padding:0 12px;
  border:1px solid var(--bd); background:var(--bg); color:var(--fg); font-size:var(--fs-sm);
  border-radius:999px; text-decoration:none; box-shadow:var(--shadow); transition:.15s ease;
}
.btn:hover{transform:translateY(-1px); box-shadow:0 6px 18px rgba(0,0,0,.08)}
.btn:active{transform:none; box-shadow:var(--shadow)}
.btn.sm{height:28px; padding:0 10px; font-size:var(--fs-xs)}
.btn--primary{--bg:color-mix(in oklab, var(--c-brand) 10%, #fff); --bd:color-mix(in oklab, var(--c-brand) 45%, #fff); --fg:#0b1a3a}
.btn--success{--bg:color-mix(in oklab, var(--c-pos) 10%, #fff);   --bd:color-mix(in oklab, var(--c-pos) 45%, #fff);   --fg:#07351a}
.btn--ghost{--bg:#fff; --bd:var(--c-line); --fg:var(--c-text)}
.btn--soft{--bg:var(--c-muted); --bd:var(--c-line); --fg:var(--c-text)}
.btn svg{width:16px; height:16px}

/* Select con look de botón */
.btn-select{display:inline-flex; align-items:center; gap:8px; height:34px; padding:0 10px; border:1px solid var(--c-line); background:#fff; border-radius:999px; box-shadow:var(--shadow)}
.btn-select select{border:0; background:transparent; outline:0; font-size:var(--fs-sm); color:var(--c-text); padding-right:20px; appearance:none}
.btn-select .chev{width:14px; height:14px; color:var(--c-soft)}

/* ===== Tarjetas y bloques ===== */
.card{background:var(--c-card); border:1px solid var(--c-line); border-radius:var(--rad); padding:var(--pad); box-shadow:var(--shadow)}
.card.headless{padding-top:10px}
.page-sub{color:var(--c-soft); font-size:var(--fs-xs)}
.progress{background:var(--c-muted); border-radius:999px; height:6px; overflow:hidden}
.progress>span{display:block; height:100%; background:color-mix(in oklab, var(--c-brand) 30%, #4ade80)}

.quick{display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:8px}
.quick .btn{justify-content:space-between}

/* ===== Grids ===== */
.account-grid{display:grid; grid-template-columns:1.2fr .8fr; gap:10px}
.kpis{display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px}
.grid-2{display:grid; grid-template-columns:1.1fr 1fr; gap:10px}

/* ===== KPI ===== */
.kpi{background:var(--c-card); border:1px solid var(--c-line); border-radius:var(--rad); padding:var(--pad); box-shadow:var(--shadow)}
.kpi.primary{background:linear-gradient(180deg,#ffffff, #fbfeff)}
.kpi .label{font-size:var(--fs-xs); color:var(--c-soft)}
.kpi .value{font:600 1.05rem/1.1 ui-sans-serif,system-ui; color:var(--c-text); margin:6px 0 2px}
.kpi .hint{font-size:var(--fs-xs); color:var(--c-soft)}
.trend{display:inline-flex; align-items:center; gap:4px; font-size:var(--fs-xs)}
.trend.up{color:var(--c-pos)} .trend.down{color:var(--c-neg)} .trend.flat{color:var(--c-soft)}

/* ===== Tablas ===== */
table.list{width:100%; border-collapse:separate; border-spacing:0; font-size:var(--fs-sm)}
table.list th, table.list td{padding:10px 12px; border-bottom:1px solid var(--c-line); text-align:left; white-space:nowrap}
table.list thead th{font-weight:600; color:var(--c-soft)}
table.list .uuid{font-family:ui-monospace, SFMono-Regular, Menlo, monospace; font-size:var(--fs-xs)}

/* ===== Chart wrappers ===== */
.chart-card{background:var(--c-card); border:1px solid var(--c-line); border-radius:var(--rad); box-shadow:var(--shadow); padding:var(--pad)}
.chart-head{display:flex; align-items:center; justify-content:space-between; margin-bottom:6px}
.canvas-wrap{position:relative; height:240px}

/* ===== Badges ===== */
.badge.status{display:inline-flex; align-items:center; gap:6px; border:1px solid var(--bdg-bd); background:var(--bdg-bg); color:var(--bdg-fg); border-radius:999px; padding:2px 8px; font-size:var(--fs-xs)}
.badge.status.sm{padding:1px 7px}
</style>
@endpush

@section('content')
  {{-- HEADER (sin badge de plan aquí para evitar duplicidad) --}}
  <div class="page-header">
    <div style="display:flex; align-items:center; gap:10px">
      <h1 class="page-title">Facturación</h1>

      @if (Route::has('cliente.facturacion.nuevo'))
        <a class="btn btn--success" href="{{ route('cliente.facturacion.nuevo') }}" title="Nuevo CFDI">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
          <span>Nuevo</span>
        </a>
      @endif
    </div>

    <div class="header-actions">
      <input class="searchbar" placeholder="Buscar por receptor, folio, UUID…">

      {{-- Mes y Año (separados) --}}
      <label class="btn-select" for="mes">
        <svg class="chev" viewBox="0 0 24 24" fill="currentColor"><path d="M7 10l5 5 5-5"/></svg>
        <select id="mes" name="mes">
          @foreach (range(1,12) as $m)
            @php $nm = \Carbon\Carbon::createFromDate($yearNow, $m, 1)->locale('es'); @endphp
            <option value="{{ str_pad($m,2,'0',STR_PAD_LEFT) }}" {{ $m===$monthNow ? 'selected' : '' }}>
              {{ $nm->isoFormat('MMMM') }}
            </option>
          @endforeach
        </select>
      </label>

      <label class="btn-select" for="anio">
        <svg class="chev" viewBox="0 0 24 24" fill="currentColor"><path d="M7 10l5 5 5-5"/></svg>
        <select id="anio" name="anio">
          @foreach (range($yearNow-2, $yearNow+1) as $y)
            <option value="{{ $y }}" {{ $y===$yearNow ? 'selected' : '' }}>{{ $y }}</option>
          @endforeach
        </select>
      </label>

      @if (Route::has('cliente.facturacion.export'))
        <a class="btn btn--primary" href="{{ route('cliente.facturacion.export') }}">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v12m0 0l-4-4m4 4l4-4M4 21h16"/></svg>
          <span>Exportar</span>
        </a>
      @endif
    </div>
  </div>

  {{-- ACCESOS RÁPIDOS --}}
  <div class="card" style="margin-bottom:10px">
    <div class="page-sub" style="margin-bottom:6px;">Accesos rápidos</div>
    <div class="quick">
      <a class="btn btn--soft" href="{{ route('cliente.facturacion.index') }}"><span>Facturación</span><span class="page-sub">Panel</span></a>
      <a class="btn btn--soft" href="{{ route('cliente.estado_cuenta') }}"><span>Estado de cuenta</span><span class="page-sub">Ver</span></a>
      <a class="btn btn--soft" href="{{ route('cliente.billing.statement') }}"><span>Pagos</span><span class="page-sub">Billing</span></a>
      <a class="btn btn--soft" href="{{ route('cliente.perfil') }}"><span>Perfil</span><span class="page-sub">Editar</span></a>
    </div>
  </div>

  {{-- TU CUENTA (Plan + Saldo + Espacio/Timbres) --}}
  <div class="account-grid" style="margin-bottom:10px">
    <div class="card">
      <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:10px">
        <div>
          <div class="page-sub">Plan</div>
          <div class="page-title" style="font-size:var(--fs-lg); display:flex; align-items:center; gap:8px">
            {{ $planKey }} {!! $planBadge !!}
          </div>
          <div class="page-sub">Estado: {{ ($s['blocked'] ?? false) ? 'Bloqueado' : 'Activo' }}</div>
        </div>
        <div style="text-align:right">
          <div class="page-sub">Saldo</div>
          <div class="page-title" style="font-size:var(--fs-lg)">${{ number_format((float)($s['balance'] ?? ($saldo ?? 0)), 2) }}</div>
        </div>
      </div>

      @if(isset($pricing))
        <div style="display:flex; gap:8px; margin-top:10px; flex-wrap:wrap">
          <a class="btn btn--primary sm" href="{{ route('cliente.registro.pro') }}">Mejorar a PRO · ${{ number_format($pricing['monthly'] ?? 0,2) }}/mes</a>
          <span class="btn sm" style="pointer-events:none">Plan anual · ${{ number_format($pricing['annual'] ?? 0,2) }}</span>
        </div>
      @endif
    </div>

    <div class="card">
      @php
        $used = (float) ($s['space_used'] ?? 0);
        $total = max(1,(float) ($s['space_total'] ?? 512));
        $pct = min(100, round(($used / $total) * 100, 1));
      @endphp
      <div class="page-sub" style="margin-bottom:6px">Espacio usado</div>
      <div class="page-sub">{{ number_format($used,1) }} MB de {{ number_format($total,0) }} MB ({{ $pct }}%)</div>
      <div class="progress" style="margin-top:6px"><span style="width:{{ $pct }}%"></span></div>
      <div class="page-sub" style="margin-top:8px">Timbres: {{ number_format((int)($s['timbres'] ?? ($timbres ?? 0))) }}</div>
    </div>
  </div>

  {{-- KPIs --}}
  <div class="kpis">
    {{-- KPI principal --}}
    <div class="kpi primary">
      <div class="label">Facturación</div>
      <div class="value" data-kpi="total">${{ number_format($kpis['total'] ?? 0, 2) }}</div>
      @php $d = (float)($kpis['delta'] ?? 0); @endphp
      <div class="trend {{ $d>0?'up':($d<0?'down':'flat') }}">
        <span data-kpi="delta">{{ number_format($d,2) }}</span>%
      </div>
      <div class="hint">En el período</div>
    </div>

    {{-- Pagados --}}
    <div class="kpi">
      <div class="label">Pagados</div>
      <div class="value" data-kpi="pagados">${{ number_format($kpis['pagados'] ?? 0, 2) }}</div>
      <div class="hint"><span data-kpi="pagados_cnt">{{ (int)($kpis['pagados_cnt'] ?? 0) }}</span> Doc</div>
      <div class="hint">$<span data-kpi="pendientes">{{ number_format($kpis['pendientes'] ?? 0, 2) }}</span> Pendientes</div>
    </div>

    {{-- Cancelados --}}
    <div class="kpi">
      <div class="label">Cancelados</div>
      <div class="value" data-kpi="cancelados">${{ number_format($kpis['cancelados'] ?? 0, 2) }}</div>
      <div class="hint"><span data-kpi="cancelados_cnt">{{ (int)($kpis['cancelados_cnt'] ?? 0) }}</span> Doc</div>
      <div class="hint">$<span data-kpi="vencidos">{{ number_format($kpis['vencidos'] ?? 0, 2) }}</span> Vencidos</div>
    </div>

    <div class="kpi">
      <div class="label">Timbres</div>
      <div class="value">{{ number_format($s['timbres'] ?? ($timbres ?? 0)) }}</div>
      <div class="hint">Plan {{ $planKey }}</div>
    </div>
  </div>

  {{-- GRÁFICAS --}}
  <div class="grid-2" style="margin-top:10px">
    <div class="chart-card">
      <div class="chart-head"><div class="page-sub">Facturación YT vs LY</div></div>
      <div class="canvas-wrap"><canvas id="barQ"></canvas></div>
    </div>

    <div class="chart-card">
      <div class="chart-head"><div class="page-sub">Facturación</div></div>
      <div class="canvas-wrap"><canvas id="lineFact"></canvas></div>
    </div>
  </div>

  {{-- ÚLTIMOS DOCUMENTOS --}}
  <div class="card headless" style="margin-top:10px">
    <div class="page-sub" style="margin:2px 0 8px">Últimos documentos</div>
    <div style="overflow:auto">
      <table class="list">
        <thead>
        <tr>
          <th>UUID</th>
          <th>Serie/Folio</th>
          <th>Total</th>
          <th>Estatus</th>
          <th>Fecha</th>
          <th></th>
        </tr>
        </thead>
        <tbody>
        @forelse($recent as $r)
          <tr>
            <td class="uuid">{{ $r->uuid }}</td>
            <td>{{ trim(($r->serie ? ($r->serie.'-') : '').($r->folio ?? '')) ?: '—' }}</td>
            <td>${{ number_format((float)$r->total, 2) }}</td>
            <td><span class="badge status sm {{ $r->estatus }}">{{ ucfirst($r->estatus) }}</span></td>
            <td>{{ optional($r->fecha)->format('Y-m-d H:i') }}</td>
            <td>
              <a class="btn btn--ghost sm" href="{{ route('cliente.facturacion.show', $r->id) }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/>
                </svg>
                Ver
              </a>
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="page-sub">Sin movimientos aún.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection

@push('scripts')
{{-- Loader con fallback local para Chart.js --}}
<script>
(function(){
  const CDN   = "https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js";
  const LOCAL = "{{ asset('vendor/chart.js/chart.umd.min.js') }}";
  function load(src, next){
    const s = document.createElement('script'); s.src = src; s.defer = true;
    s.onload = next; s.onerror = ()=>{ if(src!==LOCAL) load(LOCAL,next); };
    document.head.appendChild(s);
  }
  if(!window.Chart) load(CDN, ()=>document.dispatchEvent(new Event('chartjs:ready')));
  else document.dispatchEvent(new Event('chartjs:ready'));
})();
</script>

<script>
(function(){
  // util DOM
  const $ = (sel,root=document)=>root.querySelector(sel);

  // rutas AJAX (con fallback)
  const kpisUrl   = {!! \Illuminate\Support\Facades\Route::has('cliente.home.kpis')
                    ? json_encode(route('cliente.home.kpis'))
                    : json_encode(url('/cliente/home/kpis')) !!};
  const seriesUrl = {!! \Illuminate\Support\Facades\Route::has('cliente.home.series')
                    ? json_encode(route('cliente.home.series'))
                    : json_encode(url('/cliente/home/series')) !!};
  const listUrl   = {!! \Illuminate\Support\Facades\Route::has('cliente.facturacion.index')
                    ? json_encode(route('cliente.facturacion.index'))
                    : json_encode(url('/cliente/facturacion')) !!};

  // búsqueda Enter -> listado ?q=
  const search = $('.searchbar');
  if (search) {
    search.addEventListener('keydown', (e)=>{
      if(e.key === 'Enter'){
        const q = search.value.trim();
        const url = q ? (listUrl + '?q=' + encodeURIComponent(q)) : listUrl;
        window.location.href = url;
      }
    });
  }

  // ====== Charts ======
  let lineChart = null, barChart = null;

  function rgba(hex,a){
    if(!/^#([A-Fa-f0-9]{3}){1,2}$/.test(hex)) return hex;
    let c=hex.slice(1); if(c.length===3) c=c.split('').map(x=>x+x).join('');
    const n=parseInt(c,16); return `rgba(${(n>>16)&255},${(n>>8)&255},${n&255},${a})`;
  }

  function rebuildCharts(payload){
    const labels = payload?.labels || [];
    const fact   = payload?.series?.line_facturacion || payload?.series?.emitidos_total || [];
    const canc   = payload?.series?.line_cancelados  || [];
    const bars   = payload?.series?.bar_q || [];

    const css   = getComputedStyle(document.documentElement);
    const brand = css.getPropertyValue('--c-brand').trim() || '#2563eb';
    const warn  = css.getPropertyValue('--c-neg').trim()   || '#dc2626';
    const grid  = css.getPropertyValue('--c-line').trim()  || '#e5e7eb';
    const text  = css.getPropertyValue('--c-text').trim()  || '#1f2937';

    const lineCfg = {
      type:'line',
      data:{ labels, datasets:[
        { label:'Cancelados', data:canc, borderColor:warn, backgroundColor:rgba(warn,.08), fill:false, tension:.3, pointRadius:2, borderWidth:1.4 },
        { label:'Facturación', data:fact, borderColor:brand, backgroundColor:rgba(brand,.10), fill:false, tension:.3, pointRadius:2, borderWidth:1.4 }
      ]},
      options:{ responsive:true, maintainAspectRatio:false,
        plugins:{ legend:{ position:'top', labels:{ color:text, boxWidth:10, boxHeight:2, usePointStyle:true, font:{size:11} } }, tooltip:{ mode:'index', intersect:false } },
        interaction:{ mode:'nearest', intersect:false },
        scales:{ x:{ grid:{ color:rgba(grid,.6) }, ticks:{ color:text, font:{size:11} } },
                 y:{ grid:{ color:rgba(grid,.6) }, ticks:{ color:text, font:{size:11} }, beginAtZero:true } }
      }
    };

    const barCfg = {
      type:'bar',
      data:{ labels:['Q1','Q2','Q3','Q4'], datasets:[{
        label:'Periodo', data:bars,
        backgroundColor:[rgba(brand,.18),rgba(brand,.18),rgba(brand,.18),rgba(brand,.18)],
        borderColor:brand, borderWidth:1.2, borderRadius:5
      }]},
      options:{ responsive:true, maintainAspectRatio:false,
        plugins:{ legend:{ display:false } },
        scales:{ x:{ grid:{ display:false }, ticks:{ color:text, font:{size:11} } },
                 y:{ grid:{ color:rgba(grid,.6) }, ticks:{ color:text, font:{size:11} }, beginAtZero:true,
                     suggestedMax: Math.max(10, ...bars, 0) + 5 } }
      }
    };

    const lc = document.getElementById('lineFact');
    const bc = document.getElementById('barQ');
    if (window.Chart) {
      if (lineChart) lineChart.destroy();
      if (barChart)  barChart.destroy();
      if (lc) lineChart = new Chart(lc.getContext('2d'), lineCfg);
      if (bc) barChart  = new Chart(bc.getContext('2d'), barCfg);
    }
  }

  // ====== KPIs ======
  function fmtMoney(n){ return (Number(n||0)).toLocaleString(undefined,{minimumFractionDigits:2, maximumFractionDigits:2}); }
  function fmtInt(n){ return (Number(n||0)).toLocaleString(); }

  function setKpis(k){
    const set = (key, fmt, prefix='')=>{
      const el = document.querySelector(`[data-kpi="${key}"]`);
      if (el) el.textContent = prefix + fmt(k[key]);
    };
    set('total', fmtMoney, '$');
    set('pagados', fmtMoney, '$');
    set('cancelados', fmtMoney, '$');
    set('pendientes', fmtMoney, ''); // se pinta en hint
    const c1 = document.querySelector('[data-kpi="pagados_cnt"]'); if (c1) c1.textContent = fmtInt(k.pagados_cnt);
    const c2 = document.querySelector('[data-kpi="cancelados_cnt"]'); if (c2) c2.textContent = fmtInt(k.cancelados_cnt);
    const v  = document.querySelector('[data-kpi="vencidos"]'); if (v) v.textContent = fmtMoney(k.vencidos);

    const trend = document.querySelector('.kpi.primary .trend');
    if (trend){
      trend.classList.remove('up','down','flat');
      const d = Number(k.delta||0);
      trend.classList.add(d>0?'up':(d<0?'down':'flat'));
      const dEl = document.querySelector('[data-kpi="delta"]'); if (dEl) dEl.textContent = d.toFixed(2);
    }
  }

  // ====== Periodo: mes y año separados ======
  const mes  = document.getElementById('mes');
  const anio = document.getElementById('anio');

  async function refresh(){
    if (!mes || !anio) return;
    const monthStr = `${anio.value}-${mes.value}`; // YYYY-MM
    const qs = `?month=${encodeURIComponent(monthStr)}`;
    try{
      const [sRes,kRes] = await Promise.all([
        fetch(seriesUrl + qs, {headers:{'X-Requested-With':'XMLHttpRequest'}}),
        fetch(kpisUrl   + qs, {headers:{'X-Requested-With':'XMLHttpRequest'}}),
      ]);
      rebuildCharts(await sRes.json());
      setKpis(await kRes.json());
    }catch(e){ console.error('Refresh error', e); }
  }

  if (mes)  mes.addEventListener('change', refresh);
  if (anio) anio.addEventListener('change', refresh);

  // Inicializar charts (con data server-side) cuando Chart.js esté listo
  function bootInitial(){
    const initial = @json($series);
    rebuildCharts(initial);
  }
  if (window.Chart) bootInitial(); else document.addEventListener('chartjs:ready', bootInitial);
})();
</script>
@endpush
