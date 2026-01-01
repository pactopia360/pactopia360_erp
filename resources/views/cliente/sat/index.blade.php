{{-- resources/views/cliente/sat/index.blade.php (v3.2) --}}
@extends('layouts.cliente')
@section('title','SAT · Descargas masivas CFDI')

{{-- importante para usar el ancho completo --}}
@section('pageClass','page-sat')

@php
  // ======================================================
  //  Usuario / cuenta
  // ======================================================
  $user   = $user   ?? auth('web')->user();
  $cuenta = $cuenta ?? ($user?->cuenta ?? null);

  // ======================================================
  //  Resumen unificado de cuenta (admin.accounts)
  // ======================================================
  $summary = $summary ?? app(\App\Http\Controllers\Cliente\HomeController::class)->buildAccountSummary();

  $planFromSummary = strtoupper((string)($summary['plan'] ?? 'FREE'));
  $isProSummary    = (bool)($summary['is_pro'] ?? in_array(
      strtolower($planFromSummary),
      ['pro','premium','empresa','business'],
      true
  ));

  $plan      = $planFromSummary;
  $isProPlan = $isProSummary;
  $isPro     = $isProPlan;

  // ======================================================
  //  Datos base
  // ======================================================
  $credList = collect($credList ?? []);
  $rowsInit = $initialRows ?? [];

  // Modo DEMO/PROD por cookie/entorno
  $cookieMode  = request()->cookie('sat_mode');
  $driver      = config('services.sat.download.driver','satws');
  $isLocalDemo = app()->environment(['local','development','testing']) && $driver !== 'satws';

  $mode      = $cookieMode ? strtolower($cookieMode) : ($isLocalDemo ? 'demo' : 'prod');
  $modeSafe  = $mode ?: 'prod';
  $modeLabel = strtoupper($modeSafe === 'demo' ? 'FUENTE: DEMO' : 'FUENTE: PRODUCCIÓN');

  // ======================================================
  //  Cuotas SAT (por cuenta)
  // ======================================================
  $asigDefault = $isProPlan ? 12 : 1;
  $asig        = (int)($cuenta->sat_quota_assigned ?? $asigDefault);
  $usadas      = (int)($cuenta->sat_quota_used ?? 0);
  $pct         = $asig > 0 ? min(100, max(0, (int) round(($usadas / $asig) * 100))) : 0;

  // ======================================================
  //  Rows SAT (filtradas) - NO mostrar compras de bóveda
  // ======================================================
  $periodFrom = now()->subDays(30);

  $rowsColl = collect($rowsInit ?? [])
    ->map(fn($r) => is_array($r) ? (object)$r : $r)
    ->filter(function ($row) {
        $tipoRaw = strtolower((string) data_get($row, 'tipo', data_get($row, 'origen', '')));
        if (str_contains($tipoRaw, 'vault') || str_contains($tipoRaw, 'boveda') || str_contains($tipoRaw, 'bóveda')) {
            return false;
        }
        return true;
    });

  // ======================================================
  //  Métricas dashboard (preferir summary si viene)
  // ======================================================
  $filesTotal = (int)($summary['sat_files_total'] ?? $rowsColl->count());

  $filesPeriod = (int)($summary['sat_files_period'] ?? $rowsColl->filter(function ($row) use ($periodFrom) {
      $created = data_get($row, 'created_at', data_get($row, 'createdAt', null));
      if (!$created) return false;
      try {
          return \Illuminate\Support\Carbon::parse($created)->greaterThanOrEqualTo($periodFrom);
      } catch (\Exception $e) {
          return false;
      }
  })->count());

  $rfcsValidated = (int)($summary['sat_rfcs_validated'] ?? $credList->filter(function ($c) {
      $estatusRaw = strtolower((string) data_get($c, 'estatus', ''));
      $okFlag = !empty(data_get($c,'validado'))
             || !empty(data_get($c,'validated_at'))
             || !empty(data_get($c,'has_files'))
             || !empty(data_get($c,'has_csd'))
             || !empty(data_get($c,'cer_path'))
             || !empty(data_get($c,'key_path'));

      return $okFlag || in_array($estatusRaw, ['ok','valido','válido','validado'], true);
  })->count());

  $rfcsPending = max(0, (int)$credList->count() - (int)$rfcsValidated);

  $downloadsTotal  = (int)$rowsColl->count();
  $downloadsPeriod = (int)$filesPeriod;

  $reqStart     = (int)($summary['sat_req_start']    ?? $asig);
  $reqDone      = (int)($summary['sat_req_done']     ?? $downloadsTotal);
  $reqPeriod    = (int)($summary['sat_req_period']   ?? $downloadsPeriod);
  $reqAvailable = (int)($summary['sat_req_available'] ?? max(0, $reqStart - $reqDone));

  // ======================================================
  //  Bóveda fiscal (GB) (normalización defensiva)
  // ======================================================
  $vaultCfg = $vault ?? [];

  $vaultQuotaGbFromCtrl = isset($vault_quota_gb) ? (float)$vault_quota_gb : 0.0;
  $vaultUsedGbFromCtrl  = isset($vault_used_gb)  ? (float)$vault_used_gb  : 0.0;

  $vaultQuotaGb = (float)($vaultCfg['quota_gb'] ?? $vaultQuotaGbFromCtrl);
  $vaultUsedGb  = (float)($vaultCfg['used_gb']  ?? $vaultUsedGbFromCtrl);
  if ($vaultUsedGb < 0) $vaultUsedGb = 0.0;

  $vaultAvailableGb = (float)($vaultCfg['available_gb'] ?? max(0.0, $vaultQuotaGb - $vaultUsedGb));
  $EPS = 0.000001;

  $vaultUsedPct = (float)($vaultCfg['used_pct']
      ?? ($vaultQuotaGb > 0 ? min(100.0, ($vaultUsedGb / max($vaultQuotaGb, $EPS)) * 100.0) : 0.0));

  $vaultActive = (bool)($vaultCfg['enabled'] ?? ($vaultQuotaGb > 0));
  $vaultBaseGb = (float)($vaultCfg['base_gb'] ?? 0.0);
  $vaultPurchasedGb = (float)($vaultCfg['purchased_gb'] ?? max(0.0, $vaultQuotaGb - $vaultBaseGb));

  $vault = array_merge([
      'enabled'       => $vaultActive,
      'quota_gb'      => $vaultQuotaGb,
      'base_gb'       => $vaultBaseGb,
      'purchased_gb'  => $vaultPurchasedGb,
      'used_gb'       => $vaultUsedGb,
      'available_gb'  => $vaultAvailableGb,
      'used_pct'      => $vaultUsedPct,
      'used'          => $vaultUsedGb,
      'free'          => $vaultAvailableGb,
      'files_count'   => (int)($vaultCfg['files_count'] ?? 0),
  ], $vaultCfg);

  // ------------------------------------------------------
  // Rutas (defensivas)
  // ------------------------------------------------------
  $rtCsdStore   = \Route::has('cliente.sat.credenciales.store') ? route('cliente.sat.credenciales.store') : '#';
  $rtReqCreate  = \Route::has('cliente.sat.request')            ? route('cliente.sat.request')            : '#';
  $rtVerify     = \Route::has('cliente.sat.verify')             ? route('cliente.sat.verify')             : '#';
  $rtPkgPost    = \Route::has('cliente.sat.download')           ? route('cliente.sat.download')           : '#';
  $rtZipGet     = \Route::has('cliente.sat.zip')                ? route('cliente.sat.zip',['id'=>'__ID__']) : '#';
  $rtReport     = \Route::has('cliente.sat.report')             ? route('cliente.sat.report')             : '#';
  $rtVault      = \Route::has('cliente.sat.vault')              ? route('cliente.sat.vault')              : '#';
  $rtMode       = \Route::has('cliente.sat.mode')               ? route('cliente.sat.mode')               : null;
  $rtCharts     = \Route::has('cliente.sat.charts')             ? route('cliente.sat.charts')             : null;

  // FIX: si no existe la ruta quick, no romper (antes daba error de method)
  $rtVaultQuick = \Route::has('cliente.sat.vault.quick') ? route('cliente.sat.vault.quick') : null;

  // Guardar en bóveda desde descarga pagada
  $rtVaultFromDownload = \Route::has('cliente.sat.vault.fromDownload')
      ? route('cliente.sat.vault.fromDownload', ['download' => '__ID__'])
      : null;

  // RFCs
  $rtAlias          = \Route::has('cliente.sat.alias')            ? route('cliente.sat.alias')            : '#';
  $rtRfcReg         = \Route::has('cliente.sat.rfc.register')     ? route('cliente.sat.rfc.register')     : '#';
  $rtRfcDelete      = \Route::has('cliente.sat.rfc.delete')       ? route('cliente.sat.rfc.delete')       : '#';
  $rtDownloadCancel = \Route::has('cliente.sat.download.cancel')  ? route('cliente.sat.download.cancel')  : null;

  // Carrito SAT
  $rtCartIndex  = \Route::has('cliente.sat.cart.index')  ? route('cliente.sat.cart.index')  : null;
  $rtCartAdd    = \Route::has('cliente.sat.cart.add')    ? route('cliente.sat.cart.add')    : null;
  $rtCartRemove = \Route::has('cliente.sat.cart.remove') ? route('cliente.sat.cart.remove') : null;
  $rtCartList   = \Route::has('cliente.sat.cart.list')   ? route('cliente.sat.cart.list')   : null;

  $rtCartPay = \Route::has('cliente.sat.cart.checkout')
      ? route('cliente.sat.cart.checkout')
      : null;

  $zipPattern = $rtZipGet ?? '#';

  $vaultCtaUrl = $rtCartIndex
      ?? $rtCartPay
      ?? $rtCartList
      ?? $rtVault
      ?? '#';
@endphp

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/client/css/sat-dashboard.css') }}">
@endpush

@section('content')
<div class="sat-ui" id="satApp" data-plan="{{ $plan }}" data-mode="{{ $modeSafe }}">
  {{-- 0) TÍTULO + MODO DEMO/PROD --}}
  <div class="sat-header-top">
    <div class="sat-title-wrap">
      <div class="sat-icon">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="4" y="4" width="16" height="16" rx="3"></rect>
          <path d="M4 10h16M10 4v4"></path>
        </svg>
      </div>
      <div>
        <div class="sat-title-main">Descargas masivas</div>
        <div class="sat-title-sub">CFDI EMITIDOS Y RECIBIDOS · CONTROL CENTRAL</div>
        <div class="sat-mode-label">{{ $modeLabel ?? 'FUENTE: PRODUCCIÓN' }}</div>
      </div>
    </div>

    <div class="sat-actions">
      @if($rtMode)
        <button
          type="button"
          id="badgeMode"
          data-url="{{ $rtMode }}"
          class="mode-switch {{ ($modeSafe === 'demo') ? 'is-demo' : 'is-prod' }}"
          data-tip="{{ ($modeSafe === 'demo') ? 'Modo DEMO (click para cambiar a PRODUCCIÓN)' : 'Modo PRODUCCIÓN (click para cambiar a DEMO)' }}"
          aria-label="{{ ($modeSafe === 'demo') ? 'Cambiar a producción' : 'Cambiar a modo demo' }}"
        >
          <span class="mode-pill mode-pill-demo">Demo</span>
          <span class="mode-pill mode-pill-prod">Prod</span>
        </button>
      @endif
    </div>
  </div>

  {{-- 1) CONTADORES TOP --}}
  <div class="sat-card sat-card-header">
    <div class="sat-top-grid">
      <div class="top-card">
        <div class="top-card-header">Archivos descargados en el periodo</div>
        <div class="top-card-main">{{ number_format($filesPeriod) }}</div>
        <div class="top-card-foot top-card-foot-success">0.00% ▲</div>
      </div>

      <div class="top-card">
        <div class="top-card-header">Archivos descargados (totales)</div>
        <div class="top-card-main">{{ number_format($filesTotal) }}</div>
        <div class="top-card-caption">&nbsp;</div>
      </div>

      <div class="top-card">
        <div class="top-card-header">RFCs validados</div>
        <div class="top-card-main">{{ number_format($rfcsValidated) }}</div>
        <div class="top-card-caption">&nbsp;</div>
      </div>

      <div class="top-card">
        <div class="top-card-header">RFCs por validar</div>
        <div class="top-card-main">{{ number_format($rfcsPending) }}</div>
        <div class="top-card-caption">&nbsp;</div>
      </div>

      <div class="top-card top-card-cta">
        <div class="top-card-header">Peticiones realizadas</div>
        <div class="top-card-main">{{ number_format($reqDone) }}</div>
        <div class="top-card-cta-foot">
          @unless($isProPlan)
            <button type="button" class="btn btn-cta">Comprar ahora</button>
          @endunless
        </div>
      </div>
    </div>
  </div>

  {{-- 2) GRÁFICA + DETALLE MOVIMIENTOS --}}
  <div class="sat-card sat-movements-card">
    <div class="sat-mov-layout">
      <div class="sat-mov-chart">
        <div class="sat-mov-chart-header">
          <div class="sat-mov-chart-title">Últimas semanas</div>
          <div class="sat-mov-chart-controls">
            <div class="sat-mov-range" id="satMovRange">
              <input type="date" id="satMovFrom" class="sat-mov-range-input" aria-label="Desde">
              <input type="date" id="satMovTo" class="sat-mov-range-input" aria-label="Hasta">
              <button type="button" class="btn btn-range-apply" id="satMovApply">Aplicar</button>
            </div>
          </div>
        </div>
        <div class="sat-mov-chart-body">
          <canvas id="satMovChart"></canvas>
        </div>
      </div>

      <div class="sat-mov-detail">
        <div class="section-title">Detalle de movimientos</div>
        <dl class="sat-mov-dl">
          <div class="sat-mov-row">
            <dt>Peticiones al inicio del periodo:</dt>
            <dd>{{ number_format($reqStart) }}</dd>
          </div>
          <div class="sat-mov-row">
            <dt>Peticiones realizadas en el periodo:</dt>
            <dd>{{ number_format($reqPeriod) }}</dd>
          </div>
          <div class="sat-mov-row">
            <dt>Peticiones disponibles:</dt>
            <dd>{{ number_format($reqAvailable) }}</dd>
          </div>
          <div class="sat-mov-row">
            <dt>Archivos descargados:</dt>
            <dd>{{ number_format($filesPeriod) }}</dd>
          </div>
        </dl>
      </div>
    </div>
  </div>

  {{-- 3) Guías rápidas --}}
  <div class="sat-card" id="block-quick-guides">
    <div class="sat-quick-head">
      <div class="sat-quick-title-wrap">
        <div class="sat-quick-icon" aria-hidden="true">⚡</div>
        <div>
          <div class="sat-quick-kicker">Atajos SAT</div>
          <h3 class="sat-quick-title">Guías rápidas</h3>
          <p class="sat-quick-sub">
            Usa estos atajos para lanzar descargas y revisar tu información sin navegar por todo el módulo.
          </p>
        </div>
      </div>

      <div class="sat-quick-plan">
        @if($isPro ?? false)
          <span class="badge-mode prod"><span class="dot"></span> Plan PRO</span>
        @else
          <span class="badge-mode demo"><span class="dot"></span> Plan FREE</span>
        @endif
      </div>
    </div>

    <div class="pills-row sat-quick-pills">
      <button type="button" class="pill primary" id="btnQuickLast30" data-tip="Crear solicitud para últimos 30 días">
        <span aria-hidden="true">📥</span><span>Descargar últimos 30 días</span>
      </button>

      <button type="button" class="pill" id="btnQuickThisMonth" data-tip="Emitidos y recibidos del mes actual">
        <span aria-hidden="true">🗓️</span><span>Mes actual (emitidos + recibidos)</span>
      </button>

      <button type="button" class="pill" id="btnQuickOnlyEmitted" data-tip="Sólo CFDI emitidos del periodo rápido">
        <span aria-hidden="true">📤</span><span>Sólo emitidos (rápido)</span>
      </button>

      <a href="#mis-rfcs" class="pill" data-tip="Ir a la sección Mis RFCs">
        <span aria-hidden="true">🧩</span><span>Administrar RFCs</span>
      </a>

      <a href="{{ $rtVault }}" class="pill" data-tip="Abrir la Bóveda Fiscal">
        <span aria-hidden="true">📚</span><span>Bóveda fiscal</span>
      </a>
    </div>
  </div>

  {{-- 4) CONEXIONES SAT · RFCs --}}
  <div class="sat-card" id="block-rfcs">
    <div style="margin-bottom:10px"></div>

    @include('cliente.sat._partials.rfcs', [
      'credList'   => $credList,
      'plan'       => $plan,
      'rtCsdStore' => $rtCsdStore,
      'rtAlias'    => $rtAlias,
      'rtRfcReg'   => $rtRfcReg,
      'rtRfcDelete'=> $rtRfcDelete,
    ])
  </div>

  {{-- 5) SOLICITUDES + LISTADO --}}
  @php
    $rfcOptions = [];
    foreach ($credList as $c) {
        $rf = strtoupper((string) data_get($c,'rfc',''));
        if (!$rf) continue;

        $alias = trim((string) data_get($c,'razon_social', data_get($c,'alias','')));
        $estatusRaw = strtolower((string) data_get($c,'estatus',''));

        $okFlag = !empty(data_get($c,'validado'))
               || !empty(data_get($c,'validated_at'))
               || !empty(data_get($c,'has_files'))
               || !empty(data_get($c,'has_csd'))
               || !empty(data_get($c,'cer_path'))
               || !empty(data_get($c,'key_path'))
               || in_array($estatusRaw, ['ok','valido','válido','validado'], true);

        if (!$okFlag) continue;

        $rfcOptions[] = ['rf' => $rf, 'alias' => $alias];
    }
    $kRfc = count($rfcOptions);
  @endphp

  <section class="sat-section" id="block-requests-section">
    <div class="sat-card sat-req-dl-card" id="block-requests">
      <div class="sat-req-header">
        <div class="sat-req-title">
          <span class="sat-req-icon" aria-hidden="true">⬇️</span>
          <h3>Solicitudes SAT</h3>
        </div>
        <div class="sat-req-head-actions">
          <button type="button" class="btn icon-only" id="btnSatVerify" data-tip="Verificar estado de solicitudes">
            <span aria-hidden="true">🔄</span>
          </button>
          <button type="button" class="btn icon-only auto-modal-btn"
                  data-open="auto-modal"
                  data-tip="{{ $isProPlan ? 'Automatizar descargas' : 'Automatizaciones (solo PRO)' }}">
            <span aria-hidden="true">⏱</span>
            <span class="sr-only">Automatizar descargas</span>
          </button>
        </div>
      </div>

      <div class="sat-req-panel">
        <form id="reqForm" method="post" action="{{ $rtReqCreate }}" class="sat-req-form">
          @csrf
          <div class="sat-req-row">
            <div class="sat-req-field sat-req-field-sm">
              <label class="sat-req-label">Tipo</label>
              <select class="input" name="tipo" aria-label="Tipo de CFDI" id="reqTipo">
                <option value="emitidos">Emitidos</option>
                <option value="recibidos">Recibidos</option>
                <option value="ambos">Ambos</option>
              </select>
            </div>

            <div class="sat-req-field">
              <label class="sat-req-label">Desde</label>
              <input class="input" type="date" name="from" id="reqFrom" aria-label="Desde" required>
            </div>

            <div class="sat-req-field">
              <label class="sat-req-label">Hasta</label>
              <input class="input" type="date" name="to" id="reqTo" aria-label="Hasta" required>
            </div>

            <div class="sat-req-field sat-req-field-flex">
              <label class="sat-req-label">RFCs</label>

              <div class="sat-rfc-dropdown" id="satRfcDropdown">
                <button type="button" class="input sat-rfc-trigger" id="satRfcTrigger">
                  <span id="satRfcSummary">
                    @if($kRfc > 0)
                      (Todos los RFCs)
                    @else
                      (Sin RFCs validados)
                    @endif
                  </span>
                  <span class="sat-rfc-caret" aria-hidden="true">▾</span>
                </button>

                <div class="sat-rfc-menu" id="satRfcMenu">
                  <div class="sat-rfc-search">
                    <input type="text" id="satRfcFilter" placeholder="Filtrar RFC o alias">
                  </div>

                  @if($kRfc > 0)
                    <label class="sat-rfc-option sat-rfc-option-all">
                      <input type="checkbox" id="satRfcAll" checked>
                      <span>(Todos los RFCs)</span>
                    </label>

                    @foreach($rfcOptions as $opt)
                      <label class="sat-rfc-option">
                        <input type="checkbox" class="satRfcItem" value="{{ $opt['rf'] }}" checked>
                        <span class="mono">{{ $opt['rf'] }}</span>
                        @if($opt['alias'])
                          <span>· {{ $opt['alias'] }}</span>
                        @endif
                      </label>
                    @endforeach
                  @else
                    <div class="sat-rfc-empty">
                      No tienes RFCs validados. Valida al menos uno para poder crear solicitudes.
                    </div>
                  @endif
                </div>
              </div>

              <select name="rfcs[]" id="satRfcs" multiple hidden>
                @foreach($rfcOptions as $opt)
                  <option value="{{ $opt['rf'] }}" selected>{{ $opt['rf'] }}</option>
                @endforeach
              </select>
            </div>

            <div class="sat-req-field sat-req-field-btn">
              <label class="sat-req-label">&nbsp;</label>
              <button class="btn primary sat-req-submit"
                      type="submit"
                      @if($kRfc === 0) disabled @endif
                      data-tip="Crear solicitud">
                <span aria-hidden="true">⬇️</span>
                <span>Solicitar</span>
              </button>
            </div>
          </div>
        </form>

        @if(!$isProPlan)
          <p class="text-muted sat-req-note">
            En el plan FREE puedes solicitar hasta <b>1 mes de rango</b> por ejecución
            y solo se procesan RFCs validados.
          </p>
        @endif
      </div>

      <hr class="sat-req-divider">

      {{-- LISTADO DE DESCARGAS SAT (TABLA) --}}
      <div class="sat-dl-section" id="block-downloads">
        <div class="sat-dl-head">
          <div class="sat-dl-title">
            <h3>Listado de descargas SAT</h3>
            <p>Solicitudes generadas y paquetes listos para pago / descarga.</p>
          </div>

          <div class="sat-cart-compact" id="satCartWidget">
            <span class="sat-cart-label">Carrito SAT</span>
            <span class="sat-cart-pill">
              <span id="satCartCount">0</span> ítems ·
              <span id="satCartTotal">$0.00</span>
              · <span id="satCartWeight">0.00 MB</span>
            </span>
          </div>

          <div class="sat-dl-bulk" id="satDlBulk" style="display:none;">
            <div class="sat-dl-bulk-left">
              <div class="sat-dl-selection-pill" data-tip="Solicitudes seleccionadas">
                <span class="sat-dl-selection-count" id="satDlBulkCount">0</span>
              </div>
            </div>

            <div class="sat-dl-bulk-actions">
              <button type="button" class="sat-bulk-icon sat-bulk-icon-soft" id="satDlBulkRefresh" data-tip="Actualizar estados en SAT">
                <svg class="sat-bulk-svg" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M4.5 10.5a7.5 7.5 0 0 1 12.62-5.3L19.5 7.5M19.5 3v4.5H15M19.5 13.5a7.5 7.5 0 0 1-12.62 5.3L4.5 16.5M4.5 21V16.5H9"
                        fill="none" stroke="currentColor" stroke-width="1.7"
                        stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span class="sr-only">Actualizar</span>
              </button>

              <button type="button" class="sat-bulk-icon sat-bulk-icon-cart" id="satDlAddSelected" data-tip="Agregar seleccionados al carrito">
                <svg class="sat-bulk-svg" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M3.5 4.5h2l1.4 10h11.1l1.5-7.5H7.2"
                        fill="none" stroke="currentColor" stroke-width="1.7"
                        stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span class="sr-only">Agregar al carrito</span>
              </button>

              <button type="button" class="sat-bulk-icon sat-bulk-icon-primary" id="satDlBulkBuy" data-tip="Comprar seleccionados">
                <svg class="sat-bulk-svg" viewBox="0 0 24 24" aria-hidden="true">
                  <rect x="3.5" y="6" width="17" height="12" rx="2.2" ry="2.2"
                        fill="none" stroke="currentColor" stroke-width="1.7"/>
                  <path d="M4.5 9h16M8 13.25h4"
                        fill="none" stroke="currentColor" stroke-width="1.7"
                        stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span class="sr-only">Comprar</span>
              </button>

              <button type="button" class="sat-bulk-icon sat-bulk-icon-danger" id="satDlBulkDelete" data-tip="Eliminar seleccionados">
                <svg class="sat-bulk-svg" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M9.5 4.5h5M5.5 6h13M8 6v12a1.5 1.5 0 0 0 1.5 1.5h5A1.5 1.5 0 0 0 16 18V6"
                        fill="none" stroke="currentColor" stroke-width="1.7"
                        stroke-linecap="round" stroke-linejoin="round"/>
                  <path d="M10.2 9.5v6M13.8 9.5v6"
                        fill="none" stroke="currentColor" stroke-width="1.7"
                        stroke-linecap="round"/>
                </svg>
                <span class="sr-only">Eliminar</span>
              </button>
            </div>
          </div>

          <div class="sat-dl-filters">
            <input id="satDlSearch" type="text" placeholder="Buscar por solicitud, RFC o alias">
            <select id="satDlTipo">
              <option value="">Todos los tipos</option>
              <option value="emitidos">Emitidos</option>
              <option value="recibidos">Recibidos</option>
              <option value="ambos">Emitidos y recibidos</option>
            </select>
            <select id="satDlStatus">
              <option value="">Todos los estados</option>
              <option value="pending">Pendientes</option>
              <option value="processing">En proceso</option>
              <option value="done">Listos</option>
              <option value="paid">Pagados</option>
              <option value="expired">Expirados</option>
            </select>
          </div>
        </div>

        @php
          /** @var \Illuminate\Pagination\LengthAwarePaginator|null $downloadsPaginator */
          $downloadsPaginator = $downloadsPaginator ?? null;

          $baseRows = $downloadsPaginator
              ? $downloadsPaginator->items()
              : ($rows ?? $initialRows ?? $downloads ?? $lista ?? []);

          $satRows = collect($baseRows)
              ->map(fn($r) => is_array($r) ? (object)$r : $r)
              ->filter(function ($row) {
                  $tipoRaw = strtolower((string) data_get($row,'tipo', data_get($row,'origen','')));
                  if (str_contains($tipoRaw,'vault') || str_contains($tipoRaw,'boveda') || str_contains($tipoRaw,'bóveda')) {
                      return false;
                  }

                  $expiresAt = data_get($row,'expires_at');
                  $isExpired = (bool) data_get($row,'is_expired', false);

                  if (!$isExpired && $expiresAt) {
                      try { $isExpired = \Illuminate\Support\Carbon::parse($expiresAt)->isPast(); }
                      catch (\Exception $e) { $isExpired = false; }
                  }

                  return !$isExpired;
              });

          $downloadsForJs = $satRows->values();
        @endphp

        <div class="sat-dl-table-wrap">
          <table class="sat-dl-table">
            <thead>
            <tr>
              <th class="t-center"><input type="checkbox" id="satCheckAll"></th>
              <th>ID</th>
              <th class="sat-dl-th sat-dl-th-fecha t-center">FECHA</th>
              <th class="sat-dl-th sat-dl-th-periodo t-center">PERIODO</th>
              <th class="sat-dl-th sat-dl-th-rfc t-center">RFC</th>
              <th class="sat-dl-th sat-dl-th-alias t-center">ALIAS</th>
              <th class="sat-dl-th sat-dl-th-peso t-right">PESO</th>
              <th class="sat-dl-th sat-dl-th-costo t-right">COSTO</th>
              <th class="sat-dl-th sat-dl-th-status t-center">ESTATUS SAT</th>
              <th class="sat-dl-th sat-dl-th-disp t-center">DISPONIBILIDAD</th>
              <th class="sat-dl-th sat-dl-th-cart t-center">ACCIONES</th>
            </tr>
            </thead>

            <tbody id="satDlBody">
            @forelse($satRows as $row)
              @php
                $id = data_get($row,'id') ?? data_get($row,'download_id');

                $createdAt = data_get($row,'created_at') ?? data_get($row,'createdAt') ?? data_get($row,'fecha') ?? data_get($row,'fecha_creacion');
                try { $createdObj = $createdAt ? \Illuminate\Support\Carbon::parse($createdAt) : null; }
                catch (\Exception $e) { $createdObj = null; }

                $desde       = data_get($row,'desde');
                $hasta       = data_get($row,'hasta');
                $periodLabel = data_get($row,'period_label') ?? trim(($desde.' – '.$hasta), ' –') ?: '—';

                $rfc   = data_get($row,'rfc','—');
                $alias = data_get($row,'alias','—');

                // ======================
                // PESO (Mb)
                // ======================
                $sizeLabel = data_get($row,'size_label') ?? data_get($row,'peso_label') ?? data_get($row,'zip_size_label');
                $sizeMb  = null;
                $hasSize = false;

                if ($sizeLabel !== null && trim((string)$sizeLabel) !== '') {
                    $tmp = strtolower(trim((string)$sizeLabel));
                    if (!in_array($tmp, ['pendiente','pending','—','-'], true)) {
                        $num = preg_replace('/[^\d\.]/', '', (string)$sizeLabel);
                        if ($num !== '') { $sizeMb = (float)$num; $hasSize = $sizeMb > 0; }
                    }
                }

                if (!$hasSize) {
                    $sizeRaw = null;
                    foreach (['size_mb','peso_mb','tam_mb','peso','mb','zip_mb','zip_size_mb','zip_total_mb'] as $fld) {
                        $v = data_get($row,$fld);
                        if ($v !== null && $v !== '') { $sizeRaw = $v; break; }
                    }
                    if ($sizeRaw !== null) {
                        $sizeMb = (float)$sizeRaw;
                    } else {
                        $bytes = null;
                        foreach (['size_bytes','bytes','peso_bytes','zip_bytes','zip_size','zip_size_bytes','file_size','total_bytes'] as $fld) {
                            $v = data_get($row,$fld);
                            if ($v !== null && $v !== '') { $bytes = $v; break; }
                        }
                        if ($bytes !== null) {
                            $sizeMb = (float)$bytes / 1024 / 1024;
                        } else {
                            $gb = null;
                            foreach (['size_gb','peso_gb','tam_gb','zip_gb','zip_size_gb'] as $fld) {
                                $v = data_get($row,$fld);
                                if ($v !== null && $v !== '') { $gb = $v; break; }
                            }
                            if ($gb !== null) $sizeMb = (float)$gb * 1024;
                        }
                    }
                    $hasSize = $sizeMb !== null && $sizeMb > 0;
                }

                if ($hasSize) $sizeLabel = number_format((float)$sizeMb, 2) . ' Mb';
                else $sizeLabel = 'Pendiente';

                // ======================
                // COSTO
                // ======================
                $costLabelFromRow = data_get($row,'costo_label') ?? data_get($row,'price_label') ?? data_get($row,'total_label') ?? data_get($row,'zip_cost_label');

                $costRaw = null;
                foreach ([
                    'costo','costo_mx','costo_mxn','costo_total','costo_total_mxn',
                    'precio','precio_mxn','price','monto','amount','total_mxn',
                    'zip_cost','zip_cost_mxn','zip_total_mxn','importe','importe_mxn',
                    'subtotal','subtotal_mxn'
                ] as $fld) {
                    $v = data_get($row,$fld);
                    if ($v !== null && $v !== '') { $costRaw = $v; break; }
                }

                $costUsd = $costRaw === null || $costRaw === '' ? 0.0 : (float)$costRaw;

                if ($costUsd <= 0 && $costLabelFromRow) {
                    $num = preg_replace('/[^\d\.]/', '', (string)$costLabelFromRow);
                    if ($num !== '') $costUsd = (float)$num;
                }

                if ($costUsd <= 0 && $hasSize && (float)$sizeMb > 0) {
                    $pricePerMb = (float) config('services.sat.download.price_per_mb', 100.00);
                    $costUsd    = round(((float)$sizeMb) * $pricePerMb, 2);
                }

                $hasCost = $costUsd > 0;
                if ($costLabelFromRow !== null && trim((string)$costLabelFromRow) !== '') $costLabel = (string)$costLabelFromRow;
                elseif ($hasCost) $costLabel = '$' . number_format($costUsd, 2);
                else $costLabel = '$0.00';

                // ======================
                // ESTATUS
                // ======================
                $statusDb      = (string) data_get($row,'status','');
                $statusDbLower = strtolower($statusDb);

                $statusRaw = data_get($row,'sat_status',
                              data_get($row,'status_sat',
                                data_get($row,'estado', $statusDb !== '' ? $statusDb : 'DONE')));

                $statusKey       = strtoupper(trim((string)($statusRaw ?: 'DONE')));
                $statusText      = (string) data_get($row,'status_sat_text',$statusKey);
                $statusKeyLower  = strtolower($statusKey);
                $statusTextLower = strtolower(trim($statusText));

                // ======================
                // EXPIRACIÓN / DISPONIBILIDAD
                // ======================
                $expiresAt     = data_get($row,'expires_at');
                $isExpiredFlag = (bool) data_get($row,'is_expired', false);

                if (!$isExpiredFlag && $expiresAt) {
                    try { $isExpiredFlag = \Illuminate\Support\Carbon::parse($expiresAt)->isPast(); }
                    catch (\Exception $e) { $isExpiredFlag = false; }
                }

                $isExpired = $isExpiredFlag;
                $remaining = data_get($row,'remaining_label', data_get($row,'time_left_label','—:—:—'));

                // ======================
                // PAGADO
                // ======================
                $isPaidRow = false;
                foreach (['is_paid','paid','pagado','paid_flag','pagado_flag'] as $fld) {
                    $v = data_get($row,$fld);
                    if (!is_null($v) && $v !== '' && $v !== 0 && $v !== false) { $isPaidRow = true; break; }
                }
                if (!$isPaidRow) {
                    foreach (['paid_at','fecha_pago','fecha_pagado'] as $fld) {
                        $v = data_get($row,$fld);
                        if (!empty($v)) { $isPaidRow = true; break; }
                    }
                }
                if (
                    str_contains($statusKeyLower,'paid') || str_contains($statusKeyLower,'pagado') ||
                    str_contains($statusTextLower,'paid') || str_contains($statusTextLower,'pagado') ||
                    str_contains($statusDbLower,'paid') || str_contains($statusDbLower,'pagado')
                ) $isPaidRow = true;

                if ($isPaidRow) { $isExpired = false; $expiresAt = null; }

                // READY / DONE / LISTO
                $isReadyRow = (
                    str_contains($statusKeyLower,'done') || str_contains($statusKeyLower,'ready') || str_contains($statusKeyLower,'listo') ||
                    str_contains($statusTextLower,'done') || str_contains($statusTextLower,'ready') || str_contains($statusTextLower,'listo')
                );

                // URL ZIP
                $downloadUrl = data_get($row,'zip_url');
                if (!$downloadUrl && $id && $zipPattern && $zipPattern !== '#') {
                    if (strpos($zipPattern,'__ID__') !== false) $downloadUrl = str_replace('__ID__', $id, $zipPattern);
                    elseif (strpos($zipPattern,'{id}') !== false) $downloadUrl = str_replace('{id}', $id, $zipPattern);
                    else $downloadUrl = rtrim($zipPattern,'/').'/'.$id;
                }

                // Reglas UX
                $canDownload = (!$isExpired && $isPaidRow && $downloadUrl);
                $canPay      = (!$isExpired && !$isPaidRow && $isReadyRow && $hasCost);

                $canDownloadBackend = data_get($row,'can_download');
                if ($canDownloadBackend === true || $canDownloadBackend === 1 || $canDownloadBackend === '1') $canDownload = true;

                $canPayBackend = data_get($row,'can_pay');
                if ($canPayBackend === true || $canPayBackend === 1 || $canPayBackend === '1') $canPay = true;

                if ($isExpired) { $canDownload = false; $canPay = false; }

                $inCart  = (bool) data_get($row,'in_cart', false);
                $inVault = (bool) data_get($row,'in_vault', data_get($row,'vaulted', false));

                $canVault = (bool) (
                    $id && $isPaidRow && !$isExpired && ($vaultActive ?? false) && !empty($rtVaultFromDownload)
                );

                $tipoRaw  = strtolower((string) data_get($row,'tipo',''));
                $searchIx = strtolower(trim(($id ?? '').' '.$rfc.' '.$alias.' '.$periodLabel));
              @endphp

              <tr
                data-id="{{ $id }}"
                data-costo="{{ $costUsd }}"
                data-peso="{{ $sizeMb ?? 0 }}"
                data-search="{{ $searchIx }}"
                data-tipo="{{ $tipoRaw }}"
                data-status="{{ $statusKeyLower }}"
                data-paid="{{ $isPaidRow ? 1 : 0 }}"
              >
                <td class="sat-dl-col-check t-center">
                  <input type="checkbox" name="dl_ids[]" value="{{ $id }}" class="sat-dl-check">
                </td>

                <td class="mono t-center">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</td>

                <td class="t-center">
                  @if($createdObj)
                    <span class="mono">{{ $createdObj->format('Y-m-d H:i:s') }}</span>
                  @elseif($createdAt)
                    <span class="mono">{{ \Illuminate\Support\Str::substr((string)$createdAt, 0, 19) }}</span>
                  @else
                    —
                  @endif
                </td>

                <td class="t-center">{{ $periodLabel }}</td>
                <td class="mono t-center">{{ $rfc }}</td>
                <td class="t-center">{{ $alias }}</td>

                <td class="t-right">{{ $hasSize ? $sizeLabel : 'Pendiente' }}</td>
                <td class="t-right">{{ $costLabel }}</td>

                <td class="t-center">
                  <span class="sat-badge sat-badge-{{ $statusKey }}">{{ $statusText }}</span>
                </td>

                <td class="t-center">
                  @if($isExpired)
                    <span class="sat-disp sat-disp-expired">Expirada</span>
                  @elseif($isPaidRow)
                    <span class="sat-disp sat-disp-active sat-disp-permanent">Disponible</span>
                  @elseif($isReadyRow && !$isExpired && $expiresAt)
                    <span class="sat-disp sat-disp-active" data-exp="{{ $expiresAt }}">{{ $remaining }}</span>
                  @else
                    <span class="sat-disp sat-disp-pending">En proceso</span>
                  @endif
                </td>

                <td>
                  <div class="sat-actions"
                       data-debug-status="{{ $statusKeyLower }}"
                       data-debug-paid="{{ $isPaidRow ? '1' : '0' }}"
                       data-debug-can-download="{{ $canDownload ? '1' : '0' }}"
                       data-debug-can-pay="{{ $canPay ? '1' : '0' }}"
                       data-debug-expired="{{ $isExpired ? '1' : '0' }}">

                    @if($canVault && !$inVault)
                      <button type="button" class="sat-btn-vault" data-id="{{ $id }}" data-tip="Guardar en Bóveda Fiscal">
                        <svg class="sat-row-icon" viewBox="0 0 24 24" aria-hidden="true">
                          <path d="M6 4.5h12a2 2 0 0 1 2 2v13H4v-13a2 2 0 0 1 2-2Z"
                                fill="none" stroke="currentColor" stroke-width="1.7"
                                stroke-linecap="round" stroke-linejoin="round"/>
                          <path d="M8 8h8M8 11h8"
                                fill="none" stroke="currentColor" stroke-width="1.7"
                                stroke-linecap="round"/>
                        </svg>
                        <span class="sr-only">Guardar en bóveda</span>
                      </button>
                    @endif

                    @if($id && $canDownload)
                      <button type="button" class="sat-btn-download" data-url="{{ $downloadUrl ?: '#' }}" data-id="{{ $id }}" data-tip="Descargar paquete ZIP">
                        <svg class="sat-row-icon" viewBox="0 0 24 24" aria-hidden="true">
                          <path d="M12 4v10M8.5 10.5 12 14l3.5-3.5"
                                fill="none" stroke="currentColor" stroke-width="1.7"
                                stroke-linecap="round" stroke-linejoin="round"/>
                          <path d="M6 17.5h12"
                                fill="none" stroke="currentColor" stroke-width="1.7"
                                stroke-linecap="round"/>
                        </svg>
                        <span class="sr-only">Descargar</span>
                      </button>
                    @endif

                    @if($id && $canPay)
                      <button type="button"
                              class="sat-btn-cart {{ $inCart ? 'is-in-cart' : '' }}"
                              data-id="{{ $id }}"
                              data-action="{{ $inCart ? 'cart-remove' : 'cart-add' }}"
                              data-tip="{{ $inCart ? 'Quitar del carrito' : 'Agregar al carrito' }}">
                        <svg class="sat-row-icon" viewBox="0 0 24 24" aria-hidden="true">
                          <path d="M3.5 4.5h2l1.4 10h11.1l1.5-7.5H7.2"
                                fill="none" stroke="currentColor" stroke-width="1.7"
                                stroke-linecap="round" stroke-linejoin="round"/>
                          <circle cx="10" cy="18.2" r="0.9"></circle>
                          <circle cx="17" cy="18.2" r="0.9"></circle>
                        </svg>
                        <span class="sr-only">{{ $inCart ? 'Quitar del carrito' : 'Agregar al carrito' }}</span>
                      </button>
                    @endif

                    @if($id)
                      <button type="button" class="sat-btn-cancel" data-id="{{ $id }}" data-action="delete" data-tip="Eliminar descarga">
                        <svg class="sat-row-icon" viewBox="0 0 24 24" aria-hidden="true">
                          <path d="M9.5 4.5h5M5.5 6h13M8 6v12a1.5 1.5 0 0 0 1.5 1.5h5A1.5 1.5 0 0 0 16 18V6"
                                fill="none" stroke="currentColor" stroke-width="1.7"
                                stroke-linecap="round" stroke-linejoin="round"/>
                          <path d="M10.2 9.5v6M13.8 9.5v6"
                                fill="none" stroke="currentColor" stroke-width="1.7"
                                stroke-linecap="round"/>
                        </svg>
                        <span class="sr-only">Eliminar descarga</span>
                      </button>
                    @endif
                  </div>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="11" class="sat-dl-empty t-center">Aún no tienes descargas generadas.</td>
              </tr>
            @endforelse
            </tbody>
          </table>
        </div>

        @if(isset($downloadsPaginator) && $downloadsPaginator)
          <div class="sat-dl-pagination">
            {{ $downloadsPaginator->onEachSide(1)->links() }}
          </div>
        @endif
      </div>
    </div>
  </section>

  {{-- 6) BLOQUE: BÓVEDA FISCAL --}}
  @php
    $vaultUpgradeOptions = [
        ['gb' => 5,  'label' => '+5 Gb'],
        ['gb' => 10, 'label' => '+10 Gb'],
        ['gb' => 20, 'label' => '+20 Gb'],
    ];

    $vaultHasQuota = ($vaultActive ?? false) && ($vaultQuotaGb ?? 0) > 0;

    if ($vaultHasQuota) {
        $vaultStateLabel = 'Bóveda activa · '
            . number_format((float)$vaultQuotaGb, 0) . ' Gb cuota · '
            . number_format((float)$vaultUsedGb, 3) . ' Gb usados · '
            . number_format((float)$vaultAvailableGb, 3) . ' Gb libres';
    } else {
        $vaultStateLabel = 'Bóveda desactivada · 0.00 Gb usados';
    }

    $vaultCtaParam = 'vault_gb';
    $vaultDefaultActivateGb = 5;
    $vaultCtaLabel = $vaultHasQuota ? 'Ampliar bóveda' : 'Activar bóveda';
  @endphp

  <section class="sat-section" id="block-vault-storage-section">
    <div class="sat-req-dl-card" id="block-vault-storage">
      <div class="sat-req-header">
        <div class="sat-req-title">
          <span class="sat-req-icon" aria-hidden="true">📚</span>
          <h3>Bóveda Fiscal</h3>
        </div>

        <div class="sat-req-head-actions">
          <a class="btn soft" href="{{ $rtVault }}" data-tip="Abrir bóveda completa">
            <span aria-hidden="true">↗</span>
            <span>Ver bóveda</span>
          </a>
        </div>
      </div>

      <div class="sat-req-panel vault-panel-head">
        <div class="vault-panel-text">
          <div class="vault-panel-title">
            @if($vaultHasQuota)
              Tu bóveda fiscal está activa.
            @else
              Activa tu bóveda para centralizar tus CFDI históricos.
            @endif
          </div>

          <div class="vault-panel-sub">{{ $vaultStateLabel }}</div>

          <div class="vault-panel-hint">
            @if(empty($vaultCtaUrl) || $vaultCtaUrl === '#')
              <span class="text-muted">No hay ruta de compra configurada (carrito/checkout). Revisa rutas.</span>
            @else
              <span class="text-muted">
                @if($vaultHasQuota)
                  Selecciona un bloque y confirma para aumentar tu capacidad.
                @else
                  Haz clic en “Activar bóveda” para iniciar con {{ $vaultDefaultActivateGb }} Gb.
                @endif
              </span>
            @endif
          </div>
        </div>

        <div class="vault-panel-actions">
          <select class="input vault-cta-select" id="vaultUpgradeSelect">
            <option value="">
              {{ $vaultHasQuota ? 'Selecciona ampliación' : 'Ampliación opcional (recomendado)' }}
            </option>
            @foreach($vaultUpgradeOptions as $opt)
              <option value="{{ $opt['gb'] }}">{{ $opt['label'] }}</option>
            @endforeach
          </select>

          <button
            type="button"
            class="btn-amazon"
            id="btnVaultCtaIndex"
            data-url="{{ $vaultCtaUrl }}"
            data-param="{{ $vaultCtaParam }}"
            data-default-gb="{{ $vaultDefaultActivateGb }}"
            data-has-quota="{{ $vaultHasQuota ? 1 : 0 }}"
          >
            <span class="btn-amazon-icon" aria-hidden="true">💾</span>
            <span class="btn-amazon-label">{{ $vaultCtaLabel }}</span>
          </button>
        </div>
      </div>

      <div class="vault-modern-body vault-body-inline">
        <div class="vault-modern-chart">
          <div class="vault-modern-ring">
            <canvas id="vaultDonut"></canvas>
            <div class="vault-modern-center">
              <span class="vault-modern-center-main">{{ number_format((float)$vaultQuotaGb, 0) }} Gb</span>
              <span class="vault-modern-center-sub">{{ $vaultHasQuota ? 'Cuota total' : 'Sin bóveda activa' }}</span>
            </div>
          </div>
        </div>

        <div class="vault-modern-stats">
          <div class="vault-bar vault-bar-used">
            <div class="vault-bar-top">
              <span class="vault-bar-label">Usado</span>
              <span class="vault-bar-meta">
                {{ number_format((float)$vaultUsedGb, 3) }} Gb ·
                {{ ($vaultQuotaGb ?? 0) > 0 ? number_format((float)$vaultUsedPct, 2) : 0 }}%
              </span>
            </div>
            <div class="vault-bar-track">
              <div class="vault-bar-fill" style="width: {{ ($vaultQuotaGb ?? 0) > 0 ? (float)$vaultUsedPct : 0 }}%"></div>
            </div>
          </div>

          @php $pctFree = ($vaultQuotaGb ?? 0) > 0 ? max(0, 100 - (float)$vaultUsedPct) : 0; @endphp

          <div class="vault-bar vault-bar-free">
            <div class="vault-bar-top">
              <span class="vault-bar-label">Disponible</span>
              <span class="vault-bar-meta">
                {{ number_format((float)$vaultAvailableGb, 3) }} Gb ·
                {{ number_format((float)$pctFree, 2) }}%
              </span>
            </div>
            <div class="vault-bar-track">
              <div class="vault-bar-fill" style="width: {{ (float)$pctFree }}%"></div>
            </div>
          </div>

          <div class="vault-pill-row">
            <div class="vault-pill vault-pill-used">
              <div class="vault-pill-top">
                <span class="vault-pill-chip">Usado</span>
                <span class="vault-pill-percent">{{ ($vaultQuotaGb ?? 0) > 0 ? number_format((float)$vaultUsedPct, 2) : 0 }}%</span>
              </div>
              <div class="vault-pill-value">{{ number_format((float)$vaultUsedGb, 3) }} Gb</div>
            </div>

            <div class="vault-pill vault-pill-free">
              <div class="vault-pill-top">
                <span class="vault-pill-chip">Disponible</span>
                <span class="vault-pill-percent">{{ number_format((float)$pctFree, 2) }}%</span>
              </div>
              <div class="vault-pill-value">{{ number_format((float)$vaultAvailableGb, 3) }} Gb</div>
            </div>
          </div>

          <div class="vault-mini-note">
            @if($isProPlan)
              Tu cuenta PRO incluye acceso a la bóveda. Puedes ampliar en bloques cuando lo requieras.
            @else
              En el plan PRO podrás activar la bóveda, guardar CFDI históricos y ampliar el espacio.
            @endif
          </div>
        </div>
      </div>
    </div>
  </section>

  {{-- MODAL: AGREGAR RFC / CSD --}}
  <div class="sat-modal-backdrop" id="modalRfc">
    <div class="sat-modal sat-modal-lg">
      <div class="sat-modal-header sat-modal-header-simple">
        <div>
          <div class="sat-modal-kicker">Conexiones SAT · CSD</div>
          <div class="sat-modal-title">Agregar RFC</div>
          <p class="sat-modal-sub">Solo llena lo necesario y guarda. El CSD es opcional.</p>
        </div>
        <button type="button" class="sat-modal-close" data-close="modal-rfc" aria-label="Cerrar">✕</button>
      </div>

      <form id="formRfc">
        @csrf
        <div class="sat-modal-body sat-modal-body-steps">
          <section class="sat-step-card">
            <div class="sat-step-kicker">
              <span>Paso 1</span>
              <small>Datos del RFC</small>
            </div>
            <div class="sat-step-grid">
              <div class="sat-field sat-field-full">
                <div class="sat-field-label">RFC</div>
                <input class="input sat-input-pill" type="text" name="rfc" maxlength="13" placeholder="AAA010101AAA" required>
              </div>
              <div class="sat-field sat-field-full">
                <div class="sat-field-label">Nombre o razón social</div>
                <input class="input sat-input-pill" type="text" name="alias" maxlength="190" placeholder="Alias para identificar el RFC">
              </div>
            </div>
          </section>

          <section class="sat-step-card">
            <div class="sat-step-kicker sat-step-kicker-secondary">
              <span>Paso 2 · Opcional</span>
              <small>Certificado CSD</small>
            </div>
            <div class="sat-step-grid sat-step-grid-2col">
              <div class="sat-field">
                <div class="sat-field-label">Certificado (.cer)</div>
                <input class="input sat-input-pill" type="file" name="cer" accept=".cer">
              </div>
              <div class="sat-field">
                <div class="sat-field-label">Llave privada (.key)</div>
                <input class="input sat-input-pill" type="file" name="key" accept=".key">
              </div>
              <div class="sat-field sat-field-full">
                <div class="sat-field-label">Contraseña de la llave</div>
                <input class="input sat-input-pill sat-input-password" type="password" name="key_password" autocomplete="new-password" placeholder="Contraseña del archivo .key">
              </div>
            </div>
            <p class="sat-modal-note sat-field-full">Si dejas estos campos vacíos, solo se registrará el RFC.</p>
          </section>
        </div>

        <div class="sat-modal-footer">
          <button type="button" class="btn" data-close="modal-rfc">Cancelar</button>
          <button type="submit" class="btn primary"><span aria-hidden="true">✅</span><span>Guardar y continuar</span></button>
        </div>
      </form>
    </div>
  </div>

  {{-- MODAL: AUTOMATIZAR DESCARGAS --}}
  <div class="sat-modal-backdrop" id="modalAuto">
    <div class="sat-modal">
      <div class="sat-modal-header">
        <div>
          <div class="sat-modal-kicker">Descargas SAT</div>
          <div class="sat-modal-title">Automatizar descargas</div>
          <p class="sat-modal-sub">Programa ejecuciones periódicas por RFC para no tener que lanzar las descargas manualmente.</p>
        </div>
        <button type="button" class="sat-modal-close" data-close="modal-auto" aria-label="Cerrar">✕</button>
      </div>

      <div class="sat-modal-body">
        @if(!$isProPlan)
          <p class="text-muted">
            Las automatizaciones están disponibles únicamente en el <b>plan PRO</b>.
            Contrata PRO para activar descargas diarias, semanales o mensuales por RFC.
          </p>
          <div class="auto-grid is-locked">
            <button type="button" class="btn auto-btn" disabled><span aria-hidden="true">⏱</span><span>Diario</span></button>
            <button type="button" class="btn auto-btn" disabled><span aria-hidden="true">📅</span><span>Semanal</span></button>
            <button type="button" class="btn auto-btn" disabled><span aria-hidden="true">🗓</span><span>Mensual</span></button>
            <button type="button" class="btn auto-btn" disabled><span aria-hidden="true">⚙️</span><span>Por rango</span></button>
          </div>
        @else
          <p>Aquí podrás configurar tus automatizaciones. De momento es una vista informativa; más adelante conectaremos esta pantalla con el backend para guardar las reglas.</p>
          <div class="auto-grid">
            <button type="button" class="btn auto-btn"><span aria-hidden="true">⏱</span><span>Agregar tarea diaria</span></button>
            <button type="button" class="btn auto-btn"><span aria-hidden="true">📅</span><span>Agregar tarea semanal</span></button>
            <button type="button" class="btn auto-btn"><span aria-hidden="true">🗓</span><span>Agregar tarea mensual</span></button>
            <button type="button" class="btn auto-btn"><span aria-hidden="true">⚙️</span><span>Personalizado</span></button>
          </div>
        @endif
      </div>

      <div class="sat-modal-footer">
        <button type="button" class="btn" data-close="modal-auto">Cerrar</button>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
{{-- Chart.js --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

<script>
  // CONFIG GLOBAL SAT -> debe existir ANTES de sat-dashboard.js
  window.P360_SAT = {
    csrf: @json(csrf_token()),
    isProPlan: @json((bool)($isProPlan ?? $isPro ?? false)),
    downloads: @json($downloadsForJs ?? []),

    routes: {
      request:      @json($rtReqCreate),
      verify:       @json($rtVerify),
      mode:         @json($rtMode),
      download:     @json($rtPkgPost),
      cancel:       @json($rtDownloadCancel),
      charts:       @json($rtCharts),

      csdStore:     @json($rtCsdStore),
      rfcReg:       @json($rtRfcReg),

      cartIndex:    @json($rtCartIndex),
      cartList:     @json($rtCartList ?: $rtCartIndex),
      cartAdd:      @json($rtCartAdd),
      cartRemove:   @json($rtCartRemove),
      cartCheckout: @json($rtCartPay),

      zipPattern:   @json($zipPattern),

      vaultIndex:   @json($rtVault),
      vaultQuick:   @json($rtVaultQuick),

      // plantilla con __ID__
      vaultFromDownload: @json($rtVaultFromDownload),
    },

    vault: @json($vault ?? []),
  };
</script>

{{-- JS principal del dashboard SAT --}}
<script src="{{ asset('assets/client/js/sat-dashboard.js') }}" defer></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const CFG    = window.P360_SAT || {};
  const ROUTES = (CFG.routes || {});
  const csrf   = (CFG.csrf || '');

  const bulkBar   = document.getElementById('satDlBulk');
  const bulkCount = document.getElementById('satDlBulkCount');
  const btnAll    = document.getElementById('satCheckAll');

  function toast(msg) {
    if (window.P360 && typeof window.P360.toast === 'function') return window.P360.toast(msg);
    alert(msg);
  }

  const checks = () => Array.from(document.querySelectorAll('.sat-dl-check'));

  function getSelectedIds() {
    return checks().filter(c => c.checked).map(c => (c.value || '').trim()).filter(Boolean);
  }

  function updateBulk() {
    const n = getSelectedIds().length;
    if (!bulkBar) return;
    bulkBar.style.display = n > 0 ? 'flex' : 'none';
    if (bulkCount) bulkCount.textContent = String(n);
  }

  checks().forEach(cb => cb.addEventListener('change', updateBulk));

  if (btnAll) {
    btnAll.addEventListener('change', function () {
      const check = this.checked;
      checks().forEach(cb => { cb.checked = check; });
      updateBulk();
    });
  }

  async function postJson(url, payload) {
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf,
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify(payload || {}),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || data.ok === false) throw new Error(data.msg || data.message || 'Solicitud fallida.');
    return data;
  }

  async function addToCart(downloadId) {
    if (!ROUTES.cartAdd) throw new Error('Ruta cartAdd no configurada.');
    return await postJson(ROUTES.cartAdd, { download_id: downloadId, id: downloadId });
  }

  async function removeFromCart(downloadId) {
    if (!ROUTES.cartRemove) throw new Error('Ruta cartRemove no configurada.');
    return await postJson(ROUTES.cartRemove, { download_id: downloadId, id: downloadId });
  }

  async function checkout(ids) {
    if (!ROUTES.cartCheckout) throw new Error('Ruta cartCheckout no configurada.');
    const data = await postJson(ROUTES.cartCheckout, { ids, download_ids: ids });
    const url = data.url || data.checkout_url || data.redirect || null;
    if (!url) throw new Error('Checkout generado, pero sin URL.');
    window.location.href = url;
  }

  // Bulk buttons
  const btnRefresh = document.getElementById('satDlBulkRefresh');
  const btnAdd     = document.getElementById('satDlAddSelected');
  const btnBuy     = document.getElementById('satDlBulkBuy');
  const btnDelete  = document.getElementById('satDlBulkDelete');

  if (btnRefresh) {
    btnRefresh.addEventListener('click', function () {
      const verifyBtn = document.getElementById('btnSatVerify');
      if (verifyBtn) verifyBtn.click();
    });
  }

  if (btnAdd) {
    btnAdd.addEventListener('click', async function () {
      const ids = getSelectedIds();
      if (!ids.length) return toast('Selecciona al menos 1 solicitud.');
      btnAdd.disabled = true;
      try {
        for (const id of ids) await addToCart(id);
        toast('Seleccionados agregados al carrito.');
      } catch (e) {
        toast(e?.message || 'No se pudo agregar al carrito.');
      } finally {
        btnAdd.disabled = false;
      }
    });
  }

  // IMPORTANTE: solo un handler de compra masiva (evita duplicados)
  if (btnBuy) {
    btnBuy.addEventListener('click', async function (ev) {
      ev.preventDefault();
      ev.stopPropagation();
      if (ev.stopImmediatePropagation) ev.stopImmediatePropagation();

      const ids = getSelectedIds();
      if (!ids.length) return toast('Selecciona al menos 1 solicitud para comprar.');

      btnBuy.disabled = true;
      try {
        for (const id of ids) await addToCart(id);
        await checkout(ids);
      } catch (e) {
        toast(e?.message || 'No se pudo iniciar la compra.');
      } finally {
        btnBuy.disabled = false;
      }
    }, true);
  }

  if (btnDelete) {
    btnDelete.addEventListener('click', function () {
      const selected = checks().filter(c => c.checked);
      selected.forEach(cb => {
        const tr = cb.closest('tr');
        const delBtn = tr ? tr.querySelector('.sat-btn-cancel') : null;
        if (delBtn) delBtn.click();
      });
    });
  }

  // Row cart toggle (si sat-dashboard.js no lo cubre, aquí queda asegurado)
  document.body.addEventListener('click', async function (ev) {
    const btn = ev.target.closest('.sat-btn-cart');
    if (!btn) return;

    ev.preventDefault();

    const id = (btn.dataset.id || '').trim();
    const action = (btn.dataset.action || 'cart-add').trim();
    if (!id) return;

    btn.disabled = true;
    try {
      if (action === 'cart-remove') {
        await removeFromCart(id);
        btn.dataset.action = 'cart-add';
        btn.classList.remove('is-in-cart');
        btn.setAttribute('data-tip','Agregar al carrito');
      } else {
        await addToCart(id);
        btn.dataset.action = 'cart-remove';
        btn.classList.add('is-in-cart');
        btn.setAttribute('data-tip','Quitar del carrito');
      }
    } catch (e) {
      toast(e?.message || 'No se pudo actualizar el carrito.');
    } finally {
      btn.disabled = false;
    }
  }, true);

  // Quick guides -> set fechas + tipo + submit
  function ymd(d) {
    const pad = (n) => String(n).padStart(2,'0');
    return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
  }
  function setRange(fromDate, toDate, tipo) {
    const f = document.getElementById('reqFrom');
    const t = document.getElementById('reqTo');
    const tp = document.getElementById('reqTipo');
    if (tp && tipo) tp.value = tipo;
    if (f) f.value = ymd(fromDate);
    if (t) t.value = ymd(toDate);
  }

  const btnLast30 = document.getElementById('btnQuickLast30');
  const btnMonth  = document.getElementById('btnQuickThisMonth');
  const btnEmit   = document.getElementById('btnQuickOnlyEmitted');

  const reqForm = document.getElementById('reqForm');

  if (btnLast30 && reqForm) {
    btnLast30.addEventListener('click', function () {
      const to = new Date();
      const from = new Date(); from.setDate(from.getDate() - 30);
      setRange(from, to, 'ambos');
      reqForm.requestSubmit ? reqForm.requestSubmit() : reqForm.submit();
    });
  }

  if (btnMonth && reqForm) {
    btnMonth.addEventListener('click', function () {
      const now = new Date();
      const from = new Date(now.getFullYear(), now.getMonth(), 1);
      const to   = new Date(now.getFullYear(), now.getMonth()+1, 0);
      setRange(from, to, 'ambos');
      reqForm.requestSubmit ? reqForm.requestSubmit() : reqForm.submit();
    });
  }

  if (btnEmit && reqForm) {
    btnEmit.addEventListener('click', function () {
      const to = new Date();
      const from = new Date(); from.setDate(from.getDate() - 30);
      setRange(from, to, 'emitidos');
      reqForm.requestSubmit ? reqForm.requestSubmit() : reqForm.submit();
    });
  }

  // Guardar en bóveda desde fila
  function buildFromDownloadUrl(id) {
    const tpl = ROUTES.vaultFromDownload;
    if (!tpl) return null;
    return String(tpl).replace('__ID__', encodeURIComponent(id));
  }

  async function refreshVaultQuick() {
    if (!ROUTES.vaultQuick) return;
    try {
      const res = await fetch(ROUTES.vaultQuick, {
        headers: { 'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest' }
      });
      if (!res.ok) return;
      const data = await res.json().catch(() => ({}));
      if (data && data.vault) {
        CFG.vault = data.vault;
        window.P360_SAT = CFG;
        if (window.P360_SAT_UI && typeof window.P360_SAT_UI.redrawVault === 'function') {
          window.P360_SAT_UI.redrawVault(data.vault);
        }
      }
    } catch (e) {}
  }

  document.body.addEventListener('click', async function (ev) {
    const btn = ev.target.closest('.sat-btn-vault');
    if (!btn) return;

    ev.preventDefault();

    const id  = (btn.dataset.id || '').trim();
    const url = buildFromDownloadUrl(id);

    if (!id || !url) return toast('No se pudo construir la ruta para guardar en bóveda.');

    btn.disabled = true;
    btn.classList.add('is-loading');

    try {
      const res = await fetch(url, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({}),
      });

      const data = await res.json().catch(() => ({}));
      if (!res.ok || data.ok === false) {
        return toast(data.message || data.msg || 'No se pudo guardar en bóveda. Revisa el backend.');
      }

      btn.remove();
      toast('Guardado en Bóveda Fiscal.');
      await refreshVaultQuick();
    } catch (e) {
      toast('Error de red al guardar en bóveda.');
    } finally {
      btn.disabled = false;
      btn.classList.remove('is-loading');
    }
  }, true);

  // CTA bóveda (activar / ampliar)
  const selectVault = document.getElementById('vaultUpgradeSelect');
  const btnVault    = document.getElementById('btnVaultCtaIndex');

  if (selectVault && btnVault) {
    function hasQuota() { return String(btnVault.dataset.hasQuota || '0') === '1'; }
    function syncLabel() {
      const label = btnVault.querySelector('.btn-amazon-label');
      if (!label) return;
      label.textContent = hasQuota() ? 'Ampliar bóveda' : 'Activar bóveda';
    }
    function canProceed() { return !hasQuota() || !!selectVault.value; }
    function syncDisabled() { btnVault.disabled = !canProceed(); }

    function buildFinalUrl(baseUrl, param, gb) {
      const sep = baseUrl.indexOf('?') === -1 ? '?' : '&';
      return baseUrl + sep + encodeURIComponent(param) + '=' + encodeURIComponent(String(gb));
    }

    selectVault.addEventListener('change', syncDisabled);
    syncLabel();
    syncDisabled();

    btnVault.addEventListener('click', function () {
      const baseUrl = (btnVault.dataset.url || '').trim();
      const param   = (btnVault.dataset.param || 'vault_gb').trim();

      if (!baseUrl || baseUrl === '#') return toast('No hay ruta configurada para activar/comprar bóveda (carrito/checkout).');

      let gb = selectVault.value;
      if (!gb) {
        if (!hasQuota()) gb = btnVault.dataset.defaultGb || '5';
        else return toast('Selecciona primero una ampliación de Gb para continuar.');
      }

      window.location.href = buildFinalUrl(baseUrl, param, gb);
    });
  }

  updateBulk();
});
</script>
@endpush
