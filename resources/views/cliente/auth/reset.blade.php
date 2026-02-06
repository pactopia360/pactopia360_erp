<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Pactopia360 · Cliente | Restablecer contraseña</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <script>document.documentElement.classList.add('page-login-client');</script>
  <link rel="stylesheet" href="{{ asset('assets/client/css/login.css') }}">

  <style>
    html.page-login-client body > header,
    html.page-login-client header[role="banner"],
    html.page-login-client header.navbar,
    html.page-login-client header.site-header,
    html.page-login-client .topbar .brand { display:none !important; }

    .alert-block{border-radius:.6rem;padding:.75rem 1rem;margin-bottom:1rem;font-size:.9rem;line-height:1.4;}
    .alert-error{background:#fee2e2;border:1px solid #fecaca;color:#991b1b;}
    .hint{font-size:.85rem;color:#6b7280;}
    .link-muted{color:#6b7280;text-decoration:none;font-weight:600;}
    .link-muted:hover{text-decoration:underline;}

    /* 🔥 FIX overlays */
    .shell, .panel, .card, .form-body, .form-fields, .field { position:relative; z-index:2; }
    .shell::before, .shell::after,
    .panel::before, .panel::after,
    .card::before, .card::after { pointer-events:none !important; }
    input, button, a, label { pointer-events:auto !important; }

    .card .form-head,
    .card .form-fields,
    .card .form-actions{ max-width: min(600px, 100% - 100px); }
    @media (max-width: 900px){
      .card .form-head,
      .card .form-fields,
      .card .form-actions{ max-width: 100%; }
    }
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

  $postUrl = \Illuminate\Support\Facades\Route::has('cliente.password.update')
    ? route('cliente.password.update')
    : url('/cliente/password/reset');

  $emailVal = old('email', $email ?? '');
  $tokenVal = old('token', $token ?? '');
@endphp

  <div class="shell shell--balanced">
    {{-- IZQUIERDA --}}
    <section class="brand" aria-label="Restablecer contraseña">
      <div class="brand-inner">
        <header class="brand-top">
          <div class="logo local-brand">
            <img class="logo-img logo-dark"  src="{{ asset($logoDark)  }}" alt="Pactopia360">
            <img class="logo-img logo-light" src="{{ asset($logoLight) }}" alt="Pactopia360">
          </div>
          <h2 class="slogan">Crea una nueva contraseña</h2>
        </header>

        <ul class="points" role="list">
          <li>✅ Mínimo 8 caracteres.</li>
          <li>🔢 Incluye al menos un número.</li>
          <li>🔐 Incluye un carácter especial (@$!%*?&._-).</li>
        </ul>

        <footer class="brand-foot">
          <div class="foot-note">© {{ date('Y') }} Pactopia SAPI de CV. Todos los derechos reservados.</div>
        </footer>
      </div>
    </section>

    {{-- DERECHA --}}
    <section class="panel" aria-label="Formulario restablecer contraseña">
      <form class="card card-auto" method="POST" action="{{ $postUrl }}" novalidate autocomplete="off">
        @csrf

        <div class="card-brand">
          <h1 class="title">Restablecer contraseña</h1>
        </div>

        <div aria-live="polite" aria-atomic="true" style="margin-bottom:1rem;">
          @if ($errors->any())
            <div class="alert-block alert-error">@foreach ($errors->all() as $e)<div>• {{ $e }}</div>@endforeach</div>
          @endif
        </div>

        <input type="hidden" name="token" value="{{ $tokenVal }}">
        <input type="hidden" name="email" value="{{ $emailVal }}">

        <div class="form-body">
          <div class="form-head">
            <p class="subtitle">
              Nueva contraseña para <b>{{ $emailVal ?: 'tu cuenta' }}</b>.
            </p>
          </div>

          <div class="form-fields">
            <div class="field">
              <label for="password">Nueva contraseña</label>
              <input class="input @error('password') is-invalid @enderror"
                     id="password" name="password" type="password"
                     required minlength="8" maxlength="72"
                     autocomplete="new-password" placeholder="********">
              <div class="hint" style="margin-top:.35rem;">
                Mínimo 8 caracteres, número y carácter especial.
              </div>
            </div>

            <div class="field">
              <label for="password_confirmation">Confirmar contraseña</label>
              <input class="input"
                     id="password_confirmation" name="password_confirmation"
                     type="password" required minlength="8" maxlength="72"
                     autocomplete="new-password" placeholder="********">
            </div>
          </div>

          <div class="form-actions">
            <button class="btn" type="submit">Guardar contraseña</button>
            <div class="hint" style="margin-top:.6rem;">
              <a class="link-muted" href="{{ $loginUrl }}">← Volver a iniciar sesión</a>
            </div>
          </div>
        </div>
      </form>
    </section>
  </div>

  <script>
    window.addEventListener('load', () => {
      const p = document.getElementById('password');
      if (p) p.focus({ preventScroll: true });
    });
  </script>
</body>
</html>
