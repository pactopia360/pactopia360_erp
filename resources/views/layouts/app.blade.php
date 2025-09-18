{{-- resources/views/admin/layouts/app.blade.php --}}
<!doctype html>
<html lang="es" class="h-full">
<head>
  <meta charset="utf-8">
  <meta http-equiv="x-ua-compatible" content="ie=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title>@yield('title', 'Pactopia360 · Admin')</title>

  {{-- CSS mínimo para no depender de parciales ni compilaciones --}}
  <style>
    :root{
      --bg:#f6f7f9; --card:#fff; --text:#111827; --muted:#6b7280;
      --primary:#d72d08; --border:#e5e7eb; --ok:#16a34a; --warn:#f59e0b; --err:#ef4444;
    }
    html,body{height:100%}
    body{margin:0; font-family:ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,"Helvetica Neue",Arial,"Noto Sans","Apple Color Emoji","Segoe UI Emoji"; background:var(--bg); color:var(--text)}
    .wrap{max-width:1200px; margin:24px auto; padding:0 16px}
    .card{background:var(--card); border:1px solid var(--border); border-radius:12px; box-shadow:0 10px 20px rgba(0,0,0,.04)}
    .card-hd{padding:16px 20px; border-bottom:1px solid var(--border); font-weight:600}
    .card-bd{padding:20px}
    .alert{padding:12px 14px; border-radius:10px; margin:8px 0}
    .alert-ok{background:#ecfdf5; border:1px solid #a7f3d0}
    .alert-warn{background:#fffbeb; border:1px solid #fde68a}
    .alert-err{background:#fef2f2; border:1px solid #fecaca}
    table{width:100%; border-collapse:collapse}
    th,td{padding:10px 12px; border-bottom:1px solid var(--border); text-align:left}
    th{font-weight:600; color:#374151; background:#f9fafb}
    .muted{color:var(--muted)}
    a{color:var(--primary); text-decoration:none}
    a:hover{text-decoration:underline}
  </style>

  {{-- Hooks para assets compilados (si los tienes) --}}
  @stack('head')
  @yield('head')
</head>
<body class="h-full">
  <div class="wrap">
    <div class="card">
      <div class="card-hd">
        @yield('header', 'Panel de Administración')
      </div>
      <div class="card-bd">
        @if (session('status'))
          <div class="alert alert-ok">{{ session('status') }}</div>
        @endif

        @yield('content')
      </div>
    </div>
  </div>

  @stack('scripts')
  @yield('scripts')
</body>
</html>
