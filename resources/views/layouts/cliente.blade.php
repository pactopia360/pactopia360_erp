{{-- resources/views/layouts/cliente.blade.php --}}
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
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

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
    :root{
      --header: 72px;
      --header-h: var(--header);

      /* ===== Branding Pactopia nuevo ===== */
      --brand:       #2563EB;
      --brand-2:     #1D4ED8;
      --brand-3:     #0F3FAE;
      --accent:      #60A5FA;
      --accent-2:    #38BDF8;
      --success:     #16A34A;
      --warning:     #F59E0B;
      --danger:      #EF4444;

      /* ===== Light ===== */
      --bg:          #F8FAFC;
      --bg-soft:     #FFFFFF;
      --card:        rgba(255,255,255,.88);
      --card-solid:  #FFFFFF;
      --ink:         #0F172A;
      --ink-2:       #1E293B;
      --muted:       #64748B;
      --bd:          rgba(15, 23, 42, .10);
      --bd-strong:   rgba(37, 99, 235, .18);
      --shadow-1:    0 12px 32px rgba(15, 23, 42, .06);
      --shadow-2:    0 18px 50px rgba(37, 99, 235, .10);

      /* Layout */
      --container-max: none;
      --container-px: 30px;

      --p360-rail: linear-gradient(90deg, rgba(37,99,235,.18) 0%, rgba(96,165,250,.10) 50%, rgba(37,99,235,.03) 100%);
      --p360-rail-h: 1px;

      --sb-w: 264px;
      --sb-wc: 72px;

      --footer-h: 42px;
      --footer-offset: 8px;

      --glass-blur: 18px;
      --radius-xl: 24px;
      --radius-lg: 18px;
      --radius-md: 14px;
    }

     html[data-theme="dark"]{
      --bg:          #0B1220;
      --bg-soft:     #0F172A;
      --card:        rgba(15, 23, 42, .82);
      --card-solid:  #111827;
      --ink:         #E5E7EB;
      --ink-2:       #CBD5E1;
      --muted:       #94A3B8;
      --bd:          rgba(255, 255, 255, .08);
      --bd-strong:   rgba(96, 165, 250, .22);
      --shadow-1:    0 16px 40px rgba(0, 0, 0, .24);
      --shadow-2:    0 22px 60px rgba(0, 0, 0, .30);
      --p360-rail: linear-gradient(90deg, rgba(96,165,250,.16) 0%, rgba(59,130,246,.10) 50%, rgba(96,165,250,.03) 100%);
    }

    html, body{
      height:100%;
      font-family:'Poppins', ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
      text-rendering: optimizeLegibility;
      color:var(--ink);
      background:
      linear-gradient(180deg, var(--bg-soft) 0%, var(--bg) 100%);
    }

    body{
      margin:0;
      overflow:hidden;
    }

    a, button, input, select, textarea{
      font-family:inherit;
    }

    /* ===== Header rail ===== */
    #p360-client{
      position:relative;
      backdrop-filter: blur(var(--glass-blur));
      -webkit-backdrop-filter: blur(var(--glass-blur));
    }

    #p360-client::after{
      content:"";
      position:absolute;
      left:0;
      right:0;
      bottom:0;
      height:var(--p360-rail-h);
      background:var(--p360-rail);
      pointer-events:none;
    }

    header.topbar{
      border-bottom:1px solid var(--bd) !important;
      background: rgba(255,255,255,.92) !important;
      backdrop-filter: blur(var(--glass-blur));
      -webkit-backdrop-filter: blur(var(--glass-blur));
      box-shadow: 0 8px 24px rgba(15,23,42,.04);
    }

    html[data-theme="dark"] header.topbar{
      background: rgba(15,23,42,.88) !important;
      box-shadow: 0 10px 30px rgba(0,0,0,.18);
    }

    .shell{
      display:block;
    }

    /* ===================================================================
       DESKTOP >= 1100px
       =================================================================== */
    @media (min-width:1100px){
      .shell > .sidebar{
        position:fixed !important;
        left:0 !important;
        top:calc(var(--header-h,72px) - var(--p360-rail-h,1px)) !important;
        height:calc(100dvh - (var(--header-h,72px) - var(--p360-rail-h,1px))) !important;
        margin:0 !important;
        z-index:40 !important;
        background: color-mix(in srgb, var(--card-solid) 74%, transparent) !important;
        backdrop-filter: blur(var(--glass-blur));
        -webkit-backdrop-filter: blur(var(--glass-blur));
        border-right:1px solid var(--bd) !important;
        box-shadow: 10px 0 30px rgba(15,23,42,.04);
      }

      html[data-theme="dark"] .shell > .sidebar{
        box-shadow: 10px 0 30px rgba(0,0,0,.16);
      }

      .shell > .sidebar[data-state="expanded"]{
        width:var(--sb-w) !important;
        min-width:var(--sb-w) !important;
      }

      .shell > .sidebar[data-state="collapsed"]{
        width:var(--sb-wc) !important;
        min-width:var(--sb-wc) !important;
      }

      main.content{
        position:fixed !important;
        top:var(--header-h,72px) !important;
        right:0 !important;
        bottom:calc(var(--footer-h) + var(--footer-offset)) !important;
        overflow:auto !important;
        -webkit-overflow-scrolling:touch !important;
        background:#ffffff !important;
      }

      html[data-theme="dark"] main.content{
        background: linear-gradient(180deg, var(--bg-soft) 0%, var(--bg) 100%) !important;
      }

      .shell > .sidebar[data-state="expanded"] ~ main.content{
        left:var(--sb-w) !important;
      }

      .shell > .sidebar[data-state="collapsed"] ~ main.content{
        left:var(--sb-wc) !important;
      }

      .shell > .sidebar[data-state="expanded"] ~ main.content ~ .client-footer{
        left:var(--sb-w) !important;
      }

      .shell > .sidebar[data-state="collapsed"] ~ main.content ~ .client-footer{
        left:var(--sb-wc) !important;
      }
    }

    /* ===================================================================
       MOBILE/TABLET < 1100px
       =================================================================== */
    @media (max-width:1099.98px){
      main.content{
        position:fixed;
        top:var(--header-h,72px);
        left:0;
        right:0;
        bottom:calc(var(--footer-h) + var(--footer-offset));
        overflow:auto;
        -webkit-overflow-scrolling:touch;
        background:#ffffff;
      }

      html[data-theme="dark"] main.content{
        background: linear-gradient(180deg, var(--bg-soft) 0%, var(--bg) 100%);
      }
    }

    /* ============================================================
       CONTENEDOR
       ============================================================ */
    main.content .container{
      padding:var(--container-px);
      max-width:var(--container-max);
      margin:0;
      width:100%;
      min-height:100%;
      box-sizing:border-box;
    }

    main.content .container.is-contained{
      max-width:1440px;
      margin:0 auto;
    }

    /* ===== Base visual global ===== */
    .card{
      background:var(--card) !important;
      border:1px solid var(--bd) !important;
      box-shadow:var(--shadow-1);
      border-radius:var(--radius-xl) !important;
      backdrop-filter: blur(var(--glass-blur));
      -webkit-backdrop-filter: blur(var(--glass-blur));
      overflow:hidden;
    }

    .card:hover{
      box-shadow:var(--shadow-2);
      transition: box-shadow .22s ease, border-color .22s ease, transform .22s ease;
    }

    .muted,
    .text-muted{
      color:var(--muted) !important;
    }

    .badge,
    .pill,
    .tag{
      border-radius:999px;
    }

    /* ===== Inputs genéricos ===== */
    input:not([type="checkbox"]):not([type="radio"]),
    select,
    textarea{
      border-radius:14px;
      border:1px solid var(--bd);
      background: color-mix(in srgb, var(--card-solid) 88%, transparent);
      color:var(--ink);
      transition:border-color .18s ease, box-shadow .18s ease, background .18s ease;
    }

    input:focus,
    select:focus,
    textarea:focus{
      outline:none;
      border-color:var(--bd-strong);
      box-shadow:0 0 0 4px rgba(59,130,246,.10);
    }

    /* ===== Buttons genéricos ===== */
    .btn-primary,
    .button-primary,
    button[type="submit"].primary{
      background:linear-gradient(135deg, var(--brand) 0%, var(--accent) 100%);
      color:#fff;
      border:none;
      box-shadow:0 10px 24px rgba(37,99,235,.22);
    }

    /* ===== Scroll ===== */
    *{
      scrollbar-width:thin;
      scrollbar-color: rgba(100,116,139,.45) transparent;
    }

    *::-webkit-scrollbar{
      width:10px;
      height:10px;
    }

    *::-webkit-scrollbar-track{
      background:transparent;
    }

    *::-webkit-scrollbar-thumb{
      background:rgba(100,116,139,.30);
      border-radius:999px;
      border:2px solid transparent;
      background-clip:padding-box;
    }

    *::-webkit-scrollbar-thumb:hover{
      background:rgba(100,116,139,.45);
      background-clip:padding-box;
    }

    /* ===== Footer ===== */
    .client-footer{
      height:var(--footer-h);
      font-size:12px;
      padding-inline:0 !important;
      border-top:none;
      position:fixed;
      bottom:var(--footer-offset);
      left:0;
      right:0;
      z-index:60;
      background: rgba(255,255,255,.96);
      backdrop-filter: blur(var(--glass-blur));
      -webkit-backdrop-filter: blur(var(--glass-blur));
      display:flex;
      align-items:center;
      justify-content:center;
      line-height:1.2;
      box-shadow:0 -8px 22px rgba(15,23,42,.04);
    }

    html[data-theme="dark"] .client-footer{
      background: rgba(15,23,42,.90);
      box-shadow:0 -8px 22px rgba(0,0,0,.12);
    }
    html[data-theme="dark"] .client-footer{
      box-shadow:0 -8px 22px rgba(0,0,0,.12);
    }

    .client-footer::before{
      content:"";
      position:absolute;
      top:0;
      left:0;
      right:0;
      height:var(--p360-rail-h);
      background:var(--p360-rail);
      pointer-events:none;
    }

    .client-footer > .container{
      max-width:1440px;
      margin-inline:auto;
      padding-inline:var(--container-px);
      box-sizing:border-box;
      display:flex;
      align-items:center;
      justify-content:center;
      height:100%;
      padding-top:0;
      padding-bottom:0;
    }

    /* ===== Utilidades visuales ===== */
    .p360-surface{
      background:var(--card);
      border:1px solid var(--bd);
      border-radius:var(--radius-lg);
      box-shadow:var(--shadow-1);
      backdrop-filter: blur(var(--glass-blur));
      -webkit-backdrop-filter: blur(var(--glass-blur));
    }

    .p360-title-gradient{
      background:linear-gradient(90deg, var(--brand) 0%, var(--accent) 100%);
      -webkit-background-clip:text;
      background-clip:text;
      color:transparent;
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

  @include('layouts.partials.client_header', [
      'renderDemoToggle' => true,
      'plan'             => $plan,
      'billingCycle'     => $billingCycle,
      'cuenta'           => $cuenta,
      'summary'          => $summary ?? null,
  ])

  <div class="shell">
    @include('components.client.sidebar', ['id' => 'sidebar', 'isOpen' => true])

    <main id="clientMain" class="content @yield('pageClass')" role="main">
      <div class="container" id="shotArea">
        @yield('content')
      </div>
    </main>

    @includeIf('layouts.partials.client_footer')
  </div>

  <script src="{{ $demoJs }}" defer></script>

  <script>
    function __p360SyncHeaderVars(){
      const el =
        document.getElementById('p360-client') ||
        document.querySelector('header.topbar') ||
        document.querySelector('[data-topbar]');
      const h  = el ? Math.round(el.getBoundingClientRect().height) : 72;
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
        const mime = (type === 'jpg' || type === 'jpeg') ? 'image/jpeg' : 'image/png';
        const url  = canvas.toDataURL(mime, 0.92);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'p360_captura.' + (type === 'jpg' ? 'jpg' : 'png');
        a.click();
      }

      async function capture(el, type){
        if (window.html2canvas && el){
          try{
            const canvas = await window.html2canvas(el, {
              useCORS:true,
              logging:false,
              backgroundColor:null,
              scale: window.devicePixelRatio || 1
            });
            return downloadFromCanvas(canvas, type);
          }catch(e){}
        }

        try{
          window.dispatchEvent(new CustomEvent('p360:capture', { detail:{ element: el, type } }));
        }catch(_){}

        try{
          window.P360?.toast && P360.toast('Preparando captura…');
        }catch(_){}
      }

      document.addEventListener('click', (e)=>{
        const b = e.target.closest('[data-shot]');
        if(!b) return;

        e.preventDefault();

        const type = (b.getAttribute('data-shot') || 'jpg').toLowerCase();
        const sel  = b.getAttribute('data-shot-target') || '#clientMain';
        const el   = document.querySelector(sel);

        if(!el){
          try{
            window.P360?.toast?.error && P360.toast.error('No se encontró el área a capturar');
          }catch(_){}
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