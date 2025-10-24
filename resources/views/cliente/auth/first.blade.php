@extends('layouts.client')

@section('title','Crear tu contraseña')
{{-- opcional: oculta el logo grande si quieres una pantalla más limpia --}}
@section('hide-brand','1')

@section('content')
  <div class="card" style="max-width:560px;margin:0 auto">
    <h2 style="margin:0 0 6px;font-weight:900">Establece tu nueva contraseña</h2>
    <p class="muted" style="margin:0 0 12px">
      Por seguridad debes definir una contraseña nueva para continuar.
    </p>

    @if ($errors->any())
      <div class="alert err" style="margin-bottom:12px">
        <strong>Revisa:</strong>
        <ul style="margin:6px 0 0 18px">
          @foreach ($errors->all() as $e)
            <li>{{ $e }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    @if (session('status'))
      <div class="alert ok" style="margin-bottom:12px">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('cliente.password.first.store') }}" style="display:grid;gap:12px">
      @csrf

      {{-- Si tu flujo exige validar la temporal, descomenta:
      <label>Contraseña temporal
        <input type="password" name="temp_password" class="input" autocomplete="current-password" required>
      </label>
      --}}

      <label>Nueva contraseña
        <input type="password" name="password" class="input" autocomplete="new-password" required minlength="8">
      </label>

      <label>Confirmar contraseña
        <input type="password" name="password_confirmation" class="input" autocomplete="new-password" required minlength="8">
      </label>

      <div style="display:flex;gap:8px;align-items:center">
        <button class="btn primary" type="submit">Guardar y continuar</button>
        <a class="btn ghost" href="{{ route('cliente.home') }}">Cancelar</a>
      </div>
    </form>
  </div>
@endsection
