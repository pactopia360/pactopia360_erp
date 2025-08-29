@extends('admin.layouts.app')

@section('content')
<div class="max-w-xl mx-auto bg-white dark:bg-zinc-900 rounded-2xl p-6 shadow">
  <h1 class="text-xl font-bold mb-4">Registrar nueva cuenta</h1>
  <form method="POST" action="{{ route('admin.register.store') }}">
    @csrf
    <div class="grid gap-3">
      <input name="rfc_padre" class="input" placeholder="RFC (13)" required maxlength="13" />
      <input name="razon_social" class="input" placeholder="Razón social" required />
      <input name="email" type="email" class="input" placeholder="Email" required />
      <div class="grid grid-cols-2 gap-3">
        <select name="licencia" class="input">
          <option value="free">Free</option>
          <option value="pro">Pro</option>
        </select>
        <select name="ciclo" class="input">
          <option value="mensual">Mensual</option>
          <option value="anual">Anual</option>
        </select>
      </div>
      <input name="password" type="password" class="input" placeholder="Contraseña" required />
      <input name="password_confirmation" type="password" class="input" placeholder="Confirmar contraseña" required />
      <button class="btn-primary">Crear cuenta</button>
    </div>
  </form>
</div>
@endsection
