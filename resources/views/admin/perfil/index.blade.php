@extends('layouts.admin')
@php
  $user = auth('admin')->user();
  $acctCss = asset('assets/admin/css/perfil.css');
  try { $abs = public_path('assets/admin/css/perfil.css'); if (is_file($abs)) { $acctCss .= (str_contains($acctCss,'?')?'&v=':'?v=').filemtime($abs); } } catch (\Throwable $e) {}
@endphp

@section('title','Mi perfil')

@push('styles')
<link id="css-perfil" rel="stylesheet" href="{{ $acctCss }}">
<style>
/* Fallback mínimo si no carga perfil.css */
.acct-page .card{background:var(--card-bg,#fff);border:1px solid var(--card-border,#e5e7eb);border-radius:12px;padding:12px}
.acct-grid{display:grid;grid-template-columns:360px 1fr;gap:12px}
@media (max-width:1100px){.acct-grid{grid-template-columns:1fr}}
.acct-avatar{width:72px;height:72px;border-radius:50%;display:grid;place-items:center;font-weight:800;background:color-mix(in oklab,var(--accent,#1f4ddb) 15%, transparent);color:var(--text)}
.acct-kv{display:grid;grid-template-columns:140px 1fr;gap:6px 10px;font-size:14px}
.acct-kv .k{color:var(--muted)}
.chips{display:flex;gap:6px;flex-wrap:wrap}
.chip{display:inline-block;padding:4px 10px;border-radius:9999px;font-size:12px;font-weight:700;background:color-mix(in oklab,var(--text) 8%,transparent)}
.btn{padding:8px 12px;border-radius:10px;border:1px solid var(--card-border);background:var(--card-bg);cursor:pointer}
.btn-primary{background:linear-gradient(135deg,var(--brand-red,#d72d08),color-mix(in oklab,var(--brand-red,#d72d08) 70%,black 30%));border:0;color:#fff}
.input{width:100%;min-height:38px;border:1px solid var(--card-border,#e5e7eb);border-radius:10px;padding:8px 10px;background:var(--card-bg);color:var(--text)}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
@media (max-width:900px){.form-row{grid-template-columns:1fr}}
.small{font-size:12px;color:var(--muted)}
</style>
@endpush

@section('page-header')
  <div class="acct-page">
    <h1 class="h4 m-0">Mi perfil</h1>
    <p class="small mt-1">Configura los datos de tu cuenta y tus preferencias de interfaz.</p>
  </div>
@endsection

@section('content')
<div class="acct-page">
  <div class="acct-grid">
    {{-- Columna izquierda: tarjeta de identidad --}}
    <section class="card">
      <div style="display:flex;gap:12px;align-items:center">
        <div class="acct-avatar" aria-hidden="true">
          {{ strtoupper(mb_substr($user->nombre ?? ($user->email ?? 'U'),0,1)) }}
        </div>
        <div>
          <div style="font-weight:800">{{ $user->nombre ?? '—' }}</div>
          <div class="small"><code>{{ $user->email }}</code></div>
          <div class="chips mt-1">
            @php $rol = strtolower((string)($user->rol ?? 'admin')); @endphp
            <span class="chip">Rol: {{ $rol ?: 'admin' }}</span>
            @if(!empty($user->es_superadmin)) <span class="chip">Superadmin</span> @endif
            @if(!empty($user->activo)) <span class="chip">Activo</span> @else <span class="chip">Inactivo</span> @endif
          </div>
        </div>
      </div>

      <hr style="margin:12px 0;border:0;border-top:1px solid var(--card-border)">

      <div class="acct-kv">
        <div class="k">ID</div>      <div>{{ $user->id }}</div>
        <div class="k">Creado</div>  <div>{{ optional($user->created_at)->format('Y-m-d H:i') ?: '—' }}</div>
        <div class="k">Último acceso</div><div>{{ optional($user->last_login_at ?? null)->format('Y-m-d H:i') ?: '—' }}</div>
      </div>

      <div class="mt-3">
        @if (Route::has('admin.perfil.edit'))
          <a class="btn" href="{{ route('admin.perfil.edit') }}">Editar datos</a>
        @endif
        <button class="btn" id="btnSessions">Sesiones</button>
      </div>
    </section>

    {{-- Columna derecha: formularios --}}
    <section class="card">
      {{-- Preferencias UI (sin rutas adicionales; usa localStorage + clase en <html>) --}}
      <h3 style="margin:0 0 6px">Preferencias de interfaz</h3>
      <div class="form-row">
        <label>
          <div class="small">Tema</div>
          <select id="uiTheme" class="input" aria-label="Tema">
            @php $t = session('ui.theme','light'); @endphp
            <option value="light" @selected($t==='light')>Claro</option>
            <option value="dark"  @selected($t==='dark')>Oscuro</option>
            <option value="auto"  @selected($t==='auto')>Automático</option>
          </select>
        </label>
        <label>
          <div class="small">Densidad</div>
          <select id="uiDensity" class="input" aria-label="Densidad">
            @php $d = session('ui.density','comfortable'); @endphp
            <option value="comfortable" @selected($d==='comfortable')>Cómoda</option>
            <option value="compact"     @selected($d==='compact')>Compacta</option>
          </select>
        </label>
      </div>

      <hr style="margin:12px 0;border:0;border-top:1px solid var(--card-border)">

      {{-- Datos básicos (POST opcional si tienes ruta) --}}
      @if (Route::has('admin.perfil.update'))
      <form method="POST" action="{{ route('admin.perfil.update') }}">
        @csrf @method('PUT')
        <h3 style="margin:0 0 6px">Datos básicos</h3>
        <div class="form-row">
          <label>
            <div class="small">Nombre</div>
            <input class="input" type="text" name="nombre" value="{{ old('nombre',$user->nombre) }}">
          </label>
          <label>
            <div class="small">Teléfono</div>
            <input class="input" type="text" name="telefono" value="{{ old('telefono',$user->telefono ?? '') }}">
          </label>
        </div>
        <div class="mt-2">
          <button class="btn btn-primary" type="submit">Guardar</button>
        </div>
      </form>
      @endif

      <hr style="margin:12px 0;border:0;border-top:1px solid var(--card-border)">

      {{-- Seguridad: cambio de contraseña (si existe ruta) --}}
      @if (Route::has('admin.perfil.password'))
      <form method="POST" action="{{ route('admin.perfil.password') }}" autocomplete="off">
        @csrf
        <h3 style="margin:0 0 6px">Seguridad</h3>
        <div class="form-row">
          <label>
            <div class="small">Nueva contraseña</div>
            <input class="input" type="password" name="password" id="pwd1">
          </label>
          <label>
            <div class="small">Confirmar</div>
            <input class="input" type="password" name="password_confirmation" id="pwd2">
          </label>
        </div>
        <div class="mt-2">
          <button class="btn btn-primary" type="submit">Actualizar contraseña</button>
        </div>
      </form>
      @endif
    </section>
  </div>
</div>
@endsection

@push('scripts')
<script>
/* Asegurar CSS del módulo (PJAX-safe) */
(function(){var id='css-perfil',href=@json($acctCss);function ensure(){if(!document.getElementById(id)){var l=document.createElement('link');l.id=id;l.rel='stylesheet';l.href=href;document.head.appendChild(l);}}ensure();addEventListener('p360:pjax:after',ensure);})();

/* Preferencias UI: persiste en localStorage y aplica en vivo */
(function(){
  const html = document.documentElement;
  const themeSel   = document.getElementById('uiTheme');
  const densitySel = document.getElementById('uiDensity');

  function applyTheme(v){
    if(v==='auto'){ html.removeAttribute('data-theme'); html.classList.remove('theme-dark','theme-light'); return; }
    html.setAttribute('data-theme', v);
    html.classList.toggle('theme-dark',  v==='dark');
    html.classList.toggle('theme-light', v==='light');
  }
  function applyDensity(v){
    if(v==='compact') html.setAttribute('data-density','compact');
    else html.removeAttribute('data-density');
  }
  themeSel?.addEventListener('change', e => { localStorage.setItem('ui.theme', e.target.value); applyTheme(e.target.value); });
  densitySel?.addEventListener('change', e => { localStorage.setItem('ui.density', e.target.value); applyDensity(e.target.value); });

  // Inicializa desde localStorage (si hay) sin romper la sesión actual:
  const t = localStorage.getItem('ui.theme');   if(t) { themeSel.value=t; applyTheme(t); }
  const d = localStorage.getItem('ui.density'); if(d) { densitySel.value=d; applyDensity(d); }
})();
</script>
@endpush
