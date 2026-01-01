{{-- resources/views/cliente/billing/pdf/statement.blade.php (P360 · PDF Estado de cuenta · match mock) --}}
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Estado de cuenta {{ $period ?? '—' }}</title>

  <style>
    @page { margin: 22px; }

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

    .sp6{ height:6px; }
    .sp8{ height:8px; }
    .sp10{ height:10px; }
    .sp12{ height:12px; }
    .sp14{ height:14px; }
    .sp16{ height:16px; }
    .sp18{ height:18px; }
    .sp20{ height:20px; }

    /* Cards */
    .card{
      background:#efefef;
      border-radius:18px;
      padding:16px 18px;
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

    /* Table */
    .tblWrap{
      background:#efefef;
      border-radius:18px;
      padding:0;
      overflow:hidden;
    }
    .tbl{
      width:100%;
      border-collapse:collapse;
      background:#ffffff;
    }
    .tbl th{
      background:#e9e9e9;
      padding:10px 12px;
      text-align:left;
      font-weight:900;
      font-size:12px;
      border-bottom:1px solid #d8d8d8;
    }
    .tbl td{
      padding:6px 12px;
      font-size:12px;
      border-bottom:1px solid #ededed;
      vertical-align:top;
    }
    .tbl tr:nth-child(even) td{ background:#f3f3f3; }

    .r{ text-align:right; }
    .c{ text-align:center; }

    /* Bottom blocks */
    .payTitle{ font-size:13px; font-weight:900; margin-bottom:10px; }
    .smallNote{ font-size:11px; color:#374151; }

    /* Payment logos row */
    .payRow{
      width:100%;
      border-collapse:collapse;
    }
    .payRow td{ vertical-align:middle; padding-right:10px; }
    .payLogo{
      height:24px;
      display:inline-block;
      vertical-align:middle;
    }

    /* Social icons */
    .socialRow{ width:100%; border-collapse:collapse; margin-top:8px; }
    .socialRow td{ padding-right:10px; vertical-align:middle; }
    .socialIco{ height:30px; display:inline-block; vertical-align:middle; }

    /* QR */
    .qrBox{
      width:170px;
      height:170px;
      border-radius:10px;
      overflow:hidden;
      background:#fff;
      border:0;
      margin:0 auto;
      text-align:center;
    }
    .qrBox img{
      width:170px;
      height:170px;
      display:block;
    }
  </style>
</head>
<body>

@php
  use Illuminate\Support\Carbon;
  use Illuminate\Support\Str;

  $periodSafe = $period ?? '—';

  // ✅ En este PDF: $total es "Total a pagar" (saldo)
  $total = (float)($total ?? 0);
  $cargo = (float)($cargo ?? 0);
  $abono = (float)($abono ?? 0);

  $ivaRate  = 0.16;
  $subtotal = $total > 0 ? round($total/(1+$ivaRate), 2) : 0.0;
  $iva      = $total > 0 ? round($total - $subtotal, 2) : 0.0;

  $accountId   = (int)($account_id ?? 0);

  // En la maqueta el ID se ve con 6 dígitos (ej: 011234)
  $idCuentaTxt = $accountId > 0 ? str_pad((string)$accountId, 6, '0', STR_PAD_LEFT) : '—';

  $printedAt = ($generated_at ?? null) ? Carbon::parse($generated_at) : now();
  $dueAt     = ($due_at ?? null) ? Carbon::parse($due_at) : $printedAt->copy()->addDays(4);

  $clienteRazon = $razon_social ?? ($account->razon_social ?? '—');
  $clienteRfc   = $rfc ?? ($account->rfc ?? '—');

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

  // === Assets embebidos (data uris) ===
  $logoDataUri = $logo_data_uri ?? null;

  $qrDataUri   = $qr_data_uri ?? null;
  $qrUrl       = $qr_url ?? null; // fallback si decides usar remoto (opcional)

  $payPaypal = $pay_paypal_data_uri ?? null;
  $payVisa   = $pay_visa_data_uri ?? null;
  $payAmex   = $pay_amex_data_uri ?? null;
  $payMc     = $pay_mc_data_uri ?? null;
  $payOxxo   = $pay_oxxo_data_uri ?? null;

  $socFb = $social_fb_data_uri ?? null;
  $socIn = $social_in_data_uri ?? null;
  $socYt = $social_yt_data_uri ?? null;
  $socIg = $social_ig_data_uri ?? null;

  // Total en letras (si te lo pasan desde backend lo respeta)
  $cent = (int) round(($total - floor($total)) * 100);
  $totalLetras = $total_letras ?? (number_format(floor($total), 0, '.', ',').' pesos '.str_pad((string)$cent, 2, '0', STR_PAD_LEFT).'/100 MN');

  // Periodo label (ej: Septiembre 2025)
  $periodLabel = $periodSafe;
  try {
    if (preg_match('/^\d{4}-\d{2}$/', $periodSafe)) {
      $periodLabel = Carbon::parse($periodSafe.'-01')->translatedFormat('F Y');
      $periodLabel = Str::ucfirst($periodLabel);
    }
  } catch (\Throwable $e) {}

  // =========================================================
  // ✅ DETALLE DE CONSUMOS (desde backend: $consumos)
  // Siempre debe incluir "Servicio mensual"
  // =========================================================
  $serviceItems = $consumos ?? ($service_items ?? []);
  if ($serviceItems instanceof \Illuminate\Support\Collection) $serviceItems = $serviceItems->all();
  if (!is_array($serviceItems)) $serviceItems = [];

  $minRows   = 8; // maqueta trae espacio visual
  $rowsCount = count($serviceItems);
  $padRows   = max(0, $minRows - $rowsCount);
@endphp

{{-- TOP: izquierda marca / derecha total + id --}}
<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
  <tr>
    <td width="52%" style="vertical-align:top; padding-right:12px;">
      @if($logoDataUri)
        <img src="{{ $logoDataUri }}" class="brandLogo" alt="PACTOPIA">
      @else
        <div class="b" style="font-size:24px;">PACTOPIA</div>
      @endif

      <div class="sp10"></div>

      <div class="brandName">Pactopia SAPI de CV</div>
      <div class="brandBlock">
        Fresno 195 Int 2<br>
        Santa María la Ribera<br>
        Cuauhtémoc, CP 06400<br>
        México<br>
        <span class="b">RFC</span> PACA151025NJ5
      </div>
      <div class="brandSite">Pactopia.com</div>
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
                  <td class="r totalAmt">{{ number_format($total, 2) }}</td>
                </tr>
              </table>
            </td>
          </tr>
        </table>
        <div class="totalWords">{{ $totalLetras }}</div>
      </div>

      <div class="sp12"></div>

      <div class="card">
        <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
          <tr>
            <td class="idLbl">ID Cuenta:</td>
            <td class="r mono idVal">{{ $idCuentaTxt }}</td>
          </tr>
        </table>
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
        <div>{{ $dirPais ?: 'México' }}</div>

        <div class="sp10"></div>
        <div><span class="b">RFC:</span> {{ $clienteRfc }}</div>
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
            <td class="mut">Límite de pago:</td>
            <td class="r b">{{ $dueAt->translatedFormat('d \\d\\e F Y') }}</td>
          </tr>
        </table>
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
        <th width="40%">Servicio</th>
        <th width="20%" class="r">Costo Unit</th>
        <th width="20%" class="c">Cantidad</th>
        <th width="20%" class="r">Subtotal</th>
      </tr>
    </thead>
    <tbody>
      @foreach($serviceItems as $it)
        @php
          // $consumos viene como array: ['service','unit_cost','qty','subtotal']
          $row = is_array($it) ? (object)$it : $it;

          $name = (string)($row->service ?? $row->name ?? $row->servicio ?? $row->concepto ?? 'Servicio');
          $unit = (float)($row->unit_cost ?? $row->unit_price ?? $row->costo_unit ?? $row->costo ?? 0);
          $qty  = (float)($row->qty ?? $row->cantidad ?? 1);
          $sub  = (float)($row->subtotal ?? ($unit * $qty));
        @endphp
        <tr>
          <td class="sb">{{ $name }}</td>
          <td class="r">$ {{ number_format($unit, 2) }}</td>
          <td class="c">{{ rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.') }}</td>
          <td class="r">$ {{ number_format($sub, 2) }}</td>
        </tr>
      @endforeach

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

{{-- BOTTOM: 3 cards (formas pago + QR + desglose) --}}
<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
  <tr>
    {{-- Izquierda: Formas + Redes --}}
    <td width="38%" style="vertical-align:top; padding-right:10px;">
      <div class="card">
        <div class="payTitle">Formas de pago</div>

        <table class="payRow" cellpadding="0" cellspacing="0">
          <tr>
            <td>
              @if($payPaypal)<img class="payLogo" src="{{ $payPaypal }}" alt="PayPal">@else <span class="b">PayPal</span> @endif
            </td>
            <td>
              @if($payVisa)<img class="payLogo" src="{{ $payVisa }}" alt="VISA">@else <span class="b">VISA</span> @endif
            </td>
            <td>
              @if($payAmex)<img class="payLogo" src="{{ $payAmex }}" alt="AMEX">@else <span class="b">AMEX</span> @endif
            </td>
            <td>
              @if($payMc)<img class="payLogo" src="{{ $payMc }}" alt="Mastercard">@else <span class="b">MC</span> @endif
            </td>
            <td style="padding-right:0;">
              @if($payOxxo)<img class="payLogo" src="{{ $payOxxo }}" alt="OXXO">@else <span class="b">OXXO</span> @endif
            </td>
          </tr>
        </table>

        <div class="sp14"></div>

        <div class="payTitle" style="margin-bottom:8px;">Síguenos en</div>
        <table class="socialRow" cellpadding="0" cellspacing="0">
          <tr>
            <td>
              @if($socFb)<img class="socialIco" src="{{ $socFb }}" alt="Facebook">@else <span class="b">f</span> @endif
            </td>
            <td>
              @if($socIn)<img class="socialIco" src="{{ $socIn }}" alt="LinkedIn">@else <span class="b">in</span> @endif
            </td>
            <td>
              @if($socYt)<img class="socialIco" src="{{ $socYt }}" alt="YouTube">@else <span class="b">▶</span> @endif
            </td>
            <td style="padding-right:0;">
              @if($socIg)<img class="socialIco" src="{{ $socIg }}" alt="Instagram">@else <span class="b">◎</span> @endif
            </td>
          </tr>
        </table>
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
      </div>
    </td>

    {{-- Derecha: desglose --}}
    <td width="32%" style="vertical-align:top; padding-left:10px;">
      <div class="card">
        <div class="payTitle">Desglose del importa a pagar</div>
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
            <td class="r b">$ {{ number_format($total, 2) }}</td>
          </tr>
        </table>
      </div>
    </td>
  </tr>
</table>

</body>
</html>
