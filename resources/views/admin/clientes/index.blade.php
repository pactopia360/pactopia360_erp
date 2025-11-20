@extends('layouts.admin')

@section('title','Clientes (accounts)')

@push('styles')
<style id="css-admin-clientes-v9">
:root{
  --ink:#0f172a; --mut:#64748b; --mut2:#94a3b8;
  --line:#e5e7eb; --line2:#f1f5f9; --ring:0 0 0 2px rgba(124,58,237,.18);
  --pri:#7c3aed; --pri2:#6d28d9; --ok:#16a34a; --bad:#ef4444; --warn:#f59e0b; --info:#0ea5e9;
  --card:#ffffff; --bg:#fafafa; --hover:#f8fafc;
  --radius:10px; --radius-lg:12px;
  --h-xs:26px; --h-sm:32px; --h-md:38px;
  --pad-xs:4px 8px; --pad-sm:6px 10px; --pad-md:8px 12px;
  --fs-12:12px; --fs-13:13px; --fs-14:14px;
  --safe-b: env(safe-area-inset-bottom, 0px);
}
@media (prefers-color-scheme: dark){
  :root{
    --ink:#e5e7eb; --mut:#9aa3b2; --mut2:#7c8799;
    --line:#334155; --line2:#1f2937;
    --ring:0 0 0 2px rgba(139,92,246,.28);
    --pri:#8b5cf6; --pri2:#7c3aed;
    --card:#0b1220; --bg:#0b1120; --hover:#0f172a;
  }
}
*{box-sizing:border-box}
.page{font:var(--fs-14) system-ui,Segoe UI,Roboto,Helvetica,Arial;color:var(--ink);background:var(--bg); padding-bottom:var(--safe-b)}
h1{font-size:22px;margin:8px 0 2px;font-weight:800;letter-spacing:-.2px}
.sub{color:var(--mut);margin-bottom:8px}

/* ===== Layout ===== */
.header{display:grid;grid-template-columns:1fr auto;gap:10px;align-items:center;margin-bottom:10px}
.actions{display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end}

.kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:8px;margin-bottom:10px}
.kpi{background:var(--card);border:1px solid var(--line);border-radius:var(--radius-lg);padding:10px 12px}
.kpi strong{font-size:18px;font-weight:900}
.kpi span{display:block;font-size:var(--fs-12);color:var(--mut)}

.chips{display:flex;gap:6px;flex-wrap:wrap;margin:6px 0 12px}
.chip{border:1px solid var(--line);border-radius:999px;background:var(--card);padding:4px 8px;font-size:var(--fs-12);font-weight:700;text-decoration:none;color:inherit}
.chip.active{background:linear-gradient(180deg,var(--pri),var(--pri2));color:#fff;border:0}

.tools{background:var(--card);border:1px solid var(--line);border-radius:var(--radius-lg);padding:8px;margin-bottom:10px; position:sticky; top:calc(var(--header-h,56px) + 6px); z-index:5; backdrop-filter:saturate(120%) blur(3px)}
.toolbar{display:flex;gap:6px;align-items:center;flex-wrap:wrap}
.toolbar .spacer{flex:1 1 auto}

/* ===== Controls ===== */
.btn{border:1px solid var(--line);background:var(--card);border-radius:var(--radius);padding:var(--pad-sm);height:var(--h-sm);display:inline-flex;align-items:center;gap:6px;font-weight:700;cursor:pointer}
.btn:hover{filter:brightness(1.02)} .btn:focus{outline:none;box-shadow:var(--ring)}
.btnp{background:linear-gradient(180deg,var(--pri),var(--pri2));color:#fff;border:0}
.btng{background:transparent;border:0;color:var(--mut);font-weight:700; text-decoration:none}
.input,select{border:1px solid var(--line);background:var(--card);border-radius:var(--radius);padding:0 10px;height:var(--h-sm);font-size:var(--fs-14)}
.input:focus,select:focus{outline:none;box-shadow:var(--ring)}
.search{min-width:260px}

/* mini botones para presets de plan */
.btn-xs{height:var(--h-xs);padding:2px 8px;font-size:11px}
.plan-quick{display:grid;grid-template-columns:minmax(130px,170px) 1fr;gap:6px;align-items:center;margin-top:4px}
.plan-chips{display:flex;flex-wrap:wrap;gap:4px}

/* ===== Alerts ===== */
.alerts{display:grid;gap:8px;margin:6px 0 10px}
.alert{background:var(--card);border:1px solid var(--line);border-radius:var(--radius-lg);padding:8px 10px}
.alert strong{font-weight:800}
.alert.ok{border-color:rgba(22,163,74,.35)}
.alert.bad{border-color:rgba(239,68,68,.35)}
.alert.warn{border-color:rgba(245,158,11,.35)}
.alert.info{border-color:rgba(14,165,233,.35)}

/* ===== Table ===== */
.tablewrap{
  background:var(--card);border:1px solid var(--line);border-radius:var(--radius-lg);
  overflow:auto; -webkit-overflow-scrolling:touch; position:relative;
  box-shadow:0 1px 0 var(--line2) inset;
}
.table{width:100%;border-collapse:separate;border-spacing:0;min-width:980px}
.table thead th{background:var(--card);padding:8px 10px;font-size:var(--fs-12);color:var(--mut2);border-bottom:1px solid var(--line);text-align:left;white-space:nowrap; position:sticky; top:0; z-index:3}
.table tbody td{padding:8px 10px;border-bottom:1px solid var(--line2);vertical-align:top; background:var(--card)}
.table tbody tr:hover td{background:var(--hover)}
.rowhead{display:flex;gap:8px;align-items:flex-start}
.expbtn{border:1px dashed var(--line);background:transparent;border-radius:8px;padding:2px 6px;height:var(--h-xs);font-size:var(--fs-12);cursor:pointer}
.rfc{font-weight:800;font-size:14px;letter-spacing:.2px}
.meta{font-size:var(--fs-12);color:var(--mut)}

/* Primera columna sticky */
.table thead th:first-child,
.table tbody td:first-child{ position:sticky; left:0; z-index:4; box-shadow:1px 0 0 var(--line) }
.table thead th:first-child{ z-index:5 }

/* Fila expandible */
.expand{display:none;background:linear-gradient(180deg,var(--card),var(--hover))}
.expand.open{display:table-row}
.expgrid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:10px;padding:10px}
.card{background:var(--card);border:1px solid var(--line);border-radius:var(--radius-lg);padding:10px}
.card h3{margin:0 0 6px;font-size:13px;font-weight:800}
.mono{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12px;word-break:break-all}
.divider{height:1px;background:var(--line);margin:8px 0}

/* Estados + acciones */
.badge{display:inline-flex;align-items:center;gap:6px;padding:2px 8px;border-radius:999px;font-size:var(--fs-12);font-weight:800;border:1px solid transparent}
.badge.ok{background:rgba(22,163,74,.12);color:#065f46;border-color:rgba(22,163,74,.28)}
.badge.warn{background:rgba(245,158,11,.12);color:#7c2d12;border-color:rgba(245,158,11,.3)}
.badge.danger{background:rgba(239,68,68,.15);color:#7f1d1d;border-color:rgba(239,68,68,.3)}
.badge.info{background:rgba(14,165,233,.12);color:#075985;border-color:rgba(14,165,233,.28)}
.cell-actions{display:flex;gap:6px;flex-wrap:wrap}

/* Paginación */
.pager{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-top:10px}
.pager .info{font-size:12px;color:var(--mut)}
.pager nav{display:flex;gap:6px}
.pager nav a,.pager nav span{padding:4px 8px;border:1px solid var(--line);border-radius:8px;background:var(--card)}
.pager nav .active span{background:linear-gradient(180deg,var(--pri),var(--pri2));color:#fff;border:0}

/* Column manager */
.cols-menu{position:relative}
.cols-panel{position:absolute;right:0;top:calc(100% + 6px);background:var(--card);border:1px solid var(--line);
  border-radius:10px;padding:8px;min-width:200px;box-shadow:0 8px 24px rgba(0,0,0,.12);display:none;z-index:20}
.cols-panel.show{display:block}
.cols-panel label{display:flex;align-items:center;gap:8px;margin:4px 0;font-size:13px}
.colhide{display:none}

/* Responsivo */
@media (max-width: 1100px){
  .search{min-width:220px}
  .table{min-width:920px}
}
@media (max-width: 900px){
  .header{grid-template-columns:1fr}
  .actions{justify-content:flex-start}
  .table{min-width:860px}
  .btn{height:40px;padding:8px 12px}
  .plan-quick{grid-template-columns:1fr}
}
@media (max-width: 700px){
  .kpi strong{font-size:16px}
  .toolbar .input, .toolbar select{height:36px}
  .chips{gap:8px}
}
@media (prefers-reduced-motion: reduce){
  *{scroll-behavior:auto!important;transition:none!important;animation:none!important}
}

/* Sombras scroll */
.tablewrap.has-left-shadow::before,
.tablewrap.has-right-shadow::after{
  content:""; position:sticky; top:0; bottom:-1px; width:14px; z-index:6; pointer-events:none;
}
.tablewrap.has-left-shadow::before{ left:0; box-shadow:inset 8px 0 8px -8px rgba(0,0,0,.18) }
.tablewrap.has-right-shadow::after{ right:0; box-shadow:inset -8px 0 8px -8px rgba(0,0,0,.18) }
</style>
@endpush

@section('content')
@php
  $q = request('q'); $plan = request('plan'); $blocked = request('blocked');
  $s = request('sort','created_at'); $d = strtolower(request('dir','desc'))==='asc'?'asc':'desc';
  $pp = (int) request('per_page', 25);
  $toggleDir = fn($col) => ($s===$col && $d==='asc') ? 'desc' : 'asc';
  $sortUrl = fn($col) => request()->fullUrlWithQuery(['sort'=>$col,'dir'=>$toggleDir($col)]);
  $total = method_exists($rows,'total') ? $rows->total() : (is_countable($rows) ? count($rows) : null);
  $verMail = 0; $verPhone = 0;
  foreach ($rows as $x) { if(!empty($x->email_verified_at)) $verMail++; if(!empty($x->phone_verified_at)) $verPhone++; }
@endphp

<div class="page" id="adminClientesPage">
  {{-- Header --}}
  <div class="header">
    <div>
      <h1>Clientes</h1>
      <div class="sub">Administración de cuentas (SOT: <strong>admin.accounts</strong>)</div>
    </div>
    <div class="actions">
      <form method="POST" action="{{ route('admin.clientes.syncToClientes') }}" onsubmit="return confirm('¿Sincronizar accounts → clientes (legacy)?')">
        @csrf <button class="btn btnp">🔁 Sincronizar</button>
      </form>
      <button class="btn" id="btnExportCsv" type="button" title="Exportar CSV actual">⬇️ Exportar CSV</button>
    </div>
  </div>

  {{-- KPIs --}}
  <div class="kpis">
    <div class="kpi"><strong>{{ $total ?? '—' }}</strong><span>Total clientes</span></div>
    <div class="kpi"><strong>{{ $verMail }}</strong><span>Correo verificado</span></div>
    <div class="kpi"><strong>{{ $verPhone }}</strong><span>Teléfono verificado</span></div>
    <div class="kpi"><strong>{{ now()->format('Y-m-d H:i') }}</strong><span>Corte</span></div>
  </div>

  {{-- Chips --}}
  <div class="chips">
    @php $is = fn($k,$v)=> (string)request($k, '')===(string)$v; @endphp
    <a class="chip {{ $is('blocked','0')?'active':'' }}" href="{{ request()->fullUrlWithQuery(['blocked'=>'0']) }}">🟢 Operando</a>
    <a class="chip {{ $is('blocked','1')?'active':'' }}" href="{{ request()->fullUrlWithQuery(['blocked'=>'1']) }}">⛔ Bloqueados</a>
    <a class="chip {{ $is('plan','free')?'active':'' }}" href="{{ request()->fullUrlWithQuery(['plan'=>'free']) }}">Free</a>
    <a class="chip {{ $is('plan','pro')?'active':'' }}" href="{{ request()->fullUrlWithQuery(['plan'=>'pro']) }}">Pro</a>
    <a class="chip" href="{{ route('admin.clientes.index') }}">Limpiar</a>
  </div>

  {{-- Alertas --}}
  <div class="alerts" role="region" aria-label="Mensajes del sistema">
    @if(session('ok'))   <div class="alert ok"><strong>OK:</strong> {!! nl2br(e(session('ok'))) !!}</div> @endif
    @if(session('error'))<div class="alert bad"><strong>Error:</strong> {{ session('error') }}</div> @endif
    @if($errors->any())  <div class="alert warn"><strong>Validación:</strong> {{ $errors->first() }}</div> @endif

    {{-- QA: última temporal generada si existe --}}
    @php $tl = session('tmp_last'); @endphp
    @if(is_array($tl) && !empty($tl['pass']))
      <div class="alert info">
        <strong>Temporal generada:</strong>
        RFC <code class="mono">{{ $tl['key'] }}</code> ·
        Usuario <code class="mono">{{ $tl['user'] }}</code> ·
        Pass <code class="mono">{{ $tl['pass'] }}</code>
        <span class="meta"> ({{ $tl['ts'] ?? now()->toDateTimeString() }})</span>
      </div>
    @endif
  </div>

  {{-- Filtros --}}
  <div class="tools">
    <form method="GET" class="toolbar" role="search" aria-label="Filtrar clientes" id="filtersForm">
      <input class="input search" name="q" value="{{ $q }}" placeholder="RFC, razón social, correo o teléfono" aria-label="Buscar">
      <input class="input" name="plan" value="{{ $plan }}" placeholder="Plan (free, pro, ...)" aria-label="Plan">
      <select name="blocked" class="input" aria-label="Bloqueo">
        <option value="">Bloqueo (todos)</option>
        <option value="0" {{ request('blocked')==='0' ? 'selected':'' }}>No bloqueados</option>
        <option value="1" {{ request('blocked')==='1' ? 'selected':'' }}>Bloqueados</option>
      </select>
      <select name="sort" class="input" aria-label="Ordenar por">
        <option value="created_at" {{ $s==='created_at'?'selected':'' }}>Creado</option>
        <option value="razon_social" {{ $s==='razon_social'?'selected':'' }}>Razón social</option>
        <option value="plan" {{ $s==='plan'?'selected':'' }}>Plan</option>
        <option value="email_verified_at" {{ $s==='email_verified_at'?'selected':'' }}>Correo verificado</option>
        <option value="phone_verified_at" {{ $s==='phone_verified_at'?'selected':'' }}>Tel verificado</option>
        <option value="is_blocked" {{ $s==='is_blocked'?'selected':'' }}>Bloqueo</option>
      </select>
      <select name="dir" class="input" aria-label="Dirección">
        <option value="desc" {{ $d==='desc'?'selected':'' }}>Desc</option>
        <option value="asc"  {{ $d==='asc'?'selected':'' }}>Asc</option>
      </select>
      <select name="per_page" class="input" aria-label="Registros por página">
        @foreach([10,25,50,100] as $opt)
          <option value="{{ $opt }}" {{ $pp===$opt?'selected':'' }}>{{ $opt }}/pág</option>
        @endforeach
      </select>
      <button class="btn" type="submit" title="Aplicar filtros">🔎 Filtrar</button>

      <span class="spacer"></span>

      {{-- Columnas --}}
      <div class="cols-menu" id="colsMenu">
        <button class="btn" type="button" id="btnCols" aria-haspopup="true" aria-expanded="false">🧱 Columnas</button>
        <div class="cols-panel" id="colsPanel" aria-label="Columnas visibles">
          <label><input type="checkbox" data-col="col-datos" checked> Datos y edición rápida</label>
          <label><input type="checkbox" data-col="col-estados" checked> Estados</label>
          <label><input type="checkbox" data-col="col-acciones" checked> Acciones</label>
        </div>
      </div>
    </form>
  </div>

  {{-- Tabla --}}
  <div class="tablewrap" role="region" aria-label="Listado de clientes" id="tblWrap">
    <table class="table" id="tblClientes">
      <thead>
        <tr>
          <th style="width:240px">
            <a class="btng" href="{{ $sortUrl('created_at') }}" title="Ordenar por creado">
              Cliente / Creado <span class="meta">{{ $s==='created_at' ? ($d==='asc'?'▲':'▼') : '↕' }}</span>
            </a>
          </th>
          <th class="col-datos">Datos</th>
          <th class="col-estados" style="width:340px">
            <a class="btng" href="{{ $sortUrl('email_verified_at') }}" title="Ordenar por verificación de correo">
              Estados <span class="meta">{{ $s==='email_verified_at' ? ($d==='asc'?'▲':'▼') : '↕' }}</span>
            </a>
          </th>
          <th class="col-acciones" style="width:300px">Acciones</th>
        </tr>
      </thead>
      <tbody>
      @forelse($rows as $r)
        @php
          $RFC_FULL = strtoupper(trim((string) (data_get($r,'rfc') ?: data_get($r,'tax_id') ?: data_get($r,'id'))));
          $RFC_SLUG = \Illuminate\Support\Str::slug($RFC_FULL, '-');
          $created  = optional($r->created_at)->format('Y-m-d H:i') ?? '—';
          $info     = $extras[$r->id] ?? null;
          $c        = $creds[$r->id] ?? ['owner_email'=>null,'temp_pass'=>null];
          $tokenUrl = $info && !empty($info['email_token']) ? route('cliente.verify.email.token', ['token'=>$info['email_token']]) : null;
          $planVal  = strtolower((string)($r->plan ?? ''));
        @endphp
        <tr id="row-{{ $r->id }}">
          {{-- Cliente --}}
          <td>
            <div class="rowhead">
              <button class="expbtn" type="button" aria-expanded="false" data-target="#exp-{{ $r->id }}">▼</button>
              <div>
                <div class="rfc">{{ $RFC_FULL }}</div>
                <div class="meta">Creado: {{ $created }}</div>
              </div>
            </div>
          </td>

          {{-- Datos y edición rápida --}}
          <td class="col-datos">
            <form method="POST" action="{{ route('admin.clientes.save',$r->id) }}" class="row-form">
              @csrf
              <input class="input" name="razon_social" value="{{ $r->razon_social }}" placeholder="Razón social" aria-label="Razón social">
              <input class="input" name="email" value="{{ $r->email }}" placeholder="Correo" aria-label="Correo">
              <input class="input" name="phone" value="{{ $r->phone }}" placeholder="Teléfono" aria-label="Teléfono">

              {{-- Plan + presets Free / Pro --}}
              <div class="plan-quick">
                <select class="input" name="plan" aria-label="Plan">
                  <option value="">— Plan —</option>
                  <option value="free" {{ $planVal==='free'?'selected':'' }}>Free</option>
                  <option value="pro"  {{ $planVal==='pro'?'selected':'' }}>Pro</option>
                </select>
                <div class="plan-chips">
                  <button type="button" class="btn btn-xs" data-plan-preset="free" data-cycle="" data-days="0">Free</button>
                  <button type="button" class="btn btn-xs btnp" data-plan-preset="pro" data-cycle="monthly" data-days="30">Pro · mensual</button>
                  <button type="button" class="btn btn-xs btnp" data-plan-preset="pro" data-cycle="yearly" data-days="365">Pro · anual</button>
                </div>
              </div>

              @php $bc = $r->billing_cycle; @endphp
              <select name="billing_cycle" class="input" aria-label="Ciclo de cobro">
                <option value="">— Ciclo —</option>
                <option value="monthly" {{ $bc==='monthly'?'selected':'' }}>Mensual</option>
                <option value="yearly"  {{ $bc==='yearly'?'selected':'' }}>Anual</option>
              </select>
              <input class="input" type="date" name="next_invoice_date" value="{{ $r->next_invoice_date }}" aria-label="Próxima factura">

              <label class="meta" style="display:flex;align-items:center;gap:6px">
                <input type="checkbox" name="is_blocked" value="1" {{ (int)$r->is_blocked===1 ? 'checked':'' }}>
                Bloqueado
              </label>

              <div class="cell-actions">
                <button class="btn" title="Guardar cambios">💾 Guardar</button>
              </div>
            </form>
          </td>

          {{-- Estados --}}
          <td class="col-estados">
            <div style="display:flex;gap:6px;flex-wrap:wrap">
              <span class="badge {{ $r->email_verified_at ? 'ok':'warn' }}">Correo {{ $r->email_verified_at?'✔':'pendiente' }}</span>
              <span class="badge {{ $r->phone_verified_at ? 'ok':'warn' }}">Tel {{ $r->phone_verified_at?'✔':'pendiente' }}</span>
              <span class="badge {{ (int)$r->is_blocked===1 ? 'danger':'ok' }}">{{ (int)$r->is_blocked===1 ? 'Bloqueado' : 'Operando' }}</span>
              @if($r->plan)
                @php $p = strtolower($r->plan); @endphp
                <span class="badge {{ $p==='pro' ? 'ok' : 'warn' }}">Plan: {{ $r->plan }}</span>
              @endif
            </div>
            <div class="meta" style="margin-top:4px">
              Ciclo: <strong>{{ $r->billing_cycle ?: '—' }}</strong>
              · Próx. factura: <strong>{{ $r->next_invoice_date ?: '—' }}</strong>
            </div>
          </td>

          {{-- Acciones --}}
          <td class="col-acciones">
            <div class="cell-actions">
              <form method="POST" action="{{ route('admin.clientes.resendEmail',$r->id) }}" title="Reenviar verificación de correo">
                @csrf <button class="btn">📧 Reenviar</button>
              </form>
              <form method="POST" action="{{ route('admin.clientes.sendOtp',$r->id) }}" title="Enviar OTP">
                @csrf <input type="hidden" name="channel" value="sms">
                <button class="btn">📱 Enviar OTP</button>
              </form>
              <button class="btn btnp" type="button" title="Abrir panel de onboarding" onclick="document.querySelector('[data-target=\'#exp-{{ $r->id }}\']').click()">🚀 Onboarding</button>
            </div>
          </td>
        </tr>

        {{-- Expandible --}}
        <tr class="expand" id="exp-{{ $r->id }}">
          <td colspan="4">
            <div class="expgrid">
              {{-- Correo --}}
              <div class="card">
                <h3>Verificación de correo</h3>
                @if($tokenUrl)
                  <div class="meta">Enlace de validación:</div>
                  <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin-top:2px">
                    <code class="mono" id="tok-{{ $r->id }}">{{ $tokenUrl }}</code>
                    <button class="btn" type="button" data-copy="#tok-{{ $r->id }}">Copiar</button>
                    <a class="btn" href="{{ $tokenUrl }}" target="_blank" rel="noopener">Abrir</a>
                  </div>
                  <div class="meta" style="margin-top:4px">Expira: {{ $info['email_expires_at'] ?? '—' }}</div>
                @else
                  <div class="meta">Sin token vigente.</div>
                @endif
                <div class="cell-actions" style="margin-top:6px">
                  <form method="POST" action="{{ route('admin.clientes.resendEmail',$r->id) }}">
                    @csrf <button class="btn">Reenviar correo</button>
                  </form>
                  <form method="POST" action="{{ route('admin.clientes.forceEmail',$r->id) }}" onsubmit="return confirm('¿Marcar correo como verificado?')">
                    @csrf <button class="btn">Forzar correo ✔</button>
                  </form>
                </div>
              </div>

              {{-- Teléfono / OTP --}}
              <div class="card">
                <h3>Teléfono / OTP</h3>
                @if(!empty($info['otp_code']))
                  <div><span class="badge info">Código</span> <span class="mono">{{ $info['otp_code'] }}</span></div>
                  <div class="meta">Canal: {{ strtoupper($info['otp_channel'] ?? '—') }} · Expira: {{ $info['otp_expires_at'] ?? '—' }}</div>
                @else
                  <div class="meta">Aún no se ha generado un OTP.</div>
                @endif
                <form method="POST" action="{{ route('admin.clientes.sendOtp',$r->id) }}" style="display:flex;gap:6px;align-items:center;margin-top:6px;flex-wrap:wrap">
                  @csrf
                  <select name="channel" class="input">
                    <option value="sms">SMS</option>
                    <option value="whatsapp">WhatsApp</option>
                  </select>
                  <input class="input" name="phone" value="{{ $r->phone }}" placeholder="Editar teléfono (opcional)">
                  <button class="btn">Enviar OTP</button>
                </form>
                <form method="POST" action="{{ route('admin.clientes.forcePhone',$r->id) }}" onsubmit="return confirm('¿Marcar teléfono como verificado?')" style="margin-top:6px">
                  @csrf <button class="btn">Forzar tel ✔</button>
                </form>
              </div>

              {{-- Credenciales --}}
              <div class="card">
                <h3>Credenciales</h3>
                @php
                  // ==== CLAVES por RFC (¡no por ID!) ====
                  $RFC_KEY_U = strtoupper($RFC_FULL);
                  $RFC_KEY_L = strtolower($RFC_FULL);

                  // Usuario (email)
                  $ownerEmail = session("tmp_user.$RFC_KEY_U")
                                 ?? session("tmp_user.$RFC_KEY_L")
                                 ?? ($c['owner_email'] ?? $r->email ?? null);

                  // Password temporal (texto plano)
                  $tempPass   = session("tmp_pass.$RFC_KEY_U")
                                 ?? session("tmp_pass.$RFC_KEY_L")
                                 ?? cache()->get("tmp_pass.$RFC_KEY_U")
                                 ?? cache()->get("tmp_pass.$RFC_KEY_L")
                                 ?? ($c['temp_pass'] ?? null); // este 'temp_pass' debe ser PLAIN si tu backend lo setea correcto

                  // Compat: tmp_last (si vino de otra acción)
                  if (empty($tempPass)) {
                    $tl2 = session('tmp_last');
                    if (is_array($tl2)
                        && !empty($tl2['pass'])
                        && strcasecmp($tl2['key'] ?? '', $RFC_KEY_U) === 0) {
                      $tempPass   = $tl2['pass'];
                      $ownerEmail = $ownerEmail ?: ($tl2['user'] ?? null);
                    }
                  }

                  // Fallback: cookies p360_tmp_*
                  if (empty($tempPass)) {
                      $cookiePass = request()->cookie("p360_tmp_pass_{$RFC_KEY_U}");
                      if (!empty($cookiePass)) $tempPass = $cookiePass;
                  }
                  if (empty($ownerEmail)) {
                      $cookieUser = request()->cookie("p360_tmp_user_{$RFC_KEY_U}");
                      if (!empty($cookieUser)) $ownerEmail = $cookieUser;
                  }
                @endphp

                <div class="meta">RFC:</div>
                <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                  <code class="mono" id="rfc-{{ $RFC_SLUG }}">{{ $RFC_FULL }}</code>
                  <button class="btn" type="button" data-copy="#rfc-{{ $RFC_SLUG }}">Copiar</button>
                </div>

                <div class="divider"></div>

                <div class="meta">Usuario:</div>
                <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                  <code class="mono" id="user-{{ $RFC_SLUG }}">{{ $ownerEmail ?: '—' }}</code>
                  <button class="btn" type="button" data-copy="#user-{{ $RFC_SLUG }}">Copiar</button>
                </div>

                <div class="divider"></div>

                @if(!empty($tempPass))
                  <div class="meta">Contraseña temporal:</div>
                  <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                    <code class="mono" id="pass-{{ $RFC_SLUG }}">{{ $tempPass }}</code>
                    <button class="btn" type="button" data-copy="#pass-{{ $RFC_SLUG }}">Copiar</button>
                    <a class="btn" href="{{ route('cliente.login') }}" target="_blank" rel="noopener">Probar login</a>
                  </div>
                @else
                  <div class="meta">Presiona “Resetear contraseña” para generar una temporal y verla aquí.</div>
                @endif

                <div class="cell-actions" style="margin-top:6px">
                  <form method="POST" action="{{ route('admin.clientes.resetPassword',$r->id) }}" onsubmit="return confirm('¿Generar contraseña temporal para el OWNER?')">
                    @csrf <button class="btn">Resetear contraseña</button>
                  </form>
                  <form method="POST" action="{{ route('admin.clientes.emailCredentials',$r->id) }}" onsubmit="return confirm('¿Enviar credenciales por correo?')">
                    @csrf <button class="btn btnp">Enviar credenciales</button>
                  </form>
                  <form method="POST" action="{{ route('admin.clientes.impersonate',$r->id) }}" onsubmit="return confirm('Vas a iniciar sesión como el cliente. ¿Continuar?')">
                    @csrf <button class="btn">Iniciar sesión como usuario</button>
                  </form>
                </div>

                <div class="meta" style="margin-top:4px">Último envío: {{ $info['cred_last_sent_at'] ?? '—' }}</div>
              </div>

          </td>
        </tr>
      @empty
        <tr><td colspan="4" style="text-align:center;color:var(--mut);padding:18px">Sin resultados. Modifica los filtros o limpia la búsqueda.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>

  {{-- Paginación --}}
  <div class="pager" aria-label="Paginación">
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
@endsection

@push('scripts')
<script>
(function(){
  const $ = (s,sc)=> (sc||document).querySelector(s);
  const $$ = (s,sc)=> Array.from((sc||document).querySelectorAll(s));

  // Autosubmit selects; ESC limpia búsqueda
  const form = $('#filtersForm');
  if(form){
    form.querySelectorAll('select').forEach(sel=> sel.addEventListener('change', ()=> form.submit()));
    const search = form.querySelector('input[name="q"]');
    if(search){
      search.addEventListener('keydown', e=>{ if(e.key==='Escape'){ search.value=''; form.submit(); } });
    }
  }

  // Expand/collapse + copiar + presets de plan + columnas
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.expbtn');
    if (btn) {
      const sel = btn.getAttribute('data-target');
      const panel = document.querySelector(sel);
      if (panel) {
        const open = panel.classList.contains('open');
        $$('.expand.open').forEach(el => el.classList.remove('open'));
        $$('.expbtn[aria-expanded="true"]').forEach(b => b.setAttribute('aria-expanded','false'));
        if (!open) {
          panel.classList.add('open');
          btn.setAttribute('aria-expanded','true');
          setTimeout(()=>panel.scrollIntoView({behavior:'smooth', block:'nearest'}), 40);
        }
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
        copyBtn.textContent = '¡Copiado!';
        copyBtn.disabled = true;
        setTimeout(()=>{ copyBtn.disabled=false; copyBtn.textContent=prev; }, 800);
      });
    }

    // Presets de plan Free / Pro
    const presetBtn = e.target.closest('[data-plan-preset]');
    if (presetBtn) {
      e.preventDefault();
      const plan  = presetBtn.getAttribute('data-plan-preset') || '';
      const cycle = presetBtn.getAttribute('data-cycle') || '';
      const days  = parseInt(presetBtn.getAttribute('data-days') || '0', 10);

      const formRow = presetBtn.closest('form.row-form');
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
          const iso = d.toISOString().slice(0,10);
          nextInput.value = iso;
        } else {
          nextInput.value = '';
        }
      }
    }

    // Columnas menu
    if (e.target.id==='btnCols'){
      const pnl = $('#colsPanel'); const btn2 = e.target;
      pnl.classList.toggle('show');
      btn2.setAttribute('aria-expanded', pnl.classList.contains('show') ? 'true' : 'false');
    } else if (!e.target.closest('#colsMenu')){
      $('#colsPanel')?.classList.remove('show');
      $('#btnCols')?.setAttribute('aria-expanded','false');
    }
  });

  // Column visibility
  const colChecks = $$('#colsPanel input[type="checkbox"]');
  function applyCols(){
    colChecks.forEach(ch=>{
      const cls = ch.getAttribute('data-col');
      $$('.'+cls).forEach(td=> td.classList.toggle('colhide', !ch.checked));
    });
  }
  colChecks.forEach(ch=> ch.addEventListener('change', applyCols));
  applyCols();

  // Export CSV
  $('#btnExportCsv')?.addEventListener('click', ()=>{
    const rows = [];
    const head = ['RFC','RazonSocial','Email','Phone','Plan','BillingCycle','NextInvoice','EmailVerif','PhoneVerif','Blocked','CreatedAt'];
    rows.push(head.join(','));
    $$('#tblClientes tbody tr').forEach(tr=>{
      if(tr.classList.contains('expand')) return;
      const rfc = tr.querySelector('.rfc')?.innerText?.trim() || '';
      const form = tr.querySelector('form.row-form');
      const v = (name)=> form?.querySelector(`[name="${name}"]`)?.value || '';
      const created = tr.querySelector('.meta')?.innerText?.replace('Creado:','').trim() || '';
      const emailBadge = Array.from(tr.querySelectorAll('.badge')).find(b=> b.textContent.includes('Correo'));
      const phoneBadge = Array.from(tr.querySelectorAll('.badge')).find(b=> b.textContent.includes('Tel'));
      const emailVer = emailBadge ? (emailBadge.classList.contains('ok') ? '1':'0') : '';
      const phoneVer = phoneBadge ? (phoneBadge.classList.contains('ok') ? '1':'0') : '';
      const blocked = Array.from(tr.querySelectorAll('.badge')).some(b=> b.textContent.includes('Bloqueado')) ? '1':'0';

      rows.push([rfc,v('razon_social'),v('email'),v('phone'),v('plan'),v('billing_cycle'),v('next_invoice_date'),emailVer,phoneVer,blocked,created].map(s=>{
        const t = (s??'').toString();
        return /[",\n]/.test(t) ? `"${t.replace(/"/g,'""')}"` : t;
      }).join(','));
    });
    const blob = new Blob([rows.join('\n')],{type:'text/csv;charset=utf-8;'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = 'clientes_export.csv';
    document.body.appendChild(a); a.click();
    a.remove(); URL.revokeObjectURL(url);
  });

  // Sombras scroll horizontal
  (function trackShadows(){
    const wrap = document.getElementById('tblWrap');
    if(!wrap) return;
    const update = ()=>{
      const {scrollLeft, scrollWidth, clientWidth} = wrap;
      wrap.classList.toggle('has-left-shadow', scrollLeft > 0);
      wrap.classList.toggle('has-right-shadow', scrollLeft + clientWidth < scrollWidth - 1);
    };
    wrap.addEventListener('scroll', update, {passive:true});
    window.addEventListener('resize', update);
    setTimeout(update, 60);
  })();

  // ===== PARCHE DEFINITIVO: crea slots si faltan y rellena desde cookies p360_tmp_* =====
  (function cookieFallback(){
    function slugify(s){ return (s||'').toString().toLowerCase().replace(/[^a-z0-9]+/g,'-'); }
    function getCookie(name){
      if (!document.cookie) return null;
      const parts = document.cookie.split('; ');
      for (const part of parts){
        const eq = part.indexOf('=');
        if (eq === -1) continue;
        const key = decodeURIComponent(part.slice(0, eq));
        if (key === name){
          return decodeURIComponent(part.slice(eq + 1));
        }
      }
      return null;
    }
    function ensureSlot(card, labelText, idPrefix, rfc){
      // Busca existente
      let el = card.querySelector(`[id^="${idPrefix}-"]`);
      if (el) return el;
      // Si no existe, lo crea con su label y wrapper
      const slug = slugify(rfc);
      const wrap = document.createElement('div');
      wrap.className = 'autogenerated-slot';
      const label = document.createElement('div');
      label.className = 'meta';
      label.textContent = labelText;
      const row = document.createElement('div');
      row.style.display = 'flex';
      row.style.gap = '6px';
      row.style.alignItems = 'center';
      row.style.flexWrap = 'wrap';
      el = document.createElement('code');
      el.className = 'mono';
      el.id = `${idPrefix}-${slug}`;
      el.textContent = '—';
      row.appendChild(el);
      wrap.appendChild(label);
      wrap.appendChild(row);
      // Inserta antes del bloque de acciones si existe, si no al final
      const actions = card.querySelector('.cell-actions');
      if (actions && actions.parentNode){
        actions.parentNode.insertBefore(wrap, actions);
      } else {
        card.appendChild(wrap);
      }
      return el;
    }

    document.querySelectorAll('.card h3').forEach(function(h3){
      try{
        if(!h3) return;
        if((h3.textContent||'').trim()!=='Credenciales') return;
        const card = h3.closest('.card'); if(!card) return;

        const rfcEl = card.querySelector('[id^="rfc-"]');
        if(!rfcEl) return;
        const RFC = (rfcEl.textContent||'').trim().toUpperCase();
        if(!RFC) return;

        // Asegura que existan los code necesarios
        const userEl = ensureSlot(card, 'Usuario:', 'user', RFC);
        const passEl = ensureSlot(card, 'Contraseña temporal:', 'pass', RFC);

        const isDash = (el)=> (el.textContent||'').trim()==='—';

        // Rellena usuario si procede
        if (isDash(userEl)){
          const cu = getCookie('p360_tmp_user_'+RFC);
          if (cu) userEl.textContent = cu;
        }
        // Rellena pass si procede
        if (isDash(passEl)){
          const cp = getCookie('p360_tmp_pass_'+RFC);
          if (cp){
            passEl.textContent = cp;
            const tag = document.createElement('span');
            tag.className = 'badge info';
            tag.style.marginLeft = '6px';
            tag.textContent = 'cookie';
            passEl.parentElement?.insertBefore(tag, passEl.nextSibling);
          }
        }
      }catch(err){
        console.warn('cookieFallback skip', err);
      }
    });
  })();

})();
</script>
@endpush
