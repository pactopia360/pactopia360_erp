{{-- C:\wamp64\www\pactopia360_erp\resources\views\admin\billing\invoicing\invoices\show.blade.php --}}
@extends('layouts.admin')

@section('title', 'Factura · Detalle')
@section('contentLayout', 'full')
@section('pageClass', 'billing-invoice-show-page')

@php
    $invoice = $invoice ?? null;
    $account = $account ?? null;
    $requestRow = $requestRow ?? null;

    $id = $invoice->id ?? null;
    $uuid = $invoice->cfdi_uuid ?? null;
    $serie = $invoice->serie ?? '';
    $folio = $invoice->folio ?? '';
    $status = strtolower((string) ($invoice->status ?? 'draft'));

    $invoiceTitle = trim($serie . ' ' . $folio);
    $invoiceTitle = $invoiceTitle !== '' ? $invoiceTitle : ('#' . $id);

    $accountName = $account->razon_social ?? ($account->name ?? ($invoice->razon_social ?? 'Cliente'));
    $accountRfc  = $account->rfc ?? ($invoice->rfc ?? '—');

    $total = $invoice->display_total_mxn ?? null;
    if ($total === null || $total === '') $total = $invoice->amount_mxn ?? null;
    if ($total === null || $total === '') $total = $invoice->monto_mxn ?? null;
    if ($total === null || $total === '') $total = $invoice->total ?? null;
    if ($total === null || $total === '') $total = $invoice->subtotal ?? null;
    if (($total === null || $total === '') && !empty($invoice->amount)) $total = ((float) $invoice->amount) / 100;
    if (($total === null || $total === '') && !empty($invoice->amount_cents)) $total = ((float) $invoice->amount_cents) / 100;
    $total = is_numeric($total) ? (float) $total : 0;

    $issuedAt = $invoice->issued_at ?? $invoice->created_at ?? null;

    $pdfPath = $invoice->pdf_path ?? null;
    $xmlPath = $invoice->xml_path ?? null;
    $hasPdf = !empty($pdfPath);
    $hasXml = !empty($xmlPath);

    $resolvedRecipients = $invoice->resolved_recipients ?? [];
    if (!is_array($resolvedRecipients)) $resolvedRecipients = [];
    $defaultTo = old('to', !empty($resolvedRecipients) ? implode(',', $resolvedRecipients) : '');

    $statusLabel = match ($status) {
        'draft'     => 'Borrador',
        'pending'   => 'Pendiente',
        'generated' => 'Generada',
        'stamped'   => 'Timbrada',
        'sent'      => 'Enviada',
        'paid'      => 'Pagada',
        'cancelled', 'canceled' => 'Cancelada',
        'error'     => 'Error',
        'active'    => 'Activa',
        'issued'    => 'Emitida',
        default     => strtoupper($status ?: '—'),
    };

    $showStamp = empty($uuid) && !in_array($status, ['cancelled', 'canceled'], true);
    $showCancel = !in_array($status, ['cancelled', 'canceled'], true);

    $fmtMoney = static fn ($v) => '$' . number_format((float) $v, 2);

    $fmtDate = static function ($v): string {
        if (!$v) return '—';
        try {
            return \Illuminate\Support\Carbon::parse($v)->format('Y-m-d H:i');
        } catch (\Throwable $e) {
            return (string) $v;
        }
    };
@endphp

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/admin/css/invoicing-invoices.css') }}?v={{ @filemtime(public_path('assets/admin/css/invoicing-invoices.css')) ?: time() }}">

<style>
  .billing-invoice-show-page .page-container{
    padding:clamp(8px, 1vw, 16px);
  }

  .billing-invoice-show-page .page-shell,
  .billing-invoice-show-page .page-shell--full,
  .billing-invoice-show-page .page-shell--contained{
    width:100%;
    max-width:100% !important;
    margin:0 !important;
  }

  .invshow-page{
    display:grid;
    gap:14px;
  }

  .invshow-top{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:14px;
    flex-wrap:wrap;
    padding:14px 16px;
    border:1px solid var(--card-border, #e5e7eb);
    border-radius:20px;
    background:var(--card-bg, #fff);
    box-shadow:0 8px 22px rgba(15,23,42,.04);
  }

  .invshow-top__left{
    display:grid;
    gap:4px;
  }

  .invshow-top__eyebrow{
    display:inline-flex;
    align-items:center;
    gap:8px;
    width:max-content;
    min-height:28px;
    padding:0 10px;
    border-radius:999px;
    border:1px solid var(--card-border, #e5e7eb);
    background:var(--panel-bg, #f8fafc);
    color:var(--muted, #64748b);
    font-size:11px;
    text-transform:uppercase;
    letter-spacing:.08em;
    font-weight:900;
  }

  .invshow-top__dot{
    width:8px;
    height:8px;
    border-radius:999px;
    background:#10b981;
    flex:0 0 8px;
  }

  .invshow-top__title{
    margin:0;
    font-size:clamp(28px, 2vw, 38px);
    line-height:1;
    letter-spacing:-.05em;
    font-weight:950;
    color:var(--text, #0f172a);
  }

  .invshow-top__sub{
    margin:0;
    color:var(--muted, #64748b);
    font-size:13px;
    line-height:1.5;
  }

  .invshow-actions{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
    align-items:center;
  }

  .invshow-btn{
    appearance:none;
    min-height:42px;
    padding:0 14px;
    border-radius:12px;
    border:1px solid var(--card-border, #e5e7eb);
    background:var(--card-bg, #fff);
    color:var(--text, #0f172a);
    text-decoration:none;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    font-size:13px;
    font-weight:900;
    transition:.18s ease;
    cursor:pointer;
  }

  .invshow-btn:hover{
    transform:translateY(-1px);
    box-shadow:0 10px 20px rgba(15,23,42,.06);
  }

  .invshow-btn--primary{
    color:#fff;
    border-color:transparent;
    background:linear-gradient(180deg, #0f3f60, #0c2f47);
  }

  .invshow-btn--danger{
    color:#fff;
    border-color:transparent;
    background:linear-gradient(180deg, #dc2626, #b91c1c);
  }

  .invshow-btn--success{
    color:#fff;
    border-color:transparent;
    background:linear-gradient(180deg, #059669, #047857);
  }

  .invshow-grid{
    display:grid;
    grid-template-columns:repeat(12, minmax(0, 1fr));
    gap:14px;
  }

  .invshow-col-12{ grid-column:span 12; }
  .invshow-col-6{ grid-column:span 6; }
  .invshow-col-4{ grid-column:span 4; }
  .invshow-col-3{ grid-column:span 3; }

  .invshow-card{
    border:1px solid var(--card-border, #e5e7eb);
    border-radius:18px;
    background:var(--card-bg, #fff);
    box-shadow:0 8px 22px rgba(15,23,42,.03);
    overflow:hidden;
  }

  .invshow-card__head{
    padding:14px 16px 0;
  }

  .invshow-card__title{
    margin:0;
    font-size:16px;
    line-height:1.1;
    font-weight:950;
    color:var(--text, #0f172a);
  }

  .invshow-card__sub{
    margin:6px 0 0;
    color:var(--muted, #64748b);
    font-size:12px;
    line-height:1.5;
  }

  .invshow-card__body{
    padding:16px;
    display:grid;
    gap:14px;
  }

  .invshow-kpis{
    display:grid;
    grid-template-columns:repeat(4, minmax(0, 1fr));
    gap:12px;
  }

  .invshow-kpi{
    border:1px solid var(--card-border, #e5e7eb);
    border-radius:14px;
    background:var(--panel-bg, #f8fafc);
    padding:12px;
    display:grid;
    gap:5px;
  }

  .invshow-kpi__k{
    font-size:11px;
    color:var(--muted, #64748b);
    text-transform:uppercase;
    font-weight:900;
    letter-spacing:.08em;
  }

  .invshow-kpi__v{
    font-size:20px;
    line-height:1.1;
    font-weight:950;
    color:var(--text, #0f172a);
    word-break:break-word;
  }

  .invshow-pair{
    display:grid;
    gap:6px;
  }

  .invshow-pair__k{
    font-size:11px;
    color:var(--muted, #64748b);
    text-transform:uppercase;
    font-weight:900;
    letter-spacing:.08em;
  }

  .invshow-pair__v{
    color:var(--text, #0f172a);
    font-size:14px;
    line-height:1.6;
    word-break:break-word;
  }

  .invshow-files{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
  }

  .invshow-modal[aria-hidden="true"]{
    display:none;
  }

  .invshow-modal[aria-hidden="false"]{
    display:grid;
    place-items:center;
    position:fixed;
    inset:0;
    z-index:2400;
  }

  .invshow-modal__backdrop{
    position:absolute;
    inset:0;
    background:rgba(15,23,42,.48);
    backdrop-filter:blur(6px);
  }

  .invshow-modal__dialog{
    position:relative;
    width:min(720px, 94vw);
    max-height:88vh;
    overflow:auto;
    background:var(--card-bg, #fff);
    border:1px solid var(--card-border, #e5e7eb);
    border-radius:20px;
    box-shadow:0 30px 80px rgba(15,23,42,.22);
  }

  .invshow-modal__head{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:12px;
    padding:16px;
    border-bottom:1px solid var(--card-border, #e5e7eb);
  }

  .invshow-modal__title{
    margin:0;
    font-size:18px;
    font-weight:950;
    color:var(--text, #0f172a);
  }

  .invshow-modal__sub{
    margin:6px 0 0;
    font-size:12px;
    color:var(--muted, #64748b);
    line-height:1.5;
  }

  .invshow-modal__body{
    padding:16px;
    display:grid;
    gap:14px;
  }

  .invshow-modal__actions{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
  }

  .invshow-form-grid{
    display:grid;
    gap:12px;
  }

  .invshow-json{
    background:#0f172a;
    color:#e5e7eb;
    border-radius:12px;
    padding:12px;
    font-size:12px;
    overflow:auto;
  }

  @media (max-width: 980px){
    .invshow-col-6,
    .invshow-col-4,
    .invshow-col-3{
      grid-column:span 12;
    }

    .invshow-kpis{
      grid-template-columns:repeat(2, minmax(0, 1fr));
    }
  }

  @media (max-width: 680px){
    .billing-invoice-show-page .page-container{
      padding:8px;
    }

    .invshow-top,
    .invshow-card__head,
    .invshow-card__body,
    .invshow-modal__head,
    .invshow-modal__body{
      padding-left:12px;
      padding-right:12px;
    }

    .invshow-top,
    .invshow-card,
    .invshow-modal__dialog{
      border-radius:16px;
    }

    .invshow-actions,
    .invshow-modal__actions{
      width:100%;
    }

    .invshow-btn{
      width:100%;
    }

    .invshow-kpis{
      grid-template-columns:1fr;
    }
  }
</style>
@endpush

@section('content')
<div class="invshow-page">

    <section class="invshow-top">
        <div class="invshow-top__left">
            <div class="invshow-top__eyebrow">
                <span class="invshow-top__dot"></span>
                Factura guardada
            </div>
            <h1 class="invshow-top__title">Factura {{ $invoiceTitle }}</h1>
            <p class="invshow-top__sub">
                UUID: {{ $uuid ?: '—' }} · Estado: {{ $statusLabel }}
            </p>
        </div>

        <div class="invshow-actions">
            <a href="{{ route('admin.billing.invoicing.invoices.index') }}" class="invshow-btn">Volver</a>

            <button type="button" class="invshow-btn invshow-btn--success" id="invshowOpenDelivery">
                Archivos y envío
            </button>

            @if($showStamp)
                <form method="POST" action="{{ route('admin.billing.invoicing.invoices.stamp', $id) }}">
                    @csrf
                    <button class="invshow-btn invshow-btn--primary">Timbrar CFDI</button>
                </form>
            @endif

            @if($showCancel)
                <form method="POST" action="{{ route('admin.billing.invoicing.invoices.cancel', $id) }}">
                    @csrf
                    <button class="invshow-btn invshow-btn--danger">Cancelar CFDI</button>
                </form>
            @endif
        </div>
    </section>

    @if(session('ok'))
        <div class="invx-alert invx-alert--success">{{ session('ok') }}</div>
    @endif

    @if ($errors->any())
        <div class="invx-alert invx-alert--danger">
            <strong>Se encontraron errores:</strong>
            <ul class="invx-errors">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="invshow-card">
        <div class="invshow-card__head">
            <h2 class="invshow-card__title">Resumen</h2>
            <p class="invshow-card__sub">Datos principales de la factura registrada.</p>
        </div>

        <div class="invshow-card__body">
            <div class="invshow-kpis">
                <div class="invshow-kpi">
                    <div class="invshow-kpi__k">Factura</div>
                    <div class="invshow-kpi__v">#{{ $id }}</div>
                </div>

                <div class="invshow-kpi">
                    <div class="invshow-kpi__k">Estado</div>
                    <div class="invshow-kpi__v">{{ $statusLabel }}</div>
                </div>

                <div class="invshow-kpi">
                    <div class="invshow-kpi__k">Total</div>
                    <div class="invshow-kpi__v">{{ $fmtMoney($total) }}</div>
                </div>

                <div class="invshow-kpi">
                    <div class="invshow-kpi__k">Emitida</div>
                    <div class="invshow-kpi__v">{{ $fmtDate($issuedAt) }}</div>
                </div>
            </div>
        </div>
    </section>

    <div class="invshow-grid">
        <section class="invshow-card invshow-col-6">
            <div class="invshow-card__head">
                <h2 class="invshow-card__title">Cuenta / receptor</h2>
            </div>

            <div class="invshow-card__body">
                <div class="invshow-pair">
                    <div class="invshow-pair__k">Nombre</div>
                    <div class="invshow-pair__v">{{ $accountName }}</div>
                </div>

                <div class="invshow-pair">
                    <div class="invshow-pair__k">RFC</div>
                    <div class="invshow-pair__v">{{ $accountRfc }}</div>
                </div>

                <div class="invshow-pair">
                    <div class="invshow-pair__k">Periodo</div>
                    <div class="invshow-pair__v">{{ $invoice->period ?? '—' }}</div>
                </div>
            </div>
        </section>

        <section class="invshow-card invshow-col-6">
            <div class="invshow-card__head">
                <h2 class="invshow-card__title">Archivos CFDI</h2>
            </div>

            <div class="invshow-card__body">
                <div class="invshow-files">
                    @if($hasPdf)
                        <a href="{{ route('admin.billing.invoicing.invoices.download', [$id, 'pdf']) }}" class="invshow-btn">Descargar PDF</a>
                    @endif

                    @if($hasXml)
                        <a href="{{ route('admin.billing.invoicing.invoices.download', [$id, 'xml']) }}" class="invshow-btn">Descargar XML</a>
                    @endif

                    @if(!$hasPdf && !$hasXml)
                        <div class="invshow-pair__v">Aún no hay archivos CFDI generados.</div>
                    @endif
                </div>
            </div>
        </section>

        <section class="invshow-card invshow-col-12">
            <div class="invshow-card__head">
                <h2 class="invshow-card__title">JSON técnico</h2>
                <p class="invshow-card__sub">Solo para revisión rápida del registro actual.</p>
            </div>

            <div class="invshow-card__body">
                <pre class="invshow-json">{{ json_encode($invoice, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </div>
        </section>
    </div>
</div>

<div class="invshow-modal" id="invshowDeliveryModal" aria-hidden="{{ session('open_delivery_modal') ? 'false' : 'true' }}">
    <div class="invshow-modal__backdrop" data-invshow-close></div>

    <div class="invshow-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="invshowDeliveryTitle">
        <div class="invshow-modal__head">
            <div>
                <h3 class="invshow-modal__title" id="invshowDeliveryTitle">Archivos y envío</h3>
                <p class="invshow-modal__sub">
                    Ya se registró la factura. Desde aquí puedes descargar PDF/XML y enviarla por correo.
                </p>
            </div>

            <button type="button" class="invx-iconbtn" data-invshow-close aria-label="Cerrar">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M6 6L18 18M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
            </button>
        </div>

        <div class="invshow-modal__body">
            <div class="invshow-form-grid">
                <div class="invshow-files">
                    @if($hasPdf)
                        <a href="{{ route('admin.billing.invoicing.invoices.download', [$id, 'pdf']) }}" class="invshow-btn">Descargar PDF</a>
                    @endif

                    @if($hasXml)
                        <a href="{{ route('admin.billing.invoicing.invoices.download', [$id, 'xml']) }}" class="invshow-btn">Descargar XML</a>
                    @endif
                </div>

                <form method="POST" action="{{ route('admin.billing.invoicing.invoices.send', $id) }}" class="invshow-form-grid">
                    @csrf

                    <div class="invx-floating">
                        <input
                            id="invshow_to"
                            type="text"
                            name="to"
                            class="invx-input"
                            value="{{ $defaultTo }}"
                            placeholder=" "
                        >
                        <label for="invshow_to">Correos destino</label>
                    </div>

                    <div class="invshow-modal__actions">
                        <button type="button" class="invshow-btn" data-invshow-close>Cerrar</button>
                        <button type="submit" class="invshow-btn invshow-btn--success">Enviar factura</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    function qs(sel, root = document) {
        return root.querySelector(sel);
    }

    function qsa(sel, root = document) {
        return Array.from(root.querySelectorAll(sel));
    }

    function openModal() {
        const modal = qs('#invshowDeliveryModal');
        if (!modal) return;
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        const modal = qs('#invshowDeliveryModal');
        if (!modal) return;
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    function init() {
        const openBtn = qs('#invshowOpenDelivery');
        if (openBtn) {
            openBtn.addEventListener('click', openModal);
        }

        qsa('[data-invshow-close]').forEach((el) => {
            el.addEventListener('click', closeModal);
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });

        const modal = qs('#invshowDeliveryModal');
        if (modal && modal.getAttribute('aria-hidden') === 'false') {
            document.body.style.overflow = 'hidden';
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
@endpush