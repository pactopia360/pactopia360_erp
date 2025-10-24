@php($login = route('cliente.login'))
<!doctype html><meta charset="utf-8">
<div style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;max-width:580px;margin:0 auto;padding:24px;background:#0b1220;color:#e5e7eb">
  <h1 style="margin:0 0 8px;font-size:20px">¡Bienvenido a Pactopia360!</h1>
  <p style="margin:0 0 10px">Tu registro se completó correctamente. Estos son tus accesos temporales:</p>
  <ul style="margin:0 0 14px;padding-left:18px">
    <li><strong>Usuario (correo):</strong> {{ $email }}</li>
    <li><strong>Usuario (RFC):</strong> {{ $rfc }}</li>
    <li><strong>Contraseña temporal:</strong> {{ $password }}</li>
  </ul>
  <p style="margin:0 0 18px">Por seguridad te pediremos cambiarla al iniciar sesión.</p>
  <p><a href="{{ $login }}" style="background:#3b82f6;color:#fff;text-decoration:none;padding:12px 16px;border-radius:10px;font-weight:700;display:inline-block">Entrar al panel</a></p>
</div>
