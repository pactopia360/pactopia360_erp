{{-- resources/views/layouts/guest.blade.php --}}
@php
  // Ocultar marca superior desde vistas hijas: @section('hide-brand','1')
  $hideBrand = trim($__env->yieldContent('hide-brand')) === '1';

  // Flash
  $flashOk    = session('ok');
  $flashInfo  = session('info');
  $firstError = $errors->any() ? $errors->first() : null;
  $flashError = $firstError ?? session('error');

  // LOGOS (con espacios -> URL encoded)
  $logoLight = asset('assets/client/' . rawurlencode('P360 BLACK.png'));
  $logoDark  = asset('assets/client/' . rawurlencode('P360 WHITE.png'));
@endphp
<!doctype html>
<html lang="es" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>@yield('title','Pactopia360')</title>
  <meta name="color-scheme" content="light dark">

  <style>
    :root{
      /* Marca */
      --rose:#ff6b8a; --red:#ff2a2a;

      /* Light */
      --bg:#f7f9fc; --bg1:#ffffff; --bg2:#ecf1fb;
      --card:#ffffff; --text:#0f172a; --muted:#64748b; --border:#e6eaf2;

      /* Shadow */
      --shadow:0 26px 100px rgba(15,23,42,.12);

      /* Estados */
      --ok:#10b981; --ok-soft:#e7fff5;
      --err:#ef4444; --err-soft:#fff1f2;

      /* Radios */
      --r-card:22px; --r-pill:999px;

      --font:-apple-system,BlinkMacSystemFont,"Inter","Segoe UI",Roboto,Helvetica,Arial;
    }
    [data-theme="dark"]{
      --bg:#0a1020; --bg1:#0f172a; --bg2:#0b1324;
      --card:#0f172a; --text:#eaf2ff; --muted:#9fb0c5; --border:#1b2a40;
      --shadow:0 42px 160px rgba(0,0,0,.65);
      --ok:#34d399; --ok-soft:#062e24;
      --err:#f87171; --err-soft:#3a0a0a;
    }

    html,body{height:100%}
    body{
      margin:0; font-family:var(--font); color:var(--text);
      background:
        radial-gradient(1200px 780px at 12% -10%, color-mix(in srgb,var(--bg2) 55%, transparent), transparent 60%),
        radial-gradient(1100px 720px at 90% 110%, color-mix(in srgb,var(--bg2) 45%, transparent), transparent 60%),
        linear-gradient(180deg, var(--bg1), var(--bg));
      background-color:var(--bg);
    }

    /* Shell */
    .wrap{min-height:100vh; display:grid; grid-template-rows:auto 1fr auto}

    .topbar{display:flex; align-items:center; justify-content:space-between; padding:14px 18px}
    .brand{display:flex; align-items:center; gap:10px; text-decoration:none}
    .brand img{height:22px; object-fit:contain; display:block}
    .brand.is-hidden{display:none}

    .theme{border:1px solid var(--border); background:transparent; color:var(--text);
           padding:8px 12px; border-radius:var(--r-pill); font-weight:700; cursor:pointer; font-size:13px}

    /* CENTRADO REAL del contenido */
    .main-shell{
      display:grid; place-items:center;
      padding:clamp(16px,3vw,28px);
    }

    .footer{padding:16px 18px; text-align:center; color:var(--muted); font-size:12px}

    /* Toast */
    .toast{position:fixed; inset:0; display:none; place-items:center; background:rgba(2,8,23,.28); backdrop-filter:blur(3px); z-index:50}
    .toast.is-open{display:grid}
    .toast-card{width:min(560px,92vw); border:1px solid var(--border); border-radius:16px; background:var(--card);
                color:var(--text); box-shadow:var(--shadow); padding:16px 18px}
    .toast-h{display:flex; align-items:center; gap:10px; margin-bottom:6px}
    .toast-ico{width:34px; height:34px; border-radius:12px; display:grid; place-items:center; font-weight:800}
    .ok{background:var(--ok-soft); color:var(--ok)} .err{background:var(--err-soft); color:var(--err)}
    .toast-actions{margin-top:10px; display:flex; gap:8px; justify-content:flex-end}
    .btn{border:1px solid var(--border); background:transparent; color:var(--text); border-radius:12px; padding:10px 12px; font-weight:700; cursor:pointer}
    .btn-primary{border:0; color:#fff; background:linear-gradient(180deg, var(--rose), var(--red))}

    *{transition:background .25s,color .25s,border-color .25s,box-shadow .25s,filter .25s}

    /* ===== Toolbar de captura (PNG/JPG) ===== */
    .shot-toolbar{
      display:flex; gap:8px; align-items:center; justify-content:flex-end;
      width:min(980px, 92vw);
      margin: 0 auto 10px auto;
      transform: translateY(-6px);
      flex-wrap:wrap;
    }
    .btn-shot{
      display:inline-flex; align-items:center; gap:6px;
      border:1px solid var(--border);
      background:transparent; color:inherit;
      padding:6px 10px; border-radius:12px; font-weight:700; cursor:pointer;
      user-select:none; font-size:13px;
    }
    .btn-shot:hover{ background:rgba(0,0,0,.05) }
    [data-theme="dark"] .btn-shot{ border-color:rgba(255,255,255,.22) }
    [data-theme="dark"] .btn-shot:hover{ background:rgba(255,255,255,.08) }
  </style>

  @stack('styles')
</head>
<body>
<div class="wrap">
  <header class="topbar">
    <a class="brand {{ $hideBrand ? 'is-hidden' : '' }}" href="{{ url('/') }}" aria-label="Inicio">
      <img id="brandLogo" src="{{ $logoLight }}" alt="Pactopia360">
    </a>
    <button class="theme" id="themeBtn"><span id="themeIco">🌙</span> Tema</button>
  </header>

  {{-- Toolbar de captura arriba del contenido centrado --}}
  <div class="shot-toolbar" aria-label="Exportar captura del contenido">
    <button type="button" class="btn-shot" data-shot="png" data-shot-target="#guestMain">🖼️ PNG</button>
    <button type="button" class="btn-shot" data-shot="jpg" data-shot-target="#guestMain">JPG</button>
  </div>

  <main id="guestMain" class="main-shell">@yield('content')</main>

  <footer class="footer">
    {{-- Sin lema para mantener limpio --}}
    © {{ date('Y') }} Pactopia360. Todos los derechos reservados.
  </footer>
</div>

<!-- Toast -->
<div class="toast" id="toast">
  <div class="toast-card">
    <div class="toast-h"><div class="toast-ico ok" id="toastIco">✅</div><strong id="toastTitle">Listo</strong></div>
    <div id="toastBody" style="color:var(--muted); font-size:14px">Operación completada.</div>
    <div class="toast-actions"><button class="btn" id="toastClose">Cerrar</button></div>
  </div>
</div>

<script>
  // Theme + logo swap
  (function(){
    const root = document.documentElement;
    const btn  = document.getElementById('themeBtn');
    const ico  = document.getElementById('themeIco');
    const logo = document.getElementById('brandLogo');
    const path = { light: @json($logoLight), dark: @json($logoDark) };

    function apply(mode){
      root.setAttribute('data-theme', mode);
      ico.textContent = mode === 'dark' ? '🌙' : '☀️';
      if (logo) logo.src = mode === 'dark' ? path.dark : path.light;
    }
    const saved = localStorage.getItem('p360-theme');
    if (saved === 'light' || saved === 'dark') apply(saved);
    else apply(matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');

    btn.addEventListener('click', ()=>{
      const next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      localStorage.setItem('p360-theme', next); apply(next);
    });
  })();

  // Flash -> Toast
  (function(){
    const ok   = @json($flashOk);
    const info = @json($flashInfo);
    const err  = @json($flashError);
    const $ov  = document.getElementById('toast');
    const $ico = document.getElementById('toastIco');
    const $ti  = document.getElementById('toastTitle');
    const $bd  = document.getElementById('toastBody');
    const $cl  = document.getElementById('toastClose');

    function show(type, title, body){
      $ico.className = 'toast-ico ' + (type === 'error' ? 'err' : 'ok');
      $ico.textContent = type === 'error' ? '⚠️' : '✅';
      $ti.textContent = title || (type === 'error' ? 'Revisa tu información' : '¡Listo!');
      $bd.textContent = body  || (type === 'error' ? 'Hubo un problema.' : 'Operación completada.');
      $ov.classList.add('is-open'); const t = setTimeout(hide, 6000);
      $cl.onclick = ()=>{ clearTimeout(t); hide(); };
      $ov.onclick = (e)=>{ if (e.target === $ov){ clearTimeout(t); hide(); } };
      function hide(){ $ov.classList.remove('is-open'); }
    }
    if (err)  { show('error', null, err); return; }
    if (info) { show('ok', 'Info', info); return; }
    if (ok)   { show('ok', '¡Bien!', ok); return; }
  })();

  // ===== Captura (png/jpg) usando html2canvas si existe; si no, emite evento p360:capture =====
  (function(){
    function downloadFromCanvas(canvas, type){
      const mime = (type==='jpg' || type==='jpeg') ? 'image/jpeg' : 'image/png';
      const url  = canvas.toDataURL(mime, 0.92);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'p360_captura.' + (type==='jpg'?'jpg':'png');
      a.click();
    }
    async function capture(el, type){
      if (window.html2canvas && el){
        try{
          const canvas = await window.html2canvas(el, {
            useCORS:true, logging:false, backgroundColor:null, scale: window.devicePixelRatio || 1
          });
          return downloadFromCanvas(canvas, type);
        }catch(e){}
      }
      // Fallback para tu loader/orquestador global
      try{
        window.dispatchEvent(new CustomEvent('p360:capture', { detail:{ element: el, type } }));
      }catch(_){}
    }

    document.addEventListener('click', (e)=>{
      const b = e.target.closest('[data-shot]');
      if(!b) return;
      e.preventDefault();
      const type = (b.getAttribute('data-shot') || 'png').toLowerCase();
      const sel  = b.getAttribute('data-shot-target') || '#guestMain';
      const el   = document.querySelector(sel);
      if(!el) return;
      capture(el, type);
    });
  })();
</script>

@stack('scripts')
</body>
</html>
