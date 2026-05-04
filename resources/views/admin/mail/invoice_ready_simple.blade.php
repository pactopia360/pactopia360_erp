{{-- resources/views/admin/mail/invoice_ready_simple.blade.php --}}
@php
    $accName = '';

    if (isset($account)) {
        $accName = trim((string) ($account->razon_social ?? $account->name ?? ''));
    }

    if ($accName === '' && isset($req)) {
        $accName = trim((string) ($req->account_name ?? $req->razon_social ?? $req->name ?? ''));
    }

    $accName = $accName !== '' ? $accName : 'Cliente';

    $periodTxt = trim((string) ($period ?? ''));
    $portal = trim((string) ($portalUrl ?? ''));

    $hasPdf = (bool) ($hasPdf ?? false);
    $hasXml = (bool) ($hasXml ?? false);
    $hasStatementPdf = (bool) ($hasStatementPdf ?? false);

    $hasPactopiaPdf = (bool) ($hasPactopiaPdf ?? false);

    $metodoPago = strtoupper(trim((string) ($req->metodo_pago ?? $invoice->metodo_pago ?? '')));
    $isPpd = $metodoPago === 'PPD';
@endphp

<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Factura lista</title>
</head>
<body style="margin:0;padding:0;background:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#132238;">
    <div style="max-width:720px;margin:0 auto;padding:28px 18px;">
        <div style="background:linear-gradient(135deg,#15356c 0%,#234b92 55%,#5f90f6 100%);border-radius:20px;padding:26px 24px;color:#ffffff;">
            <div style="font-size:12px;letter-spacing:.08em;text-transform:uppercase;font-weight:700;opacity:.85;">PACTOPIA360</div>
            <h1 style="margin:10px 0 0;font-size:30px;line-height:1.05;">Factura lista</h1>
            <p style="margin:10px 0 0;font-size:14px;line-height:1.6;opacity:.92;">
                {{ $periodTxt !== '' ? $periodTxt : 'Documento fiscal disponible' }}
            </p>
        </div>

        <div style="background:#ffffff;border:1px solid #e3ebf5;border-radius:20px;padding:24px;margin-top:18px;">
            <p style="margin:0 0 12px;font-size:15px;line-height:1.7;">Hola <strong>{{ e($accName) }}</strong>,</p>

            <p style="margin:0 0 14px;font-size:15px;line-height:1.7;color:#334155;">
                Tu factura Pactopia360 ya está lista{{ $periodTxt !== '' ? ' para el período ' . e($periodTxt) : '' }}.
                @if($isPpd)
                    Esta factura fue emitida en modalidad <strong>PPD</strong> y se acompaña con el estado de cuenta correspondiente.
                @endif
            </p>

            <div style="border:1px solid #dbe7ff;background:#f7faff;border-radius:16px;padding:16px;margin:18px 0;">
                <div style="font-size:13px;font-weight:800;color:#15356c;margin-bottom:10px;">Archivos incluidos</div>

                <ul style="margin:0;padding-left:18px;font-size:14px;line-height:1.9;color:#334155;">
                    <li>PDF CFDI: <strong>{{ $hasPdf ? 'Adjunto' : 'No disponible' }}</strong></li>
                    <li>XML CFDI: <strong>{{ $hasXml ? 'Adjunto' : 'No disponible' }}</strong></li>
                    <li>Estado de cuenta PDF: <strong>{{ $hasStatementPdf ? 'Adjunto' : 'No incluido' }}</strong></li>
                    <li>PDF comercial Pactopia: <strong>{{ $hasPactopiaPdf ? 'Adjunto' : 'No incluido' }}</strong></li>
                </ul>
            </div>

            @if($portal !== '')
                <div style="margin:20px 0 8px;">
                    <a href="{{ $portal }}" style="display:inline-block;padding:12px 18px;border-radius:12px;background:#2f6df6;color:#ffffff;text-decoration:none;font-weight:700;">
                        Ir a Mi Cuenta
                    </a>
                </div>
            @endif

            <p style="margin:18px 0 0;font-size:13px;line-height:1.7;color:#64748b;">
                Este correo fue generado automáticamente por Pactopia360. Si necesitas ayuda, responde a este mensaje.
            </p>
        </div>
    </div>
</body>
</html>