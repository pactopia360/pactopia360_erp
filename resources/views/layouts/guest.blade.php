{{-- resources/views/layouts/guest.blade.php (v4.0 con Popup/Toast dinámico) --}}
@php
  // Permite ocultar la marca desde la vista hija: @section('hide-brand','1')
  $hideBrand = trim($__env->yieldContent('hide-brand')) === '1' || trim($__env->yieldContent('hide_global_brand')) === '1';

  // Prepara mensajes flash para el popup
  $flashOk    = session('ok');
  $flashError = $errors->any() ? $errors->first() : null;
  // Hint de clave del primer error (si existe)
  $flashErrKey = null;
  if (method_exists($errors, 'keys')) {
      $keys = $errors->keys();
      $flashErrKey = $keys[0] ?? null;
  }
@endphp
<!doctype html>
<html lang="es" data-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title','Pactopia 360')</title>
  <meta name="color-scheme" content="light dark">
  <style>
    /* Guest Layout con gradiente animado */
    :root{
      --p360-red:#ff2a2a; --p360-rose:#ff6b8a; --bg:#f6f7fb; --card:#fff; --text:#0e1626; --muted:#5f6f86; --border:#e6ebf2;
      --shadow:0 26px 90px rgba(11,42,58,.12); --focus:rgba(255,42,42,.20); --ring:#ff6b8a;
      --ok:#10b981; --ok-ink:#064e3b; --ok-soft:#e7fff5;
      --err:#ef4444; --err-ink:#7f1d1d; --err-soft:#fff1f2;
    }
    [data-theme="dark"]{
      --bg:#0b1120; --card:#0e172a; --text:#e7eef6; --muted:#9fb0c5; --border:#18263b; --shadow:0 34px 120px rgba(0,0,0,.6);
      --focus:rgba(255,42,42,.28); --ring:#ff6b8a;
      --ok:#34d399; --ok-ink:#093; --ok-soft:#062e24;
      --err:#f87171; --err-ink:#ffe5e5; --err-soft:#3a0a0a;
    }
    html,body{height:100%}
    body{
      margin:0;color:var(--text);
      background:
        radial-gradient(1600px 900px at 12% -10%, rgba(255,107,138,.20), transparent 60%),
        radial-gradient(1400px 900px at 100% 90%, rgba(70,100,160,.25), transparent 60%),
        linear-gradient(160deg, #0b2230 0%, #0f172a 60%, #091320 100%);
      font-family:-apple-system,BlinkMacSystemFont,"Inter","Segoe UI",Roboto,Helvetica,Arial;
      overflow-x:hidden;
    }
    body::before{
      content:""; position:fixed; inset:-30%;
      background:
        radial-gradient(46% 56% at 16% 12%, rgba(255,42,42,.30), transparent 60%),
        radial-gradient(40% 58% at 84% 70%, rgba(56,88,140,.40), transparent 60%);
      filter:blur(90px); opacity:.75; pointer-events:none; z-index:-1;
      animation:pan 22s ease-in-out infinite alternate;
    }
    @keyframes pan{0%{transform:translate3d(0,0,0) scale(1)}50%{transform:translate3d(-1.2%,1%,0) scale(1.02)}100%{transform:translate3d(1.2%,-1%,0) scale(1.01)}}
    .guest-wrap{min-height:100vh;display:grid;grid-template-rows:auto 1fr auto}

    .topbar{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 20px;background:transparent}
    .brand{display:flex;align-items:center;gap:10px;text-decoration:none;color:var(--text);padding:6px 8px;border-radius:12px}
    .brand.is-hidden{display:none !important;}
    .sr-only{position:absolute!important;width:1px;height:1px;margin:-1px;clip:rect(0,0,0,0);overflow:hidden}
    .theme-toggle{border:1px solid var(--border);background:transparent;color:var(--text);padding:8px 12px;border-radius:999px;cursor:pointer;font-weight:800;box-shadow:0 8px 26px rgba(0,0,0,.12)}

    .container{display:flex;align-items:center;justify-content:center;padding:clamp(16px,2.2vw,24px)}
    .card{width:min(1100px,96vw);background:color-mix(in srgb, var(--card) 92%, transparent);border:1px solid var(--border);border-radius:22px;box-shadow:var(--shadow);padding:clamp(16px,2.2vw,26px)}

    .footer{padding:14px 20px;text-align:center;color:var(--muted);font-size:12px}
    .pill{display:inline-flex;gap:8px;align-items:center;padding:6px 10px;border-radius:999px;border:1px solid var(--border);background:color-mix(in srgb, var(--card) 75%, transparent);color:var(--muted);font-weight:800;font-size:12px}

    /* Anti-logo automático para páginas login/registro */
    html.page-register-free .topbar .brand,
    html.page-register-pro  .topbar .brand,
    html.page-login-client  .topbar .brand{ display:none !important; }

    /* ===========================
       Popup / Toast dinámico
       =========================== */
    .toast-overlay{
      position:fixed; inset:0; display:none; place-items:center; backdrop-filter:blur(3px);
      background:rgba(2,8,23,.28); z-index:9999; animation:fadeIn .15s ease-out forwards;
    }
    .toast-overlay.is-open{ display:grid; }
    @keyframes fadeIn{from{opacity:0}to{opacity:1}}

    .toast-card{
      width:min(560px,92vw); border-radius:18px; border:1px solid color-mix(in srgb, var(--border) 82%, transparent);
      background:linear-gradient(180deg, color-mix(in srgb,var(--card) 94%, transparent), color-mix(in srgb,var(--card) 88%, transparent));
      box-shadow:0 40px 120px rgba(0,0,0,.38); padding:18px 18px 16px; transform:translateY(10px); opacity:0;
      animation:popIn .22s ease-out forwards;
    }
    @keyframes popIn{to{transform:translateY(0);opacity:1}}

    .toast-head{ display:flex; align-items:center; gap:10px; margin-bottom:8px }
    .toast-ico{
      display:grid; place-items:center; width:36px; height:36px; border-radius:12px;
      background:var(--ok-soft); color:var(--ok); font-size:18px; box-shadow:inset 0 0 0 1px color-mix(in srgb, var(--ok) 40%, transparent);
    }
    .toast-ico.err{ background:var(--err-soft); color:var(--err); box-shadow:inset 0 0 0 1px color-mix(in srgb, var(--err) 40%, transparent); }
    .toast-title{ font-weight:900; font-size:16px }
    .toast-body{ color:var(--muted); font-size:14px; margin:6px 0 2px; line-height:1.4 }
    .toast-actions{ display:flex; gap:8px; margin-top:10px; justify-content:flex-end; }
    .btn-toast{
      border:0; padding:10px 12px; border-radius:12px; font-weight:900; cursor:pointer;
      background:color-mix(in srgb, var(--card) 90%, transparent); color:var(--text); border:1px solid color-mix(in srgb, var(--border) 80%, transparent);
    }
    .btn-primary{
      background:linear-gradient(180deg, var(--p360-rose), var(--p360-red));
      color:#fff; box-shadow:0 18px 40px rgba(255,42,42,.28);
    }
  </style>
  @stack('styles')
</head>
<body>
<div class="guest-wrap">
  <header class="topbar" role="banner">
    <a class="brand {{ $hideBrand ? 'is-hidden' : '' }}" href="{{ url('/') }}" aria-label="Inicio">
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="{{ asset('assets/client/logop360dark.png') }}">
        <img src="{{ asset('assets/client/logp360ligjt.png') }}" alt="Pactopia 360" height="28"
             onerror="this.src='{{ asset('assets/client/logop360dark.png') }}';">
      </picture>
      <span class="sr-only">Pactopia 360</span>
    </a>

    <button class="theme-toggle" id="themeToggle" type="button" aria-live="polite">
      <span id="themeIcon" aria-hidden="true">🌙</span><span class="sr-only">Cambiar tema</span>
    </button>
  </header>

  <main id="main" class="container" role="main">
    <div class="card">@yield('content')</div>
  </main>

  <footer class="footer" role="contentinfo">
    <span class="pill">Hecho para contadoras exigentes</span>
    <div>© {{ date('Y') }} Pactopia360. Todos los derechos reservados.</div>
  </footer>
</div>

<!-- Popup / Toast -->
<div class="toast-overlay" id="flashToast" role="dialog" aria-modal="true" aria-hidden="true">
  <div class="toast-card" role="document">
    <div class="toast-head">
      <div class="toast-ico" id="flashIcon" aria-hidden="true">✅</div>
      <div class="toast-title" id="flashTitle">Listo</div>
    </div>
    <div class="toast-body" id="flashBody">Operación completada.</div>
    <div class="toast-actions">
      <button class="btn-toast" id="flashClose">Cerrar</button>
      <a class="btn-toast btn-primary sr-only" id="flashCta" href="#"></a>
    </div>
  </div>
</div>

<script>
  // Persistencia de tema (light/dark)
  (function(){
    const root=document.documentElement, key='p360-theme', btn=document.getElementById('themeToggle'), icon=document.getElementById('themeIcon');
    const setIcon=(m)=> icon&&(icon.textContent=m==='dark'?'🌙':'☀️');
    const saved=localStorage.getItem(key);
    if(saved){ root.setAttribute('data-theme', saved); setIcon(saved); }
    else{ const init=matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'; root.setAttribute('data-theme', init); setIcon(init); }
    btn?.addEventListener('click', ()=>{ const next=root.getAttribute('data-theme')==='dark'?'light':'dark'; root.setAttribute('data-theme', next); localStorage.setItem(key,next); setIcon(next); });
  })();

  // Datos flash desde servidor (éxito / error)
  window.FLASH = {
    ok: @json($flashOk),
    error: @json($flashError),
    errorKey: @json($flashErrKey),
    // CTA opcional (por ejemplo, tras registro exitoso -> "Verificar teléfono")
    cta: null,
    ctaHref: null
  };

  (function(){
    const $ov = document.getElementById('flashToast');
    const $ico= document.getElementById('flashIcon');
    const $ti = document.getElementById('flashTitle');
    const $bd = document.getElementById('flashBody');
    const $cl = document.getElementById('flashClose');
    const $cta= document.getElementById('flashCta');

    function setIcon(type){
      $ico.classList.remove('err');
      if(type==='error'){ $ico.classList.add('err'); $ico.textContent='⚠️'; }
      else{ $ico.textContent='✅'; }
    }

    function show(type, title, body, opts={}){
      setIcon(type);
      $ti.textContent = title || (type==='error' ? 'Algo no salió bien' : '¡Listo!');
      $bd.textContent = body || (type==='error' ? 'Revisa los campos e intenta de nuevo.' : 'Operación completada.');

      // CTA opcional
      if(opts.cta && opts.ctaHref){
        $cta.textContent = opts.cta; $cta.href = opts.ctaHref; $cta.classList.remove('sr-only');
      }else{
        $cta.classList.add('sr-only');
      }

      $ov.classList.add('is-open');
      $ov.setAttribute('aria-hidden','false');

      // Auto-cierre
      const timeout = setTimeout(hide, 7000);
      // Cierre manual
      $cl.onclick = ()=>{ clearTimeout(timeout); hide(); };
      $ov.onclick = (e)=>{ if(e.target === $ov){ clearTimeout(timeout); hide(); } };
      document.addEventListener('keydown', escOnce);
      function escOnce(ev){ if(ev.key==='Escape'){ clearTimeout(timeout); hide(); document.removeEventListener('keydown', escOnce);} }
      function hide(){ $ov.classList.remove('is-open'); $ov.setAttribute('aria-hidden','true'); }
    }

    // Heurísticas de mensaje amable
    const f = window.FLASH || {};
    if(f.ok){
      // Éxito genérico; si venimos del registro FREE, proponemos ir a verificar teléfono
      const isVerifyEmail = /verific(a|ación).*correo/i.test(document.title) || window.location.href.includes('/cliente/verificar/email');
      const isRegister    = /registro/i.test(document.title) || window.location.href.includes('/cliente/registro');
      const opts = {};
      if(isRegister || isVerifyEmail){
        opts.cta = 'Verificar teléfono';
        opts.ctaHref = "{{ route('cliente.verify.phone') }}";
      }
      show('success', '¡Felicidades! 🎉', f.ok, opts);
    } else if(f.error){
      let title = 'Revisa tu información';
      // Si el primer error está ligado a RFC, damos mensaje específico
      if((f.errorKey&&f.errorKey.toLowerCase()==='rfc') || /rfc/i.test(f.error)){
        title = 'Este RFC ya fue registrado';
      }
      show('error', title, f.error);
    }
  })();
</script>

@stack('scripts')
</body>
</html>
