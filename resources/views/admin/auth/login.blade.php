<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Pactopia360 · Admin | Iniciar sesión</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <link rel="stylesheet" href="{{ asset('assets/admin/css/login.css') }}">

  <script>
    // Persistencia de tema (admin) — sin dependencia del JS externo
    (function(){
      const KEY='p360-theme-admin';
      const saved = localStorage.getItem(KEY);
      const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
      const initial = saved || (prefersDark ? 'dark' : 'light');

      function setUI(dark){
        const btn = document.getElementById('themeToggle');
        if (!btn) return;
        btn.setAttribute('aria-pressed', String(dark));
        const i = btn.querySelector('.icon');  if(i) i.textContent  = dark ? '🌞' : '🌙';
        const l = btn.querySelector('.label'); if(l) l.textContent = dark ? 'Modo claro' : 'Modo oscuro';
      }

      document.addEventListener('DOMContentLoaded', function(){
        document.body.classList.toggle('theme-dark',  initial==='dark');
        document.body.classList.toggle('theme-light', initial!=='dark');
        setUI(initial==='dark');

        const btn = document.getElementById('themeToggle');
        btn && btn.addEventListener('click', () => {
          const toDark = !document.body.classList.contains('theme-dark');
          document.body.classList.toggle('theme-dark', toDark);
          document.body.classList.toggle('theme-light', !toDark);
          localStorage.setItem(KEY, toDark ? 'dark' : 'light');
          setUI(toDark);
        });
      });
    })();

    // ✅ Toggle password ULTRA-robusto: disponible incluso si no carga login.js
    window.P360_togglePwd = function(){
      const inp = document.getElementById('password');
      const btn = document.getElementById('btnTogglePassword');
      if (!inp || !btn) return;

      const isPw = (inp.getAttribute('type') || '').toLowerCase() === 'password';
      inp.setAttribute('type', isPw ? 'text' : 'password');
      btn.textContent = isPw ? 'Ocultar' : 'Mostrar';
      btn.setAttribute('aria-pressed', String(isPw));
    };
  </script>

  {{-- ✅ Micro-fix CSS inline SOLO para asegurar que el botón sea clickeable --}}
  <style>
    /* Si tu CSS trae overlays/position raros, esto asegura el click */
    .pwd-field{ position:relative; }
    .pwd-field .toggle{
      position: absolute;
      right: 10px;
      top: 50%;
      transform: translateY(-50%);
      z-index: 50;
      pointer-events: auto !important;
      touch-action: manipulation;
    }
    /* Asegura espacio para el botón */
    .pwd-field input.input{ padding-right: 92px; }
  </style>
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

        {{-- ALERTA ROBUSTA: errores y/o mensaje de sesión --}}
        <div aria-live="polite" aria-atomic="true">
          @php
            $hasErrors = $errors && $errors->any();
            $sessErr   = session('error') ?: session('warn');
          @endphp

          @if ($hasErrors || $sessErr)
            <div class="err" role="alert">
              @if ($sessErr)
                <div>• {{ $sessErr }}</div>
              @endif

              @if ($hasErrors)
                @foreach ($errors->all() as $e)
                  <div>• {{ $e }}</div>
                @endforeach
              @endif
            </div>
          @endif
        </div>

        <div>
          <label for="email">Email</label>
          <input
            class="input"
            id="email"
            type="text"
            name="email"
            value="{{ old('email') }}"
            required
            autofocus
            autocomplete="username"
            inputmode="email"
            placeholder="micorreo@dominio.com"
            maxlength="150">
        </div>

        <div class="pwd-wrap">
          <label for="password">Contraseña</label>

          <div class="pwd-field">
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

            {{-- ✅ onclick directo (no depende de listeners) --}}
            <button
              type="button"
              class="toggle"
              id="btnTogglePassword"
              aria-controls="password"
              aria-pressed="false"
              aria-label="Mostrar u ocultar contraseña"
              onclick="window.P360_togglePwd && window.P360_togglePwd()">Mostrar</button>
          </div>

          <div id="capsTip" class="hint hint-caps" style="display:none;">🔠 Bloq Mayús está activado</div>
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

  {{-- ✅ Listener adicional (por si quitas onclick) + CapsLock tip --}}
  <script>
    (function(){
      const btn = document.getElementById('btnTogglePassword');
      const inp = document.getElementById('password');
      const tip = document.getElementById('capsTip');

      if (btn && inp) {
        btn.addEventListener('click', function(e){
          // redundante, pero asegura que NUNCA se “pierda” el click
          e.preventDefault();
          if (window.P360_togglePwd) window.P360_togglePwd();
        });

        inp.addEventListener('keyup', function(e){
          try {
            const on = e.getModifierState && e.getModifierState('CapsLock');
            if (tip) tip.style.display = on ? 'block' : 'none';
          } catch (_) {}
        });
        inp.addEventListener('blur', function(){
          if (tip) tip.style.display = 'none';
        });
      }
    })();
  </script>

  {{-- Tu JS externo (si existe). Ya NO es necesario para el toggle. --}}
  <script src="{{ asset('assets/admin/js/login.js') }}"></script>
</body>
</html>
