<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Pactopia360 · Cliente | Iniciar sesión</title>
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
  <script src="{{ asset('assets/client/js/login.js') }}?v={{ @filemtime(public_path('assets/client/js/login.js')) ?: time() }}" defer></script>
</head>
<body class="theme-light">
@php
    use Illuminate\Support\Facades\Route;

    $logoDark  = 'assets/client/img/Pactopia - Letra Blanca.png';
    $logoLight = 'assets/client/img/Pactopia - Letra Blanca.png';

    $forgotUrl = Route::has('cliente.password.forgot')
        ? route('cliente.password.forgot')
        : url('/cliente/password/forgot');

    $adminLoginUrl = Route::has('admin.login')
        ? route('admin.login')
        : url('/admin/login');

    $urlFree = Route::has('cliente.registro.free')
        ? route('cliente.registro.free')
        : '#';

    $urlPro = Route::has('cliente.registro.pro')
        ? route('cliente.registro.pro')
        : '#';

    $resendUrl = Route::has('cliente.verify.email.resend')
        ? route('cliente.verify.email.resend', ['email' => (string) session('verify_email', old('login', ''))])
        : url('/cliente/verificar/email/reenviar');

    $emailToShow = trim((string) session('verify_email', old('login', '')));
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
        <h1 class="login-title">Hola! Bienvenido</h1>

        <p class="login-text">
          Accede a tu plataforma, gestiona y<br>
          controla la operación de tu empresa.
        </p>

        <div class="login-register">
          <a href="https://pactopia.com/p360/create/" target="_blank">
            ¿No tienes una cuenta?
          </a>
        </div>

        <p class="login-text">
         
        </p>

        <div class="plans-box">
          <div class="plans-box__title">¿No tienes cuenta?</div>

          <div class="plans-grid">
            <a class="plan-card plan-card--free" href="{{ $urlFree }}">
              <div class="plan-card__kicker">Forever Free</div>
              <div class="plan-card__title">Cuenta FREE</div>
              <div class="plan-card__desc">
                Ideal para comenzar con timbrado, XML y acceso inicial.
              </div>
              <span class="plan-card__btn plan-card__btn--free">Crear cuenta</span>
            </a>

            <a class="plan-card plan-card--pro" href="{{ $urlPro }}">
              <div class="plan-card__kicker">PRO</div>
              <div class="plan-card__title">Sube a PRO</div>
              <div class="plan-card__desc">
                Más capacidad, reportes avanzados y mejor control.
              </div>
              <span class="plan-card__btn plan-card__btn--pro">Conocer PRO</span>
            </a>
          </div>
        </div>
      </div>

      <div class="login-foot">
        © {{ date('Y') }} Pactopia SAPI de CV. Todos los derechos reservados.
      </div>
    </div>

    <div class="login-right">
      <form class="auth-card" method="POST" action="{{ route('cliente.login.do') }}" id="loginForm" novalidate autocomplete="on">
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
          <h2 class="auth-head__title">Iniciar <br>
           sesión</h2>
          <p class="auth-head__sub">
            <span id="detectMsg" class="hint" style="display:none;"></span>
          </p>
        </div>

        <div aria-live="polite" aria-atomic="true">
          @if (session('logged_out'))
            <div class="alert-block alert-info">Cerraste sesión correctamente.</div>
          @endif

          @if (session('ok'))
            <div class="alert-block alert-success">{{ session('ok') }}</div>
          @endif

          @if (session('info'))
            <div class="alert-block alert-info">{{ session('info') }}</div>
          @endif

          @if (session('error'))
            <div class="alert-block alert-error">{{ session('error') }}</div>
          @endif

          @if (session('need_verify'))
            <div class="alert-block alert-warn">
              <div class="alert-flex">
                <div class="alert-flex__body">
                  <div>
                    {{ is_string(session('need_verify')) ? session('need_verify') : 'Verifica tu correo y tu teléfono para activar tu cuenta.' }}
                  </div>

                  @if($emailToShow !== '')
                    <div class="alert-mail">
                      Correo: <strong>{{ $emailToShow }}</strong>
                    </div>
                  @endif

                  <div class="alert-help">
                    Puedes reenviar el enlace de verificación.
                  </div>
                </div>

                <div class="alert-flex__action">
                  <a href="{{ $resendUrl }}" class="inline-resend-btn">Reenviar</a>
                </div>
              </div>
            </div>
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
                class="input @error('login') is-invalid @enderror"
                id="login"
                name="login"
                type="text"
                value="{{ old('login') }}"
                placeholder="micorreo@dominio.com o RFC"
                required
                autocomplete="username"
                maxlength="120"
                inputmode="email"
              >
            </div>
            <div id="loginHelp" class="hint"></div>
          </div>

          <div class="field field-icon pwd-wrap">
            <div class="input-shell">
              <span class="input-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none">
                  <path d="M7 11V8a5 5 0 0 1 10 0v3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                  <rect x="5" y="11" width="14" height="9" rx="2.2" stroke="currentColor" stroke-width="1.7"/>
                </svg>
              </span>

              <input
                class="input @error('password') is-invalid @enderror"
                id="password"
                type="password"
                name="password"
                required
                autocomplete="current-password"
                placeholder="********"
                minlength="6"
                maxlength="72"
              >

              <button
                type="button"
                class="toggle"
                id="pwdToggle"
                aria-label="Mostrar contraseña"
                aria-pressed="false"
                data-showing="false">
                <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                  <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                  <circle cx="12" cy="12" r="3" stroke-width="2"/>
                </svg>
                <svg class="eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                  <path d="M3 3l18 18" stroke-width="2" stroke-linecap="round"/>
                  <path d="M2 12s4-7 10-7c2.7 0 5 .9 7 2.5" stroke-width="2"/>
                  <path d="M22 12s-4 7-10 7c-2.7 0-5-.9-7-2.5" stroke-width="2"/>
                </svg>
              </button>
            </div>
            <div id="capsTip" class="hint" style="display:none;">Bloq Mayús activado</div>
          </div>

          <div class="auth-row">
            <label class="remember">
              <input type="checkbox" name="remember" value="1">
              <span>Recordarme</span>
            </label>

            <a class="link-muted" href="{{ $forgotUrl }}">¿Olvidaste tu contraseña?</a>
          </div>

          <div class="auth-actions">
            <button class="btn-submit" id="btnSubmit" type="submit">Entrar</button>

            <div class="hint hint-center">
              Al continuar acepto los 
              <a href="https://pactopia.com/terminosycondiciones/" target="_blank" class="link-terms">
                Términos y Condiciones
              </a> de PACTOPIA.
            </div>
            </div>

          @if(app()->environment('local') && session('diag'))
            <details class="diag">
              <summary>Diagnóstico local</summary>
              <pre>{{ json_encode(session('diag'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </details>
          @endif
        </div>
      </form>
    </div>
  </section>
</main>

</body>
</html>