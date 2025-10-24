{{-- resources/views/cliente/facturacion/index.blade.php (v4 · UI alineada al mock · fix: sin @json en comentarios) --}}
@extends('layouts.client')
@section('title','Facturación · Pactopia360')

@push('styles')
<style>
  /* ======= LOOK & FEEL DE LA VISTA (sólo esta pantalla) ======= */
  :root{
    --fx-card: 0 6px 20px rgba(0,0,0,.06);
    --kpi-grad: linear-gradient(180deg,#f7ecff 0%, #ffffff 55%);
    --kpi-ring: #e7d5ff;
    --green: #16a34a;
    --red: #ef4444;
    --muted-2:#6b7280;
    --line-2:#e5e7eb;
    --chip-2:#f8fafc;
    --pill-h: 38px;
    --rad: 14px;
  }

  .page-header{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:12px}
  .page-header h1{margin:0;font:900 20px/1.1 ui-sans-serif,system-ui}

  .toolbar{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
  .pill{display:inline-flex;align-items:center;gap:8px;height:var(--pill-h);padding:0 12px;background:var(--chip-2);
        border:1px solid var(--line-2);border-radius:999px;box-shadow:var(--fx-card)}
  .pill input{all:unset;width:min(46vw,520px);font-weight:700;letter-spacing:.1px}
  .pill select{all:unset;font-weight:800}

  .segmented{display:inline-flex;border:1px solid var(--line-2);border-radius:999px;background:#fff;box-shadow:var(--fx-card)}
  .segmented a{padding:8px 12px;font-weight:900;color:inherit;text-decoration:none;border-radius:999px}
  .segmented a.active{background:color-mix(in oklab, var(--brand) 18%, transparent);}

  .btn{display:inline-flex;align-items:center;gap:8px;height:var(--pill-h);padding:0 12px;border-radius:999px;border:1px solid var(--line-2);
       background:var(--chip-2);text-decoration:none;color:inherit;font-weight:900;box-shadow:var(--fx-card)}
  .btn--primary{background:linear-gradient(180deg,#2563eb,#1649c6);border-color:#1f5eea;color:#fff}
  .btn--success{background:linear-gradient(180deg,#1ec162,#119b48);border-color:#0c8a3f;color:#052d18}
  .btn svg{width:16px;height:16px}

  .kpi-grid{display:grid;grid-template-columns:1.25fr 1fr;gap:12px}
  .kpi-cards{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
  .kcard{background:#fff;border:1px solid var(--line-2);border-radius:var(--rad);padding:14px;box-shadow:var(--fx-card)}
  .kcard.primary{background:var(--kpi-grad);outline:2px solid var(--kpi-ring);outline-offset:-1px}
  .k-amount{font:900 28px/1 ui-sans-serif}
  .k-sub{display:flex;justify-content:space-between;gap:8px;margin-top:4px;color:var(--muted-2);font-weight:800}
  .k-foot{margin-top:6px;color:var(--muted-2);font-weight:900}
  .delta{font-weight:900}
  .delta.up{color:var(--green)} .delta.down{color:var(--red)} .delta.flat{color:var(--muted-2)}

  .chart-card{background:#fff;border:1px solid var(--line-2);border-radius:var(--rad);padding:10px 12px;box-shadow:var(--fx-card)}
  .chart-title{margin:0 0 6px;color:var(--muted-2);font:800 12px/1 ui-sans-serif;letter-spacing:.2px;text-transform:uppercase}
  .chart-wrap{height:220px}
  svg.chart{width:100%;height:100%;display:block}

  .table-card{background:#fff;border:1px solid var(--line-2);border-radius:var(--rad);box-shadow:var(--fx-card);margin-top:12px}
  .thead{display:flex;justify-content:space-between;align-items:center;padding:12px;border-bottom:1px solid var(--line-2)}
  .thead h3{margin:0;font:800 12px/1 ui-sans-serif;color:var(--muted-2);text-transform:uppercase}

  table.list{width:100%;border-collapse:separate;border-spacing:0}
  table.list th, table.list td{padding:12px;border-bottom:1px solid var(--line-2);text-align:left;font-size:14px}
  table.list th{color:var(--muted-2);font-weight:900}
  table.list tbody tr:nth-child(odd){background:#f2f7ff} /* zebra azul suave */
  .right{text-align:right}
  .uuid{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12px}
</style>
@endpush

@section('content')
@php
  // ======= Datos seguros para filtros =======
  $filters    = (isset($filters) && is_array($filters)) ? $filters : (request()->all() ?? []);
  $monthParam = $filters['month'] ?? now()->format('Y-m');
  $mesSel     = (int)($filters['mes']  ?? \Carbon\Carbon::parse($monthParam.'-01')->month);
  $anioSel    = (int)($filters['anio'] ?? \Carbon\Carbon::parse($monthParam.'-01')->year);
  $qParam      = trim((string)($filters['q'] ?? ''));
  $statusParam = (string)($filters['status'] ?? '');

  $kp   = $kpis ?? ['total_periodo'=>0,'emitidos'=>0,'cancelados'=>0,'delta_total'=>0];
  $d    = (float)($kp['delta_total'] ?? 0);
  $dCls = $d>0?'up':($d<0?'down':'flat'); $dSign = $d>0?'▲':($d<0?'▼':'—');

  $totalRows = method_exists($cfdis,'total') ? $cfdis->total() : 0;
  $rangeText = $totalRows ? ($cfdis->firstItem().'–'.$cfdis->lastItem().' de '.$totalRows) : '0 de 0';

  // Parámetros que reusaremos en enlaces
  $common = request()->only(['q','mes','anio']);
  $statusUrl = function($st) use ($common){
      $q = array_merge($common, ['status'=>$st ?: null]); // null => quitar
      return url()->current().'?'.http_build_query(array_filter($q, fn($v)=>$v!==null && $v!==''));
  };

  $exportParams = request()->only(['q','status','mes','anio']);
  $queryParams  = request()->only(['q','status','mes','anio','month']);
  $queryString  = http_build_query($queryParams);
@endphp

<div class="page-header">
  <h1>Facturación</h1>

  <div class="toolbar">
    {{-- Buscar --}}
    <form id="filtersForm" method="GET" action="{{ route('cliente.facturacion.index') }}" class="toolbar">
      <div class="pill" title="Buscar por receptor / UUID / Serie-Folio">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M21 21l-4.3-4.3M10 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16z"/></svg>
        <input type="text" name="q" value="{{ $qParam }}" placeholder="Buscar por receptor, folio, UUID…"/>
      </div>

      {{-- Segmentado: Todos/Emitidos/Cancelados --}}
      <div class="segmented" style="margin-left:6px">
        <a href="{{ $statusUrl('') }}"          class="{{ $statusParam===''?'active':'' }}">Todos</a>
        <a href="{{ $statusUrl('emitido') }}"   class="{{ $statusParam==='emitido'?'active':'' }}">Emitidos</a>
        <a href="{{ $statusUrl('cancelado') }}" class="{{ $statusParam==='cancelado'?'active':'' }}">Cancelados</a>
      </div>

      {{-- Mes/Año --}}
      <div class="pill">
        <select name="mes" onchange="this.form.submit()">
          @foreach(range(1,12) as $m)
            <option value="{{ $m }}" {{ $mesSel===$m ? 'selected' : '' }}>
              {{ \Carbon\Carbon::create(null,$m,1)->isoFormat('MMMM') }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="pill">
        <select name="anio" onchange="this.form.submit()">
          @foreach(range(now()->year-4, now()->year+1) as $y)
            <option value="{{ $y }}" {{ $anioSel===$y ? 'selected' : '' }}>{{ $y }}</option>
          @endforeach
        </select>
      </div>

      <a class="btn btn--primary" href="{{ route('cliente.facturacion.export', $exportParams) }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v12m0 0L8 11m4 4l4-4M4 21h16"/></svg>
        Export
      </a>
    </form>

    <a class="btn btn--success" href="{{ route('cliente.facturacion.nuevo') }}">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
      Nuevo
    </a>
  </div>
</div>

<div class="kpi-grid">
  <div class="kpi-cards">
    <div class="kcard primary">
      <div class="k-amount">${{ number_format($kp['total_periodo'] ?? 0, 2) }}</div>
      <div class="k-sub">
        <span>32 Doc</span>
        <span class="delta {{ $dCls }}">{{ $dSign }} {{ number_format($d,2) }}%</span>
      </div>
      <div class="k-foot">En el período</div>
    </div>

    <div class="kcard">
      <div class="k-amount">${{ number_format($kp['emitidos'] ?? 0, 2) }}</div>
      <div class="k-sub"><span>Doc</span><span class="delta up">▲</span></div>
      <div class="k-foot">Pagados</div>
    </div>

    <div class="kcard">
      <div class="k-amount">${{ number_format($kp['cancelados'] ?? 0, 2) }}</div>
      <div class="k-sub"><span>Doc</span><span class="delta down">▼</span></div>
      <div class="k-foot">Cancelados</div>
    </div>
  </div>

  <div class="chart-card">
    <h3 class="chart-title">Facturación</h3>
    <div class="chart-wrap">
      <svg class="chart" id="svgChart" viewBox="0 0 1000 280" preserveAspectRatio="none"></svg>
    </div>
  </div>
</div>

<div class="table-card">
  <div class="thead">
    <h3>Documentos del periodo</h3>
    <div style="color:#6b7280;font-weight:800">Mostrando {{ $rangeText }}</div>
  </div>
  <div style="overflow:auto">
    <table class="list">
      <thead>
        <tr>
          <th>Fecha</th>
          <th>Empresa</th>
          <th>RFC</th>
          <th>Servicio</th>
          <th class="right">Subtotal</th>
          <th class="right">IVA</th>
          <th class="right">total</th>
          <th>UUID</th>
        </tr>
      </thead>
      <tbody>
      @forelse($cfdis as $r)
        @php
          $empresa = $r->cliente->razon_social ?? $r->cliente->nombre_comercial ?? ('ID '.$r->cliente_id);
          $rfc     = $r->cliente->rfc ?? '—';
          $serv    = optional($r->conceptos->first())->descripcion ?? '—';
        @endphp
        <tr>
          <td>{{ optional($r->fecha)->format('d/m/Y') }}</td>
          <td>{{ $empresa }}</td>
          <td>{{ $rfc }}</td>
          <td>{{ \Illuminate\Support\Str::limit($serv, 64) }}</td>
          <td class="right">${{ number_format((float)($r->subtotal ?? 0), 2) }}</td>
          <td class="right">${{ number_format((float)($r->iva ?? 0), 2) }}</td>
          <td class="right">${{ number_format((float)($r->total ?? 0), 2) }}</td>
          <td class="uuid">{{ $r->uuid }}</td>
        </tr>
      @empty
        <tr><td colspan="8" style="padding:18px;color:#6b7280">No hay documentos.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>

  @if (method_exists($cfdis,'hasPages') && $cfdis->hasPages())
    <div class="pagination" style="display:flex;gap:8px;justify-content:flex-end;padding:10px">
      @if ($cfdis->onFirstPage()) <span>&laquo;</span> @else <a href="{{ $cfdis->previousPageUrl() }}">&laquo;</a> @endif
      @foreach ($cfdis->getUrlRange(max(1,$cfdis->currentPage()-2), min($cfdis->lastPage(), $cfdis->currentPage()+2)) as $p => $u)
        @if ($p == $cfdis->currentPage()) <span style="padding:6px 10px;border:1px solid var(--line-2);border-radius:10px;background:#eef2ff">{{ $p }}</span>
        @else <a href="{{ $u }}" style="padding:6px 10px;border:1px solid var(--line-2);border-radius:10px;text-decoration:none"> {{ $p }} </a>
        @endif
      @endforeach
      @if ($cfdis->hasMorePages()) <a href="{{ $cfdis->nextPageUrl() }}">&raquo;</a> @else <span>&raquo;</span> @endif
    </div>
  @endif
</div>
@endsection

@push('scripts')
<script>
  // URLs seguras para fetch (evitar escribir directivas de Blade en comentarios)
  (function(){
    var params = new URLSearchParams("{{ $queryString }}");
    window.KPI_URL    = "{{ route('cliente.facturacion.kpis') }}?"   + params.toString();
    window.SERIES_URL = "{{ route('cliente.facturacion.series') }}?" + params.toString();
  })();

  // SERIES (inyectado con json_encode puro)
  const SERIES = {!! json_encode(
    isset($series) && is_array($series) ? $series : ['labels'=>[],'series'=>['emitidos_total'=>[],'cancelados_total'=>[]]],
    JSON_UNESCAPED_UNICODE
  ) !!};

  // Mini chart SVG (líneas finas con puntos)
  (function draw(){
    const svg = document.getElementById('svgChart'); if(!svg) return;
    const NS='http://www.w3.org/2000/svg';
    const W=svg.viewBox.baseVal.width||1000, H=svg.viewBox.baseVal.height||280, P={l:40,t:10,r:10,b:28};
    const labels = (SERIES && SERIES.labels) ? SERIES.labels : [];
    const a = (SERIES && SERIES.series && SERIES.series.emitidos_total) ? SERIES.series.emitidos_total : [];
    const b = (SERIES && SERIES.series && SERIES.series.cancelados_total) ? SERIES.series.cancelados_total : [];
    const all=[...a,...b]; const maxV=Math.max(1, ...all); const minV=0;
    const x=i=>P.l+(labels.length<=1?0:(i/(labels.length-1))*(W-P.l-P.r));
    const y=v=>P.t+(H-P.t-P.b)-((v-minV)/(maxV-minV))*(H-P.t-P.b);
    const dPath=arr=>arr.length?('M '+arr.map((v,i)=>x(i)+' '+y(v)).join(' L ')):'';    
    while(svg.firstChild) svg.removeChild(svg.firstChild);

    // Grid suave
    for(let g=0; g<=4; g++){
      const gy=P.t+g*(H-P.t-P.b)/4;
      const ln=document.createElementNS(NS,'line');
      ln.setAttribute('x1',P.l); ln.setAttribute('x2',W-P.r); ln.setAttribute('y1',gy); ln.setAttribute('y2',gy);
      ln.setAttribute('stroke','currentColor'); ln.setAttribute('opacity','.15'); svg.appendChild(ln);
    }

    const p1=document.createElementNS(NS,'path');
    p1.setAttribute('d',dPath(a)); p1.setAttribute('fill','none'); p1.setAttribute('stroke','currentColor'); p1.setAttribute('stroke-width','2'); svg.appendChild(p1);
    const p2=document.createElementNS(NS,'path');
    p2.setAttribute('d',dPath(b)); p2.setAttribute('fill','none'); p2.setAttribute('stroke','currentColor'); p2.setAttribute('stroke-width','2'); p2.setAttribute('opacity','.55'); svg.appendChild(p2);

    const drawDots = (arr, op=1)=>arr.forEach((v,i)=>{
      const c=document.createElementNS(NS,'circle'); c.setAttribute('cx',x(i)); c.setAttribute('cy',y(v)); c.setAttribute('r','3');
      c.setAttribute('fill','currentColor'); c.setAttribute('opacity',op); svg.appendChild(c);
    });
    drawDots(a,1); drawDots(b,.55);
  })();
</script>
@endpush
