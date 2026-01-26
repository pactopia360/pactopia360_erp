<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Pactopia360 · Cliente | Iniciar sesión</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <script>document.documentElement.classList.add('page-login-client');</script>
  <link rel="stylesheet" href="{{ asset('assets/client/css/login.css') }}">

  <style>
    /* Oculta headers globales si los hubiera */
    html.page-login-client body > header,
    html.page-login-client header[role="banner"],
    html.page-login-client header.navbar,
    html.page-login-client header.site-header,
    html.page-login-client .topbar .brand { display:none !important; }

    /* Alertas compactas */
    .alert-block{border-radius:.6rem;padding:.75rem 1rem;margin-bottom:1rem;font-size:.9rem;line-height:1.4;}
    .alert-success{background:#dcfce7;border:1px solid #86efac;color:#166534;}
    .alert-warn{background:#fef9c3;border:1px solid #fde047;color:#78350f;}
    .alert-info{background:#eff6ff;border:1px solid #93c5fd;color:#1e3a8a;}
    .alert-error{background:#fee2e2;border:1px solid #fecaca;color:#991b1b;}

    /* Texto pequeño de “equipo Pactopia” */
    .mini-note-admin{font-size:.78rem;line-height:1.25;color:#6b7280;text-align:center;}
    .mini-note-admin a{font-size:.78rem;color:#E11D48;text-decoration:none;font-weight:600;}
    .mini-note-admin a:hover{text-decoration:underline;}

    /* ===== Toggle de contraseña (solo ícono) ===== */
    .pwd-wrap{position:relative;}
    .pwd-wrap .toggle{
      --eye-top: 56%;
      position:absolute; right:10px; top:var(--eye-top); transform:translateY(-50%);
      display:inline-grid; place-items:center; width:34px; height:34px; border-radius:8px;
      background:transparent; border:0; cursor:pointer; color:#6b7280;
    }
    .pwd-wrap .toggle:focus-visible{outline:none; box-shadow:0 0 0 2px rgba(225,29,72,.25)}
    .pwd-wrap .toggle svg{width:20px; height:20px; display:block; pointer-events:none;}
    .pwd-wrap .toggle .eye-open{display:none;}
    .pwd-wrap .toggle[data-showing="true"] .eye-open{display:block;}
    .pwd-wrap .toggle[data-showing="true"] .eye-closed{display:none;}

    /* Limite interno para que los campos “queden al ancho del cuadro” */
    .card .form-head,
    .card .form-fields,
    .card .form-actions{
      max-width: min(600px, 100% - 100px); /* súbelo o bájalo a gusto */
    }

  </style>

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
    $logoLight = collect($lightCandidates)->first(fn($cand)=>file_exists(public_path($cand))) ?? $lightCandidates[0];

    $urlFree   = Route::has('cliente.registro.free')   ? route('cliente.registro.free')   : '#';
    $urlPro    = Route::has('cliente.registro.pro')    ? route('cliente.registro.pro')    : '#';
    $forgotUrl = Route::has('cliente.password.forgot') ? route('cliente.password.forgot') : '#';
  @endphp

  <div class="theme-switch">
    <button type="button" class="theme-btn" id="themeToggle" aria-pressed="false">
      <span class="icon">🌙</span><span class="label">Modo oscuro</span>
    </button>
  </div>

  <div class="shell shell--balanced">
    {{-- IZQUIERDA --}}
    <section class="brand" aria-label="Beneficios del portal de clientes">
      <div class="brand-inner">
        <header class="brand-top">
          <div class="logo local-brand">
            <img class="logo-img logo-dark"  src="{{ asset($logoDark)  }}" alt="Pactopia360">
            <img class="logo-img logo-light" src="{{ asset($logoLight) }}" alt="Pactopia360">
          </div>
          <h2 class="slogan">Acceso seguro al portal de clientes</h2>
        </header>

        <ul class="points" role="list">
          <li>🧾 <b>CFDI 4.0 sin fricción</b> con validaciones claras.</li>
          <li>📊 <b>Conciliación ágil</b> con menos clics y más control.</li>
          <li>🗄️ <b>Bóveda XML</b> con búsqueda rápida y descargas masivas.</li>
          <li>🧑‍🤝‍🧑 <b>Multiusuario</b> y roles para tu equipo contable.</li>
          <li>🔐 <b>Seguridad reforzada</b> y auditoría de accesos.</li>
        </ul>

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

    {{-- DERECHA (FORM) --}}
    <section class="panel" aria-label="Inicio de sesión de cliente">
      <form class="card card-auto" method="POST" action="{{ route('cliente.login.do') }}" id="loginForm" novalidate autocomplete="on">
        @csrf

        <div class="card-brand"><h1 class="title">Iniciar sesión</h1></div>

        {{-- MENSAJES --}}
        <div aria-live="polite" aria-atomic="true" style="margin-bottom:1rem;">
          @if (session('logged_out'))  <div class="alert-block alert-info">Cerraste sesión correctamente.</div>@endif
          @if (session('ok'))          <div class="alert-block alert-success">{{ session('ok') }}</div>@endif
          @if (session('info'))        <div class="alert-block alert-info">{{ session('info') }}</div>@endif
          @if (session('error'))       <div class="alert-block alert-error">{{ session('error') }}</div>@endif
          @if (session('need_verify')) <div class="alert-block alert-warn">{{ session('need_verify') }}</div>@endif
          @if ($errors->any())
            <div class="alert-block alert-error">@foreach ($errors->all() as $e)<div>• {{ $e }}</div>@endforeach</div>
          @endif
        </div>

        <div class="form-body">
          <div class="form-head">
            <p class="subtitle">
              Ingresa <b>tu correo</b> o <b>RFC</b> y tu contraseña.
              <span id="detectMsg" class="hint" style="display:none;"></span>
            </p>
          </div>

          <div class="form-fields">
            <div class="field">
              <label for="login">Correo electrónico o RFC</label>
              <input class="input @error('login') is-invalid @enderror"
                     id="login" name="login" type="text"
                     value="{{ old('login') }}"
                     placeholder="micorreo@dominio.com o TU RFC"
                     required autocomplete="username" maxlength="120" inputmode="email">
              <div id="loginHelp" class="hint" style="margin-top:.25rem;"></div>
            </div>

            <div class="field pwd-wrap">
              <label for="password">Contraseña</label>
              <input class="input @error('password') is-invalid @enderror"
                     id="password" type="password" name="password"
                     required autocomplete="current-password"
                     placeholder="********" minlength="6" maxlength="72">
              <button type="button"
                      class="toggle"
                      id="pwdToggle"
                      aria-label="Mostrar contraseña"
                      aria-pressed="false"
                      data-showing="false">
                <!-- Ojo ABIERTO -->
                <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                  <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                  <circle cx="12" cy="12" r="3" stroke-width="2"/>
                </svg>
                <!-- Ojo CERRADO -->
                <svg class="eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                  <path d="M3 3l18 18" stroke-width="2" stroke-linecap="round"/>
                  <path d="M2 12s4-7 10-7c2.7 0 5 .9 7 2.5" stroke-width="2"/>
                  <path d="M22 12s-4 7-10 7c-2.7 0-5-.9-7-2.5" stroke-width="2"/>
                </svg>
              </button>
              <div id="capsTip" class="hint" style="display:none;margin-top:.35rem;">🔠 Bloq Mayús activado</div>
            </div>

            <div class="row">
              <label class="remember"><input type="checkbox" name="remember" value="1"> Recordarme</label>
              <a class="link-muted" href="{{ $forgotUrl }}">¿Olvidaste tu contraseña?</a>
            </div>
          </div>

          <div class="form-actions">
            <button class="btn" id="btnSubmit" type="submit">Entrar</button>
            <div class="hint">Al continuar aceptas las políticas de seguridad de Pactopia360.</div>
          </div>

          @if(app()->environment('local') && session('diag'))
            <details class="diag" style="margin-top:1rem;">
              <summary>🧪 Diagnóstico local</summary>
              <pre>{{ json_encode(session('diag'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </details>
          @endif
        </div>

        <div class="mini-note-admin" style="margin-top:10px;">
          ¿Eres del equipo Pactopia?<br>
          @php
            $adminLoginUrl = \Illuminate\Support\Facades\Route::has('admin.login')
              ? route('admin.login')
              : url('/admin/login');
          @endphp

          <a href="{{ $adminLoginUrl }}">Ir a panel interno</a>

        </div>
      </form>
    </section>
  </div>

</body>
</html>
