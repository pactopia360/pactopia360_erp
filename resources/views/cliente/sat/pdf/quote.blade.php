{{-- resources/views/cliente/sat/pdf/quote.blade.php --}}
{{-- P360 · SAT · Cotización PDF (v2.0 · Pro Layout, no dup logo) --}}

@php
    use Illuminate\Support\Carbon;

    $issuer = $issuer ?? [];
    $appName = (string)($issuer['name'] ?? (config('app.name') ?: 'Pactopia360'));

    $generatedAt = $generated_at ?? null;
    if ($generatedAt instanceof \DateTimeInterface) {
        $generatedAtStr = Carbon::instance($generatedAt)->format('Y-m-d H:i');
    } else {
        try { $generatedAtStr = Carbon::parse((string)$generatedAt)->format('Y-m-d H:i'); }
        catch (\Throwable) { $generatedAtStr = (string) $generatedAt; }
    }

    $validUntilStr = '';
    if (($valid_until ?? null) instanceof \DateTimeInterface) $validUntilStr = Carbon::instance($valid_until)->format('Y-m-d');
    else {
        try { $validUntilStr = Carbon::parse((string)($valid_until ?? ''))->format('Y-m-d'); }
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

    // Strings defensivos
    $empresa = (string) ($empresa ?? '—');
    $plan    = (string) ($plan ?? '—');
    $folio   = (string) ($folio ?? '—');
    $cuenta  = (string) ($cuenta_id ?? '—');

    $xmlCount = (int) ($xml_count ?? 0);
    $ivaRate  = (int) ($iva_rate ?? 0);

    $noteTxt = trim((string) ($note ?? ''));

    $hash = (string) ($quote_hash ?? '');

    $site  = trim((string)($issuer['website'] ?? ''));
    $email = trim((string)($issuer['email'] ?? ''));
    $phone = trim((string)($issuer['phone'] ?? ''));
@endphp

<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Cotización · Descarga SAT</title>
    <style>
        /* PDF base (compacto, no “gigante”) */
        @page { margin: 16mm 14mm 18mm 14mm; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #0b1220;
            font-size: 11px;
            line-height: 1.35;
        }

        :root{
            --ink:#0b1220;
            --mut:#5b667a;
            --bd:#e5e7eb;
            --soft:#f8fafc;
            --soft2:#fbfdff;
            --accent:#0f172a; /* navy */
        }

        /* Top accent bar */
        .bar {
            height: 10px;
            border-radius: 999px;
            background: var(--accent);
            margin-bottom: 10px;
        }

        /* Header card */
        .card {
            border: 1px solid var(--bd);
            border-radius: 12px;
            padding: 12px 14px;
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

        /* Identity block */
        .idWrap {
            display: table;
            width: 100%;
        }
        .idLogo, .idText {
            display: table-cell;
            vertical-align: middle;
        }
        .idLogo { width: 190px; }
        .idLogo img { width: 176px; height: auto; }


        .idText .sub {
            font-size: 10px;
            color: var(--mut);
            margin-top: 2px;
        }

        /* If no logo, show name as wordmark */
        .wordmark {
            font-size: 16px;
            font-weight: 900;
            letter-spacing: .2px;
            margin: 0;
        }

        /* Document title and badge */
        .docTitle {
            margin: 0;
            font-size: 15px;
            font-weight: 900;
        }
        .badge {
            display: inline-block;
            margin-top: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            border: 1px solid #dbe2ea;
            background: var(--soft);
            color: #263244;
            font-size: 10px;
            font-weight: 700;
        }

        /* Key/Value */
        .kv {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid var(--bd);
        }
        .kv .line {
            margin: 2px 0;
            font-size: 10px;
            color: var(--mut);
        }
        .kv .line b { color: var(--ink); }

        /* Sections */
        .section {
            margin-top: 10px;
            border: 1px solid var(--bd);
            border-radius: 12px;
            padding: 10px 12px;
            background: #fff;
        }
        .sectionTitle {
            margin: 0 0 8px;
            font-size: 12px;
            font-weight: 900;
        }

        /* Table */
        table { width: 100%; border-collapse: collapse; }
        th, td {
            padding: 8px 8px;
            border-bottom: 1px solid #eef2f7;
            vertical-align: top;
        }
        th {
            background: var(--soft);
            border-bottom: 1px solid var(--bd);
            text-align: left;
            font-size: 10px;
            color: #334155;
            font-weight: 900;
        }
        td.num, th.num { text-align: right; white-space: nowrap; }
        .muted { color: var(--mut); font-size: 10px; }
        .conceptTitle { font-weight: 900; font-size: 11px; }
        .conceptDesc  { margin-top: 2px; }

        /* Totals grid */
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

        .sumBox {
            border: 1px solid var(--bd);
            border-radius: 12px;
            background: var(--soft2);
            padding: 10px 10px;
        }

        .sumLine {
            display: table;
            width: 100%;
            margin: 4px 0;
        }
        .sumLabel, .sumVal { display: table-cell; }
        .sumLabel { color: var(--mut); font-size: 10px; }
        .sumVal { text-align: right; font-size: 10px; font-weight: 800; }

        .grand {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 2px solid var(--accent);
        }
        .grand .sumLabel, .grand .sumVal {
            font-size: 12px;
            font-weight: 900;
            color: var(--ink);
        }

        /* Terms */
        .terms {
            margin-top: 10px;
            border: 1px dashed #cbd5e1;
            border-radius: 12px;
            background: #fff;
            padding: 10px 12px;
        }
        .termsTitle { font-weight: 900; font-size: 11px; margin: 0 0 6px; }
        .terms ul { margin: 0 0 0 18px; padding: 0; }
        .terms li { margin: 4px 0; color: var(--mut); font-size: 10px; }

        /* Watermark subtle */
        .wm {
            position: fixed;
            top: 46%;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 60px;
            font-weight: 900;
            color: rgba(15, 23, 42, 0.035);
            transform: rotate(-18deg);
            z-index: -1;
        }

        /* Footer */
        .footer {
            position: fixed;
            bottom: 10mm;
            left: 14mm;
            right: 14mm;
            color: #6b7280;
            font-size: 9px;
        }
        .footerRow { display: table; width: 100%; }
        .footerL, .footerR { display: table-cell; vertical-align: top; }
        .footerR { text-align: right; }
        .hash { font-family: DejaVu Sans, monospace; letter-spacing: .5px; }
    </style>
</head>
<body>
    <div class="wm">COTIZACIÓN</div>

    <div class="bar"></div>

    <div class="card">
        <div class="row">
            <div class="col colL">
                <div class="idWrap">
                    <div class="idLogo">
                        @if($hasLogo)
                            <img src="{{ $logoData }}" alt="Logo">
                        @else
                            <p class="wordmark">{{ $appName }}</p>
                        @endif
                    </div>
                    <div class="idText">
                        {{-- ✅ NO duplicar nombre grande cuando hay logo (el logo ya trae wordmark) --}}
                        @if($hasLogo)
                            <div class="sub">
                                {{ $site !== '' ? $site : (string) (config('app.url') ?: '') }}
                                @if($email !== '') · {{ $email }} @endif
                                @if($phone !== '') · {{ $phone }} @endif
                            </div>
                        @else
                            <div class="sub">
                                {{ $site !== '' ? $site : (string) (config('app.url') ?: '') }}
                                @if($email !== '') · {{ $email }} @endif
                                @if($phone !== '') · {{ $phone }} @endif
                            </div>
                        @endif
                    </div>
                </div>

                <div class="kv">
                    <div class="line"><b>Cliente:</b> {{ $empresa }}</div>
                    <div class="line"><b>Cuenta:</b> {{ $cuenta }}</div>
                    <div class="line"><b>Plan:</b> {{ $plan }}</div>
                </div>
            </div>

            <div class="col colR">
                <p class="docTitle">Cotización · Descarga SAT</p>
                <span class="badge">Documento informativo</span>

                <div class="kv" style="text-align:right">
                    <div class="line"><b>Folio:</b> {{ $folio }}</div>
                    <div class="line"><b>Generado:</b> {{ $generatedAtStr }}</div>
                    <div class="line"><b>Vigencia:</b> {{ $validUntilStr }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="section">
        <p class="sectionTitle">Detalle</p>

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
                        <div class="conceptTitle">Descarga de CFDI (XML)</div>
                        <div class="muted conceptDesc">
                            Descarga y armado de paquete. El volumen final se confirma con el conteo definitivo.
                        </div>
                    </td>
                    <td class="num">{{ number_format($xmlCount, 0, '.', ',') }}</td>
                    <td class="num muted">—</td>
                    <td class="num"><b>{{ $money($base ?? 0) }}</b></td>
                </tr>
            </tbody>
        </table>

        <div class="totalsGrid">
            <div class="tL">
                <div class="muted">
                    <b>Nota de precio:</b>
                    @if($noteTxt !== '')
                        {{ $noteTxt }}
                    @else
                        Los precios pueden variar según validaciones, disponibilidad y políticas vigentes.
                    @endif
                </div>
                <div class="muted" style="margin-top:6px">
                    * Esta cotización es referencial. No garantiza disponibilidad del SAT ni volumen final.
                    El total final puede cambiar con el conteo definitivo.
                </div>
            </div>

            <div class="tR">
                <div class="sumBox">
                    <div class="sumLine">
                        <div class="sumLabel">Base</div>
                        <div class="sumVal">{{ $money($base ?? 0) }}</div>
                    </div>
                    <div class="sumLine">
                        <div class="sumLabel">Descuento</div>
                        <div class="sumVal">- {{ $money($discount_amount ?? 0) }}</div>
                    </div>
                    <div class="sumLine">
                        <div class="sumLabel">Subtotal</div>
                        <div class="sumVal">{{ $money($subtotal ?? 0) }}</div>
                    </div>
                    <div class="sumLine">
                        <div class="sumLabel">IVA {{ $ivaRate }}%</div>
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
        <p class="termsTitle">Condiciones y alcance</p>
        <ul>
            <li>Vigencia: hasta <b>{{ $validUntilStr }}</b>. Después de esa fecha, la tarifa puede cambiar.</li>
            <li>Los precios pueden variar por validaciones, conteo definitivo, cambios de catálogo y/o disponibilidad.</li>
            <li>No es factura ni comprobante fiscal. Para contratar/confirmar, se genera orden/pago correspondiente.</li>
            <li>La descarga depende de la disponibilidad del SAT y de las credenciales configuradas.</li>
            <li>En caso de incidencias del SAT o bloqueo temporal, los tiempos de entrega pueden variar.</li>
        </ul>
    </div>

    <div class="footer">
        <div class="footerRow">
            <div class="footerL">
                {{ $appName }} · Cotización de referencia
                @if($email !== '') · {{ $email }} @endif
            </div>
            <div class="footerR">
                Huella: <span class="hash">{{ $hash !== '' ? $hash : '—' }}</span>
            </div>
        </div>
    </div>
</body>
</html>
