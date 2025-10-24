{{-- resources/views/cliente/auth/register_free.blade.php --}}
@extends('layouts.guest')

@section('title', 'Registro FREE · Pactopia360')

@push('styles')
<style>
  :root{
    --bg:#f6f7fb; --text:#0f172a; --muted:#6b7280; --card:#ffffff; --border:#e5e7eb;
    --brand:#3b82f6; --brand-2:#2563eb; --accent:#7c9cff;
    --ok:#16a34a; --bad:#ef4444; --ring:#9cc8ff;
    --g1: rgba(124,156,255,.30); --g2: rgba(255,130,160,.26);
    --g3: rgba(160,120,255,.22); --g4: rgba(255,180,210,.20);
  }
  html[data-theme="dark"]{
    --bg:#0b1220; --text:#e5e7eb; --muted:#9aa4b2; --card:#0f172a; --border:#1f2a44;
    --brand:#8aa3ff; --brand-2:#4f46e5; --accent:#8da8ff;
    --ok:#22c55e; --bad:#f87171; --ring:#6ea8ff;
    --g1: rgba(124,156,255,.16); --g2: rgba(255,130,160,.14);
    --g3: rgba(160,120,255,.14); --g4: rgba(255,180,210,.12);
  }

  /* ===== Kill-switch del layout (adiós 2º cuadro) ===== */
  html.page-register-free .container > .card,
  html.page-register-free .guest-card,
  html.page-register-free .guest-shell,
  html.page-register-free main > .card,
  html.page-register-free .layout-card,
  html.page-register-free .auth-card {
    background: transparent !important;
    border: 0 !important;
    box-shadow: none !important;
    padding: 0 !important;
    display: contents !important;
  }

  /* Fondo + contenedor de página */
  html.page-register-free body{ background:var(--bg); height:100svh; overflow:hidden; }
  html.page-register-free body::before,
  html.page-register-free body::after{
    content:""; position:fixed; inset:-25%; z-index:-1; filter:blur(90px); pointer-events:none;
  }
  html.page-register-free body::before{
    background:
      radial-gradient(42% 62% at 12% 18%, var(--g2), transparent 60%),
      radial-gradient(44% 62% at 88% 20%, var(--g1), transparent 60%);
    animation: floatA 10s ease-in-out infinite alternate;
  }
  html.page-register-free body::after{
    background:
      radial-gradient(55% 75% at 50% 86%, var(--g3), transparent 60%),
      radial-gradient(30% 42% at 78% 78%, var(--g4), transparent 60%);
    animation: floatB 12s ease-in-out infinite alternate;
  }
  @keyframes floatA{0%{transform:translate3d(-5%,-5%,0)}50%{transform:translate3d(5%,7%,0)}100%{transform:translate3d(8%,-7%,0)}}
  @keyframes floatB{0%{transform:translate3d(6%,9%,0)}50%{transform:translate3d(-6%,-6%,0)}100%{transform:translate3d(9%,11%,0)}}

  /* ===== Wrapper: anclado ARRIBA y centrado horizontal ===== */
  .wrap{
    height:100svh;               /* ocupa exactamente la altura de la pantalla */
    display:grid;
    place-items:start center;    /* arriba y centrado horizontal */
    padding:0;
  }

  /* ===== Un solo card, delgado ===== */
  .card{
    width:min(520px,92vw);
    margin-top:6vh;              /* lo sube: 6% de la altura de la pantalla */
    position:relative; background:var(--card); border:1px solid var(--border);
    border-radius:20px; padding:22px; box-shadow:0 22px 64px rgba(2,8,23,.14);
  }
  .card::before{
    content:""; position:absolute; inset:-1px; border-radius:21px; padding:1px;
    background:linear-gradient(135deg,var(--brand),var(--accent));
    -webkit-mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);
    -webkit-mask-composite:xor; mask-composite:exclude; opacity:.22; pointer-events:none;
  }

  /* Header */
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

  .badge-free{
    display:inline-flex; align-items:center; gap:8px; padding:5px 9px; border-radius:999px;
    background:#111827; color:#fff; font-size:11px; font-weight:800; box-shadow:0 8px 20px rgba(17,24,39,.25);
    margin:8px auto 0; display:block; width:max-content;
  }
  html[data-theme="dark"] .badge-free{ background:#0b1220; }

  .title{ text-align:center; margin-top:6px }
  .title h1{ margin:0; font-size:20px; font-weight:900; color:var(--text); }
  .title p{ margin:6px 0 0; color:var(--muted); font-size:12px }

  /* Formulario compacto */
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
  .btn-pro{ padding:9px 14px; border-radius:999px; color:#fff; background:linear-gradient(180deg,#7c9cff,#3b82f6); box-shadow:0 8px 20px rgba(59,130,246,.25); text-decoration:none; white-space:nowrap }
  .btn-pro:hover{ filter:brightness(.97) }

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

  /* Modal */
  .modal{ position:fixed; inset:0; display:none; align-items:center; justify-content:center; background:rgba(2,8,23,.45); z-index:9999; }
  .modal[aria-hidden="false"]{ display:flex; }
  .modal-card{
    width:min(520px, 92vw); border-radius:18px; padding:18px;
    background:var(--card); color:var(--text); border:1px solid var(--border);
    box-shadow:0 24px 80px rgba(2,8,23,.45); animation: pop .18s ease-out;
  }
  @keyframes pop{ from{ transform:scale(.96); opacity:.4 } to{ transform:scale(1); opacity:1 } }
  .modal-title{ margin:0 0 6px; font-size:18px; font-weight:900 }
  .modal-body{ font-size:14px; color:var(--muted) }
  .modal-actions{ display:flex; justify-content:flex-end; gap:10px; margin-top:12px }
  .btn-ghost{ border:1px solid var(--border); background:transparent; color:var(--text) }
</style>
@endpush

@section('content')
  <div class="wrap">
    <section class="card" role="region" aria-label="Registro FREE">
      {{-- Tema --}}
      <button type="button" class="theme" id="rfThemeToggle" aria-label="Cambiar tema">🌙 Modo</button>

      {{-- Branding --}}
      <div class="brand">
        <img class="logo logo-light" src="{{ asset('assets/client/logp360ligjt.png') }}" alt="Pactopia 360">
        <img class="logo logo-dark"  src="{{ asset('assets/client/logop360dark.png') }}" alt="">
      </div>

      <span class="badge-free">FOR EVER FREE</span>

      <div class="title">
        <h1>Crear cuenta GRATIS</h1>
        <p>Completa tus datos para comenzar. Te pediremos verificar tu correo y teléfono.</p>
      </div>

      {{-- Alertas servidor --}}
      @if (session('ok'))<div class="alert ok" role="status">{{ session('ok') }}</div>@endif
      @if ($errors->any())<div class="alert err" role="alert">{{ $errors->first() }}</div>@endif

      {{-- Formulario --}}
      <form method="POST" action="{{ route('cliente.registro.free.do') }}" novalidate id="regForm" class="form">
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
          <div class="help">Nombre y apellidos tal como deseas que aparezcan.</div>
          @error('nombre')<div class="help error">{{ $message }}</div>@enderror
        </div>

        <div class="field">
          <label class="label" for="email">Correo electrónico *</label>
          <input class="input @error('email') is-invalid @enderror"
                 type="email" name="email" id="email" value="{{ old('email') }}"
                 required maxlength="150" autocomplete="email" placeholder="micorreo@dominio.com"
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
          <div class="help">No se permiten cuentas duplicadas por RFC.</div>
          @error('rfc')<div class="help error">{{ $message }}</div>@enderror
        </div>

        <div class="field">
          <label class="label" for="telefono">Teléfono *</label>
          <input class="input @error('telefono') is-invalid @enderror"
                 type="tel" name="telefono" id="telefono" value="{{ old('telefono') }}"
                 required maxlength="25" autocomplete="tel" placeholder="+52 55 1234 5678"
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
        @else
          <div class="help" style="margin-top:10px">
            <strong>Nota (solo local):</strong> Configura <code>RECAPTCHA_SITE_KEY</code> en <code>.env</code> para habilitar el captcha y evitar errores.
          </div>
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
        <button class="btn btn-ghost" type="button" id="rfModalClose">Cerrar</button>
        <button class="btn btn-primary" type="button" id="rfModalOk">Entendido</button>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
<script>
  document.documentElement.classList.add('page-register-free');

  // Tema
  (function(){
    const html = document.documentElement;
    const KEY  = 'p360-theme';
    const btn  = document.getElementById('rfThemeToggle') || document.querySelector('.theme');
    const set  = v => { html.dataset.theme = v; localStorage.setItem(KEY,v); paint(); };
    const paint= () => { if(btn) btn.textContent = (html.dataset.theme==='dark') ? '☀️ Modo' : '🌙 Modo'; };
    set(localStorage.getItem(KEY) || 'light');
    btn?.addEventListener('click', ()=> set(html.dataset.theme==='dark' ? 'light' : 'dark'));
  })();

  // Modal helpers
  const $modal = document.getElementById('rfModal');
  const $mt    = document.getElementById('rfModalTitle');
  const $mb    = document.getElementById('rfModalBody');
  const $mc    = document.getElementById('rfModalClose');
  const $mo    = document.getElementById('rfModalOk');

  const SHOULD_CLEAR = {{ session('clear_form') ? 'true' : 'false' }};

  function showModal(t, msg, autoCloseMs=4600){
    $mt.textContent = t || 'Aviso';
    $mb.textContent = msg || '';
    $modal.setAttribute('aria-hidden','false');

    const closeAll = () => {
      hideModal();
      if (SHOULD_CLEAR) {
        const form = document.getElementById('regForm');
        if (form) {
          form.reset();
          ['nombre','email','rfc','telefono'].forEach(id=>{ const el=document.getElementById(id); if(el) el.value=''; });
          const terms = document.getElementById('terms'); if (terms) terms.checked = false;
          document.getElementById('submitBtn').disabled = true;
        }
      }
    };

    const timer = setTimeout(closeAll, autoCloseMs);
    [$mc,$mo].forEach(btn=>btn.onclick=()=>{ clearTimeout(timer); closeAll(); });
    $modal.onclick = (e)=>{ if(e.target===$modal){ clearTimeout(timer); closeAll(); } };
    document.addEventListener('keydown', function esc(e){
      if(e.key==='Escape'){ clearTimeout(timer); closeAll(); document.removeEventListener('keydown', esc); }
    });
  }
  function hideModal(){ $modal.setAttribute('aria-hidden','true'); }

  // Habilitar submit (términos + recaptcha)
  (function () {
    const form  = document.getElementById('regForm');
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
    btn.disabled = true;
    terms?.addEventListener('change', toggleBtn);

    if(hasRecaptcha){
      (function loop(){ try{ if(typeof grecaptcha!=='undefined' && grecaptcha.getResponse().length>0) toggleBtn(); }catch(e){} setTimeout(loop,380); })();
    }

    // RFC a mayúsculas
    document.addEventListener('input', e => { if(e.target?.id==='rfc'){ e.target.value = e.target.value.toUpperCase(); } });

    // Honeypot
    form.addEventListener('submit', function(ev){
      const hp = document.getElementById('hp_field');
      if(hp && hp.value.trim().length){
        ev.preventDefault();
        showModal('Error de validación','No pudimos procesar tu solicitud. Intenta nuevamente.');
      }
    });

    // Popups backend
    @if (session('popup_ok'))
      showModal('¡Felicidades!', '{{ addslashes(session('popup_ok')) }}');
    @elseif (session('ok'))
      showModal('¡Felicidades!', '{{ addslashes(session('ok')) }}');
    @endif

    @if ($errors->any())
      @php $msg = $errors->first(); @endphp
      @if (Str::contains($msg, ['RFC ya fue registrado','RFC ya está en uso','ya fue registrado']))
        showModal('Lo sentimos','{{ addslashes($msg) }}');
      @elseif (Str::contains($msg, ['correo', 'email']))
        showModal('Correo no disponible','{{ addslashes($msg) }}');
      @else
        showModal('Revisa tu información','{{ addslashes($msg) }}');
      @endif
    @endif
  })();
</script>
@if (env('RECAPTCHA_SITE_KEY'))
  <script src="https://www.google.com/recaptcha/api.js?hl=es" async defer></script>
@endif
@endpush
