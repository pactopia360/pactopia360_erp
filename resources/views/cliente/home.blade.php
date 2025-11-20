@extends('layouts.cliente')
@section('title','Inicio · Pactopia360')

@php
  // Plan unificado: si viene del summary (admin), úsalo primero
  $summaryPlan = null;
  if (isset($summary) && is_array($summary) && !empty($summary['plan'])) {
      $summaryPlan = strtoupper((string)$summary['plan']);
  }

  $planBase = $plan    ?? 'FREE';
  $plan     = $summaryPlan ?? $planBase;
  $planKey  = strtolower($planKey ?? $plan);

  $razonV   = $razon   ?? (auth('web')->user()->nombre ?? 'Cliente');
  $timbresV = (int)($timbres ?? 0);
  $saldoV   = (float)($saldo ?? 0.0);

  $kEmit    = (float)($kpis['emitidos']   ?? 0);
  $kCanc    = (float)($kpis['cancelados'] ?? 0);
  $kTotal   = (float)($kpis['total']      ?? 0);

  $labelsV  = $series['labels'] ?? [];
  $lineV    = data_get($series, 'series.line_facturacion', data_get($series, 'series.emitidos_total', []));
  $barsV    = data_get($series, 'series.bar_q', [0,0,0,0]);

  $rtKpisJs   = \Illuminate\Support\Facades\Route::has('cliente.home.kpis')
                  ? route('cliente.home.kpis')
                  : (\Illuminate\Support\Facades\Route::has('cliente.kpis') ? route('cliente.kpis') : '');
  $rtSeriesJs = \Illuminate\Support\Facades\Route::has('cliente.home.series')
                  ? route('cliente.home.series')
                  : (\Illuminate\Support\Facades\Route::has('cliente.series') ? route('cliente.series') : '');

  // Variables de verificación de fuente
  $dataSource = ($dataSource ?? 'db');    // 'db' | 'demo' (desde el controlador)
  $isLocal    = (bool) ($isLocal ?? false);
@endphp

@push('styles')
<style>
  @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800;900&display=swap');

  .p360-page{
    --page-pad: 30px; --gap: 16px;
    padding: var(--page-pad);
    min-height: calc(100vh - var(--header,64px) - var(--footer,64px));
    box-sizing: border-box;
    font-family: 'Poppins', var(--font-sans, ui-sans-serif), system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', sans-serif;
  }
  @media (max-width: 640px){ .p360-page{ --page-pad: 18px } }

  .home-grid{ display:grid; grid-template-columns: 340px 1fr; gap:18px; align-items:start }
  @media (max-width:1100px){ .home-grid{ grid-template-columns: 1fr } }

  .card{
    background:var(--card); border:1px solid var(--bd); border-radius:16px; padding:16px;
    box-shadow:0 4px 12px rgba(0,0,0,.05);
  }
  .card .hd{ font-weight:900; margin-bottom:8px }
  .welcome{ background: linear-gradient(180deg, color-mix(in oklab, var(--brand) 8%, transparent), transparent); }

  .kpis{ display:grid; grid-template-columns: 1fr; gap:12px }
  .kpi{ border:1px solid #fecaca; background:linear-gradient(180deg,#fff1f2,#fff); border-radius:12px; padding:12px }
  html.theme-dark .kpi{ background:linear-gradient(180deg,#312e81,#1e1b4b); border-color:rgba(255,255,255,.12) }
  .kpi span{ display:block; font-size:12px; color:var(--muted,#6b7280); margin-bottom:6px; font-weight:700 }
  .kpi strong{ display:block; font:900 22px/1.1 Poppins, system-ui }

  .chart{ width:100%; height:340px }

  .quick ul{margin:0; padding-left:16px}
  .quick a{ color:inherit; font-weight:700; text-decoration:none }
  .quick a:hover{ text-decoration:underline }

  .col-right{ display:grid; gap:12px }
  .muted{ color:var(--muted,#6b7280) }

  /* Badge de fuente (solo visible en local) */
  .src-badge{
    display:inline-flex; align-items:center; gap:6px;
    font-weight:800; font-size:11px; letter-spacing:.2px;
    padding:5px 8px; border-radius:999px; border:1px solid;
  }
  .src-badge.db{ color:#065f46; border-color:#10b981; background:color-mix(in oklab, #10b981 12%, transparent) }
  .src-badge.demo{ color:#7c2d12; border-color:#f59e0b; background:color-mix(in oklab, #f59e0b 14%, transparent) }
</style>
@endpush

@section('content')
<div class="p360-page">
  <div class="home-grid">
    <div class="col-left" style="display:grid; gap:12px">
      <div class="card welcome">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px">
          <div style="font-weight:900">Bienvenido, {{ $razonV }}</div>

          {{-- Badge de fuente, solo en entornos locales --}}
          @if($isLocal)
            <span class="src-badge {{ $dataSource === 'db' ? 'db' : 'demo' }}"
                  title="Fuente de datos para el mes actual">
              Fuente: {{ $dataSource === 'db' ? 'Base de datos' : 'DEMO' }}
            </span>
          @endif
        </div>
        <div class="muted" style="margin-top:6px">
          Tu plan actual es <strong>{{ strtoupper($plan) }}</strong>.
          Tienes {{ number_format($timbresV) }} timbres disponibles y un saldo de
          <strong>${{ number_format($saldoV, 2) }}</strong> MXN.
        </div>
      </div>

      <div class="kpis">
        <div class="kpi"><span>Emitidos (mes actual)</span><strong id="kpi-em">${{ number_format($kEmit,2) }}</strong></div>
        <div class="kpi"><span>Cancelados</span><strong id="kpi-ca">${{ number_format($kCanc,2) }}</strong></div>
        <div class="kpi"><span>Total mensual</span><strong id="kpi-to">${{ number_format($kTotal,2) }}</strong></div>
      </div>

      <div class="card quick">
        <div class="hd">Accesos rápidos</div>
        <ul>
          <li>
            <a href="{{ Route::has('cliente.sat.descargas.index')
                          ? route('cliente.sat.descargas.index')
                          : (Route::has('cliente.sat.index') ? route('cliente.sat.index') : '#') }}">
              Descargas SAT
            </a>
          </li>
          <li><a href="{{ Route::has('cliente.facturacion.index') ? route('cliente.facturacion.index') : '#' }}">Facturación</a></li>
          <li><a href="{{ Route::has('cliente.perfil') ? route('cliente.perfil') : url('/cliente/perfil') }}">Perfil y configuración</a></li>
        </ul>
      </div>
    </div>

    <div class="col-right">
      <div class="card">
        <div class="hd">Facturación del mes</div>
        <div id="chart-lines" class="chart"></div>
      </div>
      <div class="card">
        <div class="hd">Comparativo semanal</div>
        <div id="chart-bars" class="chart"></div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
  <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
  <script>
  document.addEventListener('DOMContentLoaded', ()=>{
    const theme = document.documentElement.getAttribute('data-theme') || 'light';
    const fore  = theme==='dark' ? '#e5e7eb' : '#0f172a';
    const gridC = theme==='dark' ? 'rgba(255,255,255,.12)' : 'rgba(0,0,0,.08)';

    const labels   = @json($labelsV);
    const lineData = @json($lineV);
    const barsData = @json($barsV);

    if (window.ApexCharts) {
      window.chartLines = new ApexCharts(document.querySelector('#chart-lines'), {
        chart:{ type:'area', height:340, foreColor:fore, toolbar:{show:false} },
        stroke:{ curve:'smooth', width:3 },
        series:[{ name:'Emitidos', data: lineData }],
        xaxis:{ categories: labels, labels:{ style:{ colors: fore } } },
        yaxis:{ labels:{ style:{ colors: fore } } },
        colors:['#2563eb'],
        fill:{ type:'gradient', gradient:{shadeIntensity:.5,opacityFrom:.5,opacityTo:.1,stops:[0,90,100]} },
        grid:{ borderColor:gridC }
      });
      chartLines.render();

      window.chartBars = new ApexCharts(document.querySelector('#chart-bars'), {
        chart:{ type:'bar', height:340, foreColor:fore, toolbar:{show:false} },
        plotOptions:{ bar:{ columnWidth:'45%', borderRadius:6 } },
        series:[{ name:'Emitidos por semana', data: barsData }],
        xaxis:{ categories:['Sem 1','Sem 2','Sem 3','Sem 4'], labels:{ style:{ colors: fore } } },
        yaxis:{ labels:{ style:{ colors: fore } } },
        colors:['#E11D48'],
        grid:{ borderColor:gridC }
      });
      chartBars.render();
    }

    // ===== Auto refresh (30s) con logging de fuente =====
    const rtKpis   = @json($rtKpisJs);
    const rtSeries = @json($rtSeriesJs);
    const csrf     = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const isLocal  = @json($isLocal);

    async function safeJson(res){
      const ct = res.headers.get('content-type') || '';
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      if (!ct.includes('application/json')) {
        const txt = await res.text(); throw new Error(`Respuesta no JSON: ${txt.slice(0,120)}…`);
      }
      return res.json();
    }

    async function refreshData(){
      try{
        if(!rtKpis || !rtSeries) return;
        const [kpiRes, serieRes] = await Promise.all([
          fetch(rtKpis,   { headers:{'X-CSRF-TOKEN': csrf} }),
          fetch(rtSeries, { headers:{'X-CSRF-TOKEN': csrf} })
        ]);
        const k = await safeJson(kpiRes);
        const s = await safeJson(serieRes);

        document.getElementById('kpi-em').textContent = `$${Number(k.emitidos||0).toFixed(2)}`;
        document.getElementById('kpi-ca').textContent = `$${Number(k.cancelados||0).toFixed(2)}`;
        document.getElementById('kpi-to').textContent = `$${Number(k.total||0).toFixed(2)}`;

        const line = (s.series && (s.series.line_facturacion || s.series.emitidos_total)) ? (s.series.line_facturacion || s.series.emitidos_total) : [];
        const bars = (s.series && s.series.bar_q) ? s.series.bar_q : [0,0,0,0];

        if(window.chartLines && window.chartBars){
          chartLines.updateOptions({ xaxis:{ categories: s.labels || [] } });
          chartLines.updateSeries([{ name:'Emitidos', data: line }]);
          chartBars.updateSeries([{ name:'Emitidos por semana', data: bars }]);
        }

        // Log de verificación en local
        if (isLocal) {
          console.info('[P360][HOME] Fuente KPIs:', k.source || 'db', 'rows:', k.row_count ?? 'n/a');
          console.info('[P360][HOME] Fuente Series:', s.source || 'db', 'rows:', s.row_count ?? 'n/a');
          // Actualiza badge si existe
          const badge = document.querySelector('.src-badge');
          if (badge && (k.source || s.source)) {
            const src = (k.source === 'demo' || s.source === 'demo') ? 'demo' : 'db';
            badge.textContent = 'Fuente: ' + (src === 'demo' ? 'DEMO' : 'Base de datos');
            badge.classList.toggle('demo', src === 'demo');
            badge.classList.toggle('db', src === 'db');
          }
        }
      }catch(e){
        console.warn('AutoRefresh error:', e);
      }
    }
    setInterval(refreshData, 30000);
  });
  </script>
@endpush
