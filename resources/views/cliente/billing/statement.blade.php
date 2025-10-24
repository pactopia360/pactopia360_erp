{{-- resources/views/cliente/billing/statement.blade.php --}}
@extends('layouts.cliente')

@section('title', 'Pagos y suscripción · Pactopia360')

@push('styles')
<style>
  .kv{ display:grid; grid-template-columns:minmax(120px,180px) 1fr; gap:10px; }
  .kv .k{ color:var(--muted); font-weight:800; }
  .cta{ display:flex; flex-wrap:wrap; gap:10px }

  table{ width:100%; border-collapse:collapse; overflow:hidden; border-radius:12px; border:1px solid var(--border); }
  th,td{ padding:10px 12px; border-bottom:1px solid var(--border); text-align:left; }
  thead th{ font-size:12px; color:var(--muted); letter-spacing:.2px; text-transform:uppercase }
  tbody tr:hover{ background:color-mix(in oklab, var(--card) 92%, transparent) }

  .badge{ display:inline-block; border-radius:999px; padding:4px 10px; font-weight:800; font-size:12px }
  .badge.ok{ background:color-mix(in oklab, var(--success, #16a34a) 22%, transparent); border:1px solid color-mix(in oklab, var(--success, #16a34a) 44%, transparent) }
  .badge.warn{ background:color-mix(in oklab, var(--warning, #f59e0b) 20%, transparent); border:1px solid color-mix(in oklab, var(--warning, #f59e0b) 40%, transparent) }
  .badge.err{ background:color-mix(in oklab, var(--danger, #ef4444) 20%, transparent); border:1px solid color-mix(in oklab, var(--danger, #ef4444) 40%, transparent) }

  .muted{ color:var(--muted) }
</style>
@endpush

@section('content')
@php
  /** @var \stdClass|null $account */
  /** @var \stdClass|null $subscription */
  /** @var \Illuminate\Support\Collection $pending */
  /** @var \Illuminate\Support\Collection $recentPaid */

  $u  = auth('web')->user();
  $c  = $u?->cuenta;

  $planLabel      = strtoupper(($c->plan_actual ?? $account->plan_actual ?? 'FREE'));
  $modoCobro      = $account->modo_cobro ?? $c->modo_cobro ?? '—';
  $rfcView        = $account->rfc ?? $c->rfc_padre ?? '—';
  $emailView      = $account->email ?? $u->email ?? '—';

  $isBlocked      = (bool)($account->is_blocked ?? false);
  $isPastDue      = (isset($subscription->status) && $subscription->status === 'past_due');
  $statusText     = $isBlocked ? 'Bloqueada' : ($isPastDue ? 'Pago pendiente' : 'Activa');
  $statusClass    = $isBlocked ? 'warn' : ($isPastDue ? 'warn' : 'ok');

  $accountId      = $accountId ?? ($account->id ?? null);

  // Precios que vienen desde el controlador; si faltan, fallback:
  $displayMonthly = isset($displayMonthly) ? $displayMonthly : (config('services.stripe.display_price_monthly', 249.99));
  $displayAnnual  = isset($displayAnnual)  ? $displayAnnual  : (config('services.stripe.display_price_annual', 1999.99));
@endphp

<div class="grid grid-2">
  <div class="card">
    <h2>Tu suscripción</h2>
    <div class="kv" style="margin-top:6px">
      <div class="k">Plan</div><div><strong>{{ $planLabel }}</strong></div>
      <div class="k">Modo de cobro</div><div>{{ $modoCobro }}</div>
      <div class="k">Estado</div><div><span class="badge {{ $statusClass }}">{{ $statusText }}</span></div>
      <div class="k">RFC</div><div>{{ $rfcView }}</div>
      <div class="k">Correo</div><div>{{ $emailView }}</div>
    </div>

    <hr style="border-color:var(--border);margin:14px 0">

    <div class="cta">
      {{-- CTA Stripe Checkout (mensual) --}}
      <form method="POST" action="{{ route('cliente.checkout.pro.monthly') }}">
        @csrf
        <input type="hidden" name="account_id" value="{{ $accountId }}">
        <button class="btn primary"
                @if(!$accountId) disabled title="Cuenta no identificada" @endif>
          Pagar Mensual · ${{ number_format($displayMonthly, 2) }} MXN
        </button>
      </form>

      {{-- CTA Stripe Checkout (anual) --}}
      <form method="POST" action="{{ route('cliente.checkout.pro.annual') }}">
        @csrf
        <input type="hidden" name="account_id" value="{{ $accountId }}">
        <button class="btn"
                @if(!$accountId) disabled title="Cuenta no identificada" @endif>
          Pagar Anual · ${{ number_format($displayAnnual, 2) }} MXN
        </button>
      </form>

      {{-- Pagar pendientes internos (si aplicara) --}}
      @if(($totalDue ?? 0) > 0)
        <form method="POST" action="{{ route('cliente.billing.payPending') }}">
          @csrf
          <button class="btn">Pagar pendientes ({{ number_format($totalDue,2) }} MXN)</button>
        </form>
      @endif
    </div>

    <p class="muted" style="margin-top:8px">
      Serás redirigido a Stripe Checkout. Tras el pago, tu cuenta se activa automáticamente.
    </p>
  </div>

  <div class="card">
    <h2>Tus límites y uso</h2>
    <div class="grid grid-3" style="margin-top:6px">
      <div class="stat">
        <div class="muted">Usuarios permitidos</div>
        <div style="font-weight:900;font-size:20px">
          @php $maxU = $c->max_usuarios ?? 0; @endphp
          {{ $maxU ? $maxU : 'Ilimitado' }}
        </div>
      </div>
      <div class="stat">
        <div class="muted">Timbres (usados / asignados)</div>
        <div style="font-weight:900;font-size:20px">
          {{ (int)($c->hits_usados ?? 0) }} / {{ (int)($c->hits_asignados ?? 0) }}
        </div>
      </div>
      <div class="stat">
        <div class="muted">Almacenamiento</div>
        <div style="font-weight:900;font-size:20px">
          {{ (int)($c->espacio_usado_mb ?? 0) }} / {{ (int)($c->espacio_asignado_mb ?? 0) }} MB
        </div>
      </div>
    </div>

    @if(($c->plan_actual ?? 'FREE') === 'PRO')
      <div class="stat" style="margin-top:12px">
        <div class="muted">Facturación masiva hoy</div>
        <div style="font-weight:900;font-size:18px">
          {{ (int)($c->mass_invoices_used_today ?? 0) }} / {{ (int)($c->max_mass_invoices_per_day ?? 0) }}
        </div>
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
            <td style="font-family:ui-monospace,monospace">{{ $p->reference }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
</div>
@endsection
