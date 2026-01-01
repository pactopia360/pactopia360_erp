<!doctype html>
<html lang="es">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Estado de cuenta</title>
  </head>
  <body style="font-family:Arial,Helvetica,sans-serif; background:#f6f7f9; margin:0; padding:20px;">
    @php
      $snap = (array)($st->snapshot ?? []);
      $acc  = (array)($snap['account'] ?? []);
      $lic  = (array)($snap['license'] ?? []);
      $name = trim(($acc['razon_social'] ?? '') ?: (($acc['nombre_comercial'] ?? '') ?: ($acc['email'] ?? 'Cliente')));
    @endphp

    <div style="max-width:720px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;">
      <h2 style="margin:0 0 6px;">Estado de cuenta · {{ $st->period }}</h2>
      <div style="color:#6b7280;font-size:13px;margin-bottom:14px;">
        {{ $name }} — RFC {{ $acc['rfc'] ?? '—' }}
      </div>

      <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:14px;">
        <div style="padding:10px 12px;border:1px solid #e5e7eb;border-radius:10px;">
          <div style="color:#6b7280;font-size:12px;font-weight:700;">Cargo</div>
          <div style="font-size:18px;font-weight:800;">${{ number_format((float)$st->total_cargo, 2) }}</div>
        </div>
        <div style="padding:10px 12px;border:1px solid #e5e7eb;border-radius:10px;">
          <div style="color:#6b7280;font-size:12px;font-weight:700;">Abono</div>
          <div style="font-size:18px;font-weight:800;">${{ number_format((float)$st->total_abono, 2) }}</div>
        </div>
        <div style="padding:10px 12px;border:1px solid #e5e7eb;border-radius:10px;">
          <div style="color:#6b7280;font-size:12px;font-weight:700;">Saldo</div>
          <div style="font-size:18px;font-weight:800;">${{ number_format((float)$st->saldo, 2) }}</div>
        </div>
      </div>

      <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
        <thead>
          <tr>
            <th align="left" style="border-bottom:1px solid #e5e7eb;padding:8px 6px;font-size:12px;color:#6b7280;">Concepto</th>
            <th align="right" style="border-bottom:1px solid #e5e7eb;padding:8px 6px;font-size:12px;color:#6b7280;">Importe</th>
          </tr>
        </thead>
        <tbody>
          @foreach($st->items as $it)
            <tr>
              <td style="border-bottom:1px solid #f1f5f9;padding:8px 6px;font-size:13px;">
                {{ $it->description }}
              </td>
              <td align="right" style="border-bottom:1px solid #f1f5f9;padding:8px 6px;font-size:13px;font-weight:700;">
                ${{ number_format((float)$it->amount, 2) }}
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>

      <div style="margin-top:14px;color:#6b7280;font-size:12px;">
        Vence: {{ $st->due_date ? $st->due_date->format('Y-m-d') : '—' }} · Estatus: {{ strtoupper($st->status) }}
      </div>
    </div>
  </body>
</html>
