{{-- C:\wamp64\www\pactopia360_erp\resources\views\admin\billing\invoicing\settings\index.blade.php --}}
@extends('layouts.admin')

@section('title', 'Facturación · Configuración')
@section('contentLayout', 'contained')
@section('pageClass', 'billing-invoicing-settings-page')

@php
    $settings = is_array($settings ?? null) ? $settings : [];
    $resolved = is_array($resolved ?? null) ? $resolved : [];

    $facturotopiaMode       = old('facturotopia_mode', (string) data_get($settings, 'facturotopia_mode', 'sandbox'));
    $facturotopiaFlow       = old('facturotopia_flow', (string) data_get($settings, 'facturotopia_flow', 'api_comprobantes'));
    $facturotopiaAuthScheme = old('facturotopia_auth_scheme', (string) data_get($settings, 'facturotopia_auth_scheme', 'bearer'));
    $facturotopiaBase       = old('facturotopia_base', (string) data_get($settings, 'facturotopia_base', ''));
    $facturotopiaKeyTest    = old('facturotopia_api_key_test', (string) data_get($settings, 'facturotopia_api_key_test', ''));
    $facturotopiaKeyLive    = old('facturotopia_api_key_live', (string) data_get($settings, 'facturotopia_api_key_live', ''));
    $facturotopiaEmisor     = old('facturotopia_emisor_id', (string) data_get($settings, 'facturotopia_emisor_id', ''));
    $facturotopiaTenancy    = old('facturotopia_tenancy', (string) data_get($settings, 'facturotopia_tenancy', ''));
    $facturotopiaTenHdr     = old('facturotopia_tenancy_header', (string) data_get($settings, 'facturotopia_tenancy_header', 'X-Tenancy'));
    $emailFrom              = old('email_from', (string) data_get($settings, 'email_from', ''));

    $defaultSandboxBase     = (string) data_get($settings, '__sandbox_base_default', 'https://api-demo.facturotopia.com');
    $defaultProductionBase  = (string) data_get($settings, '__production_base_default', 'https://api.facturotopia.com');

    $resolvedBase           = (string) data_get($resolved, 'base', '');
    $resolvedMode           = (string) data_get($resolved, 'mode', '');
    $resolvedFlow           = (string) data_get($resolved, 'flow', '');
    $resolvedAuthScheme     = (string) data_get($resolved, 'auth_scheme', 'bearer');
    $resolvedKey            = (string) data_get($resolved, 'api_key', '');
    $resolvedEmisor         = (string) data_get($resolved, 'emisor_id', '');
    $resolvedTenancy        = (string) data_get($resolved, 'tenancy', '');
    $resolvedTenHdr         = (string) data_get($resolved, 'tenancy_header', '');
    $resolvedEmailFrom      = (string) data_get($resolved, 'email_from', $emailFrom);

    $modeLabel = $resolvedMode === 'production' ? 'Producción' : 'Sandbox';
    $modeBadgeClass = $resolvedMode === 'production' ? 'biset-badge-live' : 'biset-badge-test';

    $authLabel = strtolower($resolvedAuthScheme) === 'apikey' ? 'ApiKey' : 'Bearer';

    $maskKey = static function (string $value): string {
        $value = trim($value);
        if ($value === '') return '—';
        $len = mb_strlen($value);
        if ($len <= 8) return str_repeat('*', $len);
        return mb_substr($value, 0, 6) . str_repeat('*', max(0, $len - 10)) . mb_substr($value, -4);
    };
@endphp

@push('styles')
<style>
  .biset-page{display:grid;gap:18px}
  .biset-hero{
    display:grid;grid-template-columns:minmax(0,1fr) auto;gap:18px;align-items:start;
    padding:24px;border:1px solid var(--card-border);border-radius:24px;
    background:
      radial-gradient(circle at top right, rgba(59,130,246,.10), transparent 34%),
      radial-gradient(circle at bottom left, rgba(16,185,129,.08), transparent 28%),
      linear-gradient(180deg, color-mix(in oklab, var(--card-bg) 95%, white 5%), var(--card-bg));
    box-shadow:0 18px 40px rgba(15,23,42,.07);
  }
  .biset-title{
    margin:0;font-size:clamp(30px,3vw,42px);line-height:1.02;font-weight:900;
    letter-spacing:-.04em;color:var(--text);
  }
  .biset-subtitle{margin:10px 0 0;color:var(--muted);font-size:14px;line-height:1.6;max-width:860px}
  .biset-actions{display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end}
  .biset-btn{
    appearance:none;border:1px solid var(--card-border);background:var(--card-bg);color:var(--text);
    border-radius:14px;padding:10px 15px;font-size:14px;font-weight:800;text-decoration:none;
    display:inline-flex;align-items:center;justify-content:center;gap:8px;cursor:pointer;transition:.18s ease;
    min-height:44px;
  }
  .biset-btn:hover{
    transform:translateY(-1px);
    border-color:color-mix(in oklab, var(--accent) 26%, var(--card-border));
    box-shadow:0 10px 24px rgba(15,23,42,.08)
  }
  .biset-btn-primary{
    background:linear-gradient(180deg, color-mix(in oklab, var(--accent) 92%, white 8%), var(--accent));
    color:#fff;border-color:transparent
  }

  .biset-env-banner{
    display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;
    padding:14px 16px;border-radius:18px;border:1px solid var(--card-border);background:var(--card-bg)
  }
  .biset-env-banner-live{
    border-color:rgba(220,38,38,.16);
    background:linear-gradient(180deg, rgba(220,38,38,.08), rgba(220,38,38,.04));
  }
  .biset-env-banner-test{
    border-color:rgba(59,130,246,.16);
    background:linear-gradient(180deg, rgba(59,130,246,.08), rgba(59,130,246,.04));
  }
  .biset-env-banner-title{font-size:14px;font-weight:900;color:var(--text)}
  .biset-env-banner-sub{font-size:13px;color:var(--muted);margin-top:4px}
  .biset-badge{
    display:inline-flex;align-items:center;justify-content:center;min-height:32px;padding:6px 12px;
    border-radius:999px;font-size:12px;font-weight:900;letter-spacing:.04em;text-transform:uppercase;border:1px solid transparent
  }
  .biset-badge-live{background:rgba(220,38,38,.10);border-color:rgba(220,38,38,.20);color:#b91c1c}
  .biset-badge-test{background:rgba(59,130,246,.10);border-color:rgba(59,130,246,.20);color:#1d4ed8}
  .biset-badge-neutral{background:rgba(148,163,184,.10);border-color:rgba(148,163,184,.20);color:#475569}
  html.theme-dark .biset-badge-live{color:#fca5a5}
  html.theme-dark .biset-badge-test{color:#93c5fd}
  html.theme-dark .biset-badge-neutral{color:#cbd5e1}

  .biset-alert{border:1px solid var(--card-border);background:var(--card-bg);border-radius:16px;padding:14px 16px;font-size:14px}
  .biset-alert-success{border-color:rgba(22,163,74,.20);background:rgba(22,163,74,.07);color:#166534}
  .biset-alert-danger{border-color:rgba(220,38,38,.18);background:rgba(220,38,38,.07);color:#991b1b}
  .biset-errors{margin:8px 0 0;padding-left:18px}

  .biset-grid{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(320px,.82fr);gap:18px;align-items:start}
  .biset-card{
    border:1px solid var(--card-border);background:var(--card-bg);border-radius:24px;
    box-shadow:0 14px 34px rgba(15,23,42,.05);overflow:hidden
  }
  .biset-card-head{padding:18px 20px;border-bottom:1px solid var(--card-border)}
  .biset-card-title{margin:0;font-size:16px;font-weight:900;color:var(--text)}
  .biset-card-sub{margin-top:4px;color:var(--muted);font-size:13px;line-height:1.5}
  .biset-body{padding:20px}

  .biset-form{display:grid;gap:18px}
  .biset-section{
    display:grid;gap:14px;padding:16px;border:1px solid var(--card-border);border-radius:18px;background:color-mix(in oklab, var(--panel-bg) 85%, transparent)
  }
  .biset-section-title{
    margin:0;font-size:13px;font-weight:900;text-transform:uppercase;letter-spacing:.05em;color:var(--muted)
  }

  .biset-field{display:grid;gap:8px}
  .biset-field-row{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
  .biset-label{font-size:12px;font-weight:900;text-transform:uppercase;letter-spacing:.04em;color:var(--muted)}
  .biset-input,.biset-select{
    width:100%;min-height:48px;border:1px solid var(--card-border);border-radius:14px;background:var(--panel-bg);
    color:var(--text);padding:11px 13px;outline:none;transition:.18s ease
  }
  .biset-input:focus,.biset-select:focus{
    border-color:color-mix(in oklab, var(--accent) 45%, var(--card-border));
    box-shadow:0 0 0 4px color-mix(in oklab, var(--accent) 12%, transparent);
    background:var(--card-bg)
  }
  .biset-help{color:var(--muted);font-size:13px;line-height:1.55}
  .biset-help code,.biset-note code,.biset-card-sub code{
    background:rgba(148,163,184,.12);padding:2px 6px;border-radius:8px;border:1px solid rgba(148,163,184,.14)
  }

  .biset-mode-switch{
    display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px
  }
  .biset-mode-card{
    position:relative;border:1px solid var(--card-border);border-radius:18px;padding:16px;background:var(--panel-bg);
    cursor:pointer;transition:.18s ease
  }
  .biset-mode-card:hover{transform:translateY(-1px);box-shadow:0 10px 24px rgba(15,23,42,.06)}
  .biset-mode-card input{position:absolute;inset:0;opacity:0;cursor:pointer}
  .biset-mode-card.is-active{
    border-color:color-mix(in oklab, var(--accent) 55%, var(--card-border));
    box-shadow:0 0 0 4px color-mix(in oklab, var(--accent) 12%, transparent)
  }
  .biset-mode-name{font-size:14px;font-weight:900;color:var(--text)}
  .biset-mode-desc{margin-top:6px;font-size:13px;color:var(--muted);line-height:1.5}
  .biset-mode-pill{
    margin-top:10px;display:inline-flex;align-items:center;justify-content:center;padding:4px 10px;border-radius:999px;
    font-size:11px;font-weight:900;text-transform:uppercase;letter-spacing:.04em
  }
  .biset-mode-pill-test{background:rgba(59,130,246,.10);color:#1d4ed8}
  .biset-mode-pill-live{background:rgba(220,38,38,.10);color:#b91c1c}
  html.theme-dark .biset-mode-pill-test{color:#93c5fd}
  html.theme-dark .biset-mode-pill-live{color:#fca5a5}

  .biset-actions-bar{display:flex;gap:10px;flex-wrap:wrap;padding-top:4px}
  .biset-kpis{display:grid;gap:14px}
  .biset-kpi{border:1px solid var(--card-border);border-radius:18px;background:var(--panel-bg);padding:16px}
  .biset-kpi-label{
    font-size:12px;font-weight:900;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:6px
  }
  .biset-kpi-value{color:var(--text);font-size:15px;font-weight:900;line-height:1.45;word-break:break-word}
  .biset-kpi-sub{margin-top:6px;color:var(--muted);font-size:12px;line-height:1.5}
  .biset-note{
    border:1px dashed color-mix(in oklab, var(--card-border) 90%, transparent);
    border-radius:16px;background:color-mix(in oklab, var(--panel-bg) 70%, transparent);
    padding:16px;color:var(--muted);font-size:13px;line-height:1.65
  }
  .biset-divider{height:1px;background:var(--card-border);margin:2px 0}
  .biset-hidden{display:none!important}

  @media (max-width:1100px){
    .biset-grid{grid-template-columns:1fr}
    .biset-hero{grid-template-columns:1fr}
    .biset-actions{justify-content:flex-start}
  }
  @media (max-width:720px){
    .biset-field-row,.biset-mode-switch{grid-template-columns:1fr}
  }
</style>
@endpush

@section('content')
<div class="biset-page">
  <section class="biset-hero">
    <div>
      <h1 class="biset-title">Configuración de facturación</h1>
      <p class="biset-subtitle">
        Cambia entre <strong>pruebas</strong> y <strong>producción</strong> sin tocar SQL manual.
        El sistema debe resolver automáticamente la base, la API key efectiva, el tenancy y el esquema de autenticación según el entorno seleccionado.
      </p>
    </div>

    <div class="biset-actions">
      <a href="{{ route('admin.billing.invoicing.dashboard') }}" class="biset-btn">Dashboard</a>
      <a href="{{ route('admin.billing.invoicing.invoices.index') }}" class="biset-btn">Facturas</a>
      <a href="{{ route('admin.billing.invoicing.requests.index') }}" class="biset-btn">Solicitudes</a>
    </div>
  </section>

  <section class="biset-env-banner {{ $resolvedMode === 'production' ? 'biset-env-banner-live' : 'biset-env-banner-test' }}">
    <div>
      <div class="biset-env-banner-title">Entorno activo del módulo</div>
      <div class="biset-env-banner-sub">
        Base resuelta: <strong>{{ $resolvedBase !== '' ? $resolvedBase : '—' }}</strong>
      </div>
    </div>
    <div class="biset-badge {{ $modeBadgeClass }}">{{ $modeLabel }}</div>
  </section>

  @if (session('ok'))
    <div class="biset-alert biset-alert-success">
      {{ session('ok') }}
    </div>
  @endif

  @if ($errors->any())
    <div class="biset-alert biset-alert-danger">
      <strong>Se encontraron errores al guardar:</strong>
      <ul class="biset-errors">
        @foreach ($errors->all() as $err)
          <li>{{ $err }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="biset-grid">
    <section class="biset-card">
      <div class="biset-card-head">
        <h2 class="biset-card-title">Parámetros Facturotopia</h2>
        <div class="biset-card-sub">
          Esta configuración se guarda en <code>billing_settings</code> y determina qué credenciales usa el ERP al emitir, sincronizar emisores, sincronizar receptores y timbrar.
        </div>
      </div>

      <div class="biset-body">
        <form method="POST" action="{{ route('admin.billing.invoicing.settings.save') }}" class="biset-form" id="facturotopiaSettingsForm">
          @csrf

          <div class="biset-section">
            <h3 class="biset-section-title">Entorno de trabajo</h3>

            <div class="biset-mode-switch" id="modeSwitch">
              <label class="biset-mode-card {{ $facturotopiaMode === 'sandbox' ? 'is-active' : '' }}" data-mode-card="sandbox">
                <input type="radio" name="facturotopia_mode" value="sandbox" {{ $facturotopiaMode === 'sandbox' ? 'checked' : '' }}>
                <div class="biset-mode-name">Sandbox / Pruebas</div>
                <div class="biset-mode-desc">
                  Úsalo para pruebas de timbrado, receptores demo, QA y validaciones sin impactar operación real.
                </div>
                <div class="biset-mode-pill biset-mode-pill-test">api-demo.facturotopia.com</div>
              </label>

              <label class="biset-mode-card {{ $facturotopiaMode === 'production' ? 'is-active' : '' }}" data-mode-card="production">
                <input type="radio" name="facturotopia_mode" value="production" {{ $facturotopiaMode === 'production' ? 'checked' : '' }}>
                <div class="biset-mode-name">Producción</div>
                <div class="biset-mode-desc">
                  Úsalo cuando vayas a operar con datos reales, receptores reales y timbrado productivo.
                </div>
                <div class="biset-mode-pill biset-mode-pill-live">api.facturotopia.com</div>
              </label>
            </div>

            <div class="biset-help">
              El cambio de entorno debe hacerse aquí. No necesitas editar la base manualmente si el modo está bien configurado.
            </div>
          </div>

          <div class="biset-section">
            <h3 class="biset-section-title">Conexión API</h3>

            <div class="biset-field-row">
              <div class="biset-field">
                <label for="facturotopia_flow" class="biset-label">Flujo de facturación</label>
                <select name="facturotopia_flow" id="facturotopia_flow" class="biset-select">
                  <option value="api_comprobantes" {{ $facturotopiaFlow === 'api_comprobantes' ? 'selected' : '' }}>api_comprobantes</option>
                </select>
                <div class="biset-help">
                  Este proyecto opera únicamente con <strong>api_comprobantes</strong>.
                </div>
              </div>

              <div class="biset-field">
                <label for="facturotopia_auth_scheme" class="biset-label">Esquema de autenticación</label>
                <select name="facturotopia_auth_scheme" id="facturotopia_auth_scheme" class="biset-select">
                  <option value="bearer" {{ strtolower($facturotopiaAuthScheme) === 'bearer' ? 'selected' : '' }}>Bearer</option>
                  <option value="apikey" {{ strtolower($facturotopiaAuthScheme) === 'apikey' ? 'selected' : '' }}>ApiKey</option>
                </select>
                <div class="biset-help">
                  Facturotopía puede requerir <code>Authorization: Bearer ...</code> o <code>Authorization: ApiKey ...</code>.
                </div>
              </div>
            </div>

            <div class="biset-field">
              <label for="facturotopia_base" class="biset-label">Base URL override</label>
              <input
                type="text"
                name="facturotopia_base"
                id="facturotopia_base"
                class="biset-input"
                value="{{ $facturotopiaBase }}"
                placeholder="Déjalo vacío para usar la base automática del entorno"
                autocomplete="off"
              >
              <div class="biset-help">
                Sugerida para <span id="baseModeLabel">{{ $facturotopiaMode === 'production' ? 'producción' : 'sandbox' }}</span>:
                <strong id="baseSuggestedValue">{{ $facturotopiaMode === 'production' ? $defaultProductionBase : $defaultSandboxBase }}</strong>.
              </div>
            </div>

            <div class="biset-field-row">
              <div class="biset-field">
                <label for="facturotopia_api_key_test" class="biset-label">API Key sandbox / test</label>
                <input
                  type="text"
                  name="facturotopia_api_key_test"
                  id="facturotopia_api_key_test"
                  class="biset-input"
                  value="{{ $facturotopiaKeyTest }}"
                  placeholder="fa_test_..."
                  autocomplete="off"
                >
              </div>

              <div class="biset-field">
                <label for="facturotopia_api_key_live" class="biset-label">API Key production / live</label>
                <input
                  type="text"
                  name="facturotopia_api_key_live"
                  id="facturotopia_api_key_live"
                  class="biset-input"
                  value="{{ $facturotopiaKeyLive }}"
                  placeholder="fa_live_..."
                  autocomplete="off"
                >
              </div>
            </div>

            <div class="biset-help">
              El sistema tomará automáticamente la key correspondiente al entorno seleccionado.
            </div>
          </div>

          <div class="biset-section">
            <h3 class="biset-section-title">Contexto de emisión</h3>

            <div class="biset-field-row">
              <div class="biset-field">
                <label for="facturotopia_emisor_id" class="biset-label">Emisor ID</label>
                <input
                  type="text"
                  name="facturotopia_emisor_id"
                  id="facturotopia_emisor_id"
                  class="biset-input"
                  value="{{ $facturotopiaEmisor }}"
                  placeholder="ID del emisor configurado en Facturotopia"
                >
              </div>

              <div class="biset-field">
                <label for="email_from" class="biset-label">Email remitente</label>
                <input
                  type="email"
                  name="email_from"
                  id="email_from"
                  class="biset-input"
                  value="{{ $emailFrom }}"
                  placeholder="facturacion@tudominio.com"
                >
              </div>
            </div>

            <div class="biset-field-row">
              <div class="biset-field">
                <label for="facturotopia_tenancy" class="biset-label">Tenancy</label>
                <input
                  type="text"
                  name="facturotopia_tenancy"
                  id="facturotopia_tenancy"
                  class="biset-input"
                  value="{{ $facturotopiaTenancy }}"
                  placeholder="tenant exacto entregado por Facturotopia"
                >
              </div>

              <div class="biset-field">
                <label for="facturotopia_tenancy_header" class="biset-label">Header de tenancy</label>
                <input
                  type="text"
                  name="facturotopia_tenancy_header"
                  id="facturotopia_tenancy_header"
                  class="biset-input"
                  value="{{ $facturotopiaTenHdr }}"
                  placeholder="X-Tenancy"
                >
              </div>
            </div>

            <div class="biset-help">
              Normalmente el header debe quedarse como <code>X-Tenancy</code>. El valor tenancy debe ser exactamente el que te entregó Facturotopia.
            </div>
          </div>

          <div class="biset-actions-bar">
            <button type="submit" class="biset-btn biset-btn-primary">Guardar configuración</button>
            <a href="{{ route('admin.billing.invoicing.settings.index') }}" class="biset-btn">Recargar</a>
            <button type="button" class="biset-btn" id="btnUseSuggestedBase">Usar base sugerida</button>
            <button type="button" class="biset-btn" id="btnClearBase">Limpiar override</button>
          </div>
        </form>
      </div>
    </section>

    <section class="biset-card">
      <div class="biset-card-head">
        <h2 class="biset-card-title">Resumen efectivo</h2>
        <div class="biset-card-sub">Así está resolviendo actualmente la configuración del módulo.</div>
      </div>

      <div class="biset-body">
        <div class="biset-kpis">
          <div class="biset-kpi">
            <div class="biset-kpi-label">Modo activo</div>
            <div class="biset-kpi-value">
              <span class="biset-badge {{ $modeBadgeClass }}">{{ $modeLabel }}</span>
            </div>
            <div class="biset-kpi-sub">
              Cambia automáticamente entre credenciales demo y productivas.
            </div>
          </div>

          <div class="biset-kpi">
            <div class="biset-kpi-label">Flujo activo</div>
            <div class="biset-kpi-value">{{ $resolvedFlow !== '' ? $resolvedFlow : '—' }}</div>
          </div>

          <div class="biset-kpi">
            <div class="biset-kpi-label">Auth scheme</div>
            <div class="biset-kpi-value">
              <span class="biset-badge biset-badge-neutral">{{ $authLabel }}</span>
            </div>
            <div class="biset-kpi-sub">
              El cliente intenta este esquema primero y si es necesario prueba el alterno.
            </div>
          </div>

          <div class="biset-kpi">
            <div class="biset-kpi-label">Base efectiva</div>
            <div class="biset-kpi-value" id="livePreviewBase">{{ $resolvedBase !== '' ? $resolvedBase : '—' }}</div>
            <div class="biset-kpi-sub">
              Si dejas el override vacío, se usa la base por entorno.
            </div>
          </div>

          <div class="biset-kpi">
            <div class="biset-kpi-label">API key efectiva</div>
            <div class="biset-kpi-value">{{ $maskKey($resolvedKey) }}</div>
            <div class="biset-kpi-sub">
              La key activa depende de si estás en sandbox o producción.
            </div>
          </div>

          <div class="biset-kpi">
            <div class="biset-kpi-label">Emisor ID</div>
            <div class="biset-kpi-value">{{ $resolvedEmisor !== '' ? $resolvedEmisor : '—' }}</div>
          </div>

          <div class="biset-kpi">
            <div class="biset-kpi-label">Tenancy</div>
            <div class="biset-kpi-value">{{ $resolvedTenancy !== '' ? $resolvedTenancy : '—' }}</div>
          </div>

          <div class="biset-kpi">
            <div class="biset-kpi-label">Header tenancy</div>
            <div class="biset-kpi-value">{{ $resolvedTenHdr !== '' ? $resolvedTenHdr : '—' }}</div>
          </div>

          <div class="biset-kpi">
            <div class="biset-kpi-label">Email remitente</div>
            <div class="biset-kpi-value">{{ $resolvedEmailFrom !== '' ? $resolvedEmailFrom : '—' }}</div>
          </div>
        </div>

        <div class="biset-divider"></div>

        <div class="biset-note" style="margin-top:16px;">
          <strong>Recomendación de operación:</strong><br>
          Usa <strong>sandbox</strong> para pruebas de timbrado, sincronización de receptores y QA.
          Cambia a <strong>producción</strong> únicamente cuando vayas a operar con datos reales.
          El objetivo es que el sistema te deje alternar entre ambos sin tocar SQL ni código.
        </div>
      </div>
    </section>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const modeInputs = Array.from(document.querySelectorAll('input[name="facturotopia_mode"]'));
  const modeCards  = Array.from(document.querySelectorAll('[data-mode-card]'));
  const baseInput  = document.getElementById('facturotopia_base');
  const baseModeLabel = document.getElementById('baseModeLabel');
  const baseSuggestedValue = document.getElementById('baseSuggestedValue');
  const livePreviewBase = document.getElementById('livePreviewBase');
  const btnUseSuggestedBase = document.getElementById('btnUseSuggestedBase');
  const btnClearBase = document.getElementById('btnClearBase');

  const sandboxBase = @json($defaultSandboxBase);
  const productionBase = @json($defaultProductionBase);

  function currentMode() {
    const checked = modeInputs.find(i => i.checked);
    return checked ? checked.value : 'sandbox';
  }

  function suggestedBase(mode) {
    return mode === 'production' ? productionBase : sandboxBase;
  }

  function refreshModeCards() {
    const mode = currentMode();
    modeCards.forEach(card => {
      card.classList.toggle('is-active', card.getAttribute('data-mode-card') === mode);
    });
  }

  function refreshBaseHelp() {
    const mode = currentMode();
    const suggested = suggestedBase(mode);

    if (baseModeLabel) {
      baseModeLabel.textContent = mode === 'production' ? 'producción' : 'sandbox';
    }

    if (baseSuggestedValue) {
      baseSuggestedValue.textContent = suggested;
    }

    if (livePreviewBase) {
      const current = (baseInput.value || '').trim();
      livePreviewBase.textContent = current !== '' ? current : suggested;
    }
  }

  modeInputs.forEach(input => {
    input.addEventListener('change', function () {
      refreshModeCards();
      refreshBaseHelp();
    });
  });

  if (baseInput) {
    baseInput.addEventListener('input', refreshBaseHelp);
  }

  if (btnUseSuggestedBase) {
    btnUseSuggestedBase.addEventListener('click', function () {
      baseInput.value = suggestedBase(currentMode());
      refreshBaseHelp();
      baseInput.focus();
    });
  }

  if (btnClearBase) {
    btnClearBase.addEventListener('click', function () {
      baseInput.value = '';
      refreshBaseHelp();
      baseInput.focus();
    });
  }

  refreshModeCards();
  refreshBaseHelp();
});
</script>
@endpush