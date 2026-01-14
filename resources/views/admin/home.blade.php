{{-- C:\wamp64\www\pactopia360_erp\resources\views\admin\home.blade.php --}}
@extends('layouts.admin')
@section('title','Panel Administrativo')
@section('pageClass','p360-admin-home')


{{-- Encabezado de la página (sólo UNO) --}}
@section('page-header')
  <div class="d-flex align-items-center gap-2">
    <span style="font-size:22px">📊</span>
    <div>
      <h1 class="m-0" style="font:700 18px/1.2 system-ui">Panel de Administración</h1>
      <small class="text-muted">Resumen general y métricas clave</small>
    </div>
  </div>
@endsection

@push('styles')
  @php
    $HOME_CSS_ABS = public_path('assets/admin/css/home.css');
    $HOME_CSS_URL = asset('assets/admin/css/home.css') . (is_file($HOME_CSS_ABS) ? ('?v='.filemtime($HOME_CSS_ABS)) : '');
  @endphp
  <link id="css-home" rel="stylesheet" href="{{ $HOME_CSS_URL }}">
@endpush

@section('content')
  <i hidden data-require-css="{{ $HOME_CSS_URL }}"></i>

 <div class="page"
     style="width:100%;max-width:none"
     data-stats-url="{{ route('admin.home.stats') }}"
     data-income-url="{{ route('admin.home.incomeMonth', ['ym'=>'__YM__']) }}">


    <div id="alerts"></div>
    <div id="loadingOverlay" aria-hidden="true">
      <div class="spinner" role="status" aria-label="Cargando"></div>
    </div>

    {{-- Card: Filtros + KPIs (SIN título duplicado) --}}
    <section class="page-card" aria-label="Filtros y KPIs">
      <div class="filters-bar" role="group" aria-label="Filtros del panel">
        <div class="filters-left">
          <label for="fMonths" class="lbl">Periodo</label>
          <select id="fMonths" class="select">
            <option value="3">3 meses</option>
            <option value="6">6 meses</option>
            <option value="12" selected>12 meses</option>
            <option value="24">24 meses</option>
          </select>

          <label for="fPlan" class="lbl">Plan</label>
          <select id="fPlan" class="select">
            <option value="all" selected>Todos</option>
          </select>

          <button id="btnApply" class="btn btn-primary" type="button">Aplicar</button>
        </div>

        <div class="filters-right btn-group">
          <button id="btnResetZoom" class="btn" type="button">Reset zoom</button>
          <button id="btnDownloadIncomePNG" class="btn" type="button">PNG Ingresos</button>
          <button id="btnDownloadIncomeCSV" class="btn" type="button">CSV Ingresos</button>
        </div>
      </div>

      <div class="kpi-grid" role="list">
        <div class="kpi-box kpi-blue"   role="listitem"><div class="kpi-label">Clientes</div><div class="kpi-value" id="kpi_clientes">-</div></div>
        <div class="kpi-box kpi-green"  role="listitem"><div class="kpi-label">Activos</div><div class="kpi-value" id="kpi_activos">-</div></div>
        <div class="kpi-box kpi-red"    role="listitem"><div class="kpi-label">Inactivos</div><div class="kpi-value" id="kpi_inactivos">-</div></div>
        <div class="kpi-box kpi-yellow" role="listitem"><div class="kpi-label">Nuevos este mes</div><div class="kpi-value" id="kpi_nuevos">-</div></div>
        <div class="kpi-box" role="listitem"><div class="kpi-label">Pend. Pago</div><div class="kpi-value" id="kpi_pendientes">-</div></div>
        <div class="kpi-box" role="listitem"><div class="kpi-label">Premium</div><div class="kpi-value" id="kpi_premium">-</div></div>
        <div class="kpi-box" role="listitem"><div class="kpi-label">Timbres Usados</div><div class="kpi-value" id="kpi_timbres">-</div></div>
        <div class="kpi-box" role="listitem"><div class="kpi-label">Ingresos Mes</div><div class="kpi-value" id="kpi_ingresos">-</div></div>
      </div>
    </section>

    {{-- Gráficas --}}
    <section class="charts-grid" aria-label="Métricas">
      <article class="chart-card" data-span="2" aria-labelledby="chartIncomeTitle">
        <div class="chart-head sticky">
          <div class="widget-title" id="chartIncomeTitle"><span aria-hidden="true">🧾</span> <span class="t">Ingresos Mensuales</span></div>
          <div class="chart-tools">
            <button class="btn" id="btnIncomeResetZoom" type="button">Reset zoom</button>
            <button class="btn" id="btnIncomePNG" type="button">PNG</button>
            <button class="btn" id="btnIncomeCSV" type="button">CSV</button>
          </div>
        </div>
        <div class="chart-wrap"><canvas id="chartIncome"></canvas></div>
      </article>

      <article class="chart-card" aria-labelledby="chartStampsTitle">
        <div class="chart-head sticky">
          <div class="widget-title" id="chartStampsTitle"><span aria-hidden="true">🧮</span> <span class="t">Timbres por mes</span></div>
          <div class="chart-tools">
            <button class="btn" id="btnStampsResetZoom" type="button">Reset zoom</button>
            <button class="btn" id="btnStampsPNG" type="button">PNG</button>
            <button class="btn" id="btnStampsCSV" type="button">CSV</button>
          </div>
        </div>
        <div class="chart-wrap"><canvas id="chartStamps"></canvas></div>
      </article>

      <article class="chart-card" aria-labelledby="chartPlansTitle">
        <div class="chart-head sticky">
          <div class="widget-title" id="chartPlansTitle"><span aria-hidden="true">⭐</span> <span class="t">Clientes por Plan</span></div>
          <div class="chart-tools">
            <button class="btn" id="btnPlansPNG" type="button">PNG</button>
            <button class="btn" id="btnPlansCSV" type="button">CSV</button>
          </div>
        </div>
        <div class="chart-wrap"><canvas id="chartPlans"></canvas></div>
      </article>

      <article class="chart-card" aria-labelledby="chartIncomePlanTitle">
        <div class="chart-head sticky">
          <div class="widget-title" id="chartIncomePlanTitle"><span aria-hidden="true">🧷</span> <span class="t">Ingresos por Plan (mensual)</span></div>
          <div class="chart-tools">
            <button class="btn" id="btnIncomePlanPNG" type="button">PNG</button>
            <button class="btn" id="btnIncomePlanCSV" type="button">CSV</button>
          </div>
        </div>
        <div class="chart-wrap"><canvas id="chartIncomePlan"></canvas></div>
      </article>

      <article class="chart-card" aria-labelledby="chartNewClientsTitle">
        <div class="chart-head sticky">
          <div class="widget-title" id="chartNewClientsTitle"><span aria-hidden="true">🆕</span> <span class="t">Nuevos clientes por mes</span></div>
          <div class="chart-tools">
            <button class="btn" id="btnNewClientsPNG" type="button">PNG</button>
            <button class="btn" id="btnNewClientsCSV" type="button">CSV</button>
          </div>
        </div>
        <div class="chart-wrap"><canvas id="chartNewClients"></canvas></div>
      </article>

      <article class="chart-card" aria-labelledby="chartTopClientsTitle">
        <div class="chart-head sticky">
          <div class="widget-title" id="chartTopClientsTitle"><span aria-hidden="true">🏆</span> <span class="t">Top clientes por ingresos</span></div>
          <div class="chart-tools">
            <button class="btn" id="btnTopClientsPNG" type="button">PNG</button>
            <button class="btn" id="btnTopClientsCSV" type="button">CSV</button>
          </div>
        </div>
        <div class="chart-wrap"><canvas id="chartTopClients"></canvas></div>
      </article>

      <article class="chart-card" aria-labelledby="chartYTDTitle">
        <div class="chart-head sticky">
          <div class="widget-title" id="chartYTDTitle"><span aria-hidden="true">📈</span> <span class="t">Ingresos acumulados YTD</span></div>
        </div>
        <div class="chart-wrap"><canvas id="chartYTD"></canvas></div>
      </article>

      <article class="chart-card" data-span="2" aria-labelledby="chartDailyTitle">
        <div class="chart-head sticky">
          <div class="widget-title" id="chartDailyTitle"><span aria-hidden="true">📅</span> <span class="t">Comparativa diaria (mes actual)</span></div>
        </div>
        <div class="chart-wrap"><canvas id="chartDaily"></canvas></div>
      </article>

      <article class="chart-card" aria-labelledby="chartMoMTitle">
        <div class="chart-head sticky">
          <div class="widget-title" id="chartMoMTitle"><span aria-hidden="true">📊</span> <span class="t">Variación mensual (%)</span></div>
        </div>
        <div class="chart-wrap"><canvas id="chartMoM"></canvas></div>
      </article>
    </section>

    {{-- Tabla: Clientes Activos --}}
    <section class="table-card" style="width:100%;max-width:none" aria-labelledby="tblClientsTitle">

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
        <table class="table hover striped" id="clientsTable">
          <thead>
            <tr><th>#</th><th>Empresa</th><th>RFC</th><th>Timbres</th><th>Última actividad</th><th>Estado</th></tr>
          </thead>
          <tbody id="clientsTbody">
            <tr id="clientsEmptyRow"><td colspan="6" class="empty">Sin datos</td></tr>
          </tbody>
        </table>
      </div>
    </section>

  </div>

  {{-- Modal --}}
  <div id="modalIncome" class="modal" aria-hidden="true">
    <div class="modal-backdrop" data-close></div>
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="modalIncomeTitle">
      <div class="modal-head">
        <div>
          <div class="modal-title" id="modalIncomeTitle">Ingresos — <span id="modalIncomeMonth">YYYY-MM</span></div>
          <div class="modal-sub text-muted">Pagos del mes seleccionado</div>
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
          <table class="table dense hover" id="incomeModalTable">
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
              <tr><th colspan="5" style="text-align:right">Total</th><th id="incomeModalTotal">-</th></tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  @php
    $HOME_JS_ABS = public_path('assets/admin/js/home.js');
    $HOME_JS_URL = asset('assets/admin/js/home.js') . (is_file($HOME_JS_ABS) ? ('?v='.filemtime($HOME_JS_ABS)) : '');
  @endphp

  <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom@2"></script>
  <script src="{{ $HOME_JS_URL }}"></script>
@endpush
