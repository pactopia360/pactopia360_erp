{{-- resources/views/cliente/sat/index.blade.php (v3.6 · CTA MOVE: Descargas manuales · FIX expired + status badge safe + rfcOptions SOT + external RFC visible) --}}
@extends('layouts.cliente')
@section('title','SAT · Descargas masivas CFDI')

{{-- importante para usar el ancho completo --}}
@section('pageClass','page-sat')

@php
  use Illuminate\Support\Carbon;
  use Illuminate\Support\Str;
  use Illuminate\Support\Facades\Route;

  // ======================================================
  //  Usuario / cuenta
  // ======================================================
  $user   = $user   ?? auth('web')->user();
  $cuenta = $cuenta ?? ($user?->cuenta ?? null);

  // ======================================================
  //  Resumen unificado de cuenta (admin.accounts)
  // ======================================================
  $summary = $summary ?? app(\App\Http\Controllers\Cliente\HomeController::class)->buildAccountSummary();

  // ======================================================
  //  RFC externo (Admin) - datos base para UI SAT
  // ======================================================
  $externalRfc      = strtoupper(trim((string) data_get($summary, 'rfc_externo', data_get($summary, 'rfc', ''))));
  $externalVerified = (bool) data_get($summary, 'rfc_external_verified', false);

  // ======================================================
  //  Plan / PRO
  // ======================================================
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

  // ✅ SOT para gráfica: usar la misma fuente que el listado (paginator/rows/etc)
  //    Evita que la tabla tenga datos y la gráfica quede en ceros.
  $downloadsPaginator = $downloadsPaginator ?? null;

  $rowsInit = $initialRows
      ?? ($downloadsPaginator ? $downloadsPaginator->items() : null)
      ?? ($rows ?? $downloads ?? $lista ?? []);


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
          return Carbon::parse($created)->greaterThanOrEqualTo($periodFrom);
      } catch (\Exception $e) {
          return false;
      }
  })->count());

  $rfcsValidated = (int)($summary['sat_rfcs_validated'] ?? $credList->filter(function ($c) {
      // ✅ Validación real:
      // - validado flag
      // - validated_at presente
      // Nota: tener CSD/archivos NO implica validación (puede ser registro externo pendiente).
      $estatusRaw = strtolower((string) data_get($c, 'estatus', ''));

      $isValidated =
          !empty(data_get($c,'validado'))
          || !empty(data_get($c,'validated_at'))
          || in_array($estatusRaw, ['ok','valido','válido','validado','valid'], true);

      return $isValidated;
  })->count());

  $rfcsPending = max(0, (int)$credList->count() - (int)$rfcsValidated);

  $downloadsTotal  = (int)$rowsColl->count();
  $downloadsPeriod = (int)$filesPeriod;

  $reqStart     = (int)($summary['sat_req_start']     ?? $asig);
  $reqDone      = (int)($summary['sat_req_done']      ?? $downloadsTotal);
  $reqPeriod    = (int)($summary['sat_req_period']    ?? $downloadsPeriod);
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
  $rtCsdStore   = Route::has('cliente.sat.credenciales.store') ? route('cliente.sat.credenciales.store') : '#';
  $rtReqCreate  = Route::has('cliente.sat.request')            ? route('cliente.sat.request')            : '#';
  $rtVerify     = Route::has('cliente.sat.verify')             ? route('cliente.sat.verify')             : '#';
  $rtPkgPost    = Route::has('cliente.sat.download')           ? route('cliente.sat.download')           : '#';
  $rtZipGet     = Route::has('cliente.sat.zip')                ? route('cliente.sat.zip',['id'=>'__ID__']) : '#';
  $rtReport     = Route::has('cliente.sat.report')             ? route('cliente.sat.report')             : '#';
  $rtVault      = Route::has('cliente.sat.vault')              ? route('cliente.sat.vault')              : '#';
  $rtMode       = Route::has('cliente.sat.mode')               ? route('cliente.sat.mode')               : null;
  $rtCharts     = Route::has('cliente.sat.charts')             ? route('cliente.sat.charts')             : null;

  $rtVaultQuick = Route::has('cliente.sat.vault.quick') ? route('cliente.sat.vault.quick') : null;

  $rtVaultFromDownload = Route::has('cliente.sat.vault.fromDownload')
      ? route('cliente.sat.vault.fromDownload', ['download' => '__ID__'])
      : null;

  // RFCs
  $rtAlias          = Route::has('cliente.sat.alias')            ? route('cliente.sat.alias')            : '#';
  $rtRfcReg         = Route::has('cliente.sat.rfc.register')     ? route('cliente.sat.rfc.register')     : '#';
  $rtRfcDelete      = Route::has('cliente.sat.rfc.delete')       ? route('cliente.sat.rfc.delete')       : '#';
  $rtDownloadCancel = Route::has('cliente.sat.download.cancel')  ? route('cliente.sat.download.cancel')  : null;

  // ✅ Registro externo (invite)
  $rtExternalInvite = Route::has('cliente.sat.external.invite')  ? route('cliente.sat.external.invite')  : null;

  // Carrito SAT
  $rtCartIndex  = Route::has('cliente.sat.cart.index')  ? route('cliente.sat.cart.index')  : null;
  $rtCartAdd    = Route::has('cliente.sat.cart.add')    ? route('cliente.sat.cart.add')    : null;
  $rtCartRemove = Route::has('cliente.sat.cart.remove') ? route('cliente.sat.cart.remove') : null;
  $rtCartList   = Route::has('cliente.sat.cart.list')   ? route('cliente.sat.cart.list')   : null;

  $rtCartPay = Route::has('cliente.sat.cart.checkout')
      ? route('cliente.sat.cart.checkout')
      : null;

  $zipPattern = $rtZipGet ?? '#';

  $vaultCtaUrl = $rtCartIndex
      ?? $rtCartPay
      ?? $rtCartList
      ?? $rtVault
      ?? '#';

  // ======================================================
  // Cotizadores / Calculadoras (DOS FLUJOS DIFERENTES)
  // ======================================================

  // A) Calculadora rápida (Guías rápidas) -> SIN RFC -> solo cotiza + PDF
  // Requiere endpoints que consulten lista de precios en Admin
  $rtQuickCalc = Route::has('cliente.sat.quick.calc') ? route('cliente.sat.quick.calc') : null;
  $rtQuickPdf  = Route::has('cliente.sat.quick.pdf')  ? route('cliente.sat.quick.pdf')  : null;

  // B) Cotizador por RFC/periodo (para manuales / proceso a pago)
  // (se mantiene como está)
  $rtQuoteCalc = Route::has('cliente.sat.quote.calc') ? route('cliente.sat.quote.calc') : null;
  $rtQuotePdf  = Route::has('cliente.sat.quote.pdf')  ? route('cliente.sat.quote.pdf')  : null;

  // ======================================================
  // Descargas manuales (FLUJO SEPARADO)
  // ======================================================
  // Nuevo módulo: listado/progreso/pago diferencia/archivos soporte
  $rtManualIndex  = Route::has('cliente.sat.manual.index')  ? route('cliente.sat.manual.index')  : null;

  // ⚠️ manual.create ya no es el CTA principal (se mantiene por compatibilidad si quieres usarlo después)
  $rtManualCreate = Route::has('cliente.sat.manual.create') ? route('cliente.sat.manual.create') : null;

  // ✅ NUEVO: inicio del proceso (wizard → pago)
  $rtManualQuote  = Route::has('cliente.sat.manual.quote')  ? route('cliente.sat.manual.quote')  : null;

  // ======================================================
  // RFC Options (SOT / fallback defensivo)
  // - Incluye RFC externo (Admin) aunque esté pendiente SAT
  // - Solo RFCs validados se usarán para SOLICITAR (ver conteos abajo)
  // ======================================================
  $rfcOptions = $rfcOptions ?? (function () use ($credList, $externalRfc, $externalVerified) {
      $out = [];

      foreach (collect($credList ?? []) as $c) {
          $rfc = strtoupper(trim((string) ($c->rfc ?? '')));
          if ($rfc === '') continue;

          $estatusRaw = strtolower((string) ($c->estatus ?? ''));

          $isValidated =
              !empty($c->validado ?? null)
              || !empty($c->validated_at ?? null)
              || in_array($estatusRaw, ['ok','valido','válido','validado','valid'], true);

          $alias = trim((string) ($c->razon_social ?? $c->alias ?? ''));

          $out[$rfc] = [
              'rf'        => $rfc,
              'alias'     => $alias !== '' ? $alias : null,
              'validated' => (bool) $isValidated,
              'source'    => 'sat_credentials',
          ];
      }

      // ✅ RFC externo visible aunque no esté validado SAT
      if (is_string($externalRfc) && trim($externalRfc) !== '') {
          $r = strtoupper(trim($externalRfc));
          if (!isset($out[$r])) {
              $out[$r] = [
                  'rf'        => $r,
                  'alias'     => 'Registro externo',
                  // IMPORTANTE: verificado externo ≠ validado SAT
                  'validated' => false,
                  'source'    => 'external_registry',
                  'external'  => true,
              ];
          }
      }

      $list = array_values($out);
      usort($list, fn($a, $b) => strcmp((string)$a['rf'], (string)$b['rf']));
      return $list;
  })();

  // ======================================================
  // RFC Options: separar VISIBLES vs VALIDOS (SOT)
  // ======================================================
  $rfcOptionsAll = is_array($rfcOptions) ? $rfcOptions : collect($rfcOptions)->values()->all();

  // Solo RFCs validados SAT (para SOLICITAR / COTIZAR / MANUAL)
  $rfcOptionsValid = collect($rfcOptionsAll)
      ->filter(fn($opt) => !empty($opt['validated']))
      ->values()
      ->all();

   // Conteos RFC (para UI)
  $kRfcVisible   = count($rfcOptionsAll);
  $kRfcValidated = count($rfcOptionsValid);

  // ======================================================
  //  Dataset local para GRÁFICA (fallback inmediato)
  //  - Evita depender de /cliente/sat/dashboard/stats (422)
  //  - Semanas: últimas 8 semanas (incluye semana actual)
  // ======================================================
  $chartWeeks = 8;

  $weekLabels = [];
  $weekCounts = [];

  $now = now();
  $start = (clone $now)->startOfWeek(); // Lunes
  $fromStart = (clone $start)->subWeeks($chartWeeks - 1); // 8 semanas contando actual

  // Pre-armar buckets por semana (ISO year-week)
  $buckets = [];
  for ($i = 0; $i < $chartWeeks; $i++) {
      $wkStart = (clone $fromStart)->addWeeks($i);
      $key = $wkStart->format('o-\WW'); // ej: 2026-W04
      $buckets[$key] = 0;

      // Label: "dd/mm - dd/mm"
      $wkEnd = (clone $wkStart)->endOfWeek();
      $weekLabels[] = $wkStart->format('d/m') . ' - ' . $wkEnd->format('d/m');
  }

  // Contar descargas por created_at dentro de esos buckets
  foreach ($rowsColl as $row) {
      $created = data_get($row, 'created_at', data_get($row, 'createdAt', null));
      if (!$created) continue;

      try {
          $dt = Carbon::parse($created);
      } catch (\Throwable $e) {
          continue;
      }

      // Solo rango considerado
      if ($dt->lt($fromStart)) continue;

      $wkKey = $dt->startOfWeek()->format('o-\WW');
      if (array_key_exists($wkKey, $buckets)) {
          $buckets[$wkKey] = (int)$buckets[$wkKey] + 1;
      }
  }

  // Convertir buckets (mismo orden que labels)
  $weekCounts = array_values($buckets);

@endphp

@push('styles')
@php
  // ============================
  // CSS SAT (cache-bust por mtime)
  // ============================
  $CSS1_REL = 'assets/client/css/sat-dashboard.css';
  $CSS2_REL = 'assets/client/css/sat/sat-index-extras.css';

  // ✅ RFCs v48 (ajusta el nombre si tu archivo real es diferente)
  $CSS3_REL = 'assets/client/css/sat/sat-rfcs-v48.css';

  $CSS1_ABS = public_path($CSS1_REL);
  $CSS2_ABS = public_path($CSS2_REL);
  $CSS3_ABS = public_path($CSS3_REL);

  $CSS1_V = is_file($CSS1_ABS) ? (string) filemtime($CSS1_ABS) : null;
  $CSS2_V = is_file($CSS2_ABS) ? (string) filemtime($CSS2_ABS) : null;
  $CSS3_V = is_file($CSS3_ABS) ? (string) filemtime($CSS3_ABS) : null;

  $CSS1_URL = asset($CSS1_REL) . ($CSS1_V ? ('?v='.$CSS1_V) : '');
  $CSS2_URL = asset($CSS2_REL) . ($CSS2_V ? ('?v='.$CSS2_V) : '');
  $CSS3_URL = asset($CSS3_REL) . ($CSS3_V ? ('?v='.$CSS3_V) : '');
@endphp

@if(!$CSS1_V || !$CSS2_V || !$CSS3_V)
  <div style="margin:10px 0; padding:10px; border:1px solid #f3c; background:#fff5ff; color:#700; border-radius:10px; font-family:ui-monospace,Menlo,monospace;">
    <b>DEBUG SAT:</b> Faltan CSS en disco:
    <div>sat-dashboard.css: {{ $CSS1_V ? 'OK' : 'NO EXISTE en public/assets' }}</div>
    <div>sat-index-extras.css: {{ $CSS2_V ? 'OK' : 'NO EXISTE en public/assets' }}</div>
    <div>sat-rfcs-v48.css: {{ $CSS3_V ? 'OK' : 'NO EXISTE en public/assets' }}</div>
  </div>
@endif

<link rel="stylesheet" href="{{ $CSS1_URL }}" onerror="alert('SAT CSS no carga: {{ $CSS1_URL }}')">
<link rel="stylesheet" href="{{ $CSS2_URL }}" onerror="alert('SAT CSS no carga: {{ $CSS2_URL }}')">
<link rel="stylesheet" href="{{ $CSS3_URL }}" onerror="alert('SAT CSS no carga: {{ $CSS3_URL }}')">
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

<div class="sat-header-actions">
  {{-- ✅ Refrescar pantalla (hard refresh) --}}
  <button
    type="button"
    class="btn icon-only"
    id="btnSatPageRefresh"
    data-tip="Refrescar pantalla"
    aria-label="Refrescar pantalla"
    style="margin-right:8px;"
  >
    <span aria-hidden="true">🔄</span>
  </button>

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


</div> {{-- ✅ /sat-header-top (FIX CRÍTICO) --}}


    {{-- 1) BARRA SUTIL (reemplazo temporal del resumen/KPIs) --}}
  <div class="sat-card" style="padding:12px 14px;">
    <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;">
      <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
        <span class="badge-mode {{ ($modeSafe === 'demo') ? 'demo' : 'prod' }}">
          <span class="dot"></span>
          {{ $modeSafe === 'demo' ? 'DEMO' : 'PRODUCCIÓN' }}
        </span>

        <span class="badge-mode {{ ($isProPlan ?? false) ? 'prod' : 'demo' }}">
          <span class="dot"></span>
          PLAN {{ strtoupper((string)($plan ?? 'FREE')) }}
        </span>

        @if(!empty($externalRfc))
          <span class="badge-mode {{ ($externalVerified ?? false) ? 'prod' : 'demo' }}">
            <span class="dot"></span>
            RFC EXTERNO {{ $externalRfc }}
            {{ ($externalVerified ?? false) ? '· VERIFICADO' : '· PENDIENTE' }}
          </span>
        @endif
      </div>

      <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
        <span class="text-muted" style="font-size:13px;">
          Peticiones: <b>{{ number_format((int)($reqAvailable ?? 0)) }}</b> disponibles ·
          Descargas (30d): <b>{{ number_format((int)($filesPeriod ?? 0)) }}</b>
        </span>

        @if(!empty($rtManualIndex))
          <a class="btn soft" href="{{ $rtManualIndex }}" style="text-decoration:none;">
            Ir a descargas manuales
          </a>
        @elseif(!empty($rtManualQuote))
          <a class="btn soft" href="{{ $rtManualQuote }}" style="text-decoration:none;">
            Iniciar manual (cotización)
          </a>
        @endif
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

{{-- 3) Guías rápidas (partial) --}}
@include('cliente.sat._partials.quick_guides', [
  'isPro'        => $isPro ?? false,
  'rtVault'      => $rtVault ?? '#',
  'rtQuickCalc'  => $rtQuickCalc ?? null,
  'rtQuickPdf'   => $rtQuickPdf  ?? null,
])


    {{-- 3.1) Descargas manuales (FLUJO SEPARADO) --}}
  <div class="sat-card" id="block-manual-downloads">
    <div class="sat-quick-head">
      <div class="sat-quick-title-wrap">
        <div class="sat-quick-icon" aria-hidden="true">⬇️</div>
        <div>
          <div class="sat-quick-kicker">DESCARGAS</div>
          <h3 class="sat-quick-title">Descargas manuales</h3>
          <p class="sat-quick-sub">
            Solicitudes atendidas por soporte: avance (%), validación de costo, adjuntos desde Admin y (si aplica) pago por diferencia.
          </p>
        </div>
      </div>

      <div class="sat-quick-plan">
        <span class="badge-mode {{ ($isProPlan ?? false) ? 'prod' : 'demo' }}">
          <span class="dot"></span>
          {{ ($isProPlan ?? false) ? 'Disponible' : 'Pago por solicitud' }}
        </span>
      </div>
    </div>

    <div class="sat-manual-grid"
         style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; align-items:stretch;">
      <div class="sat-card"
           style="border:1px solid rgba(0,0,0,.06); box-shadow:none; margin:0;">
        <div class="section-title" style="margin:0 0 6px 0;">Crear solicitud manual</div>
        <div class="text-muted">
          El flujo manual no aparece en “Solicitudes SAT” porque tiene tratamiento diferente (revisión, avance, validación y adjuntos).
        </div>

        <div style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap;">
          <button type="button"
                class="btn primary"
                id="btnManualStartProcess"
                data-url="{{ $rtManualQuote ?: '' }}"
                {{ $rtManualQuote ? '' : 'disabled' }}>
          Solicitar descarga (cotización)
        </button>


          <button type="button" class="btn soft" id="btnManualOpenQuote">
            Cotizar (estimado)
          </button>
        </div>

        @if(!$rtManualQuote)
          <div class="text-muted" style="margin-top:8px;">
            Ruta no configurada: <span class="mono">cliente.sat.manual.quote</span>
          </div>
        @endif

      </div>

      <div class="sat-card"
           style="border:1px solid rgba(0,0,0,.06); box-shadow:none; margin:0;">
        <div class="section-title" style="margin:0 0 6px 0;">Ver mis manuales</div>
        <div class="text-muted">
          Aquí verás: estatus (en revisión / pendiente pago / en progreso / listo), porcentaje de avance y mensajes de soporte.
        </div>

        <div style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap;">
          <button type="button"
                  class="btn"
                  id="btnManualGoIndex"
                  data-url="{{ $rtManualIndex ?: '' }}"
                  {{ $rtManualIndex ? '' : 'disabled' }}>
            Abrir listado de manuales
          </button>

          <a class="btn soft" href="#block-downloads" style="text-decoration:none;">
            Volver a descargas SAT
          </a>
        </div>

        @if(!$rtManualIndex)
          <div class="text-muted" style="margin-top:8px;">
            Ruta no configurada: <span class="mono">cliente.sat.manual.index</span>
          </div>
        @endif
      </div>
    </div>
  </div>

{{-- 4) CONEXIONES SAT (UI LIMPIA) --}}
@include('cliente.sat._partials.connections_clean', [
  'externalRfc'      => $externalRfc ?? '',
  'externalVerified' => (bool)($externalVerified ?? false),
  'credList'         => $credList ?? collect(),
  'plan'             => $plan ?? 'FREE',
  'rtCsdStore'       => $rtCsdStore ?? '#',
  'rtAlias'          => $rtAlias ?? '#',
  'rtRfcReg'         => $rtRfcReg ?? '#',
  'rtRfcDelete'      => $rtRfcDelete ?? '#',
  'rtExternalInvite' => $rtExternalInvite ?? null,
])



@php $kRfc = (int) count($rfcOptionsValid ?? []); @endphp


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
          <input type="hidden" name="manual" id="reqManual" value="0">

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

                    @foreach($rfcOptionsValid as $opt)
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
                @foreach(($rfcOptionsValid ?? []) as $opt)
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
                      try { $isExpired = Carbon::parse($expiresAt)->isPast(); }
                      catch (\Exception $e) { $isExpired = false; }
                  }

                  // No ocultamos expiradas: solo se marcarán en UI y no permitirán acciones
                  return true;

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
                try { $createdObj = $createdAt ? Carbon::parse($createdAt) : null; }
                catch (\Exception $e) { $createdObj = null; }

                $desde       = data_get($row,'desde');
                $hasta       = data_get($row,'hasta');
                $periodLabel = data_get($row,'period_label') ?? trim(($desde.' – '.$hasta), ' –') ?: '—';

                $rfc   = data_get($row,'rfc','—');
                $alias = data_get($row,'alias','—');

                // PESO (Mb)
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

                // COSTO
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

                // ESTATUS
                $statusDb      = (string) data_get($row,'status','');
                $statusDbLower = strtolower($statusDb);

                $statusRaw = data_get($row,'sat_status',
                              data_get($row,'status_sat',
                                data_get($row,'estado', $statusDb !== '' ? $statusDb : 'DONE')));

                $statusKey       = strtoupper(trim((string)($statusRaw ?: 'DONE')));
                $statusText      = (string) data_get($row,'status_sat_text',$statusKey);
                $statusKeyLower  = strtolower($statusKey);
                $statusTextLower = strtolower(trim($statusText));

                // Clase CSS segura (solo a-z0-9-_)
                $statusBadgeClass = Str::slug($statusKeyLower, '-');
                if ($statusBadgeClass === '') $statusBadgeClass = 'unknown';


                // EXPIRACIÓN / DISPONIBILIDAD
                $expiresAt     = data_get($row,'expires_at');
                $isExpiredFlag = (bool) data_get($row,'is_expired', false);

                if (!$isExpiredFlag && $expiresAt) {
                    try { $isExpiredFlag = Carbon::parse($expiresAt)->isPast(); }
                    catch (\Exception $e) { $isExpiredFlag = false; }
                }

                $isExpired = $isExpiredFlag;
                $remaining = data_get($row,'remaining_label', data_get($row,'time_left_label','—:—:—'));

                // PAGADO
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
                data-manual="{{ (int) (data_get($row,'is_manual', data_get($row,'manual', 0)) ? 1 : 0) }}"
              >

                <td class="sat-dl-col-check t-center">
                  <input type="checkbox" name="dl_ids[]" value="{{ $id }}" class="sat-dl-check">
                </td>

                <td class="mono t-center">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</td>

                <td class="t-center">
                  @if($createdObj)
                    <span class="mono">{{ $createdObj->format('Y-m-d H:i:s') }}</span>
                  @elseif($createdAt)
                    <span class="mono">{{ Str::substr((string)$createdAt, 0, 19) }}</span>
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
                  <span class="sat-badge sat-badge-{{ $statusBadgeClass }}">{{ $statusText }}</span>
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

    {{-- MODAL: COTIZADOR (CALCULAR DESCARGA + PDF + PROCEDER A PAGO) --}}
  <div class="sat-modal-backdrop" id="modalQuote" style="display:none;">
    <div class="sat-modal sat-modal-lg">
      <div class="sat-modal-header">
        <div>
          <div class="sat-modal-kicker">Cotizador SAT</div>
          <div class="sat-modal-title">Calcular costo de descarga</div>
          <p class="sat-modal-sub">
            El costo se calcula automáticamente con la lista de precios (Admin). Selecciona RFC y periodo para cotizar, generar PDF o proceder a pago.
          </p>
        </div>
        <button type="button" class="sat-modal-close" data-close="modal-quote" aria-label="Cerrar">✕</button>
      </div>

      <div class="sat-modal-body">
        <div class="sat-step-card">
          <div class="sat-step-kicker">
            <span>Parámetros</span>
            <small>RFC y periodo</small>
          </div>

          <div class="sat-step-grid sat-step-grid-2col">
            <div class="sat-field">
              <div class="sat-field-label">RFC</div>
              <select class="input sat-input-pill" id="quoteRfc">
                <option value="">Selecciona RFC</option>
                @foreach(($rfcOptionsValid ?? []) as $opt)
                  <option value="{{ $opt['rf'] }}">
                    {{ $opt['rf'] }}{{ !empty($opt['alias']) ? ' · '.$opt['alias'] : '' }}
                  </option>
                @endforeach
              </select>
              <div class="sat-modal-note" style="margin-top:6px;">
                Solo aparecen RFCs validados.
              </div>
            </div>

            <div class="sat-field">
              <div class="sat-field-label">Tipo</div>
              <select class="input sat-input-pill" id="quoteTipo">
                <option value="emitidos">Emitidos</option>
                <option value="recibidos">Recibidos</option>
                <option value="ambos" selected>Ambos</option>
              </select>
            </div>

            <div class="sat-field">
              <div class="sat-field-label">Desde</div>
              <input class="input sat-input-pill" id="quoteFrom" type="date">
            </div>

            <div class="sat-field">
              <div class="sat-field-label">Hasta</div>
              <input class="input sat-input-pill" id="quoteTo" type="date">
            </div>
          </div>
        </div>

        <div class="sat-step-card" style="margin-top:14px;">
          <div class="sat-step-kicker">
            <span>Volumen</span>
            <small>Estimación</small>
          </div>

          <div class="sat-step-grid sat-step-grid-2col">
            <div class="sat-field">
              <div class="sat-field-label">Cantidad estimada de XML</div>
              <input class="input sat-input-pill" id="quoteXmlCount" type="number" min="1" step="1" placeholder="1000" value="1000">
            </div>

            <div class="sat-field">
              <div class="sat-field-label">Código de descuento (opcional)</div>
              <input class="input sat-input-pill" id="quoteDiscountCode" type="text" placeholder="PROMO10">
            </div>

            <div class="sat-field">
              <div class="sat-field-label">IVA</div>
              <select class="input sat-input-pill" id="quoteIva">
                <option value="16" selected>16%</option>
                <option value="0">0%</option>
              </select>
            </div>

            <div class="sat-field">
              <div class="sat-field-label">Notas</div>
              <div class="text-muted" style="padding:10px 2px;">
                La tarifa se determina por catálogo y rangos configurados en Admin.
              </div>
            </div>
          </div>
        </div>

        <div class="sat-step-card" style="margin-top:14px;">
          <div class="sat-step-kicker">
            <span>Resultado</span>
            <small>Desglose</small>
          </div>

          <div class="sat-mov-dl" style="margin-top:8px;">
            <div class="sat-mov-row"><dt>Base</dt><dd id="quoteBaseVal">$0.00</dd></div>
            <div class="sat-mov-row"><dt>Descuento (<span id="quoteDiscPct">0</span>%)</dt><dd id="quoteDiscVal">-$0.00</dd></div>
            <div class="sat-mov-row"><dt>Subtotal</dt><dd id="quoteSubtotalVal">$0.00</dd></div>
            <div class="sat-mov-row"><dt>IVA (<span id="quoteIvaPct">16</span>%)</dt><dd id="quoteIvaVal">$0.00</dd></div>
            <div class="sat-mov-row"><dt><b>Total</b></dt><dd><b id="quoteTotalVal">$0.00</b></dd></div>
          </div>

          <div class="sat-modal-note" style="margin-top:10px;">
            <span id="quoteNote">—</span>
          </div>
        </div>
      </div>

      <div class="sat-modal-footer">
        <button type="button" class="btn" data-close="modal-quote">Cerrar</button>
        <button type="button" class="btn soft" id="btnQuoteRecalc">Recalcular</button>
        <button type="button" class="btn primary" id="btnQuotePdf">Generar PDF</button>
      </div>

    </div>
  </div>

  {{-- MODAL: SOLICITUD MANUAL (PROCESO → PAGO) --}}
<div class="sat-modal-backdrop" id="modalManualRequest" style="display:none;">
  <div class="sat-modal sat-modal-lg">
    <div class="sat-modal-header">
      <div>
        <div class="sat-modal-kicker">Descargas manuales</div>
        <div class="sat-modal-title">Solicitar descarga (cotización)</div>
        <p class="sat-modal-sub">
          Define lo necesario (RFC, periodo, tipo, cantidad estimada). Al continuar, se generará el pago.
        </p>
      </div>
      <button type="button" class="sat-modal-close" data-close="modal-manual" aria-label="Cerrar">✕</button>
    </div>

    <div class="sat-modal-body">
      <div class="sat-step-card">
        <div class="sat-step-kicker">
          <span>Paso 1</span>
          <small>Datos de la solicitud</small>
        </div>

        <div class="sat-step-grid sat-step-grid-2col">
          <div class="sat-field">
            <div class="sat-field-label">RFC</div>
            <select class="input sat-input-pill" id="manualRfc">
              <option value="">Selecciona RFC</option>
              @foreach(($rfcOptionsValid ?? []) as $opt)
                <option value="{{ $opt['rf'] }}">
                  {{ $opt['rf'] }}{{ !empty($opt['alias']) ? ' · '.$opt['alias'] : '' }}
                </option>
              @endforeach
            </select>
            <div class="sat-modal-note" style="margin-top:6px;">
              Solo aparecen RFCs validados.
            </div>
          </div>

          <div class="sat-field">
            <div class="sat-field-label">Tipo</div>
            <select class="input sat-input-pill" id="manualTipo">
              <option value="emitidos">Emitidos</option>
              <option value="recibidos">Recibidos</option>
              <option value="ambos" selected>Ambos</option>
            </select>
          </div>

          <div class="sat-field">
            <div class="sat-field-label">Desde</div>
            <input class="input sat-input-pill" type="date" id="manualFrom">
          </div>

          <div class="sat-field">
            <div class="sat-field-label">Hasta</div>
            <input class="input sat-input-pill" type="date" id="manualTo">
          </div>
        </div>
      </div>

      <div class="sat-step-card" style="margin-top:14px;">
        <div class="sat-step-kicker">
          <span>Paso 2</span>
          <small>Cotización y notas</small>
        </div>

        <div class="sat-step-grid sat-step-grid-2col">
          <div class="sat-field">
            <div class="sat-field-label">Cantidad estimada de XML</div>
            <input class="input sat-input-pill" id="manualXmlEstimated" type="number" min="1" step="1" placeholder="Ej. 1000" value="1000">
          </div>

          <div class="sat-field">
            <div class="sat-field-label">Referencia / Cliente (opcional)</div>
            <input class="input sat-input-pill" id="manualRef" type="text" maxlength="120" placeholder="Ej. Cliente XYZ / Caso 123">
          </div>

          <div class="sat-field sat-field-full">
            <div class="sat-field-label">Notas (opcional)</div>
            <textarea class="input sat-input-pill" id="manualNotes" rows="3" placeholder="Detalles relevantes para soporte (si aplica)"></textarea>
          </div>
        </div>

        <div class="sat-modal-note" style="margin-top:10px;">
          El costo final puede ajustarse tras revisar metadata. Si el costo real es mayor, se solicitará pago complementario.
        </div>
      </div>
    </div>

    <div class="sat-modal-footer">
      <button type="button" class="btn" data-close="modal-manual">Cancelar</button>
      <button type="button" class="btn soft" id="btnManualDraftToQuote">
        Ver cotizador (estimado)
      </button>
      <button type="button" class="btn primary" id="btnManualSubmitPay">
        Siguiente: solicitar pago
      </button>
    </div>
  </div>
</div>


</div>
@endsection

@push('scripts')
{{-- Chart.js --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

@php
  // ======================================================
  // P360_SAT (SOT) -> serialización segura sin duplicados
  // ======================================================
  $p360SatCfg = [
    'csrf'      => csrf_token(),
    'isProPlan' => (bool) ($isProPlan ?? ($isPro ?? false)),
    'downloads' => $downloadsForJs ?? [],

    // ✅ Dataset local para gráfica (fallback inmediato)
    'localChart' => [
      'labels' => $weekLabels ?? [],
      'counts' => $weekCounts ?? [],
    ],

    // Para UI: visibles (incluye externo) + validados (para acciones)
    'rfcOptions'      => $rfcOptionsAll   ?? ($rfcOptions ?? []),
    'rfcOptionsValid' => $rfcOptionsValid ?? [],

    'routes' => [
      // Core
      'request'        => $rtReqCreate ?: '',
      'verify'         => $rtVerify    ?: '',
      'mode'           => $rtMode      ?: '',
      'download'       => $rtPkgPost   ?: '',
      'cancel'         => $rtDownloadCancel ?: '',
      'charts'         => $rtCharts    ?: '',

      // Dashboard JSON (Chart/KPIs)
      'dashboardStats' => \Illuminate\Support\Facades\Route::has('cliente.sat.dashboard.stats')
        ? route('cliente.sat.dashboard.stats')
        : '',

      // ✅ Registro externo (invite)
      // sat-dashboard.js está pidiendo ROUTES.externalInvite
      // y algunos tests usan externalRfcInvite: exponemos ambas.
      'externalInvite'    => $rtExternalInvite ?: '',
      'externalRfcInvite' => $rtExternalInvite ?: '',

      // RFC/CSD
      'csdStore'  => $rtCsdStore ?: '',
      'rfcReg'    => $rtRfcReg   ?: '',
      'rfcDelete' => $rtRfcDelete ?: '',
      'alias'     => $rtAlias    ?: '',

      // Carrito
      'cartIndex'    => $rtCartIndex ?: '',
      'cartList'     => ($rtCartList ?: $rtCartIndex) ?: '',
      'cartAdd'      => $rtCartAdd ?: '',
      'cartRemove'   => $rtCartRemove ?: '',
      'cartCheckout' => $rtCartPay ?: '',

      // ZIP
      'zipPattern' => $zipPattern ?: '',

      // Vault
      'vaultIndex'        => $rtVault ?: '',
      'vaultQuick'        => $rtVaultQuick ?: '',
      'vaultFromDownload' => $rtVaultFromDownload ?: '',

      // Cotizador RFC/periodo
      'quoteCalc' => $rtQuoteCalc ?: '',
      'quotePdf'  => $rtQuotePdf  ?: '',

      // Manuales
      'manualQuote' => $rtManualQuote ?: '',
      'manualIndex' => $rtManualIndex ?: '',

      // Calculadora rápida (sin RFC)
      'quickCalc' => $rtQuickCalc ?: '',
      'quickPdf'  => $rtQuickPdf  ?: '',
    ],

    'vault' => $vault ?? [],
  ];
@endphp


{{-- ✅ SOT: Exponer config global ANTES de cargar cualquier JS del SAT --}}
<script>
(function () {
  'use strict';

  // Evita sobreescritura si por alguna razón se inyecta dos veces
  if (!window.P360_SAT || typeof window.P360_SAT !== 'object') {
    window.P360_SAT = {};
  }

  // Inyecta config calculada en PHP (SOT)
  const CFG = @json($p360SatCfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

  // Merge defensivo (por si otras vistas agregan propiedades)
  window.P360_SAT = Object.assign({}, window.P360_SAT, CFG);
})();
</script>

<script>
(function () {
  'use strict';
  // ✅ NO-OP:
  // La gráfica se controla únicamente desde public/assets/client/js/sat-dashboard.js
  // para evitar doble instanciación de Chart.js sobre el mismo canvas.
})();
</script>


<script>
(function () {
  'use strict';

  // Refresh duro con cache-bust (_ts) para evitar que el navegador muestre HTML viejo
  function hardRefresh() {
    try {
      const u = new URL(window.location.href);
      u.searchParams.set('_ts', String(Date.now()));
      window.location.href = u.toString();
    } catch (e) {
      window.location.reload();
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('btnSatPageRefresh');
    if (!btn) return;

    btn.addEventListener('click', function () {
      hardRefresh();
    });
  });

  // Exponer helper por si lo quieres invocar desde otros scripts
  window.P360_SAT_HARD_REFRESH = hardRefresh;
})();
</script>


{{-- ✅ FIX: define applyFilters GLOBAL antes de cualquier script SAT que lo invoque --}}
<script>
(function () {
  'use strict';

  if (typeof window.applyFilters === 'function') return;

  window.applyFilters = function () {
    try {
      // Preferimos el módulo UI si existe
      if (window.P360_SAT_UI && typeof window.P360_SAT_UI.applyFilters === 'function') {
        return window.P360_SAT_UI.applyFilters();
      }

      // Fallback suave: si existe sat-dashboard como función pública
      if (window.P360_SAT && window.P360_SAT.ui && typeof window.P360_SAT.ui.applyFilters === 'function') {
        return window.P360_SAT.ui.applyFilters();
      }
    } catch (e) {}

    // No revienta: simplemente no hace nada
    return null;
  };
})();
</script>


@php
  $SAT_DASH_ABS = public_path('assets/client/js/sat-dashboard.js');
  $SAT_BOOT_ABS = public_path('assets/client/js/sat-index-boot.js');

  $SAT_DASH_V = is_file($SAT_DASH_ABS) ? (string) filemtime($SAT_DASH_ABS) : (string) time();
  $SAT_BOOT_V = is_file($SAT_BOOT_ABS) ? (string) filemtime($SAT_BOOT_ABS) : (string) time();
@endphp

{{-- JS principal del dashboard SAT --}}
<script src="{{ asset('assets/client/js/sat-dashboard.js') }}?v={{ $SAT_DASH_V }}" defer></script>

{{-- BOOTSTRAP extra --}}
<script src="{{ asset('assets/client/js/sat-index-boot.js') }}?v={{ $SAT_BOOT_V }}" defer></script>


@endpush


