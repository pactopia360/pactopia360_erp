@php
  /** Normaliza $estados: acepta ['abierto','convertido'] o ['abierto'=>'Abierto'] */
  $estadoOptions = [];
  foreach ($estados ?? [] as $k => $v) {
      if (is_int($k)) { $estadoOptions[strtolower($v)] = ucfirst($v); }
      else           { $estadoOptions[strtolower($k)] = $v; }
  }

  $isEdit   = isset($row) && $row && $row->id;
  $action   = $isEdit
      ? route('admin.empresas.pactopia360.crm.carritos.update', $row->id)
      : route('admin.empresas.pactopia360.crm.carritos.store');

  $method   = $isEdit ? 'PUT' : 'POST';

  // valores
  $v = function($name, $def='') use ($row) {
      return old($name, $row->{$name} ?? $def);
  };
  $etiquetas = old('etiquetas', (array)($row->etiquetas ?? []));
  if (!$etiquetas) $etiquetas = [''];
@endphp

<form method="post" action="{{ $action }}" novalidate>
  @csrf
  @if($isEdit) @method('PUT') @endif

  <div class="grid" style="display:grid;grid-template-columns:repeat(12,1fr);gap:12px">
    {{-- Cliente --}}
    <div style="grid-column:span 6">
      <label class="lbl">Cliente <span class="req">*</span></label>
      <input name="cliente" type="text" class="in" required value="{{ $v('cliente') }}">
      @error('cliente') <div class="err">{{ $message }}</div> @enderror
    </div>

    {{-- Email --}}
    <div style="grid-column:span 3">
      <label class="lbl">Email</label>
      <input name="email" type="email" class="in" value="{{ $v('email') }}">
      @error('email') <div class="err">{{ $message }}</div> @enderror
    </div>

    {{-- Teléfono --}}
    <div style="grid-column:span 3">
      <label class="lbl">Teléfono</label>
      <input name="telefono" type="text" class="in" value="{{ $v('telefono') }}">
      @error('telefono') <div class="err">{{ $message }}</div> @enderror
    </div>

    {{-- Moneda --}}
    <div style="grid-column:span 2">
      <label class="lbl">Moneda <span class="req">*</span></label>
      <input name="moneda" type="text" class="in" maxlength="3" required value="{{ $v('moneda','MXN') }}">
      @error('moneda') <div class="err">{{ $message }}</div> @enderror
    </div>

    {{-- Total --}}
    <div style="grid-column:span 3">
      <label class="lbl">Total <span class="req">*</span></label>
      <input name="total" type="number" step="0.01" min="0" class="in" required value="{{ $v('total',0) }}">
      @error('total') <div class="err">{{ $message }}</div> @enderror
    </div>

    {{-- Estado --}}
    <div style="grid-column:span 3">
      <label class="lbl">Estado <span class="req">*</span></label>
      <select name="estado" class="in" required>
        @foreach($estadoOptions as $key => $label)
          <option value="{{ $key }}" @selected($v('estado')===$key)>{{ $label }}</option>
        @endforeach
      </select>
      @error('estado') <div class="err">{{ $message }}</div> @enderror
    </div>

    {{-- Origen --}}
    <div style="grid-column:span 4">
      <label class="lbl">Origen</label>
      <input name="origen" type="text" class="in" value="{{ $v('origen') }}">
      @error('origen') <div class="err">{{ $message }}</div> @enderror
    </div>

    {{-- Etiquetas (array) --}}
    <div style="grid-column:span 12">
      <label class="lbl">Etiquetas</label>
      <div id="etqs" style="display:flex;flex-wrap:wrap;gap:6px">
        @foreach($etiquetas as $tag)
          <input name="etiquetas[]" type="text" class="in" style="min-width:160px" value="{{ $tag }}">
        @endforeach
        <button type="button" class="btn add-etq">+ agregar</button>
      </div>
      @error('etiquetas') <div class="err">{{ $message }}</div> @enderror
    </div>

    {{-- Notas --}}
    <div style="grid-column:span 12">
      <label class="lbl">Notas</label>
      <textarea name="notas" rows="4" class="in" style="resize:vertical">{{ $v('notas') }}</textarea>
      @error('notas') <div class="err">{{ $message }}</div> @enderror
    </div>
  </div>

  <div style="display:flex;gap:10px;margin-top:14px">
    <button type="submit" class="btn primary">{{ $isEdit ? 'Actualizar' : 'Crear' }}</button>
    <a href="{{ route('admin.empresas.pactopia360.crm.carritos.index') }}" class="btn">Cancelar</a>
  </div>
</form>

<style>
  .lbl{display:block;font:600 12px/1 system-ui;margin-bottom:6px;color:var(--muted,#6b7280)}
  .in{width:100%;height:38px;border-radius:10px;border:1px solid rgba(0,0,0,.12);background:transparent;padding:0 10px}
  textarea.in{height:auto;padding:10px}
  .btn{height:38px;padding:0 14px;border-radius:10px;border:1px solid rgba(0,0,0,.12);background:transparent;cursor:pointer}
  .btn.primary{background:var(--accent,#6366f1);color:#fff;border-color:transparent}
  .err{color:#b91c1c;font-size:12px;margin-top:6px}
  .req{color:#ef4444}
</style>

<script>
  (function(){
    const root=document.getElementById('etqs');
    if(!root) return;
    const addBtn=root.querySelector('.add-etq');
    addBtn?.addEventListener('click',()=>{
      const i=document.createElement('input');
      i.name='etiquetas[]'; i.type='text'; i.className='in';
      i.style.minWidth='160px'; i.placeholder='Etiqueta';
      root.insertBefore(i, addBtn);
      i.focus();
    });
  })();
</script>
