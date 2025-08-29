@extends('layouts.admin')

@section('title','Mi perfil')

@push('styles')
<style>
  .prf-wrap{display:grid;gap:12px;grid-template-columns:1fr 1fr}
  @media (max-width:1100px){.prf-wrap{grid-template-columns:1fr}}
  .cardx{background:var(--card-bg,#fff);border:1px solid var(--card-border,#e5e7eb);border-radius:12px;padding:14px;box-shadow:var(--shadow-1)}
  .cardx h2{margin:0 0 8px;font-size:16px}
  .frm-row{display:grid;gap:10px;margin-top:6px}
  .frm-2{grid-template-columns:1fr 1fr}
  .lbl{font-size:12px;color:var(--muted);margin-bottom:4px}
  .in{width:100%;min-height:38px;padding:8px 10px;border:1px solid var(--card-border);border-radius:10px;background:var(--card-bg);color:var(--text)}
  .help{font-size:12px;color:var(--muted)}
  .act{display:flex;gap:8px;justify-content:flex-end;margin-top:10px}
  .alert-success,.alert-danger{margin-bottom:10px}
</style>
@endpush

@section('page-header')
  <div class="page-card">
    <div class="page-title">
      <span class="ico" aria-hidden="true">👤</span>
      <div>
        <h2>Mi perfil</h2>
        <p>Datos de cuenta, preferencias de UI y seguridad</p>
      </div>
    </div>
  </div>
@endsection

@section('content')
  @if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
  @endif
  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
      </ul>
    </div>
  @endif

  <div class="prf-wrap">
    {{-- Datos básicos + Preferencias --}}
    <div class="cardx">
      <h2>Información de la cuenta</h2>
      <form method="POST" action="{{ route('admin.perfil.update') }}" id="frmProfile">
        @csrf @method('PUT')
        <div class="frm-row">
          <label>
            <div class="lbl">Nombre</div>
            <input class="in" type="text" name="nombre" value="{{ old('nombre', $u->nombre) }}" required>
          </label>
          <label>
            <div class="lbl">Correo</div>
            <input class="in" type="email" name="email" value="{{ old('email', $u->email) }}" required>
          </label>
        </div>

        <h2 style="margin-top:12px">Preferencias de interfaz</h2>
        <div class="frm-row frm-2">
          <label>
            <div class="lbl">Tema</div>
            <select class="in" name="ui_theme" id="ui_theme">
              <option value="light" @selected(($prefs['theme'] ?? 'light')==='light')>Claro</option>
              <option value="dark"  @selected(($prefs['theme'] ?? 'light')==='dark')>Oscuro</option>
            </select>
            <div class="help">Se aplica al guardar; aquí puedes previsualizarlo.</div>
          </label>
          <label>
            <div class="lbl">Densidad</div>
            <select class="in" name="ui_density" id="ui_density">
              <option value="normal"  @selected(($prefs['density'] ?? 'normal')==='normal')>Normal</option>
              <option value="compact" @selected(($prefs['density'] ?? 'normal')==='compact')>Compacta</option>
            </select>
          </label>
        </div>

        <div class="act">
          <button type="submit" class="btn btn-primary">Guardar cambios</button>
        </div>
      </form>
    </div>

    {{-- Seguridad: cambio de contraseña --}}
    <div class="cardx">
      <h2>Seguridad</h2>
      <form method="POST" action="{{ route('admin.perfil.password') }}" id="frmPassword">
        @csrf
        <div class="frm-row">
          <label>
            <div class="lbl">Contraseña actual</div>
            <input class="in" type="password" name="current_password" required>
          </label>
          <label>
            <div class="lbl">Nueva contraseña</div>
            <input class="in" type="password" name="password" minlength="8" required>
          </label>
          <label>
            <div class="lbl">Confirmar nueva contraseña</div>
            <input class="in" type="password" name="password_confirmation" minlength="8" required>
          </label>
        </div>
        <div class="act">
          <button type="submit" class="btn">Actualizar contraseña</button>
        </div>
      </form>
    </div>
  </div>
@endsection

@push('scripts')
<script>
  // Preview inmediato de tema/densidad sin recargar
  (function(){
    var html = document.documentElement;
    var themeSel   = document.getElementById('ui_theme');
    var densitySel = document.getElementById('ui_density');

    function applyTheme(v){
      html.classList.remove('theme-light','theme-dark');
      html.dataset.theme = v;
      html.classList.add(v === 'dark' ? 'theme-dark' : 'theme-light');
    }
    function applyDensity(v){
      if (v === 'compact') html.setAttribute('data-density','compact');
      else html.removeAttribute('data-density');
    }

    themeSel?.addEventListener('change', function(){ applyTheme(this.value); });
    densitySel?.addEventListener('change', function(){ applyDensity(this.value); });
  })();
</script>
@endpush
