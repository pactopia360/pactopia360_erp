{{-- C:\wamp64\www\pactopia360_erp\resources\views\admin\billing\invoicing\settings\index.blade.php --}}
@extends('layouts.admin')

@section('title', 'Facturación · Configuración')
@section('contentLayout', 'contained')
@section('pageClass', 'billing-invoicing-settings-page')

@php
    $settings = is_array($settings ?? null) ? $settings : [];
    $resolved = is_array($resolved ?? null) ? $resolved : [];

    $facturotopiaMode    = old('facturotopia_mode', (string) data_get($settings, 'facturotopia_mode', 'sandbox'));
    $facturotopiaFlow    = old('facturotopia_flow', (string) data_get($settings, 'facturotopia_flow', 'api_comprobantes'));
    $facturotopiaBase    = old('facturotopia_base', (string) data_get($settings, 'facturotopia_base', ''));
    $facturotopiaKeyTest = old('facturotopia_api_key_test', (string) data_get($settings, 'facturotopia_api_key_test', ''));
    $facturotopiaKeyLive = old('facturotopia_api_key_live', (string) data_get($settings, 'facturotopia_api_key_live', ''));
    $facturotopiaEmisor  = old('facturotopia_emisor_id', (string) data_get($settings, 'facturotopia_emisor_id', ''));
    $emailFrom           = old('email_from', (string) data_get($settings, 'email_from', ''));

    $resolvedBase  = (string) data_get($resolved, 'base', '');
    $resolvedMode  = (string) data_get($resolved, 'mode', '');
    $resolvedFlow  = (string) data_get($resolved, 'flow', '');
    $resolvedKey   = (string) data_get($resolved, 'api_key', '');
    $resolvedEmisor= (string) data_get($resolved, 'emisor_id', '');

    $maskKey = static function (string $value): string {
        $value = trim($value);
        if ($value === '') return '—';
        $len = mb_strlen($value);
        if ($len <= 8) return str_repeat('*', $len);
        return mb_substr($value, 0, 4) . str_repeat('*', max(0, $len - 8)) . mb_substr($value, -4);
    };
@endphp

@push('styles')
<style>
  .biset-page{display:grid;gap:18px}
  .biset-hero{
    display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;
    padding:22px 24px;border:1px solid var(--card-border);
    background:linear-gradient(180deg, color-mix(in oklab, var(--card-bg) 96%, white 4%), var(--card-bg));
    border-radius:20px;box-shadow:0 12px 30px rgba(15,23,42,.06);
  }
  .biset-title{margin:0;font-size:clamp(28px,3vw,40px);line-height:1.05;font-weight:800;color:var(--text);letter-spacing:-.03em}
  .biset-subtitle{margin:10px 0 0;color:var(--muted);font-size:14px}
  .biset-actions{display:flex;gap:10px;flex-wrap:wrap}
  .biset-btn{
    appearance:none;border:1px solid var(--card-border);background:var(--card-bg);color:var(--text);
    border-radius:12px;padding:10px 14px;font-size:14px;font-weight:700;text-decoration:none;
    display:inline-flex;align-items:center;justify-content:center;gap:8px;cursor:pointer;transition:.18s ease;
  }
  .biset-btn:hover{transform:translateY(-1px);border-color:color-mix(in oklab, var(--accent) 25%, var(--card-border));box-shadow:0 8px 20px rgba(15,23,42,.08)}
  .biset-btn-primary{background:linear-gradient(180deg, color-mix(in oklab, var(--accent) 92%, white 8%), var(--accent));color:#fff;border-color:transparent}
  .biset-alert{border:1px solid var(--card-border);background:var(--card-bg);border-radius:16px;padding:14px 16px;font-size:14px}
  .biset-alert-success{border-color:rgba(22,163,74,.20);background:rgba(22,163,74,.07);color:#166534}
  .biset-alert-danger{border-color:rgba(220,38,38,.18);background:rgba(220,38,38,.07);color:#991b1b}
  .biset-errors{margin:8px 0 0;padding-left:18px}
  .biset-grid{display:grid;grid-template-columns:minmax(0,1.45fr) minmax(320px,.85fr);gap:18px;align-items:start}
  .biset-card{border:1px solid var(--card-border);background:var(--card-bg);border-radius:20px;box-shadow:0 10px 28px rgba(15,23,42,.05);overflow:hidden}
  .biset-card-head{padding:18px 20px;border-bottom:1px solid var(--card-border)}
  .biset-card-title{margin:0;font-size:16px;font-weight:800;color:var(--text)}
  .biset-card-sub{margin-top:4px;color:var(--muted);font-size:13px}
  .biset-body{padding:20px}
  .biset-form{display:grid;gap:18px}
  .biset-field{display:grid;gap:8px}
  .biset-label{font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:var(--muted)}
  .biset-input,.biset-select{
    width:100%;min-height:46px;border:1px solid var(--card-border);border-radius:12px;background:var(--panel-bg);
    color:var(--text);padding:10px 12px;outline:none;transition:.18s ease;
  }
  .biset-input:focus,.biset-select:focus{
    border-color:color-mix(in oklab, var(--accent) 45%, var(--card-border));
    box-shadow:0 0 0 4px color-mix(in oklab, var(--accent) 12%, transparent);background:var(--card-bg);
  }
  .biset-help{color:var(--muted);font-size:13px;line-height:1.5}
  .biset-actions-bar{display:flex;gap:10px;flex-wrap:wrap;padding-top:6px}
  .biset-kpis{display:grid;gap:14px}
  .biset-kpi{border:1px solid var(--card-border);border-radius:16px;background:var(--panel-bg);padding:16px}
  .biset-kpi-label{font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:6px}
  .biset-kpi-value{color:var(--text);font-size:15px;font-weight:800;line-height:1.4;word-break:break-word}
  .biset-note{
    border:1px dashed color-mix(in oklab, var(--card-border) 90%, transparent);border-radius:16px;
    background:color-mix(in oklab, var(--panel-bg) 70%, transparent);padding:16px;color:var(--muted);font-size:13px;line-height:1.6
  }
  @media (max-width:1100px){.biset-grid{grid-template-columns:1fr}}
</style>
@endpush

@section('content')
<div class="biset-page">
  <section class="biset-hero">
    <div>
      <h1 class="biset-title">Configuración de facturación</h1>
      <p class="biset-subtitle">Parámetros reales de conexión con Facturotopia y operación del módulo.</p>
    </div>

    <div class="biset-actions">
      <a href="{{ route('admin.billing.invoicing.dashboard') }}" class="biset-btn">Panel</a>
      <a href="{{ route('admin.billing.invoicing.invoices.index') }}" class="biset-btn">Facturas</a>
      <a href="{{ route('admin.billing.invoicing.requests.index') }}" class="biset-btn biset-btn-primary">Solicitudes</a>
    </div>
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
        <div class="biset-card-sub">Esta configuración quedará persistida en <code>billing_settings</code>.</div>
      </div>

      <div class="biset-body">
        <form method="POST" action="{{ route('admin.billing.invoicing.settings.save') }}" class="biset-form">
          @csrf

          <div class="biset-field">
            <label for="facturotopia_mode" class="biset-label">Modo</label>
            <select name="facturotopia_mode" id="facturotopia_mode" class="biset-select">
              <option value="sandbox" {{ $facturotopiaMode === 'sandbox' ? 'selected' : '' }}>sandbox</option>
              <option value="production" {{ $facturotopiaMode === 'production' ? 'selected' : '' }}>production</option>
            </select>
            <div class="biset-help">
              Define si la conexión activa usará credenciales demo o producción.
            </div>
          </div>

          <div class="biset-field">
            <label for="facturotopia_flow" class="biset-label">Flujo de facturación</label>
            <select name="facturotopia_flow" id="facturotopia_flow" class="biset-select">
              <option value="api_comprobantes" {{ $facturotopiaFlow === 'api_comprobantes' ? 'selected' : '' }}>api_comprobantes</option>
              <option value="xml_timbrado" {{ $facturotopiaFlow === 'xml_timbrado' ? 'selected' : '' }}>xml_timbrado</option>
            </select>
            <div class="biset-help">
              Para este proyecto vamos a operar por API. El valor recomendado inicial es <strong>api_comprobantes</strong>.
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
            >
            <div class="biset-help">
              Si queda vacío, sandbox usará la base demo y production usará la base productiva automáticamente.
            </div>
          </div>

          <div class="biset-field">
            <label for="facturotopia_api_key_test" class="biset-label">API Key sandbox / test</label>
            <input
              type="text"
              name="facturotopia_api_key_test"
              id="facturotopia_api_key_test"
              class="biset-input"
              value="{{ $facturotopiaKeyTest }}"
              placeholder="Pega aquí la API key demo"
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
              placeholder="Pega aquí la API key productiva"
            >
          </div>

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

          <div class="biset-actions-bar">
            <button type="submit" class="biset-btn biset-btn-primary">Guardar configuración</button>
            <a href="{{ route('admin.billing.invoicing.settings.index') }}" class="biset-btn">Recargar</a>
          </div>
        </form>
      </div>
    </section>

    <section class="biset-card">
      <div class="biset-card-head">
        <h2 class="biset-card-title">Resumen efectivo</h2>
        <div class="biset-card-sub">Así quedará resuelta la configuración activa del módulo.</div>
      </div>

      <div class="biset-body">
        <div class="biset-kpis">
          <div class="biset-kpi">
            <div class="biset-kpi-label">Modo activo</div>
            <div class="biset-kpi-value">{{ $resolvedMode !== '' ? $resolvedMode : '—' }}</div>
          </div>

          <div class="biset-kpi">
            <div class="biset-kpi-label">Flujo activo</div>
            <div class="biset-kpi-value">{{ $resolvedFlow !== '' ? $resolvedFlow : '—' }}</div>
          </div>

          <div class="biset-kpi">
            <div class="biset-kpi-label">Base efectiva</div>
            <div class="biset-kpi-value">{{ $resolvedBase !== '' ? $resolvedBase : '—' }}</div>
          </div>

          <div class="biset-kpi">
            <div class="biset-kpi-label">API key efectiva</div>
            <div class="biset-kpi-value">{{ $maskKey($resolvedKey) }}</div>
          </div>

          <div class="biset-kpi">
            <div class="biset-kpi-label">Emisor ID</div>
            <div class="biset-kpi-value">{{ $resolvedEmisor !== '' ? $resolvedEmisor : '—' }}</div>
          </div>

          <div class="biset-kpi">
            <div class="biset-kpi-label">Email remitente</div>
            <div class="biset-kpi-value">{{ $emailFrom !== '' ? $emailFrom : '—' }}</div>
          </div>
        </div>

        <div class="biset-note" style="margin-top:16px;">
          Después de este paso ya podremos conectar el flujo oficial por API en <code>InvoiceRequestsController</code> para generar comprobantes con Facturotopia sin depender de endpoints legacy inventados.
        </div>
      </div>
    </section>
  </div>
</div>
@endsection