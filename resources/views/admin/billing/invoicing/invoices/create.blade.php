{{-- C:\wamp64\www\pactopia360_erp\resources\views\admin\billing\invoicing\invoices\create.blade.php --}}
@extends('layouts.admin')

@section('title', 'Facturación · Nueva factura')
@section('contentLayout', 'full')
@section('pageClass', 'billing-invoices-create-page')

@php
    $routeStoreOne         = $routeStoreOne ?? route('admin.billing.invoicing.invoices.store_manual');
    $routeFormSeed         = $routeFormSeed ?? route('admin.billing.invoicing.invoices.form_seed');
    $routeSearchEmisores   = $routeSearchEmisores ?? route('admin.billing.invoicing.invoices.search_emisores');
    $routeSearchReceptores = $routeSearchReceptores ?? route('admin.billing.invoicing.invoices.search_receptores');
    $routeIndex            = $routeIndex ?? route('admin.billing.invoicing.invoices.index');
    $routeDashboard        = $routeDashboard ?? route('admin.billing.invoicing.dashboard');

    $oldTipo   = old('tipo_comprobante', 'I');
    $oldComp   = old('complemento', 'none');
    $oldStatus = old('status', 'issued');
    $oldSource = old('source', 'manual_admin');
    $oldMonto  = old('amount_mxn');
    $oldPeriod = old('period', now()->format('Y-m'));

    $tipoLabels = [
        'I' => 'Ingreso',
        'E' => 'Egreso',
        'P' => 'Pago',
        'N' => 'Nómina',
        'T' => 'Traslado',
    ];

    $tipoActualLabel = $tipoLabels[$oldTipo] ?? 'Ingreso';

    $wizardSteps = [
        1 => 'Tipo',
        2 => 'Clonado',
        3 => 'Generales',
        4 => 'Partes',
        5 => 'SAT',
        6 => 'Adjuntos',
        7 => 'Revisión',
    ];
@endphp

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/admin/css/invoicing-invoices.css') }}?v={{ @filemtime(public_path('assets/admin/css/invoicing-invoices.css')) ?: time() }}">

<style>
  .billing-invoices-create-page .page-container{
    padding:clamp(10px, .9vw, 16px);
  }

  .billing-invoices-create-page .page-shell,
  .billing-invoices-create-page .page-shell--full,
  .billing-invoices-create-page .page-shell--contained{
    width:100%;
    max-width:100% !important;
    margin:0 !important;
  }

  .invx-wizard{
    display:block;
  }

  .invx-main{
    display:grid;
    gap:14px;
    min-width:0;
  }

  .invx-side{
    display:none !important;
  }

  .invx-card{
    border:1px solid var(--card-border, #e5e7eb);
    border-radius:20px;
    background:var(--card-bg, #fff);
    box-shadow:0 10px 26px rgba(15,23,42,.045);
  }

  .invx-hero{
    padding:16px 18px;
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:12px;
    flex-wrap:wrap;
  }

  .invx-hero__eyebrow{
    display:inline-flex;
    align-items:center;
    gap:8px;
    min-height:28px;
    padding:0 10px;
    border-radius:999px;
    border:1px solid var(--card-border, #e5e7eb);
    background:var(--panel-bg, #f8fafc);
    color:var(--muted, #64748b);
    font-size:10px;
    font-weight:900;
    text-transform:uppercase;
    letter-spacing:.08em;
  }

  .invx-hero__dot{
    width:8px;
    height:8px;
    border-radius:999px;
    background:#10b981;
    flex:0 0 8px;
  }

  .invx-hero__title{
    margin:8px 0 4px;
    font-size:clamp(24px, 1.7vw, 34px);
    line-height:1.02;
    font-weight:950;
    letter-spacing:-.05em;
    color:var(--text, #0f172a);
  }

  .invx-hero__sub{
    margin:0;
    max-width:880px;
    color:var(--muted, #64748b);
    font-size:12px;
    line-height:1.55;
  }

  .invx-hero__actions{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
    align-items:center;
  }

  .invx-btnx{
    appearance:none;
    border:1px solid var(--card-border, #e5e7eb);
    background:var(--card-bg, #fff);
    color:var(--text, #0f172a);
    min-height:40px;
    padding:0 14px;
    border-radius:12px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    text-decoration:none;
    font-size:13px;
    font-weight:900;
    cursor:pointer;
    transition:.18s ease;
  }

  .invx-btnx:hover{
    transform:translateY(-1px);
    box-shadow:0 10px 22px rgba(15,23,42,.06);
  }

  .invx-btnx--primary{
    border-color:transparent;
    color:#fff;
    background:linear-gradient(180deg, #0f3f60, #0c2f47);
  }

  .invx-btnx--soft{
    background:var(--panel-bg, #f8fafc);
  }

  .invx-btnx--ai{
    border-color:rgba(15,63,96,.14);
    background:linear-gradient(180deg, rgba(15,63,96,.08), rgba(15,63,96,.03));
    color:#0f3f60;
  }

  .invx-progress{
    padding:12px 14px;
  }

  .invx-progress__head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    flex-wrap:wrap;
    margin-bottom:10px;
  }

  .invx-progress__title{
    margin:0;
    font-size:13px;
    font-weight:950;
    color:var(--text, #0f172a);
  }

  .invx-progress__meta{
    color:var(--muted, #64748b);
    font-size:11px;
    font-weight:800;
  }

  .invx-steps{
    display:grid;
    grid-template-columns:repeat(7, minmax(0,1fr));
    gap:8px;
  }

  .invx-stepdot{
    position:relative;
    border:1px solid var(--card-border, #e5e7eb);
    border-radius:14px;
    background:var(--panel-bg, #f8fafc);
    padding:8px 10px;
    display:flex;
    align-items:center;
    gap:10px;
    min-height:52px;
    cursor:pointer;
    transition:.18s ease;
  }

  .invx-stepdot:hover{
    transform:translateY(-1px);
  }

  .invx-stepdot.is-active{
    border-color:rgba(15,63,96,.18);
    background:linear-gradient(180deg, rgba(15,63,96,.08), rgba(15,63,96,.03));
    box-shadow:inset 0 0 0 1px rgba(15,63,96,.08);
  }

  .invx-stepdot.is-done{
    background:linear-gradient(180deg, rgba(16,185,129,.11), rgba(16,185,129,.04));
    border-color:rgba(16,185,129,.22);
  }

  .invx-stepdot__num{
    width:24px;
    height:24px;
    border-radius:999px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    font-size:11px;
    font-weight:950;
    color:var(--text, #0f172a);
    background:#fff;
    border:1px solid var(--card-border, #e5e7eb);
    flex:0 0 24px;
  }

  .invx-stepdot.is-active .invx-stepdot__num{
    color:#fff;
    background:#0f3f60;
    border-color:#0f3f60;
  }

  .invx-stepdot.is-done .invx-stepdot__num{
    color:#fff;
    background:#10b981;
    border-color:#10b981;
  }

  .invx-stepdot__label{
    font-size:12px;
    font-weight:900;
    color:var(--text, #0f172a);
    line-height:1.1;
  }

  .invx-form{
    display:grid;
    gap:14px;
  }

  .invx-pane{
    display:none;
  }

  .invx-pane.is-active{
    display:block;
  }

  .invx-block{
    border:1px solid var(--card-border, #e5e7eb);
    border-radius:20px;
    background:var(--card-bg, #fff);
    overflow:hidden;
    box-shadow:0 10px 24px rgba(15,23,42,.03);
  }

  .invx-block__head{
    padding:14px 16px;
    border-bottom:1px solid color-mix(in oklab, var(--card-border, #e5e7eb) 82%, transparent);
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:12px;
    flex-wrap:wrap;
  }

  .invx-block__title{
    margin:0;
    font-size:16px;
    font-weight:950;
    color:var(--text, #0f172a);
    line-height:1.1;
  }

  .invx-block__sub{
    margin:4px 0 0;
    color:var(--muted, #64748b);
    font-size:11px;
    line-height:1.5;
    max-width:860px;
  }

  .invx-block__body{
    padding:16px;
    display:grid;
    gap:14px;
  }

  .invx-chipline{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
  }

  .invx-chip{
    display:inline-flex;
    align-items:center;
    gap:7px;
    min-height:30px;
    padding:0 10px;
    border-radius:999px;
    border:1px solid var(--card-border, #e5e7eb);
    background:var(--panel-bg, #f8fafc);
    color:var(--text, #0f172a);
    font-size:11px;
    font-weight:900;
  }

  .invx-chip__dot{
    width:6px;
    height:6px;
    border-radius:999px;
    background:#0ea5e9;
    flex:0 0 6px;
  }

  .invx-typebar{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
  }

  .invx-typebtn{
    appearance:none;
    border:1px solid var(--card-border, #e5e7eb);
    background:var(--card-bg, #fff);
    color:var(--text, #0f172a);
    border-radius:14px;
    min-height:42px;
    padding:0 14px;
    font-size:13px;
    font-weight:950;
    cursor:pointer;
    transition:.18s ease;
  }

  .invx-typebtn.is-active{
    border-color:transparent;
    color:#fff;
    background:linear-gradient(180deg, #0f3f60, #0c2f47);
    box-shadow:0 12px 20px rgba(15,63,96,.18);
  }

  .invx-grid{
    display:grid;
    grid-template-columns:repeat(12, minmax(0,1fr));
    gap:12px;
  }

  .invx-span-12{ grid-column:span 12; }
  .invx-span-8{ grid-column:span 8; }
  .invx-span-6{ grid-column:span 6; }
  .invx-span-4{ grid-column:span 4; }
  .invx-span-3{ grid-column:span 3; }

  .invx-choicegrid{
    display:grid;
    grid-template-columns:repeat(2, minmax(0,1fr));
    gap:12px;
  }

  .invx-choice{
    border:1px solid var(--card-border, #e5e7eb);
    border-radius:18px;
    background:var(--panel-bg, #f8fafc);
    padding:14px;
    display:grid;
    gap:12px;
    min-height:160px;
  }

  .invx-choice__title{
    margin:0;
    font-size:14px;
    font-weight:950;
    color:var(--text, #0f172a);
  }

  .invx-choice__sub{
    margin:0;
    color:var(--muted, #64748b);
    font-size:11px;
    line-height:1.55;
  }

  .invx-search-wrap{
    position:relative;
  }

  .invx-search-results{
    position:absolute;
    left:0;
    right:0;
    top:calc(100% + 8px);
    z-index:30;
  }

  .invx-picked{
    display:grid;
    grid-template-columns:repeat(2, minmax(0,1fr));
    gap:12px;
  }

  .invx-picked__card{
    border:1px solid var(--card-border, #e5e7eb);
    border-radius:16px;
    background:var(--panel-bg, #f8fafc);
    padding:12px;
    display:grid;
    gap:7px;
    min-height:95px;
  }

  .invx-picked__label{
    font-size:10px;
    text-transform:uppercase;
    letter-spacing:.08em;
    font-weight:900;
    color:var(--muted, #64748b);
  }

  .invx-picked__body{
    color:var(--text, #0f172a);
    font-size:12px;
    line-height:1.55;
    word-break:break-word;
  }

  .invx-files{
    display:grid;
    grid-template-columns:repeat(2, minmax(0,1fr));
    gap:12px;
  }

  .invx-filecard{
    border:1px dashed color-mix(in oklab, var(--card-border, #e5e7eb) 80%, #0f766e);
    border-radius:16px;
    background:var(--panel-bg, #f8fafc);
    padding:12px;
    display:grid;
    gap:8px;
  }

  .invx-filecard__title{
    margin:0;
    font-size:12px;
    font-weight:950;
    color:var(--text, #0f172a);
  }

  .invx-filecard__sub{
    margin:0;
    color:var(--muted, #64748b);
    font-size:11px;
    line-height:1.5;
  }

  .invx-review{
    display:grid;
    gap:12px;
  }

  .invx-review__grid{
    display:grid;
    grid-template-columns:repeat(2, minmax(0,1fr));
    gap:12px;
  }

  .invx-review__card{
    border:1px solid var(--card-border, #e5e7eb);
    border-radius:16px;
    background:var(--panel-bg, #f8fafc);
    padding:12px;
    display:grid;
    gap:8px;
  }

  .invx-review__title{
    margin:0;
    font-size:10px;
    text-transform:uppercase;
    letter-spacing:.08em;
    color:var(--muted, #64748b);
    font-weight:900;
  }

  .invx-review__body{
    display:grid;
    gap:6px;
    font-size:12px;
    color:var(--text, #0f172a);
    line-height:1.5;
  }

  .invx-review__row{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:10px;
  }

  .invx-review__row strong{
    font-weight:900;
  }

  .invx-ppd-alert{
    border:1px solid rgba(245,158,11,.24);
    background:linear-gradient(180deg, rgba(245,158,11,.10), rgba(245,158,11,.03));
    color:#92400e;
    border-radius:16px;
    padding:12px;
    display:none;
    gap:8px;
    font-size:12px;
  }

  .invx-ppd-alert.is-visible{
    display:grid;
  }

  .invx-nav{
    position:sticky;
    bottom:10px;
    z-index:25;
  }

  .invx-nav__inner{
    border:1px solid var(--card-border, #e5e7eb);
    border-radius:18px;
    background:color-mix(in oklab, var(--card-bg, #fff) 92%, transparent);
    backdrop-filter:blur(10px);
    box-shadow:0 14px 24px rgba(15,23,42,.08);
    padding:12px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    flex-wrap:wrap;
  }

  .invx-nav__meta{
    display:grid;
    gap:2px;
  }

  .invx-nav__title{
    font-size:12px;
    font-weight:950;
    color:var(--text, #0f172a);
  }

  .invx-nav__sub{
    font-size:11px;
    color:var(--muted, #64748b);
  }

  .invx-nav__actions{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
  }

  .invx-hidden{
    display:none !important;
  }

  .invx-bottom-summary{
    border:1px solid var(--card-border, #e5e7eb);
    border-radius:20px;
    background:var(--card-bg, #fff);
    box-shadow:0 10px 24px rgba(15,23,42,.03);
    overflow:hidden;
  }

  .invx-bottom-summary__head{
    padding:14px 16px;
    border-bottom:1px solid color-mix(in oklab, var(--card-border, #e5e7eb) 82%, transparent);
  }

  .invx-bottom-summary__title{
    margin:0;
    font-size:15px;
    font-weight:950;
    color:var(--text, #0f172a);
  }

  .invx-bottom-summary__sub{
    margin:4px 0 0;
    font-size:11px;
    color:var(--muted, #64748b);
  }

  .invx-bottom-summary__body{
    padding:14px 16px;
  }

  .invx-summarygrid{
    display:grid;
    grid-template-columns:repeat(7, minmax(0,1fr));
    gap:10px;
  }

  .invx-summarycell{
    border:1px solid var(--card-border, #e5e7eb);
    border-radius:14px;
    background:var(--panel-bg, #f8fafc);
    padding:10px;
    display:grid;
    gap:5px;
  }

  .invx-summarycell__label{
    font-size:10px;
    text-transform:uppercase;
    letter-spacing:.08em;
    color:var(--muted, #64748b);
    font-weight:900;
  }

  .invx-summarycell__value{
    font-size:13px;
    font-weight:950;
    color:var(--text, #0f172a);
    word-break:break-word;
  }

  .invx-ai-modal-dialog{
    width:min(760px, 94vw);
  }

  .invx-ai-modal-body{
    display:grid;
    gap:12px;
  }

  .invx-ai-modal-actions{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
    justify-content:flex-end;
  }

  .invx-ai-mini{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
  }

  .invx-ai-mini button{
    appearance:none;
    border:1px solid var(--card-border, #e5e7eb);
    background:var(--panel-bg, #f8fafc);
    color:var(--text, #0f172a);
    min-height:34px;
    padding:0 12px;
    border-radius:999px;
    font-size:12px;
    font-weight:900;
    cursor:pointer;
  }

  @media (max-width: 1100px){
    .invx-summarygrid{
      grid-template-columns:repeat(3, minmax(0,1fr));
    }
  }

  @media (max-width: 980px){
    .invx-steps{
      grid-template-columns:repeat(2, minmax(0,1fr));
    }

    .invx-span-8,
    .invx-span-6,
    .invx-span-4,
    .invx-span-3{
      grid-column:span 12;
    }

    .invx-choicegrid,
    .invx-picked,
    .invx-files,
    .invx-review__grid,
    .invx-summarygrid{
      grid-template-columns:1fr;
    }
  }

  @media (max-width: 680px){
    .billing-invoices-create-page .page-container{
      padding:8px;
    }

    .invx-hero,
    .invx-progress,
    .invx-block__head,
    .invx-block__body,
    .invx-bottom-summary__head,
    .invx-bottom-summary__body{
      padding-left:12px;
      padding-right:12px;
    }

    .invx-card,
    .invx-block,
    .invx-nav__inner,
    .invx-bottom-summary{
      border-radius:16px;
    }

    .invx-hero__actions,
    .invx-nav__actions,
    .invx-ai-modal-actions{
      width:100%;
    }

    .invx-btnx{
      width:100%;
    }
  }
</style>
@endpush

@section('content')
<div class="invx-wizard">
    <div class="invx-main">
        <section class="invx-card invx-hero">
            <div>
                <div class="invx-hero__eyebrow">
                    <span class="invx-hero__dot"></span>
                    Facturación admin con IA
                </div>

                <h1 class="invx-hero__title">Nueva factura</h1>
                <p class="invx-hero__sub">
                    Ahora el flujo está guiado paso a paso. Primero eliges el tipo de comprobante, luego puedes clonar desde XML o capturar desde cero, completas datos generales, emisor/receptor, SAT, adjuntos y al final revisas antes de generar.
                </p>
            </div>

            <div class="invx-hero__actions">
                <a href="{{ $routeDashboard }}" class="invx-btnx invx-btnx--soft">Dashboard</a>
                <a href="{{ $routeIndex }}" class="invx-btnx invx-btnx--soft">Volver a facturas</a>
                <button type="button" class="invx-btnx invx-btnx--primary" data-invx-modal-open="invxAiModal">IA rápida</button>
            </div>
        </section>

        <section class="invx-card invx-progress">
            <div class="invx-progress__head">
                <h2 class="invx-progress__title">Flujo de emisión</h2>
                <div class="invx-progress__meta">
                    Paso <span id="invxCurrentStepLabel">1</span> de {{ count($wizardSteps) }}
                </div>
            </div>

            <div class="invx-steps" id="invxWizardSteps">
                @foreach($wizardSteps as $stepNum => $stepLabel)
                    <button
                        type="button"
                        class="invx-stepdot {{ $stepNum === 1 ? 'is-active' : '' }}"
                        data-step-target="{{ $stepNum }}"
                    >
                        <span class="invx-stepdot__num">{{ $stepNum }}</span>
                        <span class="invx-stepdot__label">{{ $stepLabel }}</span>
                    </button>
                @endforeach
            </div>
        </section>

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

        <form method="POST" action="{{ $routeStoreOne }}" enctype="multipart/form-data" class="invx-form" id="invxAdvancedManualForm">
            @csrf

            <input type="hidden" name="emisor_id" id="invx_emisor_id" value="{{ old('emisor_id') }}">
            <input type="hidden" name="receptor_id" id="invx_receptor_id" value="{{ old('receptor_id') }}">
            <input type="hidden" name="wizard_step" id="invx_wizard_step" value="{{ old('wizard_step', '1') }}">
            <input type="hidden" name="clone_mode" id="invx_clone_mode" value="{{ old('clone_mode', 'skip') }}">

            {{-- PASO 1 --}}
            <section class="invx-pane is-active" data-step-pane="1">
                <section class="invx-block">
                    <div class="invx-block__head">
                        <div>
                            <h2 class="invx-block__title">Paso 1 · Tipo de comprobante</h2>
                            <p class="invx-block__sub">
                                Elige qué tipo de comprobante vas a generar. La IA te ayudará a ajustar el resto del flujo según esta elección.
                            </p>
                        </div>

                        <div class="invx-chipline">
                            <span class="invx-chip"><span class="invx-chip__dot"></span>IA contextual</span>
                            <span class="invx-chip"><span class="invx-chip__dot"></span>Validación guiada</span>
                        </div>
                    </div>

                    <div class="invx-block__body">
                        <div class="invx-typebar" id="invxTypeBar">
                            <button type="button" class="invx-typebtn {{ $oldTipo === 'I' ? 'is-active' : '' }}" data-value="I">Ingreso</button>
                            <button type="button" class="invx-typebtn {{ $oldTipo === 'E' ? 'is-active' : '' }}" data-value="E">Egreso</button>
                            <button type="button" class="invx-typebtn {{ $oldTipo === 'P' ? 'is-active' : '' }}" data-value="P">Pago</button>
                            <button type="button" class="invx-typebtn {{ $oldTipo === 'N' ? 'is-active' : '' }}" data-value="N">Nómina</button>
                            <button type="button" class="invx-typebtn {{ $oldTipo === 'T' ? 'is-active' : '' }}" data-value="T">Traslado</button>
                        </div>

                        <div class="invx-grid">
                            <div class="invx-span-3">
                                <div class="invx-floating">
                                    <select id="invx_tipo_comprobante" name="tipo_comprobante" class="invx-input invx-select">
                                        <option value="I" @selected($oldTipo === 'I')>Ingreso</option>
                                        <option value="E" @selected($oldTipo === 'E')>Egreso</option>
                                        <option value="P" @selected($oldTipo === 'P')>Pago</option>
                                        <option value="N" @selected($oldTipo === 'N')>Nómina</option>
                                        <option value="T" @selected($oldTipo === 'T')>Traslado</option>
                                    </select>
                                    <label for="invx_tipo_comprobante">Tipo de comprobante</label>
                                </div>
                            </div>

                            <div class="invx-span-3">
                                <div class="invx-floating">
                                    <select id="manual_status" name="status" class="invx-input invx-select">
                                        <option value="issued" @selected($oldStatus === 'issued')>Emitida</option>
                                        <option value="active" @selected($oldStatus === 'active')>Activa</option>
                                        <option value="sent" @selected($oldStatus === 'sent')>Enviada</option>
                                        <option value="paid" @selected($oldStatus === 'paid')>Pagada</option>
                                        <option value="pending" @selected($oldStatus === 'pending')>Pendiente</option>
                                    </select>
                                    <label for="manual_status">Estado inicial</label>
                                </div>
                            </div>

                            <div class="invx-span-3">
                                <div class="invx-floating">
                                    <select id="invx_complemento" name="complemento" class="invx-input invx-select">
                                        <option value="none" @selected($oldComp === 'none')>Sin complemento</option>
                                        <option value="pago20" @selected($oldComp === 'pago20')>Pago 2.0</option>
                                        <option value="carta_porte" @selected($oldComp === 'carta_porte')>Carta Porte</option>
                                        <option value="comercio_exterior" @selected($oldComp === 'comercio_exterior')>Comercio Exterior</option>
                                        <option value="nomina12" @selected($oldComp === 'nomina12')>Nómina 1.2</option>
                                    </select>
                                    <label for="invx_complemento">Complemento</label>
                                </div>
                            </div>

                            <div class="invx-span-3">
                                <div class="invx-floating">
                                    <input id="manual_source" type="text" name="source" class="invx-input" value="{{ $oldSource }}" placeholder=" ">
                                    <label for="manual_source">Origen</label>
                                </div>
                            </div>
                        </div>

                        <div class="invx-review__card">
                            <h3 class="invx-review__title">Ayuda IA para este paso</h3>
                            <div class="invx-review__body" id="invxTipoHelpBox">
                                <div><strong>Tipo actual:</strong> <span id="invxTipoActualLabel">{{ $tipoActualLabel }}</span></div>
                                <div id="invxTipoSmartText">La IA te explicará este tipo de comprobante y ajustará recomendaciones en los siguientes pasos.</div>
                            </div>
                        </div>
                    </div>
                </section>
            </section>

            {{-- PASO 2 --}}
            <section class="invx-pane" data-step-pane="2">
                <section class="invx-block">
                    <div class="invx-block__head">
                        <div>
                            <h2 class="invx-block__title">Paso 2 · Clonar comprobante o continuar desde cero</h2>
                            <p class="invx-block__sub">
                                Puedes clonar información desde XML para ahorrar tiempo o continuar sin clonar. Esto se usa mucho para repetir estructuras o capturar comprobantes parecidos.
                            </p>
                        </div>
                    </div>

                    <div class="invx-block__body">
                        <div class="invx-choicegrid">
                            <div class="invx-choice">
                                <div>
                                    <h3 class="invx-choice__title">Clonar desde XML</h3>
                                    <p class="invx-choice__sub">
                                        Sube un XML para precargar información base. La IA podrá sugerir campos faltantes, explicar el tipo detectado y ayudarte a revisar inconsistencias.
                                    </p>
                                </div>

                                <div class="invx-filecard">
                                    <p class="invx-filecard__title">XML base</p>
                                    <p class="invx-filecard__sub">Acepta XML o TXT/XML plano según tu flujo actual.</p>
                                    <input id="manual_xml" type="file" name="xml" class="invx-input" accept=".xml,application/xml,text/xml,.txt,text/plain">
                                </div>

                                <button type="button" class="invx-btnx invx-btnx--soft" id="invxUseCloneMode">
                                    Usar XML como base
                                </button>
                            </div>

                            <div class="invx-choice">
                                <div>
                                    <h3 class="invx-choice__title">Capturar desde cero</h3>
                                    <p class="invx-choice__sub">
                                        Si no vas a clonar nada, omite este paso y continúa con la captura guiada. La IA seguirá asistiendo con datos generales, SAT y revisión.
                                    </p>
                                </div>

                                <div class="invx-review__card">
                                    <h3 class="invx-review__title">Consejo IA</h3>
                                    <div class="invx-review__body">
                                        <div>Para facturas nuevas o diferentes, conviene omitir el clonado y capturar con ayuda IA paso a paso.</div>
                                    </div>
                                </div>

                                <button type="button" class="invx-btnx invx-btnx--soft" id="invxSkipCloneMode">
                                    Omitir clonado
                                </button>
                            </div>
                        </div>

                        <div class="invx-grid">
                            <div class="invx-span-12">
                                <div class="invx-floating">
                                    <input id="manual_cfdi_uuid" type="text" name="cfdi_uuid" class="invx-input" value="{{ old('cfdi_uuid') }}" placeholder=" ">
                                    <label for="manual_cfdi_uuid">UUID / folio fiscal relacionado</label>
                                </div>
                            </div>
                        </div>

                        <div class="invx-review__card">
                            <h3 class="invx-review__title">Estado del paso</h3>
                            <div class="invx-review__body">
                                <div><strong>Modo actual:</strong> <span id="invxCloneModeLabel">Omitir clonado</span></div>
                                <div id="invxCloneModeText">Continuarás capturando la factura paso a paso.</div>
                            </div>
                        </div>
                    </div>
                </section>
            </section>

            {{-- PASO 3 --}}
            <section class="invx-pane" data-step-pane="3">
                <section class="invx-block">
                    <div class="invx-block__head">
                        <div>
                            <h2 class="invx-block__title">Paso 3 · Datos generales</h2>
                            <p class="invx-block__sub">
                                Captura la información base de la factura. Aquí más adelante se disparará el flujo especial cuando el método sea PPD.
                            </p>
                        </div>
                    </div>

                    <div class="invx-block__body">
                        <div class="invx-grid">
                            <div class="invx-span-3">
                                <div class="invx-floating">
                                    <input id="manual_account_id" type="text" name="account_id" class="invx-input" value="{{ old('account_id') }}" placeholder=" " required>
                                    <label for="manual_account_id">Cuenta</label>
                                </div>
                            </div>

                            <div class="invx-span-3">
                                <div class="invx-floating">
                                    <input id="manual_period" type="text" name="period" class="invx-input" value="{{ $oldPeriod }}" placeholder=" " required>
                                    <label for="manual_period">Periodo</label>
                                </div>
                            </div>

                            <div class="invx-span-3">
                                <div class="invx-floating">
                                    <input id="manual_amount_mxn" type="number" step="0.01" min="0" name="amount_mxn" class="invx-input" value="{{ $oldMonto }}" placeholder=" ">
                                    <label for="manual_amount_mxn">Monto MXN</label>
                                </div>
                            </div>

                            <div class="invx-span-3">
                                <div class="invx-floating">
                                    <input id="manual_issued_at" type="datetime-local" name="issued_at" class="invx-input" value="{{ old('issued_at') }}" placeholder=" ">
                                    <label for="manual_issued_at">Fecha y hora</label>
                                </div>
                            </div>

                            <div class="invx-span-4">
                                <div class="invx-floating">
                                    <input id="manual_issued_date" type="date" name="issued_date" class="invx-input" value="{{ old('issued_date') }}" placeholder=" ">
                                    <label for="manual_issued_date">Solo fecha</label>
                                </div>
                            </div>

                            <div class="invx-span-4">
                                <div class="invx-floating">
                                    <input id="manual_serie" type="text" name="serie" class="invx-input" value="{{ old('serie') }}" placeholder=" ">
                                    <label for="manual_serie">Serie</label>
                                </div>
                            </div>

                            <div class="invx-span-4">
                                <div class="invx-floating">
                                    <input id="manual_folio" type="text" name="folio" class="invx-input" value="{{ old('folio') }}" placeholder=" ">
                                    <label for="manual_folio">Folio</label>
                                </div>
                            </div>
                        </div>

                        <div class="invx-ppd-alert" id="invxPpdAlertBox">
                            <div><strong>Flujo PPD detectado.</strong></div>
                            <div>
                                Si el método de pago termina siendo PPD, esta factura deberá poder mostrar estado pendiente, parcial o pagada y administrar complementos de pago después de emitirse.
                            </div>
                        </div>
                    </div>
                </section>
            </section>

            {{-- PASO 4 --}}
            <section class="invx-pane" data-step-pane="4">
                <section class="invx-block">
                    <div class="invx-block__head">
                        <div>
                            <h2 class="invx-block__title">Paso 4 · Emisor y receptor</h2>
                            <p class="invx-block__sub">
                                Selecciona ambas partes antes de generar la factura. La IA podrá ayudarte a ubicar rápidamente al receptor correcto o detectar datos faltantes.
                            </p>
                        </div>
                    </div>

                    <div class="invx-block__body">
                        <div class="invx-grid">
                            <div class="invx-span-6">
                                <div class="invx-search-wrap">
                                    <div class="invx-floating">
                                        <input id="invx_emisor_search" type="text" class="invx-input" placeholder=" ">
                                        <label for="invx_emisor_search">Buscar emisor</label>
                                    </div>
                                    <div class="invx-search-results" id="invx_emisor_results"></div>
                                </div>
                            </div>

                            <div class="invx-span-6">
                                <div class="invx-search-wrap">
                                    <div class="invx-floating">
                                        <input id="invx_receptor_search" type="text" class="invx-input" placeholder=" ">
                                        <label for="invx_receptor_search">Buscar receptor</label>
                                    </div>
                                    <div class="invx-search-results" id="invx_receptor_results"></div>
                                </div>
                            </div>

                            <div class="invx-span-12">
                                <div class="invx-picked">
                                    <div class="invx-picked__card">
                                        <div class="invx-picked__label">Emisor seleccionado</div>
                                        <div id="invxPickedEmisor" class="invx-picked__body">Sin seleccionar</div>
                                    </div>

                                    <div class="invx-picked__card">
                                        <div class="invx-picked__label">Receptor seleccionado</div>
                                        <div id="invxPickedReceptor" class="invx-picked__body">Sin seleccionar</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </section>

            {{-- PASO 5 --}}
            <section class="invx-pane" data-step-pane="5">
                <section class="invx-block">
                    <div class="invx-block__head">
                        <div>
                            <h2 class="invx-block__title">Paso 5 · Datos SAT</h2>
                            <p class="invx-block__sub">
                                Aquí se concentra la configuración fiscal. Este paso es clave para la validación y para el flujo futuro de PUE/PPD y complementos.
                            </p>
                        </div>
                    </div>

                    <div class="invx-block__body">
                        <div class="invx-grid">
                            <div class="invx-span-3">
                                <div class="invx-floating">
                                    <select id="invx_uso_cfdi" name="uso_cfdi" class="invx-input invx-select"></select>
                                    <label for="invx_uso_cfdi">Uso CFDI</label>
                                </div>
                            </div>

                            <div class="invx-span-3">
                                <div class="invx-floating">
                                    <select id="invx_regimen_fiscal" name="regimen_fiscal" class="invx-input invx-select"></select>
                                    <label for="invx_regimen_fiscal">Régimen fiscal</label>
                                </div>
                            </div>

                            <div class="invx-span-3">
                                <div class="invx-floating">
                                    <select id="invx_forma_pago" name="forma_pago" class="invx-input invx-select"></select>
                                    <label for="invx_forma_pago">Forma de pago</label>
                                </div>
                            </div>

                            <div class="invx-span-3">
                                <div class="invx-floating">
                                    <select id="invx_metodo_pago" name="metodo_pago" class="invx-input invx-select"></select>
                                    <label for="invx_metodo_pago">Método de pago</label>
                                </div>
                            </div>

                            <div class="invx-span-3">
                                <div class="invx-floating">
                                    <select id="invx_moneda" name="moneda" class="invx-input invx-select"></select>
                                    <label for="invx_moneda">Moneda</label>
                                </div>
                            </div>

                            <div class="invx-span-3">
                                <div class="invx-floating">
                                    <select id="invx_exportacion" name="exportacion" class="invx-input invx-select"></select>
                                    <label for="invx_exportacion">Exportación</label>
                                </div>
                            </div>

                            <div class="invx-span-3">
                                <div class="invx-floating">
                                    <select id="invx_objeto_impuesto" name="objeto_impuesto" class="invx-input invx-select"></select>
                                    <label for="invx_objeto_impuesto">Objeto impuesto</label>
                                </div>
                            </div>

                            <div class="invx-span-3">
                                <div class="invx-floating">
                                    <input id="invx_tasa_iva" type="text" name="tasa_iva" class="invx-input" value="{{ old('tasa_iva', '0.160000') }}" placeholder=" ">
                                    <label for="invx_tasa_iva">Tasa IVA</label>
                                </div>
                            </div>
                        </div>

                        <div class="invx-review__card">
                            <h3 class="invx-review__title">Ayuda IA SAT</h3>
                            <div class="invx-review__body" id="invxSatHintBox">
                                <div>La IA te ayudará a revisar compatibilidad entre uso CFDI, régimen, forma de pago y método de pago.</div>
                            </div>
                        </div>
                    </div>
                </section>
            </section>

            {{-- PASO 6 --}}
            <section class="invx-pane" data-step-pane="6">
                <section class="invx-block">
                    <div class="invx-block__head">
                        <div>
                            <h2 class="invx-block__title">Paso 6 · Adjuntos base</h2>
                            <p class="invx-block__sub">
                                Carga los archivos base necesarios para registrar esta factura manual. El envío y las descargas aparecerán después en el detalle.
                            </p>
                        </div>
                    </div>

                    <div class="invx-block__body">
                        <div class="invx-files">
                            <div class="invx-filecard">
                                <p class="invx-filecard__title">PDF</p>
                                <p class="invx-filecard__sub">Adjunta el PDF de la factura.</p>
                                <input id="manual_pdf" type="file" name="pdf" class="invx-input" accept="application/pdf">
                            </div>

                            <div class="invx-filecard">
                                <p class="invx-filecard__title">XML</p>
                                <p class="invx-filecard__sub">Puedes volver a adjuntar XML si no lo subiste en el paso de clonado.</p>
                                <input id="manual_xml_copy" type="file" name="xml" class="invx-input" accept=".xml,application/xml,text/xml,.txt,text/plain">
                            </div>
                        </div>
                    </div>
                </section>
            </section>

            {{-- PASO 7 --}}
            <section class="invx-pane" data-step-pane="7">
                <section class="invx-block">
                    <div class="invx-block__head">
                        <div>
                            <h2 class="invx-block__title">Paso 7 · Revisión final</h2>
                            <p class="invx-block__sub">
                                Revisa el resumen antes de generar. Aquí después agregaremos también revisión de pagos, PPD y administración de complementos cuando conectemos backend.
                            </p>
                        </div>
                    </div>

                    <div class="invx-block__body">
                        <div class="invx-review">
                            <div class="invx-review__grid">
                                <div class="invx-review__card">
                                    <h3 class="invx-review__title">Resumen general</h3>
                                    <div class="invx-review__body" id="invxReviewGeneral">
                                        <div class="invx-review__row"><span>Tipo</span><strong id="invxReviewTipo">{{ $tipoActualLabel }}</strong></div>
                                        <div class="invx-review__row"><span>Cuenta</span><strong id="invxReviewCuenta">—</strong></div>
                                        <div class="invx-review__row"><span>Periodo</span><strong id="invxReviewPeriodo">{{ $oldPeriod }}</strong></div>
                                        <div class="invx-review__row"><span>Monto</span><strong id="invxReviewMonto">$0.00</strong></div>
                                        <div class="invx-review__row"><span>Estado</span><strong id="invxReviewEstado">{{ ucfirst($oldStatus) }}</strong></div>
                                    </div>
                                </div>

                                <div class="invx-review__card">
                                    <h3 class="invx-review__title">Estado fiscal y archivos</h3>
                                    <div class="invx-review__body">
                                        <div class="invx-review__row"><span>Método</span><strong id="invxReviewMetodo">—</strong></div>
                                        <div class="invx-review__row"><span>Forma</span><strong id="invxReviewForma">—</strong></div>
                                        <div class="invx-review__row"><span>Moneda</span><strong id="invxReviewMoneda">—</strong></div>
                                        <div class="invx-review__row"><span>Adjuntos</span><strong id="invxReviewAdjuntos">0</strong></div>
                                        <div class="invx-review__row"><span>Modo clonado</span><strong id="invxReviewClonado">Omitir</strong></div>
                                    </div>
                                </div>
                            </div>

                            <div class="invx-review__card">
                                <h3 class="invx-review__title">Sugerencia IA antes de emitir</h3>
                                <div class="invx-review__body" id="invxAiPreflightBox">
                                    <div>La IA revisará la consistencia básica del formulario antes de generar la factura.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </section>

            <div class="invx-nav">
                <div class="invx-nav__inner">
                    <div class="invx-nav__meta">
                        <div class="invx-nav__title" id="invxNavTitle">Paso 1 · Tipo de comprobante</div>
                        <div class="invx-nav__sub" id="invxNavSub">Selecciona el tipo de comprobante y arranca el flujo.</div>
                    </div>

                    <div class="invx-nav__actions">
                        <a href="{{ $routeIndex }}" class="invx-btnx invx-btnx--soft">Cancelar</a>
                        <button type="button" class="invx-btnx invx-btnx--soft" id="invxPrevStep" disabled>Anterior</button>
                        <button type="button" class="invx-btnx invx-btnx--soft" id="invxNextStep">Siguiente</button>
                        <button type="submit" class="invx-btnx invx-btnx--primary invx-hidden" id="invxSubmitCreateInvoice">Generar factura</button>
                    </div>
                </div>
            </div>
        </form>
        <section class="invx-bottom-summary">
            <div class="invx-bottom-summary__head">
                <h2 class="invx-bottom-summary__title">Resumen rápido</h2>
                <p class="invx-bottom-summary__sub">Se actualiza mientras capturas y queda fijo al final del flujo.</p>
            </div>

            <div class="invx-bottom-summary__body">
                <div class="invx-summarygrid">
                    <div class="invx-summarycell">
                        <div class="invx-summarycell__label">Tipo</div>
                        <div class="invx-summarycell__value" id="invxSummaryTipo">{{ $tipoActualLabel }}</div>
                    </div>

                    <div class="invx-summarycell">
                        <div class="invx-summarycell__label">Cuenta</div>
                        <div class="invx-summarycell__value" id="invxSummaryCuenta">—</div>
                    </div>

                    <div class="invx-summarycell">
                        <div class="invx-summarycell__label">Monto</div>
                        <div class="invx-summarycell__value" id="invxSummaryMonto">$0.00</div>
                    </div>

                    <div class="invx-summarycell">
                        <div class="invx-summarycell__label">Periodo</div>
                        <div class="invx-summarycell__value" id="invxSummaryPeriodo">{{ $oldPeriod }}</div>
                    </div>

                    <div class="invx-summarycell">
                        <div class="invx-summarycell__label">Adjuntos</div>
                        <div class="invx-summarycell__value" id="invxSummaryAdjuntos">0</div>
                    </div>

                    <div class="invx-summarycell">
                        <div class="invx-summarycell__label">Método</div>
                        <div class="invx-summarycell__value" id="invxSummaryMetodo">—</div>
                    </div>

                    <div class="invx-summarycell">
                        <div class="invx-summarycell__label">Complemento</div>
                        <div class="invx-summarycell__value" id="invxSummaryComplemento">Sin complemento</div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<div class="invx-modal" id="invxAiModal" aria-hidden="true">
    <div class="invx-modal__backdrop" data-invx-modal-close></div>

    <div class="invx-modal__dialog invx-ai-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="invxAiModalTitle">
        <div class="invx-modal__head">
            <div>
                <h3 class="invx-modal__title" id="invxAiModalTitle">Asistente IA</h3>
                <p class="invx-modal__sub">Describe la factura y usa IA para ayudarte a llenar el wizard.</p>
            </div>

            <button type="button" class="invx-iconbtn" data-invx-modal-close aria-label="Cerrar IA">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M6 6L18 18M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
            </button>
        </div>

        <div class="invx-modal__body">
            <div class="invx-ai-modal-body">
                <div class="invx-floating invx-floating--textarea">
                    <textarea id="invxAiPrompt" class="invx-textarea invx-textarea--sm" placeholder=" "></textarea>
                    <label for="invxAiPrompt">Ejemplo: factura de ingreso por servicio mensual, pago pendiente, receptor habitual...</label>
                </div>

                <div class="invx-ai-modal-actions">
                    <button type="button" class="invx-btnx invx-btnx--primary" id="invxAiComposeBtn">Aplicar IA</button>
                    <button type="button" class="invx-btnx invx-btnx--soft" id="invxAiExplainStepBtn">Explícame este paso</button>
                    <button type="button" class="invx-btnx invx-btnx--soft" id="invxAiReviewBtn">Revisar inconsistencias</button>
                </div>

                <div class="invx-ai-mini">
                    <button type="button" id="invxAssistToday">Hoy</button>
                    <button type="button" id="invxAssistCurrentPeriod">Periodo actual</button>
                    <button type="button" id="invxAssistDefaultSource">Origen IA</button>
                    <button type="button" id="invxAssistClearUuid">Limpiar UUID</button>
                    <button type="button" id="invxAssistFocusEmisor">Emisor</button>
                    <button type="button" id="invxAssistFocusReceptor">Receptor</button>
                </div>

                <div class="invx-review__card">
                    <h3 class="invx-review__title">Respuesta IA</h3>
                    <div class="invx-review__body" id="invxAiResponseBox">
                        <div>La IA está lista para ayudarte con tipo de comprobante, SAT, PUE/PPD y revisión de campos.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
window.__INVX_FORM_SEED__ = @json($routeFormSeed);
window.__INVX_SEARCH_EMISORES__ = @json($routeSearchEmisores);
window.__INVX_SEARCH_RECEPTORES__ = @json($routeSearchReceptores);
</script>
@endsection

@push('scripts')
<script src="{{ asset('assets/admin/js/invoicing-invoices.js') }}?v={{ @filemtime(public_path('assets/admin/js/invoicing-invoices.js')) ?: time() }}"></script>
<script>
window.__INVX_AUTO_OPEN_SINGLE__ = false;
</script>

@push('scripts')
<script src="{{ asset('assets/admin/js/invoicing-invoices.js') }}?v={{ @filemtime(public_path('assets/admin/js/invoicing-invoices.js')) ?: time() }}"></script>
<script>
window.__INVX_AUTO_OPEN_SINGLE__ = false;
</script>
@endpush
@endpush