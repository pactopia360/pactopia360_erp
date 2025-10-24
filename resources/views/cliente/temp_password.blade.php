{{-- resources/views/emails/cliente/temp_password.blade.php --}}
@php
  $rfc   = $rfc   ?? '—';
  $email = $email ?? '—';
  $temp  = $temp  ?? '—';
  $url   = $url   ?? route('cliente.login');
@endphp

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Pactopia360 · Acceso temporal</title>
  <style>
    body{font-family:Arial,Helvetica,sans-serif;margin:0;padding:0;background:#f5f6f8;color:#222}
    .wrapper{max-width:580px;margin:30px auto;background:#fff;border-radius:12px;overflow:hidden;
             box-shadow:0 4px 24px rgba(0,0,0,.08)}
    .header{background:linear-gradient(90deg,#e31b23,#b8141b);padding:24px;text-align:center}
    .header img{max-width:160px;height:auto}
    .body{padding:32px 28px 36px;line-height:1.6}
    h1{font-size:20px;margin:0 0 10px;font-weight:800;color:#111}
    p{margin:10px 0}
    .code{display:inline-block;font-size:20px;font-weight:900;letter-spacing:2px;
          background:#f0f0f0;border:1px dashed #ccc;padding:10px 14px;border-radius:8px}
    .btn{display:inline-block;margin-top:22px;background:#e31b23;color:#fff;
         text-decoration:none;font-weight:700;padding:12px 22px;border-radius:8px}
    .footer{margin-top:30px;font-size:12px;color:#888;text-align:center}
  </style>
</head>
<body>
  <div class="wrapper">
    <div class="header">
      <img src="{{ asset('assets/client/logop360dark.png') }}" alt="Pactopia360">
    </div>
    <div class="body">
      <h1>Acceso temporal generado</h1>
      <p>Hola,</p>
      <p>Generamos una <strong>contraseña temporal</strong> para que puedas entrar al portal de clientes
        <strong>Pactopia360</strong> con tu correo o tu RFC.</p>

      <p><strong>RFC:</strong> {{ $rfc }}<br>
         <strong>Correo:</strong> {{ $email }}</p>

      <p>Tu contraseña temporal es:</p>
      <p><span class="code">{{ $temp }}</span></p>

      <p>Por seguridad, te pediremos crear una nueva contraseña al entrar.</p>

      <p style="text-align:center">
        <a href="{{ $url }}" class="btn">Iniciar sesión ahora</a>
      </p>

      <div class="footer">
        — Equipo Pactopia360<br>
        <a href="https://pactopia.com" style="color:#e31b23;text-decoration:none;">pactopia.com</a>
      </div>
    </div>
  </div>
</body>
</html>
