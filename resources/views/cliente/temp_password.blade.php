{{-- resources/views/emails/cliente/temp_password.blade.php --}}
@php
  $rfc   = $rfc   ?? '—';
  $email = $email ?? '—';
  $temp  = $temp  ?? '—';
  $url   = $url   ?? route('cliente.login');
@endphp
<!DOCTYPE html>
<html lang="es" xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta charset="UTF-8">
  <meta name="x-apple-disable-message-reformatting">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Pactopia360 · Acceso temporal</title>
  <!--[if mso]>
  <style type="text/css">
    body, table, td, a { font-family: Arial, Helvetica, sans-serif !important; }
  </style>
  <![endif]-->
  <style>
    /* Reset básico */
    html, body { margin:0 !important; padding:0 !important; height:100% !important; width:100% !important; }
    * { -ms-text-size-adjust:100%; -webkit-text-size-adjust:100%; }
    table, td { mso-table-lspace:0pt !important; mso-table-rspace:0pt !important; }
    img { -ms-interpolation-mode:bicubic; border:0; outline:none; text-decoration:none; display:block; }
    a { text-decoration:none; }

    /* Preheader oculto */
    .preheader { display:none !important; visibility:hidden; opacity:0; color:transparent; height:0; width:0; overflow:hidden; mso-hide:all; }

    /* Layout */
    body { background:#f5f6f8; color:#222; }
    .wrapper { width:100%; background:#f5f6f8; }
    .container { width:100%; max-width:600px; margin:0 auto; }
    .card {
      background:#ffffff;
      border-radius:12px;
      box-shadow:0 4px 24px rgba(0,0,0,.08);
      overflow:hidden;
    }

    .header { background:linear-gradient(90deg,#e31b23,#b8141b); padding:24px; text-align:center; }
    .header img { max-width:180px; height:auto; margin:0 auto; }

    .body { padding:32px 28px; font-family:Arial, Helvetica, sans-serif; line-height:1.6; color:#111; }
    h1 { margin:0 0 8px; font-size:20px; font-weight:800; color:#111; }
    p  { margin:10px 0; font-size:14px; color:#222; }

    /* Código temporal */
    .code {
      display:inline-block; font-size:20px; font-weight:900; letter-spacing:2px;
      background:#f0f0f0; border:1px dashed #ccc; padding:10px 14px; border-radius:8px;
      font-family: "Courier New", Courier, monospace;
    }

    /* Botón estándar (no-Outlook) */
    .btn {
      display:inline-block; margin-top:18px; background:#e31b23; color:#ffffff !important;
      font-weight:700; padding:12px 22px; border-radius:8px;
    }

    .muted { color:#666; font-size:12px; }

    .footer { text-align:center; padding:18px 10px 28px; color:#888; font-size:12px; font-family:Arial, Helvetica, sans-serif; }

    /* Dark-ish compat */
    @media (prefers-color-scheme: dark) {
      body { background:#0b0f14; color:#e6e6e6; }
      .card { background:#141a22; }
      .body { color:#e6e6e6; }
      p { color:#cfd6de; }
      .code { background:#111922; border-color:#2a3542; color:#e6e6e6; }
    }

    /* Responsive */
    @media screen and (max-width:600px){
      .body { padding:26px 20px; }
    }
  </style>
</head>
<body>
  <!-- Preheader (texto que algunos clientes muestran en la bandeja) -->
  <div class="preheader">
    Tu contraseña temporal de acceso a Pactopia360: {{ $temp }}. Úsala para iniciar sesión y cámbiala al entrar.
  </div>

  <table role="presentation" cellpadding="0" cellspacing="0" border="0" class="wrapper" width="100%">
    <tr>
      <td align="center" style="padding:24px 12px;">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" class="container">
          <tr>
            <td class="card">
              <!-- Header -->
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td class="header">
                    <img src="{{ asset('assets/client/logop360dark.png') }}" alt="Pactopia360">
                  </td>
                </tr>
              </table>

              <!-- Body -->
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td class="body">
                    <h1>Acceso temporal generado</h1>
                    <p>Hola,</p>
                    <p>Generamos una <strong>contraseña temporal</strong> para que puedas entrar al portal de clientes <strong>Pactopia360</strong> con tu correo o tu RFC.</p>

                    <p style="margin-top:14px">
                      <strong>RFC:</strong> {{ e($rfc) }}<br>
                      <strong>Correo:</strong> {{ e($email) }}
                    </p>

                    <p>Tu contraseña temporal es:</p>
                    <p><span class="code">{{ e($temp) }}</span></p>

                    <p class="muted" style="margin-top:6px">Por seguridad, te pediremos crear una nueva contraseña al entrar.</p>

                    <!-- Botón CTA (con VML para Outlook) -->
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-top:14px;">
                      <tr>
                        <td align="center">
                          <!--[if mso]>
                          <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word"
                            href="{{ $url }}" style="height:44px;v-text-anchor:middle;width:240px;" arcsize="12%" stroke="f" fillcolor="#e31b23">
                            <w:anchorlock/>
                            <center style="color:#ffffff;font-family:Arial, Helvetica, sans-serif;font-size:14px;font-weight:bold;">
                              Iniciar sesión ahora
                            </center>
                          </v:roundrect>
                          <![endif]-->
                          <!--[if !mso]><!-- -->
                          <a class="btn" href="{{ $url }}">Iniciar sesión ahora</a>
                          <!--<![endif]-->
                        </td>
                      </tr>
                    </table>

                    <p style="margin-top:18px; font-size:13px;">
                      Si el botón no funciona, copia y pega esta URL en tu navegador:<br>
                      <a href="{{ $url }}" style="color:#e31b23; word-break:break-all;">{{ $url }}</a>
                    </p>

                    <p class="muted" style="margin-top:18px">
                      Si no solicitaste este acceso, puedes ignorar este correo. Tu cuenta seguirá protegida.
                    </p>
                  </td>
                </tr>
              </table>

              <!-- Footer -->
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td class="footer">
                    — Equipo Pactopia360<br>
                    <a href="{{ config('p360.public.site_url') }}" style="color:#e31b23;">{{ preg_replace('#^https?://#','', config('p360.public.site_url')) }}</a>
                  </td>
                </tr>
              </table>

            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
