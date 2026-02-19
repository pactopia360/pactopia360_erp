{{-- C:\wamp64\www\pactopia360_erp\resources\views\cliente\sat\mail\external_zip_invite.blade.php --}}
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Invitación ZIP FIEL</title>
</head>
<body style="margin:0;background:#f6f7fb;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
  <div style="max-width:640px;margin:0 auto;padding:22px;">
    <div style="background:#ffffff;border:1px solid rgba(15,23,42,.12);border-radius:14px;padding:18px 18px 16px;">
      <div style="font-size:14px;color:#64748b;margin-bottom:6px;">{{ $appName }}</div>
      <div style="font-size:18px;font-weight:700;margin-bottom:10px;">Invitación para subir ZIP de FIEL</div>

      @if(!empty($reference))
        <div style="font-size:13px;color:#334155;margin:0 0 12px;">
          <b>Referencia:</b> {{ $reference }}
        </div>
      @endif

      <div style="font-size:14px;line-height:1.55;color:#0f172a;margin-bottom:14px;">
        Te compartimos un enlace para cargar tu archivo <b>ZIP</b> con la FIEL (certificados/llave).
      </div>

      <div style="text-align:center;margin:18px 0 16px;">
        <a href="{{ $inviteUrl }}"
           style="display:inline-block;padding:12px 16px;border-radius:12px;background:#0ea5e9;color:#fff;text-decoration:none;font-weight:700;">
          Abrir enlace de carga
        </a>
      </div>

      @if(!empty($expiresAt))
        <div style="font-size:12px;color:#64748b;margin-top:10px;">
          Este enlace expira: <b>{{ $expiresAt }}</b>
        </div>
      @endif

      <div style="font-size:12px;color:#64748b;margin-top:14px;">
        Si el botón no funciona, copia y pega este enlace en tu navegador:
        <div style="word-break:break-all;margin-top:6px;color:#0f172a;">{{ $inviteUrl }}</div>
      </div>

      @if(!empty($traceId))
        <div style="font-size:11px;color:#94a3b8;margin-top:14px;">
          Trace: {{ $traceId }}
        </div>
      @endif
    </div>

    <div style="font-size:12px;color:#94a3b8;margin-top:10px;text-align:center;">
      © {{ date('Y') }} {{ $appName }} · {{ $appUrl }}
    </div>
  </div>
</body>
</html>
