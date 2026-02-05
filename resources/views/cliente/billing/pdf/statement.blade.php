{{-- resources/views/cliente/billing/pdf/statement.blade.php (P360 · PDF Estado de cuenta · MOCK GRID · vSOT-MOCK1) --}}
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Estado de cuenta {{ $period ?? '—' }}</title>

  @php
    $pdfCssPath = public_path('assets/client/pdf/statement.css');
    $pdfCss = '';
    try {
      if (is_file($pdfCssPath) && is_readable($pdfCssPath)) {
        $raw = file_get_contents($pdfCssPath);
        $pdfCss = is_string($raw) ? $raw : '';
      }
    } catch (\Throwable $e) { $pdfCss = ''; }

    // ✅ Debug visible: hash CSS para confirmar que se está aplicando el CSS actual
    $cssHash6 = $pdfCss !== '' ? substr(md5($pdfCss), 0, 6) : 'nocss';

  @endphp

  <style>{!! $pdfCss !!}</style>
</head>
<body>
<div class="pdfPage">
  <div class="pageGutter">


@php
  use Illuminate\Support\Carbon;
  use Illuminate\Support\Str;

  $f = function ($n): float {
    if ($n === null) return 0.0;
    if (is_int($n) || is_float($n)) return (float)$n;
    if (is_string($n)) {
      $s = trim($n);
      if ($s === '') return 0.0;
      $s = str_replace(['$',',','MXN','mxn',' '], '', $s);
      return is_numeric($s) ? (float)$s : 0.0;
    }
    return is_numeric($n) ? (float)$n : 0.0;
  };

  $periodSafe = (string)($period ?? '—');
  $accountObj = (isset($account) && is_object($account)) ? $account : (object)[];

  $cargo = $f($cargo ?? 0);
  $abono = $f($abono ?? 0);

  $prevPeriod      = (string)($prev_period ?? '');
  $prevPeriodLabel = (string)($prev_period_label ?? $prevPeriod);

  $prevBalanceFallback = $f($prev_balance ?? 0);
  $prevBalance = $prevBalanceFallback;

  // ⚠️ OJO: NO dependemos de dompdf enable_remote; si hay URL QR intentamos bajarlo en servidor -> dataURI
  try {
    $prevPeriodOk = is_string($prevPeriod) && preg_match('/^\d{4}-\d{2}$/', $prevPeriod);
    if ($prevPeriodOk) {
      $accountIdRaw2 = $account_id ?? ($accountObj->id ?? 0);
      $accountIdNum2 = is_numeric($accountIdRaw2) ? (int)$accountIdRaw2 : 0;

      if ($accountIdNum2 > 0) {
        $admConn = 'mysql_admin';
        if (\Illuminate\Support\Facades\Schema::connection($admConn)->hasTable('payments')) {
          $cols = \Illuminate\Support\Facades\Schema::connection($admConn)->getColumnListing('payments');
          $lc   = array_map('strtolower', $cols);
          $has  = static fn (string $c): bool => in_array(strtolower($c), $lc, true);

          if ($has('amount') && $has('status') && $has('account_id')) {
            $validDebt = ['pending','expired','unpaid'];
            $exclude   = ['paid','cancelled','canceled'];

            $q = \Illuminate\Support\Facades\DB::connection($admConn)->table('payments')
              ->where('account_id', $accountIdNum2);

            if ($has('period')) $q->where('period', $prevPeriod);

            $q->whereIn('status', $validDebt)->whereNotIn('status', $exclude);

            $prevCents   = (int) $q->sum('amount');
            $prevBalance = round($prevCents / 100, 2);
          }
        }
      }
    }
  } catch (\Throwable $e) { $prevBalance = $prevBalanceFallback; }

  $currentDue = $f($current_period_due ?? null);
  if ($currentDue <= 0.00001) $currentDue = round(max(0.0, $cargo - $abono), 2);
  else $currentDue = round(max(0.0, $currentDue), 2);

  $showPrev   = ($prevBalance > 0.00001);
  $totalPagar = round($currentDue + ($showPrev ? $prevBalance : 0.0), 2);

  $prevLabelSafe = trim((string)($prevPeriodLabel ?? ''));
  if ($prevLabelSafe === '') $prevLabelSafe = trim((string)($prevPeriod ?? ''));
  if ($prevLabelSafe === '') $prevLabelSafe = 'Periodo anterior';

  // Status UI
  $statusLabel = 'Pendiente';
  $statusBadge = 'warn';
  if ($totalPagar <= 0.00001) {
    $statusLabel = ($cargo > 0.00001 || $abono > 0.00001) ? 'Pagado' : 'Sin movimientos';
    $statusBadge = ($statusLabel === 'Pagado') ? 'ok' : 'dim';
  } else {
    $statusLabel = ($abono > 0.00001) ? 'Parcial' : 'Pendiente';
    $statusBadge = 'warn';
  }

  $tarifaLabel = (string)($tarifa_label ?? '');
  $tarifaPill  = (string)($tarifa_pill ?? 'dim');
  if (!in_array($tarifaPill, ['info','warn','ok','dim','bad'], true)) $tarifaPill = 'dim';

  $ivaRate  = 0.16;
  $subtotal = $totalPagar > 0 ? round($totalPagar/(1+$ivaRate), 2) : 0.0;
  $iva      = $totalPagar > 0 ? round($totalPagar - $subtotal, 2) : 0.0;

  $accountIdRaw = $account_id ?? ($accountObj->id ?? 0);
  $accountIdNum = is_numeric($accountIdRaw) ? (int)$accountIdRaw : 0;
  // Mock: si quieres 00001 (5 dígitos) cambia 6 -> 5
  $idCuentaTxt  = $accountIdNum > 0 ? str_pad((string)$accountIdNum, 5, '0', STR_PAD_LEFT) : '—';

  $printedAt = ($generated_at ?? null) ? Carbon::parse($generated_at) : now();
  $dueAt     = ($due_at ?? null) ? Carbon::parse($due_at) : $printedAt->copy()->addDays(4);

  $rs1 = trim((string)($razon_social ?? ''));
  $rs2 = trim((string)($accountObj->razon_social ?? ''));
  $nm1 = trim((string)($name ?? ''));
  $nm2 = trim((string)($accountObj->name ?? ''));
  $em1 = trim((string)($email ?? ''));
  $em2 = trim((string)($accountObj->email ?? ''));

  $clienteRazon = $rs1 !== '' ? $rs1
    : ($rs2 !== '' ? $rs2
    : ($nm1 !== '' ? $nm1
    : ($nm2 !== '' ? $nm2
    : ($em1 !== '' ? $em1
    : ($em2 !== '' ? $em2 : 'Cliente')))));

  $clienteRfc   = (string)($rfc ?? ($accountObj->rfc ?? '—'));
  $clienteEmail = (string)($email ?? ($accountObj->email ?? '—'));
  $clientePlan  = (string)($plan ?? ($accountObj->plan ?? ($accountObj->plan_actual ?? '—')));
  $modoCobro    = (string)($modo_cobro ?? ($accountObj->modo_cobro ?? ($accountObj->billing_cycle ?? '—')));

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

  $addrParts = [];
  if(!empty($line1)) $addrParts[] = $line1;
  if(!empty($line2)) $addrParts[] = $line2;
  if(!empty($line3)) $addrParts[] = $line3;
  if(!empty($line4)) $addrParts[] = $line4;
  $addrParts[] = ($dirPais !== '' ? $dirPais : 'México');
  $addrInline = implode(' · ', array_filter($addrParts, fn($v)=>trim((string)$v) !== ''));

  // Logo
  $logoDataUri = $logo_data_uri ?? null;
  $logoFilePath = public_path('assets/client/Logo1Pactopia.png');
  if (!$logoDataUri && is_file($logoFilePath)) {
    try {
      $bin = file_get_contents($logoFilePath);
      if ($bin !== false && strlen($bin) > 10) $logoDataUri = 'data:image/png;base64,'.base64_encode($bin);
    } catch (\Throwable $e) {}
  }

  // QR
  $qrDataUri = $qr_data_uri ?? null;
  $qrUrl     = $qr_url ?? null;

  $payUrl    = (string)($pay_url ?? '');
  $sessionId = (string)($stripe_session_id ?? '');

  $hasPay = trim($payUrl) !== '';
  $payUrlShort = $hasPay ? Str::limit($payUrl, 150, '…') : '';

    // ======================================================
  // Total en letras REAL (ES-MX) DomPDF-safe
  // ======================================================
  $numToWordsEs = function (int $n) use (&$numToWordsEs): string {
    $n = (int)$n;
    if ($n === 0) return 'cero';

    $units = [
      '', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve',
      'diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve',
      'veinte', 'veintiuno', 'veintidós', 'veintitrés', 'veinticuatro', 'veinticinco', 'veintiséis', 'veintisiete', 'veintiocho', 'veintinueve'
    ];
    $tens = ['', '', 'veinte', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];
    $hundreds = ['', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos', 'seiscientos', 'setecientos', 'ochocientos', 'novecientos'];

    $out = [];

    $chunk = function(int $x) use ($units, $tens, $hundreds): string {
      $x = (int)$x;
      if ($x === 0) return '';
      if ($x <= 29) return $units[$x];
      if ($x === 100) return 'cien';

      $c = intdiv($x, 100);
      $r = $x % 100;

      $s = '';
      if ($c > 0) $s .= $hundreds[$c].($r ? ' ' : '');

      if ($r <= 29) {
        $s .= $units[$r];
        return trim($s);
      }

      $d = intdiv($r, 10);
      $u = $r % 10;

      if ($d === 2 && $u === 0) {
        $s .= 'veinte';
      } else {
        $s .= $tens[$d];
        if ($u > 0) $s .= ' y '.$units[$u];
      }
      return trim($s);
    };

    // Soporte hasta 999,999,999
    $millones = intdiv($n, 1000000);
    $n = $n % 1000000;
    $miles = intdiv($n, 1000);
    $resto = $n % 1000;

    if ($millones > 0) {
      if ($millones === 1) $out[] = 'un millón';
      else $out[] = $chunk($millones).' millones';
    }

    if ($miles > 0) {
      if ($miles === 1) $out[] = 'mil';
      else $out[] = $chunk($miles).' mil';
    }

    if ($resto > 0) $out[] = $chunk($resto);

    $res = trim(implode(' ', array_filter($out)));
    // Ajuste: "uno" -> "un" cuando va solo (ej: "uno mil" no existe, ya se cubre; pero "uno pesos" sí)
    $res = preg_replace('/\buno\b$/u', 'un', $res);

    return $res;
  };

  $pesosInt = (int) floor($totalPagar + 1e-9);
  $cent     = (int) round(($totalPagar - $pesosInt) * 100);
  if ($cent >= 100) { $cent = 0; $pesosInt++; }

  $pesosTxt = $numToWordsEs($pesosInt);
  // “un peso” singular
  $moneda   = ($pesosInt === 1) ? 'peso' : 'pesos';

  $totalLetrasCalc = trim($pesosTxt).' '.$moneda.' '.str_pad((string)$cent, 2, '0', STR_PAD_LEFT).'/100 MN';

  // si viene override del backend, respétalo
  $totalLetras = (string)($total_letras ?? $totalLetrasCalc);

  // Period label
  $periodLabel = $periodSafe;
  try {
    if (preg_match('/^\d{4}-\d{2}$/', $periodSafe)) {
      $periodLabel = Carbon::parse($periodSafe.'-01')->translatedFormat('F Y');
      $periodLabel = Str::ucfirst($periodLabel);
    }
  } catch (\Throwable $e) {}

  // Service items
  $serviceItems = [];
  $si = $service_items ?? null;
  if ($si instanceof \Illuminate\Support\Collection) $si = $si->all();

  if (is_array($si) && count($si) > 0) {
    $serviceItems = $si;
  } else {
    $consumosRaw = $consumos ?? null;
    if ($consumosRaw instanceof \Illuminate\Support\Collection) $consumosRaw = $consumosRaw->all();
    $serviceItems = is_array($consumosRaw) ? $consumosRaw : [];
  }
  if (!is_array($serviceItems)) $serviceItems = [];

  // Insert saldo anterior como línea (si aplica)
  if ($showPrev) {
    array_unshift($serviceItems, [
      'service'   => 'Saldo anterior (' . $prevLabelSafe . ')',
      'unit_cost' => round((float)$prevBalance, 2),
      'qty'       => 1,
      'subtotal'  => round((float)$prevBalance, 2),
    ]);
  }

  // Fill de tabla para ocupar alto sin romper footer (SOT)
  $rowsCount = count($serviceItems);
  $minRows   = ($rowsCount <= 1) ? 9 : (($rowsCount <= 3) ? 8 : (($rowsCount <= 6) ? 7 : 6));
  $padRows   = max(0, $minRows - $rowsCount);

@endphp

{{-- =========================================================
   ✅ TOP GRID (DomPDF-safe · 2 columnas · 1 sola fila)
   OBJETIVO:
   - Cliente justo debajo de datos Pactopia (llenar hueco)
   - ID Cuenta pegado debajo de Total (sp8)
   - Periodo pegado debajo de ID (sp8)
   ✅ FIX DomPDF: evitar múltiples <tr> apilados que “bajan” la col derecha
   ========================================================= --}}
<table class="grid" cellpadding="0" cellspacing="0">
  <tr>
    {{-- =========================
       COLUMNA IZQUIERDA
       ========================= --}}
    <td class="gL" style="vertical-align:top;">
      <div class="hLogo">
        @if($logoDataUri)
          <img src="{{ $logoDataUri }}" class="brandLogo" alt="PACTOPIA360">
        @else
          <div class="b" style="font-size:22px;">PACTOPIA360</div>
        @endif
      </div>

      <div class="hTitle">Estado de cuenta</div>

      {{-- Datos Pactopia --}}
      <div class="pactopiaMini">
        <div class="brandNameSm">Pactopia SAPI de CV</div>
        <div class="brandBlockSm">
          <div>Av. Anillo Periférico 7461 Depto. 3.</div>
          <div>Rinconada Coapa 1ra Sección</div>
          <div>Tlalpan C.P. 14330</div>
          <div>CDMX</div>
          <div>México</div>
          <div><span class="b">RFC</span> PAC251010CS1</div>
        </div>
      </div>

      {{-- ✅ Cliente justo debajo (llenar el hueco) --}}
      <div class="sp8"></div>

      <div class="card cardClient">
        <div class="sb clientName">{{ $clienteRazon ?: '—' }}</div>

        <div class="mut clientRow">México</div>

        <div class="clientRow">
          <span class="mut">RFC:</span>
          <span class="b mono">{{ $clienteRfc ?: '—' }}</span>
        </div>

        <div class="clientRow">
          <span class="mut">Correo electrónico:</span>
          <span class="b" style="white-space:normal; word-break:break-word;">{{ $clienteEmail ?: '—' }}</span>
        </div>
      </div>
    </td>

    {{-- =========================
       COLUMNA DERECHA (TODO APILADO)
       ========================= --}}
    <td class="gR" style="vertical-align:top; text-align:left;">
      {{-- TOTAL --}}
      <div class="card cardTotal">
        <div class="totalTop">
          <span class="totalCardLbl">Total a pagar:</span>
          <span class="totalMoneyInline">
            <span class="moneySign">$</span>
            <span class="totalAmt">{{ number_format($totalPagar, 2) }}</span>
          </span>
        </div>
        <div class="totalWords">{{ $totalLetras }}</div>
      </div>

      {{-- ✅ misma separación que pides --}}
      <div class="sp8"></div>

      {{-- ID CUENTA (pegado debajo de Total) --}}
      <div class="card cardId">
        <div class="idBox">
          <div class="idLbl2">ID Cuenta:</div>
          <div class="idVal2 mono">{{ $idCuentaTxt }}</div>
        </div>

        @if(trim($sessionId) !== '')
          <div class="sp6"></div>
          <div class="mut xs">Stripe:</div>
          <div class="mono xs" style="white-space:normal; word-break:break-all; line-height:1.15;">
            {{ \Illuminate\Support\Str::limit($sessionId, 70, '…') }}
          </div>
        @endif
      </div>

      {{-- ✅ misma separación que Total->ID --}}
      <div class="sp8"></div>

      {{-- PERIODO (justo debajo de ID) --}}
      <div class="card cardPeriodo">
        <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
          <tr>
            <td class="b" style="font-size:12px;">Periodo:</td>
            <td class="r b" style="font-size:16px;">{{ $periodLabel }}</td>
          </tr>
        </table>

        <div class="sp6"></div>

        <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
          <tr><td class="mut">Impresión</td><td class="r b">{{ $printedAt->translatedFormat('d \\d\\e M Y') }}</td></tr>
          <tr><td class="mut">Límite</td><td class="r b">{{ $dueAt->translatedFormat('d \\d\\e M Y') }}</td></tr>
          <tr><td class="mut">Estatus</td><td class="r b">{{ $statusLabel }}</td></tr>
        </table>

        @if($hasPay)
          <div class="sp6"></div>
          <div class="b xs">Enlace de pago:</div>
          <div class="payLinkBox"><div class="linkMono">{{ $payUrlShort }}</div></div>
        @endif
      </div>
    </td>
  </tr>
</table>

<div class="sp8"></div>

<div class="hSec">Detalle de consumos</div>

<div class="tblWrap">
  <table class="tbl" cellpadding="0" cellspacing="0">
    <thead>
      <tr>
        <th width="54%">Servicio / Concepto</th>
        <th width="16%" class="r">Costo</th>
        <th width="10%" class="c">Cantidad</th>
        <th width="20%" class="r">Subtotal</th>
      </tr>
    </thead>
    <tbody>
      @if(!empty($serviceItems))
        @foreach($serviceItems as $it)
          @php
            $row  = is_array($it) ? (object)$it : (is_object($it) ? $it : (object)[]);
            $name = (string)($row->service ?? $row->name ?? $row->servicio ?? $row->concepto ?? $row->title ?? 'Servicio');
            $unit = $f($row->unit_cost ?? $row->unit_price ?? $row->costo_unit ?? $row->costo ?? $row->importe ?? 0);
            $qty  = $f($row->qty ?? $row->cantidad ?? 1);
            if ($qty <= 0) $qty = 1;
            $sub  = $f($row->subtotal ?? $row->total ?? ($unit * $qty));
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

      {{-- ✅ Pad para llenar alto sin empujar footer --}}
      @for($i=0; $i<$padRows; $i++)
        <tr class="rowPad">
          <td>&nbsp;</td><td class="r">&nbsp;</td><td class="c">&nbsp;</td><td class="r">&nbsp;</td>
        </tr>
      @endfor
    </tbody>
  </table>
</div>

<div class="sp8"></div>

{{-- =========================================================
   ✅ FOOTER (como mock): Formas de pago | QR | Desglose final
   ========================================================= --}}
<div class="noBreak">
  <table class="footerGrid" cellpadding="0" cellspacing="0">
    <tr>
      <td class="fL" style="vertical-align:top;">
        <div class="card cardBottom">
          <div class="payTitle">PAGA EN LÍNEA</div>
          <div class="smallNote"><span class="b">Ingresa:</span> pactopia360.com/cliente/login</div>
          <div class="sp8"></div>
          <div class="alertRed">Si no tienes credenciales: soporte@pactopia.com</div>
        </div>
      </td>

      <td class="fC" style="vertical-align:top;">
        <div class="card cardBottom" style="text-align:center;">
          <div class="b" style="font-size:10px; margin-bottom:6px;">Escanea el QR para pago en línea</div>

          @php
            $qrImg = $qrDataUri ?? null;

            if (!$qrImg && !empty($qrUrl) && is_string($qrUrl)) {
              $u = trim($qrUrl);
              $tryLocal = null;

              if (str_starts_with($u, '/')) $tryLocal = public_path(ltrim($u, '/'));
              elseif (!preg_match('#^https?://#i', $u)) $tryLocal = public_path(ltrim($u, '/'));

              if ($tryLocal && is_file($tryLocal) && is_readable($tryLocal)) {
                try {
                  $bin = file_get_contents($tryLocal);
                  if ($bin !== false && strlen($bin) > 10) $qrImg = 'data:image/png;base64,' . base64_encode($bin);
                } catch (\Throwable $e) {}
              } else {
                if (preg_match('#^https?://#i', $u)) {
                  try {
                    $ctx = stream_context_create([
                      'http' => ['timeout' => 3],
                      'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
                    ]);
                    $bin = @file_get_contents($u, false, $ctx);
                    if ($bin !== false && strlen($bin) > 10) $qrImg = 'data:image/png;base64,' . base64_encode($bin);
                  } catch (\Throwable $e) {}
                }
              }
            }
          @endphp

          @if($qrImg && $hasPay)
            <img src="{{ $qrImg }}" alt="QR" class="qrImg">
          @else
            <div class="mut b" style="padding:26px 0;">QR no disponible</div>
            <div class="mut xs">Se habilita con enlace.</div>
          @endif
        </div>
      </td>

      <td class="fR" style="vertical-align:top;">
        <div class="card cardBottom">
          <div class="payTitle">Desglose del importe</div>

          <table class="kv" cellpadding="0" cellspacing="0">
            @if($showPrev)
              <tr><td class="k sb">Saldo anterior:</td><td class="v">$ {{ number_format($prevBalance, 2) }}</td></tr>
              <tr><td class="k sb">Periodo:</td><td class="v">$ {{ number_format($currentDue, 2) }}</td></tr>
            @endif
            <tr><td class="k sb">Subtotal:</td><td class="v">$ {{ number_format($subtotal, 2) }}</td></tr>
            <tr><td class="k sb">IVA 16%:</td><td class="v">$ {{ number_format($iva, 2) }}</td></tr>
            <tr><td class="k sb">Total:</td><td class="v b">$ {{ number_format($totalPagar, 2) }}</td></tr>
          </table>

          <div class="hr"></div>
          <div class="smallNote"><span class="b">Nota:</span> El reflejo puede tardar minutos.</div>
        </div>
      </td>
    </tr>
  </table>

  <div class="footerLine xs mut">
    Documento informativo · Periodo {{ $periodSafe }} · Cuenta {{ $accountIdNum ?: '—' }} · Generado {{ $printedAt->format('Y-m-d H:i') }} · CSS {{ $cssHash6 }}
  </div>

</div>

  </div> {{-- /pageGutter --}}
</div> {{-- /pdfPage --}}
</body>
</html>

