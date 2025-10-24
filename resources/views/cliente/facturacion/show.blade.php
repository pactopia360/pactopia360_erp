{{-- resources/views/cliente/facturacion/show.blade.php (v2 · Unificado y alineado al listado) --}}
@extends('layouts.client')
@section('title', 'Detalle CFDI · Pactopia360')

@push('styles')
<style>
  .head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px;flex-wrap:wrap}
  .head h1{margin:0;font:900 20px/1.1 ui-sans-serif,system-ui}
  .uuid{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12px}
  .tools{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
  .btnx{display:inline-flex;align-items:center;gap:8px;padding:10px 12px;border-radius:12px;border:1px solid var(--border);font-weight:900;text-decoration:none}
  .btnx.primary{background:linear-gradient(180deg,var(--accent),color-mix(in oklab,var(--accent) 85%, black));color:#fff;border:0}
  .btnx.warn{background: color-mix(in oklab, var(--warn) 20%, transparent)}
  .btnx.danger{background: color-mix(in oklab, var(--err) 20%, transparent)}
  .btnx.ghost{background:transparent}
  .btnx.disabled{opacity:.55;pointer-events:none}

  .badge{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;border:1px solid var(--border);font-weight:900;font-size:12px}
  .badge.borrador{background: color-mix(in oklab, var(--accent) 14%, transparent)}
  .badge.emitido{background: color-mix(in oklab, #16a34a 20%, transparent); border-color: color-mix(in oklab, #16a34a 40%, transparent)}
  .badge.cancelado{background: color-mix(in oklab, #ef4444 18%, transparent); border-color: color-mix(in oklab, #ef4444 38%, transparent)}

  .grid-2{display:grid;gap:12px}
  @media (min-width:1080px){ .grid-2{grid-template-columns:1fr 1fr} }

  .card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:14px;box-shadow:var(--shadow)}
  .card h3{margin:0 0 10px;font-size:12px;color:var(--muted);letter-spacing:.2px;text-transform:uppercase;font-weight:900}

  .field{display:grid;gap:4px;margin-bottom:10px}
  .lbl{font-weight:800;color:var(--muted);font-size:12px}
  .val{font-weight:900}

  table.items{width:100%;border-collapse:separate;border-spacing:0}
  table.items th, table.items td{padding:10px;border-bottom:1px solid var(--border);text-align:left;font-size:14px}
  .right{text-align:right}
  .totals{display:flex;align-items:center;gap:12px;justify-content:flex-end;font-weight:900}

  .alert-ok{background:color-mix(in oklab,var(--ok) 22%, transparent);border:1px solid color-mix(in oklab,var(--ok) 44%, transparent)}
  .alert-err{background:color-mix(in oklab,var(--err) 18%, transparent);border:1px solid color-mix(in oklab,var(--err) 38%, transparent)}
</style>
@endpush

@section('content')
@php
  /** @var \App\Models\Cfdi $cfdi */
  $u    = auth('web')->user();
  $c    = $u?->cuenta;
  $plan = strtoupper((string)($c->plan_actual ?? 'FREE')); // FREE | PRO

  $estatus = strtolower((string)($cfdi->estatus ?? 'borrador'));
  $isDraft  = $estatus === 'borrador' || $estatus === 'pendiente' || $estatus === 'nuevo';
  $isIssued = $estatus === 'emitido' || $estatus === 'pagada' || $estatus === 'pagado';
  $isVoid   = $estatus === 'cancelado';

  $serieFolio = trim(($cfdi->serie ? ($cfdi->serie.'-') : '').($cfdi->folio ?? ''), '- ') ?: '—';
  $uuid = (string)($cfdi->uuid ?? '');

  $subtotal = (float)($cfdi->subtotal ?? 0);
  $iva      = (float)($cfdi->iva ?? $cfdi->impuestos_trasladados ?? 0);
  $total    = (float)($cfdi->total ?? 0);
@endphp

<div class="head">
  <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
    <a href="{{ route('cliente.facturacion.index', request()->only(['q','status','mes','anio','month'])) }}" class="btnx ghost">← Volver</a>
    <h1>CFDI <span class="uuid">({{ $serieFolio }})</span></h1>
    <span class="badge {{ $estatus }}">{{ strtoupper($estatus) }}</span>
    @if($uuid)
      <span class="uuid" id="uuidText">{{ $uuid }}</span>
      <button type="button" class="btnx" id="copyUuid" style="padding:6px 10px;height:auto">Copiar UUID</button>
    @endif
  </div>

  <div class="tools">
    {{-- Ver / Descargar (sólo cuando está timbrado) --}}
    <a class="btnx {{ $isIssued ? '' : 'disabled' }}" target="{{ $isIssued ? '_blank' : '_self' }}"
       href="{{ $isIssued ? route('cliente.facturacion.ver_pdf', $cfdi->id) : '#' }}">Ver PDF</a>
    <a class="btnx {{ $isIssued ? '' : 'disabled' }}"
       href="{{ $isIssued ? route('cliente.facturacion.descargar_xml', $cfdi->id) : '#' }}">Descargar XML</a>

    {{-- Duplicar (sólo PRO) --}}
    @if ($plan === 'PRO')
      <form method="POST" action="{{ route('cliente.facturacion.duplicar', $cfdi->id) }}" style="display:inline">
        @csrf
        <button type="submit" class="btnx">Duplicar</button>
      </form>
    @else
      <button class="btnx disabled" title="Disponible en PRO">Duplicar</button>
    @endif

    {{-- Timbrar (si es borrador y PRO) --}}
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

    {{-- Cancelar (si emitido) --}}
    @if ($isIssued && !$isVoid)
      <form method="POST" action="{{ route('cliente.facturacion.cancelar', $cfdi->id) }}" style="display:inline" onsubmit="return confirm('¿Cancelar este CFDI?');">
        @csrf
        <button type="submit" class="btnx danger">Cancelar</button>
      </form>
    @endif
  </div>
</div>

{{-- Mensajes --}}
@if (session('ok'))
  <div class="card alert-ok"><strong>{{ session('ok') }}</strong></div>
@endif
@if ($errors->any())
  <div class="card alert-err"><strong>{{ $errors->first() }}</strong></div>
@endif

<div class="grid-2">
  <div class="card">
    <h3>Emisor</h3>
    <div class="field">
      <span class="lbl">Nombre/Razón social</span>
      <span class="val">{{ optional($cfdi->emisor)->nombre_comercial ?? optional($cfdi->emisor)->razon_social ?? '—' }}</span>
    </div>
    <div class="field">
      <span class="lbl">RFC</span>
      <span class="val">{{ optional($cfdi->emisor)->rfc ?? '—' }}</span>
    </div>
    <div class="field">
      <span class="lbl">Serie / Folio</span>
      <span class="val">{{ $serieFolio }}</span>
    </div>
    <div class="field">
      <span class="lbl">Fecha</span>
      <span class="val">{{ optional($cfdi->fecha)->format('Y-m-d H:i') ?? '—' }}</span>
    </div>
  </div>

  <div class="card">
    <h3>Receptor</h3>
    <div class="field">
      <span class="lbl">Razón social</span>
      <span class="val">{{ optional($cfdi->receptor)->razon_social ?? optional($cfdi->receptor)->nombre_comercial ?? '—' }}</span>
    </div>
    <div class="field">
      <span class="lbl">RFC</span>
      <span class="val">{{ optional($cfdi->receptor)->rfc ?? '—' }}</span>
    </div>
    <div class="field">
      <span class="lbl">Uso CFDI</span>
      <span class="val">{{ optional($cfdi->receptor)->uso_cfdi ?? ($cfdi->uso_cfdi ?? 'G03') }}</span>
    </div>
  </div>
</div>

<div class="grid-2" style="margin-top:12px">
  <div class="card">
    <h3>Pago</h3>
    <div class="field">
      <span class="lbl">Moneda</span>
      <span class="val">{{ $cfdi->moneda ?? 'MXN' }}</span>
    </div>
    <div class="field">
      <span class="lbl">Forma de pago</span>
      <span class="val">{{ $cfdi->forma_pago ?? '—' }}</span>
    </div>
    <div class="field">
      <span class="lbl">Método de pago</span>
      <span class="val">{{ $cfdi->metodo_pago ?? '—' }}</span>
    </div>
    <div class="field">
      <span class="lbl">Condiciones de pago</span>
      <span class="val">{{ $cfdi->condiciones_pago ?? '—' }}</span>
    </div>
  </div>

  <div class="card">
    <h3>Importes</h3>
    <div class="field">
      <span class="lbl">Subtotal</span>
      <span class="val">${{ number_format($subtotal,2) }}</span>
    </div>
    <div class="field">
      <span class="lbl">IVA</span>
      <span class="val">${{ number_format($iva,2) }}</span>
    </div>
    <div class="field">
      <span class="lbl">Total</span>
      <span class="val">${{ number_format($total,2) }}</span>
    </div>
  </div>
</div>

<div class="card" style="margin-top:12px">
  <h3>Conceptos</h3>
  <div style="overflow:auto">
    <table class="items">
      <thead>
        <tr>
          <th>Descripción</th>
          <th class="right">Cantidad</th>
          <th class="right">Precio</th>
          <th class="right">IVA</th>
          <th class="right">Subtotal</th>
          <th class="right">Total</th>
        </tr>
      </thead>
      <tbody>
        @forelse(($cfdi->conceptos ?? []) as $it)
          @php
            $cant = (float)($it->cantidad ?? 0);
            $precio = (float)($it->precio_unitario ?? $it->valor_unitario ?? 0);
            $sub = (float)($it->subtotal ?? ($it->importe ?? $cant * $precio));
            $ivaRow = (float)($it->iva ?? $it->impuestos_trasladados ?? ($sub * (($it->iva_tasa ?? 0.16))));
            $totalRow = (float)($it->total ?? $sub + $ivaRow);
          @endphp
          <tr>
            <td>{{ $it->descripcion ?? '—' }}</td>
            <td class="right">{{ rtrim(rtrim(number_format($cant, 4), '0'), '.') }}</td>
            <td class="right">${{ number_format($precio, 2) }}</td>
            <td class="right">{{ number_format((float)($it->iva_tasa ?? 0.16) * 100, 2) }}%</td>
            <td class="right">${{ number_format($sub, 2) }}</td>
            <td class="right">${{ number_format($totalRow, 2) }}</td>
          </tr>
        @empty
          <tr><td colspan="6" style="padding:12px;color:var(--muted)">Sin conceptos.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="totals" style="margin-top:10px">
    <div>Subtotal: ${{ number_format($subtotal, 2) }}</div>
    <div>IVA: ${{ number_format($iva, 2) }}</div>
    <div>Total: ${{ number_format($total, 2) }}</div>
  </div>
</div>

@if ($isDraft && $plan !== 'PRO')
  <div class="card" style="margin-top:12px;background:color-mix(in oklab, var(--accent) 14%, transparent)">
    <strong>¿Necesitas timbrar o duplicar?</strong> Actualiza a
    <a href="{{ route('cliente.registro.pro') }}" style="font-weight:900;text-decoration:underline">PRO</a>.
  </div>
@endif
@endsection

@push('scripts')
<script>
  (function(){
    var btn = document.getElementById('copyUuid');
    if(!btn) return;
    btn.addEventListener('click', async function(){
      try{
        var txt = (document.getElementById('uuidText')?.innerText || '').trim();
        if(!txt) return;
        await navigator.clipboard.writeText(txt);
        var old = btn.textContent; btn.textContent = 'Copiado';
        setTimeout(function(){ btn.textContent = old; }, 1200);
      }catch(e){}
    });
  })();
</script>
@endpush
