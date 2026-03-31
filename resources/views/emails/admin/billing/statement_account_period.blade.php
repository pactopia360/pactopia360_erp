{{-- resources/views/emails/admin/billing/statement_account_period.blade.php --}}
@php
  $acc = $account ?? null;

  $accName = trim((string) (($acc->razon_social ?? '') ?: ($acc->name ?? '') ?: ($acc->email ?? 'Cliente')));
  $accEmail = trim((string) ($acc->email ?? ''));
  $accRfc   = trim((string) ($acc->rfc ?? ''));

  $periodTxt = (string) ($period_label ?? $period ?? '');

  $statementCargo = (float) ($statement_cargo ?? 0);
  $statementAbono = (float) ($statement_abono ?? 0);
  $statementSaldo = (float) ($statement_saldo ?? 0);

  $cargoPeriodo = $statementCargo > 0.00001
      ? $statementCargo
      : (float) ($total_cargo ?? $cargo ?? 0);

  $abonoTotal = $statementAbono > 0.00001
      ? $statementAbono
      : (float) ($total_abono ?? $abono ?? 0);

  $saldoPeriodo = $statementSaldo > 0.00001
      ? $statementSaldo
      : (float) ($current_period_due ?? $saldo ?? 0);

  $saldoAnterior = (float) ($prev_balance ?? 0);
  $saldoTotal = (float) ($total_due ?? $total ?? max(0, $saldoPeriodo + $saldoAnterior));

  if ($statementSaldo > 0.00001) {
      $saldoTotal = max($saldoTotal, $statementSaldo);
  }

  $hasSaldo = $saldoTotal > 0.00001;

  $statusRaw = strtolower((string) ($status_override ?? $status_pago ?? $statement_status ?? ($hasSaldo ? 'pendiente' : 'pagado')));
  if (in_array($statusRaw, ['paid','succeeded','success','completed','complete'], true)) {
      $statusRaw = 'pagado';
  }

  $statusLbl = match ($statusRaw) {
      'pagado'  => 'Pagado',
      'parcial' => 'Parcial',
      'vencido' => 'Vencido',
      'sin_mov' => 'Sin movimiento',
      default   => 'Pendiente',
  };

  $statusBg = match ($statusRaw) {
      'pagado'  => '#EAF8EF',
      'parcial' => '#FFF4E8',
      'vencido' => '#FEECEC',
      'sin_mov' => '#EEF4FF',
      default   => '#EAF1FF',
  };

  $statusInk = match ($statusRaw) {
      'pagado'  => '#166534',
      'parcial' => '#9A3412',
      'vencido' => '#B91C1C',
      'sin_mov' => '#48607A',
      default   => '#2D358E',
  };

  $tarifaLabel = trim((string) ($tarifa_label ?? 'Estado de cuenta'));
  $statementId = (int) ($statement_id ?? 0);

  $payMethodUi   = trim((string) ($pay_method ?? ''));
  $payProviderUi = trim((string) ($pay_provider ?? ''));
  $payStatusUi   = trim((string) ($pay_status ?? ''));
  $lastPaidUi    = trim((string) ($last_paid ?? ''));
  $payAllowedUi  = trim((string) ($pay_allowed ?? ''));

  $openPixelUrl   = (string) ($open_pixel_url ?? '');
  $pdfTrackUrl    = (string) ($pdf_track_url ?? $pdf_url ?? '');
  $portalTrackUrl = (string) ($portal_track_url ?? $portal_url ?? '');
  $payTrackUrl    = (string) ($pay_track_url ?? $pay_url ?? '');

  $generatedAt = (string) ($generated_at ?? now());
  $emailId     = trim((string) ($email_id ?? ''));

  $itemsRaw  = $items ?? [];
  $itemsList = is_iterable($itemsRaw) ? $itemsRaw : [];

  $fmtMoney = function ($n): string {
      return '$' . number_format((float) $n, 2) . ' MXN';
  };

  $safeText = function (?string $v, string $fallback = '—'): string {
      $v = trim((string) $v);
      return $v !== '' ? $v : $fallback;
  };

  $emailTitle = (string) ($subject ?? 'Pactopia360 · Estado de cuenta');
  $emailPreheader = $hasSaldo
      ? 'Tienes un saldo pendiente de ' . $fmtMoney($saldoTotal) . '.'
      : 'Tu estado de cuenta está al corriente.';

  $footerPrimary = 'Estado de cuenta generado desde Pactopia360.';
  $footerSecondary = 'Puedes revisar el detalle y completar el pago desde los accesos incluidos en este correo.';

  $logoWhiteUrl = asset('assets/admin/img/Pactopia - Letra Blanca.png');

  $brandBlue    = '#4D8EED';
  $brandBlue2   = '#3F74DA';
  $brandBlue3   = '#2D358E';
  $brandBlue4   = '#1F56C5';
  $brandNavy    = '#102447';
  $brandSoftBg  = '#EEF4FF';
  $brandSoftBd  = '#D9E6FF';
  $brandText    = '#0F172A';
  $brandMuted   = '#64748B';

  $rows = [];
  foreach ($itemsList as $it) {
      $rows[] = $it;
  }
@endphp

@extends('emails.admin.billing.layout')

@section('email_content')
  <tr>
    <td style="padding:0;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;background:#ffffff;border:1px solid #dbe5f1;border-radius:18px;overflow:hidden;border-collapse:collapse;box-shadow:0 10px 26px rgba(15,23,42,.08);">
        <tr>
          <td style="padding:0;">

            {{-- HERO AZUL PACTOPIA --}}
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;background:linear-gradient(135deg, {{ $brandNavy }} 0%, {{ $brandBlue3 }} 54%, {{ $brandBlue }} 100%);border-collapse:collapse;">
              <tr>
                <td style="padding:20px 20px 18px 20px;">
                  <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="border-collapse:collapse;">
                    <tr>
                      <td style="vertical-align:middle;">
                        <img src="{{ $logoWhiteUrl }}" alt="Pactopia360" style="height:34px;display:block;max-width:220px;">
                      </td>
                      <td align="right" style="vertical-align:middle;">
                        <span style="display:inline-block;background:{{ $statusBg }};color:{{ $statusInk }};padding:8px 12px;border-radius:999px;font-size:12px;font-weight:800;">
                          {{ $statusLbl }}
                        </span>
                      </td>
                    </tr>
                  </table>

                  <div style="margin-top:16px;color:#dbeafe;font-size:13px;line-height:1.6;font-weight:700;">
                    Estado de cuenta de
                    <strong style="color:#ffffff;">{{ $accName }}</strong>
                    @if($accRfc !== '')
                      <span style="color:#dbeafe;">({{ $accRfc }})</span>
                    @endif
                  </div>

                  <div style="margin-top:12px;color:#ffffff;font-size:34px;line-height:1.08;font-weight:900;">
                    {{ $fmtMoney($saldoTotal) }}
                  </div>

                  <div style="margin-top:6px;color:#eaf2ff;font-size:13px;line-height:1.6;font-weight:700;">
                    {{ $periodTxt !== '' ? $periodTxt : 'Periodo actual' }}
                  </div>
                </td>
              </tr>
            </table>

            {{-- CONTENIDO --}}
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;background:#ffffff;">
              <tr>
                <td style="padding:18px;">

                  <h1 style="margin:0 0 8px;font-size:20px;line-height:1.25;color:{{ $brandText }};">
                    Resumen del estado de cuenta
                  </h1>

                  <div style="color:#475569;font-size:13px;line-height:1.7;">
                    @if($hasSaldo)
                      Registramos un saldo pendiente en este periodo. Puedes revisar el detalle y completar el pago en línea.
                    @else
                      No registramos saldo pendiente en este periodo. Puedes revisar el detalle de tu estado de cuenta cuando lo necesites.
                    @endif
                  </div>

                  {{-- KPIS --}}
                  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:14px;border-collapse:collapse;">
                    <tr>
                      <td width="50%" style="padding:0 6px 8px 0;vertical-align:top;">
                        <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:{{ $brandSoftBg }};border:1px solid {{ $brandSoftBd }};border-radius:12px;border-collapse:collapse;">
                          <tr>
                            <td style="padding:14px;">
                              <div style="color:{{ $brandMuted }};font-size:12px;font-weight:700;">Cargo del periodo</div>
                              <div style="margin-top:6px;color:{{ $brandText }};font-size:20px;font-weight:800;">{{ $fmtMoney($cargoPeriodo) }}</div>
                            </td>
                          </tr>
                        </table>
                      </td>
                      <td width="50%" style="padding:0 0 8px 6px;vertical-align:top;">
                        <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:{{ $brandSoftBg }};border:1px solid {{ $brandSoftBd }};border-radius:12px;border-collapse:collapse;">
                          <tr>
                            <td style="padding:14px;">
                              <div style="color:{{ $brandMuted }};font-size:12px;font-weight:700;">Abonos aplicados</div>
                              <div style="margin-top:6px;color:{{ $brandText }};font-size:20px;font-weight:800;">{{ $fmtMoney($abonoTotal) }}</div>
                            </td>
                          </tr>
                        </table>
                      </td>
                    </tr>
                    <tr>
                      <td width="50%" style="padding:0 6px 0 0;vertical-align:top;">
                        <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:{{ $brandSoftBg }};border:1px solid {{ $brandSoftBd }};border-radius:12px;border-collapse:collapse;">
                          <tr>
                            <td style="padding:14px;">
                              <div style="color:{{ $brandMuted }};font-size:12px;font-weight:700;">Saldo anterior</div>
                              <div style="margin-top:6px;color:{{ $brandText }};font-size:20px;font-weight:800;">{{ $fmtMoney($saldoAnterior) }}</div>
                            </td>
                          </tr>
                        </table>
                      </td>
                      <td width="50%" style="padding:0 0 0 6px;vertical-align:top;">
                        <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:{{ $brandSoftBg }};border:1px solid {{ $brandSoftBd }};border-radius:12px;border-collapse:collapse;">
                          <tr>
                            <td style="padding:14px;">
                              <div style="color:{{ $brandMuted }};font-size:12px;font-weight:700;">Total a pagar</div>
                              <div style="margin-top:6px;color:{{ $brandText }};font-size:20px;font-weight:800;">{{ $fmtMoney($saldoTotal) }}</div>
                            </td>
                          </tr>
                        </table>
                      </td>
                    </tr>
                  </table>

                  {{-- DATOS --}}
                  <div style="margin-top:14px;border-radius:12px;border:1px solid {{ $brandSoftBd }};background:{{ $brandSoftBg }};padding:14px;">
                    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="font-size:13px;border-collapse:collapse;">
                      <tr>
                        <td style="width:160px;color:{{ $brandMuted }};padding:6px 0;">Tarifa</td>
                        <td style="padding:6px 0;color:{{ $brandText }};font-weight:700;">{{ $safeText($tarifaLabel) }}</td>
                      </tr>
                      <tr>
                        <td style="color:{{ $brandMuted }};padding:6px 0;">Estado</td>
                        <td style="padding:6px 0;color:{{ $brandText }};font-weight:700;">{{ $statusLbl }}</td>
                      </tr>
                      <tr>
                        <td style="color:{{ $brandMuted }};padding:6px 0;">Statement</td>
                        <td style="padding:6px 0;color:{{ $brandText }};font-weight:700;">{{ $statementId > 0 ? ('#' . $statementId) : '—' }}</td>
                      </tr>
                      <tr>
                        <td style="color:{{ $brandMuted }};padding:6px 0;">Tracking</td>
                        <td style="padding:6px 0;color:{{ $brandText }};font-weight:700;">{{ $emailId !== '' ? $emailId : '—' }}</td>
                      </tr>

                      @if($lastPaidUi !== '')
                        <tr>
                          <td style="color:{{ $brandMuted }};padding:6px 0;">Último periodo pagado</td>
                          <td style="padding:6px 0;color:{{ $brandText }};font-weight:700;">{{ $lastPaidUi }}</td>
                        </tr>
                      @endif

                      @if($payAllowedUi !== '')
                        <tr>
                          <td style="color:{{ $brandMuted }};padding:6px 0;">Siguiente periodo permitido</td>
                          <td style="padding:6px 0;color:{{ $brandText }};font-weight:700;">{{ $payAllowedUi }}</td>
                        </tr>
                      @endif

                      @if($payMethodUi !== '' || $payProviderUi !== '' || $payStatusUi !== '')
                        <tr>
                          <td style="color:{{ $brandMuted }};padding:6px 0;">Pago</td>
                          <td style="padding:6px 0;color:{{ $brandText }};font-weight:700;">
                            Método: {{ $safeText($payMethodUi) }}
                            · Proveedor: {{ $safeText($payProviderUi) }}
                            · Estado: {{ $safeText($payStatusUi) }}
                          </td>
                        </tr>
                      @endif
                    </table>
                  </div>

                  {{-- BOTONES --}}
                  <div style="margin-top:16px;text-align:center;">
                    @if($hasSaldo && $payTrackUrl !== '')
                      <a href="{{ $payTrackUrl }}"
                        style="display:inline-block;min-width:150px;max-width:100%;box-sizing:border-box;background:linear-gradient(90deg,#ff476a 0%,#ff7a59 100%);color:#ffffff;text-decoration:none;padding:10px 16px;border-radius:12px;font-weight:800;font-size:13px;line-height:1.1;text-align:center;box-shadow:0 6px 14px rgba(255,101,92,.18);margin:0 6px 8px 6px;">
                        Pagar ahora
                      </a>
                    @endif

                    @if($pdfTrackUrl !== '')
                      <a href="{{ $pdfTrackUrl }}"
                        style="display:inline-block;background:#ffffff;color:{{ $brandBlue3 }};text-decoration:none;padding:10px 16px;border-radius:12px;font-weight:800;font-size:13px;line-height:1.1;border:1px solid {{ $brandSoftBd }};margin:0 6px 8px 6px;">
                        Ver estado de cuenta
                      </a>
                    @endif

                    @if(!$hasSaldo && $portalTrackUrl !== '')
                      <a href="{{ $portalTrackUrl }}"
                         style="display:inline-block;background:#ffffff;color:{{ $brandBlue3 }};text-decoration:none;padding:12px 18px;border-radius:12px;font-weight:700;font-size:13px;border:1px solid {{ $brandSoftBd }};margin:0 6px 8px 6px;">
                        Ir al portal
                      </a>
                    @endif
                  </div>

                  {{-- MOVIMIENTOS --}}
                  <div style="margin-top:18px;">
                    <div style="font-size:12px;line-height:1.3;color:{{ $brandMuted }};font-weight:900;letter-spacing:.08em;text-transform:uppercase;margin-bottom:10px;">
                      Movimientos del periodo
                    </div>

                    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="border:1px solid {{ $brandSoftBd }};border-radius:12px;overflow:hidden;border-collapse:collapse;background:#ffffff;">
                      <tr style="background:{{ $brandSoftBg }};">
                        <td style="padding:12px 14px;color:{{ $brandMuted }};font-size:12px;font-weight:800;">Concepto</td>
                        <td style="padding:12px 14px;color:{{ $brandMuted }};font-size:12px;font-weight:800;">Detalle</td>
                        <td align="right" style="padding:12px 14px;color:{{ $brandMuted }};font-size:12px;font-weight:800;">Cargo</td>
                        <td align="right" style="padding:12px 14px;color:{{ $brandMuted }};font-size:12px;font-weight:800;">Abono</td>
                      </tr>

                      @forelse($rows as $it)
                        <tr>
                          <td style="padding:12px 14px;border-top:1px solid {{ $brandSoftBd }};color:{{ $brandText }};font-size:13px;font-weight:700;">
                            {{ $it->concepto ?? '—' }}
                          </td>
                          <td style="padding:12px 14px;border-top:1px solid {{ $brandSoftBd }};color:#475569;font-size:13px;">
                            {{ $it->detalle ?? '—' }}
                          </td>
                          <td align="right" style="padding:12px 14px;border-top:1px solid {{ $brandSoftBd }};color:{{ $brandText }};font-size:13px;font-weight:700;white-space:nowrap;">
                            ${{ number_format((float)($it->cargo ?? 0), 2) }}
                          </td>
                          <td align="right" style="padding:12px 14px;border-top:1px solid {{ $brandSoftBd }};color:{{ $brandText }};font-size:13px;font-weight:700;white-space:nowrap;">
                            ${{ number_format((float)($it->abono ?? 0), 2) }}
                          </td>
                        </tr>
                      @empty
                        <tr>
                          <td colspan="4" style="padding:14px;border-top:1px solid {{ $brandSoftBd }};color:{{ $brandMuted }};font-size:13px;line-height:1.6;">
                            Sin movimientos registrados en este periodo.
                          </td>
                        </tr>
                      @endforelse
                    </table>
                  </div>

                  {{-- FOOT DATA --}}
                  <div style="margin-top:16px;color:{{ $brandMuted }};font-size:12px;line-height:1.7;">
                    Generado: <strong style="color:{{ $brandText }};">{{ $generatedAt }}</strong>
                    @if($emailId !== '') · Tracking: <strong style="color:{{ $brandText }};">{{ $emailId }}</strong>@endif
                  </div>

                  <div style="margin-top:6px;color:{{ $brandMuted }};font-size:12px;line-height:1.7;">
                    Cargo del periodo: <strong style="color:{{ $brandText }};">{{ $fmtMoney($cargoPeriodo) }}</strong>
                    · Abonos: <strong style="color:{{ $brandText }};">{{ $fmtMoney($abonoTotal) }}</strong>
                    · Total final: <strong style="color:{{ $brandText }};">{{ $fmtMoney($saldoTotal) }}</strong>
                  </div>

                </td>
              </tr>
            </table>

          </td>
        </tr>
      </table>
    </td>
  </tr>
@endsection