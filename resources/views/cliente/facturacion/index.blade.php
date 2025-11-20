{{-- resources/views/cliente/facturacion/index.blade.php
     v3 · Dashboard + lista CFDI
--}}
@extends('layouts.cliente')
@section('title','Facturación · Pactopia360')

@push('styles')
<style>
  .page{display:grid;gap:16px;min-width:0;}
  .head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;min-width:0;}
  .title{margin:0;font:900 20px/1.1 "Poppins",system-ui;}

  .btn{
    display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:12px;
    font-weight:800;text-decoration:none;border:1px solid #f3d5dc;
    color:#E11D48;background:#fff;white-space:nowrap;
  }
  .btn.primary{
    background:linear-gradient(90deg,#E11D48,#BE123C);border:0;color:#fff;
    box-shadow:0 8px 20px rgba(225,29,72,.18);
  }

  .card{background:#fff;border:1px solid #f3d5dc;border-radius:14px;padding:14px;}

  /* KPIs */
  .kpis{display:grid;gap:12px;}
  @media(min-width:960px){.kpis{grid-template-columns:repeat(4,minmax(0,1fr));}}
  .kpi{
    border-radius:12px;border:1px solid #fee2e2;
    padding:10px 12px;background:#fff7f9;display:grid;gap:4px;
  }
  .kpi-label{font-size:11px;text-transform:uppercase;letter-spacing:.12em;color:#6b7280;font-weight:800;}
  .kpi-value{font-size:18px;font-weight:900;color:#E11D48;}
  .kpi-sub{font-size:11px;color:#6b7280;}

  /* Filtros */
  .filters{display:grid;gap:10px;align-items:end;min-width:0;}
  @media (min-width:1120px){
    .filters{
      grid-template-columns:minmax(0,1.4fr) minmax(140px,220px) minmax(180px,260px) auto;
    }
  }
  @media (max-width:1119.98px){
    .filters{grid-template-columns:minmax(0,1.2fr) minmax(180px,220px);}
  }
  .act-row{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-start;}
  .label{font-size:12px;color:#6b7280;font-weight:800;margin-bottom:6px;}
  .in{width:100%;padding:10px 12px;border:1px solid #f3d5dc;border-radius:10px;min-width:0;}

  /* Tabla */
  .table-wrap{padding:0;overflow:auto;border-radius:14px;}
  table{width:100%;min-width:760px;border-collapse:collapse;}
  th,td{padding:10px 12px;border-bottom:1px solid #f3d5dc;text-align:left;}
  th{
    background:#fff0f3;color:#6b7280;font-size:12px;font-weight:900;
    text-transform:uppercase;white-space:nowrap;
  }
  .right{text-align:right;}
  .muted{color:#6b7280;}

  .badge{display:inline-flex;align-items:center;padding:4px 8px;border-radius:999px;font:800 12px/1 system-ui;}
  .b-borr{background:#fff0f3;color:#BE123C;border:1px solid #f3d5dc;}
  .b-emi{background:#ecfdf5;color:#047857;border:1px solid #86efac;}
  .b-canc{background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;}

  .tbl-actions{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;}
  .link{color:#E11D48;text-decoration:none;font-weight:800;white-space:nowrap;}
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
@endphp

<div class="page">
  <div class="head">
    <h1 class="title">Facturación</h1>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      @if(Route::has('cliente.emisores.index'))
        <a class="btn" href="{{ route('cliente.emisores.index') }}">Emisores</a>
      @endif
      @if(Route::has('cliente.receptores.index'))
        <a class="btn" href="{{ route('cliente.receptores.index') }}">Receptores</a>
      @endif>
      <a class="btn primary" href="{{ route('cliente.facturacion.nuevo') }}">+ Nuevo CFDI</a>
    </div>
  </div>

  {{-- Flash/errores --}}
  @if(session('ok'))
    <div class="card" style="background:#ecfdf5;border-color:#86efac;color:#047857">
      <strong>{{ session('ok') }}</strong>
    </div>
  @endif
  @if($errors->any())
    <div class="card" style="background:#fef2f2;border-color:#fecaca;color:#b91c1c">
      <strong>{{ $errors->first() }}</strong>
    </div>
  @endif

  {{-- KPIs --}}
  <div class="card">
    <div class="kpis">
      <div class="kpi">
        <div class="kpi-label">Total periodo</div>
        <div class="kpi-value">${{ number_format((float)($k['total_periodo'] ?? 0),2) }}</div>
        <div class="kpi-sub">
          @if(isset($k['period']['from'],$k['period']['to']))
            {{ \Carbon\Carbon::parse($k['period']['from'])->format('d/m') }} —
            {{ \Carbon\Carbon::parse($k['period']['to'])->format('d/m/Y') }}
          @else
            Periodo actual
          @endif
        </div>
      </div>
      <div class="kpi">
        <div class="kpi-label">Emitidos</div>
        <div class="kpi-value">${{ number_format((float)($k['emitidos'] ?? 0),2) }}</div>
        <div class="kpi-sub">CFDI timbrados en el periodo</div>
      </div>
      <div class="kpi">
        <div class="kpi-label">Cancelados</div>
        <div class="kpi-value">${{ number_format((float)($k['cancelados'] ?? 0),2) }}</div>
        <div class="kpi-sub">Importe de CFDI cancelados</div>
      </div>
      <div class="kpi">
        <div class="kpi-label">Variación vs periodo previo</div>
        @php $d = (float)($k['delta_total'] ?? 0); @endphp
        <div class="kpi-value" style="color:{{ $d>=0 ? '#16a34a' : '#b91c1c' }}">
          {{ $d >= 0 ? '▲' : '▼' }} {{ number_format($d,2) }}%
        </div>
        <div class="kpi-sub">Comparado con el periodo anterior</div>
      </div>
    </div>
  </div>

  {{-- Filtros --}}
  <form method="GET" action="{{ route('cliente.facturacion.index') }}" class="card">
    <div class="filters">
      <div style="min-width:0">
        <div class="label">Búsqueda</div>
        <input class="in" type="text" name="q" value="{{ $q }}" placeholder="UUID / Serie / Folio">
      </div>
      <div>
        <div class="label">Estatus</div>
        <select class="in" name="status">
          @php $st = [''=>'Todos','borrador'=>'Borrador','emitido'=>'Emitido','cancelado'=>'Cancelado']; @endphp
          @foreach($st as $k=>$v)
            <option value="{{ $k }}" @selected($status===$k)>{{ $v }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <div class="label">Mes (YYYY-MM)</div>
        <input class="in" type="month" name="month" value="{{ $month }}">
      </div>
      <div class="act-row">
        <button class="btn" type="submit">Aplicar</button>
        <a class="btn" href="{{ route('cliente.facturacion.index') }}">Limpiar</a>
        <a class="btn" href="{{ route('cliente.facturacion.export', array_filter(['q'=>$q,'status'=>$status,'month'=>$month,'mes'=>$mes,'anio'=>$anio])) }}">
          Exportar CSV
        </a>
      </div>
    </div>
  </form>

  {{-- Tabla --}}
  <div class="card table-wrap">
    <table>
      <thead>
        <tr>
          <th>Serie/Folio</th>
          <th>Fecha</th>
          <th>Emisor</th>
          <th>Estatus</th>
          <th class="right">Total</th>
          <th class="right">Acciones</th>
        </tr>
      </thead>
      <tbody>
        @forelse($cfdis as $row)
          @php
            $serieFolio = trim(($row->serie?($row->serie.'-'):'').($row->folio??''),'- ') ?: '—';
            $st = strtolower((string)($row->estatus ?? ''));
          @endphp
          <tr>
            <td>
              <strong>{{ $serieFolio }}</strong>
              <div class="muted" style="font-size:12px">{{ $row->uuid ?: '—' }}</div>
            </td>
            <td>{{ optional($row->fecha)->format('Y-m-d H:i') ?: '—' }}</td>
            <td>{{ optional($row->cliente)->razon_social ?? optional($row->cliente)->nombre_comercial ?? '—' }}</td>
            <td>
              @if($st==='emitido')   <span class="badge b-emi">EMITIDO</span>
              @elseif($st==='cancelado') <span class="badge b-canc">CANCELADO</span>
              @else <span class="badge b-borr">BORRADOR</span>
              @endif
            </td>
            <td class="right">${{ number_format((float)($row->total ?? 0),2) }}</td>
            <td class="right">
              <div class="tbl-actions">
                <a class="link" href="{{ route('cliente.facturacion.show', $row->id) }}">Ver</a>
                @if($st==='borrador')
                  <a class="link" href="{{ route('cliente.facturacion.edit', $row->id) }}">Editar</a>
                @endif
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="muted" style="padding:14px">No hay CFDIs en el periodo seleccionado.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  {{-- Paginación --}}
  <div>
    {{ $cfdis->onEachSide(1)->links() }}
  </div>
</div>
@endsection
