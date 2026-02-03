{{-- resources/views/cliente/billing/pdf/statement.blade.php (P360 · PDF Estado de cuenta · mejorado + DomPDF-safe) --}}
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Estado de cuenta {{ $period ?? '—' }}</title>

  @php
  // DomPDF: incluir CSS local por path absoluto
  $pdfCssPath = public_path('assets/client/pdf/statement.css');
  $pdfCss     = is_file($pdfCssPath) ? file_get_contents($pdfCssPath) : '';
@endphp

<style>{!! $pdfCss !!}</style>

</head>
<body>

@php
  use Illuminate\Support\Carbon;
  use Illuminate\Support\Str;

  $periodSafe = (string)($period ?? '—');

  /**
   * IMPORTANT:
   * En tu PDF anterior $total era "saldo a pagar".
   * Aquí soportamos ambas formas:
   * - si viene $saldo úsalo
   * - si no viene, usa $total como saldo
   */
  $cargo = (float)($cargo ?? 0);
  $abono = (float)($abono ?? 0);

  // ✅ Saldo del periodo (como antes)
  $saldoPeriodo = (float)($saldo ?? 0);

  // ✅ Nuevos campos (si vienen del backend)
  $prevPeriod      = (string)($prev_period ?? '');
  $prevPeriodLabel = (string)($prev_period_label ?? $prevPeriod);
  $prevBalance     = (float)($prev_balance ?? 0);

  $currentDue = (float)($current_period_due ?? $saldoPeriodo);
  $totalDue   = (float)($total_due ?? 0);

  /**
   * Compatibilidad:
   * - Si viene total_due, ese es el "Total a pagar" real (saldo periodo + saldo anterior)
   * - Si no viene, usa la lógica anterior (saldo o total)
   */
  $legacySaldo = (float)($saldoPeriodo > 0 ? $saldoPeriodo : (float)($total ?? 0));

  $totalPagar = $totalDue > 0.00001 ? $totalDue : $legacySaldo;

  // Para cards: mostrar saldo anterior SI hay balance > 0 (aunque no venga etiqueta)
  $showPrev = ($prevBalance > 0.00001);

  // Label defensivo para "periodo anterior"
  $prevLabelSafe = trim((string)($prevPeriodLabel ?? ''));
  if ($prevLabelSafe === '') $prevLabelSafe = trim((string)($prevPeriod ?? ''));
  if ($prevLabelSafe === '') $prevLabelSafe = 'Periodo anterior';

  // =========================
  // Estatus visible (sin backend)
  // =========================
  $statusLabel = 'Pendiente';
  $statusBadge = 'warn';

  if ($totalPagar <= 0.00001) {
    // si no hay pago requerido: pagado o sin movimientos
    $statusLabel = ($cargo > 0.00001 || $abono > 0.00001) ? 'Pagado' : 'Sin movimientos';
    $statusBadge = ($statusLabel === 'Pagado') ? 'ok' : 'dim';
  } else {
    // hay total a pagar
    if ($abono > 0.00001 && ($totalPagar > 0.00001)) {
      $statusLabel = 'Parcial';
      $statusBadge = 'warn';
    } else {
      $statusLabel = 'Pendiente';
      $statusBadge = 'warn';
    }
  }


  // Totales esperados / tarifa (si backend lo manda)

  $expectedTotal = (float)($expected_total ?? 0);
  $tarifaLabel   = (string)($tarifa_label ?? '');
  $tarifaPill    = (string)($tarifa_pill ?? 'dim');
  if (!in_array($tarifaPill, ['info','warn','ok','dim','bad'], true)) $tarifaPill = 'dim';

  // IVA sobre el saldo a pagar (si el saldo incluye IVA)
  $ivaRate  = 0.16;
  $subtotal = $totalPagar > 0 ? round($totalPagar/(1+$ivaRate), 2) : 0.0;
  $iva      = $totalPagar > 0 ? round($totalPagar - $subtotal, 2) : 0.0;

  // Cuenta
  $accountIdRaw = $account_id ?? ($account->id ?? 0);
  $accountIdNum = is_numeric($accountIdRaw) ? (int)$accountIdRaw : 0;

  // En maqueta ID con 6 dígitos
  $idCuentaTxt = $accountIdNum > 0 ? str_pad((string)$accountIdNum, 6, '0', STR_PAD_LEFT) : '—';

  // Fechas
  $printedAt = ($generated_at ?? null) ? Carbon::parse($generated_at) : now();
  $dueAt     = ($due_at ?? null) ? Carbon::parse($due_at) : $printedAt->copy()->addDays(4);

  // Cliente / datos extra (mejor fallback: razon_social -> name -> email)
  $rs1 = trim((string)($razon_social ?? ''));
  $rs2 = trim((string)($account->razon_social ?? ''));
  $nm1 = trim((string)($name ?? ''));
  $nm2 = trim((string)($account->name ?? ''));
  $em1 = trim((string)($email ?? ''));
  $em2 = trim((string)($account->email ?? ''));

  $clienteRazon = $rs1 !== '' ? $rs1
    : ($rs2 !== '' ? $rs2
    : ($nm1 !== '' ? $nm1
    : ($nm2 !== '' ? $nm2
    : ($em1 !== '' ? $em1
    : ($em2 !== '' ? $em2 : 'Cliente')))));

  $clienteRfc   = (string)($rfc ?? ($account->rfc ?? '—'));
  $clienteEmail = (string)($email ?? ($account->email ?? '—'));
  $clientePlan  = (string)($plan ?? ($account->plan ?? ($account->plan_actual ?? '—')));
  $modoCobro    = (string)($modo_cobro ?? ($account->modo_cobro ?? ($account->billing_cycle ?? '—')));

  // Dirección
  $dir = (array)($cliente_dir ?? []);
  $dirCalle = trim((string)($dir['calle'] ?? ''));
  $dirExt   = trim((string)($dir['num_ext'] ?? ''));
  $dirInt   = trim((string)($dir['num_int'] ?? ''));
  $dirCol   = trim((string)($dir['colonia'] ?? ''));
  $dirMun   = trim((string)($dir['municipio'] ?? ''));
  $dirEdo   = trim((string)($dir['estado'] ?? ''));
  $dirCp    = trim((string)($dir['cp'] ?? ''));
  $dirPais  = trim((string)($dir['pais'] ?? 'México'));

  $line1 = trim($dirCalle.' '.($dirExt ? $dirExt : '').($dirInt ? ' Int. '.$dirInt : ''));
  $line2 = $dirCol ?: null;
  $line3 = trim(($dirMun ?: '').($dirEdo ? ', '.$dirEdo : ''));
  $line4 = $dirCp ? ('C.P. '.$dirCp) : null;

  // Assets embebidos (data uris)
  $logoDataUri = $logo_data_uri ?? null;

  // ✅ Fallback robusto: convertir PNG local a Data URI (DomPDF-safe)
  $logoFilePath = public_path('assets/client/logp360ligjt.png');
  $logoFileOk   = is_file($logoFilePath);

  if (!$logoDataUri && $logoFileOk) {
      try {
          $bin = file_get_contents($logoFilePath);
          if ($bin !== false && strlen($bin) > 10) {
              $logoDataUri = 'data:image/png;base64,'.base64_encode($bin);
          }
      } catch (\Throwable $e) {
          // noop
      }
  }

  $qrDataUri   = $qr_data_uri ?? null;
  $qrUrl       = $qr_url ?? null;

  $payUrl = (string)($pay_url ?? ''); // enlace de pago Stripe si viene
  $sessionId = (string)($stripe_session_id ?? '');

  $payPaypal = $pay_paypal_data_uri ?? null;
  $payVisa   = $pay_visa_data_uri ?? null;
  $payAmex   = $pay_amex_data_uri ?? null;
  $payMc     = $pay_mc_data_uri ?? null;
  $payOxxo   = $pay_oxxo_data_uri ?? null;

  $socFb = $social_fb_data_uri ?? null;
  $socIn = $social_in_data_uri ?? null;
  $socYt = $social_yt_data_uri ?? null;
  $socIg = $social_ig_data_uri ?? null;

  // Total en letras (si backend lo pasa, respeta)
  $cent = (int) round(($totalPagar - floor($totalPagar)) * 100);
  $totalLetras = (string)($total_letras ?? (number_format(floor($totalPagar), 0, '.', ',').' pesos '.str_pad((string)$cent, 2, '0', STR_PAD_LEFT).'/100 MN'));

  // Periodo label
  $periodLabel = $periodSafe;
  try {
    if (preg_match('/^\d{4}-\d{2}$/', $periodSafe)) {
      $periodLabel = Carbon::parse($periodSafe.'-01')->translatedFormat('F Y');
      $periodLabel = Str::ucfirst($periodLabel);
    }
  } catch (\Throwable $e) {}

  // Detalle de consumos (backend: $consumos) o fallback
  // REGLA: si el backend manda service_items (y trae filas), úsalo SIEMPRE.
  // $consumos queda solo como compat/fallback.
  $serviceItems = [];

  // 1) service_items (PRIORIDAD)
  $si = $service_items ?? null;
  if ($si instanceof \Illuminate\Support\Collection) $si = $si->all();
  if (is_array($si) && count($si) > 0) {
      $serviceItems = $si;
  } else {
      // 2) consumos (FALLBACK)
      $consumosRaw = $consumos ?? null;
      if ($consumosRaw instanceof \Illuminate\Support\Collection) $consumosRaw = $consumosRaw->all();
      $serviceItems = is_array($consumosRaw) ? $consumosRaw : [];
  }

  if (!is_array($serviceItems)) $serviceItems = [];

  // =========================================================
  // ✅ Inyectar "Saldo anterior pendiente" como línea en Detalle
  // (solo si existe saldo anterior > 0)
  // =========================================================
  if ($showPrev) {
      array_unshift($serviceItems, [
          'service'   => 'Saldo anterior pendiente (' . $prevLabelSafe . ')',
          'unit_cost' => round((float)$prevBalance, 2),
          'qty'       => 1,
          'subtotal'  => round((float)$prevBalance, 2),
      ]);
  }

  // Asegurar mínimo filas visuales
  $minRows   = 4;

  $rowsCount = count($serviceItems);
  $padRows   = max(0, $minRows - $rowsCount);

@endphp

{{-- TOP: izquierda marca / derecha total + id --}}
<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
  <tr>
    <td width="52%" style="vertical-align:top; padding-right:12px;">
      @if($logoDataUri)
        <img src="{{ $logoDataUri }}" class="brandLogo" alt="PACTOPIA360">
      @else
        <div class="b" style="font-size:24px;">PACTOPIA360</div>
      @endif


      <div class="hTitle">Estado de cuenta</div>

      <div class="sp10"></div>

      <div class="brandName">Pactopia SAPI de CV</div>
      <div class="brandBlock">
        Fresno 195 Int 2<br>
        Santa María la Ribera<br>
        Cuauhtémoc, C.P. 06400<br>
        México<br>
        <span class="b">RFC</span> PACA151025NJ5
      </div>
      <div class="brandSite">Pactopia.com</div>

      <div class="sp12"></div>

      {{-- Datos extra (mejora: plan/método/email) --}}
      <div class="brandBlock">
        <div><span class="b">Email:</span> {{ $clienteEmail ?: '—' }}</div>
        <div><span class="b">Plan:</span> {{ $clientePlan ?: '—' }}</div>
        <div><span class="b">Modo de cobro:</span> {{ $modoCobro ?: '—' }}</div>

        @if($tarifaLabel !== '')
          <div class="sp8"></div>
          <div><span class="b">Tarifa:</span> <span class="pill {{ $tarifaPill }}">{{ $tarifaLabel }}</span></div>
        @endif

        @if($expectedTotal > 0.00001)
          <div><span class="b">Total esperado:</span> $ {{ number_format($expectedTotal, 2) }}</div>
        @endif
      </div>
    </td>

    <td width="48%" style="vertical-align:top; padding-left:12px;">
      <div class="card">
        <table class="totalRowTbl" cellpadding="0" cellspacing="0">
          <tr>
            <td class="totalCardLbl">
              Total a pagar:
              <span class="badge {{ $statusBadge }}" style="margin-left:8px; vertical-align:middle;">
                {{ $statusLabel }}
              </span>
            </td>

            <td class="r">
              <table cellpadding="0" cellspacing="0" style="border-collapse:collapse; width:100%;">
                <tr>
                  <td class="moneySign">$</td>
                  <td class="r totalAmt">{{ number_format($totalPagar, 2) }}</td>
                </tr>
              </table>
            </td>
          </tr>
        </table>
        <div class="totalWords">{{ $totalLetras }}</div>

        <div class="hr"></div>

        {{-- Mejora: resumen saldo/cargo/abono --}}
        <table class="kv kvTight" cellpadding="0" cellspacing="0">
          <tr>
            <td class="k mut small">Cargo del periodo</td>
            <td class="v sb small">$ {{ number_format($cargo, 2) }}</td>
          </tr>
          <tr>
            <td class="k mut small">Pagos / abonos</td>
            <td class="v sb small">$ {{ number_format($abono, 2) }}</td>
          </tr>

          @if($showPrev)
            <tr>
              <td class="k mut small">Saldo anterior pendiente ({{ $prevLabelSafe }})</td>
              <td class="v sb small">$ {{ number_format($prevBalance, 2) }}</td>
            </tr>
          @endif

          <tr>
            <td class="k mut small">Saldo del periodo</td>
            <td class="v sb small">$ {{ number_format($currentDue, 2) }}</td>
          </tr>

          <tr>
            <td class="k mut small">Total a pagar</td>
            <td class="v b small">$ {{ number_format($totalPagar, 2) }}</td>
          </tr>
        </table>


      </div>

      <div class="sp12"></div>

      <div class="card">
        <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
          <tr>
            <td class="idLbl">ID Cuenta:</td>
            <td class="r mono idVal">{{ $idCuentaTxt }}</td>
          </tr>
        </table>

        {{-- Mejora: session id si existe --}}
        @if($sessionId !== '')
          <div class="sp8"></div>
          <div class="mut xs">Stripe session:</div>
          <div class="mono xs" style="white-space:normal; word-wrap:break-word; word-break:break-all; line-height:1.2;">
            {{ $sessionId }}
          </div>

        @endif
      </div>
    </td>
  </tr>
</table>

<div class="sp16"></div>

{{-- CLIENTE (izq) + PERIODO/FECHAS (der) --}}
<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
  <tr>
    <td width="52%" style="vertical-align:top; padding-right:12px;">
      <div class="card">
        <div class="sb" style="font-size:14px;">{{ $clienteRazon }}</div>
        <div class="sp10"></div>

        @if($line1)<div>{{ $line1 }}</div>@endif
        @if($line2)<div>{{ $line2 }}</div>@endif
        @if($line3)<div>{{ $line3 }}</div>@endif
        @if($line4)<div>{{ $line4 }}</div>@endif
        <div>{{ $dirPais !== '' ? $dirPais : 'México' }}</div>

        <div class="sp10"></div>

        <div><span class="b">RFC:</span> <span class="mono">{{ $clienteRfc }}</span></div>
        <div><span class="b">Email:</span> {{ $clienteEmail ?: '—' }}</div>
      </div>
    </td>

    <td width="48%" style="vertical-align:top; padding-left:12px;">
      <div class="card">
        <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
          <tr>
            <td class="b" style="font-size:14px;">Periodo:</td>
            <td class="r b" style="font-size:18px;">{{ $periodLabel }}</td>
          </tr>
        </table>

        <div class="sp14"></div>

        <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
          <tr>
            <td class="mut">Fecha de impresión</td>
            <td class="r b">{{ $printedAt->translatedFormat('d \\d\\e F Y') }}</td>
          </tr>
          <tr>
            <td class="mut">Límite de pago</td>
            <td class="r b">{{ $dueAt->translatedFormat('d \\d\\e F Y') }}</td>
          </tr>
        </table>

        {{-- Mejora: enlace de pago visible (texto) --}}
        @if($payUrl !== '')
          <div class="sp12"></div>
          <div class="b small">Enlace de pago:</div>

          <div style="margin-top:6px; padding:8px 10px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb;">
            {{-- DomPDF-safe: forzar wrap de strings largos (Stripe URL) --}}
            <div class="linkMono" style="
              white-space: normal;
              word-wrap: break-word;
              word-break: break-all;
              line-height: 1.25;
              font-size: 9px;
            ">{{ $payUrl }}</div>
          </div>
        @endif
      </div>
    </td>
  </tr>
</table>

<div class="sp18"></div>

{{-- DETALLE --}}
<div class="hSec" style="margin-left:12px; margin-right:12px; margin-bottom:8px;">
  Detalle de consumos
</div>

<div class="tblWrap" style="margin-left:12px; margin-right:12px;">
  <table class="tbl" cellpadding="0" cellspacing="0">
    <thead>
      <tr>
        <th width="46%">Servicio / Concepto</th>
        <th width="18%" class="r">Costo Unit</th>
        <th width="16%" class="c">Cantidad</th>
        <th width="20%" class="r">Subtotal</th>
      </tr>
    </thead>
    <tbody>
      @if(!empty($serviceItems))
        @foreach($serviceItems as $it)
          @php
            $row = is_array($it) ? (object)$it : $it;

            $name = (string)($row->service ?? $row->name ?? $row->servicio ?? $row->concepto ?? $row->title ?? 'Servicio mensual');
            $unit = (float)($row->unit_cost ?? $row->unit_price ?? $row->costo_unit ?? $row->costo ?? $row->importe ?? 0);
            $qty  = (float)($row->qty ?? $row->cantidad ?? 1);
            $sub  = (float)($row->subtotal ?? $row->total ?? ($unit * $qty));
          @endphp
          <tr>
            <td class="sb">{{ $name }}</td>
            <td class="r">$ {{ number_format($unit, 2) }}</td>
            <td class="c">{{ rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.') }}</td>
            <td class="r">$ {{ number_format($sub, 2) }}</td>
          </tr>
        @endforeach
      @else
        <tr>
          <td class="sb">Servicio mensual</td>
          <td class="r">$ {{ number_format($totalPagar, 2) }}</td>
          <td class="c">1</td>
          <td class="r">$ {{ number_format($totalPagar, 2) }}</td>
        </tr>
      @endif

      @for($i=0; $i<$padRows; $i++)
        <tr>
          <td>&nbsp;</td><td class="r">&nbsp;</td><td class="c">&nbsp;</td><td class="r">&nbsp;</td>
        </tr>
      @endfor
    </tbody>
  </table>
</div>

<div class="sp16"></div>

{{-- BOTTOM: 3 cards (PAGA EN LINEA + QR + desglose) --}}
<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
  <tr>
    {{-- Izquierda: PAGA EN LINEA (como maqueta) --}}
    <td width="38%" style="vertical-align:top; padding-right:10px;">
      <div class="card">
        <div class="payTitle">PAGA EN LINEA</div>

        <div class="smallNote">
          <span class="b">Ingresa a tu cuenta en</span><br>
          <span class="b">pactopia360.com/cliente/login</span>
        </div>

        <div class="sp12"></div>

        <div style="background:#8b0000; color:#ffffff; border-radius:10px; padding:10px 12px; font-size:10px; font-weight:900; text-align:center;">
          Si no tienes tus credenciales de acceso solicita a:<br>
          soporte@pactopia.com
        </div>
      </div>
    </td>

    {{-- Centro: QR --}}
    <td width="30%" style="vertical-align:top; padding:0 10px;">
      <div class="card" style="text-align:center;">
        <div class="b" style="font-size:11px; margin-bottom:10px;">
          Escanea el QR para<br>pago en línea
        </div>

        <div class="qrBox">
          @if($qrDataUri)
            <img src="{{ $qrDataUri }}" alt="QR">
          @elseif($qrUrl)
            <img src="{{ $qrUrl }}" alt="QR">
          @else
            <div style="padding-top:66px;" class="mut b">QR no disponible</div>
          @endif
        </div>

        @if($payUrl === '')
          <div class="sp10"></div>
          <div class="mut xs">QR se habilita cuando existe enlace de pago.</div>
        @endif
      </div>
    </td>

    {{-- Derecha: desglose --}}
    <td width="32%" style="vertical-align:top; padding-left:10px;">
      <div class="card">
        <div class="payTitle">Desglose del importe a pagar</div>
        <div class="sp10"></div>

        <table class="kv" cellpadding="0" cellspacing="0">
          @if($showPrev)
            <tr>
              <td class="k sb">Saldo anterior pendiente:</td>
              <td class="v">$ {{ number_format($prevBalance, 2) }}</td>
            </tr>
            <tr>
              <td class="k sb">Saldo del periodo:</td>
              <td class="v">$ {{ number_format($currentDue, 2) }}</td>
            </tr>
            <tr><td colspan="2" style="height:8px;"></td></tr>
          @endif

          <tr>
            <td class="k sb">Subtotal:</td>
            <td class="v">$ {{ number_format($subtotal, 2) }}</td>
          </tr>
          <tr>
            <td class="k sb">IVA 16%:</td>
            <td class="v">$ {{ number_format($iva, 2) }}</td>
          </tr>
          <tr>
            <td class="k sb">Total:</td>
            <td class="v b">$ {{ number_format($totalPagar, 2) }}</td>
          </tr>
        </table>


        <div class="hr"></div>

        <div class="smallNote">
          <span class="b">Nota:</span> Si ya realizaste tu pago, el saldo puede tardar unos minutos en reflejarse.
        </div>
      </div>
    </td>
  </tr>
</table>

<div class="sp14"></div>
<div class="xs mut">
  Documento informativo · Periodo {{ $periodSafe }} · Cuenta {{ $accountIdNum ?: '—' }} · Generado {{ $printedAt->format('Y-m-d H:i') }}
</div>

</body>
</html>
