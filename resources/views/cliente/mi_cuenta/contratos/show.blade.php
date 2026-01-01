@extends('layouts.cliente')
@section('title','Ver contrato · Mi cuenta · Pactopia360')
@section('pageClass','page-mi-cuenta')

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/client/css/mi-cuenta.css') }}?v=2.6">
  <style>
    .c-doc{max-width:900px;margin:0 auto;background:#fff;border:1px solid rgba(15,23,42,.10);border-radius:14px;padding:22px}
    .c-h1{font-size:18px;font-weight:900;margin:0 0 6px}
    .c-h2{font-size:13px;font-weight:800;margin:0 0 16px;color:#475569}
    .c-h3{font-size:13px;font-weight:900;margin:16px 0 8px}
    .c-meta{color:#334155;margin:0 0 10px}
    .c-ol{margin:8px 0 0 18px}
    .c-sign{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:16px}
    .c-sign-box{border:1px dashed rgba(15,23,42,.20);border-radius:12px;padding:12px}
    .c-sign-title{font-size:12px;color:#64748b;font-weight:900}
    .c-sign-name{margin-top:6px}
    .c-sign-line{margin-top:8px;color:#64748b;font-weight:700;font-size:12px}
  </style>
@endpush

@section('content')
<div class="mc-wrap">
  <div class="mc-page">
    <section class="mc-card mc-header">
      <div class="mc-header-left">
        <div style="min-width:0">
          <h1 class="mc-title-main">Vista previa del contrato</h1>
          <div class="mc-title-sub">Lee el contenido antes de firmar.</div>
        </div>
      </div>
      <div class="mc-header-right" style="grid-template-columns:1fr;justify-items:end;">
        <a href="{{ route('cliente.mi_cuenta.contratos.index') }}" class="mc-btn">Volver</a>
      </div>
    </section>

    <section class="mc-section" data-open="1">
      <div class="mc-sec-body" style="display:block;">
        {!! $html !!}
      </div>
    </section>
  </div>
</div>
@endsection
