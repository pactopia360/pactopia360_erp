{{-- resources/views/cliente/facturacion/create.blade.php (v2 visual Pactopia360) --}}
@extends('layouts.client')
@section('title','Nuevo Documento · Pactopia360')

@push('styles')
<style>
  body{font-family:'Poppins',system-ui,sans-serif;}

  /* ======== Header ======== */
  .page-header{
    display:flex;align-items:center;justify-content:space-between;
    gap:12px;flex-wrap:wrap;margin-bottom:18px;
  }
  .page-title{
    margin:0;
    font-weight:900;
    font-size:22px;
    color:#E11D48;
  }

  /* ======== Botones ======== */
  .btn{
    display:inline-flex;align-items:center;justify-content:center;gap:8px;
    padding:10px 14px;
    border-radius:12px;
    font-weight:800;
    font-size:14px;
    cursor:pointer;
    text-decoration:none;
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
  .btn:disabled{opacity:.5;pointer-events:none;}

  /* ======== Contenedores ======== */
  .steps{display:grid;gap:18px;}
  .grid-2{display:grid;gap:14px;}
  @media(min-width:1080px){.grid-2{grid-template-columns:1fr 1fr;}}

  .card{
    position:relative;
    background:linear-gradient(180deg,rgba(255,255,255,.96),rgba(255,255,255,.9));
    border:1px solid #f3d5dc;
    border-radius:18px;
    padding:20px 22px;
    box-shadow:0 8px 28px rgba(225,29,72,.08);
    display:grid;
    gap:14px;
  }
  .card::before{
    content:"";position:absolute;inset:-1px;border-radius:19px;padding:1px;
    background:linear-gradient(145deg,#E11D48,#BE123C);
    -webkit-mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);
    -webkit-mask-composite:xor;mask-composite:exclude;
    opacity:.25;pointer-events:none;
  }
  .card h3{
    margin:0;font-size:13px;text-transform:uppercase;
    letter-spacing:.25px;font-weight:800;color:#E11D48;
  }

  /* ======== Inputs ======== */
  .field{display:grid;gap:6px;}
  .lbl{font-weight:700;color:#6b7280;font-size:12px;}
  .input,.select,.number,textarea{
    width:100%;border:1px solid #f3d5dc;border-radius:12px;
    background:#fff;color:#0f172a;
    padding:10px 12px;font-weight:700;
    transition:border-color .2s,box-shadow .2s;
  }
  .input:focus,.select:focus,.number:focus,textarea:focus{
    border-color:#E11D48;box-shadow:0 0 0 3px rgba(225,29,72,.25);
  }
  textarea{resize:vertical;min-height:60px;}

  /* ======== Tabla ======== */
  table.items{
    width:100%;
    border-collapse:collapse;
    border:1px solid #f3d5dc;
    border-radius:14px;
    overflow:hidden;
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
  table.items td textarea{min-height:38px;}

  .subtotal,.total{font-weight:700;font-size:13px;}
  .calc-line{
    background:#fff0f3;
    border-radius:10px;
    padding:8px 14px;
    font-weight:800;
    color:#BE123C;
  }

  .total{
    font-size:clamp(18px,3vw,22px);
    font-weight:900;
    color:#E11D48;
  }
</style>
@endpush

@section('content')
<div class="page-header">
  <h1 class="page-title">Nuevo Documento</h1>
  <div class="tools">
    <a href="{{ route('cliente.facturacion.index') }}" class="btn ghost">← Volver</a>
  </div>
</div>

<form method="POST" action="{{ route('cliente.facturacion.guardar') }}" id="docForm">
  @csrf
  <div class="steps">

    {{-- ================== EMISOR ================== --}}
    <div class="card">
      <h3>Emisor</h3>
      <div class="field">
        <label class="lbl">Empresa / Emisor</label>
        <select name="cliente_id" class="select" required>
          <option value="">Selecciona emisor</option>
          @foreach($emisores as $e)
            <option value="{{ $e->id }}">{{ $e->nombre_comercial ?? $e->razon_social ?? ('#'.$e->id) }} — {{ $e->rfc }}</option>
          @endforeach
        </select>
      </div>

      <div class="grid-2">
        <div class="field">
          <label class="lbl">Serie</label>
          <input type="text" name="serie" class="input" maxlength="10" placeholder="A, B, ..." />
        </div>
        <div class="field">
          <label class="lbl">Folio</label>
          <input type="text" name="folio" class="input" maxlength="20" placeholder="000123" />
        </div>
      </div>

      <div class="grid-2">
        <div class="field">
          <label class="lbl">Moneda</label>
          <input type="text" name="moneda" class="input" maxlength="10" value="MXN" />
        </div>
        <div class="field">
          <label class="lbl">Fecha</label>
          <input type="datetime-local" name="fecha" class="input" value="{{ now()->format('Y-m-d\TH:i') }}" />
        </div>
      </div>
    </div>

    {{-- ================== RECEPTOR ================== --}}
    <div class="card">
      <h3>Receptor</h3>
      <div class="grid-2">
        <div class="field">
          <label class="lbl">Receptor</label>
          <select name="receptor_id" class="select" required>
            <option value="">Selecciona receptor</option>
            @foreach($receptores as $r)
              <option value="{{ $r->id }}">{{ $r->razon_social }} — {{ $r->rfc }}</option>
            @endforeach
          </select>
        </div>
        <div class="field">
          <label class="lbl">Uso CFDI</label>
          <input type="text" class="input" value="G03" readonly />
        </div>
      </div>
    </div>

    {{-- ================== CONCEPTOS ================== --}}
    <div class="card">
      <h3>Conceptos / Productos</h3>
      <table class="items" id="itemsTable">
        <thead>
          <tr>
            <th style="width:30%">Descripción</th>
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

      <div style="display:flex;align-items:center;gap:10px;margin-top:12px;">
        <button type="button" class="btn ghost" onclick="addItem()">＋ Agregar concepto</button>
        <div class="right"></div>
        <div class="calc-line" id="calcPreview" aria-live="polite">
          Subtotal: $0.00 · IVA: $0.00 · Total: $0.00
        </div>
      </div>
    </div>

    {{-- ================== RESUMEN ================== --}}
    <div class="card">
      <h3>Resumen</h3>
      <div class="grid-2">
        <div class="field">
          <label class="lbl">Forma de pago</label>
          <input type="text" name="forma_pago" class="input" placeholder="03" />
        </div>
        <div class="field">
          <label class="lbl">Método de pago</label>
          <input type="text" name="metodo_pago" class="input" placeholder="PUE" />
        </div>
      </div>

      <div style="display:flex;align-items:center;gap:12px;margin-top:10px;">
        <div class="total" id="totalLabel">$0.00</div>
        <div class="right"></div>
        <button type="submit" class="btn primary">Guardar borrador</button>
      </div>
    </div>
  </div>

  <input type="hidden" name="conceptos_payload" id="conceptos_payload" />
</form>
@endsection

@push('scripts')
<script>
  const PRODUCTOS = @json($productos->map(fn($p)=>[
    'id'=>$p->id,
    'label'=>trim(($p->sku ? $p->sku+' - ' : '')+$p->descripcion),
    'descripcion'=>$p->descripcion,
    'precio_unitario'=>$p->precio_unitario,
    'iva_tasa'=>$p->iva_tasa
  ]));

  const itemsBody = document.getElementById('itemsBody');
  const calcPreview = document.getElementById('calcPreview');
  const totalLabel = document.getElementById('totalLabel');
  const payload = document.getElementById('conceptos_payload');
  const form = document.getElementById('docForm');

  function fmt(n){return (n||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});}

  function rowTemplate(idx,data){
    const d=Object.assign({producto_id:'',descripcion:'',cantidad:1,precio_unitario:0,iva_tasa:0.16},data||{});
    return `
      <tr data-idx="${idx}">
        <td><textarea class="input" name="conceptos[${idx}][descripcion]" rows="2" required>${d.descripcion||''}</textarea></td>
        <td>
          <select class="select" name="conceptos[${idx}][producto_id]" onchange="onProductChange(${idx},this.value)">
            <option value="">—</option>
            ${PRODUCTOS.map(p=>`<option value="${p.id}" ${String(d.producto_id)===String(p.id)?'selected':''}>${p.label}</option>`).join('')}
          </select>
        </td>
        <td><input type="number" step="0.0001" min="0.0001" class="number" name="conceptos[${idx}][cantidad]" value="${d.cantidad}" oninput="recalc()" required></td>
        <td><input type="number" step="0.0001" min="0" class="number" name="conceptos[${idx}][precio_unitario]" value="${d.precio_unitario}" oninput="recalc()" required></td>
        <td><input type="number" step="0.0001" min="0" class="number" name="conceptos[${idx}][iva_tasa]" value="${d.iva_tasa}" oninput="recalc()"></td>
        <td class="subtotal">$0.00</td>
        <td class="total">$0.00</td>
        <td><button type="button" class="btn ghost" onclick="removeItem(${idx})">✕</button></td>
      </tr>`;
  }

  function addItem(data){
    const idx=itemsBody.children.length;
    itemsBody.insertAdjacentHTML('beforeend',rowTemplate(idx,data));
    recalc();
  }

  function removeItem(idx){
    const tr=itemsBody.querySelector(`tr[data-idx="${idx}"]`);
    if(tr){tr.remove();recalc();}
  }

  function onProductChange(idx,pid){
    const p=PRODUCTOS.find(x=>String(x.id)===String(pid));
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

  addItem();

  form.addEventListener('submit',()=>{
    const conceptos=[...itemsBody.children].map((tr,i)=>({
      producto_id:tr.querySelector(`[name="conceptos[${i}][producto_id]"]`)?.value||null,
      descripcion:tr.querySelector(`[name="conceptos[${i}][descripcion]"]`)?.value||'',
      cantidad:parseFloat(tr.querySelector(`[name="conceptos[${i}][cantidad]"]`)?.value||'0'),
      precio_unitario:parseFloat(tr.querySelector(`[name="conceptos[${i}][precio_unitario]"]`)?.value||'0'),
      iva_tasa:parseFloat(tr.querySelector(`[name="conceptos[${i}][iva_tasa]"]`)?.value||'0'),
    }));
    payload.value=JSON.stringify(conceptos);
  });
</script>
@endpush
