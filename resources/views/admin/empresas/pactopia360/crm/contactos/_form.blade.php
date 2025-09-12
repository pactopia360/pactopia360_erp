{{-- resources/views/admin/empresas/pactopia360/crm/contactos/_form.blade.php --}}
@php
  /** @var \App\Models\CrmContacto $contacto */
  $c = $contacto ?? new \App\Models\CrmContacto(['empresa_slug' => 'pactopia360', 'activo' => 1]);
@endphp

@if ($errors->any())
  <div class="flash" style="background:#fef2f2;border:1px solid #fecaca;padding:10px;border-radius:8px;margin-bottom:10px">
    @foreach ($errors->all() as $e)
      <div>• {{ $e }}</div>
    @endforeach
  </div>
@endif

<div class="grid cols-2" style="gap:12px">
  <div>
    <label for="nombre">Nombre *</label>
    <input id="nombre" type="text" name="nombre"
           value="{{ old('nombre', $c->nombre ?? '') }}" required>
    @error('nombre')<div class="text-red-600" style="font-size:12px">{{ $message }}</div>@enderror
  </div>

  <div>
    <label for="email">Email</label>
    <input id="email" type="email" name="email"
           value="{{ old('email', $c->email ?? '') }}">
    @error('email')<div class="text-red-600" style="font-size:12px">{{ $message }}</div>@enderror
  </div>

  <div>
    <label for="telefono">Teléfono</label>
    <input id="telefono" type="text" name="telefono"
           value="{{ old('telefono', $c->telefono ?? '') }}">
    @error('telefono')<div class="text-red-600" style="font-size:12px">{{ $message }}</div>@enderror
  </div>

  <div>
    <label for="puesto">Puesto</label>
    <input id="puesto" type="text" name="puesto"
           value="{{ old('puesto', $c->puesto ?? '') }}">
    @error('puesto')<div class="text-red-600" style="font-size:12px">{{ $message }}</div>@enderror
  </div>

  <div style="grid-column:1/-1">
    <label for="notas">Notas</label>
    <textarea id="notas" name="notas" rows="4">{{ old('notas', $c->notas ?? '') }}</textarea>
    @error('notas')<div class="text-red-600" style="font-size:12px">{{ $message }}</div>@enderror
  </div>

  <div style="grid-column:1/-1;display:flex;align-items:center;gap:8px;margin-top:6px">
    {{-- hidden para enviar 0 cuando no está marcado --}}
    <input type="hidden" name="activo" value="0">
    <input type="checkbox" id="activo" name="activo" value="1" {{ old('activo', (int)($c->activo ?? 1)) ? 'checked' : '' }}>
    <label for="activo" style="margin:0">Activo</label>
    @error('activo')<div class="text-red-600" style="font-size:12px;margin-left:8px">{{ $message }}</div>@enderror
  </div>
</div>
