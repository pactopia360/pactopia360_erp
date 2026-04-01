{{-- resources/views/emails/admin/billing/layout.blade.php --}}
@php
  $emailTitle      = (string) ($emailTitle ?? 'Pactopia360');
  $emailPreheader  = trim((string) ($emailPreheader ?? ''));
  $openPixelUrl    = (string) ($openPixelUrl ?? '');
  $footerPrimary   = (string) ($footerPrimary ?? 'Este correo fue emitido por Pactopia360.');
  $footerSecondary = (string) ($footerSecondary ?? 'Para cualquier aclaración, responde a este mensaje o entra a tu portal.');
@endphp
<!doctype html>
<html lang="es" xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta http-equiv="x-ua-compatible" content="ie=edge">
  <meta name="x-apple-disable-message-reformatting">
  <title>{{ $emailTitle }}</title>
</head>
<body style="margin:0;padding:0;background:#eef2ff;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">

  @if($emailPreheader !== '')
    <div style="display:none!important;visibility:hidden;opacity:0;color:transparent;height:0;width:0;max-height:0;max-width:0;overflow:hidden;mso-hide:all;font-size:1px;line-height:1px;">
      {{ $emailPreheader }} &zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;
    </div>
  @endif

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;background:#eef2ff;margin:0;padding:0;">
    <tr>
      <td align="center" style="padding:28px 12px;">

        @if($openPixelUrl !== '')
          <img src="{{ $openPixelUrl }}" width="1" height="1" alt="" style="display:block;border:0;outline:none;text-decoration:none;width:1px;height:1px;">
        @endif

        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:700px;margin:0 auto;border-collapse:collapse;">
          @yield('email_content')

          <tr>
            <td align="center" style="padding:18px 10px 0 10px;">
              <div style="font-size:12px;line-height:1.6;color:#64748b;">
                {{ $footerPrimary }}
              </div>

              <div style="margin-top:4px;font-size:12px;line-height:1.6;color:#94a3b8;">
                {{ $footerSecondary }}
              </div>

              <div style="margin-top:8px;font-size:12px;line-height:1.6;color:#94a3b8;">
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