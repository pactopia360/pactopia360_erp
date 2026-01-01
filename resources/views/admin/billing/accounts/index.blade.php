{{-- resources/views/admin/billing/accounts/index.blade.php
     P360 Admin Billing · Accounts v5.3
     FIX: ruta HUB mal referenciada (admin.billing.statements_hub -> admin.billing.statements_hub.index)
     + Modal fallback cuando iframe queda en blanco por redirect/CSP/X-Frame
     FULL SCREEN + KPIs + Bulk action bar + Column prefs + Sort + Highlight
--}}
@extends('layouts.admin')

@section('title', 'Facturación · Cuentas')
@section('pageClass', 'p360-billing-accounts')

@php
  $q         = $q ?? request('q','');
  $periodNow = now()->format('Y-m');

  $conn_used = $conn_used ?? (string)(config('p360.conn.clients') ?: 'mysql_clientes');
  $has_meta  = (bool)($has_meta ?? true);
  $meta_col  = (string)($meta_col ?? 'meta');

  $filter = (string)request('filter','all'); // all|blocked|no_license|override
@endphp

@push('styles')
<style>
  /* ======================================================================
     P360 Billing Accounts (v5.3)
     ====================================================================== */

  body.p360-billing-accounts :where(
    .container, .container-fluid,
    .container-sm, .container-md, .container-lg, .container-xl, .container-xxl,
    .content, .content-wrapper, .content-body, .content-inner,
    .page-content, .page-container, .page-body, .page-wrapper,
    .app-content, .app-content-inner,
    .main, .main-content, .main-panel, .main-wrapper,
    .layout-content, .layout-content-body,
    .card, .card-body
  ){ max-width:none !important; }

  body.p360-billing-accounts :where(
    .container, .container-fluid,
    .container-sm, .container-md, .container-lg, .container-xl, .container-xxl,
    .content, .content-wrapper, .content-body, .content-inner,
    .page-content, .page-container, .page-body, .page-wrapper,
    .app-content, .app-content-inner,
    .main, .main-content, .main-panel, .main-wrapper,
    .layout-content, .layout-content-body
  ){
    width:100% !important;
    margin-left:0 !important;
    margin-right:0 !important;
  }

  body.p360-billing-accounts :where(
    .container, .container-fluid,
    .content, .content-wrapper, .content-body, .content-inner,
    .page-content, .page-container, .page-body, .page-wrapper,
    .app-content, .app-content-inner,
    .main, .main-content, .main-panel, .main-wrapper,
    .layout-content, .layout-content-body
  ){
    padding-left:0 !important;
    padding-right:0 !important;
  }

  body.p360-billing-accounts :where(
    .content-wrapper, .page-wrapper, .page-content, .main-panel, .main-content, .app-content
  ){
    flex:1 1 auto !important;
    min-width:0 !important;
  }

  body.p360-billing-accounts .p360ba{
    --ink:#0f172a;
    --mut:#64748b;
    --mut2:#94a3b8;
    --bg:#f6f7fb;
    --card:#ffffff;
    --line:rgba(15,23,42,.10);
    --line2:rgba(15,23,42,.07);
    --shadow:0 18px 45px rgba(2,6,23,.10);
    --shadow2:0 10px 26px rgba(2,6,23,.08);
    --r:18px;

    width:100% !important;
    min-height:100vh;
    display:flex;
    flex-direction:column;
    background:var(--bg);
  }

  /* Top bar */
  body.p360-billing-accounts .p360ba-top{
    position:sticky; top:0; z-index:50;
    background:rgba(246,247,251,.82);
    backdrop-filter:blur(10px) saturate(140%);
    border-bottom:1px solid rgba(15,23,42,.08);
  }
  body.p360-billing-accounts .p360ba-top-inner{
    padding:14px 18px 10px;
    display:flex; align-items:flex-end; justify-content:space-between;
    gap:14px; flex-wrap:wrap;
  }
  body.p360-billing-accounts .p360ba-hgroup{ display:flex; flex-direction:column; gap:4px; min-width:280px; }
  body.p360-billing-accounts .p360ba-title{
    margin:0; font-size:18px; font-weight:1000; letter-spacing:-.02em;
    color:var(--ink); line-height:1.15;
  }
  body.p360-billing-accounts .p360ba-sub{
    font-size:12px; font-weight:850; color:var(--mut);
    display:flex; gap:10px; flex-wrap:wrap; align-items:center;
  }
  body.p360-billing-accounts .p360ba-sub .badge{
    display:inline-flex; align-items:center; gap:8px;
    padding:6px 10px; border-radius:999px;
    border:1px solid rgba(15,23,42,.10);
    background:rgba(255,255,255,.9);
    font-weight:950; color:var(--ink); white-space:nowrap;
  }

  body.p360-billing-accounts .p360ba-controls{
    display:flex; align-items:center; gap:10px; flex-wrap:wrap;
    justify-content:flex-end;
    min-width:min(980px, 100%);
  }
  body.p360-billing-accounts .p360ba-search{
    flex:1 1 520px;
    display:flex; align-items:center; gap:10px;
    padding:10px 12px;
    border-radius:16px;
    border:1px solid rgba(15,23,42,.10);
    background:rgba(255,255,255,.92);
    box-shadow:var(--shadow2);
    min-width:360px;
  }
  body.p360-billing-accounts .p360ba-search .icon{
    width:36px; height:36px;
    display:grid; place-items:center;
    border-radius:14px;
    background:#f1f5f9;
    border:1px solid rgba(15,23,42,.08);
    color:var(--ink);
    font-weight:1000;
    user-select:none;
  }
  body.p360-billing-accounts .p360ba-search input{
    flex:1 1 auto; width:100%;
    border:0; outline:0;
    background:transparent;
    font-weight:900; color:var(--ink);
    min-width:180px;
  }

  body.p360-billing-accounts .p360ba-btn{
    appearance:none;
    border:1px solid rgba(15,23,42,.12);
    background:#fff;
    color:var(--ink);
    font-weight:950;
    border-radius:14px;
    padding:10px 12px;
    cursor:pointer;
    text-decoration:none;
    display:inline-flex;
    align-items:center;
    gap:8px;
    user-select:none;
    white-space:nowrap;
  }
  body.p360-billing-accounts .p360ba-btn:hover{ filter:brightness(.98); }
  body.p360-billing-accounts .p360ba-btn:active{ transform:translateY(1px); }
  body.p360-billing-accounts .p360ba-btn.primary{ background:#0f172a; color:#fff; border-color:rgba(15,23,42,.22); }
  body.p360-billing-accounts .p360ba-btn.soft{ background:#f8fafc; }
  body.p360-billing-accounts .p360ba-btn.ghost{ background:transparent; }
  body.p360-billing-accounts .p360ba-kbd{
    font:11px/1 ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono","Courier New", monospace;
    background:#e2e8f0;
    border:1px solid rgba(15,23,42,.10);
    padding:5px 7px;
    border-radius:10px;
    color:#334155;
    font-weight:950;
  }

  /* Chips */
  body.p360-billing-accounts .p360ba-chips{ padding:0 18px 10px; display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
  body.p360-billing-accounts .p360ba-chip{
    display:inline-flex; align-items:center; gap:8px;
    padding:8px 10px; border-radius:999px;
    border:1px solid rgba(15,23,42,.10);
    background:rgba(255,255,255,.92);
    font-weight:950; font-size:12px;
    color:var(--ink);
    text-decoration:none;
  }
  body.p360-billing-accounts .p360ba-chip .dot{ width:8px; height:8px; border-radius:999px; background:#94a3b8; }
  body.p360-billing-accounts .p360ba-chip.is-active{ background:#0f172a; color:#fff; border-color:rgba(15,23,42,.22); }
  body.p360-billing-accounts .p360ba-chip.is-active .dot{ background:#fff; }

  /* KPIs */
  body.p360-billing-accounts .p360ba-kpis{
    padding:0 18px 12px;
    display:grid;
    grid-template-columns:repeat(4, minmax(180px, 1fr));
    gap:10px;
  }
  body.p360-billing-accounts .p360ba-kpi{
    background:rgba(255,255,255,.92);
    border:1px solid rgba(15,23,42,.10);
    border-radius:16px;
    padding:12px 12px;
    box-shadow:0 10px 24px rgba(2,6,23,.06);
    min-width:0;
  }
  body.p360-billing-accounts .p360ba-kpi .t{
    display:flex; align-items:center; justify-content:space-between;
    gap:10px; color:var(--mut); font-weight:950; font-size:12px; margin-bottom:6px;
  }
  body.p360-billing-accounts .p360ba-kpi .v{
    font-weight:1050; font-size:22px; letter-spacing:-.02em;
    color:var(--ink); line-height:1.1;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
  }
  body.p360-billing-accounts .p360ba-kpi .s{
    margin-top:6px; color:var(--mut); font-weight:850; font-size:12px;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
  }

  /* Alerts */
  body.p360-billing-accounts .p360ba-alerts{ padding:0 18px 12px; display:grid; gap:10px; }
  body.p360-billing-accounts .p360ba-alert{
    padding:10px 12px; border-radius:16px; border:1px solid;
    font-weight:900; font-size:12px; background:#fff;
  }
  body.p360-billing-accounts .p360ba-alert.ok{ border-color:#bbf7d0; background:#dcfce7; color:#166534; }
  body.p360-billing-accounts .p360ba-alert.err{ border-color:#fecaca; background:#fef2f2; color:#991b1b; }

  /* Body */
  body.p360-billing-accounts .p360ba-body{
    flex:1 1 auto;
    min-height:0;
    padding:0 18px 18px;
    display:flex;
    flex-direction:column;
    gap:12px;
  }
  body.p360-billing-accounts .p360ba-card{
    background:rgba(255,255,255,.98);
    border:1px solid rgba(15,23,42,.10);
    border-radius:var(--r);
    overflow:hidden;
    box-shadow:var(--shadow);
  }
  body.p360-billing-accounts .p360ba-tablewrap{
    width:100%;
    overflow:auto;
    max-height:calc(100vh - 320px);
  }
  body.p360-billing-accounts table.p360ba-table{
    width:100%;
    border-collapse:separate;
    border-spacing:0;
    min-width:1320px;
  }
  body.p360-billing-accounts table.p360ba-table thead th{
    position:sticky; top:0; z-index:2;
    background:rgba(255,255,255,.98);
    border-bottom:1px solid rgba(15,23,42,.08);
    padding:12px 12px;
    font-size:12px;
    font-weight:1000;
    color:var(--mut);
    text-align:left;
    white-space:nowrap;
    user-select:none;
  }
  body.p360-billing-accounts table.p360ba-table thead th.sortable{ cursor:pointer; }
  body.p360-billing-accounts table.p360ba-table thead th .sort{ display:inline-flex; align-items:center; gap:6px; }
  body.p360-billing-accounts table.p360ba-table thead th .car{
    font:11px/1 ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas,"Liberation Mono","Courier New", monospace;
    padding:4px 7px; border-radius:999px;
    border:1px solid rgba(15,23,42,.10);
    background:#f8fafc; color:#334155; font-weight:950;
  }
  body.p360-billing-accounts table.p360ba-table tbody td{
    border-bottom:1px solid rgba(15,23,42,.06);
    padding:12px 12px;
    vertical-align:middle;
    background:transparent;
  }
  body.p360-billing-accounts table.p360ba-table tbody tr:hover td{ background:#f8fafc; }

  body.p360-billing-accounts .p360ba-check{ width:18px; height:18px; accent-color:#0f172a; }
  body.p360-billing-accounts .p360ba-seq{ font-weight:1000; color:var(--ink); }
  body.p360-billing-accounts .p360ba-name{ font-weight:1000; color:var(--ink); line-height:1.15; }
  body.p360-billing-accounts .p360ba-meta{
    margin-top:4px;
    font-size:12px;
    font-weight:850;
    color:var(--mut);
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    align-items:center;
  }
  body.p360-billing-accounts .p360ba-mono{
    font:12px/1 ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas,"Liberation Mono","Courier New", monospace;
    font-weight:950;
    color:var(--ink);
  }
  body.p360-billing-accounts .p360ba-idchip{
    display:inline-flex; align-items:center; justify-content:center; gap:8px;
    padding:6px 12px; border-radius:999px;
    border:1px solid rgba(15,23,42,.10);
    background:#f8fafc;
    min-width:64px;
  }
  body.p360-billing-accounts .p360ba-pill{
    display:inline-flex; align-items:center; gap:6px;
    padding:6px 10px; border-radius:999px;
    font-size:12px; font-weight:1000;
    border:1px solid;
    white-space:nowrap;
  }
  body.p360-billing-accounts .p360ba-pill.ok{ background:#dcfce7; border-color:#bbf7d0; color:#166534; }
  body.p360-billing-accounts .p360ba-pill.bad{ background:#fee2e2; border-color:#fecaca; color:#991b1b; }
  body.p360-billing-accounts .p360ba-pill.info{ background:#e0f2fe; border-color:#bae6fd; color:#075985; }
  body.p360-billing-accounts .p360ba-pill.warn{ background:#fef3c7; border-color:#fde68a; color:#92400e; }
  body.p360-billing-accounts .p360ba-pill.neutral{ background:#f1f5f9; border-color:#e2e8f0; color:#0f172a; }
  body.p360-billing-accounts .p360ba-pill.custom{ background:#ede9fe; border-color:#ddd6fe; color:#5b21b6; }

  body.p360-billing-accounts .p360ba-actions{
    display:flex; justify-content:flex-end; align-items:center;
    gap:10px; white-space:nowrap;
  }
  body.p360-billing-accounts .p360ba-btn-sm{ padding:8px 10px; border-radius:12px; font-size:12px; }
  body.p360-billing-accounts .p360ba-btn-icon{ width:40px; justify-content:center; padding:8px 0; }

  /* Menu */
  body.p360-billing-accounts .p360ba-menu{ position:relative; display:inline-block; }
  body.p360-billing-accounts .p360ba-menu-pop{
    position:absolute; right:0; top:calc(100% + 8px);
    width:290px;
    background:#fff;
    border:1px solid rgba(15,23,42,.12);
    border-radius:16px;
    box-shadow:0 18px 60px rgba(2,6,23,.18);
    padding:6px;
    display:none;
    z-index:60;
  }
  body.p360-billing-accounts .p360ba-menu.is-open .p360ba-menu-pop{ display:block; }
  body.p360-billing-accounts .p360ba-menu-item{
    width:100%;
    border:0;
    background:transparent;
    text-align:left;
    padding:10px 10px;
    border-radius:14px;
    cursor:pointer;
    display:flex; align-items:center; justify-content:space-between;
    gap:10px;
    color:var(--ink);
    font-weight:950;
  }
  body.p360-billing-accounts .p360ba-menu-item:hover{ background:#f8fafc; }
  body.p360-billing-accounts .p360ba-menu-item .sub{ color:var(--mut); font-weight:850; font-size:12px; }
  body.p360-billing-accounts .p360ba-sep{ height:1px; background:rgba(15,23,42,.08); margin:6px 0; }

  /* Footer */
  body.p360-billing-accounts .p360ba-foot{ padding:0 2px; }

  /* Bulk bar */
  body.p360-billing-accounts .p360ba-bulk{ position:sticky; bottom:0; z-index:55; display:none; padding:12px 18px 14px; }
  body.p360-billing-accounts .p360ba-bulk.is-on{ display:block; }
  body.p360-billing-accounts .p360ba-bulk-inner{
    background:rgba(15,23,42,.92);
    color:#fff;
    border:1px solid rgba(255,255,255,.10);
    border-radius:18px;
    box-shadow:0 18px 60px rgba(2,6,23,.28);
    padding:10px 12px;
    display:flex; align-items:center; justify-content:space-between;
    gap:10px; flex-wrap:wrap;
  }
  body.p360-billing-accounts .p360ba-bulk-left{ display:flex; align-items:center; gap:10px; flex-wrap:wrap; min-width:0; }
  body.p360-billing-accounts .p360ba-bulk-count{ font-weight:1000; white-space:nowrap; }
  body.p360-billing-accounts .p360ba-bulk-actions{ display:flex; align-items:center; gap:10px; flex-wrap:wrap; justify-content:flex-end; }
  body.p360-billing-accounts .p360ba-btn-inv{
    appearance:none;
    border:1px solid rgba(255,255,255,.16);
    background:rgba(255,255,255,.08);
    color:#fff;
    font-weight:950;
    border-radius:14px;
    padding:9px 11px;
    cursor:pointer;
    text-decoration:none;
    display:inline-flex; align-items:center; gap:8px;
    user-select:none;
    white-space:nowrap;
  }
  body.p360-billing-accounts .p360ba-btn-inv.primary{
    background:#fff;
    color:#0f172a;
    border-color:rgba(255,255,255,.20);
  }

  /* Modal */
  body.p360-billing-accounts .p360ba-modal{ position:fixed; inset:0; display:none; z-index:3000; }
  body.p360-billing-accounts .p360ba-modal.is-open{ display:block; }
  body.p360-billing-accounts .p360ba-modal .backdrop{ position:absolute; inset:0; background:rgba(2,6,23,.60); backdrop-filter:blur(6px) saturate(120%); }
  body.p360-billing-accounts .p360ba-modal .dialog{
    position:relative;
    width:min(1520px, 98vw);
    height:min(920px, 96vh);
    margin:10px auto;
    background:#fff;
    border:1px solid rgba(15,23,42,.14);
    border-radius:18px;
    box-shadow:0 18px 70px rgba(2,6,23,.35);
    overflow:hidden;
    display:flex;
    flex-direction:column;
  }
  body.p360-billing-accounts .p360ba-modal .top{
    display:flex; align-items:center; justify-content:space-between;
    gap:10px; padding:10px 12px;
    border-bottom:1px solid rgba(15,23,42,.10);
    background:#fff;
  }
  body.p360-billing-accounts .p360ba-modal .lhs{ min-width:0; display:flex; align-items:center; gap:10px; }
  body.p360-billing-accounts .p360ba-modal .badge{
    display:inline-flex; align-items:center;
    padding:6px 10px; border-radius:999px;
    border:1px solid rgba(15,23,42,.10);
    background:#f8fafc;
    font-weight:1000;
    color:var(--ink);
    white-space:nowrap;
  }
  body.p360-billing-accounts .p360ba-modal .title{ min-width:0; display:flex; flex-direction:column; }
  body.p360-billing-accounts .p360ba-modal .title .h{ font-weight:1000; color:var(--ink); line-height:1.1; }
  body.p360-billing-accounts .p360ba-modal .title .p{
    font-weight:850; color:var(--mut);
    font-size:12px; margin-top:2px;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
    max-width:72vw;
  }
  body.p360-billing-accounts .p360ba-modal .rhs{ display:flex; align-items:center; gap:8px; flex-wrap:wrap; justify-content:flex-end; }
  body.p360-billing-accounts .p360ba-modal .body{ flex:1 1 auto; min-height:0; background:#fff; position:relative; }

  body.p360-billing-accounts .p360ba-modal iframe{ width:100%; height:100%; border:0; background:#fff; }

  /* Modal fallback (si iframe queda blanco por bloqueo) */
  body.p360-billing-accounts .p360ba-iframe-fallback{
    position:absolute; inset:0;
    display:none;
    align-items:center; justify-content:center;
    padding:18px;
    background:#fff;
    z-index:2;
  }
  body.p360-billing-accounts .p360ba-iframe-fallback.is-on{ display:flex; }
  body.p360-billing-accounts .p360ba-iframe-fallback .box{
    width:min(760px, 96%);
    border:1px solid rgba(15,23,42,.12);
    border-radius:18px;
    box-shadow:0 18px 70px rgba(2,6,23,.10);
    padding:14px 14px;
    background:#ffffff;
  }
  body.p360-billing-accounts .p360ba-iframe-fallback .h{
    font-weight:1050; color:#0f172a; letter-spacing:-.02em;
  }
  body.p360-billing-accounts .p360ba-iframe-fallback .p{
    margin-top:6px;
    color:#475569;
    font-weight:850;
    font-size:13px;
    line-height:1.35;
  }
  body.p360-billing-accounts .p360ba-iframe-fallback .mono{
    margin-top:10px;
    font:12px/1.35 ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas,"Liberation Mono","Courier New", monospace;
    background:#f8fafc;
    border:1px solid rgba(15,23,42,.10);
    border-radius:14px;
    padding:10px 10px;
    color:#0f172a;
    overflow:auto;
    max-height:200px;
  }

  /* Highlight */
  body.p360-billing-accounts mark.p360hl{
    padding:1px 3px;
    border-radius:6px;
    background:#fef08a;
    color:#111827;
    font-weight:1000;
  }

  /* Density */
  body.p360-billing-accounts .is-compact table.p360ba-table tbody td{ padding:10px 12px; }
  body.p360-billing-accounts .is-compact .p360ba-meta{ margin-top:3px; font-size:11.5px; }

  @media (max-width:1080px){
    body.p360-billing-accounts .p360ba-kpis{ grid-template-columns:repeat(2, minmax(180px, 1fr)); }
  }
  @media (max-width:860px){
    body.p360-billing-accounts .p360ba-controls{ min-width:100%; }
    body.p360-billing-accounts .p360ba-search{ min-width:100%; flex-basis:100%; }
    body.p360-billing-accounts .p360ba-kpis{ grid-template-columns:1fr; }
    body.p360-billing-accounts .p360ba-tablewrap{ max-height:calc(100vh - 420px); }
  }
</style>
@endpush

@section('content')
@php
  $baseUrl = route('admin.billing.accounts.index');
  $mk = function($f) use ($baseUrl, $q){
    $params = array_filter(['q'=>$q ?: null, 'filter'=>$f !== 'all' ? $f : null]);
    return $params ? ($baseUrl.'?'.http_build_query($params)) : $baseUrl;
  };

  $stats = $stats ?? null;

  $pageCount = is_countable($rows ?? null) ? count($rows) : 0;
  $kpiTotal   = is_array($stats) ? (int)($stats['total'] ?? $pageCount) : ($pageCount);
  $kpiBlocked = is_array($stats) ? (int)($stats['blocked'] ?? 0) : 0;
  $kpiNoLic   = is_array($stats) ? (int)($stats['no_license'] ?? 0) : 0;
  $kpiCustom  = is_array($stats) ? (int)($stats['override'] ?? 0) : 0;

  $kpiHint = is_array($stats)
    ? 'Total global del sistema'
    : 'Vista actual (página); para total global, pasa $stats desde el controller';
@endphp

<div class="p360ba" id="p360baRoot">
  <div class="p360ba-top">
    <div class="p360ba-top-inner">
      <div class="p360ba-hgroup">
        <h2 class="p360ba-title">Facturación · Cuentas</h2>
        <div class="p360ba-sub">
          <span class="badge">Periodo: <span class="p360ba-mono">{{ $periodNow }}</span></span>
          <span class="badge">Conn: <span class="p360ba-mono">{{ $conn_used }}</span></span>
          <span class="badge">Meta: <span class="p360ba-mono">{{ $has_meta ? 'OK' : 'NO' }}</span></span>
        </div>
      </div>

      <div class="p360ba-controls">
        <form method="GET" action="{{ route('admin.billing.accounts.index') }}" class="p360ba-search" role="search" aria-label="Buscar cuentas">
          <div class="icon">⌕</div>
          <input type="text" name="q" value="{{ $q }}" placeholder="Buscar (#, ID cliente, RFC, email, razón social, UUID)" autocomplete="off" id="p360Search">
          <input type="hidden" name="filter" value="{{ $filter }}">
          <button type="submit" class="p360ba-btn primary">
            Buscar <span class="p360ba-kbd" aria-hidden="true">Ctrl + K</span>
          </button>
          <a href="{{ route('admin.billing.accounts.index') }}" class="p360ba-btn ghost">Limpiar</a>
        </form>

        <button type="button" class="p360ba-btn soft" id="btnToggleDensity" title="Cambiar densidad (se guarda)">
          Densidad <span class="p360ba-kbd">D</span>
        </button>

        <button type="button" class="p360ba-btn soft" id="btnToggleCols" title="Columnas (se guarda)">
          Columnas <span class="p360ba-kbd">C</span>
        </button>

        <button type="button" class="p360ba-btn soft" id="btnExportCsv" title="Exportar lo visible (filtro actual)">
          Exportar CSV
        </button>
      </div>
    </div>

    <div class="p360ba-chips" aria-label="Filtros rápidos">
      <a class="p360ba-chip {{ $filter==='all'?'is-active':'' }}" href="{{ $mk('all') }}"><span class="dot"></span> Todas</a>
      <a class="p360ba-chip {{ $filter==='blocked'?'is-active':'' }}" href="{{ $mk('blocked') }}"><span class="dot"></span> Bloqueadas</a>
      <a class="p360ba-chip {{ $filter==='no_license'?'is-active':'' }}" href="{{ $mk('no_license') }}"><span class="dot"></span> Sin licencia</a>
      <a class="p360ba-chip {{ $filter==='override'?'is-active':'' }}" href="{{ $mk('override') }}"><span class="dot"></span> Con override</a>
    </div>

    <div class="p360ba-kpis" aria-label="KPIs">
      <div class="p360ba-kpi" title="{{ $kpiHint }}">
        <div class="t"><span>Cuentas</span><span class="p360ba-kbd">KPI</span></div>
        <div class="v">{{ number_format($kpiTotal) }}</div>
        <div class="s">{{ is_array($stats) ? 'Total global' : 'Página actual' }}</div>
      </div>
      <div class="p360ba-kpi">
        <div class="t"><span>Bloqueadas</span><span class="p360ba-kbd">B</span></div>
        <div class="v">{{ number_format($kpiBlocked) }}</div>
        <div class="s">Filtro: Bloqueadas</div>
      </div>
      <div class="p360ba-kpi">
        <div class="t"><span>Sin licencia</span><span class="p360ba-kbd">L</span></div>
        <div class="v">{{ number_format($kpiNoLic) }}</div>
        <div class="s">Filtro: Sin licencia</div>
      </div>
      <div class="p360ba-kpi">
        <div class="t"><span>Personalizadas</span><span class="p360ba-kbd">O</span></div>
        <div class="v">{{ number_format($kpiCustom) }}</div>
        <div class="s">Override / custom</div>
      </div>
    </div>

    <div class="p360ba-alerts">
      @if(session('ok'))
        <div class="p360ba-alert ok">{{ session('ok') }}</div>
      @endif

      @if($has_meta === false)
        <div class="p360ba-alert err">
          La columna <b>{{ $meta_col }}</b> no existe en <b>{{ $conn_used }}.accounts</b>.
          Se mostrará el listado, pero no se podrá administrar licencia/módulos hasta migrar.
        </div>
      @endif
    </div>
  </div>

  <div class="p360ba-body">
    <div class="p360ba-card">
      <div class="p360ba-tablewrap" aria-label="Listado de cuentas">
        <table class="p360ba-table" id="p360Table">
          <thead>
            <tr>
              <th style="width:44px;" data-col="sel">
                <input type="checkbox" class="p360ba-check" id="chkAll" aria-label="Seleccionar todas">
              </th>

              <th style="width:90px;" class="sortable" data-sort="seq" data-col="seq">
                <span class="sort"># <span class="car" id="carSeq">↕</span></span>
              </th>

              <th style="width:160px;" class="sortable" data-sort="client_id" data-col="client_id">
                <span class="sort">ID cliente <span class="car" id="carCid">↕</span></span>
              </th>

              <th class="sortable" data-sort="name" data-col="cliente">
                <span class="sort">Cliente <span class="car" id="carName">↕</span></span>
              </th>

              <th style="width:320px;" class="sortable" data-sort="email" data-col="email">
                <span class="sort">Email <span class="car" id="carEmail">↕</span></span>
              </th>

              <th style="width:320px;" data-col="plan">Plan</th>
              <th style="width:150px;" data-col="status">Estatus</th>
              <th style="text-align:right; width:360px;" data-col="actions">Acciones</th>
            </tr>
          </thead>

          <tbody id="p360Tbody">
            @forelse($rows as $i => $r)
              @php
                $seq = method_exists($rows,'firstItem') ? ($rows->firstItem() + $i) : ($i + 1);

                $get = function($obj, string $key, $default = null) {
                  if (is_array($obj)) return $obj[$key] ?? $default;
                  if (is_object($obj) && isset($obj->{$key})) return $obj->{$key};
                  return $default;
                };

                $emailRaw = (string) ($get($r, 'email', '') ?? '');
                $rfcRaw   = (string) ($get($r, 'rfc', '') ?? '');
                $idRaw    = (string) ($get($r, 'id', '') ?? '');

                $meta = $get($r, $meta_col, null);
                if (is_string($meta) && $meta !== '') {
                  $decoded = json_decode($meta, true);
                  if (json_last_error() === JSON_ERROR_NONE) $meta = $decoded;
                }
                if (is_object($meta)) $meta = (array) $meta;
                if (!is_array($meta)) $meta = [];

                $candidates = [
                  (string)($get($r,'razon_social','') ?? ''),
                  (string)($get($r,'nombre_comercial','') ?? ''),
                  (string)($get($r,'company_name','') ?? ''),
                  (string)($get($r,'business_name','') ?? ''),
                  (string)($get($r,'nombre','') ?? ''),
                  (string)($get($r,'name','') ?? ''),
                  (string)($get($r,'contact_name','') ?? ''),
                  (string)($get($r,'cliente','') ?? ''),
                  (string)($get($r,'client_name','') ?? ''),
                  (string)data_get($meta,'billing.customer_name',''),
                  (string)data_get($meta,'customer.name',''),
                  (string)data_get($meta,'profile.razon_social',''),
                  (string)data_get($meta,'profile.nombre_comercial',''),
                ];

                $clienteNombre = '';
                foreach($candidates as $cand){
                  $cand = trim((string)$cand);
                  if ($cand !== '') { $clienteNombre = $cand; break; }
                }

                if ($clienteNombre === '' && $emailRaw !== ''){
                  $local = explode('@', $emailRaw)[0] ?? '';
                  $local = str_replace(['.','_','-','+'], ' ', $local);
                  $local = trim(preg_replace('/\s+/', ' ', $local));
                  $clienteNombre = $local !== '' ? mb_convert_case($local, MB_CASE_TITLE, "UTF-8") : '';
                }
                if ($clienteNombre === '') $clienteNombre = 'Cliente';

                $rfc   = $rfcRaw !== '' ? $rfcRaw : '—';
                $email = $emailRaw !== '' ? $emailRaw : '—';

                $clientId =
                  $get($r,'client_id', null)
                  ?? $get($r,'id_cliente', null)
                  ?? $get($r,'cuenta', null)
                  ?? $get($r,'cuenta_id', null)
                  ?? $get($r,'account_no', null)
                  ?? $get($r,'numero_cliente', null)
                  ?? data_get($meta,'billing.client_id', null)
                  ?? data_get($meta,'billing.account_no', null)
                  ?? data_get($meta,'account.client_id', null);

                $clientIdNorm = is_numeric($clientId) ? (string)(int)$clientId : '';
                $isBlocked = (int)($get($r, 'is_blocked', 0) ?? 0) === 1;

                $idShort = $idRaw !== ''
                  ? (mb_strlen($idRaw) > 12 ? ('…'.mb_substr($idRaw, -12)) : $idRaw)
                  : '—';

                $displayClientId = $clientIdNorm !== '' ? $clientIdNorm : $idShort;

                $pk       = (string) data_get($meta, 'billing.price_key', '');
                $cycleKey = (string) data_get($meta, 'billing.billing_cycle', '');
                $baseAmt  = (int) data_get($meta, 'billing.amount_mxn', 0);

                $ovNew = data_get($meta, 'billing.override.amount_mxn');
                $ovOld = data_get($meta, 'billing.override_amount_mxn');
                $overrideAmt = is_numeric($ovNew) ? (int)$ovNew : (is_numeric($ovOld) ? (int)$ovOld : null);

                $isCustom = ($overrideAmt !== null);
                $currentAmt = $isCustom ? $overrideAmt : $baseAmt;

                $planLabel = '—';
                if ($pk !== '') {
                  $planLabel = strtoupper($pk);
                  if ($cycleKey !== '') {
                    $planLabel .= ' · ' . ($cycleKey === 'yearly' ? 'ANUAL' : ($cycleKey === 'monthly' ? 'MENSUAL' : strtoupper($cycleKey)));
                  }
                }

                $urlAdmin = \Illuminate\Support\Facades\Route::has('admin.billing.accounts.show') && $idRaw !== ''
                  ? route('admin.billing.accounts.show', ['id' => $idRaw, 'modal' => 1])
                  : '#';

                // ✅ FIX: la ruta real es admin.billing.statements_hub.index
                $urlState = '#';
                if (\Illuminate\Support\Facades\Route::has('admin.billing.statements_hub.index')) {
                  $urlState = route('admin.billing.statements_hub.index', [
                    'period' => $periodNow,
                    'q'      => $idRaw,
                  ]);
                  $urlState = $urlState . (str_contains($urlState,'?') ? '&' : '?') . 'modal=1';
                }
              @endphp

              <tr
                data-row
                data-id="{{ $idRaw }}"
                data-seq="{{ $seq }}"
                data-client-id="{{ e($displayClientId) }}"
                data-name="{{ e($clienteNombre) }}"
                data-rfc="{{ e($rfc) }}"
                data-email="{{ e($email) }}"
                data-url-admin="{{ $urlAdmin }}"
                data-url-state="{{ $urlState }}"
              >
                <td data-col="sel">
                  <input type="checkbox" class="p360ba-check chkRow" aria-label="Seleccionar {{ $clienteNombre }}">
                </td>

                <td class="p360ba-seq" data-col="seq">#{{ $seq }}</td>

                <td data-col="client_id">
                  <span class="p360ba-idchip" title="ID cliente: {{ $displayClientId }}">
                    <span class="p360ba-mono">{{ $displayClientId }}</span>
                  </span>
                </td>

                <td data-col="cliente">
                  <div class="p360ba-name p360txt" data-text="{{ e($clienteNombre) }}">{{ $clienteNombre }}</div>
                  <div class="p360ba-meta">
                    <span>RFC: <span class="p360ba-mono p360txt" data-text="{{ e($rfc) }}">{{ $rfc }}</span></span>
                  </div>
                </td>

                <td data-col="email">
                  <div class="p360ba-name p360txt" data-text="{{ e($email) }}">{{ $email }}</div>
                  <div class="p360ba-meta">
                    Cuenta: <span class="p360ba-mono">{{ $clientIdNorm !== '' ? $clientIdNorm : $idShort }}</span>
                  </div>
                </td>

                <td data-col="plan">
                  @if($pk !== '')
                    @if($isCustom)
                      <span class="p360ba-pill custom">PERSONALIZADO</span>
                    @else
                      <span class="p360ba-pill info p360txt" data-text="{{ e($planLabel) }}">{{ $planLabel }}</span>
                    @endif

                    <div class="p360ba-meta" style="margin-top:8px;">
                      <span>Base: <span class="p360ba-mono">${{ number_format($baseAmt, 0, '.', ',') }}</span></span>
                      <span>Paga: <span class="p360ba-mono">${{ number_format($currentAmt, 0, '.', ',') }}</span></span>
                    </div>
                  @else
                    <span class="p360ba-pill warn">SIN LICENCIA</span>
                    @if(!($has_meta ?? true))
                      <div class="p360ba-meta" style="margin-top:8px;">Meta deshabilitado</div>
                    @endif
                  @endif
                </td>

                <td data-col="status">
                  @if($isBlocked)
                    <span class="p360ba-pill bad">BLOQUEADO</span>
                  @else
                    <span class="p360ba-pill ok">ACTIVO</span>
                  @endif
                </td>

                <td style="text-align:right;" data-col="actions">
                  <div class="p360ba-actions">
                    <button
                      type="button"
                      class="p360ba-btn primary p360ba-btn-sm"
                      data-open-modal="admin"
                      data-url="{{ $urlAdmin }}"
                      data-title="Administrar · #{{ $seq }}"
                      data-subtitle="{{ e($clienteNombre) }} · RFC {{ e($rfc) }} · {{ e($email) }}"
                    >
                      Administrar
                    </button>

                    <button
                      type="button"
                      class="p360ba-btn p360ba-btn-sm"
                      data-open-modal="state"
                      data-url="{{ $urlState }}"
                      data-title="Estado de cuenta ({{ $periodNow }}) · #{{ $seq }}"
                      data-subtitle="{{ e($clienteNombre) }} · {{ e($email) }}"
                    >
                      Estado
                    </button>

                    <div class="p360ba-menu" data-menu>
                      <button type="button" class="p360ba-btn p360ba-btn-icon p360ba-btn-sm" aria-label="Más acciones" data-menu-btn>⋯</button>
                      <div class="p360ba-menu-pop" role="menu" aria-label="Acciones rápidas">
                        <button type="button" class="p360ba-menu-item" data-copy="seq">
                          Copiar consecutivo <span class="sub">#{{ $seq }}</span>
                        </button>
                        <button type="button" class="p360ba-menu-item" data-copy="client_id">
                          Copiar ID cliente <span class="sub">{{ $displayClientId }}</span>
                        </button>
                        <button type="button" class="p360ba-menu-item" data-copy="id_full">
                          Copiar UUID <span class="sub">{{ $idShort }}</span>
                        </button>
                        <button type="button" class="p360ba-menu-item" data-copy="email">
                          Copiar email <span class="sub">{{ $email }}</span>
                        </button>
                        <button type="button" class="p360ba-menu-item" data-copy="rfc">
                          Copiar RFC <span class="sub">{{ $rfc }}</span>
                        </button>

                        <div class="p360ba-sep"></div>

                        <button type="button" class="p360ba-menu-item" data-open-new="admin">
                          Abrir administrar en pestaña <span class="sub">Nueva</span>
                        </button>
                        <button type="button" class="p360ba-menu-item" data-open-new="state">
                          Abrir estado en pestaña <span class="sub">Nueva</span>
                        </button>
                      </div>
                    </div>
                  </div>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="8" style="padding:16px;color:#64748b;font-weight:900;">Sin resultados.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <div class="p360ba-foot">
      {{ method_exists($rows,'links') ? $rows->links() : '' }}
    </div>
  </div>

  <div class="p360ba-bulk" id="bulkBar" aria-hidden="true">
    <div class="p360ba-bulk-inner">
      <div class="p360ba-bulk-left">
        <span class="p360ba-bulk-count" id="bulkCount">0 seleccionadas</span>
        <span style="opacity:.85;font-weight:850;">Acciones rápidas sobre selección (UI)</span>
      </div>
      <div class="p360ba-bulk-actions">
        <button type="button" class="p360ba-btn-inv" id="bulkCopyIds">Copiar IDs</button>
        <button type="button" class="p360ba-btn-inv" id="bulkOpenAdmin">Abrir Administrar</button>
        <button type="button" class="p360ba-btn-inv" id="bulkOpenState">Abrir Estados</button>
        <button type="button" class="p360ba-btn-inv primary" id="bulkClear">Limpiar selección</button>
      </div>
    </div>
  </div>

  <div id="p360Modal" class="p360ba-modal" aria-hidden="true">
    <div class="backdrop" data-close></div>

    <div class="dialog" role="dialog" aria-modal="true" aria-label="Submódulo">
      <div class="top">
        <div class="lhs">
          <span class="badge" id="mBadge">P360</span>
          <div class="title">
            <div class="h" id="mTitle">—</div>
            <div class="p" id="mSub">—</div>
          </div>
        </div>
        <div class="rhs">
          <a href="#" id="mOpenNew" class="p360ba-btn p360ba-btn-sm" target="_blank" rel="noopener">Abrir en nueva pestaña</a>
          <button type="button" class="p360ba-btn p360ba-btn-sm" data-close>Cerrar <span class="p360ba-kbd">Esc</span></button>
        </div>
      </div>

      <div class="body">
        <div class="p360ba-iframe-fallback" id="mFallback" aria-hidden="true">
          <div class="box">
            <div class="h">No se pudo mostrar el contenido dentro del modal</div>
            <div class="p">
              Esto suele pasar por redirección a login (302), bloqueo por CSP/X-Frame, o un error interno (500).
              Usa “Abrir en nueva pestaña” para ver el error real y el mensaje completo.
            </div>
            <div class="mono" id="mFallbackUrl">—</div>
          </div>
        </div>

        <iframe id="mFrame" src="about:blank" title="Contenido"></iframe>
      </div>
    </div>
  </div>

  <div class="p360ba-modal" id="colsModal" aria-hidden="true" style="z-index:3200;">
    <div class="backdrop" data-cols-close></div>
    <div class="dialog" role="dialog" aria-modal="true" aria-label="Columnas" style="height:auto; width:min(760px, 96vw);">
      <div class="top">
        <div class="lhs">
          <span class="badge">Columnas</span>
          <div class="title">
            <div class="h">Mostrar / ocultar</div>
            <div class="p">Preferencias guardadas localmente</div>
          </div>
        </div>
        <div class="rhs">
          <button type="button" class="p360ba-btn p360ba-btn-sm" id="colsReset">Reset</button>
          <button type="button" class="p360ba-btn p360ba-btn-sm" data-cols-close>Cerrar</button>
        </div>
      </div>
      <div class="body" style="padding:12px;">
        <div style="display:grid; grid-template-columns:repeat(3, minmax(160px,1fr)); gap:10px;">
          @php
            $cols = [
              ['sel','Seleccionar'],
              ['seq','#'],
              ['client_id','ID cliente'],
              ['cliente','Cliente'],
              ['email','Email'],
              ['plan','Plan'],
              ['status','Estatus'],
              ['actions','Acciones'],
            ];
          @endphp
          @foreach($cols as [$key,$label])
            <label style="display:flex; align-items:center; gap:10px; padding:10px 12px; border:1px solid rgba(15,23,42,.10); border-radius:14px; background:#fff; font-weight:900;">
              <input type="checkbox" data-col-toggle="{{ $key }}" class="p360ba-check" checked>
              <span>{{ $label }}</span>
            </label>
          @endforeach
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function(){
  'use strict';

  const $  = (s, c=document) => c.querySelector(s);
  const $$ = (s, c=document) => Array.from(c.querySelectorAll(s));

  function safeUrl(u){
    try{ return new URL(u, location.origin).toString(); }catch(_){ return u; }
  }
  function urlWithModalParam(u){
    try{
      const x = new URL(u, location.origin);
      x.searchParams.set('modal','1');
      return x.toString();
    }catch(_){
      const glue = u.includes('?') ? '&' : '?';
      return u + glue + 'modal=1';
    }
  }
  async function copyText(val){
    try{ await navigator.clipboard.writeText(String(val||'')); }catch(_){}
  }
  function norm(s){ return String(s||'').toLowerCase().trim(); }
  function toNum(s){
    const x = parseFloat(String(s||'').replace(/[^\d.-]/g,''));
    return isNaN(x) ? null : x;
  }

  // Density
  const root = $('#p360baRoot');
  const densityKey = 'p360.ba.density';
  const btnDensity = $('#btnToggleDensity');

  function applyDensity(){
    const v = localStorage.getItem(densityKey) || 'normal';
    root.classList.toggle('is-compact', v === 'compact');
  }
  applyDensity();

  btnDensity && btnDensity.addEventListener('click', ()=>{
    const cur = localStorage.getItem(densityKey) || 'normal';
    localStorage.setItem(densityKey, cur === 'compact' ? 'normal' : 'compact');
    applyDensity();
  });

  document.addEventListener('keydown', (e)=>{
    if (e.key.toLowerCase() === 'd' && (e.ctrlKey || e.metaKey) === false && (e.altKey || e.shiftKey) === false){
      const a = document.activeElement;
      if (a && (a.tagName === 'INPUT' || a.tagName === 'TEXTAREA')) return;
      btnDensity && btnDensity.click();
    }
  });

  // Columns
  const colsKey = 'p360.ba.cols';
  const btnCols = $('#btnToggleCols');
  const colsModal = $('#colsModal');
  const colsReset = $('#colsReset');

  const defaultCols = ['sel','seq','client_id','cliente','email','plan','status','actions'];

  function getCols(){
    try{
      const raw = localStorage.getItem(colsKey);
      if (!raw) return defaultCols.slice();
      const arr = JSON.parse(raw);
      return Array.isArray(arr) && arr.length ? arr : defaultCols.slice();
    }catch(_){
      return defaultCols.slice();
    }
  }
  function setCols(arr){ localStorage.setItem(colsKey, JSON.stringify(arr)); }

  function applyCols(){
    const cols = new Set(getCols());
    $$('th[data-col], td[data-col]').forEach(el=>{
      const key = el.getAttribute('data-col');
      if (!key) return;
      el.style.display = cols.has(key) ? '' : 'none';
    });
    $$('[data-col-toggle]').forEach(chk=>{
      const k = chk.getAttribute('data-col-toggle');
      chk.checked = cols.has(k);
    });
  }
  applyCols();

  function openCols(){
    colsModal.classList.add('is-open');
    colsModal.setAttribute('aria-hidden','false');
    document.body.style.overflow = 'hidden';
    applyCols();
  }
  function closeCols(){
    colsModal.classList.remove('is-open');
    colsModal.setAttribute('aria-hidden','true');
    document.body.style.overflow = '';
  }

  btnCols && btnCols.addEventListener('click', openCols);
  colsModal && colsModal.addEventListener('click', (e)=>{
    if (e.target.closest('[data-cols-close]')) closeCols();
  });

  document.addEventListener('change', (e)=>{
    const chk = e.target.closest('[data-col-toggle]');
    if (!chk) return;
    const key = chk.getAttribute('data-col-toggle');
    const cols = new Set(getCols());
    if (chk.checked) cols.add(key); else cols.delete(key);
    cols.add('actions'); cols.add('seq');
    setCols(Array.from(cols));
    applyCols();
  });

  colsReset && colsReset.addEventListener('click', ()=>{
    setCols(defaultCols.slice());
    applyCols();
  });

  document.addEventListener('keydown', (e)=>{
    if ((e.ctrlKey || e.metaKey) && !e.shiftKey && !e.altKey && e.key.toLowerCase() === 'c'){
      const sel = window.getSelection && window.getSelection().toString();
      if (sel && sel.length > 0) return;
      e.preventDefault();
      openCols();
    }
  });

  // Menus
  function closeAllMenus(){ $$('[data-menu].is-open').forEach(m => m.classList.remove('is-open')); }

  document.addEventListener('click', (e)=>{
    const btn = e.target.closest('[data-menu-btn]');
    if (btn){
      const menu = btn.closest('[data-menu]');
      const open = menu.classList.contains('is-open');
      closeAllMenus();
      menu.classList.toggle('is-open', !open);
      e.preventDefault();
      return;
    }
    if (!e.target.closest('[data-menu]')) closeAllMenus();
  });

  document.addEventListener('click', async (e)=>{
    const item = e.target.closest('.p360ba-menu-item');
    if (!item) return;

    const tr = item.closest('tr[data-row]');
    if (!tr) return;

    const modeCopy = item.getAttribute('data-copy');
    const modeNew  = item.getAttribute('data-open-new');

    if (modeCopy){
      let val = '';
      if (modeCopy === 'seq') val = String(tr.dataset.seq || '');
      if (modeCopy === 'client_id') val = String(tr.dataset.clientId || '');
      if (modeCopy === 'id_full') val = String(tr.dataset.id || '');
      if (modeCopy === 'email') val = String(tr.dataset.email || '');
      if (modeCopy === 'rfc') val = String(tr.dataset.rfc || '');
      await copyText(val);
      closeAllMenus();
      return;
    }

    if (modeNew){
      const url = (modeNew === 'admin') ? tr.dataset.urlAdmin : tr.dataset.urlState;
      if (url && url !== '#') window.open(url, '_blank', 'noopener');
      closeAllMenus();
      return;
    }
  });

  // Modal
  const modal   = $('#p360Modal');
  const frame   = $('#mFrame');
  const titleEl = $('#mTitle');
  const subEl   = $('#mSub');
  const badgeEl = $('#mBadge');
  const openNew = $('#mOpenNew');

  const fbWrap  = $('#mFallback');
  const fbUrl   = $('#mFallbackUrl');

  let fbTimer = null;

  function showFallback(url){
    if (!fbWrap) return;
    fbUrl.textContent = String(url || '—');
    fbWrap.classList.add('is-on');
    fbWrap.setAttribute('aria-hidden','false');
  }
  function hideFallback(){
    if (!fbWrap) return;
    fbWrap.classList.remove('is-on');
    fbWrap.setAttribute('aria-hidden','true');
    fbUrl.textContent = '—';
  }

  function openModal({mode, url, title, subtitle}){
    const base = safeUrl(url || '');
    if (!base || base === '#') return;

    const finalUrl = urlWithModalParam(base);

    badgeEl.textContent = (mode === 'state') ? 'Estado de cuenta' : 'Administrar';
    titleEl.textContent = title || '—';
    subEl.textContent   = subtitle || '—';

    hideFallback();

    frame.src = finalUrl;
    openNew.href = finalUrl;

    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden','false');
    document.body.style.overflow = 'hidden';

    // Si después de X ms sigue “en blanco” (bloqueo típico), mostramos fallback.
    clearTimeout(fbTimer);
    fbTimer = setTimeout(()=> showFallback(finalUrl), 1400);
  }

  function closeModal(){
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden','true');
    document.body.style.overflow = '';
    frame.src = 'about:blank';
    hideFallback();
    clearTimeout(fbTimer);
  }

  document.addEventListener('click', (e)=>{
    const btn = e.target.closest('[data-open-modal]');
    if (!btn) return;

    openModal({
      mode: btn.getAttribute('data-open-modal'),
      url: btn.getAttribute('data-url'),
      title: btn.getAttribute('data-title'),
      subtitle: btn.getAttribute('data-subtitle'),
    });
    e.preventDefault();
  });

  if (modal){
    modal.addEventListener('click', (e)=>{
      const closer = e.target.closest('[data-close]');
      if (closer) closeModal();
    });
  }

  // Si el iframe sí cargó algo, ocultamos fallback
  frame && frame.addEventListener('load', ()=>{
    clearTimeout(fbTimer);
    // Si el servidor respondió HTML, normalmente ya se ve. Quitamos fallback.
    hideFallback();
  });

  // Ctrl+K focus search
  document.addEventListener('keydown', (e)=>{
    const ctrl = (e.ctrlKey || e.metaKey) && !e.shiftKey && !e.altKey;
    if (ctrl && e.key.toLowerCase() === 'k'){
      const inp = $('#p360Search');
      if (inp){ e.preventDefault(); inp.focus(); inp.select && inp.select(); }
    }
  });

  // Esc
  document.addEventListener('keydown', (e)=>{
    if (e.key === 'Escape'){
      if (modal && modal.classList.contains('is-open')) closeModal();
      if (colsModal && colsModal.classList.contains('is-open')) closeCols();
      closeAllMenus();
    }
  });

  // Bulk selection
  const chkAll = $('#chkAll');
  const bulkBar = $('#bulkBar');
  const bulkCount = $('#bulkCount');
  const bulkCopyIds = $('#bulkCopyIds');
  const bulkOpenAdmin = $('#bulkOpenAdmin');
  const bulkOpenState = $('#bulkOpenState');
  const bulkClear = $('#bulkClear');

  function getSelectedRows(){
    return $$('#p360Tbody tr[data-row]').filter(tr=>{
      const cb = tr.querySelector('.chkRow');
      return cb && cb.checked;
    });
  }

  function refreshBulk(){
    const selected = getSelectedRows();
    const n = selected.length;
    bulkCount.textContent = n + (n === 1 ? ' seleccionada' : ' seleccionadas');
    bulkBar.classList.toggle('is-on', n > 0);
    bulkBar.setAttribute('aria-hidden', n > 0 ? 'false' : 'true');
  }

  chkAll && chkAll.addEventListener('change', ()=>{
    const v = !!chkAll.checked;
    $$('.chkRow').forEach(c => c.checked = v);
    refreshBulk();
  });

  document.addEventListener('change', (e)=>{
    if (e.target && e.target.classList && e.target.classList.contains('chkRow')) refreshBulk();
  });

  bulkClear && bulkClear.addEventListener('click', ()=>{
    chkAll && (chkAll.checked = false);
    $$('.chkRow').forEach(c => c.checked = false);
    refreshBulk();
  });

  bulkCopyIds && bulkCopyIds.addEventListener('click', async ()=>{
    const ids = getSelectedRows().map(tr => tr.dataset.clientId || tr.dataset.id || '').filter(Boolean);
    await copyText(ids.join('\n'));
  });

  bulkOpenAdmin && bulkOpenAdmin.addEventListener('click', ()=>{
    getSelectedRows().slice(0, 12).forEach(tr=>{
      const u = tr.dataset.urlAdmin;
      if (u && u !== '#') window.open(u, '_blank', 'noopener');
    });
  });

  bulkOpenState && bulkOpenState.addEventListener('click', ()=>{
    getSelectedRows().slice(0, 12).forEach(tr=>{
      const u = tr.dataset.urlState;
      if (u && u !== '#') window.open(u, '_blank', 'noopener');
    });
  });

  refreshBulk();

  // Sorting
  const tbody = $('#p360Tbody');
  const sortState = { key: null, dir: 1 };

  function setCarrots(activeKey){
    const map = { seq: $('#carSeq'), client_id: $('#carCid'), name: $('#carName'), email: $('#carEmail') };
    Object.keys(map).forEach(k=>{
      const el = map[k];
      if (!el) return;
      if (k !== activeKey){ el.textContent = '↕'; return; }
      el.textContent = sortState.dir === 1 ? '↑' : '↓';
    });
  }

  function sortRows(key){
    if (!tbody) return;
    const rows = $$('#p360Tbody tr[data-row]');
    const dir = sortState.dir;

    rows.sort((a,b)=>{
      const av = a.dataset[key] ?? '';
      const bv = b.dataset[key] ?? '';
      if (key === 'seq') return (toNum(av) - toNum(bv)) * dir;
      if (key === 'clientId'){
        const an = toNum(av), bn = toNum(bv);
        if (an !== null && bn !== null) return (an - bn) * dir;
      }
      return String(av).localeCompare(String(bv), undefined, { sensitivity:'base' }) * dir;
    });

    rows.forEach(r => tbody.appendChild(r));
  }

  $$('#p360Table thead th.sortable').forEach(th=>{
    th.addEventListener('click', ()=>{
      const k = th.getAttribute('data-sort');
      if (!k) return;
      const keyMap = { seq:'seq', client_id:'clientId', name:'name', email:'email' };
      const dk = keyMap[k] || k;

      if (sortState.key === dk) sortState.dir = sortState.dir * -1;
      else { sortState.key = dk; sortState.dir = 1; }

      setCarrots(k);
      sortRows(dk);
    });
  });

  // Highlight terms
  function escapeHtml(s){
    return String(s).replace(/[&<>"']/g, (m)=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;' }[m]));
  }
  function highlight(el, query){
    const raw = el.getAttribute('data-text') || el.textContent || '';
    if (!query){ el.innerHTML = escapeHtml(raw); return; }
    const qn = norm(query);
    const idx = norm(raw).indexOf(qn);
    if (idx < 0){ el.innerHTML = escapeHtml(raw); return; }
    const before = raw.slice(0, idx);
    const mid = raw.slice(idx, idx + query.length);
    const after = raw.slice(idx + query.length);
    el.innerHTML = escapeHtml(before) + '<mark class="p360hl">' + escapeHtml(mid) + '</mark>' + escapeHtml(after);
  }
  function applyHighlights(){
    const q = ($('#p360Search')?.value || '').trim();
    $$('.p360txt').forEach(el => highlight(el, q));
  }
  applyHighlights();

  let t = null;
  const inp = $('#p360Search');
  inp && inp.addEventListener('input', ()=>{
    clearTimeout(t);
    t = setTimeout(applyHighlights, 120);
  });

  // Export CSV
  function download(filename, text){
    const blob = new Blob([text], {type:'text/csv;charset=utf-8;'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(()=>URL.revokeObjectURL(url), 3000);
  }
  function csvEscape(v){
    const s = String(v ?? '');
    if (/[,"\n]/.test(s)) return '"' + s.replace(/"/g,'""') + '"';
    return s;
  }

  const btnCsv = $('#btnExportCsv');
  btnCsv && btnCsv.addEventListener('click', ()=>{
    const cols = getCols();
    const headers = { seq:'#', client_id:'ID cliente', cliente:'Cliente', email:'Email', plan:'Plan', status:'Estatus' };

    const head = cols
      .filter(c => ['seq','client_id','cliente','email','plan','status'].includes(c))
      .map(c => headers[c] || c);

    const rows = $$('#p360Tbody tr[data-row]').map(tr=>{
      const name = tr.dataset.name || '';
      const rfc  = tr.dataset.rfc || '';
      const email = tr.dataset.email || '';
      const cid = tr.dataset.clientId || '';
      const seq = tr.dataset.seq || '';
      const planCell = tr.querySelector('td[data-col="plan"]')?.innerText?.replace(/\s+/g,' ').trim() || '';
      const statusCell = tr.querySelector('td[data-col="status"]')?.innerText?.replace(/\s+/g,' ').trim() || '';

      const map = {
        seq: '#'+seq,
        client_id: cid,
        cliente: (name + (rfc ? (' (RFC ' + rfc + ')') : '')).trim(),
        email: email,
        plan: planCell,
        status: statusCell
      };

      return cols
        .filter(c => ['seq','client_id','cliente','email','plan','status'].includes(c))
        .map(c => csvEscape(map[c] ?? ''))
        .join(',');
    });

    const csv = head.map(csvEscape).join(',') + '\n' + rows.join('\n');
    const stamp = new Date().toISOString().slice(0,19).replace(/[:T]/g,'-');
    download('p360-cuentas-' + stamp + '.csv', csv);
  });

})();
</script>
@endpush
