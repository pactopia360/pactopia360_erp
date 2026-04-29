<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Pactopia360 · Cliente | Recuperar contraseña</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <script>
    document.documentElement.classList.add('page-login-client');

    (function () {
      try {
        var saved = localStorage.getItem('p360_client_login_theme');
        var systemDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        var theme = saved === 'dark' || saved === 'light' ? saved : (systemDark ? 'dark' : 'light');
        document.documentElement.setAttribute('data-login-theme', theme);
      } catch (e) {
        document.documentElement.setAttribute('data-login-theme', 'light');
      }
    })();
  </script>

  <link rel="stylesheet" href="{{ asset('assets/client/css/login.css') }}?v={{ @filemtime(public_path('assets/client/css/login.css')) ?: time() }}">
</head>

<body class="theme-light">
@php
    use Illuminate\Support\Facades\Route;

    $logoDark = 'assets/client/img/Pactopia - Letra Blanca.png';

    $loginUrl = Route::has('cliente.login')
        ? route('cliente.login')
        : url('/cliente/login');

    $postUrl = Route::has('cliente.password.email')
        ? route('cliente.password.email')
        : url('/cliente/password/email');
@endphp

<main class="login-shell">
  <section class="login-wrap">
    <div class="login-left">
      <div class="login-left__overlay"></div>

      <a
        href="https://pactopia.com"
        class="pactopia-home-btn"
        target="_blank"
        rel="noopener noreferrer"
        aria-label="Ir a pactopia.com"
      >
        <span class="pactopia-home-btn__icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none">
            <path d="M3 10.8 12 4l9 6.8" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M6.5 9.8V20h11V9.8" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M10 20v-5.2a2 2 0 0 1 2-2h0a2 2 0 0 1 2 2V20" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </span>
        <span class="pactopia-home-btn__tooltip">pactopia.com</span>
      </a>

      <div class="login-brand">
        <img class="brand-logo" src="{{ asset($logoDark) }}" alt="Pactopia360">
        <div class="brand-subtitle">Portal usuario</div>
      </div>

      <div class="login-copy">
        <p class="login-kicker">Portal usuario</p>

        <h1 class="login-title">Recupera tu acceso</h1>

        <p class="login-text">
          Ingresa tu correo o RFC y te enviaremos un enlace seguro para restablecer tu contraseña.
        </p>

        <div class="login-register">
          <a href="{{ $loginUrl }}">
            ¿Ya tienes cuenta? Inicia sesión
          </a>
        </div>
      </div>

      <div class="login-foot">
        © {{ date('Y') }} Pactopia SAPI de CV. Todos los derechos reservados.
      </div>
    </div>

    <div class="login-right">
      <form class="auth-card" method="POST" action="{{ $postUrl }}" id="forgotForm" novalidate autocomplete="on">
        @csrf

        <div class="auth-toolbar">
          <button type="button" class="theme-toggle" id="themeToggle" aria-label="Cambiar tema" title="Cambiar tema">
            <span class="theme-toggle__sun" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none">
                <circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="1.8"/>
                <path d="M12 2v2.2M12 19.8V22M4.93 4.93l1.56 1.56M17.51 17.51l1.56 1.56M2 12h2.2M19.8 12H22M4.93 19.07l1.56-1.56M17.51 6.49l1.56-1.56" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
              </svg>
            </span>

            <span class="theme-toggle__moon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none">
                <path d="M21 12.8A8.5 8.5 0 1 1 11.2 3a6.8 6.8 0 1 0 9.8 9.8Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
              </svg>
            </span>
          </button>
        </div>

        <div class="auth-head">
          <h2 class="auth-head__title">
            Recuperar <br> contraseña
          </h2>
          <p class="auth-head__sub">
            Escribe tu correo electrónico o RFC para enviarte el enlace de recuperación.
          </p>
        </div>

        <div aria-live="polite" aria-atomic="true">
          @if (session('ok'))
            <div class="alert-block alert-success">{{ session('ok') }}</div>
          @endif

          @if (session('info'))
            <div class="alert-block alert-info">{{ session('info') }}</div>
          @endif

          @if (session('error'))
            <div class="alert-block alert-error">{{ session('error') }}</div>
          @endif

          @if ($errors->any())
            <div class="alert-block alert-error">
              @foreach ($errors->all() as $e)
                <div>• {{ $e }}</div>
              @endforeach
            </div>
          @endif
        </div>

        <div class="auth-form">
          <div class="field field-icon">
            <div class="input-shell">
              <span class="input-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none">
                  <path d="M4 7.5A2.5 2.5 0 0 1 6.5 5h11A2.5 2.5 0 0 1 20 7.5v9a2.5 2.5 0 0 1-2.5 2.5h-11A2.5 2.5 0 0 1 4 16.5v-9Z" stroke="currentColor" stroke-width="1.7"/>
                  <path d="m5 7 7 5 7-5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </span>

              <input
                class="input @error('login') is-invalid @enderror @error('email') is-invalid @enderror"
                id="login"
                name="login"
                type="text"
                value="{{ old('login', old('email')) }}"
                placeholder="micorreo@dominio.com o RFC"
                required
                autocomplete="username"
                maxlength="150"
                inputmode="email"
              >
            </div>

            <input type="hidden" id="emailHidden" name="email" value="{{ old('login', old('email')) }}">

            <div class="hint">
              Si tu cuenta existe, recibirás un correo con instrucciones.
            </div>
          </div>

          <div class="auth-actions">
            <button class="btn-submit" id="btnSubmit" type="submit">
              Enviar enlace de recuperación
            </button>

            <div class="hint hint-center">
              <a class="link-muted" href="{{ $loginUrl }}">
                ← Volver al inicio de sesión
              </a>
            </div>
          </div>
        </div>
      </form>
    </div>
  </section>
</main>

<script>
  (function () {
    const body = document.body;
    const html = document.documentElement;
    const btn = document.getElementById('themeToggle');
    const login = document.getElementById('login');
    const emailHidden = document.getElementById('emailHidden');

    function applyTheme(theme) {
      const isDark = theme === 'dark';

      body.classList.toggle('theme-dark', isDark);
      body.classList.toggle('theme-light', !isDark);
      html.setAttribute('data-login-theme', isDark ? 'dark' : 'light');

      try {
        localStorage.setItem('p360_client_login_theme', isDark ? 'dark' : 'light');
      } catch (e) {}

      if (btn) {
        btn.setAttribute('aria-pressed', isDark ? 'true' : 'false');
      }
    }

    try {
      const saved = localStorage.getItem('p360_client_login_theme');
      const initial = saved === 'dark' || saved === 'light'
        ? saved
        : html.getAttribute('data-login-theme') || 'light';

      applyTheme(initial);
    } catch (e) {
      applyTheme('light');
    }

    if (btn) {
      btn.addEventListener('click', function () {
        const current = body.classList.contains('theme-dark') ? 'dark' : 'light';
        applyTheme(current === 'dark' ? 'light' : 'dark');
      });
    }

    if (login && emailHidden) {
      login.addEventListener('input', function () {
        emailHidden.value = login.value;
      });

      setTimeout(function () {
        login.disabled = false;
        login.removeAttribute('readonly');
        login.focus({ preventScroll: true });
      }, 180);
    }
  })();
</script>
</body>
</html>