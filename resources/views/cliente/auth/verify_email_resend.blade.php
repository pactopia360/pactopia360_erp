{{-- resources/views/cliente/auth/verify_email_resend.blade.php (v4 con logo light/dark) --}}
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reenviar verificación de correo · Pactopia360</title>

  @env(['local','development','testing'])
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
  @endenv

  <style>
    body{
      font-family:'Poppins',system-ui,sans-serif;
      background:#fff8f9;
      color:#0f172a;
      min-height:100vh;
      margin:0;
      display:flex;
      align-items:center;
      justify-content:center;
      overflow:hidden;
    }
    body::before{
      content:"";
      position:fixed;inset:-25%;
      z-index:-1;
      background:
        radial-gradient(40% 50% at 20% 15%, rgba(255,91,126,.3), transparent 60%),
        radial-gradient(40% 50% at 80% 80%, rgba(190,18,60,.3), transparent 60%);
      filter:blur(100px);
      animation:float 12s ease-in-out infinite alternate;
    }
    @keyframes float{
      0%{transform:translate3d(-3%,-2%,0)}
      100%{transform:translate3d(5%,5%,0)}
    }
    .card{
      background:linear-gradient(180deg,rgba(255,255,255,.96),rgba(255,255,255,.92));
      border:1px solid #f3d5dc;
      border-radius:22px;
      padding:28px 26px 24px;
      box-shadow:0 22px 60px rgba(0,0,0,.18);
      position:relative;
      width:min(520px,90vw);
      margin:auto;
    }
    .card::before{
      content:"";position:absolute;inset:-1px;border-radius:23px;padding:1px;
      background:linear-gradient(145deg,#E11D48,#BE123C);
      -webkit-mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);
      -webkit-mask-composite:xor;mask-composite:exclude;
      opacity:.25;pointer-events:none;
    }

    .logo-wrap{
      display:flex;
      justify-content:center;
      margin-bottom:12px;
    }
    .logo{
      width:160px;
      max-width:70%;
      object-fit:contain;
      filter:drop-shadow(0 6px 12px rgba(0,0,0,.25));
      display:block;
    }
    .logo-dark{display:none;}
    @media (prefers-color-scheme: dark){
      body{background:#020617;color:#e5e7eb;}
      .card{background:linear-gradient(180deg,rgba(15,23,42,.96),rgba(15,23,42,.9));
            border-color:#1f2937;}
      .card::before{opacity:.35;}
      .logo-light{display:none;}
      .logo-dark{display:block;}
    }

    .title{
      font-weight:900;font-size:20px;color:#E11D48;margin:10px 0 4px;text-align:center;
    }
    .desc{
      color:#475569;font-size:14px;text-align:center;margin-bottom:16px;
    }
    @media (prefers-color-scheme: dark){
      .desc{color:#cbd5f5;}
    }

    .icon-circle{
      width:56px;height:56px;border-radius:999px;
      display:flex;align-items:center;justify-content:center;
      margin:0 auto 10px;
      background:#fef2f2;
      color:#E11D48;
    }
    .icon-circle svg{width:28px;height:28px;}
    @media (prefers-color-scheme: dark){
      .icon-circle{background:#1e293b;color:#fb7185;}
    }

    .alert-ok{
      border-radius:12px;
      padding:10px 12px;
      font-size:13px;
      margin-bottom:14px;
      display:flex;
      align-items:flex-start;
      gap:8px;
      background:#ecfdf3;
      border:1px solid #bbf7d0;
      color:#047857;
    }
    @media (prefers-color-scheme: dark){
      .alert-ok{background:#022c22;border-color:#16a34a;color:#6ee7b7;}
    }

    form{margin-top:6px;}
    .field{margin-bottom:14px;}
    label{
      display:block;
      font-size:13px;
      font-weight:600;
      color:#0f172a;
      margin-bottom:4px;
    }
    @media (prefers-color-scheme: dark){
      label{color:#e5e7eb;}
    }
    input[type="email"]{
      width:100%;
      border-radius:12px;
      border:1px solid #e5e7eb;
      padding:10px 12px;
      font-size:14px;
      outline:none;
      transition:border-color .15s ease, box-shadow .15s ease, background .15s ease, color .15s ease;
      background:#ffffff;
      color:#0f172a;
    }
    input[type="email"]:focus{
      border-color:#fb7185;
      box-shadow:0 0 0 1px rgba(248,113,113,.55);
    }
    @media (prefers-color-scheme: dark){
      input[type="email"]{
        background:#020617;
        border-color:#1f2937;
        color:#e5e7eb;
      }
    }

    .error-text{
      font-size:12px;
      color:#b91c1c;
      margin-top:4px;
    }

    .btn{
      display:block;
      width:100%;
      text-align:center;
      font-weight:800;
      font-size:14px;
      padding:12px 14px;
      border-radius:12px;
      transition:all .15s ease;
      border:none;
      cursor:pointer;
    }
    .btn-primary{
      color:#fff;
      background:linear-gradient(90deg,#E11D48,#BE123C);
      box-shadow:0 10px 24px rgba(225,29,72,.25);
    }
    .btn-primary:hover{filter:brightness(.96);}

    .link-back{
      margin-top:10px;
      font-size:13px;
      color:#E11D48;
      font-weight:600;
      text-align:center;
      text-decoration:none;
      display:block;
    }
    .link-back:hover{text-decoration:underline;}

    .help{
      margin-top:16px;
      font-size:12px;
      color:#64748b;
      text-align:center;
    }
    .help a{
      color:#e11d48;
      font-weight:600;
      text-decoration:none;
    }
    .help a:hover{text-decoration:underline;}
  </style>
</head>
<body>
@php
  $flashOk    = session('ok');
  $emailError = $errors->first('email');
@endphp

<div class="card">
  <div class="logo-wrap">
    <img src="{{ asset('assets/client/p360-black.png') }}" alt="Pactopia360" class="logo logo-light">
    <img src="{{ asset('assets/client/p360-white.png') }}" alt="Pactopia360" class="logo logo-dark">
  </div>

  <div class="icon-circle">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round"
            d="M3 8.25l8.25 4.5L19.5 8.25M4.5 6h15a1.5 1.5 0 011.5 1.5v9a1.5 1.5 0 01-1.5 1.5h-15A1.5 1.5 0 013 16.5v-9A1.5 1.5 0 014.5 6z"/>
    </svg>
  </div>

  <h1 class="title">Reenviar verificación de correo</h1>
  <p class="desc">
    Ingresa el correo con el que te registraste en Pactopia360.<br>
    Te enviaremos un <strong>nuevo enlace de verificación</strong> si tu cuenta aún no está confirmada.
  </p>

  @if ($flashOk)
    <div class="alert-ok">
      <span>✅</span>
      <span>{{ $flashOk }}</span>
    </div>
  @endif

  <form method="POST" action="{{ route('cliente.verify.email.resend.do') }}">
    @csrf
    <div class="field">
      <label for="email">Correo electrónico</label>
      <input
        id="email"
        type="email"
        name="email"
        value="{{ old('email') }}"
        required
        autocomplete="email"
        placeholder="nombre@empresa.com">
      @if ($emailError)
        <div class="error-text">{{ $emailError }}</div>
      @endif
    </div>

    <button type="submit" class="btn btn-primary">
      Enviar nuevo enlace
    </button>
  </form>

  <a href="{{ route('cliente.login') }}" class="link-back">
    ← Volver al acceso de clientes
  </a>

  <p class="help">
    Si ya verificaste tu correo y aún no puedes entrar, intenta
    <a href="{{ route('cliente.password.forgot') }}">restablecer tu contraseña</a>
    o escribe a
    <a href="mailto:soporte@pactopia.com">soporte@pactopia.com</a>.
  </p>
</div>
</body>
</html>
