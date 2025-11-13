{{-- resources/views/cliente/estado_cuenta.blade.php (v4 visual Pactopia360) --}}
@extends('layouts.cliente')
@section('title','Estado de cuenta · Pactopia360')

@push('styles')
<style>
/* ============================================================
   PACTOPIA360 · Estado de cuenta (visual 4.0)
   ============================================================ */
.estado-wrap{
  font-family:'Poppins',system-ui,sans-serif;
  --rose:#E11D48;--rose-dark:#BE123C;
  --mut:#6b7280;--border:#f3d5dc;--card:#fff;--bg:#fff8f9;
  color:#0f172a;display:grid;gap:20px;padding:20px;
}
html[data-theme="dark"] .estado-wrap{
  --card:#0b1220;--border:#2b2f36;--bg:#0e172a;--mut:#a5adbb;color:#e5e7eb;
}
.header{
  background:linear-gradient(90deg,#E11D48,#BE123C);
  color:#fff;padding:18px 22px;border-radius:16px;
  box-shadow:0 8px 22px rgba(225,29,72,.25);
  display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;
}
.header h1{margin:0;font-weight:900;font-size:22px;}
.header .sub{font-size:13px;opacity:.85;}
.badge{
  display:inline-flex;align-items:center;gap:6px;
  padding:6px 10px;border-radius:999px;font-weight:800;font-size:12px;
}
.badge.ok{background:#ecfdf5;color:#047857;}
.badge.warn{background:#fef3c7;color:#92400e;}
.kpis{display:grid;gap:14px;}
@media(min-width:900px){.kpis{grid-template-columns:repeat(4,1fr);}}
.kpi{
  background:var(--card);border:1px solid var(--border);border-radius:14px;
  padding:14px;box-shadow:0 4px 14px rgba(225,29,72,.06);
}
.kpi small{color:var(--mut);font-weight:700;font-size:12px;}
.kpi b{font:800 22px/1.1 'Poppins';}
.table-wrap{
  background:var(--card);border:1px solid var(--border);border-radius:14px;
  overflow:auto;box-shadow:0 6px 22px rgba(225,29,72,.06);
}
table{width:100%;border-collapse:collapse;font-size:14px;}
thead{background:#fff0f3;}
th,td{padding:10px 14px;border-bottom:1px solid var(--border);}
th{text-align:left;font-weight:900;color:var(--rose);}
td.align-top{vertical-align:top;}
tr:hover td{background:#fffafc;}
.btn{
  display:inline-flex;align-items:center;gap:8px;
  border-radius:10px;font-weight:800;padding:10px 16px;font-size:14px;
  cursor:pointer;text-decoration:none;transition:.15s all ease;
}
.btn.dark{background:#0f172a;color:#fff;}
.btn.dark:hover{background:#1e293b;}
.btn.primary{
  background:linear-gradient(90deg,#E11D48,#BE123C);
  color:#fff;box-shadow:0 6px 18px rgba(225,29,72,.25);
}
.btn.primary:hover{filter:brightness(.96);}
.alert{
  border:1px solid #facc15;background:#fef9c3;color:#78350f;
  border-radius:12px;padding:12px 14px;
}
.note{color:var(--mut);font-size:12px;}
</style>
@endpush

@section('content')
@php
  use Illuminate\Support\Str;
  use Illuminate\Support\Carbon;

  $acc   = $account ?? [];
  $rfc   = $acc['rfc'] ?? null;
  $razon = $acc['razon_social'] ?? null;

  $emailVerified = $acc['email_verified'] ?? false;
  $phoneVerified = $acc['phone_verified'] ?? false;
  $isBlocked     = $acc['is_blocked'] ?? false;
  $estadoTxt     = $acc['estado_cuenta'] ?? null;
  $plan          = $acc['plan'] ?? '—';
  $cycle         = $acc['billing_cycle'] ?? '—';
  $nextInvoice   = $acc['next_invoice_at'] ?? null;

  $currency = 'MXN';
  if (isset($movs) && count($movs)) {
    foreach ($movs as $__m) { if (!empty($__m->moneda)) { $currency = (string)$__m->moneda; break; } }
  }

  $fmtDate = fn($v)=>!$v?'—':(Carbon::parse($v)->format('Y-m-d'));
  $fmtYm   = fn($v)=>!$v?'—':(Carbon::parse($v)->format('Y-m'));
  $fmtMoney= fn($n)=>number_format((float)($n??0),2);
@endphp

<div class="estado-wrap">
  {{-- Header --}}
  <div class="header">
    <div>
      <h1>Estado de cuenta</h1>
      <div class="sub">{{ $razon ?: 'Tu cuenta' }} {!! $rfc ? '· <span class="font-mono">'.e($rfc).'</span>' : '' !!}</div>
    </div>
    <div class="flex flex-wrap gap-2">
      <span class="badge {{ $emailVerified?'ok':'warn' }}">{{ $emailVerified?'Email verificado':'Email sin verificar' }}</span>
      <span class="badge {{ $phoneVerified?'ok':'warn' }}">{{ $phoneVerified?'Teléfono verificado':'Teléfono sin verificar' }}</span>
    </div>
  </div>

  {{-- Alerta de bloqueo --}}
  @if ($isBlocked || ($estadoTxt && Str::contains(Str::lower($estadoTxt), ['bloqueada','suspendida','pendiente'])))
    <div class="alert">
      <b>⚠️ Cuenta con restricciones</b><br>
      Estado: <strong>{{ Str::ucfirst($estadoTxt ?? '—') }}</strong>.
      Si tienes pagos pendientes, puedes regularizar desde <b>Pagar ahora</b>.
    </div>
  @endif

  {{-- KPIs --}}
  <div class="kpis">
    <div class="kpi"><small>Balance actual</small><b>{{ $currency }} ${{ $fmtMoney($balance) }}</b><small>Con base en movimientos</small></div>
    <div class="kpi"><small>Plan</small><b>{{ Str::upper($plan) }}</b><small>Ciclo: {{ Str::of($cycle)->lower()->replace('_',' ') }}</small></div>
    <div class="kpi"><small>Próxima facturación</small><b>{{ $fmtDate($nextInvoice) }}</b><small>Si aplica</small></div>
    <div class="kpi"><small>Estado</small><b>{{ $estadoTxt ? Str::title($estadoTxt) : '—' }}</b>
      <small class="{{ $isBlocked?'text-rose-600':'text-emerald-600' }}">{{ $isBlocked?'Con bloqueo':'Activa' }}</small></div>
  </div>

  {{-- Acciones --}}
  <div class="flex flex-wrap gap-3">
    @if (Route::has('cliente.billing.statement'))
      <a href="{{ route('cliente.billing.statement') }}" class="btn dark">Ver detalle de facturación</a>
    @endif

    @if (Route::has('cliente.billing.payPending'))
      <form action="{{ route('cliente.billing.payPending') }}" method="POST"
            onsubmit="return confirm('¿Continuar con el pago de pendientes?');">
        @csrf
        <button type="submit" class="btn primary">Pagar ahora</button>
      </form>
    @else
      <a href="/pago" class="btn primary">Pagar ahora</a>
    @endif
  </div>

  {{-- Tabla --}}
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Periodo / Fecha</th><th>Concepto</th>
          <th class="text-right">Cargo ({{ $currency }})</th>
          <th class="text-right">Abono ({{ $currency }})</th>
          <th class="text-right">Saldo ({{ $currency }})</th>
        </tr>
      </thead>
      <tbody>
        @forelse($movs as $m)
          @php
            $periodo = $m->periodo ?? ($m->created_at ?? null);
            $periodoTxt = $periodo ? (Str::contains((string)$periodo,'-') ? $fmtYm($periodo) : (string)$periodo) : '—';
          @endphp
          <tr>
            <td class="align-top">
              <div class="font-semibold">{{ $periodoTxt }}</div>
              @if(!empty($m->created_at))
                <div class="note">{{ $fmtDate($m->created_at) }}</div>
              @endif
            </td>
            <td class="align-top">
              <div>{{ $m->concepto ?? '—' }}</div>
              @if(!empty($m->detalle))
                <div class="note">{{ $m->detalle }}</div>
              @endif
            </td>
            <td class="text-right align-top">${{ $fmtMoney($m->cargo ?? 0) }}</td>
            <td class="text-right align-top">${{ $fmtMoney($m->abono ?? 0) }}</td>
            <td class="text-right align-top">
              @if(!is_null($m->saldo))
                ${{ $fmtMoney($m->saldo) }}
              @else
                <span class="note">—</span>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="5" class="text-center note p-4">Sin movimientos</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="note">* Si notas datos incompletos, tu empresa puede estar en migración o sin movimientos en el periodo.</div>
</div>
@endsection
