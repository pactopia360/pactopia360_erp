@extends('layouts.cliente')

@section('content')
<div class="max-w-md mx-auto mt-10">
  <h1 class="text-xl font-bold mb-4">Cambia tu contraseña</h1>
  @if ($errors->any())
    <div class="bg-red-100 p-3 mb-4">
      <ul class="list-disc ml-5">
        @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
      </ul>
    </div>
  @endif
  <form method="POST" action="{{ route('cliente.password.first.store') }}">
    @csrf
    <div class="mb-4">
      <label class="block text-sm mb-1">Nueva contraseña</label>
      <input name="password" type="password" class="w-full border p-2" required minlength="8" />
    </div>
    <div class="mb-4">
      <label class="block text-sm mb-1">Confirmar contraseña</label>
      <input name="password_confirmation" type="password" class="w-full border p-2" required minlength="8" />
    </div>
    <button class="bg-blue-600 text-white px-4 py-2 rounded">Guardar</button>
  </form>
</div>
@endsection
