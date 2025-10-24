@extends('layouts.admin')

@section('title', 'Editar carrito')

@php
  use App\Models\Empresas\Pactopia360\CRM\Carrito as CarritoModel;

  /** $row debe venir seteado por el controller */
  $row     = $row ?? (isset($carrito) ? $carrito : null);
  $estados = $estados
      ?? (defined(CarritoModel::class.'::ESTADOS') ? CarritoModel::ESTADOS : ['abierto','convertido','cancelado','nuevo']);
@endphp

@section('content')
<div class="page">
  <div class="card">
    <div class="card-h" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
      <strong>Editar carrito @if($row?->id)#{{ $row->id }}@endif</strong>
      <span class="muted" style="margin-left:auto">CRM · Pactopia360</span>
    </div>
    <div class="card-b">
      @include('admin.empresas.pactopia360.crm.carritos._edit_inner', [
        'row'     => $row,
        'estados' => $estados,
        'mode'    => 'edit',
      ])
    </div>
  </div>

  @if($row?->id)
  <div class="card" style="margin-top:14px">
    <div class="card-h"><strong>Zona de peligro</strong></div>
    <div class="card-b" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      <form method="post" action="{{ route('admin.empresas.pactopia360.crm.carritos.destroy', $row->id) }}"
            onsubmit="return confirm('¿Eliminar carrito #{{ $row->id }}? Esta acción no se puede deshacer.');">
        @csrf @method('DELETE')
        <button class="btn btn-danger" type="submit">Eliminar definitivamente</button>
      </form>
      <span class="muted">Eliminación lógica/física según tu modelo. Verifica relaciones antes de borrar.</span>
    </div>
  </div>
  @endif
</div>
@endsection
