{{-- resources/views/cliente/sat/pdf/quote.blade.php --}}
{{-- P360 · SAT · Cotización PDF (v2.0 · Pro Layout, no dup logo) --}}

@php
    use Illuminate\Support\Carbon;

    $issuer = $issuer ?? [];
    $appName = (string)($issuer['name'] ?? (config('app.name') ?: 'Pactopia360'));

    $generatedAt = $generated_at ?? null;
    if ($generatedAt instanceof \DateTimeInterface) {
        $generatedAtStr = Carbon::instance($generatedAt)->format('d/m/Y');
    } else {
        try { $generatedAtStr = Carbon::parse((string)$generatedAt)->format('d/m/Y'); }
        catch (\Throwable) { $generatedAtStr = (string) $generatedAt; }
    }

    $generatedAtVersion = '';
    if ($generatedAt instanceof \DateTimeInterface) {
        $generatedAtVersion = Carbon::instance($generatedAt)->format('YmdHi');
    } else {
        try { $generatedAtVersion = Carbon::parse((string)$generatedAt)->format('YmdHi'); }
        catch (\Throwable) { $generatedAtVersion = date('YmdHi'); }
    }

    $validUntilStr = '';
    if (($valid_until ?? null) instanceof \DateTimeInterface) {
        $validUntilStr = Carbon::instance($valid_until)->format('d/m/Y');
    } else {
        try { $validUntilStr = Carbon::parse((string)($valid_until ?? ''))->format('d/m/Y'); }
        catch (\Throwable) { $validUntilStr = (string) ($valid_until ?? ''); }
    }

    $money = function ($v) {
        $n = is_numeric($v) ? (float)$v : 0.0;
        return '$' . number_format($n, 2, '.', ',');
    };

    $logoPath = public_path('assets/brand/pdf/Pactopia - Letra AZUL.png');
    $logoData = null;
    $hasLogo  = false;

    try {
        if ($logoPath && file_exists($logoPath)) {
            $mime = 'image/png';
            $logoData = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($logoPath));
            $hasLogo = (bool)$logoData;
        }
    } catch (\Throwable) {
        $logoData = null;
        $hasLogo  = false;
    }

    $empresa = trim((string) ($empresa ?? ''));
    $plan    = trim((string) ($plan ?? 'PRO'));
    $folio   = trim((string) ($folio ?? '—'));
    $cuenta  = trim((string) ($cuenta_id ?? '—'));
    $rfc     = trim((string) ($rfc ?? ''));
    $razonSocial = trim((string) ($razon_social ?? ''));
    $tipoLabel = trim((string) ($tipo_label ?? 'Emitidos'));
    $periodoLabel = trim((string) ($periodo_label ?? 'Periodo no definido'));
    $conceptoTxt = trim((string) ($concepto ?? 'Cotización SAT'));
    $notesTxt = trim((string) ($notes ?? ''));
    $noteTxt = trim((string) ($note ?? ''));
    $hash = (string) ($quote_hash ?? '');

    $site  = trim((string)($issuer['website'] ?? 'https://pactopia360.com'));
    $email = trim((string)($issuer['email'] ?? 'notificaciones@pactopia360.com'));
    $phone = trim((string)($issuer['phone'] ?? ''));

    $xmlCount = (int) ($xml_count ?? 0);
    $ivaRate  = (float) ($iva_rate ?? 16);

    $baseValue         = (float) ($base ?? 0);
    $discountAmount    = (float) ($discount_amount ?? 0);
    $subtotalValue     = (float) ($subtotal ?? 0);
    $ivaAmountValue    = (float) ($iva_amount ?? 0);
    $totalValue        = (float) ($total ?? 0);

    $protection = is_array($protection ?? null) ? $protection : [];
    $isSimulation = (bool) ($is_simulation ?? false);
    $isFormalQuote = (bool) ($is_formal_quote ?? true);

    $discountCodeApplied = trim((string) ($discount_code_applied ?? $discount_code ?? ''));
    $discountLabel = trim((string) ($discount_label ?? ''));

    $dateFromRaw = trim((string) ($date_from ?? ''));
    $dateToRaw   = trim((string) ($date_to ?? ''));

    $fmtDate = function ($value) {
        if (!$value) return '';
        try {
            return Carbon::parse((string)$value)->format('d/m/Y');
        } catch (\Throwable) {
            return (string)$value;
        }
    };

    $dateFromStr = $fmtDate($dateFromRaw);
    $dateToStr   = $fmtDate($dateToRaw);

    $periodoNarrativo = ($dateFromStr !== '' && $dateToStr !== '')
        ? ('del ' . $dateFromStr . ' al ' . $dateToStr)
        : $periodoLabel;

    $tipoSolicitudNarrativa = mb_strtolower($tipoLabel, 'UTF-8');

    $recipientName = trim((string) ($empresa !== '' ? $empresa : ($razonSocial !== '' ? $razonSocial : 'Cliente')));
    $recipientShort = trim((string) ($razonSocial !== '' ? $razonSocial : $empresa));

    $documentVersion = 'C1V1';

    $documentTitle = $isSimulation
        ? 'Simulación de propuesta económica'
        : 'Propuesta técnica y económica';

    $documentSubtitle = 'SERVICIO DE DESCARGAS MASIVAS SAT (MULTIEMPRESA)';

    $introParagraph1 = 'Por medio de la presente compartimos la propuesta técnica y económica para realizar el servicio de descarga de CFDI correspondiente al periodo solicitado desde el portal del SAT, mediante la infraestructura tecnológica de PACTOPIA360.';
    $introParagraph2 = 'El servicio tiene un alcance estrictamente técnico y se limita a la extracción, organización y entrega de información, sin incluir procesos de validación contable, fiscal, conciliación o interpretación de los datos descargados.';
    $introParagraph3 = 'La presente propuesta se emite con base en el volumen estimado informado al momento de su generación y podrá ajustarse en caso de variaciones materiales en el volumen, alcance operativo o condiciones comerciales aplicables.';

    $entregables = [
        'Archivos XML originales descargados desde el SAT.',
        'Organización de la información conforme al periodo solicitado.',
        'Entrega de archivos comprimidos para resguardo y transferencia.',
        'Un archivo de metadata estándar en formato Excel o CSV.',
        'Un reporte estándar de control operativo generado por la plataforma.',
    ];

    $noIncluye = [
        'Validaciones fiscales, contables o administrativas.',
        'Conciliaciones, auditorías, reconteos o interpretación de la información.',
        'Retrabajos, reprocesamientos, reintentos posteriores o nuevas solicitudes del mismo periodo.',
        'Ajustes derivados de revisiones internas del cliente.',
        'Desarrollos especiales, reportes personalizados o integraciones con ERP o sistemas externos.',
        'Capacitación, soporte dedicado, grupo de WhatsApp exclusivo o atención continua en sitio.',
    ];

    $dependenciasSat = [
        'Disponibilidad operativa del portal del SAT.',
        'Límites, bloqueos, mantenimientos o intermitencias del servicio.',
        'Tiempos de respuesta y estabilidad de la infraestructura externa al control de PACTOPIA.',
    ];

    $responsabilidadCliente = [
        'El resguardo de la información una vez entregada.',
        'El uso, explotación, análisis e interpretación posterior de los datos.',
        'Cualquier validación posterior a la entrega.',
    ];

    $commercialConditions = [
        'Precios expresados en MXN' . ($ivaRate > 0 ? ' más IVA.' : '.'),
        $isSimulation ? 'Documento informativo sin validez comercial para cobro.' : 'Pago sujeto a confirmación por el medio autorizado.',
        'Inicio del servicio posterior a la confirmación operativa y/o validación del pago.',
        'Los tiempos de entrega podrán ajustarse conforme a la disponibilidad del SAT sin que ello represente incumplimiento.',
    ];
@endphp

<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Cotización · Descarga SAT</title>
    <style>
    @page { margin: 14mm 12mm 18mm 12mm; }
    * { box-sizing: border-box; }

    body {
        font-family: DejaVu Sans, Arial, sans-serif;
        color: #111827;
        font-size: 11px;
        line-height: 1.58;
    }

    :root{
        --ink:#111827;
        --mut:#6b7280;
        --bd:#d8deea;
        --soft:#f7f9fc;
        --soft2:#fbfcfe;
        --brand:#9fb2d6;
        --brand-dark:#1c2541;
        --footer:#9cadcf;
    }

    .page-break {
        display: block;
        height: 0;
        margin: 0;
        page-break-before: always;
        break-before: page;
    }

    .cover-logo{
    text-align:center;
    margin-top:14px;
    margin-bottom:18px;
    overflow:hidden;
    }

    .cover-logo img{
        display:inline-block;
        width:auto;
        max-width:360px;
        max-height:78px;
        height:auto;
    }

    .cover-title{
        text-align:center;
        margin-top:2px;
        color:#6d6d6d;
        font-size:12px;
        line-height:1.42;
        letter-spacing:.02em;
        text-transform:uppercase;
    }

    .cover-version{
        margin-top:26px;
        text-align:right;
        color:#777;
        font-size:11px;
        line-height:1.35;
    }

    .cover-body{
        margin-top:20px;
        padding:0 34px;
        font-size:11.2px;
        line-height:1.68;
        color:#111;
    }

    .cover-body p{
        margin:0 0 11px 0;
    }

    .cover-sign{
        margin-top:12px;
    }

    .cover-page{
        position: relative;
        min-height: 220mm;
        overflow: hidden;
    }

    .cover-footer{
        position: absolute;
        left: 0;
        right: 0;
        bottom: 0;
        background: var(--footer);
        color:#fff;
        text-align:center;
        padding:10px 10px 9px;
        font-size:10px;
        line-height:1.45;
    }

    .cover-footer strong{
        display:block;
        font-size:15px;
        font-weight:500;
        letter-spacing:.01em;
    }

    .cover-page-spacer{
    height: 10mm;
    }

    .anexo-a-start{
        page-break-before: always;
        break-before: page;
    }

    .doc-title{
        font-size:18px;
        font-weight:900;
        color:var(--brand-dark);
        margin:0 0 10px;
        letter-spacing:-.02em;
    }

    .doc-subtitle{
        font-size:12px;
        font-weight:700;
        color:#616b7d;
        margin:0 0 18px;
    }

    .box{
        border:1px solid var(--bd);
        border-radius:14px;
        background:#fff;
        padding:14px 15px;
        margin-bottom:12px;
    }

    .box-title{
        margin:0 0 10px;
        font-size:12px;
        font-weight:900;
        color:#1f2937;
        text-transform:uppercase;
        letter-spacing:.04em;
    }

    .meta-table{
        width:100%;
        border-collapse:collapse;
    }

    .meta-table td{
        padding:5px 0;
        vertical-align:top;
        border:none;
        font-size:11px;
    }

    .meta-label{
        width:160px;
        color:#6b7280;
        font-weight:700;
    }

    .meta-value{
        color:#111827;
        font-weight:800;
    }

    .paragraph{
        margin:0 0 12px;
        color:#374151;
        font-size:11px;
        line-height:1.7;
    }

    .list{
        margin:0;
        padding-left:18px;
    }

    .list li{
        margin:0 0 7px;
        color:#374151;
        font-size:11px;
        line-height:1.65;
    }

    .econ-table{
        width:100%;
        border-collapse:collapse;
        margin-top:6px;
    }

    .econ-table th,
    .econ-table td{
        border:1px solid var(--bd);
        padding:8px 9px;
        vertical-align:top;
    }

    .econ-table th{
        background:#f4f7fb;
        color:#334155;
        font-size:10px;
        font-weight:900;
        text-transform:uppercase;
        letter-spacing:.03em;
        text-align:left;
    }

    .econ-table td{
        font-size:11px;
        color:#111827;
    }

    .num{
        text-align:right;
        white-space:nowrap;
    }

    .muted{
        color:#6b7280;
    }

    .totals{
        margin-top:12px;
        margin-left:auto;
        width:310px;
        border:1px solid var(--bd);
        border-radius:14px;
        background:#fbfcfe;
        padding:12px 14px;
    }

    .total-line{
        width:100%;
        border-collapse:collapse;
    }

    .total-line td{
        border:none;
        padding:4px 0;
        font-size:11px;
    }

    .total-line td:last-child{
        text-align:right;
        font-weight:800;
        color:#111827;
        white-space:nowrap;
    }

    .grand-total{
        margin-top:6px;
        padding-top:8px;
        border-top:2px solid #cbd5e1;
    }

    .grand-total td{
        font-size:13px;
        font-weight:900;
    }

    .mini-note{
        margin-top:10px;
        border:1px solid #e2e8f0;
        border-radius:12px;
        background:#f8fafc;
        padding:10px 12px;
        color:#4b5563;
        font-size:10.5px;
        line-height:1.65;
    }

    .signature-box{
        margin-top:16px;
        border:1px solid var(--bd);
        border-radius:14px;
        background:#fff;
        padding:14px 15px;
    }

    .signature-row{
        width:100%;
        border-collapse:collapse;
        margin-top:20px;
    }

    .signature-row td{
        width:50%;
        border:none;
        padding:0 10px 0 0;
        vertical-align:bottom;
    }

    .sign-line{
        height:1px;
        background:#94a3b8;
        margin-bottom:5px;
    }

    .sign-label{
        color:#6b7280;
        font-size:10px;
    }

    .footer-doc{
        margin-top:14px;
        color:#7b8798;
        font-size:9px;
        line-height:1.6;
        text-align:center;
    }
</style>
</head>
<body>
       {{-- PORTADA --}}
    <div class="cover-page">
        <div class="cover-logo">
            @if($hasLogo)
                <img src="{{ $logoData }}" alt="PACTOPIA">
            @else
                <div style="font-size:42px;font-weight:900;color:#9fb2d6;">PACTOPIA</div>
            @endif
        </div>

        <div class="cover-title">
            <div>SERVICIO DE DESCARGAS</div>
            <div>MASIVAS SAT (MULTIEMPRESA)</div>
        </div>

        <div class="cover-version">
            <div>{{ $generatedAtStr !== '' ? $generatedAtStr : '—' }}</div>
            <div>{{ $documentVersion }}</div>
        </div>

        <div class="cover-body">
            <p><strong>{{ $recipientName !== '' ? $recipientName : 'Cliente' }}</strong><br>{{ $recipientShort !== '' ? $recipientShort : ' ' }}</p>

            <p>Reciba un cordial saludo.</p>

            <p>{{ $introParagraph1 }}</p>
            <p>{{ $introParagraph2 }}</p>
            <p>{{ $introParagraph3 }}</p>

            <div class="cover-sign">
                <p><strong>Marco Cesar Padilla Díaz</strong><br>Dirección Comercial<br>Pactopia</p>
            </div>
        </div>

        <div class="cover-footer">
            <strong>PACTOPIA SAPI de CV</strong>
            ALL CONTENT COPYRIGHT©
        </div>
    </div>

   {{-- ANEXO A --}}
    <div class="cover-page-spacer"></div>

    <h1 class="doc-title anexo-a-start">ANEXO A · ANEXO TÉCNICO</h1>
    <p class="doc-subtitle">Alcance operativo, entregables, exclusiones y condiciones de ejecución</p>

    <div class="box">
        <p class="box-title">Datos de la propuesta</p>
        <table class="meta-table">
            <tr>
                <td class="meta-label">Documento</td>
                <td class="meta-value">{{ $documentTitle }}</td>
            </tr>
            <tr>
                <td class="meta-label">Folio</td>
                <td class="meta-value">{{ $folio }}</td>
            </tr>
            <tr>
                <td class="meta-label">Cliente</td>
                <td class="meta-value">{{ $empresa !== '' ? $empresa : 'N/D' }}</td>
            </tr>
            <tr>
                <td class="meta-label">RFC</td>
                <td class="meta-value">{{ $rfc !== '' ? $rfc : 'N/D' }}</td>
            </tr>
            <tr>
                <td class="meta-label">Razón social</td>
                <td class="meta-value">{{ $razonSocial !== '' ? $razonSocial : 'N/D' }}</td>
            </tr>
            <tr>
                <td class="meta-label">Tipo de solicitud</td>
                <td class="meta-value">{{ $tipoLabel }}</td>
            </tr>
            <tr>
                <td class="meta-label">Periodo solicitado</td>
                <td class="meta-value">{{ $periodoNarrativo }}</td>
            </tr>
            <tr>
                <td class="meta-label">Volumen estimado</td>
                <td class="meta-value">{{ number_format($xmlCount, 0, '.', ',') }} CFDI</td>
            </tr>
            <tr>
                <td class="meta-label">Vigencia comercial</td>
                <td class="meta-value">{{ $validUntilStr !== '' ? $validUntilStr : 'N/D' }}</td>
            </tr>
        </table>
    </div>

    <div class="box">
        <p class="box-title">Alcance del servicio</p>
        <p class="paragraph">
            La presente propuesta contempla única y exclusivamente la prestación del servicio técnico de descarga de CFDI desde el portal del SAT,
            correspondiente a una solicitud de tipo <strong>{{ mb_strtolower($tipoLabel, 'UTF-8') }}</strong> para el periodo <strong>{{ $periodoNarrativo }}</strong>,
            con un volumen aproximado de <strong>{{ number_format($xmlCount, 0, '.', ',') }} comprobantes</strong>.
        </p>
        <p class="paragraph">
            El alcance operativo se limita a la extracción, organización y entrega de la información conforme a los parámetros configurados al momento
            de la solicitud. No incluye procesos de revisión contable, fiscal, conciliación, interpretación de datos ni explotación posterior de la información.
        </p>
    </div>

    <div class="box">
        <p class="box-title">Entregables</p>
        <ul class="list">
            @foreach($entregables as $item)
                <li>{{ $item }}</li>
            @endforeach
        </ul>
        <div class="mini-note">
            La entrega de los elementos anteriores constituye el cierre operativo del servicio contratado.
        </div>
    </div>

    <div class="box">
        <p class="box-title">No incluye</p>
        <ul class="list">
            @foreach($noIncluye as $item)
                <li>{{ $item }}</li>
            @endforeach
        </ul>
    </div>

    <div class="box">
        <p class="box-title">Dependencias del SAT</p>
        <p class="paragraph">
            El servicio depende directamente de la disponibilidad del portal del SAT y de su infraestructura externa.
            PACTOPIA no tiene control sobre tiempos de respuesta, bloqueos, límites operativos, intermitencias o mantenimientos del servicio.
        </p>
        <ul class="list">
            @foreach($dependenciasSat as $item)
                <li>{{ $item }}</li>
            @endforeach
        </ul>
        <div class="mini-note">
            Por lo anterior, los tiempos de ejecución y entrega podrán ajustarse sin que ello represente incumplimiento contractual o comercial por parte de PACTOPIA.
        </div>
    </div>

    <div class="box">
        <p class="box-title">Responsabilidad del cliente</p>
        <ul class="list">
            @foreach($responsabilidadCliente as $item)
                <li>{{ $item }}</li>
            @endforeach
        </ul>
    </div>

    {{-- ANEXO C --}}
    <div class="page-break"></div>
    
    <h1 class="doc-title">ANEXO C · ANEXO ECONÓMICO</h1>
    <p class="doc-subtitle">Resumen económico, condiciones comerciales y autorización</p>

    <div class="box">
        <p class="box-title">Concepto económico</p>

        <table class="econ-table">
            <thead>
                <tr>
                    <th>Concepto</th>
                    <th>Descripción</th>
                    <th class="num">Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Servicio de descarga SAT</strong></td>
                    <td>
                        {{ $conceptoTxt }}<br>
                        <span class="muted">
                            Solicitud {{ mb_strtolower($tipoLabel, 'UTF-8') }} para el periodo {{ $periodoNarrativo }}
                            con un volumen aproximado de {{ number_format($xmlCount, 0, '.', ',') }} CFDI.
                        </span>
                    </td>
                    <td class="num">{{ $money($baseValue) }}</td>
                </tr>

                @if($discountAmount > 0)
                    <tr>
                        <td><strong>Descuento comercial</strong></td>
                        <td>
                            {{ $discountLabel !== '' ? $discountLabel : 'Descuento aplicado' }}
                            @if($discountCodeApplied !== '')
                                <br><span class="muted">Código: {{ $discountCodeApplied }}</span>
                            @endif
                        </td>
                        <td class="num">-{{ $money($discountAmount) }}</td>
                    </tr>
                @endif
            </tbody>
        </table>

        <div class="totals">
            <table class="total-line">
                <tr>
                    <td>Base</td>
                    <td>{{ $money($baseValue) }}</td>
                </tr>

                @if($discountAmount > 0)
                    <tr>
                        <td>Descuento</td>
                        <td>-{{ $money($discountAmount) }}</td>
                    </tr>
                @endif

                <tr>
                    <td>Subtotal</td>
                    <td>{{ $money($subtotalValue) }}</td>
                </tr>
                <tr>
                    <td>IVA {{ number_format($ivaRate, 2, '.', '') }}%</td>
                    <td>{{ $money($ivaAmountValue) }}</td>
                </tr>
                <tr class="grand-total">
                    <td>Total</td>
                    <td>{{ $money($totalValue) }}</td>
                </tr>
            </table>
        </div>
    </div>

    <div class="box">
        <p class="box-title">Condiciones comerciales</p>
        <ul class="list">
            @foreach($commercialConditions as $item)
                <li>{{ $item }}</li>
            @endforeach
        </ul>

        @if($notesTxt !== '')
            <div class="mini-note"><strong>Notas de la solicitud:</strong> {{ $notesTxt }}</div>
        @endif

        @if($noteTxt !== '')
            <div class="mini-note"><strong>Nota comercial:</strong> {{ $noteTxt }}</div>
        @endif
    </div>

    @if(!$isSimulation)
        <div class="signature-box">
            <p class="box-title">Autorización de propuesta económica</p>

            <table class="signature-row">
                <tr>
                    <td>
                        <div class="sign-line"></div>
                        <div class="sign-label">Autorización de propuesta económica firmada por</div>
                    </td>
                    <td>
                        <div class="sign-line"></div>
                        <div class="sign-label">Fecha de autorización</div>
                    </td>
                </tr>
            </table>
        </div>
    @endif

    <div class="footer-doc">
        {{ $appName }} · {{ $site }}
        @if($email !== '') · {{ $email }} @endif
        · Huella: {{ $hash !== '' ? $hash : '—' }}
    </div>
</body>
</html>
