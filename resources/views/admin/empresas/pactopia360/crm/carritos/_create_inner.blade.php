{{-- resources/views/admin/empresas/pactopia360/crm/carritos/_create_inner.blade.php --}}
<h2 style="margin:0 0 12px">Nuevo carrito</h2>

{{-- Errores de validación --}}
@if ($errors->any())
  <div style="margin:8px 0 14px;padding:10px 12px;border:1px solid #fca5a5;background:#fee2e2;border-radius:10px;color:#7f1d1d">
    <strong>Corrige lo siguiente:</strong>
    <ul style="margin:6px 0 0 18px">
      @foreach ($errors->all() as $e)
        <li>{{ $e }}</li>
      @endforeach
    </ul>
  </div>
@endif

<form method="POST" action="{{ route('admin.empresas.pactopia360.crm.carritos.store') }}" novalidate>
  @csrf

  {{-- Empresa (oculto) --}}
  <input type="hidden" name="empresa_slug" value="pactopia360">

  <div style="display:grid;grid-template-columns:1fr;gap:12px;max-width:740px">
    <div>
      <label for="titulo" style="display:block;font-weight:600;margin-bottom:6px">Título</label>
      <input id="titulo" name="titulo" type="text"
             value="{{ old('titulo') }}"
             required maxlength="200"
             style="width:100%;padding:10px 12px;border:1px solid #cbd5e1;border-radius:10px">
      <small style="color:#64748b">Ej: “Carrito sitio web — febrero”.</small>
    </div>

    <div style="display:grid;grid-template-columns:160px 160px 1fr;gap:12px">
      <div>
        <label for="estado" style="display:block;font-weight:600;margin-bottom:6px">Estado</label>
        <select id="estado" name="estado" required
                style="width:100%;padding:10px 12px;border:1px solid #cbd5e1;border-radius:10px">
          @php($estados = ['nuevo'=>'Nuevo','abierto'=>'Abierto','convertido'=>'Convertido','cancelado'=>'Cancelado'])
          @foreach ($estados as $val => $label)
            <option value="{{ $val }}" @selected(old('estado','nuevo')===$val)>{{ $label }}</option>
          @endforeach
        </select>
      </div>

      <div>
        <label for="moneda" style="display:block;font-weight:600;margin-bottom:6px">Moneda</label>
        <select id="moneda" name="moneda" required
                style="width:100%;padding:10px 12px;border:1px solid #cbd5e1;border-radius:10px">
          @foreach (['MXN','USD'] as $m)
            <option value="{{ $m }}" @selected(old('moneda','MXN')===$m)>{{ $m }}</option>
          @endforeach
        </select>
      </div>

      <div>
        <label for="total" style="display:block;font-weight:600;margin-bottom:6px">Total</label>
        <input id="total" name="total" type="number" step="0.01" min="0"
               value="{{ old('total', 0) }}"
               style="width:100%;padding:10px 12px;border:1px solid #cbd5e1;border-radius:10px">
      </div>
    </div>

    <div>
      <label for="notas" style="display:block;font-weight:600;margin-bottom:6px">Notas</label>
      <textarea id="notas" name="notas" rows="5"
                style="width:100%;padding:10px 12px;border:1px solid #cbd5e1;border-radius:10px">{{ old('notas') }}</textarea>
      <small style="color:#64748b">Información adicional del carrito.</small>
    </div>
  </div>

  <div style="margin-top:14px;display:flex;gap:10px">
    <button type="submit"
            style="appearance:none;background:#4f46e5;color:#fff;border:0;padding:10px 14px;border-radius:10px;font-weight:700;cursor:pointer">
      Guardar
    </button>
    <a href="{{ route('admin.empresas.pactopia360.crm.carritos.index') }}"
       style="display:inline-flex;align-items:center;padding:10px 14px;border:1px solid #cbd5e1;border-radius:10px;text-decoration:none;color:#0f172a">
      Cancelar
    </a>
  </div>
</form>
