{{-- C:\wamp64\www\pactopia360_erp\resources\views\emails\admin\cliente_credentials.blade.php --}}
@php
  $brandName = (string) data_get($p, 'brand.name', 'Pactopia360');

  $logoDarkUrl  = (string) data_get($p, 'brand.logo_dark_url', '');
  $logoLightUrl = (string) data_get($p, 'brand.logo_light_url', '');

  if ($logoDarkUrl === '') {
      $logoDarkUrl = asset('assets/admin/img/Pactopia - Letra AZUL.png');
  }

  if ($logoLightUrl === '') {
      $logoLightUrl = asset('assets/admin/img/Pactopia - Letra Blanca.png');
  }

  $brandBlue    = '#4D8EED';
  $brandBlue2   = '#3F74DA';
  $brandBlue3   = '#2D358E';
  $brandBlue4   = '#1F56C5';
  $brandNavy    = '#102447';
  $brandSoftBg  = '#EEF4FF';
  $brandSoftBd  = '#D9E6FF';
  $brandText    = '#0F172A';
  $brandMuted   = '#64748B';
  $brandCard    = '#FFFFFF';
  $brandPage    = '#F5F8FF';

  $supportEmail = (string) data_get($p, 'brand.support_email', 'notificaciones@pactopia360.com');

  $rfc       = (string) data_get($p, 'account.rfc', '');
  $rs        = (string) data_get($p, 'account.razon_social', 'Cliente');
  $usuario   = (string) data_get($p, 'credentials.usuario', '');
  $password  = (string) data_get($p, 'credentials.password', '');
  $accessUrl = (string) data_get($p, 'credentials.access_url', '');

  $sentAt = (string) data_get($p, 'meta.sent_at', now()->toDateTimeString());
@endphp

<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta http-equiv="x-ua-compatible" content="ie=edge">
  <title>Credenciales de acceso</title>
</head>
<body style="margin:0;padding:0;background:{{ $brandPage }};font-family:Arial,Helvetica,sans-serif;color:{{ $brandText }};-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">
  <div style="display:none!important;visibility:hidden;opacity:0;color:transparent;height:0;width:0;overflow:hidden;mso-hide:all;">
    Tus credenciales de acceso para {{ $rs }} ya están listas.
  </div>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:0;padding:0;background:{{ $brandPage }};border-collapse:collapse;">
    <tr>
      <td align="center" style="padding:30px 12px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:700px;margin:0 auto;border-collapse:collapse;">

          <tr>
            <td style="padding:0;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;background:{{ $brandCard }};border:1px solid #dbe5f1;border-radius:24px;overflow:hidden;box-shadow:0 14px 34px rgba(15,23,42,.08);border-collapse:collapse;">
                <tr>
                  <td style="padding:0;">

                    {{-- HERO CLARO --}}
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;background:
                      radial-gradient(circle at left top, rgba(77,142,237,.28) 0%, rgba(77,142,237,0) 38%),
                      linear-gradient(135deg, #f4f8ff 0%, #dfeaff 46%, #c8dafd 100%);
                      border-collapse:collapse;">
                      <tr>
                        <td style="padding:22px 24px 20px 24px;">
                          <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="border-collapse:collapse;">
                            <tr>
                              <td style="vertical-align:middle;">
                                @if($logoDarkUrl !== '')
                                  <img src="{{ $logoDarkUrl }}" alt="{{ $brandName }}" style="height:34px;display:block;max-width:220px;">
                                @else
                                  <div style="color:{{ $brandBlue3 }};font-weight:900;font-size:20px;line-height:1.2;">{{ $brandName }}</div>
                                @endif
                              </td>
                              <td align="right" style="vertical-align:middle;">
                                <span style="display:inline-block;background:#ffffff;color:{{ $brandBlue3 }};border:1px solid {{ $brandSoftBd }};padding:8px 12px;border-radius:999px;font-size:12px;font-weight:800;">
                                  Acceso listo
                                </span>
                              </td>
                            </tr>
                          </table>

                          <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-top:18px;border-collapse:collapse;">
                            <tr>
                              <td width="56%" style="vertical-align:top;padding-right:18px;">
                                <div style="font-size:12px;line-height:1.3;color:{{ $brandBlue3 }};font-weight:900;letter-spacing:.10em;text-transform:uppercase;">
                                  Portal usuario
                                </div>

                                <div style="margin-top:10px;font-size:28px;line-height:1.08;color:{{ $brandText }};font-weight:900;">
                                  Tus accesos están listos
                                </div>

                                <div style="margin-top:12px;font-size:14px;line-height:1.8;color:#47607d;font-weight:700;">
                                  Ingresa a tu portal con tus credenciales temporales y actualiza tu contraseña después del primer acceso.
                                </div>

                                <div style="margin-top:14px;font-size:13px;line-height:1.7;color:#47607d;font-weight:700;">
                                  Cuenta:
                                  <strong style="color:{{ $brandText }};">{{ $rs }}</strong>
                                  @if($rfc !== '')
                                    <span style="color:{{ $brandBlue3 }};">({{ $rfc }})</span>
                                  @endif
                                </div>
                              </td>

                              <td width="44%" style="vertical-align:top;">
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:rgba(255,255,255,.62);border:1px solid rgba(217,230,255,.95);border-radius:18px;border-collapse:collapse;backdrop-filter:blur(3px);">
                                  <tr>
                                    <td style="padding:16px;">
                                      <div style="font-size:11px;line-height:1.3;color:{{ $brandBlue3 }};font-weight:900;letter-spacing:.08em;text-transform:uppercase;">
                                        Resumen
                                      </div>

                                      <div style="margin-top:10px;font-size:13px;line-height:1.7;color:#47607d;font-weight:700;">
                                        Usuario:
                                        <strong style="color:{{ $brandText }};">{{ $usuario !== '' ? $usuario : '—' }}</strong>
                                      </div>

                                      <div style="margin-top:6px;font-size:13px;line-height:1.7;color:#47607d;font-weight:700;">
                                        Contraseña temporal:
                                        <strong style="color:{{ $brandText }};">{{ $password !== '' ? $password : '—' }}</strong>
                                      </div>

                                      <div style="margin-top:6px;font-size:13px;line-height:1.7;color:#47607d;font-weight:700;">
                                        Soporte:
                                        <strong style="color:{{ $brandBlue3 }};">{{ $supportEmail }}</strong>
                                      </div>
                                    </td>
                                  </tr>
                                </table>
                              </td>
                            </tr>
                          </table>
                        </td>
                      </tr>
                    </table>

                    {{-- CONTENIDO --}}
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;background:#ffffff;border-collapse:collapse;">
                      <tr>
                        <td style="padding:22px 24px 24px 24px;">

                          <h1 style="margin:0 0 8px;font-size:22px;line-height:1.2;color:{{ $brandText }};">
                            Tus credenciales de acceso
                          </h1>

                          <div style="color:#475569;font-size:13px;line-height:1.8;">
                            Conserva este correo. Si requieres soporte, responde a tu asesor o escribe a
                            <span style="color:{{ $brandBlue3 }};font-weight:700;">{{ $supportEmail }}</span>.
                          </div>

                          <div style="margin-top:16px;border-radius:16px;border:1px solid {{ $brandSoftBd }};background:{{ $brandSoftBg }};padding:16px;">
                            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="font-size:13px;border-collapse:collapse;">
                              <tr>
                                <td style="width:155px;color:{{ $brandMuted }};padding:10px 0;">Usuario</td>
                                <td style="padding:10px 0;color:{{ $brandText }};font-weight:800;">{{ $usuario !== '' ? $usuario : '—' }}</td>
                              </tr>
                              <tr>
                                <td style="color:{{ $brandMuted }};padding:10px 0;border-top:1px solid {{ $brandSoftBd }};">Contraseña</td>
                                <td style="padding:10px 0;border-top:1px solid {{ $brandSoftBd }};color:{{ $brandText }};font-weight:800;">{{ $password !== '' ? $password : '—' }}</td>
                              </tr>
                              <tr>
                                <td style="color:{{ $brandMuted }};padding:10px 0;border-top:1px solid {{ $brandSoftBd }};">Acceso</td>
                                <td style="padding:10px 0;border-top:1px solid {{ $brandSoftBd }};color:{{ $brandText }};font-weight:700;">
                                  @if($accessUrl !== '')
                                    <a href="{{ $accessUrl }}" style="color:{{ $brandBlue3 }};text-decoration:none;font-weight:800;">Abrir portal</a>
                                    <div style="margin-top:6px;color:{{ $brandMuted }};font-size:12px;line-height:1.6;word-break:break-all;font-weight:400;">{{ $accessUrl }}</div>
                                  @else
                                    —
                                  @endif
                                </td>
                              </tr>
                            </table>
                          </div>

                          @if($accessUrl !== '')
                            <div style="margin-top:18px;text-align:center;">
                              <a href="{{ $accessUrl }}"
                                style="display:inline-block;min-width:420px;max-width:100%;box-sizing:border-box;background:linear-gradient(90deg,#ff476a 0%,#ff7a59 100%);color:#ffffff;text-decoration:none;padding:16px 24px;border-radius:16px;font-weight:900;font-size:16px;line-height:1.2;text-align:center;box-shadow:0 10px 24px rgba(255,101,92,.24);">
                                Entrar
                              </a>
                            </div>
                          @endif

                          <div style="margin-top:18px;color:{{ $brandMuted }};font-size:12px;line-height:1.75;">
                            Recomendación: cambia tu contraseña después del primer acceso.
                          </div>

                          <div style="margin-top:6px;color:{{ $brandMuted }};font-size:12px;line-height:1.75;">
                            Generado: <strong style="color:{{ $brandText }};">{{ $sentAt }}</strong>
                          </div>

                        </td>
                      </tr>
                    </table>

                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <tr>
            <td align="center" style="padding:14px 12px 0 12px;">
              @if($logoDarkUrl !== '')
                <img src="{{ $logoDarkUrl }}" alt="{{ $brandName }}" style="height:22px;display:block;max-width:180px;margin:0 auto 8px auto;">
              @endif
              <div style="font-size:12px;line-height:1.7;color:#94a3b8;font-weight:700;">
                © {{ date('Y') }} {{ $brandName }}. Todos los derechos reservados.
              </div>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>