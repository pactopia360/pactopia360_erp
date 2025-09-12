{{-- resources/views/admin/empresas/pactopia360/crm/contactos/index.blade.php --}}
@extends('admin.empresas.pactopia360.crm.layout')
@section('title', 'Contactos · CRM · Pactopia360')

@section('crm')
<div class="toolbar">
  <form method="get" class="form" style="display:flex; gap:8px; flex-wrap:wrap">
    <input type="search" name="q" value="{{ $q }}" placeholder="Buscar (nombre, email, teléfono, puesto)" />
    <label style="display:inline-flex; align-items:center; gap:6px">
      <input type="checkbox" name="act" value="0" {{ !$act ? 'checked' : '' }} onchange="this.form.submit()"> Inactivos
    </label>
    <a href="{{ route('admin.empresas.pactopia360.crm.contactos.create') }}" class="btn">Nuevo contacto</a>
  </form>
</div>

@if (session('ok'))
  <div class="crm-card" style="border-left:4px solid #10b981">{{ session('ok') }}</div>
@endif

<div class="crm-card">
  <table class="crm-table">
    <thead>
      <tr>
        <th>ID</th>
        <th>Nombre</th>
        <th>Email / Teléfono</th>
        <th>Puesto</th>
        <th>Estado</th>
        <th style="width:160px">Acciones</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($rows as $c)
        <tr>
          <td>{{ $c->id }}</td>
          <td>{{ $c->nombre }}</td>
          <td>
            @if($c->email) <div>{{ $c->email }}</div> @endif
            @if($c->telefono) <div class="muted">{{ $c->telefono }}</div> @endif
          </td>
          <td>{{ $c->puesto ?: '—' }}</td>
          <td>
            @if($c->activo)
              <span class="badge ok">Activo</span>
            @else
              <span class="badge off">Inactivo</span>
            @endif
          </td>
          <td class="actions">
            <a class="btn-link" href="{{ route('admin.empresas.pactopia360.crm.contactos.edit', $c) }}">Editar</a>
            <form method="post" action="{{ route('admin.empresas.pactopia360.crm.contactos.destroy', $c) }}" onsubmit="return confirm('¿Eliminar contacto #{{ $c->id }}?')">
              @csrf @method('DELETE')
              <button class="btn-link danger" type="submit">Eliminar</button>
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="6" class="muted">Sin resultados.</td></tr>
      @endforelse
    </tbody>
  </table>

  <div style="margin-top:10px">
    {{ $rows->links() }}
  </div>
</div>
@endsection
