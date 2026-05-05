<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Factura comercial Pactopia360</title>
<style>
@page { margin: 24px; }

body {
    font-family: DejaVu Sans, Arial, sans-serif;
    font-size: 11px;
    color: #172033;
    margin: 0;
    background: #ffffff;
}

.wrap { width: 100%; }

.header {
    background: #102a5c;
    color: #ffffff;
    padding: 22px 24px;
    border-radius: 16px;
}

.brand {
    font-size: 26px;
    font-weight: 800;
    letter-spacing: -0.5px;
}

.subtitle {
    margin-top: 4px;
    font-size: 12px;
    color: #dbeafe;
}

.badge {
    display: inline-block;
    margin-top: 12px;
    padding: 6px 10px;
    border-radius: 999px;
    background: #2563eb;
    color: #ffffff;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
}

.grid {
    width: 100%;
    margin-top: 16px;
}

.col {
    width: 49%;
    vertical-align: top;
}

.card {
    border: 1px solid #dbe4f0;
    border-radius: 14px;
    padding: 14px;
    margin-bottom: 12px;
}

.card-title {
    font-size: 10px;
    color: #64748b;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .06em;
    margin-bottom: 8px;
}

.line {
    margin-bottom: 5px;
}

.label {
    color: #64748b;
    font-weight: 700;
}

.value {
    color: #111827;
    font-weight: 700;
}

.table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 14px;
}

.table th {
    background: #eff6ff;
    color: #15356c;
    padding: 9px 8px;
    font-size: 10px;
    text-align: left;
    border-bottom: 1px solid #c7d7ee;
}

.table td {
    padding: 9px 8px;
    border-bottom: 1px solid #e5edf7;
    vertical-align: top;
}

.text-right { text-align: right; }
.text-center { text-align: center; }

.summary {
    width: 42%;
    margin-left: auto;
    margin-top: 16px;
    border: 1px solid #dbe4f0;
    border-radius: 14px;
    padding: 12px;
}

.summary-row {
    width: 100%;
    margin-bottom: 7px;
}

.summary-label {
    color: #64748b;
}

.summary-value {
    float: right;
    font-weight: 800;
    color: #0f172a;
}

.total-box {
    clear: both;
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid #dbe4f0;
    font-size: 18px;
    font-weight: 900;
    color: #102a5c;
    text-align: right;
}

.note {
    margin-top: 16px;
    padding: 12px 14px;
    border-radius: 14px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    color: #475569;
    line-height: 1.55;
}

.ppd {
    margin-top: 12px;
    padding: 12px 14px;
    border-radius: 14px;
    background: #fff7ed;
    border: 1px solid #fed7aa;
    color: #9a3412;
    line-height: 1.55;
}

.footer {
    margin-top: 24px;
    padding-top: 12px;
    border-top: 1px solid #e2e8f0;
    text-align: center;
    font-size: 9px;
    color: #64748b;
    line-height: 1.5;
}
</style>
</head>
<body>
@php
    $metodoPago = strtoupper(trim((string) ($invoice->metodo_pago ?? $billingData['metodo_pago'] ?? '')));
    $formaPago = strtoupper(trim((string) ($invoice->forma_pago ?? $billingData['forma_pago'] ?? '')));
    $usoCfdi = strtoupper(trim((string) ($invoice->uso_cfdi ?? $billingData['uso_cfdi'] ?? '')));
    $regimenFiscal = trim((string) ($invoice->regimen_fiscal ?? $billingData['regimen_fiscal'] ?? ''));

    $uuid = trim((string) ($invoice->cfdi_uuid ?? ''));
    $serie = trim((string) ($invoice->serie ?? 'A'));
    $folio = trim((string) ($invoice->folio ?? ('REQ-' . ($requestRow->id ?? $invoice->request_id ?? ''))));
    $issuedAt = $invoice->issued_at ?? now();

    $subtotal = 0.0;

    foreach ($items as $item) {
        $subtotal += (float) ($item->amount ?? 0);
    }

    $iva = round($subtotal * 0.16, 2);
    $grandTotal = round($subtotal + $iva, 2);

    if (($total ?? 0) > 0 && abs(((float) $total) - $grandTotal) > 0.01) {
        $grandTotal = (float) $total;
        $subtotal = round($grandTotal / 1.16, 2);
        $iva = round($grandTotal - $subtotal, 2);
    }

    $isPpd = $metodoPago === 'PPD';
@endphp

<div class="wrap">
    <div class="header">
        <div class="brand">Pactopia360</div>
        <div class="subtitle">Factura comercial personalizada · Documento complementario del CFDI</div>
        <div class="badge">{{ $isPpd ? 'Factura PPD con estado de cuenta' : 'Factura comercial' }}</div>
    </div>

    <table class="grid">
        <tr>
            <td class="col">
                <div class="card">
                    <div class="card-title">Cliente receptor</div>
                    <div class="line"><span class="label">Razón social:</span> <span class="value">{{ $invoice->razon_social ?? $billingData['razon_social'] ?? 'N/D' }}</span></div>
                    <div class="line"><span class="label">RFC:</span> <span class="value">{{ $invoice->rfc ?? $billingData['rfc'] ?? 'N/D' }}</span></div>
                    <div class="line"><span class="label">Régimen fiscal:</span> <span class="value">{{ $regimenFiscal !== '' ? $regimenFiscal : 'N/D' }}</span></div>
                    <div class="line"><span class="label">Uso CFDI:</span> <span class="value">{{ $usoCfdi !== '' ? $usoCfdi : 'N/D' }}</span></div>
                </div>
            </td>
            <td style="width:2%;"></td>
            <td class="col">
                <div class="card">
                    <div class="card-title">Datos del documento</div>
                    <div class="line"><span class="label">Serie/Folio:</span> <span class="value">{{ $serie }}{{ $folio !== '' ? ' - ' . $folio : '' }}</span></div>
                    <div class="line"><span class="label">UUID CFDI:</span> <span class="value">{{ $uuid !== '' ? $uuid : 'Pendiente / no disponible' }}</span></div>
                    <div class="line"><span class="label">Periodo:</span> <span class="value">{{ $period ?? 'N/D' }}</span></div>
                    <div class="line"><span class="label">Fecha emisión:</span> <span class="value">{{ \Illuminate\Support\Carbon::parse($issuedAt)->format('Y-m-d H:i') }}</span></div>
                </div>
            </td>
        </tr>
    </table>

    <div class="card">
        <div class="card-title">Condiciones fiscales</div>
        <div class="line"><span class="label">Método de pago:</span> <span class="value">{{ $metodoPago !== '' ? $metodoPago : 'N/D' }}</span></div>
        <div class="line"><span class="label">Forma de pago:</span> <span class="value">{{ $formaPago !== '' ? $formaPago : 'N/D' }}</span></div>
        <div class="line"><span class="label">Moneda:</span> <span class="value">MXN</span></div>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Concepto</th>
                <th class="text-center">Cantidad</th>
                <th class="text-right">Precio unitario</th>
                <th class="text-right">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
                <tr>
                    <td>{{ $item->description ?? 'Servicio Pactopia360' }}</td>
                    <td class="text-center">{{ number_format((float) ($item->qty ?? 1), 2) }}</td>
                    <td class="text-right">${{ number_format((float) ($item->unit_price ?? 0), 2) }}</td>
                    <td class="text-right">${{ number_format((float) ($item->amount ?? 0), 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="summary">
        <div class="summary-row">
            <span class="summary-label">Subtotal</span>
            <span class="summary-value">${{ number_format($subtotal, 2) }}</span>
        </div>
        <div class="summary-row">
            <span class="summary-label">IVA 16%</span>
            <span class="summary-value">${{ number_format($iva, 2) }}</span>
        </div>
        <div class="total-box">
            Total ${{ number_format($grandTotal, 2) }} MXN
        </div>
    </div>

    @if($isPpd)
        <div class="ppd">
            <strong>Modalidad PPD:</strong> Esta factura fue emitida con método de pago en parcialidades o diferido.
            El estado de cuenta del periodo acompaña este documento para soporte administrativo y seguimiento del saldo.
        </div>
    @endif

    <div class="note">
        Este PDF comercial es generado por Pactopia360 para acompañar el CFDI oficial y facilitar la revisión ejecutiva del cobro,
        conceptos facturados y relación con el estado de cuenta del cliente.
    </div>

    <div class="footer">
        Pactopia360 · Plataforma ERP, facturación, cumplimiento y gestión empresarial.<br>
        Documento informativo. La validez fiscal corresponde al CFDI XML/PDF timbrado por el PAC autorizado.
    </div>
</div>
</body>
</html>