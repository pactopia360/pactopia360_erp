@extends('layouts.admin')

@section('title','Home')

@push('styles')
  @php
    $HOME_CSS_PATH = public_path('assets/admin/css/home.css');
    $HOME_CSS_URL  = asset('assets/admin/css/home.css') . (is_file($HOME_CSS_PATH) ? ('?v='.filemtime($HOME_CSS_PATH)) : '');
  @endphp
  <link id="css-home" rel="stylesheet" href="{{ $HOME_CSS_URL }}">
  <style>
    /* Overlay ligero (sigue igual) */
    #loadingOverlay{position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.25);z-index:9999}
    #loadingOverlay .spinner{width:56px;height:56px;border:6px solid #fff;border-top-color:transparent;border-radius:50%;animation:spin 1s linear infinite}
    @keyframes spin{to{transform:rotate(360deg)}}
    #alerts{position:fixed;right:12px;top:12px;z-index:10000;display:flex;flex-direction:column;gap:8px}
    .alert{background:#111827;color:#fff;padding:10px 12px;border-radius:8px;box-shadow:0 6px 18px rgba(0,0,0,.2)}
    .alert-error{background:#b91c1c}
    .alert .alert-close{background:transparent;border:0;color:#fff;font-size:16px;margin-left:8px;cursor:pointer}
  </style>
@endpush

@section('content')
  {{-- Marcador opcional para loaders globales (no afecta si no lo usas) --}}
  <i hidden data-require-css="{{ $HOME_CSS_URL }}"></i>
<div class="page"
     data-stats-url="{{ route('admin.home.stats') }}"
    data-income-url="{{ route('admin.home.incomeMonth', ['ym'=>'__YM__']) }}">

  <div id="alerts" aria-live="polite" aria-atomic="true"></div>
  <div id="loadingOverlay" aria-hidden="true"><div class="spinner" role="status" aria-label="Cargando"></div></div>

  {{-- Título + filtros --}}
  <section class="page-card" aria-labelledby="homeTitle">
    <div class="page-title">
      <span class="ico" aria-hidden="true">📊</span>
      <div>
        <h2 id="homeTitle">Panel Administrativo</h2>
        <p>Resumen general y métricas clave</p>
      </div>
    </div>

    <div class="filters-bar" role="group" aria-label="Filtros del panel">
      <div class="filters-left">
        <label for="fMonths" class="lbl">Periodo</label>
        <select id="fMonths" class="select" aria-label="Periodo">
          <option value="3">3 meses</option>
          <option value="6">6 meses</option>
          <option value="12" selected>12 meses</option>
          <option value="24">24 meses</option>
        </select>

        <label for="fPlan" class="lbl">Plan</label>
        <select id="fPlan" class="select" aria-label="Plan">
          <option value="all" selected>Todos</option>
        </select>

        <button id="btnApply" class="btn btn-primary" type="button">Aplicar</button>
      </div>

      <div class="filters-right">
        <button id="btnResetZoom" class="btn" type="button">Reset zoom</button>
        <button id="btnDownloadIncomePNG" class="btn" type="button">PNG Ingresos</button>
        <button id="btnDownloadIncomeCSV" class="btn" type="button">CSV Ingresos</button>
      </div>
    </div>

    {{-- KPIs --}}
    <div class="kpi-grid" role="list">
      <div class="kpi-box kpi-blue" role="listitem">
        <div class="kpi-label">Clientes</div>
        <div class="kpi-value" id="kpi_clientes">-</div>
      </div>
      <div class="kpi-box kpi-green" role="listitem">
        <div class="kpi-label">Activos</div>
        <div class="kpi-value" id="kpi_activos">-</div>
      </div>
      <div class="kpi-box kpi-red" role="listitem">
        <div class="kpi-label">Inactivos</div>
        <div class="kpi-value" id="kpi_inactivos">-</div>
      </div>
      <div class="kpi-box kpi-yellow" role="listitem">
        <div class="kpi-label">Nuevos este mes</div>
        <div class="kpi-value" id="kpi_nuevos">-</div>
      </div>
      <div class="kpi-box" role="listitem">
        <div class="kpi-label">Pend. Pago</div>
        <div class="kpi-value" id="kpi_pendientes">-</div>
      </div>
      <div class="kpi-box" role="listitem">
        <div class="kpi-label">Premium</div>
        <div class="kpi-value" id="kpi_premium">-</div>
      </div>
      <div class="kpi-box" role="listitem">
        <div class="kpi-label">Timbres Usados</div>
        <div class="kpi-value" id="kpi_timbres">-</div>
      </div>
      <div class="kpi-box" role="listitem">
        <div class="kpi-label">Ingresos Mes</div>
        <div class="kpi-value" id="kpi_ingresos">-</div>
      </div>
    </div>
  </section>

  {{-- GRID auto-encajable de gráficas (sin huecos) --}}
  <section class="charts-grid" aria-label="Métricas">
    {{-- 1) Ingresos Mensuales (destacada a 2 columnas) --}}
    <article class="chart-card" data-span="2" aria-labelledby="chartIncomeTitle">
      <div class="chart-head">
        <div class="widget-title" id="chartIncomeTitle">
          <span aria-hidden="true">🧾</span> <span class="t">Ingresos Mensuales</span>
        </div>
        <div class="chart-tools">
          <div class="spacer"></div>
          <button class="btn btn-secondary" id="btnIncomeResetZoom" type="button">Reset zoom</button>
          <button class="btn btn-secondary" id="btnIncomePNG" type="button">PNG</button>
          <button class="btn btn-secondary" id="btnIncomeCSV" type="button">CSV</button>
        </div>
      </div>
      <div class="chart-wrap"><canvas id="chartIncome"></canvas></div>
    </article>

    {{-- 2) Timbres por mes --}}
    <article class="chart-card" aria-labelledby="chartStampsTitle">
      <div class="chart-head">
        <div class="widget-title" id="chartStampsTitle">
          <span aria-hidden="true">🧮</span> <span class="t">Timbres por mes</span>
        </div>
        <div class="chart-tools">
          <div class="spacer"></div>
          <button class="btn btn-secondary" id="btnStampsResetZoom" type="button">Reset zoom</button>
          <button class="btn btn-secondary" id="btnStampsPNG" type="button">PNG</button>
          <button class="btn btn-secondary" id="btnStampsCSV" type="button">CSV</button>
        </div>
      </div>
      <div class="chart-wrap"><canvas id="chartStamps"></canvas></div>
    </article>

    {{-- 3) Clientes por plan --}}
    <article class="chart-card" aria-labelledby="chartPlansTitle">
      <div class="chart-head">
        <div class="widget-title" id="chartPlansTitle">
          <span aria-hidden="true">⭐</span> <span class="t">Clientes por Plan</span>
        </div>
        <div class="chart-tools">
          <div class="spacer"></div>
          <button class="btn btn-secondary" id="btnPlansPNG" type="button">PNG</button>
          <button class="btn btn-secondary" id="btnPlansCSV" type="button">CSV</button>
        </div>
      </div>
      <div class="chart-wrap"><canvas id="chartPlans"></canvas></div>
    </article>

    {{-- 4) Ingresos por plan (mensual) --}}
    <article class="chart-card" aria-labelledby="chartIncomePlanTitle">
      <div class="chart-head">
        <div class="widget-title" id="chartIncomePlanTitle">
          <span aria-hidden="true">🧷</span> <span class="t">Ingresos por Plan (mensual)</span>
        </div>
        <div class="chart-tools">
          <div class="spacer"></div>
          <button class="btn btn-secondary" id="btnIncomePlanPNG" type="button">PNG</button>
          <button class="btn btn-secondary" id="btnIncomePlanCSV" type="button">CSV</button>
        </div>
      </div>
      <div class="chart-wrap"><canvas id="chartIncomePlan"></canvas></div>
    </article>

    {{-- 5) Nuevos clientes por mes --}}
    <article class="chart-card" aria-labelledby="chartNewClientsTitle">
      <div class="chart-head">
        <div class="widget-title" id="chartNewClientsTitle">
          <span aria-hidden="true">🆕</span> <span class="t">Nuevos clientes por mes</span>
        </div>
        <div class="chart-tools">
          <div class="spacer"></div>
          <button class="btn btn-secondary" id="btnNewClientsPNG" type="button">PNG</button>
          <button class="btn btn-secondary" id="btnNewClientsCSV" type="button">CSV</button>
        </div>
      </div>
      <div class="chart-wrap"><canvas id="chartNewClients"></canvas></div>
    </article>

    {{-- 6) Top clientes por ingresos --}}
    <article class="chart-card" aria-labelledby="chartTopClientsTitle">
      <div class="chart-head">
        <div class="widget-title" id="chartTopClientsTitle">
          <span aria-hidden="true">🏆</span> <span class="t">Top clientes por ingresos</span>
        </div>
        <div class="chart-tools">
          <div class="spacer"></div>
          <button class="btn btn-secondary" id="btnTopClientsPNG" type="button">PNG</button>
          <button class="btn btn-secondary" id="btnTopClientsCSV" type="button">CSV</button>
        </div>
      </div>
      <div class="chart-wrap"><canvas id="chartTopClients"></canvas></div>
    </article>

    {{-- 7) Correlación ingresos vs timbres --}}
    <article class="chart-card" aria-labelledby="chartScatterTitle">
      <div class="chart-head">
        <div class="widget-title" id="chartScatterTitle">
          <span aria-hidden="true">🔗</span> <span class="t">Correlación: Ingresos vs Timbres</span>
        </div>
        <div class="chart-tools">
          <div class="spacer"></div>
          <button class="btn btn-secondary" id="btnScatterPNG" type="button">PNG</button>
          <button class="btn btn-secondary" id="btnScatterCSV" type="button">CSV</button>
        </div>
      </div>
      <div class="chart-wrap"><canvas id="chartScatter"></canvas></div>
    </article>

    {{-- 8) Ingresos acumulados YTD (1 col) --}}
    <article class="chart-card" aria-labelledby="chartYTDTitle">
      <div class="chart-head">
        <div class="widget-title" id="chartYTDTitle">
          <span aria-hidden="true">📈</span> <span class="t">Ingresos acumulados YTD</span>
        </div>
      </div>
      <div class="chart-wrap"><canvas id="chartYTD"></canvas></div>
    </article>

    {{-- 9) Comparativa diaria (2 columnas) --}}
    <article class="chart-card" data-span="2" aria-labelledby="chartDailyTitle">
      <div class="chart-head">
        <div class="widget-title" id="chartDailyTitle">
          <span aria-hidden="true">📅</span> <span class="t">Comparativa diaria (mes actual)</span>
        </div>
      </div>
      <div class="chart-wrap"><canvas id="chartDaily"></canvas></div>
    </article>

    {{-- 10) Variación mensual (%) --}}
    <article class="chart-card" aria-labelledby="chartMoMTitle">
      <div class="chart-head">
        <div class="widget-title" id="chartMoMTitle">
          <span aria-hidden="true">📊</span> <span class="t">Variación mensual (%)</span>
        </div>
      </div>
      <div class="chart-wrap"><canvas id="chartMoM"></canvas></div>
    </article>
  </section>

  {{-- Tabla: Clientes Activos --}}
  <section class="table-card" aria-labelledby="tblClientsTitle">
    <div class="table-head">
      <div class="widget-title" id="tblClientsTitle"><span aria-hidden="true">👥</span> Clientes Activos</div>
      <div class="table-actions">
        <div class="search">
          <span aria-hidden="true">🔍</span>
          <input type="text" id="searchClients" placeholder="Buscar..." aria-label="Buscar cliente">
        </div>
        <button id="btnExport" class="btn-export" type="button">⬇ Exportar</button>
      </div>
    </div>

    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>#</th><th>Empresa</th><th>RFC</th><th>Timbres</th><th>Última actividad</th><th>Estado</th>
          </tr>
        </thead>
        <tbody id="clientsTbody">
          <tr id="clientsEmptyRow"><td colspan="6" class="empty">Sin datos</td></tr>
        </tbody>
      </table>
    </div>
  </section>
</div>
@endsection

{{-- Modal drill-down ingresos --}}
<div id="modalIncome" class="modal" aria-hidden="true">
  <div class="modal-backdrop" data-close></div>
  <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="modalIncomeTitle">
    <div class="modal-head">
      <div>
        <div class="modal-title" id="modalIncomeTitle">Ingresos — <span id="modalIncomeMonth">YYYY-MM</span></div>
        <div class="modal-sub">Pagos del mes seleccionado</div>
      </div>
      <button class="modal-close" type="button" data-close aria-label="Cerrar">✖</button>
    </div>
    <div class="modal-tools">
      <div class="search">
        <span aria-hidden="true">🔍</span>
        <input type="text" id="incomeModalSearch" placeholder="Buscar cliente / RFC / referencia..." aria-label="Buscar en pagos">
      </div>
      <div class="spacer"></div>
      <button class="btn" id="incomeModalExport" type="button">Exportar CSV</button>
    </div>
    <div class="modal-body">
      <div class="table-wrap">
        <table class="table" id="incomeModalTable">
          <thead>
            <tr>
              <th data-sort="fecha">Fecha</th>
              <th data-sort="cliente">Cliente</th>
              <th data-sort="rfc">RFC</th>
              <th data-sort="metodo">Método</th>
              <th data-sort="estado">Estado</th>
              <th data-sort="monto">Monto</th>
            </tr>
          </thead>
          <tbody id="incomeModalTbody">
            <tr id="incomeModalEmpty"><td colspan="6" class="empty">Sin pagos</td></tr>
          </tbody>
          <tfoot>
            <tr>
              <th colspan="5" style="text-align:right">Total</th>
              <th id="incomeModalTotal">-</th>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
</div>

{{-- HUD de Logs (fijo abajo) --}}
<div id="devHud" aria-live="polite" aria-atomic="false" data-open="false">
  <button id="devHudToggle" class="hud-toggle" title="Mostrar/Ocultar logs">🧪 Logs</button>
  <div class="hud-body">
    <div class="hud-status">
      <span class="badge" id="hudChartJs">Chart.js: —</span>
      <span class="badge" id="hudZoom">Zoom: —</span>
      <span class="badge" id="hudData">Datos: —</span>
      <span class="badge" id="hudErrors">Errores: 0</span>
    </div>
    <pre id="devHudLogs" class="hud-logs" tabindex="0"></pre>
    <div class="hud-actions">
      <button id="hudCopy" class="btn btn-secondary">Copiar</button>
      <button id="hudClear" class="btn">Limpiar</button>
    </div>
  </div>
</div>

@push('scripts')
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom@2" defer></script>
  <script src="{{ asset('assets/admin/js/home.js') }}" defer></script>
  <script>
    (function ensureHomeCSS(){
      var id   = 'css-home';
      var href = @json($HOME_CSS_URL);
      function inject(){
        if (!document.getElementById(id)) {
          var l = document.createElement('link');
          l.id  = id; l.rel = 'stylesheet'; l.href = href;
          document.head.appendChild(l);
          try { console.debug('[P360][Home] CSS inyectado por PJAX'); } catch(_){}
        }
      }
      inject();
      addEventListener('p360:pjax:after', inject);
    })();
  </script>
@endpush

