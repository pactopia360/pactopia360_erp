@extends('layouts.cliente')

@section('title','Establecer contraseña · Pactopia360')

@push('styles')
<style>
  /* ====== Layout coherente con /cliente/home (dentro del shell) ====== */
  .first-wrap{--gap:16px; --max-w:780px; padding:var(--gap)}
  .first-wrap .container-frm{max-width:var(--max-w); margin:0 auto; display:grid; gap:var(--gap)}
  .card{background:var(--card,#fff); border:1px solid var(--bd,#e5e7eb); border-radius:14px; overflow:hidden}
  .card .hd{padding:14px 16px; font-weight:900; border-bottom:1px solid var(--bd,#e5e7eb)}
  .card .bd{padding:18px}
  .muted{color:var(--mut,#6b7280)}
  .grid{display:grid; gap:12px}
  .grid-2{grid-template-columns:1fr 1fr}
  .field label{display:block; font-size:.92rem; font-weight:600; margin:0 0 6px}
  .input{width:100%; padding:10px 12px; border:1px solid #e5e7eb; border-radius:10px; background:#fff}
  .input:focus{outline:none; box-shadow:0 0 0 3px rgba(37,99,235,.15); border-color:#93c5fd}
  .actions{display:flex; gap:10px; align-items:center; margin-top:6px}
  .btn{display:inline-flex; align-items:center; gap:8px; border-radius:10px; padding:10px 14px; border:1px solid transparent; text-decoration:none; cursor:pointer}
  .btn-primary{background:#2563eb; color:#fff}
  .btn-light{background:#f3f4f6; color:#111827; border-color:#e5e7eb}
  .alert{border-radius:10px; padding:10px 12px; margin:12px 0}
  .alert.err{background:#fee2e2; border:1px solid #fecaca; color:#991b1b}
  .alert.ok{background:#dcfce7; border:1px solid #bbf7d0; color:#166534}
  .mini{font-size:.86rem; color:var(--mut,#6b7280)}
  /* Toggle de contraseña (sin emoji) */
  .pwd-wrap{position:relative}
  .pwd-toggle{position:absolute; right:8px; top:50%; transform:translateY(-50%); width:34px; height:34px; border:0; background:transparent; color:#64748b; border-radius:8px; cursor:pointer}
  .pwd-toggle:focus-visible{outline:none; box-shadow:0 0 0 2px rgba(225,29,72,.25)}
  .pwd-toggle svg{width:20px; height:20px; display:block}

  /* ===== Ocultar botón/toolbar de JPG solo en esta vista =====
     Cubre distintos nombres usados en el layout de demo/captura */
  #p360-demo-toolbar,
  .p360-demo-toolbar,
  .btn-demo-jpg,
  .btn-snap,
  .btn-jpg,
  [data-demo="screenshot"],
  [data-action="screenshot"],
  [data-role="screenshot"]{ display:none !important; }

  @media (max-width:900px){ .grid-2{grid-template-columns:1fr} }
</style>
@endpush

@section('content')
  <div class="first-wrap">
    <div class="container-frm">
      <div class="card">
        <div class="hd">Establece tu nueva contraseña</div>
        <div class="bd">
          <p class="muted">Por seguridad debes definir una contraseña nueva para continuar.</p>

          {{-- Alertas de sesión y errores --}}
          @if (session('ok'))
            <div class="alert ok">{{ session('ok') }}</div>
          @endif
          @if (session('error'))
            <div class="alert err">{{ session('error') }}</div>
          @endif
          @if ($errors->any())
            <div class="alert err">
              <ul style="margin:0 0 0 18px">
                @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
              </ul>
            </div>
          @endif

          <form class="grid" method="POST" action="{{ route('cliente.password.first.store') }}">
            @csrf

            {{-- Email visible (solo lectura) --}}
            <div class="field">
              <label for="email">Correo electrónico</label>
              <input id="email" name="email" type="email" class="input"
                     value="{{ old('email', $email ?? (auth('web')->user()->email ?? '')) }}"
                     placeholder="micorreo@dominio.com" required autocomplete="email" readonly>
            </div>

            <div class="grid grid-2">
              <div class="field pwd-wrap">
                <label for="password">Nueva contraseña</label>
                <input id="password" name="password" type="password" class="input @error('password') is-invalid @enderror"
                       placeholder="********" required minlength="8" autocomplete="new-password" autofocus>
                <button class="pwd-toggle" type="button" data-for="password" aria-label="Mostrar contraseña">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"/>
                    <circle cx="12" cy="12" r="3"/>
                  </svg>
                </button>
              </div>

              <div class="field pwd-wrap">
                <label for="password_confirmation">Confirmar contraseña</label>
                <input id="password_confirmation" name="password_confirmation" type="password" class="input"
                       placeholder="********" required minlength="8" autocomplete="new-password">
                <button class="pwd-toggle" type="button" data-for="password_confirmation" aria-label="Mostrar confirmación">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"/>
                    <circle cx="12" cy="12" r="3"/>
                  </svg>
                </button>
              </div>
            </div>

            <div class="actions">
              <button class="btn btn-primary" type="submit">Guardar y continuar</button>
              <a class="btn btn-light" href="{{ route('cliente.home') }}">Cancelar</a>
            </div>

            <p class="mini">Tip: usa al menos 8 caracteres, mayúsculas, minúsculas y números.</p>
          </form>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
<script>
  // Toggle show/hide password
  document.querySelectorAll('.pwd-toggle').forEach(function(btn){
    btn.addEventListener('click', function(){
      var id = btn.getAttribute('data-for');
      var input = document.getElementById(id);
      if(!input) return;
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.setAttribute('aria-label', show ? 'Ocultar' : 'Mostrar');
    });
  });
</script>
@endpush
