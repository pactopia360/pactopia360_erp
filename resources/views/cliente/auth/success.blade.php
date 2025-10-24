@extends('layouts.cliente')

@section('title','Pago exitoso')

@section('content')
<div class="card" style="text-align:center">
  <h1 class="text-2xl font-bold" style="margin-bottom:6px">✅ Pago confirmado</h1>
  <p>Gracias por tu compra de Pactopia360 PRO.</p>
  <div style="display:flex;gap:10px;justify-content:center;margin-top:12px;flex-wrap:wrap">
    <a href="{{ route('cliente.login') }}" class="btn primary">Iniciar sesión</a>
    @if (Route::has('cliente.billing.statement'))
      <a href="{{ route('cliente.billing.statement') }}" class="btn">Ver mis pagos</a>
    @endif
  </div>
</div>
@endsection
