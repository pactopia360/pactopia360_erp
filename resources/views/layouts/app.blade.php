{{-- resources/views/layouts/app.blade.php --}}
@php
  use Illuminate\Support\Facades\Route;
  $appName  = config('app.name', 'PACTOPIA 360');
  $hasBuild = file_exists(public_path('build/manifest.json'));
@endphp
<!DOCTYPE html>
<html lang="es" class="theme-light" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', $appName)</title>

  <script>
    (function(){
      try{
        var th = localStorage.getItem('p360-theme') || 'light';
        document.documentElement.classList.remove('theme-light','theme-dark');
        document.documentElement.classList.add('theme-'+th);
        document.documentElement.setAttribute('data-theme', th);
      }catch(_){}
    })();
  </script>

  @if ($hasBuild)
    @vite(['resources/css/app.css','resources/js/app.js'])
  @else
    <link rel="stylesheet" href="{{ asset('assets/admin/css/app.css') }}">
    <script defer src="{{ asset('assets/admin/js/app.js') }}"></script>
  @endif

  @stack('head')

  <style>
    body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;background:#f6f7fb;color:#0f172a}
    html.theme-dark body{background:#0b1220;color:#e5e7eb}
    .app-shell{display:grid;grid-template-columns:260px 1fr;min-height:100vh}
    @media (max-width: 991px){ .app-shell{grid-template-columns:1fr} }
    main{padding:14px}
    .container{max-width:1200px;margin:0 auto}
    .flash{padding:10px;border:1px solid #fecaca;background:#fef2f2;border-radius:10px}
    html.theme-dark .flash{background:rgba(127,29,29,.15);border-color:rgba(248,113,113,.3)}
  </style>
</head>
<body>
  @includeIf('layouts.partials.header')

  <div class="app-shell">
    @includeIf('layouts.partials.sidebar')

    <main role="main" id="main">
      <div class="container">
        @yield('content')
      </div>
    </main>
  </div>

  <div id="sidebarBackdrop" class="sidebar-backdrop" aria-hidden="true"></div>

  @stack('scripts')
</body>
</html>
