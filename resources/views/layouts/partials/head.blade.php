@php
  // Versión simple para bustear caché de assets en /public
  if (!function_exists('assetv')) {
    function assetv($path) {
      $abs = public_path($path);
      return asset($path) . (file_exists($abs) ? ('?v=' . filemtime($abs)) : '');
    }
  }
@endphp

<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>@yield('title', 'Admin')</title>

<meta name="csrf-token" content="{{ csrf_token() }}" />
<meta name="robots" content="noindex, nofollow" />
<meta name="locale" content="{{ app()->getLocale() }}" />
<meta name="color-scheme" content="light dark" />
<meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#0e141c" media="(prefers-color-scheme: dark)">

{{-- Preconnect para librerías externas usadas en el panel (Chart.js, etc.) --}}
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<link rel="dns-prefetch" href="https://cdn.jsdelivr.net">

{{-- CSS del panel (tema oscuro/claro) con versionado --}}
<link rel="stylesheet" href="{{ assetv('assets/admin/css/dashboard.css') }}">
<link rel="stylesheet" href="{{ assetv('assets/admin/css/novabot.css') }}">

{{-- Hook para css por vista (ej. home.css) --}}
@stack('styles')

{{-- Favicon (ajusta la ruta si ya tienes uno) --}}
<link rel="icon" type="image/png" href="{{ asset('assets/admin/img/favicon.png') }}">
