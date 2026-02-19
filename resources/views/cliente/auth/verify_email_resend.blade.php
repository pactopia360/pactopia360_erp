{{-- C:\wamp64\www\pactopia360_erp\resources\views\cliente\auth\verify_email_resend.blade.php (v1 · form-safe) --}}
@extends('layouts.cliente-auth')
@section('title','Solicitar enlace de verificación · Pactopia360')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/client/css/verify-flow.css') }}">
@endpush

@php
  use Illuminate\Support\Facades\Route;

  $emailPrefill = old('email', (string) ($email ?? request()->query('email', '')));
  $resendDoUrl  = Route::has('cliente.verify.email.resend.do')
      ? route('cliente.verify.email.resend.do')
      : url('/cliente/verificar/email/reenviar');

  $loginUrl = Route::has('cliente.login') ? route('cliente.login') : url('/cliente/login');
@endphp

@section('content')
<div class="vf-auth">
  <div class="vf-card" role="region" aria-label="Solicitar enlace de verificación">

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

      <h1 class="vf-title">Solicitar enlace nuevo</h1>
      <p class="vf-sub">
        Ingresa tu correo y te enviaremos un nuevo enlace de verificación.
      </p>

      {{-- Flash OK --}}
      @if(session('ok'))
        <div class="vf-alert vf-alert-ok" role="status" style="margin:14px 0;">
          {{ session('ok') }}
        </div>
      @endif

      {{-- Flash info --}}
      @if(session('info'))
        <div class="vf-alert vf-alert-info" role="status" style="margin:14px 0;">
          {{ session('info') }}
        </div>
      @endif

      {{-- Errores --}}
      @if($errors->any())
        <div class="vf-alert vf-alert-err" role="alert" style="margin:14px 0;">
          <strong>Revisa lo siguiente:</strong>
          <ul style="margin:8px 0 0 18px;">
            @foreach($errors->all() as $e)
              <li>{{ $e }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <form class="vf-form" method="POST" action="{{ $resendDoUrl }}" autocomplete="on" novalidate>
        @csrf

        <div class="vf-field">
          <label class="vf-label" for="email">Correo electrónico</label>
          <input
            id="email"
            name="email"
            type="email"
            inputmode="email"
            autocapitalize="none"
            autocomplete="email"
            class="vf-input"
            placeholder="nombre@dominio.com"
            value="{{ $emailPrefill }}"
            required
            maxlength="150"
          >
          @error('email')
            <div class="vf-error">{{ $message }}</div>
          @enderror
        </div>

        <div class="vf-actions" style="margin-top:14px;">
          <button type="submit" class="vf-btn vf-primary" style="border:0; cursor:pointer;">
            Enviar enlace
            <span class="vf-arrow" aria-hidden="true">→</span>
          </button>

          <a class="vf-btn vf-ghost" href="{{ $loginUrl }}">
            Volver al inicio de sesión
          </a>
        </div>
      </form>

      <div class="vf-help">
        <span>¿Necesitas ayuda?</span>
        <a href="mailto:{{ config('p360.support.email') }}">{{ config('p360.support.email') }}</a>
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
  const email = document.getElementById('email');
  if (email && !email.value) {
    setTimeout(() => { try { email.focus(); } catch(e) {} }, 50);
  }
})();
</script>
@endpush
@endsection
