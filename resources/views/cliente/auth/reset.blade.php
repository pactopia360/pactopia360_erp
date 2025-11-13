{{-- resources/views/cliente/auth/reset.blade.php (v2 visual Pactopia360) --}}
@extends('layouts.guest')

@section('title', 'Restablecer contraseña · Pactopia360')
@section('hide-brand', 'is-hidden')

@push('styles')
<style>
  .topbar .is-hidden{display:none!important}

  body{
    font-family:'Poppins',system-ui,sans-serif;
    color:var(--text,#0f172a);
    background:#fff8f9;
    min-height:100vh;
  }
  body::before{
    content:"";
    position:fixed;inset:-20%;
    z-index:-1;filter:blur(100px);
    background:
      radial-gradient(47% 55% at 18% 15%, rgba(255,91,126,.35), transparent 60%),
      radial-gradient(40% 50% at 82% 70%, rgba(190,18,60,.40), transparent 60%);
    animation: float 12s ease-in-out infinite alternate;
  }
  @keyframes float{0%{transform:translate3d(-3%,-3%,0)}100%{transform:translate3d(5%,6%,0)}}

  .auth-shell{
    width:min(560px,94vw);
    margin:max(6vh,32px) auto;
    position:relative;
  }

  .auth-card{
    position:relative;
    border-radius:20px;
    padding:26px 22px 22px;
    background:linear-gradient(180deg,rgba(255,255,255,.96),rgba(255,255,255,.90));
    border:1px solid #f3d5dc;
    box-shadow:0 24px 70px rgba(0,0,0,.2);
  }
  [data-theme="dark"] .auth-card{
    background:linear-gradient(180deg,#0f172a,#0b1220);
    border-color:#1d2a3a;
    box-shadow:0 24px 70px rgba(0,0,0,.55);
  }
  .auth-card::before{
    content:"";position:absolute;inset:-1px;border-radius:21px;padding:1px;
    background:linear-gradient(145deg,#E11D48,#BE123C);
    -webkit-mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);
    -webkit-mask-composite:xor;mask-composite:exclude;
    opacity:.25;pointer-events:none;
  }

  .logo-wrap{
    width:84px;height:84px;border-radius:999px;
    display:grid;place-items:center;
    margin:-62px auto 10px;
    position:relative;z-index:2;
    background:linear-gradient(180deg,#ffffff,#f6f9fc);
    border:1px solid #f3e2e5;
    box-shadow:0 16px 40px rgba(225,29,72,.25),0 6px 18px rgba(0,0,0,.18);
  }
  [data-theme="dark"] .logo-wrap{background:#0f172a;border-color:#1d2a3a;}
  .logo-wrap img{width:64px;height:auto;display:block;}

  .title{text-align:center;margin:2px 0 14px;}
  .title h1{margin:0;font-size:18px;font-weight:900;}
  .title p{margin:6px 0 0;color:#6b7280;font-size:12px;}

  .field{display:flex;flex-direction:column;gap:8px;margin:6px 0;}
  .field label{font-size:12px;color:#6b7280;font-weight:800;letter-spacing:.25px;}
  .input{
    width:100%;border-radius:12px;padding:12px 14px;
    background:#fff;color:#0f172a;border:1px solid #e5e7eb;
    outline:none;transition:.15s border-color,.15s box-shadow;
  }
  .input:focus{
    border-color:#E11D48;
    box-shadow:0 0 0 4px rgba(225,29,72,.2);
  }
  [data-theme="dark"] .input{
    background:#1e293b;color:#f1f5f9;border-color:#334155;
  }

  .input-group{position:relative;display:flex;align-items:center;}
  .input-group .input{padding-right:44px;}
  .eye{
    position:absolute;right:6px;top:50%;transform:translateY(-50%);
    border:1px solid #e5e7eb;background:transparent;color:#6b7280;
    border-radius:10px;padding:6px 8px;cursor:pointer;font-weight:800;
  }
  [data-theme="dark"] .eye{border-color:#334155;color:#9ca3af;}

  .btn-submit{
    width:100%;padding:12px 14px;border-radius:12px;border:0;
    font-weight:900;color:#fff;
    background:linear-gradient(90deg,#E11D48,#BE123C);
    box-shadow:0 12px 22px rgba(225,29,72,.25);
    margin-top:8px;transition:filter .2s;
  }
  .btn-submit:hover{filter:brightness(.96)}

  .hint{color:#6b7280;font-size:12px;margin-top:6px;text-align:center;}
  [data-theme="dark"] .hint{color:#9aa4b2;}

  .links{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-top:12px;}
  .link{color:#E11D48;text-decoration:none;font-weight:800;font-size:12px;}
  .link:hover{text-decoration:underline;}
  [data-theme="dark"] .link{color:#fb7185;}

  .alert-ok{
    background:#0f5132;color:#e6fff5;border:1px solid #0b3b24;
    border-radius:12px;padding:10px 12px;margin-bottom:10px;
  }
  .alert-err{
    background:#7f1d1d;color:#fff;border:1px solid #991b1b;
    border-radius:12px;padding:10px 12px;margin-bottom:10px;
  }
</style>
@endpush

@section('content')
  <div class="auth-shell">
    <div class="logo-wrap" aria-hidden="true">
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="{{ asset('assets/client/logop360dark.png') }}">
        <img src="{{ asset('assets/client/logop360light.png') }}" alt="Pactopia 360"
             onerror="this.src='{{ asset('assets/client/logop360dark.png') }}';">
      </picture>
    </div>

    <div class="auth-card">
      <div class="title">
        <h1>Define tu nueva contraseña</h1>
        <p>Por tu seguridad, usa mínimo 8 caracteres con número y símbolo.</p>
      </div>

      @if (session('ok'))
        <div class="alert-ok">{{ session('ok') }}</div>
      @endif
      @if ($errors->any())
        <div class="alert-err">{{ $errors->first() }}</div>
      @endif

      <form method="POST" action="{{ route('cliente.password.update') }}" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="field">
          <label for="email">Correo asociado</label>
          <input id="email" name="email" class="input" type="email"
                 placeholder="micorreo@dominio.com"
                 value="{{ old('email', $email ?? '') }}" required autocomplete="email">
        </div>

        <div class="grid grid-2" style="display:grid;gap:12px;grid-template-columns:1fr 1fr;">
          <div class="field">
            <label for="password">Nueva contraseña</label>
            <div class="input-group">
              <input id="password" name="password" class="input" type="password"
                     placeholder="••••••••" required autocomplete="new-password">
              <button class="eye" type="button" data-eye-for="password" aria-label="Mostrar u ocultar">👁</button>
            </div>
          </div>
          <div class="field">
            <label for="password_confirmation">Confirmar contraseña</label>
            <div class="input-group">
              <input id="password_confirmation" name="password_confirmation" class="input" type="password"
                     placeholder="••••••••" required autocomplete="new-password">
              <button class="eye" type="button" data-eye-for="password_confirmation" aria-label="Mostrar u ocultar">👁</button>
            </div>
          </div>
        </div>

        <button class="btn-submit" type="submit">Guardar contraseña</button>
        <div class="hint">Al continuar, se invalidará el enlace de recuperación.</div>
      </form>

      <div class="links">
        <a class="link" href="{{ route('cliente.login') }}">Volver al inicio de sesión</a>
        <a class="link" href="{{ route('cliente.password.forgot') }}">Solicitar un nuevo enlace</a>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
<script>
  document.querySelectorAll('.eye').forEach(btn=>{
    btn.addEventListener('click',()=>{
      const id=btn.getAttribute('data-eye-for');
      const input=document.getElementById(id);
      if(!input)return;
      const show=input.type==='password';
      input.type=show?'text':'password';
      btn.textContent=show?'🙈':'👁';
    });
  });
</script>
@endpush
