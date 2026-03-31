{{-- resources/views/emails/admin/billing/layout.blade.php --}}
@php
  $emailTitle       = (string) ($emailTitle ?? 'Pactopia360');
  $emailPreheader   = trim((string) ($emailPreheader ?? ''));
  $openPixelUrl     = (string) ($openPixelUrl ?? '');
  $footerPrimary    = (string) ($footerPrimary ?? 'Este correo fue emitido por Pactopia360.');
  $footerSecondary  = (string) ($footerSecondary ?? 'Para cualquier aclaración, responde a este mensaje o entra a tu portal.');
@endphp
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>{{ $emailTitle }}</title>
</head>
<body style="margin:0;padding:0;background:#eef2ff;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
  
  @if($emailPreheader !== '')
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
      {{ $emailPreheader }}
    </div>
  @endif

  @if($openPixelUrl !== '')
    <img src="{{ $openPixelUrl }}" width="1" height="1" alt="">
  @endif

  <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eef2ff;">
    <tr>
      <td align="center" style="padding:28px 12px;">
        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:640px;margin:0 auto;">
          
          @yield('email_content')

          <tr>
            <td align="center" style="padding-top:16px;">
              <div style="font-size:12px;color:#64748b;">
                {{ $footerPrimary }}
              </div>
              <div style="margin-top:4px;font-size:12px;color:#94a3b8;">
                {{ $footerSecondary }}
              </div>
              <div style="margin-top:8px;font-size:12px;color:#94a3b8;">
                © {{ date('Y') }} Pactopia360
              </div>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>

</body>
</html>