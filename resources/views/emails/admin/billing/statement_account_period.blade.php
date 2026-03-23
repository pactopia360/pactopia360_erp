{{-- resources/views/emails/admin/billing/statement_account_period.blade.php (v1.0 · Diseño + Tracking) --}}
@php
  $acc = $account ?? null;

  $accName = trim((string)(($acc->razon_social ?? '') ?: ($acc->name ?? '') ?: ($acc->email ?? 'Cliente')));
  $periodTxt = (string)($period_label ?? $period ?? '');

  $cargoPeriodo = (float)($cargo ?? 0);
  $abonoTotal   = (float)($abono ?? 0);
  $abonoEdo     = (float)($abono_edo ?? 0);
  $abonoPay     = (float)($abono_pay ?? 0);

  $saldoPeriodo = (float)($current_period_due ?? $saldo ?? 0);
  $saldoAnterior = (float)($prev_balance ?? 0);
  $saldoTotal = (float)($total_due ?? $total ?? max(0, $saldoPeriodo + $saldoAnterior));

  $expectedTotal = (float)($expected_total ?? 0);
  $consumosTotal = (float)($consumos_total ?? $cargoPeriodo);

  $hasSaldo = $saldoTotal > 0.00001;

  $statusRaw = strtolower((string)($status_pago ?? ($hasSaldo ? 'pendiente' : 'pagado')));
  if (in_array($statusRaw, ['paid','succeeded','success','completed'], true)) {
      $statusRaw = 'pagado';
  }

  $statusOverride = strtolower((string)($status_override ?? ''));
  $isOverride = $statusOverride !== '';

  $statusLbl = $statusRaw === 'pagado' ? 'PAGADO'
            : ($statusRaw === 'parcial' ? 'PARCIAL'
            : ($statusRaw === 'vencido' ? 'VENCIDO'
            : ($statusRaw === 'sin_mov' ? 'SIN MOV' : 'PENDIENTE')));

  $tarifaLabel = (string)($tarifa_label ?? '—');

  $payMethodUi   = (string)($pay_method ?? '');
  $payProviderUi = (string)($pay_provider ?? '');
  $payStatusUi   = (string)($pay_status ?? '');
  $lastPaidUi    = (string)($last_paid ?? '');
  $payAllowedUi  = (string)($pay_allowed ?? '');

  $fmtMoney = fn($n) => '$' . number_format((float)$n, 2) . ' MXN';
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
              Periodo: {{ (string)($period ?? '') }} · Tarifa: {{ $tarifaLabel }} · Estado: {{ $statusLbl }}
            </div>
            @if($isOverride)
              <div style="margin-top:6px;font-size:12px;color:#9a3412;font-weight:900;">
                Estado ajustado manualmente por override.
              </div>
            @endif
          </div>

          <div style="text-align:right;">
            <div style="font-size:12px;color:#64748b;font-weight:800;">Total a pagar</div>
            <div style="margin-top:6px;font-size:18px;font-weight:950;color:#0f172a;">
              {{ $fmtMoney($saldoTotal) }}
            </div>
          </div>
        </div>

        <div style="margin-top:14px;border:1px solid #eef0f6;border-radius:14px;overflow:hidden;">
          <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
            <tbody>
              <tr>
                <td style="padding:10px 12px;border-top:1px solid #eef0f6;color:#64748b;font-weight:800;">Cargo del periodo</td>
                <td align="right" style="padding:10px 12px;border-top:1px solid #eef0f6;color:#0f172a;font-weight:900;">{{ $fmtMoney($cargoPeriodo) }}</td>
              </tr>
              <tr>
                <td style="padding:10px 12px;border-top:1px solid #eef0f6;color:#64748b;font-weight:800;">Abonos aplicados</td>
                <td align="right" style="padding:10px 12px;border-top:1px solid #eef0f6;color:#0f172a;font-weight:900;">{{ $fmtMoney($abonoTotal) }}</td>
              </tr>
              <tr>
                <td style="padding:10px 12px;border-top:1px solid #eef0f6;color:#64748b;font-weight:800;">Saldo del periodo</td>
                <td align="right" style="padding:10px 12px;border-top:1px solid #eef0f6;color:#0f172a;font-weight:900;">{{ $fmtMoney($saldoPeriodo) }}</td>
              </tr>
              <tr>
                <td style="padding:10px 12px;border-top:1px solid #eef0f6;color:#64748b;font-weight:800;">Saldo anterior</td>
                <td align="right" style="padding:10px 12px;border-top:1px solid #eef0f6;color:#0f172a;font-weight:900;">{{ $fmtMoney($saldoAnterior) }}</td>
              </tr>
              <tr style="background:#f8fafc;">
                <td style="padding:10px 12px;border-top:1px solid #eef0f6;color:#0f172a;font-weight:900;">Total a pagar</td>
                <td align="right" style="padding:10px 12px;border-top:1px solid #eef0f6;color:#0f172a;font-weight:950;">{{ $fmtMoney($saldoTotal) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div style="margin-top:12px;font-size:12px;color:#64748b;font-weight:700;line-height:1.6;">
          @if($lastPaidUi !== '')
            Último periodo pagado: <b>{{ $lastPaidUi }}</b><br>
          @endif
          @if($payAllowedUi !== '')
            Siguiente periodo permitido: <b>{{ $payAllowedUi }}</b><br>
          @endif
          @if($payMethodUi !== '' || $payProviderUi !== '' || $payStatusUi !== '')
            Método: <b>{{ $payMethodUi ?: '—' }}</b> · Proveedor: <b>{{ $payProviderUi ?: '—' }}</b> · Estado UI: <b>{{ $payStatusUi ?: '—' }}</b>
          @endif
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
            <div style="margin-top:6px;color:#0f766e;font-weight:700;font-size:13px;">
              No hay saldo pendiente por pagar en este momento.
            </div>
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
              @forelse((is_iterable($items ?? null) ? $items : []) as $it)
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

        <div style="margin-top:14px;color:#64748b;font-size:12px;font-weight:700;line-height:1.6;">
          Generado: {{ (string)($generated_at ?? now()) }}
          @if(!empty($email_id))
            · ID tracking: {{ (string)($email_id ?? '') }}
          @endif
          <br>
          Cargo periodo: {{ $fmtMoney($cargoPeriodo) }} · Abonos: {{ $fmtMoney($abonoTotal) }} · Total final: {{ $fmtMoney($saldoTotal) }}
        </div>
      </div>
    </div>

    <div style="margin-top:12px;color:#94a3b8;font-size:12px;font-weight:700;text-align:center;">
      © {{ date('Y') }} Pactopia360
    </div>
  </div>
</body>
</html>
