{{-- resources/views/layouts/client.blade.php --}}
@php
  use Illuminate\Support\Facades\Route;

  $pageTitle      = trim($__env->yieldContent('title')) ?: 'Portal de Clientes · Pactopia360';
  $theme          = session('client_ui.theme', 'light'); // light|dark
  $htmlThemeClass = $theme === 'dark' ? 'theme-dark' : 'theme-light';

  // SAME-ORIGIN assets
  $logoLightUrl = '/assets/client/P360 BLACK.png';
  $logoDarkUrl  = '/assets/client/P360 WHITE.png';
  $brandSrc     = $theme === 'dark' ? $logoDarkUrl : $logoLightUrl;
  $spriteUrl    = '/assets/client/icons.svg';

  // Rutas base
  $rtHome     = Route::has('cliente.home') ? route('cliente.home') : url('/cliente');
  $rtSearchTo = Route::has('cliente.facturacion.index') ? route('cliente.facturacion.index') : '#';
  $rtAlerts   = Route::has('cliente.alertas') ? route('cliente.alertas') : '#';
  $rtChat     = Route::has('cliente.soporte.chat') ? route('cliente.soporte.chat') : '#';
  $rtCart     = Route::has('cliente.marketplace') ? route('cliente.marketplace') : '#';
  $rtLogout   = Route::has('cliente.logout') ? route('cliente.logout') : url('/cliente/logout');
  $rtPerfil   = Route::has('cliente.perfil') ? route('cliente.perfil') : url('cliente/perfil');
  $rtTheme    = Route::has('cliente.ui.theme.switch') ? route('cliente.ui.theme.switch') : '';

  $u    = auth('web')->user();
  $c    = $u?->cuenta;
  $plan = strtoupper((string) ($c->plan_actual ?? 'FREE'));

  $notifCount = (int)($notifCount ?? 0);
  $chatCount  = (int)($chatCount  ?? 0);
  $cartCount  = (int)($cartCount  ?? 0);
@endphp
<!DOCTYPE html>
<html lang="es" class="{{ $htmlThemeClass }}" data-theme="{{ $theme }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="color-scheme" content="light dark">
  <title>{{ $pageTitle }}</title>

  {{-- CSS same-origin global (si existe) --}}
  <link rel="stylesheet" href="/assets/client/css/core-ui.css">

  @vite(['resources/css/app.css', 'resources/js/app.js'])

  <style>
    :root{
      --brand:#E11D48; --brand-600:#BE123C; --brand-ring: 0 0 0 3px color-mix(in oklab, var(--brand) 25%, transparent);
      --ink:#0f172a; --muted: color-mix(in oklab, var(--ink) 60%, transparent);
      --card:#fff; --bd:#e5e7eb; --sb-w:260px; --header-h:64px; --max-w:1220px;
      --bg-grad: radial-gradient(900px 700px at 100% 10%, rgba(225,29,72,.05), transparent),
                 radial-gradient(900px 700px at 0% 90%, rgba(14,165,233,.05), transparent);
    }
    html.theme-dark{
      --ink:#e5e7eb; --muted: color-mix(in oklab, #fff 55%, transparent);
      --card: color-mix(in oklab, #0b1220 86%, transparent);
      --bd: rgba(255,255,255,.12);
      --bg-grad: radial-gradient(900px 700px at 100% 10%, rgba(225,29,72,.12), transparent),
                 radial-gradient(900px 700px at 0% 90%, rgba(14,165,233,.12), transparent);
    }

    /* ===== Shell ===== */
    body{margin:0; color:var(--ink); background:var(--bg-grad), color-mix(in oklab, var(--card) 92%, transparent);}
    #p360-shell{
      min-height: 100dvh;
      display: grid;
      grid-template-rows: var(--header-h) 1fr auto;
      grid-template-columns: 1fr;
    }

    /* ===== Header (topbar) ===== */
    .topbar{
      position: sticky; top:0; z-index:50; height:var(--header-h);
      backdrop-filter: saturate(140%) blur(6px);
      background: color-mix(in oklab, var(--card) 92%, transparent);
      border-bottom:1px solid var(--bd);
      display:grid; grid-template-columns: auto 1fr auto; align-items:center; gap:14px; padding:0 14px;
    }
    .topbar .brand{display:flex; align-items:center; gap:10px}
    .topbar .brand-logo{height:24px}
    .topbar .hamburger{border:1px solid var(--bd); width:36px; height:36px; border-radius:10px; background:var(--card); cursor:pointer}
    .topbar .searchbar{display:flex; align-items:center; gap:8px; border:1px solid var(--bd); background:var(--card); height:38px; padding:0 10px; border-radius:12px; max-width:560px; width:100%}
    .topbar .searchbar input{border:0; outline:0; width:100%; background:transparent; color:inherit}
    .topbar .btn.icon{border:1px solid var(--bd); background:var(--card); width:36px; height:36px; border-radius:10px; display:inline-grid; place-items:center; cursor:pointer}
    .topbar .badge{position:absolute; translate:12px -6px; font:700 10px/1 system-ui; background:var(--brand); color:#fff; border-radius:10px; padding:2px 6px}

    /* ===== Layout principal: sidebar + contenido ===== */
    .shell{
      display:grid;
      grid-template-columns: var(--sb-w) 1fr;
      gap:0;
      align-items: stretch;
    }
    @media (max-width:1120px){
      :root{ --sb-w: 0px; }
      .shell{ grid-template-columns: 1fr; }
    }

    .content{
      min-height: calc(100dvh - var(--header-h));
      padding: 18px 18px 90px;
    }
    .container{ max-width: var(--max-w); margin:0 auto; }
    .alert{ padding:10px 12px; border:1px solid var(--bd); border-radius:10px; background:var(--card); margin-bottom:12px }
    .alert.ok{ border-color: color-mix(in oklab, #10b981 40%, var(--bd)) }
    .alert.err{ border-color: color-mix(in oklab, #ef4444 40%, var(--bd)) }

    /* Footer a lo ancho (fuera del scroll del main) */
    .footer-wrap{
      border-top:1px solid var(--bd);
      backdrop-filter: saturate(140%) blur(6px);
      background: color-mix(in oklab, var(--card) 92%, transparent);
    }
  </style>

  @stack('styles')
</head>
<body id="p360-client" data-logo-light="{{ $logoLightUrl }}" data-logo-dark="{{ $logoDarkUrl }}" data-icon-sprite="{{ $spriteUrl }}">
<div id="p360-shell">
  {{-- ===================== HEADER ===================== --}}
  <header class="topbar" role="banner">
    <div class="brand">
      <button class="hamburger" id="btnSidebar" aria-label="Abrir menú">
        <svg class="ico" viewBox="0 0 24 24" width="20" height="20"><use href="{{ $spriteUrl }}#menu"/></svg>
      </button>
      <a href="{{ $rtHome }}" aria-label="Inicio">
        <img id="brandLogo" class="brand-logo" src="{{ $brandSrc }}" alt="Pactopia360">
      </a>
    </div>

    <form class="searchbar" role="search" action="{{ $rtSearchTo }}" method="GET">
      <svg class="ico" viewBox="0 0 24 24" width="18" height="18"><use href="{{ $spriteUrl }}#search"/></svg>
      <input name="q" placeholder="Buscar en tu cuenta (CFDI, receptor, folio…)" />
    </form>

    <div style="display:flex; align-items:center; gap:10px;">
      <button id="btnTheme" class="btn icon" type="button" title="Cambiar tema" aria-pressed="{{ $theme === 'dark' ? 'true' : 'false' }}">
        @if ($theme === 'dark')
          <svg class="ico" viewBox="0 0 24 24" width="20" height="20"><use href="{{ $spriteUrl }}#moon"/></svg>
        @else
          <svg class="ico" viewBox="0 0 24 24" width="20" height="20"><use href="{{ $spriteUrl }}#sun"/></svg>
        @endif
      </button>

      <a class="btn icon" href="{{ $rtAlerts }}" title="Notificaciones" aria-label="Notificaciones" style="position:relative">
        <svg class="ico" viewBox="0 0 24 24" width="20" height="20"><use href="{{ $spriteUrl }}#bell"/></svg>
        @if($notifCount>0)<span class="badge">{{ $notifCount }}</span>@endif
      </a>

      <a class="btn icon" href="{{ $rtChat }}" title="Mensajes" aria-label="Mensajes" style="position:relative">
        <svg class="ico" viewBox="0 0 24 24" width="20" height="20"><use href="{{ $spriteUrl }}#chat"/></svg>
        @if($chatCount>0)<span class="badge">{{ $chatCount }}</span>@endif
      </a>

      <a class="btn icon" href="{{ $rtCart }}" title="Marketplace" aria-label="Marketplace" style="position:relative">
        <svg class="ico" viewBox="0 0 24 24" width="20" height="20"><use href="{{ $spriteUrl }}#cart"/></svg>
        @if($cartCount>0)<span class="badge">{{ $cartCount }}</span>@endif
      </a>

      <div style="display:flex; flex-direction:column; align-items:flex-end; line-height:1.1; min-width:160px;">
        <span style="font-weight:800;">{{ $u?->razon_social ?? $u?->nombre ?? $u?->email ?? 'Cuenta cliente' }}</span>
        <span style="font-size:12px; color:var(--muted); font-weight:700;">{{ $plan }}</span>
      </div>

      <a href="{{ $rtPerfil }}" class="btn icon" title="Perfil" aria-label="Perfil">
        <span class="avatar" style="background:var(--brand);color:#fff; width:28px;height:28px; border-radius:999px; display:grid; place-items:center; font:900 12px/1 system-ui;">
          {{ strtoupper(substr(($u?->nombre ?? $u?->email ?? '?'),0,1)) }}
        </span>
      </a>

      <form action="{{ $rtLogout }}" method="POST" style="margin:0">
        @csrf
        <button type="submit" class="btn icon" title="Cerrar sesión" aria-label="Cerrar sesión">
          <svg class="ico" viewBox="0 0 24 24" width="20" height="20"><use href="{{ $spriteUrl }}#logout"/></svg>
        </button>
      </form>
    </div>
  </header>

  {{-- ===================== SIDEBAR + CONTENT ===================== --}}
  <div class="shell">
    @php
      $sidebarOk = true;
      try { echo view()->make('components.client.sidebar', ['isOpen'=>false])->render(); }
      catch (\Throwable $e) { $sidebarOk = false; }
    @endphp
    @if(!$sidebarOk)
      <aside id="sidebar" class="sidebar" data-state="collapsed"></aside>
    @endif

    <main id="clientMain" class="content" role="main">
      <div class="container">
        @if(session('ok')) <div class="alert ok" role="status">{{ session('ok') }}</div> @endif
        @if($errors?->any()) <div class="alert err" role="alert">{{ $errors->first() }}</div> @endif
        @yield('content')
      </div>
    </main>
  </div>

  {{-- ===================== FOOTER ===================== --}}
  <div class="footer-wrap">
    @includeIf('layouts.partials.client_footer')
  </div>
</div>

<script>
(function(){
  const html = document.documentElement;
  const btnTheme = document.getElementById('btnTheme');
  const logo = document.getElementById('brandLogo');
  const lightSrc = document.getElementById('p360-client')?.getAttribute('data-logo-light') || '';
  const darkSrc  = document.getElementById('p360-client')?.getAttribute('data-logo-dark')  || '';
  const route    = @json($rtTheme);
  const csrf     = document.querySelector('meta[name="csrf-token"]')?.content || '';

  function setTheme(next){
    html.setAttribute('data-theme', next);
    if (btnTheme){
      btnTheme.setAttribute('aria-pressed', next === 'dark' ? 'true' : 'false');
      btnTheme.innerHTML = next === 'dark'
        ? '<svg class="ico" viewBox="0 0 24 24" width="20" height="20"><use href="{{ $spriteUrl }}#moon"/></svg>'
        : '<svg class="ico" viewBox="0 0 24 24" width="20" height="20"><use href="{{ $spriteUrl }}#sun"/></svg>';
    }
    if(logo && lightSrc && darkSrc){ logo.src = (next === 'dark') ? darkSrc : lightSrc; }
  }

  btnTheme?.addEventListener('click', async ()=>{
    const current = html.getAttribute('data-theme') || 'light';
    const next = current === 'dark' ? 'light' : 'dark';
    setTheme(next);

    if(route){
      try{
        await fetch(route, { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf}, body:JSON.stringify({theme:next}) });
      }catch(e){ try{ localStorage.setItem('p360_client_theme', next); }catch(_){} }
    }else{
      try{ localStorage.setItem('p360_client_theme', next); }catch(_){}
    }
  });

  if(!route){
    try{ const saved = localStorage.getItem('p360_client_theme'); if(saved==='light'||saved==='dark') setTheme(saved); }catch(_){}
  }

  // Sidebar toggle (móvil)
  const btnSide = document.getElementById('btnSidebar');
  const sidebar = document.getElementById('sidebar');
  btnSide?.addEventListener('click', ()=> sidebar?.classList.toggle('open'));
})();
</script>

@stack('scripts')
@yield('scripts')
</body>
</html>
