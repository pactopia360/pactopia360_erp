{{-- resources/views/layouts/cliente-auth.blade.php --}}
<!doctype html>
<html lang="{{ str_replace('_','-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <title>@yield('title', config('app.name'))</title>

  {{-- Tipografía (si ya la cargas global, puedes quitar esto) --}}
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

  @stack('styles')

  <style>
    :root{
      --vf-font: "Poppins", system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      --vf-ink:#0f172a;
      --vf-muted:#64748b;
      --vf-border:rgba(15,23,42,.10);
      --vf-card:#ffffff;
      --vf-shadow: 0 26px 70px rgba(2,6,23,.14);
      --vf-red:#e11d48;
      --vf-red2:#be123c;
      --vf-green:#16a34a;
    }
    html,body{height:100%}
    body{
      margin:0;
      font-family:var(--vf-font);
      color:var(--vf-ink);
      background:#f6f7fb;
    }
  </style>
</head>

<body>
  @yield('content')
  @stack('scripts')
</body>
</html>
