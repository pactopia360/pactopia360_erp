<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Pactopia360 · Cliente | Iniciar sesión</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  {{-- Marcador para estilos específicos de login cliente --}}
  <script>document.documentElement.classList.add('page-login-client');</script>
  <link rel="stylesheet" href="{{ asset('assets/client/css/login.css') }}">

  {{-- Oculta encabezados globales si el layout los inyecta --}}
  <style>
    html.page-login-client body > header,
    html.page-login-client header[role="banner"],
    html.page-login-client header.navbar,
    html.page-login-client header.site-header,
    html.page-login-client .topbar .brand {
      display: none !important;
    }
  </style>

  {{-- Persistencia de tema (localStorage + prefers-color-scheme) --}}
  <script>
    (() => {
      const KEY = 'p360-theme-client';
      const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
      const saved = localStorage.getItem(KEY);
      const initial = saved || (prefersDark ? 'dark' : 'light');

      document.addEventListener('DOMContentLoaded', () => {
        const body = document.body;
        const isDark = initial === 'dark';
        body.classList.toggle('theme-dark', isDark);
        body.classList.toggle('theme-light', !isDark);

        const btn = document.getElementById('themeToggle');
        const updateUI = (dark) => {
          if (!btn) return;
          btn.setAttribute('aria-pressed', dark);
          const icon = btn.querySelector('.icon');
          const label = btn.querySelector('.label');
          if (icon) icon.textContent = dark ? '🌞' : '🌙';
          if (label) label.textContent = dark ? 'Modo claro' : 'Modo oscuro';
        };
        updateUI(isDark);

        btn?.addEventListener('click', () => {
          const toDark = !body.classList.contains('theme-dark');
          body.classList.toggle('theme-dark', toDark);
          body.classList.toggle('theme-light', !toDark);
          localStorage.setItem(KEY, toDark ? 'dark' : 'light');
          updateUI(toDark);
        });
      });
    })();
  </script>

  <script src="{{ asset('assets/client/js/login.js') }}" defer></script>
</head>

<body class="theme-light">
  @php
    $logoDark  = 'assets/client/logop360dark.png';
    $lightCandidates = [
      'assets/client/logop360light.png',
      'assets/client/logp360light_alt.png',
      'assets/client/logp360ligjt.png',
    ];
    $logoLight = $lightCandidates[0];
    foreach ($lightCandidates as $cand) {
        if (file_exists(public_path($cand))) { $logoLight = $cand; break; }
    }
  @endphp

  <noscript>
    <p style="padding:.75rem 1rem;background:#fee2e2;border:1px solid #fecaca;border-radius:.5rem;margin:1rem;">
      Activa JavaScript para una mejor experiencia.
    </p>
  </noscript>

  {{-- Botón flotante para cambio de tema --}}
  <div class="theme-switch">
    <button type="button" class="theme-btn" id="themeToggle" aria-pressed="false">
      <span class="icon">🌙</span>
      <span class="label">Modo oscuro</span>
    </button>
  </div>

  <div class="shell">
    {{-- IZQUIERDA: información y registro --}}
    <section class="brand" aria-label="Beneficios del portal de clientes">
      <div class="brand-inner">
        <header class="brand-top">
          <div class="logo local-brand">
            <img class="logo-img logo-dark" src="{{ asset($logoDark) }}" alt="Pactopia360">
            <img class="logo-img logo-light" src="{{ asset($logoLight) }}" alt="Pactopia360">
          </div>
          <h2 class="slogan">Acceso seguro al portal de clientes</h2>
        </header>

        <ul class="points">
          <li class="point">🧾 <b>CFDI 4.0 sin fricción</b> con validaciones claras.</li>
          <li class="point">📊 <b>Conciliación ágil</b> con menos clics y más control.</li>
          <li class="point">🗄️ <b>Bóveda XML</b> con búsqueda rápida y descargas masivas.</li>
          <li class="point">🧑‍🤝‍🧑 <b>Multiusuario</b> y roles para tu equipo contable.</li>
          <li class="point">🔐 <b>Seguridad reforzada</b> y auditoría de accesos.</li>
        </ul>

        @php
          use Illuminate\Support\Facades\Route;
          $urlFree = Route::has('cliente.registro.free') ? route('cliente.registro.free') : url('/registro/free');
          $urlPro  = Route::has('cliente.registro.pro')  ? route('cliente.registro.pro')  : url('/registro/pro');
        @endphp

        <div class="extras">
          <div class="extras-title">¿No tienes cuenta?</div>
          <div class="extras-grid">
            <a class="extra-card free" href="{{ $urlFree }}">
              <div class="extra-kicker">Forever Free</div>
              <div class="extra-title">Crea tu cuenta FREE</div>
              <p class="extra-desc">Ideal para comenzar: timbra, guarda XML y suma usuarios.</p>
              <span class="extra-cta">Crear cuenta</span>
            </a>
            <a class="extra-card pro" href="{{ $urlPro }}">
              <div class="extra-kicker">PRO</div>
              <div class="extra-title">Mejora a PRO</div>
              <p class="extra-desc">Reportes avanzados, capacidad ampliada y soporte priorizado.</p>
              <span class="extra-cta">Conocer PRO</span>
            </a>
          </div>
        </div>

        <footer class="brand-foot">
          <div class="foot-note">© {{ date('Y') }} Pactopia SAPI de CV. Todos los derechos reservados.</div>
        </footer>
      </div>
    </section>

    {{-- DERECHA: formulario --}}
    <section class="panel panel--wide" aria-label="Inicio de sesión de cliente">

      <form class="card card-auto" method="POST" action="{{ route('cliente.login.do') }}" id="loginForm" novalidate autocomplete="on">
        @csrf

        <div class="card-brand">
          <h1 class="title">Iniciar sesión</h1>
        </div>

        <div class="form-body">
          <div class="form-head">
            <p class="subtitle">
              Ingresa <b>tu correo</b> o <b>RFC</b> y tu contraseña.
              <span id="detectMsg" class="hint" style="display:none;"></span>
            </p>

            <div aria-live="polite" aria-atomic="true">
              @if (session('ok'))   <div class="ok">• {{ session('ok') }}</div> @endif
              @if (session('info')) <div class="ok">• {{ session('info') }}</div> @endif
              @if ($errors->any())
                <div class="err">@foreach ($errors->all() as $e)<div>• {{ $e }}</div>@endforeach</div>
              @endif
            </div>
          </div>

          <div class="form-fields">
            <div>
              <label for="login">Correo electrónico o RFC</label>
              <input class="input @error('login') is-invalid @enderror"
                id="login" name="login" type="text"
                value="{{ old('login') }}" placeholder="micorreo@dominio.com o TU RFC"
                required autocomplete="username" maxlength="120" inputmode="email">
              <div id="loginHelp" class="hint" style="margin-top:.25rem;"></div>
              @error('login')<div class="help-error">{{ $message }}</div>@enderror
            </div>

            <div class="pwd-wrap">
              <label for="password">Contraseña</label>
              <input class="input @error('password') is-invalid @enderror"
                id="password" type="password" name="password"
                required autocomplete="current-password"
                placeholder="********" minlength="6" maxlength="72">
              <button type="button" class="toggle" aria-label="Mostrar u ocultar contraseña">Mostrar</button>
              <div id="capsTip" class="hint" style="display:none;margin-top:.35rem;">🔠 Bloq Mayús activado</div>
              @error('password')<div class="help-error">{{ $message }}</div>@enderror
            </div>

            <div class="row">
              <label class="remember"><input type="checkbox" name="remember" value="1"> Recordarme</label>
              @php $forgotUrl = Route::has('cliente.password.forgot') ? route('cliente.password.forgot') : '#'; @endphp
              <a class="link-muted" href="{{ $forgotUrl }}">¿Olvidaste tu contraseña?</a>
            </div>
          </div>

          <div class="form-actions">
            <button class="btn" id="btnSubmit" type="submit">Entrar</button>
            <div class="hint">Al continuar aceptas las políticas de seguridad de Pactopia360.</div>
          </div>
        </div>
      </form>
    </section>
  </div>

  {{-- UX general: detección email/RFC, manejo de inputs y submit --}}
  <script>
    (() => {
      const login = document.getElementById('login');
      const help  = document.getElementById('loginHelp');
      const msg   = document.getElementById('detectMsg');
      const pwd   = document.getElementById('password');
      const capsEl= document.getElementById('capsTip');
      const btnPwd= document.querySelector('.pwd-wrap .toggle');
      const form  = document.getElementById('loginForm');
      const btn   = document.getElementById('btnSubmit');

      const looksEmail = v => /\S+@\S+\.\S+/.test(v);
      const sanitizeRfc = v => (v || '').toUpperCase().replace(/[^A-Z0-9&Ñ]/g,'');
      const looksRfc = v => /^[A-Z&Ñ]{3,4}\d{6}[A-Z0-9]{3}$/.test(sanitizeRfc(v||''));

      const renderDetect = (val) => {
        const v = (val||'').trim();
        let text = '';
        if (looksEmail(v)) text = 'Detectamos formato de correo electrónico.';
        else if (looksRfc(v)) text = 'Detectamos formato de RFC.';
        help.textContent = text;
        help.style.display = text ? 'block' : 'none';
        msg.textContent = text;
        msg.style.display = text ? 'inline' : 'none';
      };

      login?.addEventListener('input', e => renderDetect(e.target.value));
      if (login?.value) renderDetect(login.value);

      // Caps Lock
      if (pwd && capsEl) {
        const caps = (e) => {
          const on = e.getModifierState && e.getModifierState('CapsLock');
          capsEl.style.display = on ? 'block' : 'none';
        };
        pwd.addEventListener('keydown', caps);
        pwd.addEventListener('keyup', caps);
        pwd.addEventListener('blur', () => capsEl.style.display = 'none');
      }

      // Mostrar / ocultar contraseña
      btnPwd?.addEventListener('click', () => {
        const show = pwd.type === 'password';
        pwd.type = show ? 'text' : 'password';
        btnPwd.textContent = show ? 'Ocultar' : 'Mostrar';
      });

      // Trim de password al enviar + deshabilitar botón
      form?.addEventListener('submit', () => {
        if (btn) { btn.disabled = true; btn.textContent = 'Entrando…'; }
        if (pwd) { pwd.value = (pwd.value || '').trim(); }
      });
    })();
  </script>
</body>
</html>
