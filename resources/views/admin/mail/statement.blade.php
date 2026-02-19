{{-- C:\wamp64\www\pactopia360_erp\resources\views\admin\mail\statement.blade.php (v2.3 · expected_total + items fallback + tarifa pill + tracking-ready) --}}
@php
  // ==========================
  // Valores seguros / defaults
  // ==========================
  $periodTxt = (string)($period ?? '—');
  $periodLbl = (string)($period_label ?? $periodTxt);

  $itemsC = $items ?? collect();
  if (!($itemsC instanceof \Illuminate\Support\Collection)) {
    try { $itemsC = collect($itemsC); } catch (\Throwable $e) { $itemsC = collect(); }
  }
  $hasItems = $itemsC->count() > 0;

  // Totales (se espera que controller mande "cargo" como total mostrado y "total" como saldo)
  $cargoV = (float)($cargo ?? 0);
  $abonoV = (float)($abono ?? 0);
  $totalV = (float)($total ?? 0);

  // Nuevos campos (si no vienen, no rompe)
  $expectedV = (float)($expected_total ?? 0);
  $cargoRealV = (float)($cargo_real ?? 0);
  $tarifaLabel = (string)($tarifa_label ?? '—');

  // Si no hay items y cargo viene 0 pero sí hay expected, mostramos expected como cargo visual
  // (esto protege cuando todavía no aplicas el fix del controller).
  if (!$hasItems && $cargoV <= 0.00001 && $expectedV > 0.00001) {
    $cargoV = $expectedV;
    // saldo aproximado = cargo - abono
    $totalV = max(0, $cargoV - $abonoV);
  }

  $pdfUrl    = (string)($pdf_url ?? '');
  $payUrl    = (string)($pay_url ?? '');
  $portalUrl = (string)($portal_url ?? '');

  $hasPdf = !empty($pdfUrl);
  $hasPay = !empty($payUrl) && $totalV > 0.00001;

  // Tracking (opcionales)
  $openPixel = (string)($open_pixel_url ?? ''); // e.g. https://pactopia360.com/t/billing/open/{emailId}.gif

  // Links envueltos para click tracking (si no vienen, se usa el original)
  $pdfLink    = (string)($pdf_track_url ?? $pdfUrl);
  $payLink    = (string)($pay_track_url ?? $payUrl);
  $portalLink = (string)($portal_track_url ?? $portalUrl);

  // Preheader
  $preheader = (string)($preheader ?? ("Tu estado de cuenta del periodo {$periodTxt} está disponible."));

  // Datos de cuenta (opcionales)
  $acc = $account ?? null;
  $accName = '';
  $accRfc  = '';
  $accEmail= '';
  try {
    if (is_object($acc)) {
      $accName = (string)($acc->razon_social ?? $acc->name ?? '');
      $accRfc  = (string)($acc->rfc ?? '');
      $accEmail= (string)($acc->email ?? '');
    } elseif (is_array($acc)) {
      $accName = (string)($acc['razon_social'] ?? $acc['name'] ?? '');
      $accRfc  = (string)($acc['rfc'] ?? '');
      $accEmail= (string)($acc['email'] ?? '');
    }
  } catch (\Throwable $e) {}

  // Tarifa pill visual
  $tarifaPillBg = '#f1f5f9';   // slate-100
  $tarifaPillBd = '#e2e8f0';   // slate-200
  $tarifaPillTx = '#334155';   // slate-700

  $t = strtoupper(trim($tarifaLabel));
  if ($t === 'BASE') {
    $tarifaPillBg = '#eff6ff'; // blue-50
    $tarifaPillBd = '#bfdbfe'; // blue-200
    $tarifaPillTx = '#1d4ed8'; // blue-700
  } elseif ($t === 'PERSONALIZADO') {
    $tarifaPillBg = '#fff7ed'; // orange-50
    $tarifaPillBd = '#fed7aa'; // orange-200
    $tarifaPillTx = '#9a3412'; // orange-800
  }

  // Status
  $isPaid = ($totalV <= 0.00001);

  // Helpers de formato (Blade-safe)
  $fmtMoney = function(float $v): string {
    return '$'.number_format($v, 2);
  };
@endphp

<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Estado de cuenta</title>
</head>
<body style="margin:0;padding:0;background:#f6f7fb;">

  {{-- Preheader invisible --}}
  <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
    {{ $preheader }}
  </div>

  {{-- Tracking pixel (si existe) --}}
  @if(!empty($openPixel))
    <img src="{{ $openPixel }}" width="1" height="1" alt="" style="display:block;border:0;outline:none;text-decoration:none;">
  @endif

  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;background:#f6f7fb;">
    <tr>
      <td align="center" style="padding:28px 14px;">

        {{-- Container --}}
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0"
               style="border-collapse:collapse;max-width:640px;">
          <tr>
            <td style="padding:0 0 12px;">

              {{-- Top brand bar --}}
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
                <tr>
                  <td style="font-family:Arial,Helvetica,sans-serif;color:#0f172a;font-weight:900;font-size:18px;">
                    PACTOPIA360
                  </td>
                  <td align="right" style="font-family:Arial,Helvetica,sans-serif;color:#64748b;font-weight:700;font-size:12px;">
                    Estado de cuenta
                  </td>
                </tr>
              </table>

            </td>
          </tr>

          {{-- Card --}}
          <tr>
            <td style="background:#ffffff;border:1px solid #e8eaf2;border-radius:16px;overflow:hidden;box-shadow:0 10px 30px rgba(15,23,42,.08);">

              {{-- Header --}}
              <div style="padding:18px 18px 14px;background:linear-gradient(180deg,#ffffff,#fbfbff);border-bottom:1px solid #eef0f6;">
                <div style="font-family:Arial,Helvetica,sans-serif;color:#0f172a;font-weight:900;font-size:16px;line-height:1.25;">
                  Hola{{ $accName ? ', '.$accName : '' }},
                </div>

                @if($accRfc || $accEmail)
                  <div style="height:6px;"></div>
                  <div style="font-family:Arial,Helvetica,sans-serif;color:#64748b;font-weight:800;font-size:12px;line-height:1.45;">
                    @if($accRfc)<span style="font-weight:900;color:#334155;">RFC:</span> {{ $accRfc }}@endif
                    @if($accRfc && $accEmail) <span style="padding:0 6px;">·</span> @endif
                    @if($accEmail)<span style="font-weight:900;color:#334155;">Email:</span> {{ $accEmail }}@endif
                  </div>
                @endif

                <div style="height:10px;"></div>
                <div style="font-family:Arial,Helvetica,sans-serif;color:#334155;font-weight:700;font-size:13px;line-height:1.45;">
                  Tu estado de cuenta del periodo
                  <span style="font-weight:900;color:#0f172a;">{{ $periodTxt }}</span>
                  <span style="color:#64748b;">({{ $periodLbl }})</span>
                  está disponible.
                </div>

                <div style="height:10px;"></div>

                {{-- Tarifa pill --}}
                <div style="display:inline-block;background:{{ $tarifaPillBg }};color:{{ $tarifaPillTx }};border:1px solid {{ $tarifaPillBd }};border-radius:999px;padding:6px 10px;font-family:Arial,Helvetica,sans-serif;font-weight:900;font-size:12px;">
                  TARIFA: {{ $tarifaLabel ?: '—' }}
                </div>
              </div>

              {{-- Summary --}}
              <div style="padding:14px 18px 4px;">
                <div style="font-family:Arial,Helvetica,sans-serif;color:#0f172a;font-weight:900;font-size:13px;">
                  Resumen
                </div>
                <div style="height:10px;"></div>

                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
                  <tr>
                    <td style="padding:10px 12px;border:1px solid #eef0f6;border-radius:14px;background:#fafbff;">
                      <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
                        <tr>
                          <td style="font-family:Arial,Helvetica,sans-serif;color:#64748b;font-weight:800;font-size:12px;">
                            Cargo
                            @if(!$hasItems && $expectedV > 0.00001)
                              <span style="font-weight:900;color:#64748b;">(estimado)</span>
                            @endif
                          </td>
                          <td align="right" style="font-family:Arial,Helvetica,sans-serif;color:#0f172a;font-weight:900;font-size:13px;">
                            {{ $fmtMoney($cargoV) }}
                          </td>
                        </tr>

                        @if($hasItems && $expectedV > 0.00001 && abs($cargoRealV) > 0.00001)
                          <tr>
                            <td style="padding-top:8px;font-family:Arial,Helvetica,sans-serif;color:#94a3b8;font-weight:800;font-size:12px;">
                              Cargo real (movimientos)
                            </td>
                            <td align="right" style="padding-top:8px;font-family:Arial,Helvetica,sans-serif;color:#334155;font-weight:900;font-size:12px;">
                              {{ $fmtMoney($cargoRealV) }}
                            </td>
                          </tr>
                        @endif

                        <tr>
                          <td style="padding-top:8px;font-family:Arial,Helvetica,sans-serif;color:#64748b;font-weight:800;font-size:12px;">
                            Abono
                          </td>
                          <td align="right" style="padding-top:8px;font-family:Arial,Helvetica,sans-serif;color:#0f172a;font-weight:900;font-size:13px;">
                            {{ $fmtMoney($abonoV) }}
                          </td>
                        </tr>

                        <tr>
                          <td style="padding-top:10px;font-family:Arial,Helvetica,sans-serif;color:#0f172a;font-weight:900;font-size:13px;">
                            Saldo a pagar
                          </td>
                          <td align="right" style="padding-top:10px;font-family:Arial,Helvetica,sans-serif;color:#0f172a;font-weight:900;font-size:18px;">
                            {{ $fmtMoney($totalV) }}
                          </td>
                        </tr>
                      </table>
                    </td>
                  </tr>
                </table>

                <div style="height:12px;"></div>

                {{-- Status pill --}}
                @if($isPaid)
                  <div style="display:inline-block;background:#dcfce7;color:#166534;border:1px solid #bbf7d0;border-radius:999px;padding:6px 10px;font-family:Arial,Helvetica,sans-serif;font-weight:900;font-size:12px;">
                    PAGADO
                  </div>
                @else
                  <div style="display:inline-block;background:#fef9c3;color:#854d0e;border:1px solid #fde68a;border-radius:999px;padding:6px 10px;font-family:Arial,Helvetica,sans-serif;font-weight:900;font-size:12px;">
                    PENDIENTE
                  </div>
                @endif

                <div style="height:14px;"></div>

                {{-- Movimientos (si hay items) / Fallback licencia (si no hay) --}}
                @if($hasItems)
                  <div style="font-family:Arial,Helvetica,sans-serif;color:#0f172a;font-weight:900;font-size:13px;">
                    Movimientos
                  </div>
                  <div style="height:10px;"></div>

                  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;border:1px solid #eef0f6;border-radius:14px;overflow:hidden;">
                    <tr>
                      <td style="background:#f8fafc;padding:10px 10px;border-bottom:1px solid #eef0f6;font-family:Arial,Helvetica,sans-serif;color:#64748b;font-weight:900;font-size:11px;">
                        CONCEPTO
                      </td>
                      <td align="right" style="background:#f8fafc;padding:10px 10px;border-bottom:1px solid #eef0f6;font-family:Arial,Helvetica,sans-serif;color:#64748b;font-weight:900;font-size:11px;">
                        CARGO
                      </td>
                      <td align="right" style="background:#f8fafc;padding:10px 10px;border-bottom:1px solid #eef0f6;font-family:Arial,Helvetica,sans-serif;color:#64748b;font-weight:900;font-size:11px;">
                        ABONO
                      </td>
                    </tr>

                    @foreach($itemsC as $it)
                      @php
                        $concepto = '';
                        $detalle  = '';
                        $c = 0.0;
                        $a = 0.0;

                        try {
                          if (is_object($it)) {
                            $concepto = (string)($it->concepto ?? '');
                            $detalle  = (string)($it->detalle ?? '');
                            $c = (float)($it->cargo ?? 0);
                            $a = (float)($it->abono ?? 0);
                          } elseif (is_array($it)) {
                            $concepto = (string)($it['concepto'] ?? '');
                            $detalle  = (string)($it['detalle'] ?? '');
                            $c = (float)($it['cargo'] ?? 0);
                            $a = (float)($it['abono'] ?? 0);
                          }
                        } catch (\Throwable $e) {}
                      @endphp

                      <tr>
                        <td style="padding:10px 10px;border-bottom:1px solid #f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a;font-weight:900;font-size:12px;line-height:1.35;">
                          {{ $concepto !== '' ? $concepto : '—' }}
                          @if($detalle !== '')
                            <div style="margin-top:4px;font-family:Arial,Helvetica,sans-serif;color:#64748b;font-weight:700;font-size:11px;line-height:1.35;">
                              {{ $detalle }}
                            </div>
                          @endif
                        </td>
                        <td align="right" style="padding:10px 10px;border-bottom:1px solid #f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a;font-weight:900;font-size:12px;">
                          {{ $c > 0.00001 ? $fmtMoney($c) : '—' }}
                        </td>
                        <td align="right" style="padding:10px 10px;border-bottom:1px solid #f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a;font-weight:900;font-size:12px;">
                          {{ $a > 0.00001 ? $fmtMoney($a) : '—' }}
                        </td>
                      </tr>
                    @endforeach
                  </table>

                  <div style="height:14px;"></div>
                @else
                  <div style="padding:10px 12px;border:1px dashed #e2e8f0;border-radius:14px;background:#ffffff;">
                    <div style="font-family:Arial,Helvetica,sans-serif;color:#0f172a;font-weight:900;font-size:13px;">
                      Licencia del periodo (estimado)
                    </div>
                    <div style="height:6px;"></div>
                    <div style="font-family:Arial,Helvetica,sans-serif;color:#64748b;font-weight:800;font-size:12px;line-height:1.45;">
                      No se registraron movimientos en el periodo. El cargo mostrado corresponde a la tarifa configurada para este mes.
                    </div>
                    <div style="height:10px;"></div>

                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
                      <tr>
                        <td style="font-family:Arial,Helvetica,sans-serif;color:#64748b;font-weight:900;font-size:12px;">
                          Concepto
                        </td>
                        <td align="right" style="font-family:Arial,Helvetica,sans-serif;color:#0f172a;font-weight:900;font-size:12px;">
                          Licencia
                        </td>
                      </tr>
                      <tr>
                        <td style="padding-top:8px;font-family:Arial,Helvetica,sans-serif;color:#64748b;font-weight:900;font-size:12px;">
                          Cargo
                        </td>
                        <td align="right" style="padding-top:8px;font-family:Arial,Helvetica,sans-serif;color:#0f172a;font-weight:900;font-size:14px;">
                          {{ $fmtMoney($cargoV) }}
                        </td>
                      </tr>
                      <tr>
                        <td style="padding-top:8px;font-family:Arial,Helvetica,sans-serif;color:#64748b;font-weight:900;font-size:12px;">
                          Tarifa
                        </td>
                        <td align="right" style="padding-top:8px;font-family:Arial,Helvetica,sans-serif;color:#0f172a;font-weight:900;font-size:12px;">
                          {{ $tarifaLabel ?: '—' }}
                        </td>
                      </tr>
                    </table>
                  </div>

                  <div style="height:14px;"></div>
                @endif

                {{-- Actions --}}
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
                  <tr>
                    <td style="padding:0;">

                      {{-- PDF buttons --}}
                      @if($hasPdf)
                        <table role="presentation" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
                          <tr>
                            <td style="padding:0 10px 10px 0;">
                              <a href="{{ $pdfLink }}"
                                 style="display:inline-block;background:#22c55e;color:#0b1220;text-decoration:none;padding:12px 14px;border-radius:12px;font-family:Arial,Helvetica,sans-serif;font-weight:900;font-size:13px;border:1px solid rgba(15,23,42,.10);">
                                Visualizar PDF
                              </a>
                            </td>

                            <td style="padding:0 0 10px 0;">
                              <a href="{{ $pdfLink }}"
                                 style="display:inline-block;background:#e8f9ee;color:#14532d;text-decoration:none;padding:12px 14px;border-radius:12px;font-family:Arial,Helvetica,sans-serif;font-weight:900;font-size:13px;border:1px solid rgba(34,197,94,.30);">
                                Descargar
                              </a>
                            </td>
                          </tr>
                        </table>
                      @endif

                      {{-- Pay CTA --}}
                      @if($hasPay)
                        <div style="height:2px;"></div>
                        <a href="{{ $payLink }}"
                           style="display:block;text-align:center;background:#f59e0b;color:#111827;text-decoration:none;padding:13px 14px;border-radius:12px;font-family:Arial,Helvetica,sans-serif;font-weight:900;font-size:14px;border:1px solid rgba(15,23,42,.12);">
                          Pagar ahora con tarjeta
                        </a>
                      @else
                        <div style="font-family:Arial,Helvetica,sans-serif;color:#64748b;font-weight:800;font-size:12px;line-height:1.45;">
                          @if($isPaid)
                            No se requiere pago para este periodo.
                          @else
                            Nota: No se generó liga de pago (Stripe no disponible o configuración pendiente). Puedes revisar tu estado en el portal.
                          @endif
                        </div>
                      @endif

                      {{-- Portal --}}
                      @if(!empty($portalUrl))
                        <div style="height:12px;"></div>
                        <a href="{{ $portalLink }}"
                           style="display:block;text-align:center;background:#0f172a;color:#ffffff;text-decoration:none;padding:12px 14px;border-radius:12px;font-family:Arial,Helvetica,sans-serif;font-weight:900;font-size:13px;border:1px solid rgba(15,23,42,.12);">
                          Ir al portal
                        </a>
                      @endif

                    </td>
                  </tr>
                </table>

                {{-- Fallback links --}}
                <div style="height:14px;"></div>
                <div style="font-family:Arial,Helvetica,sans-serif;color:#64748b;font-size:12px;line-height:1.45;">
                  @if($hasPdf)
                    Si los botones no funcionan, copia y pega esta liga del PDF:<br>
                    <a href="{{ $pdfUrl }}" style="color:#2563eb;text-decoration:underline;word-break:break-all;">{{ $pdfUrl }}</a>
                    <div style="height:10px;"></div>
                  @endif

                  @if($hasPay)
                    Liga de pago:<br>
                    <a href="{{ $payUrl }}" style="color:#2563eb;text-decoration:underline;word-break:break-all;">{{ $payUrl }}</a>
                  @endif

                  @if(!empty($portalUrl))
                    @if(!$hasPay)
                      <div style="height:10px;"></div>
                    @endif
                    Portal:<br>
                    <a href="{{ $portalUrl }}" style="color:#2563eb;text-decoration:underline;word-break:break-all;">{{ $portalUrl }}</a>
                  @endif
                </div>

                <div style="height:16px;"></div>
              </div>

              {{-- Footer --}}
              <div style="padding:14px 18px;background:#fafafa;border-top:1px solid #eef0f6;">
                <div style="font-family:Arial,Helvetica,sans-serif;color:#334155;font-weight:800;font-size:12px;line-height:1.45;">
                  Atentamente,<br>
                  <span style="color:#0f172a;font-weight:900;">PACTOPIA360</span>
                </div>
                <div style="height:10px;"></div>
                <div style="font-family:Arial,Helvetica,sans-serif;color:#94a3b8;font-weight:700;font-size:11px;line-height:1.45;">
                  Los pagos con tarjeta se reflejan al confirmarse en Stripe (webhook). Si el pago es manual, Admin lo registra y se refleja en el estado de cuenta.
                </div>
              </div>

            </td>
          </tr>

          {{-- Outer footer note --}}
          <tr>
            <td style="padding:14px 6px 0;font-family:Arial,Helvetica,sans-serif;color:#94a3b8;font-weight:700;font-size:11px;line-height:1.45;text-align:center;">
              Este es un correo automático. Si necesitas soporte, responde a este correo o contáctanos desde el portal.
            </td>
          </tr>

        </table>

      </td>
    </tr>
  </table>
</body>
</html>
