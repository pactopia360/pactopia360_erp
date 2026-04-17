{{-- C:\wamp64\www\pactopia360_erp\resources\views\cliente\auth\register_free.blade.php --}}
@extends('layouts.guest')

@section('title', 'Registro FREE · Pactopia360')
@section('hide-brand', '1')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/client/css/register_free.css') }}">
@endpush

@section('content')
@php
    $recaptchaEnabled = (bool) config('services.recaptcha.enabled');
    $isProd           = app()->environment('production');
    $recaptchaKey     = (string) config('services.recaptcha.site_key');
    $showRecaptcha    = $recaptchaEnabled && $isProd && $recaptchaKey;
@endphp

<div class="wrap register-free-wrap">
    <div class="register-free-bg" aria-hidden="true">
        <span class="bg-orb bg-orb-1"></span>
        <span class="bg-orb bg-orb-2"></span>
        <span class="bg-orb bg-orb-3"></span>
        <span class="bg-grid"></span>
    </div>

    <section class="card register-free-card" role="region" aria-label="Registro FREE">
        <button type="button" class="theme" id="rfThemeToggle" aria-label="Cambiar tema">
            <span id="rfThemeIcon">🌙</span>
        </button>

        <div class="brand">
            <img
                class="logo logo-main"
                src="{{ asset('assets/client/img/Pactopia - Letra AZUL.png') }}"
                alt="Pactopia360"
            >
        </div>

        <div class="title title-clean">
            <h1>Crea tu cuenta gratuita por siempre.</h1>
            <p>Sin necesidad de tarjetas de crédito y puedes cancelar cuando quieras</p>
        </div>

        @if (session('ok'))
            <div class="alert ok">{{ session('ok') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert err">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('cliente.registro.free.do') }}" novalidate id="regForm" class="form form-clean">
            @csrf

            <div class="hp" aria-hidden="true">
                <input type="text" name="hp_field" id="hp_field" tabindex="-1" autocomplete="off">
            </div>

            <div class="field field-required">
              <input
                  class="input @error('nombre') is-invalid @enderror"
                  type="text"
                  name="nombre"
                  id="nombre"
                  value="{{ old('nombre') }}"
                  required
                  maxlength="150"
                  autocomplete="name"
                  placeholder="Nombre"
                  aria-label="Nombre"
                  aria-invalid="@error('nombre') true @else false @enderror"
              >
              <span class="field-required-mark">*</span>
              @error('nombre')
                  <div class="help error">{{ $message }}</div>
              @enderror
          </div>

            <div class="field field-required">
                <input
                    class="input @error('rfc') is-invalid @enderror"
                    type="text"
                    name="rfc"
                    id="rfc"
                    value="{{ old('rfc') }}"
                    required
                    maxlength="13"
                    placeholder="RFC"
                    oninput="this.value=this.value.toUpperCase()"
                    pattern="[A-ZÑ&]{3,4}[0-9]{6}[A-Z0-9]{3}"
                    aria-label="RFC"
                    aria-invalid="@error('rfc') true @else false @enderror"
                >
                <span class="field-required-mark">*</span>
                @error('rfc')
                    <div class="help error">{{ $message }}</div>
                @enderror
            </div>

           <div class="field field-required">
                <input
                    class="input @error('telefono') is-invalid @enderror"
                    type="tel"
                    name="telefono"
                    id="telefono"
                    value="{{ old('telefono') }}"
                    required
                    maxlength="25"
                    placeholder="Teléfono"
                    aria-label="Teléfono"
                    aria-invalid="@error('telefono') true @else false @enderror"
                >
                <span class="field-required-mark">*</span>
                @error('telefono')
                    <div class="help error">{{ $message }}</div>
                @enderror
            </div>

           <div class="field field-required">
                <input
                    class="input @error('email') is-invalid @enderror"
                    type="email"
                    name="email"
                    id="email"
                    value="{{ old('email') }}"
                    required
                    maxlength="150"
                    autocomplete="email"
                    placeholder="Correo electrónico"
                    aria-label="Correo electrónico"
                    aria-invalid="@error('email') true @else false @enderror"
                >
                <span class="field-required-mark">*</span>
                @error('email')
                    <div class="help error">{{ $message }}</div>
                @enderror
            </div>

            <div class="terms terms-clean">
                <label for="terms">
                    <input
                        type="checkbox"
                        name="terms"
                        id="terms"
                        required
                        {{ old('terms') ? 'checked' : '' }}
                    >
                    <span>
                        He leído los
                        <a href="{{ route('cliente.terminos') }}" target="_blank" rel="noopener">términos y condiciones</a>
                        del portal
                    </span>
                </label>
                @error('terms')
                    <div class="help error">{{ $message }}</div>
                @enderror
            </div>

            @if ($showRecaptcha)
                  <div class="field recaptcha-wrap recaptcha-clean">
                      <div
                          class="g-recaptcha"
                          data-sitekey="{{ $recaptchaKey }}"
                          data-theme="light"
                      ></div>
                      @error('g-recaptcha-response')
                          <div class="help error">{{ $message }}</div>
                      @enderror
                  </div>
              @else
                  {{-- Local/dev: no mostrar captcha falso --}}
                  <input type="hidden" name="g-recaptcha-response" value="local-bypass">
              @endif

            <div class="actions actions-clean">
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    Crear cuenta
                </button>
            </div>

            <div class="login-cta login-cta-clean">
                ¿Ya tienes cuenta?
                <a href="{{ route('cliente.login') }}">Inicia sesión</a>
            </div>
        </form>
    </section>
</div>

@if ($showRecaptcha)
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
@endif

<script>
document.addEventListener('DOMContentLoaded', function () {
    const html = document.documentElement;
    const toggle = document.getElementById('rfThemeToggle');
    const icon = document.getElementById('rfThemeIcon');

    const savedTheme = localStorage.getItem('rf-theme');
    if (savedTheme === 'dark') {
        html.setAttribute('data-theme', 'dark');
        if (icon) icon.textContent = '☀️';
    } else {
        html.removeAttribute('data-theme');
        if (icon) icon.textContent = '🌙';
    }

    if (toggle) {
        toggle.addEventListener('click', function () {
            const isDark = html.getAttribute('data-theme') === 'dark';

            if (isDark) {
                html.removeAttribute('data-theme');
                localStorage.setItem('rf-theme', 'light');
                if (icon) icon.textContent = '🌙';
            } else {
                html.setAttribute('data-theme', 'dark');
                localStorage.setItem('rf-theme', 'dark');
                if (icon) icon.textContent = '☀️';
            }
        });
    }

    const form = document.getElementById('regForm');
    const submitBtn = document.getElementById('submitBtn');

    if (form && submitBtn) {
        form.addEventListener('submit', function () {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Creando cuenta...';
        });
    }
});
</script>
@endsection