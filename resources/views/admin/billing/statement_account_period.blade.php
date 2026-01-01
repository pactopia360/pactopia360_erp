{{-- resources/views/emails/admin/billing/statement_account_period.blade.php (v1.0 · Diseño + Tracking) --}}
@php
  $acc = $account ?? null;
  $accName = trim((string)(($acc->razon_social ?? '') ?: ($acc->name ?? '') ?: ($acc->email ?? 'Cliente')));
  $periodTxt = (string)($period_label ?? $period ?? '');
  $saldo = (float)($total ?? 0);
  $hasSaldo = $saldo > 0.00001;
@endphp
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>{{ (string)($subject ?? 'Pactopia360 · Estado de cuenta') }}</title>
</head>
<body style="margin:0;background:#f6f7fb;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;">
  {{-- Open pixel --}}
  @if(!empty($open_pixel_url))
    <img src="{{ $open_pixel_url }}" width="1" height="1" alt="" style="display:block;border:0;outline:none;">
  @endif

  <div style="max-width:720px;margin:0 auto;padding:18px;">
    <div style="background:#0f172a;border-radius:18px;padding:18px;color:#fff;">
      <div style="font-size:12px;opacity:.85;font-weight:700;letter-spacing:.04em;text-transform:uppercase;">Pactopia360</div>
      <div style="margin-top:8px;font-size:20px;font-weight:900;">Estado de cuenta · {{ $periodTxt }}</div>
      <div style="margin-top:6px;font-size:13px;opacity:.9;font-weight:700;">{{ $accName }}</div>
    </div>

    <div style="background:#fff;border-radius:18px;margin-top:14px;border:1px solid #e7e9f2;overflow:hidden;">
      <div style="padding:16px;border-bottom:1px solid #eef0f6;">
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;">
          <div>
            <div style="font-size:12px;color:#64748b;font-weight:800;text-transform:uppercase;letter-spacing:.04em;">Resumen</div>
            <div style="margin-top:6px;font-size:14px;color:#0f172a;font-weight:900;">
              Periodo: {{ (string)($period ?? '') }} · Tarifa: {{ (string)($tarifa_label ?? '—') }}
            </div>
          </div>
          <div style="text-align:right;">
            <div style="font-size:12px;color:#64748b;font-weight:800;">Saldo pendiente</div>
            <div style="margin-top:6px;font-size:18px;font-weight:950;color:#0f172a;">
              ${{ number_format($saldo,2) }} MXN
            </div>
          </div>
        </div>

        @if($hasSaldo)
          <div style="margin-top:12px;padding:12px;border-radius:14px;background:#fff7ed;border:1px solid #fed7aa;">
            <div style="font-weight:900;color:#9a3412;">Tienes saldo pendiente.</div>
            <div style="margin-top:6px;color:#7c2d12;font-weight:700;font-size:13px;">
              Puedes pagar en línea con tarjeta desde el botón:
            </div>
            @if(!empty($pay_track_url))
              <div style="margin-top:10px;">
                <a href="{{ $pay_track_url }}"
                   style="display:inline-block;background:#0f172a;color:#fff;text-decoration:none;font-weight:900;padding:12px 14px;border-radius:12px;">
                   Pagar ahora
                </a>
              </div>
            @endif
          </div>
        @else
          <div style="margin-top:12px;padding:12px;border-radius:14px;background:#ecfeff;border:1px solid #a5f3fc;">
            <div style="font-weight:900;color:#155e75;">Tu estado de cuenta está al corriente.</div>
          </div>
        @endif
      </div>

      <div style="padding:16px;">
        <div style="font-size:12px;color:#64748b;font-weight:900;text-transform:uppercase;letter-spacing:.04em;">Movimientos</div>
        <div style="margin-top:10px;border:1px solid #eef0f6;border-radius:14px;overflow:hidden;">
          <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
            <thead>
              <tr style="background:#f8fafc;">
                <th align="left" style="padding:10px 12px;font-size:12px;color:#64748b;font-weight:900;">Concepto</th>
                <th align="left" style="padding:10px 12px;font-size:12px;color:#64748b;font-weight:900;">Detalle</th>
                <th align="right" style="padding:10px 12px;font-size:12px;color:#64748b;font-weight:900;">Cargo</th>
                <th align="right" style="padding:10px 12px;font-size:12px;color:#64748b;font-weight:900;">Abono</th>
              </tr>
            </thead>
            <tbody>
              @forelse(($items ?? []) as $it)
                <tr>
                  <td style="padding:10px 12px;border-top:1px solid #eef0f6;font-weight:900;color:#0f172a;">
                    {{ $it->concepto ?? '—' }}
                  </td>
                  <td style="padding:10px 12px;border-top:1px solid #eef0f6;color:#475569;font-weight:700;">
                    {{ $it->detalle ?? '—' }}
                  </td>
                  <td align="right" style="padding:10px 12px;border-top:1px solid #eef0f6;font-weight:900;color:#0f172a;">
                    ${{ number_format((float)($it->cargo ?? 0),2) }}
                  </td>
                  <td align="right" style="padding:10px 12px;border-top:1px solid #eef0f6;font-weight:900;color:#0f172a;">
                    ${{ number_format((float)($it->abono ?? 0),2) }}
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="4" style="padding:12px;border-top:1px solid #eef0f6;color:#64748b;font-weight:800;">
                    Sin movimientos registrados en este periodo.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        <div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap;">
          @if(!empty($pdf_track_url))
            <a href="{{ $pdf_track_url }}" style="display:inline-block;border:1px solid #e7e9f2;color:#0f172a;text-decoration:none;font-weight:900;padding:10px 12px;border-radius:12px;background:#fff;">
              Ver PDF
            </a>
          @endif
          @if(!empty($portal_track_url))
            <a href="{{ $portal_track_url }}" style="display:inline-block;border:1px solid #e7e9f2;color:#0f172a;text-decoration:none;font-weight:900;padding:10px 12px;border-radius:12px;background:#fff;">
              Ir al portal
            </a>
          @endif
        </div>

        <div style="margin-top:14px;color:#64748b;font-size:12px;font-weight:700;">
          Generado: {{ (string)($generated_at ?? now()) }} · ID tracking: {{ (string)($email_id ?? '') }}
        </div>
      </div>
    </div>

    <div style="margin-top:12px;color:#94a3b8;font-size:12px;font-weight:700;text-align:center;">
      © {{ date('Y') }} Pactopia360
    </div>
  </div>
</body>
</html>
