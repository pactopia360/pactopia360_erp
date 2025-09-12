{{-- resources/views/admin/empresas/pactopia360/crm/contactos/create.blade.php --}}
@extends('admin.empresas.pactopia360.crm.layout')

@section('title', 'Nuevo contacto · CRM · Pactopia360')

@section('crm')
  @if ($errors->any())
    <div class="crm-card" style="border-left:4px solid #ef4444">
      <strong>Hay errores:</strong>
      <ul style="margin:6px 0 0 18px">
        @foreach ($errors->all() as $e)
          <li>{{ $e }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="crm-card">
    <form method="POST" action="{{ route('admin.empresas.pactopia360.crm.contactos.store') }}">
      @csrf

      @include('admin.empresas.pactopia360.crm.contactos._form', [
        'contacto' => $contacto,
        'mode'     => 'create'
      ])

      <div class="form-actions">
        <a href="{{ route('admin.empresas.pactopia360.crm.contactos.index') }}" class="btn-secondary">Cancelar</a>
        <button class="btn-primary" type="submit">Guardar</button>
      </div>
    </form>
  </div>
@endsection
