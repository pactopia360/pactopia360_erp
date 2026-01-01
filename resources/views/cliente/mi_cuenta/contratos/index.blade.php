{{-- resources/views/cliente/mi_cuenta/contratos/index.blade.php --}}
@extends('layouts.cliente')
@section('title','Contratos · Mi cuenta · Pactopia360')
@section('pageClass','page-mi-cuenta')

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/client/css/mi-cuenta.css') }}?v=2.6">
@endpush

@section('content')
@php
  $c = $contract ?? null;
  $isSigned = $c && ($c->status ?? '') === 'signed' && !empty($c->pdf_path ?? '');
@endphp

<div class="mc-wrap">
  <div class="mc-page">

    <section class="mc-card mc-header">
      <div class="mc-header-left">
        <div class="mc-title-icon" aria-hidden="true">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <path d="M7 2h10a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="2"/>
            <path d="M8 7h8M8 11h8M8 15h6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </div>
        <div style="min-width:0">
          <h1 class="mc-title-main">Contratos</h1>
          <div class="mc-title-sub">Confirma tus datos, firma y descarga tu contrato en PDF.</div>
        </div>
      </div>

      <div class="mc-header-right" style="grid-template-columns:1fr;justify-items:end;">
        <a href="{{ route('cliente.mi_cuenta.index') }}" class="mc-btn">Volver a Mi cuenta</a>
      </div>
    </section>

    @if(session('ok'))
      <div class="mc-card mc-card--tight" style="border-left:4px solid #16a34a;">
        <div style="font-weight:800;">{{ session('ok') }}</div>
      </div>
    @endif
    @if(session('err'))
      <div class="mc-card mc-card--tight" style="border-left:4px solid #dc2626;">
        <div style="font-weight:800;">{{ session('err') }}</div>
      </div>
    @endif
    @if($errors->any())
      <div class="mc-card mc-card--tight" style="border-left:4px solid #dc2626;">
        <div style="font-weight:800;">Revisa lo siguiente:</div>
        <ul style="margin:8px 0 0 18px;">
          @foreach($errors->all() as $e)
            <li>{{ $e }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <section class="mc-section" data-open="1">
      <div class="mc-sec-head">
        <div class="mc-sec-ico" aria-hidden="true">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <path d="M4 4h16v16H4V4Z" stroke="currentColor" stroke-width="2"/>
            <path d="M8 8h8M8 12h8M8 16h5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </div>
        <div class="mc-sec-meta">
          <div class="mc-sec-kicker">CONTRATO</div>
          <h2 class="mc-sec-title">Contrato de timbrado CFDI</h2>
          <div class="mc-sec-sub">Se genera con tus datos registrados y se firma una sola vez.</div>
        </div>

        @if($isSigned)
          <span class="mc-badge" style="background:rgba(22,163,74,.12);color:#16a34a;border-color:rgba(22,163,74,.30);">Firmado</span>
        @else
          <span class="mc-badge">Pendiente</span>
        @endif
      </div>

      <div class="mc-sec-body" style="display:block;">

        {{-- Datos (solo lectura) --}}
        <div class="mc-card mc-card--tight">
          <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;">
            <div>
              <div style="font-weight:900;">Datos de contrato</div>
              <div style="color:var(--mc-mut);margin-top:4px;">
                Si algún dato es incorrecto, actualízalo en “Facturación” dentro de Mi cuenta antes de firmar.
              </div>
            </div>
            <a class="mc-btn" href="{{ route('cliente.mi_cuenta.index') }}#billing">Ir a Mi cuenta</a>
          </div>

          <div style="margin-top:14px;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;">
            <div><div style="color:var(--mc-mut);font-weight:800;font-size:12px;">RAZÓN SOCIAL</div><div style="font-weight:800;">{{ $datos['cliente_razon_social'] ?? '—' }}</div></div>
            <div><div style="color:var(--mc-mut);font-weight:800;font-size:12px;">RFC</div><div style="font-weight:800;">{{ $datos['cliente_rfc'] ?? '—' }}</div></div>

            <div><div style="color:var(--mc-mut);font-weight:800;font-size:12px;">REPRESENTANTE</div><div style="font-weight:800;">{{ $datos['cliente_representante'] ?? '—' }}</div></div>
            <div><div style="color:var(--mc-mut);font-weight:800;font-size:12px;">EMAIL</div><div style="font-weight:800;">{{ $datos['cliente_email'] ?? '—' }}</div></div>

            <div style="grid-column:1 / -1;">
              <div style="color:var(--mc-mut);font-weight:800;font-size:12px;">DOMICILIO</div>
              <div style="font-weight:800;">
                {{ trim(($datos['cliente_calle'] ?? '').' '.($datos['cliente_no_ext'] ?? '').' '.($datos['cliente_no_int'] ?? '')) }},
                Col. {{ $datos['cliente_colonia'] ?? '—' }},
                {{ $datos['cliente_municipio'] ?? '—' }}, {{ $datos['cliente_estado'] ?? '—' }},
                C.P. {{ $datos['cliente_cp'] ?? '—' }},
                {{ $datos['cliente_pais'] ?? '—' }}
              </div>
            </div>
          </div>
        </div>

        {{-- Acciones --}}
        <div class="mc-card mc-card--tight" style="margin-top:12px;">
          <div style="display:flex;justify-content:space-between;gap:16px;align-items:center;flex-wrap:wrap;">
            <div>
              <div style="font-weight:900;">Acciones</div>
              <div style="color:var(--mc-mut);margin-top:4px;">
                @if($isSigned)
                  Tu contrato ya está firmado. Puedes abrir el PDF.
                @else
                  Lee el contrato y firma para generar tu PDF firmado.
                @endif
              </div>
            </div>

            <div style="display:flex;gap:8px;flex-wrap:wrap;">
              @if($c)
                <a class="mc-btn" href="{{ route('cliente.mi_cuenta.contratos.show', ['contract'=>$c->id]) }}">Ver contrato</a>
              @endif

              @if($isSigned && $c)
                <a class="mc-btn mc-btn--primary" href="{{ route('cliente.mi_cuenta.contratos.pdf', ['contract'=>$c->id]) }}">Ver PDF firmado</a>
              @endif
            </div>
          </div>

          @if(!$isSigned && $c)
            <form method="POST" action="{{ route('cliente.mi_cuenta.contratos.sign', ['contract'=>$c->id]) }}" style="margin-top:14px;">
              @csrf
              <label style="display:flex;gap:10px;align-items:flex-start;">
                <input type="checkbox" name="accept" value="1" style="margin-top:3px;">
                <span style="font-weight:800;">
                  He leído y acepto el contrato. Entiendo que al firmar se generará un PDF firmado y quedará registrado.
                </span>
              </label>

              <div style="margin-top:12px;display:flex;gap:10px;justify-content:flex-end;">
                <button type="submit" class="mc-btn mc-btn--primary">Confirmar y firmar</button>
              </div>
            </form>
          @endif
        </div>

      </div>
    </section>

  </div>
</div>
@endsection
