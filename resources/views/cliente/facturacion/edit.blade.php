{{-- resources/views/cliente/facturacion/edit.blade.php --}}
@extends('layouts.cliente')

@section('title','Editar CFDI · Pactopia360')

@push('styles')
<style>
.fx360-edit-page{display:flex;flex-direction:column;gap:14px}
.fx360-edit-hero{
    border-radius:22px;padding:22px;background:radial-gradient(circle at 88% 16%,rgba(104,154,255,.45),transparent 32%),linear-gradient(135deg,#173b78 0%,#1c4ca3 48%,#5f8df0 100%);
    color:#fff;box-shadow:0 18px 38px rgba(20,55,120,.20);border:1px solid rgba(255,255,255,.16)
}
.fx360-edit-hero__top{display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;align-items:flex-start}
.fx360-kicker{display:inline-flex;align-items:center;gap:8px;padding:7px 11px;border-radius:999px;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.18);font-size:11px;font-weight:950;text-transform:uppercase;letter-spacing:.08em}
.fx360-title{margin:14px 0 8px;font-size:36px;line-height:1;font-weight:950;letter-spacing:-.05em}
.fx360-subtitle{max-width:760px;margin:0;color:rgba(255,255,255,.86);font-size:13px;font-weight:750;line-height:1.6}
.fx360-status{min-width:260px;border-radius:18px;padding:14px;background:rgba(255,255,255,.13);border:1px solid rgba(255,255,255,.20);box-shadow:0 18px 34px rgba(10,24,64,.16)}
.fx360-status__label{font-size:10px;font-weight:950;text-transform:uppercase;letter-spacing:.12em;color:rgba(255,255,255,.72)}
.fx360-status__value{margin-top:7px;font-size:18px;font-weight:950;color:#fff}
.fx360-status__uuid{margin-top:8px;font-size:11px;line-height:1.45;color:rgba(255,255,255,.78);word-break:break-all;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace}

.fx360-actions{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;padding:14px;border-radius:18px;background:#fff;border:1px solid #e3ebf7;box-shadow:0 10px 24px rgba(15,34,64,.045)}
.fx360-actions__group{display:flex;gap:8px;flex-wrap:wrap;align-items:center}

.fx360-btn{height:38px;padding:0 13px;border-radius:13px;border:1px solid #d8e7ff;background:linear-gradient(180deg,#fff 0%,#f7fbff 100%);color:#2458cf;display:inline-flex;align-items:center;justify-content:center;gap:8px;font-size:12px;font-weight:950;text-decoration:none;cursor:pointer;transition:.16s ease}
.fx360-btn:hover{transform:translateY(-1px);border-color:#9fc1ff;background:#eef5ff;box-shadow:0 10px 20px rgba(37,88,207,.12)}
.fx360-btn--primary{border:0;color:#fff;background:linear-gradient(135deg,#2458cf,#2f6bff);box-shadow:0 10px 22px rgba(37,88,207,.18)}
.fx360-btn--success{color:#0f8a4b;border-color:#c7f0db;background:linear-gradient(180deg,#fff 0%,#f2fff8 100%)}
.fx360-btn--disabled{opacity:.45;pointer-events:none;filter:grayscale(.35)}

.fx360-card{border-radius:18px;border:1px solid #e3ebf7;background:linear-gradient(180deg,#fff 0%,#fbfdff 100%);box-shadow:0 10px 24px rgba(15,34,64,.045);padding:16px}
.fx360-card__head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:14px}
.fx360-card__title{margin:0;color:#10233f;font-size:14px;font-weight:950;letter-spacing:-.02em}
.fx360-tag{display:inline-flex;align-items:center;justify-content:center;padding:5px 9px;border-radius:999px;background:#eef5ff;color:#2458cf;border:1px solid #d8e7ff;font-size:10px;font-weight:950;text-transform:uppercase;letter-spacing:.08em}

.fx360-grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.fx360-grid-3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
.fx360-field{display:grid;gap:6px;margin-bottom:11px}
.fx360-label{color:#71829a;font-size:10px;font-weight:950;line-height:1.2;letter-spacing:.12em;text-transform:uppercase}
.fx360-input,.fx360-select,.fx360-number,.fx360-textarea{
    width:100%;min-height:42px;border:1px solid #dbe6f4;border-radius:13px;background:#fff;color:#132238;padding:0 12px;font-size:13px;font-weight:750;outline:none
}
.fx360-textarea{padding:10px 12px;resize:vertical;min-height:70px}
.fx360-input:focus,.fx360-select:focus,.fx360-number:focus,.fx360-textarea:focus{border-color:#8db8ff;box-shadow:0 0 0 4px rgba(37,99,235,.10)}

.fx360-table-wrap{overflow:auto;border-radius:16px;border:1px solid #e3ebf7;background:#fff}
.fx360-table{width:100%;min-width:1040px;border-collapse:collapse}
.fx360-table th{background:#f5f8fd;color:#58708f;font-size:10px;font-weight:950;text-transform:uppercase;letter-spacing:.08em;text-align:left;padding:12px;border-bottom:1px solid #e3ebf7}
.fx360-table td{color:#10233f;font-size:12px;font-weight:750;padding:12px;border-bottom:1px solid #eef3fa;vertical-align:top}
.fx360-table tr:last-child td{border-bottom:0}
.fx360-right{text-align:right!important}
.fx360-remove{width:34px;height:34px;border-radius:12px;border:1px solid #ffd6d6;background:#fff6f6;color:#dc2626;font-weight:950;cursor:pointer}

.fx360-summary{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center;margin-top:12px}
.fx360-preview{border-radius:14px;border:1px solid #d8e7ff;background:#f7fbff;color:#2458cf;padding:10px 12px;font-size:12px;font-weight:900}
.fx360-total{font-size:24px;font-weight:950;color:#2458cf;letter-spacing:-.04em}

.fx360-ai-grid{display:grid;grid-template-columns:1.25fr .75fr;gap:12px}
.fx360-ai-box{border-radius:16px;padding:14px;background:linear-gradient(135deg,#f7fbff,#eef5ff);border:1px solid #d8e7ff}
.fx360-ai-title{margin:0 0 8px;font-size:13px;font-weight:950;color:#10233f}
.fx360-ai-text{margin:0;color:#58708f;font-size:12px;font-weight:750;line-height:1.55}
.fx360-checklist{display:grid;gap:9px}
.fx360-check{display:flex;align-items:flex-start;gap:8px;color:#10233f;font-size:12px;font-weight:800;line-height:1.4}
.fx360-check span{width:18px;height:18px;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;flex:0 0 auto;margin-top:1px;background:#ecfdf5;color:#0f8a4b;font-size:11px;font-weight:950}

.fx360-alert{border-radius:16px;padding:13px 14px;font-size:12px;font-weight:850;line-height:1.5;border:1px solid #d8e7ff;background:#f7fbff;color:#334861}
.fx360-alert--ok{background:#ecfdf5;border-color:#86efac;color:#047857}
.fx360-alert--err{background:#fef2f2;border-color:#fecaca;color:#b91c1c}

@media(max-width:1180px){
    .fx360-grid-2,.fx360-grid-3,.fx360-ai-grid{grid-template-columns:1fr}
}
@media(max-width:680px){
    .fx360-title{font-size:29px}
    .fx360-actions,.fx360-actions__group{align-items:stretch;width:100%}
    .fx360-btn{width:100%}
}
</style>
@endpush

@section('content')
@php
    $u = auth('web')->user();
    $c = $u?->cuenta;

    $plan = strtoupper((string)($c->plan_actual ?? $c->tipo_cuenta ?? $c->plan ?? 'FREE'));
    $estatus = strtolower((string)($cfdi->estatus ?? 'borrador'));
    $isDraft = $estatus === 'borrador';

    $uuid = (string)($cfdi->uuid ?? '');
    $serieFolio = trim((($cfdi->serie ?? '') ? ($cfdi->serie.'-') : '').($cfdi->folio ?? ''), '- ') ?: 'Sin serie / folio';

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

    $subtotal = (float)($cfdi->subtotal ?? 0);
    $iva = (float)($cfdi->iva ?? 0);
    $total = (float)($cfdi->total ?? 0);
@endphp

<div class="fx360-edit-page">

    <section class="fx360-edit-hero">
        <div class="fx360-edit-hero__top">
            <div>
                <div class="fx360-kicker">CFDI · Edición fiscal</div>
                <h1 class="fx360-title">Editar CFDI</h1>
                <p class="fx360-subtitle">
                    Ajusta emisor, receptor, conceptos, impuestos y pago antes de timbrar.
                    El flujo queda preparado para validaciones IA, revisión fiscal-contable y timbrado PAC.
                </p>
            </div>

            <div class="fx360-status">
                <div class="fx360-status__label">Estado del documento</div>
                <div class="fx360-status__value">{{ $isDraft ? 'Borrador editable' : strtoupper($estatus) }}</div>
                <div class="fx360-status__uuid">UUID: {{ $uuid ?: 'Pendiente de timbrar' }}</div>
            </div>
        </div>
    </section>

    <section class="fx360-actions">
        <div class="fx360-actions__group">
            <a href="{{ route('cliente.facturacion.index', request()->only(['q','status','mes','anio','month'])) }}" class="fx360-btn">
                ← Volver
            </a>

            @if(Route::has('cliente.facturacion.show'))
                <a href="{{ route('cliente.facturacion.show', $cfdi->id) }}" class="fx360-btn">
                    Ver detalle
                </a>
            @endif
        </div>

        <div class="fx360-actions__group">
            @if(Route::has('cliente.facturacion.duplicar'))
                @if($plan === 'PRO')
                    <form method="POST" action="{{ route('cliente.facturacion.duplicar', $cfdi->id) }}">
                        @csrf
                        <button type="submit" class="fx360-btn">Duplicar</button>
                    </form>
                @else
                    <button type="button" class="fx360-btn fx360-btn--disabled">Duplicar</button>
                @endif
            @endif

            @if($isDraft && Route::has('cliente.facturacion.timbrar'))
                @if($plan === 'PRO')
                    <form method="POST" action="{{ route('cliente.facturacion.timbrar', $cfdi->id) }}">
                        @csrf
                        <button type="submit" class="fx360-btn fx360-btn--primary">Timbrar</button>
                    </form>
                @else
                    <button type="button" class="fx360-btn fx360-btn--disabled">Timbrar</button>
                @endif
            @endif
        </div>
    </section>

    @if(session('ok'))
        <div class="fx360-alert fx360-alert--ok">{{ session('ok') }}</div>
    @endif

    @if($errors->any())
        <div class="fx360-alert fx360-alert--err">{{ $errors->first() }}</div>
    @endif

    <section class="fx360-ai-grid">
        <div class="fx360-card">
            <div class="fx360-card__head">
                <h2 class="fx360-card__title">Asistente IA fiscal-contable</h2>
                <span class="fx360-tag">IA</span>
            </div>
            <div class="fx360-ai-box">
                <h3 class="fx360-ai-title">Revisión previa al timbrado</h3>
                <p class="fx360-ai-text">
                    Valida RFC, uso CFDI, forma/método de pago, conceptos, tasa de IVA y totales.
                    Este bloque queda listo para conectar recomendaciones IA reales y alertas fiscales automáticas.
                </p>
            </div>
        </div>

        <div class="fx360-card">
            <div class="fx360-card__head">
                <h2 class="fx360-card__title">Checklist SAT</h2>
                <span class="fx360-tag">Fiscal</span>
            </div>
            <div class="fx360-checklist">
                <div class="fx360-check"><span>✓</span> Receptor seleccionado</div>
                <div class="fx360-check"><span>✓</span> Conceptos editables</div>
                <div class="fx360-check"><span>✓</span> Impuestos recalculados</div>
                <div class="fx360-check"><span>✓</span> Total listo para timbrado</div>
            </div>
        </div>
    </section>

    <form method="POST" action="{{ route('cliente.facturacion.actualizar', $cfdi->id) }}" id="editForm">
        @csrf
        @method('PUT')

        <section class="fx360-grid-2">
            <div class="fx360-card">
                <div class="fx360-card__head">
                    <h2 class="fx360-card__title">Emisor</h2>
                    <span class="fx360-tag">Empresa</span>
                </div>

                <div class="fx360-field">
                    <span class="fx360-label">Empresa / Emisor</span>
                    <select name="cliente_id" class="fx360-select" required {{ $isDraft ? '' : 'disabled' }}>
                        @foreach(($emisores ?? []) as $e)
                            <option value="{{ $e->id }}" {{ (int)$e->id === (int)$cfdi->cliente_id ? 'selected' : '' }}>
                                {{ $e->nombre_comercial ?? $e->razon_social ?? ('#'.$e->id) }} — {{ $e->rfc }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="fx360-grid-2">
                    <div class="fx360-field">
                        <span class="fx360-label">Serie</span>
                        <input type="text" name="serie" class="fx360-input" maxlength="10" value="{{ $cfdi->serie }}" {{ $isDraft ? '' : 'readonly' }}>
                    </div>
                    <div class="fx360-field">
                        <span class="fx360-label">Folio</span>
                        <input type="text" name="folio" class="fx360-input" maxlength="20" value="{{ $cfdi->folio }}" {{ $isDraft ? '' : 'readonly' }}>
                    </div>
                </div>

                <div class="fx360-grid-2">
                    <div class="fx360-field">
                        <span class="fx360-label">Fecha</span>
                        <input type="datetime-local" name="fecha" class="fx360-input" value="{{ optional($cfdi->fecha)->format('Y-m-d\TH:i') }}" {{ $isDraft ? '' : 'readonly' }}>
                    </div>
                    <div class="fx360-field">
                        <span class="fx360-label">Moneda</span>
                        <input type="text" name="moneda" class="fx360-input" maxlength="10" value="{{ $cfdi->moneda ?? 'MXN' }}" {{ $isDraft ? '' : 'readonly' }}>
                    </div>
                </div>
            </div>

            <div class="fx360-card">
                <div class="fx360-card__head">
                    <h2 class="fx360-card__title">Receptor</h2>
                    <span class="fx360-tag">Cliente</span>
                </div>

                <div class="fx360-field">
                    <span class="fx360-label">Receptor</span>
                    <select name="receptor_id" class="fx360-select" required {{ $isDraft ? '' : 'disabled' }}>
                        @foreach(($receptores ?? []) as $r)
                            <option value="{{ $r->id }}" {{ (int)$r->id === (int)$cfdi->receptor_id ? 'selected' : '' }}>
                                {{ $r->razon_social ?? $r->nombre_comercial ?? ('#'.$r->id) }} — {{ $r->rfc }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="fx360-grid-2">
                    <div class="fx360-field">
                        <span class="fx360-label">Uso CFDI</span>
                        <input type="text" class="fx360-input" value="{{ optional($cfdi->receptor)->uso_cfdi ?? ($cfdi->uso_cfdi ?? 'G03') }}" readonly>
                    </div>
                    <div class="fx360-field">
                        <span class="fx360-label">UUID</span>
                        <input type="text" class="fx360-input" value="{{ $uuid ?: 'Pendiente de timbrar' }}" readonly>
                    </div>
                </div>

                <div class="fx360-field">
                    <span class="fx360-label">Serie / Folio actual</span>
                    <input type="text" class="fx360-input" value="{{ $serieFolio }}" readonly>
                </div>
            </div>
        </section>

        <section class="fx360-card">
            <div class="fx360-card__head">
                <h2 class="fx360-card__title">Conceptos / Productos</h2>
                <span class="fx360-tag">Partidas CFDI</span>
            </div>

            <div class="fx360-table-wrap">
                <table class="fx360-table" id="itemsTable">
                    <thead>
                        <tr>
                            <th style="width:30%">Descripción</th>
                            <th>Producto</th>
                            <th class="fx360-right">Cant.</th>
                            <th class="fx360-right">Precio</th>
                            <th class="fx360-right">IVA</th>
                            <th class="fx360-right">Subtotal</th>
                            <th class="fx360-right">Total</th>
                            @if($isDraft)<th></th>@endif
                        </tr>
                    </thead>
                    <tbody id="itemsBody">
                        @php $idx = 0; @endphp
                        @foreach(($cfdi->conceptos ?? []) as $it)
                            <tr data-idx="{{ $idx }}">
                                <td>
                                    <textarea class="fx360-textarea" name="conceptos[{{ $idx }}][descripcion]" rows="2" {{ $isDraft ? '' : 'readonly' }}>{{ $it->descripcion }}</textarea>
                                </td>
                                <td>
                                    <select class="fx360-select" name="conceptos[{{ $idx }}][producto_id]" {{ $isDraft ? '' : 'disabled' }}>
                                        <option value="">—</option>
                                        @foreach(($productos ?? []) as $p)
                                            <option value="{{ $p->id }}" {{ (int)$p->id === (int)($it->producto_id ?? 0) ? 'selected' : '' }}>
                                                {{ ($p->sku ? $p->sku.' - ' : '') . $p->descripcion }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="fx360-right">
                                    <input type="number" step="0.0001" min="0.0001" class="fx360-number" name="conceptos[{{ $idx }}][cantidad]" value="{{ (float)$it->cantidad }}" oninput="recalc()" {{ $isDraft ? '' : 'readonly' }}>
                                </td>
                                <td class="fx360-right">
                                    <input type="number" step="0.0001" min="0" class="fx360-number" name="conceptos[{{ $idx }}][precio_unitario]" value="{{ (float)$it->precio_unitario }}" oninput="recalc()" {{ $isDraft ? '' : 'readonly' }}>
                                </td>
                                <td class="fx360-right">
                                    <input type="number" step="0.0001" min="0" class="fx360-number" name="conceptos[{{ $idx }}][iva_tasa]" value="{{ (float)($it->iva_tasa ?? 0.16) }}" oninput="recalc()" {{ $isDraft ? '' : 'readonly' }}>
                                </td>
                                <td class="fx360-right subtotal">${{ number_format((float)($it->subtotal ?? 0),2) }}</td>
                                <td class="fx360-right total">${{ number_format((float)($it->total ?? 0),2) }}</td>
                                @if($isDraft)
                                    <td>
                                        <button type="button" class="fx360-remove" onclick="removeItem({{ $idx }})">×</button>
                                    </td>
                                @endif
                            </tr>
                            @php $idx++; @endphp
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($isDraft)
                <div class="fx360-summary">
                    <div class="fx360-actions__group">
                        <button type="button" class="fx360-btn" onclick="addItem()">+ Agregar concepto</button>
                        <div class="fx360-preview" id="calcPreview">
                            Subtotal: ${{ number_format($subtotal, 2) }} · IVA: ${{ number_format($iva, 2) }} · Total: ${{ number_format($total, 2) }}
                        </div>
                    </div>
                </div>
            @endif
        </section>

        <section class="fx360-grid-2">
            <div class="fx360-card">
                <div class="fx360-card__head">
                    <h2 class="fx360-card__title">Pago</h2>
                    <span class="fx360-tag">Cobranza</span>
                </div>

                <div class="fx360-grid-2">
                    <div class="fx360-field">
                        <span class="fx360-label">Forma de pago</span>
                        <input type="text" name="forma_pago" class="fx360-input" value="{{ $cfdi->forma_pago }}" {{ $isDraft ? '' : 'readonly' }}>
                    </div>

                    <div class="fx360-field">
                        <span class="fx360-label">Método de pago</span>
                        <input type="text" name="metodo_pago" class="fx360-input" value="{{ $cfdi->metodo_pago }}" {{ $isDraft ? '' : 'readonly' }}>
                    </div>
                </div>
            </div>

            <div class="fx360-card">
                <div class="fx360-card__head">
                    <h2 class="fx360-card__title">Resumen contable</h2>
                    <span class="fx360-tag">Fiscal contable</span>
                </div>

                <div class="fx360-summary">
                    <div>
                        <div class="fx360-label">Total CFDI</div>
                        <div class="fx360-total" id="totalLabel">${{ number_format($total, 2) }}</div>
                    </div>

                    <button type="submit" class="fx360-btn fx360-btn--primary" {{ $isDraft ? '' : 'disabled' }}>
                        Actualizar borrador
                    </button>
                </div>
            </div>
        </section>

        <input type="hidden" name="conceptos_payload" id="conceptos_payload">
    </form>
</div>
@endsection

@push('scripts')
<script>
const PRODUCTOS = {!! json_encode($productosJs, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!};
const isDraft = {{ $isDraft ? 'true' : 'false' }};

const itemsBody = document.getElementById('itemsBody');
const calcPreview = document.getElementById('calcPreview');
const totalLabel = document.getElementById('totalLabel');
const form = document.getElementById('editForm');
const payloadInp = document.getElementById('conceptos_payload');

function fmt(n){
    return (n || 0).toLocaleString(undefined,{minimumFractionDigits:2, maximumFractionDigits:2});
}

function esc(value){
    return String(value || '')
        .replaceAll('&','&amp;')
        .replaceAll('<','&lt;')
        .replaceAll('>','&gt;')
        .replaceAll('"','&quot;')
        .replaceAll("'","&#039;");
}

function rowTemplate(idx, d){
    d = Object.assign({producto_id:'',descripcion:'',cantidad:1,precio_unitario:0,iva_tasa:0.16}, d || {});

    return `
        <tr data-idx="${idx}">
            <td>
                <textarea class="fx360-textarea" name="conceptos[${idx}][descripcion]" rows="2" required>${esc(d.descripcion)}</textarea>
            </td>
            <td>
                <select class="fx360-select" name="conceptos[${idx}][producto_id]" onchange="onProductChange(${idx}, this.value)">
                    <option value="">—</option>
                    ${PRODUCTOS.map(p => `<option value="${esc(p.id)}" ${String(d.producto_id) === String(p.id) ? 'selected' : ''}>${esc(p.label)}</option>`).join('')}
                </select>
            </td>
            <td class="fx360-right">
                <input type="number" step="0.0001" min="0.0001" class="fx360-number" name="conceptos[${idx}][cantidad]" value="${esc(d.cantidad)}" oninput="recalc()" required>
            </td>
            <td class="fx360-right">
                <input type="number" step="0.0001" min="0" class="fx360-number" name="conceptos[${idx}][precio_unitario]" value="${esc(d.precio_unitario)}" oninput="recalc()" required>
            </td>
            <td class="fx360-right">
                <input type="number" step="0.0001" min="0" class="fx360-number" name="conceptos[${idx}][iva_tasa]" value="${esc(d.iva_tasa)}" oninput="recalc()">
            </td>
            <td class="fx360-right subtotal">$0.00</td>
            <td class="fx360-right total">$0.00</td>
            <td><button type="button" class="fx360-remove" onclick="removeItem(${idx})">×</button></td>
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
    if(tr){
        tr.remove();
        renumber();
        recalc();
    }
}

function renumber(){
    [...itemsBody.children].forEach((tr,i) => {
        tr.dataset.idx = i;
        tr.querySelectorAll('[name]').forEach(el => {
            el.name = el.name.replace(/\[\d+\]/, `[${i}]`);
        });
        const select = tr.querySelector('select[name^="conceptos"]');
        if(select){
            select.setAttribute('onchange', `onProductChange(${i}, this.value)`);
        }
        const remove = tr.querySelector('.fx360-remove');
        if(remove){
            remove.setAttribute('onclick', `removeItem(${i})`);
        }
    });
}

function onProductChange(idx, pid){
    const p = PRODUCTOS.find(x => String(x.id) === String(pid));
    const tr = itemsBody.querySelector(`tr[data-idx="${idx}"]`);

    if(p && tr){
        const d = tr.querySelector(`[name="conceptos[${idx}][descripcion]"]`);
        const pu = tr.querySelector(`[name="conceptos[${idx}][precio_unitario]"]`);
        const t = tr.querySelector(`[name="conceptos[${idx}][iva_tasa]"]`);

        if(d) d.value = p.descripcion || '';
        if(pu) pu.value = p.precio_unitario || 0;
        if(t) t.value = typeof p.iva_tasa === 'number' ? p.iva_tasa : 0.16;

        recalc();
    }
}

function recalc(){
    if(!isDraft) return;

    let subtotal = 0;
    let iva = 0;
    let total = 0;

    [...itemsBody.children].forEach((tr,i) => {
        const q = parseFloat(tr.querySelector(`[name="conceptos[${i}][cantidad]"]`)?.value || '0');
        const pu = parseFloat(tr.querySelector(`[name="conceptos[${i}][precio_unitario]"]`)?.value || '0');
        const tasa = parseFloat(tr.querySelector(`[name="conceptos[${i}][iva_tasa]"]`)?.value || '0');

        const sub = Math.round(q * pu * 100) / 100;
        const imp = Math.round(sub * tasa * 100) / 100;
        const tot = Math.round((sub + imp) * 100) / 100;

        subtotal += sub;
        iva += imp;
        total += tot;

        const sEl = tr.querySelector('.subtotal');
        const tEl = tr.querySelector('.total');

        if(sEl) sEl.textContent = '$' + fmt(sub);
        if(tEl) tEl.textContent = '$' + fmt(tot);
    });

    if(calcPreview){
        calcPreview.textContent = `Subtotal: $${fmt(subtotal)} · IVA: $${fmt(iva)} · Total: $${fmt(total)}`;
    }

    if(totalLabel){
        totalLabel.textContent = '$' + fmt(total);
    }
}

if(isDraft && form){
    form.addEventListener('submit', () => {
        const conceptos = [...itemsBody.children].map((tr,i) => ({
            producto_id: tr.querySelector(`[name="conceptos[${i}][producto_id]"]`)?.value || null,
            descripcion: tr.querySelector(`[name="conceptos[${i}][descripcion]"]`)?.value || '',
            cantidad: parseFloat(tr.querySelector(`[name="conceptos[${i}][cantidad]"]`)?.value || '0') || 0,
            precio_unitario: parseFloat(tr.querySelector(`[name="conceptos[${i}][precio_unitario]"]`)?.value || '0') || 0,
            iva_tasa: parseFloat(tr.querySelector(`[name="conceptos[${i}][iva_tasa]"]`)?.value || '0') || 0,
        }));

        if(payloadInp){
            payloadInp.value = JSON.stringify(conceptos);
        }
    });

    recalc();
}
</script>
@endpush