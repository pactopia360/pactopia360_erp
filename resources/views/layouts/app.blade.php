{{-- resources/views/layouts/app.blade.php --}}
<!doctype html>
<html lang="es" class="h-full" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta http-equiv="x-ua-compatible" content="ie=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title>@yield('title', 'Pactopia360')</title>

  <style>
    :root{
      --bg:#0b1220;
      --bg-light:#eef2f7;
      --card:rgba(255,255,255,0.08);
      --card-light:#ffffff;
      --text:#e5e7eb;
      --text-light:#111827;
      --muted:#9ca3af;
      --primary:#3b82f6;
      --gradient:linear-gradient(135deg,#1e3a8a,#2563eb);
      --border:rgba(255,255,255,0.08);
    }

    [data-theme="light"]{
      --bg:var(--bg-light);
      --card:var(--card-light);
      --text:var(--text-light);
      --border:#e5e7eb;
    }

    html,body{
      height:100%;
      margin:0;
      font-family:ui-sans-serif,system-ui;
      background:var(--bg);
      color:var(--text);
    }

    /* BACKGROUND GRADIENT */
    body::before{
      content:"";
      position:fixed;
      inset:0;
      background:radial-gradient(circle at 20% 20%, #1e3a8a, transparent 40%),
                 radial-gradient(circle at 80% 80%, #2563eb, transparent 40%);
      opacity:.4;
      z-index:-1;
    }

    .layout{
      display:flex;
      min-height:100vh;
    }

    /* SIDEBAR */
    .sidebar{
      width:240px;
      background:rgba(15,23,42,.9);
      backdrop-filter:blur(12px);
      border-right:1px solid var(--border);
      padding:20px;
    }

    .logo{
      font-weight:800;
      font-size:20px;
      letter-spacing:.5px;
      background:linear-gradient(90deg,#60a5fa,#3b82f6);
      -webkit-background-clip:text;
      -webkit-text-fill-color:transparent;
      margin-bottom:30px;
    }

    .menu a{
      display:block;
      padding:10px 12px;
      border-radius:10px;
      color:var(--muted);
      text-decoration:none;
      margin-bottom:6px;
      font-weight:500;
    }

    .menu a:hover{
      background:rgba(255,255,255,.05);
      color:#fff;
    }

    .menu a.active{
      background:rgba(59,130,246,.2);
      color:#fff;
    }

    /* CONTENT */
    .content{
      flex:1;
      padding:20px 30px;
    }

    /* TOPBAR */
    .topbar{
      display:flex;
      justify-content:space-between;
      align-items:center;
      margin-bottom:20px;
    }

    .search{
      flex:1;
      max-width:400px;
      background:rgba(255,255,255,.05);
      border-radius:12px;
      padding:10px 14px;
      border:1px solid var(--border);
      color:#fff;
    }

    .user{
      display:flex;
      align-items:center;
      gap:10px;
      font-weight:600;
    }

    .avatar{
      width:36px;
      height:36px;
      border-radius:50%;
      background:#3b82f6;
      display:flex;
      align-items:center;
      justify-content:center;
      font-weight:bold;
    }

    /* CARD */
    .card{
      background:var(--card);
      backdrop-filter:blur(14px);
      border:1px solid var(--border);
      border-radius:16px;
      padding:20px;
      margin-bottom:20px;
    }

    /* BUTTON */
    .btn{
      background:var(--gradient);
      border:none;
      color:#fff;
      padding:10px 16px;
      border-radius:10px;
      cursor:pointer;
      font-weight:600;
    }

    .btn:hover{
      opacity:.9;
    }

    /* DARK/LIGHT TOGGLE */
    .toggle{
      cursor:pointer;
      border-radius:10px;
      padding:6px 10px;
      border:1px solid var(--border);
      background:rgba(255,255,255,.05);
    }

  </style>

  @stack('head')
</head>

<body>

<div class="layout">

  {{-- SIDEBAR --}}
  <aside class="sidebar">
    <div class="logo">
      PACTOPIA
    </div>

    <nav class="menu">
      <a href="#" class="active">Inicio</a>
      <a href="#">Cuenta</a>
      <a href="#">Módulos</a>
      <a href="#">Configuración</a>
    </nav>
  </aside>

  {{-- MAIN --}}
  <main class="content">

    {{-- TOPBAR --}}
    <div class="topbar">

      <input class="search" placeholder="Buscar en tu cuenta...">

      <div style="display:flex;align-items:center;gap:12px;">
        <div class="toggle" onclick="toggleTheme()">🌙</div>

        <div class="user">
          <div class="avatar">
            {{ strtoupper(substr(auth()->user()->name ?? 'U',0,1)) }}
          </div>
          {{ auth()->user()->name ?? 'Usuario' }}
        </div>
      </div>

    </div>

    {{-- CONTENT --}}
    @yield('content')

  </main>

</div>

<script>
function toggleTheme(){
  const html = document.documentElement;
  const current = html.getAttribute('data-theme');
  html.setAttribute('data-theme', current === 'dark' ? 'light' : 'dark');
}
</script>

@stack('scripts')

</body>
</html>