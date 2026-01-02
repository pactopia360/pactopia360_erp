{{-- C:\wamp64\www\pactopia360_erp\resources\views\emails\admin\cliente_credentials.blade.php --}}
@php
  $brandName = (string) data_get($p, 'brand.name', 'Pactopia360');
  $logoUrl   = (string) data_get($p, 'brand.logo_url', '');
  $rfc       = (string) data_get($p, 'account.rfc', '');
  $rs        = (string) data_get($p, 'account.razon_social', 'Cliente');
  $usuario   = (string) data_get($p, 'credentials.usuario', '');
  $password  = (string) data_get($p, 'credentials.password', '');
  $accessUrl = (string) data_get($p, 'credentials.access_url', '');
@endphp

<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Credenciales de acceso</title>
</head>
<body style="margin:0;padding:0;background:#f5f7fb;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;color:#0f172a;">
  <div style="max-width:640px;margin:0 auto;padding:28px 14px;">
    <div style="background:#0b1220;border-radius:14px;padding:18px 18px 16px;box-shadow:0 10px 30px rgba(2,6,23,.18);">
      <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
        <tr>
          <td style="vertical-align:middle;">
            @if($logoUrl)
              <img src="{{ $logoUrl }}" alt="{{ $brandName }}" style="height:34px;display:block;max-width:200px;">
            @else
              <div style="color:#e2e8f0;font-weight:700;font-size:18px;letter-spacing:.2px;">{{ $brandName }}</div>
            @endif
          </td>
          <td style="text-align:right;vertical-align:middle;">
            <span style="display:inline-block;background:rgba(124,58,237,.22);color:#e9d5ff;border:1px solid rgba(124,58,237,.35);padding:6px 10px;border-radius:999px;font-size:12px;">
              Acceso listo
            </span>
          </td>
        </tr>
      </table>

      <div style="margin-top:14px;color:#cbd5e1;font-size:13px;line-height:1.5;">
        Credenciales de acceso para:
        <strong style="color:#fff;">{{ $rs }}</strong>
        @if($rfc) <span style="color:#94a3b8;">({{ $rfc }})</span> @endif
      </div>
    </div>

    <div style="background:#ffffff;border-radius:14px;padding:18px;margin-top:14px;box-shadow:0 10px 26px rgba(2,6,23,.08);border:1px solid #e5e7eb;">
      <h1 style="margin:0 0 8px;font-size:18px;line-height:1.25;color:#0f172a;">Tus credenciales de acceso</h1>
      <div style="color:#475569;font-size:13px;line-height:1.6;">
        Conserva este correo. Si requieres soporte, responde a tu asesor o al canal oficial de {{ $brandName }}.
      </div>

      <div style="margin-top:14px;border-radius:12px;border:1px solid #e5e7eb;background:#f8fafc;padding:14px;">
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="font-size:13px;">
          <tr>
            <td style="width:140px;color:#64748b;padding:6px 0;">Usuario</td>
            <td style="padding:6px 0;color:#0f172a;font-weight:700;">{{ $usuario ?: '—' }}</td>
          </tr>
          <tr>
            <td style="color:#64748b;padding:6px 0;">Contraseña</td>
            <td style="padding:6px 0;color:#0f172a;font-weight:700;">{{ $password ?: '—' }}</td>
          </tr>
          <tr>
            <td style="color:#64748b;padding:6px 0;">Liga de acceso</td>
            <td style="padding:6px 0;">
              @if($accessUrl)
                <a href="{{ $accessUrl }}" style="color:#6d28d9;text-decoration:none;font-weight:700;">Abrir portal</a>
                <div style="margin-top:6px;color:#94a3b8;font-size:12px;word-break:break-all;">{{ $accessUrl }}</div>
              @else
                <span style="color:#94a3b8;">—</span>
              @endif
            </td>
          </tr>
        </table>
      </div>

      @if($accessUrl)
        <div style="margin-top:14px;text-align:center;">
          <a href="{{ $accessUrl }}"
             style="display:inline-block;background:#7c3aed;color:#fff;text-decoration:none;padding:12px 16px;border-radius:12px;font-weight:700;font-size:13px;">
            Ingresar a {{ $brandName }}
          </a>
        </div>
      @endif

      <div style="margin-top:16px;color:#64748b;font-size:12px;line-height:1.6;">
        Recomendación: cambia tu contraseña después del primer acceso.
      </div>
    </div>

    <div style="text-align:center;margin-top:12px;color:#94a3b8;font-size:12px;">
      © {{ date('Y') }} {{ $brandName }}. Todos los derechos reservados.
    </div>
  </div>
</body>
</html>
