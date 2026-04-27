{{-- C:\wamp64\www\pactopia360_erp\resources\views\admin\clientes\index.blade.php --}}
@extends('layouts.admin')

@section('title','Clientes (accounts)')

@push('styles')
  {{-- Legacy / base existente --}}
  <link rel="stylesheet" href="{{ asset('assets/admin/css/admin-clientes.css') }}?v={{ @filemtime(public_path('assets/admin/css/admin-clientes.css')) }}">

  {{-- ✅ vNext modular (sin inline) --}}
  <link rel="stylesheet" href="{{ asset('assets/admin/css/admin-clientes.vnext.base.css') }}?v={{ @filemtime(public_path('assets/admin/css/admin-clientes.vnext.base.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/admin/css/admin-clientes.vnext.layout.css') }}?v={{ @filemtime(public_path('assets/admin/css/admin-clientes.vnext.layout.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/admin/css/admin-clientes.vnext.list.css') }}?v={{ @filemtime(public_path('assets/admin/css/admin-clientes.vnext.list.css')) }}">

  {{-- ✅ Scroll fix: elimina doble scroll (solo scroll de pantalla completa) --}}
  <link rel="stylesheet" href="{{ asset('assets/admin/css/admin-clientes.vnext.scrollfix.css') }}?v={{ @filemtime(public_path('assets/admin/css/admin-clientes.vnext.scrollfix.css')) }}">
   <link rel="stylesheet" href="{{ asset('assets/admin/css/admin-clientes.vnext.modal.css') }}?v={{ @filemtime(public_path('assets/admin/css/admin-clientes.vnext.modal.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/admin/css/admin-clientes.vnext.overlays.v2.css') }}?v={{ @filemtime(public_path('assets/admin/css/admin-clientes.vnext.overlays.v2.css')) }}">

  {{-- ✅ Extras: estilos extraídos de inline del Blade --}}
  <link rel="stylesheet" href="{{ asset('assets/admin/css/admin-clientes.vnext.page.extras.css') }}?v={{ @filemtime(public_path('assets/admin/css/admin-clientes.vnext.page.extras.css')) }}">
@endpush

@section('content')
@php
  use Illuminate\Support\Facades\Route;
  use Illuminate\Support\Carbon;
  use Illuminate\Support\Facades\Crypt;

  $q             = request('q');
  $plan          = request('plan');
  $blocked       = request('blocked');
  $billingStatus = request('billing_status');

  $s  = request('sort','created_at');
  $d  = strtolower(request('dir','desc'))==='asc'?'asc':'desc';
  $pp = (int) request('per_page', 25);

  $total = method_exists($rows,'total') ? $rows->total() : (is_countable($rows) ? count($rows) : null);

  $planNorm = function($raw) {
    $p = strtoupper(trim((string)$raw));
    if (in_array($p, ['PRO', 'PRO_MENSUAL', 'PRO_ANUAL'], true)) return 'pro';
    if ($p === 'FREE') return 'free';
    return strtolower($p);
  };

  $billingNorm = function($raw) {
    $bs = strtolower(trim((string)$raw));
    return match ($bs) {
      'active', 'activa'         => 'active',
      'trial', 'prueba'          => 'trial',
      'grace', 'gracia'          => 'grace',
      'overdue', 'vencida'       => 'overdue',
      'suspended', 'suspendida'  => 'suspended',
      'cancelled', 'cancelada'   => 'cancelled',
      'demo'                     => 'demo',
      default                    => $bs,
    };
  };

  // KPIs rápidos (sobre $rows ya filtrado/paginado en backend)
  $verMail = 0; $verPhone = 0; $cntPro = 0; $cntFree = 0; $cntBlocked = 0;
  $cntActive=0; $cntTrial=0; $cntOverdue=0; $cntSuspended=0; $cntCancelled=0;

  foreach ($rows as $x) {
    if(!empty($x->email_verified_at)) $verMail++;
    if(!empty($x->phone_verified_at)) $verPhone++;

    $p = $planNorm($x->plan ?? '');
    if($p === 'pro')  $cntPro++;
    if($p === 'free') $cntFree++;

    if((int)($x->is_blocked ?? 0) === 1) $cntBlocked++;

    $bs = $billingNorm($x->billing_status ?? '');
    if($bs === 'active') $cntActive++;
    if($bs === 'trial') $cntTrial++;
    if($bs === 'overdue') $cntOverdue++;
    if($bs === 'suspended') $cntSuspended++;
    if($bs === 'cancelled') $cntCancelled++;
  }

  $defaultPeriod = now()->addMonthNoOverflow()->format('Y-m');

  $try = function(string $name, array $params = []) {
    try { return Route::has($name) ? route($name, $params) : null; } catch(\Throwable $e) {}
    return null;
  };

  $is = fn($k,$v)=> (string)request($k, '')===(string)$v;

  // Recipients helpers
  $recipsToString = function($recipsArr, string $kind='statement') {
    if (!is_array($recipsArr)) return '';
    $list = $recipsArr[$kind] ?? [];
    if (!is_array($list)) return '';
    $emails = [];
    foreach ($list as $it) {
      $e = strtolower(trim((string)($it['email'] ?? '')));
      if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) $emails[] = $e;
    }
    return implode(", ", array_values(array_unique($emails)));
  };

  $recipsPrimary = function($recipsArr, string $kind='statement') {
    if (!is_array($recipsArr)) return '';
    $list = $recipsArr[$kind] ?? [];
    if (!is_array($list)) return '';
    foreach ($list as $it) {
      $flag = (int)($it['is_primary'] ?? ($it['primary'] ?? 0));
      if ($flag === 1) {
        $e = strtolower(trim((string)($it['email'] ?? '')));
        if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) return $e;
      }
    }
    return '';
  };

  $cycleLabel = function($raw){
    $raw = strtolower(trim((string)$raw));
    if ($raw === 'monthly' || $raw === 'mensual') return 'Mensual';
    if ($raw === 'yearly'  || $raw === 'anual'   || $raw === 'annual') return 'Anual';
    return $raw !== '' ? strtoupper($raw) : '—';
  };

  $dateLabel = function($raw){
    $raw = trim((string)$raw);
    if ($raw === '') return '—';
    try { return Carbon::parse($raw)->format('Y-m-d'); } catch(\Throwable $e) {}
    return $raw;
  };

  $dtLabel = function($raw){
    $raw = trim((string)$raw);
    if ($raw === '') return '—';
    try { return Carbon::parse($raw)->format('Y-m-d H:i'); } catch(\Throwable $e) {}
    return $raw;
  };

  $money = function($n){
    if ($n === null || $n === '' || !is_numeric($n)) return '—';
    return '$' . number_format((float)$n, 2);
  };

  $countEmails = function(string $csv){
    $csv = trim($csv);
    if ($csv === '') return 0;
    $arr = array_filter(array_map('trim', explode(',', $csv)));
    return count($arr);
  };

  $stmtIdx = $try('admin.billing.statements.index') ?: $try('admin.billing.statementsHub.index');

  // Billing statuses (para filtros y badges)
  $billingStatuses = $billingStatuses ?? [
    'active'    => 'Activa',
    'trial'     => 'Prueba',
    'grace'     => 'Gracia',
    'overdue'   => 'Falta de pago',
    'suspended' => 'Suspendida',
    'cancelled' => 'Cancelada',
    'demo'      => 'Demo/QA',
  ];

  $bsTone = function($bs){
    $bs = strtolower((string)$bs);
    if ($bs==='active') return 'ok';
    if ($bs==='trial' || $bs==='grace') return 'warn';
    if ($bs==='overdue') return 'bad';
    if ($bs==='suspended' || $bs==='cancelled') return 'bad';
    if ($bs==='demo') return 'neutral';
    return 'neutral';
  };

  // ✅ Logo (para correo de credenciales)
  $brandLogoUrl = asset('assets/brand/pactopia-logo.png');

  // ✅ Resolver URL de verificación por token si existe la route
  $tokenRoute = $try('cliente.verify.email.token', ['token' => '___TOKEN___']);
  $hasTokenRoute = is_string($tokenRoute) && str_contains($tokenRoute, '___TOKEN___');

  // ✅ Rutas generales (seguras)
  $syncLegacyUrl = $try('admin.clientes.syncToClientes') ?: route('admin.clientes.syncToClientes');

  // ✅ vNext: STORE route (POST) para alta (NO usar create, porque create es GET)
  $createStoreUrl = $try('admin.clientes.store')
                ?: $try('admin.accounts.store')
                ?: '';
  
  $facturotopiaUrlTemplate = $try('admin.clientes.facturotopia.save', ['key' => 'P360KEY']) ?: '';
@endphp

<div id="adminClientesPage"
     class="ac-page"
     data-default-period="{{ $defaultPeriod }}">

  <div class="ac-shell">

    {{-- =========================
    Header compacto · Clientes
   ========================= --}}
<div class="ac360-head">
  <div class="ac360-head-top">
    <div class="ac360-titlebox">
      <div class="ac360-titleline">
        <h1>Clientes</h1>
        <span class="ac360-count">{{ $total ?? '—' }}</span>
      </div>
      <div class="ac360-sub">Cuentas · admin.accounts</div>
    </div>

    <form method="GET" id="quickSearchForm" class="ac360-search" autocomplete="off">
      <span class="ac360-search-ico">⌕</span>

      <input
        name="q"
        value="{{ $q }}"
        placeholder="RFC, razón social, correo o teléfono..."
        aria-label="Buscar">

      <input type="hidden" name="plan" value="{{ $plan }}">
      <input type="hidden" name="blocked" value="{{ $blocked }}">
      <input type="hidden" name="billing_status" value="{{ $billingStatus }}">
      <input type="hidden" name="sort" value="{{ $s }}">
      <input type="hidden" name="dir" value="{{ $d }}">
      <input type="hidden" name="per_page" value="{{ $pp }}">

      <button class="ac360-icon-btn primary" type="submit" data-tip="Buscar" aria-label="Buscar">
        🔎
      </button>
    </form>
  </div>

  <div class="ac360-actionbar">
    <button class="ac360-square primary" type="button" data-open-modal="#modalCreate" data-tip="Crear cliente">
      +
    </button>

    <form method="POST" action="{{ $syncLegacyUrl }}" onsubmit="return confirm('¿Sincronizar accounts → clientes (legacy)?')">
      @csrf
      <button class="ac360-square" type="submit" data-tip="Sincronizar">↻</button>
    </form>

    <button class="ac360-square" id="btnExportCsv" type="button" data-tip="Exportar">⇩</button>

    @if($stmtIdx)
      <a class="ac360-square" href="{{ $stmtIdx }}" data-tip="Estados de cuenta">◴</a>
    @endif

    <span class="ac360-sep"></span>

    <a class="ac360-filter-pill {{ ($plan===''||$plan===null)?'active':'' }}"
       href="{{ request()->fullUrlWithQuery(['plan'=>'','page'=>null]) }}"
       data-tip="Todos los planes">
      Plan
      @if($plan)
        <b>1</b>
      @endif
    </a>

    <a class="ac360-filter-pill {{ ($billingStatus===''||$billingStatus===null)?'active':'' }}"
       href="{{ request()->fullUrlWithQuery(['billing_status'=>'','page'=>null]) }}"
       data-tip="Facturación">
      Fact.
      @if($billingStatus)
        <b>1</b>
      @endif
    </a>

    <a class="ac360-filter-pill {{ ($blocked===''||$blocked===null)?'active':'' }}"
       href="{{ request()->fullUrlWithQuery(['blocked'=>'','page'=>null]) }}"
       data-tip="Bloqueo">
      Bloq.
      @if($blocked !== null && $blocked !== '')
        <b>1</b>
      @endif
    </a>

    <button class="ac360-square" type="button" onclick="document.getElementById('filtersBox')?.toggleAttribute('open')" data-tip="Filtros">
      ⛃
    </button>

    <a class="ac360-square danger" href="{{ route('admin.clientes.index') }}" data-tip="Limpiar filtros">
      ⌫
    </a>
  </div>

  <div class="ac360-meta">
    <span data-tip="Corte">▣ {{ now()->format('d/m/Y H:i') }}</span>
    <span data-tip="Orden">↕ {{ $s }} · {{ $d }}</span>
    <span data-tip="Por página">▤ {{ $pp }}/pág</span>
    <a href="{{ route('admin.clientes.index') }}" data-tip="Restablecer">↻</a>
  </div>
</div>

{{-- =========================
    KPIs compactos
   ========================= --}}
<div class="ac360-kpis">
  <a href="{{ route('admin.clientes.index') }}"><strong>{{ $total ?? '—' }}</strong><span>Total</span></a>
  <a href="{{ request()->fullUrlWithQuery(['plan'=>'pro','page'=>null]) }}"><strong>{{ $cntPro }}</strong><span>PRO</span></a>
  <a href="{{ request()->fullUrlWithQuery(['billing_status'=>'active','page'=>null]) }}"><strong>{{ $cntActive }}</strong><span>Activas</span></a>
  <a href="{{ request()->fullUrlWithQuery(['blocked'=>'1','page'=>null]) }}"><strong>{{ $cntBlocked }}</strong><span>Bloq.</span></a>
  <details>
    <summary><strong>Más</strong><span>FREE {{ $cntFree }} · Prueba {{ $cntTrial }} · Vencido {{ $cntOverdue }}</span></summary>
    <div>
      <a href="{{ request()->fullUrlWithQuery(['plan'=>'free','page'=>null]) }}">FREE {{ $cntFree }}</a>
      <a href="{{ request()->fullUrlWithQuery(['billing_status'=>'trial','page'=>null]) }}">Prueba {{ $cntTrial }}</a>
      <a href="{{ request()->fullUrlWithQuery(['billing_status'=>'overdue','page'=>null]) }}">Vencido {{ $cntOverdue }}</a>
      <span>Mail {{ $verMail }}</span>
      <span>Tel {{ $verPhone }}</span>
    </div>
  </details>
</div>

{{-- =========================
    Filtros avanzados compactos
   ========================= --}}
<details class="ac360-filters" id="filtersBox" {{ ($plan||$blocked||$billingStatus||$s!=='created_at'||$d!=='desc'||$pp!==25||$q) ? 'open' : '' }}>
  <summary>
    <span>⛃ Filtros</span>
    <small>{{ $s }} · {{ $d }} · {{ $pp }}/pág</small>
  </summary>

  <form method="GET" id="filtersForm" class="ac360-filter-form">
    <input name="q" value="{{ $q }}" placeholder="Buscar...">

    <select name="plan">
      <option value="">Plan</option>
      <option value="free" {{ (string)$plan==='free'?'selected':'' }}>Free</option>
      <option value="pro"  {{ (string)$plan==='pro'?'selected':'' }}>Pro</option>
    </select>

    <select name="blocked">
      <option value="">Bloqueo</option>
      <option value="0" {{ request('blocked')==='0' ? 'selected':'' }}>Operando</option>
      <option value="1" {{ request('blocked')==='1' ? 'selected':'' }}>Bloqueado</option>
    </select>

    <select name="billing_status">
      <option value="">Facturación</option>
      @foreach($billingStatuses as $k=>$lbl)
        <option value="{{ $k }}" {{ (string)$billingStatus===(string)$k ? 'selected':'' }}>{{ $lbl }}</option>
      @endforeach
    </select>

    <select name="sort">
      <option value="created_at" {{ $s==='created_at'?'selected':'' }}>Creado</option>
      <option value="razon_social" {{ $s==='razon_social'?'selected':'' }}>Razón social</option>
      <option value="plan" {{ $s==='plan'?'selected':'' }}>Plan</option>
      <option value="billing_cycle" {{ $s==='billing_cycle'?'selected':'' }}>Ciclo</option>
      <option value="billing_status" {{ $s==='billing_status'?'selected':'' }}>Facturación</option>
      <option value="email_verified_at" {{ $s==='email_verified_at'?'selected':'' }}>Correo</option>
      <option value="phone_verified_at" {{ $s==='phone_verified_at'?'selected':'' }}>Teléfono</option>
      <option value="is_blocked" {{ $s==='is_blocked'?'selected':'' }}>Bloqueo</option>
    </select>

    <select name="dir">
      <option value="desc" {{ $d==='desc'?'selected':'' }}>Desc</option>
      <option value="asc"  {{ $d==='asc'?'selected':'' }}>Asc</option>
    </select>

    <select name="per_page">
      @foreach([10,25,50,100] as $opt)
        <option value="{{ $opt }}" {{ $pp===$opt?'selected':'' }}>{{ $opt }}</option>
      @endforeach
    </select>

    <button class="ac360-mini primary" type="submit" data-tip="Aplicar">✓</button>
    <a class="ac360-mini" href="{{ route('admin.clientes.index') }}" data-tip="Reiniciar">↻</a>
  </form>
</details>

    {{-- Alertas --}}
    <div class="ac-alerts" role="region" aria-label="Mensajes del sistema">
      @if(session('ok'))
        <div class="ac-alert ok"><strong>OK:</strong> {!! nl2br(e(session('ok'))) !!}</div>
      @endif
      @if(session('error'))
        <div class="ac-alert bad"><strong>Error:</strong> {{ session('error') }}</div>
      @endif
      @if($errors->any())
        <div class="ac-alert warn"><strong>Validación:</strong> {{ $errors->first() }}</div>
      @endif

      @php $tl = session('tmp_last'); @endphp
      @if(is_array($tl) && !empty($tl['pass']))
        <div class="ac-alert info">
          <strong>Temporal generada:</strong>
          RFC <code class="ac-mono">{{ $tl['key'] }}</code> ·
          Usuario <code class="ac-mono">{{ $tl['user'] }}</code> ·
          Pass <code class="ac-mono">{{ $tl['pass'] }}</code>
          <span style="opacity:.75"> ({{ $tl['ts'] ?? now()->toDateTimeString() }})</span>
        </div>
      @endif
    </div>

    {{-- =========================
        LISTA HYBRID (tabla desktop / card mobile)
       ========================= --}}
    <div class="ac-list" role="region" aria-label="Listado de clientes">

      <div class="ac-list-head" aria-hidden="true">
        <div>Cliente</div>
        <div>Facturación</div>
        <div class="actions">Acciones</div>
      </div>

      @forelse($rows as $r)
        @php
          $RFC_FULL = strtoupper(trim((string) (data_get($r,'rfc') ?: data_get($r,'id'))));
          $created  = $dtLabel($r->created_at ?? '');
          $idStr    = (string)($r->id ?? '');

          $info     = $extras[$r->id] ?? null;
          $cred     = $creds[$r->id] ?? null;

          $planVal  = $planNorm($r->plan ?? '');
          $bcRaw    = (string)(data_get($r, 'billing_cycle') ?: (is_array($info) ? ($info['billing_cycle'] ?? '') : ''));
          $nextRaw  = (string)(data_get($r, 'next_invoice_date') ?: (is_array($info) ? ($info['next_invoice_date'] ?? '') : ''));
          $bsRaw    = (string)(data_get($r, 'billing_status') ?: (is_array($info) ? ($info['estado_cuenta'] ?? '') : ''));

          $bcLabel   = $cycleLabel($bcRaw);
          $nextLabel = $dateLabel($nextRaw);

          $customAmount = null;
          foreach (['custom_amount_mxn','override_amount_mxn','billing_amount_mxn','amount_mxn','precio_mxn','monto_mxn','license_amount_mxn'] as $p) {
            if (isset($r->{$p}) && $r->{$p} !== null && $r->{$p} !== '') { $customAmount = $r->{$p}; break; }
          }
          $hasCustom = ($customAmount !== null && $customAmount !== '' && is_numeric($customAmount));
          $effective = is_array($info) ? ($info['license_amount_mxn_effective'] ?? null) : null;

          $isBlocked = ((int)($r->is_blocked ?? 0) === 1);
          $mailOk = !empty($r->email_verified_at);
          $phoneOk = !empty($r->phone_verified_at);

                    $routeKey = (string)($r->id ?? '');

          $seedUrl   = $try('admin.clientes.seedStatement', ['key' => $routeKey])
                    ?: $try('admin.clientes.seedStatement', ['rfc' => $routeKey])
                    ?: $try('admin.clientes.seedStatement', ['accountId' => $routeKey]);

          $recipUrl  = $try('admin.clientes.recipientsUpsert', ['key' => $routeKey])
                    ?: $try('admin.clientes.recipientsUpsert', ['rfc' => $routeKey])
                    ?: $try('admin.clientes.recipients.upsert', ['key' => $routeKey])
                    ?: $try('admin.clientes.recipients.upsert', ['rfc' => $routeKey]);

          $emailCredsUrl = $try('admin.clientes.emailCreds', ['key' => $routeKey])
                        ?: $try('admin.clientes.emailCreds', ['rfc' => $routeKey])
                        ?: $try('admin.clientes.emailCredentials', ['key' => $routeKey])
                        ?: $try('admin.clientes.emailCredentials', ['rfc' => $routeKey]);

          $resendVerifyUrl = $try('admin.clientes.resendEmailVerification', ['key' => $routeKey])
                          ?: $try('admin.clientes.resendEmailVerification', ['rfc' => $routeKey])
                          ?: $try('admin.clientes.resendEmail', ['id' => $routeKey])
                          ?: $try('admin.clientes.resendEmail', ['rfc' => $routeKey])
                          ?: '';

          $sendOtpUrl = $try('admin.clientes.sendPhoneOtp', ['key' => $routeKey])
                     ?: $try('admin.clientes.sendPhoneOtp', ['rfc' => $routeKey])
                     ?: $try('admin.clientes.sendOtp', ['id' => $routeKey])
                     ?: $try('admin.clientes.sendOtp', ['rfc' => $routeKey])
                     ?: '';

          $blockUrl      = $try('admin.clientes.block',      ['key' => $routeKey])
                        ?: $try('admin.clientes.block',      ['rfc' => $routeKey])
                        ?: '';

          $unblockUrl    = $try('admin.clientes.unblock',    ['key' => $routeKey])
                        ?: $try('admin.clientes.unblock',    ['rfc' => $routeKey])
                        ?: '';

          $deactivateUrl = $try('admin.clientes.deactivate', ['key' => $routeKey])
                        ?: $try('admin.clientes.deactivate', ['rfc' => $routeKey])
                        ?: '';

          $reactivateUrl = $try('admin.clientes.reactivate', ['key' => $routeKey])
                        ?: $try('admin.clientes.reactivate', ['rfc' => $routeKey])
                        ?: '';

          $deleteUrl     = $try('admin.clientes.delete',     ['key' => $routeKey])
                        ?: $try('admin.clientes.delete',     ['rfc' => $routeKey])
                        ?: $try('admin.clientes.destroy',    ['key' => $routeKey])
                        ?: $try('admin.clientes.destroy',    ['rfc' => $routeKey])
                        ?: '';

          // ✅ Billing Accounts (iframe modal) + Statements HUB (iframe modal)
          $billingAdminUrl = '';
          if (Route::has('admin.billing.accounts.show')) {
            try {
              // show de Billing Accounts (admin) por id del account (SOT)
              $billingAdminUrl = route('admin.billing.accounts.show', ['id' => $r->id, 'modal' => 1]);
            } catch (\Throwable $e) { $billingAdminUrl = ''; }
          }

          $billingStateHubUrl = '';
          if (Route::has('admin.billing.statements_hub.index')) {
            try {
              $billingStateHubUrl = route('admin.billing.statements_hub.index', [
                'period' => $defaultPeriod,
                'q'      => $r->id,
                'modal'  => 1,
              ]);
            } catch (\Throwable $e) { $billingStateHubUrl = ''; }
          }

          $stmtShow  = $try('admin.billing.statements.show',  ['accountId'=>$r->id, 'period'=>$defaultPeriod]) ?: $try('admin.billing.statement.show',  ['rfc'=>$r->id, 'period'=>$defaultPeriod]);
          $stmtEmail = $try('admin.billing.statements.email', ['accountId'=>$r->id, 'period'=>$defaultPeriod]) ?: $try('admin.billing.statement.email', ['rfc'=>$r->id, 'period'=>$defaultPeriod]);

          $rRecips = $recipients[$r->id] ?? [];
          $recipsStatement = $recipsToString($rRecips, 'statement');
          $recipsInvoice   = $recipsToString($rRecips, 'invoice');
          $recipsGeneral   = $recipsToString($rRecips, 'general');

          $primaryStatement = $recipsPrimary($rRecips, 'statement');
          $primaryInvoice   = $recipsPrimary($rRecips, 'invoice');
          $primaryGeneral   = $recipsPrimary($rRecips, 'general');

          $stmtCount = $countEmails($recipsStatement);
          $invCount  = $countEmails($recipsInvoice);
          $genCount  = $countEmails($recipsGeneral);

          $stmtMain = $stmtCount ? ($primaryStatement ?: trim(explode(',', $recipsStatement)[0] ?? '')) : 'Sin correos';

          // ✅ extras
          $estadoCuenta = is_array($info) ? (string)($info['estado_cuenta'] ?? $info['account_status'] ?? $bsRaw ?? '') : $bsRaw;
          $modoCobro    = is_array($info) ? (string)($info['modo_cobro'] ?? $info['billing_mode'] ?? '') : '';
          $stripeCust   = is_array($info) ? (string)($info['stripe_customer_id'] ?? '') : '';
          $stripeSub    = is_array($info) ? (string)($info['stripe_subscription_id'] ?? '') : '';
          $periodStart  = is_array($info) ? (string)($info['current_period_start'] ?? '') : '';
          $periodEnd    = is_array($info) ? (string)($info['current_period_end'] ?? '') : '';

          // ✅ email verify token/exp
          $emailToken = is_array($info) ? (string)($info['email_token'] ?? '') : '';
          $emailTokenExp = is_array($info) ? (string)($info['email_expires_at'] ?? '') : '';
          $tokenUrl = '';
          if ($emailToken !== '' && $hasTokenRoute) {
            $tokenUrl = str_replace('___TOKEN___', $emailToken, (string)$tokenRoute);
          }

          // ✅ OTP
          $otpCode    = is_array($info) ? (string)($info['otp_code'] ?? '') : '';
          $otpChannel = is_array($info) ? (string)($info['otp_channel'] ?? '') : '';
          $otpExp     = is_array($info) ? (string)($info['otp_expires_at'] ?? '') : '';

          // ✅ credenciales
          $ownerEmail = is_array($cred) ? (string)($cred['owner_email'] ?? '') : '';
          $tempPass   = is_array($cred) ? (string)($cred['temp_pass'] ?? '') : '';

          $fallbackAccessUrl = rtrim((string)config('app.url'), '/') . '/cliente';
          $accessUrl = $fallbackAccessUrl;

          $amtShow = '—';
          $amtMeta = '—';
          if (is_numeric($effective) && (float)$effective >= 0) { $amtShow = $money($effective); $amtMeta='licencia efectiva'; }
          elseif ($hasCustom) { $amtShow = $money($customAmount); $amtMeta='precio personalizado'; }

          $bsLbl = (string)($billingStatuses[strtolower((string)$bsRaw)] ?? ($bsRaw ?: '—'));
          $bsCls = $bsTone($bsRaw);

          $nextPeriodEndLbl = $periodEnd ? $dateLabel($periodEnd) : '—';

                    $metaRaw = (string)($r->meta ?? '');
          $metaArr = [];

          if ($metaRaw !== '') {
            $decodedMeta = json_decode($metaRaw, true);
            $metaArr = is_array($decodedMeta) ? $decodedMeta : [];
          }

          $ft = data_get($metaArr, 'facturotopia', []);

          $ftStatus = (string) data_get($ft, 'status', 'pendiente');
          $ftEnv = (string) data_get($ft, 'env', 'sandbox');
          $ftCustomerId = (string) data_get($ft, 'customer_id', '');

          $ftUser = (string) data_get($ft, 'auth.user', '');

          $ftPasswordEncrypted = (string) data_get($ft, 'auth.password_encrypted', '');
          $ftPasswordPlain = '';

          if ($ftPasswordEncrypted !== '') {
              try {
                  $ftPasswordPlain = Crypt::decryptString($ftPasswordEncrypted);
              } catch (\Throwable $e) {
                  $ftPasswordPlain = '';
              }
          }

          $ftPasswordSaved = $ftPasswordPlain !== '' ? '1' : '0';

          $ftSandboxBaseUrl = (string) data_get($ft, 'sandbox.base_url', '');
          $ftSandboxApiKey = (string) data_get($ft, 'sandbox.api_key', '');

          $ftProductionBaseUrl = (string) data_get($ft, 'production.base_url', '');
          $ftProductionApiKey = (string) data_get($ft, 'production.api_key', '');

          $ftTimbresAsignados = (int) data_get($ft, 'timbres.asignados', 0);
          $ftTimbresConsumidos = (int) data_get($ft, 'timbres.consumidos', 0);
          $ftHitsAsignados = (int) data_get($ft, 'hits.asignados', 0);
          $ftHitsConsumidos = (int) data_get($ft, 'hits.consumidos', 0);

          $ftNotas = (string) data_get($ft, 'notas', '');
          $ftUpdatedAt = (string) data_get($ft, 'updated_at', '');

          $ftSaveUrl = $facturotopiaUrlTemplate !== ''
          ? str_replace('P360KEY', $routeKey, $facturotopiaUrlTemplate)
          : '';

          // payload para drawer/modals
          $clientPayload = [
            "id" => $idStr,
            "rfc" => $RFC_FULL,
            "razon_social" => (string)($r->razon_social ?? ''),
            "created" => $created,
            "email" => (string)($r->email ?? ''),
            "phone" => (string)($r->phone ?? $r->telefono ?? ''),

            "key" => $idStr, // o RFC si prefieres usar RFC como key de rutas

            "plan" => (string)($r->plan ?? ''),
            "billing_cycle" => (string)($bcRaw ?? ''),
            "billing_cycle_label" => $bcLabel,
            "billing_status" => (string)($bsRaw ?? ''),
            "billing_status_label" => (string)($billingStatuses[strtolower((string)$bsRaw)] ?? ($bsRaw ?: '—')),
            "next_invoice_date" => (string)($nextRaw ?? ''),
            "next_invoice_label" => $nextLabel,

            "custom_amount_mxn" => $hasCustom ? (string)$customAmount : '',
            "effective_amount_mxn" => (is_numeric($effective) && (float)$effective >= 0) ? (string)$effective : '',

            "blocked" => $isBlocked ? 1 : 0,
            "mail_ok" => $mailOk ? 1 : 0,
            "phone_ok" => $phoneOk ? 1 : 0,

            "estado_cuenta" => $estadoCuenta,
            "modo_cobro" => $modoCobro,
            "stripe_customer_id" => $stripeCust,
            "stripe_subscription_id" => $stripeSub,
            "current_period_start" => $periodStart,
            "current_period_end" => $periodEnd,

            "default_period" => $defaultPeriod,

            "recip_url" => $recipUrl ?: '',
            "seed_url" => $seedUrl ?: '',
            "stmt_show_url" => $stmtShow ?: '',
            "stmt_email_url" => $stmtEmail ?: '',

            "billing_admin_url" => $billingAdminUrl ?: '',
            "billing_statehub_url" => $billingStateHubUrl ?: '',

            "block_url" => $blockUrl ?: '',
            "unblock_url" => $unblockUrl ?: '',
            "deactivate_url" => $deactivateUrl ?: '',
            "reactivate_url" => $reactivateUrl ?: '',
            "delete_url" => $deleteUrl ?: '',

            "recips_statement" => $recipsStatement,
            "recips_invoice" => $recipsInvoice,
            "recips_general" => $recipsGeneral,

            "primary_statement" => $primaryStatement,
            "primary_invoice" => $primaryInvoice,
            "primary_general" => $primaryGeneral,

            "email_token" => $emailToken,
            "token_url" => $tokenUrl,
            "token_expires" => $emailTokenExp,

            "otp_code" => $otpCode,
            "otp_channel" => $otpChannel,
            "otp_expires" => $otpExp,

            "owner_email" => $ownerEmail,
            "temp_pass" => $tempPass,

            "email_creds_url" => $emailCredsUrl ?: '',

            "facturotopia_save_url" => $ftSaveUrl,
            "facturotopia_status" => $ftStatus,
            "facturotopia_env" => $ftEnv,
            "facturotopia_customer_id" => $ftCustomerId,
            "facturotopia_base_url_sandbox" => $ftSandboxBaseUrl,
            "facturotopia_api_key_sandbox" => $ftSandboxApiKey,
            "facturotopia_base_url_production" => $ftProductionBaseUrl,
            "facturotopia_api_key_production" => $ftProductionApiKey,
            "timbres_asignados" => $ftTimbresAsignados,
            "timbres_consumidos" => $ftTimbresConsumidos,
            "hits_asignados" => $ftHitsAsignados,
            "hits_consumidos" => $ftHitsConsumidos,
            "notas_facturotopia" => $ftNotas,
            "facturotopia_updated_at" => $ftUpdatedAt,

            "facturotopia_user" => $ftUser,
            "facturotopia_password_saved" => $ftPasswordSaved,
            "facturotopia_password" => $ftPasswordPlain,

            "access_url" => $accessUrl,
          ];
          $clientJson = e(json_encode($clientPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        @endphp

                <div class="ac-row" data-client="{{ $clientJson }}">

          {{-- =========================
              Col 1: Cliente
             ========================= --}}
          <div class="cell client" data-label="Cliente">
            <div class="ac-titleline">
              <div class="ac-rfc">{{ $RFC_FULL }}</div>
              <span class="badge {{ $planVal==='pro' ? 'primary' : 'warn' }}">
                <span class="dot"></span>{{ $r->plan ? strtoupper((string)$r->plan) : '—' }}
              </span>
            </div>

            <div class="ac-rs">{{ $r->razon_social ?: '—' }}</div>

            <div class="ac-subline">
              <span>ID: <strong class="ac-mono">{{ $idStr ?: '—' }}</strong></span>
              <span>Creado: <strong>{{ $created }}</strong></span>
              <span>Edo: <strong>{{ $estadoCuenta ?: '—' }}</strong></span>
              @if($modoCobro)
                <span>Modo: <strong>{{ strtoupper($modoCobro) }}</strong></span>
              @endif
            </div>
          </div>

          {{-- =========================
              Col 2: Facturación (estado + ciclo + monto + contacto)
             ========================= --}}
          <div class="cell billing" data-label="Facturación">

            {{-- Badges principales (máximo 3 para no saturar) --}}
            <div class="ac-badges">
              <span class="badge {{ $isBlocked ? 'bad':'ok' }}">
                <span class="dot"></span>{{ $isBlocked ? 'Bloqueado' : 'Operando' }}
              </span>

              <span class="badge {{ $bsCls }}">
                <span class="dot"></span>{{ $bsLbl }}
              </span>

              <span class="badge {{ ($mailOk && $phoneOk) ? 'ok':'warn' }}">
                <span class="dot"></span>
                {{ $mailOk ? 'Mail✔' : 'Mail⏳' }} · {{ $phoneOk ? 'Tel✔' : 'Tel⏳' }}
              </span>
            </div>

            {{-- Línea de billing (ciclo / próxima / monto) --}}
            <div class="ac-billline">
              <div class="it">
                <div class="k">Ciclo</div>
                <div class="v mono">{{ $bcLabel }}</div>
              </div>

              <div class="it">
                <div class="k">Próx. factura</div>
                <div class="v mono">{{ $nextLabel }}</div>
              </div>

              <div class="it">
                <div class="k">Monto</div>
                <div class="v"><strong>{{ $amtShow }}</strong> <span class="mut">({{ $amtMeta }})</span></div>
              </div>
            </div>

            {{-- Contacto compacto + destinatario principal --}}
            <div class="ac-subline mt8">
              <span class="ac-ellipsis" title="{{ $r->email ?: '' }}">
                <strong>Email:</strong> <span class="mono">{{ $r->email ?: '—' }}</span>
              </span>

              <span class="ac-ellipsis" title="{{ ($r->phone ?? $r->telefono) ?: '' }}">
                <strong>Tel:</strong> <span class="mono">{{ ($r->phone ?? $r->telefono) ?: '—' }}</span>
              </span>

              <span class="ac-ellipsis" title="{{ $stmtMain ?: '' }}">
                <strong>Edo. cuenta:</strong> <span class="mono">{{ $stmtMain ?: '—' }}</span>
              </span>
            </div>

            {{-- Stripe IDs (solo si existen; una sola línea para no ensuciar) --}}
            @if(!empty($stripeCust) || !empty($stripeSub))
              <div class="ac-subline mt6">
                @if(!empty($stripeCust))
                  <span class="ac-ellipsis" title="{{ $stripeCust }}"><strong>Stripe C:</strong> <span class="ac-mono">{{ $stripeCust }}</span></span>
                @endif
                @if(!empty($stripeSub))
                  <span class="ac-ellipsis" title="{{ $stripeSub }}"><strong>Sub:</strong> <span class="ac-mono">{{ $stripeSub }}</span></span>
                @endif
              </div>
            @endif

          </div>

          {{-- =========================
                Col 3: Acciones
              ========================= --}}
            <div class="cell actions ac-actions-compact" data-label="Acciones">
              <button class="ac-icon-btn"
                      type="button"
                      data-open-drawer
                      data-tip="Ver"
                      title="Ver">
                <span>👁</span>
              </button>

              <button class="ac-icon-btn primary"
                    type="button"
                    data-open-facturotopia
                    data-facturotopia-url="{{ $ftSaveUrl }}"
                    data-ft-status="{{ e($ftStatus) }}"
                    data-ft-env="{{ e($ftEnv) }}"
                    data-ft-customer="{{ e($ftCustomerId) }}"
                    data-ft-user="{{ e($ftUser) }}"
                    data-ft-password-saved="{{ e($ftPasswordSaved) }}"
                    data-ft-password="{{ e($ftPasswordPlain) }}"
                    data-ft-sandbox-url="{{ e($ftSandboxBaseUrl) }}"
                    data-ft-sandbox-key="{{ e($ftSandboxApiKey) }}"
                    data-ft-prod-url="{{ e($ftProductionBaseUrl) }}"
                    data-ft-prod-key="{{ e($ftProductionApiKey) }}"
                    data-ft-timbres-asignados="{{ e((string)$ftTimbresAsignados) }}"
                    data-ft-timbres-consumidos="{{ e((string)$ftTimbresConsumidos) }}"
                    data-ft-hits-asignados="{{ e((string)$ftHitsAsignados) }}"
                    data-ft-hits-consumidos="{{ e((string)$ftHitsConsumidos) }}"
                    data-ft-notas="{{ e($ftNotas) }}"
                    data-tip="PAC"
                    title="Facturotopia / Timbres">
              <span>⚡</span>
            </button>

              @if($resendVerifyUrl)
                <form method="POST" action="{{ $resendVerifyUrl }}" class="inline ac-actions-inline">
                  @csrf
                  <button class="ac-icon-btn"
                          type="submit"
                          data-tip="Reenviar"
                          title="Reenviar verificación">
                    <span>✈</span>
                  </button>
                </form>
              @endif

              @if($sendOtpUrl)
                <form method="POST" action="{{ $sendOtpUrl }}" class="inline ac-actions-inline">
                  @csrf
                  <input type="hidden" name="channel" value="sms">
                  <button class="ac-icon-btn"
                          type="submit"
                          data-tip="OTP"
                          title="Enviar OTP">
                    <span>↻</span>
                  </button>
                </form>
              @endif

              <div class="ac-menu" data-menu>
                <button class="ac-icon-btn"
                        type="button"
                        data-menu-toggle
                        data-tip="Más"
                        aria-label="Más acciones">
                  <span>⋯</span>
                </button>

                <div class="ac-menu-panel" role="menu">
                  <button type="button" class="ac-btn" data-drawer-action="edit">Editar</button>
                  <button type="button" class="ac-btn" data-drawer-action="recipients">Destinatarios</button>
                  <button type="button" class="ac-btn" data-drawer-action="creds">Credenciales</button>
                  <button type="button" class="ac-btn" data-drawer-action="billing">Billing</button>
                  <button type="button"
                        class="ac-btn"
                        data-open-facturotopia
                        data-facturotopia-url="{{ $ftSaveUrl }}"
                        data-ft-status="{{ e($ftStatus) }}"
                        data-ft-env="{{ e($ftEnv) }}"
                        data-ft-customer="{{ e($ftCustomerId) }}"
                        data-ft-user="{{ e($ftUser) }}"
                        data-ft-password-saved="{{ e($ftPasswordSaved) }}"
                        data-ft-password="{{ e($ftPasswordPlain) }}"
                        data-ft-sandbox-url="{{ e($ftSandboxBaseUrl) }}"
                        data-ft-sandbox-key="{{ e($ftSandboxApiKey) }}"
                        data-ft-prod-url="{{ e($ftProductionBaseUrl) }}"
                        data-ft-prod-key="{{ e($ftProductionApiKey) }}"
                        data-ft-timbres-asignados="{{ e((string)$ftTimbresAsignados) }}"
                        data-ft-timbres-consumidos="{{ e((string)$ftTimbresConsumidos) }}"
                        data-ft-hits-asignados="{{ e((string)$ftHitsAsignados) }}"
                        data-ft-hits-consumidos="{{ e((string)$ftHitsConsumidos) }}"
                        data-ft-notas="{{ e($ftNotas) }}">
                  Facturotopia / Timbres
                </button>

                  <div class="ac-menu-sep"></div>

                  <button type="button" role="menuitem" data-action="block" {{ $blockUrl ? '' : 'disabled' }}>Bloquear</button>
                  <button type="button" role="menuitem" data-action="unblock" {{ $unblockUrl ? '' : 'disabled' }}>Desbloquear</button>
                  <button type="button" role="menuitem" data-action="deactivate" {{ $deactivateUrl ? '' : 'disabled' }}>Dar de baja</button>
                  <button type="button" role="menuitem" data-action="reactivate" {{ $reactivateUrl ? '' : 'disabled' }}>Reactivar</button>
                  <button type="button" role="menuitem" class="danger" data-action="delete" {{ $deleteUrl ? '' : 'disabled' }}>Eliminar…</button>
                </div>
              </div>
            </div>
          </div>
      @empty
        <div style="padding:16px">Sin resultados. Ajusta filtros o limpia búsqueda.</div>
      @endforelse
    </div>

    {{-- Paginación --}}
    <div class="ac-pager" aria-label="Paginación">
       <div class="info">
        @php
          $from = method_exists($rows,'firstItem') ? $rows->firstItem() : (count($rows)?1:0);
          $to   = method_exists($rows,'lastItem')  ? $rows->lastItem()  : ($total ?? null);
        @endphp
        Mostrando {{ $from }}–{{ $to }} de {{ $total ?? '—' }}
      </div>
      <div class="links ac-pagination-wrap">
        {{ $rows->onEachSide(1)->links() }}
      </div>
    </div>

  </div>

  {{-- =======================================================
     Drawer y Modales (completos)
     ======================================================= --}}

  {{-- =========================
      Drawer Cliente (Admin)
     ========================= --}}
  <div class="ac-drawer" id="clientDrawer" aria-hidden="true">
    <div class="ac-drawer-backdrop" data-close-drawer></div>

    <div class="ac-drawer-panel" role="dialog" aria-modal="true" aria-label="Detalle de cliente">
      <div class="ac-drawer-head">
        <div>
          <div class="rfc" id="dr_rfc">—</div>
          <div class="rs" id="dr_rs">—</div>
          <div class="meta" id="dr_meta">—</div>
        </div>
        <button class="x" type="button" data-close-drawer aria-label="Cerrar">✕</button>
      </div>

      <div class="ac-drawer-body">
        <div class="ac-drawer-kpis">
          <div class="kpi"><div class="v" id="dr_plan">—</div><div class="k">Plan</div></div>
          <div class="kpi"><div class="v" id="dr_cycle">—</div><div class="k">Ciclo</div></div>
          <div class="kpi"><div class="v" id="dr_next">—</div><div class="k">Próx. factura</div></div>
          <div class="kpi"><div class="v" id="dr_amount">—</div><div class="k">Monto</div></div>
        </div>

        <div class="ac-drawer-badges">
          <span class="badge neutral" id="dr_badge_block"><span class="dot"></span>—</span>
          <span class="badge neutral" id="dr_badge_mail"><span class="dot"></span>—</span>
          <span class="badge neutral" id="dr_badge_phone"><span class="dot"></span>—</span>
        </div>

        <div class="ac-drawer-contact">
          <div class="tt">Contacto</div>
          <div class="grid">
            <div class="item">
              <div class="label">Correo</div>
              <div class="value"><code class="ac-ellipsis" id="dr_email">—</code></div>
            </div>
            <div class="item">
              <div class="label">Teléfono</div>
              <div class="value"><code class="ac-ellipsis" id="dr_phone">—</code></div>
            </div>
          </div>
        </div>

        <div class="ac-drawer-block">
          <div class="tt">Estado de cuenta (destinatarios)</div>
          <div class="mono" id="dr_stmt_main">—</div>
          <div class="mut" id="dr_stmt_list">—</div>
        </div>

        <div class="ac-drawer-actions">
          <button class="ac-btn small" type="button" id="btnOpenEdit">Editar</button>
          <button class="ac-btn small" type="button" id="btnOpenRecipients">Destinatarios</button>
          <button class="ac-btn small" type="button" id="btnOpenCreds">Credenciales</button>
          <button class="ac-btn small primary" type="button" id="btnOpenBilling">Billing</button>
        </div>

        <div class="ac-divider"></div>

        {{-- ✅ Core actions (si existen routes; si no, JS mostrará nota) --}}
        <div class="ac-drawer-foot">
          <div class="ac-divider" style="margin:12px 0"></div>

          <details class="ac-adv">
            <summary class="ac-adv__summary">
              <strong>Acciones avanzadas</strong>
              <span class="mut">Bloquear / Baja / Eliminar</span>
            </summary>

            <div class="ac-drawer-actions" style="display:flex;flex-wrap:wrap;gap:8px;margin-top:10px">
              <form method="POST" id="drFormBlock" action="#" onsubmit="return confirm('¿Bloquear cuenta? (redirigirá a Stripe)')">
                @csrf
                <button class="ac-btn small" type="submit">Bloquear</button>
              </form>

              <form method="POST" id="drFormUnblock" action="#" onsubmit="return confirm('¿Desbloquear cuenta?')">
                @csrf
                <button class="ac-btn small" type="submit">Desbloquear</button>
              </form>

              <form method="POST" id="drFormDeactivate" action="#" onsubmit="return confirm('¿Dar de baja? (cancelled/suspended)')">
                @csrf
                <button class="ac-btn small" type="submit">Dar de baja</button>
              </form>

              <form method="POST" id="drFormReactivate" action="#" onsubmit="return confirm('¿Reactivar cuenta?')">
                @csrf
                <button class="ac-btn small" type="submit">Reactivar</button>
              </form>

              <form method="POST" id="drFormDelete" action="#" onsubmit="return confirm('¿Eliminar? Recomendado: soft-delete. ¿Continuar?')">
                @csrf
                <button class="ac-btn small" type="submit">Eliminar</button>
              </form>
            </div>
          </details>

          <div class="ac-note" id="drCoreActionsMissing" hidden style="margin-top:10px">
          Algunas acciones no están disponibles porque faltan rutas (block/unblock/deactivate/reactivate/delete).
          </div>

          <form method="POST" id="drFormImpersonate" action="#" onsubmit="return confirm('Vas a iniciar sesión como el cliente. ¿Continuar?')">
            @csrf
            <button class="ac-btn" type="submit">Entrar como cliente</button>
          </form>

          <form method="POST" id="drFormResetPass" action="#" onsubmit="return confirm('¿Generar contraseña temporal para el OWNER?')">
            @csrf
            <button class="ac-btn" type="submit">Resetear contraseña</button>
          </form>

          {{-- ✅ Este submit abre el modal de credenciales y permite enviar correo --}}
          <form method="POST" id="drFormEmailCreds" action="#" onsubmit="return false;">
            @csrf
            <button class="ac-btn primary" type="submit">Enviar credenciales</button>
          </form>
        </div>

      </div>
    </div>
  </div>

  {{-- =====================================
      MODAL: Crear (nuevo, vNext)
     ===================================== --}}
  <div class="ac-modal" id="modalCreate" aria-hidden="true">
    <div class="ac-modal-backdrop" data-close-modal></div>

    <div class="ac-modal-card" role="dialog" aria-modal="true" aria-label="Crear cliente">
      <div class="ac-modal-head">
        <div>
          <div class="ttl">Crear cliente</div>
          <div class="sub">Alta rápida (SOT: admin.accounts)</div>
        </div>
        <button class="x" type="button" data-close-modal aria-label="Cerrar">✕</button>
      </div>

      <form method="POST" id="mCreate_form" action="{{ $createStoreUrl ?: '#' }}" class="ac-form">
        @csrf
        <div class="ac-grid ac-grid-12">
          <div class="ac-field ac-field-wide ac-col-8">
            <label>Razón social</label>
            <input class="ac-input" name="razon_social" placeholder="Razón social">
          </div>
          <div class="ac-field ac-col-4">
            <label>RFC</label>
            <input class="ac-input" name="rfc" placeholder="AAA010101AAA">
          </div>

          <div class="ac-field ac-field-wide ac-col-6">
            <label>Email</label>
            <input class="ac-input" name="email" placeholder="correo@dominio.com">
          </div>
          <div class="ac-field ac-col-6">
            <label>Teléfono</label>
            <input class="ac-input" name="phone" placeholder="+52...">
          </div>

          <div class="ac-field ac-col-4">
            <label>Plan</label>
            <select class="ac-select" name="plan">
              <option value="PRO">PRO</option>
              <option value="PRO_MENSUAL">PRO_MENSUAL</option>
              <option value="PRO_ANUAL">PRO_ANUAL</option>
              <option value="FREE">FREE</option>
            </select>
          </div>

          <div class="ac-field ac-col-4">
            <label>Ciclo</label>
            <select class="ac-select" name="billing_cycle">
              <option value="monthly">Mensual</option>
              <option value="yearly">Anual</option>
            </select>
          </div>

          <div class="ac-field ac-col-4">
            <label>Monto (MXN)</label>
            <input class="ac-input" name="custom_amount_mxn" inputmode="decimal" placeholder="0.00">
          </div>

          <div class="ac-field ac-field-wide ac-col-12">
            @if(!$createStoreUrl)
              <div class="ac-note">
                Este modal es UI-ready. Para hacerlo funcional, crea la route de alta (POST) (ej.
                <code class="ac-mono">admin.clientes.store</code> o <code class="ac-mono">admin.accounts.store</code>)
                y luego asigna <code class="ac-mono">$createStoreUrl</code>.
              </div>
            @endif
          </div>
        </div>

        <div class="ac-form-actions">
          <button class="ac-btn" type="button" data-close-modal>Cancelar</button>
          <button class="ac-btn primary" type="submit"
                  {{ !$createStoreUrl ? 'disabled' : '' }}
                  onclick="{{ $createStoreUrl ? "return confirm('¿Crear cliente?')" : 'return false;' }}">
            Crear
          </button>
        </div>
      </form>
    </div>
  </div>

  {{-- =========================
      MODAL: Editar
     ========================= --}}
  <div class="ac-modal" id="modalEdit" aria-hidden="true">
    <div class="ac-modal-backdrop" data-close-modal></div>

    <div class="ac-modal-card" role="dialog" aria-modal="true" aria-label="Editar cliente">
      <div class="ac-modal-head">
        <div>
          <div class="ttl">Editar cliente</div>
          <div class="sub" id="mEdit_sub">—</div>
        </div>
        <button class="x" type="button" data-close-modal aria-label="Cerrar">✕</button>
      </div>

      <form method="POST"
              id="mEdit_form"
              action="#"
              class="ac-form"
              data-save-template="{{ route('admin.clientes.save', ['key' => '__KEY__']) }}">

          @csrf

          {{-- ✅ Siempre mandar key (RFC/ID) y el “unchecked” de is_blocked --}}
          <input type="hidden" id="mEdit_id" name="id" value="">
          <input type="hidden" name="is_blocked" value="0">

          <div class="ac-grid ac-grid-12">

          {{-- Razón social --}}
          <div class="ac-field ac-field-wide ac-col-8">
            <label>Razón social</label>
            <input class="ac-input" id="mEdit_rs" name="razon_social" value=""
                  placeholder="Razón social" autocomplete="organization">
          </div>

          {{-- (solo display) RFC/ID --}}
          <div class="ac-field ac-col-4">
            <label>RFC / ID</label>
            <input class="ac-input" id="mEdit_key_show" value="" readonly>
            <div class="ac-hint">Identificador de la cuenta (solo lectura).</div>
          </div>

          {{-- Email --}}
          <div class="ac-field ac-col-6">
            <label>Email</label>
            <input class="ac-input" id="mEdit_email" name="email" value=""
                  placeholder="correo@dominio.com" autocomplete="email" inputmode="email">
          </div>

          {{-- Teléfono --}}
          <div class="ac-field ac-col-6">
            <label>Teléfono</label>
            <input class="ac-input" id="mEdit_phone" name="phone" value=""
                  placeholder="+52..." autocomplete="tel" inputmode="tel">
          </div>

          {{-- Plan --}}
          <div class="ac-field ac-col-4">
            <label>Plan</label>
            <select class="ac-select" id="mEdit_plan" name="plan">
              <option value="">—</option>
              <option value="FREE">FREE</option>
              <option value="PRO">PRO</option>
              <option value="PRO_MENSUAL">PRO_MENSUAL</option>
              <option value="PRO_ANUAL">PRO_ANUAL</option>
            </select>
          </div>

          {{-- Ciclo --}}
          <div class="ac-field ac-col-4">
            <label>Ciclo</label>
            <select class="ac-select" id="mEdit_cycle" name="billing_cycle">
              <option value="">—</option>
              <option value="monthly">Mensual</option>
              <option value="yearly">Anual</option>
            </select>
          </div>

          {{-- Próx factura --}}
          <div class="ac-field ac-col-4">
            <label>Próx. factura</label>
            <input class="ac-input" id="mEdit_next" name="next_invoice_date" type="date" value="">
            <div class="ac-hint">La fecha se usa para el siguiente statement.</div>
          </div>

          {{-- Monto --}}
          <div class="ac-field ac-col-8">
            <label>Monto personalizado (MXN)</label>
            <input class="ac-input" id="mEdit_custom" name="custom_amount_mxn"
                  inputmode="decimal" placeholder="0.00" autocomplete="off">
            <div class="ac-hint">Déjalo vacío para usar el monto calculado del plan.</div>
          </div>

          {{-- Bloqueo (mejor presentado) --}}
          <div class="ac-field ac-col-4">
            <label>Bloqueo</label>
            <label class="ac-check ac-check-card" style="align-items:flex-start">
              <input type="checkbox" id="mEdit_blocked" name="is_blocked" value="1">
              <span>
                <strong>Cuenta bloqueada</strong>
                <div class="ac-hint" style="margin-top:4px">Al iniciar sesión redirige a Stripe Checkout.</div>
              </span>
            </label>
          </div>

          {{-- Nota discreta (no “técnica” para el usuario final) --}}
          <div class="ac-field ac-col-12">
            <div class="ac-note" style="opacity:.85">
              Cambios impactan plan/ciclo, próxima factura y estado de acceso. Guarda solo si estás seguro.
            </div>
          </div>
        </div>

        <div class="ac-form-actions">
          <button class="ac-btn" type="button" data-close-modal>Cancelar</button>
          <button class="ac-btn primary" type="submit">Guardar cambios</button>
        </div>
      </form> 
    </div>
  </div>

    {{-- =========================
      MODAL: Destinatarios
     ========================= --}}
  <div class="ac-modal ac-modal--recipients" id="modalRecipients" aria-hidden="true">
    <div class="ac-modal-backdrop" data-close-modal></div>

    <div class="ac-modal-card" role="dialog" aria-modal="true" aria-label="Destinatarios">
      <div class="ac-modal-head ac-modal-head--recipients">
        <div class="ac-modal-head-copy">
          <div class="ttl">Destinatarios</div>
          <div class="sub" id="mRec_sub">—</div>
        </div>
        <button class="x" type="button" data-close-modal aria-label="Cerrar">✕</button>
      </div>

      <div class="ac-modal-body ac-modal-body--recipients">
        <div class="ac-note ac-note--modal" id="mRec_missing" hidden>
          No se detectó ruta para guardar destinatarios (recip_url). Revisa que exista la route
          <code class="ac-mono">admin.clientes.recipientsUpsert</code>.
        </div>

        <div class="ac-tabs ac-tabs--recipients" data-tabs>
          <div class="ac-tabbar ac-tabbar--recipients" role="tablist" aria-label="Tipos de destinatarios">
            <button type="button" class="ac-tab active" aria-selected="true" data-tab="tabRecStmt">Estado de cuenta</button>
            <button type="button" class="ac-tab" aria-selected="false" data-tab="tabRecInv">Factura</button>
            <button type="button" class="ac-tab" aria-selected="false" data-tab="tabRecGen">General</button>
          </div>

          {{-- Estado de cuenta --}}
          <div class="ac-tabpane show" id="tabRecStmt">
            <form method="POST" id="mRec_form_statement" action="#" class="ac-form ac-form--recipients">
              @csrf

              <div class="ac-rec-section">
                <div class="ac-rec-section__head">
                  <div class="ac-rec-section__title">Estado de cuenta</div>
                  <div class="ac-rec-section__desc">Correos que recibirán estados de cuenta y recordatorios relacionados.</div>
                </div>

                <div class="ac-grid ac-grid-12">
                  <div class="ac-field ac-field-wide ac-col-12">
                    <label>Destinatarios (CSV)</label>
                    <textarea
                      class="ac-textarea ac-textarea--recipients"
                      id="mRec_stmt_list"
                      name="recipients"
                      placeholder="correo1@dominio.com, correo2@dominio.com"></textarea>
                    <div class="ac-hint">Separados por coma. Se normaliza a minúsculas.</div>
                  </div>

                  <div class="ac-field ac-field-wide ac-col-12">
                    <label>Primary</label>
                    <input class="ac-input" id="mRec_stmt_primary" name="primary" placeholder="correo@dominio.com">
                    <div class="ac-hint">Correo principal que se tomará como preferente para este tipo.</div>
                  </div>

                  <input type="hidden" name="kind" value="statement">
                  <input type="hidden" name="active" value="1">
                </div>
              </div>

              <div class="ac-form-actions ac-form-actions--recipients">
                <button class="ac-btn" type="button" data-close-modal>Cancelar</button>
                <button class="ac-btn primary" type="submit">Guardar</button>
              </div>
            </form>
          </div>

          {{-- Factura --}}
          <div class="ac-tabpane" id="tabRecInv" hidden>
            <form method="POST" id="mRec_form_invoice" action="#" class="ac-form ac-form--recipients">
              @csrf

              <div class="ac-rec-section">
                <div class="ac-rec-section__head">
                  <div class="ac-rec-section__title">Factura</div>
                  <div class="ac-rec-section__desc">Correos para CFDI, avisos de facturación y seguimiento administrativo.</div>
                </div>

                <div class="ac-grid ac-grid-12">
                  <div class="ac-field ac-field-wide ac-col-12">
                    <label>Destinatarios (CSV)</label>
                    <textarea
                      class="ac-textarea ac-textarea--recipients"
                      id="mRec_inv_list"
                      name="recipients"
                      placeholder="correo1@dominio.com, correo2@dominio.com"></textarea>
                    <div class="ac-hint">Separados por coma. Se normaliza a minúsculas.</div>
                  </div>

                  <div class="ac-field ac-field-wide ac-col-12">
                    <label>Primary</label>
                    <input class="ac-input" id="mRec_inv_primary" name="primary" placeholder="correo@dominio.com">
                    <div class="ac-hint">Correo principal para el flujo de facturación.</div>
                  </div>

                  <input type="hidden" name="kind" value="invoice">
                  <input type="hidden" name="active" value="1">
                </div>
              </div>

              <div class="ac-form-actions ac-form-actions--recipients">
                <button class="ac-btn" type="button" data-close-modal>Cancelar</button>
                <button class="ac-btn primary" type="submit">Guardar</button>
              </div>
            </form>
          </div>

          {{-- General --}}
          <div class="ac-tabpane" id="tabRecGen" hidden>
            <form method="POST" id="mRec_form_general" action="#" class="ac-form ac-form--recipients">
              @csrf

              <div class="ac-rec-section">
                <div class="ac-rec-section__head">
                  <div class="ac-rec-section__title">General</div>
                  <div class="ac-rec-section__desc">Correos de contacto general para avisos no ligados a billing o CFDI.</div>
                </div>

                <div class="ac-grid ac-grid-12">
                  <div class="ac-field ac-field-wide ac-col-12">
                    <label>Destinatarios (CSV)</label>
                    <textarea
                      class="ac-textarea ac-textarea--recipients"
                      id="mRec_gen_list"
                      name="recipients"
                      placeholder="correo1@dominio.com, correo2@dominio.com"></textarea>
                    <div class="ac-hint">Separados por coma. Se normaliza a minúsculas.</div>
                  </div>

                  <div class="ac-field ac-field-wide ac-col-12">
                    <label>Primary</label>
                    <input class="ac-input" id="mRec_gen_primary" name="primary" placeholder="correo@dominio.com">
                    <div class="ac-hint">Correo principal para comunicación general.</div>
                  </div>

                  <input type="hidden" name="kind" value="general">
                  <input type="hidden" name="active" value="1">
                </div>
              </div>

              <div class="ac-form-actions ac-form-actions--recipients">
                <button class="ac-btn" type="button" data-close-modal>Cancelar</button>
                <button class="ac-btn primary" type="submit">Guardar</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>

    {{-- =========================
      MODAL: Credenciales
     ========================= --}}
  <div class="ac-modal ac-modal--creds" id="modalCreds" aria-hidden="true">
    <div class="ac-modal-backdrop" data-close-modal></div>

    <div class="ac-modal-card" role="dialog" aria-modal="true" aria-label="Credenciales">
      <div class="ac-modal-head ac-modal-head--creds">
        <div class="ac-modal-head-copy">
          <div class="ttl">Credenciales</div>
          <div class="sub" id="mCred_sub">—</div>
        </div>
        <button class="x" type="button" data-close-modal aria-label="Cerrar">✕</button>
      </div>

      <div class="ac-modal-body ac-modal-body--creds">

        {{-- Resumen / identidad --}}
        <section class="ac-cred-section ac-cred-section--identity">
          <div class="ac-cred-section__head">
            <div>
              <div class="ac-cred-section__eyebrow">Identidad</div>
              <div class="ac-cred-section__title">Cuenta y acceso</div>
            </div>
          </div>

          <div class="ac-cred-kv-grid">
            <div class="ac-cred-kv">
              <div class="k">RFC</div>
              <div class="v">
                <code class="ac-mono" id="mCred_rfc">—</code>
              </div>
            </div>

            <div class="ac-cred-kv">
              <div class="k">OWNER</div>
              <div class="v">
                <code class="ac-mono" id="mCred_owner">—</code>
              </div>
            </div>

            <div class="ac-cred-kv">
              <div class="k">Temp pass</div>
              <div class="v">
                <code class="ac-mono" id="mCred_pass">—</code>
              </div>
            </div>

            <div class="ac-cred-kv">
              <div class="k">OTP</div>
              <div class="v">
                <span class="ac-otp-badge">
                  <code class="ac-mono" id="mCred_otp">—</code>
                </span>
              </div>
            </div>
          </div>
        </section>

        {{-- Token / URL --}}
        <section class="ac-cred-section">
          <div class="ac-cred-section__head">
            <div>
              <div class="ac-cred-section__eyebrow">Verificación</div>
              <div class="ac-cred-section__title">Token / URL</div>
            </div>
          </div>

          <div class="ac-cred-token-card">
            <div class="ac-cred-token-main">
              <div class="ac-cred-token-value">
                <code class="ac-mono ac-break" id="mCred_tok">—</code>
              </div>
              <div class="ac-cred-token-meta" id="mCred_tok_exp">—</div>
            </div>

            <div class="ac-cred-token-actions" id="mCred_tok_actions" hidden>
              <a class="ac-btn small" id="mCred_tok_open" href="#" target="_blank" rel="noopener">Abrir</a>
              <button class="ac-btn small" type="button" data-copy="#mCred_tok">Copiar</button>
            </div>
          </div>
        </section>

        {{-- Envío por correo --}}
        <section class="ac-cred-section">
          <div class="ac-cred-section__head">
            <div>
              <div class="ac-cred-section__eyebrow">Distribución</div>
              <div class="ac-cred-section__title">Enviar credenciales por correo</div>
              <div class="ac-cred-section__desc">
                Envía <strong>usuario (OWNER email)</strong>, <strong>contraseña temporal</strong> y
                <strong>liga de acceso</strong> a uno o varios correos.
              </div>
            </div>
          </div>

          <div class="ac-note ac-note--soft">
            Logo usado en la plantilla:
            <code class="ac-mono ac-break">{{ $brandLogoUrl }}</code>
          </div>

          <form method="POST" id="mCred_form_email_creds" action="#" class="ac-form ac-form--creds-email">
            @csrf

            <div class="ac-grid ac-grid-12">
              <div class="ac-field ac-field-wide ac-col-12">
                <label>Para (CSV)</label>
                <textarea
                  class="ac-textarea ac-textarea--creds"
                  id="mCred_to"
                  name="to"
                  placeholder="correo1@dominio.com, correo2@dominio.com"></textarea>
                <div class="ac-hint">Separados por coma. Se normaliza a minúsculas.</div>
              </div>

              {{-- payload hidden --}}
              <input type="hidden" name="usuario" id="mCred_hidden_user" value="">
              <input type="hidden" name="password" id="mCred_hidden_pass" value="">
              <input type="hidden" name="access_url" id="mCred_hidden_access" value="">
              <input type="hidden" name="rfc" id="mCred_hidden_rfc" value="">
              <input type="hidden" name="rs" id="mCred_hidden_rs" value="">
              <input type="hidden" name="logo_url" value="{{ $brandLogoUrl }}">

              <div class="ac-col-12">
                <div class="ac-cred-primary-actions">
                  <button class="ac-btn primary" type="submit" onclick="return confirm('¿Enviar credenciales por correo?')">
                    Enviar
                  </button>
                </div>
              </div>
            </div>
          </form>

          <div class="ac-note" id="mCred_email_creds_missing" hidden style="margin-top:10px">
            No se detectó ruta para enviar credenciales. Se esperaba:
            <code class="ac-mono">admin.clientes.emailCreds</code> o <code class="ac-mono">admin.clientes.emailCredentials</code>.
          </div>
        </section>

        {{-- Forzar verificaciones --}}
        <section class="ac-cred-section">
          <div class="ac-cred-section__head">
            <div>
              <div class="ac-cred-section__eyebrow">Soporte</div>
              <div class="ac-cred-section__title">Forzar verificaciones</div>
              <div class="ac-cred-section__desc">
                Útil para soporte cuando el cliente ya confirmó por otro canal.
              </div>
            </div>
          </div>

          <div class="ac-cred-secondary-actions">
            <form method="POST" id="mCred_form_force_email" action="#" class="inline" onsubmit="return confirm('¿Forzar verificación de correo?')">
              @csrf
              <button class="ac-btn small" type="submit">Forzar correo</button>
            </form>

            <form method="POST" id="mCred_form_force_phone" action="#" class="inline" onsubmit="return confirm('¿Forzar verificación de teléfono?')">
              @csrf
              <button class="ac-btn small" type="submit">Forzar teléfono</button>
            </form>
          </div>

          <div class="ac-note" id="mCred_force_phone_missing" hidden style="margin-top:10px">
            No existe endpoint para force-phone.
          </div>
        </section>

      </div>

      <div class="ac-form-actions ac-form-actions--creds">
        <button class="ac-btn" type="button" data-close-modal>Cerrar</button>
      </div>
    </div>
  </div>

  {{-- =========================
      MODAL: Billing
     ========================= --}}
  <div class="ac-modal" id="modalBilling" aria-hidden="true">
    <div class="ac-modal-backdrop" data-close-modal></div>

    <div class="ac-modal-card" role="dialog" aria-modal="true" aria-label="Billing">
      <div class="ac-modal-head">
        <div>
          <div class="ttl">Billing</div>
          <div class="sub" id="mBill_sub">—</div>
        </div>
        <button class="x" type="button" data-close-modal aria-label="Cerrar">✕</button>
      </div>

      <div class="ac-form">
        <div class="ac-grid">
          <div class="ac-field">
            <label>Periodo</label>
            <input class="ac-input" id="mBill_period" value="{{ $defaultPeriod }}" readonly>
          </div>

          <div class="ac-field">
            <label>Sembrar estado de cuenta</label>
            <form method="POST" id="mBill_form_seed" action="#" onsubmit="return confirm('¿Sembrar/regen del statement?')">
              @csrf
              <input type="hidden" id="mBill_seed_period" name="period" value="{{ $defaultPeriod }}">
              <button class="ac-btn" type="submit">Seed</button>
            </form>
            <div class="ac-hint" id="mBill_seed_missing" hidden>Falta route <code class="ac-mono">admin.clientes.seedStatement</code></div>
          </div>

          <div class="ac-field">
            <label>Ver PDF / Pantalla</label>
            <a class="ac-btn" id="mBill_btn_show" href="#" target="_blank" rel="noopener">Abrir</a>
            <div class="ac-hint" id="mBill_show_missing" hidden>Falta route <code class="ac-mono">admin.billing.statements.show</code></div>
          </div>

          <div class="ac-field">
            <label>Enviar por correo</label>
            <form method="POST" id="mBill_form_email" action="#" onsubmit="return confirm('¿Enviar estado de cuenta por correo?')">
              @csrf
              <button class="ac-btn primary" type="submit">Enviar</button>
            </form>
            <div class="ac-hint" id="mBill_email_missing" hidden>Falta route <code class="ac-mono">admin.billing.statements.email</code></div>
          </div>

          <div class="ac-field ac-field-wide">
            <div class="ac-note">
              Si “Enviar” no hace nada, revisa: (1) destinatarios <strong>statement</strong> configurados,
              (2) mailer env en local/prod, (3) logs <code class="ac-mono">storage/logs/laravel.log</code>.
            </div>
          </div>
        </div>
      </div>

      <div class="ac-form-actions">
        <button class="ac-btn" type="button" data-close-modal>Cerrar</button>
      </div>
    </div>
  </div>

    {{-- =========================
      MODAL: Facturotopia / API / Timbres
     ========================= --}}
  <div class="ac-modal" id="modalFacturotopia" aria-hidden="true">
    <div class="ac-modal-backdrop" data-close-modal></div>

    <div class="ac-modal-card" role="dialog" aria-modal="true" aria-label="Facturotopia API Timbres">
      <div class="ac-modal-head">
        <div>
          <div class="ttl">Facturotopia / API / Timbres</div>
          <div class="sub" id="mFt_sub">—</div>
        </div>
        <button class="x" type="button" data-close-modal aria-label="Cerrar">✕</button>
      </div>

      <form method="POST" id="mFt_form" action="" class="ac-form" novalidate data-ready="0">
        @csrf

        <div class="ac-grid ac-grid-12">
          <div class="ac-field ac-col-4">
            <label>Estatus</label>
            <select class="ac-select" id="mFt_status" name="facturotopia_status">
              <option value="pendiente">Pendiente</option>
              <option value="en_proceso">En proceso</option>
              <option value="activo">Activo</option>
              <option value="suspendido">Suspendido</option>
              <option value="error">Error</option>
            </select>
          </div>

          <input type="hidden" id="mFt_env" name="facturotopia_env" value="sandbox">

          <div class="ac-field ac-col-4">
            <label>Configuración PAC</label>
            <input class="ac-input" value="Pruebas y Producción configurables" readonly>
          </div>

          <div class="ac-field ac-col-4">
            <label>ID cliente Facturotopia</label>
            <input class="ac-input" id="mFt_customer" name="facturotopia_customer_id" placeholder="ID externo">
          </div>

          {{-- =========================
              Acceso PAC (Facturotopia)
            ========================= --}}
          <div class="ac-field ac-col-6">
              <label>Usuario PAC</label>
              <input
                type="text"
                class="ac-input"
                id="mFt_user"
                name="facturotopia_user"
                placeholder="correo@empresa.com">
            </div>

            <div class="ac-field ac-col-6">
              <label>Contraseña PAC</label>
              <input
                type="text"
                class="ac-input"
                id="mFt_pass"
                name="facturotopia_password"
                placeholder="Contraseña PAC">
            </div>

            <div class="ac-field ac-field-wide ac-col-6">
              <label>Base URL pruebas</label>
              <input class="ac-input" id="mFt_sandbox_url" name="facturotopia_base_url_sandbox" placeholder="https://...">
            </div>

            <div class="ac-field ac-field-wide ac-col-6">
              <label>API key pruebas</label>
              <input class="ac-input" id="mFt_sandbox_key" name="facturotopia_api_key_sandbox" placeholder="API key sandbox">
            </div>

            <div class="ac-field ac-field-wide ac-col-6">
              <label>Base URL producción</label>
              <input class="ac-input" id="mFt_prod_url" name="facturotopia_base_url_production" placeholder="https://...">
            </div>

            <div class="ac-field ac-field-wide ac-col-6">
              <label>API key producción</label>
              <input class="ac-input" id="mFt_prod_key" name="facturotopia_api_key_production" placeholder="API key producción">
            </div>

          <div class="ac-field ac-col-3">
            <label>Timbres asignados</label>
            <input class="ac-input" id="mFt_timbres_asignados" name="timbres_asignados" type="number" min="0" step="1">
          </div>

          <div class="ac-field ac-col-3">
            <label>Timbres consumidos</label>
            <input class="ac-input" id="mFt_timbres_consumidos" name="timbres_consumidos" type="number" min="0" step="1">
          </div>

          <div class="ac-field ac-col-3">
            <label>Hits asignados</label>
            <input class="ac-input" id="mFt_hits_asignados" name="hits_asignados" type="number" min="0" step="1">
          </div>

          <div class="ac-field ac-col-3">
            <label>Hits consumidos</label>
            <input class="ac-input" id="mFt_hits_consumidos" name="hits_consumidos" type="number" min="0" step="1">
          </div>

          <div class="ac-field ac-field-wide ac-col-12">
            <label>Notas internas</label>
            <textarea class="ac-textarea" id="mFt_notas" name="notas_facturotopia" rows="4" placeholder="Notas internas para soporte/admin"></textarea>
          </div>

          <div class="ac-field ac-col-12">
            <div class="ac-note">
              Admin captura las credenciales de pruebas y producción. El cliente podrá consultar estos datos y elegir el ambiente operativo desde Timbres / Hits.
            </div>
          </div>
        </div>

        <div class="ac-form-actions">
          <button class="ac-btn" type="button" data-close-modal>Cancelar</button>
          <button class="ac-btn primary" type="submit" form="mFt_form">
            Guardar
          </button>
        </div>
      </form>
    </div>
  </div>

  {{-- =====================================
  MODAL IFRAME: Submódulos (Administrar / Estado HUB)
  Mejorado: loader + inspección same-origin + fallback limpio
===================================== --}}
<div class="ac-modal ac-modal--iframe" id="acIframeModal" aria-hidden="true">
  <div class="ac-modal-backdrop" data-close-ac-iframe></div>

  <div class="ac-modal-card ac-if-card-shell" role="dialog" aria-modal="true" aria-label="Submódulo">
    <div class="ac-modal-head ac-if-head">
      <div class="ac-if-head-copy">
        <div class="ttl" id="acIf_title">—</div>
        <div class="sub" id="acIf_sub">—</div>
      </div>

      <div class="ac-if-head-actions">
        <a class="ac-btn small" id="acIf_open_new" href="#" target="_blank" rel="noopener">Abrir en pestaña</a>
        <button class="x" type="button" data-close-ac-iframe aria-label="Cerrar">✕</button>
      </div>
    </div>

    <div class="ac-if-status" id="acIf_status">
      <span class="dot"></span>
      <span id="acIf_status_text">Cargando panel…</span>
    </div>

    <div class="ac-if-wrap" id="acIf_wrap" data-state="idle">
      <div class="ac-if-loader" id="acIf_loader" aria-hidden="true">
        <div class="ac-if-loader-card">
          <div class="ac-if-spinner" aria-hidden="true"></div>
          <div class="ac-if-loader-ttl">Cargando panel</div>
          <div class="ac-if-loader-desc">
            Estamos intentando abrir el submódulo dentro del modal.
          </div>
        </div>
      </div>

      <div id="acIf_fallback" class="ac-if-fallback" hidden>
        <div class="ac-if-fallback-card">
          <div class="ac-if-fallback-icon">⚠️</div>
          <div class="ac-if-ttl">No pudimos mostrar este panel dentro del modal</div>
          <div class="ac-if-desc" id="acIf_fallback_reason">
            La página puede haber redirigido al login, cargado un layout no embebible o haber respondido con un error.
          </div>

          <div class="ac-if-actions">
            <a class="ac-btn primary" id="acIf_open_new_2" href="#" target="_blank" rel="noopener">Abrir panel completo</a>
            <button class="ac-btn" type="button" id="acIf_retry">Reintentar</button>
          </div>

          <div class="ac-if-url-wrap">
            <div class="ac-if-url-label">URL</div>
            <div id="acIf_fallback_url" class="ac-if-url">—</div>
          </div>
        </div>
      </div>

      <iframe
        id="acIf_frame"
        src="about:blank"
        title="Contenido"
        class="ac-if-frame"
        loading="eager"
        referrerpolicy="strict-origin-when-cross-origin"></iframe>
    </div>

    <div class="ac-form-actions">
      <button class="ac-btn" type="button" data-close-ac-iframe>Cerrar</button>
    </div>
  </div>
</div>

</div>
@endsection
 @push('scripts')
<script src="{{ asset('assets/admin/js/admin-clientes.js') }}?v={{ @filemtime(public_path('assets/admin/js/admin-clientes.js')) }}"></script>

<script src="{{ asset('assets/admin/js/admin-clientes.vnext.overlays.v2.js') }}?v={{ @filemtime(public_path('assets/admin/js/admin-clientes.vnext.overlays.v2.js')) }}"></script> 

<script>
let p360FacturotopiaCurrentUrl = '';

document.addEventListener('click', function (e) {
  const btn = e.target.closest('[data-open-facturotopia]');
  if (!btn) return;

  e.preventDefault();

  const row = btn.closest('.ac-row');
  if (!row) return;

  let data = {};
  try {
    data = JSON.parse(row.getAttribute('data-client') || '{}');
  } catch (err) {
    data = {};
  }

  const modal = document.getElementById('modalFacturotopia');
  const form = document.getElementById('mFt_form');
  if (!modal || !form) return;

  const setVal = (id, value) => {
    const el = document.getElementById(id);
    if (el) el.value = value ?? '';
  };

  const sub = document.getElementById('mFt_sub');
  if (sub) sub.textContent = `${data.rfc || '—'} · ${data.razon_social || '—'}`;

  p360FacturotopiaCurrentUrl =
  btn.getAttribute('data-facturotopia-url') ||
  data.facturotopia_save_url ||
  '';

  if (!p360FacturotopiaCurrentUrl && data.id) {
    p360FacturotopiaCurrentUrl = `/admin/clientes/${encodeURIComponent(data.id)}/facturotopia`;
  }

  if (!p360FacturotopiaCurrentUrl && data.key) {
    p360FacturotopiaCurrentUrl = `/admin/clientes/${encodeURIComponent(data.key)}/facturotopia`;
  }

  form.setAttribute('action', p360FacturotopiaCurrentUrl);
  form.dataset.ready = p360FacturotopiaCurrentUrl ? '1' : '0';

  const ft = {
  status: btn.dataset.ftStatus || data.facturotopia_status || 'pendiente',
  env: btn.dataset.ftEnv || data.facturotopia_env || 'sandbox',
  customer: btn.dataset.ftCustomer || data.facturotopia_customer_id || '',
  user: btn.dataset.ftUser || data.facturotopia_user || '',
  passwordSaved: btn.dataset.ftPasswordSaved || data.facturotopia_password_saved || '0',
  password: btn.dataset.ftPassword || data.facturotopia_password || '',
  sandboxUrl: btn.dataset.ftSandboxUrl || data.facturotopia_base_url_sandbox || '',
  sandboxKey: btn.dataset.ftSandboxKey || data.facturotopia_api_key_sandbox || '',
  prodUrl: btn.dataset.ftProdUrl || data.facturotopia_base_url_production || '',
  prodKey: btn.dataset.ftProdKey || data.facturotopia_api_key_production || '',
  timbresAsignados: btn.dataset.ftTimbresAsignados || data.timbres_asignados || 0,
  timbresConsumidos: btn.dataset.ftTimbresConsumidos || data.timbres_consumidos || 0,
  hitsAsignados: btn.dataset.ftHitsAsignados || data.hits_asignados || 0,
  hitsConsumidos: btn.dataset.ftHitsConsumidos || data.hits_consumidos || 0,
  notas: btn.dataset.ftNotas || data.notas_facturotopia || '',
};

setVal('mFt_status', ft.status);
setVal('mFt_env', ft.env);
setVal('mFt_customer', ft.customer);
setVal('mFt_user', ft.user);
setVal('mFt_pass', ft.password);

const passInput = document.getElementById('mFt_pass');
if (passInput) {
  passInput.type = 'text';
  passInput.placeholder = ft.passwordSaved === '1'
    ? 'Contraseña PAC guardada'
    : 'Sin contraseña guardada';
}

setVal('mFt_sandbox_url', ft.sandboxUrl);
setVal('mFt_sandbox_key', ft.sandboxKey);
setVal('mFt_prod_url', ft.prodUrl);
setVal('mFt_prod_key', ft.prodKey);

setVal('mFt_timbres_asignados', ft.timbresAsignados);
setVal('mFt_timbres_consumidos', ft.timbresConsumidos);
setVal('mFt_hits_asignados', ft.hitsAsignados);
setVal('mFt_hits_consumidos', ft.hitsConsumidos);
setVal('mFt_notas', ft.notas);

  modal.classList.add('show');
  modal.setAttribute('aria-hidden', 'false');
});

document.getElementById('mFt_form')?.addEventListener('submit', function(e){
  e.preventDefault();
  e.stopPropagation();

  if (!p360FacturotopiaCurrentUrl) {
    alert('No se detectó la URL de guardado PAC para este cliente.');
    return false;
  }

  if (!confirm('¿Guardar datos Facturotopia/API/Timbres?')) {
    return false;
  }

  this.setAttribute('action', p360FacturotopiaCurrentUrl);
  this.setAttribute('method', 'POST');

  HTMLFormElement.prototype.submit.call(this);
});
</script>

@endpush