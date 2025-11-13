{{-- resources/views/layouts/partials/head.blade.php (v3) --}}
@php
  // Versión robusta para bustear caché de assets en /public
  if (!function_exists('assetv')) {
    /**
     * Retorna asset con ?v=mtime del archivo si existe en /public.
     * Si ya hay querystring, usa &v=.
     */
    function assetv(string $path): string {
      $url = asset($path);
      try {
        // Evita file_exists en URLs externas
        if (preg_match('~^https?://|^//~i', $path)) {
          return $url;
        }
        $abs = public_path($path);
        if (is_file($abs)) {
          $qs = str_contains($url, '?') ? '&v=' : '?v=';
          return $url . $qs . filemtime($abs);
        }
      } catch (\Throwable $e) {}
      return $url;
    }
  }
@endphp

<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />

{{-- IMPORTANTE: el <title> lo define el layout maestro (evitamos duplicados) --}}

{{-- Metas de seguridad y ambiente --}}
<meta name="csrf-token" content="{{ csrf_token() }}" />
@production
  <meta name="robots" content="index, follow" />
@else
  <meta name="robots" content="noindex, nofollow" />
@endproduction
<meta name="locale" content="{{ app()->getLocale() }}" />
<meta name="color-scheme" content="light dark" />
<meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#0e141c" media="(prefers-color-scheme: dark)">
<meta name="referrer" content="no-referrer-when-downgrade">

{{-- Metas de configuración P360 para scripts (evita Blade dentro de <script>) --}}
<meta name="p360-csrf"  content="{{ csrf_token() }}">
<meta name="p360-base"  content="{{ url('/') }}">
<meta name="p360-admin" content="{{ url('/admin') }}">
<meta name="p360-env"   content="{{ app()->environment() }}">

{{-- iOS/Android PWA hints (opcionales) --}}
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('assets/admin/img/apple-touch-icon.png') }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('assets/admin/img/favicon-32.png') }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('assets/admin/img/favicon-16.png') }}">
<link rel="manifest" href="{{ url('/site.webmanifest') }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">

{{-- =========================
   Meta Pixel (solo producción)
   ========================= --}}
@env('production')
  <!-- Meta Pixel Code -->
  <script>
    !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
    n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
    n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
    t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}
    (window, document,'script','https://connect.facebook.net/en_US/fbevents.js');
    fbq('init', '710622417467590');
    fbq('track', 'PageView');
  </script>
  <noscript>
    <img height="1" width="1" style="display:none"
         src="https://www.facebook.com/tr?id=710622417467590&ev=PageView&noscript=1" />
  </noscript>
  <!-- End Meta Pixel Code -->
@endenv

{{-- Preconnect / DNS Prefetch para CDN --}}
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<link rel="dns-prefetch" href="https://cdn.jsdelivr.net">

{{-- PRE-BOOT: aplica tema y estado del sidebar ANTES de pintar (evita flicker/doble logo) --}}
<script>
  (function () {
    try {
      // 1) Tema: prioriza clave cliente, luego legacy, luego preferencia del SO
      var th =
        localStorage.getItem('p360_client_theme') ||
        localStorage.getItem('p360-theme') ||
        (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');

      var html = document.documentElement, body = document.body;
      html.classList.remove('theme-dark','theme-light');
      body.classList.remove('theme-dark','theme-light');
      html.classList.add(th === 'dark' ? 'theme-dark' : 'theme-light');
      body.classList.add(th === 'dark' ? 'theme-dark' : 'theme-light');
      html.setAttribute('data-theme', th);

      // 2) Sidebar collapsed (lee varias claves por compatibilidad)
      // - Nueva: p360.client.sidebar.state = 'collapsed'|'expanded'
      // - Legacy booleanas: p360.sidebar.collapsed === '1'  ó  p360-sidebar === '1'
      var sbState = localStorage.getItem('p360.client.sidebar.state');
      var collapsed =
        (sbState === 'collapsed') ||
        localStorage.getItem('p360.sidebar.collapsed') === '1' ||
        localStorage.getItem('p360-sidebar') === '1';

      html.classList.toggle('sidebar-collapsed', !!collapsed);
      body.classList.toggle('sidebar-collapsed', !!collapsed);
    } catch(e) {}
  })();
</script>

{{-- Anti-FOUC para logo por tema (si tu header los usa con estas clases) --}}
<style>
  .brand-logo{display:none}
  html.theme-light .brand-light{display:inline}
  html.theme-dark  .brand-dark {display:inline}
</style>

{{-- Preload de logos (mejora LCP según tema) --}}
<link rel="preload" as="image" href="{{ asset('assets/admin/img/logo-pactopia360-dark.png') }}" media="(prefers-color-scheme: light)" fetchpriority="high">
<link rel="preload" as="image" href="{{ asset('assets/admin/img/logo-pactopia360-white.png') }}" media="(prefers-color-scheme: dark)"  fetchpriority="high">

{{-- CSS base del layout (header + sidebar) y estilos del panel --}}
<link rel="stylesheet" href="{{ assetv('assets/admin/css/layout.css') }}">
<link rel="stylesheet" href="{{ assetv('assets/admin/css/dashboard.css') }}">
<link rel="stylesheet" href="{{ assetv('assets/admin/css/novabot.css') }}">

{{-- Hook para preloads específicos por vista (ej. fuentes / imágenes críticas) --}}
@stack('preloads')

{{-- CSS por vista (ej. home.css) --}}
@stack('styles')

{{-- Favicon / iOS icons (fallbacks) --}}
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('assets/admin/img/favicon.png') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('assets/admin/img/apple-touch-icon.png') }}">
