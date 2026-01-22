{{-- C:\wamp64\www\pactopia360_erp\resources\views\admin\home.blade.php --}}
{{-- P360 Admin · Home/Dashboard · v5.3 (SOT)
     FIX:
     - ✅ Agrega data-compare-url para Daily chart (admin.home.compare)
     - index.js NO module, sin imports
     - Chart.js con fallback a CDN si no existe archivo local
--}}

@extends('layouts.admin')

@section('title','Dashboard · Admin')
@section('pageClass','p360-admin-home')
@section('contentLayout','full')

@php
  use Illuminate\Support\Facades\Route;

  // =======================
  // URLs (robustas)
  // =======================
  $statsUrl = null;

  if (Route::has('admin.home.stats')) {
    $statsUrl = route('admin.home.stats');
  } elseif (Route::has('admin.home.stats.index')) {
    $statsUrl = route('admin.home.stats.index');
  } elseif (Route::has('admin.dashboard.stats')) {
    $statsUrl = route('admin.dashboard.stats');
  }

  // Drilldown ingresos por mes (modal)
  $incomeMonthTpl = '';
  if (Route::has('admin.home.incomeMonth')) {
    $incomeMonthTpl = route('admin.home.incomeMonth', ['ym' => '__YM__']);
  } elseif (Route::has('admin.home.income_month')) {
    $incomeMonthTpl = route('admin.home.income_month', ['ym' => '__YM__']);
  }

  // ✅ Daily compare (línea diaria vs promedio 2 meses prev)
  $compareTpl = '';
  if (Route::has('admin.home.compare')) {
    $compareTpl = route('admin.home.compare', ['ym' => '__YM__']);
  } elseif (Route::has('admin.home.compareMonth')) {
    $compareTpl = route('admin.home.compareMonth', ['ym' => '__YM__']);
  }

  // =======================
  // Assets
  // =======================
  $minSize = 16;

  $HOME_JS_ABS = public_path('assets/admin/js/home/index.js');
  $HOME_JS_URL = is_file($HOME_JS_ABS) && filesize($HOME_JS_ABS) > $minSize
      ? asset('assets/admin/js/home/index.js').'?v='.filemtime($HOME_JS_ABS)
      : asset('assets/admin/js/home/index.js');

  $HOME_CSS_ABS = public_path('assets/admin/css/home.css');
  $HOME_CSS_URL = is_file($HOME_CSS_ABS) && filesize($HOME_CSS_ABS) > $minSize
      ? asset('assets/admin/css/home.css').'?v='.filemtime($HOME_CSS_ABS)
      : asset('assets/admin/css/home.css');

  // Chart.js local (si existe) + fallback CDN
  $CHART_ABS = public_path('assets/vendor/chartjs/chart.umd.min.js');
  $CHART_URL = (is_file($CHART_ABS) && filesize($CHART_ABS) > $minSize)
      ? asset('assets/vendor/chartjs/chart.umd.min.js').'?v='.filemtime($CHART_ABS)
      : null;

  // CDN fallback (si no hay local)
  $CHART_CDN = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';

  $showHud = (bool) config('app.debug', false) && request()->boolean('hud', false);
@endphp

@push('styles')
  <link rel="stylesheet" href="{{ $HOME_CSS_URL }}">

  <style>
    .home-debug {
      margin: 12px 0 0;
      padding: 10px 12px;
      border: 1px solid rgba(239,68,68,.28);
      background: rgba(239,68,68,.08);
      border-radius: 12px;
      color: rgba(17,24,39,.92);
      font-weight: 700;
    }
    .home-debug code{ font-weight: 900; }

    #devHud{
      position: fixed; right: 14px; bottom: 14px; z-index: 2050;
      width: min(560px, 92vw);
      border-radius: 16px; overflow: hidden;
      box-shadow: var(--shadow-2);
      background: rgba(17,24,39,.92); color:#fff;
      border: 1px solid rgba(255,255,255,.10);
      backdrop-filter: blur(8px) saturate(140%);
    }
    #devHudHead{
      display:flex; align-items:center; justify-content:space-between; gap:10px;
      padding: 10px 12px; border-bottom: 1px solid rgba(255,255,255,.10);
      cursor: pointer;
    }
    #devHudTitle{ font-weight: 900; font-size: 12px; }
    #devHudBar{
      display:flex; gap:8px; padding: 10px 12px;
      border-bottom: 1px solid rgba(255,255,255,.10); flex-wrap:wrap;
    }
    #devHudLogs{
      margin:0; padding: 10px 12px; max-height: 240px; overflow:auto;
      font-size: 12px; line-height: 1.35; white-space: pre-wrap;
    }
    #devHud[data-open="false"] #devHudLogs{ display:none; }
    #devHud[data-open="false"] #hudCopy,
    #devHud[data-open="false"] #hudClear{ display:none; }
    #devHud[data-open="false"] #devHudBar{ display:none; }
    #devHud[data-open="false"]{ width:auto; }
  </style>
@endpush

@section('page-header')
  <div style="padding:12px 14px; display:flex; align-items:center; justify-content:space-between; gap:12px;">
    <div>
      <div style="font-weight:900; letter-spacing:.2px;">Panel de control</div>
      <div class="muted">Resumen de ingresos, timbres, planos y actividad.</div>
    </div>
    <div style="display:flex; gap:10px; align-items:center;">
      <button class="btn" type="button" onclick="window.P360 && window.P360.toggleTheme && window.P360.toggleTheme()">Tema</button>
      <button class="btn" type="button" onclick="window.P360 && window.P360.openCmd && window.P360.openCmd()">Comandos</button>
    </div>
  </div>
@endsection

@section('content')
  <div class="page"
       data-stats-url="{{ $statsUrl ?? '' }}"
       data-income-url="{{ $incomeMonthTpl }}"
       data-compare-url="{{ $compareTpl }}"
       aria-busy="false">

    <div id="homeDebug" class="home-debug" style="display:none;"></div>

    <div id="loadingOverlay" aria-hidden="true"><div class="spinner"></div></div>

    <section class="page-card">
      <div class="filters-bar">
        <div class="filters-left">
          <div class="widget-title"><span class="t">Filtros</span></div>
          <div class="muted">Aplica rangos y agrupa por periodo</div>
        </div>
        <div class="filters-right">
          <div class="btn-group">
            <button id="btnApply" class="btn btn-primary" type="button">Aplicar</button>
            <button id="btnReset" class="btn" type="button">Reiniciar</button>
            <button id="btnAbort" class="btn" type="button">Cancelar</button>
          </div>
        </div>
      </div>

      <div class="filters-bar" style="border-bottom:0; padding-bottom:0; margin-bottom:0;">
        <div class="filters-left">
          <span class="lbl">Desde</span>
          <input id="fFrom" type="month" value="" class="select" aria-label="Desde">

          <span class="lbl">Hasta</span>
          <input id="fTo" type="month" value="" class="select" aria-label="Hasta">

          <span class="lbl">Alcance</span>
          <select id="fScope" class="select" aria-label="Scope">
            <option value="paid">Pagado</option>
            <option value="issued">Emitido</option>
            <option value="all">Todo</option>
          </select>

          <span class="lbl">Agrupar</span>
          <select id="fGroup" class="select" aria-label="Agrupar">
            <option value="month">Mes</option>
            <option value="week">Semana</option>
            <option value="day">Día</option>
          </select>
        </div>

        <div class="filters-right">
          <div class="muted">Nota: si el backend no soporta un filtro, se ignora</div>
        </div>
      </div>

      <div class="kpi-grid" style="margin-top:12px;">
        <div class="kpi-box kpi-green">
          <div class="kpi-label">Ingresos</div>
          <div class="kpi-value" id="kpiIncome">—</div>
        </div>
        <div class="kpi-box kpi-blue">
          <div class="kpi-label">Timbres</div>
          <div class="kpi-value" id="kpiStamps">—</div>
        </div>
        <div class="kpi-box kpi-yellow">
          <div class="kpi-label">Clientes</div>
          <div class="kpi-value" id="kpiClients">—</div>
        </div>
        <div class="kpi-box kpi-blue">
          <div class="kpi-label">ARPA / Ticket</div>
          <div class="kpi-value" id="kpiArpa">—</div>
        </div>
      </div>
    </section>

    <section class="charts-grid" aria-label="Gráficas del dashboard">
      <article class="chart-card" data-span="2">
        <div class="chart-head sticky">
          <div class="widget-title"><span class="t">Ingresos</span></div>
          <div class="muted">Haga clic en un mes para ver el desglose (si aplica).</div>
        </div>
        <div class="chart-wrap"><canvas id="chartIncome"></canvas></div>
      </article>

      <article class="chart-card">
        <div class="chart-head sticky">
          <div class="widget-title"><span class="t">Acumulado</span></div>
          <div class="muted">Se calcula localmente.</div>
        </div>
        <div class="chart-wrap"><canvas id="chartYTD"></canvas></div>
      </article>

      <article class="chart-card">
        <div class="chart-head sticky">
          <div class="widget-title"><span class="t">Timbres</span></div>
          <div class="muted">Volumen por período</div>
        </div>
        <div class="chart-wrap"><canvas id="chartStamps"></canvas></div>
      </article>

      <article class="chart-card">
        <div class="chart-head sticky">
          <div class="widget-title"><span class="t">Planes</span></div>
          <div class="muted">Distribución actual.</div>
        </div>
        <div class="chart-wrap"><canvas id="chartPlans"></canvas></div>
      </article>

      <article class="chart-card">
        <div class="chart-head sticky">
          <div class="widget-title"><span class="t">Diario</span></div>
          <div class="muted">Comparado con el promedio de los 2 meses anteriores</div>
        </div>
        <div class="chart-wrap"><canvas id="chartDaily"></canvas></div>
      </article>
    </section>

    <section class="table-card" style="margin-top: var(--h-gap2);">
      <div class="table-head">
        <div class="widget-title"><span class="t">Ingresos por mes</span></div>
      </div>
      <div class="table-wrap">
        <table class="table striped hover" id="tblIncome">
          <thead>
            <tr>
              <th>Mes</th>
              <th>Ingresos</th>
              <th>Pagos</th>
              <th>Ticket prom.</th>
            </tr>
          </thead>
          <tbody>
            <tr><td class="empty" colspan="4">Cargando…</td></tr>
          </tbody>
        </table>
      </div>
    </section>

    <section class="table-card" style="margin-top: var(--h-gap2);">
      <div class="table-head">
        <div class="widget-title"><span class="t">Clientes</span></div>
      </div>
      <div class="table-wrap">
        <table class="table striped hover" id="tblClients">
          <thead>
            <tr>
              <th>Cliente</th>
              <th>Plan / Estado</th>
              <th>Ingresos</th>
              <th>Timbres</th>
            </tr>
          </thead>
          <tbody>
            <tr><td class="empty" colspan="4">Cargando…</td></tr>
          </tbody>
        </table>
      </div>
    </section>

    @if($showHud)
      <div id="devHud" data-open="false" aria-label="HUD de desarrollo">
        <div id="devHudHead">
          <div id="devHudTitle">DEV HUD (click para abrir/cerrar)</div>
        </div>
        <div id="devHudBar">
          <button id="hudCopy" type="button" class="btn">Copiar</button>
          <button id="hudClear" type="button" class="btn">Limpiar</button>
        </div>
        <pre id="devHudLogs"></pre>
      </div>
    @endif

  </div>
@endsection

@push('scripts')
  {{-- Chart.js (local si existe; si no, CDN) --}}
  @if($CHART_URL)
    <script src="{{ $CHART_URL }}"></script>
  @else
    <script src="{{ $CHART_CDN }}"></script>
  @endif

  {{-- JS Home (NO module) --}}
  <script src="{{ $HOME_JS_URL }}"></script>
@endpush
