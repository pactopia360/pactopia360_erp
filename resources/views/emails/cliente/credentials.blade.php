{{-- resources/views/emails/cliente/credentials.blade.php --}}
@php
  $login = $login ?? ( \Illuminate\Support\Facades\Route::has('cliente.login') ? route('cliente.login') : url('/cliente/login') );
  $email = $email ?? '—';
  $rfc   = $rfc   ?? '—';
  $password = $password ?? '—';
  $brand = '#E11D48';   // rojo Pactopia
  $ink   = '#0b1220';   // texto oscuro
@endphp
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="x-apple-disable-message-reformatting">
  <meta name="color-scheme" content="light dark">
  <meta name="supported-color-schemes" content="light dark">
  <title>Bienvenido a Pactopia360</title>
  <style>
    /* Resets cortos compatibles */
    html,body{margin:0!important;padding:0!important}
    img{border:0;outline:none;text-decoration:none;display:block}
    a{color:{{ $brand }};text-decoration:none}
    table{border-collapse:collapse}
    /* Modo oscuro (clientes que lo soportan) */
    @media (prefers-color-scheme: dark){
      .bg{background:#0b1220 !important}
      .card{background:#121a2c !important;border-color:#1f2a44 !important;color:#e5e7eb !important}
      .muted{color:#9aa4b2 !important}
      .btn{background:{{ $brand }} !important}
      .link{color:#8ab4f8 !important}
    }
    /* Móvil */
    @media screen and (max-width:600px){
      .container{width:100% !important}
      .px{padding-left:18px !important;padding-right:18px !important}
    }
  </style>
</head>
<body class="bg" style="background:#f5f6f8; margin:0; padding:0;">
  {{-- Preheader oculto --}}
  <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
    Tus accesos temporales para entrar al panel de Pactopia360.
  </div>

  <table role="presentation" width="100%" bgcolor="#f5f6f8" class="bg">
    <tr>
      <td align="center" style="padding:24px">
        <table role="presentation" width="580" class="container" style="width:580px;max-width:100%;">
          {{-- Header/logo --}}
          <tr>
            <td align="center" style="padding:10px 0 16px">
              <img src="{{ asset('assets/client/logop360dark.png') }}" alt="Pactopia360" width="176" height="auto" style="height:auto;">
            </td>
          </tr>

          {{-- Card principal --}}
          <tr>
            <td class="px" style="padding:0 24px 24px">
              <table role="presentation" width="100%" class="card" style="background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
                <tr>
                  <td style="background:linear-gradient(90deg,#e31b23,#be123c);padding:18px 20px;">
                    <h1 style="margin:0;font:800 19px/1.2 system-ui,Segoe UI,Roboto,Arial;color:#ffffff;">
                      ¡Bienvenido a Pactopia360!
                    </h1>
                  </td>
                </tr>
                <tr>
                  <td style="padding:22px 20px 8px; color:{{ $ink }}; font:500 14px/1.55 system-ui,Segoe UI,Roboto,Arial;">
                    <p style="margin:0 0 10px;">Tu registro se completó correctamente. Estos son tus <strong>accesos temporales</strong>:</p>
                    <ul style="margin:10px 0 12px; padding-left:18px;">
                      <li><strong>Usuario (correo):</strong> {{ $email }}</li>
                      <li><strong>Usuario (RFC):</strong> {{ $rfc }}</li>
                      <li><strong>Contraseña temporal:</strong> {{ $password }}</li>
                    </ul>
                    <p class="muted" style="margin:0 0 18px; color:#5b6472;">
                      Por seguridad te pediremos cambiarla al iniciar sesión.
                    </p>
                    {{-- Botón bulletproof --}}
                    <table role="presentation" align="left" cellpadding="0" cellspacing="0" style="margin:0 0 6px;">
                      <tr>
                        <td bgcolor="{{ $brand }}" class="btn" style="border-radius:10px;">
                          <a href="{{ $login }}" style="display:inline-block;padding:12px 18px;font:800 14px/1 system-ui,Segoe UI,Roboto,Arial;color:#ffffff;background:{{ $brand }};border-radius:10px;">
                            Entrar al panel
                          </a>
                        </td>
                      </tr>
                    </table>

                    <p class="muted" style="clear:both;margin:14px 0 0;color:#5b6472;font-size:12px;">
                      Si el botón no funciona, copia y pega esta URL en tu navegador:<br>
                      <span class="link" style="word-break:break-all;color:#2563eb;">{{ $login }}</span>
                    </p>
                  </td>
                </tr>

                <tr>
                  <td style="padding:14px 20px 20px;">
                    <hr style="border:none;border-top:1px solid #e5e7eb;margin:0 0 12px;">
                    <p class="muted" style="margin:0;color:#5b6472;font:500 12px/1.5 system-ui,Segoe UI,Roboto,Arial;">
                      ¿Necesitas ayuda? Responde este correo o contáctanos en
                      <a href="https://pactopia.com" class="link" style="color:#2563eb;">pactopia.com</a>.
                    </p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          {{-- Footer --}}
          <tr>
            <td align="center" class="px" style="padding:8px 24px 0;">
              <p class="muted" style="margin:12px 0 0; color:#9aa4b2; font:500 11px/1.5 system-ui,Segoe UI,Roboto,Arial;">
                © {{ date('Y') }} Pactopia360 · Todos los derechos reservados
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
