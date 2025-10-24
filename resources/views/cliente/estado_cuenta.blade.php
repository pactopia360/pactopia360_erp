@extends('cliente.layouts.app')

@section('content')
@php
  use Illuminate\Support\Str;
  use Illuminate\Support\Carbon;

  // Datos de cuenta (verificados desde el controlador)
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

  // Moneda heurística (si la trae algún movimiento)
  $currency = 'MXN';
  if (isset($movs) && count($movs)) {
    foreach ($movs as $__m) { if (!empty($__m->moneda)) { $currency = (string)$__m->moneda; break; } }
  }

  // Helpers
  $fmtDate = function($v){
    if (empty($v)) return '—';
    try { return Carbon::parse($v)->format('Y-m-d'); } catch (\Throwable $e) { return (string)$v; }
  };
  $fmtYm = function($v){
    if (empty($v)) return '—';
    try { return Carbon::parse($v)->format('Y-m'); } catch (\Throwable $e) { return (string)$v; }
  };
  $fmtMoney = fn($n) => number_format((float)($n ?? 0), 2);
@endphp

<div class="p-6 space-y-6">
  {{-- Encabezado --}}
  <div class="flex items-baseline justify-between">
    <div>
      <h1 class="text-2xl font-bold">Estado de cuenta</h1>
      <p class="text-sm text-zinc-500 mt-1">
        {{ $razon ?: 'Tu cuenta' }} {!! $rfc ? '· <span class="font-mono">'.e($rfc).'</span>' : '' !!}
      </p>
    </div>
    <div class="flex items-center gap-2">
      @if ($emailVerified)
        <span class="text-xs px-2 py-1 rounded bg-emerald-100 text-emerald-700">Email verificado</span>
      @else
        <span class="text-xs px-2 py-1 rounded bg-amber-100 text-amber-700">Email sin verificar</span>
      @endif
      @if ($phoneVerified)
        <span class="text-xs px-2 py-1 rounded bg-emerald-100 text-emerald-700">Teléfono verificado</span>
      @else
        <span class="text-xs px-2 py-1 rounded bg-amber-100 text-amber-700">Teléfono sin verificar</span>
      @endif>
    </div>
  </div>

  {{-- Alertas de estado --}}
  @if ($isBlocked || ($estadoTxt && Str::contains(Str::lower($estadoTxt), ['bloqueada','suspendida','pendiente'])))
    <div class="p-4 rounded-lg border border-amber-300 bg-amber-50 text-amber-800">
      <div class="font-medium">Cuenta con restricciones</div>
      <div class="text-sm mt-1">
        Estado: <strong>{{ Str::ucfirst($estadoTxt ?? '—') }}</strong>.
        Si tienes pagos pendientes, puedes regularizar desde el botón “Pagar ahora”.
      </div>
    </div>
  @endif

  {{-- Resumen / KPIs --}}
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <div class="rounded-xl border bg-white p-4">
      <div class="text-xs text-zinc-500">Balance actual</div>
      <div class="mt-1 text-2xl font-semibold tracking-tight">
        {{ $currency }} ${{ $fmtMoney($balance) }}
      </div>
      <div class="mt-1 text-xs text-zinc-400">Con base en los movimientos mostrados</div>
    </div>
    <div class="rounded-xl border bg-white p-4">
      <div class="text-xs text-zinc-500">Plan</div>
      <div class="mt-1 text-lg font-medium">{{ Str::upper($plan) }}</div>
      <div class="text-xs text-zinc-500">Ciclo: {{ Str::of($cycle)->lower()->replace('_',' ')->value() }}</div>
    </div>
    <div class="rounded-xl border bg-white p-4">
      <div class="text-xs text-zinc-500">Próxima facturación</div>
      <div class="mt-1 text-lg font-medium">{{ $fmtDate($nextInvoice) }}</div>
      <div class="text-xs text-zinc-500">Si aplica</div>
    </div>
    <div class="rounded-xl border bg-white p-4">
      <div class="text-xs text-zinc-500">Estado</div>
      <div class="mt-1 text-lg font-medium">
        {{ $estadoTxt ? Str::title($estadoTxt) : '—' }}
      </div>
      <div class="text-xs {{ $isBlocked ? 'text-rose-600' : 'text-emerald-600' }}">
        {{ $isBlocked ? 'Con bloqueo' : 'Activa' }}
      </div>
    </div>
  </div>

  {{-- Acciones --}}
  <div class="flex flex-wrap items-center gap-3">
    @if (Route::has('cliente.billing.statement'))
      <a href="{{ route('cliente.billing.statement') }}"
         class="inline-flex items-center gap-2 rounded-lg bg-zinc-900 px-4 py-2 text-white hover:bg-zinc-800 transition">
        Ver detalle de facturación
      </a>
    @endif

    @if (Route::has('cliente.billing.payPending'))
      <form action="{{ route('cliente.billing.payPending') }}" method="POST"
            onsubmit="return confirm('Continuar con el pago de pendientes?');">
        @csrf
        <button type="submit"
          class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-white hover:bg-emerald-500 transition">
          Pagar ahora
        </button>
      </form>
    @else
      <a href="/pago" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-white hover:bg-emerald-500 transition">
        Pagar ahora
      </a>
    @endif
  </div>

  {{-- Tabla de movimientos --}}
  <div class="overflow-x-auto rounded-xl border bg-white">
    <table class="min-w-full text-sm">
      <thead class="bg-zinc-50">
        <tr class="text-left">
          <th class="p-3 font-semibold">Periodo / Fecha</th>
          <th class="p-3 font-semibold">Concepto</th>
          <th class="p-3 text-right font-semibold">Cargo ({{ $currency }})</th>
          <th class="p-3 text-right font-semibold">Abono ({{ $currency }})</th>
          <th class="p-3 text-right font-semibold">Saldo ({{ $currency }})</th>
        </tr>
      </thead>
      <tbody>
        @forelse($movs as $m)
          @php
            // Periodo flexible: usa periodo; si no, created_at; si no, id
            $periodo = $m->periodo ?? ($m->created_at ?? null);
            $periodoTxt = $periodo ? (Str::contains((string)$periodo, '-') ? $fmtYm($periodo) : (string)$periodo) : '—';
            $cargo = $m->cargo ?? 0;
            $abono = $m->abono ?? 0;
            $saldo = $m->saldo ?? null; // podría venir null si la tabla no tiene
          @endphp
          <tr class="border-t">
            <td class="p-3 align-top">
              <div class="font-medium">{{ $periodoTxt }}</div>
              @if (!empty($m->created_at))
                <div class="text-xs text-zinc-500">{{ $fmtDate($m->created_at) }}</div>
              @endif
            </td>
            <td class="p-3 align-top">
              <div>{{ $m->concepto ?? '—' }}</div>
              @if (!empty($m->detalle))
                <div class="text-xs text-zinc-500 mt-1">{{ $m->detalle }}</div>
              @endif
            </td>
            <td class="p-3 text-right align-top">${{ $fmtMoney($cargo) }}</td>
            <td class="p-3 text-right align-top">${{ $fmtMoney($abono) }}</td>
            <td class="p-3 text-right align-top">
              @if(!is_null($saldo))
                ${{ $fmtMoney($saldo) }}
              @else
                <span class="text-zinc-400">—</span>
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="5" class="p-6 text-center text-zinc-500">Sin movimientos</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  {{-- Tips de ayuda (opcional) --}}
  <div class="text-xs text-zinc-500">
    * Si notas datos incompletos, es posible que tu empresa esté migrando o que aún no existan movimientos en el periodo.
  </div>
</div>
@endsection
