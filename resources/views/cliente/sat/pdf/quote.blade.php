{{-- resources/views/cliente/sat/pdf/quote.blade.php
     P360 · SAT · PDF Cotización descarga (DomPDF-safe) --}}

<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Cotización SAT {{ $folio ?? '' }}</title>
  <style>
    @page { margin: 24px; }
    body{
      font-family: DejaVu Sans, Arial, sans-serif;
      font-size: 12px;
      color:#111827;
      margin:0; padding:0;
    }
    .mut{ color:#6b7280; }
    .b{ font-weight:800; }
    .sb{ font-weight:700; }
    .r{ text-align:right; }
    .c{ text-align:center; }

    .h1{ font-size:18px; font-weight:900; margin:0 0 6px; }
    .h2{ font-size:12px; font-weight:800; margin:0; color:#111827; }
    .row{ width:100%; }
    .card{
      border:1px solid #e5e7eb;
      border-radius:10px;
      padding:14px;
      margin-bottom:12px;
    }
    .grid{
      width:100%;
      border-collapse:collapse;
    }
    .grid td{
      padding:6px 8px;
      vertical-align:top;
    }

    table.lines{
      width:100%;
      border-collapse:collapse;
      margin-top:10px;
    }
    table.lines th, table.lines td{
      border:1px solid #e5e7eb;
      padding:8px;
    }
    table.lines th{
      background:#f3f4f6;
      font-weight:800;
      text-align:left;
    }

    .totals{
      width:100%;
      border-collapse:collapse;
      margin-top:10px;
    }
    .totals td{
      padding:6px 8px;
    }
    .totals .label{ color:#374151; }
    .totals .value{ font-weight:800; text-align:right; }
    .totals .grand{
      font-size:14px;
      font-weight:900;
      border-top:2px solid #111827;
      padding-top:10px;
    }
    .footer{
      margin-top:16px;
      font-size:10px;
      color:#6b7280;
    }
    .pill{
      display:inline-block;
      padding:3px 8px;
      border-radius:999px;
      border:1px solid #e5e7eb;
      background:#f9fafb;
      font-size:10px;
      font-weight:700;
      color:#111827;
    }
  </style>
</head>
<body>

  <div class="card">
    <table class="grid">
      <tr>
        <td style="width:65%;">
          <div class="h1">Cotización · Descarga SAT</div>
          <div class="mut">Folio: <span class="b">{{ $folio ?? '—' }}</span></div>
          <div class="mut">Generado: <span class="sb">{{ optional($generated_at)->format('Y-m-d H:i') ?? '' }}</span></div>
          <div class="mut">Válido hasta: <span class="sb">{{ optional($valid_until)->format('Y-m-d') ?? '' }}</span></div>
        </td>
        <td class="r" style="width:35%;">
          <div class="pill">Plan: {{ $plan ?? 'FREE' }}</div><br>
          <div class="mut" style="margin-top:6px;">Cuenta: <span class="sb">{{ $cuenta_id ?? '—' }}</span></div>
        </td>
      </tr>
    </table>
  </div>

  <div class="card">
    <div class="h2">Cliente</div>
    <div style="margin-top:6px;">
      <div><span class="mut">Razón social:</span> <span class="sb">{{ $empresa ?? '—' }}</span></div>
    </div>
  </div>

  <div class="card">
    <div class="h2">Detalle</div>

    <table class="lines">
      <thead>
        <tr>
          <th>Concepto</th>
          <th class="r">Cantidad</th>
          <th class="r">Precio</th>
          <th class="r">Importe</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>Descarga de CFDI (XML)</td>
          <td class="r">{{ number_format((int)($xml_count ?? 0)) }}</td>
          <td class="r">—</td>
          <td class="r">${{ number_format((float)($base ?? 0), 2) }}</td>
        </tr>
      </tbody>
    </table>

    <table class="totals">
      <tr>
        <td class="label r" style="width:75%;">Base</td>
        <td class="value" style="width:25%;">${{ number_format((float)($base ?? 0), 2) }}</td>
      </tr>

      <tr>
        <td class="label r">
          Descuento
          @if(!empty($discount_code))
            <span class="mut">({{ $discount_code }})</span>
          @endif
          <span class="mut">· {{ number_format((float)($discount_pct ?? 0), 2) }}%</span>
        </td>
        <td class="value">- ${{ number_format((float)($discount_amount ?? 0), 2) }}</td>
      </tr>

      <tr>
        <td class="label r">Subtotal</td>
        <td class="value">${{ number_format((float)($subtotal ?? 0), 2) }}</td>
      </tr>

      <tr>
        <td class="label r">IVA {{ (int)($iva_rate ?? 16) }}%</td>
        <td class="value">${{ number_format((float)($iva_amount ?? 0), 2) }}</td>
      </tr>

      <tr>
        <td class="label r grand">Total</td>
        <td class="value grand">${{ number_format((float)($total ?? 0), 2) }}</td>
      </tr>
    </table>

    <div class="footer">
      <div><span class="sb">Nota:</span> {{ $note ?? '—' }}</div>
      <div style="margin-top:6px;">Este documento es una cotización informativa. Los precios pueden variar según validaciones y disponibilidad.</div>
    </div>
  </div>

</body>
</html>
