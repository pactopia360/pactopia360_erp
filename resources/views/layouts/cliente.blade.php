{{-- resources/views/layouts/cliente.blade.php (v3.4 · FIX: main FULL-WIDTH por defecto + sidebar inicia expanded) --}}
@php
  use Illuminate\Support\Facades\File;
  use Illuminate\Support\Facades\Auth;

  $title = trim($__env->yieldContent('title', 'P360 · Cliente'));
  $theme = session('client_ui.theme','light'); // 'light' | 'dark'

  // Usuario y cuenta espejo (mysql_clientes)
  $user   = Auth::guard('web')->user();
  $cuenta = $cuenta ?? ($user->cuenta ?? null);

  // Si hay resumen de cuenta (HomeController::buildAccountSummary), úsalo para el plan/ciclo
  $summaryPlan = null;
  $summaryCycle = null;
  if (isset($summary) && is_array($summary)) {
      if (!empty($summary['plan'])) $summaryPlan = strtoupper((string) $summary['plan']);
      if (!empty($summary['cycle'])) $summaryCycle = (string) $summary['cycle'];
  }

  // Plan: prioriza summary.plan, luego plan_actual, luego plan, luego FREE
  $planRaw = $summaryPlan ?? ($cuenta->plan_actual ?? $cuenta->plan ?? 'FREE');
  $plan    = strtoupper((string) $planRaw);

  // Ciclo de facturación (monthly/yearly) si existe
  $billingCycle = $cuenta->billing_cycle ?? $summaryCycle ?? null;

  $viteManifest = public_path('build/manifest.json');
  $hasViteBuild = File::exists($viteManifest);

  $fallbackCss = asset('assets/client/css/app.css');
  $fallbackJs  = asset('assets/client/js/app.js');

  $coreCss = asset('assets/client/css/core-ui.css');
  $demoJs  = asset('assets/client/js/p360-demo-mode.js');
  $vaultThemeCss = asset('assets/client/css/p360-vault-theme.css');
@endphp
<!DOCTYPE html>
<html lang="es" class="theme-{{ $theme }}" data-theme="{{ $theme }}" data-plan="{{ strtolower($plan) }}">
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
  <link rel="stylesheet" href="{{ $vaultThemeCss }}?v=1.0">

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
      --header: 64px;
      --header-h: var(--header);

      --brand:   #E11D48;
      --brand-2: #BE123C;
      --accent:  #0EA5E9;

      /* FIX: por defecto FULL WIDTH (ya no centra home) */
      --container-max: none;
      --container-px: 30px;

      /* Línea rosa del header/footer */
      --p360-rail: color-mix(in oklab, var(--brand-red, #E11D48) 28%, transparent);
      --p360-rail-h: 2px;

      /* Sidebar widths (MISMO que sidebar.css) */
      --sb-w: 260px;      /* expandido */
      --sb-wc: 68px;      /* colapsado */

      /* Footer */
      --footer-h: 40px;
      --footer-offset: 8px;
    }

    html, body{
      height:100%;
      font-family:'Poppins', ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
      text-rendering: optimizeLegibility;
      background:#fff;
    }

    /* ===== Header rail ===== */
    #p360-client{ position:relative; }
    #p360-client::after{
      content:"";
      position:absolute; left:0; right:0; bottom:0;
      height:var(--p360-rail-h);
      background:var(--p360-rail);
    }
    header.topbar{ border-bottom-width:1px; }

    /* ===== Shell base ===== */
    .shell{ display:block; }

    /* ===================================================================
       DESKTOP >= 1100px
       - Sidebar fixed
       - Main & Footer se mueven por CSS segun data-state (NO JS)
       =================================================================== */
    @media (min-width:1100px){

      .shell > .sidebar{
        position:fixed !important;
        left:0 !important;
        top:calc(var(--header-h,64px) - var(--p360-rail-h,2px)) !important;
        height:calc(100dvh - (var(--header-h,64px) - var(--p360-rail-h,2px))) !important;
        margin:0 !important;
        z-index:40 !important;
      }

      /* el ancho REAL del sidebar lo define su CSS; aquí lo reforzamos por estado */
      .shell > .sidebar[data-state="expanded"]{ width:var(--sb-w) !important; min-width:var(--sb-w) !important; }
      .shell > .sidebar[data-state="collapsed"]{ width:var(--sb-wc) !important; min-width:var(--sb-wc) !important; }

      main.content{
        position:fixed !important;
        top:var(--header-h,64px) !important;
        right:0 !important;
        bottom:calc(var(--footer-h) + var(--footer-offset)) !important;
        overflow:auto !important;
        -webkit-overflow-scrolling:touch !important;
        background:#fff !important;
      }

      /* MAIN left depende del estado */
      .shell > .sidebar[data-state="expanded"]  ~ main.content{ left:var(--sb-w)  !important; }
      .shell > .sidebar[data-state="collapsed"] ~ main.content{ left:var(--sb-wc) !important; }

      /* FOOTER también depende del estado */
      .shell > .sidebar[data-state="expanded"]  ~ main.content ~ .client-footer{ left:var(--sb-w)  !important; }
      .shell > .sidebar[data-state="collapsed"] ~ main.content ~ .client-footer{ left:var(--sb-wc) !important; }
    }

    /* ===================================================================
       MOBILE/TABLET < 1100px
       - Main full width
       =================================================================== */
    @media (max-width:1099.98px){
      main.content{
        position:fixed;
        top:var(--header-h,64px);
        left:0;
        right:0;
        bottom: calc(var(--footer-h) + var(--footer-offset));
        overflow:auto;
        -webkit-overflow-scrolling:touch;
        background:#fff;
      }
    }

    /* ============================================================
       CONTENEDOR DE PÁGINA (FIX: FULL WIDTH por defecto)
       ============================================================ */
    main.content .container{
      padding:var(--container-px);
      max-width:var(--container-max); /* none => full width */
      margin:0;                       /* ya NO centramos */
      width:100%;
      min-height:100%;
      box-sizing:border-box;
    }

    /* Si en el futuro quieres páginas contenidas (opt-in):
       en la vista agrega: <div class="container is-contained"> */
    main.content .container.is-contained{
      max-width:1440px;
      margin:0 auto;
    }

    /* Cards */
    .card{ background:#fff; border:1px solid var(--bd, #e5e7eb); box-shadow:0 6px 18px rgba(0,0,0,.05); }

    /* ===== Footer fixed ===== */
    .client-footer{
      height:var(--footer-h);
      font-size:12px;
      padding-inline:0 !important;
      border-top:none;
      position:fixed;
      bottom: var(--footer-offset);
      left:0;
      right:0;
      z-index:60;
      background:var(--card, #fff);
      display:flex;
      align-items:center;
      justify-content:center;
      line-height: 1.2;
    }
    .client-footer::before{
      content:"";
      position:absolute; top:0; left:0; right:0;
      height:var(--p360-rail-h);
      background:var(--p360-rail);
      pointer-events:none;
    }
    .client-footer > .container{
      /* footer sí puede ir contenido sin afectar el main */
      max-width:1440px;
      margin-inline:auto;
      padding-inline:var(--container-px);
      box-sizing:border-box;
      display:flex;
      align-items:center;
      justify-content:center;
      height:100%;
      padding-top:0; padding-bottom:0;
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
  @include('layouts.partials.client_header', [
      'renderDemoToggle' => true,
      'plan'             => $plan,
      'billingCycle'     => $billingCycle,
      'cuenta'           => $cuenta,
      'summary'          => $summary ?? null,
  ])

  <div class="shell">
    {{-- Sidebar (FIX: inicia expanded; el JS ya persiste colapsado/expandido en desktop) --}}
    @include('components.client.sidebar', ['id' => 'sidebar', 'isOpen' => true])

    {{-- Main (scrolleable) --}}
    <main id="clientMain" class="content @yield('pageClass')" role="main">
      <div class="container" id="shotArea">
        @yield('content')
      </div>
    </main>

    {{-- Footer (DEBE quedar dentro del .shell para que el selector ~ funcione) --}}
    @includeIf('layouts.partials.client_footer')
  </div>

  <script src="{{ $demoJs }}" defer></script>

  <script>
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
  </script>

  <script>
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
        const type = (b.getAttribute('data-shot') || 'jpg').toLowerCase();
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

  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

</body>
</html>
