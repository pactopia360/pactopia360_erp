{{-- C:\wamp64\www\pactopia360_erp\resources\views\cliente\auth\verify_email.blade.php (v2 · flow-safe) --}}
@extends('layouts.cliente-auth')
@section('title','Verificación de correo · Pactopia360')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/client/css/verify-flow.css') }}">
@endpush

@php
  use Illuminate\Support\Facades\Route;

  /**
   * Inputs del Controller (cuando aplique):
   * - status: invalid|expired|ok|null
   * - message: string|null
   * - email: string|null
   * - phone_masked: string|null
   */
  $status  = (string) ($status ?? request()->query('status', 'ok'));
  $message = (string) ($message ?? '');

  // ✅ AccountId fallback duro:
  $verifyAccountId = (int) (
      session('verify.account_id')
      ?? request()->query('account_id')
      ?? request()->input('account_id')
      ?? ($account_id ?? 0)
  );

  // URLs seguras
  $resendUrl = Route::has('cliente.verify.email.resend')
      ? route('cliente.verify.email.resend', $verifyAccountId > 0 ? ['account_id' => $verifyAccountId] : [])
      : url('/cliente/verificar/email/reenviar');

  $phoneUrl = Route::has('cliente.verify.phone')
      ? route('cliente.verify.phone', ['account_id' => $verifyAccountId > 0 ? $verifyAccountId : null])
      : url('/cliente/verificar/telefono' . ($verifyAccountId > 0 ? ('?account_id='.$verifyAccountId) : ''));

  // Normaliza: route() con array que tiene null genera query rara en algunos casos
  if ($verifyAccountId <= 0) {
    // si no hay account_id, NO mandamos a teléfono (no tiene cómo resolver)
    $phoneUrl = $resendUrl;
  }

  $isInvalid = in_array($status, ['invalid','expired'], true);
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

      {{-- Icono --}}
      <div class="vf-check" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none">
          <path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>

      @if($isInvalid)
        <h1 class="vf-title">
          {{ $status === 'expired' ? 'Enlace expirado' : 'Enlace no válido' }}
        </h1>

        <p class="vf-sub">
          {{ $message !== '' ? $message : 'El enlace no es válido o ya fue usado. Solicita uno nuevo.' }}
        </p>

        <div class="vf-actions">
          <a class="vf-btn vf-primary" href="{{ $resendUrl }}">
            Solicitar enlace nuevo
            <span class="vf-arrow" aria-hidden="true">→</span>
          </a>

          <a class="vf-btn vf-ghost" href="{{ route('cliente.login') }}">
            Volver al inicio de sesión
          </a>
        </div>

      @else
        <h1 class="vf-title">Correo verificado</h1>

        <p class="vf-sub">
          Continúa con la verificación de tu teléfono para activar tu cuenta.
        </p>

        @if($verifyAccountId <= 0)
          <p class="vf-hint">
            Nota: no detectamos tu sesión de verificación. Para continuar necesitamos asociar tu cuenta.
            Solicita un enlace nuevo (o abre el enlace desde el mismo navegador donde te registraste).
          </p>
        @endif

        <div class="vf-actions">
          <a class="vf-btn vf-primary" id="btnContinuePhone" href="{{ $phoneUrl }}">
            {{ $verifyAccountId > 0 ? 'Continuar con teléfono' : 'Solicitar enlace nuevo' }}
            <span class="vf-arrow" aria-hidden="true">→</span>
          </a>

          <a class="vf-btn vf-ghost" href="{{ route('cliente.login') }}">
            Volver al inicio de sesión
          </a>
        </div>
      @endif

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
