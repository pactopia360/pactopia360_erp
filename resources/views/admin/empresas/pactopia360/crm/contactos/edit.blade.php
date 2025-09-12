{{-- resources/views/admin/empresas/pactopia360/crm/contactos/edit.blade.php --}}
@extends('admin.empresas.pactopia360.crm.layout')
@section('title', 'Editar contacto · CRM · Pactopia360')

@section('crm')
  @if (session('ok'))
    <div class="crm-card" style="border-left:4px solid #10b981">{{ session('ok') }}</div>
  @endif

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
    <form method="POST" action="{{ route('admin.empresas.pactopia360.crm.contactos.update', $contacto) }}">
      @csrf
      @method('PUT')

      @include('admin.empresas.pactopia360.crm.contactos._form', [
        'contacto' => $contacto,
        'mode'     => 'edit'
      ])

      <div class="form-actions">
        <a href="{{ route('admin.empresas.pactopia360.crm.contactos.index') }}" class="btn-secondary">Cancelar</a>
        <button class="btn-primary" type="submit">Actualizar</button>
      </div>
    </form>
  </div>
@endsection
