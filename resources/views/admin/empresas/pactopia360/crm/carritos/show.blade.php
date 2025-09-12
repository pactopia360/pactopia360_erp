{{-- resources/views/admin/empresas/pactopia360/crm/carritos/show.blade.php --}}
@include('admin.empresas.pactopia360.crm.carritos._layout_detect')
@extends($__layout)

@php
  $row = $row ?? $carrito ?? null;
@endphp

@section('title', $row ? 'Carrito #'.$row->id : 'Carrito')
@section('breadcrumb','Pactopia360 · CRM · Carritos · Detalle')

@section('content')
  <div class="row" style="justify-content:space-between;margin-bottom:12px">
    <h1 style="margin:0">{{ $row ? 'Carrito #'.$row->id : 'Carrito' }}</h1>
    <div class="actions">
      @if($row)
        <a class="btn" href="{{ route('admin.empresas.pactopia360.crm.carritos.edit', $row->id) }}">Editar</a>
      @endif
      <a class="btn" href="{{ route('admin.empresas.pactopia360.crm.carritos.index') }}">Volver</a>
    </div>
  </div>

  @if($row)
    @include('admin.empresas.pactopia360.crm.carritos._show_inner', ['row' => $row])
  @else
    <div class="flash" style="background:#fef2f2;border:1px solid #fecaca;padding:10px;border-radius:8px">
      No se encontró el carrito solicitado.
    </div>
  @endif
@endsection
