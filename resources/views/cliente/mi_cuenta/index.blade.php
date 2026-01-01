{{-- resources/views/cliente/mi_cuenta/index.blade.php (v4.1 · FIX: Facturas modal abre listado real + define rtInvoicesModal + robust modal/iframe) --}}
@extends('layouts.cliente')
@section('title','Mi cuenta · Pactopia360')
@section('pageClass','page-mi-cuenta')

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/client/css/mi-cuenta.css') }}?v=4.1">

  {{-- Estilos críticos (solo lo mínimo para overlay + centrado + bloqueo) --}}
  <style>
/* ===========================================================
   CRÍTICO (LIGHT FIRST): overlay + dialogs centrados
   - En claro: overlay suave, modal blanco limpio
   - En oscuro: respeta variables (si existieran)
=========================================================== */

body.mc-modal-open{ overflow:hidden !important; }

/* Overlay: en claro NO debe verse “night mode” */
#mcOverlay{
  position: fixed !important;
  inset: 0 !important;

  /* default (claro): más suave */
  background: rgba(15,23,42,.45) !important;
  backdrop-filter: blur(4px) !important;
  -webkit-backdrop-filter: blur(4px) !important;

  z-index: 2147483646 !important;
  display: none !important;
  pointer-events: auto !important;
}
#mcOverlay.is-on{ display:block !important; }

/* Si el tema real está en dark, hacemos overlay más fuerte */
html[data-theme="dark"] #mcOverlay{
  background: rgba(2,6,23,.72) !important;
  backdrop-filter: blur(2px) !important;
  -webkit-backdrop-filter: blur(2px) !important;
}

/* Dialog base */
#billingModal,
#configModal,
#paymentsModal,
#invoicesModal{
  z-index: 2147483647 !important;
  border: 1px solid rgba(15,23,42,.10) !important;
  padding: 0 !important;

  /* Importante: NO transparente */
  background: #ffffff !important;

  width: auto !important;
  max-width: none !important;

  border-radius: 18px !important;
  overflow: hidden !important;

  /* sombra “clean” en claro */
  box-shadow: 0 22px 70px rgba(15,23,42,.22) !important;
}

/* En dark, si quieres sombra más intensa */
html[data-theme="dark"] #billingModal,
html[data-theme="dark"] #configModal,
html[data-theme="dark"] #paymentsModal,
html[data-theme="dark"] #invoicesModal{
  background: var(--mc-card, #0b1220) !important;
  border-color: rgba(148,163,184,.18) !important;
  box-shadow: 0 26px 80px rgba(0,0,0,.55) !important;
}

/* Posicionamiento */
#billingModal[open],
#configModal[open],
#paymentsModal[open],
#invoicesModal[open]{
  position: fixed !important;
  inset: 0 !important;
  margin: auto !important;
  width: min(96vw, 1080px) !important;
  max-height: min(88vh, 820px) !important;
}

/* Backdrop nativo del dialog (por si el overlay no alcanza) */
#billingModal::backdrop,
#configModal::backdrop,
#paymentsModal::backdrop,
#invoicesModal::backdrop{
  background: rgba(15,23,42,.45) !important;
  backdrop-filter: blur(4px) !important;
  -webkit-backdrop-filter: blur(4px) !important;
}
html[data-theme="dark"] #billingModal::backdrop,
html[data-theme="dark"] #configModal::backdrop,
html[data-theme="dark"] #paymentsModal::backdrop,
html[data-theme="dark"] #invoicesModal::backdrop{
  background: rgba(2,6,23,.72) !important;
  backdrop-filter: blur(2px) !important;
  -webkit-backdrop-filter: blur(2px) !important;
}

/* Scroll body */
#billingModal .mc-modal-body,
#configModal .mc-modal-body,
#paymentsModal .mc-modal-body,
#invoicesModal .mc-modal-body{
  max-height: calc(min(88vh, 820px) - 72px) !important;
  overflow: auto !important;
}

/* Tabs (mantener claro) */
.mc-tabs{display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px;}
.mc-tab{
  border: 1px solid rgba(15,23,42,.10);
  background: #fff;
  border-radius: 999px;
  padding: .45rem .8rem;
  font-weight: 900;
  cursor:pointer;
}
.mc-tab.is-active{
  border-color: rgba(225,29,72,.30);
  background: rgba(225,29,72,.08);
  color: #9f1239;
}
.mc-pane[hidden]{display:none !important;}

.mc-modal .mc-input,
.mc-modal .mc-select{ width:100%; }

.mc-two{
  display:grid;
  grid-template-columns: 1fr 1fr;
  gap: .8rem;
}
@media (max-width: 860px){
  .mc-two{grid-template-columns:1fr;}
}
</style>

@endpush

@section('content')
@php
  use Illuminate\Support\Facades\Route;

  $user   = $user   ?? auth('web')->user();
  $cuenta = $cuenta ?? ($user?->cuenta ?? null);

  $userName  = $user?->name ?? $user?->nombre ?? '—';
  $userEmail = $user?->email ?? '—';

  $plan   = strtoupper((string)($cuenta->plan_actual ?? $cuenta->plan ?? 'FREE'));
  $cycle  = strtoupper((string)($cuenta->billing_cycle ?? '—'));
  $status = strtoupper((string)($cuenta->estado_cuenta ?? $cuenta->status ?? '—'));

  $statusClass = 'mc-pill--neutral';
  if (str_contains($status, 'ACTIV')) {
      $statusClass = 'mc-pill--ok';
  } elseif (str_contains($status, 'SUSP') || str_contains($status, 'BLOQ')) {
      $statusClass = 'mc-pill--bad';
  }

  $rtPerfil    = Route::has('cliente.perfil') ? route('cliente.perfil') : null;
  $rtContratos = Route::has('cliente.mi_cuenta.contratos.index') ? route('cliente.mi_cuenta.contratos.index') : null;

  $rtBillingUpdate = Route::has('cliente.mi_cuenta.billing.update')
    ? route('cliente.mi_cuenta.billing.update')
    : null;

  $rtEstadoCuenta = Route::has('cliente.estado_cuenta')
    ? route('cliente.estado_cuenta')
    : (Route::has('cliente.billing.statement') ? route('cliente.billing.statement') : null);

  // ✅ Endpoint JSON (historial de pagos)
  $rtMisPagos = Route::has('cliente.mi_cuenta.pagos') ? route('cliente.mi_cuenta.pagos') : null;

  // ✅ FIX: URL del listado de facturas para el iframe/modal
  // IMPORTANTE: debe apuntar a tu controlador real Cliente\MiCuenta\FacturasController@index y abrir "modal" con embed=1
  $rtInvoicesModal = null;
  if (Route::has('cliente.mi_cuenta.facturas.index')) {
      $rtInvoicesModal = route('cliente.mi_cuenta.facturas.index', ['embed' => 1, 'theme' => 'light']);
  } elseif (Route::has('cliente.facturas.index')) {
      $rtInvoicesModal = route('cliente.facturas.index', ['embed' => 1, 'theme' => 'light']);
  }


  $canProfileUpdate  = Route::has('cliente.mi_cuenta.profile.update');
  $rtProfileUpdate   = $canProfileUpdate ? route('cliente.mi_cuenta.profile.update') : null;

  $canSecurityUpdate = Route::has('cliente.mi_cuenta.security.update');
  $rtSecurityUpdate  = $canSecurityUpdate ? route('cliente.mi_cuenta.security.update') : null;

  $canBrandUpdate    = Route::has('cliente.mi_cuenta.brand.update');
  $rtBrandUpdate     = $canBrandUpdate ? route('cliente.mi_cuenta.brand.update') : null;

  $canPrefsUpdate    = Route::has('cliente.mi_cuenta.preferences.update');
  $rtPrefsUpdate     = $canPrefsUpdate ? route('cliente.mi_cuenta.preferences.update') : null;

  $brandAccent = (string)($cuenta?->brand_accent ?? session('client_ui.accent', '#E11D48'));
  if ($brandAccent === '') $brandAccent = '#E11D48';

  $brandName = (string)($cuenta?->brand_name ?? $cuenta?->nombre_marca ?? $cuenta?->nombre_comercial_ui ?? $cuenta?->nombre_comercial ?? $cuenta?->razon_social ?? '');
  $brandNameFallback = (string)($cuenta?->razon_social ?? $userName ?? 'Cuenta');

  $billing = [
    'razon_social'            => (string)($cuenta->razon_social ?? $cuenta->nombre_fiscal ?? ''),
    'nombre_comercial'        => (string)($cuenta->nombre_comercial ?? ''),
    'rfc'                     => (string)($cuenta->rfc ?? $cuenta->rfc_principal ?? ''),
    'correo'                  => (string)($cuenta->correo_facturacion ?? $cuenta->email_facturacion ?? $userEmail ?? ''),
    'telefono'                => (string)($cuenta->telefono ?? $cuenta->telefono_facturacion ?? ''),
    'pais'                    => (string)($cuenta->pais ?? 'México'),
    'calle'                   => (string)($cuenta->calle ?? ''),
    'no_ext'                  => (string)($cuenta->no_ext ?? ''),
    'no_int'                  => (string)($cuenta->no_int ?? ''),
    'colonia'                 => (string)($cuenta->colonia ?? ''),
    'municipio'               => (string)($cuenta->municipio ?? $cuenta->alcaldia ?? ''),
    'estado'                  => (string)($cuenta->estado ?? ''),
    'cp'                      => (string)($cuenta->cp ?? $cuenta->codigo_postal ?? ''),
    'regimen_fiscal'          => (string)($cuenta->regimen_fiscal ?? ''),
    'uso_cfdi'                => (string)($cuenta->uso_cfdi ?? ''),
    'metodo_pago'             => (string)($cuenta->metodo_pago ?? ''),
    'forma_pago'              => (string)($cuenta->forma_pago ?? ''),
    'leyenda_pdf'             => (string)($cuenta->leyenda_pdf ?? ''),
    'pdf_mostrar_nombre_comercial' => (int)($cuenta->pdf_mostrar_nombre_comercial ?? 0),
    'pdf_mostrar_telefono'         => (int)($cuenta->pdf_mostrar_telefono ?? 0),
  ];
@endphp

<div class="mc-wrap">
  <div class="mc-page">

    {{-- Header --}}
    <section class="mc-card mc-header" style="--mc-accent: {{ $brandAccent }};">
      <div class="mc-header-left">
        <div class="mc-title-icon" aria-hidden="true">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <path d="M12 12a4.5 4.5 0 1 0-4.5-4.5A4.51 4.51 0 0 0 12 12Z" stroke="currentColor" stroke-width="2"/>
            <path d="M20 21a8 8 0 1 0-16 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </div>
        <div style="min-width:0">
          <h1 class="mc-title-main">Mi cuenta</h1>
          <div class="mc-title-sub">Accesos rápidos a tu cuenta, datos fiscales, estado de cuenta y pagos.</div>
        </div>
      </div>

      <div class="mc-header-right">
        <div class="mc-pill-row">
          <span class="mc-pill mc-pill--ok">● {{ $plan }}</span>
          <span class="mc-pill mc-pill--neutral">{{ $cycle }}</span>
          <span class="mc-pill {{ $statusClass }}">{{ $status }}</span>
        </div>

        <div class="mc-kv">
          <div class="k">Usuario</div>
          <div class="v" title="{{ $userName }}">{{ $userName }}</div>
        </div>

        <div class="mc-kv">
          <div class="k">Correo</div>
          <div class="v" title="{{ $userEmail }}">{{ $userEmail }}</div>
        </div>
      </div>
    </section>

    {{-- Sección 1 --}}
    <section class="mc-section" data-mc-section="s1" data-open="1">
      <div class="mc-sec-head">
        <div class="mc-sec-ico" aria-hidden="true">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <path d="M4 21v-2a4 4 0 0 1 4-4h8a4 4 0 0 1 4 4v2" stroke="currentColor" stroke-width="2"/>
            <path d="M12 11a4 4 0 1 0-4-4 4 4 0 0 0 4 4Z" stroke="currentColor" stroke-width="2"/>
          </svg>
        </div>

        <div class="mc-sec-meta">
          <div class="mc-sec-kicker">SECCIÓN 1</div>
          <h2 class="mc-sec-title">Cuenta</h2>
          <div class="mc-sec-sub">Datos de la cuenta · Estatus · Contratos</div>
        </div>

        <button type="button" class="mc-sec-toggle" aria-expanded="true" aria-controls="mc-sec-body-s1" data-mc-toggle="s1" title="Desplegar">+</button>
      </div>

      <div id="mc-sec-body-s1" class="mc-sec-body">
        <div class="mc-subgrid">
          <div class="mc-sub">
            <div class="mc-sub-left">
              <div class="mc-sub-title">Mi perfil</div>
              <div class="mc-sub-desc">Información del usuario y cuenta</div>
            </div>
            <div class="mc-sub-right">
              @if($rtPerfil)
                <a class="mc-go" href="{{ $rtPerfil }}" title="Ir">›</a>
              @else
                <span class="mc-badge">ND</span>
              @endif
            </div>
          </div>

          <div class="mc-sub">
            <div class="mc-sub-left">
              <div class="mc-sub-title">Configuración</div>
              <div class="mc-sub-desc">Preferencias, seguridad y tema</div>
            </div>
            <div class="mc-sub-right">
              <a class="mc-go" href="#" data-open-config-modal title="Abrir configuración" aria-label="Abrir configuración">›</a>
            </div>
          </div>

          <div class="mc-sub">
            <div class="mc-sub-left">
              <div class="mc-sub-title">Contratos</div>
              <div class="mc-sub-desc">Aceptación y firma</div>
            </div>
            <div class="mc-sub-right">
              @if($rtContratos)
                <a class="mc-go" href="{{ $rtContratos }}" title="Ir">›</a>
              @else
                <span class="mc-badge">ND</span>
              @endif
            </div>
          </div>
        </div>
      </div>
    </section>

    {{-- Sección 2 --}}
    <section class="mc-section" data-mc-section="s2" data-open="1">
      <div class="mc-sec-head">
        <div class="mc-sec-ico" aria-hidden="true">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" stroke="currentColor" stroke-width="2"/>
            <path d="M14 2v6h6" stroke="currentColor" stroke-width="2"/>
          </svg>
        </div>

        <div class="mc-sec-meta">
          <div class="mc-sec-kicker">SECCIÓN 2</div>
          <h2 class="mc-sec-title">Datos fiscales</h2>
          <div class="mc-sec-sub">Razón social, RFC, domicilio fiscal y facturación</div>
        </div>

        <button type="button" class="mc-sec-toggle" aria-expanded="true" aria-controls="mc-sec-body-s2" data-mc-toggle="s2" title="Desplegar">+</button>
      </div>

      <div id="mc-sec-body-s2" class="mc-sec-body">
        <div class="mc-subgrid" style="margin-bottom:.85rem;">
          <div class="mc-sub">
            <div class="mc-sub-left">
              <div class="mc-sub-title">Datos de facturación</div>
              <div class="mc-sub-desc">Se usan para factura y para PDF del estado de cuenta</div>
            </div>
            <div class="mc-sub-right">
              <a class="mc-go" href="#" data-open-billing-modal title="Abrir" aria-label="Abrir datos de facturación">›</a>
            </div>
          </div>
        </div>

        @if(session('ok'))
          <div style="margin-top:.25rem;padding:.65rem .85rem;border-radius:14px;border:1px solid rgba(34,197,94,.25);background:rgba(34,197,94,.08);font-weight:800;">
            {{ session('ok') }}
          </div>
        @endif
        @if($errors->any())
          <div style="margin-top:.25rem;padding:.65rem .85rem;border-radius:14px;border:1px solid rgba(239,68,68,.25);background:rgba(239,68,68,.08);font-weight:800;">
            Revisa los campos marcados: {{ $errors->first() }}
          </div>
        @endif
      </div>
    </section>

    {{-- Sección 3 --}}
    <section class="mc-section" data-mc-section="s3" data-open="0">
      <div class="mc-sec-head">
        <div class="mc-sec-ico" aria-hidden="true">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <path d="M7 2h10a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="2"/>
            <path d="M8 7h8M8 11h8M8 15h6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </div>

        <div class="mc-sec-meta">
          <div class="mc-sec-kicker">SECCIÓN 3</div>
          <h2 class="mc-sec-title">Estado de cuenta</h2>
          <div class="mc-sec-sub">Facturación, periodos y documentos</div>
        </div>

        <button type="button" class="mc-sec-toggle" aria-expanded="false" aria-controls="mc-sec-body-s3" data-mc-toggle="s3" title="Desplegar">+</button>
      </div>

      <div id="mc-sec-body-s3" class="mc-sec-body">
        <div class="mc-subgrid">
          <div class="mc-sub">
            <div class="mc-sub-left">
              <div class="mc-sub-title">Estado de cuenta</div>
              <div class="mc-sub-desc">PDFs, periodos y consumos</div>
            </div>
            <div class="mc-sub-right">
              @if($rtEstadoCuenta)
                <a class="mc-go" href="{{ $rtEstadoCuenta }}" title="Ir">›</a>
              @else
                <span class="mc-badge">ND</span>
              @endif
            </div>
          </div>

          <div class="mc-sub">
            <div class="mc-sub-left">
              <div class="mc-sub-title">Facturas</div>
              <div class="mc-sub-desc">Solicitudes de factura y archivos generados</div>
            </div>
            <div class="mc-sub-right">
              @if($rtInvoicesModal)
                <a class="mc-go"
                   href="#"
                   data-open-invoices-modal
                   data-invoices-url="{{ $rtInvoicesModal }}"
                   title="Ver facturas"
                   aria-label="Ver facturas">›</a>
              @else
                <span class="mc-badge">ND</span>
              @endif
            </div>
          </div>

        </div>

        @if(!$rtInvoicesModal)
          <div class="mc-hint" style="margin-top:10px;">
            Ruta faltante: <code>cliente.mi_cuenta.facturas.index</code>.
          </div>
        @endif
      </div>
    </section>

    {{-- Sección 4 --}}
    <section class="mc-section" data-mc-section="s4" data-open="0">
      <div class="mc-sec-head">
        <div class="mc-sec-ico" aria-hidden="true">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <path d="M3 7h18v10H3V7Z" stroke="currentColor" stroke-width="2"/>
            <path d="M3 10h18" stroke="currentColor" stroke-width="2"/>
            <path d="M7 14h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </div>

        <div class="mc-sec-meta">
          <div class="mc-sec-kicker">SECCIÓN 4</div>
          <h2 class="mc-sec-title">Pagos</h2>
          <div class="mc-sec-sub">Historial de pagos y comprobantes</div>
        </div>

        <button type="button" class="mc-sec-toggle" aria-expanded="false" aria-controls="mc-sec-body-s4" data-mc-toggle="s4" title="Desplegar">+</button>
      </div>

      <div id="mc-sec-body-s4" class="mc-sec-body">
        <div class="mc-subgrid">
          <div class="mc-sub">
            <div class="mc-sub-left">
              <div class="mc-sub-title">Mis pagos</div>
              <div class="mc-sub-desc">Todo lo que has pagado o comprado</div>
            </div>
            <div class="mc-sub-right">
              @if($rtMisPagos)
                <a class="mc-go" href="#" data-open-payments-modal title="Ver mis pagos" aria-label="Ver mis pagos">›</a>
              @else
                <span class="mc-badge">ND</span>
              @endif
            </div>
          </div>
        </div>

        @if(!$rtMisPagos)
          <div class="mc-hint" style="margin-top:10px;">
            Ruta faltante: <code>cliente.mi_cuenta.pagos</code>.
          </div>
        @endif
      </div>
    </section>

  </div>
</div>

<div id="mcOverlay" aria-hidden="true"></div>

{{-- ===========================================================
   MODAL: CONFIGURACIÓN
=========================================================== --}}
<dialog class="mc-modal" id="configModal" aria-label="Configuración">
  <div class="mc-modal-top">
    <div>
      <h3 class="mc-modal-title">Configuración</h3>
      <p class="mc-modal-sub">Ajustes de perfil, seguridad y personalización.</p>
    </div>
    <button type="button" class="mc-modal-close" data-close-config-modal aria-label="Cerrar">✕</button>
  </div>

  <div class="mc-modal-body">
    <div class="mc-tabs">
      <button type="button" class="mc-tab is-active" data-mc-tab="brand">Personalización</button>
      <button type="button" class="mc-tab" data-mc-tab="profile">Perfil</button>
      <button type="button" class="mc-tab" data-mc-tab="security">Seguridad</button>
      <button type="button" class="mc-tab" data-mc-tab="prefs">Preferencias</button>
    </div>

    {{-- PANE: Personalización --}}
    <section class="mc-pane" data-mc-pane="brand">
      <div class="mc-form-head" style="border-radius:14px;border:1px solid var(--mc-line);">
        <h4 class="mc-form-title" style="font-size:.94rem;">
          <span style="display:inline-flex;width:10px;height:10px;border-radius:999px;background:rgba(225,29,72,.6)"></span>
          Marca del cliente (UI)
        </h4>
        <p class="mc-form-sub">Logo, nombre visible en la UI y color/acento.</p>
      </div>

      <div style="padding:14px 0 0;">
        <form method="POST" action="{{ $rtBrandUpdate ?: '#' }}" enctype="multipart/form-data" @if(!$canBrandUpdate) onsubmit="return false;" @endif>
          @csrf

          <div class="mc-two">
            <div class="mc-field">
              <label class="mc-label">Nombre de marca (UI)</label>
              <input class="mc-input" name="brand_name" id="brandNameInput"
                     value="{{ old('brand_name', $brandName) }}"
                     placeholder="Nombre comercial para mostrar en la UI">
              <div class="mc-hint">Si lo dejas vacío, usa razón social.</div>
            </div>

            <div class="mc-field">
              <label class="mc-label">Color/acento (UI)</label>
              <input class="mc-input" type="color" name="brand_accent" id="brandAccentInput"
                     value="{{ old('brand_accent', $brandAccent) }}">
              <div class="mc-hint">Se aplica al instante (solo UI). Luego lo persistimos al guardar.</div>
            </div>
          </div>

          <div class="mc-field" style="margin-top:.8rem;">
            <label class="mc-label">Logo (PNG/JPG/WEBP)</label>
            <input class="mc-input" type="file" name="logo" id="brandLogoInput" accept="image/png,image/jpeg,image/webp">
            <div class="mc-hint">Recomendado: 512×512, máximo 2MB.</div>
          </div>

          <div style="display:flex; gap:.6rem; justify-content:flex-end; margin-top:14px;">
            <button type="button" class="mc-btn" data-close-config-modal>Cancelar</button>
            <button type="submit" class="mc-btn mc-btn-primary" @if(!$canBrandUpdate) aria-disabled="true" @endif>
              Guardar personalización
            </button>
          </div>

          @if(!$canBrandUpdate)
            <div class="mc-hint" style="margin-top:10px;">
              Ruta faltante: <code>cliente.mi_cuenta.brand.update</code>.
            </div>
          @endif
        </form>
      </div>
    </section>

    {{-- PANE: Perfil --}}
    <section class="mc-pane" data-mc-pane="profile" hidden>
      <div class="mc-form-head" style="border-radius:14px;border:1px solid var(--mc-line);">
        <h4 class="mc-form-title" style="font-size:.94rem;">
          <span style="display:inline-flex;width:10px;height:10px;border-radius:999px;background:rgba(16,185,129,.65)"></span>
          Perfil
        </h4>
        <p class="mc-form-sub">Nombre, correo y contacto.</p>
      </div>

      <div style="padding:14px 0 0;">
        <form method="POST" action="{{ $rtProfileUpdate ?: '#' }}" @if(!$canProfileUpdate) onsubmit="return false;" @endif>
          @csrf

          <div class="mc-two">
            <div class="mc-field">
              <label class="mc-label">Nombre</label>
              <input class="mc-input" name="nombre" id="profileNameInput"
                     value="{{ old('nombre', (string)($user?->nombre ?? $user?->name ?? '')) }}">
            </div>
            <div class="mc-field">
              <label class="mc-label">Email</label>
              <input class="mc-input" name="email" id="profileEmailInput"
                     value="{{ old('email', (string)($user?->email ?? '')) }}">
            </div>
          </div>

          <div class="mc-two" style="margin-top:.8rem;">
            <div class="mc-field">
              <label class="mc-label">Teléfono</label>
              <input class="mc-input" name="telefono" value="{{ old('telefono', (string)($user?->telefono ?? '')) }}">
            </div>
            <div class="mc-field">
              <label class="mc-label">Puesto</label>
              <input class="mc-input" name="puesto" value="{{ old('puesto', (string)($user?->puesto ?? '')) }}">
            </div>
          </div>

          <div style="display:flex; gap:.6rem; justify-content:flex-end; margin-top:14px;">
            <button type="button" class="mc-btn" data-close-config-modal>Cancelar</button>
            <button type="submit" class="mc-btn mc-btn-primary" @if(!$canProfileUpdate) aria-disabled="true" @endif>
              Guardar perfil
            </button>
          </div>

          @if(!$canProfileUpdate)
            <div class="mc-hint" style="margin-top:10px;">
              Ruta faltante: <code>cliente.mi_cuenta.profile.update</code>.
            </div>
          @endif
        </form>
      </div>
    </section>

    {{-- PANE: Seguridad --}}
    <section class="mc-pane" data-mc-pane="security" hidden>
      <div class="mc-form-head" style="border-radius:14px;border:1px solid var(--mc-line);">
        <h4 class="mc-form-title" style="font-size:.94rem;">
          <span style="display:inline-flex;width:10px;height:10px;border-radius:999px;background:rgba(59,130,246,.65)"></span>
          Seguridad
        </h4>
        <p class="mc-form-sub">Cambio de contraseña.</p>
      </div>

      <div style="padding:14px 0 0;">
        <form method="POST" action="{{ $rtSecurityUpdate ?: '#' }}" @if(!$canSecurityUpdate) onsubmit="return false;" @endif>
          @csrf

          <div class="mc-two">
            <div class="mc-field">
              <label class="mc-label">Contraseña actual</label>
              <input class="mc-input" type="password" name="current_password" autocomplete="current-password" placeholder="••••••••">
            </div>
            <div class="mc-field">
              <label class="mc-label">Nueva contraseña</label>
              <input class="mc-input" type="password" name="password" autocomplete="new-password" placeholder="••••••••" required>
            </div>
          </div>

          <div class="mc-field" style="margin-top:.8rem;">
            <label class="mc-label">Confirmar nueva contraseña</label>
            <input class="mc-input" type="password" name="password_confirmation" autocomplete="new-password" placeholder="••••••••" required>
          </div>

          <div style="display:flex; gap:.6rem; justify-content:flex-end; margin-top:14px;">
            <button type="button" class="mc-btn" data-close-config-modal>Cancelar</button>
            <button type="submit" class="mc-btn mc-btn-primary" @if(!$canSecurityUpdate) aria-disabled="true" @endif>
              Actualizar contraseña
            </button>
          </div>

          @if(!$canSecurityUpdate)
            <div class="mc-hint" style="margin-top:10px;">
              Ruta faltante: <code>cliente.mi_cuenta.security.update</code>.
            </div>
          @endif
        </form>
      </div>
    </section>

    {{-- PANE: Preferencias --}}
    <section class="mc-pane" data-mc-pane="prefs" hidden>
      <div class="mc-form-head" style="border-radius:14px;border:1px solid var(--mc-line);">
        <h4 class="mc-form-title" style="font-size:.94rem;">
          <span style="display:inline-flex;width:10px;height:10px;border-radius:999px;background:rgba(148,163,184,.9)"></span>
          Preferencias (UI)
        </h4>
        <p class="mc-form-sub">Opcional: tema, zona horaria, etc.</p>
      </div>

      <div style="padding:14px 0 0;">
        @php
          $uiTheme = (string)session('client_ui.theme','system');
          $uiTz    = (string)session('client_ui.timezone','America/Mexico_City');
        @endphp

        <form method="POST" action="{{ $rtPrefsUpdate ?: '#' }}" @if(!$canPrefsUpdate) onsubmit="return false;" @endif>
          @csrf

          <div class="mc-two">
            <div class="mc-field">
              <label class="mc-label">Tema</label>
              <select class="mc-input mc-select" name="theme">
                <option value="system" @selected($uiTheme==='system')>Sistema</option>
                <option value="light"  @selected($uiTheme==='light')>Claro</option>
                <option value="dark"   @selected($uiTheme==='dark')>Oscuro</option>
              </select>
            </div>
            <div class="mc-field">
              <label class="mc-label">Zona horaria</label>
              <input class="mc-input" name="timezone" value="{{ old('timezone',$uiTz) }}">
            </div>
          </div>

          <div style="display:flex; gap:.6rem; justify-content:flex-end; margin-top:14px;">
            <button type="button" class="mc-btn" data-close-config-modal>Cancelar</button>
            <button type="submit" class="mc-btn mc-btn-primary" @if(!$canPrefsUpdate) aria-disabled="true" @endif>
              Guardar preferencias
            </button>
          </div>

          @if(!$canPrefsUpdate)
            <div class="mc-hint" style="margin-top:10px;">
              Ruta faltante: <code>cliente.mi_cuenta.preferences.update</code>.
            </div>
          @endif
        </form>
      </div>
    </section>
  </div>
</dialog>

{{-- ===========================================================
   MODAL: Datos de facturación
=========================================================== --}}
<dialog class="mc-modal" id="billingModal" aria-label="Datos de facturación">
  <div class="mc-modal-top">
    <div>
      <h3 class="mc-modal-title">Datos de facturación</h3>
      <p class="mc-modal-sub">Estos datos se usarán para generar tu FACTURA y también se imprimirán en el PDF del Estado de cuenta.</p>
    </div>
    <button type="button" class="mc-modal-close" data-close-billing-modal aria-label="Cerrar">✕</button>
  </div>

  <div class="mc-modal-body">
    <div class="mc-form-head" style="border-radius:14px;border:1px solid var(--mc-line);">
      <h4 class="mc-form-title" style="font-size:.94rem;">
        <span style="display:inline-flex;width:10px;height:10px;border-radius:999px;background:rgba(225,29,72,.6)"></span>
        Datos para PDF / Facturación
      </h4>
      <p class="mc-form-sub">Completa o actualiza tu información fiscal y preferencias de impresión.</p>
    </div>

    <div class="mc-form-body" style="padding:14px 0 0;">
      <form method="POST" action="{{ $rtBillingUpdate ?: '#' }}" @if(!$rtBillingUpdate) onsubmit="return false;" @endif>
        @csrf

        <div class="mc-form-grid">
          <div class="mc-field">
            <label class="mc-label">Razón social</label>
            <input class="mc-input" name="razon_social" value="{{ old('razon_social', $billing['razon_social']) }}" placeholder="Razón social" required>
          </div>

          <div class="mc-field">
            <label class="mc-label">Nombre comercial (opcional)</label>
            <input class="mc-input" name="nombre_comercial" value="{{ old('nombre_comercial', $billing['nombre_comercial']) }}" placeholder="Nombre comercial">
          </div>

          <div class="mc-field">
            <label class="mc-label">RFC</label>
            <input class="mc-input" name="rfc" value="{{ old('rfc', $billing['rfc']) }}" placeholder="RFC" required>
          </div>

          <div class="mc-field">
            <label class="mc-label">Correo</label>
            <input class="mc-input" type="email" name="correo" value="{{ old('correo', $billing['correo']) }}" placeholder="correo@dominio.com" required>
          </div>

          <div class="mc-field">
            <label class="mc-label">Teléfono (opcional)</label>
            <input class="mc-input" name="telefono" value="{{ old('telefono', $billing['telefono']) }}" placeholder="55 0000 0000">
          </div>

          <div class="mc-field">
            <label class="mc-label">País</label>
            <input class="mc-input" name="pais" value="{{ old('pais', $billing['pais']) }}" placeholder="México">
          </div>

          <div class="mc-field">
            <label class="mc-label">Calle</label>
            <input class="mc-input" name="calle" value="{{ old('calle', $billing['calle']) }}" placeholder="Calle">
          </div>

          <div class="mc-field" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.8rem;">
            <div class="mc-field" style="gap:.35rem;">
              <label class="mc-label">No. ext</label>
              <input class="mc-input" name="no_ext" value="{{ old('no_ext', $billing['no_ext']) }}" placeholder="Ext">
            </div>
            <div class="mc-field" style="gap:.35rem;">
              <label class="mc-label">No. int</label>
              <input class="mc-input" name="no_int" value="{{ old('no_int', $billing['no_int']) }}" placeholder="Int">
            </div>
          </div>

          <div class="mc-field">
            <label class="mc-label">Colonia</label>
            <input class="mc-input" name="colonia" value="{{ old('colonia', $billing['colonia']) }}" placeholder="Colonia">
          </div>

          <div class="mc-field">
            <label class="mc-label">Municipio / Alcaldía</label>
            <input class="mc-input" name="municipio" value="{{ old('municipio', $billing['municipio']) }}" placeholder="Municipio / Alcaldía">
          </div>

          <div class="mc-field">
            <label class="mc-label">Estado</label>
            <input class="mc-input" name="estado" value="{{ old('estado', $billing['estado']) }}" placeholder="Estado">
          </div>

          <div class="mc-field">
            <label class="mc-label">Código postal</label>
            <input class="mc-input" name="cp" value="{{ old('cp', $billing['cp']) }}" placeholder="CP">
          </div>

          <div class="mc-field">
            <label class="mc-label">Régimen fiscal (opcional)</label>
            <input class="mc-input" name="regimen_fiscal" value="{{ old('regimen_fiscal', $billing['regimen_fiscal']) }}" placeholder="601">
          </div>

          <div class="mc-field">
            <label class="mc-label">Uso CFDI (opcional)</label>
            <input class="mc-input" name="uso_cfdi" value="{{ old('uso_cfdi', $billing['uso_cfdi']) }}" placeholder="G03">
          </div>

          <div class="mc-field">
            <label class="mc-label">Método de pago (opcional)</label>
            <input class="mc-input" name="metodo_pago" value="{{ old('metodo_pago', $billing['metodo_pago']) }}" placeholder="PUE/PPD">
          </div>

          <div class="mc-field">
            <label class="mc-label">Forma de pago (opcional)</label>
            <input class="mc-input" name="forma_pago" value="{{ old('forma_pago', $billing['forma_pago']) }}" placeholder="03">
          </div>

          <div class="mc-field mc-span2">
            <label class="mc-label">Leyenda en PDF (opcional)</label>
            <input class="mc-input" name="leyenda_pdf" value="{{ old('leyenda_pdf', $billing['leyenda_pdf']) }}" placeholder="Texto que deseas que aparezca en tu PDF.">
            <div class="mc-hint">Sugerencia: aquí puedes poner referencias internas, instrucciones, o un texto legal breve.</div>
          </div>
        </div>

        <div class="mc-checkrow">
          <label class="mc-check">
            <input type="checkbox" name="pdf_mostrar_nombre_comercial" value="1"
              @if((int)old('pdf_mostrar_nombre_comercial', $billing['pdf_mostrar_nombre_comercial'])===1) checked @endif>
            Mostrar nombre comercial en PDF (si existe)
          </label>

          <label class="mc-check">
            <input type="checkbox" name="pdf_mostrar_telefono" value="1"
              @if((int)old('pdf_mostrar_telefono', $billing['pdf_mostrar_telefono'])===1) checked @endif>
            Mostrar teléfono en PDF
          </label>
        </div>

        <div class="mc-modal-actions">
          <button type="button" class="mc-btn" data-close-billing-modal>Cancelar</button>
          <button type="submit" class="mc-btn mc-btn-primary" @if(!$rtBillingUpdate) aria-disabled="true" @endif>Guardar datos</button>
        </div>

        @if(!$rtBillingUpdate)
          <div class="mc-hint" style="margin-top:10px;">
            Ruta faltante: <code>cliente.mi_cuenta.billing.update</code>.
          </div>
        @endif
      </form>
    </div>
  </div>
</dialog>

{{-- ===========================================================
   MODAL: MIS PAGOS
=========================================================== --}}
<dialog class="mc-modal" id="paymentsModal" aria-label="Mis pagos">
  <div class="mc-modal-top">
    <div>
      <h3 class="mc-modal-title">Mis pagos</h3>
      <p class="mc-modal-sub">Listado de todos los pagos y compras realizados en tu cuenta.</p>
    </div>
    <button type="button" class="mc-modal-close" data-close-payments-modal aria-label="Cerrar">✕</button>
  </div>

  <div class="mc-modal-body">
    <div class="mc-pay-head">
      <div class="mc-pay-left">
        <div class="mc-pay-title">Historial</div>
        <div class="mc-pay-sub">Incluye suscripción, módulos, consumos y otros cargos.</div>
      </div>
      <div class="mc-pay-right">
        <button type="button" class="mc-btn" data-refresh-payments>Actualizar</button>
      </div>
    </div>

    <div id="mcPayState" class="mc-pay-state" data-state="idle">
      <div class="mc-pay-skel">
        <div class="bar"></div><div class="bar"></div><div class="bar"></div>
      </div>
      <div class="mc-pay-empty" hidden>
        Aún no tienes pagos registrados.
      </div>
      <div class="mc-pay-error" hidden>
        No se pudo cargar el historial. Revisa el log.
      </div>
    </div>

    <div class="mc-pay-tablewrap" id="mcPayTableWrap" hidden>
      <table class="mc-pay-table">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Concepto</th>
            <th>Periodo</th>
            <th style="text-align:right">Monto</th>
            <th>Método</th>
            <th>Estatus</th>
            <th>Comprobantes</th>
          </tr>
        </thead>
        <tbody id="mcPayTbody"></tbody>
      </table>
    </div>
  </div>

  <div class="mc-modal-actions">
    <button type="button" class="mc-btn" data-close-payments-modal>Cerrar</button>
  </div>
</dialog>

{{-- ===========================================================
   MODAL: FACTURAS (LISTADO REAL EN IFRAME)
=========================================================== --}}
<dialog class="mc-modal" id="invoicesModal" aria-label="Facturas">
  <div class="mc-modal-top">
    <div>
      <h3 class="mc-modal-title">Facturas</h3>
      <p class="mc-modal-sub">Solicitudes de factura y archivos generados.</p>
    </div>
    <button type="button" class="mc-modal-close" data-close-invoices-modal aria-label="Cerrar">✕</button>
  </div>

  <div class="mc-modal-body" style="padding:0;">
    <iframe
      id="mcInvFrame"
      src="about:blank"
      style="width:100%;height: calc(min(88vh, 820px) - 72px); border:0; display:block;"
      loading="lazy"
    ></iframe>
  </div>

  <div class="mc-modal-actions">
    <button type="button" class="mc-btn" data-close-invoices-modal>Cerrar</button>
  </div>
</dialog>

@push('scripts')
<script>
(function(){
  // ===== Accordions (+) =====
  function toggle(sectionId){
    const sec = document.querySelector('.mc-section[data-mc-section="'+sectionId+'"]');
    if(!sec) return;

    const open = sec.getAttribute('data-open') === '1';
    sec.setAttribute('data-open', open ? '0' : '1');

    const btn = sec.querySelector('[data-mc-toggle="'+sectionId+'"]');
    if(btn){
      btn.setAttribute('aria-expanded', open ? 'false' : 'true');
    }
  }

  // ===== Overlay =====
  const overlay = document.getElementById('mcOverlay');

  function overlayOn(){
    document.body.classList.add('mc-modal-open');
    if (overlay) overlay.classList.add('is-on');
  }
  function overlayOff(){
    document.body.classList.remove('mc-modal-open');
    if (overlay) overlay.classList.remove('is-on');
  }

  // ===== Modales =====
  const billingModal  = document.getElementById('billingModal');
  const configModal   = document.getElementById('configModal');
  const paymentsModal = document.getElementById('paymentsModal');
  const invoicesModal = document.getElementById('invoicesModal');
  const invFrame      = document.getElementById('mcInvFrame');

  function safeShow(dlg){
    if (!dlg) return;

    // cierra otros
    [billingModal, configModal, paymentsModal, invoicesModal].forEach(d => {
      if (!d || d === dlg) return;
      try { d.close?.(); } catch(e) {}
      d.removeAttribute?.('open');
    });

    overlayOn();
    if (typeof dlg.showModal === 'function') {
      try { dlg.showModal(); return; } catch(e) {}
    }
    dlg.setAttribute('open','open');
  }

  function safeClose(dlg){
    if (!dlg) return;
    try { dlg.close?.(); } catch(e) {}
    dlg.removeAttribute('open');

    const anyOpen = !!(
      (billingModal?.hasAttribute('open') || billingModal?.open) ||
      (configModal?.hasAttribute('open')  || configModal?.open)  ||
      (paymentsModal?.hasAttribute('open') || paymentsModal?.open) ||
      (invoicesModal?.hasAttribute('open') || invoicesModal?.open)
    );

    if (!anyOpen) overlayOff();
  }

  function openBilling(){ safeShow(billingModal); }
  function closeBilling(){ safeClose(billingModal); }
  function openConfig(){ safeShow(configModal); }
  function closeConfig(){ safeClose(configModal); }
  function openPayments(){ safeShow(paymentsModal); }
  function closePayments(){ safeClose(paymentsModal); }

  function openInvoices(url){
    safeShow(invoicesModal);
    if (!invFrame) return;

    // ✅ FORZAR SIEMPRE: embed=1 + theme=light + cache-bust
    try{
      const u = new URL(url, window.location.origin);
      u.searchParams.set('embed','1');
      u.searchParams.set('theme','light');
      u.searchParams.set('_t', Date.now().toString());
      invFrame.src = u.toString();
    }catch(e){
      const glue = url.includes('?') ? '&' : '?';
      invFrame.src = url + glue + 'embed=1&theme=light&_t=' + Date.now();
    }
  }


  function closeInvoices(){
    if (invFrame) invFrame.src = 'about:blank';
    safeClose(invoicesModal);
  }

  // ===== Tabs (config) =====
  function handleConfigTab(tab){
    const name = tab.getAttribute('data-mc-tab');
    if(!name) return;

    const tabs = Array.from(configModal.querySelectorAll('[data-mc-tab]'));
    const panes = Array.from(configModal.querySelectorAll('[data-mc-pane]'));

    tabs.forEach(t=> t.classList.toggle('is-active', t.getAttribute('data-mc-tab') === name));
    panes.forEach(p=>{
      const on = p.getAttribute('data-mc-pane') === name;
      if(on) p.removeAttribute('hidden'); else p.setAttribute('hidden','hidden');
    });
  }

  // ===== Mis Pagos (fetch + render) =====
  const payState    = document.getElementById('mcPayState');
  const payEmpty    = payState ? payState.querySelector('.mc-pay-empty') : null;
  const payError    = payState ? payState.querySelector('.mc-pay-error') : null;
  const paySkel     = payState ? payState.querySelector('.mc-pay-skel') : null;
  const payWrap     = document.getElementById('mcPayTableWrap');
  const payTbody    = document.getElementById('mcPayTbody');

  const rtMisPagos  = @json($rtMisPagos);

  function setPayState(state){
    if(!payState) return;
    payState.setAttribute('data-state', state);

    if (paySkel)  paySkel.style.display = (state === 'loading') ? 'block' : 'none';
    if (payEmpty) payEmpty.hidden = (state !== 'empty');
    if (payError) payError.hidden = (state !== 'error');
    if (payWrap)  payWrap.hidden = (state !== 'ready');
  }

  function fmtMoney(amount, currency){
    const c = (currency || 'MXN').toUpperCase();
    let n = 0;
    try { n = parseFloat(String(amount).replace(/[^0-9.\-]/g,'')) || 0; } catch(e) { n = 0; }
    try {
      return new Intl.NumberFormat('es-MX', { style:'currency', currency: c, maximumFractionDigits: 2 }).format(n);
    } catch(e){
      return (c + ' ' + n.toFixed(2));
    }
  }

  function pillStatus(st){
    const s = String(st || '—').toUpperCase();
    let cls = 'mc-pay-pill neutral';
    if (s.includes('PAID') || s.includes('PAGAD') || s.includes('SUCC') || s.includes('COMPLET')) cls = 'mc-pay-pill ok';
    else if (s.includes('PEND') || s.includes('OPEN') || s.includes('DUE')) cls = 'mc-pay-pill neutral';
    else if (s.includes('FAIL') || s.includes('CANC') || s.includes('REFUND') || s.includes('REEMB')) cls = 'mc-pay-pill bad';
    return '<span class="'+cls+'">'+s+'</span>';
  }

  function esc(s){
    return String(s ?? '').replace(/[&<>"']/g, function(m){
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]);
    });
  }

  function renderPayments(rows){
    if(!payTbody) return;
    payTbody.innerHTML = '';

    const LABELS = {
      date: 'Fecha',
      concept: 'Concepto',
      period: 'Periodo',
      amount: 'Monto',
      method: 'Método',
      status: 'Estatus',
      proofs: 'Comprobantes'
    };

    rows.forEach(r=>{
      const date = esc(r.date || '—');
      const concept = esc(r.concept || 'Pago');
      const period = esc(r.period || '—');
      const amt = fmtMoney(r.amount || 0, r.currency || 'MXN');
      const method = esc(r.method || '—');
      const status = pillStatus(r.status || '—');

      let proofs = '';
      const inv = (r.invoice || '').trim();
      const rec = (r.receipt || '').trim();
      if (inv) proofs += '<a class="mc-pay-link" href="'+esc(inv)+'" target="_blank" rel="noopener">Factura</a>';
      if (rec) proofs += (proofs ? '<span class="mc-pay-dot">•</span>' : '') + '<a class="mc-pay-link" href="'+esc(rec)+'" target="_blank" rel="noopener">Recibo</a>';
      if (!proofs) proofs = '<span class="mc-pay-muted">—</span>';

      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td data-label="${LABELS.date}">
          <div class="mc-pay-date">${date}</div>
          <div class="mc-pay-ref">${esc(r.reference || '')}</div>
        </td>

        <td data-label="${LABELS.concept}">
          <div class="mc-pay-concept">${concept}</div>
          <div class="mc-pay-source">${esc(r.source || '')}</div>
        </td>

        <td data-label="${LABELS.period}">${period}</td>

        <td data-label="${LABELS.amount}" style="text-align:right">
          <span class="mc-pay-amt">${esc(amt)}</span>
        </td>

        <td data-label="${LABELS.method}">${method}</td>

        <td data-label="${LABELS.status}">${status}</td>

        <td data-label="${LABELS.proofs}">${proofs}</td>
      `;
      payTbody.appendChild(tr);
    });
  }

  async function loadPayments(){
    if(!rtMisPagos){
      setPayState('error');
      return;
    }
    setPayState('loading');

    try{
      const res = await fetch(rtMisPagos, { headers: { 'Accept':'application/json' } });
      if(!res.ok) throw new Error('HTTP '+res.status);
      const json = await res.json();
      const rows = (json && json.ok && Array.isArray(json.rows)) ? json.rows : [];
      if(rows.length <= 0){
        setPayState('empty');
        return;
      }
      renderPayments(rows);
      setPayState('ready');
    }catch(e){
      setPayState('error');
    }
  }

  // ===== Delegación global =====
  document.addEventListener('click', function(e){
    const tog = e.target.closest('[data-mc-toggle]');
    if(tog){
      e.preventDefault();
      toggle(tog.getAttribute('data-mc-toggle'));
      return;
    }

    if (e.target.closest('[data-open-billing-modal]')) { e.preventDefault(); openBilling(); return; }
    if (e.target.closest('[data-close-billing-modal]')){ e.preventDefault(); closeBilling(); return; }

    if (e.target.closest('[data-open-config-modal]')) { e.preventDefault(); openConfig(); return; }
    if (e.target.closest('[data-close-config-modal]')){ e.preventDefault(); closeConfig(); return; }

    if (e.target.closest('[data-open-payments-modal]')) { e.preventDefault(); openPayments(); loadPayments(); return; }
    if (e.target.closest('[data-close-payments-modal]')){ e.preventDefault(); closePayments(); return; }

    const invOpen = e.target.closest('[data-open-invoices-modal]');
    if (invOpen){
      e.preventDefault();
      const url = invOpen.getAttribute('data-invoices-url') || '';
      if (url) openInvoices(url);
      return;
    }

    if (e.target.closest('[data-close-invoices-modal]')){
      e.preventDefault();
      closeInvoices();
      return;
    }

    const refresh = e.target.closest('[data-refresh-payments]');
    if (refresh){ e.preventDefault(); loadPayments(); return; }

    const tab = e.target.closest('[data-mc-tab]');
    if(tab && configModal && configModal.contains(tab)){
      e.preventDefault();
      handleConfigTab(tab);
      return;
    }
  });

  if(overlay){
    overlay.addEventListener('click', function(){
      if (invoicesModal?.open || invoicesModal?.hasAttribute('open')) return closeInvoices();
      if (paymentsModal?.open || paymentsModal?.hasAttribute('open')) return closePayments();
      if (configModal?.open || configModal?.hasAttribute('open')) return closeConfig();
      if (billingModal?.open || billingModal?.hasAttribute('open')) return closeBilling();
      overlayOff();
    });
  }

  [billingModal, configModal, paymentsModal, invoicesModal].forEach((dlg)=>{
    if(!dlg) return;
    dlg.addEventListener('cancel', function(ev){
      ev.preventDefault();
      safeClose(dlg);
    });
    dlg.addEventListener('close', function(){
      const anyOpen = !!(
        billingModal?.open || configModal?.open || paymentsModal?.open || invoicesModal?.open ||
        billingModal?.hasAttribute('open') || configModal?.hasAttribute('open') || paymentsModal?.hasAttribute('open') || invoicesModal?.hasAttribute('open')
      );
      if (!anyOpen) overlayOff();
    });
  });

  // ===== Preview UI mínimo: solo acento =====
  const brandAccentInput = document.getElementById('brandAccentInput');
  function applyAccent(hex){
    const v = (hex || '').trim() || '#E11D48';
    document.documentElement.style.setProperty('--mc-accent', v);
    const header = document.querySelector('.mc-card.mc-header');
    if (header) header.style.setProperty('--mc-accent', v);
  }
  applyAccent(@json($brandAccent));
  if (brandAccentInput){
    brandAccentInput.addEventListener('input', function(){
      applyAccent(brandAccentInput.value);
    });
  }

  // Si vienes con errores de validación del billing modal, abrimos el billing modal automáticamente
  const hasErrors = @json($errors->any());
  if (hasErrors){
    openBilling();
  }
})();
</script>
@endpush

@endsection
