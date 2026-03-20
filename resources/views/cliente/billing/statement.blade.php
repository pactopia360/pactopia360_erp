{{-- C:\wamp64\www\pactopia360_erp\resources\views\cliente\billing\statement.blade.php --}}
@extends('layouts.cliente')

@section('title', 'Estado de cuenta · Pactopia360')
@section('pageClass', 'page-estado-cuenta')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/client/css/estado-cuenta.css') }}?v={{ filemtime(public_path('assets/client/css/estado-cuenta.css')) }}">
@endpush

@section('content')
@php
  $mxn = fn($n) => '$' . number_format((float) $n, 2);

  $rawRows = (isset($rows) && is_array($rows)) ? $rows : [];

  $safe = array_values(array_filter($rawRows, function ($r) {
    if (!is_array($r)) return false;
    $p = (string) ($r['period'] ?? '');
    return $p !== '' && (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $p);
  }));

  $paidStatuses = ['paid', 'pagado', 'pago', 'paid_ok', 'activa_ok'];

  $byPeriod = [];
  foreach ($safe as $r) {
    $p = (string) ($r['period'] ?? '');
    if ($p === '') continue;

    $canPay = (bool) ($r['can_pay'] ?? false);
    $status = strtolower(trim((string) ($r['status'] ?? 'pending')));
    $isPaid = in_array($status, $paidStatuses, true);

    $paidAmount = (float) ($r['paid_amount'] ?? 0);
    $charge     = (float) ($r['charge'] ?? 0);

    $score = 0;
    if ($canPay) $score += 1000000;
    if ($isPaid) $score += 500000;
    $score += (int) round($paidAmount * 100);
    $score += (int) round($charge * 100);

    if (!isset($byPeriod[$p]) || $score > ($byPeriod[$p]['__score'] ?? -INF)) {
      $r['__score'] = $score;
      $byPeriod[$p] = $r;
    }
  }

  $safe = array_values(array_map(function ($r) {
    unset($r['__score']);
    return $r;
  }, $byPeriod));

  $displayBase = '';
  if (!empty($payAllowed) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $payAllowed)) {
    $displayBase = (string) $payAllowed;
  } elseif (!empty($lastPaid) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $lastPaid)) {
    $displayBase = (string) $lastPaid;
  } else {
    $displayBase = now()->format('Y-m');
  }

  $displayYear = substr($displayBase, 0, 4);

  $rows = array_values(array_filter($safe, function ($r) use ($displayYear) {
    $p = (string) ($r['period'] ?? '');
    return $p !== '' && substr($p, 0, 4) === $displayYear;
  }));

  usort($rows, function ($a, $b) {
    return strcmp((string) ($a['period'] ?? ''), (string) ($b['period'] ?? ''));
  });

  if (empty($rows)) {
    $rows = $safe;
    usort($rows, function ($a, $b) {
      return strcmp((string) ($a['period'] ?? ''), (string) ($b['period'] ?? ''));
    });
  }

  $pdfInlineRouteExists   = \Illuminate\Support\Facades\Route::has('cliente.billing.pdfInline');
  $pdfDownloadRouteExists = \Illuminate\Support\Facades\Route::has('cliente.billing.pdf');
  $payRouteExists         = \Illuminate\Support\Facades\Route::has('cliente.billing.pay');
  $invoiceRequestRoute    = \Illuminate\Support\Facades\Route::has('cliente.billing.factura.request');
  $invoiceDownloadRoute   = \Illuminate\Support\Facades\Route::has('cliente.billing.factura.download');

  $rtMiCuenta = \Illuminate\Support\Facades\Route::has('cliente.mi_cuenta.index')
    ? route('cliente.mi_cuenta.index')
    : url('/cliente/mi-cuenta');

  $visibleAmount = 0.0;
  if (!empty($rows)) {
    $firstRow = $rows[0];
    $rowStatus = strtolower(trim((string) ($firstRow['status'] ?? 'pending')));
    $rowPaid   = in_array($rowStatus, $paidStatuses, true);

    $rowCharge = (float) ($firstRow['charge'] ?? 0);
    $rowSaldo  = (float) ($firstRow['saldo'] ?? 0);
    $rowPaidMx = (float) ($firstRow['paid_amount'] ?? 0);

    if ($rowPaid) {
      $visibleAmount = $rowPaidMx > 0 ? $rowPaidMx : $rowCharge;
    } else {
      $visibleAmount = $rowSaldo > 0 ? $rowSaldo : $rowCharge;
    }
  }

  $mensualidadHeader = 0.0;
  if ($visibleAmount > 0) {
    $mensualidadHeader = $visibleAmount;
  } elseif ((float) ($mensualidadAdmin ?? 0) > 0) {
    $mensualidadHeader = (float) $mensualidadAdmin;
  }

  $rowsCount = count($rows);
  $paidCount = 0;
  $pendingCount = 0;

  foreach ($rows as $tmpRow) {
    $tmpStatus = strtolower(trim((string) ($tmpRow['status'] ?? 'pending')));
    $tmpIsPaid = in_array($tmpStatus, $paidStatuses, true);
    if ($tmpIsPaid) $paidCount++; else $pendingCount++;
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
        <h1 class="p360-title">Estado de cuenta</h1>
        <div class="p360-sub">
          Mensualidad: <strong>{{ $mxn($mensualidadHeader) }}</strong>
          &nbsp;·&nbsp; Último pagado: <strong>{{ $lastPaid ?? '—' }}</strong>
          &nbsp;·&nbsp; Permitido: <strong>{{ $payAllowed ?? '—' }}</strong>
          &nbsp;·&nbsp; Pagados: <strong>{{ $paidCount }}</strong>
          &nbsp;·&nbsp; Pendientes: <strong>{{ $pendingCount }}</strong>
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
            <path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </div>

        <div style="min-width:0">
          <div class="p360-section-badge">HISTORIAL</div>
          <h2 class="p360-section-h2">Movimientos del año {{ $displayYear }}</h2>
          <div class="p360-section-p">
            Se muestran periodos pagados y pendientes, incluyendo pagos anteriores.
          </div>
        </div>
      </div>

      <span class="p360-chip">{{ $rowsCount }} registros</span>
    </div>

    <div class="p360-section-body">
      <div class="p360-table-head" aria-hidden="true">
        <div>Mes</div>
        <div>Periodo</div>
        <div>RFC</div>
        <div>Alias</div>
        <div>Estatus</div>
        <div>Monto</div>
        <div>Acciones</div>
      </div>

      <div class="p360-list">
        @forelse($rows as $row)
          @php
            $period = (string) ($row['period'] ?? '');

            $statusRaw = strtolower(trim((string) ($row['status'] ?? 'pending')));
            $isPaid    = in_array($statusRaw, $paidStatuses, true);
            $canPay    = (bool) ($row['can_pay'] ?? false);

            $monthName = $period
              ? \Illuminate\Support\Carbon::createFromFormat('Y-m', $period)->translatedFormat('F')
              : '—';
            $monthName = \Illuminate\Support\Str::ucfirst($monthName);

            $range  = (string) ($row['period_range'] ?? '');
            $rfcV   = (string) ($row['rfc'] ?? ($rfc ?? '—'));
            $aliasV = (string) ($row['alias'] ?? ($alias ?? '—'));

            $paidAmount = (float) ($row['paid_amount'] ?? 0);
            $saldo      = (float) ($row['saldo'] ?? 0);
            $charge     = (float) ($row['charge'] ?? 0);

            $amount = $isPaid
              ? ($paidAmount > 0 ? $paidAmount : $charge)
              : ($saldo > 0 ? $saldo : $charge);

            $statusText  = $isPaid ? 'Pagado' : 'Pendiente';
            $statusClass = $isPaid ? 'paid' : 'pending';

            $pdfEnabled  = $pdfInlineRouteExists && $period !== '';
            $payEnabled  = (!$isPaid) && $canPay && $payRouteExists && $period !== '';

            $isLastPaid = ($lastPaid && $period === $lastPaid);

            $invStatus = strtolower((string) ($row['invoice_request_status'] ?? ''));
            $invHasZip = (bool) ($row['invoice_has_zip'] ?? false);

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

            $rowClass = $isPaid ? 'is-paid' : 'is-pending';
          @endphp

          <div class="p360-row p360-row--flat {{ $rowClass }}" data-period="{{ $period }}">
            <div class="p360-cell p360-cell--month">
              <div class="p360-month">{{ $monthName }}</div>
              <div class="p360-monthsub">{{ $period }}</div>
            </div>

            <div class="p360-cell p360-cell--period">
              <div class="p360-cell__value">{{ $range !== '' ? $range : $period }}</div>
            </div>

            <div class="p360-cell p360-cell--rfc">
              <div class="p360-cell__value">{{ $rfcV }}</div>
            </div>

            <div class="p360-cell p360-cell--alias">
              <div class="p360-cell__value">{{ $aliasV }}</div>
            </div>

            <div class="p360-cell p360-cell--status">
              <span class="p360-status {{ $statusClass }}">
                <span class="p360-dot"></span> {{ $statusText }}
              </span>
            </div>

            <div class="p360-cell p360-cell--amount">
              <div class="p360-amount {{ $statusClass }}">{{ $mxn($amount) }}</div>
            </div>

            <div class="p360-cell p360-cell--actions">
              <div class="p360-icon-actions">
                @if($pdfEnabled)
                  <button type="button"
                          class="p360-iconbtn p360-iconbtn--green js-p360-open-pdf"
                          data-pdf-view="{{ $pdfViewUrl }}"
                          data-pdf-download="{{ $pdfDownloadUrl }}"
                          data-tooltip="Visualizar estado de cuenta"
                          aria-label="Visualizar estado de cuenta">
                    <svg viewBox="0 0 24 24" fill="none">
                      <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12z" stroke="currentColor" stroke-width="2"/>
                      <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
                    </svg>
                  </button>
                @else
                  <button type="button"
                          class="p360-iconbtn p360-iconbtn--green"
                          disabled
                          data-tooltip="Visualizar no disponible"
                          aria-label="Visualizar no disponible">
                    <svg viewBox="0 0 24 24" fill="none">
                      <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12z" stroke="currentColor" stroke-width="2"/>
                      <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
                    </svg>
                  </button>
                @endif

                @if($pdfDownloadUrl !== '#')
                  <a class="p360-iconbtn p360-iconbtn--green"
                     href="{{ $pdfDownloadUrl }}"
                     target="_blank"
                     rel="noopener"
                     data-tooltip="Descargar PDF"
                     aria-label="Descargar PDF">
                    <svg viewBox="0 0 24 24" fill="none">
                      <path d="M12 3v12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                      <path d="M7 10l5 5 5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                      <path d="M5 21h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                  </a>
                @else
                  <button type="button"
                          class="p360-iconbtn p360-iconbtn--green"
                          disabled
                          data-tooltip="Descarga no disponible"
                          aria-label="Descarga no disponible">
                    <svg viewBox="0 0 24 24" fill="none">
                      <path d="M12 3v12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                      <path d="M7 10l5 5 5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                      <path d="M5 21h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                  </button>
                @endif

                @if($isPaid)
                  @if($isLastPaid)
                    @if($invoiceZipEnabled)
                      <a class="p360-iconbtn p360-iconbtn--blue"
                         href="{{ route('cliente.billing.factura.download', ['period' => $period]) }}"
                         target="_blank"
                         rel="noopener"
                         data-tooltip="Descargar factura"
                         aria-label="Descargar factura">
                        <svg viewBox="0 0 24 24" fill="none">
                          <path d="M7 3h7l3 3v15a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z" stroke="currentColor" stroke-width="2"/>
                          <path d="M14 3v4a2 2 0 0 0 2 2h4" stroke="currentColor" stroke-width="2"/>
                          <path d="M12 11v6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                          <path d="M9.5 14.5 12 17l2.5-2.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                      </a>
                    @elseif($invoiceEnabled && $invProcessing)
                      <button type="button"
                              class="p360-iconbtn p360-iconbtn--blue"
                              disabled
                              data-tooltip="Factura en proceso"
                              aria-label="Factura en proceso">
                        <svg viewBox="0 0 24 24" fill="none">
                          <path d="M12 6v6l4 2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                          <circle cx="12" cy="12" r="8" stroke="currentColor" stroke-width="2"/>
                        </svg>
                      </button>
                    @elseif($invoiceEnabled && $invDone && !$invHasZip)
                      <button type="button"
                              class="p360-iconbtn p360-iconbtn--blue"
                              disabled
                              data-tooltip="Preparando ZIP"
                              aria-label="Preparando ZIP">
                        <svg viewBox="0 0 24 24" fill="none">
                          <path d="M7 3h7l3 3v15a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z" stroke="currentColor" stroke-width="2"/>
                          <path d="M14 3v4a2 2 0 0 0 2 2h4" stroke="currentColor" stroke-width="2"/>
                          <path d="M8 12h8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                      </button>
                    @elseif($invoiceEnabled)
                      <form method="POST"
                            action="{{ route('cliente.billing.factura.request', ['period' => $period]) }}"
                            class="p360-inlineform"
                            style="margin:0">
                        @csrf
                        <button type="submit"
                                class="p360-iconbtn p360-iconbtn--blue"
                                data-tooltip="Solicitar factura"
                                aria-label="Solicitar factura">
                          <svg viewBox="0 0 24 24" fill="none">
                            <path d="M7 3h7l3 3v15a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z" stroke="currentColor" stroke-width="2"/>
                            <path d="M14 3v4a2 2 0 0 0 2 2h4" stroke="currentColor" stroke-width="2"/>
                            <path d="M12 9v6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            <path d="M9 12h6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                          </svg>
                        </button>
                      </form>
                    @else
                      <button type="button"
                              class="p360-iconbtn p360-iconbtn--blue"
                              disabled
                              data-tooltip="Factura no disponible"
                              aria-label="Factura no disponible">
                        <svg viewBox="0 0 24 24" fill="none">
                          <path d="M7 3h7l3 3v15a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z" stroke="currentColor" stroke-width="2"/>
                          <path d="M14 3v4a2 2 0 0 0 2 2h4" stroke="currentColor" stroke-width="2"/>
                        </svg>
                      </button>
                    @endif
                  @else
                    <button type="button"
                            class="p360-iconbtn p360-iconbtn--blue js-p360-inv-window"
                            data-inv-msg="Lo sentimos, la solicitud de la factura está fuera del mes de pago."
                            data-tooltip="Factura fuera de ventana"
                            aria-label="Factura fuera de ventana">
                      <svg viewBox="0 0 24 24" fill="none">
                        <path d="M7 3h7l3 3v15a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z" stroke="currentColor" stroke-width="2"/>
                        <path d="M14 3v4a2 2 0 0 0 2 2h4" stroke="currentColor" stroke-width="2"/>
                        <path d="M12 9v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <path d="M12 17h.01" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                      </svg>
                    </button>
                  @endif
                @else
                  @if($payEnabled)
                    <a class="p360-iconbtn p360-iconbtn--orange"
                       href="{{ route('cliente.billing.pay', ['period' => $period]) }}"
                       data-tooltip="Pagar ahora"
                       aria-label="Pagar ahora">
                      <svg viewBox="0 0 24 24" fill="none">
                        <rect x="3" y="6" width="18" height="12" rx="2" stroke="currentColor" stroke-width="2"/>
                        <path d="M3 10h18" stroke="currentColor" stroke-width="2"/>
                        <path d="M16 14h2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                      </svg>
                    </a>
                  @else
                    <button type="button"
                            class="p360-iconbtn p360-iconbtn--orange"
                            disabled
                            data-tooltip="Pago no disponible"
                            aria-label="Pago no disponible">
                      <svg viewBox="0 0 24 24" fill="none">
                        <rect x="3" y="6" width="18" height="12" rx="2" stroke="currentColor" stroke-width="2"/>
                        <path d="M3 10h18" stroke="currentColor" stroke-width="2"/>
                        <path d="M16 14h2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                      </svg>
                    </button>
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
  const on = (el, ev, fn, opts) => el && el.addEventListener(ev, fn, opts || false);

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

      const safeView = withPdfViewerPrefs(viewUrl);
      openTab.href = safeView;
      download.href = (downloadUrl && downloadUrl !== '#') ? downloadUrl : viewUrl;

      modal.classList.add('is-open');
      modal.setAttribute('aria-hidden','false');
      document.documentElement.classList.add('p360-modal-open');
      document.body.classList.add('p360-modal-open');

      showLoader('Cargando estado de cuenta…');

      frame.onload = null;
      frame.src = 'about:blank';

      slowTimer = window.setTimeout(function(){
        showLoader('Cargando estado de cuenta… (puede tardar algunos segundos)');
      }, 1800);

      hardTimer = window.setTimeout(function(){
        showLoader('No se pudo cargar el PDF. Intenta “Abrir en pestaña” o “Descargar”.');
      }, 25000);

      frame.onload = function(){
        clearTimers();
        hideLoader();
      };

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

    on(modal, 'click', function(e){
      const close = e.target.closest('[data-close="1"]');
      if (close) closeModal();
    });

    document.addEventListener('keydown', function(e){
      if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
    });
  }

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

  document.addEventListener('click', function(e){
    const btn = e.target.closest('.js-p360-inv-window');
    if (!btn) return;
    e.preventDefault();
    const msg = btn.getAttribute('data-inv-msg') || '';
    openInvModal(msg);
  });

  if (invModal) {
    on(invModal, 'click', function(e){
      const close = e.target.closest('[data-close-inv="1"]');
      if (close) closeInvModal();
    });

    document.addEventListener('keydown', function(e){
      if (e.key === 'Escape' && invModal.classList.contains('is-open')) closeInvModal();
    });
  }

  const flashInv    = @json(session('invoice_window_error'));
  const flashInvMsg = @json(session('invoice_window_error_msg'));

  if (flashInv) {
    openInvModal(flashInvMsg || 'Lo sentimos, la solicitud de la factura está fuera del mes de pago.');
  }
})();
</script>
@endsection