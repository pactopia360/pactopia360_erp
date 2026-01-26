{{-- resources/views/admin/sat/ops/downloads/index.blade.php --}}
{{-- P360 · Admin · SAT Ops · Descargas (v1.0) --}}

@extends('layouts.admin')

@section('title', $title ?? 'SAT · Operación · Descargas')
@section('pageClass','page-admin-sat-ops page-admin-sat-ops-downloads')

@php
  use Illuminate\Support\Facades\Route;
  $backUrl = Route::has('admin.sat.ops.index') ? route('admin.sat.ops.index') : (url('/admin'));
@endphp

@section('page-header')
  <div class="p360-ph">
    <div class="p360-ph-left">
      <div class="p360-ph-kicker">ADMIN · SAT OPS</div>
      <h1 class="p360-ph-title">Descargas</h1>
      <div class="p360-ph-sub">Monitoreo operativo de lotes, estados, ZIPs, reintentos y trazabilidad.</div>
    </div>

    <div class="p360-ph-right">
      <a class="p360-btn" href="{{ $backUrl }}">← Volver</a>
      <button type="button" class="p360-btn" onclick="location.reload()">Refrescar</button>
    </div>
  </div>
@endsection

@section('content')

  <div class="ops-wrap">

    <div class="ops-toolbar">
      <div class="ops-search">
        <div class="lbl">Buscar</div>
        <input class="inp" type="search" placeholder="RFC, lote, folio, periodo, estado..." autocomplete="off">
      </div>

      <div class="ops-filters">
        <div class="ops-select">
          <div class="lbl">Estado</div>
          <select class="sel">
            <option value="">Todos</option>
            <option value="queued">En cola</option>
            <option value="running">En proceso</option>
            <option value="done">Completado</option>
            <option value="failed">Fallido</option>
          </select>
        </div>

        <div class="ops-select">
          <div class="lbl">Tipo</div>
          <select class="sel">
            <option value="">Todos</option>
            <option value="emitidas">Emitidas</option>
            <option value="recibidas">Recibidas</option>
          </select>
        </div>
      </div>
    </div>

    <div class="ops-grid">
      <div class="ops-card tone-blue is-static">
        <div class="ops-card-top">
          <div class="ops-ico" aria-hidden="true">⬇️</div>
          <div class="ops-title">Histórico de descargas</div>
        </div>

        <div class="ops-desc">
          Mostraremos: RFC, rango fechas, tipo, estado, ZIP/XML count, tiempos, y acciones (reintentar/ver detalle).
        </div>

        <div class="ops-meta">
          <span class="ops-pill off">Pendiente: datasource</span>
          <span class="ops-go">—</span>
        </div>
      </div>

      <div class="ops-card tone-amber is-static">
        <div class="ops-card-top">
          <div class="ops-ico" aria-hidden="true">🧠</div>
          <div class="ops-title">Reintentos y trazas</div>
        </div>

        <div class="ops-desc">
          Reglas de reintento, backoff, errores comunes SAT, y vínculo a logs por download_id.
        </div>

        <div class="ops-meta">
          <span class="ops-pill off">Pendiente: endpoints</span>
          <span class="ops-go">—</span>
        </div>
      </div>
    </div>

  </div>

@endsection

@push('styles')
<style>
  /* Base styles (same family as credentials) */
  .page-admin-sat-ops .page-container{ padding-top: 14px; }

  .p360-ph{ display:flex; align-items:flex-end; justify-content:space-between; gap:14px; padding: 14px 16px; }
  .p360-ph-kicker{ font: 900 11px/1 system-ui; letter-spacing:.12em; color: var(--muted); }
  .p360-ph-title{ margin: 6px 0 0; font: 950 22px/1.15 system-ui; letter-spacing:-.01em; }
  .p360-ph-sub{ margin-top: 6px; color: var(--muted); font: 600 13px/1.45 system-ui; }
  .p360-ph-right{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
  .p360-btn{
    display:inline-flex; align-items:center; justify-content:center;
    border:1px solid var(--bd);
    background: color-mix(in oklab, var(--card-bg) 88%, transparent);
    color: var(--text);
    padding:10px 12px;
    border-radius: 12px;
    font: 850 13px/1 system-ui;
    text-decoration:none;
    cursor:pointer;
  }
  .p360-btn:hover{ background: color-mix(in oklab, var(--card-bg) 78%, transparent); }

  .ops-wrap{ display:grid; gap:14px; }

  .ops-toolbar{
    display:flex; align-items:flex-end; justify-content:space-between;
    gap:12px; padding: 12px 14px;
    border:1px solid var(--bd);
    border-radius: 16px;
    background: var(--card-bg);
    box-shadow: var(--shadow-1);
  }
  .ops-search{ flex: 1 1 auto; min-width: 240px; }
  .ops-filters{ display:flex; gap:10px; flex-wrap:wrap; }
  .lbl{ font: 900 11px/1 system-ui; letter-spacing:.10em; color: var(--muted); margin-bottom: 6px; }
  .inp, .sel{
    width: 100%;
    border:1px solid var(--bd);
    background: color-mix(in oklab, var(--panel-bg) 72%, transparent);
    color: var(--text);
    border-radius: 12px;
    padding: 11px 12px;
    font: 750 13px/1 system-ui;
    outline: none;
  }
  .ops-select{ width: 190px; }

  .ops-grid{ display:grid; grid-template-columns: repeat(12, 1fr); gap: 12px; }

  .ops-card{
    grid-column: span 6;
    display:flex; flex-direction:column; gap:10px;
    padding: 14px;
    border-radius: 16px;
    border:1px solid var(--bd);
    background: var(--card-bg);
    box-shadow: var(--shadow-1);
    position:relative; overflow:hidden;
    min-height: 118px;
  }
  .ops-card.is-static{ cursor: default; }
  .ops-card::before{
    content:'';
    position:absolute; inset:-40px -40px auto auto;
    width: 140px; height: 140px;
    border-radius: 999px;
    opacity:.22;
    background: #3b82f6;
  }
  .ops-card.tone-amber::before{ background: #f59e0b; }

  .ops-card-top{ display:flex; align-items:center; gap:10px; }
  .ops-ico{
    width: 38px; height: 38px; border-radius: 14px;
    display:grid; place-items:center;
    border:1px solid var(--bd);
    background: color-mix(in oklab, var(--panel-bg) 70%, transparent);
    font-size: 18px;
  }
  .ops-title{ font: 950 15px/1.1 system-ui; letter-spacing:-.01em; }
  .ops-desc{ color: var(--muted); font: 650 13px/1.45 system-ui; }
  .ops-meta{ display:flex; justify-content:space-between; align-items:center; margin-top:auto; gap:10px; }
  .ops-pill{
    display:inline-flex; align-items:center; gap:8px;
    padding: 7px 10px;
    border-radius: 999px;
    font: 850 12px/1 system-ui;
    border: 1px solid var(--bd);
    background: color-mix(in oklab, var(--panel-bg) 72%, transparent);
  }
  .ops-pill.off{ border-color: color-mix(in oklab, #ef4444 35%, var(--bd)); }
  .ops-go{ font: 900 12px/1 system-ui; opacity:.85; }

  @media (max-width: 1100px){
    .ops-card{ grid-column: span 12; }
    .ops-toolbar{ flex-direction:column; align-items:stretch; }
    .ops-select{ width: 100%; }
    .p360-ph{ align-items:flex-start; }
  }
</style>
@endpush
