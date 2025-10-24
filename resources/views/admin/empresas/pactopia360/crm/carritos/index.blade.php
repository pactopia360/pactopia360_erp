@extends('layouts.admin')

@section('title','CRM · Carritos')

@push('styles')
<style id="css-carritos-pro">
  /* ==========================================================================
     PACTOPIA360 · CRM » Carritos — v4.0 (responsive-only)
     - Mantiene el diseño actual
     - Ajustes para móviles/tablets y safe-areas
     - Encapsulado en .mod-carritos (sin fugas)
     ========================================================================== */
  :root{
    --p360-red:#ff2a2a; --p360-red-700:#a50f0f;
    --p360-navy:#0b2a3a; --p360-bluegray:#394759; --p360-slate:#323642;
    --text:#0f172a; --muted:#6b7280;
    --bg:#f6f7f9; --card-bg:#fff; --panel-bg:#f8fafc; --card-border:rgba(0,0,0,.08);
    --shadow-1:0 6px 18px rgba(0,0,0,.06); --shadow-2:0 16px 40px rgba(0,0,0,.10);
  }
  [data-theme="dark"]{
    --text:#e5e7eb; --muted:#a1a1aa;
    --bg:#0b1220; --card-bg:#0f172a; --panel-bg:#0c172b; --card-border:rgba(255,255,255,.1);
    --shadow-1:0 10px 28px rgba(0,0,0,.45); --shadow-2:0 16px 50px rgba(0,0,0,.6);
  }

  .mod-carritos{ position:relative; }
  .mod-carritos .page-shell{ width:100%; max-width:var(--container-max,1280px); margin-inline:auto }
  .mod-carritos .page { display:grid; gap:14px }

  .mod-carritos .card{
    background:var(--card-bg); border:1px solid var(--card-border);
    border-radius:14px; box-shadow:var(--shadow-1);
  }
  .mod-carritos .card-b{ padding:12px 14px }

  /* ====== HERO ====== */
  .mod-carritos .hero{
    position:relative; overflow:hidden; border-radius:16px; border:1px solid var(--card-border);
    background:
      radial-gradient(90% 120% at 0% 0%, color-mix(in oklab, var(--p360-navy) 16%, transparent), transparent 54%),
      radial-gradient(80% 120% at 100% 0%, color-mix(in oklab, var(--p360-bluegray) 18%, transparent), transparent 56%),
      var(--card-bg);
    box-shadow: var(--shadow-2);
  }
  .mod-carritos .hero-inner{
    display:grid; grid-template-columns:1fr auto; gap:12px; align-items:center;
    padding:16px 14px;
  }
  .mod-carritos .titlebox{ display:flex; align-items:center; gap:12px; min-width:0 }
  .mod-carritos .title-ico{
    width:38px; height:38px; display:grid; place-items:center; color:#fff; border-radius:12px;
    background:linear-gradient(135deg,var(--p360-navy), color-mix(in oklab, var(--p360-bluegray) 70%, black 30%));
    box-shadow:0 12px 28px color-mix(in oklab, var(--p360-navy) 24%, transparent);
    flex:0 0 auto;
  }
  .mod-carritos .title{ min-width:0 }
  .mod-carritos .title h1{ margin:0; font:800 18px/1.2 system-ui; color:var(--text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis }
  .mod-carritos .title small{ display:block; color:var(--muted); margin-top:2px; font-size:12px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis }

  .mod-carritos .kpirow{ display:flex; align-items:center; gap:10px; flex-wrap:wrap; justify-content:flex-end }
  .mod-carritos .kpi{
    --ring: color-mix(in oklab, var(--p360-navy) 55%, transparent);
    position:relative; padding:8px 12px; border-radius:999px; font:800 12px/1 system-ui; color:var(--text);
    background:linear-gradient(180deg, color-mix(in oklab, var(--p360-navy) 12%, transparent), transparent);
    border:1px solid color-mix(in oklab, var(--p360-navy) 34%, transparent);
    white-space:nowrap;
  }
  .mod-carritos .kpi .mono{ font-variant-numeric:tabular-nums }
  .mod-carritos .kpi::after{
    content:""; position:absolute; inset:-2px; border-radius:inherit; pointer-events:none;
    background:radial-gradient(120% 120% at 100% 0%, var(--ring), transparent 60%);
    -webkit-mask:radial-gradient(120% 120% at 100% 0%, #000 40%, transparent 60%);
            mask:radial-gradient(120% 120% at 100% 0%, #000 40%, transparent 60%);
    opacity:.45;
  }

  /* ====== Toolbar avanzada (sticky) ====== */
  .mod-carritos .filters.card{ position:sticky; top:calc(var(--header-h,56px) + 8px); z-index:20 }
  .mod-carritos .toolbar{ display:flex; gap:10px; flex-wrap:wrap; align-items:center }
  .mod-carritos .search{
    display:flex; align-items:center; gap:8px; min-width:220px;
    padding:10px 12px; border:1px solid var(--card-border); border-radius:12px;
    background:var(--panel-bg); color:var(--muted)
  }
  .mod-carritos .search input{ background:transparent; border:0; outline:0; color:var(--text); min-width:160px }

  .mod-carritos .select, .mod-carritos .input{
    -webkit-appearance:none; appearance:none; min-height:38px; font-size:14px;
    padding:10px 12px; border-radius:12px; border:1px solid var(--card-border); background:var(--card-bg); color:var(--text)
  }
  .mod-carritos .btn{
    min-height:38px; padding:10px 12px; border-radius:12px; border:1px solid var(--card-border);
    background:var(--card-bg); color:var(--text); font-weight:800; cursor:pointer;
    transition: filter .15s ease, transform .1s ease;
  }
  .mod-carritos .btn:hover{ filter:brightness(1.06) }
  .mod-carritos .btn:active{ transform:translateY(1px) }
  .mod-carritos .btn-primary{
    background:linear-gradient(135deg,var(--p360-navy), color-mix(in oklab, var(--p360-bluegray) 72%, black 28%));
    color:#fff; border:0; box-shadow:0 10px 26px color-mix(in oklab, var(--p360-navy) 24%, transparent);
  }
  .mod-carritos .btn-danger{
    background:linear-gradient(135deg,var(--p360-red), color-mix(in oklab, var(--p360-red) 70%, black 30%)); color:#fff; border:0;
  }
  .mod-carritos .spacer{ flex:1 }

  .mod-carritos .chipset{ display:flex; gap:8px; flex-wrap:wrap; margin-top:10px }
  .mod-carritos .chip{
    display:inline-flex; gap:8px; align-items:center; padding:6px 10px; border-radius:999px; font:700 12px/1 system-ui;
    border:1px dashed color-mix(in oklab, var(--p360-navy) 30%, transparent); background: color-mix(in oklab, var(--p360-navy) 10%, transparent);
  }
  .mod-carritos .chip button{ border:0; background:transparent; cursor:pointer; font:700 13px/1 system-ui }

  /* ====== Tabla ====== */
  .mod-carritos .tbl-wrap{ overflow:auto; border-radius:12px; border:1px solid var(--card-border); background:var(--card-bg) }
  .mod-carritos table{ width:100%; border-collapse:separate; border-spacing:0; min-width:760px }
  .mod-carritos th, .mod-carritos td{ padding:10px 12px; border-bottom:1px solid var(--card-border); white-space:nowrap; font-size:14px; color:var(--text) }
  .mod-carritos.compact th, .mod-carritos.compact td{ padding:6px 8px; font-size:13px }
  .mod-carritos thead th{
    position:sticky; top:0; z-index:4; text-align:left; font:800 12px/1.1 system-ui; color:var(--muted);
    background:linear-gradient(180deg,var(--card-bg), color-mix(in oklab, var(--card-bg) 88%, transparent));
  }
  .mod-carritos tbody tr:hover{ background: color-mix(in oklab, var(--p360-navy) 8%, transparent) }
  .mod-carritos tbody tr:nth-child(odd){ background: color-mix(in oklab, var(--text) 3%, transparent) }
  .mod-carritos .num{ text-align:right; font-variant-numeric: tabular-nums }
  .mod-carritos td[data-col="titulo"] small{ display:block; max-width:520px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis }

  .mod-carritos .sortable{ cursor:pointer; user-select:none }
  .mod-carritos .sortable .arrow{ opacity:.4; margin-left:6px; font-size:11px }
  .mod-carritos .sorted-asc .arrow{ opacity:1; transform:rotate(180deg) }
  .mod-carritos .sorted-desc .arrow{ opacity:1 }

  /* Estado badges */
  .mod-carritos .status{ display:inline-flex; align-items:center; gap:6px; padding:6px 10px;
    border-radius:999px; font:800 12px/1 system-ui; border:1px solid transparent }
  .mod-carritos .status .dot{ width:8px; height:8px; border-radius:999px; background:currentColor }
  .mod-carritos .is-open{ color:#1d4ed8; background: color-mix(in oklab, #1d4ed8 16%, transparent); border-color: color-mix(in oklab, #1d4ed8 32%, transparent) }
  .mod-carritos .is-new{ color:#0891b2; background: color-mix(in oklab, #0891b2 16%, transparent); border-color: color-mix(in oklab, #0891b2 32%, transparent) }
  .mod-carritos .is-converted{ color:#16a34a; background: color-mix(in oklab, #16a34a 16%, transparent); border-color: color-mix(in oklab, #16a34a 28%, transparent) }
  .mod-carritos .is-cancelled{ color:var(--p360-red); background: color-mix(in oklab, var(--p360-red) 14%, transparent); border-color: color-mix(in oklab, var(--p360-red) 30%, transparent) }

  .mod-carritos .actions{ display:flex; gap:6px; align-items:center; flex-wrap:wrap }
  .mod-carritos .btn-xs{
    font:800 11px/1 system-ui; padding:6px 8px; border-radius:10px; border:1px solid var(--card-border);
    background:var(--card-bg); color:var(--text); text-decoration:none; cursor:pointer
  }
  .mod-carritos .btn-xs.edit{ border-color: color-mix(in oklab, var(--p360-navy) 45%, transparent) }
  .mod-carritos .btn-xs.danger{ border-color: color-mix(in oklab, var(--p360-red) 45%, transparent); color: var(--p360-red-700) }

  .mod-carritos .empty{ padding:24px; text-align:center; color:var(--muted) }
  .mod-carritos .bulkbar{ display:flex; gap:8px; align-items:center; padding:10px 12px; border-radius:12px;
    background: color-mix(in oklab, var(--p360-navy) 12%, transparent); border:1px dashed color-mix(in oklab, var(--p360-navy) 30%, transparent)
  }

  /* Chips de totales al pie */
  .mod-carritos .totals{ display:flex; gap:10px; flex-wrap:wrap; padding:8px 12px }
  .mod-carritos .total-chip{
    display:inline-flex; gap:8px; align-items:center; padding:8px 12px; border-radius:10px;
    border:1px solid var(--card-border); background:var(--panel-bg); font:800 12px/1 system-ui;
  }

  /* Paginación */
  .mod-carritos .mono{ font-variant-numeric: tabular-nums }
  .mod-carritos .pager .card-b{ padding:10px 14px }
  .mod-carritos .pagin{ display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
  .mod-carritos .pagin-left{ color:var(--muted); font-size:12px }
  .mod-carritos .pagin-left strong{ color:var(--text) }
  .mod-carritos .pagin-right nav{ display:flex; width:100% }
  .mod-carritos .pagin-right nav > div:first-child{ display:none }
  .mod-carritos .pagin-right nav > div:last-child{ margin-left:auto }
  .mod-carritos .pagin-right nav ul{ list-style:none; display:flex; gap:6px; margin:0; padding:0; }
  .mod-carritos .pagin-right nav a,
  .mod-carritos .pagin-right nav span{
    display:inline-flex; align-items:center; justify-content:center;
    min-width:36px; padding:8px 10px; border-radius:10px;
    border:1px solid var(--card-border); background:var(--card-bg); color:var(--text);
    text-decoration:none; font:800 12px/1 system-ui;
  }
  .mod-carritos .pagin-right nav span[aria-current="page"]{
    background:linear-gradient(135deg,var(--p360-navy), color-mix(in oklab, var(--p360-bluegray) 70%, black 30%));
    color:#fff; border-color:transparent;
  }
  .mod-carritos .pagin-right nav span[aria-disabled="true"]{ opacity:.45; cursor:not-allowed }

  /* ====== Responsivo ====== */
  @media (max-width: 1100px){
    .mod-carritos .hero-inner{ grid-template-columns:1fr }
    .mod-carritos .kpirow{ justify-content:flex-start }
    .mod-carritos .search{ min-width:200px }
  }
  @media (max-width: 900px){
    .mod-carritos [data-col="moneda"],
    .mod-carritos [data-col="origen"]{ display:none }
    .mod-carritos td[data-col="titulo"] small{ max-width:360px }
  }
  @media (max-width: 720px){
    .mod-carritos .title h1{ font-size:16px }
    .mod-carritos .title small{ font-size:11px }
    .mod-carritos .kpi{ padding:6px 10px; font-size:11px }
    .mod-carritos .toolbar .btn-group{ width:100% }
    .mod-carritos .toolbar .btn-group > *{ flex:1 1 auto }
    .mod-carritos table{ min-width:680px } /* mantiene scroll horizontal sin romper diseño */
  }
  @media (max-width: 480px){
    .mod-carritos .search{ min-width:0; width:100% }
    .mod-carritos .search input{ min-width:0; width:100% }
    .mod-carritos td[data-col="titulo"] small{ max-width:240px }
  }
</style>
@endpush

@section('content')
@php
  /* ========= NORMALIZACIÓN Y UTILIDADES ========= */
  $rows = $rows ?? $carritos ?? $items ?? $data ?? [];
  if (is_array($rows)) $rows = collect($rows);

  if (empty($estadosMap) || !is_array($estadosMap)) {
    if (!empty($estados) && is_array($estados)) {
      $estadosMap = [];
      foreach ($estados as $e) { $estadosMap[strtolower($e)] = ucfirst($e); }
    } else {
      $estadosMap = ['nuevo'=>'Nuevo','abierto'=>'Abierto','convertido'=>'Convertido','cancelado'=>'Cancelado'];
    }
  }

  $q       = request('q','');
  $estadoF = request('estado', request('est',''));
  $monedaF = request('moneda', '');
  $desdeF  = request('desde', '');
  $hastaF  = request('hasta', '');
  $minF    = request('min_total','');
  $maxF    = request('max_total','');

  $sort    = request('sort','created_at');
  $dir     = request('dir','desc');

  if ($rows instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
    $collection = $rows->getCollection();
  } else {
    $collection = collect($rows);
    $allowed = ['id','titulo','title','nombre','total','monto','moneda','currency','origen','source','created_at','creado','estado','status'];
    if (in_array($sort,$allowed,true)) {
      $collection = $collection->sortBy(fn($r)=>data_get($r,$sort), SORT_REGULAR, strtolower($dir)==='desc');
    }
    $rows = $collection;
  }

  $kTotal       = $collection->count();
  $kAbiertos    = $collection->filter(fn($r)=>strtolower(($r->estado ?? $r->status ?? ''))==='abierto')->count();
  $kConvertidos = $collection->filter(fn($r)=>strtolower(($r->estado ?? $r->status ?? ''))==='convertido')->count();
  $kCancelados  = $collection->filter(fn($r)=>strtolower(($r->estado ?? $r->status ?? ''))==='cancelado')->count();

  $sumTotal     = $collection->sum(fn($r)=>(float)($r->total ?? $r->monto ?? 0));
  $sumAbiertos  = $collection->filter(fn($r)=>strtolower(($r->estado ?? $r->status ?? ''))==='abierto')->sum(fn($r)=>(float)($r->total ?? $r->monto ?? 0));
  $sumConv      = $collection->filter(fn($r)=>strtolower(($r->estado ?? $r->status ?? ''))==='convertido')->sum(fn($r)=>(float)($r->total ?? $r->monto ?? 0));
  $sumCanc      = $collection->filter(fn($r)=>strtolower(($r->estado ?? $r->status ?? ''))==='cancelado')->sum(fn($r)=>(float)($r->total ?? $r->monto ?? 0));

  $canCreate      = \Illuminate\Support\Facades\Route::has('admin.empresas.pactopia360.crm.carritos.create');
  $canEdit        = \Illuminate\Support\Facades\Route::has('admin.empresas.pactopia360.crm.carritos.edit');
  $canDestroy     = \Illuminate\Support\Facades\Route::has('admin.empresas.pactopia360.crm.carritos.destroy');
  $canDestroyBulk = \Illuminate\Support\Facades\Route::has('admin.empresas.pactopia360.crm.carritos.destroy.bulk');

  // Export no requiere ruta dedicada: el controlador ya soporta ?export=csv
  $exportUrl = request()->fullUrlWithQuery(array_merge(request()->query(), ['export' => 'csv']));

  $sortUrl = function(string $key) use ($dir){
    $newDir = (request('sort')===$key && $dir==='asc') ? 'desc' : 'asc';
    return request()->fullUrlWithQuery(['sort'=>$key,'dir'=>$newDir]);
  };
  $tabUrl = function($k){ return request()->fullUrlWithQuery(['estado'=>$k ?: null, 'page'=>null]); };
@endphp

<div class="mod-carritos page page-shell" data-module="crm-carritos">

  {{-- HERO --}}
  <section class="hero" aria-label="Carritos">
    <div class="hero-inner">
      <div class="titlebox">
        <div class="title-ico" aria-hidden="true">🛒</div>
        <div class="title">
          <h1>Carritos</h1>
          <small>Embudo de oportunidades, conversiones y cancelaciones</small>
        </div>
      </div>
      <div class="kpirow" role="group" aria-label="KPIs">
        <div class="kpi">Total: <strong class="mono">{{ number_format($kTotal) }}</strong></div>
        <div class="kpi">Abiertos: <strong class="mono">{{ number_format($kAbiertos) }}</strong></div>
        <div class="kpi">Convertidos: <strong class="mono">{{ number_format($kConvertidos) }}</strong></div>
        <div class="kpi">Cancelados: <strong class="mono">{{ number_format($kCancelados) }}</strong></div>
      </div>
    </div>

    {{-- Tabs rápidas por estado --}}
    <div class="seg-tabs" role="tablist" aria-label="Filtros rápidos por estado" style="display:flex;gap:8px;flex-wrap:wrap;padding:0 14px 14px">
      @php $act = $estadoF ?: ''; @endphp
      <a class="seg {{ $act==='' ? 'active':'' }}" role="tab" aria-selected="{{ $act==='' ? 'true':'false' }}" href="{{ $tabUrl('') }}" style="text-decoration:none;border:1px solid var(--card-border);border-radius:999px;padding:8px 12px;font:800 12px/1 system-ui;color:inherit;background:var(--card-bg)">Todos <strong style="margin-left:6px">{{ number_format($kTotal) }}</strong></a>
      <a class="seg {{ $act==='abierto' ? 'active':'' }}" role="tab" aria-selected="{{ $act==='abierto' ? 'true':'false' }}" href="{{ $tabUrl('abierto') }}"  style="text-decoration:none;border:1px solid var(--card-border);border-radius:999px;padding:8px 12px;font:800 12px/1 system-ui;color:inherit;background:var(--card-bg)">Abiertos <strong style="margin-left:6px">{{ number_format($kAbiertos) }}</strong></a>
      <a class="seg {{ $act==='convertido' ? 'active':'' }}" role="tab" aria-selected="{{ $act==='convertido' ? 'true':'false' }}" href="{{ $tabUrl('convertido') }}" style="text-decoration:none;border:1px solid var(--card-border);border-radius:999px;padding:8px 12px;font:800 12px/1 system-ui;color:inherit;background:var(--card-bg)">Convertidos <strong style="margin-left:6px">{{ number_format($kConvertidos) }}</strong></a>
      <a class="seg {{ $act==='cancelado' ? 'active':'' }}" role="tab" aria-selected="{{ $act==='cancelado' ? 'true':'false' }}" href="{{ $tabUrl('cancelado') }}" style="text-decoration:none;border:1px solid var(--card-border);border-radius:999px;padding:8px 12px;font:800 12px/1 system-ui;color:inherit;background:var(--card-bg)">Cancelados <strong style="margin-left:6px">{{ number_format($kCancelados) }}</strong></a>
    </div>
  </section>

  {{-- FILTROS AVANZADOS (sticky) --}}
  <div class="card filters" role="region" aria-label="Filtros y Acciones">
    <div class="card-b">
      <form method="get" class="toolbar" action="{{ url()->current() }}" data-pjax-form>
        <label class="search" aria-label="Buscar">
          🔎 <input id="q" type="search" name="q" value="{{ $q }}" placeholder="Buscar: cliente, email, teléfono…">
        </label>

        <select name="estado" class="select" aria-label="Estado">
          <option value="">Estado (todos)</option>
          @foreach($estadosMap as $k=>$v)
            <option value="{{ $k }}" @selected($estadoF===$k)>{{ $v }}</option>
          @endforeach
        </select>

        <input class="input" type="text" name="moneda" value="{{ $monedaF }}" placeholder="Moneda (MXN, USD…)" aria-label="Moneda">
        <input class="input" type="date" name="desde" value="{{ $desdeF }}" aria-label="Desde">
        <input class="input" type="date" name="hasta" value="{{ $hastaF }}" aria-label="Hasta">
        <input class="input" type="number" step="0.01" name="min_total" value="{{ $minF }}" placeholder="Min $" aria-label="Monto mínimo">
        <input class="input" type="number" step="0.01" name="max_total" value="{{ $maxF }}" placeholder="Max $" aria-label="Monto máximo">

        <select name="per_page" class="select" aria-label="Por página" onchange="this.form.submit()">
          @foreach([15,25,50,100,200] as $pp)
            <option value="{{ $pp }}" @selected((int)request('per_page',15)===$pp)>{{ $pp }}/pág.</option>
          @endforeach
        </select>

        <button class="btn" type="submit">Aplicar</button>
        @if($q || $estadoF || $monedaF || $desdeF || $hastaF || $minF || $maxF)
          <a class="btn" href="{{ url()->current() }}" title="Limpiar filtros">Limpiar</a>
        @endif

        <div class="spacer"></div>

        {{-- Columnas / Densidad / CSV / Guardar vista --}}
        <div class="btn-group" role="group" aria-label="Opciones de vista" style="display:flex; gap:8px; flex-wrap:wrap">
          <button class="btn" type="button" id="btnCols">Columnas</button>
          <button class="btn" type="button" id="btnDensity" title="Cambiar densidad (Shift+D)">Densidad</button>
          <a class="btn" href="{{ $exportUrl }}" title="Exportar CSV (E)">CSV</a>
          <button class="btn" type="button" id="btnSaveView" title="Guardar vista actual">Guardar vista</button>
          <select id="selViews" class="select" aria-label="Vistas guardadas" style="min-width:160px"></select>
          <button class="btn" type="button" id="btnShare" title="Copiar URL con filtros">Compartir</button>
        </div>

        @if($canCreate)
          <a class="btn btn-primary" href="{{ route('admin.empresas.pactopia360.crm.carritos.create') }}">+ Nuevo</a>
        @endif>
      </form>

      {{-- chips de filtros activos --}}
      <div class="chipset" id="chips"></div>
    </div>
  </div>

  {{-- Bulk (opcional) --}}
  @if($canDestroyBulk)
    <form id="bulkForm" method="post" action="{{ route('admin.empresas.pactopia360.crm.carritos.destroy.bulk') }}"
          onsubmit="return confirm('¿Eliminar los seleccionados?')">
      @csrf @method('DELETE')
      <div class="bulkbar">
        <div><strong>Acciones masivas</strong></div>
        <button class="btn btn-danger" type="submit">Eliminar seleccionados</button>
      </div>
  @endif

  {{-- Tabla --}}
  <div class="tbl-wrap" role="region" aria-label="Listado de carritos">
    <table class="table" id="tbl-carritos">
      <thead>
        <tr>
          @if($canDestroyBulk)
            <th style="width:36px" data-col="cb">
              <input type="checkbox" aria-label="Seleccionar todos"
                     onclick="document.querySelectorAll('.bulk-cb').forEach(cb=>cb.checked=this.checked)">
            </th>
          @endif

          <th style="width: 80px" class="sortable {{ request('sort')==='id' ? ('sorted-'.request('dir','asc')) : '' }}" data-col="id">
            <a href="{{ $sortUrl('id') }}">ID <span class="arrow">▾</span></a>
          </th>

          <th class="sortable {{ request('sort')==='titulo' ? ('sorted-'.request('dir','asc')) : '' }}" data-col="titulo">
            <a href="{{ $sortUrl('titulo') }}">Título / Cliente <span class="arrow">▾</span></a>
          </th>

          <th style="width:170px" class="sortable {{ request('sort')==='estado' ? ('sorted-'.request('dir','asc')) : '' }}" data-col="estado">
            <a href="{{ $sortUrl('estado') }}">Estado <span class="arrow">▾</span></a>
          </th>

          <th style="width:140px" class="sortable {{ request('sort')==='total' ? ('sorted-'.request('dir','asc')) : '' }}" data-col="total">
            <a href="{{ $sortUrl('total') }}">Total <span class="arrow">▾</span></a>
          </th>

          <th style="width:110px" data-col="moneda">Moneda</th>
          <th style="width:120px" data-col="origen">Origen</th>

          <th style="width:160px" class="sortable {{ request('sort')==='created_at' ? ('sorted-'.request('dir','asc')) : '' }}" data-col="creado">
            <a href="{{ $sortUrl('created_at') }}">Creado <span class="arrow">▾</span></a>
          </th>

          <th style="width:200px" data-col="acciones">Acciones</th>
        </tr>
      </thead>
      <tbody>
        @forelse($rows as $r)
          @php
            $id       = $r->id ?? $r->carrito_id ?? null;
            $titulo   = $r->titulo ?? $r->title ?? $r->nombre ?? ('Cliente '.$id);
            $cliente  = $r->cliente ?? $r->cliente_nombre ?? $r->contacto ?? null;
            $email    = $r->email ?? $r->cliente_email ?? null;
            $estado   = strtolower($r->estado ?? $r->status ?? 'nuevo');
            $estadoLbl= $estadosMap[$estado] ?? ucfirst($estado ?: '—');
            $total    = (float)($r->total ?? $r->monto ?? 0);
            $moneda   = $r->moneda ?? $r->currency ?? 'MXN';
            $origen   = $r->origen ?? $r->source ?? '—';
            $fechaVal = $r->created_at ?? $r->creado ?? now();
            try{ $fecha = \Illuminate\Support\Carbon::parse($fechaVal)->format('Y-m-d'); }catch(\Exception $e){ $fecha = '—'; }

            $map = [
              'abierto'    => ['is-open','🔵'],
              'convertido' => ['is-converted','✅'],
              'cancelado'  => ['is-cancelled','✖'],
              'nuevo'      => ['is-new','✨'],
            ];
            [$cls,$icon] = $map[$estado] ?? $map['nuevo'];

            $editUrl    = $canEdit    ? route('admin.empresas.pactopia360.crm.carritos.edit',$id)    : '#';
            $destroyUrl = $canDestroy ? route('admin.empresas.pactopia360.crm.carritos.destroy',$id) : null;
          @endphp
          <tr data-id="{{ $id }}">
            @if($canDestroyBulk)
              <td data-col="cb"><input class="bulk-cb" type="checkbox" name="ids[]" value="{{ $id }}" aria-label="Seleccionar carrito {{ $id }}"></td>
            @endif
            <td class="num mono" data-col="id">#{{ $id }}</td>
            <td data-col="titulo">
              <div style="display:flex; flex-direction:column; min-width:0">
                <strong style="max-width:520px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis">{{ $titulo }}</strong>
                @if($cliente || $email)
                  <small style="color:var(--muted)">{{ $cliente ?? '' }} @if($cliente && $email) · @endif {{ $email ?? '' }}</small>
                @endif
              </div>
            </td>
            <td data-col="estado">
              <span class="status {{ $cls }}" title="{{ $estadoLbl }}"><span class="dot"></span> {{ $icon }} {{ $estadoLbl }}</span>
            </td>
            <td class="num mono" data-col="total">${{ number_format($total,2) }}</td>
            <td class="mono" data-col="moneda">{{ $moneda }}</td>
            <td class="mono" data-col="origen">{{ strtoupper($origen) }}</td>
            <td class="mono" data-col="creado">{{ $fecha }}</td>
            <td data-col="acciones">
              <div class="actions">
                @if($canEdit)
                  <a class="btn-xs edit" href="{{ $editUrl }}">Editar</a>
                @endif
                @if($destroyUrl)
                  <form method="post" action="{{ $destroyUrl }}" onsubmit="return confirm('¿Eliminar carrito #{{ $id }}?')">
                    @csrf @method('DELETE')
                    <button class="btn-xs danger" type="submit">Eliminar</button>
                  </form>
                @endif
              </div>
            </td>
          </tr>
        @empty
          <tr>
            @php $colspan = 9 - (int)!$canDestroyBulk; @endphp
            <td colspan="{{ $colspan }}" class="empty">
              Sin carritos.
              @if($canCreate)
                <a class="btn" href="{{ route('admin.empresas.pactopia360.crm.carritos.create') }}">Crear el primero</a>
              @endif
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  {{-- Totales al pie (cliente) --}}
  <div class="card">
    <div class="totals">
      <div class="total-chip">Suma total: <strong class="mono">${{ number_format($sumTotal,2) }}</strong></div>
      <div class="total-chip">Abiertos: <strong class="mono">${{ number_format($sumAbiertos,2) }}</strong></div>
      <div class="total-chip">Convertidos: <strong class="mono">${{ number_format($sumConv,2) }}</strong></div>
      <div class="total-chip">Cancelados: <strong class="mono">${{ number_format($sumCanc,2) }}</strong></div>
      @if(!empty($metricas['sumas']))
        <div class="total-chip" title="Servidor">Servidor · Total: <strong class="mono">${{ number_format($metricas['sumas']['total'] ?? 0,2) }}</strong></div>
      @endif
    </div>
  </div>

  @if($canDestroyBulk)
    </form> {{-- cierra bulkForm --}}
  @endif

  {{-- Paginación --}}
  @if($rows instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
    <div class="card pager" role="navigation" aria-label="Paginación">
      <div class="card-b pagin">
        <div class="pagin-left">
          Mostrando
          <strong class="mono">{{ $rows->firstItem() }}</strong>–<strong class="mono">{{ $rows->lastItem() }}</strong>
          de <strong class="mono">{{ $rows->total() }}</strong> resultados
        </div>
        <div class="pagin-right">
          {{ $rows->onEachSide(1)->links() }}
        </div>
      </div>
    </div>
  @endif

</div>
@endsection

@push('scripts')
<script>
/* ===== Utilidades UI de la vista (sin dependencias) ===== */
(function(){
  const d=document, w=window;
  const ROOT = d.querySelector('.mod-carritos');
  if(!ROOT) return;

  // ====== Densidad (compact) ======
  const DKEY='p360.carritos.density';
  const setDensity = (compact) => {
    ROOT.classList.toggle('compact', !!compact);
    try{ localStorage.setItem(DKEY, compact ? '1':'0'); }catch(_){}
  };
  try{ setDensity(localStorage.getItem(DKEY)==='1'); }catch(_){}
  d.getElementById('btnDensity')?.addEventListener('click', ()=> setDensity(!ROOT.classList.contains('compact')));

  // Atajos: Shift+D densidad · E export
  d.addEventListener('keydown', (ev)=>{
    if(ev.shiftKey && (ev.key==='d' || ev.key==='D')){ ev.preventDefault(); d.getElementById('btnDensity')?.click(); }
    if(!ev.shiftKey && (ev.key==='e' || ev.key==='E')){
      const link=[...d.querySelectorAll('.btn')].find(b=>b.textContent.trim()==='CSV'); link?.click();
    }
  });

  // ====== Columnas visibles ======
  const CKEY='p360.carritos.cols';
  const defaultCols = ['id','titulo','estado','total','moneda','origen','creado','acciones'];
  const loadCols = ()=>{ try{ const arr = JSON.parse(localStorage.getItem(CKEY)||'[]'); return Array.isArray(arr)&&arr.length?arr:defaultCols.slice(); }catch{ return defaultCols.slice(); } };
  const saveCols = (arr)=>{ try{ localStorage.setItem(CKEY, JSON.stringify(arr)); }catch(_){ } };

  function applyCols(){
    const cols = loadCols();
    d.querySelectorAll('#tbl-carritos [data-col]').forEach(el=>{
      const key = el.getAttribute('data-col'); if(!key) return;
      el.style.display = cols.includes(key)? '':'none';
    });
  }
  applyCols();

  // Panel simple de columnas
  d.getElementById('btnCols')?.addEventListener('click', ()=>{
    const all = defaultCols;
    const sel = new Set(loadCols());
    const labels = { cb:'Selec.', id:'ID', titulo:'Título', estado:'Estado', total:'Total', moneda:'Moneda', origen:'Origen', creado:'Creado', acciones:'Acciones' };
    const html = all.map(k=>{
      const ch = sel.has(k)?'checked':'';
      return `<label style="display:flex;gap:8px;align-items:center;margin:6px 0">
        <input type="checkbox" data-k="${k}" ${ch}> ${labels[k]||k}
      </label>`;
    }).join('');
    const wrap = d.createElement('div');
    wrap.innerHTML = `<div class="card" style="position:fixed;right:14px;top:calc(var(--header-h,56px)+70px);z-index:999;padding:8px;max-width:230px">
      <div class="card-b">
        <strong>Columnas</strong>
        <div style="margin-top:8px">${html}</div>
        <div style="display:flex;gap:8px;margin-top:10px">
          <button class="btn btn-primary" id="colsApply">Aplicar</button>
          <button class="btn" id="colsClose">Cerrar</button>
        </div>
      </div>
    </div>`;
    d.body.appendChild(wrap);
    const close = ()=> wrap.remove();
    wrap.querySelector('#colsClose').addEventListener('click', close);
    wrap.querySelector('#colsApply').addEventListener('click', ()=>{
      const picks = [...wrap.querySelectorAll('input[type=checkbox][data-k]')]
        .filter(x=>x.checked).map(x=>x.getAttribute('data-k'));
      saveCols(picks.length? picks : defaultCols);
      applyCols();
      close();
    });
  });

  // ====== Chips de filtros activos + limpiar individual ======
  (function chips(){
    const C = d.getElementById('chips'); if(!C) return;
    const params = new URLSearchParams(location.search);
    const labels = { q:'Búsqueda', estado:'Estado', moneda:'Moneda', desde:'Desde', hasta:'Hasta', min_total:'Min $', max_total:'Max $', per_page:'Por pág.' };
    const keys = [...params.keys()].filter(k=>params.get(k));
    if(!keys.length){ C.innerHTML=''; return; }
    C.innerHTML = keys.map(k=>{
      const v = params.get(k);
      return `<span class="chip" data-k="${k}">
        ${labels[k]||k}: <strong>${(k==='q')?('“'+v+'”'):v}</strong>
        <button title="Quitar" aria-label="Quitar filtro ${labels[k]||k}">×</button>
      </span>`;
    }).join('');
    C.querySelectorAll('.chip button').forEach(btn=>{
      btn.addEventListener('click', ()=>{
        const k = btn.parentElement.getAttribute('data-k');
        const p = new URLSearchParams(location.search);
        p.delete(k); p.delete('page'); location.href = location.pathname + (p.toString()?('?'+p.toString()):'');
      });
    });
  })();

  // ====== Guardar / Cargar vistas (localStorage) ======
  const VKEY='p360.carritos.views';
  const selViews = d.getElementById('selViews');
  const btnSaveView = d.getElementById('btnSaveView');

  function loadViews(){ try{ return JSON.parse(localStorage.getItem(VKEY)||'[]'); }catch{ return []; } }
  function saveViews(arr){ try{ localStorage.setItem(VKEY, JSON.stringify(arr)); }catch(_){ } }
  function refreshViews(){
    if(!selViews) return;
    const list = loadViews();
    const cur = location.search;
    selViews.innerHTML = `<option value="">— Vistas guardadas —</option>` +
      list.map(v=>`<option value="${encodeURIComponent(v.qs)}">${v.name}</option>`).join('');
    if(list.some(v=>v.qs===cur)){ selViews.value = encodeURIComponent(cur); }
  }
  refreshViews();

  selViews?.addEventListener('change', ()=>{
    const qs = decodeURIComponent(selViews.value||'');
    if(qs){ location.href = location.pathname + qs; }
  });

  btnSaveView?.addEventListener('click', ()=>{
    const name = prompt('Nombre de la vista:', new Date().toLocaleString());
    if(!name) return;
    const list = loadViews();
    const qs = location.search;
    if(!qs){ alert('No hay filtros/orden para guardar.'); return; }
    const idx = list.findIndex(v=>v.qs===qs);
    if(idx>=0){ list[idx].name = name; } else { list.push({name, qs}); }
    saveViews(list); refreshViews();
    alert('Vista guardada.');
  });

  // ====== Compartir URL con filtros ======
  d.getElementById('btnShare')?.addEventListener('click', async ()=>{
    const url = location.href;
    try{
      await navigator.clipboard.writeText(url);
      alert('URL copiada al portapapeles.');
    }catch(_){
      prompt('Copia la URL:', url);
    }
  });

  // ===== Enfoque rápido en búsqueda (/) =====
  d.addEventListener('keydown', (e)=>{
    if(e.key==='/' && !e.ctrlKey && !e.metaKey && !e.altKey && !/^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName)){
      e.preventDefault(); const q=d.getElementById('q'); q?.focus(); q?.select?.();
    }
  });

  // ===== Footer rebind (asegura footer correcto tras PJAX) =====
  try{
    w.dispatchEvent(new CustomEvent('p360:pjax:after'));
    const footer = document.querySelector('.admin-footer');
    if (footer){ footer.style.removeProperty('position'); footer.style.marginTop = 'auto'; }
  }catch(_){}
})();
</script>
@endpush
