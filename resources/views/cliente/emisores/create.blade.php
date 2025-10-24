@extends('layouts.client')
@section('title','Nuevo Emisor')

@push('styles')
<style>
  .grid{display:grid;gap:12px}
  @media(min-width:1100px){.grid{grid-template-columns:1fr 1fr}}
  .card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:14px}
  .card h3{margin:0 0 8px;font:800 12px/1 ui-sans-serif;color:var(--muted);text-transform:uppercase}
  .field{display:grid;gap:6px}
  .lbl{font-size:12px;color:var(--muted);font-weight:800}
  .input{border:1px solid var(--border);border-radius:12px;padding:10px 12px;font-weight:800;background:color-mix(in oklab,var(--card)96%,transparent)}
  .row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
  .btn{display:inline-flex;align-items:center;gap:8px;padding:10px 12px;border:1px solid var(--border);border-radius:12px;font-weight:900;text-decoration:none}
  .btn.primary{background:linear-gradient(180deg,var(--accent),color-mix(in oklab,var(--accent)85%,black));color:#fff;border:0}
  .hint{font-size:12px;color:var(--muted)}
</style>
@endpush

@section('content')
<h1 style="margin:0 0 12px;font-weight:900">Nuevo Emisor</h1>

@if(session('warn')) <div class="card">{{ session('warn') }}</div> @endif
@if($errors->any()) <div class="card"><strong>{{ $errors->first() }}</strong></div> @endif

<form method="POST" action="{{ route('cliente.emisores.store') }}" enctype="multipart/form-data" class="grid">
  @csrf

  <div class="card">
    <h3>Datos fiscales</h3>
    <div class="row">
      <div class="field">
        <span class="lbl">RFC *</span>
        <input class="input" name="rfc" value="{{ old('rfc') }}" required maxlength="13">
      </div>
      <div class="field">
        <span class="lbl">Régimen (SAT) *</span>
        <input class="input" name="regimen_fiscal" value="{{ old('regimen_fiscal') }}" placeholder="Ej. 601" required>
      </div>
    </div>
    <div class="field">
      <span class="lbl">Razón social *</span>
      <input class="input" name="razon_social" value="{{ old('razon_social') }}" required>
    </div>
    <div class="row">
      <div class="field">
        <span class="lbl">Nombre comercial</span>
        <input class="input" name="nombre_comercial" value="{{ old('nombre_comercial') }}">
      </div>
      <div class="field">
        <span class="lbl">Email *</span>
        <input class="input" type="email" name="email" value="{{ old('email') }}" required>
      </div>
    </div>
    <div class="row">
      <div class="field">
        <span class="lbl">Grupo</span>
        <input class="input" name="grupo" value="{{ old('grupo') }}" placeholder="restaurante, conductor, etc">
      </div>
      <div class="field">
        <span class="lbl">CP *</span>
        <input class="input" name="dir_cp" value="{{ old('dir_cp') }}" required>
      </div>
    </div>
    <div class="field">
      <span class="lbl">Dirección</span>
      <input class="input" name="dir_direccion" value="{{ old('dir_direccion') }}">
    </div>
    <div class="row">
      <div class="field">
        <span class="lbl">Ciudad</span>
        <input class="input" name="dir_ciudad" value="{{ old('dir_ciudad') }}">
      </div>
      <div class="field">
        <span class="lbl">Estado</span>
        <input class="input" name="dir_estado" value="{{ old('dir_estado') }}">
      </div>
    </div>
  </div>

  <div class="card">
    <h3>Series</h3>
    <div class="row">
      <div class="field">
        <span class="lbl">Serie ingreso</span>
        <input class="input" name="serie_ingreso" value="{{ old('serie_ingreso','IN') }}">
      </div>
      <div class="field">
        <span class="lbl">Folio ingreso</span>
        <input class="input" name="folio_ingreso" type="number" value="{{ old('folio_ingreso',1) }}" min="1">
      </div>
    </div>
    <div class="row">
      <div class="field">
        <span class="lbl">Serie egreso</span>
        <input class="input" name="serie_egreso" value="{{ old('serie_egreso','EG') }}">
      </div>
      <div class="field">
        <span class="lbl">Folio egreso</span>
        <input class="input" name="folio_egreso" type="number" value="{{ old('folio_egreso',1) }}" min="1">
      </div>
    </div>
    <div class="field">
      <span class="lbl">Series (JSON opcional)</span>
      <textarea class="input" name="series_json" rows="4" placeholder='[{"tipo":"ingreso","serie":"IN","folio":1}]'>{{ old('series_json') }}</textarea>
      <span class="hint">Si pegas JSON, ignoramos los campos de arriba.</span>
    </div>
  </div>

  <div class="card">
    <h3>Certificados (PRO)</h3>
    <div class="row">
      <div class="field">
        <span class="lbl">CSD .cer</span>
        <input class="input" type="file" name="csd_cer" accept=".cer">
      </div>
      <div class="field">
        <span class="lbl">CSD .key</span>
        <input class="input" type="file" name="csd_key" accept=".key">
      </div>
    </div>
    <div class="field">
      <span class="lbl">Contraseña CSD</span>
      <input class="input" name="csd_password" type="password">
    </div>

    <div class="row">
      <div class="field">
        <span class="lbl">FIEL .cer</span>
        <input class="input" type="file" name="fiel_cer" accept=".cer">
      </div>
      <div class="field">
        <span class="lbl">FIEL .key</span>
        <input class="input" type="file" name="fiel_key" accept=".key">
      </div>
    </div>
    <div class="field">
      <span class="lbl">Contraseña FIEL</span>
      <input class="input" name="fiel_password" type="password">
    </div>

    <label style="display:flex;gap:8px;align-items:center;margin-top:8px">
      <input type="checkbox" name="sync_pac" value="1"> Sincronizar con el PAC al guardar
    </label>
    <span class="hint">Si no marcas, sólo se guarda localmente.</span>
  </div>

  <div style="grid-column:1/-1;display:flex;gap:8px">
    <a href="{{ route('cliente.emisores.index') }}" class="btn">Cancelar</a>
    <button class="btn primary">Guardar</button>
  </div>
</form>
@endsection
