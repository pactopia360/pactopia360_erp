{{-- resources/views/admin/billing/statements_hub/index.blade.php (P360 HUB · v1.0 · Estados+Pagos+Emails+Facturas+Tracking) --}}
@extends('layouts.admin')

@section('title', 'Facturación · HUB Estados de Cuenta')
@section('layout', 'full')

@push('styles')
<style>
  .hub-wrap{ padding: 14px 16px 18px; }
  .hub-shell{
    width:100%;
    max-width: 1600px;
    margin: 0 auto;
    background: var(--card-bg);
    border:1px solid var(--card-border);
    border-radius:18px;
    box-shadow: var(--shadow-1);
    overflow:hidden;
  }
  .hub-head{
    padding:14px 14px 10px;
    border-bottom:1px solid rgba(15,23,42,.08);
    display:flex; gap:12px; justify-content:space-between; align-items:flex-start; flex-wrap:wrap;
  }
  .hub-title{ margin:0; font-size:18px; font-weight:950; color:var(--text); }
  .hub-sub{ margin-top:6px; color:var(--muted); font-weight:850; font-size:12px; line-height:1.35; }
  .hub-controls{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
  .hub-in{
    height:40px; padding:0 12px; border-radius:12px;
    border:1px solid rgba(15,23,42,.12);
    background:transparent; color:var(--text);
    font-weight:850;
    min-width: 220px;
  }
  .hub-btn{
    height:40px; padding:0 12px; border-radius:12px;
    border:1px solid rgba(15,23,42,.12);
    background: color-mix(in oklab, var(--card-bg) 92%, transparent);
    color:var(--text); font-weight:950; cursor:pointer; white-space:nowrap;
  }
  .hub-btn.primary{ background:var(--text); color:#fff; border-color:rgba(15,23,42,.22); }
  html[data-theme="dark"] .hub-btn.primary{ background:#111827; border-color:rgba(255,255,255,.12); }

  .hub-tabs{
    padding:10px 14px;
    display:flex; gap:8px; flex-wrap:wrap;
    border-bottom:1px solid rgba(15,23,42,.08);
    background: color-mix(in oklab, var(--card-bg) 94%, transparent);
  }
  .hub-tab{
    display:inline-flex; align-items:center; gap:8px;
    padding:9px 12px;
    border-radius:999px;
    border:1px solid rgba(15,23,42,.12);
    font-weight:950;
    color:var(--muted);
    text-decoration:none;
  }
  .hub-tab.active{ background:rgba(14,165,233,.10); color:var(--text); border-color:rgba(14,165,233,.28); }

  .hub-table{ width:100%; border-collapse:collapse; }
  .hub-table th{
    text-align:left;
    padding:10px 12px;
    font-size:11px;
    color:var(--muted);
    font-weight:950;
    letter-spacing:.04em;
    text-transform:uppercase;
    border-bottom:1px solid rgba(15,23,42,.08);
    background: color-mix(in oklab, var(--card-bg) 96%, transparent);
    position:sticky; top:0; z-index:1;
  }
  .hub-table td{
    padding:12px;
    border-bottom:1px solid rgba(15,23,42,.06);
    vertical-align:top;
    color:var(--text);
    font-weight:850;
  }
  .mono{ font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; font-weight:900; }
  .mut{ color:var(--muted); font-weight:850; font-size:12px; }
  .right{ text-align:right; }

  .pill{
    display:inline-flex; align-items:center; gap:6px;
    padding:6px 10px; border-radius:999px;
    font-weight:950; font-size:12px;
    border:1px solid transparent;
    white-space:nowrap;
  }
  .pill.ok{ background:#dcfce7; color:#166534; border-color:#bbf7d0; }
  .pill.warn{ background:#fef3c7; color:#92400e; border-color:#fde68a; }
  .pill.dim{ background:rgba(15,23,42,.06); color:var(--muted); border-color:rgba(15,23,42,.10); }
  .pill.info{ background:rgba(14,165,233,.10); color:#075985; border-color:rgba(14,165,233,.26); }
  html[data-theme="dark"] .pill.info{ color:#7dd3fc; border-color:rgba(125,211,252,.18); }

  .actions{ display:flex; gap:8px; justify-content:flex-end; flex-wrap:wrap; }
  .btn-mini{
    height:34px; padding:0 10px;
    border-radius:12px;
    border:1px solid rgba(15,23,42,.12);
    background:transparent;
    color:var(--text);
    font-weight:950;
    cursor:pointer;
  }
  .btn-mini.primary{ background:var(--text); color:#fff; }

  .row-title{ font-weight:950; }
  .row-sub{ margin-top:4px; display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
</style>
@endpush

@section('content')
<div class="hub-wrap">
  <div class="hub-shell">
    <div class="hub-head">
      <div>
        <h2 class="hub-title">Facturación · HUB Estados de Cuenta</h2>
        <div class="hub-sub">
          Administra: Estados de cuenta + Pagos + Envíos de correo + Tracking + Solicitudes de factura (por periodo).<br>
          Periodo actual: <span class="mono">{{ $period }}</span>
        </div>
      </div>

      <form class="hub-controls" method="GET" action="{{ route('admin.billing.statements_hub') }}">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <input class="hub-in" name="q" value="{{ $q }}" placeholder="Buscar (ID, RFC, email, razón social)">
        <input class="hub-in" name="period" value="{{ $period }}" style="min-width:120px; width:120px;">
        <select class="hub-in" name="status" style="min-width:160px; width:160px;">
          <option value="" {{ $status===''?'selected':'' }}>Estatus (todos)</option>
          <option value="pendiente" {{ $status==='pendiente'?'selected':'' }}>Pendiente</option>
          <option value="parcial" {{ $status==='parcial'?'selected':'' }}>Parcial</option>
          <option value="pagado" {{ $status==='pagado'?'selected':'' }}>Pagado</option>
          <option value="sin_mov" {{ $status==='sin_mov'?'selected':'' }}>Sin mov.</option>
        </select>
        <label class="mut" style="display:flex;gap:8px;align-items:center;">
          <input type="checkbox" name="pending" value="1" {{ $pending ? 'checked' : '' }}>
          Solo pendientes
        </label>
        <button class="hub-btn primary" type="submit">Filtrar</button>
      </form>
    </div>

    <div class="hub-tabs">
      <a class="hub-tab {{ $tab==='statements'?'active':'' }}"
         href="{{ route('admin.billing.statements_hub', array_merge(request()->all(), ['tab'=>'statements'])) }}">Estados</a>
      <a class="hub-tab {{ $tab==='payments'?'active':'' }}"
         href="{{ route('admin.billing.statements_hub', array_merge(request()->all(), ['tab'=>'payments'])) }}">Pagos</a>
      <a class="hub-tab {{ $tab==='emails'?'active':'' }}"
         href="{{ route('admin.billing.statements_hub', array_merge(request()->all(), ['tab'=>'emails'])) }}">Correos</a>
      <a class="hub-tab {{ $tab==='invoices'?'active':'' }}"
         href="{{ route('admin.billing.statements_hub', array_merge(request()->all(), ['tab'=>'invoices'])) }}">Facturas</a>
    </div>

    @if(session('ok'))
      <div style="padding:10px 14px;">
        <span class="pill ok">{{ session('ok') }}</span>
      </div>
    @endif
    @if($errors->any())
      <div style="padding:10px 14px;">
        <span class="pill warn">{{ $errors->first() }}</span>
      </div>
    @endif

    <div style="max-height: calc(100vh - 260px); overflow:auto;">
      <table class="hub-table">
        <thead>
          <tr>
            <th style="width:90px;">Cuenta</th>
            <th style="min-width:260px;">Cliente</th>
            <th style="min-width:220px;">Email</th>

            @if($tab==='statements')
              <th class="right" style="width:140px;">Esperado</th>
              <th class="right" style="width:140px;">Cargo</th>
              <th class="right" style="width:140px;">Pagado</th>
              <th class="right" style="width:140px;">Saldo</th>
              <th style="width:140px;">Estatus</th>
              <th style="width:360px;">Acciones</th>
            @elseif($tab==='payments')
              <th style="width:140px;">Proveedor</th>
              <th style="min-width:220px;">Sesión</th>
              <th style="min-width:240px;">Liga</th>
              <th style="width:160px;">Creada</th>
              <th style="width:360px;">Acciones</th>
            @elseif($tab==='emails')
              <th style="width:140px;">Último envío</th>
              <th style="min-width:240px;">Destinatario</th>
              <th style="width:120px;">Abiertos</th>
              <th style="width:120px;">Clicks</th>
              <th style="width:180px;">Estado</th>
              <th style="width:360px;">Acciones</th>
            @elseif($tab==='invoices')
              <th style="width:160px;">Solicitada</th>
              <th style="width:160px;">Estatus</th>
              <th style="min-width:280px;">Nota</th>
              <th style="width:360px;">Acciones</th>
            @endif
          </tr>
        </thead>

        <tbody>
          @forelse($rows as $a)
            @php
              $name = trim((string)(($a->razon_social ?? '') ?: ($a->name ?? '') ?: '—'));
              $rfc  = (string)($a->rfc ?? '—');

              $pill = 'dim'; $st = strtoupper((string)$a->status_pago);
              if ($a->status_pago==='pagado') $pill='ok';
              elseif (in_array($a->status_pago,['pendiente','parcial'],true)) $pill='warn';
              elseif ($a->status_pago==='sin_mov') $pill='dim';

              $emailState = $a->email_last?->status ?? null;
              $emailPill = $emailState==='sent' ? 'ok' : ($emailState==='failed' ? 'warn' : 'dim');

              $inv = (string)($a->invoice_status ?? '');
              $invPill = $inv==='sent' || $inv==='ready' ? 'ok' : ($inv==='requested' || $inv==='in_progress' ? 'warn' : 'dim');
            @endphp

            <tr>
              <td class="mono">#{{ $a->id }}</td>

              <td>
                <div class="row-title">{{ $name }}</div>
                <div class="row-sub">
                  <span class="mut">RFC: <span class="mono">{{ $rfc }}</span></span>
                  <span class="pill info">{{ strtoupper((string)($a->plan ?? '')) ?: '—' }}</span>
                  <span class="pill dim">{{ strtoupper((string)($a->billing_cycle ?? $a->modo_cobro ?? '')) ?: '—' }}</span>
                </div>
              </td>

              <td>
                <div class="mono">{{ $a->email ?? '—' }}</div>
                <div class="mut">Periodo: <span class="mono">{{ $a->period }}</span></div>
              </td>

              @if($tab==='statements')
                <td class="right">${{ number_format((float)$a->expected_total, 2) }}</td>
                <td class="right">${{ number_format((float)$a->cargo_total, 2) }}</td>
                <td class="right">${{ number_format((float)$a->abono_total, 2) }}</td>
                <td class="right"><span class="pill {{ ((float)$a->saldo)>0 ? 'warn':'ok' }}">${{ number_format((float)$a->saldo, 2) }}</span></td>
                <td><span class="pill {{ $pill }}">{{ $st }}</span></td>
                <td>
                  <div class="actions">
                    {{-- Liga de pago --}}
                    <form method="POST" action="{{ route('admin.billing.hub.create_pay_link') }}">
                      @csrf
                      <input type="hidden" name="account_id" value="{{ $a->id }}">
                      <input type="hidden" name="period" value="{{ $a->period }}">
                      <button class="btn-mini" type="submit">Crear liga</button>
                    </form>

                    {{-- Enviar correo --}}
                    <form method="POST" action="{{ route('admin.billing.hub.send_email') }}">
                      @csrf
                      <input type="hidden" name="account_id" value="{{ $a->id }}">
                      <input type="hidden" name="period" value="{{ $a->period }}">
                      <button class="btn-mini primary" type="submit">Enviar correo</button>
                    </form>

                    {{-- Factura --}}
                    <form method="POST" action="{{ route('admin.billing.hub.invoice_request') }}">
                      @csrf
                      <input type="hidden" name="account_id" value="{{ $a->id }}">
                      <input type="hidden" name="period" value="{{ $a->period }}">
                      <button class="btn-mini" type="submit">Solicitar factura</button>
                    </form>
                  </div>
                </td>

              @elseif($tab==='payments')
                <td><span class="pill {{ $a->pay_provider ? 'info':'dim' }}">{{ strtoupper((string)($a->pay_provider ?? '—')) }}</span></td>
                <td class="mono">{{ $a->pay_session_id ?? '—' }}</td>
                <td class="mut" style="max-width:360px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                  {{ $a->pay_url ?? '—' }}
                </td>
                <td class="mut">{{ $a->pay_link_created_at ? \Illuminate\Support\Carbon::parse($a->pay_link_created_at)->format('Y-m-d H:i') : '—' }}</td>
                <td>
                  <div class="actions">
                    <form method="POST" action="{{ route('admin.billing.hub.create_pay_link') }}">
                      @csrf
                      <input type="hidden" name="account_id" value="{{ $a->id }}">
                      <input type="hidden" name="period" value="{{ $a->period }}">
                      <button class="btn-mini primary" type="submit">Regenerar liga</button>
                    </form>
                  </div>
                </td>

              @elseif($tab==='emails')
                <td class="mut">{{ $a->email_last?->sent_at ? \Illuminate\Support\Carbon::parse($a->email_last->sent_at)->format('Y-m-d H:i') : '—' }}</td>
                <td class="mono">{{ $a->email_last?->to ?? ($a->last_email_to ?? '—') }}</td>
                <td><span class="pill {{ ((int)$a->open_count)>0 ? 'ok':'dim' }}">{{ (int)$a->open_count }}</span></td>
                <td><span class="pill {{ ((int)$a->click_count)>0 ? 'ok':'dim' }}">{{ (int)$a->click_count }}</span></td>
                <td><span class="pill {{ $emailPill }}">{{ strtoupper((string)($emailState ?? '—')) }}</span></td>
                <td>
                  <div class="actions">
                    <form method="POST" action="{{ route('admin.billing.hub.send_email') }}">
                      @csrf
                      <input type="hidden" name="account_id" value="{{ $a->id }}">
                      <input type="hidden" name="period" value="{{ $a->period }}">
                      <button class="btn-mini primary" type="submit">Reenviar</button>
                    </form>
                  </div>
                </td>

              @elseif($tab==='invoices')
                <td class="mut">{{ $a->invoice_requested_at ? \Illuminate\Support\Carbon::parse($a->invoice_requested_at)->format('Y-m-d H:i') : '—' }}</td>
                <td><span class="pill {{ $invPill }}">{{ strtoupper($inv ?: '—') }}</span></td>
                <td class="mut">{{ $a->invoice_note ?? '—' }}</td>
                <td>
                  <div class="actions">
                    <form method="POST" action="{{ route('admin.billing.hub.invoice_status') }}">
                      @csrf
                      <input type="hidden" name="account_id" value="{{ $a->id }}">
                      <input type="hidden" name="period" value="{{ $a->period }}">
                      <input type="hidden" name="invoice_status" value="in_progress">
                      <button class="btn-mini" type="submit">En proceso</button>
                    </form>
                    <form method="POST" action="{{ route('admin.billing.hub.invoice_status') }}">
                      @csrf
                      <input type="hidden" name="account_id" value="{{ $a->id }}">
                      <input type="hidden" name="period" value="{{ $a->period }}">
                      <input type="hidden" name="invoice_status" value="ready">
                      <button class="btn-mini" type="submit">Lista</button>
                    </form>
                    <form method="POST" action="{{ route('admin.billing.hub.invoice_status') }}">
                      @csrf
                      <input type="hidden" name="account_id" value="{{ $a->id }}">
                      <input type="hidden" name="period" value="{{ $a->period }}">
                      <input type="hidden" name="invoice_status" value="sent">
                      <button class="btn-mini primary" type="submit">Enviada</button>
                    </form>
                  </div>
                </td>
              @endif
            </tr>
          @empty
            <tr>
              <td colspan="12" class="mut" style="padding:14px;">No hay resultados.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div style="padding:12px 14px;">
      {{ $rows->links() }}
    </div>
  </div>
</div>
@endsection
