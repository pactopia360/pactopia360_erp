@extends('layouts.admin')
@section('title', 'Carrito · Detalle')

@section('content')
<div class="page">
  <div class="card">
    <div class="card-h" style="display:flex;align-items:center;gap:10px">
      <strong>Detalle de carrito</strong>
      <span class="muted" style="margin-left:auto">CRM · Pactopia360</span>
    </div>
    <div class="card-b">
      @include('admin.empresas.pactopia360.crm.carritos._show_inner', [
        'row' => $carrito ?? $row ?? null,
      ])
    </div>
  </div>
</div>
@endsection
