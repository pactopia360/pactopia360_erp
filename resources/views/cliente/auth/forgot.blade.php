<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Pactopia360 · Cliente | Recuperar acceso</title>
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
    .alert-error{background:#fee2e2;border:1px solid #fecaca;color:#991b1b;}
    .alert-info{background:#eff6ff;border:1px solid #93c5fd;color:#1e3a8a;}

    /* 🔥 FIX: evita overlays “fantasma” que bloquean inputs */
    .shell, .panel, .card, .form-body, .form-fields, .field { position:relative; z-index:2; }
    .shell::before, .shell::after,
    .panel::before, .panel::after,
    .card::before, .card::after { pointer-events:none !important; }
    .brand, .brand * { pointer-events:auto; }
    input, button, a, label, textarea, select { pointer-events:auto !important; }

    /* Consistencia ancho interno */
    .card .form-head,
    .card .form-fields,
    .card .form-actions{ max-width: min(600px, 100% - 100px); }
    @media (max-width: 900px){
      .card .form-head,
      .card .form-fields,
      .card .form-actions{ max-width: 100%; }
    }

    .hint{font-size:.85rem;color:#6b7280;}
    .link-muted{color:#6b7280;text-decoration:none;font-weight:600;}
    .link-muted:hover{text-decoration:underline;}
  </style>
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

  $loginUrl = \Illuminate\Support\Facades\Route::has('cliente.login')
    ? route('cliente.login')
    : url('/cliente/login');

  $postUrl = \Illuminate\Support\Facades\Route::has('cliente.password.email')
    ? route('cliente.password.email')
    : url('/cliente/password/email');
@endphp

  <div class="theme-switch">
    <button type="button" class="theme-btn" id="themeToggle" aria-pressed="false">
      <span class="icon">🌙</span><span class="label">Modo oscuro</span>
    </button>
  </div>

  <div class="shell shell--balanced">
    {{-- IZQUIERDA --}}
    <section class="brand" aria-label="Recuperar acceso">
      <div class="brand-inner">
        <header class="brand-top">
          <div class="logo local-brand">
            <img class="logo-img logo-dark"  src="{{ asset($logoDark)  }}" alt="Pactopia360">
            <img class="logo-img logo-light" src="{{ asset($logoLight) }}" alt="Pactopia360">
          </div>
          <h2 class="slogan">Recupera tu acceso en minutos</h2>
        </header>

        <ul class="points" role="list">
          <li>🔐 Te enviamos un enlace seguro para crear una nueva contraseña.</li>
          <li>⏱️ El enlace expira en <b>60 minutos</b>.</li>
          <li>🧾 Puedes ingresar tu <b>correo</b> o tu <b>RFC</b>.</li>
          <li>🛡️ Por seguridad, no confirmamos si una cuenta existe.</li>
        </ul>

        <footer class="brand-foot">
          <div class="foot-note">© {{ date('Y') }} Pactopia SAPI de CV. Todos los derechos reservados.</div>
        </footer>
      </div>
    </section>

    {{-- DERECHA --}}
    <section class="panel" aria-label="Formulario recuperación">
      <form class="card card-auto" method="POST" action="{{ $postUrl }}" novalidate autocomplete="on">
        @csrf

        <div class="card-brand">
          <h1 class="title">Recuperar acceso</h1>
        </div>

        <div aria-live="polite" aria-atomic="true" style="margin-bottom:1rem;">
          @if (session('ok'))   <div class="alert-block alert-success">{{ session('ok') }}</div>@endif
          @if (session('info')) <div class="alert-block alert-info">{{ session('info') }}</div>@endif
          @if ($errors->any())
            <div class="alert-block alert-error">@foreach ($errors->all() as $e)<div>• {{ $e }}</div>@endforeach</div>
          @endif
        </div>

        <div class="form-body">
          <div class="form-head">
            <p class="subtitle">
              Escribe tu <b>correo</b> o <b>RFC</b> y te enviaremos un enlace de restablecimiento.
            </p>
          </div>

          <div class="form-fields">
            <div class="field">
              <label for="login">Correo o RFC</label>
              <input
                class="input @error('login') is-invalid @enderror @error('email') is-invalid @enderror"
                id="login" name="login" type="text"
                value="{{ old('login', old('email')) }}"
                placeholder="micorreo@dominio.com o TU RFC"
                required autocomplete="username" maxlength="150"
                inputmode="email"
              />

              {{-- Compat: si backend espera "email", mandamos ambos --}}
              <input type="hidden" name="email" value="{{ old('login', old('email')) }}">

              <div class="hint" style="margin-top:.35rem;">
                Si tu cuenta existe, recibirás el enlace por correo.
              </div>
            </div>
          </div>

          <div class="form-actions">
            <button class="btn" type="submit">Enviar enlace</button>
            <div class="hint" style="margin-top:.6rem;">
              <a class="link-muted" href="{{ $loginUrl }}">← Volver a iniciar sesión</a>
            </div>
          </div>
        </div>
      </form>
    </section>
  </div>

  <script>
    // Autofocus + FIX click (si había overlay extraño)
    window.addEventListener('load', () => {
      const el = document.getElementById('login');
      if (el) {
        el.removeAttribute('readonly');
        el.disabled = false;
        el.focus({ preventScroll: true });
      }
    });
  </script>
</body>
</html>
