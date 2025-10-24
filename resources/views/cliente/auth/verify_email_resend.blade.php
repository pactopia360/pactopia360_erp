{{-- resources/views/cliente/auth/verify_email_resend.blade.php --}}
@extends('layouts.guest')

@section('title', 'Reenviar verificación · Pactopia360')

@push('styles')
<style>
  .container > .card{ background:transparent; border:0; box-shadow:none; padding:0 }
  .auth-shell{ width:min(600px, 92vw); margin: clamp(24px, 8vh, 66px) auto; }
  .auth-card{
    position:relative; border-radius:24px; padding:24px 22px;
    background: linear-gradient(180deg,
      color-mix(in srgb, var(--card) 92%, transparent),
      color-mix(in srgb, var(--card) 84%, transparent));
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
  .field{ display:flex; flex-direction:column; gap:8px; margin:10px 0 }
  .field label{ font-size:12px; color:var(--muted); font-weight:800 }
  .input{
    width:100%; border-radius:12px; padding:12px 14px;
    background: color-mix(in srgb, var(--card) 92%, transparent);
    color: var(--text);
    border:1px solid color-mix(in srgb, var(--border) 82%, transparent);
    outline:none; transition: .15s border-color, .15s box-shadow;
  }
  .input:focus{
    border-color: color-mix(in srgb, #ff6b8a 56%, var(--border));
    box-shadow: 0 0 0 5px color-mix(in srgb, #ff6b8a 22%, transparent);
  }
  .btn{
    width:100%; padding:12px 14px; border-radius:12px; border:0; margin-top:12px;
    font-weight:900; color:#fff;
    background: linear-gradient(180deg, #ff2a2a, #ff3a3a);
    box-shadow: 0 16px 30px rgba(255,42,42,.30);
  }
  .ok{ background:#0f5132;color:#e6fff5;border:1px solid #0b3b24;border-radius:12px;padding:10px 12px;margin:10px 0 }
  .err{ background:#7f1d1d;color:#fff;border:1px solid #991b1b;border-radius:12px;padding:10px 12px;margin:10px 0 }
</style>
@endpush

@section('content')
<div class="auth-shell">
  <div class="auth-card">
    <h1>Reenviar verificación</h1>
    <p class="muted">Escribe el correo con el que te registraste y te enviaremos un nuevo enlace.</p>

    @if (session('ok'))
      <div class="ok" role="status">{{ session('ok') }}</div>
    @endif
    @if ($errors->any())
      <div class="err" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('cliente.verify.email.resend.do') }}">
      @csrf
      <div class="field">
        <label for="email">Correo electrónico</label>
        <input class="input" type="email" id="email" name="email" required maxlength="150"
               value="{{ old('email', request('email')) }}" placeholder="micorreo@dominio.com">
      </div>
      <button class="btn" type="submit">Enviar enlace</button>
    </form>
  </div>
</div>
@endsection
