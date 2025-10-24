<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Pactopia360 · Admin | Iniciar sesión</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <link rel="stylesheet" href="{{ asset('assets/admin/css/login.css') }}">
  <script>
    // Persistencia de tema (no rompe tu JS si ya existe)
    (function(){
      const KEY='p360-theme-admin';
      const saved = localStorage.getItem(KEY);
      const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
      const initial = saved || (prefersDark ? 'dark' : 'light');
      document.addEventListener('DOMContentLoaded', function(){
        document.body.classList.toggle('theme-dark',  initial==='dark');
        document.body.classList.toggle('theme-light', initial!=='dark');
        const btn = document.getElementById('themeToggle');
        const setUI = (dark) => {
          if (!btn) return;
          btn.setAttribute('aria-pressed', String(dark));
          const i = btn.querySelector('.icon');  if(i) i.textContent  = dark ? '🌞' : '🌙';
          const l = btn.querySelector('.label'); if(l) l.textContent = dark ? 'Modo claro' : 'Modo oscuro';
        };
        setUI(initial==='dark');
        btn && btn.addEventListener('click', () => {
          const toDark = !document.body.classList.contains('theme-dark');
          document.body.classList.toggle('theme-dark', toDark);
          document.body.classList.toggle('theme-light', !toDark);
          localStorage.setItem(KEY, toDark ? 'dark' : 'light');
          setUI(toDark);
        });
      });
    })();
  </script>
</head>
<body>
  {{-- Botón flotante para cambiar de tema --}}
  <div class="theme-switch">
    <button type="button" class="theme-btn" id="themeToggle" aria-pressed="false">
      <span class="icon">🌙</span>
      <span class="label">Modo oscuro</span>
    </button>
  </div>

  <noscript><p class="nojs">Activa JavaScript para una mejor experiencia.</p></noscript>

  <div class="shell" role="main">
    <!-- IZQUIERDA: Branding / valor -->
    <section class="brand" aria-label="Acerca de Pactopia360">
      <div>
        <div class="logo" aria-label="Pactopia360">
          {{-- Logo para tema oscuro --}}
          <img class="logo-img logo-dark" src="{{ asset('assets/admin/img/logo-pactopia360-white.png') }}" alt="Pactopia360">
          {{-- Logo para tema claro --}}
          <img class="logo-img logo-light" src="{{ asset('assets/admin/img/logo-pactopia360-dark.png') }}"  alt="Pactopia360">
        </div>

        <h2 class="slogan">Acceso seguro al panel administrativo</h2>
        <div class="points" role="list">
          <div class="point" role="listitem">✅ <b>Roles y permisos</b> granulares (superadmin, perfiles y overrides).</div>
          <div class="point" role="listitem">📊 <b>Dashboard</b> con KPIs y módulos clave.</div>
          <div class="point" role="listitem">🛡️ <b>Sesión protegida</b> y auditoría de accesos.</div>
        </div>
      </div>
      <div class="foot-note">© {{ date('Y') }} Pactopia360. Todos los derechos reservados.</div>
    </section>

    <!-- DERECHA: Formulario -->
    <section class="panel" aria-label="Formulario de inicio de sesión para administradores">
      <form class="card" method="POST" action="{{ route('admin.login.do') }}" id="loginForm" novalidate autocomplete="on" accept-charset="UTF-8">
        @csrf
        <h1 class="title">Iniciar sesión</h1>
        <p class="subtitle">Usa tus credenciales administrativas para continuar.</p>

        <div aria-live="polite" aria-atomic="true">
          @if ($errors->any())
            <div class="err">
              @foreach ($errors->all() as $e)
                <div>• {{ $e }}</div>
              @endforeach
            </div>
          @endif
        </div>

        <div>
          <label for="email">Email</label>
          <input
            class="input"
            id="email"
            type="email"
            name="email"
            value="{{ old('email') }}"
            required
            autofocus
            autocomplete="username"
            inputmode="email"
            placeholder="micorreo@dominio.com"
            maxlength="120">
        </div>

        <div class="pwd-wrap">
          <label for="password">Contraseña</label>
          <input
            class="input"
            id="password"
            type="password"
            name="password"
            required
            autocomplete="current-password"
            placeholder="********"
            minlength="6"
            maxlength="72"
            aria-describedby="capsTip">
          <button type="button" class="toggle" aria-controls="password" aria-label="Mostrar u ocultar contraseña">Mostrar</button>
          <div id="capsTip" class="hint" style="display:none;margin-top:.35rem;">🔠 Bloq Mayús está activado</div>
        </div>

        <div class="row">
          <label class="remember">
            <input type="checkbox" name="remember" value="1"> Recordarme
          </label>
          <a class="link-muted" href="#" aria-disabled="true">¿Olvidaste tu contraseña?</a>
        </div>

        <button class="btn" id="btnSubmit" type="submit">Entrar</button>
        <div class="hint">Al continuar aceptas las políticas de seguridad de Pactopia360.</div>
      </form>
    </section>
  </div>

  <script src="{{ asset('assets/admin/js/login.js') }}"></script>
  <script>
    // UX: CapsLock, toggle contraseña, anti doble submit y limpieza
    (function(){
      const pwd    = document.getElementById('password');
      const capsEl = document.getElementById('capsTip');
      const btnPwd = document.querySelector('.pwd-wrap .toggle');
      const form   = document.getElementById('loginForm');
      const btn    = document.getElementById('btnSubmit');

      if (pwd && capsEl) {
        const onCaps = (e)=>{ const on = e.getModifierState && e.getModifierState('CapsLock'); capsEl.style.display = on ? 'block' : 'none'; };
        pwd.addEventListener('keydown', onCaps);
        pwd.addEventListener('keyup',   onCaps);
        pwd.addEventListener('blur',    ()=> capsEl.style.display='none');
      }
      if (btnPwd && pwd) {
        btnPwd.addEventListener('click', () => {
          const show = (pwd.type === 'password');
          pwd.type = show ? 'text' : 'password';
          btnPwd.textContent = show ? 'Ocultar' : 'Mostrar';
        });
      }
      if (form) {
        form.addEventListener('submit', () => {
          if (btn){ btn.disabled = true; btn.textContent = 'Entrando…'; }
          if (pwd){ pwd.value = (pwd.value || '').trim(); }
        });
      }
    })();
  </script>
</body>
</html>
