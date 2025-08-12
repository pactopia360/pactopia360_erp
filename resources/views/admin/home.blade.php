@extends('layouts.admin')

@section('title','Home')

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/admin/css/home.css') }}">
@endpush

@section('content')
<div class="page"
     data-stats-url="{{ route('admin.home.stats') }}"
     data-income-url="{{ route('admin.home.incomeMonth', ['ym'=>'__YM__']) }}">

    {{-- Título --}}
    <div class="page-card">
      <div class="page-title">
        <span class="ico">📊</span>
        <div>
          <h2>Panel Administrativo</h2>
          <p>Resumen general y métricas clave</p>
        </div>
      </div>

      {{-- Barra de filtros --}}
      <div class="filters-bar">
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
            {{-- opciones dinámicas desde la API --}}
          </select>

          <button id="btnApply" class="btn btn-primary">Aplicar</button>
        </div>

        <div class="filters-right">
          <button id="btnResetZoom" class="btn">Reset zoom</button>
          <button id="btnDownloadIncomePNG" class="btn">PNG Ingresos</button>
          <button id="btnDownloadIncomeCSV" class="btn">CSV Ingresos</button>
        </div>
      </div>

      {{-- KPIs --}}
      <div class="kpi-grid">
        <div class="kpi-box kpi-blue">
          <div class="kpi-label">Clientes</div>
          <div class="kpi-value" id="kpi_clientes">-</div>
        </div>
        <div class="kpi-box kpi-green">
          <div class="kpi-label">Activos</div>
          <div class="kpi-value" id="kpi_activos">-</div>
        </div>
        <div class="kpi-box kpi-red">
          <div class="kpi-label">Inactivos</div>
          <div class="kpi-value" id="kpi_inactivos">-</div>
        </div>
        <div class="kpi-box kpi-yellow">
          <div class="kpi-label">Nuevos este mes</div>
          <div class="kpi-value" id="kpi_nuevos">-</div>
        </div>
        <div class="kpi-box">
          <div class="kpi-label">Pend. Pago</div>
          <div class="kpi-value" id="kpi_pendientes">-</div>
        </div>
        <div class="kpi-box">
          <div class="kpi-label">Premium</div>
          <div class="kpi-value" id="kpi_premium">-</div>
        </div>
        <div class="kpi-box">
          <div class="kpi-label">Timbres Usados</div>
          <div class="kpi-value" id="kpi_timbres">-</div>
        </div>
        <div class="kpi-box">
          <div class="kpi-label">Ingresos Mes</div>
          <div class="kpi-value" id="kpi_ingresos">-</div>
        </div>
      </div>
    </div>

    {{-- Gráficas --}}
    <div class="charts-row">
      <div class="chart-card">
        <div class="widget-title"><span>🧾</span> Ingresos Mensuales</div>
        <canvas id="chartIncome" height="120"></canvas>
      </div>

      <div class="chart-card">
        <div class="widget-title"><span>🧮</span> Timbres por Mes</div>
        <canvas id="chartStamps" height="120"></canvas>
      </div>

      <div class="chart-card">
        <div class="widget-title"><span>⭐</span> Clientes por Plan</div>
        <canvas id="chartPlans" height="120"></canvas>
      </div>
    </div>

    {{-- Tabla: Ingresos Mensuales (mejorada) --}}
    <div class="table-card">
      <div class="table-head">
        <div class="widget-title"><span>💰</span> Ingresos mensuales</div>
        <div class="table-actions">
          <div class="search">
            <span>🔍</span>
            <input type="text" id="searchIncome" placeholder="Buscar mes...">
          </div>
          <button id="btnExportIncome" class="btn-export" type="button">⬇ Exportar</button>
        </div>
      </div>

      <div class="table-wrap">
        <table class="table" id="incomeTable">
          <thead>
          <tr>
            <th data-sort="mes">Mes</th>
            <th data-sort="total">Ingresos</th>
            <th data-sort="pagos">Pagos</th>
            <th data-sort="avg">Ticket prom.</th>
          </tr>
          </thead>
          <tbody id="incomeTbody">
            <tr id="incomeEmpty"><td colspan="4" class="empty">Sin datos</td></tr>
          </tbody>
          <tfoot>
          <tr>
            <th>Total</th>
            <th id="incomeSum">-</th>
            <th id="incomeCount">-</th>
            <th id="incomeAvg">-</th>
          </tr>
          </tfoot>
        </table>
      </div>
    </div>

    {{-- Tabla: Clientes Activos (se mantiene) --}}
    <div class="table-card">
      <div class="table-head">
        <div class="widget-title"><span>👥</span> Clientes Activos</div>

        <div class="table-actions">
          <div class="search">
            <span>🔍</span>
            <input type="text" id="searchClients" placeholder="Buscar...">
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
    </div>

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
      <button class="modal-close" type="button" data-close>✖</button>
    </div>
    <div class="modal-tools">
      <div class="search">
        <span>🔍</span>
        <input type="text" id="incomeModalSearch" placeholder="Buscar cliente / RFC / referencia...">
      </div>
      <div class="spacer"></div>
      <button class="btn" id="incomeModalExport">Exportar CSV</button>
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


@push('scripts')
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom@2" defer></script>
  <script src="{{ asset('assets/admin/js/home.js') }}" defer></script>
@endpush
