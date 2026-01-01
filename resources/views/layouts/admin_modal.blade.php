{{-- resources/views/layouts/admin_modal.blade.php (P360 · Admin Modal Shell v2.2 · FIX NESTED MODAL/IFRAME) --}}
<!doctype html>
<html lang="{{ str_replace('_','-', app()->getLocale()) }}" data-theme="{{ request()->cookie('theme','light') }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <title>@yield('title', config('app.name','Pactopia360'))</title>

  {{-- CSS base admin --}}
  <link rel="stylesheet" href="{{ asset('assets/admin/css/app.css') }}?v=1">
  <link rel="stylesheet" href="{{ asset('assets/admin/css/sidebar.css') }}?v=1">

  {{-- Estilos empujados por las vistas --}}
  @stack('styles')

  <style>
    :root{
      --p360-modal-bg: rgba(2,6,23,.55);
      --p360-card: #fff;
      --p360-line: rgba(15,23,42,.10);
      --p360-ink: #0f172a;
      --p360-mut: #64748b;
      --p360-r: 18px;
    }
    html[data-theme="dark"]{
      --p360-card: rgba(2,6,23,.92);
      --p360-line: rgba(255,255,255,.10);
      --p360-ink: #e5e7eb;
      --p360-mut: rgba(229,231,235,.70);
      --p360-modal-bg: rgba(0,0,0,.70);
    }

    body{
      margin:0;
      background: var(--p360-modal-bg);
      color: var(--p360-ink);
      font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
    }

    .wrap{
      min-height: 100dvh;
      display:flex;
      align-items:stretch;
      justify-content:center;
      padding: 18px;
      box-sizing:border-box;
    }

    .card{
      width: min(1500px, calc(100vw - 36px));
      background: var(--p360-card);
      border: 1px solid var(--p360-line);
      border-radius: var(--p360-r);
      box-shadow: 0 28px 80px rgba(0,0,0,.35);
      overflow:hidden;

      display:flex;
      flex-direction:column;
      max-height: calc(100dvh - 36px);
    }

    .topbar{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:12px;
      padding: 12px 14px;
      border-bottom: 1px solid var(--p360-line);
      background: rgba(255,255,255,.72);
      backdrop-filter: blur(10px) saturate(140%);
    }
    html[data-theme="dark"] .topbar{
      background: rgba(2,6,23,.55);
    }

    .ttl{
      min-width:0;
      display:flex;
      align-items:center;
      gap:10px;
    }
    .badge{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      height:24px;
      padding:0 10px;
      border-radius:999px;
      font-weight:900;
      font-size:12px;
      border:1px solid var(--p360-line);
      background: rgba(15,23,42,.04);
      color: var(--p360-ink);
      white-space:nowrap;
    }
    html[data-theme="dark"] .badge{ background: rgba(255,255,255,.06); }

    .meta{
      min-width:0;
      font-size:12px;
      font-weight:800;
      color: var(--p360-mut);
      white-space:nowrap;
      overflow:hidden;
      text-overflow:ellipsis;
    }

    .actions{
      display:flex;
      align-items:center;
      gap:10px;
      flex-wrap:wrap;
      justify-content:flex-end;
    }

    .btn{
      appearance:none;
      border:1px solid var(--p360-line);
      background:#fff;
      color: var(--p360-ink);
      font-weight:900;
      border-radius: 12px;
      padding: 10px 12px;
      cursor:pointer;
      text-decoration:none;
      display:inline-flex;
      align-items:center;
      gap:8px;
      white-space:nowrap;
    }
    html[data-theme="dark"] .btn{ background: rgba(255,255,255,.06); }

    .btn.primary{
      background:#0f172a;
      color:#fff;
      border-color: rgba(15,23,42,.22);
    }

    .kbd{
      border:1px solid var(--p360-line);
      border-radius:10px;
      padding: 6px 10px;
      font-size:12px;
      font-weight:950;
      color: var(--p360-mut);
      background: rgba(15,23,42,.03);
    }
    html[data-theme="dark"] .kbd{ background: rgba(255,255,255,.06); }

    /* CLAVE: el contenido vive aquí y SIEMPRE tiene scroll */
    .body{
      overflow:auto;
      flex: 1 1 auto;
      min-height: 220px;
      background: transparent;
    }

    /* =========================================================
       ✅ MODO EMBEBIDO (IFRAME) = SIN CHROME
       Evita “modal dentro de modal” feo (doble barra + doble fondo)
       ========================================================= */
    html.is-embed body{
      background: transparent !important;
    }
    html.is-embed .wrap{
      min-height: auto !important;
      padding: 0 !important;
      display:block !important;
    }
    html.is-embed .card{
      width: 100% !important;
      max-height: none !important;
      border: 0 !important;
      border-radius: 0 !important;
      box-shadow: none !important;
      background: transparent !important;
      overflow: visible !important;
    }
    html.is-embed .topbar{
      display:none !important;
    }
    html.is-embed .body{
      overflow: visible !important; /* deja que el modal padre controle scroll */
      min-height: 0 !important;
    }
  </style>
</head>

@php
  // “Abrir en nueva pestaña”: quita modal=1 de la URL
  $full = url()->full();
  $openUrl = preg_replace('/([&?])modal=1(&?)/', '$1', $full);
  $openUrl = rtrim((string)$openUrl, '&?');
@endphp

<body>
  <div class="wrap">
    <div class="card" role="dialog" aria-modal="true">
      <div class="topbar">
        <div class="ttl">
          <span class="badge">Administrar</span>
          <div class="meta">@yield('title', 'Detalle')</div>
        </div>

        <div class="actions">
          <a class="btn" href="{{ $openUrl }}">Abrir en nueva pestaña</a>
          <button class="btn primary" type="button" id="p360ModalCloseBtn">Cerrar</button>
          <span class="kbd">Esc</span>
        </div>
      </div>

      <div class="body" id="p360ModalBody">
        @yield('content')

        {{-- Compat extra --}}
        @yield('modal_content')
        @yield('body')
        @yield('modal_body')
      </div>
    </div>
  </div>

  @stack('scripts')

  <script>
    (function(){
      // ✅ Si estamos dentro de un iframe (modal embebido), quitar chrome automáticamente.
      try {
        if (window.self !== window.top) {
          document.documentElement.classList.add('is-embed');
        }
      } catch(e) {
        // Si el navegador bloquea acceso a window.top por seguridad, asumimos embed.
        document.documentElement.classList.add('is-embed');
      }

      const btn = document.getElementById('p360ModalCloseBtn');

      function closeModal(){
        try {
          if (window.opener && !window.opener.closed) { window.close(); return; }
        } catch(e){}

        try { history.back(); } catch(e){}
      }

      if (btn) btn.addEventListener('click', closeModal);

      window.addEventListener('keydown', function(e){
        if ((e.key || '').toLowerCase() === 'escape') closeModal();
      });
    })();
  </script>
</body>
</html>
