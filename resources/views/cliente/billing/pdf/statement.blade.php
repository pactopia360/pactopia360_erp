{{-- resources/views/cliente/billing/pdf/statement.blade.php (P360 · PDF Estado de cuenta · DomPDF-safe · layout aligned · vNext) --}}
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Estado de cuenta {{ $period ?? '—' }}</title>

 @php
  // DomPDF: CSS inline (no URLs). Con try/catch y sin warnings.
  $pdfCssPath = public_path('assets/client/pdf/statement.css');
  $pdfCss = '';
  try {
    if (is_file($pdfCssPath) && is_readable($pdfCssPath)) {
      $raw = file_get_contents($pdfCssPath);
      $pdfCss = is_string($raw) ? $raw : '';
    }
  } catch (\Throwable $e) {
    $pdfCss = '';
  }
@endphp

<style>
{!! $pdfCss !!}
</style>

</head>
<body>
<div class="pageGutter">

@php
  use Illuminate\Support\Carbon;
  use Illuminate\Support\Str;

  // ----------------------------------------------------------
  // Helpers mínimos (DomPDF-safe)
  // ----------------------------------------------------------
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

  // ----------------------------------------------------------
  // Normalización de datos (evitar null/undefined)
  // ----------------------------------------------------------
  $accountObj = (isset($account) && is_object($account)) ? $account : (object)[];

  // Totales base
  $cargo = $f($cargo ?? 0);
  $abono = $f($abono ?? 0);

  $saldoPeriodo = $f($saldo ?? 0);

    $prevPeriod      = (string)($prev_period ?? '');
  $prevPeriodLabel = (string)($prev_period_label ?? $prevPeriod);

  // ----------------------------------------------------------
  // ✅ PrevBalance SOT (PDF): recalcular desde payments para evitar "debe" fantasma
  // ----------------------------------------------------------
  // Si el controller manda prev_balance, lo tomamos como fallback,
  // pero la fuente correcta para "saldo anterior pendiente" es payments:
  // - incluir: pending / expired / unpaid (si existe)
  // - excluir: cancelled / canceled / paid
  //
  // amount está en centavos (payments.amount)
  // ----------------------------------------------------------
  $prevBalanceFallback = $f($prev_balance ?? 0);
  $prevBalance = $prevBalanceFallback;

  try {
    $prevPeriodOk = is_string($prevPeriod) && preg_match('/^\d{4}-\d{2}$/', $prevPeriod);

    // Solo intentamos DB si hay periodo anterior válido y account_id numérico
    if ($prevPeriodOk) {

      // Resolver account id (ya lo calculas abajo, pero aquí lo necesitamos)
      $accountIdRaw2 = $account_id ?? ($accountObj->id ?? 0);
      $accountIdNum2 = is_numeric($accountIdRaw2) ? (int)$accountIdRaw2 : 0;

      if ($accountIdNum2 > 0) {
        $admConn = 'mysql_admin';

        if (\Illuminate\Support\Facades\Schema::connection($admConn)->hasTable('payments')) {
          $cols = \Illuminate\Support\Facades\Schema::connection($admConn)->getColumnListing('payments');
          $lc   = array_map('strtolower', $cols);
          $has  = static fn (string $c): bool => in_array(strtolower($c), $lc, true);

          // Debe existir amount y status para hacer el cálculo correcto
          if ($has('amount') && $has('status') && $has('account_id')) {
            $validDebt = ['pending','expired','unpaid'];
            $exclude   = ['paid','cancelled','canceled'];

            $q = \Illuminate\Support\Facades\DB::connection($admConn)->table('payments')
              ->where('account_id', $accountIdNum2);

            if ($has('period')) {
              $q->where('period', $prevPeriod);
            }

            // solo deuda vigente
            $q->whereIn('status', $validDebt)
              ->whereNotIn('status', $exclude);

            $prevCents = (int) $q->sum('amount');
            $prevBalance = round($prevCents / 100, 2);
          }
        }
      }
    }
  } catch (\Throwable $e) {
    // fail-safe: mantener fallback del controller
    $prevBalance = $prevBalanceFallback;
  }

  // Si controller manda current_period_due / total_due, respetar.
  // Si no, caer a saldoPeriodo/total legacy.
  $currentDue = $f($current_period_due ?? $saldoPeriodo);
  $totalDue   = $f($total_due ?? 0);

  // Legacy: algunos flujos mandan total como "saldo"
  $legacySaldo = $saldoPeriodo > 0.00001 ? $saldoPeriodo : $f($total ?? 0);

  // Total a pagar:
  // - si total_due viene bien, úsalo
  // - si no, usa currentDue si es >0
  // - si no, usa legacy
  $totalPagar = $totalDue > 0.00001 ? $totalDue : (($currentDue > 0.00001) ? $currentDue : $legacySaldo);

  // Mostrar saldo anterior solo si realmente hay deuda vigente
  $showPrev = ($prevBalance > 0.00001);


  $prevLabelSafe = trim((string)($prevPeriodLabel ?? ''));
  if ($prevLabelSafe === '') $prevLabelSafe = trim((string)($prevPeriod ?? ''));
  if ($prevLabelSafe === '') $prevLabelSafe = 'Periodo anterior';

  // ----------------------------------------------------------
  // Estatus visible (simple y estable)
  // ----------------------------------------------------------
  $statusLabel = 'Pendiente';
  $statusBadge = 'warn';

  if ($totalPagar <= 0.00001) {
    $statusLabel = ($cargo > 0.00001 || $abono > 0.00001) ? 'Pagado' : 'Sin movimientos';
    $statusBadge = ($statusLabel === 'Pagado') ? 'ok' : 'dim';
  } else {
    $statusLabel = ($abono > 0.00001) ? 'Parcial' : 'Pendiente';
    $statusBadge = 'warn';
  }

  // Tarifa: solo mostrar label (sin “total esperado”)
  $tarifaLabel = (string)($tarifa_label ?? '');
  $tarifaPill  = (string)($tarifa_pill ?? 'dim');
  if (!in_array($tarifaPill, ['info','warn','ok','dim','bad'], true)) $tarifaPill = 'dim';

  // ----------------------------------------------------------
  // IVA (se calcula sobre total a pagar)
  // ----------------------------------------------------------
  $ivaRate  = 0.16;
  $subtotal = $totalPagar > 0 ? round($totalPagar/(1+$ivaRate), 2) : 0.0;
  $iva      = $totalPagar > 0 ? round($totalPagar - $subtotal, 2) : 0.0;

  // ----------------------------------------------------------
  // Cuenta
  // ----------------------------------------------------------
  $accountIdRaw = $account_id ?? ($accountObj->id ?? 0);
  $accountIdNum = is_numeric($accountIdRaw) ? (int)$accountIdRaw : 0;
  $idCuentaTxt  = $accountIdNum > 0 ? str_pad((string)$accountIdNum, 6, '0', STR_PAD_LEFT) : '—';

  // Fechas
  $printedAt = ($generated_at ?? null) ? Carbon::parse($generated_at) : now();
  $dueAt     = ($due_at ?? null) ? Carbon::parse($due_at) : $printedAt->copy()->addDays(4);

  // ----------------------------------------------------------
  // Cliente (razon_social -> name -> email)
  // ----------------------------------------------------------
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

  // Assets
  $logoDataUri = $logo_data_uri ?? null;
  $logoFilePath = public_path('assets/client/logp360ligjt.png');
  if (!$logoDataUri && is_file($logoFilePath)) {
    try {
      $bin = file_get_contents($logoFilePath);
      if ($bin !== false && strlen($bin) > 10) {
        $logoDataUri = 'data:image/png;base64,'.base64_encode($bin);
      }
    } catch (\Throwable $e) {}
  }

  $qrDataUri = $qr_data_uri ?? null;
  $qrUrl     = $qr_url ?? null;

  $payUrl    = (string)($pay_url ?? '');
  $sessionId = (string)($stripe_session_id ?? '');

  // Total letras (fallback)
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

  // ----------------------------------------------------------
  // Detalle items (service_items o consumos)
  // ----------------------------------------------------------
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

  // Inyectar saldo anterior como línea del detalle (solo si hay deuda vigente real)
  if ($showPrev && $prevBalance > 0.00001) {
    array_unshift($serviceItems, [
      'service'   => 'Saldo anterior pendiente (' . $prevLabelSafe . ')',
      'unit_cost' => round((float)$prevBalance, 2),
      'qty'       => 1,
      'subtotal'  => round((float)$prevBalance, 2),
    ]);
  }


  $minRows   = 4;
  $rowsCount = count($serviceItems);
  $padRows   = max(0, $minRows - $rowsCount);

  // Para UI: si no hay payUrl, el QR no debe “prometer”
  $hasPay = trim($payUrl) !== '';
@endphp

{{-- TOP (2 columnas) --}}
<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; table-layout:fixed;">
  <tr>
    {{-- IZQUIERDA --}}
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

      <div class="sp10"></div>

      {{-- CUADRO CLIENTE (compacto y menos ancho) --}}
      <div class="card" style="
        background:#eeeeee;
        border:0;
        padding:6px 10px;
        border-radius:14px;
        margin-top:8px;
        display:inline-block;
        width:320px;
        max-width:320px;
      ">
        <div class="sb" style="font-size:11px; line-height:1.05; margin:0 0 3px;">
          {{ $clienteRazon ?: '—' }}
        </div>

        <div class="mut" style="font-size:9px; line-height:1.05; margin:0 0 4px;">
          @php
            $addrParts = [];
            if(!empty($line1)) $addrParts[] = $line1;
            if(!empty($line2)) $addrParts[] = $line2;
            if(!empty($line3)) $addrParts[] = $line3;
            if(!empty($line4)) $addrParts[] = $line4;
            $addrParts[] = ($dirPais !== '' ? $dirPais : 'México');
            $addrInline = implode(' · ', array_filter($addrParts, fn($v)=>trim((string)$v) !== ''));
          @endphp
          {{ $addrInline }}
        </div>

        <div style="font-size:9px; line-height:1.05; margin:0 0 2px;">
          <span class="mut">RFC:</span>
          <span class="b mono">{{ $clienteRfc ?: '—' }}</span>
        </div>

        <div style="font-size:9px; line-height:1.05; margin:0 0 2px;">
          <span class="mut">Plan:</span>
          <span class="b">{{ $clientePlan ?: '—' }}</span>
        </div>

        <div style="font-size:9px; line-height:1.05; margin:0 0 2px;">
          <span class="mut">Email:</span>
          <span class="b" style="white-space:normal; word-break:break-word;">{{ $clienteEmail ?: '—' }}</span>
        </div>

        @if(trim($tarifaLabel) !== '')
          <div style="font-size:9px; line-height:1.05; margin:2px 0 0;">
            <span class="mut">Tarifa:</span>
            <span class="pill {{ $tarifaPill }}" style="padding:2px 8px; font-size:9px; line-height:1;">
              {{ $tarifaLabel }}
            </span>
          </div>
        @endif
      </div>
    </td> {{-- ✅ FIX: cierre TD izquierda --}}

    {{-- DERECHA --}}
    <td width="48%" style="vertical-align:top; padding-left:12px;">

      {{-- 1) TOTAL --}}
      <div class="card">
        <table class="totalRowTbl" cellpadding="0" cellspacing="0" style="border-collapse:collapse; width:100%;">
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

      {{-- 2) ID CUENTA --}}
      <div class="card">
        <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
          <tr>
            <td class="idLbl">ID Cuenta:</td>
            <td class="r mono idVal">{{ $idCuentaTxt }}</td>
          </tr>
        </table>

        @if(trim($sessionId) !== '')
          <div class="sp8"></div>
          <div class="mut xs">Stripe session:</div>
          <div class="mono xs" style="white-space:normal; word-wrap:break-word; word-break:break-all; line-height:1.2;">
            {{ $sessionId }}
          </div>
        @endif
      </div>

      <div class="sp12"></div>

      {{-- 3) PERIODO --}}
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

        @if($hasPay)
          <div class="sp12"></div>
          <div class="b small">Enlace de pago:</div>

          <div style="margin-top:6px; padding:8px 10px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb;">
            <div class="linkMono" style="white-space: normal; word-wrap: break-word; word-break: break-all; line-height: 1.25; font-size: 9px;">
              {{ $payUrl }}
            </div>
          </div>
        @endif
      </div>

    </td>
  </tr>
</table>

<div class="sp18"></div>

{{-- DETALLE --}}
<div class="hSec" style="margin-bottom:8px;">
  Detalle de consumos
</div>

<div class="tblWrap">
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
            $row  = is_array($it) ? (object)$it : (is_object($it) ? $it : (object)[]);
            $name = (string)($row->service ?? $row->name ?? $row->servicio ?? $row->concepto ?? $row->title ?? 'Servicio mensual');
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

      @for($i=0; $i<$padRows; $i++)
        <tr>
          <td>&nbsp;</td>
          <td class="r">&nbsp;</td>
          <td class="c">&nbsp;</td>
          <td class="r">&nbsp;</td>
        </tr>
      @endfor
    </tbody>
  </table>
</div>

<div class="sp16"></div>

{{-- BOTTOM: 3 cards --}}
<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
  <tr>
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

    <td width="30%" style="vertical-align:top; padding:0 10px;">
      <div class="card" style="text-align:center;">
        <div class="b" style="font-size:11px; margin-bottom:10px;">
          Escanea el QR para<br>pago en línea
        </div>

        @php
          // DomPDF-safe QR: preferir data URI
          $qrImg = $qrDataUri ?? null;

          if (!$qrImg && !empty($qrUrl) && is_string($qrUrl)) {
            $u = trim($qrUrl);

            // 1) Si parece ruta relativa del sitio, convertir a public_path()
            //    Ej: /assets/... o assets/...
            $tryLocal = null;
            if (str_starts_with($u, '/')) {
              $tryLocal = public_path(ltrim($u, '/'));
            } elseif (!preg_match('#^https?://#i', $u)) {
              $tryLocal = public_path(ltrim($u, '/'));
            }

            // 2) Si existe local, base64
            if ($tryLocal && is_file($tryLocal) && is_readable($tryLocal)) {
              try {
                $bin = file_get_contents($tryLocal);
                if ($bin !== false && strlen($bin) > 10) {
                  $qrImg = 'data:image/png;base64,' . base64_encode($bin);
                }
              } catch (\Throwable $e) {}
            } else {
              // 3) Si es remoto http(s) y dompdf remote está enabled, intentar fetch (puede fallar por SSL)
              if (preg_match('#^https?://#i', $u)) {
                try {
                  $ctx = stream_context_create([
                    'http' => ['timeout' => 3],
                    'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
                  ]);
                  $bin = @file_get_contents($u, false, $ctx);
                  if ($bin !== false && strlen($bin) > 10) {
                    $qrImg = 'data:image/png;base64,' . base64_encode($bin);
                  }
                } catch (\Throwable $e) {}
              }
            }
          }
        @endphp

        @if($qrImg)
          <img src="{{ $qrImg }}" alt="QR">
        @else
          <div style="padding-top:66px;" class="mut b">QR no disponible</div>
        @endif


        @if(!$hasPay)
          <div class="sp10"></div>
          <div class="mut xs">QR se habilita cuando existe enlace de pago.</div>
        @endif
      </div>
    </td>

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

</div>
</body>
</html>
