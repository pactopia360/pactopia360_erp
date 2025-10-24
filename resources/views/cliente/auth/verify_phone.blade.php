{{-- resources/views/cliente/auth/verify_phone.blade.php --}}
@extends('layouts.guest')

@section('title', 'Verificación de teléfono · Pactopia360')

@push('styles')
<style>
  .container > .card{ background:transparent; border:0; box-shadow:none; padding:0 }
  .auth-shell{ width:min(720px, 96vw); margin: clamp(24px, 8vh, 66px) auto; }
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
  .ok{ background:#0f5132;color:#e6fff5;border:1px solid #0b3b24;border-radius:12px;padding:10px 12px;margin:10px 0 }
  .err{ background:#7f1d1d;color:#fff;border:1px solid #991b1b;border-radius:12px;padding:10px 12px;margin:10px 0 }
  .grid{ display:grid; gap:14px; grid-template-columns: 1fr; }
  @media (min-width: 760px){ .grid{ grid-template-columns: 1fr 1fr; } }
  .field{ display:flex; flex-direction:column; gap:8px; }
  .label{ font-size:12px; color:var(--muted); font-weight:800 }
  .select, .input{
    width:100%; border-radius:12px; padding:12px 14px;
    background: color-mix(in srgb, var(--card) 92%, transparent);
    color: var(--text);
    border:1px solid color-mix(in srgb, var(--border) 82%, transparent);
    outline:none; transition: .15s border-color, .15s box-shadow;
  }
  .select:focus, .input:focus{
    border-color: color-mix(in srgb, #ff6b8a 56%, var(--border));
    box-shadow: 0 0 0 5px color-mix(in srgb, #ff6b8a 22%, transparent);
  }
  .btn{
    width:100%; padding:12px 14px; border-radius:12px; border:0; margin-top:6px;
    font-weight:900; color:#fff;
    background: linear-gradient(180deg, #ff2a2a, #ff3a3a);
    box-shadow: 0 16px 30px rgba(255,42,42,.30);
    text-decoration:none; display:inline-flex; align-items:center; justify-content:center;
  }
  .code-input{ letter-spacing: 6px; text-align:center; font-weight:900; font-size:18px; }
  .hint{ font-size:12px; color:var(--muted); margin-top:6px }
</style>
@endpush

@section('content')
<div class="auth-shell">
  <div class="auth-card">
    <h1>Verificación de teléfono</h1>
    <p class="muted">Te enviaremos un código de <strong>6 dígitos</strong> para confirmar tu número.</p>

    @if (session('ok')) <div class="ok" role="status">{{ session('ok') }}</div> @endif
    @if ($errors->any()) <div class="err" role="alert">{{ $errors->first() }}</div> @endif

    {{-- Form A: Actualizar teléfono y enviar OTP --}}
    <form method="POST" action="{{ route('cliente.verify.phone.update') }}" style="margin-top:8px">
      @csrf
      <div class="grid">
        <div class="field">
          <label class="label" for="telefono">Editar teléfono (opcional)</label>
          <input class="input" type="tel" name="telefono" id="telefono" placeholder="+52 55 1234 5678" maxlength="25">
          <div class="hint">Tu número actual: <strong>{{ $phone_masked ?? 'No disponible' }}</strong></div>
        </div>
        <div class="field">
          <label class="label" for="channelA">Canal</label>
          <select class="select" name="channel" id="channelA">
            <option value="sms">SMS</option>
            <option value="whatsapp">WhatsApp</option>
          </select>
        </div>
      </div>
      <button class="btn" type="submit">Actualizar y enviar código</button>
    </form>

    {{-- Form B: Enviar OTP al número ya guardado --}}
    <form method="POST" action="{{ route('cliente.verify.phone.send') }}" style="margin-top:12px">
      @csrf
      <div class="grid">
        <div class="field">
          <label class="label" for="channelB">Enviar a número registrado</label>
          <select class="select" name="channel" id="channelB" required>
            <option value="sms">SMS</option>
            <option value="whatsapp">WhatsApp</option>
          </select>
        </div>
        <div class="field">
          <label class="label">Número registrado</label>
          <input class="input" type="text" value="{{ $phone_masked ?? 'No disponible' }}" disabled>
        </div>
      </div>
      <button class="btn" type="submit">Enviar código</button>
    </form>

    {{-- Form C: Validar OTP --}}
    <form method="POST" action="{{ route('cliente.verify.phone.check') }}" style="margin-top:12px">
      @csrf
      <div class="field">
        <label class="label" for="code">Código de 6 dígitos</label>
        <input id="code" name="code" class="input code-input" inputmode="numeric" maxlength="6" minlength="6" placeholder="••••••" required>
      </div>
      <button class="btn" type="submit">Verificar código</button>
    </form>

    <div class="hint" style="margin-top:10px">
      ¿No te llegó? espera unos segundos o solicita otro. El código expira en 10 minutos.
    </div>
    <div class="hint" style="margin-top:10px">
      ¿Ya verificaste? <a href="{{ route('cliente.login') }}">Ir al login</a>
    </div>
  </div>
</div>
@endsection
