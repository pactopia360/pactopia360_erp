{{-- C:\wamp64\www\pactopia360_erp\resources\views\cliente\auth\verify_phone.blade.php --}}
@extends('layouts.guest')
@section('hide-brand','1')
@section('title','Verificar teléfono · Pactopia360')

@php
  /**
   * Variables esperadas desde controller:
   * @var object $account (id, phone)
   * @var string $phone_masked
   * @var string $state "otp"|"phone"
   */

  // ✅ Resolver account_id de forma robusta:
  // 1) account->id (desde controller)
  // 2) request('account_id') (fallback por query o hidden)
  // 3) session('verify.account_id')
  $aid = (int) data_get($account, 'id', 0);
  if ($aid <= 0) $aid = (int) request()->input('account_id', 0);
  if ($aid <= 0) $aid = (int) session('verify.account_id', 0);

  // Logos (tus rutas reales)
  $logoLight = asset('assets/client/p360-black.png'); // modo claro
  $logoDark  = asset('assets/client/p360-white.png'); // modo oscuro

  // Normalizar state
  $state = ($state ?? 'phone');
  if (!in_array($state, ['phone','otp'], true)) $state = 'phone';

  // Si NO tenemos account_id, forzamos estado phone y mostramos alerta
  $missingAccount = ($aid <= 0);
  if ($missingAccount) {
    $state = 'phone';
  }
@endphp

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/client/css/verify-base.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/client/css/verify-phone.css') }}">
@endpush

@section('content')
<div class="vf-page">
  <div class="vf-card" role="region" aria-labelledby="vh1">

    <div class="vf-logo">
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="{{ $logoDark }}">
        <img id="vhLogo" src="{{ $logoLight }}" alt="Pactopia360">
      </picture>
    </div>

    <div class="vf-kicker">Verificación de identidad</div>
    <h1 id="vh1" class="vf-h1">Seguridad de cuenta</h1>
    <div class="vf-sub">Verificación en dos pasos (WhatsApp / SMS)</div>

    {{-- Alertas --}}
    @if (session('ok'))      <div class="vf-alert vf-alert-ok">{{ session('ok') }}</div> @endif
    @if (session('warning')) <div class="vf-alert vf-alert-warn">{{ session('warning') }}</div> @endif

    @if ($missingAccount)
      <div class="vf-alert vf-alert-warn">
        No pudimos detectar tu sesión de verificación (account_id).<br>
        Regresa y solicita un enlace nuevo de verificación de correo, o abre el enlace desde el mismo dispositivo/navegador.
      </div>
    @endif

    @if ($errors->any())
      <div class="vf-alert vf-alert-err">
        @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
      </div>
    @endif

        {{-- DEBUG OTP (solo LOCAL): muestra el código aunque no haya WhatsApp/Twilio --}}
    @if(app()->environment(['local','development','testing']) && session('otp_debug_code'))
      <div class="vf-alert vf-alert-ok" style="display:flex;justify-content:space-between;gap:10px;align-items:center;">
        <div>
          <strong>OTP (LOCAL)</strong> Código: <strong style="letter-spacing:2px;">{{ session('otp_debug_code') }}</strong>
        </div>
        <button type="button"
                class="vf-resend-btn"
                style="padding:8px 10px;min-height:auto;font-size:12px;"
                onclick="navigator.clipboard?.writeText('{{ session('otp_debug_code') }}');">
          Copiar
        </button>
      </div>
    @endif


    {{-- Estado: Captura de teléfono --}}
    @if ($state === 'phone')
      <form method="POST" action="{{ route('cliente.verify.phone.update') }}" class="vf-grid">
        @csrf
        <input type="hidden" name="account_id" value="{{ $aid }}">

        <label class="vf-field">
          <span class="vf-label">Prefijo de país</span>
          <input class="vf-control" name="country_code" value="{{ old('country_code','52') }}" maxlength="5" required>
        </label>

        <label class="vf-field">
          <span class="vf-label">Teléfono (WhatsApp / SMS)</span>
          <input class="vf-control" name="telefono" placeholder="5537747366" value="{{ old('telefono') }}" maxlength="25" required>
        </label>

        <button class="vf-btn" type="submit" @if($missingAccount) disabled aria-disabled="true" @endif>
          Enviar código
        </button>
      </form>
    @else
      {{-- Estado: OTP --}}
      <div class="vf-helper vf-helper-top">
        Enviamos un código a <strong>{{ $phone_masked }}</strong>. Vence en 10 minutos.
      </div>

      <form id="otpForm" method="POST" action="{{ route('cliente.verify.phone.check') }}" class="vf-grid">
        @csrf
        <input type="hidden" name="account_id" value="{{ $aid }}">
        <input type="hidden" id="code" name="code" value="">

        <div class="vf-otp" aria-label="Código de verificación">
          @for ($i=0; $i<6; $i++)
            <input inputmode="numeric" pattern="[0-9]*" maxlength="1" class="vf-otp-box" data-i="{{ $i }}" autocomplete="one-time-code">
          @endfor
        </div>

        <button class="vf-btn" type="submit">Verificar</button>
      </form>

      <form method="POST" action="{{ route('cliente.verify.phone.send') }}" class="vf-resend">
        @csrf
        <input type="hidden" name="account_id" value="{{ $aid }}">
        <button type="submit" class="vf-resend-btn">Reenviar código</button>
      </form>
    @endif

    <div class="vf-helper">
      ¿Ya tienes acceso?
      <a href="{{ route('cliente.login') }}">Inicia sesión</a>
    </div>

  </div>
</div>
@endsection

@push('scripts')
<script>
(function(){
  // Sync logo con data-theme="dark|light" si existe
  const root = document.documentElement;
  const logo = document.getElementById('vhLogo');
  const imgs = { light: @json($logoLight), dark: @json($logoDark) };

  function applyLogo(){
    const mode = root.getAttribute('data-theme');
    if (!mode || !logo) return;
    logo.src = (mode === 'dark') ? imgs.dark : imgs.light;
  }
  applyLogo();
  new MutationObserver(applyLogo).observe(root, { attributes:true, attributeFilter:['data-theme'] });

  // OTP UX
  const boxes  = Array.from(document.querySelectorAll('.vf-otp-box'));
  const hidden = document.getElementById('code');
  const form   = document.getElementById('otpForm');

  if (!boxes.length || !hidden || !form) return;

  const compose = () => hidden.value = boxes.map(b => (b.value || '').replace(/\D/g,'')).join('').slice(0,6);

  boxes.forEach((box, idx) => {
    box.addEventListener('input', () => {
      box.value = box.value.replace(/\D/g,'').slice(0,1);
      compose();
      if (box.value && idx < boxes.length - 1) boxes[idx+1].focus();
    });

    box.addEventListener('keydown', (e) => {
      if (e.key === 'Backspace' && !box.value && idx > 0) boxes[idx-1].focus();
      if ((e.key || '').match(/^[0-9]$/)) box.value = '';
      setTimeout(compose, 0);
    });

    box.addEventListener('paste', (e) => {
      e.preventDefault();
      const d = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g,'').slice(0,6);
      for (let i=0; i<boxes.length; i++) boxes[i].value = d[i] || '';
      compose();
      boxes[Math.max(0, (d.length ? d.length-1 : 0))].focus();
    });
  });

  boxes[0].focus();

  form.addEventListener('submit', (e) => {
    compose();
    if (hidden.value.length !== 6) {
      e.preventDefault();
      boxes[hidden.value.length || 0].focus();
    }
  });
})();
</script>
@endpush
