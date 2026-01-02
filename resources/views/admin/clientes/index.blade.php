{{-- C:\wamp64\www\pactopia360_erp\resources\views\admin\clientes\index.blade.php --}}
@extends('layouts.admin')

@section('title','Clientes (accounts)')

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/admin/css/admin-clientes.css') }}?v=13.2.0">
@endpush

@section('content')
@php
  use Illuminate\Support\Facades\Route;
  use Illuminate\Support\Carbon;

  $q             = request('q');
  $plan          = request('plan');
  $blocked       = request('blocked');
  $billingStatus = request('billing_status');

  $s  = request('sort','created_at');
  $d  = strtolower(request('dir','desc'))==='asc'?'asc':'desc';
  $pp = (int) request('per_page', 25);

  $total = method_exists($rows,'total') ? $rows->total() : (is_countable($rows) ? count($rows) : null);

  // KPIs rápidos (sobre $rows ya filtrado/paginado en backend)
  $verMail = 0; $verPhone = 0; $cntPro = 0; $cntFree = 0; $cntBlocked = 0;
  $cntActive=0; $cntTrial=0; $cntOverdue=0; $cntSuspended=0; $cntCancelled=0;

  foreach ($rows as $x) {
    if(!empty($x->email_verified_at)) $verMail++;
    if(!empty($x->phone_verified_at)) $verPhone++;

    $p = strtolower((string)($x->plan ?? ''));
    if($p==='pro')  $cntPro++;
    if($p==='free') $cntFree++;

    if((int)($x->is_blocked ?? 0)===1) $cntBlocked++;

    $bs = strtolower((string)($x->billing_status ?? ''));
    if($bs==='active') $cntActive++;
    if($bs==='trial') $cntTrial++;
    if($bs==='overdue') $cntOverdue++;
    if($bs==='suspended') $cntSuspended++;
    if($bs==='cancelled') $cntCancelled++;
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

  $stmtIdx = $try('admin.billing.statements.index');

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
@endphp

<div id="adminClientesPage"
     class="ac-page"
     data-default-period="{{ $defaultPeriod }}">

  <div class="ac-shell">

    {{-- Topbar --}}
    <div class="ac-topbar">
      <div class="ac-topbar-left">
        <div class="ac-title">
          <h1>Clientes</h1>
          <div class="ac-sub">Administración de cuentas (SOT: <strong>admin.accounts</strong>)</div>
        </div>

        <form method="GET" id="quickSearchForm" class="ac-search" autocomplete="off">
          <input class="ac-input"
                 name="q"
                 value="{{ $q }}"
                 placeholder="Buscar RFC, razón social, correo o teléfono…"
                 aria-label="Buscar">

          <input type="hidden" name="plan" value="{{ $plan }}">
          <input type="hidden" name="blocked" value="{{ $blocked }}">
          <input type="hidden" name="billing_status" value="{{ $billingStatus }}">
          <input type="hidden" name="sort" value="{{ $s }}">
          <input type="hidden" name="dir" value="{{ $d }}">
          <input type="hidden" name="per_page" value="{{ $pp }}">

          <button class="ac-btn primary" type="submit">Buscar</button>
          @if($q)
            <a class="ac-btn ghost" href="{{ request()->fullUrlWithQuery(['q'=>'','page'=>null]) }}">Limpiar</a>
          @endif
        </form>
      </div>

      <div class="ac-topbar-right">
        <form method="POST" action="{{ route('admin.clientes.syncToClientes') }}" onsubmit="return confirm('¿Sincronizar accounts → clientes (legacy)?')">
          @csrf
          <button class="ac-btn" type="submit">Sincronizar</button>
        </form>

        <button class="ac-btn" id="btnExportCsv" type="button">Exportar CSV</button>

        @if($stmtIdx)
          <a class="ac-btn primary" href="{{ $stmtIdx }}">Estados de cuenta</a>
        @endif
      </div>
    </div>

    {{-- KPIs --}}
    <div class="ac-kpis">
      <div class="ac-kpi"><div class="v">{{ $total ?? '—' }}</div><div class="k">Total</div></div>
      <div class="ac-kpi"><div class="v">{{ $cntPro }}</div><div class="k">PRO</div></div>
      <div class="ac-kpi"><div class="v">{{ $cntFree }}</div><div class="k">FREE</div></div>
      <div class="ac-kpi"><div class="v">{{ $cntBlocked }}</div><div class="k">Bloqueados</div></div>

      <div class="ac-kpi"><div class="v">{{ $cntActive }}</div><div class="k">Activas</div></div>
      <div class="ac-kpi"><div class="v">{{ $cntTrial }}</div><div class="k">Prueba</div></div>
      <div class="ac-kpi"><div class="v">{{ $cntOverdue }}</div><div class="k">Overdue</div></div>
      <div class="ac-kpi"><div class="v">{{ $cntSuspended + $cntCancelled }}</div><div class="k">Suspend/Cancel</div></div>

      <div class="ac-kpi"><div class="v">{{ $verMail }}</div><div class="k">Correo verificado</div></div>
      <div class="ac-kpi"><div class="v">{{ $verPhone }}</div><div class="k">Tel verificado</div></div>
      <div class="ac-kpi ghost"><div class="v">{{ $defaultPeriod }}</div><div class="k">Periodo sugerido</div></div>
      <div class="ac-kpi ghost"><div class="v">{{ now()->format('Y-m-d H:i') }}</div><div class="k">Corte</div></div>
    </div>

    {{-- Toolbar --}}
    <div class="ac-toolbar">
      <div class="ac-chips">
        <a class="ac-chip {{ $is('blocked','0')?'active':'' }}" href="{{ request()->fullUrlWithQuery(['blocked'=>'0','page'=>null]) }}">Operando</a>
        <a class="ac-chip {{ $is('blocked','1')?'active':'' }}" href="{{ request()->fullUrlWithQuery(['blocked'=>'1','page'=>null]) }}">Bloqueados</a>
        <a class="ac-chip {{ $is('plan','free')?'active':'' }}" href="{{ request()->fullUrlWithQuery(['plan'=>'free','page'=>null]) }}">Free</a>
        <a class="ac-chip {{ $is('plan','pro')?'active':'' }}" href="{{ request()->fullUrlWithQuery(['plan'=>'pro','page'=>null]) }}">Pro</a>

        <a class="ac-chip {{ $is('billing_status','active')?'active':'' }}" href="{{ request()->fullUrlWithQuery(['billing_status'=>'active','page'=>null]) }}">Activas</a>
        <a class="ac-chip {{ $is('billing_status','trial')?'active':'' }}" href="{{ request()->fullUrlWithQuery(['billing_status'=>'trial','page'=>null]) }}">Prueba</a>
        <a class="ac-chip {{ $is('billing_status','overdue')?'active':'' }}" href="{{ request()->fullUrlWithQuery(['billing_status'=>'overdue','page'=>null]) }}">Falta pago</a>

        <a class="ac-chip ghost" href="{{ route('admin.clientes.index') }}">Limpiar</a>
      </div>

      <details class="ac-filters" id="filtersBox" {{ ($plan||$blocked||$billingStatus||$s!=='created_at'||$d!=='desc'||$pp!==25||$q) ? 'open' : '' }}>
        <summary class="ac-filters-summary">
          <span>Filtros avanzados</span>
          <span class="ac-meta">
            Orden: <strong>{{ $s }}</strong> · <strong>{{ $d }}</strong> · {{ $pp }}/pág
            @if($billingStatus) · Billing: <strong>{{ $billingStatus }}</strong>@endif
          </span>
        </summary>

        <form method="GET" id="filtersForm" class="ac-filters-form">
          <div class="ac-filters-grid">
            <div class="ac-field">
              <label>Buscar</label>
              <input class="ac-input" name="q" value="{{ $q }}" placeholder="RFC, razón social, correo o teléfono">
            </div>

            <div class="ac-field">
              <label>Plan</label>
              <select name="plan" class="ac-select">
                <option value="">Todos</option>
                <option value="free" {{ (string)$plan==='free'?'selected':'' }}>Free</option>
                <option value="pro"  {{ (string)$plan==='pro'?'selected':'' }}>Pro</option>
              </select>
            </div>

            <div class="ac-field">
              <label>Bloqueo</label>
              <select name="blocked" class="ac-select">
                <option value="">Todos</option>
                <option value="0" {{ request('blocked')==='0' ? 'selected':'' }}>No bloqueados</option>
                <option value="1" {{ request('blocked')==='1' ? 'selected':'' }}>Bloqueados</option>
              </select>
            </div>

            <div class="ac-field">
              <label>Billing status</label>
              <select name="billing_status" class="ac-select">
                <option value="">Todos</option>
                @foreach($billingStatuses as $k=>$lbl)
                  <option value="{{ $k }}" {{ (string)$billingStatus===(string)$k ? 'selected':'' }}>{{ $lbl }}</option>
                @endforeach
              </select>
            </div>

            <div class="ac-field">
              <label>Orden</label>
              <select name="sort" class="ac-select">
                <option value="created_at" {{ $s==='created_at'?'selected':'' }}>Creado</option>
                <option value="razon_social" {{ $s==='razon_social'?'selected':'' }}>Razón social</option>
                <option value="plan" {{ $s==='plan'?'selected':'' }}>Plan</option>
                <option value="billing_cycle" {{ $s==='billing_cycle'?'selected':'' }}>Ciclo</option>
                <option value="billing_status" {{ $s==='billing_status'?'selected':'' }}>Billing status</option>
                <option value="email_verified_at" {{ $s==='email_verified_at'?'selected':'' }}>Correo verificado</option>
                <option value="phone_verified_at" {{ $s==='phone_verified_at'?'selected':'' }}>Tel verificado</option>
                <option value="is_blocked" {{ $s==='is_blocked'?'selected':'' }}>Bloqueo</option>
              </select>
            </div>

            <div class="ac-field">
              <label>Dirección</label>
              <select name="dir" class="ac-select">
                <option value="desc" {{ $d==='desc'?'selected':'' }}>Desc</option>
                <option value="asc"  {{ $d==='asc'?'selected':'' }}>Asc</option>
              </select>
            </div>

            <div class="ac-field">
              <label>Por página</label>
              <select name="per_page" class="ac-select">
                @foreach([10,25,50,100] as $opt)
                  <option value="{{ $opt }}" {{ $pp===$opt?'selected':'' }}>{{ $opt }}</option>
                @endforeach
              </select>
            </div>

            <div class="ac-filters-actions">
              <button class="ac-btn primary" type="submit">Aplicar</button>
              <a class="ac-btn ghost" href="{{ route('admin.clientes.index') }}">Reset</a>
            </div>
          </div>
        </form>
      </details>
    </div>

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
          <span class="ac-meta"> ({{ $tl['ts'] ?? now()->toDateTimeString() }})</span>
        </div>
      @endif
    </div>

    {{-- Lista --}}
    <div class="ac-list" role="region" aria-label="Listado de clientes">
      <div class="ac-list-head">
        <div class="hcol">Cliente</div>
        <div class="hcol">Contacto</div>
        <div class="hcol">Estado</div>
        <div class="hcol">Plan</div>
        <div class="hcol">Ciclo</div>
        <div class="hcol">Próx. factura</div>
        <div class="hcol">Monto</div>
        <div class="hcol">Edo. cuenta</div>
        <div class="hcol actions">Acciones</div>
      </div>

      @forelse($rows as $r)
        @php
          $RFC_FULL = strtoupper(trim((string) (data_get($r,'rfc') ?: data_get($r,'tax_id') ?: data_get($r,'id'))));
          $created  = optional($r->created_at)->format('Y-m-d H:i') ?? '—';
          $idStr    = (string)($r->id ?? '');

          $info     = $extras[$r->id] ?? null;

          $planVal  = strtolower((string)($r->plan ?? ''));
          $bcRaw    = (string)($r->billing_cycle ?? '');
          $nextRaw  = (string)($r->next_invoice_date ?? '');
          $bsRaw    = (string)($r->billing_status ?? '');

          if (($bcRaw === '' || $bcRaw === null) && is_array($info) && !empty($info['billing_cycle'])) {
            $bcRaw = (string)$info['billing_cycle'];
          }
          if (($nextRaw === '' || $nextRaw === null) && is_array($info) && !empty($info['next_invoice_date'])) {
            $nextRaw = (string)$info['next_invoice_date'];
          }
          if (($bsRaw === '' || $bsRaw === null) && is_array($info) && !empty($info['billing_status'])) {
            $bsRaw = (string)$info['billing_status'];
          }

          $bcLabel   = $cycleLabel($bcRaw);
          $nextLabel = $dateLabel($nextRaw);

          $customAmount = null;
          foreach (['custom_amount_mxn','override_amount_mxn','billing_amount_mxn','amount_mxn','precio_mxn','monto_mxn','license_amount_mxn'] as $p) {
            if (isset($r->{$p}) && $r->{$p} !== null && $r->{$p} !== '') { $customAmount = $r->{$p}; break; }
          }
          $hasCustom = ($customAmount !== null && $customAmount !== '' && is_numeric($customAmount));
          $effective = is_array($info) ? ($info['license_amount_mxn_effective'] ?? null) : null;

          $isBlocked = ((int)$r->is_blocked===1);
          $mailOk = !empty($r->email_verified_at);
          $phoneOk = !empty($r->phone_verified_at);

          $seedUrl   = $try('admin.clientes.seedStatement', ['rfc'=>$r->id]);
          $recipUrl  = $try('admin.clientes.recipientsUpsert', ['rfc'=>$r->id]);

          $stmtShow  = $try('admin.billing.statements.show',  ['accountId'=>$r->id, 'period'=>$defaultPeriod]);
          $stmtEmail = $try('admin.billing.statements.email', ['accountId'=>$r->id, 'period'=>$defaultPeriod]);

          // ✅ URL para enviar credenciales
          $emailCredsUrl = $try('admin.clientes.emailCreds', ['rfc'=>$r->id]) ?: $try('admin.clientes.emailCredentials', ['rfc'=>$r->id]);

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

          $estadoCuenta = is_array($info) ? (string)($info['estado_cuenta'] ?? $info['account_status'] ?? '') : '';
          $modoCobro    = is_array($info) ? (string)($info['modo_cobro'] ?? $info['billing_mode'] ?? '') : '';
          $stripeCust   = is_array($info) ? (string)($info['stripe_customer_id'] ?? '') : '';
          $stripeSub    = is_array($info) ? (string)($info['stripe_subscription_id'] ?? '') : '';
          $periodStart  = is_array($info) ? (string)($info['current_period_start'] ?? '') : '';
          $periodEnd    = is_array($info) ? (string)($info['current_period_end'] ?? '') : '';

          $amtShow = '—';
          $amtMeta = '—';
          if (is_numeric($effective) && (float)$effective >= 0) { $amtShow = $money($effective); $amtMeta='licencia efectiva'; }
          elseif ($hasCustom) { $amtShow = $money($customAmount); $amtMeta='precio personalizado'; }

          $amtCustomShow = $hasCustom ? $money($customAmount) : '—';
          $amtEffShow    = (is_numeric($effective) && (float)$effective >= 0) ? $money($effective) : '—';

          $otpCode    = is_array($info) ? (string)($info['otp_code'] ?? '') : '';
          $otpChannel = is_array($info) ? (string)($info['otp_channel'] ?? '') : '';
          $tokenUrl   = is_array($info) ? (string)($info['token_url'] ?? '') : '';
          $tokenExp   = is_array($info) ? (string)($info['token_expires'] ?? '') : '';

          $fallbackAccessUrl = rtrim((string)config('app.url'), '/') . '/cliente';
          $accessUrl = $tokenUrl !== '' ? $tokenUrl : $fallbackAccessUrl;

          $exportPayload = [
            "ID" => $idStr,
            "RFC" => $RFC_FULL,
            "RazonSocial" => (string)($r->razon_social ?? ''),
            "Email" => (string)($r->email ?? ''),
            "Phone" => (string)($r->phone ?? ''),
            "Plan" => (string)($r->plan ?? ''),
            "BillingCycle" => (string)($bcRaw ?? ''),
            "BillingStatus" => (string)($bsRaw ?? ''),
            "NextInvoice" => (string)($nextRaw ?? ''),
            "CustomAmountMxn" => $hasCustom ? (string)$customAmount : '',
            "EffectiveAmountMxn" => (is_numeric($effective) && (float)$effective >= 0) ? (string)$effective : '',
            "StatementRecipients" => $recipsStatement,
            "InvoiceRecipients" => $recipsInvoice,
            "GeneralRecipients" => $recipsGeneral,
            "EmailVerif" => $mailOk ? "1" : "0",
            "PhoneVerif" => $phoneOk ? "1" : "0",
            "Blocked" => $isBlocked ? "1" : "0",
            "EstadoCuenta" => $estadoCuenta,
            "ModoCobro" => $modoCobro,
            "StripeCustomer" => $stripeCust,
            "StripeSubscription" => $stripeSub,
            "CreatedAt" => $created,
          ];
          $exportJson = e(json_encode($exportPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

          $clientPayload = [
            "id" => $idStr,
            "rfc" => $RFC_FULL,
            "razon_social" => (string)($r->razon_social ?? ''),
            "created" => $created,
            "email" => (string)($r->email ?? ''),
            "phone" => (string)($r->phone ?? ''),

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

            "recips_statement" => $recipsStatement,
            "recips_invoice" => $recipsInvoice,
            "recips_general" => $recipsGeneral,

            "primary_statement" => $primaryStatement,
            "primary_invoice" => $primaryInvoice,
            "primary_general" => $primaryGeneral,

            "otp_code" => $otpCode,
            "otp_channel" => $otpChannel,
            "token_url" => $tokenUrl,
            "token_expires" => $tokenExp,

            "email_creds_url" => $emailCredsUrl ?: '',
            "access_url" => $accessUrl,
          ];
          $clientJson = e(json_encode($clientPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

          $bsLbl = (string)($billingStatuses[strtolower((string)$bsRaw)] ?? ($bsRaw ?: '—'));
          $bsCls = $bsTone($bsRaw);

          $nextPeriodEndLbl = $periodEnd ? $dateLabel($periodEnd) : '—';
        @endphp

        <div class="ac-row"
             data-client="{{ $clientJson }}"
             data-export="{{ $exportJson }}">

          {{-- Cliente --}}
          <div class="cell client" data-label="Cliente">
            <div class="rfc">{{ $RFC_FULL }}</div>
            <div class="rs">{{ $r->razon_social ?: '—' }}</div>

            <div class="meta">
              ID: <strong class="ac-mono">{{ $idStr ?: '—' }}</strong>
              · Creado: <strong>{{ $created }}</strong>
            </div>

            <div class="meta" style="margin-top:6px">
              Estado cuenta: <strong>{{ $estadoCuenta ?: '—' }}</strong>
              @if($modoCobro)
                · Modo cobro: <strong>{{ strtoupper($modoCobro) }}</strong>
              @endif
            </div>

            @if(!empty($stripeCust) || !empty($stripeSub))
              <div class="meta" style="margin-top:6px">
                Stripe:
                <span class="ac-mono" title="Customer">{{ $stripeCust ?: '—' }}</span>
                · <span class="ac-mono" title="Subscription">{{ $stripeSub ?: '—' }}</span>
              </div>
            @endif

            @if(!empty($periodStart) || !empty($periodEnd))
              <div class="meta" style="margin-top:6px">
                Periodo:
                <span class="ac-mono">{{ $periodStart ? $dateLabel($periodStart) : '—' }}</span>
                →
                <span class="ac-mono">{{ $periodEnd ? $dateLabel($periodEnd) : '—' }}</span>
              </div>
            @endif
          </div>

          {{-- Contacto --}}
          <div class="cell" data-label="Contacto">
            <div class="ac-kv">
              <div class="k">Email</div>
              <div class="v"><span class="mono">{{ $r->email ?: '—' }}</span></div>

              <div class="k">Tel</div>
              <div class="v"><span class="mono">{{ $r->phone ?: '—' }}</span></div>

              <div class="k">Primary</div>
              <div class="v"><span class="mono">{{ $primaryStatement ?: '—' }}</span></div>
            </div>

            <div class="meta" style="margin-top:10px">
              Edo. cuenta: <strong>{{ $stmtCount ? ($stmtCount.' destinatarios') : 'Sin destinatarios' }}</strong>
            </div>
          </div>

          {{-- Estado --}}
          <div class="cell" data-label="Estado">
            <div class="mini" style="margin-top:0">
              <span class="badge {{ $isBlocked ? 'bad':'ok' }}"><span class="dot"></span>{{ $isBlocked ? 'Bloqueado' : 'Operando' }}</span>
              <span class="badge {{ $mailOk ? 'ok':'warn' }}"><span class="dot"></span>Correo {{ $mailOk?'✔':'pendiente' }}</span>
              <span class="badge {{ $phoneOk ? 'ok':'warn' }}"><span class="dot"></span>Tel {{ $phoneOk?'✔':'pendiente' }}</span>
              <span class="badge {{ $bsCls }}"><span class="dot"></span>{{ $bsLbl }}</span>
            </div>

            <div class="meta" style="margin-top:10px">
              Suscripción: <strong>{{ $r->plan ? strtoupper((string)$r->plan) : '—' }}</strong>
              · <strong>{{ $bcLabel ?: '—' }}</strong>
            </div>
          </div>

          {{-- Plan --}}
          <div class="cell" data-label="Plan">
            @if($r->plan)
              <span class="badge {{ $planVal==='pro' ? 'primary' : 'warn' }}"><span class="dot"></span>{{ strtoupper($r->plan) }}</span>
            @else
              <span class="badge neutral"><span class="dot"></span>—</span>
            @endif

            <div class="meta" style="margin-top:10px">
              Billing: <strong>{{ $bsLbl }}</strong>
            </div>

            <div class="meta" style="margin-top:6px">
              Modo cobro: <strong>{{ $modoCobro ? strtoupper($modoCobro) : '—' }}</strong>
            </div>
          </div>

          {{-- Ciclo --}}
          <div class="cell" data-label="Ciclo">
            <div class="mono">{{ $bcLabel }}</div>

            <div class="meta" style="margin-top:8px">
              Próx periodo:
              <div class="mono">{{ $nextPeriodEndLbl }}</div>
            </div>
          </div>

          {{-- Próx factura --}}
          <div class="cell" data-label="Próx. factura">
            <div class="mono">{{ $nextLabel }}</div>
            <div class="meta" style="margin-top:6px">Periodo: <strong>{{ $defaultPeriod }}</strong></div>
          </div>

          {{-- Monto --}}
          <div class="cell" data-label="Monto">
            <div class="mono">{{ $amtShow }}</div>
            <div class="meta">{{ $amtMeta }}</div>

            <div class="meta" style="margin-top:10px">
              Efectivo: <strong class="mono">{{ $amtEffShow }}</strong><br>
              Custom: <strong class="mono">{{ $amtCustomShow }}</strong>
            </div>
          </div>

          {{-- Edo cuenta --}}
          <div class="cell" data-label="Edo. cuenta">
            <div class="mono" style="white-space:normal; word-break:break-word;" title="{{ $stmtMain ?: '' }}">
              {{ $stmtMain ?: '—' }}
            </div>

            <div class="meta" style="margin-top:8px">
              <strong>Statement:</strong> {{ $stmtCount ?: 0 }}
              @if($primaryStatement) · <span class="ac-ellipsis" title="{{ $primaryStatement }}">Primary ✔</span> @endif
            </div>
            <div class="meta">
              <strong>Invoice:</strong> {{ $invCount ?: 0 }}
              @if($primaryInvoice) · <span class="ac-ellipsis" title="{{ $primaryInvoice }}">Primary ✔</span> @endif
            </div>
            <div class="meta">
              <strong>General:</strong> {{ $genCount ?: 0 }}
              @if($primaryGeneral) · <span class="ac-ellipsis" title="{{ $primaryGeneral }}">Primary ✔</span> @endif
            </div>
          </div>

          {{-- Acciones --}}
          <div class="cell actions" data-label="Acciones">
            <form method="POST" action="{{ route('admin.clientes.resendEmail',$r->id) }}" class="inline">
              @csrf
              <button class="ac-btn small" type="submit" title="Reenviar verificación de correo">Reenviar</button>
            </form>

            <form method="POST" action="{{ route('admin.clientes.sendOtp',$r->id) }}" class="inline">
              @csrf
              <input type="hidden" name="channel" value="sms">
              <button class="ac-btn small" type="submit" title="Enviar OTP">OTP</button>
            </form>

            <button class="ac-btn small primary" type="button" data-open-drawer title="Abrir panel del cliente (drawer)">
              Ver
            </button>
          </div>

        </div>
      @empty
        <div class="ac-empty">Sin resultados. Ajusta filtros o limpia búsqueda.</div>
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
      <div class="links">
        {{ $rows->links() }}
      </div>
    </div>

  </div>

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

        <div class="ac-drawer-foot">
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

      <form method="POST" id="mEdit_form" action="#" class="ac-form">
        @csrf
        <div class="ac-grid">
          <div class="ac-field ac-field-wide">
            <label>Razón social</label>
            <input class="ac-input" id="mEdit_rs" name="razon_social" value="" placeholder="Razón social">
          </div>

          <div class="ac-field">
            <label>Email</label>
            <input class="ac-input" id="mEdit_email" name="email" value="" placeholder="correo@dominio.com">
          </div>

          <div class="ac-field">
            <label>Teléfono</label>
            <input class="ac-input" id="mEdit_phone" name="phone" value="" placeholder="+52...">
          </div>

          <div class="ac-field">
            <label>Plan</label>
            <select class="ac-select" id="mEdit_plan" name="plan">
              <option value="">—</option>
              <option value="free">Free</option>
              <option value="pro">Pro</option>
            </select>
          </div>

          <div class="ac-field">
            <label>Ciclo</label>
            <select class="ac-select" id="mEdit_cycle" name="billing_cycle">
              <option value="">—</option>
              <option value="monthly">Mensual</option>
              <option value="yearly">Anual</option>
            </select>
          </div>

          <div class="ac-field">
            <label>Próx. factura (YYYY-MM-DD)</label>
            <input class="ac-input" id="mEdit_next" name="next_invoice_date" type="date" value="">
          </div>

          <div class="ac-field">
            <label>Monto personalizado (MXN)</label>
            <input class="ac-input" id="mEdit_custom" name="custom_amount_mxn" inputmode="decimal" placeholder="0.00">
            <div class="ac-hint">Si se deja vacío, se usa el monto calculado del plan.</div>
          </div>

          <div class="ac-field">
            <label>Bloqueo</label>
            <div class="ac-check">
              <input type="checkbox" id="mEdit_blocked" name="is_blocked" value="1">
              <span>Cuenta bloqueada (redirige a Stripe)</span>
            </div>
          </div>
        </div>

        <div class="ac-note">
          Nota: este formulario depende de tu endpoint <code class="ac-mono">/admin/clientes/{id}/save</code>.
          Si no existe aún, el modal seguirá abriendo pero el submit no funcionará.
        </div>

        <div class="ac-form-actions">
          <button class="ac-btn" type="button" data-close-modal>Cancelar</button>
          <button class="ac-btn primary" type="submit">Guardar</button>
        </div>
      </form>
    </div>
  </div>

  {{-- =========================
      MODAL: Destinatarios
     ========================= --}}
  <div class="ac-modal" id="modalRecipients" aria-hidden="true">
    <div class="ac-modal-backdrop" data-close-modal></div>

    <div class="ac-modal-card" role="dialog" aria-modal="true" aria-label="Destinatarios">
      <div class="ac-modal-head">
        <div>
          <div class="ttl">Destinatarios</div>
          <div class="sub" id="mRec_sub">—</div>
        </div>
        <button class="x" type="button" data-close-modal aria-label="Cerrar">✕</button>
      </div>

      <div class="ac-note" id="mRec_missing" hidden>
        No se detectó ruta para guardar destinatarios (recip_url). Revisa que exista la route
        <code class="ac-mono">admin.clientes.recipientsUpsert</code>.
      </div>

      <div class="ac-tabs" data-tabs>
        <div class="ac-tabbar">
          <button type="button" class="ac-tab active" aria-selected="true" data-tab="tabRecStmt">Estado de cuenta</button>
          <button type="button" class="ac-tab" aria-selected="false" data-tab="tabRecInv">Factura</button>
          <button type="button" class="ac-tab" aria-selected="false" data-tab="tabRecGen">General</button>
        </div>

        {{-- Estado de cuenta --}}
        <div class="ac-tabpane show" id="tabRecStmt">
          <form method="POST" id="mRec_form_statement" action="#" class="ac-form">
            @csrf
            <div class="ac-grid">
              <div class="ac-field ac-field-wide">
                <label>Destinatarios (CSV)</label>
                <textarea class="ac-textarea" id="mRec_stmt_list" name="recipients" placeholder="correo1@dominio.com, correo2@dominio.com"></textarea>
                <div class="ac-hint">Separados por coma. Se normaliza a minúsculas.</div>
              </div>
              <div class="ac-field ac-field-wide">
                <label>Primary</label>
                <input class="ac-input" id="mRec_stmt_primary" name="primary" placeholder="correo@dominio.com">
              </div>

              <input type="hidden" name="kind" value="statement">
              <input type="hidden" name="active" value="1">
            </div>

            <div class="ac-form-actions">
              <button class="ac-btn" type="button" data-close-modal>Cancelar</button>
              <button class="ac-btn primary" type="submit">Guardar</button>
            </div>
          </form>
        </div>

        {{-- Factura --}}
        <div class="ac-tabpane" id="tabRecInv" hidden>
          <form method="POST" id="mRec_form_invoice" action="#" class="ac-form">
            @csrf
            <div class="ac-grid">
              <div class="ac-field ac-field-wide">
                <label>Destinatarios (CSV)</label>
                <textarea class="ac-textarea" id="mRec_inv_list" name="recipients" placeholder="correo1@dominio.com, correo2@dominio.com"></textarea>
              </div>
              <div class="ac-field ac-field-wide">
                <label>Primary</label>
                <input class="ac-input" id="mRec_inv_primary" name="primary" placeholder="correo@dominio.com">
              </div>

              <input type="hidden" name="kind" value="invoice">
              <input type="hidden" name="active" value="1">
            </div>

            <div class="ac-form-actions">
              <button class="ac-btn" type="button" data-close-modal>Cancelar</button>
              <button class="ac-btn primary" type="submit">Guardar</button>
            </div>
          </form>
        </div>

        {{-- General --}}
        <div class="ac-tabpane" id="tabRecGen" hidden>
          <form method="POST" id="mRec_form_general" action="#" class="ac-form">
            @csrf
            <div class="ac-grid">
              <div class="ac-field ac-field-wide">
                <label>Destinatarios (CSV)</label>
                <textarea class="ac-textarea" id="mRec_gen_list" name="recipients" placeholder="correo1@dominio.com, correo2@dominio.com"></textarea>
              </div>
              <div class="ac-field ac-field-wide">
                <label>Primary</label>
                <input class="ac-input" id="mRec_gen_primary" name="primary" placeholder="correo@dominio.com">
              </div>

              <input type="hidden" name="kind" value="general">
              <input type="hidden" name="active" value="1">
            </div>

            <div class="ac-form-actions">
              <button class="ac-btn" type="button" data-close-modal>Cancelar</button>
              <button class="ac-btn primary" type="submit">Guardar</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  {{-- =========================
      MODAL: Credenciales
     ========================= --}}
  <div class="ac-modal" id="modalCreds" aria-hidden="true">
    <div class="ac-modal-backdrop" data-close-modal></div>

    <div class="ac-modal-card" role="dialog" aria-modal="true" aria-label="Credenciales">
      <div class="ac-modal-head">
        <div>
          <div class="ttl">Credenciales</div>
          <div class="sub" id="mCred_sub">—</div>
        </div>
        <button class="x" type="button" data-close-modal aria-label="Cerrar">✕</button>
      </div>

      <div class="ac-credgrid">
        <div class="ac-cred">
          <div class="k">RFC</div>
          <div class="v"><span class="ac-mono" id="mCred_rfc">—</span></div>
        </div>

        <div class="ac-cred">
          <div class="k">OTP</div>
          <div class="v"><span class="ac-mono" id="mCred_otp">—</span></div>
        </div>

        <div class="ac-cred ac-cred-wide">
          <div class="k">Token / URL</div>
          <div class="v">
            <span class="ac-mono" id="mCred_tok">—</span>
            <div class="meta" id="mCred_tok_exp" style="margin-top:6px">—</div>
          </div>
          <div class="a" id="mCred_tok_actions" hidden>
            <a class="ac-btn small" id="mCred_tok_open" href="#" target="_blank" rel="noopener">Abrir</a>
            <button class="ac-btn small" type="button" data-copy="#mCred_tok">Copiar</button>
          </div>
        </div>

        {{-- ✅ Enviar credenciales por correo --}}
        <div class="ac-cred ac-cred-wide">
          <div class="k">Enviar credenciales por correo</div>
          <div class="v">
            Envía <strong>usuario (RFC)</strong>, <strong>contraseña</strong> y <strong>liga de acceso</strong> a uno o varios correos.
          </div>

          <div class="ac-note" style="margin-top:10px">
            Logo usado en la plantilla: <code class="ac-mono">{{ $brandLogoUrl }}</code>
          </div>

          <form method="POST" id="mCred_form_email_creds" action="#" class="ac-form" style="margin-top:10px">
            @csrf
            <div class="ac-grid">
              <div class="ac-field ac-field-wide">
                <label>Para (CSV)</label>
                <textarea class="ac-textarea" id="mCred_to" name="to" placeholder="correo1@dominio.com, correo2@dominio.com"></textarea>
                <div class="ac-hint">Separados por coma. Se normaliza a minúsculas.</div>
              </div>

              {{-- payload hidden --}}
              <input type="hidden" name="usuario" id="mCred_hidden_user" value="">
              <input type="hidden" name="password" id="mCred_hidden_pass" value="">
              <input type="hidden" name="access_url" id="mCred_hidden_access" value="">
              <input type="hidden" name="rfc" id="mCred_hidden_rfc" value="">
              <input type="hidden" name="rs" id="mCred_hidden_rs" value="">
              <input type="hidden" name="logo_url" value="{{ $brandLogoUrl }}">

              <div class="ac-form-actions" style="margin-top:0">
                <button class="ac-btn primary" type="submit" onclick="return confirm('¿Enviar credenciales por correo?')">Enviar</button>
              </div>
            </div>
          </form>

          <div class="ac-note" id="mCred_email_creds_missing" hidden style="margin-top:10px">
            No se detectó ruta para enviar credenciales. Se esperaba:
            <code class="ac-mono">admin.clientes.emailCreds</code> o <code class="ac-mono">admin.clientes.emailCredentials</code>.
          </div>
        </div>

        <div class="ac-cred ac-cred-wide">
          <div class="k">Forzar verificaciones</div>
          <div class="v">Útil para soporte cuando el cliente ya confirmó por otro canal.</div>
          <div class="a">
            <form method="POST" id="mCred_form_force_email" action="#" class="inline" onsubmit="return confirm('¿Forzar verificación de correo?')">
              @csrf
              <button class="ac-btn small" type="submit">Forzar correo</button>
            </form>

            <form method="POST" id="mCred_form_force_phone" action="#" class="inline" onsubmit="return confirm('¿Forzar verificación de teléfono?')">
              @csrf
              <button class="ac-btn small" type="submit">Forzar teléfono</button>
            </form>
          </div>

          <div class="ac-note" id="mCred_force_phone_missing" hidden>
            No existe endpoint para force-phone.
          </div>
        </div>
      </div>

      <div class="ac-form-actions">
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

</div>
@endsection

@push('scripts')
  <script src="{{ asset('assets/admin/js/admin-clientes.js') }}?v=13.2.0"></script>

  {{-- ✅ Hook para: setear action del envío de credenciales + defaults + abrir modal desde botón del drawer --}}
  <script>
  (function () {
    'use strict';

    const $ = (s, sc) => (sc || document).querySelector(s);

    function txt(id){
      const el = $(id);
      return (el && (el.textContent || el.innerText) || '').trim();
    }

    function ensureActionAndPayload(){
      const drawer = $('#clientDrawer');
      const c = drawer && drawer._client ? drawer._client : null;

      const form = $('#mCred_form_email_creds');
      const missing = $('#mCred_email_creds_missing');
      if (!form) return;

      const url = c && c.email_creds_url ? String(c.email_creds_url) : '';
      if (!url || url === '#') {
        form.setAttribute('action', '#');
        if (missing) missing.hidden = false;
      } else {
        form.setAttribute('action', url);
        if (missing) missing.hidden = true;
      }

      // defaults destinatarios: statement recipients → email principal → vacío
      const to = $('#mCred_to');
      if (to) {
        const csv = c && c.recips_statement ? String(c.recips_statement || '') : '';
        const fallback = c && c.email ? String(c.email || '') : '';
        if (!to.value.trim()) to.value = (csv || fallback || '').trim();
      }

      // payload para backend
      const user = txt('#mCred_rfc') || (c && c.rfc) || '';
      const pass = txt('#mCred_otp') || (c && c.otp_code) || '';
      const access = (c && c.access_url ? String(c.access_url) : '') || (c && c.token_url ? String(c.token_url) : '') || '';

      const hu = $('#mCred_hidden_user'); if (hu) hu.value = user;
      const hp = $('#mCred_hidden_pass'); if (hp) hp.value = pass;
      const ha = $('#mCred_hidden_access'); if (ha) ha.value = access;
      const hr = $('#mCred_hidden_rfc'); if (hr) hr.value = (c && c.rfc ? String(c.rfc) : user);
      const hrs= $('#mCred_hidden_rs'); if (hrs) hrs.value = (c && c.razon_social ? String(c.razon_social) : txt('#mCred_sub'));
    }

    // Cuando se abre el modal de credenciales desde tu UI
    document.addEventListener('click', function (e) {
      const btnCreds = e.target.closest('#btnOpenCreds');
      if (!btnCreds) return;

      setTimeout(ensureActionAndPayload, 80);
    });

    // “Enviar credenciales” del drawer: abre el modal Credenciales (sin depender del submit)
    document.addEventListener('click', function (e) {
      const btn = e.target.closest('#drFormEmailCreds button');
      if (!btn) return;

      e.preventDefault();

      // click “Credenciales” para mantener el flujo/JS existente
      const openCreds = $('#btnOpenCreds');
      if (openCreds) openCreds.click();

      setTimeout(ensureActionAndPayload, 120);
    });

    // si el modal ya estaba abierto y editas campos, mantener hidden sync (seguro)
    ['input','change','keyup'].forEach(evt => {
      document.addEventListener(evt, function (e) {
        if (!e.target) return;
        if (e.target.id === 'mCred_to') return; // no afecta payload
        const modal = $('#modalCreds');
        if (modal && modal.getAttribute('aria-hidden') === 'false') {
          ensureActionAndPayload();
        }
      }, true);
    });
  })();
  </script>
@endpush
