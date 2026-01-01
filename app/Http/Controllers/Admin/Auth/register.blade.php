@extends('layouts.cliente-auth')
@section('title','Crear cuenta GRATIS · Pactopia360')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/client/css/auth-register.css') }}">
@endpush

@section('content')
<div class="auth-bg">
  <div class="auth-card">

    {{-- Header --}}
    <div class="auth-header">
      <img src="{{ asset('assets/client/img/logo-p360.svg') }}" alt="Pactopia360" class="auth-logo">

      <span class="badge-free">PLAN FREE</span>

      <h1>Crear cuenta gratis</h1>
      <p>
        Accede a Pactopia360 sin costo.<br>
        Verificaremos tu correo y teléfono para activar tu cuenta.
      </p>
    </div>

    {{-- Form --}}
    <form method="POST" action="{{ route('cliente.register') }}" class="auth-form">
      @csrf

      <div class="form-group">
        <label>Nombre completo</label>
        <input type="text" name="nombre" placeholder="Tu nombre y apellido" required>
      </div>

      <div class="form-group">
        <label>Correo electrónico</label>
        <input type="email" name="email" placeholder="correo@empresa.com" required>
      </div>

      <div class="form-group">
        <label>RFC con homoclave</label>
        <input type="text" name="rfc" placeholder="XAXX010101000" required>
        <small>No se permiten cuentas duplicadas por RFC.</small>
      </div>

      <div class="form-group">
        <label>Teléfono</label>
        <input type="tel" name="telefono" placeholder="+52 55 1234 5678" required>
      </div>

      <div class="form-terms">
        <label>
          <input type="checkbox" required>
          Acepto los <a href="#">términos y condiciones</a>
        </label>
      </div>

      <button type="submit" class="btn-primary">
        Crear cuenta gratis
      </button>
    </form>

    {{-- Divider --}}
    <div class="auth-divider">
      <span>o</span>
    </div>

    {{-- Secondary actions --}}
    <div class="auth-actions">
      <a href="{{ route('cliente.login') }}">¿Ya tienes cuenta? Inicia sesión</a>
      <a href="{{ route('cliente.pricing') }}" class="btn-pro">Pasar a PRO</a>
    </div>

  </div>
</div>
@endsection
