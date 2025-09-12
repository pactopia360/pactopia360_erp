@extends('layouts.admin')

@section('title','CRM · Carritos')

@push('styles')
  <style id="css-carritos">
    .page { display: grid; gap: 14px; }
    .card { background: var(--card-bg,#fff); border:1px solid var(--card-border,rgba(0,0,0,.08)); border-radius:14px; box-shadow:0 2px 10px rgba(0,0,0,.04); }
    .card-h { padding:12px 14px; border-bottom:1px solid var(--card-border,rgba(0,0,0,.08)); display:flex; align-items:center; gap:10px }
    .card-b { padding:12px 14px }

    .filters { display:flex; gap:8px; flex-wrap:wrap; align-items:center }
    .filters .in, .filters select { height:36px; border-radius:10px; border:1px solid rgba(0,0,0,.12); padding:0 10px; background:transparent }
    .filters .btn { height:36px; border-radius:10px; border:1px solid rgba(0,0,0,.12); background:transparent; padding:0 12px; cursor:pointer }
    .filters .btn.primary { background:var(--accent,#6366f1); color:#fff; border-color:transparent }
    .filters .spacer { flex:1 1 auto }

    .kpis { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:8px }
    .kpi { background:var(--panel-bg,#f8fafc); border:1px dashed rgba(0,0,0,.08); border-radius:12px; padding:10px 12px }
    .kpi .lbl { font:600 12px/1 system-ui; color:var(--muted,#6b7280); margin-bottom:6px }
    .kpi .val { font:700 18px/1.1 system-ui }

    .tbl-wrap { overflow:auto; border-radius:12px; border:1px solid var(--card-border,rgba(0,0,0,.08)); background:var(--card-bg,#fff) }
    table { width:100%; border-collapse:separate; border-spacing:0 }
    thead th { position:sticky; top:0; background:var(--panel-bg,#f8fafc); font:600 12px/1.1 system-ui; color:var(--muted,#6b7280); text-align:left; padding:10px 12px; border-bottom:1px solid var(--card-border,rgba(0,0,0,.08)) }
    tbody td { padding:10px 12px; border-bottom:1px solid rgba(0,0,0,.05) }
    tbody tr:hover td { background:rgba(0,0,0,.03) }
    [data-theme="dark"] tbody tr:hover td { background:rgba(255,255,255,.04) }

    .bad { display:inline-flex; align-items:center; padding:3px 8px; border-radius:999px; font:700 11px/1 system-ui }
    .bad.abierto     { background:rgba(59,130,246,.14); color:#1d4ed8 }
    .bad.convertido  { background:rgba(16,185,129,.18); color:#047857 }
    .bad.cancelado   { background:rgba(239,68,68,.18); color:#991b1b }
    .bad.nuevo       { background:rgba(234,179,8,.18);  color:#92400e }

    .actions { display:flex; gap:6px; }
    .btn-xs { font:600 11px/1 system-ui; padding:6px 8px; border-radius:8px; border:1px solid rgba(0,0,0,.12); background:transparent; cursor:pointer; text-decoration:none; color:inherit }
    .btn-xs.edit { border-color:rgba(99,102,241,.45) }
    .btn-xs.danger { border-color:rgba(239,68,68,.45); color:#b91c1c }

    .mono { font-variant-numeric: tabular-nums; }
    .muted { color:var(--muted,#6b7280) }

    .empty { padding:18px; text-align:center; color:var(--muted,#6b7280) }

    .topbar { display:flex; gap:8px; align-items:center; flex-wrap:wrap }
    .topbar .title { font:700 16px/1.1 system-ui }
    .topbar .right { margin-left:auto; display:flex; gap:8px }

    @media (max-width: 900px){
      .kpis { grid-template-columns:repeat(2,minmax(0,1fr)); }
      thead th:nth-child(5), tbody td:nth-child(5) { display:none } /* Moneda */
      thead th:nth-child(6), tbody td:nth-child(6) { display:none } /* Origen */
    }
  </style>
@endpush

@section('content')
@php
  // ========= NORMALIZACIÓN DE DATOS =========
  // La colección puede venir como $rows, $carritos, $items o $data.
  $rows = $rows
      ?? $carritos
      ?? $items
      ?? $data
      ?? collect();

  // Mapa de estados: usa el que venga ($estadosMap o $estados), si no, por defecto.
  if (!isset($estadosMap) || !is_array($estadosMap) || !count($estadosMap)) {
      if (isset($estados) && is_array($estados) && count($estados)) {
          // Convierte ['abierto','convertido'] -> ['abierto'=>'Abierto','convertido'=>'Convertido']
          $estadosMap = [];
          foreach ($estados as $e) { $estadosMap[strtolower($e)] = ucfirst($e); }
      } else {
          $estadosMap = [
              'abierto'    => 'Abierto',
              'convertido' => 'Convertido',
              'cancelado'  => 'Cancelado',
              'nuevo'      => 'Nuevo',
          ];
      }
  }

  // Filtros (el controlador usa "q" y "estado")
  $q       = request('q','');
  $estadoF = request('estado', request('est','')); // por compatibilidad con la versión previa

  // KPI (funciona con paginator o con colección simple)
  $collection = ($rows instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
      ? $rows->getCollection()
      : collect($rows);

  $kTotal       = $collection->count();
  $kAbiertos    = $collection->filter(fn($r)=>strtolower(($r->estado ?? $r->status ?? ''))==='abierto')->count();
  $kConvertidos = $collection->filter(fn($r)=>strtolower(($r->estado ?? $r->status ?? ''))==='convertido')->count();
  $kCancelados  = $collection->filter(fn($r)=>strtolower(($r->estado ?? $r->status ?? ''))==='cancelado')->count();

  // Rutas opcionales
  $canCreate  = \Illuminate\Support\Facades\Route::has('admin.empresas.pactopia360.crm.carritos.create');
  $canEdit    = \Illuminate\Support\Facades\Route::has('admin.empresas.pactopia360.crm.carritos.edit');
  $canDestroy = \Illuminate\Support\Facades\Route::has('admin.empresas.pactopia360.crm.carritos.destroy');
@endphp

<div class="page" data-module="crm-carritos">
  <div class="card">
    <div class="card-h topbar">
      <div class="title">Carritos</div>
      <div class="right">
        @if($canCreate)
          <a class="btn-xs" href="{{ route('admin.empresas.pactopia360.crm.carritos.create') }}">+ Nuevo carrito</a>
        @endif
      </div>
    </div>
    <div class="card-b">
      <form method="get" class="filters" action="{{ url()->current() }}" data-pjax-form>
        <input class="in" type="search" name="q" value="{{ $q }}" placeholder="Buscar: cliente, email, teléfono…">
        <select name="estado" class="in">
          <option value="">Estado</option>
          @foreach($estadosMap as $k=>$v)
            <option value="{{ $k }}" @selected($estadoF===$k)>{{ $v }}</option>
          @endforeach
        </select>
        <button class="btn primary" type="submit">Buscar</button>
        @if($q || $estadoF)
          <a class="btn" href="{{ url()->current() }}">Limpiar</a>
        @endif
        <div class="spacer"></div>
      </form>
    </div>
  </div>

  <div class="kpis">
    <div class="kpi"><div class="lbl">Total</div><div class="val mono">{{ number_format($kTotal) }}</div></div>
    <div class="kpi"><div class="lbl">Abiertos</div><div class="val mono">{{ number_format($kAbiertos) }}</div></div>
    <div class="kpi"><div class="lbl">Convertidos</div><div class="val mono">{{ number_format($kConvertidos) }}</div></div>
    <div class="kpi"><div class="lbl">Cancelados</div><div class="val mono">{{ number_format($kCancelados) }}</div></div>
  </div>

  <div class="tbl-wrap">
    <table>
      <thead>
        <tr>
          <th style="width:70px">ID</th>
          <th>Título / Cliente</th>
          <th style="width:140px">Estado</th>
          <th style="width:140px">Total</th>
          <th style="width:100px">Moneda</th>
          <th style="width:120px">Origen</th>
          <th style="width:150px">Creado</th>
          <th style="width:160px">Acciones</th>
        </tr>
      </thead>
      <tbody>
      @forelse($rows as $r)
        @php
          $id     = $r->id ?? $r->carrito_id ?? null;
          $titulo = $r->titulo ?? $r->title ?? $r->nombre ?? ('Cliente '.$id);
          $cliente= $r->cliente ?? $r->cliente_nombre ?? $r->contacto ?? null;
          $email  = $r->email ?? $r->cliente_email ?? null;
          $estado = strtolower($r->estado ?? $r->status ?? '');
          $estadoLbl = $estadosMap[$estado] ?? ucfirst($estado ?: '—');
          $total  = $r->total ?? $r->monto ?? 0;
          $moneda = $r->moneda ?? $r->currency ?? 'MXN';
          $origen = $r->origen ?? $r->source ?? '—';
          $fecha  = \Illuminate\Support\Carbon::parse($r->created_at ?? $r->creado ?? now())->format('Y-m-d');
          $cls    = in_array($estado,['abierto','convertido','cancelado','nuevo']) ? $estado : 'nuevo';

          $editUrl    = $canEdit    ? route('admin.empresas.pactopia360.crm.carritos.edit',$id)    : '#';
          $destroyUrl = $canDestroy ? route('admin.empresas.pactopia360.crm.carritos.destroy',$id) : null;
        @endphp
        <tr>
          <td class="mono">{{ $id }}</td>
          <td>
            <div style="display:flex; flex-direction:column">
              <strong>{{ $titulo }}</strong>
              @if($cliente || $email)
                <small class="muted">
                  {{ $cliente ?? '' }} @if($cliente && $email) · @endif {{ $email ?? '' }}
                </small>
              @endif
            </div>
          </td>
          <td><span class="bad {{ $cls }}">{{ $estadoLbl }}</span></td>
          <td class="mono">${{ number_format((float)$total, 2) }}</td>
          <td class="mono">{{ $moneda }}</td>
          <td class="mono">{{ $origen }}</td>
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
        <tr><td colspan="8" class="empty">Sin carritos.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>

  @if($rows instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
    <div class="card"><div class="card-b">{{ $rows->withQueryString()->links() }}</div></div>
  @endif
</div>
@endsection
