{{-- resources/views/emails/cliente/welcome_account_activated.blade.php --}}
<!DOCTYPE html>
<html lang="es" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>Tu cuenta ya está activa · Pactopia360</title>
  <!--[if mso]>
  <xml><o:OfficeDocumentSettings><o:AllowPNG/><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml>
  <![endif]-->
  <style>
    html,body{margin:0!important;padding:0!important;height:100%!important;width:100%!important}
    *{-ms-text-size-adjust:100%;-webkit-text-size-adjust:100%}
    table,td{mso-table-lspace:0pt!important;mso-table-rspace:0pt!important}
    img{border:0;outline:0;text-decoration:none;-ms-interpolation-mode:bicubic;display:block}
    a{text-decoration:none}
    .container{width:600px;max-width:600px}
    /* Evita auto-colorear teléfonos/fechas en iOS */
    a[x-apple-data-detectors], .x-apple-data-detectors, .link-apple{
      color:inherit!important; text-decoration:none!important; font-size:inherit!important; font-family:inherit!important; font-weight:inherit!important; line-height:inherit!important;
    }
    @media screen and (max-width:600px){
      .container{width:100%!important}
      .px-24{padding-left:24px!important;padding-right:24px!important}
      .btn{width:100%!important}
    }
    /* dark-mode (clientes modernos) */
    @media (prefers-color-scheme: dark){
      .logo-dark{display:block!important}
      .logo-light{display:none!important}
    }
  </style>
</head>
<body style="margin:0;padding:0;background:#f6f7fb;">
  <!-- Preheader oculto -->
  <div style="display:none;visibility:hidden;opacity:0;overflow:hidden;height:0;width:0;max-height:0;max-width:0;line-height:1px;color:transparent;">
    Tu cuenta en Pactopia360 ya está activa. Entra al portal y comienza a timbrar CFDI 4.0.&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;
  </div>

  @php
    use Illuminate\Support\Facades\Route;

    $assetBase = rtrim(config('app.asset_url') ?: config('app.url'), '/');
    $logoLight = $assetBase . '/assets/client/' . rawurlencode('P360 BLACK.png');  // fondo claro
    $logoDark  = $assetBase . '/assets/client/' . rawurlencode('P360 WHITE.png');  // fondo oscuro

    // Fallback SEGURO: si no pasas $loginUrl y no existe la ruta, usa /cliente/login
    $loginHref = $loginUrl
      ?? (Route::has('cliente.login') ? route('cliente.login') : url('/cliente/login'));

    $nombreOk  = trim((string)($nombre ?? '')) !== '' ? $nombre : 'Usuario';
  @endphp

  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" bgcolor="#f6f7fb" style="background:#f6f7fb;">
    <tr>
      <td align="center" style="padding:32px 16px;">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" class="container" bgcolor="#ffffff"
               style="background:#ffffff;border:1px solid #e8ecf2;border-radius:14px;box-shadow:0 6px 24px rgba(16,24,40,.06)">
          <tr>
            <td class="px-24" style="padding:28px 32px 8px 32px;">
              <table role="presentation" width="100%">
                <tr>
                  <td align="center" style="padding-bottom:16px;">
                    <!-- Logo (UNO solo por defecto) -->
                    <img src="{{ $logoLight }}" width="180" alt="P360" class="logo-light" style="max-width:180px">
                    <!--[if !mso]><!-->
                    <img src="{{ $logoDark }}"  width="180" alt="P360" class="logo-dark"  style="display:none;max-width:180px">
                    <!--<![endif]-->
                  </td>
                </tr>
                <tr>
                  <td align="center" style="font:800 24px/1.3 -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;color:#111827;padding-bottom:6px;">
                    Tu cuenta ya está activa 🎉
                  </td>
                </tr>
                <tr>
                  <td align="center" style="font:400 15px/22px -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;color:#475467;padding-bottom:20px;">
                    Hola <span style="color:#111827;font-weight:700;">{{ $nombreOk }}</span>, ya puedes entrar al portal de <span style="color:#111827;font-weight:700;">Pactopia360</span>.
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Highlight -->
          <tr>
            <td class="px-24" style="padding:0 32px 8px 32px;">
              <table role="presentation" width="100%" bgcolor="#f8fafc" style="background:#f8fafc;border:1px solid #e6eaf0;border-radius:12px;">
                <tr>
                  <td style="padding:14px 16px;font:400 14px/20px -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;color:#475467;">
                    <div style="color:#111827;font-weight:700;padding-bottom:4px">Acceso habilitado</div>
                    <div>Tu correo y tu teléfono fueron verificados correctamente. Tu cuenta está lista para facturar CFDI 4.0, consultar reportes y administrar tu bóveda fiscal.</div>

                    @if(!empty($email) || !empty($rfc) || !empty($tempPassword))
                    <table role="presentation" width="100%" bgcolor="#ffffff" style="margin-top:12px;background:#ffffff;border:1px solid #e6eaf0;border-radius:10px;">
                      <tr>
                        <td style="padding:12px 14px;">
                          @isset($email)
                            <div style="font:600 12px/1 -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;color:#667085;text-transform:uppercase;letter-spacing:.02em;margin-bottom:2px">Correo</div>
                            <div style="font:700 13px/1.5 -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;color:#111827;word-break:break-all;margin-bottom:8px">{{ $email }}</div>
                          @endisset
                          @isset($rfc)
                            <div style="font:600 12px/1 -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;color:#667085;text-transform:uppercase;letter-spacing:.02em;margin-bottom:2px">RFC</div>
                            <div style="font:700 13px/1.5 -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;color:#111827;margin-bottom:8px">{{ $rfc }}</div>
                          @endisset
                          @isset($tempPassword)
                            <div style="font:600 12px/1 -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;color:#667085;text-transform:uppercase;letter-spacing:.02em;margin-bottom:2px">Contraseña temporal</div>
                            <div style="font:700 13px/1.5 -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;color:#111827">{{ $tempPassword }}</div>
                            <div style="font:400 12px/18px -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;color:#667085;margin-top:6px">Te pediremos cambiarla en tu primer inicio de sesión.</div>
                          @endisset
                        </td>
                      </tr>
                    </table>
                    @endif
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- CTA (UNO solo) -->
          <tr>
            <td align="center" style="padding:24px 32px 8px 32px;">
              <!--[if mso]>
              <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" href="{{ $loginHref }}"
                style="height:44px;v-text-anchor:middle;width:260px;" arcsize="12%"
                fillcolor="#e31b23" strokecolor="#b91c1c">
                <center style="color:#ffffff;font-family:Segoe UI,Arial,Helvetica,sans-serif;font-size:15px;font-weight:700;">
                  Ir al portal de clientes
                </center>
              </v:roundrect>
              <![endif]-->
              <!--[if !mso]><!-- -->
              <a href="{{ $loginHref }}" class="btn"
                 style="background:#e31b23;border:1px solid #b91c1c;border-radius:8px;color:#ffffff;display:inline-block;font:700 15px/44px -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;text-align:center;width:260px;">
                Ir a mi Pactopia360
              </a>
              <!--<![endif]-->
            </td>
          </tr>

          <!-- Nota -->
          <tr>
            <td align="center" style="padding:8px 32px 4px 32px;font:400 12px/18px -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;color:#667085;">
              Si olvidaste tu contraseña, puedes restablecerla en la pantalla de acceso.
            </td>
          </tr>

          <!-- Bullets -->
          <tr>
            <td class="px-24" style="padding:16px 32px 6px 32px;">
              <div style="font:700 13px/1.4 -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;color:#111827;margin-bottom:6px">Lo que puedes hacer desde hoy:</div>
              <div style="font:400 13px/20px -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;color:#475467">
                • Timbrar y cancelar CFDI 4.0 con validaciones en tiempo real.<br>
                • Visualizar tu flujo mensual de facturación con métricas claras.<br>
                • Subir, organizar y descargar XML de manera masiva.<br>
                • Dar acceso a tu contador y controlar permisos por usuario.<br>
                • Monitorear estatus de la cuenta y saldo de timbres.
              </div>

              @isset($is_pro)
                @if ($is_pro)
                  <div style="height:10px"></div>
                  <div style="font:700 13px/1.4 -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;color:#111827;margin-bottom:4px">Tu plan PRO</div>
                  <div style="font:400 13px/20px -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;color:#475467">Tienes prioridad en soporte, más almacenamiento, más timbres al mes y reportes avanzados.</div>
                @else
                  <div style="height:10px"></div>
                  <div style="font:700 13px/1.4 -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;color:#111827;margin-bottom:4px">¿Necesitas más capacidad?</div>
                  <div style="font:400 13px/20px -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;color:#475467">Pásate a PRO para más timbres, espacio ampliado y SLA prioritario.</div>
                @endif
              @endisset
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td align="center" style="padding:24px 24px 28px 24px;font:400 12px/18px -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;color:#667085;">
              ¿Dudas o necesitas ayuda? Escríbenos a
              <a href="mailto:{{ $soporte ?? 'soporte@pactopia.com' }}" class="link-apple" style="color:#475467;text-decoration:underline">
                {{ $soporte ?? 'soporte@pactopia.com' }}
              </a>
              <br><br>© {{ date('Y') }} Pactopia360 · Todos los derechos reservados
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
