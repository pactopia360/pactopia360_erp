{{-- resources/views/cliente/auth/redirect_checkout.blade.php --}}
@extends('layouts.guest')

@section('title', 'Redirigiendo a pago · Pactopia360')

@section('content')
@php
  $plan = $plan ?? 'mensual';
  $accountId = $accountId ?? null;
  $email = $email ?? null;

  $action = $plan === 'anual'
    ? route('cliente.checkout.pro.annual')
    : route('cliente.checkout.pro.monthly');
@endphp

<div style="min-height:70vh;display:flex;align-items:center;justify-content:center;padding:24px;">
  <div style="max-width:520px;width:100%;background:#fff;border-radius:16px;padding:24px;box-shadow:0 10px 30px rgba(0,0,0,.08);">
    <h1 style="margin:0 0 8px;font-size:18px;">Redirigiendo a Stripe…</h1>
    <p style="margin:0 0 16px;color:#444;line-height:1.4;">
      Estamos iniciando tu proceso de pago para activar tu plan PRO.
      Si no avanza automáticamente, usa el botón de abajo.
    </p>

    @if(!$accountId)
      <div style="background:#fff5f5;border:1px solid #ffd6d6;color:#a40000;padding:10px;border-radius:10px;margin-bottom:14px;">
        Falta <strong>account_id</strong> para iniciar el checkout.
      </div>
    @endif

    <form id="checkoutForm" method="POST" action="{{ $action }}">
      @csrf
      <input type="hidden" name="account_id" value="{{ $accountId }}">
      @if($email)
        <input type="hidden" name="email" value="{{ $email }}">
      @endif

      <button type="submit"
              style="width:100%;padding:12px 14px;border-radius:12px;border:0;background:#111;color:#fff;font-weight:600;cursor:pointer;">
        Continuar a pago
      </button>
    </form>

    <div style="margin-top:10px;color:#666;font-size:12px;">
      Plan seleccionado: <strong>{{ $plan === 'anual' ? 'Anual' : 'Mensual' }}</strong>
    </div>
  </div>
</div>

<script>
  (function () {
    const form = document.getElementById('checkoutForm');
    if (!form) return;
    // Auto-submit inmediato (sin esperar interacción)
    form.submit();
  })();
</script>
@endsection
