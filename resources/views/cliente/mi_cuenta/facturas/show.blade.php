{{-- resources/views/cliente/mi_cuenta/facturas/show.blade.php (v1.0 placeholder) --}}
@extends('layouts.cliente')

@section('title','Factura · Mi cuenta · Pactopia360')
@section('pageClass','page-mi-cuenta page-mi-cuenta-facturas')

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/client/css/mi-cuenta.css') }}?v=3.0">
@endpush

@section('content')
@php
  $rtBack = route('cliente.mi_cuenta.facturas.index');
  $st = (string)($row->status ?? 'requested');
@endphp

<div class="mc-wrap">
  <div class="mc-page">

    <section class="mc-card mc-header">
      <div class="mc-header-left">
        <div class="mc-title-icon" aria-hidden="true" style="background:linear-gradient(180deg, rgba(16,185,129,.18), rgba(16,185,129,.10)); color:#065f46;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <path d="M7 2h10a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="2"/>
            <path d="M8 7h8M8 11h8M8 15h6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </div>
        <div style="min-width:0">
          <h1 class="mc-title-main">Solicitud #{{ $row->id }}</h1>
          <div class="mc-title-sub">Periodo {{ $row->period }} · Estatus {{ $st }}</div>
        </div>
      </div>
      <div class="mc-header-right">
        <a class="mc-btn" href="{{ $rtBack }}" style="text-decoration:none;">Volver</a>
      </div>
    </section>

    <section class="mc-section" data-open="1">
      <div class="mc-sec-head">
        <div class="mc-sec-ico" aria-hidden="true">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" stroke="currentColor" stroke-width="2"/>
            <path d="M14 2v6h6" stroke="currentColor" stroke-width="2"/>
          </svg>
        </div>
        <div class="mc-sec-meta">
          <div class="mc-sec-kicker">SECCIÓN</div>
          <h2 class="mc-sec-title">Detalle</h2>
          <div class="mc-sec-sub">Información de la solicitud.</div>
        </div>
      </div>

      <div class="mc-sec-body">
        <div class="mc-card" style="box-shadow:none; border:1px solid var(--mc-line, rgba(15,23,42,.10)); border-radius:16px; padding:14px;">
          <div style="display:grid; gap:10px;">
            <div><strong>Periodo:</strong> {{ $row->period }}</div>
            <div><strong>Estatus:</strong> {{ $st }}</div>
            <div><strong>Notas:</strong> {{ $row->notes ?: '—' }}</div>
            <div><strong>ZIP:</strong> {{ $row->zip_path ?: '—' }}</div>
          </div>
        </div>
      </div>
    </section>

  </div>
</div>
@endsection
