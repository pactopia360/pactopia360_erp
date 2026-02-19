{{-- resources/views/cliente/sat/index.blade.php (v4.1 · MODAL-FIRST · Minimal UI · NO FOOTER PILLS) --}}
@extends('layouts.cliente')
@section('title','SAT · Descargas masivas CFDI')
@section('pageClass','page-sat')

@php
  use Illuminate\Support\Carbon;
  use Illuminate\Support\Str;
  use Illuminate\Support\Facades\Route;

  // ======================================================
  // Base user/account
  // ======================================================
  $user   = $user   ?? auth('web')->user();
  $cuenta = $cuenta ?? ($user?->cuenta ?? null);

  // Summary unificado
  $summary = $summary ?? app(\App\Http\Controllers\Cliente\HomeController::class)->buildAccountSummary();

  // RFC externo (Admin)
  $externalRfc      = strtoupper(trim((string) data_get($summary, 'rfc_externo', data_get($summary, 'rfc', ''))));
  $externalVerified = (bool) data_get($summary, 'rfc_external_verified', false);

  // Plan / PRO
  $planFromSummary = strtoupper((string)($summary['plan'] ?? 'FREE'));
  $isProSummary    = (bool)($summary['is_pro'] ?? in_array(strtolower($planFromSummary), ['pro','premium','empresa','business'], true));
  $plan      = $planFromSummary;
  $isProPlan = $isProSummary;

  // Mode DEMO/PROD
  $cookieMode  = request()->cookie('sat_mode');
  $driver      = config('services.sat.download.driver','satws');
  $isLocalDemo = app()->environment(['local','development','testing']) && $driver !== 'satws';
  $mode      = $cookieMode ? strtolower($cookieMode) : ($isLocalDemo ? 'demo' : 'prod');
  $modeSafe  = $mode ?: 'prod';

  // Cuotas SAT
  $asigDefault = $isProPlan ? 12 : 1;
  $asig        = (int)($cuenta->sat_quota_assigned ?? $asigDefault);
  $usadas      = (int)($cuenta->sat_quota_used ?? 0);

  // Dataset descargas (SOT para modals)
  $downloadsPaginator = $downloadsPaginator ?? null;
  $rowsInit = $initialRows
      ?? ($downloadsPaginator ? $downloadsPaginator->items() : null)
      ?? ($rows ?? $downloads ?? $lista ?? []);

  $rowsColl = collect($rowsInit ?? [])
      ->map(fn($r) => is_array($r) ? (object)$r : $r)
      ->filter(function ($row) {
        $tipoRaw = strtolower((string) data_get($row, 'tipo', data_get($row, 'origen', '')));
        if (str_contains($tipoRaw, 'vault') || str_contains($tipoRaw, 'boveda') || str_contains($tipoRaw, 'bóveda')) return false;
        return true;
      })
      ->values();

  // KPIs simples
  $periodFrom = now()->subDays(30);
  $downloads30 = (int)($summary['sat_files_period'] ?? $rowsColl->filter(function($row) use($periodFrom){
      $created = data_get($row,'created_at', data_get($row,'createdAt', null));
      if (!$created) return false;
      try { return Carbon::parse($created)->greaterThanOrEqualTo($periodFrom); }
      catch (\Throwable) { return false; }
  })->count());

  $downloadsTotal = (int)($summary['sat_files_total'] ?? $rowsColl->count());

  $reqStart     = (int)($summary['sat_req_start']     ?? $asig);
  $reqDone      = (int)($summary['sat_req_done']      ?? $downloadsTotal);
  $reqAvailable = (int)($summary['sat_req_available'] ?? max(0, $reqStart - $reqDone));

  // Credenciales/RFCs
  $credList = collect($credList ?? []);

  // RFC options: visibles (incluye externo) + validos
  $rfcOptions = $rfcOptions ?? (function () use ($credList, $externalRfc) {
      $out = [];
      foreach ($credList as $c) {
          $r = strtoupper(trim((string)($c->rfc ?? '')));
          if ($r === '') continue;

          $estatusRaw = strtolower((string)($c->estatus ?? ''));
          $isValidated =
              !empty($c->validado ?? null)
              || !empty($c->validated_at ?? null)
              || in_array($estatusRaw, ['ok','valido','válido','validado','valid'], true);

          $alias = trim((string)($c->razon_social ?? $c->alias ?? ''));
          $out[$r] = [
              'rf'        => $r,
              'alias'     => $alias !== '' ? $alias : null,
              'validated' => (bool)$isValidated,
              'source'    => 'sat_credentials',
          ];
      }

      if ($externalRfc !== '') {
          $r = strtoupper($externalRfc);
          if (!isset($out[$r])) {
              $out[$r] = [
                  'rf'        => $r,
                  'alias'     => 'Registro externo',
                  'validated' => false,
                  'source'    => 'external_registry',
                  'external'  => true,
              ];
          }
      }

      $list = array_values($out);
      usort($list, fn($a,$b)=>strcmp((string)$a['rf'], (string)$b['rf']));
      return $list;
  })();

  $rfcOptionsAll   = is_array($rfcOptions) ? $rfcOptions : collect($rfcOptions)->values()->all();
  $rfcOptionsValid = collect($rfcOptionsAll)->filter(fn($o)=>!empty($o['validated']))->values()->all();
  $kRfcValid = (int) count($rfcOptionsValid);

  // ------------------------------------------------------
  // Rutas (defensivas)
  // ------------------------------------------------------
  $rtMode       = Route::has('cliente.sat.mode')               ? route('cliente.sat.mode')               : null;
  $rtVerify     = Route::has('cliente.sat.verify')             ? route('cliente.sat.verify')             : '#';
  $rtReqCreate  = Route::has('cliente.sat.request')            ? route('cliente.sat.request')            : '#';
  $rtZipGet     = Route::has('cliente.sat.zip')                ? route('cliente.sat.zip',['id'=>'__ID__']) : '';

  $rtManualIndex  = Route::has('cliente.sat.manual.index')  ? route('cliente.sat.manual.index')  : null;
  $rtManualQuote  = Route::has('cliente.sat.manual.quote')  ? route('cliente.sat.manual.quote')  : null;

  $rtQuoteCalc = Route::has('cliente.sat.quote.calc') ? route('cliente.sat.quote.calc') : null;
  $rtQuotePdf  = Route::has('cliente.sat.quote.pdf')  ? route('cliente.sat.quote.pdf')  : null;

  $rtQuickCalc = Route::has('cliente.sat.quick.calc') ? route('cliente.sat.quick.calc') : null;
  $rtQuickPdf  = Route::has('cliente.sat.quick.pdf')  ? route('cliente.sat.quick.pdf')  : null;

  // FIEL externo preferido
  $hasFielList = Route::has('cliente.sat.fiel.external.list');

  $rtFielList     = $hasFielList ? route('cliente.sat.fiel.external.list') : '';
  $rtFielInvite   = Route::has('cliente.sat.fiel.external.invite') ? route('cliente.sat.fiel.external.invite') : null;

  // ✅ Invitación “oficial” (la que tu backend exige)
  // - Si existe named route, úsala.
  // - Si no existe, fallback duro al path.
  $rtExternalInvite = Route::has('cliente.sat.external.invite')
    ? route('cliente.sat.external.invite')
    : url('/cliente/sat/external/invite');


  $rtFielDownload = Route::has('cliente.sat.fiel.external.download') ? route('cliente.sat.fiel.external.download',['id'=>'__ID__']) : '';
  $rtFielUpdate   = Route::has('cliente.sat.fiel.external.update')   ? route('cliente.sat.fiel.external.update',['id'=>'__ID__'])   : '';
  $rtFielDestroy  = Route::has('cliente.sat.fiel.external.destroy')  ? route('cliente.sat.fiel.external.destroy',['id'=>'__ID__'])  : '';
  $rtFielPassword = Route::has('cliente.sat.fiel.external.password') ? route('cliente.sat.fiel.external.password',['id'=>'__ID__']) : '';

  // External ZIP (fallback si no hay FIEL externo)
  $rtExternalZipList = (!$hasFielList && Route::has('cliente.sat.external.zip.list')) ? route('cliente.sat.external.zip.list') : '';
  $rtExternalZipRegister = Route::has('cliente.sat.external.zip.register') ? route('cliente.sat.external.zip.register') : '';

  // Conexiones SAT
  $rtCsdStore   = Route::has('cliente.sat.credenciales.store') ? route('cliente.sat.credenciales.store') : '#';
  $rtAlias      = Route::has('cliente.sat.alias')              ? route('cliente.sat.alias')              : '#';
  $rtRfcReg     = Route::has('cliente.sat.rfc.register')       ? route('cliente.sat.rfc.register')       : '#';
  $rtRfcDelete  = Route::has('cliente.sat.rfc.delete')         ? route('cliente.sat.rfc.delete')         : '#';

  // Vault
  $rtVault      = Route::has('cliente.sat.vault') ? route('cliente.sat.vault') : '#';
  $rtCartIndex  = Route::has('cliente.sat.cart.index') ? route('cliente.sat.cart.index') : null;
  $rtCartPay    = Route::has('cliente.sat.cart.checkout') ? route('cliente.sat.cart.checkout') : null;
  $vaultCtaUrl  = $rtCartIndex ?? $rtCartPay ?? $rtVault;

  // Vault cfg (defensivo)
  $vaultCfg = $vault ?? [];
  $vaultQuotaGb = (float)($vaultCfg['quota_gb'] ?? 0.0);
  $vaultUsedGb  = (float)($vaultCfg['used_gb'] ?? 0.0);
  if ($vaultUsedGb < 0) $vaultUsedGb = 0.0;
  $vaultActive = (bool)($vaultCfg['enabled'] ?? ($vaultQuotaGb > 0));
  $vault = array_merge([
      'enabled' => $vaultActive,
      'quota_gb' => $vaultQuotaGb,
      'used_gb'  => $vaultUsedGb,
  ], $vaultCfg);

  // Config JS (SOT)
  $p360SatCfg = [
    'csrf'      => csrf_token(),
    'isProPlan' => (bool) $isProPlan,
    'plan'      => (string) $plan,
    'mode'      => (string) $modeSafe,
    'baseUrl'   => url('/'),

    'kpi' => [
      'reqAvailable' => (int)$reqAvailable,
      'downloads30'  => (int)$downloads30,
      'downloadsAll' => (int)$downloadsTotal,
      'rfcsValid'    => (int)$kRfcValid,
    ],

    'rfcOptions'      => $rfcOptionsAll,
    'rfcOptionsValid' => $rfcOptionsValid,

    'downloads' => $rowsColl->values()->all(),

    'routes' => [
      'mode'      => $rtMode ?: '',
      'verify'    => $rtVerify ?: '',
      'request'   => $rtReqCreate ?: '',
      'zipPattern'=> $rtZipGet ?: '',

      'manualIndex' => $rtManualIndex ?: '',
      'manualQuote' => $rtManualQuote ?: '',

      'quoteCalc' => $rtQuoteCalc ?: '',
      'quotePdf'  => $rtQuotePdf  ?: '',

      'quickCalc' => $rtQuickCalc ?: '',
      'quickPdf'  => $rtQuickPdf  ?: '',

      'fielList'     => $rtFielList ?: '',
      'fielInvite'   => $rtFielInvite ?: '',
      'externalZipInvite' => $rtExternalInvite ?: '',
      'fielDownload' => $rtFielDownload ?: '',
      'fielUpdate'   => $rtFielUpdate ?: '',
      'fielDestroy'  => $rtFielDestroy ?: '',
      'fielPassword' => $rtFielPassword ?: '',

      'externalZipList'     => $rtExternalZipList ?: '',
      'externalZipRegister' => $rtExternalZipRegister ?: '',

      'vaultUrl' => $rtVault ?: '',
      'vaultCta' => $vaultCtaUrl ?: '',
      'cartIndex' => $rtCartIndex ?: '',
      'cartCheckout' => $rtCartPay ?: '',

      'csdStore' => $rtCsdStore ?: '',
      'alias'    => $rtAlias ?: '',
      'rfcReg'   => $rtRfcReg ?: '',
      'rfcDelete'=> $rtRfcDelete ?: '',
    ],

    'external' => [
      'rfc' => (string)($externalRfc ?: ''),
      'verified' => (bool)$externalVerified,
    ],

    'vault' => $vault,
  ];
@endphp

@push('styles')
@php
  $CSS_REL = 'assets/client/css/sat/sat-portal-v1.css';
  $CSS_ABS = public_path($CSS_REL);
  $CSS_V   = is_file($CSS_ABS) ? (string) filemtime($CSS_ABS) : null;
@endphp
<link rel="stylesheet" href="{{ asset($CSS_REL) }}{{ $CSS_V ? ('?v='.$CSS_V) : '' }}">
@endpush

@section('content')
<div class="sat4" id="sat4App"
  data-plan="{{ $plan }}"
  data-mode="{{ $modeSafe }}"
>
  {{-- Topbar minimal --}}
  <header class="sat4-top">
    <div class="sat4-brand">
      <div class="sat4-dot {{ $modeSafe === 'demo' ? 'is-demo' : 'is-prod' }}"></div>
      <div class="sat4-title">
        <div class="sat4-h1">SAT</div>
        <div class="sat4-h2">Descargas masivas</div>
      </div>
    </div>

        <div class="sat4-actions">
      @if($rtMode)
        <button class="sat4-chip" type="button" id="sat4Mode" data-url="{{ $rtMode }}">
          <span class="sat4-chip-ico">●</span>
          <span class="sat4-chip-txt">{{ $modeSafe === 'demo' ? 'DEMO' : 'PROD' }}</span>
        </button>
      @endif

      <button class="sat4-chip" type="button" id="sat4Refresh">
        <span class="sat4-chip-ico">↻</span>
        <span class="sat4-chip-txt">Refrescar</span>
      </button>

      {{-- Campana / Notificaciones (portal style) --}}
      <div style="position:relative;">
        <button type="button" class="sat4-bell" id="sat4Bell" aria-label="Notificaciones">
          🔔
          <span class="sat4-bell-dot"></span>
        </button>

        <div class="sat4-notify" id="sat4Notify" aria-label="Notificaciones">
          <div class="sat4-notify-head">
            <div class="sat4-notify-ttl">Notificaciones</div>
            <button type="button" class="sat4-notify-clear" id="sat4NotifyClear">Descartar todo</button>
          </div>
          <div class="sat4-notify-list" id="sat4NotifyList">
            <div class="sat4-note">
              <div class="sat4-note-ico">🛰️</div>
              <div class="sat4-note-meta">
                <div class="sat4-note-title">SAT</div>
                <div class="sat4-note-sub">Sin notificaciones aún.</div>
                <div class="sat4-note-time">—</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

  </header>

  {{-- KPIs (ERP cards) --}}
  <section class="sat4-kpis2" aria-label="Indicadores SAT">
    <div class="sat4-kpi2 is-accent">
      <div class="sat4-kpi2-ico">🛰️</div>
      <div class="sat4-kpi2-meta">
        <div class="sat4-kpi2-l">Peticiones</div>
        <div class="sat4-kpi2-n mono" id="kpiReq">{{ number_format((int)$reqAvailable) }}</div>
        <div class="sat4-kpi2-h">Disponibles</div>
      </div>
    </div>
    <div class="sat4-kpi2">
      <div class="sat4-kpi2-ico">📦</div>
      <div class="sat4-kpi2-meta">
        <div class="sat4-kpi2-l">30 días</div>
        <div class="sat4-kpi2-n mono" id="kpi30">{{ number_format((int)$downloads30) }}</div>
        <div class="sat4-kpi2-h">Descargas</div>
      </div>
    </div>
    <div class="sat4-kpi2">
      <div class="sat4-kpi2-ico">✅</div>
      <div class="sat4-kpi2-meta">
        <div class="sat4-kpi2-l">RFCs OK</div>
        <div class="sat4-kpi2-n mono" id="kpiRfcs">{{ number_format((int)$kRfcValid) }}</div>
        <div class="sat4-kpi2-h">{{ $kRfcValid>0 ? 'Verificado' : 'Pendiente' }}</div>
      </div>
    </div>
    <div class="sat4-kpi2">
      <div class="sat4-kpi2-ico">📦</div>
      <div class="sat4-kpi2-meta">
        <div class="sat4-kpi2-l">Plan</div>
        <div class="sat4-kpi2-n">{{ $isProPlan ? 'PRO' : 'FREE' }}</div>
        <div class="sat4-kpi2-h">{{ $isProPlan ? 'Acceso completo' : 'Limitado' }}</div>
      </div>
    </div>
  </section>

  {{-- Workspace principal (como ERP moderno) --}}
  <section class="sat4-workspace" aria-label="Nueva descarga">
    <div class="sat4-work-card">
      <div class="sat4-work-head">
        <div class="sat4-work-titles">
          <div class="sat4-work-ttl">Nueva descarga</div>
          <div class="sat4-work-sub">Emitidos / Recibidos / Ambos</div>
        </div>

        <div class="sat4-work-actions">
          <button class="sat4-btn sat4-btn-ghost" type="button" data-open="sat4ModalConnections">Conexiones</button>
          <button class="sat4-btn" type="button" data-open="sat4ModalExternal">RFC externo</button>
          <button class="sat4-btn sat4-btn-primary" type="button" data-open="sat4ModalDownloads">Ver todas</button>
        </div>
      </div>

      <div class="sat4-work-grid">
        <button class="sat4-work-cta" type="button" data-open="sat4ModalRequest">
          <div class="sat4-work-cta-ico">⬇️</div>
          <div class="sat4-work-cta-t">
            <div class="sat4-work-cta-ttl">Crear solicitud</div>
            <div class="sat4-work-cta-sub">Selecciona tipo, fechas y RFC</div>
          </div>
        </button>

        <button class="sat4-work-tile" type="button" data-open="sat4ModalQuote">
          <div class="sat4-work-tile-ico">🧮</div>
          <div class="sat4-work-tile-ttl">Cotizar</div>
          <div class="sat4-work-tile-sub">Costo + PDF</div>
        </button>

        <button class="sat4-work-tile" type="button" data-open="sat4ModalExternal">
          <div class="sat4-work-tile-ico">🧾</div>
          <div class="sat4-work-tile-ttl">RFC externo</div>
          <div class="sat4-work-tile-sub">ZIP / Invitación</div>
        </button>

        <a class="sat4-work-tile" href="{{ $vaultCtaUrl ?: '#' }}" style="text-decoration:none;">
          <div class="sat4-work-tile-ico">📚</div>
          <div class="sat4-work-tile-ttl">Bóveda</div>
          <div class="sat4-work-tile-sub">Abrir / Ampliar</div>
        </a>

      </div>
    </div>
  </section>

    {{-- Portal de Descarga (progreso + estado actual) --}}
  <section class="sat4-portal" aria-label="Portal Descarga SAT">
    <div class="sat4-portal-card">
      <div class="sat4-portal-head">
        <div>
          <div class="sat4-portal-ttl">DESCARGA SAT</div>
          <div class="sat4-portal-sub">Progreso y estado de la descarga actual</div>
        </div>

        <div style="display:flex; gap:8px; flex-wrap:wrap;">
          <button class="sat4-btn sat4-btn-primary" type="button" id="sat4StartPortal">Iniciar descarga</button>
          <button class="sat4-btn" type="button" data-open="sat4ModalDownloads">Ver descargas</button>
        </div>
      </div>

      <div class="sat4-portal-body">
        {{-- Ring --}}
        <div class="sat4-ring">
          <div class="sat4-ring-wrap" id="sat4RingWrap" style="--p:0;">
            <div class="sat4-ring-center">
              <div class="sat4-ring-p" id="sat4RingP">0%</div>
              <div class="sat4-ring-l" id="sat4RingL">Sin proceso</div>
            </div>
          </div>

          <div class="sat4-ring-actions">
            <button class="sat4-btn sat4-btn-ghost" type="button" id="sat4RingRefresh">Actualizar</button>
            <button class="sat4-btn" type="button" data-open="sat4ModalRequest">Avanzado</button>
          </div>

          <div class="sat4-help">
            Tip: “Iniciar descarga” abre el selector de <b>Mes/Año</b> (como portal).
            El modo <b>Avanzado</b> usa rango de fechas y Multi-RFC.
          </div>
        </div>

        {{-- Estado actual --}}
        <div class="sat4-status">
          <div class="sat4-status-ttl">Estado de la Descarga Actual</div>

          <div class="sat4-status-grid">
            <div class="sat4-stat">
              <div class="sat4-stat-n mono" id="sat4StatSat">0</div>
              <div class="sat4-stat-l">Comprobantes SAT</div>
            </div>
            <div class="sat4-stat">
              <div class="sat4-stat-n mono" id="sat4StatNew">0</div>
              <div class="sat4-stat-l">Comprobantes nuevos</div>
            </div>
            <div class="sat4-stat">
              <div class="sat4-stat-n mono" id="sat4StatFail">0</div>
              <div class="sat4-stat-l">Comprobantes fallidos</div>
            </div>
            <div class="sat4-stat">
              <div class="sat4-stat-n mono" id="sat4StatReg">0</div>
              <div class="sat4-stat-l">Comprobantes registrados</div>
            </div>
          </div>

          <div class="sat4-mini" id="sat4StatusHint">—</div>
        </div>
      </div>
    </div>
  </section>


  {{-- Actividad reciente (tabla abajo, sin cortes) --}}
  <section class="sat4-activity2" aria-label="Actividad reciente">
    <div class="sat4-activity2-head">
      <div>
        <div class="sat4-activity2-ttl">Actividad reciente</div>
        <div class="sat4-activity2-sub">Últimas descargas · estado + acción contextual</div>
      </div>
      <button class="sat4-btn sat4-btn-primary" type="button" data-open="sat4ModalDownloads">Ver todas</button>
    </div>

    <div class="sat4-activity2-card">
      <div class="sat4-tablewrap">
        <table class="sat4-table2" aria-label="Tabla de actividad">
          <thead>
            <tr>
              <th>RFC</th>
              <th>Periodo</th>
              <th>Estado</th>
              <th class="sat4-th-act">Acción</th>
            </tr>
          </thead>
          <tbody id="sat4ActivityBody">
            <tr><td colspan="4" class="sat4-td-empty">Cargando…</td></tr>
          </tbody>
        </table>
      </div>
      <div class="sat4-activity2-foot">
        <button class="sat4-btn" type="button" data-open="sat4ModalDownloads">Ver todas</button>
      </div>
    </div>
  </section>

</div>

{{-- =========================
   MODALS / DRAWERS
========================= --}}

<div class="sat4-backdrop" id="sat4Backdrop" style="display:none;"></div>

{{-- Modal: Nueva solicitud --}}
<div class="sat4-modal" id="sat4ModalRequest" style="display:none;" role="dialog" aria-modal="true">
  <div class="sat4-modal-card">
    <div class="sat4-modal-head">
      <div class="sat4-modal-title">Nueva descarga</div>
      <button type="button" class="sat4-x" data-close>✕</button>
    </div>

    <form id="sat4ReqForm" method="post" action="{{ $rtReqCreate }}">
      @csrf

      <div class="sat4-form">
        <div class="sat4-row">
          <select class="sat4-in" name="tipo" aria-label="Tipo">
            <option value="emitidos">Emitidos</option>
            <option value="recibidos">Recibidos</option>
            <option value="ambos">Ambos</option>
          </select>
          <input class="sat4-in" type="date" name="from" required aria-label="Desde">
          <input class="sat4-in" type="date" name="to" required aria-label="Hasta">
        </div>

        <div class="sat4-row sat4-row--tight">
            <div class="sat4-field sat4-field--grow">
              <div class="sat4-label">RFC</div>

              {{-- ✅ Lista integrada: muestra TODOS (válidos + pendientes).
                  - Los no validados van deshabilitados y marcados como (pendiente)
                  - Si no hay válidos, el select NO se bloquea: se ve la lista pero no permite seleccionar inválidos
              --}}
              <select class="sat4-in" name="rfc_single" id="sat4RfcSingle" aria-label="RFC">
                <option value="">{{ $kRfcValid > 0 ? 'Selecciona RFC…' : 'No tienes RFC verificados (revisa Conexiones)' }}</option>

                @foreach($rfcOptionsAll as $opt)
                  @php
                    $rf   = (string)($opt['rf'] ?? '');
                    $al   = (string)($opt['alias'] ?? '');
                    $ok   = (bool)($opt['validated'] ?? false);

                    // externo
                    $isExt = !empty($opt['external'] ?? false) || (($opt['source'] ?? '') === 'external_registry');

                    $suffix = $ok ? '' : ' (pendiente)';
                    if ($isExt && !$ok) $suffix = ' (externo · pendiente)';
                    if ($isExt && $ok)  $suffix = ' (externo)';
                  @endphp

                  <option value="{{ $rf }}" {{ $ok ? '' : 'disabled' }}>
                    {{ $rf }}{{ $al !== '' ? ' · '.$al : '' }}{{ $suffix }}
                  </option>
                @endforeach
              </select>

              @if($kRfcValid <= 0)
                <div class="sat4-help2">
                  Para poder solicitar descargas, primero valida tu RFC en <b>Conexiones</b>.
                </div>
              @else
                <div class="sat4-help2">
                  Solo puedes seleccionar RFCs <b>verificados</b>. Los pendientes aparecen deshabilitados.
                </div>
              @endif
            </div>

            <div class="sat4-field sat4-field--auto">
              <div class="sat4-label">&nbsp;</div>
              <button class="sat4-btn sat4-btn-ghost" type="button" id="sat4ReqMultiBtn" {{ $kRfcValid>0 ? '' : 'disabled' }}>
                Multi-RFC
              </button>
            </div>

            <div class="sat4-field sat4-field--auto">
              <div class="sat4-label">&nbsp;</div>
              <button class="sat4-btn sat4-btn-primary" type="submit" {{ $kRfcValid>0 ? '' : 'disabled' }}>
                Solicitar
              </button>
            </div>
          </div>


        {{-- Multi RFC --}}
        <div class="sat4-multi" id="sat4MultiWrap" style="display:none;">
          <div class="sat4-multi-head">
            <div class="sat4-mini">Selecciona RFCs</div>
            <button type="button" class="sat4-btn sat4-btn-ghost" id="sat4MultiAll">Todos</button>
          </div>

          <div class="sat4-multi-list" id="sat4MultiList">
            @foreach($rfcOptionsAll as $opt)
              @php
                $rf = (string)($opt['rf'] ?? '');
                $ok = (bool)($opt['validated'] ?? false);
                $al = trim((string)($opt['alias'] ?? ''));
              @endphp

              <label class="sat4-check {{ $ok ? '' : 'is-disabled' }}">
                <input type="checkbox"
                      class="sat4-rfc-item"
                      value="{{ $rf }}"
                      {{ $ok ? 'checked' : '' }}
                      {{ $ok ? '' : 'disabled' }}>
                <span class="sat4-check-t mono">{{ $rf }}</span>
                <span class="sat4-check-sub">{{ $ok ? 'Verificado' : 'Pendiente' }}{{ $al!=='' ? ' · '.$al : '' }}</span>
              </label>
            @endforeach
          </div>

          <select name="rfcs[]" id="sat4RfcsHidden" multiple hidden>
            @foreach($rfcOptionsValid as $opt)
              <option value="{{ $opt['rf'] }}" selected>{{ $opt['rf'] }}</option>
            @endforeach
          </select>

          <div class="sat4-mini" style="margin-top:8px;">
            (Si usas Multi-RFC, el selector “RFC…” se ignora.)
          </div>
        </div>
      </div>
    </form>

    <div class="sat4-modal-foot">
      <button type="button" class="sat4-btn" data-close>Cerrar</button>
      <button type="button" class="sat4-btn sat4-btn-ghost" id="sat4Verify" data-url="{{ $rtVerify }}">Verificar</button>
    </div>
  </div>
</div>

{{-- Modal: Cotizador --}}
<div class="sat4-modal" id="sat4ModalQuote" style="display:none;" role="dialog" aria-modal="true" aria-label="Cotizador SAT">
  <div class="sat4-modal-card sat4-modal-card--quote">
    <div class="sat4-modal-head">
      <div>
        <div class="sat4-modal-title">Cotizar</div>
        <div class="sat4-mini" style="margin-top:4px;">Calcula costo por XML y aplica código de descuento (si existe).</div>
      </div>
      <button type="button" class="sat4-x" data-close aria-label="Cerrar">✕</button>
    </div>

    <div class="sat4-form">
      <div class="sat4-row sat4-quote-grid">
        <div class="sat4-qfield">
          <div class="sat4-label">XML</div>
          <input class="sat4-in mono" id="qXml" type="number" min="1" step="1" placeholder="Ej. 1000" value="1000">
        </div>

        <div class="sat4-qfield sat4-qfield--code">
          <div class="sat4-label">Código de descuento</div>
          <input class="sat4-in mono" id="qDisc" type="text" placeholder="Ej. SOCIO20">
        </div>

        <div class="sat4-qfield sat4-qfield--iva">
          <div class="sat4-label">IVA</div>
          <select class="sat4-in" id="qIva">
            <option value="16" selected>IVA 16%</option>
            <option value="0">IVA 0%</option>
          </select>
        </div>

        <div class="sat4-qfield sat4-qfield--applied">
          <div class="sat4-mini sat4-mini-inline">
            Aplicado: <b class="mono" id="qDiscApplied">—</b>
          </div>
        </div>
      </div>


      <div class="sat4-row sat4-row-actions">
        <button class="sat4-btn sat4-btn-primary" type="button" id="qCalc" {{ $rtQuickCalc ? '' : 'disabled' }}>Calcular</button>
        <button class="sat4-btn" type="button" id="qPdf" {{ $rtQuickPdf ? '' : 'disabled' }}>PDF</button>
        <div class="sat4-mini" id="qNote" style="margin-left:auto;">—</div>
      </div>

      <div class="sat4-quote-card" id="qOut" aria-label="Resultado cotización">
        <div class="sat4-quote-row">
          <span class="sat4-quote-k">Base</span>
          <b class="sat4-quote-v mono" id="qBase">$0.00</b>
        </div>

        <div class="sat4-quote-row">
          <span class="sat4-quote-k mono" id="qDiscLabel">Descuento</span>
          <b class="sat4-quote-v mono" id="qDesc">-$0.00</b>
        </div>

        <div class="sat4-quote-row">
          <span class="sat4-quote-k">IVA</span>
          <b class="sat4-quote-v mono" id="qIvaV">$0.00</b>
        </div>

        <div class="sat4-quote-divider"></div>

        <div class="sat4-quote-row sat4-quote-total">
          <span class="sat4-quote-k">Total</span>
          <b class="sat4-quote-v mono" id="qTotal">$0.00</b>
        </div>

        <div class="sat4-quote-foot">
          <span class="sat4-mini" id="qTariffNote">—</span>
        </div>
      </div>
    </div>

    <div class="sat4-modal-foot">
      <button type="button" class="sat4-btn" data-close>Cerrar</button>
    </div>
  </div>
</div>


{{-- Modal: Mis descargas --}}
<div class="sat4-modal sat4-modal-xl" id="sat4ModalDownloads" style="display:none;" role="dialog" aria-modal="true">
  <div class="sat4-modal-card">
    <div class="sat4-modal-head">
      <div class="sat4-modal-title">Mis descargas</div>
      <button type="button" class="sat4-x" data-close>✕</button>
    </div>

    <div class="sat4-form">
      <div class="sat4-row">
        <input class="sat4-in" id="dSearch" type="text" placeholder="Buscar…">
        <select class="sat4-in" id="dStatus">
          <option value="">Estado</option>
          <option value="pending">Pendiente</option>
          <option value="processing">Proceso</option>
          <option value="done">Listo</option>
          <option value="paid">Pagado</option>
          <option value="expired">Expirado</option>
        </select>
        <button class="sat4-btn sat4-btn-primary" type="button" id="dRender">Aplicar</button>
      </div>
    </div>

    <div class="sat4-table" id="dTable">
      <div class="sat4-mini" style="padding:12px;">Cargando…</div>
    </div>

    <div class="sat4-modal-foot">
      <button type="button" class="sat4-btn" data-close>Cerrar</button>
    </div>
  </div>
</div>

{{-- Modal: RFC externo --}}
<div class="sat4-modal sat4-modal-xl" id="sat4ModalExternal" style="display:none;" role="dialog" aria-modal="true">
  <div class="sat4-modal-card">
    <div class="sat4-modal-head">
      <div>
        <div class="sat4-modal-title">RFC externo</div>
        <div class="sat4-mini" style="margin-top:4px;">
          ZIP / Invitación · Admin-like · acciones por registro
        </div>
      </div>
      <button type="button" class="sat4-x" data-close>✕</button>
    </div>

    <div class="sat4-form">
      <div class="sat4-row sat4-row--ext-actions">
        <button class="sat4-btn sat4-btn-primary" type="button" id="exOpenUpload">Subir ZIP</button>
        <button class="sat4-btn" type="button" id="exOpenInvite" {{ $rtFielInvite ? '' : 'disabled' }}>Invitar</button>
        <button class="sat4-btn sat4-btn-ghost" type="button" id="exRefresh">Actualizar</button>

        <div class="sat4-ext-hint" style="margin-left:auto;">
          <span class="sat4-mini">RFCs OK:</span>
          <b class="mono">{{ number_format((int)$kRfcValid) }}</b>
        </div>
      </div>
    </div>

    {{-- ✅ RFCs válidos integrados (server-side, no depende de JS) --}}
    <div class="sat4-ext">
      <div class="sat4-ext-sec">
        <div class="sat4-ext-sec-head">
          <div class="sat4-ext-sec-ttl">RFCs verificados</div>
          <div class="sat4-mini">Disponibles para operar</div>
        </div>

        <div class="sat4-ext-chips" aria-label="RFCs verificados">
          @if(!empty($rfcOptionsValid) && count($rfcOptionsValid) > 0)
            @foreach($rfcOptionsValid as $opt)
              @php
                $rf = (string)($opt['rf'] ?? '');
                $al = trim((string)($opt['alias'] ?? ''));
                $isExt = !empty($opt['external'] ?? false) || (($opt['source'] ?? '') === 'external_registry');
              @endphp
              <div class="sat4-ext-chip" title="{{ $al !== '' ? $al : $rf }}">
                <span class="sat4-ext-chip-dot {{ $isExt ? 'is-ext' : 'is-ok' }}"></span>
                <span class="sat4-ext-chip-rfc mono">{{ $rf }}</span>
                @if($al !== '')
                  <span class="sat4-ext-chip-al">{{ $al }}</span>
                @endif
                @if($isExt)
                  <span class="sat4-ext-chip-tag">externo</span>
                @endif
              </div>
            @endforeach
          @else
            <div class="sat4-ext-empty">
              <div class="sat4-mini">
                No tienes RFCs verificados aún. Ve a <b>Conexiones</b> y valida tu RFC.
              </div>
            </div>
          @endif
        </div>
      </div>

      {{-- Lista dinámica (JS) --}}
      <div class="sat4-ext-sec">
        <div class="sat4-ext-sec-head">
          <div class="sat4-ext-sec-ttl">Archivos / registros externos</div>
          <div class="sat4-mini">Aquí aparecen ZIPs/FIEL externos y sus acciones</div>
        </div>

        <div class="sat4-ext-list" id="exTable">
          <div class="sat4-mini" style="padding:12px;">Cargando…</div>
        </div>
      </div>
    </div>

    <div class="sat4-modal-foot">
      <button type="button" class="sat4-btn" data-close>Cerrar</button>
    </div>
  </div>
</div>


{{-- Modal: Conexiones --}}
<div class="sat4-modal sat4-modal-xl" id="sat4ModalConnections" style="display:none;" role="dialog" aria-modal="true">
  <div class="sat4-modal-card">
    <div class="sat4-modal-head">
      <div class="sat4-modal-title">Conexiones</div>
      <button type="button" class="sat4-x" data-close>✕</button>
    </div>

    <div class="sat4-pad">
      @include('cliente.sat._partials.connections_clean', [
        'externalRfc'      => $externalRfc ?? '',
        'externalVerified' => (bool)($externalVerified ?? false),
        'credList'         => $credList ?? collect(),
        'plan'             => $plan ?? 'FREE',
        'rtCsdStore'       => $rtCsdStore ?? '#',
        'rtAlias'          => $rtAlias ?? '#',
        'rtRfcReg'         => $rtRfcReg ?? '#',
        'rtRfcDelete'      => $rtRfcDelete ?? '#',
        'rtExternalInvite' => $rtFielInvite ?? null,
      ])
    </div>

    <div class="sat4-modal-foot">
      <button type="button" class="sat4-btn" data-close>Cerrar</button>
    </div>
  </div>
</div>

{{-- Submodal: Upload ZIP externo --}}
<div class="sat4-modal" id="sat4ModalExtUpload" style="display:none;" role="dialog" aria-modal="true">
  <div class="sat4-modal-card">
    <div class="sat4-modal-head">
      <div class="sat4-modal-title">Subir ZIP</div>
      <button type="button" class="sat4-x" data-close>✕</button>
    </div>

    <form id="exUploadForm" class="sat4-form" enctype="multipart/form-data">
      @csrf

      <input type="hidden" name="cuenta_id" value="{{ (string)($cuenta->id ?? '') }}">
      <input type="hidden" name="admin_account_id" value="{{ (string)($cuenta->admin_account_id ?? '') }}">
      <input type="hidden" name="email" value="{{ (string)($user->email ?? '') }}">       
      <input type="hidden" name="cuenta_id" value="{{ (string)($cuenta->id ?? '') }}">
      <input type="hidden" name="admin_account_id" value="{{ (string)($cuenta->admin_account_id ?? '') }}">

      <div class="sat4-row">
        <input class="sat4-in mono" name="rfc" id="exRfc" placeholder="RFC" value="{{ $externalRfc ?: '' }}">
        <input class="sat4-in" type="password" name="fiel_password" id="exPass" placeholder="Password FIEL">
        <input class="sat4-in" type="file" name="zip" id="exZip" accept=".zip,application/zip">
      </div>

      <div class="sat4-row">
        <input class="sat4-in" name="reference" id="exRef" placeholder="Referencia">
        <button class="sat4-btn sat4-btn-primary" type="button" id="exSend">Enviar</button>
      </div>

      <div class="sat4-mini" id="exStatus">—</div>
    </form>

    <div class="sat4-modal-foot">
      <button type="button" class="sat4-btn" data-close>Cerrar</button>
    </div>
  </div>
</div>

{{-- Submodal: Invitar externo --}}
<div class="sat4-modal" id="sat4ModalExtInvite" style="display:none;" role="dialog" aria-modal="true">
  <div class="sat4-modal-card">
    <div class="sat4-modal-head">
      <div class="sat4-modal-title">Invitar</div>
      <button type="button" class="sat4-x" data-close>✕</button>
    </div>

    <div class="sat4-form">
      <div class="sat4-row">
        <input class="sat4-in" id="invEmail" type="email" placeholder="correo@ejemplo.com">
        <input class="sat4-in" id="invRef" type="text" placeholder="Referencia">
      </div>

      <div class="sat4-row">
        <button class="sat4-btn sat4-btn-primary" type="button" id="invSend" {{ $rtExternalInvite ? '' : 'disabled' }}>Enviar</button>
      </div>

      <div class="sat4-mini" id="invStatus">—</div>
    </div>

    <div class="sat4-modal-foot">
      <button type="button" class="sat4-btn" data-close>Cerrar</button>
    </div>
  </div>
</div>

{{-- Modal: Iniciar descarga (Mes/Año) --}}
<div class="sat4-modal" id="sat4ModalYm" style="display:none;" role="dialog" aria-modal="true">
  <div class="sat4-modal-card">
    <div class="sat4-modal-head">
      <div class="sat4-modal-title">Iniciar descarga portal SAT</div>
      <button type="button" class="sat4-x" data-close>✕</button>
    </div>

    <div class="sat4-form">
      <div class="sat4-ym">
        <div>
          <div class="sat4-mini" style="margin:0 0 6px;">Año</div>
          <input class="sat4-in mono" id="sat4YmYear" type="number" min="2015" max="2100" step="1">
        </div>
        <div>
          <div class="sat4-mini" style="margin:0 0 6px;">Mes</div>
          <select class="sat4-in" id="sat4YmMonth">
            <option value="1">ENERO</option>
            <option value="2">FEBRERO</option>
            <option value="3">MARZO</option>
            <option value="4">ABRIL</option>
            <option value="5">MAYO</option>
            <option value="6">JUNIO</option>
            <option value="7">JULIO</option>
            <option value="8">AGOSTO</option>
            <option value="9">SEPTIEMBRE</option>
            <option value="10">OCTUBRE</option>
            <option value="11">NOVIEMBRE</option>
            <option value="12">DICIEMBRE</option>
          </select>
        </div>
      </div>

      <div class="sat4-help">
        Por defecto se muestra el mes y año en curso. Al continuar, se abrirá la descarga avanzada con el rango del mes seleccionado.
      </div>

      <div class="sat4-row" style="margin-top:12px;">
        <button class="sat4-btn" type="button" data-close>Cancelar</button>
        <button class="sat4-btn sat4-btn-primary" type="button" id="sat4YmContinue">Continuar</button>
      </div>

      <div class="sat4-mini" id="sat4YmStatus" style="margin-top:10px;">—</div>
    </div>
  </div>
</div>


@endsection

@push('scripts')
@php
  $JS_REL = 'assets/client/js/sat/sat-v4.js';
  $JS_ABS = public_path($JS_REL);
  $JS_V   = is_file($JS_ABS) ? (string) filemtime($JS_ABS) : null;
@endphp

<script>
(function(){
  'use strict';
  if (!window.P360_SAT || typeof window.P360_SAT !== 'object') window.P360_SAT = {};
  const CFG = @json($p360SatCfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  window.P360_SAT = Object.assign({}, window.P360_SAT, CFG);
})();
</script>

<script src="{{ asset($JS_REL) }}{{ $JS_V ? ('?v='.$JS_V) : '' }}" defer></script>
@endpush
