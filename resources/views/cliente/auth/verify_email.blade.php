{{-- resources/views/cliente/auth/verify_email.blade.php --}}
@extends('layouts.guest')

@section('title', 'Verificación de correo · Pactopia360')

@push('styles')
<style>
  .container > .card{ background:transparent; border:0; box-shadow:none; padding:0 }
  .auth-shell{ width:min(680px, 92vw); margin: clamp(24px, 8vh, 66px) auto; }
  .auth-card{
    position:relative; border-radius:24px; padding:24px 22px;
    background: linear-gradient(180deg, color-mix(in srgb, var(--card) 92%, transparent), color-mix(in srgb, var(--card) 84%, transparent));
    border:1px solid color-mix(in srgb, var(--border) 85%, transparent);
    box-shadow: 0 30px 80px rgba(0,0,0,.42);
  }
  .auth-card::before{
    content:""; position:absolute; inset:-1px; border-radius:25px; padding:1px;
    background: linear-gradient(145deg, rgba(255,107,138,.70), rgba(255,42,42,.50));
    -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
    -webkit-mask-composite: xor; mask-composite: exclude;
    pointer-events:none;
  }
  h1{ margin:0 0 8px; font-size:24px; font-weight:900 }
  .muted{ color:var(--muted); font-size:13px }
  .ok{ background:#0f5132;color:#e6fff5;border:1px solid #0b3b24;border-radius:12px;padding:10px 12px;margin:12px 0 }
  .err{ background:#7f1d1d;color:#fff;border:1px solid #991b1b;border-radius:12px;padding:10px 12px;margin:12px 0 }
  .actions{ display:flex; gap:10px; flex-wrap:wrap; margin-top:12px }
  .btn{ padding:10px 12px; border-radius:12px; border:0; font-weight:900; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; justify-content:center }
  .btn-primary{ color:#fff; background:linear-gradient(180deg,#ff2a2a,#ff3a3a); box-shadow:0 16px 30px rgba(255,42,42,.30) }
  .btn-ghost{ border:1px solid color-mix(in srgb, var(--border) 82%, transparent); background:transparent; color:var(--text) }
</style>
@endpush

@section('content')
<div class="auth-shell">
  <div class="auth-card">
    @if (isset($status) && $status === 'ok')
      <h1>¡Correo verificado!</h1>
      <p class="muted">Ahora verifica tu teléfono para asegurar tu cuenta.</p>
      @if (!empty($message)) <div class="ok" role="status">{{ $message }}</div> @endif
      @if (!empty($phone_masked))
        <p class="muted">Enviaremos un código al número terminado en <strong>{{ $phone_masked }}</strong>.</p>
      @endif
      <div class="actions">
        <a href="{{ route('cliente.verify.phone') }}" class="btn btn-primary">Verificar teléfono</a>
        <a href="{{ route('cliente.login') }}" class="btn btn-ghost">Volver al login</a>
      </div>

    @elseif (isset($status) && $status === 'expired')
      <h1>Enlace expirado</h1>
      <div class="err" role="alert">{{ $message ?? 'El enlace de verificación expiró. Solicita uno nuevo.' }}</div>
      <div class="actions">
        <a href="{{ route('cliente.verify.email.resend') }}?email={{ urlencode($email ?? '') }}" class="btn btn-primary">Reenviar verificación</a>
        <a href="{{ route('cliente.login') }}" class="btn btn-ghost">Volver al login</a>
      </div>

    @elseif (isset($status) && $status === 'error')
      <h1>Verificación no válida</h1>
      <div class="err" role="alert">{{ $message ?? 'El enlace no es válido.' }}</div>
      <div class="actions">
        <a href="{{ route('cliente.verify.email.resend') }}" class="btn btn-primary">Solicitar nuevo enlace</a>
        <a href="{{ route('cliente.login') }}" class="btn btn-ghost">Volver al login</a>
      </div>

    @else
      {{-- Estado genérico tras registro: instrucción para ir al correo --}}
      <h1>Confirma tu correo</h1>
      <p class="muted">
        Te enviamos un correo de verificación a
        <strong>{{ $email ?? 'tu dirección registrada' }}</strong>.<br>
        Da clic en el enlace dentro del mensaje para confirmar tu cuenta.
      </p>
      <p class="muted">Si no lo encuentras, revisa tu carpeta de spam.</p>
      <div class="actions">
        <a href="{{ route('cliente.verify.email.resend') }}?email={{ urlencode($email ?? '') }}" class="btn btn-primary">Reenviar verificación</a>
        <a href="{{ route('cliente.login') }}" class="btn btn-ghost">Ir al login</a>
      </div>
    @endif
  </div>
</div>
@endsection
