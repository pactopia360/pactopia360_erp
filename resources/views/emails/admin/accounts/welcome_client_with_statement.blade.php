<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Acceso Pactopia360</title>
</head>
<body style="font-family:Arial,Helvetica,sans-serif;background:#f6f7fb;margin:0;padding:24px;color:#0f172a;">
  <div style="max-width:720px;margin:0 auto;background:#ffffff;border-radius:14px;box-shadow:0 8px 24px rgba(15,23,42,.08);overflow:hidden;">
    <div style="padding:18px 22px;background:#0b1220;color:#fff;">
      <div style="font-size:16px;font-weight:700;">Pactopia360 ERP</div>
      <div style="opacity:.9;font-size:13px;margin-top:4px;">Acceso al portal + Estado de cuenta</div>
    </div>

    <div style="padding:22px;">
      <p style="margin:0 0 12px 0;font-size:14px;line-height:1.5;">
        Se ha creado su acceso al portal de clientes. A continuación encontrará sus credenciales y enlaces.
      </p>

      <div style="border:1px solid #e5e7eb;border-radius:12px;padding:14px;margin:14px 0;background:#fafafa;">
        <div style="font-weight:700;margin-bottom:10px;">Acceso al portal</div>
        <div style="font-size:14px;line-height:1.55;">
          <div><b>URL:</b> <a href="{{ $portal_url }}" target="_blank" rel="noopener">{{ $portal_url }}</a></div>
          <div><b>Usuario:</b> {{ $email }}</div>
          <div><b>Contraseña temporal:</b> {{ $password }}</div>
        </div>
      </div>

      <div style="border:1px solid #e5e7eb;border-radius:12px;padding:14px;margin:14px 0;background:#fff;">
        <div style="font-weight:700;margin-bottom:10px;">Estado de cuenta</div>
        <div style="font-size:14px;line-height:1.55;">
          <div><b>Periodo:</b> {{ $period }}</div>
          <div><b>PDF:</b> <a href="{{ $pdf_url }}" target="_blank" rel="noopener">Ver estado de cuenta</a></div>
        </div>
        <div style="font-size:12px;color:#64748b;margin-top:10px;">
          Si su estado de cuenta presenta saldo, el PDF incluye la liga de pago y QR.
        </div>
      </div>

      <p style="margin:16px 0 0 0;font-size:12px;color:#64748b;line-height:1.5;">
        Recomendación: cambie su contraseña al ingresar por primera vez.
      </p>
    </div>

    <div style="padding:16px 22px;background:#f8fafc;border-top:1px solid #e5e7eb;font-size:12px;color:#64748b;">
      © {{ date('Y') }} Pactopia360. Este correo fue enviado automáticamente.
    </div>
  </div>
</body>
</html>
