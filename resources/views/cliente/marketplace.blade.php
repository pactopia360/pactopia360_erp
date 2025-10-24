{{-- resources/views/cliente/marketplace.blade.php --}}
@extends('layouts.client')
@section('title','Marketplace · Pactopia360')

@push('styles')
<style>
  .page-header{ display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:14px }
  .page-title{ margin:0; font-size:clamp(18px,2.4vw,22px); font-weight:900; color:var(--text) }
  .muted{ color:var(--muted) }
  .grid{ display:grid; gap:12px; grid-template-columns: repeat(auto-fill, minmax(240px,1fr)) }
  .card{ background:var(--card); border:1px solid var(--border); border-radius:14px; padding:14px; box-shadow:var(--shadow) }
  .btn{ display:inline-flex; align-items:center; gap:8px; padding:10px 12px; border-radius:12px; border:1px solid var(--border); background:linear-gradient(180deg, var(--accent-2), color-mix(in oklab, var(--accent-2) 85%, black)); color:#fff; font-weight:900; text-decoration:none }
</style>
@endpush

@section('content')
<div class="page-header">
  <div>
    <h1 class="page-title">Marketplace</h1>
    <div class="muted">Complementos y servicios para tu cuenta.</div>
  </div>
</div>

<div class="grid">
  <div class="card">
    <h3 style="margin:0 0 6px">Timbres adicionales</h3>
    <p class="muted">Compra paquetes de timbres para tus CFDI.</p>
    <a href="#" class="btn">Ver paquetes</a>
  </div>
  <div class="card">
    <h3 style="margin:0 0 6px">Soporte PRO</h3>
    <p class="muted">Respuesta prioritaria y asesoría experta.</p>
    <a href="#" class="btn">Más información</a>
  </div>
</div>
@endsection
