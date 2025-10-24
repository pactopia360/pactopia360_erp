@extends('layouts.client')
@section('title','Emisores · Pactopia360')

@push('styles')
<style>
  .page-hd{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px}
  .tools{display:flex;gap:8px;flex-wrap:wrap}
  .btn{display:inline-flex;align-items:center;gap:8px;padding:10px 12px;border:1px solid var(--border);border-radius:12px;font-weight:900;text-decoration:none}
  .btn.primary{background:linear-gradient(180deg,var(--accent),color-mix(in oklab,var(--accent)85%,black));color:#fff;border:0}
  .badge{padding:4px 8px;border-radius:999px;border:1px solid var(--border);font-size:12px;font-weight:900}
  .badge.active{background:color-mix(in oklab,#16a34a 20%,transparent);border-color:color-mix(in oklab,#16a34a 40%,transparent)}
  .pill{display:flex;align-items:center;gap:8px;border:1px solid var(--border);border-radius:999px;padding:8px 10px}
  .pill input{all:unset;min-width:240px;font-weight:800}
  table{width:100%;border-collapse:separate;border-spacing:0}
  th,td{padding:10px;border-bottom:1px solid var(--border);text-align:left}
  tbody tr:nth-child(odd){background:#f8fafc}
  .right{text-align:right}
</style>
@endpush

@section('content')
<div class="page-hd">
  <h1 style="margin:0;font-weight:900">Emisores</h1>
  <div class="tools">
    <a class="btn" href="{{ route('cliente.emisores.nuevo') }}">+ Nuevo Emisor</a>

    {{-- PRO: carga masiva --}}
    <form method="POST" action="{{ route('cliente.emisores.import') }}" enctype="multipart/form-data">
      @csrf
      <label class="btn {{ $plan==='PRO' ? '' : 'disabled' }}">
        <input type="file" name="file" accept=".csv" style="display:none" onchange="this.form.submit()" {{ $plan==='PRO'?'':'disabled' }}>
        Descargar/Importar
      </label>
    </form>

    {{-- PRO: validador CSD rápido --}}
    <form method="POST" action="{{ route('cliente.emisores.csd.validate') }}" enctype="multipart/form-data" class="tools">
      @csrf
      <label class="btn {{ $plan==='PRO' ? '' : 'disabled' }}">
        <input type="file" name="cer" accept=".cer" style="display:none" onchange="/*keep*/">
        CSD .cer
      </label>
      <label class="btn {{ $plan==='PRO' ? '' : 'disabled' }}">
        <input type="file" name="key" accept=".key" style="display:none" onchange="/*keep*/">
        CSD .key
      </label>
      <input type="password" name="pwd" placeholder="Contraseña CSD" class="pill" {{ $plan==='PRO'?'':'disabled' }}>
      <button class="btn primary" {{ $plan==='PRO'?'':'disabled' }}>Validar CSD</button>
    </form>
  </div>
</div>

@if(session('ok')) <div class="badge active">{{ session('ok') }}</div> @endif
@if($errors->any()) <div class="badge">{{ $errors->first() }}</div> @endif

<form method="GET" class="pill" style="margin-bottom:10px">
  <input name="q" value="{{ request('q') }}" placeholder="Buscar por RFC / Razón social / Comercial…">
  <button class="btn">Buscar</button>
</form>

<div style="overflow:auto">
  <table>
    <thead>
      <tr>
        <th>RFC</th>
        <th>Razón Social</th>
        <th>Régimen</th>
        <th>Fecha expiración</th>
        <th>Grupo</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      @forelse($emisores as $e)
        <tr>
          <td>{{ $e->rfc }}</td>
          <td>{{ $e->razon_social ?? $e->nombre_comercial }}</td>
          <td>{{ $e->regimen_fiscal ?? '—' }}</td>
          <td>{{ optional($e->csd_vigencia_hasta)->format('Y-m-d H:i') ?? '—' }}</td>
          <td>{{ $e->grupo ?? '—' }}</td>
          <td><span class="badge {{ $e->status==='active'?'active':'' }}">{{ $e->status }}</span></td>
        </tr>
      @empty
        <tr><td colspan="6" style="color:var(--muted)">Sin emisores.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="right" style="margin-top:8px">
  {{ $emisores->links() }}
</div>
@endsection
