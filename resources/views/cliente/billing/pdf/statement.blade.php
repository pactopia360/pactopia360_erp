<<<<<<< HEAD
{{-- resources/views/cliente/billing/pdf/statement.blade.php (P360 · PDF Estado de cuenta) --}}
=======
{{-- resources/views/cliente/billing/pdf/statement.blade.php (P360 · PDF Estado de cuenta · mejorado + DomPDF-safe) --}}
>>>>>>> 3e7910d (Fix: admin usuarios administrativos + UI full width + debug safe)
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Estado de cuenta {{ $period ?? '—' }}</title>

  <style>
    @page { margin: 20px; }

    body{
      font-family: DejaVu Sans, Arial, sans-serif;
      font-size: 12px;
      color:#111827;
      margin:0;
      padding:0;
      background:#ffffff;
    }

    /* Helpers */
    .mut{ color:#6b7280; }
    .b{ font-weight:900; }
    .sb{ font-weight:700; }
    .mono{ font-family: DejaVu Sans Mono, ui-monospace, monospace; }
<<<<<<< HEAD
    .r{ text-align:right; }
    .c{ text-align:center; }
=======
    .small{ font-size:11px; }
    .xs{ font-size:10px; }
>>>>>>> 3e7910d (Fix: admin usuarios administrativos + UI full width + debug safe)

    .sp6{ height:6px; }
    .sp8{ height:8px; }
    .sp10{ height:10px; }
    .sp12{ height:12px; }
    .sp14{ height:14px; }
    .sp16{ height:16px; }
    .sp18{ height:18px; }
    .sp20{ height:20px; }

    /* Cards (DomPDF-safe) */
    .card{
      background:#efefef;
      border-radius:14px;
      padding:14px 16px;
      border:1px solid #e5e7eb;
      page-break-inside: avoid;
    }

    /* Brand */
    .brandLogo{ height:54px; display:block; }
    .brandName{ font-size:18px; font-weight:900; margin-top:6px; }
    .brandBlock{ font-size:12px; line-height:1.35; }
    .brandSite{ font-size:14px; font-weight:900; margin-top:8px; }

    /* Total card */
    .totalCardLbl{ font-size:16px; font-weight:900; }
    .totalAmt{ font-size:30px; font-weight:900; letter-spacing:.2px; }
    .totalRowTbl{ width:100%; border-collapse:collapse; }
    .totalRowTbl td{ vertical-align:middle; }
    .moneySign{
      width:18px;
      text-align:right;
      font-size:22px;
      font-weight:900;
      padding-right:8px;
    }
    .totalWords{ margin-top:10px; font-size:12px; color:#111827; text-align:center; }

    /* ID card */
    .idLbl{ font-size:18px; font-weight:900; }
    .idVal{ font-size:34px; font-weight:900; letter-spacing:.5px; }

    /* Section title */
    .hSec{
      font-size:14px;
      font-weight:400;
      margin:0 0 10px;
      color:#111827;
    }

<<<<<<< HEAD
    /* Key/Value table */
    .kv{ width:100%; border-collapse:collapse; }
    .kv td{ padding:2px 0; vertical-align:top; }
    .kv .k{ color:#6b7280; width:44%; }
    .kv .v{ font-weight:700; }

    /* Chip */
    .chip{
      display:inline-block;
      padding:3px 10px;
      border-radius:999px;
      font-size:11px;
      font-weight:900;
      background:#e5e7eb;
      color:#111827;
      vertical-align:middle;
    }
    .chip.dim{ background:#e5e7eb; color:#111827; }
    .chip.info{ background:#dbeafe; color:#1e40af; }
    .chip.ok{ background:#dcfce7; color:#166534; }
    .chip.warn{ background:#fef9c3; color:#854d0e; }
    .chip.bad{ background:#fee2e2; color:#991b1b; }

    /* Table */
=======
    /* Pills */
    .pill{
      display:inline-block;
      padding:4px 10px;
      border-radius:999px;
      font-size:10px;
      font-weight:900;
      letter-spacing:.2px;
      border:1px solid #d1d5db;
      background:#ffffff;
      color:#111827;
      white-space:nowrap;
    }
    .pill.info{ border-color:#93c5fd; background:#eff6ff; }
    .pill.ok{ border-color:#86efac; background:#ecfdf5; }
    .pill.warn{ border-color:#fde68a; background:#fffbeb; }
    .pill.bad{ border-color:#fca5a5; background:#fef2f2; }
    .pill.dim{ border-color:#e5e7eb; background:#f9fafb; color:#4b5563; }

    /* Table (DomPDF-safe) */
>>>>>>> 3e7910d (Fix: admin usuarios administrativos + UI full width + debug safe)
    .tblWrap{
      background:#efefef;
      border-radius:14px;
      padding:0;
      border:1px solid #e5e7eb;
      /* IMPORTANT: NO overflow:hidden -> DomPDF recorta contenido */
      page-break-inside: avoid;
    }
    .tbl{
      width:100%;
      border-collapse:collapse;
      background:#ffffff;
      table-layout: fixed; /* evita que se “salga” */
    }
    .tbl th{
      background:#e9e9e9;
      padding:10px 12px;
      text-align:left;
      font-weight:900;
      font-size:12px;
      border-bottom:1px solid #d8d8d8;
      word-wrap:break-word;
      overflow-wrap:break-word;
    }
    .tbl td{
      padding:7px 12px;
      font-size:12px;
      border-bottom:1px solid #ededed;
      vertical-align:top;
      word-wrap:break-word;
      overflow-wrap:break-word;
    }
    .tbl tr:nth-child(even) td{ background:#f3f3f3; }

    /* Bottom blocks */
    .payTitle{ font-size:13px; font-weight:900; margin-bottom:10px; }
<<<<<<< HEAD
    .smallNote{ font-size:10.5px; color:#374151; word-break:break-all; }
=======
    .smallNote{ font-size:11px; color:#374151; line-height:1.35; }
>>>>>>> 3e7910d (Fix: admin usuarios administrativos + UI full width + debug safe)

    /* Payment logos row */
    .payRow{ width:100%; border-collapse:collapse; }
    .payRow td{ vertical-align:middle; padding-right:10px; }
    .payLogo{ height:22px; display:inline-block; vertical-align:middle; }

    /* Social icons */
    .socialRow{ width:100%; border-collapse:collapse; margin-top:8px; }
    .socialRow td{ padding-right:10px; vertical-align:middle; }
    .socialIco{ height:26px; display:inline-block; vertical-align:middle; }

    /* QR */
    .qrBox{
      width:170px;
      height:170px;
      border-radius:10px;
      background:#fff;
      border:1px solid #e5e7eb;
      margin:0 auto;
      text-align:center;
    }
    .qrBox img{
      width:170px;
      height:170px;
      display:block;
    }

<<<<<<< HEAD
    /* Divider */
=======
    /* Links (print safe) */
    .linkMono{
      font-size:10px;
      word-break: break-all;
      overflow-wrap: anywhere;
      font-family: DejaVu Sans Mono, ui-monospace, monospace;
      color:#111827;
    }

>>>>>>> 3e7910d (Fix: admin usuarios administrativos + UI full width + debug safe)
    .hr{ height:1px; background:#e5e7eb; margin:10px 0; }
  </style>
</head>
<body>

@php
  use Illuminate\Support\Carbon;
  use Illuminate\Support\Str;

<<<<<<< HEAD
  // ---------- Core ----------
  $periodSafe = $period ?? '—';

  // En este PDF: mostramos "Total a pagar" como SALDO mostrado
  $total = (float)($total ?? 0);
=======
  $periodSafe = (string)($period ?? '—');

  /**
   * IMPORTANT:
   * En tu PDF anterior $total era "saldo a pagar".
   * Aquí soportamos ambas formas:
   * - si viene $saldo úsalo
   * - si no viene, usa $total como saldo
   */
>>>>>>> 3e7910d (Fix: admin usuarios administrativos + UI full width + debug safe)
  $cargo = (float)($cargo ?? 0);
  $abono = (float)($abono ?? 0);
  $saldo = (float)($saldo ?? ($total ?? 0));

<<<<<<< HEAD
  // Totales alternos si vienen (por consistencia)
  $expectedTotal = (float)($expected_total ?? 0); // total esperado por licencia
  $tarifaLabel   = (string)($tarifa_label ?? 'Tarifa base');
  $tarifaPill    = (string)($tarifa_pill ?? 'dim');

  if (!in_array($tarifaPill, ['info','warn','ok','dim','bad'], true)) $tarifaPill = 'dim';

=======
  // Total mostrado arriba: siempre es "saldo a pagar"
  $totalPagar = $saldo;

  // Totales esperados / tarifa (si backend lo manda)
  $expectedTotal = (float)($expected_total ?? 0);
  $tarifaLabel   = (string)($tarifa_label ?? '');
  $tarifaPill    = (string)($tarifa_pill ?? 'dim');
  if (!in_array($tarifaPill, ['info','warn','ok','dim','bad'], true)) $tarifaPill = 'dim';

  // IVA sobre el saldo a pagar (si el saldo incluye IVA)
>>>>>>> 3e7910d (Fix: admin usuarios administrativos + UI full width + debug safe)
  $ivaRate  = 0.16;
  $subtotal = $totalPagar > 0 ? round($totalPagar/(1+$ivaRate), 2) : 0.0;
  $iva      = $totalPagar > 0 ? round($totalPagar - $subtotal, 2) : 0.0;

<<<<<<< HEAD
  $accountObj = $account ?? null;

  // ID cuenta
  $accountId = (int)($account_id ?? ($accountObj->id ?? 0));
  $idCuentaTxt = $accountId > 0 ? str_pad((string)$accountId, 6, '0', STR_PAD_LEFT) : '—';
=======
  // Cuenta
  $accountIdRaw = $account_id ?? ($account->id ?? 0);
  $accountIdNum = is_numeric($accountIdRaw) ? (int)$accountIdRaw : 0;

  // En maqueta ID con 6 dígitos
  $idCuentaTxt = $accountIdNum > 0 ? str_pad((string)$accountIdNum, 6, '0', STR_PAD_LEFT) : '—';
>>>>>>> 3e7910d (Fix: admin usuarios administrativos + UI full width + debug safe)

  // Fechas
  $printedAt = ($generated_at ?? null) ? Carbon::parse($generated_at) : now();
  $dueAt     = ($due_at ?? null) ? Carbon::parse($due_at) : $printedAt->copy()->addDays(4);

<<<<<<< HEAD
  // Period label (Enero 2026)
  $periodLabel = $periodSafe;
  try {
    if (preg_match('/^\d{4}-\d{2}$/', $periodSafe)) {
      $periodLabel = Carbon::parse($periodSafe.'-01')->translatedFormat('F Y');
      $periodLabel = Str::ucfirst($periodLabel);
    }
  } catch (\Throwable $e) {}

  // ---------- Cliente ----------
  $clienteRazon = (string)($razon_social ?? ($accountObj->razon_social ?? $accountObj->name ?? '—'));
  $clienteRfc   = (string)($rfc ?? ($accountObj->rfc ?? '—'));
  $clienteEmail = (string)($email ?? ($accountObj->email ?? ''));

  // Dirección (si existe)
=======
  // Cliente / datos extra
  $clienteRazon = (string)($razon_social ?? ($account->razon_social ?? '—'));
  $clienteRfc   = (string)($rfc ?? ($account->rfc ?? '—'));
  $clienteEmail = (string)($email ?? ($account->email ?? '—'));
  $clientePlan  = (string)($plan ?? ($account->plan ?? ($account->plan_actual ?? '—')));
  $modoCobro    = (string)($modo_cobro ?? ($account->modo_cobro ?? ($account->billing_cycle ?? '—')));

  // Dirección
>>>>>>> 3e7910d (Fix: admin usuarios administrativos + UI full width + debug safe)
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

<<<<<<< HEAD
  // ---------- Plan / Cobro (esto debe venir bien desde backend) ----------
  // Si no vienen, intentamos inferir sin romper
  $rawPlan = (string)($plan ?? ($accountObj->plan_actual ?? $accountObj->plan ?? ''));
  $rawModo = (string)($modo_cobro ?? ($accountObj->modo_cobro ?? $accountObj->billing_cycle ?? ''));

  $planTxt = $rawPlan !== '' ? $rawPlan : '—';
  $modoTxt = $rawModo !== '' ? $rawModo : '—';

  // Normalización visual (solo presentación)
  $planPretty = strtoupper(str_replace(['-', ' '], '_', $planTxt));
  $planPretty = str_replace('_', ' ', $planPretty);

  $modoPretty = strtolower(trim($modoTxt));
  $modoPretty = $modoPretty === 'anual' || $modoPretty === 'annual' ? 'Anual' :
                ($modoPretty === 'mensual' || $modoPretty === 'monthly' ? 'Mensual' :
                ($modoPretty !== '' ? $modoTxt : '—'));

  // ---------- URLs ----------
  $pdfUrl    = $pdf_url ?? null;
  $portalUrl = $portal_url ?? null;
  $payUrl    = $pay_url ?? null;

  // ---------- Assets embebidos (data uris) ----------
=======
  // Assets embebidos (data uris)
>>>>>>> 3e7910d (Fix: admin usuarios administrativos + UI full width + debug safe)
  $logoDataUri = $logo_data_uri ?? null;

  $qrDataUri   = $qr_data_uri ?? null;
  $qrUrl       = $qr_url ?? null;
<<<<<<< HEAD
=======

  $payUrl = (string)($pay_url ?? ''); // enlace de pago Stripe si viene
  $sessionId = (string)($stripe_session_id ?? '');
>>>>>>> 3e7910d (Fix: admin usuarios administrativos + UI full width + debug safe)

  $payPaypal = $pay_paypal_data_uri ?? null;
  $payVisa   = $pay_visa_data_uri ?? null;
  $payAmex   = $pay_amex_data_uri ?? null;
  $payMc     = $pay_mc_data_uri ?? null;
  $payOxxo   = $pay_oxxo_data_uri ?? null;

  $socFb = $social_fb_data_uri ?? null;
  $socIn = $social_in_data_uri ?? null;
  $socYt = $social_yt_data_uri ?? null;
  $socIg = $social_ig_data_uri ?? null;

<<<<<<< HEAD
  // Total en letras
  $cent = (int) round(($total - floor($total)) * 100);
  $totalLetras = $total_letras ?? (number_format(floor($total), 0, '.', ',').' pesos '.str_pad((string)$cent, 2, '0', STR_PAD_LEFT).'/100 MN');

  // ---------- Consumos ----------
=======
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
>>>>>>> 3e7910d (Fix: admin usuarios administrativos + UI full width + debug safe)
  $serviceItems = $consumos ?? ($service_items ?? []);
  if ($serviceItems instanceof \Illuminate\Support\Collection) $serviceItems = $serviceItems->all();
  if (!is_array($serviceItems)) $serviceItems = [];

<<<<<<< HEAD
  // Regla: si no viene "Servicio mensual/anual", lo inyectamos basado en modo/ciclo y expectedTotal
  $hasBaseService = false;
  foreach ($serviceItems as $it) {
    $row = is_array($it) ? (object)$it : $it;
    $name = strtolower((string)($row->service ?? $row->name ?? $row->servicio ?? $row->concepto ?? ''));
    if (str_contains($name, 'servicio') && (str_contains($name, 'mensual') || str_contains($name, 'anual'))) {
      $hasBaseService = true;
      break;
    }
  }

  if (!$hasBaseService && $expectedTotal > 0.00001) {
    $baseLabel = (stripos((string)$modoPretty, 'anual') !== false) ? 'Servicio anual' : 'Servicio mensual';
    array_unshift($serviceItems, [
      'service'   => $baseLabel,
      'unit_cost' => $expectedTotal,
      'qty'       => 1,
      'subtotal'  => $expectedTotal,
    ]);
  }

  $minRows   = 10;
=======
  // Asegurar mínimo 8 filas visuales
  $minRows   = 8;
>>>>>>> 3e7910d (Fix: admin usuarios administrativos + UI full width + debug safe)
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
<<<<<<< HEAD
      <div class="brandBlock">
        <div><span class="b">Email:</span> {{ $clienteEmail !== '' ? $clienteEmail : '—' }}</div>
        <div><span class="b">Plan:</span> {{ $planPretty }}</div>
        <div><span class="b">Modo de cobro:</span> {{ $modoPretty }}</div>

        <div class="sp8"></div>
        <div>
          <span class="b">Tarifa:</span>
          <span class="chip {{ $tarifaPill }}">{{ $tarifaLabel }}</span>
        </div>
        <div class="sp6"></div>
        <div><span class="b">Total esperado:</span> $ {{ number_format($expectedTotal, 2) }}</div>
=======

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
>>>>>>> 3e7910d (Fix: admin usuarios administrativos + UI full width + debug safe)
      </div>
    </td>

    <td width="48%" style="vertical-align:top; padding-left:12px;">
      <div class="card">
        <table class="totalRowTbl" cellpadding="0" cellspacing="0">
          <tr>
            <td class="totalCardLbl">Total a pagar:</td>
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

<<<<<<< HEAD
        <table class="kv" cellpadding="0" cellspacing="0">
          <tr><td class="k">Cargo del periodo</td><td class="v r">$ {{ number_format($cargo, 2) }}</td></tr>
          <tr><td class="k">Pagos / abonos</td><td class="v r">$ {{ number_format($abono, 2) }}</td></tr>
          <tr><td class="k">Saldo</td><td class="v r">$ {{ number_format($total, 2) }}</td></tr>
=======
        {{-- Mejora: resumen saldo/cargo/abono --}}
        <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
          <tr>
            <td class="mut small">Cargo del periodo</td>
            <td class="r sb small">$ {{ number_format($cargo, 2) }}</td>
          </tr>
          <tr>
            <td class="mut small">Pagos / abonos</td>
            <td class="r sb small">$ {{ number_format($abono, 2) }}</td>
          </tr>
          <tr>
            <td class="mut small">Saldo</td>
            <td class="r b small">$ {{ number_format($saldo, 2) }}</td>
          </tr>
>>>>>>> 3e7910d (Fix: admin usuarios administrativos + UI full width + debug safe)
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
          <div class="mono xs">{{ $sessionId }}</div>
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
<<<<<<< HEAD
        <div><span class="b">RFC:</span> {{ $clienteRfc }}</div>
        <div><span class="b">Email:</span> {{ $clienteEmail !== '' ? $clienteEmail : '—' }}</div>
=======

        <div><span class="b">RFC:</span> <span class="mono">{{ $clienteRfc }}</span></div>
        <div><span class="b">Email:</span> {{ $clienteEmail ?: '—' }}</div>
>>>>>>> 3e7910d (Fix: admin usuarios administrativos + UI full width + debug safe)
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

<<<<<<< HEAD
        <div class="sp12"></div>

        @if(is_string($payUrl) && $payUrl !== '')
          <div class="mut b">Enlace de pago:</div>
          <div class="smallNote">{{ $payUrl }}</div>
        @elseif(is_string($portalUrl) && $portalUrl !== '')
          <div class="mut b">Portal:</div>
          <div class="smallNote">{{ $portalUrl }}</div>
        @elseif(is_string($pdfUrl) && $pdfUrl !== '')
          <div class="mut b">PDF:</div>
          <div class="smallNote">{{ $pdfUrl }}</div>
=======
        {{-- Mejora: enlace de pago visible (texto) --}}
        @if($payUrl !== '')
          <div class="sp12"></div>
          <div class="b small">Enlace de pago:</div>
          <div class="linkMono">{{ $payUrl }}</div>
>>>>>>> 3e7910d (Fix: admin usuarios administrativos + UI full width + debug safe)
        @endif
      </div>
    </td>
  </tr>
</table>

<div class="sp18"></div>

{{-- DETALLE --}}
<div class="hSec">Detalle de consumos</div>

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
<<<<<<< HEAD
      @foreach($serviceItems as $it)
        @php
          $row = is_array($it) ? (object)$it : $it;
=======
      @if(!empty($serviceItems))
        @foreach($serviceItems as $it)
          @php
            $row = is_array($it) ? (object)$it : $it;
>>>>>>> 3e7910d (Fix: admin usuarios administrativos + UI full width + debug safe)

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

{{-- BOTTOM: 3 cards (formas pago + QR + desglose) --}}
<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
  <tr>
    {{-- Izquierda: Formas + Redes --}}
    <td width="38%" style="vertical-align:top; padding-right:10px;">
      <div class="card">
        <div class="payTitle">Formas de pago</div>

        <table class="payRow" cellpadding="0" cellspacing="0">
          <tr>
            <td>@if($payPaypal)<img class="payLogo" src="{{ $payPaypal }}" alt="PayPal">@else <span class="b">PayPal</span> @endif</td>
            <td>@if($payVisa)<img class="payLogo" src="{{ $payVisa }}" alt="VISA">@else <span class="b">VISA</span> @endif</td>
            <td>@if($payAmex)<img class="payLogo" src="{{ $payAmex }}" alt="AMEX">@else <span class="b">AMEX</span> @endif</td>
            <td>@if($payMc)<img class="payLogo" src="{{ $payMc }}" alt="Mastercard">@else <span class="b">MC</span> @endif</td>
            <td style="padding-right:0;">@if($payOxxo)<img class="payLogo" src="{{ $payOxxo }}" alt="OXXO">@else <span class="b">OXXO</span> @endif</td>
          </tr>
        </table>

        <div class="sp14"></div>

        <div class="payTitle" style="margin-bottom:8px;">Síguenos en</div>
        <table class="socialRow" cellpadding="0" cellspacing="0">
          <tr>
            <td>@if($socFb)<img class="socialIco" src="{{ $socFb }}" alt="Facebook">@else <span class="b">f</span> @endif</td>
            <td>@if($socIn)<img class="socialIco" src="{{ $socIn }}" alt="LinkedIn">@else <span class="b">in</span> @endif</td>
            <td>@if($socYt)<img class="socialIco" src="{{ $socYt }}" alt="YouTube">@else <span class="b">▶</span> @endif</td>
            <td style="padding-right:0;">@if($socIg)<img class="socialIco" src="{{ $socIg }}" alt="Instagram">@else <span class="b">◎</span> @endif</td>
          </tr>
        </table>

<<<<<<< HEAD
        <div class="sp10"></div>
        <div class="smallNote">
          Si algún logo no aparece, el backend debe enviarlo como <span class="b">data URI</span> (base64), no URL remota.
        </div>
=======
        @if($payUrl !== '')
          <div class="sp12"></div>
          <div class="smallNote">
            <span class="b">Pago en línea:</span><br>
            <span class="linkMono">{{ $payUrl }}</span>
          </div>
        @else
          <div class="sp12"></div>
          <div class="smallNote mut">
            No hay enlace de pago disponible para este estado de cuenta.
          </div>
        @endif
>>>>>>> 3e7910d (Fix: admin usuarios administrativos + UI full width + debug safe)
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
            <div style="padding-top:78px;" class="mut b">QR no disponible</div>
          @endif
        </div>

<<<<<<< HEAD
        <div class="sp10"></div>
        @if(is_string($payUrl) && $payUrl !== '')
          <div class="smallNote">{{ $payUrl }}</div>
=======
        @if($payUrl === '')
          <div class="sp10"></div>
          <div class="mut xs">QR se habilita cuando existe enlace de pago.</div>
>>>>>>> 3e7910d (Fix: admin usuarios administrativos + UI full width + debug safe)
        @endif
      </div>
    </td>

    {{-- Derecha: desglose --}}
    <td width="32%" style="vertical-align:top; padding-left:10px;">
      <div class="card">
        <div class="payTitle">Desglose del importe a pagar</div>
        <div class="sp10"></div>

        <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
          <tr>
            <td class="sb">Subtotal:</td>
            <td class="r">$ {{ number_format($subtotal, 2) }}</td>
          </tr>
          <tr>
            <td class="sb">IVA 16%:</td>
            <td class="r">$ {{ number_format($iva, 2) }}</td>
          </tr>
          <tr>
            <td class="sb">Total:</td>
            <td class="r b">$ {{ number_format($totalPagar, 2) }}</td>
          </tr>
        </table>

        <div class="hr"></div>

<<<<<<< HEAD
        <table class="kv" cellpadding="0" cellspacing="0">
          <tr><td class="k">Cargo</td><td class="v r">$ {{ number_format($cargo, 2) }}</td></tr>
          <tr><td class="k">Abono</td><td class="v r">$ {{ number_format($abono, 2) }}</td></tr>
          <tr><td class="k">Saldo</td><td class="v r">$ {{ number_format($total, 2) }}</td></tr>
        </table>
=======
        {{-- Mejora: notas de estado --}}
        <div class="smallNote">
          <span class="b">Nota:</span> Si ya realizaste tu pago, el saldo puede tardar unos minutos en reflejarse.
        </div>
>>>>>>> 3e7910d (Fix: admin usuarios administrativos + UI full width + debug safe)
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
