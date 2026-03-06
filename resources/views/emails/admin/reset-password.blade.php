<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Restablecer contraseña</title>
</head>
<body style="margin:0;padding:0;background:#f5f7fb;font-family:Arial,Helvetica,sans-serif;color:#111827;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f5f7fb;padding:32px 16px;">
        <tr>
            <td align="center">
                <table width="620" cellpadding="0" cellspacing="0" border="0" style="max-width:620px;width:100%;background:#ffffff;border-radius:20px;overflow:hidden;box-shadow:0 10px 30px rgba(15,23,42,.08);">
                    <tr>
                        <td style="background:linear-gradient(135deg,#0f172a 0%, #111827 35%, #dc2626 100%);padding:28px 32px;color:#ffffff;">
                            <div style="font-size:13px;letter-spacing:.12em;text-transform:uppercase;opacity:.85;">Pactopia360 · Admin</div>
                            <h1 style="margin:10px 0 0;font-size:28px;line-height:1.2;">Recuperación de contraseña</h1>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 16px;font-size:16px;line-height:1.7;color:#374151;">
                                Recibimos una solicitud para restablecer la contraseña de tu cuenta administrativa.
                            </p>

                            <p style="margin:0 0 16px;font-size:16px;line-height:1.7;color:#374151;">
                                Cuenta: <strong>{{ $email }}</strong>
                            </p>

                            <p style="margin:0 0 24px;font-size:16px;line-height:1.7;color:#374151;">
                                Este enlace estará disponible durante <strong>{{ $expiresMin }} minutos</strong>.
                            </p>

                            <p style="margin:0 0 28px;">
                                <a href="{{ $resetUrl }}" style="display:inline-block;background:#dc2626;color:#ffffff;text-decoration:none;font-weight:700;padding:14px 24px;border-radius:12px;">
                                    Restablecer contraseña
                                </a>
                            </p>

                            <p style="margin:0 0 12px;font-size:14px;line-height:1.7;color:#6b7280;">
                                Si el botón no abre directamente, copia y pega este enlace en tu navegador:
                            </p>

                            <p style="margin:0 0 24px;font-size:13px;line-height:1.7;word-break:break-all;color:#2563eb;">
                                {{ $resetUrl }}
                            </p>

                            <p style="margin:0;font-size:14px;line-height:1.7;color:#6b7280;">
                                Si tú no solicitaste este cambio, puedes ignorar este correo sin realizar ninguna acción.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:20px 32px;background:#f8fafc;color:#6b7280;font-size:12px;">
                            {{ $appName }} · Seguridad de acceso administrativo
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>