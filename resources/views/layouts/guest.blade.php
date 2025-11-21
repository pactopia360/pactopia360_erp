{{-- resources/views/layouts/guest.blade.php --}}
@php
  // Ocultar marca superior desde vistas hijas: @section('hide-brand','1')
  $hideBrand = true; // Forzamos NO mostrar logo arriba en ninguna vista guest

  // Flash
  $flashOk    = session('ok');
  $flashInfo  = session('info');
  $firstError = $errors->any() ? $errors->first() : null;
  $flashError = $firstError ?? session('error');

  // LOGOS (tema claro / oscuro) – ya solo los usan las tarjetas internas si quieren
  $logoLight = asset('assets/client/p360-black.png');
  $logoDark  = asset('assets/client/p360-white.png');
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

    .topbar{
      display:flex; align-items:center; justify-content:flex-start;
      padding:14px 18px;
    }
    /* Ya NO hay logo aquí: se deja vacío a propósito */

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
  </style>

  @stack('styles')
</head>
<body>
<div class="wrap">
  <header class="topbar">
    {{-- Sin logo aquí para evitar doble marca: los formularios pintan su propio logo --}}
  </header>

  <main id="guestMain" class="main-shell">@yield('content')</main>

  <footer class="footer">
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
  // Inicializar tema desde localStorage (sin botón externo)
  (function(){
    const root  = document.documentElement;
    const saved = localStorage.getItem('p360-theme');
    const mode  = (saved === 'dark' || saved === 'light')
      ? saved
      : (window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    root.setAttribute('data-theme', mode);
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
</script>

@stack('scripts')
</body>
</html>
