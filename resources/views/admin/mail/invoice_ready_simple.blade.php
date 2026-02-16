{{-- resources/views/admin/mail/invoice_ready_simple.blade.php --}}
@php
  $accName = '';
  if (isset($account)) {
    $accName = trim((string)($account->razon_social ?? $account->name ?? ''));
  }
  if ($accName === '' && isset($req)) {
    $accName = trim((string)($req->account_name ?? $req->razon_social ?? $req->name ?? ''));
  }
  $accName = $accName !== '' ? $accName : 'Cliente';

  $periodTxt = trim((string)($period ?? ''));
  $portal    = trim((string)($portalUrl ?? ''));
  $zipOk     = (bool)($hasZip ?? false);
@endphp

<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Factura lista</title>
</head>
<body style="margin:0;padding:0;background:#f6f7fb;">
  <div style="max-width:640px;margin:0 auto;padding:24px;">
    <div style="background:#ffffff;border:1px solid #e5e7eb;border-radius:14px;overflow:hidden;">
      <div style="padding:18px 20px;background:#111827;color:#fff;">
        <div style="font:700 16px/1.2 system-ui,-apple-system,Segoe UI,Roboto,Arial;">
          Pactopia360
        </div>
        <div style="margin-top:4px;font:500 13px/1.3 system-ui,-apple-system,Segoe UI,Roboto,Arial;color:#cbd5e1;">
          Factura lista{{ $periodTxt !== '' ? ' · '.$periodTxt : '' }}
        </div>
      </div>

      <div style="padding:20px;">
        <p style="margin:0 0 12px;font:600 15px/1.45 system-ui,-apple-system,Segoe UI,Roboto,Arial;color:#111827;">
          Hola {{ e($accName) }},
        </p>

        <p style="margin:0 0 12px;font:400 14px/1.65 system-ui,-apple-system,Segoe UI,Roboto,Arial;color:#374151;">
          Tu factura ya está lista{{ $periodTxt !== '' ? ' para el periodo <b>'.e($periodTxt).'</b>' : '' }}.
          @if($zipOk)
            Te adjuntamos un <b>ZIP</b> con los archivos (PDF/XML) en este correo.
          @else
            Si no ves el ZIP adjunto, puedes descargarla desde tu portal.
          @endif
        </p>

        @if($portal !== '')
          <div style="margin:16px 0 4px;">
            <a href="{{ $portal }}"
               style="display:inline-block;text-decoration:none;background:#2563eb;color:#fff;padding:10px 14px;border-radius:10px;font:600 14px/1 system-ui,-apple-system,Segoe UI,Roboto,Arial;">
              Ir a Mi Cuenta
            </a>
          </div>
          <div style="margin:8px 0 0;font:400 12px/1.5 system-ui,-apple-system,Segoe UI,Roboto,Arial;color:#6b7280;">
            Si el botón no funciona, copia y pega este enlace: <span style="color:#111827;">{{ e($portal) }}</span>
          </div>
        @endif

        <hr style="border:0;border-top:1px solid #e5e7eb;margin:18px 0;">

        <div style="font:400 12px/1.6 system-ui,-apple-system,Segoe UI,Roboto,Arial;color:#6b7280;">
          Este correo fue enviado automáticamente por Pactopia360.
          Si necesitas ayuda, responde a este mensaje o contacta a soporte.
        </div>
      </div>
    </div>
  </div>
</body>
</html>