@php
  /** Normaliza datos y modo */
  $mode   = ($mode ?? null) === 'edit' ? 'edit' : 'create';
  $row    = $row  ?? null;

  $action = $mode === 'edit' && $row?->id
              ? route('admin.empresas.pactopia360.crm.carritos.update', $row->id)
              : route('admin.empresas.pactopia360.crm.carritos.store');

  $method = $mode === 'edit' ? 'PUT' : 'POST';

  /** Helpers de value */
  $v = function(string $k, $def=null) use($row){
      $val = old($k, data_get($row, $k, $def));
      return is_array($val) ? $val : (is_string($val) ? trim($val) : $val);
  };

  /** Map de estados seguro */
  $optsEstados = collect($estados ?? ['abierto','convertido','cancelado','nuevo'])
                    ->mapWithKeys(fn($e)=>[strtolower($e)=>ucfirst($e)])->all();

  /** Monedas comunes (mantener editable) */
  $monedas = ['MXN'=>'MXN','USD'=>'USD','EUR'=>'EUR'];
@endphp

@if($errors->any())
  <div class="alert" style="border:1px solid #fecaca;background:#fef2f2;color:#991b1b;border-radius:12px;padding:12px 14px;margin-bottom:12px">
    <strong>Revisa los campos:</strong>
    <ul style="margin:6px 0 0 18px">
      @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
    </ul>
  </div>
@endif

<form method="post" action="{{ $action }}" id="carritoForm" novalidate>
  @csrf
  @if($mode === 'edit') @method('PUT') @endif

  <div class="grid" style="display:grid;gap:14px">
    {{-- ===== Encabezado rápido ===== --}}
    <div class="card" style="border:1px dashed rgba(0,0,0,.1)">
      <div class="card-b" style="display:grid;gap:12px">
        <div style="display:grid;grid-template-columns:1.5fr .7fr .7fr .7fr;gap:10px">
          <label style="display:grid;gap:6px">
            <span>Título <b style="color:#ef4444">*</b></span>
            <input type="text" name="titulo" required maxlength="200"
                   value="{{ $v('titulo') }}"
                   placeholder="Ej. Carrito P360 · Cliente ABC"
                   class="inp">
          </label>

          <label style="display:grid;gap:6px">
            <span>Estado <b style="color:#ef4444">*</b></span>
            <select name="estado" required class="inp">
              @foreach($optsEstados as $k=>$lbl)
                <option value="{{ $k }}" @selected($v('estado','abierto') === $k)>{{ $lbl }}</option>
              @endforeach
            </select>
          </label>

          <label style="display:grid;gap:6px">
            <span>Total <b style="color:#ef4444">*</b></span>
            <input type="number" step="0.01" min="0" name="total" required
                   value="{{ is_null($v('total')) ? '' : $v('total') }}"
                   placeholder="0.00" class="inp mono" inputmode="decimal">
          </label>

          <label style="display:grid;gap:6px">
            <span>Moneda <b style="color:#ef4444">*</b></span>
            <select name="moneda" required class="inp">
              @php $mon = strtoupper($v('moneda','MXN')); @endphp
              @foreach($monedas as $k=>$lbl)
                <option value="{{ $k }}" @selected($mon===$k)>{{ $lbl }}</option>
              @endforeach
              @if(!array_key_exists($mon,$monedas))
                <option value="{{ $mon }}" selected>{{ $mon }}</option>
              @endif
            </select>
          </label>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:10px">
          <label style="display:grid;gap:6px">
            <span>Cliente</span>
            <input type="text" name="cliente" maxlength="160"
                   value="{{ $v('cliente') }}"
                   placeholder="Nombre o Razón social" class="inp">
          </label>
          <label style="display:grid;gap:6px">
            <span>Email</span>
            <input type="email" name="email" maxlength="160"
                   value="{{ $v('email') }}"
                   placeholder="cliente@mail.com" class="inp">
          </label>
          <label style="display:grid;gap:6px">
            <span>Teléfono</span>
            <input type="text" name="telefono" maxlength="60"
                   value="{{ $v('telefono') }}"
                   placeholder="+52 55 0000 0000" class="inp">
          </label>
          <label style="display:grid;gap:6px">
            <span>Origen</span>
            @php $origenes = ['manual'=>'Manual','web'=>'Web','crm'=>'CRM','api'=>'API','import'=>'Importación']; @endphp
            <select name="origen" class="inp">
              <option value="">—</option>
              @foreach($origenes as $ok=>$ol)
                <option value="{{ $ok }}" @selected($v('origen')===$ok)>{{ $ol }}</option>
              @endforeach
            </select>
          </label>
        </div>
      </div>
    </div>

    {{-- ===== Etiquetas / Notas / Empresa ===== --}}
    <div class="card">
      <div class="card-h"><strong>Detalles</strong></div>
      <div class="card-b" style="display:grid;gap:12px">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <label style="display:grid;gap:6px">
            <span>Etiquetas (coma o Enter)</span>
            {{-- El controller normaliza string o array. Aquí mandamos string y el JS añade UX de chips --}}
            <input type="text" name="etiquetas" id="tagsInput" class="inp"
                   value="{{ is_array($v('etiquetas')) ? implode(',', $v('etiquetas')) : (string)$v('etiquetas') }}"
                   placeholder="demo,p360,prioridad-alta">
            <small class="muted">Se convertirán a lista. Ej: <code>demo, p360</code></small>
          </label>

          <label style="display:grid;gap:6px">
            <span>Empresa (tenant / opcional)</span>
            <input type="text" name="empresa_slug" maxlength="50"
                   value="{{ $v('empresa_slug', session('empresa_slug')) }}"
                   placeholder="pactopia360" class="inp">
          </label>
        </div>

        <label style="display:grid;gap:6px">
          <span>Notas</span>
          <textarea name="notas" rows="4" class="inp" placeholder="Notas internas, acuerdos, observaciones…">{{ $v('notas') }}</textarea>
        </label>
      </div>
    </div>

    {{-- ===== Metadata JSON ===== --}}
    <div class="card">
      <div class="card-h" style="display:flex;align-items:center;gap:10px">
        <strong>Metadata (JSON opcional)</strong>
        <span id="metaState" class="muted" aria-live="polite"></span>
        <div style="margin-left:auto;display:flex;gap:8px">
          <button class="btn" type="button" id="beautifyBtn">Formatear</button>
          <button class="btn" type="button" id="clearMetaBtn">Limpiar</button>
        </div>
      </div>
      <div class="card-b">
        <textarea name="meta" id="metaField" class="inp mono" rows="8"
                  placeholder='{"canal":"web","utm":{"source":"ads"}}'>@php
                    $m = $v('meta');
                    echo is_array($m) ? json_encode($m, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) : trim((string)$m);
                  @endphp</textarea>
        <small class="muted">Si dejas contenido no-JSON válido, el servidor lo ignorará (el controller lo trata como <code>null</code>).</small>
      </div>
    </div>

    {{-- ===== Acciones ===== --}}
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      <button class="btn btn-primary" type="submit">
        {{ $mode==='edit' ? 'Guardar cambios' : 'Crear carrito' }}
      </button>
      <a class="btn" href="{{ route('admin.empresas.pactopia360.crm.carritos.index') }}">Volver al listado</a>
      @if($mode==='edit' && $row?->id)
        <span class="muted">Creado: {{ optional($row->created_at)->format('Y-m-d H:i') }} ·
          Actualizado: {{ optional($row->updated_at)->format('Y-m-d H:i') }}</span>
      @endif
    </div>
  </div>
</form>

@push('styles')
<style>
  .inp{
    display:inline-flex; align-items:center; min-height:38px; width:100%;
    padding:10px 12px; border-radius:12px; border:1px solid rgba(0,0,0,.12);
    background:var(--card-bg,#fff); color:var(--text,#0f172a); outline:none;
  }
  .inp:focus{ border-color:#6366f1; box-shadow:0 0 0 3px color-mix(in oklab, #6366f1 25%, transparent) }
  .mono{ font-variant-numeric: tabular-nums; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace }
  .muted{ color:#6b7280 }
  [data-theme="dark"] .inp{ border-color:rgba(255,255,255,.12) }
</style>
@endpush

@push('scripts')
<script>
(function () {
  'use strict';
  const $ = (s,ctx=document)=>ctx.querySelector(s);

  /* ===== Meta JSON live-validate ===== */
  const metaField = $('#metaField');
  const metaState = $('#metaState');
  const beautifyBtn = $('#beautifyBtn');
  const clearMetaBtn= $('#clearMetaBtn');

  function validateMeta() {
    const v = metaField.value.trim();
    if (!v) { metaState.textContent = 'Vacío (opcional)'; metaState.style.color=''; return true; }
    try {
      JSON.parse(v);
      metaState.textContent = 'JSON válido';
      metaState.style.color = '#16a34a';
      return true;
    } catch {
      metaState.textContent = 'JSON inválido';
      metaState.style.color = '#dc2626';
      return false;
    }
  }
  metaField?.addEventListener('input', validateMeta);
  beautifyBtn?.addEventListener('click', () => {
    try { metaField.value = JSON.stringify(JSON.parse(metaField.value || '{}'), null, 2); validateMeta(); } catch {}
  });
  clearMetaBtn?.addEventListener('click', () => { metaField.value=''; validateMeta(); });
  validateMeta();

  /* ===== Etiquetas UX mínima (coma o Enter) ===== */
  const tagsInput = $('#tagsInput');
  if (tagsInput) {
    tagsInput.addEventListener('keydown', (e)=>{
      if (e.key === 'Enter') { e.preventDefault(); }
    });
  }

  /* ===== Prevención básica de envío con JSON inválido ===== */
  const form = $('#carritoForm');
  form?.addEventListener('submit', (e)=>{
    if (!validateMeta()) {
      e.preventDefault();
      alert('El campo Metadata contiene JSON inválido. Corrígelo o déjalo vacío.');
      metaField.focus();
    }
  });
})();
</script>
@endpush
