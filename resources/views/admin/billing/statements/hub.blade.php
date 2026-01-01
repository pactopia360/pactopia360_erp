{{-- resources/views/admin/billing/statements/hub.blade.php (v2.0 · HUB final: Estados + Emails + Pagos + Solicitudes + Facturas) --}}
@extends('layouts.admin')

@section('title', 'Facturación · HUB')
@section('layout', 'full')

@php
  use Illuminate\Support\Facades\Route;

  $tab = $tab ?? request('tab','statements');
  $q = $q ?? request('q','');
  $period = $period ?? request('period', now()->format('Y-m'));
  $accountId = $accountId ?? request('accountId','');

  $rows = $rows ?? collect();
  $kpis = $kpis ?? ['cargo'=>0,'abono'=>0,'saldo'=>0,'accounts'=>0];

  $emails = $emails ?? collect();
  $payments = $payments ?? collect();
  $invoiceRequests = $invoiceRequests ?? collect();
  $invoices = $invoices ?? collect();

  $routeIndex = route('admin.billing.statements_hub.index');
@endphp

@push('styles')
<style>
  .p360-page{ padding:0 !important; }
  .hub{
    padding:16px;
  }
  .card{
    background:var(--card-bg);
    border:1px solid var(--card-border);
    border-radius:18px;
    box-shadow: var(--shadow-1);
    overflow:hidden;
  }
  .head{
    padding:16px;
    display:flex;
    justify-content:space-between;
    gap:14px;
    flex-wrap:wrap;
    align-items:flex-end;
    border-bottom:1px solid rgba(15,23,42,.08);
  }
  .ttl{ margin:0;font-size:18px;font-weight:950;color:var(--text); }
  .sub{ margin-top:6px;color:var(--muted);font-weight:850;font-size:12px; max-width:900px; }
  .filters{
    display:grid;
    grid-template-columns: 1fr 160px 160px auto;
    gap:10px;
    align-items:end;
    min-width:min(860px, 100%);
  }
  @media(max-width: 980px){
    .filters{ grid-template-columns:1fr 1fr; }
  }
  .ctl label{ display:block; font-size:12px; color:var(--muted); font-weight:900; margin-bottom:6px; }
  .in{
    width:100%;
    padding:10px 12px;
    border-radius:12px;
    border:1px solid rgba(15,23,42,.12);
    background:transparent;
    color:var(--text);
    font-weight:850;
    outline:none;
  }
  .btn{
    padding:10px 12px;
    border-radius:12px;
    border:1px solid rgba(15,23,42,.14);
    font-weight:950;
    cursor:pointer;
    text-decoration:none;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    white-space:nowrap;
  }
  .btn-dark{ background:var(--text); color:#fff; }
  html[data-theme="dark"] .btn-dark{ background:#111827; border-color:rgba(255,255,255,.12); }
  .btn-light{ background: color-mix(in oklab, var(--card-bg) 92%, transparent); color:var(--text); }

  .tabs{
    padding:12px 16px;
    display:flex;
    gap:8px;
    flex-wrap:wrap;
    border-bottom:1px solid rgba(15,23,42,.08);
    background: color-mix(in oklab, var(--card-bg) 96%, transparent);
  }
  .tab{
    padding:9px 12px;
    border-radius:999px;
    border:1px solid rgba(15,23,42,.12);
    font-weight:950;
    color:var(--text);
    text-decoration:none;
    background:transparent;
  }
  .tab.on{
    background:var(--text);
    color:#fff;
    border-color:rgba(15,23,42,.22);
  }
  html[data-theme="dark"] .tab.on{ background:#111827; border-color:rgba(255,255,255,.12); }

  .kpis{
    padding:16px;
    display:grid;
    grid-template-columns: repeat(5, minmax(160px, 1fr));
    gap:10px;
    border-bottom:1px solid rgba(15,23,42,.08);
  }
  @media(max-width: 1100px){
    .kpis{ grid-template-columns: repeat(2, minmax(160px, 1fr)); }
  }
  .kpi{
    border:1px solid rgba(15,23,42,.10);
    border-radius:16px;
    padding:12px;
    background: color-mix(in oklab, var(--card-bg) 92%, transparent);
  }
  .k{ font-size:12px;color:var(--muted);font-weight:950;text-transform:uppercase;letter-spacing:.04em; }
  .v{ font-size:16px;font-weight:950;color:var(--text); margin-top:6px; }
  .mono{ font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas,"Liberation Mono","Courier New", monospace; font-weight:900; }
  .mut{ color:var(--muted); font-weight:850; font-size:12px; }

  .msg{ margin:12px 16px 0; padding:10px 12px; border-radius:12px; font-weight:900; }
  .msg.ok{ border:1px solid #bbf7d0; background:#dcfce7; color:#166534; }
  .msg.err{ border:1px solid #fecaca; background:#fee2e2; color:#991b1b; }

  .table{ padding:16px; }
  table.t{ width:100%; border-collapse:collapse; }
  .t th{
    text-align:left; padding:10px 12px; font-size:12px; color:var(--muted); font-weight:950;
    text-transform:uppercase; letter-spacing:.04em; border-bottom:1px solid rgba(15,23,42,.10);
  }
  .t td{ padding:12px; border-bottom:1px solid rgba(15,23,42,.07); vertical-align:top; color:var(--text); font-weight:800; }
  .tright{ text-align:right; }

  .pill{
    display:inline-block; padding:6px 10px; border-radius:999px; font-weight:950; font-size:12px;
    border:1px solid transparent; white-space:nowrap;
  }
  .pill-ok{ background:#dcfce7;color:#166534;border-color:#bbf7d0; }
  .pill-warn{ background:#fef3c7;color:#92400e;border-color:#fde68a; }
  .pill-dim{ background:rgba(15,23,42,.06);color:var(--muted);border-color:rgba(15,23,42,.10); }
  .pill-info{ background:#dbeafe;color:#1d4ed8;border-color:#bfdbfe; }

  .grid2{
    display:grid;
    grid-template-columns: 1fr 420px;
    gap:14px;
  }
  @media(max-width: 1100px){ .grid2{ grid-template-columns:1fr; } }

  .box{
    border:1px solid rgba(15,23,42,.10);
    border-radius:16px;
    background: color-mix(in oklab, var(--card-bg) 92%, transparent);
    padding:14px;
  }

  .actions{
    display:flex; gap:8px; justify-content:flex-end; flex-wrap:wrap;
  }
</style>
@endpush

@section('content')
<div class="hub">
  <div class="card">

    <div class="head">
      <div>
        <div class="ttl">Facturación · HUB</div>
        <div class="sub">
          Un solo panel: estados de cuenta, pagos, correos (tracking open/click + reenvío + multi-destino + preview),
          solicitudes de factura y facturas emitidas por admin.
        </div>
      </div>

      <form method="GET" action="{{ $routeIndex }}" class="filters">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <div class="ctl">
          <label>Buscar</label>
          <input class="in" name="q" value="{{ $q }}" placeholder="ID, RFC, email, UUID, status, etc.">
        </div>
        <div class="ctl">
          <label>Periodo</label>
          <input class="in" name="period" value="{{ $period }}" placeholder="YYYY-MM">
        </div>
        <div class="ctl">
          <label>Cuenta (ID)</label>
          <input class="in" name="accountId" value="{{ $accountId }}" placeholder="ID exacto">
        </div>
        <div class="ctl">
          <label>&nbsp;</label>
          <button class="btn btn-dark" type="submit">Filtrar</button>
        </div>
      </form>
    </div>

    <div class="tabs">
      <a class="tab {{ $tab==='statements'?'on':'' }}" href="{{ $routeIndex }}?tab=statements&period={{ urlencode($period) }}&q={{ urlencode($q) }}&accountId={{ urlencode($accountId) }}">Estados</a>
      <a class="tab {{ $tab==='emails'?'on':'' }}" href="{{ $routeIndex }}?tab=emails&period={{ urlencode($period) }}&q={{ urlencode($q) }}&accountId={{ urlencode($accountId) }}">Correos</a>
      <a class="tab {{ $tab==='payments'?'on':'' }}" href="{{ $routeIndex }}?tab=payments&period={{ urlencode($period) }}&q={{ urlencode($q) }}&accountId={{ urlencode($accountId) }}">Pagos</a>
      <a class="tab {{ $tab==='invoice_requests'?'on':'' }}" href="{{ $routeIndex }}?tab=invoice_requests&period={{ urlencode($period) }}&q={{ urlencode($q) }}&accountId={{ urlencode($accountId) }}">Solicitudes de factura</a>
      <a class="tab {{ $tab==='invoices'?'on':'' }}" href="{{ $routeIndex }}?tab=invoices&period={{ urlencode($period) }}&q={{ urlencode($q) }}&accountId={{ urlencode($accountId) }}">Facturas emitidas</a>
    </div>

    @if(session('ok'))
      <div class="msg ok">{{ session('ok') }}</div>
    @endif
    @if($errors->any())
      <div class="msg err">{{ $errors->first() }}</div>
    @endif

    @if($tab==='statements')
      <div class="kpis">
        <div class="kpi"><div class="k">Total</div><div class="v">${{ number_format((float)($kpis['cargo'] ?? 0),2) }}</div></div>
        <div class="kpi"><div class="k">Pagado</div><div class="v">${{ number_format((float)($kpis['abono'] ?? 0),2) }}</div></div>
        <div class="kpi"><div class="k">Saldo</div><div class="v">${{ number_format((float)($kpis['saldo'] ?? 0),2) }}</div></div>
        <div class="kpi"><div class="k">Cuentas</div><div class="v">{{ (int)($kpis['accounts'] ?? 0) }}</div></div>

        <div class="kpi">
          <div class="k">Acciones rápidas</div>
          <div class="v" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px;">
            <a class="btn btn-light" target="_blank" href="{{ route('admin.billing.statements_hub.index') }}?tab=emails&period={{ $period }}&accountId={{ $accountId }}">Ver correos</a>
            <a class="btn btn-light" target="_blank" href="{{ route('admin.billing.statements_hub.index') }}?tab=payments&period={{ $period }}&accountId={{ $accountId }}">Ver pagos</a>
          </div>
        </div>
      </div>

      <div class="table">
        <div class="grid2">
          <div class="box">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
              <div style="font-weight:950;">Estados de cuenta (resumen)</div>
              <div class="mut">Periodo: <span class="mono">{{ $period }}</span></div>
            </div>

            <div style="height:10px;"></div>
            <table class="t">
              <thead>
                <tr>
                  <th style="width:90px">Cuenta</th>
                  <th>Cliente</th>
                  <th>Email</th>
                  <th class="tright" style="width:140px">Total</th>
                  <th class="tright" style="width:140px">Pagado</th>
                  <th class="tright" style="width:140px">Saldo</th>
                  <th style="width:140px">Estatus</th>
                </tr>
              </thead>
              <tbody>
                @forelse($rows as $r)
                  @php
                    $aid = (string)($r->id ?? '');
                    $mail = (string)($r->email ?? '—');
                    $name = trim((string)(($r->razon_social ?? '') ?: ($r->name ?? '') ?: ($mail ?: '—')));
                    $cargoReal = (float)($r->cargo ?? 0);
                    $paid = (float)($r->abono ?? 0);
                    $expected = (float)($r->expected_total ?? 0);
                    $totalShown = $cargoReal > 0 ? $cargoReal : $expected;
                    $saldo = max(0, $totalShown - $paid);

                    $st = (string)($r->status_pago ?? '');
                    $pill = $st==='pagado' ? 'pill-ok' : ($st==='pendiente' ? 'pill-warn' : 'pill-dim');
                    $lbl  = $st==='pagado' ? 'PAGADO' : ($st==='pendiente' ? 'PENDIENTE' : 'SIN MOV');
                  @endphp
                  <tr>
                    <td class="mono">#{{ $aid }}</td>
                    <td>
                      <div style="font-weight:950;">{{ $name }}</div>
                      <div class="mut">Tarifa: <span class="pill {{ (string)($r->tarifa_pill ?? 'pill-dim') }}">{{ (string)($r->tarifa_label ?? '—') }}</span></div>
                    </td>
                    <td class="mono">{{ $mail }}</td>
                    <td class="tright mono">${{ number_format($totalShown,2) }}</td>
                    <td class="tright mono">${{ number_format($paid,2) }}</td>
                    <td class="tright"><span class="pill {{ $saldo>0?'pill-warn':'pill-ok' }} mono">${{ number_format($saldo,2) }}</span></td>
                    <td><span class="pill {{ $pill }}">{{ $lbl }}</span></td>
                  </tr>
                @empty
                  <tr><td colspan="7" class="mut" style="padding:14px;">Sin cuentas o faltan tablas.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>

          <div class="box">
            <div style="font-weight:950;">Operaciones (correo / liga pago / factura)</div>
            <div class="mut" style="margin-top:4px;">Trabaja por <b>account_id + periodo</b>. “to” acepta múltiples correos separados por coma.</div>

            <div style="height:12px;"></div>

            <form method="POST" action="{{ route('admin.billing.statements_hub.send_email') }}" style="display:grid; gap:8px;">
              @csrf
              <input class="in" name="account_id" value="{{ $accountId }}" placeholder="account_id" required>
              <input class="in" name="period" value="{{ $period }}" placeholder="YYYY-MM" required>
              <input class="in" name="to" value="" placeholder="to (opcional) ej: a@x.com,b@y.com">
              <button class="btn btn-dark" type="submit">Enviar correo ahora</button>
            </form>

            <div style="height:14px;border-top:1px solid rgba(15,23,42,.08);"></div>

            <form method="POST" action="{{ route('admin.billing.statements_hub.schedule') }}" style="display:grid; gap:8px; margin-top:14px;">
              @csrf
              <input class="in" name="account_id" value="{{ $accountId }}" placeholder="account_id" required>
              <input class="in" name="period" value="{{ $period }}" placeholder="YYYY-MM" required>
              <input class="in" name="to" value="" placeholder="to (opcional) multi">
              <input class="in" name="queued_at" value="{{ now()->addMinutes(10)->format('Y-m-d H:i:s') }}" placeholder="YYYY-MM-DD HH:MM:SS">
              <button class="btn btn-dark" type="submit">Programar correo</button>
              <div class="mut">Cron: <span class="mono">php artisan p360:billing:process-scheduled-emails</span></div>
            </form>

            <div style="height:14px;border-top:1px solid rgba(15,23,42,.08);"></div>

            <form method="GET" action="{{ route('admin.billing.statements_hub.preview_email') }}" target="_blank" style="display:grid; gap:8px; margin-top:14px;">
              <input class="in" name="account_id" value="{{ $accountId }}" placeholder="account_id" required>
              <input class="in" name="period" value="{{ $period }}" placeholder="YYYY-MM" required>
              <button class="btn btn-light" type="submit">Vista previa del correo</button>
            </form>

            <div style="height:14px;border-top:1px solid rgba(15,23,42,.08);"></div>

            <form method="POST" action="{{ route('admin.billing.statements_hub.create_pay_link') }}" style="display:grid; gap:8px; margin-top:14px;">
              @csrf
              <input class="in" name="account_id" value="{{ $accountId }}" placeholder="account_id" required>
              <input class="in" name="period" value="{{ $period }}" placeholder="YYYY-MM" required>
              <button class="btn btn-dark" type="submit">Generar liga de pago (Stripe)</button>
            </form>

            <div style="height:14px;border-top:1px solid rgba(15,23,42,.08);"></div>

            <form method="POST" action="{{ route('admin.billing.statements_hub.invoice_request') }}" style="display:grid; gap:8px; margin-top:14px;">
              @csrf
              <input class="in" name="account_id" value="{{ $accountId }}" placeholder="account_id" required>
              <input class="in" name="period" value="{{ $period }}" placeholder="YYYY-MM" required>
              <textarea class="in" name="notes" rows="3" placeholder="Notas (opcional)" style="resize:vertical;"></textarea>
              <button class="btn btn-dark" type="submit">Crear/actualizar solicitud de factura</button>
            </form>
          </div>
        </div>
      </div>

    @elseif($tab==='emails')
      <div class="table">
        <div class="box" style="margin:16px;">
          <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
            <div style="font-weight:950;">Correos (billing_email_logs)</div>
            <div class="mut">Tracking open/click + reenvío</div>
          </div>

          <div style="height:10px;"></div>
          <table class="t">
            <thead>
              <tr>
                <th style="width:80px">ID</th>
                <th>Destinos</th>
                <th style="width:100px">Periodo</th>
                <th style="width:120px">Status</th>
                <th style="width:110px">Opens</th>
                <th style="width:110px">Clicks</th>
                <th style="width:190px">Fechas</th>
                <th style="width:220px" class="tright">Acciones</th>
              </tr>
            </thead>
            <tbody>
              @forelse($emails as $e)
                @php
                  $st = (string)($e->status ?? 'queued');
                  $pill = $st==='sent' ? 'pill-ok' : ($st==='failed' ? 'pill-warn' : 'pill-dim');
                  $aid = (string)($e->account_id ?? '');
                  $p = (string)($e->period ?? '');
                @endphp
                <tr>
                  <td class="mono">#{{ (int)($e->id ?? 0) }}</td>
                  <td>
                    <div style="font-weight:950;">
                      {{ (string)($e->to_list ?? $e->email ?? '—') }}
                    </div>
                    <div class="mut">account: <span class="mono">{{ $aid ?: '—' }}</span> · email_id: <span class="mono">{{ (string)($e->email_id ?? '—') }}</span></div>
                    <div class="mut">subject: {{ (string)($e->subject ?? '—') }}</div>
                  </td>
                  <td class="mono">{{ $p ?: '—' }}</td>
                  <td><span class="pill {{ $pill }}">{{ strtoupper($st) }}</span></td>
                  <td class="mono">{{ (int)($e->open_count ?? 0) }}</td>
                  <td class="mono">{{ (int)($e->click_count ?? 0) }}</td>
                  <td class="mut">
                    queued: {{ $e->queued_at ?? '—' }}<br>
                    sent: {{ $e->sent_at ?? '—' }}<br>
                    failed: {{ $e->failed_at ?? '—' }}
                  </td>
                  <td class="tright">
                    <div class="actions">
                      @if($aid && $p)
                        <a class="btn btn-light" target="_blank"
                           href="{{ route('admin.billing.statements_hub.preview_email',['account_id'=>$aid,'period'=>$p]) }}">Preview</a>
                      @endif
                      <form method="POST" action="{{ route('admin.billing.statements_hub.resend', ['id'=>(int)($e->id ?? 0)]) }}">
                        @csrf
                        <button class="btn btn-dark" type="submit">Reenviar</button>
                      </form>
                    </div>
                  </td>
                </tr>
              @empty
                <tr><td colspan="8" class="mut" style="padding:14px;">Sin logs para el filtro.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

    @elseif($tab==='payments')
      <div class="table">
        <div class="box" style="margin:16px;">
          <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
            <div style="font-weight:950;">Pagos (payments)</div>
            <div class="mut">Incluye pending/paid/succeeded. Filtra por account_id y period si existe.</div>
          </div>

          <div style="height:10px;"></div>
          <table class="t">
            <thead>
              <tr>
                <th style="width:80px">ID</th>
                <th>Cuenta</th>
                <th style="width:110px">Periodo</th>
                <th style="width:160px">Amount</th>
                <th style="width:130px">Status</th>
                <th>Referencia</th>
              </tr>
            </thead>
            <tbody>
              @forelse($payments as $p)
                @php
                  $st = strtoupper((string)($p->status ?? '—'));
                  $pill = in_array(strtolower((string)$p->status), ['paid','succeeded','success','completed'], true) ? 'pill-ok' : (strtolower((string)$p->status)==='pending' ? 'pill-warn' : 'pill-dim');
                  $amount = (int)($p->amount ?? 0);
                @endphp
                <tr>
                  <td class="mono">#{{ (int)($p->id ?? 0) }}</td>
                  <td class="mono">{{ (string)($p->account_id ?? '—') }}</td>
                  <td class="mono">{{ (string)($p->period ?? '—') }}</td>
                  <td class="mono">${{ number_format($amount/100, 2) }} MXN</td>
                  <td><span class="pill {{ $pill }}">{{ $st }}</span></td>
                  <td class="mut">
                    provider: <span class="mono">{{ (string)($p->provider ?? '—') }}</span><br>
                    ref: <span class="mono">{{ (string)($p->reference ?? $p->stripe_session_id ?? '—') }}</span>
                  </td>
                </tr>
              @empty
                <tr><td colspan="6" class="mut" style="padding:14px;">Sin pagos.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

    @elseif($tab==='invoice_requests')
      <div class="table">
        <div class="box" style="margin:16px;">
          <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
            <div style="font-weight:950;">Solicitudes de factura (billing_invoice_requests)</div>
            <div class="mut">requested / issued / rejected</div>
          </div>

          <div style="height:10px;"></div>
          <table class="t">
            <thead>
              <tr>
                <th style="width:80px">ID</th>
                <th>Cuenta/Periodo</th>
                <th style="width:140px">Estatus</th>
                <th>UUID / Notas</th>
                <th style="width:320px" class="tright">Actualizar</th>
              </tr>
            </thead>
            <tbody>
              @forelse($invoiceRequests as $ir)
                @php
                  $s = strtolower((string)($ir->status ?? 'requested'));
                  $pill = $s==='issued' ? 'pill-ok' : ($s==='rejected' ? 'pill-warn' : 'pill-dim');
                @endphp
                <tr>
                  <td class="mono">#{{ (int)($ir->id ?? 0) }}</td>
                  <td class="mut">
                    <div>account: <span class="mono">{{ (string)($ir->account_id ?? '—') }}</span></div>
                    <div>period: <span class="mono">{{ (string)($ir->period ?? '—') }}</span></div>
                  </td>
                  <td><span class="pill {{ $pill }}">{{ strtoupper((string)($ir->status ?? 'REQUESTED')) }}</span></td>
                  <td class="mut">
                    uuid: <span class="mono">{{ (string)($ir->cfdi_uuid ?? '—') }}</span><br>
                    {{ (string)($ir->notes ?? '') }}
                  </td>
                  <td class="tright">
                    <form method="POST" action="{{ route('admin.billing.statements_hub.invoice_status') }}" style="display:grid; gap:8px;">
                      @csrf
                      <input type="hidden" name="id" value="{{ (int)($ir->id ?? 0) }}">
                      <div style="display:grid; grid-template-columns: 1fr 1fr; gap:8px;">
                        <input class="in" name="status" value="{{ (string)($ir->status ?? 'requested') }}" placeholder="requested|issued|rejected">
                        <input class="in" name="cfdi_uuid" value="{{ (string)($ir->cfdi_uuid ?? '') }}" placeholder="UUID (opcional)">
                      </div>
                      <input class="in" name="notes" value="{{ (string)($ir->notes ?? '') }}" placeholder="Notas (opcional)">
                      <button class="btn btn-dark" type="submit">Guardar</button>
                    </form>
                  </td>
                </tr>
              @empty
                <tr><td colspan="5" class="mut" style="padding:14px;">Sin solicitudes.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

    @elseif($tab==='invoices')
      <div class="table">
        <div class="grid2" style="padding:16px;">
          <div class="box">
            <div style="font-weight:950;">Facturas emitidas (billing_invoices)</div>
            <div class="mut" style="margin-top:4px;">Registro administrativo de facturas por account_id + periodo.</div>

            <div style="height:10px;"></div>
            <table class="t">
              <thead>
                <tr>
                  <th style="width:80px">ID</th>
                  <th>Cuenta/Periodo</th>
                  <th>Serie/Folio</th>
                  <th>UUID</th>
                  <th style="width:170px">Fecha</th>
                  <th class="tright" style="width:160px">Monto</th>
                </tr>
              </thead>
              <tbody>
                @forelse($invoices as $inv)
                  @php $amt = (int)($inv->amount_cents ?? 0); @endphp
                  <tr>
                    <td class="mono">#{{ (int)($inv->id ?? 0) }}</td>
                    <td class="mut">
                      <div>account: <span class="mono">{{ (string)($inv->account_id ?? '—') }}</span></div>
                      <div>period: <span class="mono">{{ (string)($inv->period ?? '—') }}</span></div>
                    </td>
                    <td class="mono">{{ (string)($inv->serie ?? '') }}{{ (string)($inv->folio ?? '') ? '-'.(string)$inv->folio : '' }}</td>
                    <td class="mono">{{ (string)($inv->cfdi_uuid ?? '—') }}</td>
                    <td class="mono">{{ (string)($inv->issued_date ?? '—') }}</td>
                    <td class="tright mono">${{ number_format($amt/100,2) }} MXN</td>
                  </tr>
                @empty
                  <tr><td colspan="6" class="mut" style="padding:14px;">Sin facturas registradas.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>

          <div class="box">
            <div style="font-weight:950;">Registrar / actualizar factura</div>
            <div class="mut" style="margin-top:4px;">Si existe solicitud, al guardar se marca como <b>issued</b>.</div>

            <div style="height:12px;"></div>

            <form method="POST" action="{{ route('admin.billing.statements_hub.save_invoice') }}" style="display:grid; gap:8px;">
              @csrf
              <input class="in" name="account_id" value="{{ $accountId }}" placeholder="account_id" required>
              <input class="in" name="period" value="{{ $period }}" placeholder="YYYY-MM" required>

              <div style="display:grid; grid-template-columns: 1fr 1fr; gap:8px;">
                <input class="in" name="serie" placeholder="Serie (opcional)">
                <input class="in" name="folio" placeholder="Folio (opcional)">
              </div>

              <input class="in" name="cfdi_uuid" placeholder="UUID (opcional)">
              <input class="in" name="issued_date" placeholder="Fecha emisión (YYYY-MM-DD)">

              <input class="in" name="amount_mxn" placeholder="Monto MXN (ej. 999.00)">
              <textarea class="in" name="notes" rows="3" placeholder="Notas (opcional)" style="resize:vertical;"></textarea>

              <button class="btn btn-dark" type="submit">Guardar factura</button>
            </form>
          </div>
        </div>
      </div>
    @endif

  </div>
</div>
@endsection
