{{-- C:\wamp64\www\pactopia360_erp\resources\views\cliente\auth\verify_email.blade.php --}}
@extends('layouts.cliente-auth')
@section('title','Correo verificado · Pactopia360')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/client/css/verify-flow.css') }}">
@endpush

@php
  /**
   * ✅ AccountId fallback duro:
   * - 1) session('verify.account_id') (ideal)
   * - 2) request()->query('account_id') (si venimos con ?account_id=)
   * - 3) request()->input('account_id') (si venimos por POST redirect)
   * - 4) $account_id (si algún controller lo manda a la vista)
   */
  $verifyAccountId = (int) (session('verify.account_id')
      ?? request()->query('account_id')
      ?? request()->input('account_id')
      ?? ($account_id ?? 0));

  $phoneUrl = $verifyAccountId > 0
      ? route('cliente.verify.phone', ['account_id' => $verifyAccountId])
      : route('cliente.verify.phone');
@endphp

@section('content')
<div class="vf-auth">
  <div class="vf-card" role="region" aria-label="Verificación de correo">

    <div class="vf-pill">
      <span class="vf-dot" aria-hidden="true"></span>
      Verificación de correo
    </div>

    <div class="vf-brand">
      <span class="vf-logo-wrap" aria-label="Pactopia360">
        <img class="vf-logo vf-logo-dark"  src="{{ asset('assets/client/p360-black.png') }}" alt="Pactopia360">
        <img class="vf-logo vf-logo-light" src="{{ asset('assets/client/p360-white.png') }}" alt="Pactopia360">
      </span>

      <button type="button" class="vf-theme" aria-label="Cambiar tema" title="Tema">
        <span aria-hidden="true">☾</span>
      </button>
    </div>

    <div class="vf-center">
      <div class="vf-check" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none">
          <path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>

      <h1 class="vf-title">Correo verificado</h1>

      <p class="vf-sub">
        Continúa con la verificación de tu teléfono para activar tu cuenta.
      </p>

      @if($verifyAccountId <= 0)
        <p class="vf-hint">
          Nota: no detectamos tu sesión de verificación. Si al continuar no se asocia tu cuenta,
          solicita un enlace nuevo.
        </p>
      @endif

      <div class="vf-actions">
        <a class="vf-btn vf-primary" id="btnContinuePhone" href="{{ $phoneUrl }}">
          Continuar con teléfono
          <span class="vf-arrow" aria-hidden="true">→</span>
        </a>

        <a class="vf-btn vf-ghost" href="{{ route('cliente.login') }}">
          Volver al inicio de sesión
        </a>
      </div>

      <div class="vf-help">
        <span>¿Necesitas ayuda?</span>
        <a href="mailto:soporte@pactopia.com">soporte@pactopia.com</a>
      </div>
    </div>

    <div class="vf-note">
      Si no solicitaste este registro, puedes ignorar este mensaje.
    </div>

  </div>
</div>

{{-- Fallback JS: si algún overlay/capa está interceptando clicks, forzamos navegación --}}
@push('scripts')
<script>
(function () {
  'use strict';
  const a = document.getElementById('btnContinuePhone');
  if (!a) return;

  a.addEventListener('click', function (e) {
    // Forzamos navegación estable (evita problemas por capas/handlers)
    e.preventDefault();
    const href = a.getAttribute('href');
    if (href) window.location.href = href;
  }, { passive: false });
})();
</script>
@endpush
@endsection
