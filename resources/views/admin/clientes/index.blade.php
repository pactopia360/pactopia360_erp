{{-- C:\wamp64\www\pactopia360_erp\resources\views\admin\clientes\index.blade.php --}}
@extends('layouts.admin')

@section('title','Clientes (accounts)')

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/admin/css/admin-clientes.css') }}?v=11.1.0">
@endpush

@section('content')
@php
  use Illuminate\Support\Facades\Route;
  use Illuminate\Support\Str;
  use Illuminate\Support\Carbon;

  $q = request('q'); $plan = request('plan'); $blocked = request('blocked');
  $s = request('sort','created_at'); $d = strtolower(request('dir','desc'))==='asc'?'asc':'desc';
  $pp = (int) request('per_page', 25);
  $toggleDir = fn($col) => ($s===$col && $d==='asc') ? 'desc' : 'asc';
  $sortUrl = fn($col) => request()->fullUrlWithQuery(['sort'=>$col,'dir'=>$toggleDir($col)]);
  $total = method_exists($rows,'total') ? $rows->total() : (is_countable($rows) ? count($rows) : null);

  $verMail = 0; $verPhone = 0;
  foreach ($rows as $x) { if(!empty($x->email_verified_at)) $verMail++; if(!empty($x->phone_verified_at)) $verPhone++; }

  // Periodo sugerido para sembrado / envío
  $defaultPeriod = now()->addMonthNoOverflow()->format('Y-m');

  $try = function(string $name, array $params = []) {
    try { return Route::has($name) ? route($name, $params) : null; } catch(\Throwable $e) {}
    return null;
  };

  $is = fn($k,$v)=> (string)request($k, '')===(string)$v;

  // helper recipients → string
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

  // helper primary detect (compatible con is_primary o primary)
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

  // helper: primer correo del listado
  $recipsFirst = function(string $csv) {
    $csv = trim($csv);
    if ($csv === '') return '';
    $parts = array_values(array_filter(array_map('trim', preg_split('/\s*,\s*/', $csv))));
    return $parts[0] ?? '';
  };

  // helper: label ciclo
  $cycleLabel = function($raw){
    $raw = strtolower(trim((string)$raw));
    if ($raw === 'monthly' || $raw === 'mensual') return 'Mensual';
    if ($raw === 'yearly'  || $raw === 'anual'   || $raw === 'annual') return 'Anual';
    return $raw !== '' ? $raw : '—';
  };

  // helper: fecha
  $dateLabel = function($raw){
    $raw = trim((string)$raw);
    if ($raw === '') return '—';
    try { return Carbon::parse($raw)->format('Y-m-d'); } catch(\Throwable $e) {}
    return $raw;
  };
@endphp

<div id="adminClientesPage" class="page-admin-clientes v-cards">
  <div class="ac-container">

    {{-- Topbar sticky --}}
    <div class="ac-topbar">
      <div class="ac-topbar-left">
        <div class="ac-title">
          <h1>Clientes</h1>
          <p class="sub">Administración de cuentas (SOT: <strong>admin.accounts</strong>)</p>
        </div>

        <div class="ac-search">
          <form method="GET" id="quickSearchForm">
            <input class="ac-input ac-input-search" name="q" value="{{ $q }}" placeholder="Buscar RFC, razón social, correo o teléfono…" aria-label="Buscar">
            <input type="hidden" name="plan" value="{{ $plan }}">
            <input type="hidden" name="blocked" value="{{ $blocked }}">
            <input type="hidden" name="sort" value="{{ $s }}">
            <input type="hidden" name="dir" value="{{ $d }}">
            <input type="hidden" name="per_page" value="{{ $pp }}">
            <button class="ac-btn primary" type="submit" title="Buscar">Buscar</button>
            @if($q)
              <a class="ac-btn ghost" href="{{ request()->fullUrlWithQuery(['q'=>'']) }}" title="Limpiar búsqueda">Limpiar</a>
            @endif
          </form>
        </div>
      </div>

      <div class="ac-topbar-right">
        <form method="POST" action="{{ route('admin.clientes.syncToClientes') }}" onsubmit="return confirm('¿Sincronizar accounts → clientes (legacy)?')">
          @csrf
          <button class="ac-btn" type="submit">Sincronizar</button>
        </form>

        <button class="ac-btn" id="btnExportCsv" type="button" title="Exportar CSV (vista actual)">Exportar CSV</button>

        @php $stmtIdx = $try('admin.billing.statements.index'); @endphp
        @if($stmtIdx)
          <a class="ac-btn primary" href="{{ $stmtIdx }}" title="Abrir módulo Estados de cuenta">Estados de cuenta</a>
        @endif
      </div>
    </div>

    {{-- KPIs compact --}}
    <div class="ac-kpis">
      <div class="ac-kpi"><strong>{{ $total ?? '—' }}</strong><span>Total</span></div>
      <div class="ac-kpi"><strong>{{ $verMail }}</strong><span>Correo verificado</span></div>
      <div class="ac-kpi"><strong>{{ $verPhone }}</strong><span>Tel verificado</span></div>
      <div class="ac-kpi"><strong>{{ now()->format('Y-m-d H:i') }}</strong><span>Corte</span></div>
      <div class="ac-kpi ac-kpi-ghost"><strong>{{ $defaultPeriod }}</strong><span>Periodo sugerido</span></div>
    </div>

    {{-- Chips + filtros colapsables --}}
    <div class="ac-toolbar">
      <div class="ac-chips">
        <a class="ac-chip {{ $is('blocked','0')?'active':'' }}" href="{{ request()->fullUrlWithQuery(['blocked'=>'0']) }}">Operando</a>
        <a class="ac-chip {{ $is('blocked','1')?'active':'' }}" href="{{ request()->fullUrlWithQuery(['blocked'=>'1']) }}">Bloqueados</a>
        <a class="ac-chip {{ $is('plan','free')?'active':'' }}" href="{{ request()->fullUrlWithQuery(['plan'=>'free']) }}">Free</a>
        <a class="ac-chip {{ $is('plan','pro')?'active':'' }}" href="{{ request()->fullUrlWithQuery(['plan'=>'pro']) }}">Pro</a>
        <a class="ac-chip ghost" href="{{ route('admin.clientes.index') }}">Limpiar</a>
      </div>

      <details class="ac-filters" id="filtersBox" {{ ($plan||$blocked||$s!=='created_at'||$d!=='desc'||$pp!==25) ? 'open' : '' }}>
        <summary class="ac-filters-summary">
          <span>Filtros avanzados</span>
          <span class="ac-meta">Orden: <strong>{{ $s }}</strong> · <strong>{{ $d }}</strong> · {{ $pp }}/pág</span>
        </summary>

        <form method="GET" id="filtersForm" class="ac-filters-form">
          <div class="ac-filters-grid">
            <div class="ac-field">
              <label>Buscar</label>
              <input class="ac-input" name="q" value="{{ $q }}" placeholder="RFC, razón social, correo o teléfono">
            </div>

            <div class="ac-field">
              <label>Plan</label>
              <input class="ac-input" name="plan" value="{{ $plan }}" placeholder="free, pro">
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
              <label>Orden</label>
              <select name="sort" class="ac-select">
                <option value="created_at" {{ $s==='created_at'?'selected':'' }}>Creado</option>
                <option value="razon_social" {{ $s==='razon_social'?'selected':'' }}>Razón social</option>
                <option value="plan" {{ $s==='plan'?'selected':'' }}>Plan</option>
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

    {{-- Listado en Cards --}}
    <div class="ac-list" id="clientesList" role="region" aria-label="Listado de clientes">
      @forelse($rows as $r)
        @php
          $RFC_FULL = strtoupper(trim((string) (data_get($r,'rfc') ?: data_get($r,'tax_id') ?: data_get($r,'id'))));
          $RFC_SLUG = Str::slug($RFC_FULL, '-');
          $created  = optional($r->created_at)->format('Y-m-d H:i') ?? '—';

          $info     = $extras[$r->id] ?? null;
          $c        = $creds[$r->id] ?? ['owner_email'=>null,'temp_pass'=>null];

          $planVal  = strtolower((string)($r->plan ?? ''));
          $bcRaw    = (string)($r->billing_cycle ?? '');
          $nextRaw  = (string)($r->next_invoice_date ?? '');

          // Fallbacks por si vienen en extras
          if (($bcRaw === '' || $bcRaw === null) && is_array($info) && !empty($info['billing_cycle'])) {
            $bcRaw = (string)$info['billing_cycle'];
          }
          if (($nextRaw === '' || $nextRaw === null) && is_array($info) && !empty($info['next_invoice_date'])) {
            $nextRaw = (string)$info['next_invoice_date'];
          }

          $bcLabel   = $cycleLabel($bcRaw);
          $nextLabel = $dateLabel($nextRaw);

          $customAmount = null;
          foreach (['custom_amount_mxn','override_amount_mxn','billing_amount_mxn','amount_mxn','precio_mxn','monto_mxn','license_amount_mxn'] as $p) {
            if (isset($r->{$p}) && $r->{$p} !== null && $r->{$p} !== '') { $customAmount = $r->{$p}; break; }
          }
          $hasCustom = ($customAmount !== null && $customAmount !== '' && is_numeric($customAmount));

          $stmtShow  = $try('admin.billing.statements.show',  ['accountId'=>$r->id, 'period'=>$defaultPeriod]);
          $stmtEmail = $try('admin.billing.statements.email', ['accountId'=>$r->id, 'period'=>$defaultPeriod]);

          $seedUrl   = $try('admin.clientes.seedStatement', ['rfc'=>$r->id]);
          $recipUrl  = $try('admin.clientes.recipientsUpsert', ['rfc'=>$r->id]);

          $rRecips = $recipients[$r->id] ?? [];
          $recipsStatement = $recipsToString($rRecips, 'statement');
          $recipsInvoice   = $recipsToString($rRecips, 'invoice');
          $recipsGeneral   = $recipsToString($rRecips, 'general');

          $primaryStatement = $recipsPrimary($rRecips, 'statement');
          $primaryInvoice   = $recipsPrimary($rRecips, 'invoice');
          $primaryGeneral   = $recipsPrimary($rRecips, 'general');

          $stmtCount = $recipsStatement !== '' ? count(explode(',', $recipsStatement)) : 0;

          // Mostrar en resumen: primary → primer correo → Sin correos
          $stmtMain = 'Sin correos';
          if ($stmtCount > 0) {
            $stmtMain = $primaryStatement ?: ($recipsFirst($recipsStatement) ?: '—');
          }
          $stmtPreview = $stmtCount > 0 ? Str::limit($recipsStatement, 46) : 'Sin correos';
          $stmtTitle = $stmtCount > 0 ? $recipsStatement : 'Sin correos configurados';

          $effective = $info['license_amount_mxn_effective'] ?? null;

          $isBlocked = ((int)$r->is_blocked===1);
          $mailOk = !empty($r->email_verified_at);
          $phoneOk = !empty($r->phone_verified_at);

          $exportEmail  = (string)($r->email ?? '');
          $exportPhone  = (string)($r->phone ?? '');
          $exportPlan   = (string)($r->plan ?? '');
          $exportCycle  = (string)($r->billing_cycle ?? '');
          $exportNext   = (string)($r->next_invoice_date ?? '');
          $exportCustom = $hasCustom ? (string)$customAmount : '';

          // ✅ FIX: no usar @json dentro de atributo con comillas.
          $exportPayload = [
            "RFC" => $RFC_FULL,
            "RazonSocial" => (string)($r->razon_social ?? ''),
            "Email" => $exportEmail,
            "Phone" => $exportPhone,
            "Plan" => $exportPlan,
            "BillingCycle" => $exportCycle,
            "NextInvoice" => $exportNext,
            "CustomAmountMxn" => $exportCustom,
            "EmailVerif" => $mailOk ? "1" : "0",
            "PhoneVerif" => $phoneOk ? "1" : "0",
            "Blocked" => $isBlocked ? "1" : "0",
            "CreatedAt" => $created,
          ];
          $exportJson = e(json_encode($exportPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        @endphp

        <article class="ac-cardrow"
                 id="row-{{ $r->id }}"
                 data-export="{{ $exportJson }}">

          {{-- Head: resumen --}}
          <header class="ac-cardrow-head">
            <div class="ac-cardrow-main">
              <div class="ac-ident">
                <div class="ac-rfc">{{ $RFC_FULL }}</div>
                <div class="ac-subline">
                  <span class="ac-meta">Creado: <strong>{{ $created }}</strong></span>
                  <span class="ac-dotsep">·</span>
                  <span class="ac-meta">Razón social: <strong>{{ $r->razon_social ?: '—' }}</strong></span>
                </div>
              </div>

              <div class="ac-badges">
                <span class="ac-badge {{ $mailOk ? 'ok':'warn' }}"><span class="dot"></span>Correo {{ $mailOk?'✔':'pendiente' }}</span>
                <span class="ac-badge {{ $phoneOk ? 'ok':'warn' }}"><span class="dot"></span>Tel {{ $phoneOk?'✔':'pendiente' }}</span>
                <span class="ac-badge {{ $isBlocked ? 'bad':'ok' }}"><span class="dot"></span>{{ $isBlocked ? 'Bloqueado' : 'Operando' }}</span>
                @if($r->plan)
                  <span class="ac-badge {{ $planVal==='pro' ? 'primary' : 'warn' }}"><span class="dot"></span>Plan: {{ strtoupper($r->plan) }}</span>
                @else
                  <span class="ac-badge warn"><span class="dot"></span>Plan: —</span>
                @endif
              </div>
            </div>

            <div class="ac-cardrow-side">
              <div class="ac-mini">
                <div class="ac-mini-item">
                  <span class="k">Ciclo</span>
                  <span class="v">{{ $bcLabel }}</span>
                </div>
                <div class="ac-mini-item">
                  <span class="k">Próx. factura</span>
                  <span class="v">{{ $nextLabel }}</span>
                </div>

                <div class="ac-mini-item">
                  <span class="k">Edo. cuenta</span>
                  <span class="v" title="{{ $stmtTitle }}">{{ $stmtMain }}</span>
                  <div style="margin-top:2px;font-size:12px;color:var(--mut,#64748b);line-height:1.15" title="{{ $stmtTitle }}">
                    {{ $stmtPreview }}
                    @if($stmtCount>0)
                      <span style="opacity:.85">({{ $stmtCount }})</span>
                    @endif
                  </div>
                </div>

                @if($effective !== null && is_numeric($effective) && (float)$effective > 0)
                  <div class="ac-mini-item">
                    <span class="k">Licencia efectiva</span>
                    <span class="v">${{ number_format((float)$effective, 2) }}</span>
                  </div>
                @elseif($hasCustom)
                  <div class="ac-mini-item">
                    <span class="k">Precio personalizado</span>
                    <span class="v">${{ number_format((float)$customAmount, 2) }}</span>
                  </div>
                @else
                  <div class="ac-mini-item">
                    <span class="k">Monto</span>
                    <span class="v">—</span>
                  </div>
                @endif
              </div>

              <div class="ac-quickactions">
                <form method="POST" action="{{ route('admin.clientes.resendEmail',$r->id) }}">
                  @csrf
                  <button class="ac-btn small" type="submit" title="Reenviar verificación de correo">Reenviar correo</button>
                </form>

                <form method="POST" action="{{ route('admin.clientes.sendOtp',$r->id) }}">
                  @csrf
                  <input type="hidden" name="channel" value="sms">
                  <button class="ac-btn small" type="submit" title="Enviar OTP">Enviar OTP</button>
                </form>

                <button class="ac-btn small primary" type="button"
                        data-toggle="details"
                        data-target="#det-{{ $r->id }}"
                        aria-expanded="false">
                  Ver detalles
                </button>
              </div>
            </div>
          </header>

          {{-- Body: secciones --}}
          <section class="ac-cardrow-body" id="det-{{ $r->id }}" hidden>
            <div class="ac-sections">

              {{-- A) Edición rápida --}}
              <div class="ac-section">
                <div class="ac-section-head">
                  <h3>Edición rápida</h3>
                  <div class="ac-section-meta">Actualiza datos base + plan/ciclo + bloqueo</div>
                </div>

                <form method="POST" action="{{ route('admin.clientes.save',$r->id) }}" class="ac-form">
                  @csrf

                  <div class="ac-grid">
                    <div class="ac-field">
                      <label>Razón social</label>
                      <input class="ac-input" name="razon_social" value="{{ $r->razon_social }}" placeholder="Razón social">
                    </div>

                    <div class="ac-field">
                      <label>Correo</label>
                      <input class="ac-input" name="email" value="{{ $r->email }}" placeholder="correo@cliente.com">
                    </div>

                    <div class="ac-field">
                      <label>Teléfono</label>
                      <input class="ac-input" name="phone" value="{{ $r->phone }}" placeholder="+52…">
                    </div>

                    <div class="ac-field">
                      <label>Plan</label>
                      <select class="ac-select" name="plan">
                        <option value="">— Plan —</option>
                        <option value="free" {{ $planVal==='free'?'selected':'' }}>Free</option>
                        <option value="pro"  {{ $planVal==='pro'?'selected':'' }}>Pro</option>
                      </select>

                      <div class="ac-planchips">
                        <button type="button" class="ac-btn tiny" data-plan-preset="free" data-cycle="" data-days="0">Free</button>
                        <button type="button" class="ac-btn tiny primary" data-plan-preset="pro" data-cycle="monthly" data-days="30">Pro mensual</button>
                        <button type="button" class="ac-btn tiny primary" data-plan-preset="pro" data-cycle="yearly" data-days="365">Pro anual</button>
                      </div>
                    </div>

                    <div class="ac-field">
                      <label>Ciclo de cobro</label>
                      <select name="billing_cycle" class="ac-select">
                        <option value="">— Ciclo —</option>
                        <option value="monthly" {{ strtolower((string)($r->billing_cycle ?? ''))==='monthly'?'selected':'' }}>Mensual</option>
                        <option value="yearly"  {{ strtolower((string)($r->billing_cycle ?? ''))==='yearly'?'selected':'' }}>Anual</option>
                      </select>
                    </div>

                    <div class="ac-field">
                      <label>Próxima factura</label>
                      <input class="ac-input" type="date" name="next_invoice_date" value="{{ (string)($r->next_invoice_date ?? '') }}">
                    </div>

                    <div class="ac-field ac-field-wide">
                      <label>Precio personalizado (MXN)</label>
                      <input class="ac-input" name="custom_amount_mxn" inputmode="decimal"
                             value="{{ $customAmount ?? '' }}"
                             placeholder="Ej. 999.00">
                      <div class="ac-hint">Si lo capturas aquí, el backend debe persistirlo (meta/override) para que el Edo. Cuenta use este monto.</div>
                    </div>

                    <div class="ac-field ac-field-wide">
                      <label class="ac-check">
                        <input type="checkbox" name="is_blocked" value="1" {{ $isBlocked ? 'checked':'' }}>
                        Bloqueado (redirige a Stripe al login según tu regla PRO)
                      </label>
                    </div>
                  </div>

                  <div class="ac-form-actions">
                    <button class="ac-btn primary" type="submit">Guardar cambios</button>
                  </div>
                </form>
              </div>

              {{-- B) Destinatarios --}}
              <div class="ac-section">
                <div class="ac-section-head">
                  <h3>Destinatarios</h3>
                  <div class="ac-section-meta">account_recipients: Edo. cuenta / Facturas / General</div>
                </div>

                @if($recipUrl)
                  <div class="ac-tabs" data-tabs>
                    <div class="ac-tabbar" role="tablist" aria-label="Destinatarios">
                      <button class="ac-tab active" type="button" role="tab" aria-selected="true" data-tab="statement-{{ $r->id }}">Edo. cuenta</button>
                      <button class="ac-tab" type="button" role="tab" aria-selected="false" data-tab="invoice-{{ $r->id }}">Facturas</button>
                      <button class="ac-tab" type="button" role="tab" aria-selected="false" data-tab="general-{{ $r->id }}">General</button>
                    </div>

                    <div class="ac-tabpanes">
                      {{-- statement --}}
                      <div class="ac-tabpane show" id="statement-{{ $r->id }}" role="tabpanel">
                        <form method="POST" action="{{ $recipUrl }}" class="ac-form">
                          @csrf
                          <input type="hidden" name="kind" value="statement">

                          <div class="ac-grid">
                            <div class="ac-field ac-field-wide">
                              <label>Correos (separa por coma, ; o salto de línea)</label>
                              <textarea name="recipients" class="ac-textarea" placeholder="pagos@cliente.com, admin@cliente.com">{{ $recipsStatement }}</textarea>
                            </div>

                            <div class="ac-field">
                              <label>Primary (opcional)</label>
                              <input class="ac-input" name="primary" value="{{ $primaryStatement }}" placeholder="primary@cliente.com">
                            </div>

                            <div class="ac-field">
                              <label>Activo</label>
                              <select class="ac-select" name="active">
                                <option value="1" selected>Activo</option>
                                <option value="0">Inactivo</option>
                              </select>
                            </div>
                          </div>

                          <div class="ac-form-actions">
                            <button class="ac-btn primary" type="submit">Guardar Edo. cuenta</button>
                          </div>
                        </form>
                      </div>

                      {{-- invoice --}}
                      <div class="ac-tabpane" id="invoice-{{ $r->id }}" role="tabpanel" hidden>
                        <form method="POST" action="{{ $recipUrl }}" class="ac-form">
                          @csrf
                          <input type="hidden" name="kind" value="invoice">

                          <div class="ac-grid">
                            <div class="ac-field ac-field-wide">
                              <label>Correos</label>
                              <textarea name="recipients" class="ac-textarea" placeholder="facturas@cliente.com">{{ $recipsInvoice }}</textarea>
                            </div>

                            <div class="ac-field">
                              <label>Primary (opcional)</label>
                              <input class="ac-input" name="primary" value="{{ $primaryInvoice }}" placeholder="primary@cliente.com">
                            </div>

                            <div class="ac-field">
                              <label>Activo</label>
                              <select class="ac-select" name="active">
                                <option value="1" selected>Activo</option>
                                <option value="0">Inactivo</option>
                              </select>
                            </div>
                          </div>

                          <div class="ac-form-actions">
                            <button class="ac-btn primary" type="submit">Guardar Facturas</button>
                          </div>
                        </form>
                      </div>

                      {{-- general --}}
                      <div class="ac-tabpane" id="general-{{ $r->id }}" role="tabpanel" hidden>
                        <form method="POST" action="{{ $recipUrl }}" class="ac-form">
                          @csrf
                          <input type="hidden" name="kind" value="general">

                          <div class="ac-grid">
                            <div class="ac-field ac-field-wide">
                              <label>Correos</label>
                              <textarea name="recipients" class="ac-textarea" placeholder="admin@cliente.com">{{ $recipsGeneral }}</textarea>
                            </div>

                            <div class="ac-field">
                              <label>Primary (opcional)</label>
                              <input class="ac-input" name="primary" value="{{ $primaryGeneral }}" placeholder="primary@cliente.com">
                            </div>

                            <div class="ac-field">
                              <label>Activo</label>
                              <select class="ac-select" name="active">
                                <option value="1" selected>Activo</option>
                                <option value="0">Inactivo</option>
                              </select>
                            </div>
                          </div>

                          <div class="ac-form-actions">
                            <button class="ac-btn primary" type="submit">Guardar General</button>
                          </div>
                        </form>
                      </div>
                    </div>
                  </div>
                @else
                  <div class="ac-missing">
                    <strong>Falta ruta:</strong> <code class="ac-mono">admin.clientes.recipientsUpsert</code>
                  </div>
                @endif
              </div>

              {{-- C) Credenciales + Verificación --}}
              <div class="ac-section">
                <div class="ac-section-head">
                  <h3>Credenciales & Verificaciones</h3>
                  <div class="ac-section-meta">Token email / OTP / password temporal</div>
                </div>

                @php
                  $tokenUrl = $info && !empty($info['email_token'])
                    ? route('cliente.verify.email.token', ['token'=>$info['email_token']])
                    : null;

                  $RFC_KEY_U = strtoupper($RFC_FULL);
                  $RFC_KEY_L = strtolower($RFC_FULL);

                  $ownerEmail = session("tmp_user.$RFC_KEY_U")
                                ?? session("tmp_user.$RFC_KEY_L")
                                ?? ($c['owner_email'] ?? $r->email ?? null);

                  $tempPass   = session("tmp_pass.$RFC_KEY_U")
                                ?? session("tmp_pass.$RFC_KEY_L")
                                ?? cache()->get("tmp_pass.$RFC_KEY_U")
                                ?? cache()->get("tmp_pass.$RFC_KEY_L")
                                ?? ($c['temp_pass'] ?? null);

                  if (empty($tempPass)) {
                    $tl2 = session('tmp_last');
                    if (is_array($tl2) && !empty($tl2['pass']) && strcasecmp($tl2['key'] ?? '', $RFC_KEY_U) === 0) {
                      $tempPass   = $tl2['pass'];
                      $ownerEmail = $ownerEmail ?: ($tl2['user'] ?? null);
                    }
                  }
                @endphp

                <div class="ac-credgrid">
                  <div class="ac-cred">
                    <div class="k">RFC</div>
                    <div class="v"><code class="ac-mono" id="rfc-{{ $RFC_SLUG }}">{{ $RFC_FULL }}</code></div>
                    <div class="a">
                      <button class="ac-btn tiny" type="button" data-copy="#rfc-{{ $RFC_SLUG }}">Copiar</button>
                    </div>
                  </div>

                  <div class="ac-cred">
                    <div class="k">Usuario</div>
                    <div class="v"><code class="ac-mono" id="user-{{ $RFC_SLUG }}">{{ $ownerEmail ?: '—' }}</code></div>
                    <div class="a">
                      <button class="ac-btn tiny" type="button" data-copy="#user-{{ $RFC_SLUG }}">Copiar</button>
                    </div>
                  </div>

                  <div class="ac-cred">
                    <div class="k">Contraseña temporal</div>
                    <div class="v">
                      @if(!empty($tempPass))
                        <code class="ac-mono" id="pass-{{ $RFC_SLUG }}">{{ $tempPass }}</code>
                      @else
                        <span class="ac-meta">—</span>
                      @endif
                    </div>
                    <div class="a">
                      @if(!empty($tempPass))
                        <button class="ac-btn tiny" type="button" data-copy="#pass-{{ $RFC_SLUG }}">Copiar</button>
                      @endif
                      <a class="ac-btn tiny ghost" href="{{ route('cliente.login') }}" target="_blank" rel="noopener">Probar login</a>
                    </div>
                  </div>

                  <div class="ac-cred ac-cred-wide">
                    <div class="k">Enlace de validación de correo</div>
                    <div class="v">
                      @if($tokenUrl)
                        <code class="ac-mono" id="tok-{{ $r->id }}">{{ $tokenUrl }}</code>
                        <div class="ac-meta" style="margin-top:6px">Expira: {{ $info['email_expires_at'] ?? '—' }}</div>
                      @else
                        <span class="ac-meta">Sin token vigente.</span>
                      @endif
                    </div>
                    <div class="a">
                      @if($tokenUrl)
                        <button class="ac-btn tiny" type="button" data-copy="#tok-{{ $r->id }}">Copiar</button>
                        <a class="ac-btn tiny primary" href="{{ $tokenUrl }}" target="_blank" rel="noopener">Abrir</a>
                      @endif
                    </div>
                  </div>
                </div>

                <div class="ac-divider"></div>

                <div class="ac-row-actions">
                  <form method="POST" action="{{ route('admin.clientes.resetPassword',$r->id) }}" onsubmit="return confirm('¿Generar contraseña temporal para el OWNER?')">
                    @csrf
                    <button class="ac-btn" type="submit">Resetear contraseña</button>
                  </form>

                  <form method="POST" action="{{ route('admin.clientes.emailCredentials',$r->id) }}" onsubmit="return confirm('¿Enviar credenciales por correo?')">
                    @csrf
                    <button class="ac-btn primary" type="submit">Enviar credenciales</button>
                  </form>

                  <form method="POST" action="{{ route('admin.clientes.forceEmail',$r->id) }}" onsubmit="return confirm('¿Marcar correo como verificado?')">
                    @csrf
                    <button class="ac-btn" type="submit">Forzar correo ✔</button>
                  </form>

                  <form method="POST" action="{{ route('admin.clientes.forcePhone',$r->id) }}" onsubmit="return confirm('¿Marcar teléfono como verificado?')">
                    @csrf
                    <button class="ac-btn" type="submit">Forzar tel ✔</button>
                  </form>

                  <form method="POST" action="{{ route('admin.clientes.impersonate',$r->id) }}" onsubmit="return confirm('Vas a iniciar sesión como el cliente. ¿Continuar?')">
                    @csrf
                    <button class="ac-btn" type="submit">Entrar como cliente</button>
                  </form>
                </div>

                <div class="ac-meta" style="margin-top:10px">
                  Último envío credenciales: <strong>{{ $info['cred_last_sent_at'] ?? '—' }}</strong>
                  · OTP: <strong>{{ !empty($info['otp_code']) ? ($info['otp_code'].' ('.strtoupper($info['otp_channel'] ?? '—').')') : '—' }}</strong>
                </div>
              </div>

              {{-- D) Billing / Estado de cuenta --}}
              <div class="ac-section">
                <div class="ac-section-head">
                  <h3>Billing / Estado de cuenta</h3>
                  <div class="ac-section-meta">Periodo: <strong>{{ $defaultPeriod }}</strong></div>
                </div>

                <div class="ac-note">
                  Checklist: (1) destinatarios, (2) sembrado, (3) show, (4) email.
                </div>

                <div class="ac-grid">
                  <div class="ac-field">
                    <label>Sembrar Edo. cuenta</label>
                    @if($seedUrl)
                      <form method="POST" action="{{ $seedUrl }}" onsubmit="return confirm('¿Sembrar/asegurar estado de cuenta {{ $defaultPeriod }} para este cliente?')">
                        @csrf
                        <input type="hidden" name="period" value="{{ $defaultPeriod }}">
                        <button class="ac-btn" type="submit">Sembrar</button>
                      </form>
                    @else
                      <div class="ac-missing"><strong>Falta ruta:</strong> <code class="ac-mono">admin.clientes.seedStatement</code></div>
                    @endif
                  </div>

                  <div class="ac-field">
                    <label>Abrir Edo. cuenta (Admin)</label>
                    @if($stmtShow)
                      <a class="ac-btn primary" href="{{ $stmtShow }}" target="_blank" rel="noopener">Abrir</a>
                    @else
                      <div class="ac-missing"><strong>Falta ruta:</strong> <code class="ac-mono">admin.billing.statements.show</code></div>
                    @endif
                  </div>

                  <div class="ac-field ac-field-wide">
                    <label>Enviar Edo. cuenta por correo</label>
                    @if($stmtEmail)
                      <form method="POST" action="{{ $stmtEmail }}">
                        @csrf
                        <div class="ac-inline">
                          <input class="ac-input" name="to" placeholder="a@a.com,b@b.com (opcional)">
                          <button class="ac-btn primary" type="submit" onclick="return confirm('¿Enviar estado de cuenta {{ $defaultPeriod }} por correo?')">Enviar</button>
                        </div>
                        <div class="ac-hint">Si “to” va vacío, el backend debe resolver con recipients configurados.</div>
                      </form>
                    @else
                      <div class="ac-missing"><strong>Falta ruta:</strong> <code class="ac-mono">admin.billing.statements.email</code></div>
                    @endif
                  </div>
                </div>
              </div>

            </div>
          </section>

        </article>
      @empty
        <div class="ac-empty">
          Sin resultados. Ajusta filtros o limpia búsqueda.
        </div>
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
</div>
@endsection

@push('scripts')
<script>
(function(){
  const $ = (s,sc)=> (sc||document).querySelector(s);
  const $$ = (s,sc)=> Array.from((sc||document).querySelectorAll(s));

  // QuickSearch: ESC limpia
  const qf = $('#quickSearchForm');
  if(qf){
    const q = qf.querySelector('input[name="q"]');
    if(q){
      q.addEventListener('keydown', e=>{
        if(e.key==='Escape'){
          q.value='';
          qf.submit();
        }
      });
    }
  }

  // Filtros: autosubmit selects
  const form = $('#filtersForm');
  if(form){
    form.querySelectorAll('select').forEach(sel=> sel.addEventListener('change', ()=> form.submit()));
  }

  // Toggle details + copiar + presets plan + tabs
  document.addEventListener('click', (e)=>{
    const tgl = e.target.closest('[data-toggle="details"]');
    if(tgl){
      const sel = tgl.getAttribute('data-target');
      const panel = sel ? document.querySelector(sel) : null;
      if(!panel) return;

      const isHidden = panel.hasAttribute('hidden');

      // Cierra otros
      $$('.ac-cardrow-body:not([hidden])').forEach(p=>{
        if(p!==panel){
          p.setAttribute('hidden','hidden');
          const btn = document.querySelector(`[data-toggle="details"][data-target="#${p.id}"]`);
          if(btn) btn.setAttribute('aria-expanded','false');
        }
      });

      if(isHidden){
        panel.removeAttribute('hidden');
        tgl.setAttribute('aria-expanded','true');
        setTimeout(()=> panel.scrollIntoView({behavior:'smooth', block:'nearest'}), 40);
      }else{
        panel.setAttribute('hidden','hidden');
        tgl.setAttribute('aria-expanded','false');
      }
    }

    // Copiar
    const copyBtn = e.target.closest('[data-copy]');
    if (copyBtn) {
      const sel = copyBtn.getAttribute('data-copy');
      const node = document.querySelector(sel);
      if (!node) return;
      const text = (node.innerText || node.textContent || '').trim();
      navigator.clipboard.writeText(text).then(()=>{
        const prev = copyBtn.textContent;
        copyBtn.textContent = 'Copiado';
        copyBtn.disabled = true;
        setTimeout(()=>{ copyBtn.disabled=false; copyBtn.textContent=prev; }, 700);
      });
    }

    // Presets de plan
    const presetBtn = e.target.closest('[data-plan-preset]');
    if (presetBtn) {
      e.preventDefault();
      const plan  = presetBtn.getAttribute('data-plan-preset') || '';
      const cycle = presetBtn.getAttribute('data-cycle') || '';
      const days  = parseInt(presetBtn.getAttribute('data-days') || '0', 10);

      const card = presetBtn.closest('.ac-cardrow');
      if (!card) return;
      const formRow = card.querySelector('form.ac-form');
      if (!formRow) return;

      const planField = formRow.querySelector('select[name="plan"], input[name="plan"]');
      if (planField) planField.value = plan;

      const cycleSel = formRow.querySelector('select[name="billing_cycle"]');
      if (cycleSel) cycleSel.value = cycle;

      const nextInput = formRow.querySelector('input[name="next_invoice_date"]');
      if (nextInput) {
        if (days > 0) {
          const d = new Date();
          d.setDate(d.getDate() + days);
          nextInput.value = d.toISOString().slice(0,10);
        } else {
          nextInput.value = '';
        }
      }
    }

    // Tabs
    const tabBtn = e.target.closest('.ac-tab[data-tab]');
    if(tabBtn){
      const tabs = tabBtn.closest('[data-tabs]');
      if(!tabs) return;

      tabs.querySelectorAll('.ac-tab').forEach(b=>{
        b.classList.remove('active');
        b.setAttribute('aria-selected','false');
      });
      tabBtn.classList.add('active');
      tabBtn.setAttribute('aria-selected','true');

      const targetId = tabBtn.getAttribute('data-tab');
      tabs.querySelectorAll('.ac-tabpane').forEach(p=>{
        p.classList.remove('show');
        p.setAttribute('hidden','hidden');
      });
      const pane = targetId ? document.getElementById(targetId) : null;
      if(pane){
        pane.classList.add('show');
        pane.removeAttribute('hidden');
      }
    }
  });

  // Export CSV desde data-export
  $('#btnExportCsv')?.addEventListener('click', ()=>{
    const head = ['RFC','RazonSocial','Email','Phone','Plan','BillingCycle','NextInvoice','CustomAmountMxn','EmailVerif','PhoneVerif','Blocked','CreatedAt'];
    const lines = [];
    lines.push(head.join(','));

    $$('.ac-cardrow[data-export]').forEach(card=>{
      let obj = {};
      try { obj = JSON.parse(card.getAttribute('data-export') || '{}'); } catch(e) {}
      const row = head.map(k => (obj[k] ?? '').toString());
      lines.push(row.map(t=>{
        return /[",\n]/.test(t) ? `"${t.replace(/"/g,'""')}"` : t;
      }).join(','));
    });

    const blob = new Blob([lines.join('\n')],{type:'text/csv;charset=utf-8;'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = 'clientes_export.csv';
    document.body.appendChild(a); a.click();
    a.remove(); URL.revokeObjectURL(url);
  });
})();
</script>
@endpush
