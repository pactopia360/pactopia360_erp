{{-- resources/views/layouts/cliente.blade.php (v3 · header/sidebar fijos, scroll solo contenido, footer full-bleed) --}}
@php
  use Illuminate\Support\Facades\File;

  $title = trim($__env->yieldContent('title', 'P360 · Cliente'));
  $theme = session('client_ui.theme','light'); // 'light' | 'dark'

  $viteManifest = public_path('build/manifest.json');
  $hasViteBuild = File::exists($viteManifest);

  $fallbackCss = asset('assets/client/css/app.css');
  $fallbackJs  = asset('assets/client/js/app.js');

  $coreCss = asset('assets/client/css/core-ui.css');
  $demoJs  = asset('assets/client/js/p360-demo-mode.js');
@endphp
<!DOCTYPE html>
<html lang="es" class="theme-{{ $theme }}" data-theme="{{ $theme }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="color-scheme" content="light dark">
  <title>{{ $title }}</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="{{ $coreCss }}">
  @if ($hasViteBuild)
    @vite(['resources/css/app.css','resources/js/app.js'])
  @else
    <link rel="stylesheet" href="{{ $fallbackCss }}">
    @if ($fallbackJs)
      <script src="{{ $fallbackJs }}" defer></script>
    @endif
  @endif

  <style>
    /* ===== Variables base ===== */
    :root{
      --header: 64px;                 /* se recalcula en runtime */
      --header-h: var(--header);      /* alias para sidebar/positioning */

      --brand:   #E11D48;
      --brand-2: #BE123C;
      --accent:  #0EA5E9;

      --container-max: 1440px;
      --container-px: 30px;

      /* Línea rosa del header/footer */
      --p360-rail: color-mix(in oklab, var(--brand-red, #E11D48) 28%, transparent);
      --p360-rail-h: 2px;

      /* Sidebar widths */
      --sb-w: 260px;      /* expandido */
      --sb-wc: 68px;      /* colapsado */
      --sb-cur: var(--sb-w); /* ancho actual (lo actualiza JS) */

      /* Footer */
      --footer-h: 40px;
    }
    [data-theme="light"]{ --brand:#E11D48; --brand-2:#BE123C; }

    html, body{
      height:100%;
      font-family:'Poppins', ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
      text-rendering: optimizeLegibility;
      background:#fff;
    }

    /* ===== Header (línea rosa inferior) ===== */
    #p360-client{ position:relative; }
    #p360-client::after{
      content:""; position:absolute; left:0; right:0; bottom:0; height:var(--p360-rail-h); background:var(--p360-rail);
    }
    header.topbar{ border-bottom-width:1px; }

    /* ===== Shell base ===== */
    .shell{ display:block; }

    /* ===== Sidebar fijo y alineado al header ===== */
    @media (min-width:1120px){
      .shell > .sidebar{
        position:fixed !important;
        left:0;
        top:calc(var(--header-h,64px) - var(--p360-rail-h,2px)) !important;
        height:calc(100dvh - (var(--header-h,64px) - var(--p360-rail-h,2px))) !important;
        width:var(--sb-cur);
        z-index:40;
        margin:0 !important;
      }
    }

    /* ===== Solo el contenido scrollea (entre header y footer) ===== */
    /* Móvil/estrecho: sidebar se comporta como off-canvas; el contenido ocupa todo el ancho */
    @media (max-width:1119.98px){
      main.content{
        position:fixed;
        top:var(--header-h,64px);
        left:0;
        right:0;
        bottom:var(--footer-h);
        overflow:auto;
        -webkit-overflow-scrolling:touch;
        background:#fff;
      }
    }
    /* Desktop: deja margen izquierdo del ancho del sidebar actual */
    @media (min-width:1120px){
      main.content{
        position:fixed;
        top:var(--header-h,64px);
        left:var(--sb-cur);
        right:0;
        bottom:var(--footer-h);
        overflow:auto;
        -webkit-overflow-scrolling:touch;
        background:#fff;
      }
    }

    /* Contenedor de página */
    main.content .container{
      padding:var(--container-px);
      max-width:var(--container-max);
      margin:0 auto;
      width:100%;
      min-height:100%;
      box-sizing:border-box;
    }

    /* Tarjetas y KPIs (estilo existente) */
    .card{ background:#fff; border:1px solid var(--bd, #e5e7eb); box-shadow:0 6px 18px rgba(0,0,0,.05); }
    .card--kpi, .kpi{
      background:#fff; border:1px solid var(--bd, #f0f0f0);
      border-top:3px solid color-mix(in oklab, var(--brand-red, #E11D48) 55%, transparent);
    }

    /* ===== Toolbar de capturas ===== */
    .shot-toolbar{ display:flex; gap:8px; align-items:center; justify-content:flex-end; margin:-6px 0 12px 0; flex-wrap:wrap; }
    .btn-shot{
      display:inline-flex; align-items:center; gap:6px;
      border:1px solid rgba(0,0,0,.12); background:transparent; color:inherit;
      padding:6px 10px; border-radius:10px; font-weight:700; cursor:pointer; user-select:none;
    }
    .btn-shot:hover{ background:rgba(0,0,0,.05) }
    html[data-theme="dark"] .btn-shot{ border-color:rgba(255,255,255,.22) }
    html[data-theme="dark"] .btn-shot:hover{ background:rgba(255,255,255,.08) }

    /* ===== Footer full-bleed, pegado al menú y delgado ===== */
    .client-footer{
      height:var(--footer-h);
      line-height:var(--footer-h);
      font-size:12px;
      padding-inline:0 !important;
      border-top:none;
      position:fixed;
      bottom:0;
      left:0;   /* valor por defecto (móvil) */
      right:0;
      z-index:39;
      background:var(--card, #fff);
    }
    @media (min-width:1120px){
      .client-footer{ left:var(--sb-cur); } /* arranca justo donde termina el sidebar */
    }
    .client-footer::before{
      content:""; position:absolute; top:0; left:0; right:0; height:var(--p360-rail-h); background:var(--p360-rail); pointer-events:none;
    }

    /* Si el partial del footer trae un .container interno, centramos texto y respetamos máximo */
    .client-footer > .container{
      max-width:var(--container-max);
      margin-inline:auto;
      padding-inline:var(--container-px);
      box-sizing:border-box;
    }


    /* ==== Fix footer visible: un poco más arriba + centrado + encima del main ==== */
:root{
  --footer-h: 40px;        /* altura */
  --footer-offset: 8px;    /* súbelo ~8px. Ajusta 6–10px si prefieres */
}

@media (max-width:1119.98px){
  main.content{
    bottom: calc(var(--footer-h) + var(--footer-offset));
  }
}
@media (min-width:1120px){
  main.content{
    bottom: calc(var(--footer-h) + var(--footer-offset));
  }
}

.client-footer{
  bottom: var(--footer-offset);      /* <- lo sube un poco */
  z-index: 60;                       /* por encima del contenido */
  display: flex;                     /* centra contenido vertical/horizontal */
  align-items: center;
  justify-content: center;
  line-height: 1.2;                  /* evita que se “corte” el texto */
}

/* Si el partial trae contenedor interno, que no meta márgenes verticales raros */
.client-footer > .container{
  display:flex; align-items:center; justify-content:center;
  height: 100%;
  padding-top: 0; padding-bottom: 0;
}

  </style>

  @stack('styles')
  @yield('head')
</head>
<body>
  <noscript>
    <div style="background:#FEF3C7;color:#92400E;padding:10px 14px;font-weight:700;border-bottom:1px solid #F59E0B">
      Activa JavaScript para usar Pactopia360 al 100%.
    </div>
  </noscript>

  {{-- Header --}}
  @include('layouts.partials.client_header', ['renderDemoToggle' => true])

  <div class="shell">
    {{-- Sidebar --}}
    @include('components.client.sidebar', ['id' => 'sidebar', 'isOpen' => false])

    {{-- Main (scrolleable) --}}
    <main id="clientMain" class="content" role="main">
      @includeIf('components.client.demo-toggle', ['bannerId'=>'p360DemoBannerTop','storageKey'=>'p360_demo_mode'])

      <div class="container" id="shotArea">
        {{-- Toolbar de captura eliminada (quitamos botón JPG) --}}
        {{-- <div class="shot-toolbar" aria-label="Exportar captura del contenido">
          <button type="button" class="btn-shot" data-shot="jpg" data-shot-target="#clientMain">JPG</button>
        </div> --}}

        @yield('content')
      </div>
    </main>
  </div>

  {{-- Footer (global, fijo y alineado al menú) --}}
  @includeIf('layouts.partials.client_footer')

  <script src="{{ $demoJs }}" defer></script>

  <script>
    /* Sincroniza --header y --header-h con el alto real del header */
    function __p360SyncHeaderVars(){
      const el =
        document.getElementById('p360-client') ||
        document.querySelector('header.topbar') ||
        document.querySelector('[data-topbar]');
      const h  = el ? Math.round(el.getBoundingClientRect().height) : 64;
      const r  = document.documentElement.style;
      r.setProperty('--header',   h + 'px');
      r.setProperty('--header-h', h + 'px');
    }
    addEventListener('load', __p360SyncHeaderVars, {once:true});
    addEventListener('resize', __p360SyncHeaderVars);

    /* Mantén --sb-cur con el ancho actual del sidebar (expandido/colapsado) */
    (function(){
      const sb = document.getElementById('sidebar');
      const r  = document.documentElement.style;
      function getVar(name, fallback){ return getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback; }
      function applySbWidth(){
        const collapsed = sb?.getAttribute('data-state') === 'collapsed';
        r.setProperty('--sb-cur', collapsed ? getVar('--sb-wc','68px') : getVar('--sb-w','260px'));
      }
      if (window.MutationObserver && sb){
        new MutationObserver(applySbWidth).observe(sb, { attributes:true, attributeFilter:['data-state'] });
      }
      addEventListener('load', applySbWidth, {once:true});
      addEventListener('resize', applySbWidth);
    })();
  </script>

  <script>
    /* Captura (si existe html2canvas) */
    (function(){
      function downloadFromCanvas(canvas, type){
        const mime = (type==='jpg' || type==='jpeg') ? 'image/jpeg' : 'image/png';
        const url  = canvas.toDataURL(mime, 0.92);
        const a = document.createElement('a'); a.href = url; a.download = 'p360_captura.' + (type==='jpg'?'jpg':'png'); a.click();
      }
      async function capture(el, type){
        if (window.html2canvas && el){
          try{
            const canvas = await window.html2canvas(el, { useCORS:true, logging:false, backgroundColor:null, scale: window.devicePixelRatio || 1 });
            return downloadFromCanvas(canvas, type);
          }catch(e){}
        }
        try{ window.dispatchEvent(new CustomEvent('p360:capture', { detail:{ element: el, type } })); }catch(_){}
        try{ window.P360?.toast && P360.toast('Preparando captura…'); }catch(_){}
      }
      document.addEventListener('click', (e)=>{
        const b = e.target.closest('[data-shot]'); if(!b) return;
        e.preventDefault();
        const type = (b.getAttribute('data-shot') || 'jpg').toLowerCase(); // default ahora JPG
        const sel  = b.getAttribute('data-shot-target') || '#clientMain';
        const el   = document.querySelector(sel);
        if(!el){
          try{ window.P360?.toast?.error && P360.toast.error('No se encontró el área a capturar'); }catch(_){}
          return;
        }
        capture(el, type);
      });
    })();
  </script>

  @stack('scripts')
  @yield('scripts')
</body>
</html>
