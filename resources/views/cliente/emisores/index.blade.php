{{-- resources/views/cliente/emisores/index.blade.php (v2 visual Pactopia360) --}}
@extends('layouts.client')
@section('title','Emisores · Pactopia360')

@push('styles')
<style>
  body{font-family:'Poppins',system-ui,sans-serif;}

  .page-hd{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:10px;
    flex-wrap:wrap;
    margin-bottom:14px;
  }
  h1{
    margin:0;
    font-weight:900;
    font-size:22px;
    color:#E11D48;
  }

  .tools{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
  }

  .btn{
    display:inline-flex;align-items:center;justify-content:center;gap:8px;
    padding:10px 14px;
    border-radius:12px;
    font-weight:800;
    font-size:14px;
    cursor:pointer;
    text-decoration:none;
    transition:.15s filter ease;
  }
  .btn.primary{
    background:linear-gradient(90deg,#E11D48,#BE123C);
    color:#fff;border:0;
    box-shadow:0 8px 20px rgba(225,29,72,.25);
  }
  .btn.primary:hover{filter:brightness(.96);}
  .btn.secondary{
    background:#fff;
    border:1px solid #f3d5dc;
    color:#E11D48;
  }
  .btn.secondary:hover{background:#fff0f3;}
  .btn.disabled{
    opacity:.45;cursor:not-allowed;filter:grayscale(.8);
  }

  .pill{
    display:flex;
    align-items:center;
    gap:8px;
    border:1px solid #f3d5dc;
    border-radius:999px;
    padding:8px 12px;
    background:#fff;
    box-shadow:0 4px 10px rgba(225,29,72,.05);
  }
  .pill input{
    all:unset;
    min-width:240px;
    font-weight:700;
    font-size:13px;
    color:#0f172a;
  }

  .badge{
    display:inline-block;
    border-radius:999px;
    padding:5px 10px;
    font-weight:800;
    font-size:12px;
    background:#fff;
    border:1px solid #f3d5dc;
    color:#6b7280;
  }
  .badge.active{
    background:#ecfdf5;
    border-color:#86efac;
    color:#047857;
  }

  .alert{
    padding:10px 12px;
    background:#fff5f7;
    border:1px solid #f3d5dc;
    border-radius:12px;
    font-weight:700;
    color:#BE123C;
    margin-bottom:10px;
  }

  .table-wrap{
    overflow:auto;
    border-radius:14px;
    border:1px solid #f3d5dc;
    box-shadow:0 8px 22px rgba(225,29,72,.05);
    background:linear-gradient(180deg,rgba(255,255,255,.96),rgba(255,255,255,.9));
  }

  table{width:100%;border-collapse:collapse;min-width:800px;}
  th,td{padding:12px 14px;text-align:left;border-bottom:1px solid #f3d5dc;}
  thead th{
    font-size:12px;
    text-transform:uppercase;
    letter-spacing:.08em;
    color:#6b7280;
    background:#fff0f3;
  }
  tbody tr:hover{background:#fff5f7;}
  tbody td{font-size:13px;color:#0f172a;font-weight:500;}
  tbody td:first-child{font-weight:700;color:#E11D48;}

  .right{text-align:right;}
</style>
@endpush

@section('content')
<div class="page-hd">
  <h1>Emisores</h1>

  <div class="tools">
    <a class="btn primary" href="{{ route('cliente.emisores.nuevo') }}">+ Nuevo Emisor</a>

    {{-- PRO: carga masiva --}}
    <form method="POST" action="{{ route('cliente.emisores.import') }}" enctype="multipart/form-data">
      @csrf
      <label class="btn secondary {{ $plan==='PRO' ? '' : 'disabled' }}">
        <input type="file" name="file" accept=".csv" style="display:none" onchange="this.form.submit()" {{ $plan==='PRO'?'':'disabled' }}>
        Importar CSV
      </label>
    </form>

    {{-- PRO: validador CSD rápido --}}
    <form method="POST" action="{{ route('cliente.emisores.csd.validate') }}" enctype="multipart/form-data" class="tools">
      @csrf
      <label class="btn secondary {{ $plan==='PRO' ? '' : 'disabled' }}">
        <input type="file" name="cer" accept=".cer" style="display:none" onchange="/*keep*/">
        CSD .cer
      </label>
      <label class="btn secondary {{ $plan==='PRO' ? '' : 'disabled' }}">
        <input type="file" name="key" accept=".key" style="display:none" onchange="/*keep*/">
        CSD .key
      </label>
      <input type="password" name="pwd" placeholder="Contraseña CSD" class="pill" {{ $plan==='PRO'?'':'disabled' }}>
      <button class="btn primary" {{ $plan==='PRO'?'':'disabled' }}>Validar CSD</button>
    </form>
  </div>
</div>

@if(session('ok')) <div class="alert">{{ session('ok') }}</div> @endif
@if($errors->any()) <div class="alert">{{ $errors->first() }}</div> @endif

<form method="GET" class="pill" style="margin-bottom:14px;">
  <input name="q" value="{{ request('q') }}" placeholder="Buscar por RFC, Razón social o Comercial…">
  <button class="btn secondary">Buscar</button>
</form>

<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th>RFC</th>
        <th>Razón Social</th>
        <th>Régimen</th>
        <th>Vigencia CSD</th>
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
          <td>{{ optional($e->csd_vigencia_hasta)->format('Y-m-d') ?? '—' }}</td>
          <td>{{ $e->grupo ?? '—' }}</td>
          <td><span class="badge {{ $e->status==='active'?'active':'' }}">{{ $e->status }}</span></td>
        </tr>
      @empty
        <tr><td colspan="6" style="text-align:center;color:#9ca3af;padding:16px;">Sin emisores registrados.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="right" style="margin-top:10px;">
  {{ $emisores->links() }}
</div>
@endsection
