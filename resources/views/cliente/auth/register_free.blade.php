{{-- resources/views/cliente/auth/register_free.blade.php (v2.1 visual refinado Pactopia360) --}}
@extends('layouts.guest')

@section('title', 'Registro FREE · Pactopia360')
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

  html.page-register-free body{
    background:var(--bg);
    min-height:100vh;
    overflow:hidden;
    font-family:'Poppins',system-ui,sans-serif;
    color:var(--text);
  }

  /* Fondo dinámico tipo nube */
  html.page-register-free body::before,
  html.page-register-free body::after{
    content:"";position:fixed;inset:-25%;z-index:-1;filter:blur(100px);pointer-events:none;
  }
  html.page-register-free body::before{
    background:
      radial-gradient(40% 60% at 18% 22%, var(--g2), transparent 60%),
      radial-gradient(45% 65% at 82% 20%, var(--g1), transparent 60%);
    animation:floatA 12s ease-in-out infinite alternate;
  }
  html.page-register-free body::after{
    background:
      radial-gradient(60% 80% at 50% 80%, var(--g3), transparent 60%),
      radial-gradient(40% 50% at 80% 85%, var(--g4), transparent 60%);
    animation:floatB 16s ease-in-out infinite alternate;
  }
  @keyframes floatA{0%{transform:translate3d(-5%,-5%,0)}100%{transform:translate3d(6%,8%,0)}}
  @keyframes floatB{0%{transform:translate3d(5%,7%,0)}100%{transform:translate3d(-6%,-8%,0)}}

  /* Layout */
  .wrap{display:grid;place-items:start center;height:100vh;}
  .card{
    width:min(520px,92vw);
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

  .badge-free{
    display:inline-flex;align-items:center;gap:8px;
    padding:5px 9px;border-radius:999px;background:linear-gradient(90deg,#E11D48,#BE123C);
    color:#fff;font-size:11px;font-weight:800;
    margin:6px auto 0;box-shadow:0 8px 20px rgba(225,29,72,.25);
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

  /* Ocultar honeypot (anti-bots) */
  .hp{position:absolute;left:-9999px;opacity:0;width:1px;height:1px;overflow:hidden;}

  .terms{font-size:12px;color:var(--muted);margin:6px 0;line-height:1.45;}
  .terms a{font-weight:800;text-decoration:none;color:var(--brand);}
  .terms a:hover{text-decoration:underline;}
  .terms input{accent-color:var(--brand);margin-right:8px;}

  .actions{margin-top:12px;display:flex;gap:10px;align-items:center;}
  .btn{
    border:0;padding:11px 14px;border-radius:12px;
    font-weight:900;cursor:pointer;transition:filter .12s ease;
  }
  .btn-primary{
    flex:1;color:#fff;background:linear-gradient(90deg,#E11D48,#BE123C);
    box-shadow:0 12px 22px rgba(225,29,72,.28);
  }
  .btn-primary:hover{filter:brightness(.96);}
  .btn-primary[disabled]{background:linear-gradient(90deg,#cbd5e1,#94a3b8);color:#f1f5f9;box-shadow:none;cursor:not-allowed;}
  .btn-pro{
    padding:9px 14px;border-radius:999px;color:#fff;
    background:linear-gradient(90deg,#E11D48,#BE123C);
    box-shadow:0 8px 20px rgba(225,29,72,.25);text-decoration:none;white-space:nowrap;
  }
  .btn-pro:hover{filter:brightness(.96);}

  .login-cta{margin-top:10px;text-align:center;font-size:12px;}
  .login-cta a{font-weight:900;color:var(--brand);text-decoration:none;}
  .login-cta a:hover{text-decoration:underline;}

  .alert{
    border:1px solid var(--border);
    background:color-mix(in srgb,var(--card) 95%,transparent);
    color:var(--text);border-radius:12px;padding:10px 12px;font-size:12px;margin:8px 0;
  }
  .alert.ok{border-color:color-mix(in srgb,var(--ok) 40%,var(--border));background:color-mix(in srgb,var(--ok) 8%,var(--card));}
  .alert.err{border-color:color-mix(in srgb,var(--bad) 45%,var(--border));background:color-mix(in srgb,var(--bad) 8%,var(--card));}

  /* Modal */
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
  .btn-ghost{border:1px solid var(--border);background:transparent;color:var(--text);}
</style>
@endpush

@section('content')
@php use Illuminate\Support\Str; @endphp
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

      @php $recaptchaKey = env('RECAPTCHA_SITE_KEY'); @endphp
      @if ($recaptchaKey)
        <div style="margin-top:10px"><div class="g-recaptcha" data-sitekey="{{ $recaptchaKey }}"></div></div>
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
</script>
@if (env('RECAPTCHA_SITE_KEY'))
<script src="https://www.google.com/recaptcha/api.js?hl=es" async defer></script>
@endif
@endpush
