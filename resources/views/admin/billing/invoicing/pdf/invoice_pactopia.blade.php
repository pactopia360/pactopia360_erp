<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
body {
    font-family: DejaVu Sans, Arial, sans-serif;
    font-size: 12px;
    color: #1e293b;
    margin: 0;
}

.header {
    background: linear-gradient(135deg, #1e3a8a, #2563eb);
    color: #fff;
    padding: 20px;
}

.header h1 {
    margin: 0;
    font-size: 22px;
}

.section {
    padding: 20px;
}

.card {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 10px;
}

.table {
    width: 100%;
    border-collapse: collapse;
}

.table th {
    background: #f1f5f9;
    padding: 8px;
    font-weight: bold;
    text-align: left;
}

.table td {
    padding: 8px;
    border-bottom: 1px solid #e2e8f0;
}

.total {
    text-align: right;
    font-size: 16px;
    font-weight: bold;
    padding-top: 10px;
}

.footer {
    text-align: center;
    font-size: 10px;
    color: #64748b;
    margin-top: 20px;
}
</style>
</head>
<body>

<div class="header">
    <h1>Pactopia360</h1>
    <div>Factura comercial</div>
</div>

<div class="section">

    <div class="card">
        <strong>Cliente:</strong> {{ $invoice->razon_social ?? 'N/D' }}<br>
        <strong>RFC:</strong> {{ $invoice->rfc ?? 'N/D' }}<br>
        <strong>Periodo:</strong> {{ $period ?? 'N/D' }}
    </div>

    <div class="card">
        <strong>Método de pago:</strong> {{ $invoice->metodo_pago ?? 'N/D' }}<br>
        <strong>Forma de pago:</strong> {{ $invoice->forma_pago ?? 'N/D' }}<br>
        <strong>Uso CFDI:</strong> {{ $invoice->uso_cfdi ?? 'N/D' }}
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Concepto</th>
                <th>Cantidad</th>
                <th>Precio</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td>{{ number_format($item->qty, 2) }}</td>
                    <td>${{ number_format($item->unit_price, 2) }}</td>
                    <td>${{ number_format($item->amount, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total">
        Total: ${{ number_format($total ?? 0, 2) }} MXN
    </div>

</div>

<div class="footer">
    Documento generado por Pactopia360<br>
    Este documento es informativo y acompaña al CFDI oficial.
</div>

</body>
</html>