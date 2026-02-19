{{-- resources/views/emails/cliente/verify_phone.blade.php --}}
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Tu código de verificación · Pactopia360</title>
</head>
<body style="margin:0;padding:0;background:#f6f7fb;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
  <div style="max-width:640px;margin:0 auto;padding:24px;">
    <div style="background:#ffffff;border:1px solid rgba(15,23,42,.10);border-radius:14px;padding:22px;">
      <div style="font-weight:700;font-size:16px;margin-bottom:12px;">Pactopia360</div>

      <h2 style="margin:0 0 10px 0;font-size:18px;">Tu código de verificación</h2>

      <p style="margin:0 0 14px 0;font-size:14px;color:#334155;line-height:1.45;">
        Usa este código para verificar tu teléfono:
      </p>

      <div style="display:inline-block;padding:12px 16px;border-radius:12px;background:#0f172a;color:#fff;font-size:22px;letter-spacing:2px;">
        {{ $code ?? ($otp ?? '------') }}
      </div>

      <p style="margin:14px 0 0 0;font-size:13px;color:#64748b;">
        Este código expira en {{ $ttl_minutes ?? 10 }} minutos.
      </p>

      @if(!empty($phone))
        <p style="margin:10px 0 0 0;font-size:13px;color:#64748b;">
          Teléfono: {{ $phone }}
        </p>
      @endif

      <p style="margin:18px 0 0 0;font-size:12px;color:#64748b;line-height:1.45;">
        Si no solicitaste este código, ignora este correo.
      </p>
    </div>

    <p style="margin:14px 0 0 0;font-size:12px;color:#94a3b8;">
      Soporte: <a href="mailto:{{ config('p360.support.email') }}" style="color:#94a3b8;text-decoration:underline;">{{ config('p360.support.email') }}</a>
    </p>
  </div>
</body>
</html>
