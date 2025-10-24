{{-- resources/views/cliente/auth/password_first.blade.php --}}
@extends('layouts.guest')

@section('title', 'Define tu contraseña segura · Pactopia360')

{{-- Ocultamos la marca de la topbar para centrar la tarjeta --}}
@section('hide-brand', 'is-hidden')

@push('styles')
<style>
  .topbar .is-hidden{ display:none !important; }

  /* Fondo con acento femenino (rosa/rojo) */
  body::before{
    background:
      radial-gradient(47% 55% at 18% 15%, rgba(255,91,126,.34), transparent 60%),
      radial-gradient(40% 50% at 82% 70%, rgba(40,70,102,.55), transparent 60%);
    filter: blur(100px);
  }

  .container > .card{ background:transparent; border:0; box-shadow:none; padding:0 }

  .auth-shell{
    width:min(560px, 94vw);
    margin: max(6vh, 32px) auto;
    position:relative;
  }

  .auth-card{
    position:relative;
    border-radius:20px;
    padding:26px 22px 22px;
    background: linear-gradient(180deg,
      color-mix(in srgb, var(--card) 92%, transparent),
      color-mix(in srgb, var(--card) 84%, transparent));
    border:1px solid color-mix(in srgb, var(--border) 85%, transparent);
    box-shadow: 0 28px 70px rgba(0,0,0,.38);
  }
  .auth-card::before{
    content:""; position:absolute; inset:-1px; border-radius:21px; padding:1px;
    background: linear-gradient(145deg, rgba(255,91,126,.55), rgba(255,42,42,.45));
    -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
    -webkit-mask-composite: xor; mask-composite: exclude;
    border:1px solid transparent; pointer-events:none;
  }

  .logo-wrap{
    width:84px;height:84px;border-radius:999px;
    display:grid;place-items:center;
    margin:-62px auto 10px;
    position:relative; z-index:2;
    background: linear-gradient(180deg,#ffffff,#f6f9fc);
    border:1px solid color-mix(in srgb, var(--border) 90%, transparent);
    box-shadow: 0 16px 40px rgba(255,91,126,.25), 0 6px 18px rgba(0,0,0,.18);
  }
  [data-theme="dark"] .logo-wrap{
    background: linear-gradient(180deg,#0f172a,#0d1626);
    border-color: #1d2a3a;
  }
  .logo-wrap img{ width:64px;height:auto;display:block }

  .title{ text-align:center; margin:2px 0 14px }
  .title h1{ margin:0; font-size:18px; font-weight:900; letter-spacing:.2px }
  .title p{ margin:6px 0 0; color:var(--muted); font-size:12px }

  .grid{ display:grid; grid-template-columns: 1fr; gap:12px; margin-top:6px }
  @media (min-width: 560px){
    .grid-2{ grid-template-columns: 1fr 1fr; }
  }

  .field{ display:flex; flex-direction:column; gap:8px; margin:6px 0 }
  .field label{ font-size:12px; color:var(--muted); font-weight:800; letter-spacing:.25px }
  .input{
    width:100%; border-radius:12px; padding:12px 14px;
    background: color-mix(in srgb, var(--card) 92%, transparent);
    color: var(--text);
    border:1px solid color-mix(in srgb, var(--border) 82%, transparent);
    outline:none; transition: .15s border-color, .15s box-shadow;
  }
  .input:focus{
    border-color: color-mix(in srgb, #ff5b7e 56%, var(--border));
    box-shadow: 0 0 0 5px color-mix(in srgb, #ff5b7e 22%, transparent);
  }
  .input-group{ position:relative; display:flex; align-items:center }
  .input-group .input{ padding-right:44px }
  .eye{
    position:absolute; right:6px; top:50%; transform:translateY(-50%);
    border:1px solid color-mix(in srgb, var(--border) 82%, transparent);
    background:transparent; color:var(--muted);
    border-radius:10px; padding:6px 8px; cursor:pointer; font-weight:800;
  }

  /* Medidor de fortaleza */
  .strength{ display:flex; gap:6px; margin-top:6px; align-items:center }
  .pill{ height:8px; border-radius:999px; flex:1; background:color-mix(in srgb, var(--card) 70%, transparent); border:1px solid color-mix(in srgb, var(--border) 82%, transparent) }
  .pill.on--weak{ background:#fca5a5; border-color:#f87171 }
  .pill.on--ok{ background:#fcd34d; border-color:#f59e0b }
  .pill.on--good{ background:#86efac; border-color:#22c55e }
  .strength-label{ font-size:11px; color:var(--muted); font-weight:800; min-width:84px; text-align:right }

  .btn-submit{
    width:100%; padding:12px 14px; border-radius:12px; border:0;
    font-weight:900; color:#fff;
    background: linear-gradient(180deg, #ff5b7e, #ff2a2a);
    box-shadow: 0 12px 22px rgba(255,42,42,.26);
    margin-top:6px;
  }
  .btn-submit:hover{ filter:brightness(.96) }

  .hint{ color:var(--muted); font-size:12px; margin-top:6px }
  .links{ display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap; margin-top:10px }
  .link{ color:var(--text); text-decoration:none; font-weight:800; font-size:12px }
  .link:hover{ text-decoration:underline }

  .alert-ok{
    background:#0f5132;color:#e6fff5;border:1px solid #0b3b24;border-radius:12px;padding:10px 12px;margin-bottom:10px
  }
  .alert-err{
    background:#7f1d1d;color:#fff;border:1px solid #991b1b;border-radius:12px;padding:10px 12px;margin-bottom:10px
  }
</style>
@endpush

@section('content')
  <div class="auth-shell">
    <div class="logo-wrap" aria-hidden="true">
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="{{ asset('assets/client/logop360dark.png') }}">
        <img src="{{ asset('assets/client/logp360ligjt.png') }}" alt="Pactopia 360"
             onerror="this.src='{{ asset('assets/client/logop360dark.png') }}';">
      </picture>
    </div>

    <div class="auth-card">
      <div class="title">
        <h1>Crea tu nueva contraseña</h1>
        <p>Por tu seguridad, usa mínimo 8 caracteres, con al menos un número y un carácter especial.</p>
      </div>

      @if (session('ok'))
        <div class="alert-ok">{{ session('ok') }}</div>
      @endif

      @if ($errors->any())
        <div class="alert-err">{{ $errors->first() }}</div>
      @endif

      <form method="POST" action="{{ route('cliente.password.first.update') }}" novalidate>
        @csrf

        <div class="grid grid-2">
          <div class="field">
            <label for="password">Nueva contraseña</label>
            <div class="input-group">
              <input id="password" name="password" class="input" type="password"
                     placeholder="••••••••" required autocomplete="new-password">
              <button class="eye" type="button" data-eye-for="password" aria-label="Mostrar u ocultar">👁</button>
            </div>

            {{-- Medidor de fortaleza --}}
            <div class="strength" aria-live="polite">
              <div class="pill" id="st1"></div>
              <div class="pill" id="st2"></div>
              <div class="pill" id="st3"></div>
              <div class="strength-label" id="stLabel">Débil</div>
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

        <button class="btn-submit" type="submit">Guardar y continuar</button>

        <div class="hint">Tu sesión permanecerá activa. Podrás cambiarla más tarde desde tu perfil.</div>
      </form>

      <div class="links">
        <a class="link" href="{{ route('cliente.home') }}">Ir al inicio</a>
        <a class="link" href="{{ route('cliente.logout') }}"
           onclick="event.preventDefault(); document.getElementById('logoutForm').submit();">Cerrar sesión</a>
      </div>

      <form id="logoutForm" action="{{ route('cliente.logout') }}" method="POST" style="display:none">
        @csrf
      </form>
    </div>
  </div>
@endsection

@push('scripts')
<script>
  // Mostrar / ocultar contraseñas
  (function(){
    document.querySelectorAll('.eye').forEach(function(btn){
      btn.addEventListener('click', function(){
        const id = btn.getAttribute('data-eye-for');
        const input = document.getElementById(id);
        if(!input) return;
        const to = input.type === 'password' ? 'text' : 'password';
        input.type = to;
        btn.textContent = to === 'password' ? '👁' : '🙈';
      });
    });
  })();

  // Medidor de fortaleza simple (min 8, número, especial)
  (function(){
    const pwd = document.getElementById('password');
    const st1 = document.getElementById('st1');
    const st2 = document.getElementById('st2');
    const st3 = document.getElementById('st3');
    const label = document.getElementById('stLabel');

    if(!pwd || !st1 || !st2 || !st3 || !label) return;

    const hasNum = v => /[0-9]/.test(v);
    const hasSpec = v => /[@$!%*?&._-]/.test(v);

    function update(){
      const v = pwd.value || '';
      let score = 0;
      if(v.length >= 8) score++;
      if(hasNum(v)) score++;
      if(hasSpec(v)) score++;

      // reset
      [st1,st2,st3].forEach(x => { x.className = 'pill'; });
      label.textContent = 'Débil';

      if(score === 1){
        st1.classList.add('on--weak'); label.textContent = 'Débil';
      } else if(score === 2){
        st1.classList.add('on--ok'); st2.classList.add('on--ok'); label.textContent = 'Aceptable';
      } else if(score >= 3){
        st1.classList.add('on--good'); st2.classList.add('on--good'); st3.classList.add('on--good'); label.textContent = 'Fuerte';
      }
    }
    pwd.addEventListener('input', update);
    update();
  })();
</script>
@endpush
