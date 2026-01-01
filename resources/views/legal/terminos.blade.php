{{-- resources/views/cliente/legal/terminos.blade.php --}}
@extends('layouts.cliente')

@section('title', 'Términos y condiciones · Pactopia360')
@section('pageClass', 'page-legal-terminos')

@push('styles')
<style>
  .legal-wrap{max-width: 980px; margin: 28px auto; padding: 0 16px;}
  .legal-card{
    background: var(--card, #fff);
    border: 1px solid var(--line, rgba(229,231,235,1));
    border-radius: 16px;
    box-shadow: 0 10px 30px rgba(15,23,42,.06);
    padding: 22px;
  }
  .legal-title{font-size: 22px; font-weight: 800; color: var(--ink, #0f172a); margin: 0 0 6px;}
  .legal-sub{color: var(--mut, #64748b); margin: 0 0 18px;}
  .legal-content{color: var(--ink, #0f172a); line-height: 1.7; font-size: 14.5px;}
  .legal-content h3{margin: 18px 0 8px; font-size: 16px;}
  .legal-content ul{margin: 8px 0 0 18px;}
  .legal-actions{display:flex; gap:10px; margin-top: 18px; flex-wrap: wrap;}
  .btn-legal{
    display:inline-flex; align-items:center; justify-content:center;
    height: 40px; padding: 0 14px; border-radius: 12px;
    border: 1px solid var(--line, rgba(229,231,235,1));
    background: var(--card, #fff);
    color: var(--ink, #0f172a);
    text-decoration:none;
    font-weight: 700;
  }
  .btn-legal:hover{background: rgba(2,6,23,.03);}
</style>
@endpush

@section('content')
<div class="legal-wrap">
  <div class="legal-card">
    <h1 class="legal-title">Términos y condiciones</h1>
    <p class="legal-sub">Última actualización: {{ now()->format('d/m/Y') }}</p>

    <div class="legal-content">
      <p>
        Este documento describe los términos y condiciones de uso de la plataforma Pactopia360.
        Al registrarte y/o utilizar el servicio, aceptas estos términos.
      </p>

      <h3>1. Uso del servicio</h3>
      <ul>
        <li>El acceso y uso está sujeto al plan contratado (Free / Premium).</li>
        <li>El usuario es responsable de la veracidad de la información registrada.</li>
      </ul>

      <h3>2. Pagos, renovaciones y bloqueo</h3>
      <ul>
        <li>Las suscripciones Premium pueden ser mensuales o anuales, según la configuración vigente.</li>
        <li>En caso de falta de pago, la cuenta puede ser restringida conforme a la política de la plataforma.</li>
      </ul>

      <h3>3. Soporte</h3>
      <ul>
        <li>El soporte puede variar de acuerdo con el plan.</li>
      </ul>

      <h3>4. Modificaciones</h3>
      <p>
        Pactopia360 puede actualizar estos términos cuando sea necesario. La versión vigente estará publicada en esta página.
      </p>
    </div>

    <div class="legal-actions">
      <a class="btn-legal" href="{{ url()->previous() }}">Volver</a>
      <a class="btn-legal" href="{{ url('/cliente/registro') }}">Ir a registro</a>
    </div>
  </div>
</div>
@endsection
