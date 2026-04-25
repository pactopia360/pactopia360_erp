{{-- C:\wamp64\www\pactopia360_erp\resources\views\cliente\mi_cuenta\index.blade.php --}}
@extends('layouts.cliente')
@section('title','Mi cuenta · Pactopia360')
@section('pageClass','page-mi-cuenta')

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/client/css/mi-cuenta.css') }}?v=5.0">
  <script src="{{ asset('assets/client/js/mi-cuenta.js') }}?v=1.0" defer></script>
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

  $rtMisPagos = Route::has('cliente.mi_cuenta.pagos')
    ? route('cliente.mi_cuenta.pagos')
    : null;

  $rtInvoicesModal = Route::has('cliente.mi_cuenta.facturas.index')
      ? route('cliente.mi_cuenta.facturas.index', ['embed' => 1, 'theme' => 'light'])
      : null;

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

  $errorBagKeys = $errors->keys();

  $openModalOnLoad = null;
  $openConfigTabOnLoad = 'brand';

  $billingKeys = [
    'razon_social','nombre_comercial','rfc','correo','telefono','pais','calle','no_ext','no_int',
    'colonia','municipio','estado','cp','regimen_fiscal','uso_cfdi','metodo_pago','forma_pago',
    'leyenda_pdf','pdf_mostrar_nombre_comercial','pdf_mostrar_telefono',
  ];

  $profileKeys = ['nombre','email','telefono','puesto'];
  $securityKeys = ['current_password','password','password_confirmation'];
  $prefsKeys = ['theme','timezone','language','demo_mode'];
  $brandKeys = ['brand_name','brand_accent','logo'];
  $invoiceKeys = ['period','notes'];

  if (!empty($errorBagKeys)) {
      if (count(array_intersect($errorBagKeys, $billingKeys)) > 0) {
          $openModalOnLoad = 'billing';
      } elseif (count(array_intersect($errorBagKeys, $profileKeys)) > 0) {
          $openModalOnLoad = 'config';
          $openConfigTabOnLoad = 'profile';
      } elseif (count(array_intersect($errorBagKeys, $securityKeys)) > 0) {
          $openModalOnLoad = 'config';
          $openConfigTabOnLoad = 'security';
      } elseif (count(array_intersect($errorBagKeys, $prefsKeys)) > 0) {
          $openModalOnLoad = 'config';
          $openConfigTabOnLoad = 'prefs';
      } elseif (count(array_intersect($errorBagKeys, $brandKeys)) > 0) {
          $openModalOnLoad = 'config';
          $openConfigTabOnLoad = 'brand';
      } elseif (count(array_intersect($errorBagKeys, $invoiceKeys)) > 0) {
          $openModalOnLoad = 'invoices';
      } else {
          $openModalOnLoad = 'billing';
      }
  }
@endphp

<div class="mc-wrap">
  <div class="mc-page mc-page-v9">

    <section class="mc-hero-account mc-hero-account-v9" style="--mc-accent: {{ $brandAccent }};">
      <div class="mc-hero-left">
        <div class="mc-hero-chip">
          <span></span>
          MI CUENTA · PORTAL USUARIO
        </div>

        <h1>Mi cuenta</h1>

        <p>
          Administra tu información personal, empresas, configuración fiscal,
          documentos y todo lo relacionado con tu cuenta en Pactopia360.
        </p>
      </div>

      <div class="mc-hero-summary">
        <div class="mc-hero-mini-card">
          <span>Usuario</span>
          <strong title="{{ $userName }}">{{ $userName }}</strong>
          <i>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
              <path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Z" stroke="currentColor" stroke-width="2"/>
              <path d="M4 21a8 8 0 0 1 16 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </i>
        </div>

        <div class="mc-hero-mini-card">
          <span>Correo</span>
          <strong title="{{ $userEmail }}">{{ $userEmail }}</strong>
          <i>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
              <path d="M4 6h16v12H4V6Z" stroke="currentColor" stroke-width="2"/>
              <path d="m4 7 8 6 8-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </i>
        </div>
      </div>
    </section>

    @if(session('ok'))
      <div class="mc-alert mc-alert-ok">{{ session('ok') }}</div>
    @endif

    @if($errors->any())
      <div class="mc-alert mc-alert-bad">
        Revisa los campos marcados: {{ $errors->first() }}
      </div>
    @endif

    <section class="mc-info-grid-v9">
      <article class="mc-info-box">
        <div class="mc-box-head">
          <h2>Información de tu cuenta</h2>
          <a href="{{ $rtPerfil ?: '#' }}" class="mc-mini-btn">Editar</a>
        </div>

        <div class="mc-detail-list">
          <div>
            <span>Nombre</span>
            <strong>{{ $userName }}</strong>
          </div>
          <div>
            <span>Correo</span>
            <strong>{{ $userEmail }}</strong>
          </div>
          <div>
            <span>Marca visible</span>
            <strong>{{ $brandName !== '' ? $brandName : $brandNameFallback }}</strong>
          </div>
          <div>
            <span>Cuenta</span>
            <strong>{{ $status !== '—' ? $status : 'Activa' }}</strong>
          </div>
        </div>
      </article>

      <article class="mc-info-box">
        <div class="mc-box-head">
          <h2>Seguridad y acceso</h2>
          <button type="button" class="mc-mini-btn" data-open-config-modal>Gestionar</button>
        </div>

        <div class="mc-detail-list">
          <div>
            <span>Contraseña</span>
            <strong>••••••••••</strong>
          </div>
          <div>
            <span>Perfil</span>
            <strong>Usuario principal</strong>
          </div>
          <div>
            <span>Preferencias</span>
            <strong>Tema, zona horaria y acceso</strong>
          </div>
          <div>
            <span>Último acceso</span>
            <strong>Sesión actual</strong>
          </div>
        </div>
      </article>
    </section>

    <section class="mc-panel mc-panel-v9">
      <div class="mc-panel-head mc-panel-head-v9">
        <div>
          <h2>Documentos y detalle de cuenta</h2>
          <p>Consulta lo principal de tu cuenta: datos, estados, pagos, facturas y contratos.</p>
        </div>
      </div>

      <div class="mc-dashboard-grid-v9">
        <a class="mc-action-card-v9" href="#" data-open-billing-modal>
          <span class="mc-action-icon green">
            <svg width="21" height="21" viewBox="0 0 24 24" fill="none">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z" stroke="currentColor" stroke-width="2"/>
              <path d="M14 2v6h6M8 13h8M8 17h5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </span>
          <strong>Datos fiscales</strong>
          <small>RFC, razón social y domicilio fiscal.</small>
          <b>›</b>
        </a>

        <a class="mc-action-card-v9" href="{{ $rtEstadoCuenta ?: '#' }}">
          <span class="mc-action-icon purple">
            <svg width="21" height="21" viewBox="0 0 24 24" fill="none">
              <path d="M7 2h10a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="2"/>
              <path d="M8 7h8M8 11h8M8 15h5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </span>
          <strong>Estado de cuenta</strong>
          <small>Cargos, abonos, periodos y saldo.</small>
          <b>›</b>
        </a>

        <a class="mc-action-card-v9" href="#" data-open-payments-modal>
          <span class="mc-action-icon orange">
            <svg width="21" height="21" viewBox="0 0 24 24" fill="none">
              <path d="M3 7h18v10H3V7Z" stroke="currentColor" stroke-width="2"/>
              <path d="M3 10h18M7 14h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </span>
          <strong>Pagos</strong>
          <small>Historial, métodos y comprobantes.</small>
          <b>›</b>
        </a>

        <a class="mc-action-card-v9" href="#" data-open-invoices-modal data-invoices-url="{{ $rtInvoicesModal }}">
          <span class="mc-action-icon blue">
            <svg width="21" height="21" viewBox="0 0 24 24" fill="none">
              <path d="M8 3h8l4 4v14H4V3h4Z" stroke="currentColor" stroke-width="2"/>
              <path d="M16 3v5h5M8 13h8M8 17h6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </span>
          <strong>Facturas</strong>
          <small>Descarga y consulta tus facturas.</small>
          <b>›</b>
        </a>
      </div>
    </section>

    <section class="mc-panel mc-panel-v9">
      <div class="mc-panel-head mc-panel-head-v9">
        <div>
          <h2>Configuración global</h2>
          <p>Ajustes que pertenecen a tu cuenta y se reutilizan en otros módulos.</p>
        </div>
      </div>

      <div class="mc-dashboard-grid-v9">
        <button type="button" class="mc-action-card-v9" data-open-config-modal>
          <span class="mc-action-icon blue">
            <svg width="21" height="21" viewBox="0 0 24 24" fill="none">
              <path d="M12 15.5A3.5 3.5 0 1 0 12 8a3.5 3.5 0 0 0 0 7.5Z" stroke="currentColor" stroke-width="2"/>
              <path d="M19 12a7 7 0 0 1-.13 1.35l2.05 1.6-2 3.46-2.42-.98a7.07 7.07 0 0 1-2.34 1.36L13.8 21h-4l-.36-2.21a7.07 7.07 0 0 1-2.34-1.36l-2.42.98-2-3.46 2.05-1.6A7 7 0 0 1 4.6 12c0-.46.04-.91.13-1.35l-2.05-1.6 2-3.46 2.42.98a7.07 7.07 0 0 1 2.34-1.36L9.8 3h4l.36 2.21a7.07 7.07 0 0 1 2.34 1.36l2.42-.98 2 3.46-2.05 1.6c.09.44.13.89.13 1.35Z" stroke="currentColor" stroke-width="1.5"/>
            </svg>
          </span>
          <strong>Preferencias</strong>
          <small>Tema, zona horaria y seguridad.</small>
          <b>›</b>
        </button>

        <button type="button" class="mc-action-card-v9" data-open-config-modal>
          <span class="mc-action-icon purple">
            <svg width="21" height="21" viewBox="0 0 24 24" fill="none">
              <path d="M4 5h16v14H4V5Z" stroke="currentColor" stroke-width="2"/>
              <path d="m4 16 5-5 4 4 2-2 5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>
          <strong>Logo y marca</strong>
          <small>Logo, color y nombre visible.</small>
          <b>›</b>
        </button>

        <button type="button" class="mc-action-card-v9" data-open-billing-modal>
          <span class="mc-action-icon green">
            <svg width="21" height="21" viewBox="0 0 24 24" fill="none">
              <path d="M4 4h16v16H4V4Z" stroke="currentColor" stroke-width="2"/>
              <path d="M8 8h8M8 12h8M8 16h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </span>
          <strong>Plantilla PDF</strong>
          <small>Datos globales para documentos.</small>
          <b>›</b>
        </button>

        <a class="mc-action-card-v9" href="{{ $rtContratos ?: '#' }}">
          <span class="mc-action-icon orange">
            <svg width="21" height="21" viewBox="0 0 24 24" fill="none">
              <path d="M8 3h8l4 4v14H4V3h4Z" stroke="currentColor" stroke-width="2"/>
              <path d="M16 3v5h5M8 13h8M8 17h6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </span>
          <strong>Contratos</strong>
          <small>Firma, aceptación y documentos legales.</small>
          <b>›</b>
        </a>
      </div>
    </section>

  </div>
</div>

<div id="mcOverlay" aria-hidden="true"></div>

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
      style="width:100%;height: calc(min(88vh, 820px) - 72px); border:0; display:block; background:#fff;"
      loading="lazy"
    ></iframe>
  </div>

  <div class="mc-modal-actions">
    <button type="button" class="mc-btn" data-close-invoices-modal>Cerrar</button>
  </div>
</dialog>
@endsection