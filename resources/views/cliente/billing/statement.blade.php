{{-- C:\wamp64\www\pactopia360_erp\resources\views\cliente\billing\statement.blade.php --}}
{{-- resources/views/cliente/billing/statement.blade.php (UI: v18.3 · paid robusto + factura fuera de mes modal + requestInvoice UX + loader modal) --}}
@extends('layouts.cliente')

@section('title', 'Estado de cuenta · Pactopia360')
@section('pageClass', 'page-estado-cuenta')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/client/css/estado-cuenta.css') }}?v={{ filemtime(public_path('assets/client/css/estado-cuenta.css')) }}">
@endpush

@section('content')

@php
  $mxn = fn($n) => '$' . number_format((float)$n, 2);

  // ==========================================================
  // 1) Normaliza rows: array + period válido (YYYY-MM)
  // ==========================================================
  $rawRows = (isset($rows) && is_array($rows)) ? $rows : [];

  $safe = array_values(array_filter($rawRows, function ($r) {
    if (!is_array($r)) return false;
    $p = (string)($r['period'] ?? '');
    return $p !== '' && (bool)preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $p);
  }));

  // ==========================================================
  // 2) Dedup por period (toma el "mejor" registro si hay duplicados)
  //    Prioridad: can_pay=true > mayor saldo > mayor charge > más reciente
  // ==========================================================
  $byPeriod = [];
  foreach ($safe as $r) {
    $p = (string)$r['period'];

    $canPay = (bool)($r['can_pay'] ?? false);
    $saldo  = (float)($r['saldo'] ?? 0);
    $charge = (float)($r['charge'] ?? 0);

    $score = 0;
    if ($canPay) $score += 1000000;
    $score += (int)round($saldo * 100);   // centavos
    $score += (int)round($charge * 10);   // peso relativo

    if (!isset($byPeriod[$p]) || $score > ($byPeriod[$p]['__score'] ?? -INF)) {
      $r['__score'] = $score;
      $byPeriod[$p] = $r;
    }
  }

  $safe = array_values($byPeriod);

  // Orden por period DESC (más reciente primero)
  usort($safe, function ($a, $b) {
    return strcmp((string)($b['period'] ?? ''), (string)($a['period'] ?? ''));
  });

  // ==========================================================
  // 3) ✅ FILTRO: SOLO PERIODOS PENDIENTES (NO pagados)
  //    + elegimos 1 fila final: primero el "pagable" (can_pay),
  //      si no hay, el que tenga saldo/cargo.
  // ==========================================================
  $pending = array_values(array_filter($safe, function ($r) {
    $status = strtolower(trim((string)($r['status'] ?? 'pending')));

    // Interpretación robusta de pagado
    $isPaid = in_array($status, ['paid', 'pagado', 'pago', 'paid_ok', 'activa_ok'], true);
    if ($isPaid) return false;

    // Si viene un flag explícito de "can_pay", se respeta más adelante.
    // Aquí solo exigimos que sea realmente "un adeudo" si existe info.
    $saldo  = (float)($r['saldo'] ?? 0);
    $charge = (float)($r['charge'] ?? 0);

    // Si no hay montos, aún lo dejamos pasar (por si el backend define can_pay)
    if ($saldo <= 0 && $charge <= 0 && !isset($r['can_pay'])) return false;

    return true;
  }));

  // Prioriza can_pay=true, luego mayor saldo, luego mayor charge, luego period DESC
  usort($pending, function ($a, $b) {
    $aCan = (bool)($a['can_pay'] ?? false);
    $bCan = (bool)($b['can_pay'] ?? false);
    if ($aCan !== $bCan) return $aCan ? -1 : 1;

    $aSaldo = (float)($a['saldo'] ?? 0);
    $bSaldo = (float)($b['saldo'] ?? 0);
    if ($aSaldo !== $bSaldo) return ($aSaldo > $bSaldo) ? -1 : 1;

    $aCh = (float)($a['charge'] ?? 0);
    $bCh = (float)($b['charge'] ?? 0);
    if ($aCh !== $bCh) return ($aCh > $bCh) ? -1 : 1;

    return strcmp((string)($b['period'] ?? ''), (string)($a['period'] ?? ''));
  });

  // ✅ Resultado final: SOLO 1 periodo pendiente
  $rows = array_slice($pending, 0, 1);

  // ==========================================================
  // 4) Rutas (route:cache safe)
  // ==========================================================
  $pdfInlineRouteExists   = \Illuminate\Support\Facades\Route::has('cliente.billing.pdfInline');
  $pdfDownloadRouteExists = \Illuminate\Support\Facades\Route::has('cliente.billing.pdf');
  $payRouteExists         = \Illuminate\Support\Facades\Route::has('cliente.billing.pay');

  $invoiceRequestRoute    = \Illuminate\Support\Facades\Route::has('cliente.billing.factura.request');
  $invoiceDownloadRoute   = \Illuminate\Support\Facades\Route::has('cliente.billing.factura.download');

  $rtMiCuenta = \Illuminate\Support\Facades\Route::has('cliente.mi_cuenta.index')
    ? route('cliente.mi_cuenta.index')
    : url('/cliente/mi-cuenta');

  /**
   * ✅ Mensualidad en header (SIEMPRE mensual)
   * - Si llega anual (ej. ID_ANUAL) normaliza a mensual (ID_MENSUAL)
   * - Si no viene, la deriva del cargo/saldo del periodo mostrado
   */
  $mensualidadHeader = (float)($mensualidadAdmin ?? 0);

  // Detecta anualidad por campos si existen
  $isAnnual = false;
  foreach ($rows as $rr) {
    $cycle = strtolower((string)($rr['billing_cycle'] ?? $rr['cycle'] ?? ($billing_cycle ?? '')));
    $modo  = strtolower((string)($rr['modo_cobro'] ?? ($modo_cobro ?? '')));

    if (in_array($cycle, ['annual','year','yearly','anual','anualidad'], true) ||
        in_array($modo,  ['annual','year','yearly','anual','anualidad'], true)) {
      $isAnnual = true;
      break;
    }
  }

  // Cargos del set mostrado (ahora normalmente 1 fila)
  $charges = [];
  foreach ($rows as $rr) {
    $c = (float)($rr['charge'] ?? 0);
    if ($c > 0) $charges[] = $c;
  }

  // Heurística 12x (por si no vienen campos)
  if (!$isAnnual && count($charges) >= 2) {
    $minC = min($charges);
    $maxC = max($charges);
    if ($minC > 0) {
      $ratio = $maxC / $minC;
      if (abs($ratio - 12.0) <= 0.35 || abs(($maxC / 12.0) - $minC) <= 1.00) {
        $isAnnual = true;
      }
    }
  }

  $ANNUAL_HINT_MIN = 6000.0;

  if ($mensualidadHeader > 0) {
    $mensualidadHeader = ($mensualidadHeader >= $ANNUAL_HINT_MIN)
      ? round($mensualidadHeader / 12.0, 2)
      : round($mensualidadHeader, 2);
  } else {
    if (!empty($charges)) {
      $c = (float)max($charges);
      $mensualidadHeader = round(($isAnnual && $c >= $ANNUAL_HINT_MIN) ? ($c / 12.0) : $c, 2);
    } else {
      $mensualidadHeader = 0.0;
    }
  }
@endphp


<div class="p360-page">
  <div class="p360-topcard">
    <div class="p360-top-left">
      <div class="p360-icon" aria-hidden="true">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
          <path d="M7 3h7l3 3v15a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z" stroke="currentColor" stroke-width="2"/>
          <path d="M14 3v4a2 2 0 0 0 2 2h4" stroke="currentColor" stroke-width="2"/>
          <path d="M8 12h8M8 16h8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
      </div>

      <div class="p360-toptext">
        <h1 class="p360-title">Estados de cuenta</h1>
        <div class="p360-sub">
          Se muestra el último mes pagado y el mes permitido para pagar. Si un mes está pagado, la acción principal es facturar.
        </div>
      </div>
    </div>

    <div class="p360-top-actions">
      <a class="p360-pillbtn" href="{{ $rtMiCuenta }}">Volver a Mi cuenta</a>
    </div>
  </div>

  <div class="p360-section">
    <div class="p360-section-head">
      <div class="p360-section-left">
        <div class="p360-icon" style="background:linear-gradient(180deg,rgba(37,99,235,.14),rgba(37,99,235,.08));border-color:rgba(37,99,235,.18);color:#1d4ed8;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <path d="M8 6h13M8 12h13M8 18h13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            <path d="M3 6h.01M3 12h.01M3 18h.01" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>
          </svg>
        </div>

        <div style="min-width:0">
          <div class="p360-section-badge">SECCIÓN</div>
          <h2 class="p360-section-h2">Estado de suscripción</h2>
          <div class="p360-section-p">
            Mensualidad: <strong>{{ $mxn($mensualidadHeader) }}</strong>
            &nbsp;·&nbsp; Último pagado: <strong>{{ $lastPaid ?? '—' }}</strong>
            &nbsp;·&nbsp; Permitido: <strong>{{ $payAllowed ?? '—' }}</strong>
          </div>
        </div>
      </div>

      <span class="p360-chip">P360 Billing</span>
    </div>

    <div class="p360-section-body">
      <div class="p360-list">
        @forelse($rows as $row)
          @php
            $period = (string)($row['period'] ?? '');

            $statusRaw = strtolower(trim((string)($row['status'] ?? 'pending')));
            $isPaid = in_array($statusRaw, ['paid','pagado','pago','paid_ok','activa_ok'], true);

            $canPay = (bool)($row['can_pay'] ?? false);

            $monthName = $period
              ? \Illuminate\Support\Carbon::createFromFormat('Y-m', $period)->translatedFormat('F')
              : '—';
            $monthName = \Illuminate\Support\Str::ucfirst($monthName);

            $range  = (string)($row['period_range'] ?? '');
            $rfcV   = (string)($row['rfc'] ?? ($rfc ?? '—'));
            $aliasV = (string)($row['alias'] ?? ($alias ?? '—'));

            $paidAmount = (float)($row['paid_amount'] ?? 0);
            $saldo      = (float)($row['saldo'] ?? 0);
            $charge     = (float)($row['charge'] ?? 0);

            $amount = $isPaid
              ? ($paidAmount > 0 ? $paidAmount : $charge)
              : ($saldo > 0 ? $saldo : $charge);

            $statusText  = $isPaid ? 'PAGADO' : 'PENDIENTE';
            $statusClass = $isPaid ? 'paid' : 'pending';

            $pdfEnabled  = $pdfInlineRouteExists && $period !== '';
            $payEnabled  = (!$isPaid) && $canPay && $payRouteExists && $period !== '';

            $isLastPaid = ($lastPaid && $period === $lastPaid);

            $invStatus = strtolower((string)($row['invoice_request_status'] ?? ''));
            $invHasZip = (bool)($row['invoice_has_zip'] ?? false);

            $invProcessing = in_array($invStatus, [
              'requested','pending','facturando','processing','in_progress','queued','generating'
            ], true);

            $invDone = in_array($invStatus, [
              'done','ready','completed','complete','success','succeeded','finished'
            ], true);

            $invoiceEnabled    = $isPaid && $isLastPaid && $invoiceRequestRoute && $period !== '';
            $invoiceZipEnabled = $isPaid && $isLastPaid && $invoiceDownloadRoute && $invHasZip;

            $pdfViewUrl = $pdfEnabled
              ? route('cliente.billing.pdfInline', ['period' => $period])
              : '#';

            $pdfDownloadUrl = ($pdfDownloadRouteExists && $period !== '')
              ? route('cliente.billing.pdf', ['period' => $period])
              : '#';
          @endphp

          <div class="p360-row" data-period="{{ $period }}">
            <div class="p360-monthbox p360-ga-month">
              <div class="p360-month">{{ $monthName }}</div>
              <div class="p360-monthsub">{{ $period }}</div>
            </div>

            <div class="p360-details p360-ga-details">
              <div class="p360-col">
                <div class="k">Periodo</div>
                <div class="v">{{ $range !== '' ? $range : $period }}</div>
              </div>

              <div class="p360-col">
                <div class="k">RFC</div>
                <div class="v">{{ $rfcV }}</div>
              </div>

              <div class="p360-col">
                <div class="k">Alias</div>
                <div class="v">{{ $aliasV }}</div>
              </div>
            </div>

            <div class="p360-paybox p360-ga-pay" aria-label="Estatus y monto del periodo">
              <div class="top">
                <div class="p360-status {{ $statusClass }}">
                  <span class="p360-dot"></span> {{ $statusText }}
                </div>

                {{-- ✅ Indicador mini de factura (solo aplica al último pagado) --}}
                @if($isPaid && $isLastPaid)
                  @if($invProcessing)
                    <span class="p360-chip-mini warn">Factura: Facturando</span>
                  @elseif($invDone || $invHasZip)
                    <span class="p360-chip-mini ok">Factura: Lista</span>
                  @else
                    <span class="p360-chip-mini">Factura: No solicitada</span>
                  @endif
                @endif

                <div class="k">{{ $isPaid ? 'Monto pagado' : 'Por pagar' }}</div>
              </div>

              <div class="amt">{{ $mxn($amount) }}</div>
            </div>

            <div class="p360-actions-right p360-ga-actions">
              <div class="p360-actions-grid">
                {{-- PDF --}}
                @if($pdfEnabled)
                  <button type="button"
                          class="p360-btn green js-p360-open-pdf"
                          data-pdf-view="{{ $pdfViewUrl }}"
                          data-pdf-download="{{ $pdfDownloadUrl }}">
                    Visualizar
                  </button>

                  @if($pdfDownloadUrl !== '#')
                    <a class="p360-btn green"
                       href="{{ $pdfDownloadUrl }}"
                       target="_blank"
                       rel="noopener">
                      Descargar
                    </a>
                  @else
                    <button type="button" class="p360-btn green" disabled>Descargar</button>
                  @endif
                @else
                  <button type="button" class="p360-btn green" disabled>Visualizar</button>
                  <button type="button" class="p360-btn green" disabled>Descargar</button>
                @endif

                {{-- Factura / Pago --}}
                @if($isPaid)
                  @if($isLastPaid)

                    {{-- 1) ZIP listo => link directo --}}
                    @if($invoiceZipEnabled)
                      <a class="p360-btn blue"
                         href="{{ route('cliente.billing.factura.download', ['period' => $period]) }}"
                         target="_blank"
                         rel="noopener">
                        Factura
                      </a>

                    {{-- 2) En proceso => disabled --}}
                    @elseif($invoiceEnabled && $invProcessing)
                      <button type="button" class="p360-btn blue" disabled>Facturando</button>

                    {{-- 3) Done pero sin ZIP => espera --}}
                    @elseif($invoiceEnabled && $invDone && !$invHasZip)
                      <button type="button" class="p360-btn blue" disabled>Preparando ZIP</button>

                    {{-- 4) Solicitar factura (POST real) --}}
                    @elseif($invoiceEnabled)
                      <form method="POST"
                            action="{{ route('cliente.billing.factura.request', ['period' => $period]) }}"
                            class="p360-inlineform"
                            style="margin:0">
                        @csrf
                        <button type="submit" class="p360-btn blue">Solicitar factura</button>
                      </form>

                    @else
                      <button type="button" class="p360-btn blue" disabled>Factura</button>
                    @endif

                  @else
                    {{-- Pagado pero NO es el último pagado => modal fuera de mes --}}
                    <button type="button"
                            class="p360-btn blue js-p360-inv-window"
                            data-inv-msg="Lo sentimos, la solicitud de la factura está fuera del mes de pago.">
                      Factura
                    </button>
                  @endif

                @else
                  {{-- No pagado => pago --}}
                  @if($payEnabled)
                    <a class="p360-btn orange" href="{{ route('cliente.billing.pay', ['period' => $period]) }}">
                      Pagar ahora
                    </a>
                  @else
                    <button type="button" class="p360-btn orange" disabled>Pagar ahora</button>
                  @endif
                @endif
              </div>
            </div>
          </div>
        @empty
          <div class="p360-row" style="grid-template-columns:1fr;grid-template-areas:'details';">
            <div class="p360-details p360-ga-details" style="grid-template-columns:1fr;">
              <div class="p360-col">
                <div class="k">Estados de cuenta</div>
                <div class="v">No hay periodos disponibles para mostrar.</div>
              </div>
            </div>
          </div>
        @endforelse
      </div>
    </div>
  </div>
</div>

{{-- ====== Modal PDF Viewer (P360) ====== --}}
<div id="p360PdfModal" class="p360-modal" aria-hidden="true">
  <div class="p360-modal__backdrop" data-close="1"></div>

  <div class="p360-modal__dialog" role="dialog" aria-modal="true" aria-label="Vista previa PDF">
    <div class="p360-modal__head">
      <div class="p360-modal__left">
        <div class="p360-modal__badge" aria-hidden="true">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <path d="M7 3h7l3 3v15a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z" stroke="currentColor" stroke-width="2"/>
            <path d="M14 3v4a2 2 0 0 0 2 2h4" stroke="currentColor" stroke-width="2"/>
            <path d="M8 12h8M8 16h8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </div>

        <div class="p360-modal__titlewrap">
          <p class="p360-modal__title" id="p360PdfTitle">Estado de cuenta</p>
          <p class="p360-modal__meta" id="p360PdfMeta">—</p>
        </div>
      </div>

      <div class="p360-modal__actions">
        <a id="p360PdfOpenTab"
           class="p360-modal__iconbtn"
           href="#"
           target="_blank"
           rel="noopener"
           title="Abrir en pestaña">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <path d="M14 3h7v7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            <path d="M10 14L21 3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            <path d="M21 14v6a1 1 0 0 1-1 1H5a2 2 0 0 1-2-2V4a1 1 0 0 1 1-1h6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </a>

        <a id="p360PdfDownload"
           class="p360-modal__btn green"
           href="#"
           target="_blank"
           rel="noopener">
          Descargar
        </a>

        <button type="button" class="p360-modal__btn ghost" data-close="1">Cerrar</button>
      </div>
    </div>

    <div class="p360-modal__body">
      <div id="p360PdfLoader" class="p360-modal__loader">
        <span class="p360-spin" aria-hidden="true"></span>
        <span id="p360PdfLoaderText">Cargando estado de cuenta…</span>
      </div>

      <div class="p360-modal__framewrap">
        <iframe id="p360PdfFrame"
                class="p360-modal__frame"
                src="about:blank"
                title="PDF Estado de cuenta"
                loading="lazy"></iframe>
      </div>
    </div>
  </div>
</div>

{{-- ====== Modal: Solicitud de factura fuera del mes ====== --}}
<div id="p360InvoiceWindowModal" class="p360-mini" aria-hidden="true">
  <div class="p360-mini__backdrop" data-close-inv="1"></div>
  <div class="p360-mini__card" role="dialog" aria-modal="true" aria-label="Solicitud fuera de mes">
    <div class="p360-mini__top">
      <div class="p360-mini__icon" aria-hidden="true">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
          <path d="M12 9v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          <path d="M12 17h.01" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>
          <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
        </svg>
      </div>
      <div style="min-width:0">
        <p class="p360-mini__title">No es posible solicitar la factura</p>
        <p class="p360-mini__msg" id="p360InvoiceWindowMsg">
          Lo sentimos, la solicitud de la factura está fuera del mes de pago.
        </p>
      </div>
    </div>

    <div class="p360-mini__actions">
      <button type="button" class="p360-modal__btn ghost" data-close-inv="1">Cerrar</button>
    </div>
  </div>
</div>
<script>
(function(){
  // =======================
  // P360 · Helpers
  // =======================
  const on = (el, ev, fn, opts) => el && el.addEventListener(ev, fn, opts || false);

  // =======================
  // PDF MODAL (loader visible SIEMPRE)
  // =======================
  const modal      = document.getElementById('p360PdfModal');
  const frame      = document.getElementById('p360PdfFrame');
  const loader     = document.getElementById('p360PdfLoader');
  const loaderText = document.getElementById('p360PdfLoaderText');
  const download   = document.getElementById('p360PdfDownload');
  const openTab    = document.getElementById('p360PdfOpenTab');
  const titleEl    = document.getElementById('p360PdfTitle');
  const metaEl     = document.getElementById('p360PdfMeta');

  if (modal && frame && loader && download && openTab && titleEl && metaEl) {
    let lastFocus = null;
    let openAt = 0;
    let hardTimer = null;
    let slowTimer = null;

    function withPdfViewerPrefs(url){
      try {
        const u = new URL(url, window.location.origin);
        u.searchParams.set('inline', '1');
        u.searchParams.set('_ts', String(Date.now()));
        u.hash = 'toolbar=0&navpanes=0&scrollbar=1';
        return u.toString();
      } catch (e) {
        const sep = String(url).includes('?') ? '&' : '?';
        return String(url) + sep + 'inline=1&_ts=' + Date.now() + '#toolbar=0&navpanes=0&scrollbar=1';
      }
    }

    function showLoader(msg){
      if (loaderText && msg) loaderText.textContent = msg;
      loader.style.display = 'flex';
      frame.style.visibility = 'hidden';
    }

    function hideLoader(){
      const elapsed = Date.now() - openAt;
      const wait = Math.max(0, 250 - elapsed);
      window.setTimeout(function(){
        loader.style.display = 'none';
        frame.style.visibility = 'visible';
      }, wait);
    }

    function clearTimers(){
      if (hardTimer) { clearTimeout(hardTimer); hardTimer = null; }
      if (slowTimer) { clearTimeout(slowTimer); slowTimer = null; }
    }

    function openModal(viewUrl, downloadUrl, metaText){
      if (!viewUrl || viewUrl === '#') return;

      lastFocus = document.activeElement;
      openAt = Date.now();
      clearTimers();

      titleEl.textContent = 'Estado de cuenta';
      metaEl.textContent  = metaText || '—';

      // hrefs
      const safeView = withPdfViewerPrefs(viewUrl);
      openTab.href = safeView;

      // descarga: preferimos downloadUrl si existe; si no, al view
      download.href = (downloadUrl && downloadUrl !== '#') ? downloadUrl : viewUrl;

      // abrir modal
      modal.classList.add('is-open');
      modal.setAttribute('aria-hidden','false');
      document.documentElement.classList.add('p360-modal-open');
      document.body.classList.add('p360-modal-open');

      // estado inicial
      showLoader('Cargando estado de cuenta…');

      // reset frame
      frame.onload = null;
      frame.src = 'about:blank';

      // loader "lento"
      slowTimer = window.setTimeout(function(){
        showLoader('Cargando estado de cuenta… (puede tardar algunos segundos)');
      }, 1800);

      // hard timeout
      hardTimer = window.setTimeout(function(){
        showLoader('No se pudo cargar el PDF. Intenta “Abrir en pestaña” o “Descargar”.');
      }, 25000);

      frame.onload = function(){
        clearTimers();
        hideLoader();
      };

      // set src en doble RAF para evitar flicker / race
      window.requestAnimationFrame(function(){
        window.requestAnimationFrame(function(){
          frame.src = safeView;
        });
      });
    }

    function closeModal(){
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden','true');

      document.documentElement.classList.remove('p360-modal-open');
      document.body.classList.remove('p360-modal-open');

      clearTimers();

      frame.onload = null;
      frame.src = 'about:blank';
      showLoader('Cargando estado de cuenta…');

      if (lastFocus && typeof lastFocus.focus === 'function') {
        try { lastFocus.focus(); } catch(e) {}
      }
    }

    // Delegación: abrir PDF desde cualquier botón
    document.addEventListener('click', function(e){
      const btn = e.target.closest('.js-p360-open-pdf');
      if (!btn) return;

      e.preventDefault();

      const viewUrl = btn.getAttribute('data-pdf-view') || '';
      const dlUrl   = btn.getAttribute('data-pdf-download') || '';

      const row = btn.closest('.p360-row');
      const period = row ? (row.getAttribute('data-period') || '') : '';
      const metaText = period ? ('Periodo: ' + period) : '—';

      openModal(viewUrl, dlUrl, metaText);
    });

    // click cerrar
    on(modal, 'click', function(e){
      const close = e.target.closest('[data-close="1"]');
      if (close) closeModal();
    });

    // escape
    document.addEventListener('keydown', function(e){
      if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
    });
  }

  // =======================
  // INVOICE WINDOW MODAL (fuera de mes)
  // =======================
  const invModal = document.getElementById('p360InvoiceWindowModal');
  const invMsg   = document.getElementById('p360InvoiceWindowMsg');

  function openInvModal(msg){
    if (!invModal) return;
    if (invMsg) invMsg.textContent = (msg && String(msg).trim() !== '')
      ? String(msg)
      : 'Lo sentimos, la solicitud de la factura está fuera del mes de pago.';
    invModal.classList.add('is-open');
    invModal.setAttribute('aria-hidden','false');
  }

  function closeInvModal(){
    if (!invModal) return;
    invModal.classList.remove('is-open');
    invModal.setAttribute('aria-hidden','true');
  }

  // 1) Delegación: botón "Factura" fuera de mes (clase del bloque 1)
  document.addEventListener('click', function(e){
    const btn = e.target.closest('.js-p360-inv-window');
    if (!btn) return;
    e.preventDefault();
    const msg = btn.getAttribute('data-inv-msg') || '';
    openInvModal(msg);
  });

  // 2) Cerrar por backdrop/botón
  if (invModal) {
    on(invModal, 'click', function(e){
      const close = e.target.closest('[data-close-inv="1"]');
      if (close) closeInvModal();
    });

    document.addEventListener('keydown', function(e){
      if (e.key === 'Escape' && invModal.classList.contains('is-open')) closeInvModal();
    });
  }

  // 3) Flash server-side (redirect con warning)
  const flashInv    = @json(session('invoice_window_error'));
  const flashInvMsg = @json(session('invoice_window_error_msg'));

  if (flashInv) {
    openInvModal(flashInvMsg || 'Lo sentimos, la solicitud de la factura está fuera del mes de pago.');
  }
})();
</script>
@endsection

