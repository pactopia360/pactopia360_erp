{{-- resources/views/layouts/client.blade.php (safe JS, sin Blade dentro de <script>) --}}
@php
  use Illuminate\Support\Facades\Route;

  $pageTitle      = trim($__env->yieldContent('title')) ?: 'Portal de Clientes · Pactopia360';
  $theme          = session('client_ui.theme', 'dark'); // 'light' | 'dark'
  $htmlThemeClass = $theme === 'dark' ? 'theme-dark' : 'theme-light';

  $routeThemeSwitch = Route::has('cliente.ui.theme.switch') ? route('cliente.ui.theme.switch') : '';

  $logoLightUrl = asset('assets/client/P360 BLACK.png');
  $logoDarkUrl  = asset('assets/client/P360 WHITE.png');
  $brandSrc     = $theme === 'dark' ? $logoDarkUrl : $logoLightUrl;

  $u    = auth('web')->user();
  $c    = $u?->cuenta;
  $plan = strtoupper((string) ($c->plan_actual ?? 'FREE'));

  // Bags/contadores opcionales
  $errBag = $errors ?? session('errors');
  $firstError = '';
  if ($errBag instanceof \Illuminate\Support\ViewErrorBag) {
    $firstError = (string) $errBag->first();
  }

  // Rutas “mejor esfuerzo”: si no existen, caen a '#'
  $rtSearchTo = Route::has('cliente.facturacion.index') ? route('cliente.facturacion.index') : '#';
  $rtAlerts   = Route::has('cliente.alertas') ? route('cliente.alertas') : (Route::has('cliente.notificaciones') ? route('cliente.notificaciones') : '#');
  $rtChat     = Route::has('cliente.soporte.chat') ? route('cliente.soporte.chat') : (Route::has('cliente.chat') ? route('cliente.chat') : '#');
  $rtCart     = Route::has('cliente.marketplace') ? route('cliente.marketplace') : (Route::has('cliente.tienda') ? route('cliente.tienda') : '#');

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

  <link rel="stylesheet" href="{{ asset('assets/client/css/core-ui.css') }}">
  @stack('styles')
</head>
<body
  id="p360-client"
  data-theme-switch="{{ $routeThemeSwitch }}"
  data-logo-light="{{ $logoLightUrl }}"
  data-logo-dark="{{ $logoDarkUrl }}"
  data-icon-sprite="{{ asset('assets/client/icons.svg') }}"
>
  {{-- ===================== TOPBAR ===================== --}}
  <header class="topbar" role="banner" style="grid-template-columns:auto 1fr auto; gap:16px;">
    {{-- Menú + Logo --}}
    <div class="brand" style="display:flex; align-items:center; gap:12px;">
      <button class="hamburger" id="btnSidebar" aria-label="Abrir menú">
        <svg class="ico" width="20" height="20" viewBox="0 0 24 24">
          <use href="{{ asset('assets/client/icons.svg') }}#menu"/>
        </svg>
      </button>
      <a href="{{ route('cliente.home') }}" aria-label="Inicio" style="display:flex;align-items:center;gap:6px;">
        <img id="brandLogo" class="brand-logo" src="{{ $brandSrc }}" alt="Pactopia360"
             data-src-light="{{ $logoLightUrl }}" data-src-dark="{{ $logoDarkUrl }}">
      </a>
    </div>

    {{-- Buscador central --}}
    <form class="searchbar" role="search" action="{{ $rtSearchTo }}" method="GET" style="margin-left:12px; max-width:520px;">
      <svg class="ico" width="18" height="18" viewBox="0 0 24 24">
        <use href="{{ asset('assets/client/icons.svg') }}#search"/>
      </svg>
      <input name="q" placeholder="Buscar en tu cuenta (CFDI, receptor, folio…)" />
    </form>

    {{-- Acciones derecha --}}
    <div style="display:flex; align-items:center; gap:14px;">
      {{-- Tema --}}
      <button id="btnTheme" class="btn icon" type="button" title="Cambiar tema"
              aria-pressed="{{ $theme === 'dark' ? 'true' : 'false' }}">
        @if ($theme === 'dark')
          <svg class="ico" width="20" height="20" viewBox="0 0 24 24"><use href="{{ asset('assets/client/icons.svg') }}#moon"/></svg>
        @else
          <svg class="ico" width="20" height="20" viewBox="0 0 24 24"><use href="{{ asset('assets/client/icons.svg') }}#sun"/></svg>
        @endif
      </button>

      {{-- Notificaciones --}}
      <a class="btn icon" href="{{ $rtAlerts }}" title="Notificaciones" aria-label="Notificaciones">
        <svg class="ico" width="20" height="20" viewBox="0 0 24 24"><use href="{{ asset('assets/client/icons.svg') }}#bell"/></svg>
        @if($notifCount > 0)<span class="badge">{{ $notifCount }}</span>@endif
      </a>

      {{-- Chat soporte --}}
      <a class="btn icon" href="{{ $rtChat }}" title="Mensajes con soporte" aria-label="Mensajes con soporte">
        <svg class="ico" width="20" height="20" viewBox="0 0 24 24"><use href="{{ asset('assets/client/icons.svg') }}#chat"/></svg>
        @if($chatCount > 0)<span class="badge">{{ $chatCount }}</span>@endif
      </a>

      {{-- Carrito Marketplace --}}
      <a class="btn icon" href="{{ $rtCart }}" title="Marketplace" aria-label="Carrito de compras">
        <svg class="ico" width="20" height="20" viewBox="0 0 24 24"><use href="{{ asset('assets/client/icons.svg') }}#cart"/></svg>
        @if($cartCount > 0)<span class="badge">{{ $cartCount }}</span>@endif
      </a>

      {{-- Datos de cuenta --}}
      <div style="display:flex; flex-direction:column; align-items:flex-end; line-height:1.1; min-width:160px;">
        <span style="font-weight:800;">{{ $u?->razon_social ?? $u?->nombre ?? $u?->email ?? 'Cuenta cliente' }}</span>
        <span style="font-size:12px; color:var(--muted); font-weight:700;">{{ $plan === 'PRO' ? 'PRO' : 'FREE' }}</span>
      </div>

      {{-- Perfil --}}
      <a href="{{ url('cliente/perfil') }}" class="btn icon" title="Perfil" aria-label="Perfil">
        <span class="avatar" title="Cuenta" style="background:var(--brand);color:#fff;">
          {{ strtoupper(substr(($u?->nombre ?? $u?->email ?? '?'),0,1)) }}
        </span>
      </a>
    </div>
  </header>

  {{-- Overlay de búsqueda para móvil --}}
  <div id="searchPanel" class="search-panel" aria-hidden="true">
    <div class="inner">
      <form class="searchbar" role="search" action="{{ Route::has('cliente.facturacion.index') ? route('cliente.facturacion.index') : '#' }}">
        <svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M10 2a8 8 0 105.293 14.293l4.707 4.707l-1.414 1.414l-4.707-4.707A8 8 0 0010 2m0 2a6 6 0 110 12a6 6 0 010-12"/></svg>
        <input name="q" placeholder="Buscar (CFDI, receptor, folio…)" autofocus />
      </form>
      <button id="btnCloseSearch" class="close" type="button">Cerrar</button>
    </div>
  </div>

  {{-- ===================== SHELL ===================== --}}
  <div class="shell">
    {{-- Sidebar como componente --}}
    <x-client.sidebar :is-open="false" />

    <main class="content" role="main">
      <div class="container">
        @if(session('ok'))
          <div class="alert ok" role="status">{{ session('ok') }}</div>
        @endif

        {{-- Primer error de validación (sin referir $errors en el script compilado) --}}
        @if(!empty($firstError))
          <div class="alert err" role="alert" aria-live="assertive">{{ $firstError }}</div>
        @endif

        @yield('content')
      </div>

      @includeIf('layouts.partials.client_footer')
    </main>
  </div>

  <script>
    (function(){
      const html     = document.documentElement;
      const root     = document.getElementById('p360-client');
      const sidebar  = document.getElementById('sidebar');
      const btnSide  = document.getElementById('btnSidebar');
      const btnTheme = document.getElementById('btnTheme');
      const logo     = document.getElementById('brandLogo');

      const route    = root?.getAttribute('data-theme-switch') || '';
      const sprite   = root?.getAttribute('data-icon-sprite') || '';
      const lightSrc = root?.getAttribute('data-logo-light') || '';
      const darkSrc  = root?.getAttribute('data-logo-dark')  || '';
      const csrf     = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

      // Sidebar
      btnSide?.addEventListener('click', () => {
        if (!sidebar) return;
        sidebar.classList.toggle('open');
        sidebar.setAttribute('data-state', sidebar.classList.contains('open') ? 'expanded' : 'collapsed');
      });
      sidebar?.addEventListener('click', (e) => {
        if (window.matchMedia('(max-width:1120px)').matches && e.target.closest('a')) sidebar.classList.remove('open');
      });

      // Tema + logo
      function setLogoForTheme(next){
        if (logo && lightSrc && darkSrc) logo.src = (next === 'dark') ? darkSrc : lightSrc;
      }
      function svgUse(id){ return `<svg class="ico" width="20" height="20" viewBox="0 0 24 24"><use href="${sprite}#${id}"/></svg>`; }
      function setTheme(next){
        html.setAttribute('data-theme', next);
        if (btnTheme){
          btnTheme.setAttribute('aria-pressed', next === 'dark' ? 'true' : 'false');
          btnTheme.innerHTML = next === 'dark' ? svgUse('moon') : svgUse('sun');
        }
        setLogoForTheme(next);
      }
      setLogoForTheme(html.getAttribute('data-theme') || 'dark');

      btnTheme?.addEventListener('click', async () => {
        const current = html.getAttribute('data-theme') || 'dark';
        const next = current === 'dark' ? 'light' : 'dark';
        setTheme(next);

        if(route){
          try{
            await fetch(route, {
              method: 'POST',
              headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': csrf },
              body: JSON.stringify({ theme: next })
            });
          }catch(e){ try { localStorage.setItem('p360_client_theme', next); } catch(_){} }
        }else{
          try { localStorage.setItem('p360_client_theme', next); } catch(_){}
        }
      });

      if(!route){
        try{
          const saved = localStorage.getItem('p360_client_theme');
          if(saved && (saved === 'light' || saved === 'dark')) setTheme(saved);
        }catch(_){}
      }

      // Overlay búsqueda móvil
      const panel   = document.getElementById('searchPanel');
      const openBtn = document.getElementById('btnOpenSearch');
      const closeBtn= document.getElementById('btnCloseSearch');
      function openSearch(){ if(!panel) return; panel.style.display='block'; panel.setAttribute('aria-hidden','false'); setTimeout(()=>panel.querySelector('input')?.focus(),20); }
      function closeSearch(){ if(!panel) return; panel.style.display='none'; panel.setAttribute('aria-hidden','true'); }
      openBtn?.addEventListener('click', openSearch);
      closeBtn?.addEventListener('click', closeSearch);
      panel?.addEventListener('click', (e)=>{ if(e.target === panel) closeSearch(); });
      window.addEventListener('keydown', (e)=>{ if(e.key==='Escape') closeSearch(); });
    })();
  </script>

  @stack('scripts')
  @yield('scripts')
</body>
</html>
