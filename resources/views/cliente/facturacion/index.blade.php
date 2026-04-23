{{-- resources/views/cliente/facturacion/index.blade.php
     Pactopia360 · Facturación · v4 visual
--}}
@extends('layouts.cliente')
@section('title','Facturación · Pactopia360')

@push('styles')
<style>
  .fx-page{
    display:grid;
    gap:18px;
    min-width:0;
    color:var(--ink,#0f172a);
  }

  .fx-hero{
    position:relative;
    overflow:hidden;
    border:1px solid color-mix(in oklab, var(--brand,#2563eb) 14%, var(--bd,rgba(15,23,42,.08)));
    border-radius:24px;
    padding:22px;
    background:
      radial-gradient(circle at top right, rgba(96,165,250,.22), transparent 32%),
      linear-gradient(135deg, rgba(255,255,255,.98) 0%, rgba(248,250,255,.96) 56%, rgba(239,246,255,.95) 100%);
    box-shadow:0 18px 46px rgba(15,23,42,.06);
  }

  html[data-theme="dark"] .fx-hero{
    background:
      radial-gradient(circle at top right, rgba(59,130,246,.16), transparent 30%),
      linear-gradient(135deg, rgba(15,23,42,.96) 0%, rgba(17,24,39,.94) 56%, rgba(10,20,40,.94) 100%);
    border-color:rgba(96,165,250,.18);
    box-shadow:0 20px 50px rgba(0,0,0,.24);
  }

  .fx-hero__grid{
    display:grid;
    grid-template-columns:minmax(0,1.3fr) minmax(320px,.9fr);
    gap:16px;
    align-items:stretch;
  }

  @media (max-width: 980px){
    .fx-hero__grid{
      grid-template-columns:1fr;
    }
  }

  .fx-eyebrow{
    display:inline-flex;
    align-items:center;
    gap:8px;
    font-size:11px;
    font-weight:900;
    text-transform:uppercase;
    letter-spacing:.14em;
    color:var(--muted,#64748b);
  }

  .fx-title{
    margin:10px 0 0;
    font:900 clamp(26px, 3vw, 38px)/1.02 "Poppins",system-ui;
    letter-spacing:-.04em;
  }

  .fx-sub{
    margin:10px 0 0;
    max-width:720px;
    font-size:14px;
    line-height:1.55;
    color:var(--muted,#64748b);
  }

  .fx-chiprow{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    margin-top:16px;
  }

  .fx-chip{
    display:inline-flex;
    align-items:center;
    gap:8px;
    min-height:36px;
    padding:8px 12px;
    border-radius:999px;
    font-size:12px;
    font-weight:800;
    border:1px solid rgba(37,99,235,.10);
    background:rgba(255,255,255,.74);
    box-shadow:0 8px 20px rgba(15,23,42,.04);
  }

  html[data-theme="dark"] .fx-chip{
    background:rgba(255,255,255,.06);
    border-color:rgba(255,255,255,.08);
  }

  .fx-chip svg{
    width:16px;
    height:16px;
    flex:0 0 auto;
  }

  .fx-side{
    display:grid;
    gap:12px;
  }

  .fx-plan{
    display:grid;
    gap:12px;
    border-radius:22px;
    padding:18px;
    border:1px solid rgba(37,99,235,.10);
    background:
      linear-gradient(180deg, rgba(255,255,255,.96), rgba(255,255,255,.90));
    box-shadow:0 14px 30px rgba(15,23,42,.05);
  }

  html[data-theme="dark"] .fx-plan{
    background:linear-gradient(180deg, rgba(15,23,42,.92), rgba(17,24,39,.88));
    border-color:rgba(255,255,255,.08);
    box-shadow:0 16px 34px rgba(0,0,0,.22);
  }

  .fx-plan__top{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
  }

  .fx-badge{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:28px;
    padding:0 12px;
    border-radius:999px;
    font-size:11px;
    font-weight:900;
    letter-spacing:.08em;
    border:1px solid rgba(37,99,235,.14);
    background:rgba(37,99,235,.10);
    color:#0f4ed8;
  }

  html[data-theme="dark"] .fx-badge{
    background:rgba(96,165,250,.12);
    color:#dbeafe;
    border-color:rgba(96,165,250,.16);
  }

  .fx-badge--pro{
    background:linear-gradient(90deg, #0f5eff, #38bdf8);
    color:#fff;
    border-color:transparent;
    box-shadow:0 10px 24px rgba(37,99,235,.18);
  }

  .fx-plan__amount{
    font:900 28px/1 "Poppins",system-ui;
    letter-spacing:-.04em;
  }

  .fx-plan__mini{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:10px;
  }

  .fx-mini{
    border-radius:16px;
    padding:12px;
    background:rgba(37,99,235,.06);
    border:1px solid rgba(37,99,235,.08);
  }

  html[data-theme="dark"] .fx-mini{
    background:rgba(255,255,255,.04);
    border-color:rgba(255,255,255,.07);
  }

  .fx-mini__k{
    font-size:11px;
    text-transform:uppercase;
    letter-spacing:.12em;
    color:var(--muted,#64748b);
    font-weight:900;
  }

  .fx-mini__v{
    margin-top:6px;
    font-size:18px;
    font-weight:900;
    letter-spacing:-.03em;
  }

  .fx-mini__s{
    margin-top:5px;
    font-size:11px;
    color:var(--muted,#64748b);
  }

  .fx-actions{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
  }

  .fx-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    min-height:42px;
    padding:0 14px;
    border-radius:14px;
    text-decoration:none;
    border:1px solid rgba(37,99,235,.12);
    background:rgba(255,255,255,.88);
    color:#0f172a;
    font-weight:900;
    transition:transform .16s ease, box-shadow .16s ease, border-color .16s ease;
    box-shadow:0 10px 22px rgba(15,23,42,.04);
  }

  .fx-btn:hover{
    transform:translateY(-1px);
    border-color:rgba(37,99,235,.18);
    box-shadow:0 14px 28px rgba(15,23,42,.08);
  }

  .fx-btn svg{
    width:18px;
    height:18px;
  }

  .fx-btn--primary{
    color:#fff;
    border-color:transparent;
    background:linear-gradient(90deg, #0f5eff, #38bdf8);
    box-shadow:0 14px 30px rgba(37,99,235,.18);
  }

  .fx-btn--ghost{
    background:rgba(255,255,255,.66);
  }

  html[data-theme="dark"] .fx-btn{
    background:rgba(255,255,255,.06);
    color:#e5e7eb;
    border-color:rgba(255,255,255,.08);
  }

  .fx-kpis{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:12px;
  }

  @media (max-width: 1100px){
    .fx-kpis{ grid-template-columns:repeat(2,minmax(0,1fr)); }
  }

  @media (max-width: 640px){
    .fx-kpis{ grid-template-columns:1fr; }
  }

  .fx-kpi{
    position:relative;
    overflow:hidden;
    border-radius:20px;
    padding:16px;
    border:1px solid rgba(37,99,235,.10);
    background:linear-gradient(180deg, rgba(255,255,255,.96), rgba(255,255,255,.92));
    box-shadow:0 12px 24px rgba(15,23,42,.05);
  }

  .fx-kpi::before{
    content:"";
    position:absolute;
    left:0;
    right:0;
    bottom:0;
    height:3px;
    background:linear-gradient(90deg, #2563eb, #38bdf8);
  }

  html[data-theme="dark"] .fx-kpi{
    background:linear-gradient(180deg, rgba(15,23,42,.92), rgba(17,24,39,.88));
    border-color:rgba(255,255,255,.08);
    box-shadow:0 16px 30px rgba(0,0,0,.22);
  }

  .fx-kpi__label{
    font-size:11px;
    text-transform:uppercase;
    letter-spacing:.12em;
    color:var(--muted,#64748b);
    font-weight:900;
  }

  .fx-kpi__value{
    margin-top:6px;
    font-size:26px;
    font-weight:900;
    letter-spacing:-.04em;
  }

  .fx-kpi__sub{
    margin-top:8px;
    font-size:11px;
    color:var(--muted,#64748b);
  }

  .fx-acc{
    display:grid;
    gap:14px;
  }

  .fx-pane{
    border-radius:22px;
    border:1px solid rgba(37,99,235,.10);
    background:linear-gradient(180deg, rgba(255,255,255,.96), rgba(255,255,255,.92));
    box-shadow:0 12px 30px rgba(15,23,42,.05);
    overflow:hidden;
  }

  html[data-theme="dark"] .fx-pane{
    background:linear-gradient(180deg, rgba(15,23,42,.92), rgba(17,24,39,.88));
    border-color:rgba(255,255,255,.08);
    box-shadow:0 16px 34px rgba(0,0,0,.22);
  }

  .fx-pane > summary{
    list-style:none;
    cursor:pointer;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:16px;
    padding:18px 20px;
  }

  .fx-pane > summary::-webkit-details-marker{ display:none; }

  .fx-pane__left{
    display:flex;
    align-items:center;
    gap:14px;
    min-width:0;
  }

  .fx-pane__ico{
    width:46px;
    height:46px;
    border-radius:16px;
    display:grid;
    place-items:center;
    background:linear-gradient(135deg, rgba(37,99,235,.14), rgba(56,189,248,.12));
    color:#0f5eff;
    flex:0 0 auto;
  }

  html[data-theme="dark"] .fx-pane__ico{
    color:#dbeafe;
    background:linear-gradient(135deg, rgba(59,130,246,.20), rgba(56,189,248,.14));
  }

  .fx-pane__ico svg{
    width:22px;
    height:22px;
  }

  .fx-pane__title{
    font-size:16px;
    font-weight:900;
    letter-spacing:-.02em;
  }

  .fx-pane__sub{
    margin-top:4px;
    font-size:12px;
    color:var(--muted,#64748b);
  }

  .fx-pane__right{
    display:flex;
    align-items:center;
    gap:10px;
    flex:0 0 auto;
  }

  .fx-pane__toggle{
    width:36px;
    height:36px;
    border-radius:12px;
    display:grid;
    place-items:center;
    border:1px solid rgba(37,99,235,.10);
    background:rgba(255,255,255,.70);
  }

  html[data-theme="dark"] .fx-pane__toggle{
    background:rgba(255,255,255,.06);
    border-color:rgba(255,255,255,.08);
  }

  .fx-pane[open] .fx-pane__toggle{
    transform:rotate(180deg);
  }

  .fx-pane__body{
    padding:0 20px 20px;
    display:grid;
    gap:16px;
  }

  .fx-tilegrid{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:12px;
  }

  @media (max-width: 1100px){
    .fx-tilegrid{ grid-template-columns:repeat(2,minmax(0,1fr)); }
  }

  @media (max-width: 640px){
    .fx-tilegrid{ grid-template-columns:1fr; }
  }

  .fx-tile{
    position:relative;
    min-height:120px;
    border-radius:18px;
    padding:14px;
    border:1px solid rgba(37,99,235,.10);
    background:linear-gradient(180deg, rgba(255,255,255,.94), rgba(255,255,255,.90));
    text-decoration:none;
    color:inherit;
    box-shadow:0 10px 22px rgba(15,23,42,.04);
    overflow:hidden;
  }

  .fx-tile:hover{
    transform:translateY(-2px);
    box-shadow:0 16px 30px rgba(15,23,42,.08);
  }

  html[data-theme="dark"] .fx-tile{
    background:linear-gradient(180deg, rgba(15,23,42,.90), rgba(17,24,39,.86));
    border-color:rgba(255,255,255,.08);
    box-shadow:0 14px 28px rgba(0,0,0,.20);
  }

  .fx-tile__ico{
    width:42px;
    height:42px;
    border-radius:14px;
    display:grid;
    place-items:center;
    background:linear-gradient(135deg, rgba(37,99,235,.14), rgba(56,189,248,.12));
    color:#0f5eff;
  }

  .fx-tile__ico svg{
    width:20px;
    height:20px;
  }

  .fx-tile__title{
    margin-top:14px;
    font-size:14px;
    font-weight:900;
    letter-spacing:-.02em;
  }

  .fx-tile__sub{
    margin-top:6px;
    font-size:12px;
    line-height:1.45;
    color:var(--muted,#64748b);
  }

  .fx-lock{
    position:absolute;
    top:12px;
    right:12px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    width:28px;
    height:28px;
    border-radius:999px;
    background:rgba(15,23,42,.08);
    color:#0f172a;
  }

  html[data-theme="dark"] .fx-lock{
    background:rgba(255,255,255,.08);
    color:#fff;
  }

  .fx-lock svg{
    width:14px;
    height:14px;
  }

  .fx-proveil{
    position:relative;
    overflow:hidden;
  }

  .fx-proveil::after{
    content:"";
    position:absolute;
    inset:0;
    backdrop-filter:blur(2px);
    -webkit-backdrop-filter:blur(2px);
    background:linear-gradient(180deg, rgba(255,255,255,.16), rgba(255,255,255,.32));
    pointer-events:none;
  }

  html[data-theme="dark"] .fx-proveil::after{
    background:linear-gradient(180deg, rgba(15,23,42,.18), rgba(15,23,42,.36));
  }

  .fx-panel{
    border-radius:18px;
    border:1px solid rgba(37,99,235,.10);
    background:rgba(255,255,255,.68);
    padding:14px;
  }

  html[data-theme="dark"] .fx-panel{
    background:rgba(255,255,255,.04);
    border-color:rgba(255,255,255,.08);
  }

  .fx-filters{
    display:grid;
    grid-template-columns:minmax(0,1.4fr) minmax(180px,220px) minmax(180px,220px) auto;
    gap:10px;
    align-items:end;
  }

  @media (max-width: 1100px){
    .fx-filters{
      grid-template-columns:1fr 1fr;
    }
  }

  @media (max-width: 640px){
    .fx-filters{
      grid-template-columns:1fr;
    }
  }

  .fx-label{
    display:block;
    margin-bottom:6px;
    font-size:11px;
    font-weight:900;
    text-transform:uppercase;
    letter-spacing:.10em;
    color:var(--muted,#64748b);
  }

  .fx-in{
    width:100%;
    min-width:0;
    min-height:42px;
    padding:10px 12px;
    border-radius:12px;
    border:1px solid rgba(37,99,235,.10);
    background:rgba(255,255,255,.84);
    color:inherit;
  }

  html[data-theme="dark"] .fx-in{
    background:rgba(255,255,255,.06);
    border-color:rgba(255,255,255,.08);
    color:#e5e7eb;
  }

  .fx-tableWrap{
    overflow:auto;
    border-radius:18px;
    border:1px solid rgba(37,99,235,.10);
  }

  .fx-table{
    width:100%;
    min-width:860px;
    border-collapse:collapse;
  }

  .fx-table th,
  .fx-table td{
    padding:12px 14px;
    border-bottom:1px solid rgba(37,99,235,.08);
    text-align:left;
    vertical-align:middle;
  }

  .fx-table th{
    font-size:11px;
    font-weight:900;
    text-transform:uppercase;
    letter-spacing:.12em;
    color:var(--muted,#64748b);
    background:rgba(37,99,235,.04);
    white-space:nowrap;
  }

  .fx-right{ text-align:right; }

  .fx-uuid{
    display:block;
    margin-top:4px;
    font-size:11px;
    color:var(--muted,#64748b);
  }

  .fx-status{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:28px;
    padding:0 10px;
    border-radius:999px;
    font-size:11px;
    font-weight:900;
    letter-spacing:.08em;
  }

  .fx-status--draft{
    background:#fff0f3;
    color:#be123c;
    border:1px solid #f3d5dc;
  }

  .fx-status--ok{
    background:#ecfdf5;
    color:#047857;
    border:1px solid #86efac;
  }

  .fx-status--cancel{
    background:#fef2f2;
    color:#b91c1c;
    border:1px solid #fecaca;
  }

  .fx-rowactions{
    display:flex;
    align-items:center;
    justify-content:flex-end;
    gap:8px;
    flex-wrap:wrap;
  }

  .fx-iconlink{
    width:36px;
    height:36px;
    border-radius:12px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    text-decoration:none;
    border:1px solid rgba(37,99,235,.10);
    background:rgba(255,255,255,.82);
    color:#0f172a;
    box-shadow:0 8px 18px rgba(15,23,42,.04);
  }

  .fx-iconlink svg{
    width:16px;
    height:16px;
  }

  html[data-theme="dark"] .fx-iconlink{
    background:rgba(255,255,255,.06);
    color:#e5e7eb;
    border-color:rgba(255,255,255,.08);
  }

  .fx-empty{
    padding:18px;
    text-align:center;
    color:var(--muted,#64748b);
  }

  .fx-note{
    display:flex;
    align-items:flex-start;
    gap:10px;
    border-radius:16px;
    padding:12px 14px;
    border:1px solid rgba(37,99,235,.10);
    background:rgba(37,99,235,.05);
    color:#0f172a;
  }

  .fx-note svg{
    width:18px;
    height:18px;
    flex:0 0 auto;
    margin-top:2px;
  }

  html[data-theme="dark"] .fx-note{
    background:rgba(96,165,250,.08);
    color:#e5e7eb;
    border-color:rgba(96,165,250,.12);
  }

  .fx-flash{
    border-radius:16px;
    padding:14px 16px;
    font-weight:800;
  }

  .fx-flash--ok{
    background:#ecfdf5;
    border:1px solid #86efac;
    color:#047857;
  }

  .fx-flash--err{
    background:#fef2f2;
    border:1px solid #fecaca;
    color:#b91c1c;
  }
</style>
@endpush

@section('content')
@php
  $k = $kpis ?? [
    'total_periodo' => 0,
    'emitidos'      => 0,
    'cancelados'    => 0,
    'delta_total'   => 0,
    'period'        => [],
    'prev_period'   => [],
  ];

  $f = $filters ?? [];
  $q      = $f['q']      ?? request('q');
  $status = $f['status'] ?? request('status');
  $month  = $f['month']  ?? request('month');
  $mes    = $f['mes']    ?? request('mes');
  $anio   = $f['anio']   ?? request('anio');

  $sum        = is_array($summary ?? null) ? $summary : [];
  $sumPlan    = strtoupper((string)($sum['plan'] ?? $plan ?? 'FREE'));
  $sumCycle   = (string)($sum['cycle'] ?? '');
  $sumIsPro   = (bool)($sum['is_pro'] ?? in_array(strtolower($sumPlan), ['pro','pro_mensual','pro_anual','premium','premium_mensual','premium_anual','empresa','business'], true));
  $sumTimbres = (int)($sum['timbres'] ?? 0);
  $sumEstado  = (string)($sum['estado'] ?? 'activa');
  $sumBlocked = (bool)($sum['blocked'] ?? false);

  $licAmount = (int)(
      data_get($sum, 'billing.amount_mxn')
      ?? data_get($sum, 'amount_mxn')
      ?? data_get($sum, 'license.amount_mxn')
      ?? 0
  );

  $rtCreate      = Route::has('cliente.facturacion.create') ? route('cliente.facturacion.create') : '#';
  $rtExport      = Route::has('cliente.facturacion.export')
                    ? route('cliente.facturacion.export', array_filter([
                        'q'      => $q,
                        'status' => $status,
                        'month'  => $month,
                        'mes'    => $mes,
                        'anio'   => $anio,
                      ]))
                    : '#';
  $rtIndex       = Route::has('cliente.facturacion.index') ? route('cliente.facturacion.index') : '#';
  $rtEmisores    = Route::has('cliente.emisores.index') ? route('cliente.emisores.index') : null;
  $rtReceptores  = Route::has('cliente.receptores.index') ? route('cliente.receptores.index') : null;
  $rtProductos   = Route::has('cliente.productos.index') ? route('cliente.productos.index') : null;
  $rtNomina      = Route::has('cliente.nomina.index') ? route('cliente.nomina.index') : null;
@endphp

<div class="fx-page">
  <section class="fx-hero">
    <div class="fx-hero__grid">
      <div>
        <span class="fx-eyebrow">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="M4 7h16M4 12h16M4 17h10"/>
          </svg>
          CFDI · FACTURACIÓN 360
        </span>

        <h1 class="fx-title">Facturación</h1>

        <p class="fx-sub">
          Centro operativo para emitir, consultar y escalar CFDI dentro de Pactopia360.
          Lo masivo, plantillas y operaciones por lote quedan gobernadas por plan PRO.
        </p>

        <div class="fx-chiprow">
          <span class="fx-chip">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="M12 2v20M2 12h20"/>
            </svg>
            Manual
          </span>

          <span class="fx-chip">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="M3 7h18M6 3h12v18H6z"/>
            </svg>
            CFDI
          </span>

          <span class="fx-chip">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="M4 4h16v16H4z"/>
              <path d="M8 8h8M8 12h8M8 16h5"/>
            </svg>
            Excel PRO
          </span>

          <span class="fx-chip">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="M12 1v6M12 17v6M4.22 4.22l4.24 4.24M15.54 15.54l4.24 4.24M1 12h6M17 12h6M4.22 19.78l4.24-4.24M15.54 8.46l4.24-4.24"/>
            </svg>
            SAT Ready
          </span>
        </div>
      </div>

      <aside class="fx-side">
        <div class="fx-plan">
          <div class="fx-plan__top">
            <div>
              <div class="fx-eyebrow">PLAN ACTUAL</div>
              <div class="fx-plan__amount">
                {{ $sumPlan ?: 'FREE' }}
              </div>
            </div>
            <span class="fx-badge {{ $sumIsPro ? 'fx-badge--pro' : '' }}">
              {{ $sumIsPro ? 'PRO' : 'FREE' }}
            </span>
          </div>

          <div class="fx-plan__mini">
            <div class="fx-mini">
              <div class="fx-mini__k">Timbres</div>
              <div class="fx-mini__v">{{ number_format($sumTimbres) }}</div>
              <div class="fx-mini__s">Disponibles</div>
            </div>
            <div class="fx-mini">
              <div class="fx-mini__k">Estado</div>
              <div class="fx-mini__v">{{ strtoupper($sumEstado ?: 'ACTIVA') }}</div>
              <div class="fx-mini__s">{{ $sumBlocked ? 'Bloqueada' : 'Operativa' }}</div>
            </div>
          </div>

          <div class="fx-actions">
            <a href="{{ $rtCreate }}" class="fx-btn fx-btn--primary" title="Nuevo CFDI">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M12 5v14M5 12h14"/>
              </svg>
              Nuevo
            </a>

            <a href="{{ $rtExport }}" class="fx-btn fx-btn--ghost" title="Exportar">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M12 3v12M7 10l5 5 5-5"/>
                <path d="M5 21h14"/>
              </svg>
              Exportar
            </a>
          </div>
        </div>
      </aside>
    </div>
  </section>

  @if(session('ok'))
    <div class="fx-flash fx-flash--ok">{{ session('ok') }}</div>
  @endif

  @if($errors->any())
    <div class="fx-flash fx-flash--err">{{ $errors->first() }}</div>
  @endif

  <section class="fx-kpis">
    <article class="fx-kpi">
      <div class="fx-kpi__label">Total periodo</div>
      <div class="fx-kpi__value">${{ number_format((float)($k['total_periodo'] ?? 0), 2) }}</div>
      <div class="fx-kpi__sub">
        @if(isset($k['period']['from'],$k['period']['to']))
          {{ \Carbon\Carbon::parse($k['period']['from'])->format('d/m') }} —
          {{ \Carbon\Carbon::parse($k['period']['to'])->format('d/m/Y') }}
        @else
          Periodo actual
        @endif
      </div>
    </article>

    <article class="fx-kpi">
      <div class="fx-kpi__label">Emitidos</div>
      <div class="fx-kpi__value">${{ number_format((float)($k['emitidos'] ?? 0), 2) }}</div>
      <div class="fx-kpi__sub">Timbrados</div>
    </article>

    <article class="fx-kpi">
      <div class="fx-kpi__label">Cancelados</div>
      <div class="fx-kpi__value">${{ number_format((float)($k['cancelados'] ?? 0), 2) }}</div>
      <div class="fx-kpi__sub">Periodo</div>
    </article>

    <article class="fx-kpi">
      @php $d = (float)($k['delta_total'] ?? 0); @endphp
      <div class="fx-kpi__label">Variación</div>
      <div class="fx-kpi__value" style="color:{{ $d >= 0 ? '#16a34a' : '#b91c1c' }}">
        {{ $d >= 0 ? '▲' : '▼' }} {{ number_format($d, 2) }}%
      </div>
      <div class="fx-kpi__sub">Vs periodo previo</div>
    </article>
  </section>

  <section class="fx-acc">
    <details class="fx-pane" open>
      <summary>
        <div class="fx-pane__left">
          <div class="fx-pane__ico">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="M12 5v14M5 12h14"/>
            </svg>
          </div>
          <div>
            <div class="fx-pane__title">Emisión</div>
            <div class="fx-pane__sub">CFDI manual, catálogos y accesos operativos</div>
          </div>
        </div>
        <div class="fx-pane__right">
          <span class="fx-badge {{ $sumIsPro ? 'fx-badge--pro' : '' }}">{{ $sumIsPro ? 'PRO' : 'BASE' }}</span>
          <span class="fx-pane__toggle">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="M6 9l6 6 6-6"/>
            </svg>
          </span>
        </div>
      </summary>

      <div class="fx-pane__body">
        <div class="fx-tilegrid">
          <a href="{{ $rtCreate }}" class="fx-tile">
            <div class="fx-tile__ico">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M12 5v14M5 12h14"/>
              </svg>
            </div>
            <div class="fx-tile__title">Nuevo CFDI</div>
            <div class="fx-tile__sub">Alta manual rápida para emisión puntual.</div>
          </a>

          @if($rtEmisores)
            <a href="{{ $rtEmisores }}" class="fx-tile">
              <div class="fx-tile__ico">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                  <path d="M3 21h18"/>
                  <path d="M5 21V7l7-4 7 4v14"/>
                </svg>
              </div>
              <div class="fx-tile__title">Emisores</div>
              <div class="fx-tile__sub">Gestión visual de emisores disponibles.</div>
            </a>
          @endif

          @if($rtReceptores)
            <a href="{{ $rtReceptores }}" class="fx-tile">
              <div class="fx-tile__ico">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                  <path d="M20 21a8 8 0 0 0-16 0"/>
                  <circle cx="12" cy="7" r="4"/>
                </svg>
              </div>
              <div class="fx-tile__title">Receptores</div>
              <div class="fx-tile__sub">Clientes, RFC y datos fiscales reutilizables.</div>
            </a>
          @endif

          @if($rtProductos)
            <a href="{{ $rtProductos }}" class="fx-tile">
              <div class="fx-tile__ico">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                  <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                </svg>
              </div>
              <div class="fx-tile__title">Conceptos</div>
              <div class="fx-tile__sub">Productos y servicios listos para facturar.</div>
            </a>
          @endif
        </div>
      </div>
    </details>

    <details class="fx-pane" open>
      <summary>
        <div class="fx-pane__left">
          <div class="fx-pane__ico">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <rect x="3" y="4" width="18" height="16" rx="2"/>
              <path d="M7 8h10M7 12h10M7 16h5"/>
            </svg>
          </div>
          <div>
            <div class="fx-pane__title">Emitidos</div>
            <div class="fx-pane__sub">Consulta, filtros y acciones rápidas sobre CFDI</div>
          </div>
        </div>
        <div class="fx-pane__right">
          <span class="fx-pane__toggle">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="M6 9l6 6 6-6"/>
            </svg>
          </span>
        </div>
      </summary>

      <div class="fx-pane__body">
        <form method="GET" action="{{ $rtIndex }}" class="fx-panel">
          <div class="fx-filters">
            <div>
              <label class="fx-label">Buscar</label>
              <input class="fx-in" type="text" name="q" value="{{ $q }}" placeholder="UUID / Serie / Folio">
            </div>

            <div>
              <label class="fx-label">Estatus</label>
              <select class="fx-in" name="status">
                @php $st = [''=>'Todos','borrador'=>'Borrador','emitido'=>'Emitido','cancelado'=>'Cancelado']; @endphp
                @foreach($st as $sv => $sl)
                  <option value="{{ $sv }}" @selected($status === $sv)>{{ $sl }}</option>
                @endforeach
              </select>
            </div>

            <div>
              <label class="fx-label">Mes</label>
              <input class="fx-in" type="month" name="month" value="{{ $month }}">
            </div>

            <div style="display:flex;gap:10px;flex-wrap:wrap;">
              <button class="fx-btn" type="submit" title="Aplicar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                  <path d="M4 6h16M7 12h10M10 18h4"/>
                </svg>
                Aplicar
              </button>

              <a class="fx-btn" href="{{ $rtIndex }}" title="Limpiar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                  <path d="M3 6h18"/>
                  <path d="M8 6V4h8v2"/>
                  <path d="M19 6l-1 14H6L5 6"/>
                </svg>
                Limpiar
              </a>

              @if(Route::has('cliente.facturacion.export'))
                <a class="fx-btn" href="{{ $rtExport }}" title="Exportar CSV">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M12 3v12M7 10l5 5 5-5"/>
                    <path d="M5 21h14"/>
                  </svg>
                  CSV
                </a>
              @endif
            </div>
          </div>
        </form>

        <div class="fx-tableWrap">
          <table class="fx-table">
            <thead>
              <tr>
                <th>Serie/Folio</th>
                <th>Fecha</th>
                <th>Emisor</th>
                <th>Estatus</th>
                <th class="fx-right">Total</th>
                <th class="fx-right">Acciones</th>
              </tr>
            </thead>
            <tbody>
              @forelse($cfdis as $row)
                @php
                  $serieFolio = trim(($row->serie ? ($row->serie.'-') : '').($row->folio ?? ''), '- ') ?: '—';
                  $st = strtolower((string)($row->estatus ?? ''));
                @endphp
                <tr>
                  <td>
                    <strong>{{ $serieFolio }}</strong>
                    <span class="fx-uuid">{{ $row->uuid ?: '—' }}</span>
                  </td>
                  <td>{{ optional($row->fecha)->format('Y-m-d H:i') ?: '—' }}</td>
                  <td>{{ optional($row->cliente)->razon_social ?? optional($row->cliente)->nombre_comercial ?? '—' }}</td>
                  <td>
                    @if($st === 'emitido')
                      <span class="fx-status fx-status--ok">EMITIDO</span>
                    @elseif($st === 'cancelado')
                      <span class="fx-status fx-status--cancel">CANCELADO</span>
                    @else
                      <span class="fx-status fx-status--draft">BORRADOR</span>
                    @endif
                  </td>
                  <td class="fx-right">${{ number_format((float)($row->total ?? 0), 2) }}</td>
                  <td class="fx-right">
                    <div class="fx-rowactions">
                      @if(Route::has('cliente.facturacion.show'))
                        <a class="fx-iconlink" href="{{ route('cliente.facturacion.show', $row->id) }}" title="Ver">
                          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/>
                            <circle cx="12" cy="12" r="3"/>
                          </svg>
                        </a>
                      @endif

                      @if($st === 'borrador' && Route::has('cliente.facturacion.edit'))
                        <a class="fx-iconlink" href="{{ route('cliente.facturacion.edit', $row->id) }}" title="Editar">
                          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M12 20h9"/>
                            <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/>
                          </svg>
                        </a>
                      @endif
                    </div>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="6" class="fx-empty">No hay CFDI en el periodo seleccionado.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        <div>
          {{ $cfdis->onEachSide(1)->links() }}
        </div>
      </div>
    </details>

    <details class="fx-pane" open>
      <summary>
        <div class="fx-pane__left">
          <div class="fx-pane__ico">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="M4 4h16v16H4z"/>
              <path d="M9 4v16M4 9h16"/>
            </svg>
          </div>
          <div>
            <div class="fx-pane__title">Masivo y plantillas</div>
            <div class="fx-pane__sub">Operación por Excel, lotes y reuso de layouts</div>
          </div>
        </div>
        <div class="fx-pane__right">
          <span class="fx-badge {{ $sumIsPro ? 'fx-badge--pro' : '' }}">{{ $sumIsPro ? 'PRO' : 'LOCKED' }}</span>
          <span class="fx-pane__toggle">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="M6 9l6 6 6-6"/>
            </svg>
          </span>
        </div>
      </summary>

      <div class="fx-pane__body">
        <div class="fx-note">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <circle cx="12" cy="12" r="10"/>
            <path d="M12 8h.01M11 12h1v4h1"/>
          </svg>
          <div>
            En Pactopia360, toda la operación CFDI masiva, importación por Excel, plantillas y procesos en lote
            debe quedar habilitada únicamente para cuentas PRO.
          </div>
        </div>

        <div class="fx-tilegrid">
          <div class="fx-tile {{ $sumIsPro ? '' : 'fx-proveil' }}">
            @unless($sumIsPro)
              <span class="fx-lock" title="Solo PRO">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                  <rect x="5" y="11" width="14" height="10" rx="2"/>
                  <path d="M8 11V8a4 4 0 1 1 8 0v3"/>
                </svg>
              </span>
            @endunless
            <div class="fx-tile__ico">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M4 4h16v16H4z"/>
                <path d="M8 8h8M8 12h8M8 16h5"/>
              </svg>
            </div>
            <div class="fx-tile__title">Importar Excel</div>
            <div class="fx-tile__sub">Carga masiva de CFDI desde layout controlado.</div>
          </div>

          <div class="fx-tile {{ $sumIsPro ? '' : 'fx-proveil' }}">
            @unless($sumIsPro)
              <span class="fx-lock" title="Solo PRO">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                  <rect x="5" y="11" width="14" height="10" rx="2"/>
                  <path d="M8 11V8a4 4 0 1 1 8 0v3"/>
                </svg>
              </span>
            @endunless
            <div class="fx-tile__ico">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M6 3h9l5 5v13H6z"/>
                <path d="M14 3v5h5"/>
              </svg>
            </div>
            <div class="fx-tile__title">Plantillas</div>
            <div class="fx-tile__sub">Mapeo reusable por cliente y por proceso.</div>
          </div>

          <div class="fx-tile {{ $sumIsPro ? '' : 'fx-proveil' }}">
            @unless($sumIsPro)
              <span class="fx-lock" title="Solo PRO">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                  <rect x="5" y="11" width="14" height="10" rx="2"/>
                  <path d="M8 11V8a4 4 0 1 1 8 0v3"/>
                </svg>
              </span>
            @endunless
            <div class="fx-tile__ico">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M3 12h18"/>
                <path d="M12 3v18"/>
              </svg>
            </div>
            <div class="fx-tile__title">Lotes</div>
            <div class="fx-tile__sub">Timbrado por volumen con control de errores.</div>
          </div>

          <div class="fx-tile {{ $sumIsPro ? '' : 'fx-proveil' }}">
            @unless($sumIsPro)
              <span class="fx-lock" title="Solo PRO">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                  <rect x="5" y="11" width="14" height="10" rx="2"/>
                  <path d="M8 11V8a4 4 0 1 1 8 0v3"/>
                </svg>
              </span>
            @endunless
            <div class="fx-tile__ico">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M12 20V10"/>
                <path d="M18 20V4"/>
                <path d="M6 20v-6"/>
              </svg>
            </div>
            <div class="fx-tile__title">Nómina / CFDI masivo</div>
            <div class="fx-tile__sub">Escalable para RH y emisión de alto volumen.</div>
          </div>
        </div>
      </div>
    </details>

    @if($rtNomina)
      <details class="fx-pane">
        <summary>
          <div class="fx-pane__left">
            <div class="fx-pane__ico">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M16 11V7a4 4 0 1 0-8 0v4"/>
                <rect x="4" y="11" width="16" height="10" rx="2"/>
              </svg>
            </div>
            <div>
              <div class="fx-pane__title">Nómina</div>
              <div class="fx-pane__sub">Integración con RH para CFDI de nómina</div>
            </div>
          </div>
          <div class="fx-pane__right">
            <span class="fx-badge {{ $sumIsPro ? 'fx-badge--pro' : '' }}">{{ $sumIsPro ? 'PRO' : 'BASE' }}</span>
            <span class="fx-pane__toggle">
              <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M6 9l6 6 6-6"/>
              </svg>
            </span>
          </div>
        </summary>

        <div class="fx-pane__body">
          <a href="{{ $rtNomina }}" class="fx-btn fx-btn--primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="M16 11V7a4 4 0 1 0-8 0v4"/>
              <rect x="4" y="11" width="16" height="10" rx="2"/>
            </svg>
            Abrir Nómina
          </a>
        </div>
      </details>
    @endif
  </section>
</div>
@endsection