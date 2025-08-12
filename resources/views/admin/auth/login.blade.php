<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Pactopia360 · Admin | Iniciar sesión</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="{{ asset('assets/admin/css/login.css') }}">
</head>
<body>
  {{-- Botón flotante para cambiar de tema --}}
  <div class="theme-switch">
    <button type="button" class="theme-btn" id="themeToggle">
      <span class="icon">🌙</span>
      <span class="label">Modo oscuro</span>
    </button>
  </div>

  <div class="shell">
    <!-- IZQUIERDA: Branding / valor -->
    <section class="brand">
      <div>
      <div class="logo">
        {{-- Logo para tema oscuro --}}
        <img class="logo-img logo-dark" src="{{ asset('assets/admin/img/logo-pactopia360-white.png') }}" alt="Pactopia360">

        {{-- Logo para tema claro --}}
        <img class="logo-img logo-light" src="{{ asset('assets/admin/img/logo-pactopia360-dark.png') }}" alt="Pactopia360">
      </div>

        <h2 class="slogan">Acceso seguro al panel administrativo</h2>
        <div class="points">
          <div class="point">✅ <b>Roles y permisos</b> granulares (superadmin, perfiles y overrides).</div>
          <div class="point">📊 <b>Dashboard</b> con KPIs y módulos clave.</div>
          <div class="point">🛡️ <b>Sesión protegida</b> y auditoría de accesos.</div>
        </div>
      </div>
      <div class="foot-note">© {{ date('Y') }} Pactopia360. Todos los derechos reservados.</div>
    </section>

    <!-- DERECHA: Formulario -->
    <section class="panel">
      <form class="card" method="POST" action="{{ route('admin.login.do') }}" id="loginForm" novalidate>
        @csrf
        <h1 class="title">Iniciar sesión</h1>
        <p class="subtitle">Usa tus credenciales administrativas para continuar.</p>

        @if ($errors->any())
          <div class="err">
            @foreach ($errors->all() as $e)
              <div>• {{ $e }}</div>
            @endforeach
          </div>
        @endif

        <div>
          <label for="email">Email</label>
          <input class="input" id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
        </div>

        <div class="pwd-wrap">
          <label for="password">Contraseña</label>
          <input class="input" id="password" type="password" name="password" required>
          <button type="button" class="toggle" aria-label="Mostrar/Ocultar">Mostrar</button>
        </div>

        <div class="row">
          <label class="remember">
            <input type="checkbox" name="remember"> Recordarme
          </label>
          <a class="link-muted" href="#" aria-disabled="true">¿Olvidaste tu contraseña?</a>
        </div>

        <button class="btn" id="btnSubmit">Entrar</button>
        <div class="hint">Al continuar aceptas las políticas de seguridad de Pactopia360.</div>
      </form>
    </section>
  </div>

  <script src="{{ asset('assets/admin/js/login.js') }}"></script>
</body>
</html>
