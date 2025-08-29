{{-- resources/views/layouts/admin.blade.php --}}
{{-- Layout maestro del panel administrativo (Laravel 10/11/12) --}}
{{-- Compatible con resources/views/layouts/partials/* y con el orquestador PJAX --}}

@php
  $pageTitle       = trim($__env->yieldContent('title')) ?: 'Panel Administrativo · Pactopia360';
  $bodyTheme       = session('ui.theme', 'light'); // 'light' | 'dark'
  $htmlThemeClass  = $bodyTheme === 'dark' ? 'theme-dark' : 'theme-light';

  // Rutas de CSS (con cache-busting si existen)
  $BASE_ABS = public_path('assets/admin/css/base.css');   // tu skin global previo
  $UI_ABS   = public_path('assets/admin/css/ui.css');     // alterno si renombraste
  $APP_ABS  = public_path('assets/admin/css/app.css');    // si lo integraste en app.css
  $SB_ABS   = public_path('assets/admin/css/sidebar.css'); // skin del sidebar
  $FRAME_ABS= public_path('assets/admin/css/frame.css');   // estructura/scroll opcional

  $BASE_URL = is_file($BASE_ABS) ? asset('assets/admin/css/base.css').'?v='.filemtime($BASE_ABS) : null;
  $UI_URL   = is_file($UI_ABS)   ? asset('assets/admin/css/ui.css').'?v='.filemtime($UI_ABS)     : null;
  $APP_URL  = is_file($APP_ABS)  ? asset('assets/admin/css/app.css').'?v='.filemtime($APP_ABS)   : null;
  $SB_URL   = is_file($SB_ABS)   ? asset('assets/admin/css/sidebar.css').'?v='.filemtime($SB_ABS): null;
  $FRAME_URL= is_file($FRAME_ABS)? asset('assets/admin/css/frame.css').'?v='.filemtime($FRAME_ABS): null;

  // Elegimos 1 skin principal (el primero que exista)
  $SKIN_URL = $BASE_URL ?: ($UI_URL ?: $APP_URL);
@endphp

<!DOCTYPE html>
<html lang="es" class="h-100 {{ $htmlThemeClass }}" data-theme="{{ $bodyTheme }}">
  <head>
    {{-- Head global (meta, favicon, etc.) --}}
    @includeIf('layouts.partials.head')

    {{-- <title> robusto --}}
    @hasSection('title')
      <title>@yield('title')</title>
    @else
      <title>{{ $pageTitle }}</title>
    @endif

    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- ====== CRITICAL CSS (tokens) para evitar FOUC si tarda el skin ====== --}}
    <style id="p360-critical-tokens">
      :root{
        --bg:#f6f7f9; --text:#0f172a; --muted:#6b7280;
        --card-bg:#ffffff; --card-border:rgba(0,0,0,.08); --panel-bg:#f8fafc;
        --brand-red:#e11d48; --accent:#6366f1; --accent-2:#8b5cf6; --accent-3:#06b6d4;
        --success:#10b981; --danger:#ef4444; --warning:#f59e0b; --info:#3b82f6;
      }
      [data-theme="dark"]{
        --bg:#0b1220; --text:#e5e7eb; --muted:#9ca3af;
        --card-bg:#0f172a; --card-border:rgba(255,255,255,.08); --panel-bg:#0c172b;
      }
      /* Estructura mínima para que jamás “salte” el layout */
      html,body{height:100%}
      body{min-height:100vh;display:flex;flex-direction:column;overflow:hidden;background:var(--bg);color:var(--text)}
      .admin-app{flex:1 1 auto;min-height:0;display:flex;overflow:hidden}
      .admin-content{flex:1 1 auto;min-height:0;overflow:auto;-webkit-overflow-scrolling:touch}
      .page-container{padding:.75rem}
      .admin-footer{border-top:1px solid rgba(0,0,0,.05);margin-top:1rem}
      [data-theme="dark"] .admin-footer{border-top-color:rgba(255,255,255,.1)}
      .skiplink{position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden}
      .skiplink:focus{left:12px;top:12px;width:auto;height:auto;padding:8px 10px;background:#111827;color:#fff;border-radius:8px;z-index:9999;box-shadow:0 6px 18px rgba(0,0,0,.2)}
    </style>

    {{-- ====== SKIN / BASE (si existe) -> primero para pintar consistente ====== --}}
    @if ($SKIN_URL)
      <link id="css-admin-skin" rel="stylesheet" href="{{ $SKIN_URL }}">
    @endif

    {{-- ====== Sidebar skin (si existe) ====== --}}
    @if ($SB_URL)
      <link id="css-sidebar" rel="stylesheet" href="{{ $SB_URL }}">
    @endif

    {{-- ====== Frame/estructura (si existe) ====== --}}
    @if ($FRAME_URL)
      <link id="css-admin-frame" rel="stylesheet" href="{{ $FRAME_URL }}">
    @endif

    {{-- Estilos por vista (módulos) --}}
    @stack('styles')
  </head>

  <body class="min-h-screen bg-body text-body d-flex flex-column">
    <a href="#p360-main" class="skiplink">Saltar al contenido</a>

    {{-- Header / Topbar --}}
    @includeIf('layouts.partials.header')

    <div id="admin-app" class="admin-app d-flex flex-fill">
      {{-- Sidebar --}}
      @includeIf('layouts.partials.sidebar')

      {{-- Contenido principal (zona PJAX) --}}
      <main id="p360-main" class="admin-content flex-fill" role="main" aria-live="polite">
        @hasSection('page-header')
          <header id="page-header" class="page-header">@yield('page-header')</header>
        @else
          <header id="page-header" class="page-header"></header>
        @endif

        {{-- Mensajes flash --}}
        @if(session('status'))
          <div class="alert alert-success" role="alert">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
          <div class="alert alert-danger" role="alert">
            <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
          </div>
        @endif

        <div class="page-container">@yield('content')</div>

        <footer class="admin-footer text-muted">
          <div class="container-fluid py-3">
            <small>© {{ date('Y') }} Pactopia360 — Todos los derechos reservados.</small>
          </div>
        </footer>
      </main>
    </div>

    @includeIf('layouts.partials.novabot')
    @includeIf('layouts.partials.scripts')

    {{-- Guardas PJAX: fade + limpieza SOLO de CSS de MÓDULO --}}
    <script>
      (function(){
        var main = document.getElementById('p360-main');
        // IDs de hojas de estilo que inyectan los módulos (@push('styles'))
        var MOD_CSS_IDS = ['css-usuarios','css-perfiles','css-crud-module'];

        addEventListener('p360:pjax:before', function(){
          if (main) main.style.opacity = '.001';
          // Quitar hojas de módulos anteriores (no tocamos skin/base ni sidebar)
          MOD_CSS_IDS.forEach(function(id){
            var el = document.getElementById(id);
            if (el) el.remove();
          });
          document.body.classList.remove('sidebar-open');
        });

        addEventListener('p360:pjax:after', function(){
          requestAnimationFrame(function(){ if (main) main.style.opacity = ''; });
        });
      })();
    </script>

    @stack('scripts')
    @yield('scripts')

    <noscript>
      <div style="position:fixed;left:0;right:0;top:0;background:#b91c1c;color:#fff;padding:.5rem 1rem;z-index:99999;text-align:center">
        Para usar el panel correctamente, habilita JavaScript.
      </div>
    </noscript>

    {{-- ====== Fallback de sidebar SOLO si no existe su CSS ====== --}}
    @if (!$SB_URL)
      <style>
        #sidebar{
          --sb-w:264px; flex:0 0 var(--sb-w); width:var(--sb-w);
          background:#fff; border-right:1px solid rgba(17,24,39,.06);
          display:flex; flex-direction:column; min-height:0
        }
        [data-theme="dark"] #sidebar{background:#0f172a;border-right-color:rgba(255,255,255,.08)}
        #sidebar .sidebar-scroll{min-height:0; overflow:auto; flex:1 1 auto; padding:12px}
        #sidebar .menu{display:flex; flex-direction:column; gap:6px}
        #sidebar .menu-section{margin:6px 0 8px; font-weight:700; font-size:12px; letter-spacing:.02em; opacity:.7; padding:4px 8px}
        #sidebar .menu-item{display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:12px; color:var(--text); text-decoration:none; line-height:1.15}
        #sidebar .menu-item:hover{background:rgba(99,102,241,.08)}
        [data-theme="dark"] #sidebar .menu-item:hover{background:rgba(99,102,241,.18)}
        #sidebar .menu-item.active{
          background:linear-gradient(180deg,rgba(99,102,241,.12),rgba(99,102,241,.08));
          border:1px solid rgba(99,102,241,.35);
          box-shadow:0 4px 18px rgba(99,102,241,.15) inset,0 1px 6px rgba(0,0,0,.06);
        }
      </style>
    @endif
  </body>
</html>
