{{-- resources/views/cliente/billing/statement.blade.php (v2 visual Pactopia360) --}}
@extends('layouts.cliente')

@section('title', 'Pagos y suscripción · Pactopia360')

@push('styles')
<style>
  body{font-family:'Poppins',system-ui,sans-serif;}

  .page-wrap{
    display:grid;
    gap:18px;
    padding-bottom:40px;
  }

  .card{
    background:linear-gradient(180deg,rgba(255,255,255,.96),rgba(255,255,255,.9));
    border:1px solid #f3d5dc;
    border-radius:18px;
    padding:20px 22px 22px;
    box-shadow:0 8px 28px rgba(225,29,72,.08);
    position:relative;
  }
  .card::before{
    content:"";position:absolute;inset:-1px;border-radius:19px;padding:1px;
    background:linear-gradient(145deg,#E11D48,#BE123C);
    -webkit-mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);
    -webkit-mask-composite:xor;mask-composite:exclude;
    opacity:.25;pointer-events:none;
  }

  h2{
    margin:0 0 8px;
    font-weight:900;
    color:#E11D48;
    letter-spacing:.2px;
    font-size:17px;
  }

  .grid-2{display:grid;gap:18px;}
  @media(min-width:1020px){.grid-2{grid-template-columns:1fr 1fr;}}

  .kv{display:grid;grid-template-columns:minmax(120px,180px) 1fr;gap:10px;}
  .kv .k{color:#6b7280;font-weight:800;font-size:13px;}
  .kv .v{font-weight:700;color:#0f172a;}

  .cta{display:flex;flex-wrap:wrap;gap:10px;margin-top:10px;}
  .btn{
    display:inline-flex;align-items:center;justify-content:center;
    padding:11px 16px;border-radius:10px;font-weight:800;font-size:14px;
    cursor:pointer;border:0;transition:.15s filter ease;text-decoration:none;
  }
  .btn.primary{
    color:#fff;background:linear-gradient(90deg,#E11D48,#BE123C);
    box-shadow:0 10px 22px rgba(225,29,72,.25);
  }
  .btn.primary:hover{filter:brightness(.97);}
  .btn.secondary{
    background:#fff;border:1px solid #f3d5dc;color:#E11D48;
  }
  .btn.secondary:hover{background:#fff0f3;}
  .muted{color:#6b7280;font-size:13px;}

  table{
    width:100%;
    border-collapse:collapse;
    margin-top:10px;
    border-radius:10px;
    overflow:hidden;
    border:1px solid #f1d3da;
  }
  th,td{padding:10px 12px;border-bottom:1px solid #f3d5dc;text-align:left;}
  thead th{font-size:12px;color:#6b7280;letter-spacing:.15em;text-transform:uppercase;}
  tbody tr:hover{background:#fff5f7;}

  .badge{
    display:inline-block;
    border-radius:999px;
    padding:4px 10px;
    font-weight:800;
    font-size:12px;
  }
  .badge.ok{background:#ecfdf5;border:1px solid #a7f3d0;color:#047857;}
  .badge.warn{background:#fefce8;border:1px solid #fde68a;color:#b45309;}
  .badge.err{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;}

  .stat{display:grid;gap:4px;}
  .stat .muted{font-size:12px;color:#6b7280;}
  .stat .val{font-weight:900;font-size:20px;color:#0f172a;}

  hr{border:0;border-top:1px solid #f3d5dc;margin:14px 0;}
</style>
@endpush

@section('content')
@php
  $u  = auth('web')->user();
  $c  = $u?->cuenta;

  // summary viene del controlador (misma lógica que Home/Perfil/EstadoCuenta)
  $summaryArr = $summary ?? null;

  // Plan y ciclo: priorizar admin.accounts (plan / billing_cycle) y luego espejo
  $planLabel = strtoupper(
      $summaryArr['plan'] ?? ($account->plan ?? ($c->plan_actual ?? 'FREE'))
  );

  $modoCobro = $summaryArr['cycle']
      ?? ($account->billing_cycle ?? ($c->modo_cobro ?? '—'));

  $rfcView   = $account->rfc ?? $c->rfc_padre ?? '—';
  $emailView = $account->email ?? $u->email ?? '—';

  $isBlocked = (bool)($account->is_blocked ?? false);
  $isPastDue = (isset($subscription->status) && $subscription->status === 'past_due');

  $statusText  = $isBlocked ? 'Bloqueada' : ($isPastDue ? 'Pago pendiente' : 'Activa');
  $statusClass = $isBlocked ? 'warn' : ($isPastDue ? 'warn' : 'ok');

  $accountId   = $accountId ?? ($account->id ?? null);

  $displayMonthly = $displayMonthly ?? config('services.stripe.display_price_monthly',990.00);
  $displayAnnual  = $displayAnnual  ?? config('services.stripe.display_price_annual',9990.00);
@endphp

<div class="page-wrap">

  <div class="grid-2">
    <div class="card">
      <h2>Tu suscripción</h2>
      <div class="kv">
        <div class="k">Plan</div><div class="v">{{ $planLabel }}</div>
        <div class="k">Modo de cobro</div><div>{{ $modoCobro }}</div>
        <div class="k">Estado</div><div><span class="badge {{ $statusClass }}">{{ $statusText }}</span></div>
        <div class="k">RFC</div><div>{{ $rfcView }}</div>
        <div class="k">Correo</div><div>{{ $emailView }}</div>
      </div>

      <hr>

      <div class="cta">
        {{-- Pago mensual --}}
        <form method="POST" action="{{ route('cliente.checkout.pro.monthly') }}">
          @csrf
          <input type="hidden" name="account_id" value="{{ $accountId }}">
          <button class="btn primary" @if(!$accountId) disabled title="Cuenta no identificada" @endif>
            Pagar Mensual · ${{ number_format($displayMonthly, 2) }} MXN
          </button>
        </form>

        {{-- Pago anual --}}
        <form method="POST" action="{{ route('cliente.checkout.pro.annual') }}">
          @csrf
          <input type="hidden" name="account_id" value="{{ $accountId }}">
          <button class="btn secondary" @if(!$accountId) disabled title="Cuenta no identificada" @endif>
            Pagar Anual · ${{ number_format($displayAnnual, 2) }} MXN
          </button>
        </form>

        @if(($totalDue ?? 0) > 0)
          <form method="POST" action="{{ route('cliente.billing.payPending') }}">
            @csrf
            <button class="btn secondary">Pagar pendientes ({{ number_format($totalDue,2) }} MXN)</button>
          </form>
        @endif
      </div>

      <p class="muted" style="margin-top:8px;">
        Serás redirigido a Stripe Checkout. Tras el pago, tu cuenta se activa automáticamente.
      </p>
    </div>

    <div class="card">
      <h2>Tus límites y uso</h2>
      <div class="grid" style="margin-top:6px;">
        <div class="stat">
          <div class="muted">Usuarios permitidos</div>
          <div class="val">{{ ($c->max_usuarios ?? 0) ?: 'Ilimitado' }}</div>
        </div>
        <div class="stat">
          <div class="muted">Timbres (usados / asignados)</div>
          <div class="val">{{ (int)($c->hits_usados ?? 0) }} / {{ (int)($c->hits_asignados ?? 0) }}</div>
        </div>
        <div class="stat">
          <div class="muted">Almacenamiento</div>
          <div class="val">{{ (int)($c->espacio_usado_mb ?? 0) }} / {{ (int)($c->espacio_asignado_mb ?? 0) }} MB</div>
        </div>
      </div>

      @if(($c->plan_actual ?? 'FREE') === 'PRO')
        <div class="stat" style="margin-top:12px;">
          <div class="muted">Facturación masiva hoy</div>
          <div class="val">{{ (int)($c->mass_invoices_used_today ?? 0) }} / {{ (int)($c->max_mass_invoices_per_day ?? 0) }}</div>
        </div>
      @endif
    </div>
  </div>

  <div class="card">
    <h2>Pagos pendientes</h2>
    @if(($pending ?? collect())->isEmpty())
      <p class="muted">No tienes pagos pendientes.</p>
    @else
      <table>
        <thead>
          <tr><th>Concepto</th><th>Monto</th><th>Vence</th><th>Estatus</th></tr>
        </thead>
        <tbody>
          @foreach($pending as $p)
            <tr>
              <td>{{ $p->concepto ?? 'Suscripción PRO' }}</td>
              <td>${{ number_format($p->amount, 2) }} {{ strtoupper($p->currency ?? 'MXN') }}</td>
              <td>{{ $p->due_date ? \Illuminate\Support\Carbon::parse($p->due_date)->format('d/M/Y') : '—' }}</td>
              <td>{{ strtoupper($p->status ?? 'pending') }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </div>

  <div class="card">
    <h2>Últimos pagos</h2>
    @if(($recentPaid ?? collect())->isEmpty())
      <p class="muted">Aún no hay pagos registrados.</p>
    @else
      <table>
        <thead>
          <tr><th>Fecha</th><th>Monto</th><th>Método</th><th>Ref</th></tr>
        </thead>
        <tbody>
          @foreach($recentPaid as $p)
            <tr>
              <td>{{ \Illuminate\Support\Carbon::parse($p->created_at)->format('d/M/Y H:i') }}</td>
              <td>${{ number_format($p->amount, 2) }} {{ strtoupper($p->currency ?? 'MXN') }}</td>
              <td>{{ strtoupper($p->method ?? 'stripe') }}</td>
              <td style="font-family:ui-monospace,monospace">
                {{ $p->reference ?? $p->stripe_id ?? $p->payment_intent ?? $p->id ?? '-' }}
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </div>

</div>
@endsection
