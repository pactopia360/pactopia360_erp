{{-- resources/views/cliente/auth/register_pro.blade.php --}}
@extends('layouts.guest')

@section('title', 'Registro PRO · Pactopia360')
@section('hide-brand','1')

@push('styles')
<style>
  :root{
    --bg:#f6f7fb; --text:#0f172a; --muted:#6b7280; --card:#ffffff; --border:#e5e7eb;
    --brand:#3b82f6; --brand-2:#2563eb; --accent:#7c9cff;
    --ok:#16a34a; --bad:#ef4444; --ring:#9cc8ff;
    --g1: rgba(124,156,255,.30); --g2: rgba(60,220,200,.22);
    --g3: rgba(160,120,255,.22); --g4: rgba(120,220,255,.20);
  }
  html[data-theme="dark"]{
    --bg:#0b1220; --text:#e5e7eb; --muted:#9aa4b2; --card:#0f172a; --border:#1f2a44;
    --brand:#8aa3ff; --brand-2:#4f46e5; --accent:#8da8ff;
    --ok:#22c55e; --bad:#f87171; --ring:#6ea8ff;
    --g1: rgba(124,156,255,.16); --g2: rgba(60,220,200,.12);
    --g3: rgba(160,120,255,.14); --g4: rgba(120,220,255,.12);
  }

  /* Kill-switch del layout (solo 1 card) */
  html.page-register-pro .container > .card,
  html.page-register-pro .guest-card,
  html.page-register-pro .guest-shell,
  html.page-register-pro main > .card,
  html.page-register-pro .layout-card,
  html.page-register-pro .auth-card {
    background: transparent !important;
    border: 0 !important;
    box-shadow: none !important;
    padding: 0 !important;
    display: contents !important;
  }

  /* Fondo y altura */
  html.page-register-pro body{ background:var(--bg); height:100svh; overflow:hidden; }
  html.page-register-pro body::before,
  html.page-register-pro body::after{
    content:""; position:fixed; inset:-25%; z-index:-1; filter:blur(90px); pointer-events:none;
  }
  html.page-register-pro body::before{
    background:
      radial-gradient(42% 62% at 12% 18%, var(--g2), transparent 60%),
      radial-gradient(44% 62% at 88% 20%, var(--g1), transparent 60%);
    animation: pfloatA 10s ease-in-out infinite alternate;
  }
  html.page-register-pro body::after{
    background:
      radial-gradient(55% 75% at 50% 86%, var(--g3), transparent 60%),
      radial-gradient(30% 42% at 78% 78%, var(--g4), transparent 60%);
    animation: pfloatB 12s ease-in-out infinite alternate;
  }
  @keyframes pfloatA{0%{transform:translate3d(-5%,-5%,0)}50%{transform:translate3d(5%,7%,0)}100%{transform:translate3d(8%,-7%,0)}}
  @keyframes pfloatB{0%{transform:translate3d(6%,9%,0)}50%{transform:translate3d(-6%,-6%,0)}100%{transform:translate3d(9%,11%,0)}}

  .wrap{ height:100svh; display:grid; place-items:start center; }
  .card{
    width:min(520px,92vw);
    margin-top:6vh;
    position:relative; background:var(--card); border:1px solid var(--border);
    border-radius:20px; padding:22px; box-shadow:0 22px 64px rgba(2,8,23,.14);
  }
  .card::before{
    content:""; position:absolute; inset:-1px; border-radius:21px; padding:1px;
    background:linear-gradient(135deg,var(--brand),var(--accent));
    -webkit-mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);
    -webkit-mask-composite:xor; mask-composite:exclude; opacity:.22; pointer-events:none;
  }

  .brand{ display:flex; align-items:center; justify-content:center; gap:10px; }
  .logo{ height:38px; width:auto; display:block; }
  .logo-dark{ display:none; }
  html[data-theme="dark"] .logo-light{ display:none; }
  html[data-theme="dark"] .logo-dark{ display:block; }

  .theme{
    position:absolute; top:12px; right:12px; border:1px solid var(--border); background:var(--card); color:var(--text);
    border-radius:999px; padding:6px 10px; font-weight:800; font-size:12px; cursor:pointer; box-shadow:0 8px 18px rgba(2,8,23,.08);
  }
  .theme:hover{ filter:brightness(.97) }

  .badge-pro{
    display:inline-flex; align-items:center; gap:8px; padding:5px 9px; border-radius:999px;
    background:#0b3a7a; color:#fff; font-size:11px; font-weight:800; box-shadow:0 8px 20px rgba(11,58,122,.25);
    margin:8px auto 0; display:block; width:max-content;
  }
  html[data-theme="dark"] .badge-pro{ background:#1e3a8a; }

  .title{ text-align:center; margin-top:6px }
  .title h1{ margin:0; font-size:20px; font-weight:900; color:var(--text); }
  .title p{ margin:6px 0 0; color:var(--muted); font-size:12px }

  .form{ margin-top:10px; max-height:calc(100svh - 6vh - 22px - 22px - 24px); overflow:auto; padding-right:2px; }
  .form::-webkit-scrollbar{ width:8px } .form::-webkit-scrollbar-thumb{ background:#c7d2fe55; border-radius:8px }
  .field{ display:flex; flex-direction:column; gap:6px; margin:10px 0 }
  .label{ font-size:12px; color:var(--muted); font-weight:800; letter-spacing:.25px; }
  .input{
    width:100%; box-sizing:border-box; border-radius:12px; border:1px solid var(--border);
    padding:11px 12px; background:var(--card); color:var(--text); outline:none;
    transition:.15s border-color,.15s box-shadow; font-size:14px;
  }
  .input:focus{ border-color:var(--ring); box-shadow:0 0 0 3px color-mix(in srgb, var(--ring) 26%, transparent); }
  .input[aria-invalid="true"]{ border-color:var(--bad); box-shadow:0 0 0 3px #fee2e255; }
  .help{ font-size:11px; color:var(--muted); margin-top:-2px }
  .error{ color:var(--bad) }

  .plan{ display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:2px; }
  .opt{
    display:flex; align-items:center; gap:10px; padding:10px 12px; border:1px solid var(--border);
    border-radius:12px; cursor:pointer; background:color-mix(in srgb, var(--card) 96%, transparent);
  }
  .opt input[type="radio"]{ accent-color:var(--brand); }
  .price-note{ font-size:11px; color:var(--muted); margin-top:6px; }

  .terms{ font-size:12px; color:var(--muted); margin:6px 0 0; line-height:1.45 }
  .terms a{ font-weight:800; text-decoration:none; color:var(--text) }
  .terms a:hover{ text-decoration:underline }
  .terms input[type="checkbox"]{ accent-color:var(--brand); margin-right:8px }

  .actions{ margin-top:12px; display:flex; gap:8px; align-items:center }
  .btn{ border:0; padding:11px 14px; border-radius:12px; font-weight:900; cursor:pointer; transition:filter .12s ease; }
  .btn-primary{ flex:1; color:#fff; background:linear-gradient(180deg,var(--brand),var(--brand-2)); box-shadow:0 12px 24px rgba(59,130,246,.25); }
  .btn-primary:hover{ filter:brightness(.97) }
  .btn-primary[disabled]{ background:linear-gradient(180deg,#cbd5e1,#94a3b8); color:#f1f5f9; box-shadow:none; cursor:not-allowed; }
  html[data-theme="dark"] .btn-primary[disabled]{ background:linear-gradient(180deg,#334155,#1f2937); color:#94a3b8; }

  .login-cta{ margin-top:10px; text-align:center; font-size:12px }
  .login-cta a{ font-weight:900; color:var(--text); text-decoration:none }
  .login-cta a:hover{ text-decoration:underline }

  .alert{
    border:1px solid var(--border); background:color-mix(in srgb, var(--card) 96%, transparent);
    color:var(--text); border-radius:12px; padding:10px 12px; font-size:12px; margin:8px 0;
  }
  .alert.ok{ border-color:color-mix(in srgb, var(--ok) 40%, var(--border)); background:color-mix(in srgb, var(--ok) 8%, var(--card)); }
  .alert.err{ border-color:color-mix(in srgb, var(--bad) 45%, var(--border)); background:color-mix(in srgb, var(--bad) 8%, var(--card)); }

  .hp{ position:absolute; left:-10000px; top:auto; width:1px; height:1px; overflow:hidden; }
</style>
@endpush

@section('content')
@php
  $price_monthly = $price_monthly ?? config('services.stripe.display_price_monthly', 999.00);
  $price_annual  = $price_annual  ?? config('services.stripe.display_price_annual', 999.00 * 12);
@endphp

<div class="wrap">
  <section class="card" role="region" aria-label="Registro PRO">
    {{-- Toggle de tema --}}
    <button type="button" class="theme" id="rpThemeToggle" aria-label="Cambiar tema">🌙 Modo</button>

    {{-- Branding con fallback automático --}}
    <div class="brand" aria-label="Pactopia360">
      <img class="logo logo-light"
           src="{{ asset('assets/client/logop360light.png') }}"
           onerror="this.onerror=null;this.src='{{ asset('assets/admin/img/logo-pactopia360-dark.png') }}';"
           alt="Pactopia360">
      <img class="logo logo-dark"
           src="{{ asset('assets/client/logop360dark.png') }}"
           onerror="this.onerror=null;this.src='{{ asset('assets/admin/img/logo-pactopia360-white.png') }}';"
           alt="Pactopia360">
    </div>

    <span class="badge-pro" aria-hidden="true">PRO</span>

    <div class="title">
      <h1>Cuenta PRO</h1>
      <p>1 usuario, multiempresa ilimitado y timbrado ilimitado en emisión manual.
    Plan anual incluye 1 mes gratis y soporte prioritario.</p>
    </div>

    {{-- Alertas servidor --}}
    @if (session('ok'))<div class="alert ok" role="status">{{ session('ok') }}</div>@endif
    @if ($errors->any())<div class="alert err" role="alert">{{ $errors->first() }}</div>@endif

    {{-- 1) Formulario PRO --}}
    <form method="POST" action="{{ route('cliente.registro.pro.do') }}" id="regFormPro" class="form" novalidate @if(session('checkout_ready')) style="display:none" @endif>
      @csrf

      {{-- Honeypot --}}
      <div class="hp" aria-hidden="true">
        <input type="text" name="hp_field" id="hp_field" tabindex="-1" autocomplete="off">
      </div>

      <div class="field">
        <label class="label" for="nombre">Nombre completo *</label>
        <input class="input @error('nombre') is-invalid @enderror"
               type="text" name="nombre" id="nombre" value="{{ old('nombre') }}"
               required maxlength="150" autocomplete="name" placeholder="Mi nombre"
               aria-invalid="@error('nombre') true @else false @enderror">
        <div class="help">Tal como deseas que aparezca en tu cuenta.</div>
        @error('nombre')<div class="help error">{{ $message }}</div>@enderror
      </div>

      <div class="field">
        <label class="label" for="email">Correo electrónico *</label>
        <input class="input @error('email') is-invalid @enderror"
               type="email" name="email" id="email" value="{{ old('email') }}"
               required maxlength="150" autocomplete="email" placeholder="micorreo@dominio.com"
               inputmode="email"
               aria-invalid="@error('email') true @else false @enderror">
        @error('email')<div class="help error">{{ $message }}</div>@enderror
      </div>

      <div class="field">
        <label class="label" for="rfc">RFC con homoclave *</label>
        <input class="input @error('rfc') is-invalid @enderror"
               type="text" name="rfc" id="rfc" value="{{ old('rfc') }}"
               required maxlength="13" autocomplete="off" placeholder="XAXX010101000"
               oninput="this.value=this.value.toUpperCase()"
               pattern="[A-ZÑ&]{3,4}[0-9]{6}[A-Z0-9]{3}"
               aria-invalid="@error('rfc') true @else false @enderror">
        <div class="help">RFC maestro único por cuenta (dentro podrás dar de alta empresas).</div>
        @error('rfc')<div class="help error">{{ $message }}</div>@enderror
      </div>

      <div class="field">
        <label class="label" for="telefono">Teléfono *</label>
        <input class="input @error('telefono') is-invalid @enderror"
               type="tel" name="telefono" id="telefono" value="{{ old('telefono') }}"
               required maxlength="25" autocomplete="tel" placeholder="+52 55 1234 5678"
               inputmode="tel"
               aria-invalid="@error('telefono') true @else false @enderror">
        @error('telefono')<div class="help error">{{ $message }}</div>@enderror
      </div>

      {{-- Plan --}}
      <div class="field">
        <label class="label">Selecciona tu plan *</label>
        <div class="plan" role="radiogroup" aria-label="Planes PRO">
          <label class="opt" for="plan_mensual">
            <input
              type="radio"
              name="plan"
              id="plan_mensual"
              value="mensual"
              {{ old('plan','mensual')==='mensual'?'checked':'' }}
              aria-label="Mensual"
            >
            <div>
              <strong>Mensual</strong><br>
              ${{ number_format($price_monthly, 2) }} MXN / mes + IVA
              <div class="help" style="margin-top:2px;font-size:10px;line-height:1.3">
                1 usuario · multiempresa ilimitado · timbrado ilimitado manual.
              </div>
            </div>
          </label>

          <label class="opt" for="plan_anual">
            <input
              type="radio"
              name="plan"
              id="plan_anual"
              value="anual"
              {{ old('plan')==='anual'?'checked':'' }}
              aria-label="Anual"
            >
            <div>
              <strong>Anual</strong><br>
              ${{ number_format($price_annual, 2) }} MXN / año + IVA
              <div class="help" style="margin-top:2px;font-size:10px;line-height:1.3">
                Equivale a $832/mes aprox. 1 mes gratis + soporte prioritario.
              </div>
            </div>
          </label>
        </div>

        <div class="price-note">
          La carga masiva por Excel usa timbres prepagados. La emisión manual incluye timbrado ilimitado.
        </div>
      </div>

      <div class="terms">
        <label>
          <input type="checkbox" name="terms" id="terms" required>
          He leído y acepto los <a href="{{ route('cliente.terminos') }}" target="_blank" rel="noopener">términos y condiciones</a>.
        </label>
      </div>

      @php $recaptchaKey = env('RECAPTCHA_SITE_KEY'); @endphp
      @if ($recaptchaKey)
        <div style="margin-top:10px">
          <div class="g-recaptcha" data-sitekey="{{ $recaptchaKey }}"></div>
          <noscript><div class="help error">Activa JavaScript para completar el captcha.</div></noscript>
        </div>
      @else
        <div class="help" style="margin-top:10px">
          <strong>Nota:</strong> Configura <code>RECAPTCHA_SITE_KEY</code> y <code>RECAPTCHA_SECRET_KEY</code> en <code>.env</code> para habilitar el captcha.
        </div>
      @endif

      <div class="actions">
        <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
          Crear cuenta PRO y continuar al pago
        </button>
      </div>

      <div class="login-cta">¿Ya tienes cuenta? <a href="{{ route('cliente.login') }}">Inicia sesión</a></div>
    </form>

    {{-- 2) Autopost a Checkout Stripe cuando venimos de storePro --}}
    @if (session('checkout_ready') && session('account_id'))
      @php
        $plan = session('checkout_plan', 'mensual');
        $checkoutRoute = $plan === 'anual' ? 'cliente.checkout.pro.annual' : 'cliente.checkout.pro.monthly';
      @endphp
      <form id="autopay" method="POST" action="{{ route($checkoutRoute) }}">
        @csrf
        <input type="hidden" name="account_id" value="{{ session('account_id') }}">
        <input type="hidden" name="email" value="{{ old('email', request('email')) }}">
        <noscript>
          <button type="submit" class="btn btn-primary">Continuar a Stripe ({{ $plan }})</button>
        </noscript>
      </form>
      <script> setTimeout(function(){ try{ document.getElementById('autopay')?.submit(); }catch(e){} }, 350); </script>
    @endif
  </section>
</div>
@endsection

@push('scripts')
<script>
  document.documentElement.classList.add('page-register-pro');

  // Tema persistente
  (function(){
    const html=document.documentElement, KEY='p360-theme', btn=document.getElementById('rpThemeToggle');
    html.dataset.theme = localStorage.getItem(KEY) || 'light';
    const paint = ()=> btn.textContent = (html.dataset.theme==='dark') ? '☀️ Modo' : '🌙 Modo';
    btn.addEventListener('click', ()=>{ html.dataset.theme = (html.dataset.theme==='dark') ? 'light' : 'dark'; localStorage.setItem(KEY, html.dataset.theme); paint(); });
    paint();
  })();

  // Validaciones + honeypot + reCAPTCHA
  (function(){
    const form  = document.getElementById('regFormPro');
    const btn   = document.getElementById('submitBtn');
    const terms = document.getElementById('terms');
    const hasRecaptcha = !!document.querySelector('.g-recaptcha');

    function toggleBtn(){
      const termsOk = terms?.checked;
      if(!hasRecaptcha){
        btn.disabled = !termsOk;
      }else{
        const ok = (typeof grecaptcha !== 'undefined') && (grecaptcha.getResponse().length > 0);
        btn.disabled = !(termsOk && ok);
      }
    }
    btn && (btn.disabled = true);
    terms?.addEventListener('change', toggleBtn);

    // Uppercase RFC (extra por si el atributo falla)
    document.addEventListener('input', e => { if(e.target?.id==='rfc'){ e.target.value = e.target.value.toUpperCase(); } });

    // Honeypot
    form?.addEventListener('submit', function(ev){
      const hp = document.getElementById('hp_field');
      if(hp && hp.value.trim().length){ ev.preventDefault(); alert('Error de validación.'); }
    });

    // Poll reCAPTCHA
    if(hasRecaptcha){
      (function loop(){ try{ if(typeof grecaptcha!=='undefined' && grecaptcha.getResponse().length>0) toggleBtn(); }catch(e){} setTimeout(loop,380); })();
    }
    toggleBtn();
  })();
</script>
@if (env('RECAPTCHA_SITE_KEY'))
  <script src="https://www.google.com/recaptcha/api.js?hl=es" async defer></script>
@endif
@endpush
