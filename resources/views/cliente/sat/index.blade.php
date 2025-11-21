@extends('layouts.cliente')
@section('title','SAT · Descargas masivas CFDI')

@php
  // ===== Resumen unificado de cuenta (admin.accounts) =====
  $summary = $summary ?? app(\App\Http\Controllers\Cliente\HomeController::class)->buildAccountSummary();

  $planFromSummary = strtoupper((string)($summary['plan'] ?? 'FREE'));
  $isProSummary    = (bool)($summary['is_pro'] ?? in_array(
      strtolower($planFromSummary),
      ['pro','premium','empresa','business'],
      true
  ));

  // ===== Datos base seguros (SAT) =====
  $plan      = $planFromSummary;
  $isProPlan = $isProSummary;
  $isPro     = $isProPlan;

  $credList = $credList    ?? [];
  $rowsInit = $initialRows ?? [];

  // Modo DEMO/PROD por cookie/entorno
  $cookieMode  = request()->cookie('sat_mode');
  $driver      = config('services.sat.download.driver','satws');
  $isLocalDemo = app()->environment(['local','development','testing']) && $driver !== 'satws';
  $mode        = $cookieMode ? strtolower($cookieMode) : ($isLocalDemo ? 'demo' : 'prod');
  $modeLabel   = strtoupper($mode === 'demo' ? 'FUENTE: DEMO' : 'FUENTE: PRODUCCIÓN');

  // Totales rápidos (solicitudes)
  $kRfc   = is_countable($credList) ? count($credList) : 0;
  $kPend  = 0;
  $kReady = 0;

  if (is_iterable($rowsInit)) {
      foreach ($rowsInit as $r) {
          $estado = strtolower($r['estado'] ?? '');
          if (in_array($estado, ['pending','processing'])) {
              $kPend++;
          }
          if (in_array($estado, ['ready','done','listo'])) {
              $kReady++;
          }
      }
  }

  // Cuotas SAT: si es PRO usa 12, si no 1 (puedes ajustar después)
  $asigDefault = $isProPlan ? 12 : 1;
  $asig        = (int)($cuenta->sat_quota_assigned ?? $asigDefault);
  $usadas      = (int)($cuenta->sat_quota_used ?? 0);
  $pct         = $asig > 0 ? min(100, max(0, round(($usadas / $asig) * 100))) : 0;

  // ===== Métricas de archivos y peticiones para el dashboard =====
  $filesPeriod = (int)($summary['sat_files_period'] ?? 0);   // Archivos descargados en el periodo
  $filesTotal  = (int)($summary['sat_files_total']  ?? 0);   // Archivos descargados acumulados

  $rfcsValidated = (int)($summary['sat_rfcs_validated'] ?? $kRfc);
  $rfcsPending   = max(0, (int)($summary['sat_rfcs_pending'] ?? 0));

  // Detalle de movimientos
  $reqStart     = (int)($summary['sat_req_start']     ?? 0);
  $reqPeriod    = (int)($summary['sat_req_period']    ?? 0);
  $reqAvailable = (int)($summary['sat_req_available'] ?? ($cuenta->sat_quota_available ?? 0));
  $reqDone      = (int)($summary['sat_req_done']      ?? $reqPeriod); // para "Peticiones realizadas" top

  // Almacenamiento bóveda (en GB)
  $vaultQuotaGb     = (float)($cuenta->vault_quota_gb     ?? 5.0);
  $vaultUsedGb      = (float)($summary['vault_used_gb']   ?? 0.0);
  $vaultAvailableGb = max(0, $vaultQuotaGb - $vaultUsedGb);
  $vaultUsedPct     = $vaultQuotaGb > 0 ? min(100, round($vaultUsedGb / $vaultQuotaGb * 100)) : 0;

  // Rutas (defensivas)
  $rtCsdStore  = \Route::has('cliente.sat.credenciales.store') ? route('cliente.sat.credenciales.store') : '#';
  $rtReqCreate = \Route::has('cliente.sat.request')            ? route('cliente.sat.request')            : '#';
  $rtVerify    = \Route::has('cliente.sat.verify')             ? route('cliente.sat.verify')             : '#';
  $rtPkgPost   = \Route::has('cliente.sat.download')           ? route('cliente.sat.download')           : '#';
  $rtZipGet    = \Route::has('cliente.sat.zip')                ? route('cliente.sat.zip',['id'=>'__ID__']) : '#';
  $rtReport    = \Route::has('cliente.sat.report')             ? route('cliente.sat.report')             : '#';
  $rtVault     = \Route::has('cliente.sat.vault')              ? route('cliente.sat.vault')              : '#';
  $rtMode      = \Route::has('cliente.sat.mode')               ? route('cliente.sat.mode')               : null;
  $rtCharts    = \Route::has('cliente.sat.charts')             ? route('cliente.sat.charts')             : null;

  // Rutas para RFCs (partial / modal)
  $rtAlias     = \Route::has('cliente.sat.alias')        ? route('cliente.sat.alias')         : '#';
  $rtRfcReg    = \Route::has('cliente.sat.rfc.register') ? route('cliente.sat.rfc.register')  : '#';
@endphp

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/client/css/sat-dashboard.css') }}">
@endpush


@section('content')
<div class="sat-ui" id="satApp" data-plan="{{ $plan }}" data-mode="{{ $mode }}">

  {{-- HEADER DE PÁGINA: TÍTULO + BOTONES + FUENTE --}}
  <div class="sat-page-header">
    <div class="sat-page-left">
      <div class="sat-title-main">Descargas masivas CFDI</div>
      <div class="sat-title-sub">CFDI EMITIDOS Y RECIBIDOS · CONTROL CENTRAL</div>
    </div>

    <div class="sat-page-right">
      <button class="btn" type="button" id="btnRefresh" data-tip="Actualizar pantalla">
        <span aria-hidden="true">⟳</span><span>Actualizar</span>
      </button>
      <a class="btn" href="{{ $rtReport }}?fmt=csv" data-tip="Descargar reporte CSV">
        <span aria-hidden="true">🗒</span><span>CSV</span>
      </a>
      <a class="btn" href="{{ $rtReport }}?fmt=xlsx" data-tip="Descargar reporte Excel">
        <span aria-hidden="true">📑</span><span>Excel</span>
      </a>
      <a class="btn soft" href="{{ $rtReport }}" data-tip="Ir al reporte contable">
        <span aria-hidden="true">📊</span><span>Reporte</span>
      </a>
      @if($rtMode)
        <button
          type="button"
          class="badge-mode {{ $mode==='demo' ? 'demo' : 'prod' }}"
          id="badgeMode"
          data-url="{{ $rtMode }}"
        >
          <span class="dot"></span>
          <span>{{ $modeLabel }}</span>
        </button>
      @endif
    </div>
  </div>

  {{-- CARD PRINCIPAL RESUMEN --}}
  <div class="sat-card sat-card-header">

    {{-- CONTADORES TOP EN EL ORDEN SOLICITADO --}}
    <div class="sat-top-grid">
      {{-- 1. Archivos descargados en el periodo --}}
      <div class="top-card top-card-large">
        <div class="top-card-header">Archivos descargados en el periodo</div>
        <div class="top-card-main">{{ number_format($filesPeriod) }}</div>
        <div class="top-card-foot top-card-foot-success">
          0.00% ▲
        </div>
      </div>

      {{-- 2. Archivos descargados totales --}}
      <div class="top-card">
        <div class="top-card-header">Archivos descargados (totales)</div>
        <div class="top-card-main">{{ number_format($filesTotal) }}</div>
        <div class="top-card-caption">
          CFDI acumulados descargados desde que activaste el módulo.
        </div>
      </div>

      {{-- 3. RFCs validados --}}
      <div class="top-card">
        <div class="top-card-header">RFCs validados</div>
        <div class="top-card-main">{{ number_format($rfcsValidated) }}</div>
      </div>

      {{-- 4. RFCs por validar --}}
      <div class="top-card">
        <div class="top-card-header">RFCs por validar</div>
        <div class="top-card-main">{{ number_format($rfcsPending) }}</div>
      </div>

      {{-- 5. Peticiones realizadas --}}
      <div class="top-card top-card-cta">
        <div class="top-card-header">
          Peticiones realizadas
        </div>
        <div class="top-card-main">
          {{ number_format($reqDone) }}
        </div>

        <div class="top-card-cta-foot">
          @if($isProPlan)
            <span class="tag-inline">Incluidas en PRO (uso ilimitado)</span>
          @else
            <button type="button" class="btn btn-cta">
              Comprar ahora
            </button>
          @endif
        </div>
      </div>
    </div>

    {{-- LÍNEA + DETALLE DE MOVIMIENTOS --}}
    <div class="sat-main-bottom">
      <div class="sat-main-chart">
        <div class="main-chart-header">
          <span>Últimas semanas</span>
        </div>
        <div class="main-chart-body">
          <canvas id="satTrendMain"></canvas>
        </div>
      </div>
      <div class="sat-main-movements">
        <div class="section-title">Detalle de movimientos</div>
        <ul class="movements-list">
          <li>
            <span>Peticiones al inicio del periodo:</span>
            <strong>@if($isProPlan) Ilimitadas @else {{ number_format($reqStart) }} @endif</strong>
          </li>
          <li>
            <span>Peticiones realizadas en el periodo:</span>
            <strong>{{ number_format($reqPeriod) }}</strong>
          </li>
          <li>
            <span>Peticiones disponibles:</span>
            <strong>
              @if($isProPlan)
                Ilimitadas
              @else
                {{ number_format($reqAvailable) }}
              @endif
            </strong>
          </li>
          <li>
            <span>Archivos descargados:</span>
            <strong>{{ number_format($filesPeriod) }}</strong>
          </li>
        </ul>
      </div>
    </div>
  </div>

  {{-- 3) ALMACENAMIENTO DE BÓVEDA (DONUT) --}}
  <div class="sat-card sat-vault-card">
    <div class="vault-header">
      <div>
        <div class="section-title">Almacenamiento de bóveda fiscal</div>
        <div class="section-sub">
          Control de espacio usado vs disponible. Bóveda desde <b>$650.00 + IVA / mes</b>.
        </div>
      </div>
      <button type="button" class="btn soft btn-marketplace">
        <span aria-hidden="true">🛒</span><span>Marketplace</span>
      </button>
    </div>

    <div class="vault-grid">
      <div class="vault-chart-wrap">
        <canvas id="vaultDonut"></canvas>
        <div class="vault-center-label">
          <div class="vault-center-amount">{{ number_format($vaultQuotaGb, 2) }} Gb</div>
          <div class="vault-center-caption">Cuota total</div>
        </div>
      </div>

      <div class="vault-legend">
        <div class="vault-legend-item">
          <span class="dot dot-used"></span>
          <div>
            <div class="vault-legend-label">Consumido</div>
            <div class="vault-legend-value">{{ number_format($vaultUsedGb,2) }} Gb ({{ $vaultUsedPct }}%)</div>
          </div>
        </div>
        <div class="vault-legend-item">
          <span class="dot dot-free"></span>
          <div>
            <div class="vault-legend-label">Disponible</div>
            <div class="vault-legend-value">{{ number_format($vaultAvailableGb,2) }} Gb</div>
          </div>
        </div>
        <div class="vault-legend-item">
          <span class="dot dot-quota"></span>
          <div>
            <div class="vault-legend-label">Cuota total</div>
            <div class="vault-legend-value">{{ number_format($vaultQuotaGb,2) }} Gb</div>
          </div>
        </div>
        <p class="vault-note">
          En PRO la bóveda fiscal está activa. Puedes eliminar CFDI antiguos para liberar espacio
          o comprar bloques adicionales de 5GB cuando lo requieras.
        </p>
      </div>
    </div>
  </div>

  {{-- 4) GRÁFICAS DE TENDENCIAS --}}
  <div class="sat-card">
    <div class="charts-header">
      <div>
        <div class="section-title">Tendencias</div>
        <div class="section-sub">
          Evolución reciente de CFDI descargados e importes relacionados (últimos meses).
        </div>
      </div>
      <div class="tabs">
        <button class="tab is-active" data-scope="emitidos">CFDI descargados</button>
        <button class="tab" data-scope="recibidos">Importe emitidos</button>
        <button class="tab" data-scope="ambos">Importe recibidos</button>
      </div>
    </div>

    <div class="chart-wrap">
      <div class="canvas-card"><canvas id="chartA"></canvas></div>
      <div class="canvas-card"><canvas id="chartB"></canvas></div>
    </div>

    <p class="text-muted charts-foot">
      Las series se calculan con tus CFDI reales (o, en su defecto, con el historial de descargas SAT).
    </p>
  </div>

  {{-- 5) GUÍAS RÁPIDAS --}}
  <div class="sat-card">
    <div style="margin-bottom:8px">
      <div class="section-title">Guías rápidas</div>
      <div class="section-sub">Flujos frecuentes de trabajo</div>
    </div>
    <div class="pills-row">
      <button type="button" class="pill primary" data-open="add-rfc" data-tip="Registrar un nuevo RFC / CSD">
        <span aria-hidden="true">➕</span><span>Agregar RFC</span>
      </button>
      <div class="pill" data-tip="Ir a automatizadas">
        <span aria-hidden="true">⚙️</span><span>Descargas automatizadas</span>
      </div>
      <a class="pill" href="{{ $rtVault }}" data-tip="Ir a la Bóveda Fiscal">
        <span aria-hidden="true">💼</span><span>Bóveda fiscal</span>
      </a>
    </div>
  </div>

  {{-- 6) RFCs REGISTRADOS --}}
  <div class="sat-card">
    <div style="margin-bottom:8px">
      <div class="section-title">Conexiones SAT</div>
      <div class="section-sub">RFCs y certificados CSD registrados</div>
    </div>

    @include('cliente.sat._partials.rfcs', [
      'credList'   => $credList,
      'plan'       => $plan,
      'rtCsdStore' => $rtCsdStore,
      'rtAlias'    => $rtAlias,
      'rtRfcReg'   => $rtRfcReg,
    ])
  </div>

  {{-- 7) LISTADO DE DESCARGAS SAT --}}
  <div class="sat-card">
    <div class="sat-dl-head">
      <div class="sat-dl-title">
        <h3>Listado de descargas SAT</h3>
        <p>Histórico de solicitudes y paquetes generados</p>
      </div>
      <div class="sat-dl-filters">
        <input id="satDlSearch" type="text" placeholder="Buscar por solicitud, RFC o alias">
        <select id="satDlTipo">
          <option value="">Todos los tipos</option>
          <option value="emitidos">Emitidos</option>
          <option value="recibidos">Recibidos</option>
        </select>
        <select id="satDlStatus">
          <option value="">Todos los estados</option>
          <option value="pending">Pendientes</option>
          <option value="processing">Procesando</option>
          <option value="done">Listos</option>
          <option value="pay">Pendientes de pago</option>
        </select>
      </div>
    </div>

    <div class="sat-dl-table-wrap">
      <table class="sat-dl-table">
        <thead>
        <tr>
          <th>Solicitud</th>
          <th>Fecha</th>
          <th>Periodo</th>
          <th>RFC</th>
          <th>Alias</th>
          <th>Peso</th>
          <th>Costo</th>
          <th>Disponibilidad</th>
          <th class="t-right">Acciones</th>
        </tr>
        </thead>
        <tbody id="satDlBody"></tbody>
      </table>
    </div>
  </div>

  {{-- 8) PANEL DE SOLICITUDES (MANUAL) --}}
  <div class="sat-card">
    <div style="margin-bottom:10px">
      <div class="section-title">Panel de solicitudes</div>
      <div class="section-sub">Genera nuevas solicitudes de descarga manuales</div>
    </div>

    @if(!$isProPlan)
      <div class="chip-plan" data-tip="FREE: 1 solicitud activa, periodos máximo 1 mes">
        <span aria-hidden="true">🆓</span>
        <span>Plan FREE · 1 solicitud · ≤ 1 mes</span>
      </div>
    @else
      <div class="chip-plan" data-tip="Cuota contratada de descargas SAT">
        <span aria-hidden="true">✅</span>
        <span>Cuota actual · hasta {{ $asig }} solicitudes por RFC</span>
      </div>
    @endif

    <form id="reqForm" method="post" action="{{ $rtReqCreate }}" class="filters" style="margin-top:10px">
      @csrf
      <select class="select" name="tipo" aria-label="Tipo de CFDI">
        <option value="emitidos">Emitidos</option>
        <option value="recibidos">Recibidos</option>
        <option value="ambos">Ambos</option>
      </select>

      {{-- from / to para SatDescargaController::requestList --}}
      <input class="input" type="date" name="from" aria-label="Desde" required>
      <input class="input" type="date" name="to" aria-label="Hasta" required>

      {{-- Buscador de RFCs --}}
      <input class="input" type="text" id="rfcSearch" placeholder="Buscar RFC / razón social" aria-label="Buscar RFC">

      {{-- Selección múltiple de RFCs --}}
      <select class="select" name="rfcs[]" id="satRfcs" aria-label="RFCs" multiple required
              data-tip="Selecciona uno o varios RFC para esta solicitud">
        @foreach($credList as $c)
          @php
            $rf    = strtoupper($c['rfc'] ?? ($c->rfc ?? ''));
            $alias = $c['razon_social'] ?? ($c->razon_social ?? '');
          @endphp
          <option value="{{ $rf }}">
            {{ $rf }} @if($alias) · {{ $alias }} @endif
          </option>
        @endforeach
      </select>

      <button class="btn primary" type="submit" data-tip="Crear solicitud">
        <span aria-hidden="true">⤵️</span><span>Solicitar</span>
      </button>
      <button type="button" class="btn" id="btnSatVerify" data-tip="Verificar estado de solicitudes">
        <span aria-hidden="true">🔄</span><span>Verificar</span>
      </button>
    </form>

    @if(!$isProPlan)
      <p class="text-muted" style="margin-top:6px">
        En el plan FREE puedes solicitar hasta <b>1 mes de rango</b> por ejecución
        y un volumen limitado de solicitudes simultáneas.
      </p>
    @endif
  </div>

  {{-- 9) AUTOMATIZADAS --}}
  <div class="sat-card">
    <div class="auto-header">
      <div>
        <div class="section-title">Automatizadas</div>
        <div class="section-sub">Programación de descargas periódicas por RFC</div>
      </div>
      @if(!$isProPlan)
        <div class="badge-plan-lock">Solo PRO</div>
      @else
        <div class="badge-plan-ok">Incluido en tu plan PRO</div>
      @endif
    </div>

    <div class="auto-grid {{ !$isProPlan ? 'is-locked' : '' }}">
      <button type="button" class="btn auto-btn" {{ !$isProPlan ? 'disabled' : '' }}>
        <span aria-hidden="true">⏱</span><span>Diario</span>
      </button>
      <button type="button" class="btn auto-btn" {{ !$isProPlan ? 'disabled' : '' }}>
        <span aria-hidden="true">📅</span><span>Semanal</span>
      </button>
      <button type="button" class="btn auto-btn" {{ !$isProPlan ? 'disabled' : '' }}>
        <span aria-hidden="true">🗓</span><span>Mensual</span>
      </button>
      <button type="button" class="btn auto-btn" {{ !$isProPlan ? 'disabled' : '' }}>
        <span aria-hidden="true">⚙️</span><span>Por rango</span>
      </button>
    </div>

    <p class="text-muted" style="margin-top:8px">
      @if(!$isProPlan)
        Estas opciones se activan al contratar el plan PRO. Cada ejecución automática consume solicitudes de tu cuota contratada.
      @else
        Tus automatizaciones están activas. Cada ejecución automática consumirá solicitudes de tu cuota contratada de descargas SAT.
      @endif
    </p>
  </div>

  {{-- 10) BÓVEDA FISCAL (resumen rápido) --}}
  <div class="sat-card">
    <div class="vault-mini-header">
      <div>
        <div class="section-title">Vista rápida · Bóveda fiscal</div>
        <div class="section-sub">Resumen de CFDI guardados</div>
      </div>
      <a class="btn soft" href="{{ $rtVault }}" data-tip="Abrir bóveda completa">
        <span aria-hidden="true">↗</span><span>Ver bóveda</span>
      </a>
    </div>

    <form class="filters" style="margin-bottom:8px">
      <select class="select" aria-label="Tipo CFDI">
        <option>Ambos</option>
        <option>Emitidos</option>
        <option>Recibidos</option>
      </select>
      <input class="input" type="date" aria-label="Desde">
      <input class="input" type="date" aria-label="Hasta">
      <input class="input" type="text" placeholder="RFC / UUID / Razón social" aria-label="Buscar">
      <button class="btn" type="button">
        <span aria-hidden="true">🔍</span><span>Filtrar</span>
      </button>
    </form>

    <div class="v-totals">
      <div class="v-pill">CFDI: <b id="tCnt">0</b></div>
      <div class="v-pill">Subtotal: <b id="tSub">$0.00</b></div>
      <div class="v-pill">IVA: <b id="tIva">$0.00</b></div>
      <div class="v-pill">Total: <b id="tTot">$0.00</b></div>
    </div>

    <div class="table-wrap" style="margin-top:8px">
      <table class="table">
        <thead>
        <tr>
          <th>Fecha</th>
          <th>RFC</th>
          <th>Razón social</th>
          <th class="t-right">Total</th>
          <th>UUID</th>
          <th>Acciones</th>
        </tr>
        </thead>
        <tbody>
        <tr>
          <td colspan="6" class="empty">
            Vista rápida. Usa la Bóveda Fiscal para navegar y descargar CFDI a detalle.
          </td>
        </tr>
        </tbody>
      </table>
    </div>
  </div>

</div>

{{-- MODAL: AGREGAR RFC / CSD --}}
<div class="sat-modal-backdrop" id="modalRfc">
  <div class="sat-modal">
    <div class="sat-modal-header">
      <div>
        <div class="sat-modal-kicker">Conexiones SAT · CSD</div>
        <div class="sat-modal-title">Agregar RFC</div>
        <p class="sat-modal-sub">
          Registra un nuevo RFC y, si quieres, sube el CSD para empezar a descargar CFDI.
        </p>
      </div>
      <button type="button"
              class="sat-modal-close"
              data-close="modal-rfc"
              aria-label="Cerrar">
        ✕
      </button>
    </div>

    <form id="formRfc">
      @csrf
      <div class="sat-modal-body">
        <div class="sat-field">
          <div class="sat-field-label">RFC</div>
          <input class="input"
                 type="text"
                 name="rfc"
                 maxlength="13"
                 placeholder="AAA010101AAA"
                 required>
        </div>

        <div class="sat-field">
          <div class="sat-field-label">Nombre o razón social (alias)</div>
          <input class="input"
                 type="text"
                 name="alias"
                 maxlength="190"
                 placeholder="Razón social para identificar el RFC">
        </div>

        <hr class="sat-modal-sep">

        <div class="sat-field sat-field-inline">
          <div>
            <div class="sat-field-label">Certificado (.cer)</div>
            <input class="input"
                   type="file"
                   name="cer"
                   accept=".cer">
          </div>
          <div>
            <div class="sat-field-label">Llave privada (.key)</div>
            <input class="input"
                   type="file"
                   name="key"
                   accept=".key">
          </div>
        </div>

        <div class="sat-field">
          <div class="sat-field-label">Contraseña de la llave</div>
          <input class="input"
                 type="password"
                 name="key_password"
                 autocomplete="new-password"
                 placeholder="••••••••">
        </div>

        <p class="sat-modal-note">
          Si no adjuntas <b>.cer</b> y <b>.key</b>, sólo se registrará el RFC.
          Si adjuntas archivos y contraseña, se validará el <b>CSD</b> contra el SAT.
        </p>
      </div>

      <div class="sat-modal-footer">
        <button type="button" class="btn" data-close="modal-rfc">Cancelar</button>
        <button type="submit" class="btn primary">Guardar</button>
      </div>
    </form>
  </div>
</div>
@endsection


@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const SAT_ROUTES = {
  charts: @json($rtCharts),
  csdStore: @json($rtCsdStore),
  rfcReg: @json($rtRfcReg),
  verify: @json($rtVerify),
  download: @json($rtPkgPost),
};
const SAT_ZIP_PATTERN   = @json($rtZipGet);
const SAT_DOWNLOAD_ROWS = @json($rowsInit);
const SAT_CSRF = '{{ csrf_token() }}';

const SAT_VAULT = {
  used:      {{ json_encode($vaultUsedGb) }},
  free:      {{ json_encode($vaultAvailableGb) }},
  quota:     {{ json_encode($vaultQuotaGb) }},
  used_pct:  {{ json_encode($vaultUsedPct) }},
};
</script>
<script>
/* ===== LISTADO DESCARGAS: filtros + descarga ZIP + pago ===== */
(() => {
  const rows      = Array.isArray(SAT_DOWNLOAD_ROWS) ? SAT_DOWNLOAD_ROWS : [];
  const body      = document.getElementById('satDlBody');
  const qInput    = document.getElementById('satDlSearch');
  const tipoSel   = document.getElementById('satDlTipo');
  const statusSel = document.getElementById('satDlStatus');

  let timersInterval = null;

  function normalizeStatus(s){
    s = (s || '').toString().toLowerCase();
    if (['ready','done','listo'].includes(s)) return 'done';
    if (['pending_payment','payment_pending'].includes(s)) return 'pay';
    if (['expired','rechazado','rejected'].includes(s)) return 'expired';
    return s;
  }

  function formatMoney(n) {
    const v = Number(n || 0);
    if (!v) return '—';
    try {
      return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        maximumFractionDigits: 2,
      }).format(v);
    } catch (_) {
      return '$' + v.toFixed(2);
    }
  }

  function pad2(n){
    n = Math.floor(n);
    return n < 10 ? '0'+n : ''+n;
  }

  function updateTimers(){
    const items = document.querySelectorAll('.sat-timer[data-expires]');
    if (!items.length) return;

    const now = Date.now();
    items.forEach(el => {
      const exp = el.dataset.expires;
      if (!exp) return;
      const t = Date.parse(exp);
      if (isNaN(t)) return;
      let diff = Math.floor((t - now) / 1000);
      if (diff <= 0){
        el.textContent = 'Expirada';
        el.classList.add('expired');
        return;
      }
      const h = Math.floor(diff / 3600);
      diff   -= h * 3600;
      const m = Math.floor(diff / 60);
      const s = diff - m * 60;
      el.textContent = `${pad2(h)}:${pad2(m)}:${pad2(s)}`;
    });
  }

  function ensureTimers(){
    if (timersInterval !== null) return;
    timersInterval = setInterval(updateTimers, 1000);
  }

  function render(){
    if (!body) return;
    const q      = (qInput?.value || '').toLowerCase();
    const ftipo  = (tipoSel?.value || '').toLowerCase();
    const fstat  = (statusSel?.value || '').toLowerCase();

    body.innerHTML = '';

    if (!rows.length){
      const tr = document.createElement('tr');
      const td = document.createElement('td');
      td.colSpan = 9;
      td.className = 'empty';
      td.textContent = 'Aún no has generado solicitudes de descarga.';
      tr.appendChild(td);
      body.appendChild(tr);
      return;
    }

    rows.forEach(r => {
      const idRaw     = r.dlid || r.id || '';
      const idStr     = idRaw.toString();
      const solicitud = idStr ? idStr.padStart(2,'0') : '';

      const rfc       = (r.rfc || '').toString();
      const alias     = (r.alias || r.razon_social || r.razon || '').toString();
      const tipo      = (r.tipo || '').toString().toLowerCase();
      const statusRaw = (r.estado || r.status || '').toString();
      const status    = normalizeStatus(statusRaw);

      // filtros
      if (q) {
        const qq = q.toLowerCase();
        if (
          !solicitud.toLowerCase().includes(qq) &&
          !rfc.toLowerCase().includes(qq) &&
          !(alias && alias.toLowerCase().includes(qq))
        ) {
          return;
        }
      }
      if (ftipo && tipo !== ftipo) return;
      if (fstat && status !== fstat) return;

      // campos de fecha / periodo
      const fecha = (r.fecha || r.created_at || '').toString();
      const periodo = (r.desde || '') && (r.hasta || '')
        ? `${r.desde} - ${r.hasta}`
        : '';

      // peso
      let pesoLabel = '—';
      if (r.size_gb != null) {
        const g = Number(r.size_gb) || 0;
        if (g > 0) pesoLabel = g.toFixed(1) + ' Gb';
      } else if (r.size_bytes != null) {
        const g = Number(r.size_bytes) / (1024*1024*1024);
        if (g > 0) pesoLabel = g.toFixed(1) + ' Gb';
      } else if (r.files_count != null) {
        pesoLabel = Number(r.files_count) + ' XML';
      }

      const costo = r.costo_mxn ?? r.costo ?? 0;

      const expires = r.expires_at || r.vigente_hasta || '';

      let disponibilidadHtml = '';
      if (status === 'pay' && expires) {
        disponibilidadHtml = `<span class="sat-timer" data-expires="${expires}"></span>`;
      } else if (expires) {
        disponibilidadHtml = `<span>${expires}</span>`;
      } else {
        disponibilidadHtml = '<span>—</span>';
      }

      let accionesHtml = '';
      if (status === 'done') {
        accionesHtml = `
          <button
            class="sat-dl-btn-download"
            data-id="${idStr}"
            data-status="${status}"
            title="Descargar ZIP"
          >⬇</button>
        `;
      } else if (status === 'pay') {
        accionesHtml = `
          <button
            class="sat-dl-btn-pay"
            data-id="${idStr}"
            title="Pagar ahora"
          >Pagar ahora</button>
          <button
            class="sat-dl-btn-cancel"
            data-id="${idStr}"
            title="Eliminar solicitud"
          >🗑</button>
        `;
      } else if (status === 'expired') {
        accionesHtml = `<span class="sat-status-label expired">Expirada</span>`;
      } else if (status === 'error') {
        accionesHtml = `
          <button
            class="sat-dl-btn-error"
            data-id="${idStr}"
            title="Ver detalle de error"
          >TXT error</button>
        `;
      } else {
        accionesHtml = `<span class="sat-status-label">${status || 'Pendiente'}</span>`;
      }

      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td class="mono">${solicitud}</td>
        <td>${fecha || '—'}</td>
        <td>${periodo || '—'}</td>
        <td class="mono">${rfc}</td>
        <td>${alias || '—'}</td>
        <td>${pesoLabel}</td>
        <td class="t-right">${formatMoney(costo)}</td>
        <td>${disponibilidadHtml}</td>
        <td class="t-right">${accionesHtml}</td>
      `;
      body.appendChild(tr);
    });

    // Iniciar / actualizar timers
    updateTimers();
    ensureTimers();
  }

  if (qInput)    qInput.addEventListener('input',  render);
  if (tipoSel)   tipoSel.addEventListener('change', render);
  if (statusSel) statusSel.addEventListener('change', render);

  if (body) {
    body.addEventListener('click', async (ev) => {
      const btnDownload = ev.target.closest('.sat-dl-btn-download');
      const btnPay      = ev.target.closest('.sat-dl-btn-pay');
      const btnCancel   = ev.target.closest('.sat-dl-btn-cancel');
      const btnError    = ev.target.closest('.sat-dl-btn-error');

      if (btnDownload) {
        const id     = btnDownload.dataset.id;
        const status = (btnDownload.dataset.status || '').toLowerCase();
        if (!id) return;

        // 1) Si ya está "done", abrimos directamente el ZIP
        if (status === 'done' && SAT_ZIP_PATTERN && SAT_ZIP_PATTERN !== '#') {
          const zipUrl = SAT_ZIP_PATTERN.replace('__ID__', id);
          window.location.href = zipUrl;
          return;
        }

        // 2) Si no está listo, llamamos al endpoint /sat/download
        if (!SAT_ROUTES.download || SAT_ROUTES.download === '#') return;

        btnDownload.disabled = true;

        try{
          const fd = new FormData();
          fd.append('download_id', id);

          const res = await fetch(SAT_ROUTES.download, {
            method:'POST',
            headers:{
              'X-Requested-With':'XMLHttpRequest',
              'X-CSRF-TOKEN': SAT_CSRF,
            },
            body: fd,
          });

          const ct = res.headers.get('content-type') || '';
          let json = null;
          if (ct.includes('application/json')) {
            json = await res.json().catch(()=>null);
          }

          if (!res.ok) {
            alert(json?.msg || 'No se pudo preparar el ZIP.');
            return;
          }

          if (json && json.zip_url) {
            window.location.href = json.zip_url;
          } else {
            location.reload();
          }
        }catch(e){
          console.error(e);
          alert('Error de conexión al descargar.');
        }finally{
          btnDownload.disabled = false;
        }
        return;
      }

      if (btnPay) {
        const id = btnPay.dataset.id;
        alert('Aquí conectaremos el flujo de pago para la solicitud #' + id);
        return;
      }

      if (btnCancel) {
        const id = btnCancel.dataset.id;
        alert('Aquí cancelaremos / eliminaremos la solicitud #' + id);
        return;
      }

      if (btnError) {
        const id = btnError.dataset.id;
        alert('Aquí descargaremos el TXT de error de la solicitud #' + id);
        return;
      }
    });
  }

  render();
})();

/* ===== Actualizar pantalla ===== */
(() => {
  const btn = document.getElementById('btnRefresh');
  if(!btn) return;
  btn.addEventListener('click', () => {
    location.reload();
  });
})();

/* ===== Verificar solicitudes (POST) ===== */
(() => {
  const btn = document.getElementById('btnSatVerify');
  if(!btn || !SAT_ROUTES.verify) return;
  btn.addEventListener('click', async () => {
    btn.disabled = true;
    try{
      const r = await fetch(SAT_ROUTES.verify, {
        method:'POST',
        headers:{
          'X-Requested-With':'XMLHttpRequest',
          'X-CSRF-TOKEN':SAT_CSRF
        }
      });
      const j = await r.json();
      alert(`Pendientes: ${j.pending ?? 0} · Listos: ${j.ready ?? 0}`);
      location.reload();
    }catch(e){
      console.error(e);
      alert('No se pudo verificar');
    }finally{
      btn.disabled = false;
    }
  });
})();

/* ===== Regla FREE: ≤ 1 mes ===== */
(() => {
  const planIsPro = @json($isProPlan);
  const f = document.getElementById('reqForm');
  if(!f || planIsPro) return;
  f.addEventListener('submit', ev=>{
    const d = new FormData(f);
    const a = new Date(d.get('from'));
    const b = new Date(d.get('to'));
    if(isNaN(+a) || isNaN(+b)) return;
    if((b - a) > 32*24*3600*1000){
      ev.preventDefault();
      alert('En FREE sólo puedes solicitar hasta 1 mes.');
    }
  });
})();

/* ===== Modal RFC (abrir / cerrar / submit) ===== */
(() => {
  const modal = document.getElementById('modalRfc');
  const form  = document.getElementById('formRfc');
  if(!modal || !form) return;

  const open  = () => modal.classList.add('is-open');
  const close = () => modal.classList.remove('is-open');

  document.addEventListener('click',(ev)=>{
    const openBtn  = ev.target.closest('[data-open="add-rfc"]');
    const closeBtn = ev.target.closest('[data-close="modal-rfc"]');
    if(openBtn){
      ev.preventDefault();
      open();
    }
    if(closeBtn || ev.target === modal){
      ev.preventDefault();
      close();
    }
  });

  window.addEventListener('sat-open-add-rfc', open);

  form.addEventListener('submit', async (ev)=>{
    ev.preventDefault();
    const submitBtn = form.querySelector('button[type="submit"]');
    if(submitBtn) submitBtn.disabled = true;

    const fd = new FormData(form);
    const hasCer = fd.get('cer') instanceof File && fd.get('cer').name;
    const hasKey = fd.get('key') instanceof File && fd.get('key').name;
    const pwd    = (fd.get('key_password') || '').toString().trim();
    const useCsd = (hasCer || hasKey || pwd !== '');
    const url    = useCsd ? SAT_ROUTES.csdStore : SAT_ROUTES.rfcReg;

    if(!url || url === '#'){
      alert('Ruta de guardado de RFC no configurada.');
      if(submitBtn) submitBtn.disabled = false;
      return;
    }

    try{
      const res = await fetch(url, {
        method:'POST',
        headers:{'X-Requested-With':'XMLHttpRequest'},
        body: fd
      });
      let data = {};
      try{ data = await res.json(); }catch(_){}
      if(!res.ok || (data.ok === false)){
        const msg = data.msg || 'No se pudo guardar el RFC / CSD';
        alert(msg);
        if(submitBtn) submitBtn.disabled = false;
        return;
      }
      close();
      location.reload();
    }catch(e){
      console.error(e);
      alert('Error enviando datos');
    }finally{
      if(submitBtn) submitBtn.disabled = false;
    }
  });
})();

/* ===== Charts con datos reales (endpoint /sat/charts) ===== */
(() => {
  if(!SAT_ROUTES.charts) return;

  // Chart principal (encima, en el bloque resumen)
  const trendMainEl = document.getElementById('satTrendMain');
  let chartMain = null;

  const mkMain = () => {
    if (!trendMainEl || !window.Chart) return null;
    return new Chart(trendMainEl, {
      type:'line',
      data:{labels:[],datasets:[{
        label:'Archivos descargados',
        data:[],
        borderWidth:2,
        tension:.35,
        pointRadius:4,
        pointHoverRadius:5,
      }]},
      options:{
        responsive:true,
        maintainAspectRatio:false,
        plugins:{legend:{display:false}},
        scales:{
          x:{grid:{display:false}},
          y:{grid:{color:'rgba(148,163,184,.25)'}}
        }
      }
    });
  };

  const mk = (id,label)=> {
    const el = document.getElementById(id);
    if(!el) return null;
    return new Chart(el, {
      type:'line',
      data:{labels:[],datasets:[{
        label,
        data:[],
        borderWidth:2,
        tension:.35,
        pointRadius:4,
        pointHoverRadius:5,
      }]},
      options:{
        responsive:true,
        maintainAspectRatio:false,
        plugins:{legend:{display:true}},
        scales:{
          x:{grid:{display:false}},
          y:{grid:{color:'rgba(148,163,184,.25)'}}
        }
      }
    });
  };

  const chartA = mk('chartA','Importe total');
  const chartB = mk('chartB','# CFDI');
  chartMain = mkMain();

  const loadScope = async (scope) => {
    try{
      const url = SAT_ROUTES.charts + '?scope=' + encodeURIComponent(scope || 'emitidos');
      const res = await fetch(url, {headers:{'X-Requested-With':'XMLHttpRequest'}});
      if(!res.ok) throw new Error('HTTP '+res.status);
      const j = await res.json();
      const labels  = j.labels || [];
      const series  = j.series || {};
      const amounts = series.amounts || [];
      const counts  = series.counts  || [];

      if (chartA && chartB) {
        chartA.data.labels = labels;
        chartB.data.labels = labels;

        chartA.data.datasets[0].label = series.label_amount || 'Importe total';
        chartB.data.datasets[0].label = series.label_count  || '# CFDI';

        chartA.data.datasets[0].data = amounts;
        chartB.data.datasets[0].data = counts;

        chartA.update();
        chartB.update();
      }

      if (chartMain) {
        chartMain.data.labels = labels;
        chartMain.data.datasets[0].data = counts;
        chartMain.update();
      }
    }catch(e){
      console.error(e);
    }
  };

  loadScope('emitidos');

  document.querySelectorAll('.tab').forEach(t=>{
    t.addEventListener('click',()=>{
      document.querySelectorAll('.tab').forEach(x=>x.classList.remove('is-active'));
      t.classList.add('is-active');
      loadScope(t.dataset.scope || 'emitidos');
    });
  });
})();

/* ===== DONUT DE BÓVEDA FISCAL ===== */
(() => {
  const el = document.getElementById('vaultDonut');
  if(!el || !window.Chart || !SAT_VAULT) return;

  const used = Number(SAT_VAULT.used || 0);
  const free = Number(SAT_VAULT.free || 0);

  new Chart(el, {
    type:'doughnut',
    data:{
      labels:['Consumido','Disponible'],
      datasets:[{
        data:[used, free],
        borderWidth:0,
        backgroundColor:['#ec4899','#e5e7eb'],
      }]
    },
    options:{
      responsive:true,
      maintainAspectRatio:false,
      cutout:'70%',
      plugins:{
        legend:{display:false},
        tooltip:{enabled:true}
      }
    }
  });
})();

/* ===== Buscador de RFCs en select múltiple ===== */
(() => {
  const input  = document.getElementById('rfcSearch');
  const select = document.getElementById('satRfcs');
  if (!input || !select) return;

  const original = Array.from(select.options).map(o => ({
    value: o.value,
    text:  o.text,
    selected: o.selected,
  }));

  input.addEventListener('input', () => {
    const q = input.value.toLowerCase();
    select.innerHTML = '';

    original.forEach(o => {
      if (!q || o.text.toLowerCase().includes(q) || o.value.toLowerCase().includes(q)) {
        const opt = document.createElement('option');
        opt.value   = o.value;
        opt.text    = o.text;
        opt.selected= o.selected;
        select.appendChild(opt);
      }
    });
  });
})();
</script>
@endpush
