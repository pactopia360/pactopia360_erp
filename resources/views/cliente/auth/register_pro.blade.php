{{-- resources/views/cliente/auth/register_pro.blade.php (v2.1 visual Pactopia360) --}}
@extends('layouts.guest')

@section('title', 'Registro PRO · Pactopia360')
@section('hide-brand','1') {{-- OCULTA LOGO DEL LAYOUT PARA EVITAR DOBLE LOGO --}}

@push('styles')
<style>
  :root{
    --bg:#fff8f9; --text:#0f172a; --muted:#6b7280; --card:#ffffff; --border:#e5e7eb;
    --brand:#E11D48; --brand-2:#BE123C; --accent:#fb7185;
    --ok:#16a34a; --bad:#ef4444; --ring:#fda4af;
    --g1: rgba(255,91,126,.28); --g2: rgba(255,42,42,.22);
    --g3: rgba(190,18,60,.20); --g4: rgba(255,180,210,.18);
  }
  html[data-theme="dark"]{
    --bg:#0b1220; --text:#e5e7eb; --muted:#9aa4b2; --card:#0f172a; --border:#1f2a44;
    --brand:#E11D48; --brand-2:#BE123C; --accent:#fb7185;
    --ok:#22c55e; --bad:#f87171; --ring:#fb7185;
    --g1: rgba(255,91,126,.14); --g2: rgba(255,42,42,.12);
    --g3: rgba(190,18,60,.12); --g4: rgba(255,180,210,.08);
  }

  html.page-register-pro body{
    background:var(--bg);
    min-height:100vh;overflow:hidden;
    font-family:'Poppins',system-ui,sans-serif;color:var(--text);
  }

  /* Fondo animado */
  html.page-register-pro body::before,
  html.page-register-pro body::after{
    content:"";position:fixed;inset:-25%;z-index:-1;filter:blur(100px);pointer-events:none;
  }
  html.page-register-pro body::before{
    background:
      radial-gradient(42% 62% at 12% 18%, var(--g2), transparent 60%),
      radial-gradient(44% 62% at 88% 20%, var(--g1), transparent 60%);
    animation:floatA 12s ease-in-out infinite alternate;
  }
  html.page-register-pro body::after{
    background:
      radial-gradient(55% 75% at 50% 86%, var(--g3), transparent 60%),
      radial-gradient(30% 42% at 78% 78%, var(--g4), transparent 60%);
    animation:floatB 14s ease-in-out infinite alternate;
  }
  @keyframes floatA{0%{transform:translate3d(-5%,-5%,0)}100%{transform:translate3d(6%,8%,0)}}
  @keyframes floatB{0%{transform:translate3d(5%,7%,0)}100%{transform:translate3d(-6%,-8%,0)}}

  .wrap{display:grid;place-items:start center;height:100vh;}
  .card{
    width:min(540px,92vw);
    margin-top:6vh;
    background:var(--card);
    border:1px solid var(--border);
    border-radius:20px;
    padding:24px;
    box-shadow:0 20px 60px rgba(0,0,0,.15);
    position:relative;
  }
  .card::before{
    content:"";position:absolute;inset:-1px;border-radius:21px;padding:1px;
    background:linear-gradient(145deg,var(--brand),var(--accent));
    -webkit-mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);
    -webkit-mask-composite:xor;mask-composite:exclude;
    opacity:.25;pointer-events:none;
  }

  .brand{display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:4px;}
  .logo{height:56px;width:auto;display:block;}
  .logo-dark{display:none;}
  html[data-theme="dark"] .logo-light{display:none;}
  html[data-theme="dark"] .logo-dark{display:block;}

  .theme{
    position:absolute;top:12px;right:12px;border:1px solid var(--border);
    background:var(--card);color:var(--text);border-radius:999px;
    width:44px;height:44px;padding:0;
    display:grid;place-items:center;
    font-weight:800;font-size:18px;cursor:pointer;
    box-shadow:0 6px 20px rgba(0,0,0,.1);
  }
  .theme span{pointer-events:none;}
  .theme:hover{filter:brightness(.97)}

  .badge-pro{
    display:inline-flex;align-items:center;gap:8px;
    padding:6px 10px;border-radius:999px;
    background:linear-gradient(90deg,#E11D48,#BE123C);
    color:#fff;font-size:11px;font-weight:800;
    box-shadow:0 8px 20px rgba(225,29,72,.25);
    margin:6px auto 0;
  }

  .title{text-align:center;margin-top:8px;margin-bottom:4px;}
  .title h1{margin:0;font-size:22px;font-weight:900;color:var(--text);}
  .title p{margin:6px 0 0;color:var(--muted);font-size:13px;}

  .form{margin-top:10px;max-height:calc(100vh - 20vh);overflow:auto;padding-right:4px;}
  .form::-webkit-scrollbar{width:8px;}
  .form::-webkit-scrollbar-thumb{background:#fda4af55;border-radius:8px;}

  .field{display:flex;flex-direction:column;gap:6px;margin:10px 0;}
  .label{font-size:12px;color:var(--muted);font-weight:800;}
  .input{
    width:100%;border-radius:12px;border:1px solid var(--border);
    padding:11px 12px;background:var(--card);color:var(--text);
    transition:border-color .15s,box-shadow .15s;font-size:14px;
  }
  .input:focus{border-color:var(--brand);box-shadow:0 0 0 3px rgba(225,29,72,.18);}
  .input[aria-invalid="true"]{border-color:var(--bad);box-shadow:0 0 0 3px rgba(239,68,68,.2);}
  .help{font-size:11px;color:var(--muted);}
  .error{color:var(--bad);}

  /* Honeypot oculto */
  .hp{position:absolute;left:-9999px;opacity:0;width:1px;height:1px;overflow:hidden;}

  .plan-picker{display:flex;gap:10px;margin-top:6px;flex-wrap:wrap;}
  .plan-opt{
    flex:1 1 auto;min-width:140px;
    border:1px solid var(--border);border-radius:14px;
    padding:12px;background:var(--card);cursor:pointer;
    transition:all .2s ease;box-shadow:0 8px 18px rgba(0,0,0,.05);
  }
  .plan-opt[aria-checked="true"]{
    border-color:var(--brand);box-shadow:0 0 0 3px rgba(225,29,72,.2);
  }
  .plan-head{display:flex;justify-content:space-between;align-items:baseline;}
  .plan-head span:first-child{font-weight:700;color:var(--text);}
  .plan-price{font-weight:900;color:var(--brand);}
  .plan-desc{color:var(--muted);font-size:12px;margin-top:4px;}

  .terms{font-size:12px;color:var(--muted);margin:8px 0;line-height:1.45;}
  .terms a{font-weight:800;text-decoration:none;color:var(--brand);}
  .terms a:hover{text-decoration:underline;}
  .terms input{accent-color:var(--brand);margin-right:8px;}

  .actions{margin-top:14px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between;}
  .btn{
    border:0;padding:11px 14px;border-radius:12px;font-weight:900;cursor:pointer;transition:filter .12s ease;
  }
  .btn-primary{
    flex:1;min-width:180px;text-align:center;color:#fff;
    background:linear-gradient(90deg,#E11D48,#BE123C);
    box-shadow:0 12px 22px rgba(225,29,72,.28);
  }
  .btn-primary:hover{filter:brightness(.96);}
  .btn-primary[disabled]{background:linear-gradient(90deg,#cbd5e1,#94a3b8);color:#f1f5f9;box-shadow:none;cursor:not-allowed;}
  .btn-free{
    padding:9px 14px;border-radius:999px;color:var(--brand);
    border:1px solid var(--border);text-decoration:none;font-size:12px;font-weight:800;
  }
  .btn-free:hover{filter:brightness(.97);}
  .login-cta{width:100%;text-align:center;font-size:12px;margin-top:10px;}
  .login-cta a{font-weight:900;color:var(--brand);text-decoration:none;}
  .login-cta a:hover{text-decoration:underline;}

  .alert{border:1px solid var(--border);
    background:color-mix(in srgb,var(--card) 95%,transparent);
    color:var(--text);border-radius:12px;padding:10px 12px;font-size:12px;margin:8px 0;}
  .alert.ok{border-color:color-mix(in srgb,var(--ok) 40%,var(--border));background:color-mix(in srgb,var(--ok) 8%,var(--card));}
  .alert.err{border-color:color-mix(in srgb,var(--bad) 45%,var(--border));background:color-mix(in srgb,var(--bad) 8%,var(--card));}

  .modal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.45);z-index:9999;}
  .modal[aria-hidden="false"]{display:flex;}
  .modal-card{
    width:min(520px,92vw);
    border-radius:18px;padding:20px;
    background:var(--card);color:var(--text);border:1px solid var(--border);
    box-shadow:0 24px 80px rgba(0,0,0,.45);animation:pop .18s ease-out;
  }
  @keyframes pop{from{transform:scale(.96);opacity:.4}to{transform:scale(1);opacity:1}}
  .modal-title{margin:0 0 8px;font-size:18px;font-weight:900;color:var(--brand);}
  .modal-body{font-size:14px;color:var(--muted);}
  .modal-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:14px;}
  .btn-ghost{border:1px solid var(--border);background:transparent;color:var(--text);border-radius:12px;padding:9px 14px;font-weight:800;cursor:pointer;}
</style>
@endpush

@section('content')
@php
  use Illuminate\Support\Str;
  $priceMonthly = $price_monthly ?? config('services.stripe.display_price_monthly', 990.00);
  $priceAnnual  = $price_annual  ?? config('services.stripe.display_price_annual', 9990.00);
  $prefPlan = old('plan', session('checkout_plan', 'mensual'));
  $isMensual = $prefPlan === 'mensual';
  $isAnual   = $prefPlan === 'anual';
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

    @if (session('ok'))<div class="alert ok">{{ session('ok') }}</div>@endif
    @if ($errors->any())<div class="alert err">{{ $errors->first() }}</div>@endif

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
            <div class="plan-head"><span>Mensual</span><span class="plan-price">${{ number_format($priceMonthly, 2) }} MXN</span></div>
            <div class="plan-desc">Pago mes a mes. Actívate hoy, factura hoy.</div>
          </label>

          <label class="plan-opt" data-plan="anual" aria-checked="{{ $isAnual ? 'true' : 'false' }}">
            <input class="sr-only" type="radio" name="plan" value="anual" style="display:none" @checked($isAnual)>
            <div class="plan-head"><span>Anual</span><span class="plan-price">${{ number_format($priceAnnual, 2) }} MXN</span></div>
            <div class="plan-desc">1 solo pago con ahorro y prioridad en soporte.</div>
          </label>
        </div>
      </div>

      <div class="terms">
        <label><input type="checkbox" name="terms" id="terms" required> Acepto los <a href="{{ route('cliente.terminos') }}" target="_blank">términos y condiciones</a>.</label>
      </div>

      @php $recaptchaKey = env('RECAPTCHA_SITE_KEY'); @endphp
      @if ($recaptchaKey)
        <div style="margin-top:10px"><div class="g-recaptcha" data-sitekey="{{ $recaptchaKey }}"></div></div>
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
      <button class="btn-ghost" id="rpModalClose">Cerrar</button>
      <button class="btn btn-primary" id="rpModalOk">Entendido</button>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
  document.documentElement.classList.add('page-register-pro');
  (function(){
    const html=document.documentElement,
          KEY='p360-theme',
          btn=document.getElementById('rpThemeToggle'),
          ico=document.getElementById('rpThemeIcon');

    const paint=()=>{ ico.textContent = (html.dataset.theme==='dark') ? '☀️' : '🌙'; };
    const set=v=>{ html.dataset.theme=v; localStorage.setItem(KEY,v); paint(); };

    set(localStorage.getItem(KEY)||'light');
    btn?.addEventListener('click',()=>set(html.dataset.theme==='dark'?'light':'dark'));
  })();
</script>
@if (env('RECAPTCHA_SITE_KEY'))
<script src="https://www.google.com/recaptcha/api.js?hl=es" async defer></script>
@endif
@endpush
