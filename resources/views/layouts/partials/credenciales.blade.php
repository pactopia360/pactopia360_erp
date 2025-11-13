{{-- resources/views/layouts/partials/credenciales.blade.php (v3) --}}
@php
  /** @var \Illuminate\Support\Carbon|null $updatedAt */
  $hasCred   = isset($cred) && $cred;
  $rfcVal    = old('rfc', $hasCred ? ($cred->rfc ?? '') : '');
  $updatedAt = $hasCred ? ($cred->updated_at ?? null) : null;
  $cuentaRef = auth('web')->user()?->cuenta_id ?? auth('web')->id();
@endphp

<div class="sat-cred card">
  <div class="card-header d-flex align-items-center justify-content-between">
    <span class="ttl">1) Cargar / Actualizar FIEL (CSD)</span>

    <div class="state">
      @if($hasCred)
        <span class="badge bg-success">Configurado</span>
        <span class="small-muted ms-2">RFC activo: <span class="mono">{{ $cred->rfc }}</span></span>
      @else
        <span class="badge bg-warning text-dark">Pendiente</span>
      @endif
    </div>
  </div>

  <div class="card-body">
    @if($hasCred)
      <div class="row g-3 mb-3 small-muted">
        <div class="col-md-4">
          <strong>.cer:</strong>
          <span class="truncate">{{ $cred->cer_path }}</span>
        </div>
        <div class="col-md-4">
          <strong>.key:</strong>
          <span class="truncate">{{ $cred->key_path }}</span>
        </div>
        <div class="col-md-2">
          <strong>Actualizado:</strong>
          {{ optional($updatedAt)->format('Y-m-d H:i') ?? '—' }}
        </div>
        <div class="col-md-2">
          <strong>Cuenta:</strong> <span class="mono">{{ $cuentaRef }}</span>
        </div>
      </div>
    @endif

    <form method="post"
          enctype="multipart/form-data"
          action="{{ route('cliente.sat.credenciales.store') }}"
          autocomplete="off"
          class="row g-3"
          id="satCredForm"
          onsubmit="return p360SatCredSubmit(event)">
      @csrf

      <div class="col-md-3">
        <label for="rfc" class="form-label">RFC</label>
        <input type="text"
               name="rfc"
               id="rfc"
               class="form-control mono"
               maxlength="13"
               inputmode="latin"
               value="{{ $rfcVal }}"
               required>
        <div class="form-text">Se valida que coincida con el del certificado.</div>
      </div>

      <div class="col-md-4">
        <label class="form-label" for="cer">Archivo .cer</label>
        <input type="file"
               name="cer"
               id="cer"
               class="form-control"
               accept=".cer"
               {{ $hasCred ? '' : 'required' }}>
        <div class="form-text">Certificado digital del CSD (extensión .cer).</div>
      </div>

      <div class="col-md-4">
        <label class="form-label" for="key">Archivo .key</label>
        <input type="file"
               name="key"
               id="key"
               class="form-control"
               accept=".key"
               {{ $hasCred ? '' : 'required' }}>
        <div class="form-text">Llave privada del CSD (extensión .key).</div>
      </div>

      <div class="col-md-4">
        <label class="form-label" for="key_password">Contraseña de la llave</label>
        <input type="password"
               name="key_password"
               id="key_password"
               class="form-control"
               minlength="1"
               required>
      </div>

      <div class="col-12 d-flex align-items-center gap-2">
        <button class="btn btn-primary" id="btnSaveCred" type="submit">
          <span class="btn-txt">Guardar y validar</span>
        </button>
        <span class="small-muted">Se verificará que el RFC contenido en el CSD coincida con el ingresado.</span>
      </div>
    </form>
  </div>
</div>

{{-- ===== Scoped UX styles (funcionan con o sin Bootstrap) ===== --}}
<style>
  .sat-cred .ttl{ font-weight:800 }
  .sat-cred .mono{ font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace }
  .sat-cred .small-muted{ color: var(--muted, #6b7280); font-size: 12px }
  .sat-cred .truncate{ display:inline-block; max-width: 100%; overflow:hidden; text-overflow:ellipsis; vertical-align:bottom }
  /* Mejoras visuales si no hay Bootstrap */
  .sat-cred.card{ background:var(--card,#fff); border:1px solid var(--bd,#e5e7eb); border-radius:14px }
  .sat-cred .card-header{ padding:12px 14px; border-bottom:1px solid var(--bd,#e5e7eb) }
  .sat-cred .card-body{ padding:14px }
  .sat-cred .form-label{ font-weight:800 }
  .sat-cred .form-text{ font-size:12px; color:var(--muted,#6b7280) }
</style>

<script>
  // UX rápido: RFC → mayúsculas/trim, validación simple de extensión, botón con estado.
  function p360SatCredSubmit(ev){
    const form = ev.target;
    const btn  = form.querySelector('#btnSaveCred');
    const rfc  = form.querySelector('#rfc');
    const cer  = form.querySelector('#cer');
    const key  = form.querySelector('#key');

    // Normaliza RFC
    if (rfc){ rfc.value = (rfc.value || '').trim().toUpperCase(); }

    // Validación ligera de extensiones cuando son requeridas
    const mustCer = !{{ $hasCred ? 'true' : 'false' }};
    const mustKey = !{{ $hasCred ? 'true' : 'false' }};
    if (mustCer && cer?.files?.length){
      if(!cer.files[0].name.toLowerCase().endsWith('.cer')){
        alert('El archivo .cer no es válido.'); ev.preventDefault(); return false;
      }
    }
    if (mustKey && key?.files?.length){
      if(!key.files[0].name.toLowerCase().endsWith('.key')){
        alert('El archivo .key no es válido.'); ev.preventDefault(); return false;
      }
    }

    // Estado del botón
    if (btn){
      btn.disabled = true;
      const t = btn.querySelector('.btn-txt');
      if (t) t.textContent = 'Validando…';
    }

    // Continúa envío normal
    return true;
  }
</script>
