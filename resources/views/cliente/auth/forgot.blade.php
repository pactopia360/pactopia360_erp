{{-- resources/views/cliente/auth/forgot.blade.php (v3) --}}
@extends('layouts.guest')

@section('title', 'Recuperar acceso · Pactopia360')
@section('hide-brand', 'is-hidden')

@push('styles')
<style>
  /* ======== Fondo femenino P360 con gradiente ======== */
  body{
    background:
      radial-gradient(40% 50% at 20% 20%, rgba(255,91,126,.45), transparent 65%),
      radial-gradient(40% 50% at 80% 70%, rgba(255,42,42,.32), transparent 65%),
      linear-gradient(180deg, #fff8f9 0%, #fff 100%);
    min-height:100dvh; display:flex; flex-direction:column; justify-content:center;
    font-family: 'Poppins', system-ui, sans-serif;
  }
  html.theme-dark body{
    background:
      radial-gradient(40% 50% at 15% 25%, rgba(255,91,126,.12), transparent 65%),
      radial-gradient(40% 50% at 80% 70%, rgba(255,42,42,.12), transparent 65%),
      linear-gradient(180deg, #0d1524 0%, #111827 100%);
  }

  /* ======== Auth shell ======== */
  .auth-shell{width:min(480px,92vw);margin:auto;position:relative;text-align:center;}
  .auth-card{
    position:relative; background:var(--card,#fff);
    border-radius:20px; padding:28px 26px 26px;
    box-shadow:0 22px 60px rgba(0,0,0,.15);
    border:1px solid color-mix(in oklab,var(--bd,#e5e7eb) 85%, transparent);
    backdrop-filter:saturate(140%) blur(12px);
  }
  html.theme-dark .auth-card{
    background:color-mix(in oklab,#1e293b 92%, transparent);
    border-color:rgba(255,255,255,.1);
  }
  .auth-card::before{
    content:"";position:absolute;inset:-1px;border-radius:21px;
    background:linear-gradient(145deg,rgba(255,91,126,.7),rgba(255,42,42,.6));
    -webkit-mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);
    -webkit-mask-composite:xor;mask-composite:exclude;padding:1px;
  }

  /* ===== Logo circular ===== */
  .logo-wrap{
    width:84px;height:84px;margin:-64px auto 10px;
    display:grid;place-items:center;border-radius:50%;
    background:linear-gradient(180deg,#fff,#f6f9fc);
    border:1px solid color-mix(in oklab,var(--bd,#e5e7eb) 85%, transparent);
    box-shadow:0 16px 40px rgba(255,91,126,.25),0 6px 18px rgba(0,0,0,.18);
  }
  html.theme-dark .logo-wrap{background:linear-gradient(180deg,#1e293b,#111827);border-color:#334155;}
  .logo-wrap img{width:64px;height:auto;display:block}

  /* ===== Title / text ===== */
  h1{margin:0;font-weight:900;font-size:1.3rem;letter-spacing:.2px}
  .subtitle{margin:8px 0 16px;color:var(--muted,#6b7280);font-size:.9rem;line-height:1.4}

  /* ===== Field / input ===== */
  .field{display:flex;flex-direction:column;align-items:flex-start;text-align:left;margin:10px 0;width:100%;}
  .field label{font-size:.85rem;font-weight:700;margin-bottom:6px;color:var(--muted,#6b7280);}
  .input{
    width:100%;border-radius:12px;padding:12px 14px;
    background:color-mix(in oklab,var(--card,#fff) 92%, transparent);
    border:1px solid color-mix(in oklab,var(--bd,#e5e7eb) 85%, transparent);
    transition:border-color .2s, box-shadow .2s;
  }
  .input:focus{
    border-color:#E11D48;
    box-shadow:0 0 0 4px rgba(225,29,72,.15);
    outline:none;
  }

  /* ===== Buttons ===== */
  .btn-submit{
    width:100%;padding:12px 14px;border-radius:12px;border:0;
    background:linear-gradient(90deg,#E11D48,#BE123C);
    color:#fff;font-weight:800;font-size:.95rem;
    box-shadow:0 10px 20px rgba(190,18,60,.25);
    cursor:pointer;transition:filter .2s;
  }
  .btn-submit:hover{filter:brightness(.96)}

  /* ===== Links ===== */
  .links{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-top:14px;}
  .link{color:#E11D48;text-decoration:none;font-weight:700;font-size:.85rem;}
  .link:hover{text-decoration:underline}
  .muted{color:var(--muted,#6b7280);font-size:.8rem;}

  /* ===== Alerts ===== */
  .alert-ok,.alert-err{border-radius:10px;padding:10px 12px;margin-bottom:10px;font-size:.85rem;}
  .alert-ok{background:#dcfce7;border:1px solid #86efac;color:#166534;}
  .alert-err{background:#fee2e2;border:1px solid #fecaca;color:#991b1b;}

  /* ===== Footer (sticky bottom always visible) ===== */
  footer{
    margin-top:48px;padding:10px;text-align:center;font-size:.8rem;color:var(--muted,#64748b);
  }
  footer a{color:#E11D48;text-decoration:none;font-weight:600;}
  footer a:hover{text-decoration:underline;}
</style>
@endpush

@section('content')
  <div class="auth-shell">
    <div class="logo-wrap" aria-hidden="true">
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="{{ asset('assets/client/logop360dark.png') }}">
        <img src="{{ asset('assets/client/logop360light.png') }}" alt="Pactopia360"
             onerror="this.src='{{ asset('assets/client/logop360dark.png') }}';">
      </picture>
    </div>

    <div class="auth-card">
      <h1>Recuperar acceso</h1>
      <p class="subtitle">
        Escribe tu <strong>correo</strong> o <strong>RFC</strong> y te enviaremos un enlace de restablecimiento.
      </p>

      @if (session('ok'))
        <div class="alert-ok">{{ session('ok') }}</div>
      @endif

      @if ($errors->any())
        <div class="alert-err">{{ $errors->first() }}</div>
      @endif

      <form method="POST" action="{{ route('cliente.password.email') }}" novalidate>
        @csrf
        <div class="field">
          <label for="email">Correo o RFC</label>
          <input id="email" name="email" class="input" type="text"
                 placeholder="micorreo@dominio.com o RFC"
                 value="{{ old('email', request('e')) }}" required>
        </div>
        <button class="btn-submit" type="submit">Enviar enlace</button>
      </form>

      <div class="links">
        <a class="link" href="{{ route('cliente.login') }}">Volver al inicio de sesión</a>
        <span class="muted">Por seguridad, no indicamos si el correo/RFC existe.</span>
      </div>
    </div>

    <footer>
      © {{ date('Y') }} Pactopia360 ·
      <a href="https://pactopia.com" target="_blank" rel="noopener">Sitio oficial</a>
    </footer>
  </div>
@endsection
