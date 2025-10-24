{{-- resources/views/cliente/facturacion/nuevo.blade.php (v1.0) --}}
@extends('layouts.client')
@section('title','Nuevo CFDI · Pactopia360')

@push('styles')
<style>
  .page-header{ display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:14px; flex-wrap:wrap }
  .page-title{ margin:0; font-size:clamp(18px,2.4vw,22px); font-weight:900 }
  .badge{ display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; border:1px solid var(--border); font-weight:900; font-size:12px }
  .tools{ display:flex; align-items:center; gap:8px; flex-wrap:wrap }
  .btn{ display:inline-flex; align-items:center; gap:8px; padding:10px 12px; border-radius:12px; border:1px solid var(--border); font-weight:900; text-decoration:none; cursor:pointer }
  .btn.primary{ background: linear-gradient(180deg, var(--accent), color-mix(in oklab, var(--accent) 85%, black)); color:#fff; border:0 }
  .btn.ghost{ background: transparent }
  .btn.disabled{ opacity:.55; pointer-events:none }
  .card{ background:var(--card); border:1px solid var(--border); border-radius:14px; padding:14px; box-shadow:var(--shadow) }
  .card h3{ margin:0 0 10px; font-size:12px; color:var(--muted); letter-spacing:.2px; text-transform:uppercase; font-weight:900 }
  .grid-2{ display:grid; gap:12px }
  @media (min-width: 1080px){ .grid-2{ grid-template-columns: 1fr 1fr } }
  .field{ display:grid; gap:6px; margin-bottom:10px }
  .lbl{ font-weight:800; color:var(--muted); font-size:12px }
  .input, .select, .number, textarea{
    width:100%; border:1px solid var(--border); border-radius:12px; background: color-mix(in oklab, var(--card) 96%, transparent);
    color:var(--text); padding:10px 12px; font-weight:700; resize:vertical
  }
  table.items{ width:100%; border-collapse:separate; border-spacing:0; }
  table.items th, table.items td{ padding:10px; border-bottom:1px solid var(--border); text-align:left; font-size:14px }
  .total{ font-size:clamp(18px,2.8vw,22px); font-weight:900 }
  .hint{ color:var(--muted); font-size:12px }
</style>
@endpush

@section('content')
<div class="page-header">
  <h1 class="page-title">Nuevo CFDI</h1>
  <div class="tools">
    <a href="{{ route('cliente.facturacion.index', request()->only(['q','status','month'])) }}" class="btn ghost">← Volver</a>
  </div>
</div>

{{-- Mensajes --}}
@if (session('ok'))   <div class="card" style="background:color-mix(in oklab, var(--ok) 22%, transparent); border-color:color-mix(in oklab, var(--ok) 44%, transparent)"><strong>{{ session('ok') }}</strong></div> @endif
@if ($errors->any())
  <div class="card" style="background:color-mix(in oklab, var(--err) 18%, transparent); border-color:color-mix(in oklab, var(--err) 38%, transparent)">
    <strong>{{ $errors->first() }}</strong>
  </div>
@endif

@php
  // Asegurar colecciones
  $emisores   = collect($emisores ?? []);
  $receptores = collect($receptores ?? []);
  $productos  = collect($productos ?? []);
@endphp

<form method="POST" action="{{ route('cliente.facturacion.store') }}" id="newForm">
  @csrf

  <div class="card">
    <h3>Emisor</h3>
    <div class="grid-2">
      <div class="field">
        <span class="lbl">Empresa / Emisor</span>
        <select name="cliente_id" class="select" required>
          @if($emisores->isEmpty())
            <option value="">— No hay emisores —</option>
          @else
            @foreach($emisores as $e)
              <option value="{{ $e->id }}" @selected(old('cliente_id')==$e->id)>
                {{ $e->nombre_comercial ?? $e->razon_social ?? ('#'.$e->id) }} — {{ $e->rfc }}
              </option>
            @endforeach
          @endif
        </select>
        @if($emisores->isEmpty())
          <div class="hint">Agrega emisores en el módulo de Clientes.</div>
        @endif
      </div>
      <div class="field">
        <span class="lbl">Fecha</span>
        <input type="datetime-local" name="fecha" class="input" value="{{ old('fecha', now()->format('Y-m-d\TH:i')) }}" />
      </div>
    </div>

    <div class="grid-2">
      <div class="field">
        <span class="lbl">Serie</span>
        <input type="text" name="serie" class="input" maxlength="10" value="{{ old('serie') }}"/>
      </div>
      <div class="field">
        <span class="lbl">Folio</span>
        <input type="text" name="folio" class="input" maxlength="20" value="{{ old('folio') }}"/>
      </div>
    </div>

    <div class="grid-2">
      <div class="field">
        <span class="lbl">Moneda</span>
        <input type="text" name="moneda" class="input" maxlength="10" value="{{ old('moneda','MXN') }}"/>
      </div>
      <div class="field">
        <span class="lbl">UUID (temporal)</span>
        <div class="input" style="display:flex; align-items:center">Se generará automáticamente</div>
      </div>
    </div>
  </div>

  <div class="card" style="margin-top:12px">
    <h3>Receptor</h3>
    <div class="grid-2">
      <div class="field">
        <span class="lbl">Receptor</span>
        <select name="receptor_id" class="select" required>
          @if($receptores->isEmpty())
            <option value="">— No hay receptores —</option>
          @else
            @foreach($receptores as $r)
              <option value="{{ $r->id }}" @selected(old('receptor_id')==$r->id)>
                {{ $r->razon_social ?? $r->nombre_comercial ?? ('#'.$r->id) }} — {{ $r->rfc }}
              </option>
            @endforeach
          @endif
        </select>
        @if($receptores->isEmpty())
          <div class="hint">Agrega receptores en el módulo de Clientes/Receptores.</div>
        @endif
      </div>
      <div class="field">
        <span class="lbl">Uso CFDI</span>
        <input type="text" class="input" value="G03" readonly />
      </div>
    </div>
  </div>

  <div class="card" style="margin-top:12px">
    <h3>Conceptos / Productos</h3>

    <table class="items" id="itemsTable">
      <thead>
        <tr>
          <th style="width:34%">Descripción</th>
          <th>Producto</th>
          <th>Cant.</th>
          <th>Precio</th>
          <th>IVA</th>
          <th>Subtotal</th>
          <th>Total</th>
          <th></th>
        </tr>
      </thead>
      <tbody id="itemsBody">
        {{-- Fila inicial --}}
      </tbody>
    </table>

    <div style="display:flex; gap:8px; margin-top:10px; align-items:center; flex-wrap:wrap">
      <button type="button" class="btn" onclick="addItem()">+ Agregar concepto</button>
      <div class="btn ghost right" id="calcPreview" aria-live="polite">Subtotal: $0.00 · IVA: $0.00 · Total: $0.00</div>
    </div>
  </div>

  <div class="card" style="margin-top:12px">
    <h3>Pago</h3>
    <div class="grid-2">
      <div class="field">
        <span class="lbl">Forma de pago</span>
        <input type="text" name="forma_pago" class="input" value="{{ old('forma_pago') }}"/>
      </div>
      <div class="field">
        <span class="lbl">Método de pago</span>
        <input type="text" name="metodo_pago" class="input" value="{{ old('metodo_pago') }}"/>
      </div>
    </div>

    <div style="display:flex; align-items:center; gap:12px; margin-top:8px">
      <div class="total" id="totalLabel">$0.00</div>
      <div class="right"></div>
      <button type="submit" class="btn primary">Crear borrador</button>
    </div>
  </div>
</form>
@endsection

@push('scripts')
<script>
  // Productos para autollenado (id, label, descripcion, precio_unitario, iva_tasa)
  const PRODUCTOS = {!! json_encode(
    ($productos ?? collect())->map(function($p){
      return [
        'id' => $p->id,
        'label' => ($p->sku ? ($p->sku+' - ') : '') . ($p->descripcion ?? ''),
        'descripcion' => $p->descripcion ?? '',
        'precio_unitario' => (float)($p->precio_unitario ?? 0),
        'iva_tasa' => (float)($p->iva_tasa ?? 0.16),
      ];
    })->values(),
    JSON_UNESCAPED_UNICODE
  ) !!};

  const itemsBody   = document.getElementById('itemsBody');
  const calcPreview = document.getElementById('calcPreview');
  const totalLabel  = document.getElementById('totalLabel');

  function fmt(n){ return (n||0).toLocaleString(undefined,{minimumFractionDigits:2, maximumFractionDigits:2}); }

  function rowTemplate(idx, data){
    const d = Object.assign({producto_id:'',descripcion:'',cantidad:1,precio_unitario:0,iva_tasa:0.16,subtotal:0,iva:0,total:0}, data||{});
    const options = (PRODUCTOS||[]).map(p=>`<option value="${p.id}" ${String(d.producto_id)===String(p.id)?'selected':''}>${p.label}</option>`).join('');
    return `
      <tr data-idx="${idx}">
        <td><textarea class="input" name="conceptos[${idx}][descripcion]" rows="2" required>${d.descripcion||''}</textarea></td>
        <td>
          <select class="select" name="conceptos[${idx}][producto_id]" onchange="onProductChange(${idx}, this.value)">
            <option value="">—</option>
            ${options}
          </select>
        </td>
        <td><input type="number" step="0.0001" min="0.0001" class="number" name="conceptos[${idx}][cantidad]" value="${d.cantidad}" oninput="recalc()" required></td>
        <td><input type="number" step="0.0001" min="0" class="number" name="conceptos[${idx}][precio_unitario]" value="${d.precio_unitario}" oninput="recalc()" required></td>
        <td><input type="number" step="0.0001" min="0" class="number" name="conceptos[${idx}][iva_tasa]" value="${d.iva_tasa}" oninput="recalc()"></td>
        <td class="subtotal">$0.00</td>
        <td class="total">$0.00</td>
        <td><button type="button" class="btn ghost" onclick="removeItem(${idx})">✕</button></td>
      </tr>
    `;
  }

  function addItem(prefill){
    const idx = itemsBody.children.length;
    itemsBody.insertAdjacentHTML('beforeend', rowTemplate(idx, prefill));
    recalc();
  }

  function removeItem(idx){
    const tr = itemsBody.querySelector(`tr[data-idx="${idx}"]`);
    if(tr){ tr.remove(); renumber(); recalc(); }
  }

  function renumber(){
    [...itemsBody.children].forEach((tr,i) => {
      tr.dataset.idx = i;
      tr.querySelectorAll('[name]').forEach(el=>{
        el.name = el.name.replace(/\[\d+\]/, `[${i}]`);
      });
    });
  }

  function onProductChange(idx, pid){
    const p = (PRODUCTOS||[]).find(x=>String(x.id)===String(pid));
    const tr = itemsBody.querySelector(`tr[data-idx="${idx}"]`);
    if(p && tr){
      tr.querySelector(`[name="conceptos[${idx}][descripcion]"]`).value = p.descripcion||'';
      tr.querySelector(`[name="conceptos[${idx}][precio_unitario]"]`).value = p.precio_unitario||0;
      tr.querySelector(`[name="conceptos[${idx}][iva_tasa]"]`).value = (p.iva_tasa ?? 0.16);
      recalc();
    }
  }

  function recalc(){
    let subtotal=0, iva=0, total=0;
    [...itemsBody.children].forEach((tr, i)=>{
      const q  = parseFloat(tr.querySelector(`[name="conceptos[${i}][cantidad]"]`)?.value || '0');
      const pu = parseFloat(tr.querySelector(`[name="conceptos[${i}][precio_unitario]"]`)?.value || '0');
      const t  = parseFloat(tr.querySelector(`[name="conceptos[${i}][iva_tasa]"]`)?.value || '0');
      const s  = Math.round(q*pu*100)/100;
      const v  = Math.round(s*t*100)/100;
      const tot= Math.round((s+v)*100)/100;
      subtotal+=s; iva+=v; total+=tot;
      const sEl = tr.querySelector('.subtotal'); if(sEl) sEl.textContent = '$'+fmt(s);
      const tEl = tr.querySelector('.total'); if(tEl) tEl.textContent    = '$'+fmt(tot);
    });
    if(calcPreview) calcPreview.textContent = `Subtotal: $${fmt(subtotal)} · IVA: $${fmt(iva)} · Total: $${fmt(total)}`;
    if(totalLabel)  totalLabel.textContent  = '$'+fmt(total);
  }

  // Fila inicial
  addItem({ cantidad:1, precio_unitario:0, iva_tasa:0.16 });
</script>
@endpush
