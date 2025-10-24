{{-- resources/views/cliente/facturacion/edit.blade.php (v2 · Unificado + robusto) --}}
@extends('layouts.client')
@section('title','Editar borrador · Pactopia360')

@push('styles')
<style>
  .head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px;flex-wrap:wrap}
  .head h1{margin:0;font:900 20px/1.1 ui-sans-serif,system-ui}
  .badge{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;border:1px solid var(--border);font-weight:900;font-size:12px}
  .badge.borrador{background: color-mix(in oklab, var(--accent) 14%, transparent)}

  .tools{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
  .btnx{display:inline-flex;align-items:center;gap:8px;padding:10px 12px;border-radius:12px;border:1px solid var(--border);font-weight:900;text-decoration:none}
  .btnx.primary{background:linear-gradient(180deg,var(--accent),color-mix(in oklab,var(--accent) 85%, black));color:#fff;border:0}
  .btnx.ghost{background:transparent}
  .btnx.disabled{opacity:.55;pointer-events:none}

  .card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:14px;box-shadow:var(--shadow)}
  .card h3{margin:0 0 10px;font-size:12px;color:var(--muted);letter-spacing:.2px;text-transform:uppercase;font-weight:900}

  .grid-2{display:grid;gap:12px}
  @media (min-width:1080px){ .grid-2{grid-template-columns:1fr 1fr} }

  .field{display:grid;gap:6px;margin-bottom:10px}
  .lbl{font-weight:800;color:var(--muted);font-size:12px}
  .input,.select,.number,textarea{
    width:100%;border:1px solid var(--border);border-radius:12px;
    background:color-mix(in oklab,var(--card) 96%, transparent);
    color:var(--text);padding:10px 12px;font-weight:700;resize:vertical
  }

  table.items{width:100%;border-collapse:separate;border-spacing:0}
  table.items th,table.items td{padding:10px;border-bottom:1px solid var(--border);text-align:left;font-size:14px}
  .right{text-align:right}
  .total-big{font-size:clamp(18px,2.8vw,22px);font-weight:900}

  .alert-ok{background:color-mix(in oklab,var(--ok) 22%, transparent);border:1px solid color-mix(in oklab,var(--ok) 44%, transparent)}
  .alert-err{background:color-mix(in oklab,var(--err) 18%, transparent);border:1px solid color-mix(in oklab,var(--err) 38%, transparent)}
</style>
@endpush

@section('content')
@php
  /** @var \App\Models\Cfdi $cfdi */
  $u = auth('web')->user();
  $c = $u?->cuenta;
  $plan   = strtoupper((string)($c->plan_actual ?? 'FREE'));
  $isDraft = strtolower((string)($cfdi->estatus ?? 'borrador')) === 'borrador';

  // Productos listos para JS (evitamos @json). Normalizamos a un arreglo plano seguro.
  $productosJs = collect($productos ?? [])
    ->map(function($p){
      return [
        'id' => (string)($p->id),
        'label' => ($p->sku ? $p->sku.' - ' : '') . (string)($p->descripcion ?? ''),
        'descripcion' => (string)($p->descripcion ?? ''),
        'precio_unitario' => (float)($p->precio_unitario ?? 0),
        'iva_tasa' => (float)($p->iva_tasa ?? 0.16),
      ];
    })
    ->values()
    ->all();
@endphp

<div class="head">
  <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
    <h1>Editar borrador</h1>
    <span class="badge borrador">BORRADOR</span>
  </div>

  <div class="tools">
    <a href="{{ route('cliente.facturacion.index', request()->only(['q','status','mes','anio','month'])) }}" class="btnx ghost">← Volver</a>

    {{-- Duplicar (sólo PRO) --}}
    @if ($plan === 'PRO')
      <form method="POST" action="{{ route('cliente.facturacion.duplicar', $cfdi->id) }}" style="display:inline">
        @csrf
        <button type="submit" class="btnx">Duplicar</button>
      </form>
    @else
      <button class="btnx disabled" title="Disponible en PRO">Duplicar</button>
    @endif

    {{-- Timbrar --}}
    @if ($isDraft)
      @if ($plan === 'PRO')
        <form method="POST" action="{{ route('cliente.facturacion.timbrar', $cfdi->id) }}" style="display:inline">
          @csrf
          <button type="submit" class="btnx primary">Timbrar</button>
        </form>
      @else
        <button class="btnx disabled" title="Para timbrar, actualiza a PRO">Timbrar</button>
      @endif
    @endif
  </div>
</div>

@if (session('ok'))
  <div class="card alert-ok"><strong>{{ session('ok') }}</strong></div>
@endif
@if ($errors->any())
  <div class="card alert-err"><strong>{{ $errors->first() }}</strong></div>
@endif

<form method="POST" action="{{ route('cliente.facturacion.actualizar', $cfdi->id) }}" id="editForm">
  @csrf
  @method('PUT')

  {{-- Emisor --}}
  <div class="card">
    <h3>Emisor</h3>
    <div class="grid-2">
      <div class="field">
        <span class="lbl">Empresa / Emisor</span>
        <select name="cliente_id" class="select" required {{ $isDraft ? '' : 'disabled' }}>
          @foreach(($emisores ?? []) as $e)
            <option value="{{ $e->id }}" {{ (int)$e->id === (int)$cfdi->cliente_id ? 'selected' : '' }}>
              {{ $e->nombre_comercial ?? $e->razon_social ?? ('#'.$e->id) }} — {{ $e->rfc }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="field">
        <span class="lbl">Fecha</span>
        <input type="datetime-local" name="fecha" class="input"
               value="{{ optional($cfdi->fecha)->format('Y-m-d\TH:i') }}"
               {{ $isDraft ? '' : 'readonly' }}/>
      </div>
    </div>

    <div class="grid-2">
      <div class="field">
        <span class="lbl">Serie</span>
        <input type="text" name="serie" class="input" maxlength="10" value="{{ $cfdi->serie }}" {{ $isDraft ? '' : 'readonly' }}/>
      </div>
      <div class="field">
        <span class="lbl">Folio</span>
        <input type="text" name="folio" class="input" maxlength="20" value="{{ $cfdi->folio }}" {{ $isDraft ? '' : 'readonly' }}/>
      </div>
    </div>

    <div class="grid-2">
      <div class="field">
        <span class="lbl">Moneda</span>
        <input type="text" name="moneda" class="input" maxlength="10" value="{{ $cfdi->moneda ?? 'MXN' }}" {{ $isDraft ? '' : 'readonly' }}/>
      </div>
      <div class="field">
        <span class="lbl">UUID</span>
        <div class="input" style="display:flex;align-items:center">{{ $cfdi->uuid }}</div>
      </div>
    </div>
  </div>

  {{-- Receptor --}}
  <div class="card" style="margin-top:12px">
    <h3>Receptor</h3>
    <div class="grid-2">
      <div class="field">
        <span class="lbl">Receptor</span>
        <select name="receptor_id" class="select" required {{ $isDraft ? '' : 'disabled' }}>
          @foreach(($receptores ?? []) as $r)
            <option value="{{ $r->id }}" {{ (int)$r->id === (int)$cfdi->receptor_id ? 'selected' : '' }}>
              {{ $r->razon_social ?? $r->nombre_comercial ?? ('#'.$r->id) }} — {{ $r->rfc }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="field">
        <span class="lbl">Uso CFDI</span>
        <input type="text" class="input" value="{{ optional($cfdi->receptor)->uso_cfdi ?? ($cfdi->uso_cfdi ?? 'G03') }}" readonly />
      </div>
    </div>
  </div>

  {{-- Conceptos --}}
  <div class="card" style="margin-top:12px">
    <h3>Conceptos / Productos</h3>

    <table class="items" id="itemsTable">
      <thead>
        <tr>
          <th style="width:34%">Descripción</th>
          <th>Producto</th>
          <th class="right">Cant.</th>
          <th class="right">Precio</th>
          <th class="right">IVA</th>
          <th class="right">Subtotal</th>
          <th class="right">Total</th>
          @if ($isDraft) <th></th> @endif
        </tr>
      </thead>
      <tbody id="itemsBody">
        @php $idx = 0; @endphp
        @foreach(($cfdi->conceptos ?? []) as $it)
          <tr data-idx="{{ $idx }}">
            <td>
              <textarea class="input" name="conceptos[{{ $idx }}][descripcion]" rows="2" {{ $isDraft ? '' : 'readonly' }}>{{ $it->descripcion }}</textarea>
            </td>
            <td>
              <select class="select" name="conceptos[{{ $idx }}][producto_id]" {{ $isDraft ? '' : 'disabled' }}>
                <option value="">—</option>
                @foreach(($productos ?? []) as $p)
                  <option value="{{ $p->id }}" {{ (int)$p->id === (int)($it->producto_id ?? 0) ? 'selected' : '' }}>
                    {{ ($p->sku ? $p->sku.' - ' : '') . $p->descripcion }}
                  </option>
                @endforeach
              </select>
            </td>
            <td class="right">
              <input type="number" step="0.0001" min="0.0001" class="number"
                     name="conceptos[{{ $idx }}][cantidad]" value="{{ (float)$it->cantidad }}"
                     oninput="recalc()" {{ $isDraft ? '' : 'readonly' }}>
            </td>
            <td class="right">
              <input type="number" step="0.0001" min="0" class="number"
                     name="conceptos[{{ $idx }}][precio_unitario]" value="{{ (float)$it->precio_unitario }}"
                     oninput="recalc()" {{ $isDraft ? '' : 'readonly' }}>
            </td>
            <td class="right">
              <input type="number" step="0.0001" min="0" class="number"
                     name="conceptos[{{ $idx }}][iva_tasa]" value="{{ (float)($it->iva_tasa ?? 0.16) }}"
                     oninput="recalc()" {{ $isDraft ? '' : 'readonly' }}>
            </td>
            <td class="right subtotal">${{ number_format((float)($it->subtotal ?? 0),2) }}</td>
            <td class="right total">${{ number_format((float)($it->total ?? 0),2) }}</td>
            @if ($isDraft) <td><button type="button" class="btnx ghost" onclick="removeItem({{ $idx }})">✕</button></td> @endif
          </tr>
          @php $idx++; @endphp
        @endforeach
      </tbody>
    </table>

    @if ($isDraft)
      <div style="display:flex;gap:8px;margin-top:10px;align-items:center">
        <button type="button" class="btnx" onclick="addItem()">+ Agregar concepto</button>
        <div class="btnx ghost" id="calcPreview" aria-live="polite">
          Subtotal: ${{ number_format((float)($cfdi->subtotal ?? 0), 2) }} · IVA: ${{ number_format((float)($cfdi->iva ?? 0), 2) }} · Total: ${{ number_format((float)($cfdi->total ?? 0), 2) }}
        </div>
      </div>
    @endif
  </div>

  {{-- Pago / Total --}}
  <div class="card" style="margin-top:12px">
    <h3>Pago</h3>
    <div class="grid-2">
      <div class="field">
        <span class="lbl">Forma de pago</span>
        <input type="text" name="forma_pago" class="input" value="{{ $cfdi->forma_pago }}" {{ $isDraft ? '' : 'readonly' }}/>
      </div>
      <div class="field">
        <span class="lbl">Método de pago</span>
        <input type="text" name="metodo_pago" class="input" value="{{ $cfdi->metodo_pago }}" {{ $isDraft ? '' : 'readonly' }}/>
      </div>
    </div>

    <div style="display:flex;align-items:center;gap:12px;margin-top:8px">
      <div class="total-big" id="totalLabel">${{ number_format((float)($cfdi->total ?? 0),2) }}</div>
      <div style="flex:1"></div>
      <button type="submit" class="btnx primary" {{ $isDraft ? '' : 'disabled' }}>Actualizar borrador</button>
    </div>
  </div>

  <input type="hidden" name="conceptos_payload" id="conceptos_payload" />
</form>
@endsection

@push('scripts')
<script>
  // Productos desde PHP (sin @json para evitar conflictos de compilación)
  const PRODUCTOS = {!! json_encode($productosJs, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!};
  const isDraft   = {{ $isDraft ? 'true' : 'false' }};

  const itemsBody   = document.getElementById('itemsBody');
  const calcPreview = document.getElementById('calcPreview');
  const totalLabel  = document.getElementById('totalLabel');
  const form        = document.getElementById('editForm');
  const payloadInp  = document.getElementById('conceptos_payload');

  function fmt(n){ return (n||0).toLocaleString(undefined,{minimumFractionDigits:2, maximumFractionDigits:2}); }

  function rowTemplate(idx, d){
    d = Object.assign({producto_id:'',descripcion:'',cantidad:1,precio_unitario:0,iva_tasa:0.16}, d||{});
    return `
      <tr data-idx="${idx}">
        <td><textarea class="input" name="conceptos[${idx}][descripcion]" rows="2" required>${(d.descripcion||'')}</textarea></td>
        <td>
          <select class="select" name="conceptos[${idx}][producto_id]" onchange="onProductChange(${idx}, this.value)">
            <option value="">—</option>
            ${PRODUCTOS.map(p=>`<option value="${p.id}" ${String(d.producto_id)===String(p.id)?'selected':''}>${p.label}</option>`).join('')}
          </select>
        </td>
        <td class="right"><input type="number" step="0.0001" min="0.0001" class="number" name="conceptos[${idx}][cantidad]" value="${d.cantidad}" oninput="recalc()" required></td>
        <td class="right"><input type="number" step="0.0001" min="0" class="number" name="conceptos[${idx}][precio_unitario]" value="${d.precio_unitario}" oninput="recalc()" required></td>
        <td class="right"><input type="number" step="0.0001" min="0" class="number" name="conceptos[${idx}][iva_tasa]" value="${d.iva_tasa}" oninput="recalc()"></td>
        <td class="right subtotal">$0.00</td>
        <td class="right total">$0.00</td>
        <td><button type="button" class="btnx ghost" onclick="removeItem(${idx})">✕</button></td>
      </tr>
    `;
  }

  function addItem(data){
    if(!isDraft) return;
    const idx = itemsBody.children.length;
    itemsBody.insertAdjacentHTML('beforeend', rowTemplate(idx, data));
    recalc();
  }

  function removeItem(idx){
    if(!isDraft) return;
    const tr = itemsBody.querySelector(`tr[data-idx="${idx}"]`);
    if(tr){ tr.remove(); renumber(); recalc(); }
  }

  function renumber(){
    [...itemsBody.children].forEach((tr,i)=>{
      tr.dataset.idx = i;
      tr.querySelectorAll('[name]').forEach(el=>{
        el.name = el.name.replace(/\[\d+\]/, `[${i}]`);
      });
    });
  }

  function onProductChange(idx, pid){
    const p = PRODUCTOS.find(x=>String(x.id)===String(pid));
    const tr = itemsBody.querySelector(`tr[data-idx="${idx}"]`);
    if(p && tr){
      const d = tr.querySelector(`[name="conceptos[${idx}][descripcion]"]`);
      const pu = tr.querySelector(`[name="conceptos[${idx}][precio_unitario]"]`);
      const t  = tr.querySelector(`[name="conceptos[${idx}][iva_tasa]"]`);
      if(d) d.value = p.descripcion || '';
      if(pu) pu.value = p.precio_unitario || 0;
      if(t)  t.value  = (typeof p.iva_tasa==='number'?p.iva_tasa:0.16);
      recalc();
    }
  }

  function recalc(){
    if(!isDraft) return;
    let subtotal=0, iva=0, total=0;
    [...itemsBody.children].forEach((tr,i)=>{
      const q  = parseFloat(tr.querySelector(`[name="conceptos[${i}][cantidad]"]`)?.value || '0');
      const pu = parseFloat(tr.querySelector(`[name="conceptos[${i}][precio_unitario]"]`)?.value || '0');
      const t  = parseFloat(tr.querySelector(`[name="conceptos[${i}][iva_tasa]"]`)?.value || '0');
      const s  = Math.round(q*pu*100)/100;
      const v  = Math.round(s*t*100)/100;
      const tot= Math.round((s+v)*100)/100;
      subtotal+=s; iva+=v; total+=tot;
      const sEl = tr.querySelector('.subtotal'); if(sEl) sEl.textContent = '$'+fmt(s);
      const tEl = tr.querySelector('.total');    if(tEl) tEl.textContent = '$'+fmt(tot);
    });
    if(calcPreview) calcPreview.textContent = `Subtotal: $${fmt(subtotal)} · IVA: $${fmt(iva)} · Total: $${fmt(total)}`;
    if(totalLabel)  totalLabel.textContent  = '$'+fmt(total);
  }

  if (isDraft) {
    form.addEventListener('submit', ()=>{
      const conceptos = [...itemsBody.children].map((tr,i)=>({
        producto_id:      tr.querySelector(`[name="conceptos[${i}][producto_id]"]`)?.value || null,
        descripcion:      tr.querySelector(`[name="conceptos[${i}][descripcion]"]`)?.value || '',
        cantidad:         parseFloat(tr.querySelector(`[name="conceptos[${i}][cantidad]"]`)?.value || '0') || 0,
        precio_unitario:  parseFloat(tr.querySelector(`[name="conceptos[${i}][precio_unitario]"]`)?.value || '0') || 0,
        iva_tasa:         parseFloat(tr.querySelector(`[name="conceptos[${i}][iva_tasa]"]`)?.value || '0') || 0,
      }));
      if(payloadInp) payloadInp.value = JSON.stringify(conceptos);
    });
    // Recalcular una vez al cargar por si hay filas
    recalc();
  }
</script>
@endpush
