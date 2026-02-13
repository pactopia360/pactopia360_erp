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

  // ======================================================
  // ✅ Helpers numéricos (DomPDF-safe)
  // - Acepta: null, int/float, strings "$1,234.50 MXN"
  // - Regresa float >= 0 (si quieres permitir negativos, quita max(0,...))
  // ======================================================
  $f = function ($n): float {
    if ($n === null) return 0.0;

    if (is_int($n) || is_float($n)) {
      $v = (float)$n;
      return is_finite($v) ? $v : 0.0;
    }

    if (is_string($n)) {
      $s = trim($n);
      if ($s === '') return 0.0;

      // Limpieza común: $, comas, MXN, espacios
      $s = str_ireplace(['mxn'], '', $s);
      $s = str_replace(['$', ',', ' '], '', $s);

      // Soporta formato "1.234,56" (EU) si llega así: convierte a "1234.56"
      // Heurística simple: si tiene coma y punto, asume coma decimal (EU) y remueve puntos
      if (str_contains($s, ',') && str_contains($s, '.')) {
        // "1.234,56" => "1234.56"
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
      } elseif (str_contains($s, ',') && !str_contains($s, '.')) {
        // "1234,56" => "1234.56"
        $s = str_replace(',', '.', $s);
      }

      if (!is_numeric($s)) return 0.0;
      $v = (float)$s;
      return is_finite($v) ? $v : 0.0;
    }

    if (is_object($n)) {
      // Si llega un objeto tipo Money/DTO con ->amount
      if (isset($n->amount) && is_numeric($n->amount)) {
        $v = (float)$n->amount;
        return is_finite($v) ? $v : 0.0;
      }
      return 0.0;
    }

    if (is_numeric($n)) {
      $v = (float)$n;
      return is_finite($v) ? $v : 0.0;
    }

    return 0.0;
  };

  $r2 = function (float $n): float {
    return round($n, 2);
  };

  $periodSafe = (string)($period ?? '—');
  $accountObj = (isset($account) && is_object($account)) ? $account : (object)[];

  $cargo = $f($cargo ?? 0);
  $abono = $f($abono ?? 0);

  $prevPeriod      = (string)($prev_period ?? '');
  $prevPeriodLabel = (string)($prev_period_label ?? $prevPeriod);

  // ======================================================
  // ✅ Saldo anterior (SOT = billing_statements / backend)
  // - NUNCA lo calcules con payments.
  // ======================================================
  $prevBalanceFallback = $f($prev_balance ?? 0);
  $prevStatusRaw = strtolower(trim((string)($prev_status ?? $prev_statement_status ?? '')));
  $prevIsPaid = in_array($prevStatusRaw, ['paid','pagado','paid_ok','pago'], true);

  if (isset($prev_is_paid)) {
    $prevIsPaid = (bool)$prev_is_paid;
  }

  $prevBalance = $prevIsPaid ? 0.0 : $prevBalanceFallback;

  // Fallback adicional (compat)
  if ($prevBalance <= 0.00001 && !$prevIsPaid) {
    $prevBalanceAlt = $f($prev_statement_saldo ?? $prev_saldo ?? null);
    if ($prevBalanceAlt > 0.00001) $prevBalance = $prevBalanceAlt;
  }

  // ✅ Bandera para UI: mostrar línea del periodo anterior aunque esté pagado (sin sumar)
  $showPrevLine = false;
  if (is_string($prevPeriod) && trim($prevPeriod) !== '') {
    $showPrevLine = true;
  } elseif (is_string($prevPeriodLabel) && trim($prevPeriodLabel) !== '') {
    $showPrevLine = true;
  } elseif ($prevBalanceFallback > 0.00001 || $prevIsPaid) {
    $showPrevLine = true;
  }

  // ======================================================
  // ✅ Due del periodo actual (SOT)
  // Orden de preferencia:
  // 1) current_period_due
  // 2) saldo/amount_due/total/charge del statement actual
  // 3) cargo - abono
  // ======================================================
  $candidates = [
    $current_period_due ?? null,
    $saldo_actual ?? null,
    $saldo ?? null,
    $amount_due ?? null,
    $statement_saldo ?? null,
    $statement_total ?? null,
    $total ?? null,
    $charge ?? null,
    $cargo ?? null,
  ];

  $currentDue = 0.0;
  foreach ($candidates as $cand) {
    $v = $f($cand);
    if ($v > 0.00001) { $currentDue = $v; break; }
  }

  if ($currentDue <= 0.00001) {
    $currentDue = $r2(max(0.0, $cargo - $abono));
  } else {
    $currentDue = $r2(max(0.0, $currentDue));
  }

  // ✅ sumar saldo anterior SOLO si hay deuda real
  $showPrev   = ($prevBalance > 0.00001);
  $totalPagar = $r2($currentDue + ($showPrev ? $prevBalance : 0.0));

  $prevLabelSafe = trim((string)($prevPeriodLabel ?? ''));
  if ($prevLabelSafe === '') $prevLabelSafe = trim((string)($prevPeriod ?? ''));
  if ($prevLabelSafe === '') $prevLabelSafe = 'Periodo anterior';

  // Status UI
  $statusLabel = 'Pendiente';
  $statusBadge = 'warn';
  if ($totalPagar <= 0.00001) {
    $statusLabel = 'Pagado';
    $statusBadge = 'ok';
  } else {
    $statusLabel = ($abono > 0.00001) ? 'Parcial' : 'Pendiente';
    $statusBadge = 'warn';
  }

  $tarifaLabel = (string)($tarifa_label ?? '');
  $tarifaPill  = (string)($tarifa_pill ?? 'dim');
  if (!in_array($tarifaPill, ['info','warn','ok','dim','bad'], true)) $tarifaPill = 'dim';

  // IVA: por defecto 16%, pero si viene iva_rate del backend lo respetamos (0.0 - 0.99)
  $ivaRate = $f($iva_rate ?? 0.16);
  if ($ivaRate <= 0 || $ivaRate >= 0.99) $ivaRate = 0.16;

  $subtotal = $totalPagar > 0 ? $r2($totalPagar / (1 + $ivaRate)) : 0.0;
  $iva      = $totalPagar > 0 ? $r2($totalPagar - $subtotal) : 0.0;

  // ✅ divisor sin IVA (re-usable)
  $divNoIva = 1 + (float)$ivaRate;
  if ($divNoIva <= 0.00001) $divNoIva = 1.16;

  // ======================================================
  // ✅ ID Cliente NO consecutivo (visible al cliente)
  // Formato: P{YYYY}-{PUBLIC}
  // PUBLIC se toma de:
  // - $public_id (si el controller lo manda)
  // - $accountObj->public_id
  // - $accountObj->meta['public_id'] (meta JSON/string/array/object)
  // Fallback temporal: hash del account_id (mejor setearlo en backend)
  // ======================================================
  $accountIdRaw = $account_id ?? ($accountObj->id ?? 0);
  $accountIdNum = is_numeric($accountIdRaw) ? (int)$accountIdRaw : 0;

  // Año del periodo (si viene 2026-02 => 2026)
  $yearForId = '';
  try {
    if (preg_match('/^\d{4}-\d{2}$/', $periodSafe)) $yearForId = (string) substr($periodSafe, 0, 4);
  } catch (\Throwable $e) { $yearForId = ''; }

  // printedAt se declara más abajo; usa fallback seguro aquí
  if ($yearForId === '') {
    $yearForId = (string) date('Y');
  }

  // Public ID (no secuencial)
  $publicId = trim((string)($public_id ?? ''));
  if ($publicId === '') {
    try {
      $publicId = trim((string)($accountObj->public_id ?? ''));
    } catch (\Throwable $e) { $publicId = ''; }
  }

  if ($publicId === '' && isset($accountObj->meta)) {
    try {
      $m = $accountObj->meta;

      if (is_string($m)) {
        $j = json_decode($m, true);
        if (is_array($j)) $publicId = trim((string)($j['public_id'] ?? ''));
      } elseif (is_array($m)) {
        $publicId = trim((string)($m['public_id'] ?? ''));
      } elseif (is_object($m)) {
        $publicId = trim((string)($m->public_id ?? ''));
      }
    } catch (\Throwable $e) { $publicId = ''; }
  }

  // Fallback temporal (NO ideal): hash estable derivado del ID (evita que se vea consecutivo)
  if ($publicId === '' && $accountIdNum > 0) {
    $publicId = substr(strtoupper(md5('P360-'.$accountIdNum)), 0, 10);
  }

  $idCuentaTxt = ($publicId !== '')
    ? ('P' . $yearForId . '-' . $publicId)
    : '—';

  // ======================================================
  // Fechas (aquí ya tenemos printedAt "real")
  // ======================================================
  $printedAt = ($generated_at ?? null) ? Carbon::parse($generated_at) : now();
  $dueAt     = ($due_at ?? null) ? Carbon::parse($due_at) : $printedAt->copy()->addDays(4);

  // Si yearForId no se pudo por period, mejorarlo con printedAt real
  if ($yearForId === '') {
    try { $yearForId = (string) $printedAt->format('Y'); } catch (\Throwable $e) { $yearForId = (string) date('Y'); }
    if ($publicId !== '') $idCuentaTxt = 'P' . $yearForId . '-' . $publicId;
  }

  // ✅ Nombre mostrado del cliente (nunca dependas de $name por colisión con items)
  $rs1 = trim((string)($razon_social ?? ''));
  $rs2 = trim((string)($accountObj->razon_social ?? ''));

  $nm1 = trim((string)(
    $accountName
    ?? ($accountObj->razon_social ?? null)
    ?? ($accountObj->name ?? null)
    ?? ($accountObj->nombre ?? null)
    ?? ($accountObj->empresa ?? null)
    ?? ((isset($account) && is_object($account)) ? ($account->razon_social ?? ($account->name ?? ($account->nombre ?? null))) : null)
    ?? ''
  ));

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

  $addrParts = [];
  if(!empty($line1)) $addrParts[] = $line1;
  if(!empty($line2)) $addrParts[] = $line2;
  if(!empty($line3)) $addrParts[] = $line3;
  if(!empty($line4)) $addrParts[] = $line4;
  $addrParts[] = ($dirPais !== '' ? $dirPais : 'México');
  $addrInline = implode(' · ', array_filter($addrParts, fn($v)=>trim((string)$v) !== ''));

  // Logo (data URI)
  $logoDataUri = $logo_data_uri ?? null;
  $logoFilePath = public_path('assets/client/Logo1Pactopia.png');
  if (!$logoDataUri && is_file($logoFilePath)) {
    try {
      $bin = file_get_contents($logoFilePath);
      if ($bin !== false && strlen($bin) > 10) $logoDataUri = 'data:image/png;base64,'.base64_encode($bin);
    } catch (\Throwable $e) {}
  }

  // QR / Pay
  $qrDataUri = $qr_data_uri ?? null;
  $qrUrl     = $qr_url ?? null;

  $payUrl    = (string)($pay_url ?? '');
  $sessionId = (string)($stripe_session_id ?? '');

  $hasPay = trim($payUrl) !== '';
  $payUrlShort = $hasPay ? Str::limit($payUrl, 150, '…') : '';

  // ======================================================
  // ✅ Total en letras REAL (ES-MX) DomPDF-safe
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

    $chunk = function(int $x) use ($units, $tens, $hundreds): string {
      $x = (int)$x;
      if ($x === 0) return '';
      if ($x <= 29) return $units[$x];
      if ($x === 100) return 'cien';

      $c = intdiv($x, 100);
      $r = $x % 100;

      $s = '';
      if ($c > 0) $s .= $hundreds[$c].($r ? ' ' : '');

      if ($r <= 29) return trim($s.$units[$r]);

      $d = intdiv($r, 10);
      $u = $r % 10;

      $s .= $tens[$d];
      if ($u > 0) $s .= ' y '.$units[$u];

      return trim($s);
    };

    $out = [];

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
    $res = preg_replace('/\buno\b$/u', 'un', $res);

    return $res;
  };

  $pesosInt = (int) floor($totalPagar + 1e-9);
  $cent     = (int) round(($totalPagar - $pesosInt) * 100);
  if ($cent >= 100) { $cent = 0; $pesosInt++; }

  $pesosTxt = $numToWordsEs($pesosInt);
  $moneda   = ($pesosInt === 1) ? 'peso' : 'pesos';
  $totalLetrasCalc = trim($pesosTxt).' '.$moneda.' '.str_pad((string)$cent, 2, '0', STR_PAD_LEFT).'/100 MN';

  $totalLetras = (string)($total_letras ?? $totalLetrasCalc);

  // Period label
  $periodLabel = $periodSafe;
  try {
    if (preg_match('/^\d{4}-\d{2}$/', $periodSafe)) {
      $periodLabel = Carbon::parse($periodSafe.'-01')->translatedFormat('F Y');
      $periodLabel = Str::ucfirst($periodLabel);
    }
  } catch (\Throwable $e) {}

  // ======================================================
  // ✅ Service label SOT + ciclo (mensual/anual)
  // ======================================================
  $cycleRaw = trim((string)($billing_cycle ?? $modoCobro ?? ($accountObj->billing_cycle ?? '') ?? ''));
  $cycleKey = strtolower($cycleRaw);

  $isAnnual = in_array($cycleKey, ['annual','anual','yearly','year','1y','12m'], true)
    || str_contains($cycleKey, 'anual')
    || str_contains($cycleKey, 'annual')
    || str_contains($cycleKey, 'year');

  $serviceLabel = trim((string)($service_label ?? ''));

  if ($serviceLabel === '') {
    $serviceLabel = $isAnnual ? 'Servicio anual' : 'Servicio mensual';
  }

  if ($isAnnual) {
    $serviceLabel = preg_replace('/\bmensual\b/iu', 'anual', $serviceLabel);
    $serviceLabel = preg_replace('/\bmonthly\b/iu', 'annual', $serviceLabel);
  }

  // ======================================================
  // ✅ Service items (normalización compat) - DOMPDF SAFE
  // - Prefiere service_items (moderno)
  // - Fallback a consumos (legacy)
  // - Normaliza: name, unit_price, qty, subtotal
  // ======================================================
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

  $serviceItems = array_values(array_map(function ($it) use ($f, $r2) {
    $row = is_array($it) ? $it : (is_object($it) ? (array)$it : []);

    // nombre (SOT: row['name'] o similares)
    $itemName = trim((string)($row['service'] ?? $row['name'] ?? $row['servicio'] ?? $row['concepto'] ?? $row['title'] ?? 'Servicio'));
    if ($itemName === '') $itemName = 'Servicio';

    // unit
    $unit = $f($row['unit_cost'] ?? $row['unit_price'] ?? $row['costo_unit'] ?? $row['costo'] ?? $row['importe'] ?? 0);

    // qty
    $qty  = $f($row['qty'] ?? $row['cantidad'] ?? 1);
    if ($qty <= 0.00001) $qty = 1.0;

    // subtotal (si viene, se respeta; si no, se calcula)
    $subRaw = $row['subtotal'] ?? $row['total'] ?? $row['importe_total'] ?? null;
    $sub    = $f($subRaw);
    if ($sub <= 0.00001) $sub = $unit * $qty;

    return [
      'name'       => $itemName,
      'unit_price' => $r2(max(0.0, $unit)),
      'qty'        => $r2(max(0.0, $qty)),
      'subtotal'   => $r2(max(0.0, $sub)),
    ];
  }, $serviceItems));

  // ✅ Insert saldo anterior como línea:
  // - Si hay deuda: suma y muestra monto
  // - Si está pagado: muestra "Pagado" pero NO suma
  if ($showPrevLine) {
    if ($prevIsPaid) {
      array_unshift($serviceItems, [
        'name'       => 'Saldo anterior (' . $prevLabelSafe . ') · Pagado',
        'unit_price' => 0,
        'qty'        => 1,
        'subtotal'   => 0,
      ]);
    } elseif ($showPrev) {
      $pb = $r2((float)$prevBalance);
      array_unshift($serviceItems, [
        'name'       => 'Saldo anterior (' . $prevLabelSafe . ')',
        'unit_price' => $pb,
        'qty'        => 1,
        'subtotal'   => $pb,
      ]);
    }
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
          <div class="idLbl2">ID Cliente:</div>
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

        {{-- Header: Periodo --}}
        <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
          <tr>
            <td class="b" style="font-size:12px;">Periodo:</td>
            <td class="r b" style="font-size:16px;">{{ $periodLabel }}</td>
          </tr>
        </table>

        <div class="sp6"></div>

        {{-- KV: Impresión / Límite / Estatus --}}
        <table class="periodKv" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
          <tr>
            <td class="mut">Impresión</td>
            <td class="r b">{{ $printedAt->translatedFormat('d \\d\\e M Y') }}</td>
          </tr>
          <tr>
            <td class="mut">Límite</td>
            <td class="r b">{{ $dueAt->translatedFormat('d \\d\\e M Y') }}</td>
          </tr>
          <tr>
            <td class="mut">Estatus</td>
            <td class="r b">{{ $statusLabel }}</td>
          </tr>
        </table>

        {{-- ✅ Spacer DomPDF-safe: reparte la altura del cuadro (empuja el link hacia abajo) --}}
        <div class="periodSpacer"></div>

        {{-- Enlace de pago: inline, sin caja blanca, alineado a la derecha --}}
        @if($hasPay)
          <table class="payLinkInlineRow" cellpadding="0" cellspacing="0" style="border-collapse:collapse; width:100%;">
            <tr>
              <td class="payLinkK b xs" style="width:92px; white-space:nowrap;">Enlace de pago:</td>
              <td class="payLinkV r" style="text-align:right;">
                <a href="{{ $payUrl }}" class="payLinkInlineA">Clic para pagar</a>
              </td>
            </tr>
          </table>
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
        <th width="16%" class="r">Unitario</th>
        <th width="10%" class="c">Cantidad</th>
        <th width="20%" class="r">Subtotal</th>
      </tr>
    </thead>
    <tbody>
      @if(!empty($serviceItems))
        @foreach($serviceItems as $it)
          @php
            $rowArr = is_array($it) ? $it : (is_object($it) ? (array)$it : []);

            // ✅ Nombre del servicio/concepto (NO usar $name para evitar colisión con el nombre del cliente)
            $svcName = trim((string)($rowArr['name'] ?? $rowArr['service'] ?? $rowArr['servicio'] ?? $rowArr['concepto'] ?? $rowArr['title'] ?? 'Servicio'));

            // ✅ Si la cuenta es anual, evita que el backend muestre "mensual" en el PDF
            if (!isset($isAnnual)) {
              // Por si el foreach corre antes de declarar (compat rara), recalculamos rápido:
              $cycleRaw2 = trim((string)($billing_cycle ?? $modoCobro ?? ($accountObj->billing_cycle ?? '') ?? ''));
              $cycleKey2 = strtolower($cycleRaw2);
              $isAnnual = in_array($cycleKey2, ['annual','anual','yearly','year','1y','12m'], true)
                || str_contains($cycleKey2, 'anual')
                || str_contains($cycleKey2, 'annual')
                || str_contains($cycleKey2, 'year');
            }

            if ($isAnnual) {
              $svcName = preg_replace('/\bmensual\b/iu', 'anual', $svcName);
              $svcName = preg_replace('/\bmonthly\b/iu', 'annual', $svcName);
            }

            // unitario (normalmente viene CON IVA desde backend)
            $unit = $f($rowArr['unit_price'] ?? $rowArr['unit_cost'] ?? $rowArr['costo_unit'] ?? $rowArr['costo'] ?? $rowArr['importe'] ?? 0);

            $qty = $f($rowArr['qty'] ?? $rowArr['cantidad'] ?? 1);
            if ($qty <= 0) $qty = 1;

            // ✅ Mostrar SIN IVA (unitario y subtotal)
            // Subtotal SIEMPRE = unitario(sin IVA) * cantidad
            $div = 1 + (float)($ivaRate ?? 0.16);
            if ($div <= 0.00001) $div = 1.16;

            $unitNoIva = $unit > 0 ? $r2($unit / $div) : 0.0;
            $subNoIva  = $r2($unitNoIva * $qty);
          @endphp

          <tr>
            <td class="sb">{{ $svcName }}</td>
            <td class="r">$ {{ number_format($unitNoIva, 2) }}</td>
            <td class="c">{{ rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.') }}</td>
            <td class="r">$ {{ number_format($subNoIva, 2) }}</td>
          </tr>
        @endforeach
      @else
        @php
          $div = 1 + (float)($ivaRate ?? 0.16);
          if ($div <= 0.00001) $div = 1.16;

          $unitNoIva = $currentDue > 0 ? $r2($currentDue / $div) : 0.0;
          $qty = 1;
          $subNoIva = $r2($unitNoIva * $qty);
        @endphp

        <tr>
          <td class="sb">{{ $serviceLabel }}</td>
          <td class="r">$ {{ number_format($unitNoIva, 2) }}</td>
          <td class="c">1</td>
          <td class="r">$ {{ number_format($subNoIva, 2) }}</td>
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

          {{-- ✅ NUEVO: Transferencias (arriba de "Ingresa") --}}
          <div class="sp6"></div>
          <div class="transferTitle">TRANSFERENCIAS</div>

          <table class="transferKv" cellpadding="0" cellspacing="0">
            <tr>
              <td class="k">Razón Social</td>
              <td class="v b">PACTOPIA SAPI DE CV</td>
            </tr>
            <tr>
              <td class="k">CTA CLABE</td>
              <td class="v b mono">699180600008252099</td>
            </tr>
            <tr>
              <td class="k">BANCO</td>
              <td class="v b">FONDEADORA</td>
            </tr>
          </table>

          <div class="sp6"></div>

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
             @if($showPrevLine)
              <tr>
                <td class="k sb">Saldo anterior:</td>
                <td class="v">
                  @if($prevIsPaid)
                    $ {{ number_format(0, 2) }} <span class="mut">· Pagado</span>
                  @else
                    $ {{ number_format($prevBalance, 2) }}
                  @endif
                </td>
              </tr>
            @endif

            <tr>
              <td class="k sb">Periodo actual:</td>
              <td class="v">$ {{ number_format($currentDue, 2) }}</td>
            </tr>

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

