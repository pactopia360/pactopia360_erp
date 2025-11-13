{{-- resources/views/cliente/auth/verify_email.blade.php (v2 visual Pactopia360) --}}
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Verificación de correo · Pactopia360</title>

  @env(['local','development','testing'])
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
  @endenv

  <style>
    body{
      font-family:'Poppins',system-ui,sans-serif;
      background:#fff8f9;
      color:#0f172a;
      min-height:100vh;
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
      0%{transform:translate3d(-3%,-2%,0)}100%{transform:translate3d(5%,5%,0)}
    }
    .card{
      background:linear-gradient(180deg,rgba(255,255,255,.96),rgba(255,255,255,.92));
      border:1px solid #f3d5dc;
      border-radius:22px;
      padding:28px 26px;
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
    .logo{
      width:160px;margin:0 auto 14px;display:block;
      filter:drop-shadow(0 6px 12px rgba(0,0,0,.25));
    }
    .title{
      font-weight:900;font-size:20px;color:#E11D48;margin:10px 0 8px;text-align:center;
    }
    .desc{color:#475569;font-size:14px;text-align:center;margin-bottom:16px;}
    .btn{
      display:block;width:100%;text-align:center;
      font-weight:800;font-size:14px;padding:12px 14px;border-radius:12px;
      transition:all .15s ease;
    }
    .btn-primary{
      color:#fff;background:linear-gradient(90deg,#E11D48,#BE123C);
      box-shadow:0 10px 24px rgba(225,29,72,.25);
    }
    .btn-primary:hover{filter:brightness(.96);}
    .btn-secondary{
      border:1px solid #f3d5dc;background:#fff;color:#E11D48;
    }
    .btn-secondary:hover{background:#fff0f3;}
    .icon-circle{
      width:56px;height:56px;border-radius:999px;
      display:flex;align-items:center;justify-content:center;
      margin:0 auto 10px;
    }
  </style>
</head>

<body>
  @php
    $status   = $status   ?? 'ok';
    $message  = $message  ?? '';
    $masked   = $phone_masked ?? '';
  @endphp

  <div class="card">
    <img src="{{ asset('assets/client/logop360light.png') }}" alt="Pactopia360" class="logo">

    @if ($status === 'ok')
      <div class="icon-circle" style="background:#ecfdf5;color:#059669;">
        <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
        </svg>
      </div>
      <h1 class="title">¡Correo verificado!</h1>
      <p class="desc">
        {{ $message ?: 'Tu correo fue confirmado correctamente.' }}<br>
        Ahora continúa con la <strong>verificación de tu teléfono</strong>.
      </p>
      @if ($masked)
        <p class="text-sm text-slate-500 text-center mb-3">
          Número registrado: <span class="font-semibold text-slate-700">{{ $masked }}</span>
        </p>
      @endif
      <a href="{{ route('cliente.verify.phone') }}" class="btn btn-primary mb-2">Verificar mi teléfono</a>
      <a href="{{ route('cliente.login') }}" class="btn btn-secondary">Ir al acceso de clientes</a>

    @elseif ($status === 'expired')
      <div class="icon-circle" style="background:#fefce8;color:#b45309;">
        <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l3 3M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
      </div>
      <h1 class="title">Enlace expirado</h1>
      <p class="desc">
        {{ $message ?: 'El enlace de verificación ya no es válido.' }}<br>
        Solicita un nuevo enlace con tu correo.
      </p>
      <a href="{{ route('cliente.verify.email.resend') }}" class="btn btn-primary mb-2">Reenviar enlace</a>
      <a href="{{ route('cliente.login') }}" class="btn btn-secondary">Volver al acceso</a>

    @else
      <div class="icon-circle" style="background:#fee2e2;color:#b91c1c;">
        <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round"
                d="M12 9v3m0 3h.01M9.75 4.5l-7.5 13A1.5 1.5 0 003.6 20h16.8a1.5 1.5 0 001.35-2.25l-7.5-13a1.5 1.5 0 00-2.6 0z"/>
        </svg>
      </div>
      <h1 class="title">No pudimos verificar</h1>
      <p class="desc">{{ $message ?: 'El enlace no es válido o ya fue utilizado.' }}</p>
      <a href="{{ route('cliente.verify.email.resend') }}" class="btn btn-primary mb-2">Obtener nuevo enlace</a>
      <a href="{{ route('cliente.login') }}" class="btn btn-secondary">Ir al acceso</a>
    @endif

    <div class="text-center mt-6 text-xs text-slate-500">
      ¿Necesitas ayuda? 
      <a href="mailto:{{ $soporte ?? 'soporte@pactopia.com' }}" class="font-semibold text-rose-600 hover:underline">
        {{ $soporte ?? 'soporte@pactopia.com' }}
      </a>
    </div>
  </div>
</body>
</html>
