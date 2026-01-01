{{-- resources/views/cliente/auth/register_pro.blade.php (Vault-style + CSS separado) --}}
@extends('layouts.guest')

@section('title', 'Registro PRO · Pactopia360')
@section('hide-brand','1') {{-- OCULTA LOGO DEL LAYOUT PARA EVITAR DOBLE LOGO --}}

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/client/css/register_pro.css') }}">
@endpush

@section('content')
@php
  use Illuminate\Support\Str;

  $priceMonthly = $price_monthly ?? config('services.stripe.display_price_monthly', 990.00);
  $priceAnnual  = $price_annual  ?? config('services.stripe.display_price_annual', 9990.00);

  $prefPlan  = old('plan', session('checkout_plan', 'mensual'));
  $isMensual = $prefPlan === 'mensual';
  $isAnual   = $prefPlan === 'anual';

  // reCAPTCHA centralizado (no env() en blade)
  $recaptchaKey = config('services.recaptcha.site_key');
  $recaptchaOn  = (bool) config('services.recaptcha.enabled', false);
@endphp

<div class="wrap">
  <section class="card" role="region" aria-label="Registro PRO">
    <button type="button" class="theme" id="rpThemeToggle" aria-label="Cambiar tema">
      <span id="rpThemeIcon">🌙</span>
    </button>

    <div class="brand">
      <img class="logo logo-light" src="{{ asset('assets/client/p360-black.png') }}" alt="Pactopia 360">
      <img class="logo logo-dark"  src="{{ asset('assets/client/p360-white.png') }}" alt="Pactopia 360">
    </div>

    <span class="badge-pro">PLAN PRO</span>

    <div class="title">
      <h1>Configura tu cuenta PRO</h1>
      <p>Recibirás acceso inmediato y pasos de pago para activar tu facturación ilimitada.</p>
    </div>

    @if (session('ok'))
      <div class="alert ok">{{ session('ok') }}</div>
    @endif

    @if ($errors->any())
      <div class="alert err">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('cliente.registro.pro.do') }}" novalidate id="regProForm" class="form">
      @csrf

      {{-- Honeypot oculto --}}
      <div class="hp" aria-hidden="true">
        <input type="text" name="hp_field" id="hp_field" tabindex="-1" autocomplete="off">
      </div>

      <div class="field">
        <label class="label" for="nombre">Nombre / Razón social *</label>
        <input class="input" type="text" name="nombre" id="nombre" value="{{ old('nombre') }}" required maxlength="150" placeholder="Mi empresa S.A. de C.V.">
      </div>

      <div class="field">
        <label class="label" for="email">Correo de contacto *</label>
        <input class="input" type="email" name="email" id="email" value="{{ old('email') }}" required placeholder="facturacion@miempresa.com">
      </div>

      <div class="field">
        <label class="label" for="rfc">RFC con homoclave *</label>
        <input class="input" type="text" name="rfc" id="rfc" value="{{ old('rfc') }}" maxlength="13" oninput="this.value=this.value.toUpperCase()" placeholder="XAXX010101000">
      </div>

      <div class="field">
        <label class="label" for="telefono">Teléfono / WhatsApp *</label>
        <input class="input" type="tel" name="telefono" id="telefono" value="{{ old('telefono') }}" maxlength="25" placeholder="+52 55 1234 5678">
      </div>

      <div class="field">
        <label class="label">Plan *</label>
        <div class="plan-picker">
          <label class="plan-opt" data-plan="mensual" aria-checked="{{ $isMensual ? 'true' : 'false' }}">
            <input class="sr-only" type="radio" name="plan" value="mensual" style="display:none" @checked($isMensual)>
            <div class="plan-head">
              <span>Mensual</span>
              <span class="plan-price">${{ number_format($priceMonthly, 2) }} MXN</span>
            </div>
            <div class="plan-desc">Pago mes a mes. Actívate hoy, factura hoy.</div>
          </label>

          <label class="plan-opt" data-plan="anual" aria-checked="{{ $isAnual ? 'true' : 'false' }}">
            <input class="sr-only" type="radio" name="plan" value="anual" style="display:none" @checked($isAnual)>
            <div class="plan-head">
              <span>Anual</span>
              <span class="plan-price">${{ number_format($priceAnnual, 2) }} MXN</span>
            </div>
            <div class="plan-desc">1 solo pago con ahorro y prioridad en soporte.</div>
          </label>
        </div>
      </div>

      <div class="terms">
        <label>
          <input type="checkbox" name="terms" id="terms" required>
          <span>
            Acepto los
            <a href="{{ route('cliente.terminos') }}" target="_blank" rel="noopener">términos y condiciones</a>.
          </span>
        </label>
      </div>

      @if ($recaptchaOn && $recaptchaKey)
        <div style="margin-top:10px">
          <div
            class="g-recaptcha"
            data-sitekey="{{ $recaptchaKey }}"
            data-callback="rpCaptchaDone"
            data-expired-callback="rpCaptchaExpired"
            data-error-callback="rpCaptchaExpired"
          ></div>

        </div>
      @endif

      <div class="actions">
        <button type="submit" class="btn btn-primary" id="submitBtn" disabled>Crear cuenta PRO</button>
        <a href="{{ route('cliente.registro.free') }}" class="btn-free">¿Mejor gratis?</a>
        <div class="login-cta">¿Ya tienes cuenta? <a href="{{ route('cliente.login') }}">Inicia sesión</a></div>
      </div>
    </form>
  </section>
</div>

<div class="modal" id="rpModal" aria-hidden="true">
  <div class="modal-card">
    <h3 class="modal-title" id="rpModalTitle">Aviso</h3>
    <div class="modal-body" id="rpModalBody">Mensaje</div>
    <div class="modal-actions">
      <button class="btn-ghost" id="rpModalClose" type="button">Cerrar</button>
      <button class="btn btn-primary" id="rpModalOk" type="button">Entendido</button>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
  document.documentElement.classList.add('page-register-pro');

  (function(){
    const html = document.documentElement;
    const KEY  = 'p360-theme';
    const btn  = document.getElementById('rpThemeToggle');
    const ico  = document.getElementById('rpThemeIcon');

    const paint = () => { ico.textContent = (html.dataset.theme === 'dark') ? '☀️' : '🌙'; };
    const setTheme = (v) => { html.dataset.theme = v; localStorage.setItem(KEY, v); paint(); };

    setTheme(localStorage.getItem(KEY) || 'light');
    btn?.addEventListener('click', () => setTheme(html.dataset.theme === 'dark' ? 'light' : 'dark'));
  })();

  // ===== Enable/Disable botón "Crear cuenta PRO" =====
  (function(){
    const form   = document.getElementById('regProForm');
    const submit = document.getElementById('submitBtn');
    const terms  = document.getElementById('terms');

    if (!form || !submit) return;

    // Estos 2 valores vienen del mismo criterio que usas en Blade
    const recaptchaOn  = @json((bool) config('services.recaptcha.enabled', false));
    const recaptchaKey = @json((string) config('services.recaptcha.site_key'));

    // Estado captcha (solo aplica si recaptchaOn && recaptchaKey)
    window.__rpCaptchaOk = false;

    function updateSubmit(){
      // HTML5 validity + términos
      let ok = form.checkValidity() && (!!terms && terms.checked);

      // Debe haber un plan seleccionado
      const planChecked = !!form.querySelector('input[name="plan"]:checked');
      ok = ok && planChecked;

      // Si reCAPTCHA está activo, requerimos que se haya resuelto
      if (recaptchaOn && recaptchaKey) {
        ok = ok && (window.__rpCaptchaOk === true);
      }

      submit.disabled = !ok;
    }

    // Listeners generales
    form.addEventListener('input', updateSubmit);
    form.addEventListener('change', updateSubmit);
    terms?.addEventListener('change', updateSubmit);

    // Soporte: click en tarjetas de plan (por si el input está oculto)
    form.querySelectorAll('.plan-opt[data-plan]').forEach((opt) => {
      opt.addEventListener('click', () => {
        const plan = opt.getAttribute('data-plan');
        const radio = form.querySelector(`input[name="plan"][value="${plan}"]`);
        if (radio) {
          radio.checked = true;
          // aria
          form.querySelectorAll('.plan-opt').forEach(x => x.setAttribute('aria-checked', 'false'));
          opt.setAttribute('aria-checked', 'true');
        }
        updateSubmit();
      });
    });

    // Primer pintado
    updateSubmit();
  })();

  // Callbacks reCAPTCHA (solo si está activo)
  window.rpCaptchaDone = function(){
    window.__rpCaptchaOk = true;
    const form = document.getElementById('regProForm');
    const submit = document.getElementById('submitBtn');
    const terms = document.getElementById('terms');
    if (!form || !submit) return;

    // re-evaluar
    let ok = form.checkValidity() && (!!terms && terms.checked) && !!form.querySelector('input[name="plan"]:checked');
    submit.disabled = !ok;
  };
  window.rpCaptchaExpired = function(){
    window.__rpCaptchaOk = false;
    const submit = document.getElementById('submitBtn');
    if (submit) submit.disabled = true;
  };
</script>

@php
  $recaptchaKey = config('services.recaptcha.site_key');
  $recaptchaOn  = (bool) config('services.recaptcha.enabled', false);
@endphp

@if ($recaptchaOn && $recaptchaKey)
  <script src="https://www.google.com/recaptcha/api.js?hl=es" async defer></script>
@endif
@endpush
