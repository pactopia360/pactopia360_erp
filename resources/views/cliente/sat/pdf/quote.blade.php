{{-- resources/views/cliente/sat/pdf/quote.blade.php --}}
{{-- P360 · SAT · Cotización PDF (v2.0 · Pro Layout, no dup logo) --}}

@php
    use Illuminate\Support\Carbon;

    $issuer = $issuer ?? [];
    $appName = (string)($issuer['name'] ?? (config('app.name') ?: 'Pactopia360'));

    $generatedAt = $generated_at ?? null;
    if ($generatedAt instanceof \DateTimeInterface) {
        $generatedAtStr = Carbon::instance($generatedAt)->format('d/m/Y H:i');
    } else {
        try { $generatedAtStr = Carbon::parse((string)$generatedAt)->format('d/m/Y H:i'); }
        catch (\Throwable) { $generatedAtStr = (string) $generatedAt; }
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

    $logoPath = (string)($issuer['logo_public_path'] ?? '');
    $logoData = null;
    $hasLogo  = false;
    try {
        if ($logoPath && file_exists($logoPath)) {
            $mime = 'image/png';
            $ext = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg'], true)) $mime = 'image/jpeg';
            if ($ext === 'svg') $mime = 'image/svg+xml';
            $logoData = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($logoPath));
            $hasLogo = (bool)$logoData;
        }
    } catch (\Throwable) {
        $logoData = null;
        $hasLogo  = false;
    }

    $empresa = (string) ($empresa ?? '—');
    $plan    = (string) ($plan ?? '—');
    $folio   = (string) ($folio ?? '—');
    $cuenta  = (string) ($cuenta_id ?? '—');
    $rfc     = trim((string) ($rfc ?? ''));
    $razonSocial = trim((string) ($razon_social ?? ''));
    $tipoLabel = trim((string) ($tipo_label ?? 'Emitidos'));
    $periodoLabel = trim((string) ($periodo_label ?? 'Periodo no definido'));
    $conceptoTxt = trim((string) ($concepto ?? 'Cotización SAT'));
    $notesTxt = trim((string) ($notes ?? ''));
    $noteTxt = trim((string) ($note ?? ''));
    $hash = (string) ($quote_hash ?? '');

    $site  = trim((string)($issuer['website'] ?? ''));
    $email = trim((string)($issuer['email'] ?? ''));
    $phone = trim((string)($issuer['phone'] ?? ''));

    $xmlCount = (int) ($xml_count ?? 0);
    $ivaRate  = (float) ($iva_rate ?? 0);

    $protection = is_array($protection ?? null) ? $protection : [];
    $isSimulation = (bool) ($is_simulation ?? false);
    $isFormalQuote = (bool) ($is_formal_quote ?? false);

    $watermark = (string) ($protection['watermark'] ?? ($isSimulation ? 'SIMULACIÓN / SIN VALIDEZ COMERCIAL' : 'COTIZACIÓN PACTOPIA'));
    $documentTitle = (string) ($protection['document_title'] ?? ($isSimulation ? 'Simulación de cotización SAT' : 'Cotización de servicio SAT'));
    $documentSubtitle = (string) ($protection['document_subtitle'] ?? '');
    $legalNotice = (string) ($protection['legal_notice'] ?? '');
    $pricingNotice = (string) ($protection['pricing_notice'] ?? '');
    $satDependencyNotice = (string) ($protection['sat_dependency_notice'] ?? '');
    $footerNotice = (string) ($protection['footer_notice'] ?? '');
    $showNoValidityBadge = (bool) ($protection['show_no_validity_badge'] ?? false);

    $discountCodeApplied = trim((string) ($discount_code_applied ?? $discount_code ?? ''));
    $discountLabel = trim((string) ($discount_label ?? ''));
@endphp

<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Cotización · Descarga SAT</title>
    <style>
        @page { margin: 15mm 14mm 18mm 14mm; }
        * { box-sizing: border-box; }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #142033;
            font-size: 10.6px;
            line-height: 1.45;
        }

        :root{
            --ink:#142033;
            --mut:#607086;
            --bd:#dce5ef;
            --soft:#f6f9fc;
            --soft2:#fbfdff;
            --accent:#0f172a;
            --accent2:#1f3b73;
            --warn:#c77a00;
        }

        .wm {
            position: fixed;
            top: 40%;
            left: -8%;
            right: -8%;
            text-align: center;
            font-size: 52px;
            font-weight: 900;
            color: rgba(15, 23, 42, 0.05);
            transform: rotate(-20deg);
            z-index: -1;
            letter-spacing: 1px;
        }

        .topbar {
            height: 10px;
            border-radius: 999px;
            background: var(--accent);
            margin-bottom: 10px;
        }

        .hero {
            border: 1px solid var(--bd);
            border-radius: 14px;
            padding: 14px 15px;
            background: #fff;
        }

        .row {
            display: table;
            width: 100%;
        }

        .col {
            display: table-cell;
            vertical-align: top;
        }

        .colL { width: 58%; }
        .colR { width: 42%; text-align: right; }

        .brandWrap {
            display: table;
            width: 100%;
        }

        .brandLogo,
        .brandText {
            display: table-cell;
            vertical-align: middle;
        }

        .brandLogo { width: 190px; }
        .brandLogo img { width: 176px; height: auto; }

        .wordmark {
            font-size: 18px;
            font-weight: 900;
            margin: 0;
            color: var(--accent);
        }

        .brandSub {
            margin-top: 3px;
            color: var(--mut);
            font-size: 9.5px;
            line-height: 1.45;
        }

        .docTitle {
            margin: 0;
            font-size: 16px;
            font-weight: 900;
            color: var(--accent);
        }

        .docSubtitle {
            margin: 5px 0 0;
            font-size: 10px;
            color: var(--mut);
            line-height: 1.45;
        }

        .badge {
            display: inline-block;
            margin-top: 7px;
            padding: 4px 10px;
            border-radius: 999px;
            border: 1px solid #d9e3ef;
            background: var(--soft);
            color: #25364f;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: .03em;
        }

        .badgeWarn {
            background: #fff7e8;
            border-color: #ffd798;
            color: #a76300;
        }

        .meta {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid var(--bd);
        }

        .meta .line {
            margin: 3px 0;
            font-size: 9.8px;
            color: var(--mut);
        }

        .meta .line b {
            color: var(--ink);
        }

        .section {
            margin-top: 10px;
            border: 1px solid var(--bd);
            border-radius: 14px;
            padding: 11px 12px;
            background: #fff;
        }

        .sectionTitle {
            margin: 0 0 8px;
            font-size: 11.6px;
            font-weight: 900;
            color: var(--accent);
        }

        .lead {
            margin: 0;
            color: #4f6078;
            font-size: 9.8px;
            line-height: 1.55;
        }

        .miniGrid {
            display: table;
            width: 100%;
            margin-top: 8px;
        }

        .miniCell {
            display: table-cell;
            width: 25%;
            vertical-align: top;
            padding-right: 8px;
        }

        .miniCard {
            min-height: 62px;
            padding: 9px 10px;
            border: 1px solid var(--bd);
            border-radius: 12px;
            background: var(--soft2);
        }

        .miniLabel {
            display: block;
            color: var(--mut);
            font-size: 8.9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .04em;
            margin-bottom: 4px;
        }

        .miniVal {
            color: var(--ink);
            font-size: 10.5px;
            font-weight: 800;
            line-height: 1.45;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 8px 8px;
            border-bottom: 1px solid #ebf0f5;
            vertical-align: top;
        }

        th {
            background: var(--soft);
            border-bottom: 1px solid var(--bd);
            text-align: left;
            font-size: 9px;
            color: #334155;
            font-weight: 900;
        }

        td.num, th.num {
            text-align: right;
            white-space: nowrap;
        }

        .conceptTitle {
            font-weight: 900;
            font-size: 10.8px;
            color: var(--ink);
        }

        .conceptDesc {
            margin-top: 3px;
            color: var(--mut);
            font-size: 9.5px;
            line-height: 1.5;
        }

        .totalsGrid {
            display: table;
            width: 100%;
            margin-top: 10px;
        }

        .tL, .tR {
            display: table-cell;
            vertical-align: top;
        }

        .tL { width: 58%; padding-right: 10px; }
        .tR { width: 42%; }

        .noticeBox {
            border: 1px solid #e4ebf3;
            border-radius: 12px;
            background: #fbfdff;
            padding: 10px 11px;
            margin-bottom: 8px;
        }

        .noticeBox strong {
            color: var(--accent);
        }

        .noticeWarn {
            background: #fff8ed;
            border-color: #ffd9a8;
        }

        .noticeText {
            color: #576983;
            font-size: 9.6px;
            line-height: 1.55;
        }

        .sumBox {
            border: 1px solid var(--bd);
            border-radius: 12px;
            background: var(--soft2);
            padding: 10px;
        }

        .sumLine {
            display: table;
            width: 100%;
            margin: 4px 0;
        }

        .sumLabel,
        .sumVal {
            display: table-cell;
        }

        .sumLabel {
            color: var(--mut);
            font-size: 9.6px;
        }

        .sumVal {
            text-align: right;
            font-size: 9.8px;
            font-weight: 800;
        }

        .grand {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 2px solid var(--accent);
        }

        .grand .sumLabel,
        .grand .sumVal {
            font-size: 11.2px;
            font-weight: 900;
            color: var(--ink);
        }

        .terms {
            margin-top: 10px;
            border: 1px dashed #c9d6e4;
            border-radius: 13px;
            background: #fff;
            padding: 10px 12px;
        }

        .termsTitle {
            margin: 0 0 6px;
            font-size: 10.8px;
            font-weight: 900;
            color: var(--accent);
        }

        .terms ul {
            margin: 0 0 0 16px;
            padding: 0;
        }

        .terms li {
            margin: 4px 0;
            color: #586a83;
            font-size: 9.5px;
            line-height: 1.5;
        }

        .signBlock {
            margin-top: 10px;
            border: 1px solid var(--bd);
            border-radius: 13px;
            background: #fff;
            padding: 10px 12px;
        }

        .signTitle {
            margin: 0 0 10px;
            font-size: 10.8px;
            font-weight: 900;
            color: var(--accent);
        }

        .signRow {
            display: table;
            width: 100%;
            margin-top: 18px;
        }

        .signCell {
            display: table-cell;
            width: 50%;
            vertical-align: bottom;
            padding-right: 14px;
        }

        .signLine {
            height: 1px;
            background: #9fb0c3;
            margin-bottom: 4px;
        }

        .signLabel {
            color: var(--mut);
            font-size: 9.3px;
        }

        .footer {
            position: fixed;
            bottom: 8mm;
            left: 14mm;
            right: 14mm;
            color: #69788f;
            font-size: 8.8px;
        }

        .footerRow {
            display: table;
            width: 100%;
        }

        .footerL,
        .footerR {
            display: table-cell;
            vertical-align: top;
        }

        .footerR {
            text-align: right;
        }

        .hash {
            font-family: DejaVu Sans, monospace;
            letter-spacing: .4px;
        }
    </style>
</head>
<body>
    <div class="wm">{{ $watermark }}</div>

    <div class="topbar"></div>

    <div class="hero">
        <div class="row">
            <div class="col colL">
                <div class="brandWrap">
                    <div class="brandLogo">
                        @if($hasLogo)
                            <img src="{{ $logoData }}" alt="Logo">
                        @else
                            <p class="wordmark">{{ $appName }}</p>
                        @endif
                    </div>
                    <div class="brandText">
                        <div class="brandSub">
                            {{ $site !== '' ? $site : (string) (config('app.url') ?: '') }}
                            @if($email !== '') · {{ $email }} @endif
                            @if($phone !== '') · {{ $phone }} @endif
                        </div>
                    </div>
                </div>

                <div class="meta">
                    <div class="line"><b>Cliente:</b> {{ $empresa }}</div>
                    <div class="line"><b>Cuenta:</b> {{ $cuenta }}</div>
                    <div class="line"><b>RFC:</b> {{ $rfc !== '' ? $rfc : 'N/D' }}</div>
                    <div class="line"><b>Razón social:</b> {{ $razonSocial !== '' ? $razonSocial : 'N/D' }}</div>
                </div>
            </div>

            <div class="col colR">
                <p class="docTitle">{{ $documentTitle }}</p>
                <p class="docSubtitle">{{ $documentSubtitle }}</p>

                @if($showNoValidityBadge)
                    <span class="badge badgeWarn">SIN VALIDEZ COMERCIAL</span>
                @else
                    <span class="badge">DOCUMENTO COMERCIAL CONTROLADO</span>
                @endif

                <div class="meta" style="text-align:right">
                    <div class="line"><b>Folio:</b> {{ $folio }}</div>
                    <div class="line"><b>Generado:</b> {{ $generatedAtStr }}</div>
                    <div class="line"><b>Vigencia:</b> {{ $validUntilStr !== '' ? $validUntilStr : 'N/D' }}</div>
                    <div class="line"><b>Plan:</b> {{ $plan }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="section">
        <p class="sectionTitle">Resumen de la solicitud</p>
        <p class="lead">
            Propuesta técnica y económica para el servicio de descarga de CFDI desde el portal del SAT mediante la plataforma Pactopia.
            El alcance se limita a procesos de extracción, organización y entrega de información conforme al periodo y volumen estimado solicitados.
        </p>

        <div class="miniGrid">
            <div class="miniCell">
                <div class="miniCard">
                    <span class="miniLabel">Tipo de solicitud</span>
                    <div class="miniVal">{{ $tipoLabel }}</div>
                </div>
            </div>
            <div class="miniCell">
                <div class="miniCard">
                    <span class="miniLabel">Periodo</span>
                    <div class="miniVal">{{ $periodoLabel }}</div>
                </div>
            </div>
            <div class="miniCell">
                <div class="miniCard">
                    <span class="miniLabel">XML estimados</span>
                    <div class="miniVal">{{ number_format($xmlCount, 0, '.', ',') }}</div>
                </div>
            </div>
            <div class="miniCell">
                <div class="miniCard">
                    <span class="miniLabel">Documento</span>
                    <div class="miniVal">{{ $isSimulation ? 'Simulación' : 'Cotización formal' }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="section">
        <p class="sectionTitle">Detalle económico</p>

        <table>
            <thead>
                <tr>
                    <th>Concepto</th>
                    <th class="num">Cantidad</th>
                    <th class="num">Precio</th>
                    <th class="num">Importe</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <div class="conceptTitle">{{ $conceptoTxt }}</div>
                        <div class="conceptDesc">
                            Servicio técnico de descarga y organización de CFDI desde el SAT. No incluye análisis contable, fiscal, conciliación, interpretación de datos ni retrabajos posteriores no contratados.
                        </div>
                    </td>
                    <td class="num">{{ number_format($xmlCount, 0, '.', ',') }}</td>
                    <td class="num">—</td>
                    <td class="num"><b>{{ $money($base ?? 0) }}</b></td>
                </tr>
            </tbody>
        </table>

        <div class="totalsGrid">
            <div class="tL">
                @if($pricingNotice !== '')
                    <div class="noticeBox {{ $isSimulation ? 'noticeWarn' : '' }}">
                        <div class="noticeText">
                            <strong>Condición comercial:</strong> {{ $pricingNotice }}
                        </div>
                    </div>
                @endif

                @if($legalNotice !== '')
                    <div class="noticeBox">
                        <div class="noticeText">
                            <strong>Alcance y exclusiones:</strong> {{ $legalNotice }}
                        </div>
                    </div>
                @endif

                @if($satDependencyNotice !== '')
                    <div class="noticeBox">
                        <div class="noticeText">
                            <strong>Dependencia SAT:</strong> {{ $satDependencyNotice }}
                        </div>
                    </div>
                @endif

                @if($notesTxt !== '')
                    <div class="noticeBox">
                        <div class="noticeText">
                            <strong>Notas de la solicitud:</strong> {{ $notesTxt }}
                        </div>
                    </div>
                @endif

                @if($noteTxt !== '')
                    <div class="noticeBox">
                        <div class="noticeText">
                            <strong>Nota comercial:</strong> {{ $noteTxt }}
                        </div>
                    </div>
                @endif
            </div>

            <div class="tR">
                <div class="sumBox">
                    <div class="sumLine">
                        <div class="sumLabel">Base</div>
                        <div class="sumVal">{{ $money($base ?? 0) }}</div>
                    </div>

                    @if(($discount_amount ?? 0) > 0 || $discountLabel !== '' || $discountCodeApplied !== '')
                        <div class="sumLine">
                            <div class="sumLabel">
                                Descuento
                                @if($discountLabel !== '')
                                    · {{ $discountLabel }}
                                @elseif($discountCodeApplied !== '')
                                    · {{ $discountCodeApplied }}
                                @endif
                            </div>
                            <div class="sumVal">- {{ $money($discount_amount ?? 0) }}</div>
                        </div>
                    @endif

                    <div class="sumLine">
                        <div class="sumLabel">Subtotal</div>
                        <div class="sumVal">{{ $money($subtotal ?? 0) }}</div>
                    </div>

                    <div class="sumLine">
                        <div class="sumLabel">IVA {{ number_format($ivaRate, 2, '.', '') }}%</div>
                        <div class="sumVal">{{ $money($iva_amount ?? 0) }}</div>
                    </div>

                    <div class="sumLine grand">
                        <div class="sumLabel">Total</div>
                        <div class="sumVal">{{ $money($total ?? 0) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="terms">
        <p class="termsTitle">Condiciones y protección comercial</p>
        <ul>
            <li>La presente propuesta contempla únicamente el alcance técnico aquí descrito.</li>
            <li>No incluye validaciones fiscales, contables, conciliaciones, interpretación de información, reprocesos, retrabajos ni desarrollos adicionales no expresamente contratados.</li>
            <li>Los tiempos y resultados dependen del portal del SAT, sus límites operativos, disponibilidad, mantenimientos, bloqueos o intermitencias.</li>
            <li>El cliente es responsable del uso, resguardo, análisis y explotación de la información entregada.</li>
            <li>Cualquier requerimiento fuera de este alcance será considerado como servicio adicional sujeto a nueva cotización.</li>
            @if($isSimulation)
                <li>Este documento corresponde a una simulación informativa y el costo final puede variar conforme al volumen definitivo, validaciones operativas y condiciones comerciales vigentes.</li>
            @else
                <li>La aceptación de esta cotización implica conformidad con el alcance, exclusiones, condiciones comerciales y vigencia aquí señalados.</li>
            @endif
        </ul>
    </div>

    @if(!$isSimulation)
        <div class="signBlock">
            <p class="signTitle">Autorización de propuesta económica</p>

            <div class="signRow">
                <div class="signCell">
                    <div class="signLine"></div>
                    <div class="signLabel">Nombre y firma de autorización</div>
                </div>
                <div class="signCell">
                    <div class="signLine"></div>
                    <div class="signLabel">Fecha de autorización</div>
                </div>
            </div>
        </div>
    @endif

    <div class="footer">
        <div class="footerRow">
            <div class="footerL">
                {{ $appName }}
                @if($footerNotice !== '') · {{ $footerNotice }} @endif
                @if($email !== '') · {{ $email }} @endif
            </div>
            <div class="footerR">
                Huella: <span class="hash">{{ $hash !== '' ? $hash : '—' }}</span>
            </div>
        </div>
    </div>
</body>
</html>
