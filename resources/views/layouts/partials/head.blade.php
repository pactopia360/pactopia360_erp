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
<meta name="robots" content="noindex, nofollow" />
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

<meta name="theme-color" content="#0b1220">
<meta name="mobile-web-app-capable" content="yes">      <!-- ← nuevo -->
<meta name="apple-mobile-web-app-capable" content="yes"> <!-- ← iOS -->

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
      var th = localStorage.getItem('p360-theme');
      if (!th) th = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';

      // Soporta clave nueva y legacy para colapso del sidebar
      var sbNew = localStorage.getItem('p360.sidebar.collapsed') === '1';
      var sbOld = localStorage.getItem('p360-sidebar') === '1'; // compat
      var sb = sbNew || sbOld;

      var el = document.documentElement, body=document.body;
      el.classList.toggle('theme-dark', th === 'dark');
      el.classList.toggle('theme-light', th !== 'dark');
      el.setAttribute('data-theme', th);
      body.classList.toggle('theme-dark', th === 'dark');
      body.classList.toggle('theme-light', th !== 'dark');
      if (sb) {
        el.classList.add('sidebar-collapsed');
        body.classList.add('sidebar-collapsed');
      }
    } catch(e) {}
  })();
</script>

{{-- Anti-FOUC para logo por tema --}}
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

{{-- Favicon / iOS icons --}}
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('assets/admin/img/favicon.png') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('assets/admin/img/apple-touch-icon.png') }}">
