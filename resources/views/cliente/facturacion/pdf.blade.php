<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>CFDI {{ $cfdi->serie }}-{{ $cfdi->folio }}</title>
    <style>
        body{font-family:Arial,sans-serif;margin:0;background:#f3f6fb;color:#10233f}
        .page{max-width:900px;margin:24px auto;background:#fff;border-radius:20px;overflow:hidden;border:1px solid #e3ebf7}
        .head{padding:24px;background:linear-gradient(135deg,{{ $brand['secondary'] }},{{ $brand['primary'] }});color:#fff}
        .top{display:flex;justify-content:space-between;gap:16px;align-items:flex-start}
        .logo{max-height:58px;max-width:180px;background:#fff;border-radius:12px;padding:8px}
        h1{margin:12px 0 4px;font-size:28px}
        .uuid{font-size:11px;word-break:break-all;opacity:.9}
        .body{padding:24px}
        .grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:18px}
        .card{border:1px solid #e3ebf7;border-radius:14px;padding:14px;background:#fbfdff}
        .label{font-size:10px;text-transform:uppercase;color:#71829a;font-weight:900;letter-spacing:.08em}
        .value{font-size:14px;font-weight:800;margin-top:4px}
        table{width:100%;border-collapse:collapse;margin-top:14px}
        th{background:#f5f8fd;color:#58708f;font-size:10px;text-transform:uppercase;text-align:left;padding:10px}
        td{border-bottom:1px solid #eef3fa;padding:10px;font-size:12px}
        .right{text-align:right}
        .totals{margin-top:18px;margin-left:auto;width:280px}
        .totals div{display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid #eef3fa;font-weight:800}
        .total{font-size:18px;color:{{ $brand['primary'] }}}
        .note{margin-top:18px;padding:12px;border-radius:12px;background:#fff7ed;color:#9a3412;font-size:12px;font-weight:800}
        @media print{body{background:#fff}.page{margin:0;border:0;border-radius:0}.note{display:none}}
    </style>
</head>
<body>
<div class="page">
    <div class="head">
        <div class="top">
            <div>
                <div>CFDI · Facturación 360</div>
                <h1>Factura {{ $cfdi->serie ?: 'S' }}-{{ $cfdi->folio ?: $cfdi->id }}</h1>
                <div class="uuid">UUID: {{ $cfdi->uuid ?: 'Pendiente' }}</div>
            </div>

            @if(!empty($brand['logo_url']))
                <img class="logo" src="{{ $brand['logo_url'] }}" alt="Logo">
            @else
                <strong>{{ $brand['nombre'] }}</strong>
            @endif
        </div>
    </div>

    <div class="body">
        <div class="grid">
            <div class="card">
                <div class="label">Emisor</div>
                <div class="value">{{ $cfdi->emisor_razon_social ?? $cfdi->emisor_nombre ?? '—' }}</div>
                <div>{{ $cfdi->emisor_rfc ?? '—' }}</div>
            </div>

            <div class="card">
                <div class="label">Receptor</div>
                <div class="value">{{ optional($cfdi->receptor)->razon_social ?? optional($cfdi->receptor)->nombre_comercial ?? '—' }}</div>
                <div>{{ optional($cfdi->receptor)->rfc ?? '—' }}</div>
            </div>
        </div>

        <table>
            <thead>
            <tr>
                <th>Descripción</th>
                <th class="right">Cantidad</th>
                <th class="right">Precio</th>
                <th class="right">Importe</th>
            </tr>
            </thead>
            <tbody>
            @foreach($cfdi->conceptos ?? [] as $concepto)
                <tr>
                    <td>{{ $concepto->descripcion }}</td>
                    <td class="right">{{ number_format((float) $concepto->cantidad, 2) }}</td>
                    <td class="right">${{ number_format((float) $concepto->precio_unitario, 2) }}</td>
                    <td class="right">${{ number_format((float) ($concepto->subtotal ?? $concepto->total ?? 0), 2) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <div class="totals">
            <div><span>Subtotal</span><span>${{ number_format((float) $cfdi->subtotal, 2) }}</span></div>
            <div><span>IVA</span><span>${{ number_format((float) $cfdi->iva, 2) }}</span></div>
            <div class="total"><span>Total</span><span>${{ number_format((float) $cfdi->total, 2) }}</span></div>
        </div>

        @if(!empty($isFallbackPdf))
            <div class="note">
                Vista imprimible temporal. Cuando conectemos el timbrado real del PAC, aquí se mostrará el PDF oficial generado desde XML timbrado.
            </div>
        @endif
    </div>
</div>
<script>window.print();</script>
</body>
</html>