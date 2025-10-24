{{-- resources/views/cliente/qa/index.blade.php --}}
@extends('layouts.client') {{-- Ojo: usa layouts.client, no layouts.cliente --}}

@section('title','QA · Clientes')

@push('styles')
<style>
  .grid{display:grid;gap:14px}
  @media(min-width:960px){.grid{grid-template-columns:1fr 1fr}}
  .rows{display:grid;gap:8px}
  code{font-family:ui-monospace,monospace}
</style>
@endpush

@section('content')
<div class="card">
  <h1>QA · Portal de Clientes</h1>
  <p class="muted">Botonera y atajos para probar E2E los flujos FREE y PRO en local.</p>
  <div class="rows" style="margin-top:10px">
    <form method="POST" action="{{ route('cliente.qa.seed') }}">@csrf
      <button class="btn primary">Cargar datos de demo</button>
    </form>
    <form method="POST" action="{{ route('cliente.qa.clean') }}" onsubmit="return confirm('¿Eliminar datos de demo?')">@csrf
      <button class="btn">Eliminar datos de demo</button>
    </form>
  </div>
</div>

<div class="grid" style="margin-top:14px">
  <div class="card">
    <h2>FREE (id=101)</h2>
    <div class="rows">
      <div class="muted">
        Email: <code>free@example.com</code> · RFC: <code>ABC0102039A1</code><br>
        Tel: {{ $free?->phone ?? '—' }} · verif_email={{ $free?->email_verified_at ? 'sí' : 'no' }} · verif_phone={{ $free?->phone_verified_at ? 'sí' : 'no' }}
      </div>
      <a class="btn" href="{{ route('cliente.verify.email.token', ['token'=>'TOKENFREE1234567890abcdef']) }}">1) Verificar email (FREE)</a>
      <a class="btn" href="{{ route('cliente.verify.phone') }}">2) Ir a verificación de teléfono</a>
      <a class="btn" href="{{ route('cliente.login') }}">3) Ir a login</a>
      <a class="btn" href="{{ route('cliente.home') }}">4) Ir a Home (si ya quedó todo)</a>
      <div class="muted">OTP último: <code>{{ $otpFree?->code ?? '—' }}</code></div>
    </div>
  </div>

  <div class="card">
    <h2>PRO (id=202)</h2>
    <div class="rows">
      <div class="muted">
        Email: <code>pro@example.com</code> · RFC: <code>XYZ010203AA1</code><br>
        Sub: <code>{{ optional(DB::connection('mysql_admin')->table('subscriptions')->where('account_id',202)->first())->status ?? '—' }}</code><br>
        Tel verificado: {{ $pro?->phone_verified_at ? 'sí' : 'no' }}
      </div>
      <a class="btn" href="{{ route('cliente.login') }}">1) Login</a>
      <a class="btn" href="{{ route('cliente.password.first') }}">2) Forzar cambio de contraseña</a>
      <a class="btn" href="{{ route('cliente.verify.phone') }}">3) Verificar teléfono (si falta)</a>
      <a class="btn" href="{{ route('cliente.home') }}">4) Home</a>
      <a class="btn" href="{{ route('cliente.billing.statement') }}">5) Billing/Statement</a>
      <div class="muted">OTP último: <code>{{ $otpPro?->code ?? '—' }}</code></div>
    </div>
  </div>
</div>
@endsection
