{{-- resources/views/cliente/facturacion/nuevo.blade.php (v2 visual Pactopia360) --}}
@extends('layouts.client')
@section('title','Nuevo CFDI · Pactopia360')

@push('styles')
<style>
  body{font-family:'Poppins',system-ui,sans-serif;}

  /* Header */
  .page-header{
    display:flex;align-items:center;justify-content:space-between;
    gap:12px;margin-bottom:18px;flex-wrap:wrap;
  }
  .page-title{
    margin:0;font-weight:900;font-size:22px;color:#E11D48;
  }

  /* Botones */
  .btn{
    display:inline-flex;align-items:center;justify-content:center;gap:8px;
    padding:10px 14px;
    border-radius:12px;
    font-weight:800;font-size:14px;
    cursor:pointer;text-decoration:none;
    transition:.15s filter ease;
  }
  .btn.primary{
    background:linear-gradient(90deg,#E11D48,#BE123C);
    color:#fff;border:0;
    box-shadow:0 8px 20px rgba(225,29,72,.25);
  }
  .btn.primary:hover{filter:brightness(.96);}
  .btn.ghost{
    background:#fff;
    border:1px solid #f3d5dc;
    color:#E11D48;
  }
  .btn.ghost:hover{background:#fff0f3;}
  .btn.disabled{
    opacity:.5;cursor:not-allowed;filter:grayscale(.8);
  }

  /* Cards */
  .card{
    position:relative;
    background:linear-gradient(180deg,rgba(255,255,255,.96),rgba(255,255,255,.9));
    border:1px solid #f3d5dc;
    border-radius:18px;
    padding:20px 22px;
    box-shadow:0 8px 28px rgba(225,29,72,.08);
    margin-top:12px;
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
    font-size:13px;
    text-transform:uppercase;
    letter-spacing:.25px;
    font-weight:800;
    color:#E11D48;
  }

  /* Grids / Inputs */
  .grid-2{display:grid;gap:14px;}
  @media(min-width:1080px){.grid-2{grid-template-columns:1fr 1fr;}}

  .field{display:grid;gap:6px;}
  .lbl{font-size:12px;color:#6b7280;font-weight:700;}
  .input,.select,.number,textarea{
    width:100%;
    border:1px solid #f3d5dc;border-radius:12px;
    background:#fff;color:#0f172a;
    padding:10px 12px;font-weight:700;
    transition:border-color .2s,box-shadow .2s;
  }
  .input:focus,.select:focus,.number:focus,textarea:focus{
    border-color:#E11D48;box-shadow:0 0 0 3px rgba(225,29,72,.25);
  }
  textarea{resize:vertical;min-height:60px;}
  .hint{color:#6b7280;font-size:12px;}

  /* Tabla */
  table.items{
    width:100%;
    border-collapse:collapse;
    border:1px solid #f3d5dc;
    border-radius:14px;
    overflow:hidden;
    margin-top:6px;
  }
  table.items th,table.items td{
    padding:10px 12px;
    border-bottom:1px solid #f3d5dc;
    text-align:left;
  }
  table.items th{
    background:#fff0f3;
    color:#6b7280;
    font-size:12px;
    font-weight:900;
    text-transform:uppercase;
  }
  table.items tr:hover td{background:#fffafc;}
  .subtotal,.total{font-weight:700;font-size:13px;}
  .total{color:#E11D48;}
  .calc-line{
    background:#fff0f3;
    border-radius:10px;
    padding:8px 14px;
    font-weight:800;
    color:#BE123C;
  }
</style>
@endpush

@section('content')
<div class="page-header">
  <h1 class="page-title">Nuevo CFDI</h1>
  <div class="tools">
    <a href="{{ route('cliente.facturacion.index', request()->only(['q','status','month'])) }}" class="btn ghost">← Volver</a>
  </div>
</div>

@if(session('ok'))
  <div class="card" style="background:#ecfdf5;border:1px solid #86efac;color:#047857;"><strong>{{ session('ok') }}</strong></div>
@endif
@if($errors->any())
  <div class="card" style="background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;"><strong>{{ $errors->first() }}</strong></div>
@endif

@php
  $emisores   = collect($emisores ?? []);
  $receptores = collect($receptores ?? []);
  $productos  = collect($productos ?? []);
@endphp

<form method="POST" action="{{ route('cliente.facturacion.store') }}" id="newForm">
  @csrf

  {{-- Emisor --}}
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
        <div class="input" style="display:flex;align-items:center">Se generará automáticamente</div>
      </div>
    </div>
  </div>

  {{-- Receptor --}}
  <div class="card">
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

  {{-- Conceptos --}}
  <div class="card">
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
      <tbody id="itemsBody"></tbody>
    </table>

    <div style="display:flex;gap:8px;margin-top:10px;align-items:center;flex-wrap:wrap;">
      <button type="button" class="btn ghost" onclick="addItem()">＋ Agregar concepto</button>
      <div style="flex:1"></div>
      <div class="calc-line" id="calcPreview" aria-live="polite">Subtotal: $0.00 · IVA: $0.00 · Total: $0.00</div>
    </div>
  </div>

  {{-- Pago --}}
  <div class="card">
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

    <div style="display:flex;align-items:center;gap:12px;margin-top:8px;">
      <div class="total" id="totalLabel">$0.00</div>
      <div style="flex:1"></div>
      <button type="submit" class="btn primary">Crear borrador</button>
    </div>
  </div>
</form>
@endsection

@push('scripts')
<script>
  const PRODUCTOS={!! json_encode(
    ($productos??collect())->map(fn($p)=>[
      'id'=>$p->id,
      'label'=>($p->sku?$p->sku.' - ':'').($p->descripcion??''),
      'descripcion'=>$p->descripcion??'',
      'precio_unitario'=>(float)($p->precio_unitario??0),
      'iva_tasa'=>(float)($p->iva_tasa??0.16),
    ])->values(),
    JSON_UNESCAPED_UNICODE
  ) !!};

  const itemsBody=document.getElementById('itemsBody');
  const calcPreview=document.getElementById('calcPreview');
  const totalLabel=document.getElementById('totalLabel');

  function fmt(n){return(n||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});}

  function rowTemplate(idx,d){
    const data=Object.assign({producto_id:'',descripcion:'',cantidad:1,precio_unitario:0,iva_tasa:0.16},d||{});
    const opts=(PRODUCTOS||[]).map(p=>`<option value="${p.id}" ${String(data.producto_id)===String(p.id)?'selected':''}>${p.label}</option>`).join('');
    return `<tr data-idx="${idx}">
      <td><textarea class="input" name="conceptos[${idx}][descripcion]" rows="2" required>${data.descripcion||''}</textarea></td>
      <td><select class="select" name="conceptos[${idx}][producto_id]" onchange="onProductChange(${idx},this.value)">
        <option value="">—</option>${opts}</select></td>
      <td><input type="number" step="0.0001" min="0.0001" class="number" name="conceptos[${idx}][cantidad]" value="${data.cantidad}" oninput="recalc()" required></td>
      <td><input type="number" step="0.0001" min="0" class="number" name="conceptos[${idx}][precio_unitario]" value="${data.precio_unitario}" oninput="recalc()" required></td>
      <td><input type="number" step="0.0001" min="0" class="number" name="conceptos[${idx}][iva_tasa]" value="${data.iva_tasa}" oninput="recalc()"></td>
      <td class="subtotal">$0.00</td><td class="total">$0.00</td>
      <td><button type="button" class="btn ghost" onclick="removeItem(${idx})">✕</button></td></tr>`;
  }

  function addItem(d){
    const idx=itemsBody.children.length;
    itemsBody.insertAdjacentHTML('beforeend',rowTemplate(idx,d));
    recalc();
  }

  function removeItem(idx){
    const tr=itemsBody.querySelector(`tr[data-idx="${idx}"]`);
    if(tr){tr.remove();renumber();recalc();}
  }

  function renumber(){
    [...itemsBody.children].forEach((tr,i)=>{
      tr.dataset.idx=i;
      tr.querySelectorAll('[name]').forEach(el=>{el.name=el.name.replace(/\[\d+\]/,`[${i}]`);});
    });
  }

  function onProductChange(idx,pid){
    const p=(PRODUCTOS||[]).find(x=>String(x.id)===String(pid));
    const tr=itemsBody.querySelector(`tr[data-idx="${idx}"]`);
    if(p&&tr){
      tr.querySelector(`[name="conceptos[${idx}][descripcion]"]`).value=p.descripcion;
      tr.querySelector(`[name="conceptos[${idx}][precio_unitario]"]`).value=p.precio_unitario;
      tr.querySelector(`[name="conceptos[${idx}][iva_tasa]"]`).value=p.iva_tasa;
      recalc();
    }
  }

  function recalc(){
    let subtotal=0,iva=0,total=0;
    [...itemsBody.children].forEach((tr,i)=>{
      const q=parseFloat(tr.querySelector(`[name="conceptos[${i}][cantidad]"]`)?.value||'0');
      const pu=parseFloat(tr.querySelector(`[name="conceptos[${i}][precio_unitario]"]`)?.value||'0');
      const t=parseFloat(tr.querySelector(`[name="conceptos[${i}][iva_tasa]"]`)?.value||'0');
      const s=Math.round(q*pu*100)/100;
      const v=Math.round(s*t*100)/100;
      const tot=Math.round((s+v)*100)/100;
      subtotal+=s;iva+=v;total+=tot;
      tr.querySelector('.subtotal').textContent='$'+fmt(s);
      tr.querySelector('.total').textContent='$'+fmt(tot);
    });
    calcPreview.textContent=`Subtotal: $${fmt(subtotal)} · IVA: $${fmt(iva)} · Total: $${fmt(total)}`;
    totalLabel.textContent='$'+fmt(total);
  }

  addItem({cantidad:1,precio_unitario:0,iva_tasa:0.16});
</script>
@endpush
