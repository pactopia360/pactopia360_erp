{{-- resources/views/cliente/auth/register_free.blade.php (v2.1 visual refinado Pactopia360) --}}
@extends('layouts.guest')

@section('title', 'Registro FREE · Pactopia360')
@section('hide-brand','1') {{-- OCULTA LOGO DEL LAYOUT PARA EVITAR DOBLE LOGO --}}

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/client/css/register_free.css') }}">
@endpush

@section('content')
@php
  use Illuminate\Support\Str;

  // reCAPTCHA: solo en producción y si está habilitado
  $recaptchaEnabled = (bool) config('services.recaptcha.enabled');
  $isProd           = app()->environment('production');
  $recaptchaKey     = (string) config('services.recaptcha.site_key');
  $showRecaptcha    = $recaptchaEnabled && $isProd && $recaptchaKey;
@endphp

<div class="wrap">
  <section class="card" role="region" aria-label="Registro FREE">
    <button type="button" class="theme" id="rfThemeToggle" aria-label="Cambiar tema">
      <span id="rfThemeIcon">🌙</span>
    </button>

    <div class="brand">
      <img class="logo logo-light" src="{{ asset('assets/client/p360-black.png') }}" alt="Pactopia360">
      <img class="logo logo-dark"  src="{{ asset('assets/client/p360-white.png') }}" alt="Pactopia360">
    </div>

    <span class="badge-free">FOR EVER FREE</span>

    <div class="title">
      <h1>Crear cuenta GRATIS</h1>
      <p>Completa tus datos para comenzar. Te pediremos verificar tu correo y teléfono.</p>
    </div>

    @if (session('ok'))<div class="alert ok">{{ session('ok') }}</div>@endif
    @if ($errors->any())<div class="alert err">{{ $errors->first() }}</div>@endif

    <form method="POST" action="{{ route('cliente.registro.free.do') }}" novalidate id="regForm" class="form">
      @csrf

      {{-- Honeypot oculto --}}
      <div class="hp" aria-hidden="true">
        <input type="text" name="hp_field" id="hp_field" tabindex="-1" autocomplete="off">
      </div>

      <div class="field">
        <label class="label" for="nombre">Nombre completo *</label>
        <input class="input @error('nombre') is-invalid @enderror" type="text" name="nombre" id="nombre"
               value="{{ old('nombre') }}" required maxlength="150" autocomplete="name"
               placeholder="Mi nombre" aria-invalid="@error('nombre') true @else false @enderror">
        <div class="help">Nombre y apellidos tal como deseas que aparezcan.</div>
        @error('nombre')<div class="help error">{{ $message }}</div>@enderror
      </div>

      <div class="field">
        <label class="label" for="email">Correo electrónico *</label>
        <input class="input @error('email') is-invalid @enderror" type="email" name="email" id="email"
               value="{{ old('email') }}" required maxlength="150" autocomplete="email"
               placeholder="micorreo@dominio.com" aria-invalid="@error('email') true @else false @enderror">
        @error('email')<div class="help error">{{ $message }}</div>@enderror
      </div>

      <div class="field">
        <label class="label" for="rfc">RFC con homoclave *</label>
        <input class="input @error('rfc') is-invalid @enderror" type="text" name="rfc" id="rfc"
               value="{{ old('rfc') }}" required maxlength="13" placeholder="XAXX010101000"
               oninput="this.value=this.value.toUpperCase()" pattern="[A-ZÑ&]{3,4}[0-9]{6}[A-Z0-9]{3}"
               aria-invalid="@error('rfc') true @else false @enderror">
        <div class="help">No se permiten cuentas duplicadas por RFC.</div>
        @error('rfc')<div class="help error">{{ $message }}</div>@enderror
      </div>

      <div class="field">
        <label class="label" for="telefono">Teléfono *</label>
        <input class="input @error('telefono') is-invalid @enderror" type="tel" name="telefono" id="telefono"
               value="{{ old('telefono') }}" required maxlength="25" placeholder="+52 55 1234 5678"
               aria-invalid="@error('telefono') true @else false @enderror">
        @error('telefono')<div class="help error">{{ $message }}</div>@enderror
      </div>

      <div class="terms">
        <label>
          <input type="checkbox" name="terms" id="terms" required>
          He leído y acepto los <a href="{{ route('cliente.terminos') }}" target="_blank" rel="noopener">términos y condiciones</a>.
        </label>
      </div>

      @if ($showRecaptcha)
        <div style="margin-top:10px">
          <div class="g-recaptcha" data-sitekey="{{ $recaptchaKey }}"></div>
        </div>
        @error('g-recaptcha-response')
          <div class="help error">{{ $message }}</div>
        @enderror
      @else
        {{-- Local/dev: captcha deshabilitado --}}
        <input type="hidden" name="g-recaptcha-response" value="local-bypass">
      @endif

      <div class="actions">
        <button type="submit" class="btn btn-primary" id="submitBtn" disabled>Crear cuenta</button>
        <a href="{{ route('cliente.registro.pro') }}" class="btn-pro">Pasa a PRO</a>
      </div>

      <div class="login-cta">¿Ya tienes cuenta? <a href="{{ route('cliente.login') }}">Inicia sesión</a></div>
    </form>
  </section>
</div>

{{-- Modal --}}
<div class="modal" id="rfModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="rfModalTitle">
  <div class="modal-card">
    <h3 class="modal-title" id="rfModalTitle">Aviso</h3>
    <div class="modal-body" id="rfModalBody">Mensaje</div>
    <div class="modal-actions">
      <button class="btn btn-ghost" id="rfModalClose">Cerrar</button>
      <button class="btn btn-primary" id="rfModalOk">Entendido</button>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
  document.documentElement.classList.add('page-register-free');

  // Tema persistente (icono solo)
  (function(){
    const html=document.documentElement;
    const KEY='p360-theme';
    const btn=document.getElementById('rfThemeToggle');
    const ico=document.getElementById('rfThemeIcon');

    const paint=()=>{ ico.textContent = (html.dataset.theme==='dark') ? '☀️' : '🌙'; };
    const set=v=>{ html.dataset.theme=v; localStorage.setItem(KEY,v); paint(); };

    set(localStorage.getItem(KEY)||'light');
    btn?.addEventListener('click',()=>set(html.dataset.theme==='dark'?'light':'dark'));
  })();

  // Habilita botón cuando acepta términos (y captcha si aplica)
  (function(){
    const submitBtn = document.getElementById('submitBtn');
    const terms     = document.getElementById('terms');
    const showRecaptcha = @json($showRecaptcha);

    const canSubmit = () => {
      if (!terms?.checked) return false;
      if (showRecaptcha) {
        const token = (window.grecaptcha && typeof window.grecaptcha.getResponse === 'function')
          ? window.grecaptcha.getResponse()
          : '';
        return !!token;
      }
      return true;
    };

    const tick = () => {
      const ok = canSubmit();
      if (submitBtn) submitBtn.disabled = !ok;
    };

    terms?.addEventListener('change', tick);
    setInterval(tick, 600);
    tick();
  })();
</script>

@if ($showRecaptcha)
  <script src="https://www.google.com/recaptcha/api.js?hl=es" async defer></script>
@endif
@endpush
