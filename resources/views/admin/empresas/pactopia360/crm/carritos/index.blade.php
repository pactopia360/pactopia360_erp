@extends('layouts.admin')

@section('title','CRM · Carritos')

@push('styles')
<style id="css-carritos-pro">
  /* ==========================================================================
     PACTOPIA360 · CRM » Carritos — v2.5 (brand-driven + footer-safe)
     - Encapsulado en .mod-carritos (no contamina otros módulos)
     - Safeties para que el legal del layout NUNCA se vaya a la derecha ni al header
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

  /* ===== Encapsulado del módulo ===== */
  .mod-carritos{
    --accent: var(--p360-navy);
    --accent-2: var(--p360-bluegray);
    --accent-3: var(--p360-slate);
    --brand-red: var(--p360-red);
    /* Safety: nunca permitir floats colgando que “empujen” elementos del layout */
    position: relative;
  }
  .mod-carritos::after{ content:""; display:block; clear:both }

  /* Safety: cualquier “legal” que por error se pinte dentro del header del módulo se oculta aquí.
     El layout lo volverá a insertar en el footer. */
  #page-header .legal, #page-header .copyright, #page-header [data-copyright]{ display:none!important }

  .mod-carritos .page { display:grid; gap:14px }
  .mod-carritos .card{
    background:var(--card-bg); border:1px solid var(--card-border);
    border-radius:14px; box-shadow:var(--shadow-1);
  }
  .mod-carritos .card-b{ padding:12px 14px }

  /* ====== HERO encabezado ====== */
  .mod-carritos .hero{
    position:relative; overflow:hidden; border-radius:16px;
    border:1px solid var(--card-border);
    background:
      radial-gradient(90% 120% at 0% 0%,
        color-mix(in oklab, var(--accent) 16%, transparent),
        transparent 54%),
      radial-gradient(80% 120% at 100% 0%,
        color-mix(in oklab, var(--accent-2) 18%, transparent),
        transparent 56%),
      var(--card-bg);
    box-shadow: var(--shadow-2);
  }
  .mod-carritos .hero-inner{
    display:grid; grid-template-columns:1fr auto; gap:12px; align-items:center;
    padding:16px 14px;
  }
  .mod-carritos .titlebox{ display:flex; align-items:center; gap:12px }
  .mod-carritos .title-ico{
    width:38px; height:38px; display:grid; place-items:center; color:#fff; border-radius:12px;
    background: linear-gradient(135deg,var(--accent), color-mix(in oklab, var(--accent-2) 70%, black 30%));
    box-shadow: 0 12px 28px color-mix(in oklab, var(--accent) 24%, transparent);
  }
  .mod-carritos .title h1{ margin:0; font:800 18px/1.2 system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial; color:var(--text) }
  .mod-carritos .title small{ display:block; color:var(--muted); margin-top:2px; font-size:12px }

  .mod-carritos .kpirow{ display:flex; align-items:center; gap:10px; flex-wrap:wrap; justify-content:flex-end }
  .mod-carritos .kpi{
    --ring: color-mix(in oklab, var(--accent) 55%, transparent);
    position:relative; padding:8px 12px; border-radius:999px; font:800 12px/1 system-ui; color:var(--text);
    background: linear-gradient(180deg, color-mix(in oklab, var(--accent) 12%, transparent), transparent);
    border:1px solid color-mix(in oklab, var(--accent) 34%, transparent);
  }
  .mod-carritos .kpi::after{
    content:""; position:absolute; inset:-2px; border-radius:inherit; pointer-events:none;
    background: radial-gradient(120% 120% at 100% 0%, var(--ring), transparent 60%);
    -webkit-mask: radial-gradient(120% 120% at 100% 0%, #000 40%, transparent 60%);
            mask: radial-gradient(120% 120% at 100% 0%, #000 40%, transparent 60%);
    opacity:.45;
  }

  /* ====== TABS rápidos ====== */
  .mod-carritos .seg-tabs{
    display:flex; gap:8px; flex-wrap:wrap; align-items:center;
    padding:10px 14px; border-top:1px solid var(--card-border); background: color-mix(in oklab, var(--card-bg) 94%, transparent);
  }
  .mod-carritos .seg{
    display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border-radius:999px;
    border:1px solid var(--card-border); background:var(--card-bg); color:var(--text); text-decoration:none; font:800 12px/1 system-ui;
    transition:filter .15s ease, transform .1s ease;
  }
  .mod-carritos .seg strong{ font:800 12px/1 system-ui }
  .mod-carritos .seg:hover{ filter:brightness(1.05) }
  .mod-carritos .seg:active{ transform:translateY(1px) }
  .mod-carritos .seg.active{
    background: linear-gradient(135deg,var(--accent), color-mix(in oklab, var(--accent-2) 70%, black 30%));
    color:#fff; border-color:transparent; box-shadow: 0 10px 20px color-mix(in oklab, var(--accent) 25%, transparent);
  }

  /* ====== Toolbar ====== */
  .mod-carritos .toolbar{ display:flex; gap:10px; flex-wrap:wrap; align-items:center }
  .mod-carritos .search{
    display:flex; align-items:center; gap:8px; min-width:280px;
    padding:10px 12px; border:1px solid var(--card-border); border-radius:12px;
    background:var(--panel-bg); color:var(--muted)
  }
  .mod-carritos .search input{ background:transparent; border:0; outline:0; color:var(--text); min-width:220px }
  .mod-carritos .select{
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
    background:linear-gradient(135deg,var(--accent), color-mix(in oklab, var(--accent-2) 72%, black 28%));
    color:#fff; border:0; box-shadow:0 10px 26px color-mix(in oklab, var(--accent) 24%, transparent);
  }
  .mod-carritos .btn-danger{
    background:linear-gradient(135deg,var(--brand-red), color-mix(in oklab, var(--brand-red) 70%, black 30%)); color:#fff; border:0;
  }
  .mod-carritos .spacer{ flex:1 }

  /* ====== Tabla ====== */
  .mod-carritos .tbl-wrap{ overflow:auto; border-radius:12px; border:1px solid var(--card-border); background:var(--card-bg) }
  .mod-carritos table{ width:100%; border-collapse:separate; border-spacing:0 }
  .mod-carritos th, .mod-carritos td{ padding:10px 12px; border-bottom:1px solid var(--card-border); white-space:nowrap; font-size:14px; color:var(--text) }
  .mod-carritos thead th{
    position:sticky; top:0; z-index:4; text-align:left; font:800 12px/1.1 system-ui; color:var(--muted);
    background:linear-gradient(180deg,var(--card-bg), color-mix(in oklab, var(--card-bg) 88%, transparent));
  }
  .mod-carritos tbody tr:hover{ background: color-mix(in oklab, var(--accent) 8%, transparent) }
  .mod-carritos tbody tr:nth-child(odd){ background: color-mix(in oklab, var(--text) 3%, transparent) }
  .mod-carritos .num{ text-align:right; font-variant-numeric: tabular-nums }

  /* Sort visual */
  .mod-carritos .sortable{ cursor:pointer; user-select:none }
  .mod-carritos .sortable .arrow{ opacity:.4; margin-left:6px; font-size:11px }
  .mod-carritos .sorted-asc .arrow{ opacity:1; transform:rotate(180deg) }
  .mod-carritos .sorted-desc .arrow{ opacity:1 }

  /* Badges de estado */
  .mod-carritos .status{ display:inline-flex; align-items:center; gap:6px; padding:6px 10px;
    border-radius:999px; font:800 12px/1 system-ui; border:1px solid transparent }
  .mod-carritos .status .dot{ width:8px; height:8px; border-radius:999px; background:currentColor }
  .mod-carritos .is-open{ color:#1d4ed8; background: color-mix(in oklab, #1d4ed8 16%, transparent); border-color: color-mix(in oklab, #1d4ed8 32%, transparent) }
  .mod-carritos .is-new{ color:#0891b2; background: color-mix(in oklab, #0891b2 16%, transparent); border-color: color-mix(in oklab, #0891b2 32%, transparent) }
  .mod-carritos .is-converted{ color:#16a34a; background: color-mix(in oklab, #16a34a 16%, transparent); border-color: color-mix(in oklab, #16a34a 28%, transparent) }
  .mod-carritos .is-cancelled{ color:var(--brand-red); background: color-mix(in oklab, var(--brand-red) 14%, transparent); border-color: color-mix(in oklab, var(--brand-red) 30%, transparent) }

  /* Acciones */
  .mod-carritos .actions{ display:flex; gap:6px; align-items:center }
  .mod-carritos .btn-xs{
    font:800 11px/1 system-ui; padding:6px 8px; border-radius:10px; border:1px solid var(--card-border);
    background:var(--card-bg); color:var(--text); text-decoration:none; cursor:pointer
  }
  .mod-carritos .btn-xs.edit{ border-color: color-mix(in oklab, var(--accent) 45%, transparent) }
  .mod-carritos .btn-xs.danger{ border-color: color-mix(in oklab, var(--brand-red) 45%, transparent); color: var(--p360-red-700) }

  .mod-carritos .empty{ padding:24px; text-align:center; color:var(--muted) }
  .mod-carritos .bulkbar{ display:flex; gap:8px; align-items:center; padding:10px 12px; border-radius:12px;
    background: color-mix(in oklab, var(--accent) 12%, transparent); border:1px dashed color-mix(in oklab, var(--accent) 30%, transparent)
  }

  /* Responsive */
  @media (max-width: 1100px){
    .mod-carritos .hero-inner{ grid-template-columns:1fr }
    .mod-carritos .kpirow{ justify-content:flex-start }
    .mod-carritos .search{ min-width:220px }
  }
  @media (max-width: 900px){
    .mod-carritos thead th:nth-child(6), .mod-carritos tbody td:nth-child(6){ display:none } /* Moneda */
    .mod-carritos thead th:nth-child(7), .mod-carritos tbody td:nth-child(7){ display:none } /* Origen */
  }

  /* ===== Paginación (brand) ===== */
  .mod-carritos .mono{ font-variant-numeric: tabular-nums }
  .mod-carritos .pager .card-b{ padding:10px 14px }
  .mod-carritos .pagin{
    display:flex; align-items:center; justify-content:space-between;
    gap:12px; flex-wrap:wrap;
  }
  .mod-carritos .pagin-left{ color:var(--muted); font-size:12px }
  .mod-carritos .pagin-left strong{ color:var(--text) }

  /* --- Paginador Tailwind (por defecto de Laravel) --- */
  .mod-carritos .pagin-right nav{ display:flex; width:100% }
  .mod-carritos .pagin-right nav > div:first-child{ display:none } /* ocultamos “Showing …” duplicado */
  .mod-carritos .pagin-right nav > div:last-child{ margin-left:auto }
  .mod-carritos .pagin-right nav ul{
    list-style:none; display:flex; gap:6px; margin:0; padding:0;
  }
  .mod-carritos .pagin-right nav a,
  .mod-carritos .pagin-right nav span{
    display:inline-flex; align-items:center; justify-content:center;
    min-width:36px; padding:8px 10px; border-radius:10px;
    border:1px solid var(--card-border); background:var(--card-bg); color:var(--text);
    text-decoration:none; font:800 12px/1 system-ui;
  }
  .mod-carritos .pagin-right nav span[aria-current="page"]{
    background:linear-gradient(135deg,var(--accent), color-mix(in oklab, var(--accent-2) 70%, black 30%));
    color:#fff; border-color:transparent;
  }
  .mod-carritos .pagin-right nav span[aria-disabled="true"]{ opacity:.45; cursor:not-allowed }

  /* --- Bootstrap (por si tu proyecto lo usa) --- */
  .mod-carritos .pagination{ list-style:none; display:flex; gap:6px; margin:0; padding:0 }
  .mod-carritos .page-link{
    display:inline-flex; align-items:center; justify-content:center;
    min-width:36px; padding:8px 10px; border-radius:10px;
    border:1px solid var(--card-border); background:var(--card-bg); color:var(--text);
    text-decoration:none; font:800 12px/1 system-ui;
  }
  .mod-carritos .page-item.active .page-link{
    background:linear-gradient(135deg,var(--accent), color-mix(in oklab, var(--accent-2) 70%, black 30%));
    color:#fff; border-color:transparent;
  }
  .mod-carritos .page-item.disabled .page-link{ opacity:.45; cursor:not-allowed }
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
      $estadosMap = ['abierto'=>'Abierto','convertido'=>'Convertido','cancelado'=>'Cancelado','nuevo'=>'Nuevo'];
    }
  }

  $q       = request('q','');
  $estadoF = request('estado', request('est',''));
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

  $canCreate      = \Illuminate\Support\Facades\Route::has('admin.empresas.pactopia360.crm.carritos.create');
  $canEdit        = \Illuminate\Support\Facades\Route::has('admin.empresas.pactopia360.crm.carritos.edit');
  $canDestroy     = \Illuminate\Support\Facades\Route::has('admin.empresas.pactopia360.crm.carritos.destroy');
  $canExport      = \Illuminate\Support\Facades\Route::has('admin.empresas.pactopia360.crm.carritos.export');
  $canDestroyBulk = \Illuminate\Support\Facades\Route::has('admin.empresas.pactopia360.crm.carritos.destroy.bulk');

  $exportUrl = $canExport ? route('admin.empresas.pactopia360.crm.carritos.export', request()->query()) : null;

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
    <div class="seg-tabs" role="tablist" aria-label="Filtros rápidos por estado">
      @php $act = $estadoF ?: ''; @endphp
      <a class="seg {{ $act==='' ? 'active':'' }}" role="tab" aria-selected="{{ $act==='' ? 'true':'false' }}" href="{{ $tabUrl('') }}">Todos <strong>{{ number_format($kTotal) }}</strong></a>
      <a class="seg {{ $act==='abierto' ? 'active':'' }}" role="tab" aria-selected="{{ $act==='abierto' ? 'true':'false' }}" href="{{ $tabUrl('abierto') }}">Abiertos <strong>{{ number_format($kAbiertos) }}</strong></a>
      <a class="seg {{ $act==='convertido' ? 'active':'' }}" role="tab" aria-selected="{{ $act==='convertido' ? 'true':'false' }}" href="{{ $tabUrl('convertido') }}">Convertidos <strong>{{ number_format($kConvertidos) }}</strong></a>
      <a class="seg {{ $act==='cancelado' ? 'active':'' }}" role="tab" aria-selected="{{ $act==='cancelado' ? 'true':'false' }}" href="{{ $tabUrl('cancelado') }}">Cancelados <strong>{{ number_format($kCancelados) }}</strong></a>
    </div>
  </section>

  {{-- Toolbar --}}
  <div class="card" role="region" aria-label="Filtros y Acciones">
    <div class="card-b">
      <form method="get" class="toolbar" action="{{ url()->current() }}" data-pjax-form>
        <label class="search" aria-label="Buscar">🔎
          <input type="search" name="q" value="{{ $q }}" placeholder="Buscar: cliente, email, teléfono…">
        </label>

        <select name="estado" class="select" aria-label="Estado">
          <option value="">Estado (todos)</option>
          @foreach($estadosMap as $k=>$v)
            <option value="{{ $k }}" @selected($estadoF===$k)>{{ $v }}</option>
          @endforeach
        </select>

        <button class="btn" type="submit">Aplicar</button>
        @if($q || $estadoF)
          <a class="btn" href="{{ url()->current() }}">Limpiar</a>
        @endif

        <div class="spacer"></div>

        @if($canExport && $exportUrl)
          <a class="btn" href="{{ $exportUrl }}">CSV</a>
        @endif
        @if($canCreate)
          <a class="btn btn-primary" href="{{ route('admin.empresas.pactopia360.crm.carritos.create') }}">+ Nuevo carrito</a>
        @endif
      </form>
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
    <table class="table">
      <thead>
        <tr>
          @if($canDestroyBulk)
            <th style="width:36px"><input type="checkbox" aria-label="Seleccionar todos" onclick="document.querySelectorAll('.bulk-cb').forEach(cb=>cb.checked=this.checked)"></th>
          @endif
          <th style="width:80px" class="sortable {{ request('sort')==='id' ? ('sorted-'.request('dir','asc')) : '' }}">
            <a href="{{ $sortUrl('id') }}">ID <span class="arrow">▾</span></a>
          </th>
          <th class="sortable {{ request('sort')==='titulo' ? ('sorted-'.request('dir','asc')) : '' }}">
            <a href="{{ $sortUrl('titulo') }}">Título / Cliente <span class="arrow">▾</span></a>
          </th>
          <th style="width:170px" class="sortable {{ request('sort')==='estado' ? ('sorted-'.request('dir','asc')) : '' }}">
            <a href="{{ $sortUrl('estado') }}">Estado <span class="arrow">▾</span></a>
          </th>
          <th style="width:140px" class="sortable {{ request('sort')==='total' ? ('sorted-'.request('dir','asc')) : '' }}">
            <a href="{{ $sortUrl('total') }}">Total <span class="arrow">▾</span></a>
          </th>
          <th style="width:110px">Moneda</th>
          <th style="width:120px">Origen</th>
          <th style="width:160px" class="sortable {{ request('sort')==='created_at' ? ('sorted-'.request('dir','asc')) : '' }}">
            <a href="{{ $sortUrl('created_at') }}">Creado <span class="arrow">▾</span></a>
          </th>
          <th style="width:170px">Acciones</th>
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
              <td><input class="bulk-cb" type="checkbox" name="ids[]" value="{{ $id }}" aria-label="Seleccionar carrito {{ $id }}"></td>
            @endif
            <td class="num mono">#{{ $id }}</td>
            <td>
              <div style="display:flex; flex-direction:column">
                <strong>{{ $titulo }}</strong>
                @if($cliente || $email)
                  <small style="color:var(--muted)">
                    {{ $cliente ?? '' }} @if($cliente && $email) · @endif {{ $email ?? '' }}
                  </small>
                @endif
              </div>
            </td>
            <td>
              <span class="status {{ $cls }}" title="{{ $estadoLbl }}"><span class="dot"></span> {{ $icon }} {{ $estadoLbl }}</span>
            </td>
            <td class="num mono">${{ number_format($total,2) }}</td>
            <td class="mono">{{ $moneda }}</td>
            <td class="mono">{{ strtoupper($origen) }}</td>
            <td class="mono">{{ $fecha }}</td>
            <td>
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
            <td colspan="{{ 9 - (int)!$canDestroyBulk }}" class="empty">
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
/* ===== Footer Rebind (por si el módulo cargó antes que el enforcer del layout) ===== */
(function(){
  try{
    // Fuerza un sweep del “legal enforcer” del layout para mover ©/links al footer
    window.dispatchEvent(new CustomEvent('p360:pjax:after'));
    // Safety: asegúrate de que el footer esté al fondo (layout ya lo maneja, esto refuerza).
    const footer = document.querySelector('.admin-footer');
    if (footer){
      footer.style.removeProperty('position');
      footer.style.marginTop = 'auto';
    }
  }catch(_){}
})();
</script>
@endpush
