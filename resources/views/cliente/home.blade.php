{{-- C:\wamp64\www\pactopia360_erp\resources\views\cliente\home.blade.php --}}
@extends('layouts.cliente')

@section('title', 'Inicio · Pactopia360')

@php
  use Illuminate\Support\Facades\Route;
  use Carbon\Carbon;

  $viewUser = auth()->user() ?? auth('cliente')->user() ?? auth('web')->user();
  $sum = is_array($summary ?? null) ? $summary : [];

  $razonV = (string) (
      $razon
      ?? data_get($sum, 'razon')
      ?? data_get($sum, 'razon_social')
      ?? ($viewUser->nombre ?? null)
      ?? ($viewUser->name ?? null)
      ?? ($viewUser->email ?? 'Cliente')
  );

  $sumEstado  = strtoupper((string) (data_get($sum, 'estado') ?? data_get($sum, 'billing_status') ?? 'ACTIVA'));
  $sumBlocked = (bool) (data_get($sum, 'blocked') ?? data_get($sum, 'is_blocked') ?? false);

  $timbresV = (int) ($timbres ?? data_get($sum, 'timbres', 0));

  $kEmit  = (float) data_get($kpis ?? [], 'emitidos', 0);
  $kCanc  = (float) data_get($kpis ?? [], 'cancelados', 0);
  $kTotal = (float) data_get($kpis ?? [], 'total', 0);
  $kDelta = (float) data_get($kpis ?? [], 'delta', 0);

  $periodFrom = (string) data_get($kpis ?? [], 'period.from', '');
  $periodTo   = (string) data_get($kpis ?? [], 'period.to', '');

  $labelsV  = data_get($series ?? [], 'labels', []);
  $lineV    = data_get($series ?? [], 'series.line_facturacion', data_get($series ?? [], 'series.emitidos_total', []));
  $lineCanc = data_get($series ?? [], 'series.line_cancelados', []);
  $barsV    = data_get($series ?? [], 'series.bar_q', [0, 0, 0, 0]);

  $rtKpisJs = Route::has('cliente.home.kpis')
      ? route('cliente.home.kpis')
      : (Route::has('cliente.kpis') ? route('cliente.kpis') : '');

  $rtSeriesJs = Route::has('cliente.home.series')
      ? route('cliente.home.series')
      : (Route::has('cliente.series') ? route('cliente.series') : '');

  $isLocal = (bool) ($isLocal ?? false);
  $dataSource = (string) ($dataSource ?? 'db');

  $spaceTotal = (float) data_get($sum, 'space_total', 0);
  $spaceUsed  = (float) data_get($sum, 'space_used', 0);
  $spacePct   = $spaceTotal > 0 ? (float) data_get($sum, 'space_pct', 0) : 0;

  $rtSat = Route::has('cliente.sat.index')
      ? route('cliente.sat.index')
      : (Route::has('cliente.sat.descargas.index') ? route('cliente.sat.descargas.index') : '#');

  $rtSatDescargas = Route::has('cliente.sat.descargas.index')
      ? route('cliente.sat.descargas.index')
      : $rtSat;

  $rtFact = Route::has('cliente.facturacion.index')
      ? route('cliente.facturacion.index')
      : (Route::has('cliente.facturacion') ? route('cliente.facturacion') : '#');

  $rtPerfil = Route::has('cliente.perfil')
      ? route('cliente.perfil')
      : url('/cliente/perfil');

  $rtMiCuenta = Route::has('cliente.mi_cuenta')
      ? route('cliente.mi_cuenta')
      : (Route::has('cliente.mi_cuenta.index') ? route('cliente.mi_cuenta.index') : url('/cliente/mi-cuenta'));

  $rtTimbres = Route::has('cliente.modulos.timbres')
      ? route('cliente.modulos.timbres')
      : (Route::has('cliente.sat.cart.index') ? route('cliente.sat.cart.index') : $rtFact);

  $rtSoporte = Route::has('cliente.soporte.chat')
      ? route('cliente.soporte.chat')
      : (Route::has('cliente.chat') ? route('cliente.chat') : '#');

  $rtCrm = Route::has('cliente.modulos.crm') ? route('cliente.modulos.crm') : '#';
  $rtInventario = Route::has('cliente.modulos.inventario') ? route('cliente.modulos.inventario') : '#';
  $rtVentas = Route::has('cliente.modulos.ventas') ? route('cliente.modulos.ventas') : '#';
  $rtReportes = Route::has('cliente.modulos.reportes') ? route('cliente.modulos.reportes') : '#';
  $rtRh = Route::has('cliente.modulos.rh') ? route('cliente.modulos.rh') : '#';

  $recentRows = collect($recent ?? []);

  $modulesState = data_get($sum, 'modules_state');
  if (!is_array($modulesState)) $modulesState = data_get($sum, 'modules.state');
  if (!is_array($modulesState)) $modulesState = data_get($sum, 'modules');
  if (!is_array($modulesState)) $modulesState = data_get($sum, 'meta.modules_state');
  if (!is_array($modulesState)) $modulesState = data_get($sum, 'account.modules_state');
  if (!is_array($modulesState)) $modulesState = [];

  $moduleEnabled = function (string $key, bool $default = true) use ($modulesState) {
      $state = $modulesState[$key] ?? null;

      if (is_bool($state)) return $state;
      if (is_numeric($state)) return ((int) $state) === 1;

      if (is_string($state)) {
          $s = strtolower(trim($state));
          if (in_array($s, ['1', 'true', 'on', 'yes', 'active', 'enabled', 'visible'], true)) return true;
          if (in_array($s, ['0', 'false', 'off', 'no', 'inactive', 'disabled', 'hidden', 'blocked'], true)) return false;
      }

      if (is_array($state)) {
          if (array_key_exists('hidden', $state) && (bool) $state['hidden'] === true) return false;
          if (array_key_exists('visible', $state)) return (bool) $state['visible'];
          if (array_key_exists('enabled', $state)) return (bool) $state['enabled'];
          if (array_key_exists('active', $state)) return (bool) $state['active'];
          if (array_key_exists('access', $state)) return (bool) $state['access'];
      }

      return $default;
  };

  $mainApps = collect([
      [
          'key' => 'sat_descargas',
          'icon' => '🧾',
          'title' => 'Portal SAT',
          'desc' => 'RFC, cotizaciones, descargas y bóveda.',
          'href' => $rtSat,
          'accent' => 'blue',
      ],
      [
          'key' => 'facturacion',
          'icon' => '📄',
          'title' => 'Facturación',
          'desc' => 'Emisión, administración y control CFDI.',
          'href' => $rtFact,
          'accent' => 'cyan',
      ],
      [
          'key' => 'timbres_hits',
          'icon' => '⚡',
          'title' => 'Timbres',
          'desc' => number_format($timbresV) . ' disponibles.',
          'href' => $rtTimbres,
          'accent' => 'amber',
      ],
      [
          'key' => 'crm',
          'icon' => '👥',
          'title' => 'CRM',
          'desc' => 'Clientes, prospectos y seguimiento comercial.',
          'href' => $rtCrm,
          'accent' => 'violet',
      ],
      [
          'key' => 'inventario',
          'icon' => '📦',
          'title' => 'Inventario',
          'desc' => 'Productos, existencias y movimientos.',
          'href' => $rtInventario,
          'accent' => 'orange',
      ],
      [
          'key' => 'ventas',
          'icon' => '💳',
          'title' => 'Ventas',
          'desc' => 'Tickets, venta y autofacturación.',
          'href' => $rtVentas,
          'accent' => 'green',
      ],
      [
          'key' => 'recursos_humanos',
          'icon' => '🧑‍💼',
          'title' => 'RH',
          'desc' => 'Personal, incidencias y nómina.',
          'href' => $rtRh,
          'accent' => 'rose',
      ],
      [
          'key' => 'reportes',
          'icon' => '📊',
          'title' => 'Reportes',
          'desc' => 'Indicadores e inteligencia operativa.',
          'href' => $rtReportes,
          'accent' => 'slate',
      ],
  ])->filter(fn ($m) => $moduleEnabled((string) $m['key'], true))->values();

  $quickActions = [
      ['icon' => '➕', 'title' => 'Emitir CFDI', 'href' => $rtFact],
      ['icon' => '⬇️', 'title' => 'Descargar SAT', 'href' => $rtSatDescargas],
      ['icon' => '⚡', 'title' => 'Comprar timbres', 'href' => $rtTimbres],
      ['icon' => '💼', 'title' => 'Mi cuenta', 'href' => $rtMiCuenta],
      ['icon' => '👤', 'title' => 'Perfil', 'href' => $rtPerfil],
      ['icon' => '💬', 'title' => 'Soporte', 'href' => $rtSoporte],
  ];

  $alerts = collect();

  if ($sumBlocked) {
      $alerts->push([
          'level' => 'danger',
          'icon' => '⛔',
          'title' => 'Cuenta requiere atención',
          'href' => $rtMiCuenta,
      ]);
  }

  if ($timbresV <= 10) {
      $alerts->push([
          'level' => 'warn',
          'icon' => '⚡',
          'title' => 'Revisar timbres',
          'href' => $rtTimbres,
      ]);
  }

  if ($alerts->isEmpty()) {
      $alerts->push([
          'level' => 'ok',
          'icon' => '✅',
          'title' => 'Operación estable',
          'href' => $rtFact,
      ]);
  }

  $periodLabel = ($periodFrom && $periodTo)
      ? Carbon::parse($periodFrom)->format('d/m/Y') . ' – ' . Carbon::parse($periodTo)->format('d/m/Y')
      : 'Periodo actual';
@endphp

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/client/css/pages/home.css') }}">
@endpush

@section('content')
<div class="p360-home p360-home-clean">

  <section class="p360-clean-hero">
    <div class="p360-clean-hero__copy">
      <span class="p360-clean-hero__tag">Portal operativo</span>
      <h1>{{ $razonV }}</h1>
      <p>Administra tus módulos, CFDI, SAT, timbres y operación desde un solo lugar.</p>
    </div>

    <div class="p360-clean-hero__orbit" aria-hidden="true">
      <div class="p360-orbit-card p360-orbit-card--main">P360</div>
      <div class="p360-orbit-item item-1">🧾</div>
      <div class="p360-orbit-item item-2">📊</div>
      <div class="p360-orbit-item item-3">⚡</div>
      <div class="p360-orbit-item item-4">📦</div>
      <div class="p360-orbit-item item-5">📄</div>
    </div>
  </section>

  <section class="p360-clean-strip">
    <article><span>CFDI Emitidos</span><strong id="kpi-em">${{ number_format($kEmit, 2) }}</strong><small>Este mes</small></article>
    <article><span>Total Mensual</span><strong id="kpi-to">${{ number_format($kTotal, 2) }}</strong><small>Este mes</small></article>
    <article><span>Cancelados</span><strong id="kpi-ca">${{ number_format($kCanc, 2) }}</strong><small>Este mes</small></article>
    <article><span>Timbres Disponibles</span><strong>{{ number_format($timbresV) }}</strong><small>Disponibles</small></article>
    <article><span>Bóveda</span><strong>{{ $spaceTotal > 0 ? number_format($spacePct, 0) . '%' : 'Lista' }}</strong><small>{{ $spaceTotal > 0 ? number_format($spaceUsed, 1) . ' / ' . number_format($spaceTotal, 1) . ' MB' : 'Activa' }}</small></article>
  </section>

  <section class="p360-dashboard-grid">

    <div class="p360-dashboard-main">
      <section class="p360-clean-panel">
        <div class="p360-clean-head">
          <div>
            <span>Mis módulos</span>
            <h2>Accesos principales</h2>
          </div>
        </div>

        <div class="p360-app-grid">
          @foreach($mainApps as $app)
            <a href="{{ $app['href'] }}" class="p360-app-card p360-app-card--{{ $app['accent'] }}">
              <div class="p360-app-card__icon">{{ $app['icon'] }}</div>
              <div class="p360-app-card__body">
                <strong>{{ $app['title'] }}</strong>
                <p>{{ $app['desc'] }}</p>
              </div>
              <div class="p360-app-card__go">›</div>
            </a>
          @endforeach
        </div>
      </section>

      <section class="p360-clean-row p360-clean-row--charts">
        <div class="p360-clean-panel">
          <div class="p360-clean-head">
            <div>
              <span>Facturación del mes</span>
              <h2>Facturación</h2>
            </div>
          </div>
          <div id="chart-lines" class="p360-chart-clean"></div>
        </div>

        <div class="p360-clean-panel">
          <div class="p360-clean-head">
            <div>
              <span>Comparativo semanal</span>
              <h2>Semanal</h2>
            </div>
          </div>
          <div id="chart-bars" class="p360-chart-clean"></div>
        </div>
      </section>
    </div>

    <aside class="p360-dashboard-side">
      <section class="p360-clean-panel">
        <div class="p360-clean-head">
          <div>
            <span>Acciones rápidas</span>
            <h2>Acciones</h2>
          </div>
        </div>

        <div class="p360-side-actions">
          @foreach($quickActions as $action)
            <a href="{{ $action['href'] }}" class="p360-side-action">
              <span>{{ $action['icon'] }}</span>
              <strong>{{ $action['title'] }}</strong>
              <em>›</em>
            </a>
          @endforeach
        </div>
      </section>

      <section class="p360-clean-panel">
        <div class="p360-clean-head">
          <div>
            <span>Estado actual</span>
            <h2>Hoy</h2>
          </div>
        </div>

        <div class="p360-status-list">
          @foreach($alerts as $alert)
            <a href="{{ $alert['href'] }}" class="p360-status-item p360-status-item--{{ $alert['level'] }}">
              <span>{{ $alert['icon'] }}</span>
              <strong>{{ $alert['title'] }}</strong>
              <em>›</em>
            </a>
          @endforeach

          <div class="p360-status-item">
            <span>🔄</span>
            <strong>{{ $isLocal && $dataSource === 'db' ? 'Sistema actualizado' : 'Sincronización activa' }}</strong>
            <em>›</em>
          </div>
        </div>
      </section>

      <section class="p360-clean-panel">
        <div class="p360-clean-head">
          <div>
            <span>Actividad reciente</span>
            <h2>Movimientos</h2>
          </div>
        </div>

        @if($recentRows->count() > 0)
          <div class="p360-feed">
            @foreach($recentRows->take(3) as $r)
              @php
                $uuid = (string) ($r->uuid ?? '—');
                $uuidShort = $uuid !== '—' ? (substr($uuid, 0, 8) . '…' . substr($uuid, -6)) : '—';
                $st = strtoupper((string) ($r->estatus ?? 'N/D'));
                $dt = !empty($r->fecha) ? Carbon::parse($r->fecha)->format('d/m/Y') : '—';
              @endphp

              <div class="p360-feed-item">
                <span class="p360-feed-icon">📄</span>
                <div>
                  <strong>{{ $uuidShort }}</strong>
                  <small>{{ $dt }} · ${{ number_format((float) ($r->total ?? 0), 2) }}</small>
                </div>
                <em>{{ $st }}</em>
              </div>
            @endforeach
          </div>
        @else
          <div class="p360-empty-clean">
            <span>✨</span>
            <strong>Tu actividad aparecerá aquí</strong>
          </div>
        @endif
      </section>
    </aside>

  </section>

</div>
@endsection

@push('scripts')
  <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
  <script>
  document.addEventListener('DOMContentLoaded', () => {
    const rootStyle = getComputedStyle(document.documentElement);
    const getVar = (name, fallback) => (rootStyle.getPropertyValue(name) || '').trim() || fallback;

    const fore = getVar('--ink', document.documentElement.getAttribute('data-theme') === 'dark' ? '#e5e7eb' : '#0f172a');
    const gridC = getVar('--bd', document.documentElement.getAttribute('data-theme') === 'dark' ? 'rgba(255,255,255,.12)' : 'rgba(0,0,0,.08)');
    const brand = getVar('--brand', '#2563EB');
    const accent = getVar('--accent', '#18A0FB');

    const labels = @json($labelsV);
    const lineData = @json($lineV);
    const cancData = @json($lineCanc);
    const barsData = @json($barsV);

    function money(v) {
      return '$' + Number(v || 0).toFixed(2);
    }

    if (window.ApexCharts) {
      window.chartLines = new ApexCharts(document.querySelector('#chart-lines'), {
        chart: { type: 'area', height: 280, foreColor: fore, toolbar: { show: false } },
        stroke: { curve: 'smooth', width: 3 },
        series: [
          { name: 'Emitidos', data: lineData },
          { name: 'Cancelados', data: cancData }
        ],
        xaxis: { categories: labels, labels: { rotate: -35 } },
        yaxis: { labels: { formatter: (v) => money(v) } },
        tooltip: { y: { formatter: (v) => money(v) } },
        colors: [accent, brand],
        fill: { type: 'gradient', gradient: { opacityFrom: .34, opacityTo: .04 } },
        grid: { borderColor: gridC }
      });
      chartLines.render();

      window.chartBars = new ApexCharts(document.querySelector('#chart-bars'), {
        chart: { type: 'bar', height: 280, foreColor: fore, toolbar: { show: false } },
        plotOptions: { bar: { columnWidth: '45%', borderRadius: 9 } },
        series: [{ name: 'Semana', data: barsData }],
        xaxis: { categories: ['Sem 1', 'Sem 2', 'Sem 3', 'Sem 4'] },
        yaxis: { labels: { formatter: (v) => money(v) } },
        tooltip: { y: { formatter: (v) => money(v) } },
        colors: [brand],
        grid: { borderColor: gridC }
      });
      chartBars.render();
    }

    const rtKpis = @json($rtKpisJs);
    const rtSeries = @json($rtSeriesJs);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    async function safeJson(res) {
      const ct = res.headers.get('content-type') || '';
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      if (!ct.includes('application/json')) throw new Error('Respuesta no JSON');
      return res.json();
    }

    function setText(id, val) {
      const el = document.getElementById(id);
      if (el) el.textContent = val;
    }

    async function refreshData() {
      try {
        if (!rtKpis || !rtSeries) return;

        const [kpiRes, serieRes] = await Promise.all([
          fetch(rtKpis, { headers: { 'X-CSRF-TOKEN': csrf } }),
          fetch(rtSeries, { headers: { 'X-CSRF-TOKEN': csrf } })
        ]);

        const k = await safeJson(kpiRes);
        const s = await safeJson(serieRes);

        const kd = k.kpis || k;

        setText('kpi-em', money(kd.emitidos || 0));
        setText('kpi-ca', money(kd.cancelados || 0));
        setText('kpi-to', money(kd.total || 0));

        const line = (s.series && (s.series.line_facturacion || s.series.emitidos_total))
          ? (s.series.line_facturacion || s.series.emitidos_total)
          : [];

        const canc = (s.series && s.series.line_cancelados) ? s.series.line_cancelados : [];
        const bars = (s.series && s.series.bar_q) ? s.series.bar_q : [0, 0, 0, 0];

        if (window.chartLines && window.chartBars) {
          chartLines.updateOptions({ xaxis: { categories: s.labels || [] } });
          chartLines.updateSeries([
            { name: 'Emitidos', data: line },
            { name: 'Cancelados', data: canc }
          ]);
          chartBars.updateSeries([{ name: 'Semana', data: bars }]);
        }
      } catch (e) {
        console.warn('Home refresh error:', e);
      }
    }

    setInterval(refreshData, 30000);
  });
  </script>
@endpush