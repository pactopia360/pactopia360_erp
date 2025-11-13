{{-- resources/views/cliente/descargas/index.blade.php (v2 visual Pactopia360) --}}
@extends('layouts.cliente')

@section('title','Descargas · Pactopia360')

@push('styles')
<style>
  body{font-family:'Poppins',system-ui,sans-serif;}

  .dl-wrap{
    --gap:18px;
    display:grid;
    gap:var(--gap);
    padding-bottom:40px;
  }

  h1{
    margin:0;
    font-weight:900;
    font-size:22px;
    color:#E11D48;
  }
  .muted{color:#6b7280;font-size:13px;}

  .card{
    background:linear-gradient(180deg,rgba(255,255,255,.96),rgba(255,255,255,.9));
    border:1px solid #f3d5dc;
    border-radius:18px;
    padding:18px 20px;
    box-shadow:0 8px 28px rgba(225,29,72,.08);
    position:relative;
  }
  .card::before{
    content:"";position:absolute;inset:-1px;border-radius:19px;padding:1px;
    background:linear-gradient(145deg,#E11D48,#BE123C);
    -webkit-mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);
    -webkit-mask-composite:xor;mask-composite:exclude;
    opacity:.25;pointer-events:none;
  }

  .list{display:grid;gap:10px;margin-top:6px;}
  .row{
    display:flex;align-items:center;justify-content:space-between;gap:12px;
    border:1px solid #f3d5dc;border-radius:12px;
    padding:12px 14px;
    background:#fff;
    transition:background .15s ease,transform .15s ease;
  }
  .row:hover{background:#fff5f7;transform:translateY(-1px);}
  .name{font-weight:800;color:#0f172a;font-size:14px;}
  .desc{color:#6b7280;font-size:12px;margin-top:2px;}

  .btn{
    display:inline-flex;align-items:center;justify-content:center;
    padding:9px 14px;
    border-radius:10px;
    border:0;
    font-weight:800;
    font-size:13px;
    cursor:pointer;
    color:#fff;
    background:linear-gradient(90deg,#E11D48,#BE123C);
    box-shadow:0 6px 14px rgba(225,29,72,.25);
    text-decoration:none;
    transition:filter .15s ease;
  }
  .btn:hover{filter:brightness(.96);}

  .empty{
    padding:22px;
    border:2px dashed #f3d5dc;
    border-radius:14px;
    text-align:center;
    color:#9ca3af;
    font-weight:700;
    font-size:14px;
    background:#fffafc;
  }

  @media(max-width:540px){
    .row{flex-direction:column;align-items:flex-start;gap:8px;}
    .btn{width:100%;text-align:center;}
  }
</style>
@endpush

@section('content')
<div class="dl-wrap">
  <div>
    <h1>Descargas disponibles</h1>
    <div class="muted">Archivos utilitarios asociados a tu cuenta Pactopia360.</div>
  </div>

  <div class="card">
    @if(!empty($items))
      <div class="list">
        @foreach($items as $it)
          <div class="row">
            <div>
              <div class="name">{{ $it['name'] ?? 'Archivo' }}</div>
              @if(!empty($it['desc']))
                <div class="desc">{{ $it['desc'] }}</div>
              @endif
            </div>
            <a class="btn" href="{{ $it['url'] }}" download>
              Descargar
            </a>
          </div>
        @endforeach
      </div>
    @else
      <div class="empty">Sin archivos por ahora.</div>
    @endif
  </div>
</div>
@endsection
