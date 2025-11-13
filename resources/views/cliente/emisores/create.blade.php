{{-- resources/views/cliente/emisores/create.blade.php (v2 visual Pactopia360) --}}
@extends('layouts.client')
@section('title','Nuevo Emisor · Pactopia360')

@push('styles')
<style>
  body{font-family:'Poppins',system-ui,sans-serif;}

  h1{
    margin:0 0 16px;
    font-weight:900;
    font-size:22px;
    color:#E11D48;
  }

  .grid{display:grid;gap:18px;}
  @media(min-width:1100px){.grid{grid-template-columns:1fr 1fr;}}

  .card{
    background:linear-gradient(180deg,rgba(255,255,255,.96),rgba(255,255,255,.9));
    border:1px solid #f3d5dc;
    border-radius:18px;
    padding:20px 22px;
    box-shadow:0 8px 28px rgba(225,29,72,.08);
    position:relative;
  }
  .card::before{
    content:"";position:absolute;inset:-1px;border-radius:19px;padding:1px;
    background:linear-gradient(145deg,#E11D48,#BE123C);
    -webkit-mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);
    -webkit-mask-composite:xor;mask-composite:exclude;
    opacity:.25;pointer-events:none;
  }

  .card h3{
    margin:0 0 10px;
    font-weight:800;
    font-size:13px;
    text-transform:uppercase;
    letter-spacing:.4px;
    color:#E11D48;
  }

  .row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
  .field{display:grid;gap:6px;}
  .lbl{font-size:12px;color:#6b7280;font-weight:800;}
  .input{
    border:1px solid #f3d5dc;
    border-radius:12px;
    padding:11px 13px;
    font-size:14px;
    font-weight:700;
    color:#0f172a;
    background:#fff;
    outline:none;
    transition:.15s border-color,.15s box-shadow;
  }
  .input:focus{border-color:#E11D48;box-shadow:0 0 0 3px rgba(225,29,72,.25);}
  textarea.input{resize:vertical;min-height:70px;}
  .hint{font-size:12px;color:#9ca3af;}

  .alert{
    border:1px solid #f3d5dc;
    border-radius:12px;
    padding:10px 12px;
    background:#fff5f7;
    color:#BE123C;
    font-weight:700;
  }

  .btn{
    display:inline-flex;align-items:center;justify-content:center;gap:8px;
    padding:11px 16px;
    border-radius:12px;
    font-weight:800;
    font-size:14px;
    cursor:pointer;
    text-decoration:none;
    transition:filter .15s ease;
  }
  .btn.primary{
    background:linear-gradient(90deg,#E11D48,#BE123C);
    color:#fff;
    border:0;
    box-shadow:0 10px 22px rgba(225,29,72,.25);
  }
  .btn.primary:hover{filter:brightness(.96);}
  .btn.secondary{
    background:#fff;border:1px solid #f3d5dc;color:#E11D48;
  }
  .btn.secondary:hover{background:#fff0f3;}
</style>
@endpush

@section('content')
<h1>Nuevo Emisor</h1>

@if(session('warn'))
  <div class="alert">{{ session('warn') }}</div>
@endif
@if($errors->any())
  <div class="alert"><strong>{{ $errors->first() }}</strong></div>
@endif

<form method="POST" action="{{ route('cliente.emisores.store') }}" enctype="multipart/form-data" class="grid">
  @csrf

  {{-- ====================== DATOS FISCALES ====================== --}}
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

  {{-- ====================== SERIES ====================== --}}
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

  {{-- ====================== CERTIFICADOS ====================== --}}
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

    <label style="display:flex;gap:8px;align-items:center;margin-top:8px;">
      <input type="checkbox" name="sync_pac" value="1">
      <span>Sincronizar con el PAC al guardar</span>
    </label>
    <span class="hint">Si no marcas, sólo se guarda localmente.</span>
  </div>

  {{-- ====================== BOTONES ====================== --}}
  <div style="grid-column:1/-1;display:flex;gap:10px;flex-wrap:wrap;">
    <a href="{{ route('cliente.emisores.index') }}" class="btn secondary">Cancelar</a>
    <button type="submit" class="btn primary">Guardar Emisor</button>
  </div>
</form>
@endsection
