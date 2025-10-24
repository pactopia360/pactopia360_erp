@extends('layouts.admin')

@section('title', 'Nuevo carrito')

@php
  use App\Models\Empresas\Pactopia360\CRM\Carrito as CarritoModel;

  /** $row vacío en create */
  $row     = $row ?? null;
  $estados = $estados
      ?? (defined(CarritoModel::class.'::ESTADOS') ? CarritoModel::ESTADOS : ['abierto','convertido','cancelado','nuevo']);
@endphp

@section('content')
<div class="page">
  <div class="card">
    <div class="card-h" style="display:flex;align-items:center;gap:10px">
      <strong>Nuevo carrito</strong>
      <span class="muted" style="margin-left:auto">CRM · Pactopia360</span>
    </div>
    <div class="card-b">
      @include('admin.empresas.pactopia360.crm.carritos._edit_inner', [
        'row'     => $row,
        'estados' => $estados,
        'mode'    => 'create',
      ])
    </div>
  </div>
</div>
@endsection
