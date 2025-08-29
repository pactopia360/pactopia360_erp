@extends('layouts.admin')

@section('title', ($item->exists ? $titles['edit'] : $titles['create']).' · Pactopia360')

@push('styles')
  <style>.prf-form{max-width:780px;margin-inline:auto}.prf-form .card{background:var(--card-bg);border:1px solid var(--card-border);border-radius:12px;padding:12px}</style>
@endpush

@section('content')
<div class="prf-page prf-form">
  <div class="card">
    <form method="post" action="{{ $item->exists ? route($routeBase.'.update',$item) : route($routeBase.'.store') }}">
      @csrf @if($item->exists) @method('PUT') @endif

      <div class="mb-3">
        <label class="form-label">Clave</label>
        <input class="form-control" name="clave" value="{{ old('clave',$item->clave) }}" required>
      </div>

      <div class="mb-3">
        <label class="form-label">Nombre</label>
        <input class="form-control" name="nombre" value="{{ old('nombre',$item->nombre) }}" required>
      </div>

      <div class="mb-3">
        <label class="form-label">Descripción</label>
        <textarea class="form-control" name="descripcion" rows="3">{{ old('descripcion',$item->descripcion) }}</textarea>
      </div>

      <div class="mb-3">
        <label><input type="checkbox" name="activo" value="1" {{ old('activo',$item->activo) ? 'checked':'' }}> Activo</label>
      </div>

      <div class="mt-3">
        <button class="btn btn-primary" type="submit">{{ $item->exists ? 'Actualizar':'Crear' }}</button>
        <a class="btn" href="{{ route($routeBase.'.index') }}">Volver</a>
      </div>
    </form>
  </div>
</div>
@endsection
