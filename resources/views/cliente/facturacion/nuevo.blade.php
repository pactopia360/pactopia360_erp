{{-- resources/views/cliente/facturacion/nuevo.blade.php
     v3.1 · Nuevo CFDI · UI unificada Pactopia360 (FREE vs PRO)
--}}
@extends('layouts.cliente')
@section('title','Nuevo CFDI · Pactopia360')

@php
  use Illuminate\Support\Facades\Route;
  use Illuminate\Support\Str;
  use Illuminate\Support\Carbon;

  $summary = $summary ?? app(\App\Http\Controllers\Cliente\HomeController::class)->buildAccountSummary();

  $planFromSummary = strtoupper((string)($summary['plan'] ?? 'FREE'));
  $isProPlan       = (bool)($summary['is_pro'] ?? in_array(
      strtolower($planFromSummary),
      ['pro','premium','empresa','business'],
      true
  ));
  $planLabel = $planFromSummary;

  $emisores   = collect($emisores   ?? []);
  $receptores = collect($receptores ?? []);
  $productos  = collect($productos  ?? []);

  $today         = Carbon::now();
  $monedaDefault = 'MXN';

  $rtBack      = Route::has('cliente.facturacion.index')
                    ? route('cliente.facturacion.index', request()->only(['q','status','month']))
                    : url('/cliente/facturacion');
  $rtStore     = Route::has('cliente.facturacion.store')
                    ? route('cliente.facturacion.store')
                    : url('/cliente/facturacion');

  $rtPreview   = Route::has('cliente.facturacion.preview')    ? route('cliente.facturacion.preview')    : '#';
  $rtTemplates = Route::has('cliente.facturacion.plantillas') ? route('cliente.facturacion.plantillas') : '#';
  $rtClone     = Route::has('cliente.facturacion.clone')      ? route('cliente.facturacion.clone')      : '#';
  $rtFromXml   = Route::has('cliente.facturacion.fromXml')    ? route('cliente.facturacion.fromXml')    : '#';

  $lockTitle = 'Disponible en plan PRO';

  $rtEmisoresIndex  = Route::has('cliente.emisores.index')  ? route('cliente.emisores.index')  : '#';
  $rtEmisoresCreate = Route::has('cliente.emisores.create') ? route('cliente.emisores.create') : '#';
  $rtReceptoresIdx  = Route::has('cliente.receptores.index')  ? route('cliente.receptores.index')  : '#';
  $rtReceptoresNew  = Route::has('cliente.receptores.create') ? route('cliente.receptores.create') : '#';

  $complementosCatalogo = [
      'pagos'          => 'Pagos',
      'nomina'         => 'Nómina',
      'carta_porte'    => 'Carta Porte',
      'inst_educativas'=> 'Instituciones Educativas',
      'comercio_ext'   => 'Comercio exterior',
  ];
@endphp

@push('styles')
<style>
  .cfdi-page{
    --rose:#e11d48;
    --rose-dark:#be123c;
    --ink:#0f172a;
    --mut:#6b7280;
    --card:#ffffff;
    --bg:#fff7f9;
    --bd:#f3d5dc;
    --bd-soft:#fee2e2;
    --radius:18px;
    --shadow:0 10px 30px rgba(225,29,72,.08);

    font-family:'Poppins',system-ui,sans-serif;
    color:var(--ink);
    display:flex;
    flex-direction:column;
    gap:18px;
    padding:18px 18px 32px;
  }
  html[data-theme="dark"] .cfdi-page{
    --ink:#e5e7eb;
    --mut:#9ca3af;
    --card:#020617;
    --bg:#020617;
    --bd:#1f2937;
    --bd-soft:#111827;
    --shadow:0 16px 40px rgba(0,0,0,.6);
  }

  .cfdi-header{display:flex;justify-content:space-between;gap:10px;align-items:flex-start;flex-wrap:wrap;}
  .cfdi-title-block{display:flex;flex-direction:column;gap:6px;}
  .cfdi-kicker{font:800 11px/1 'Poppins';letter-spacing:.16em;text-transform:uppercase;color:var(--mut);}
  .cfdi-title{font:900 22px/1.2 'Poppins';color:var(--ink);}
  .cfdi-subtitle{font-size:12px;font-weight:500;color:var(--mut);}

  .btn-cfdi{
    display:inline-flex;align-items:center;justify-content:center;gap:6px;
    padding:8px 13px;border-radius:999px;border:1px solid var(--bd);
    background:var(--card);font:800 12px/1 'Poppins';
    color:var(--ink);min-height:34px;cursor:pointer;text-decoration:none;
    transition:background .12s, box-shadow .12s, transform .12s, opacity .12s;
  }
  .btn-cfdi span[aria-hidden="true"]{font-size:15px;}
  .btn-cfdi.primary{
    background:linear-gradient(90deg,var(--rose),var(--rose-dark));
    border-color:var(--rose-dark);color:#fff;
    box-shadow:0 10px 26px rgba(225,29,72,.25);
  }
  .btn-cfdi.ghost{background:#fff;}
  .btn-cfdi:hover:not(:disabled){
    background:#fff7f9;transform:translateY(-1px);
    box-shadow:0 8px 18px rgba(15,23,42,.08);
  }
  .btn-cfdi.primary:hover:not(:disabled){filter:brightness(.97);}
  .btn-cfdi.locked{opacity:.5;cursor:not-allowed;}
  .btn-cfdi.back{background:transparent;border-color:transparent;color:var(--mut);padding-left:0;}
  .btn-cfdi.back span[aria-hidden="true"]{font-size:13px;}

  .cfdi-plan-pill{
    display:inline-flex;align-items:center;gap:6px;
    border-radius:999px;padding:6px 12px;
    font:800 11px/1 'Poppins';text-transform:uppercase;
    letter-spacing:.12em;border:1px solid var(--bd);
    background:#fff1f2;color:var(--rose-dark);
  }
  .cfdi-plan-pill span[aria-hidden="true"]{font-size:13px;}
  .cfdi-plan-pill.pro{
    background:#dcfce7;border-color:#bbf7d0;color:#166534;
  }

  .cfdi-toolbar{display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:flex-end;}

  .cfdi-layout{
    background:var(--bg);border-radius:var(--radius);border:1px solid var(--bd-soft);
    box-shadow:var(--shadow);padding:18px 18px 20px;
    display:flex;flex-direction:column;gap:16px;
  }

  .cfdi-section{
    border-radius:14px;border:1px solid var(--bd);
    background:var(--card);padding:14px 16px 16px;
  }
  .cfdi-section-header{display:flex;justify-content:space-between;gap:10px;align-items:center;margin-bottom:10px;}
  .cfdi-section-title{font:800 13px/1.2 'Poppins';text-transform:uppercase;letter-spacing:.16em;color:var(--rose-dark);}
  .cfdi-section-sub{font-size:11px;font-weight:500;color:var(--mut);}

  .grid-2{display:grid;gap:10px 16px;}
  @media(min-width:960px){.grid-2{grid-template-columns:1.2fr 1fr;}}

  .field{display:flex;flex-direction:column;gap:4px;}
  .field-label{font:800 11px/1.1 'Poppins';text-transform:uppercase;letter-spacing:.12em;color:var(--mut);}
  .field-help{font-size:11px;color:var(--mut);}

  .input-cfdi,.select-cfdi,.textarea-cfdi{
    border-radius:11px;border:1px solid var(--bd);background:#fff;
    padding:8px 10px;font:600 13px/1.2 'Poppins';color:var(--ink);
  }
  .textarea-cfdi{min-height:40px;resize:vertical;}
  .input-cfdi:focus,.select-cfdi:focus,.textarea-cfdi:focus{
    outline:none;border-color:var(--rose);box-shadow:0 0 0 1px rgba(225,29,72,.2);
  }

  table.concepts{
    width:100%;border-collapse:collapse;border:1px solid var(--bd-soft);
    border-radius:14px;overflow:hidden;margin-top:6px;font-size:13px;
  }
  table.concepts th,table.concepts td{
    padding:8px 10px;border-bottom:1px solid var(--bd-soft);text-align:left;
  }
  table.concepts th{
    background:#fff0f3;color:var(--mut);
    font:800 11px/1 'Poppins';text-transform:uppercase;letter-spacing:.12em;
  }
  table.concepts tr:hover td{background:#fffafc;}

  .subtotal,.total{font-weight:700;font-size:13px;}
  .total{color:var(--rose-dark);}

  .btn-mini{
    width:26px;height:26px;border-radius:999px;border:1px solid var(--bd);
    background:#fff;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;font-size:14px;
  }
  .btn-mini:hover{background:#fee2e2;}

  .qty-control{display:inline-flex;align-items:center;border-radius:999px;border:1px solid var(--bd);overflow:hidden;}
  .qty-control button{width:26px;height:26px;border:0;background:#fff7f9;font-size:15px;cursor:pointer;}
  .qty-control input{width:52px;border:0;text-align:center;font:600 13px/1 'Poppins';background:#fff;}

  .cfdi-summary{text-align:right;font-size:13px;font-weight:700;color:var(--mut);}
  .cfdi-summary b{color:var(--rose-dark);}

  .cfdi-footer-actions{display:flex;justify-content:flex-end;gap:10px;align-items:center;margin-top:10px;flex-wrap:wrap;}
  .cfdi-badge-free-note{font-size:11px;color:var(--mut);}

  .pill-feature{
    display:inline-flex;align-items:center;gap:6px;
    padding:6px 10px;border-radius:999px;border:1px dashed var(--bd);
    font:700 11px/1 'Poppins';color:var(--mut);
  }
  .pill-feature.locked::after{
    content:"Solo PRO";font-weight:800;text-transform:uppercase;
    letter-spacing:.12em;margin-left:4px;color:#b91c1c;
  }

  .cfdi-alert-pro{
    border-radius:12px;border:1px dashed #f97316;background:#fffbeb;
    padding:8px 10px;font-size:11px;color:#92400e;margin-top:6px;
  }

  /* Complementos como chips */
  .cfdi-complements-grid{
    display:flex;flex-wrap:wrap;gap:8px;
  }
  .comp-pill{
    display:inline-flex;align-items:center;gap:6px;
    padding:6px 10px;border-radius:999px;
    border:1px solid var(--bd);background:#fff;
    font:700 11px/1 'Poppins';cursor:pointer;
  }
  .comp-pill input{display:none;}
  .comp-pill span{pointer-events:none;}
  .comp-pill.active{
    background:#fee2e2;border-color:var(--rose-dark);color:var(--rose-dark);
  }
  .comp-pill.disabled{
    opacity:.5;cursor:not-allowed;
  }
</style>
@endpush

@section('content')
<div class="cfdi-page">

  {{-- HEADER --}}
  <div class="cfdi-header">
    <div class="cfdi-title-block">
      <button type="button" class="btn-cfdi back" onclick="window.location='{{ $rtBack }}'">
        <span aria-hidden="true">←</span><span>Volver</span>
      </button>
      <div class="cfdi-kicker">Facturación</div>
      <div class="cfdi-title">Nuevo CFDI</div>
      <div class="cfdi-subtitle">
        En FREE capturas CFDI simples. En PRO se habilitan vista previa, plantillas, clonación y carga desde XML.
      </div>
    </div>

    <div>
      <div style="display:flex;justify-content:flex-end;margin-bottom:8px;">
        <div class="cfdi-plan-pill {{ $isProPlan ? 'pro' : '' }}">
          <span aria-hidden="true">{{ $isProPlan ? '⭐' : '🆓' }}</span>
          <span>{{ $planLabel }}</span>
        </div>
      </div>

      <div class="cfdi-toolbar">
        <button type="button"
                class="btn-cfdi ghost {{ $isProPlan ? '' : 'locked' }}"
                @if($isProPlan && $rtPreview !== '#')
                  data-action="preview"
                @else
                  title="{{ $isProPlan ? '' : $lockTitle }}"
                  disabled
                @endif>
          <span aria-hidden="true">👁</span><span>Vista previa</span>
        </button>

        <button type="button"
                class="btn-cfdi ghost {{ $isProPlan ? '' : 'locked' }}"
                @if($isProPlan && $rtTemplates !== '#')
                  data-action="templates"
                @else
                  title="{{ $isProPlan ? '' : $lockTitle }}"
                  disabled
                @endif>
          <span aria-hidden="true">📄</span><span>Plantillas</span>
        </button>

        <button type="button"
                class="btn-cfdi ghost {{ $isProPlan ? '' : 'locked' }}"
                @if($isProPlan && $rtClone !== '#')
                  data-action="clone"
                @else
                  title="{{ $isProPlan ? '' : $lockTitle }}"
                  disabled
                @endif>
          <span aria-hidden="true">🧬</span><span>Clonar CFDI</span>
        </button>

        <button type="button"
                class="btn-cfdi ghost {{ $isProPlan ? '' : 'locked' }}"
                @if($isProPlan && $rtFromXml !== '#')
                  data-action="from-xml"
                @else
                  title="{{ $isProPlan ? '' : $lockTitle }}"
                  disabled
                @endif>
          <span aria-hidden="true">📥</span><span>Desde XML</span>
        </button>
      </div>
    </div>
  </div>

  {{-- FLASHES --}}
  @if(session('ok'))
    <div class="cfdi-layout" style="padding:12px 16px;">
      <div style="border-radius:12px;background:#ecfdf5;border:1px solid #86efac;color:#047857;padding:8px 10px;font-size:13px;font-weight:700;">
        {{ session('ok') }}
      </div>
    </div>
  @endif
  @if($errors->any())
    <div class="cfdi-layout" style="padding:12px 16px;">
      <div style="border-radius:12px;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:8px 10px;font-size:13px;font-weight:700;">
        {{ $errors->first() }}
      </div>
    </div>
  @endif

  {{-- FORMULARIO --}}
  <form method="POST" action="{{ $rtStore }}" id="newForm">
    @csrf

    <div class="cfdi-layout">

      {{-- EMISOR --}}
      <div class="cfdi-section">
        <div class="cfdi-section-header">
          <div>
            <div class="cfdi-section-title">Emisor</div>
            <div class="cfdi-section-sub">Datos de la empresa emisora y encabezado del CFDI</div>
          </div>
          @if($rtEmisoresIndex !== '#')
            <a href="{{ $rtEmisoresIndex }}" class="btn-cfdi ghost" style="font-size:11px;">
              <span aria-hidden="true">🏢</span><span>Gestionar emisores</span>
            </a>
          @endif
        </div>

        <div class="grid-2">
          <div class="field">
            <div class="field-label">Empresa / Emisor</div>
            <select name="cliente_id" class="select-cfdi" required>
              @if($emisores->isEmpty())
                <option value="">— No hay emisores —</option>
              @else
                <option value="">Selecciona emisor…</option>
                @foreach($emisores as $e)
                  <option value="{{ $e->id }}" @selected(old('cliente_id')==$e->id)>
                    {{ $e->nombre_comercial ?? $e->razon_social ?? ('#'.$e->id) }} — {{ $e->rfc }}
                  </option>
                @endforeach
              @endif
            </select>
            <div style="margin-top:6px;display:flex;gap:8px;flex-wrap:wrap;">
              @if($emisores->isEmpty())
                <div class="field-help">Configura al menos un emisor para poder timbrar.</div>
              @else
                <div class="field-help">Solo se muestran emisores registrados en tu cuenta.</div>
              @endif

              @if($rtEmisoresCreate !== '#')
                <a href="{{ $rtEmisoresCreate }}" class="btn-cfdi ghost" style="padding:4px 10px;font-size:11px;">
                  <span aria-hidden="true">＋</span><span>Nuevo emisor</span>
                </a>
              @endif
            </div>
          </div>

          <div class="field">
            <div class="field-label">Fecha</div>
            <input type="datetime-local"
                   name="fecha"
                   class="input-cfdi"
                   value="{{ old('fecha', $today->format('Y-m-d\TH:i')) }}">
          </div>
        </div>

        <div class="grid-2" style="margin-top:8px;">
          <div class="field">
            <div class="field-label">Serie</div>
            <input type="text" name="serie" class="input-cfdi" maxlength="10" value="{{ old('serie') }}">
          </div>
          <div class="field">
            <div class="field-label">Folio</div>
            <input type="text" name="folio" class="input-cfdi" maxlength="20" value="{{ old('folio') }}">
          </div>
        </div>

        <div class="grid-2" style="margin-top:8px;">
          <div class="field">
            <div class="field-label">Moneda</div>
            <input type="text" name="moneda" class="input-cfdi" maxlength="10" value="{{ old('moneda',$monedaDefault) }}">
          </div>
          <div class="field">
            <div class="field-label">UUID (temporal)</div>
            <div class="input-cfdi" style="display:flex;align-items:center;">Se generará automáticamente</div>
          </div>
        </div>
      </div>

      {{-- RECEPTOR --}}
      <div class="cfdi-section">
        <div class="cfdi-section-header">
          <div>
            <div class="cfdi-section-title">Receptor</div>
            <div class="cfdi-section-sub">Selecciona el cliente receptor del CFDI</div>
          </div>
          @if($rtReceptoresIdx !== '#')
            <a href="{{ $rtReceptoresIdx }}" class="btn-cfdi ghost" style="font-size:11px;">
              <span aria-hidden="true">👤</span><span>Gestionar receptores</span>
            </a>
          @endif
        </div>

        <div class="grid-2">
          <div class="field">
            <div class="field-label">Receptor</div>
            <select name="receptor_id" class="select-cfdi" required>
              @if($receptores->isEmpty())
                <option value="">— No hay receptores —</option>
              @else
                <option value="">Selecciona receptor…</option>
                @foreach($receptores as $r)
                  <option value="{{ $r->id }}" @selected(old('receptor_id')==$r->id)>
                    {{ $r->razon_social ?? $r->nombre_comercial ?? ('#'.$r->id) }} — {{ $r->rfc }}
                  </option>
                @endforeach
              @endif
            </select>
            <div style="margin-top:6px;display:flex;gap:8px;flex-wrap:wrap;">
              @if($receptores->isEmpty())
                <div class="field-help">Agrega receptores en el módulo de Clientes/Receptores.</div>
              @endif
              @if($rtReceptoresNew !== '#')
                <a href="{{ $rtReceptoresNew }}" class="btn-cfdi ghost" style="padding:4px 10px;font-size:11px;">
                  <span aria-hidden="true">＋</span><span>Nuevo receptor</span>
                </a>
              @endif
            </div>
          </div>

          <div class="field">
            <div class="field-label">Uso CFDI</div>
            <input type="text" class="input-cfdi" value="G03" readonly>
          </div>
        </div>
      </div>

      {{-- CONCEPTOS --}}
      <div class="cfdi-section">
        <div class="cfdi-section-header">
          <div>
            <div class="cfdi-section-title">Conceptos / productos</div>
            <div class="cfdi-section-sub">Agrega los conceptos con sus importes e impuestos</div>
          </div>
          <div class="pill-feature {{ $isProPlan ? '' : 'locked' }}">
            Complementos por concepto
          </div>
        </div>

        <table class="concepts" id="itemsTable">
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
          <button type="button" class="btn-cfdi ghost" id="btnAddConcept">
            <span aria-hidden="true">➕</span><span>Agregar concepto</span>
          </button>
          <div style="flex:1;"></div>
          <div class="cfdi-summary" id="calcPreview" aria-live="polite">
            Subtotal: $0.00 · IVA: $0.00 · Total: $0.00
          </div>
        </div>
      </div>

      {{-- PAGO --}}
      <div class="cfdi-section">
        <div class="cfdi-section-header">
          <div>
            <div class="cfdi-section-title">Pago</div>
            <div class="cfdi-section-sub">Forma y método de pago del CFDI</div>
          </div>
        </div>

        <div class="grid-2">
          <div class="field">
            <div class="field-label">Forma de pago</div>
            <input type="text" name="forma_pago" class="input-cfdi" value="{{ old('forma_pago') }}">
          </div>
          <div class="field">
            <div class="field-label">Método de pago</div>
            <input type="text" name="metodo_pago" class="input-cfdi" value="{{ old('metodo_pago') }}">
          </div>
        </div>

        <div class="field" style="margin-top:8px;max-width:260px;">
          <div class="field-label">Total a pagar</div>
          <input type="text" class="input-cfdi" id="totalLabel" value="$0.00" readonly>
        </div>
      </div>

      {{-- COMPLEMENTOS --}}
      <div class="cfdi-section">
        <div class="cfdi-section-header">
          <div>
            <div class="cfdi-section-title">Complementos</div>
            <div class="cfdi-section-sub">
              Pagos, nómina, carta porte, instituciones educativas, comercio exterior y más.
            </div>
          </div>
          <div class="pill-feature {{ $isProPlan ? '' : 'locked' }}">
            Complementos SAT
          </div>
        </div>

        @if($isProPlan)
          <div class="field">
            <div class="field-label">Selecciona complementos</div>
            <div class="cfdi-complements-grid" id="complementsGrid">
              @foreach($complementosCatalogo as $key => $label)
                @php $checked = in_array($key, (array)old('complementos', []), true); @endphp
                <label class="comp-pill {{ $checked ? 'active' : '' }}">
                  <input type="checkbox" name="complementos[]" value="{{ $key }}" {{ $checked ? 'checked' : '' }}>
                  <span>{{ $label }}</span>
                </label>
              @endforeach
            </div>
            <div class="field-help">
              El detalle de cada complemento se llenará en pasos siguientes del flujo PRO.
            </div>
          </div>
        @else
          <div class="field">
            <div class="field-label">Complementos disponibles en PRO</div>
            <div class="cfdi-complements-grid">
              @foreach($complementosCatalogo as $key => $label)
                <label class="comp-pill disabled">
                  <input type="checkbox" disabled>
                  <span>{{ $label }}</span>
                </label>
              @endforeach
            </div>
            <div class="cfdi-alert-pro">
              En el plan FREE puedes timbrar CFDI simples de ingreso/egreso.
              Los complementos avanzados se activan al contratar el plan PRO.
            </div>
          </div>
        @endif
      </div>

      {{-- FOOTER --}}
      <div class="cfdi-footer-actions">
        <div class="cfdi-badge-free-note">
          @if(!$isProPlan)
            Vista previa, plantillas, clonación y complementos avanzados están disponibles en <b>plan PRO</b>.
          @else
            Todas las funciones PRO de facturación están activas en tu cuenta.
          @endif
        </div>

        <button type="submit" class="btn-cfdi primary">
          <span aria-hidden="true">🧾</span>
          <span>Crear borrador</span>
        </button>
      </div>

    </div>
  </form>
</div>
@endsection

@push('scripts')
<script>
  const PRODUCTOS = {!! json_encode(
    $productos->map(fn($p)=>[
      'id'             => $p->id,
      'label'          => trim(($p->sku ? $p->sku.' - ' : '').($p->descripcion ?? '')),
      'descripcion'    => $p->descripcion ?? '',
      'precio_unitario'=> (float)($p->precio_unitario ?? 0),
      'iva_tasa'       => (float)($p->iva_tasa ?? 0.16),
    ])->values(),
    JSON_UNESCAPED_UNICODE
  ) !!};

  const itemsBody   = document.getElementById('itemsBody');
  const calcPreview = document.getElementById('calcPreview');
  const totalLabel  = document.getElementById('totalLabel');
  const btnAdd      = document.getElementById('btnAddConcept');

  function fmt(n){
    return (n || 0).toLocaleString(undefined,{
      minimumFractionDigits:2,
      maximumFractionDigits:2
    });
  }

  function rowTemplate(idx, d){
    const data = Object.assign(
      {producto_id:'', descripcion:'', cantidad:1, precio_unitario:0, iva_tasa:0.16},
      d || {}
    );
    const opts = (PRODUCTOS || []).map(p =>
      `<option value="${p.id}" ${String(data.producto_id)===String(p.id)?'selected':''}>${p.label}</option>`
    ).join('');
    return `
      <tr data-idx="${idx}">
        <td>
          <textarea class="textarea-cfdi" name="conceptos[${idx}][descripcion]" rows="2" required>${data.descripcion||''}</textarea>
        </td>
        <td>
          <select class="select-cfdi" name="conceptos[${idx}][producto_id]" onchange="onProductChange(${idx},this.value)">
            <option value="">—</option>
            ${opts}
          </select>
        </td>
        <td>
          <div class="qty-control">
            <button type="button" data-step="-1">−</button>
            <input type="number" step="0.0001" min="0.0001"
                   name="conceptos[${idx}][cantidad]" value="${data.cantidad}">
            <button type="button" data-step="1">+</button>
          </div>
        </td>
        <td>
          <input type="number" step="0.0001" min="0"
                 class="input-cfdi"
                 name="conceptos[${idx}][precio_unitario]"
                 value="${data.precio_unitario}">
        </td>
        <td>
          <input type="number" step="0.0001" min="0"
                 class="input-cfdi"
                 name="conceptos[${idx}][iva_tasa]"
                 value="${data.iva_tasa}">
        </td>
        <td class="subtotal">$0.00</td>
        <td class="total">$0.00</td>
        <td>
          <button type="button" class="btn-mini" onclick="removeItem(${idx})" title="Eliminar">✕</button>
        </td>
      </tr>
    `;
  }

  function recalc(){
    let subtotal=0, iva=0, total=0;
    [...itemsBody.children].forEach((tr,i)=>{
      const q  = parseFloat(tr.querySelector(`[name="conceptos[${i}][cantidad]"]`)?.value || '0');
      const pu = parseFloat(tr.querySelector(`[name="conceptos[${i}][precio_unitario]"]`)?.value || '0');
      const t  = parseFloat(tr.querySelector(`[name="conceptos[${i}][iva_tasa]"]`)?.value || '0');
      const s  = Math.round(q*pu*100)/100;
      const v  = Math.round(s*t*100)/100;
      const tot= Math.round((s+v)*100)/100;
      subtotal+=s; iva+=v; total+=tot;
      tr.querySelector('.subtotal').textContent = '$'+fmt(s);
      tr.querySelector('.total').textContent    = '$'+fmt(tot);
    });
    calcPreview.textContent = `Subtotal: $${fmt(subtotal)} · IVA: $${fmt(iva)} · Total: $${fmt(total)}`;
    if(totalLabel) totalLabel.value = '$'+fmt(total);
  }

  function addItem(data){
    const idx = itemsBody.children.length;
    itemsBody.insertAdjacentHTML('beforeend', rowTemplate(idx, data));
    recalc();
  }

  function removeItem(idx){
    const tr = itemsBody.querySelector(`tr[data-idx="${idx}"]`);
    if(tr){
      tr.remove();
      [...itemsBody.children].forEach((row,i)=>{
        row.dataset.idx = i;
        row.querySelectorAll('[name]').forEach(el=>{
          el.name = el.name.replace(/\[\d+]/, '['+i+']');
        });
      });
      recalc();
    }
  }

  window.removeItem = removeItem;
  window.onProductChange = function(idx,pid){
    const p = (PRODUCTOS||[]).find(x=>String(x.id)===String(pid));
    const tr = itemsBody.querySelector(`tr[data-idx="${idx}"]`);
    if(p && tr){
      tr.querySelector(`[name="conceptos[${idx}][descripcion]"]`).value      = p.descripcion || '';
      tr.querySelector(`[name="conceptos[${idx}][precio_unitario]"]`).value  = p.precio_unitario ?? 0;
      tr.querySelector(`[name="conceptos[${idx}][iva_tasa]"]`).value         = p.iva_tasa ?? 0.16;
      recalc();
    }
  };

  // +/- cantidad
  itemsBody.addEventListener('click', function(ev){
    const btn = ev.target.closest('.qty-control button');
    if(!btn) return;
    const step = parseFloat(btn.dataset.step || '0');
    const input = btn.parentElement.querySelector('input[type="number"]');
    if(!input) return;
    let v = parseFloat(input.value || '0');
    if(isNaN(v)) v = 0;
    v += step;
    if(v < 0.0001) v = 0.0001;
    input.value = v.toFixed(4).replace(/\.?0+$/,'');
    recalc();
  });

  itemsBody.addEventListener('input', function(ev){
    if(ev.target.matches('input[type="number"]')) recalc();
  });

  btnAdd?.addEventListener('click',()=>addItem({cantidad:1,precio_unitario:0,iva_tasa:0.16}));
  addItem({cantidad:1,precio_unitario:0,iva_tasa:0.16});

  // Chips de complementos
  document.getElementById('complementsGrid')?.addEventListener('click',function(e){
    const pill = e.target.closest('.comp-pill');
    if(!pill || pill.classList.contains('disabled')) return;
    const chk = pill.querySelector('input[type="checkbox"]');
    if(!chk) return;
    chk.checked = !chk.checked;
    pill.classList.toggle('active', chk.checked);
  });
</script>
@endpush
