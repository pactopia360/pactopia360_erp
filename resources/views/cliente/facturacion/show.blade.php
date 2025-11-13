{{-- resources/views/cliente/facturacion/show.blade.php (v4 visual Pactopia360 + banner dinámico de estatus) --}}
@extends('layouts.client')
@section('title','Detalle CFDI · Pactopia360')

@push('styles')
<style>
  body{font-family:'Poppins',system-ui,sans-serif;}

  /* ===== Banner dinámico ===== */
  .status-banner{
    display:flex;align-items:center;justify-content:space-between;
    padding:14px 20px;margin-bottom:20px;
    border-radius:14px;font-weight:800;font-size:15px;
    color:#fff;animation:fadeIn .6s ease forwards;
    box-shadow:0 8px 20px rgba(0,0,0,.08);
  }
  .status-banner small{font-weight:600;font-size:12px;opacity:.9;}
  .status-banner.borrador{background:linear-gradient(90deg,#F472B6,#E11D48);}
  .status-banner.emitido{background:linear-gradient(90deg,#10B981,#059669);}
  .status-banner.cancelado{background:linear-gradient(90deg,#EF4444,#B91C1C);}
  @keyframes fadeIn{from{opacity:0;transform:translateY(-8px);}to{opacity:1;transform:translateY(0);}}

  /* ===== Header ===== */
  .head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:20px;flex-wrap:wrap;}
  .head h1{margin:0;font-weight:900;font-size:22px;color:#E11D48;}
  .uuid{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12px;color:#6b7280;}

  /* ===== Botones ===== */
  .btnx{
    display:inline-flex;align-items:center;justify-content:center;gap:8px;
    padding:10px 14px;border-radius:12px;font-weight:800;font-size:14px;
    cursor:pointer;text-decoration:none;transition:.15s filter ease;
  }
  .btnx.primary{background:linear-gradient(90deg,#E11D48,#BE123C);color:#fff;border:0;box-shadow:0 8px 20px rgba(225,29,72,.25);}
  .btnx.primary:hover{filter:brightness(.96);}
  .btnx.ghost{background:#fff;border:1px solid #f3d5dc;color:#E11D48;}
  .btnx.ghost:hover{background:#fff0f3;}
  .btnx.danger{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;}
  .btnx.disabled{opacity:.5;cursor:not-allowed;filter:grayscale(.8);}

  /* ===== Cards ===== */
  .card{
    position:relative;background:linear-gradient(180deg,rgba(255,255,255,.96),rgba(255,255,255,.9));
    border:1px solid #f3d5dc;border-radius:18px;padding:20px 22px;
    box-shadow:0 8px 28px rgba(225,29,72,.08);
  }
  .card::before{
    content:"";position:absolute;inset:-1px;border-radius:19px;padding:1px;
    background:linear-gradient(145deg,#E11D48,#BE123C);
    -webkit-mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);
    -webkit-mask-composite:xor;mask-composite:exclude;opacity:.25;pointer-events:none;
  }
  .card h3{margin:0 0 10px;font-size:13px;text-transform:uppercase;letter-spacing:.25px;font-weight:800;color:#E11D48;}

  .grid-2{display:grid;gap:14px;}
  @media(min-width:1080px){.grid-2{grid-template-columns:1fr 1fr;}}

  .field{display:grid;gap:4px;margin-bottom:8px;}
  .lbl{font-weight:700;font-size:12px;color:#6b7280;}
  .val{font-weight:800;font-size:14px;color:#0f172a;}

  /* ===== Tabla ===== */
  table.items{
    width:100%;border-collapse:collapse;border:1px solid #f3d5dc;border-radius:14px;overflow:hidden;margin-top:6px;
  }
  table.items th,table.items td{padding:10px 12px;border-bottom:1px solid #f3d5dc;text-align:left;}
  table.items th{background:#fff0f3;color:#6b7280;font-size:12px;font-weight:900;text-transform:uppercase;}
  table.items tr:hover td{background:#fffafc;}
  .right{text-align:right;}
  .totals{display:flex;align-items:center;gap:14px;justify-content:flex-end;font-weight:900;color:#BE123C;margin-top:12px;}

  /* ===== Alerts ===== */
  .alert-ok{background:#ecfdf5;border:1px solid #86efac;color:#047857;border-radius:12px;padding:10px 14px;margin-bottom:10px;font-weight:700;}
  .alert-err{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;border-radius:12px;padding:10px 14px;margin-bottom:10px;font-weight:700;}
</style>
@endpush

@section('content')
@php
  $plan = strtoupper((string)($cuenta->plan_actual ?? 'FREE' ?? auth('web')->user()?->cuenta?->plan_actual));
  $estatus = strtolower((string)($cfdi->estatus ?? 'borrador'));
  $isDraft = in_array($estatus,['borrador','pendiente','nuevo']);
  $isIssued = in_array($estatus,['emitido','pagado','pagada']);
  $isVoid = $estatus==='cancelado';
  $serieFolio = trim(($cfdi->serie?($cfdi->serie.'-'):'').($cfdi->folio??''),'- ') ?: '—';
  $uuid=(string)($cfdi->uuid??'');
  $subtotal=(float)($cfdi->subtotal??0);
  $iva=(float)($cfdi->iva??$cfdi->impuestos_trasladados??0);
  $total=(float)($cfdi->total??0);
@endphp

{{-- ===== Banner dinámico ===== --}}
<div class="status-banner {{ $estatus }}">
  <div>
    @if($isDraft)
      ✍️ <strong>Documento en Borrador</strong>
      <small>(Pendiente de timbrar)</small>
    @elseif($isIssued)
      ✅ <strong>Documento Timbrado</strong>
      <small>(Emitido correctamente)</small>
    @elseif($isVoid)
      ❌ <strong>Documento Cancelado</strong>
      <small>(Inhabilitado ante el SAT)</small>
    @else
      📄 <strong>Estatus: {{ strtoupper($estatus) }}</strong>
    @endif
  </div>
  @if($uuid)
    <div style="text-align:right;">
      <small>UUID:</small> <span style="font-family:ui-monospace">{{ $uuid }}</span>
    </div>
  @endif
</div>

{{-- ===== Header general ===== --}}
<div class="head">
  <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
    <a href="{{ route('cliente.facturacion.index', request()->only(['q','status','mes','anio','month'])) }}" class="btnx ghost">← Volver</a>
    <h1>CFDI <span class="uuid">({{ $serieFolio }})</span></h1>
  </div>

  <div class="tools">
    <a class="btnx {{ $isIssued?'':'disabled' }}" target="{{ $isIssued?'_blank':'_self' }}"
       href="{{ $isIssued?route('cliente.facturacion.ver_pdf',$cfdi->id):'#' }}">Ver PDF</a>
    <a class="btnx {{ $isIssued?'':'disabled' }}"
       href="{{ $isIssued?route('cliente.facturacion.descargar_xml',$cfdi->id):'#' }}">Descargar XML</a>

    @if($plan==='PRO')
      <form method="POST" action="{{ route('cliente.facturacion.duplicar',$cfdi->id) }}" style="display:inline">@csrf
        <button type="submit" class="btnx ghost">Duplicar</button>
      </form>
    @else
      <button class="btnx disabled" title="Disponible en PRO">Duplicar</button>
    @endif

    @if($isDraft)
      @if($plan==='PRO')
        <form method="POST" action="{{ route('cliente.facturacion.timbrar',$cfdi->id) }}" style="display:inline">@csrf
          <button type="submit" class="btnx primary">Timbrar</button>
        </form>
      @else
        <button class="btnx disabled" title="Para timbrar, actualiza a PRO">Timbrar</button>
      @endif
    @endif

    @if($isIssued && !$isVoid)
      <form method="POST" action="{{ route('cliente.facturacion.cancelar',$cfdi->id) }}" style="display:inline" onsubmit="return confirm('¿Cancelar este CFDI?');">@csrf
        <button type="submit" class="btnx danger">Cancelar</button>
      </form>
    @endif
  </div>
</div>

@if(session('ok'))<div class="alert-ok"><strong>{{ session('ok') }}</strong></div>@endif
@if($errors->any())<div class="alert-err"><strong>{{ $errors->first() }}</strong></div>@endif

{{-- ===== Secciones del CFDI ===== --}}
<div class="grid-2">
  <div class="card">
    <h3>Emisor</h3>
    <div class="field"><span class="lbl">Nombre/Razón social</span><span class="val">{{ optional($cfdi->emisor)->razon_social ?? '—' }}</span></div>
    <div class="field"><span class="lbl">RFC</span><span class="val">{{ optional($cfdi->emisor)->rfc ?? '—' }}</span></div>
    <div class="field"><span class="lbl">Serie / Folio</span><span class="val">{{ $serieFolio }}</span></div>
    <div class="field"><span class="lbl">Fecha</span><span class="val">{{ optional($cfdi->fecha)->format('Y-m-d H:i') ?? '—' }}</span></div>
  </div>

  <div class="card">
    <h3>Receptor</h3>
    <div class="field"><span class="lbl">Razón social</span><span class="val">{{ optional($cfdi->receptor)->razon_social ?? '—' }}</span></div>
    <div class="field"><span class="lbl">RFC</span><span class="val">{{ optional($cfdi->receptor)->rfc ?? '—' }}</span></div>
    <div class="field"><span class="lbl">Uso CFDI</span><span class="val">{{ optional($cfdi->receptor)->uso_cfdi ?? ($cfdi->uso_cfdi ?? 'G03') }}</span></div>
  </div>
</div>

<div class="grid-2" style="margin-top:12px">
  <div class="card">
    <h3>Pago</h3>
    <div class="field"><span class="lbl">Moneda</span><span class="val">{{ $cfdi->moneda ?? 'MXN' }}</span></div>
    <div class="field"><span class="lbl">Forma de pago</span><span class="val">{{ $cfdi->forma_pago ?? '—' }}</span></div>
    <div class="field"><span class="lbl">Método de pago</span><span class="val">{{ $cfdi->metodo_pago ?? '—' }}</span></div>
  </div>

  <div class="card">
    <h3>Importes</h3>
    <div class="field"><span class="lbl">Subtotal</span><span class="val">${{ number_format($subtotal,2) }}</span></div>
    <div class="field"><span class="lbl">IVA</span><span class="val">${{ number_format($iva,2) }}</span></div>
    <div class="field"><span class="lbl">Total</span><span class="val" style="color:#E11D48">${{ number_format($total,2) }}</span></div>
  </div>
</div>

<div class="card" style="margin-top:12px">
  <h3>Conceptos</h3>
  <div style="overflow:auto">
    <table class="items">
      <thead><tr><th>Descripción</th><th class="right">Cantidad</th><th class="right">Precio</th><th class="right">IVA</th><th class="right">Subtotal</th><th class="right">Total</th></tr></thead>
      <tbody>
        @forelse($cfdi->conceptos ?? [] as $it)
          @php
            $cant=(float)($it->cantidad??0);
            $precio=(float)($it->precio_unitario??$it->valor_unitario??0);
            $sub=(float)($it->subtotal??($it->importe??$cant*$precio));
            $ivaRow=(float)($it->iva??$it->impuestos_trasladados??($sub*($it->iva_tasa??0.16)));
            $totalRow=(float)($it->total??$sub+$ivaRow);
          @endphp
          <tr>
            <td>{{ $it->descripcion??'—' }}</td>
            <td class="right">{{ rtrim(rtrim(number_format($cant,4),'0'),'.') }}</td>
            <td class="right">${{ number_format($precio,2) }}</td>
            <td class="right">{{ number_format(($it->iva_tasa??0.16)*100,2) }}%</td>
            <td class="right">${{ number_format($sub,2) }}</td>
            <td class="right">${{ number_format($totalRow,2) }}</td>
          </tr>
        @empty
          <tr><td colspan="6" style="padding:12px;color:#6b7280">Sin conceptos.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="totals">
    <div>Subtotal: ${{ number_format($subtotal,2) }}</div>
    <div>IVA: ${{ number_format($iva,2) }}</div>
    <div>Total: ${{ number_format($total,2) }}</div>
  </div>
</div>

@if($isDraft && $plan!=='PRO')
  <div class="card" style="margin-top:12px;background:#fff0f3;">
    <strong>¿Necesitas timbrar o duplicar?</strong>
    Actualiza a <a href="{{ route('cliente.registro.pro') }}" style="font-weight:900;text-decoration:underline;color:#E11D48;">PRO</a>.
  </div>
@endif
@endsection

@push('scripts')
<script>
(function(){
  const btn=document.getElementById('copyUuid');
  if(!btn)return;
  btn.addEventListener('click',async()=>{
    try{
      const txt=(document.getElementById('uuidText')?.innerText||'').trim();
      if(!txt)return;
      await navigator.clipboard.writeText(txt);
      const old=btn.textContent;btn.textContent='Copiado';
      setTimeout(()=>btn.textContent=old,1200);
    }catch(e){}
  });
})();
</script>
@endpush
