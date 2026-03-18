{{-- C:\wamp64\www\pactopia360_erp\resources\views\admin\billing\invoicing\dashboard.blade.php --}}
@extends('layouts.admin')

@section('title', 'Facturación · Dashboard')
@section('contentLayout', 'full')
@section('pageClass', 'billing-invoicing-dashboard-page billing-invoicing-dashboard-page--clean')

@php
    $cards          = is_array($cards ?? null) ? $cards : [];
    $recentRequests = collect($recentRequests ?? []);
    $recentInvoices = collect($recentInvoices ?? []);
    $requestsChart  = collect($requestsChart ?? []);
    $invoicesChart  = collect($invoicesChart ?? []);
    $statusDonut    = is_array($statusDonut ?? null) ? $statusDonut : [];

    $card = static function (string $key, $default = 0) use ($cards) {
        return data_get($cards, $key, $default);
    };

    $money = static function ($value): string {
        return '$' . number_format((float) $value, 2);
    };

    $fmtDate = static function ($value): string {
        if (empty($value)) return '—';

        try {
            return \Illuminate\Support\Carbon::parse($value)->format('Y-m-d H:i');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    };

    $invoiceStatusLabel = static function ($value): string {
        $v = strtolower(trim((string) $value));

        return match ($v) {
            'draft'     => 'Borrador',
            'pending'   => 'Pendiente',
            'generated' => 'Generada',
            'stamped'   => 'Timbrada',
            'sent'      => 'Enviada',
            'paid'      => 'Pagada',
            'cancelled', 'canceled' => 'Cancelada',
            'error'     => 'Error',
            'issued'    => 'Emitida',
            'active'    => 'Activa',
            default     => $value !== '' ? ucfirst((string) $value) : '—',
        };
    };

    $invoiceStatusClass = static function ($value): string {
        $v = strtolower(trim((string) $value));

        return match ($v) {
            'draft'     => 'is-muted',
            'pending'   => 'is-pending',
            'generated' => 'is-progress',
            'stamped', 'sent', 'paid', 'issued', 'active' => 'is-success',
            'cancelled', 'canceled' => 'is-muted',
            'error'     => 'is-error',
            default     => 'is-default',
        };
    };

    $requestStatusLabel = static function ($value): string {
        $v = strtolower(trim((string) $value));

        return match ($v) {
            'requested'   => 'Solicitada',
            'in_progress' => 'En proceso',
            'issued', 'done' => 'Emitida',
            'rejected'    => 'Rechazada',
            'error'       => 'Error',
            default       => $value !== '' ? ucfirst((string) $value) : '—',
        };
    };

    $requestStatusClass = static function ($value): string {
        $v = strtolower(trim((string) $value));

        return match ($v) {
            'requested'   => 'is-pending',
            'in_progress' => 'is-progress',
            'issued', 'done' => 'is-success',
            'rejected'    => 'is-muted',
            'error'       => 'is-error',
            default       => 'is-default',
        };
    };

    $requestStatusField = static function ($row) {
        return $row->status ?? $row->estatus ?? '';
    };

    $requestNameField = static function ($row) {
        foreach (['razon_social', 'nombre_fiscal', 'business_name', 'name'] as $field) {
            if (!empty($row->{$field})) {
                return (string) $row->{$field};
            }
        }
        return '—';
    };

    $requestPeriodField = static function ($row) {
        return (string) ($row->period ?? $row->periodo ?? '—');
    };

    $invoiceNameField = static function ($row) {
        foreach (['razon_social', 'account_name', 'name'] as $field) {
            if (!empty($row->{$field})) {
                return (string) $row->{$field};
            }
        }
        return '—';
    };

    $invoiceRfcField = static function ($row) {
        foreach (['rfc', 'account_rfc'] as $field) {
            if (!empty($row->{$field})) {
                return (string) $row->{$field};
            }
        }
        return '—';
    };

    $reqMax = max(1, (int) $requestsChart->max('value'));
    $invMax = max(1, (int) $invoicesChart->max('value'));

    $donutTotal = max(1, array_sum([
        (int) data_get($statusDonut, 'pending', 0),
        (int) data_get($statusDonut, 'sent', 0),
        (int) data_get($statusDonut, 'paid', 0),
        (int) data_get($statusDonut, 'cancelled', 0),
        (int) data_get($statusDonut, 'error', 0),
    ]));

    $donutItems = [
        ['key' => 'pending',   'label' => 'Pendiente', 'value' => (int) data_get($statusDonut, 'pending', 0), 'class' => 'is-pending'],
        ['key' => 'sent',      'label' => 'Enviada',   'value' => (int) data_get($statusDonut, 'sent', 0), 'class' => 'is-progress'],
        ['key' => 'paid',      'label' => 'Pagada',    'value' => (int) data_get($statusDonut, 'paid', 0), 'class' => 'is-success'],
        ['key' => 'cancelled', 'label' => 'Cancelada', 'value' => (int) data_get($statusDonut, 'cancelled', 0), 'class' => 'is-muted'],
        ['key' => 'error',     'label' => 'Error',     'value' => (int) data_get($statusDonut, 'error', 0), 'class' => 'is-error'],
    ];
@endphp

@push('styles')
<style>
  .billing-invoicing-dashboard-page .page-container{
    padding: clamp(10px, 1.2vw, 18px);
  }

  .billing-invoicing-dashboard-page .page-shell,
  .billing-invoicing-dashboard-page .page-shell--full,
  .billing-invoicing-dashboard-page .page-shell--contained{
    width:100%;
    max-width:100% !important;
    margin:0 !important;
  }

  .ivd-page{
    display:grid;
    gap:16px;
  }

  .ivd-hero{
    display:grid;
    grid-template-columns:minmax(0,1fr) auto;
    gap:14px;
    align-items:center;
    padding:16px 18px;
    border:1px solid var(--card-border);
    border-radius:22px;
    background:linear-gradient(180deg, color-mix(in oklab, var(--card-bg) 96%, white 4%), var(--card-bg));
    box-shadow:0 12px 26px rgba(15,23,42,.05);
  }

  .ivd-badge{
    display:inline-flex;
    align-items:center;
    gap:8px;
    min-height:30px;
    padding:0 12px;
    border-radius:999px;
    border:1px solid var(--card-border);
    background:var(--panel-bg);
    color:var(--muted);
    font-size:11px;
    text-transform:uppercase;
    letter-spacing:.08em;
    font-weight:900;
    width:max-content;
  }

  .ivd-badge__dot{
    width:8px;
    height:8px;
    border-radius:999px;
    background:var(--accent);
    flex:0 0 8px;
  }

  .ivd-title{
    margin:10px 0 0;
    font-size:clamp(24px, 2vw, 34px);
    line-height:1.02;
    letter-spacing:-.04em;
    font-weight:900;
    color:var(--text);
  }

  .ivd-subtitle{
    margin:6px 0 0;
    color:var(--muted);
    font-size:13px;
  }

  .ivd-actions{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
    justify-content:flex-end;
  }

  .ivd-btn,
  .ivd-link{
    appearance:none;
    border:1px solid var(--card-border);
    background:var(--card-bg);
    color:var(--text);
    border-radius:14px;
    min-height:40px;
    padding:0 12px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    text-decoration:none;
    font-size:13px;
    font-weight:800;
    transition:.18s ease;
  }

  .ivd-btn:hover,
  .ivd-link:hover{
    transform:translateY(-1px);
    border-color:color-mix(in oklab, var(--accent) 28%, var(--card-border));
    box-shadow:0 10px 20px rgba(15,23,42,.06);
  }

  .ivd-btn--primary{
    color:#fff;
    border-color:transparent;
    background:linear-gradient(180deg, #0f3f60, #0c2f47);
  }

  .ivd-btn--soft{
    background:var(--panel-bg);
  }

  .ivd-btn--success{
    color:#166534;
    border-color:rgba(22,163,74,.18);
    background:rgba(22,163,74,.08);
  }

  .ivd-icon{
    width:16px;
    height:16px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    flex:0 0 16px;
  }

  .ivd-icon svg{
    width:100%;
    height:100%;
    display:block;
  }

  .ivd-top-grid{
    display:grid;
    grid-template-columns: minmax(280px,.8fr) minmax(0,1.2fr);
    gap:16px;
  }

  .ivd-card{
    border:1px solid var(--card-border);
    background:var(--card-bg);
    border-radius:22px;
    box-shadow:0 10px 24px rgba(15,23,42,.05);
    overflow:hidden;
  }

  .ivd-card__head{
    padding:14px 16px 0;
  }

  .ivd-card__title{
    margin:0;
    font-size:15px;
    font-weight:900;
    color:var(--text);
  }

  .ivd-card__sub{
    margin:4px 0 0;
    color:var(--muted);
    font-size:12px;
  }

  .ivd-card__body{
    padding:16px;
  }

  .ivd-stamps{
    display:grid;
    gap:12px;
  }

  .ivd-stamps__ring{
    position:relative;
    height:10px;
    border-radius:999px;
    background:color-mix(in oklab, var(--panel-bg) 88%, var(--card-bg));
    overflow:hidden;
    border:1px solid var(--card-border);
  }

  .ivd-stamps__bar{
    height:100%;
    border-radius:999px;
    background:linear-gradient(90deg, #0f3f60 0%, #15aabf 100%);
  }

  .ivd-stamps__numbers{
    display:grid;
    grid-template-columns:repeat(3, minmax(0,1fr));
    gap:10px;
  }

  .ivd-mini{
    border:1px solid var(--card-border);
    border-radius:16px;
    background:linear-gradient(180deg, color-mix(in oklab, var(--panel-bg) 96%, white 4%), var(--card-bg));
    padding:12px;
    display:grid;
    gap:5px;
  }

  .ivd-mini__label{
    font-size:11px;
    text-transform:uppercase;
    letter-spacing:.08em;
    color:var(--muted);
    font-weight:900;
  }

  .ivd-mini__value{
    font-size:24px;
    line-height:1;
    letter-spacing:-.04em;
    font-weight:900;
    color:var(--text);
  }

  .ivd-mini__value--money{
    font-size:18px;
  }

  .ivd-kpis{
    display:grid;
    grid-template-columns:repeat(6, minmax(0,1fr));
    gap:10px;
  }

  .ivd-kpi{
    border:1px solid var(--card-border);
    border-radius:18px;
    background:linear-gradient(180deg, color-mix(in oklab, var(--panel-bg) 96%, white 4%), var(--card-bg));
    padding:12px;
    display:grid;
    gap:6px;
  }

  .ivd-kpi__top{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:8px;
  }

  .ivd-kpi__label{
    font-size:11px;
    text-transform:uppercase;
    letter-spacing:.08em;
    color:var(--muted);
    font-weight:900;
  }

  .ivd-kpi__value{
    font-size:22px;
    line-height:1;
    letter-spacing:-.04em;
    font-weight:900;
    color:var(--text);
  }

  .ivd-kpi__sub{
    font-size:11px;
    color:var(--muted);
  }

  .ivd-mid-grid{
    display:grid;
    grid-template-columns: minmax(0,1fr) minmax(320px,.92fr);
    gap:16px;
  }

  .ivd-bars{
    display:grid;
    gap:12px;
  }

  .ivd-bars__row{
    display:grid;
    grid-template-columns:44px 1fr 44px;
    gap:10px;
    align-items:center;
  }

  .ivd-bars__label,
  .ivd-bars__value{
    font-size:12px;
    font-weight:800;
    color:var(--text);
  }

  .ivd-bars__track{
    height:10px;
    border-radius:999px;
    border:1px solid var(--card-border);
    background:color-mix(in oklab, var(--panel-bg) 90%, var(--card-bg));
    overflow:hidden;
  }

  .ivd-bars__fill{
    height:100%;
    border-radius:999px;
    background:linear-gradient(90deg, #0f3f60 0%, #15aabf 100%);
  }

  .ivd-status-list{
    display:grid;
    gap:10px;
  }

  .ivd-status{
    display:grid;
    gap:6px;
  }

  .ivd-status__row{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
  }

  .ivd-status__name{
    font-size:13px;
    font-weight:800;
    color:var(--text);
  }

  .ivd-status__count{
    font-size:12px;
    color:var(--muted);
    font-weight:800;
  }

  .ivd-status__bar{
    height:8px;
    border-radius:999px;
    border:1px solid var(--card-border);
    background:color-mix(in oklab, var(--panel-bg) 90%, var(--card-bg));
    overflow:hidden;
  }

  .ivd-status__fill{
    height:100%;
    border-radius:999px;
  }

  .ivd-status.is-pending .ivd-status__fill{ background:rgba(245,158,11,.82); }
  .ivd-status.is-progress .ivd-status__fill{ background:rgba(59,130,246,.82); }
  .ivd-status.is-success .ivd-status__fill{ background:rgba(22,163,74,.82); }
  .ivd-status.is-muted .ivd-status__fill{ background:rgba(107,114,128,.72); }
  .ivd-status.is-error .ivd-status__fill{ background:rgba(220,38,38,.82); }

  .ivd-bottom-grid{
    display:grid;
    grid-template-columns:repeat(2, minmax(0,1fr));
    gap:16px;
  }

  .ivd-list{
    display:grid;
    gap:10px;
  }

  .ivd-list-item{
    border:1px solid var(--card-border);
    border-radius:16px;
    background:linear-gradient(180deg, color-mix(in oklab, var(--panel-bg) 96%, white 4%), var(--card-bg));
    padding:12px;
    display:grid;
    gap:8px;
  }

  .ivd-list-item__top{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:10px;
  }

  .ivd-list-item__title{
    margin:0;
    font-size:14px;
    font-weight:900;
    color:var(--text);
  }

  .ivd-list-item__meta{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
    color:var(--muted);
    font-size:12px;
  }

  .ivd-pill{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:28px;
    padding:0 10px;
    border-radius:999px;
    font-size:11px;
    font-weight:900;
    border:1px solid transparent;
    background:rgba(148,163,184,.10);
    color:var(--text);
    white-space:nowrap;
  }

  .ivd-pill.is-pending{ background:rgba(245,158,11,.12); color:#92400e; }
  .ivd-pill.is-progress{ background:rgba(59,130,246,.12); color:#1d4ed8; }
  .ivd-pill.is-success{ background:rgba(22,163,74,.12); color:#166534; }
  .ivd-pill.is-error{ background:rgba(220,38,38,.12); color:#991b1b; }
  .ivd-pill.is-muted{ background:rgba(107,114,128,.14); color:#374151; }
  .ivd-pill.is-default{ background:rgba(148,163,184,.10); color:var(--text); }

  .ivd-empty{
    padding:14px;
    border:1px dashed var(--card-border);
    border-radius:16px;
    color:var(--muted);
    text-align:center;
    font-size:13px;
  }

  @media (max-width: 1380px){
    .ivd-kpis{
      grid-template-columns:repeat(3, minmax(0,1fr));
    }
  }

  @media (max-width: 1100px){
    .ivd-top-grid,
    .ivd-mid-grid,
    .ivd-bottom-grid,
    .ivd-hero{
      grid-template-columns:1fr;
    }

    .ivd-actions{
      justify-content:flex-start;
    }
  }

  @media (max-width: 760px){
    .ivd-kpis{
      grid-template-columns:repeat(2, minmax(0,1fr));
    }

    .ivd-stamps__numbers{
      grid-template-columns:1fr;
    }
  }

  @media (max-width: 560px){
    .ivd-kpis{
      grid-template-columns:1fr;
    }

    .ivd-btn,
    .ivd-link{
      width:100%;
    }
  }
</style>
@endpush

@section('content')
<div class="ivd-page">
    <section class="ivd-hero">
        <div>
            <span class="ivd-badge">
                <span class="ivd-badge__dot"></span>
                Dashboard
            </span>

            <h1 class="ivd-title">Facturación Admin</h1>
            <p class="ivd-subtitle">Panel limpio para timbres, actividad del mes, estatus y accesos rápidos.</p>
        </div>

        <div class="ivd-actions">
            <a href="{{ route('admin.billing.invoicing.requests.index') }}" class="ivd-btn ivd-btn--primary">
                <span class="ivd-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                </span>
                Solicitudes
            </a>

            <a href="{{ route('admin.billing.invoicing.invoices.index') }}" class="ivd-btn ivd-btn--soft">
                <span class="ivd-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M7 3h8l5 5v11a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8"/>
                        <path d="M15 3v5h5" stroke="currentColor" stroke-width="1.8"/>
                    </svg>
                </span>
                Facturas
            </a>

            <a href="{{ route('admin.billing.invoicing.emisores.index') }}" class="ivd-link">Emisores</a>
            <a href="{{ route('admin.billing.invoicing.receptores.index') }}" class="ivd-link">Receptores</a>
            <a href="{{ route('admin.billing.invoicing.settings.index') }}" class="ivd-link">Configuración</a>
            <a href="{{ route('admin.billing.invoicing.logs.index') }}" class="ivd-link">Logs</a>
        </div>
    </section>

    <section class="ivd-top-grid">
        <article class="ivd-card">
            <div class="ivd-card__head">
                <h2 class="ivd-card__title">Consumo de timbres</h2>
                <p class="ivd-card__sub">Resumen rápido de asignados, usados y disponibles.</p>
            </div>

            <div class="ivd-card__body">
                <div class="ivd-stamps">
                    <div class="ivd-stamps__ring" aria-hidden="true">
                        <div class="ivd-stamps__bar" style="width: {{ min(100, max(0, (float) $card('stamps_usage_pct', 0))) }}%;"></div>
                    </div>

                    <div class="ivd-stamps__numbers">
                        <article class="ivd-mini">
                            <span class="ivd-mini__label">Asignados</span>
                            <strong class="ivd-mini__value">{{ number_format((int) $card('stamps_assigned')) }}</strong>
                        </article>

                        <article class="ivd-mini">
                            <span class="ivd-mini__label">Usados</span>
                            <strong class="ivd-mini__value">{{ number_format((int) $card('stamps_used')) }}</strong>
                        </article>

                        <article class="ivd-mini">
                            <span class="ivd-mini__label">Disponibles</span>
                            <strong class="ivd-mini__value">{{ number_format((int) $card('stamps_available')) }}</strong>
                        </article>
                    </div>
                </div>
            </div>
        </article>

        <section class="ivd-kpis">
            <article class="ivd-kpi">
                <div class="ivd-kpi__top">
                    <span class="ivd-kpi__label">Solicitudes</span>
                    <span class="ivd-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </span>
                </div>
                <strong class="ivd-kpi__value">{{ number_format((int) $card('requests_total')) }}</strong>
                <span class="ivd-kpi__sub">{{ number_format((int) $card('requests_pending')) }} pendientes</span>
            </article>

            <article class="ivd-kpi">
                <div class="ivd-kpi__top">
                    <span class="ivd-kpi__label">Facturas</span>
                    <span class="ivd-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M7 3h8l5 5v11a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8"/>
                            <path d="M15 3v5h5" stroke="currentColor" stroke-width="1.8"/>
                        </svg>
                    </span>
                </div>
                <strong class="ivd-kpi__value">{{ number_format((int) $card('invoices_total')) }}</strong>
                <span class="ivd-kpi__sub">{{ number_format((int) $card('invoices_unsent')) }} sin enviar</span>
            </article>

            <article class="ivd-kpi">
                <div class="ivd-kpi__top">
                    <span class="ivd-kpi__label">Mes</span>
                    <span class="ivd-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <rect x="3" y="5" width="18" height="16" rx="2" stroke="currentColor" stroke-width="1.8"/>
                            <path d="M16 3v4M8 3v4M3 10h18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </span>
                </div>
                <strong class="ivd-kpi__value">{{ number_format((int) $card('month_total')) }}</strong>
                <span class="ivd-kpi__sub">{{ number_format((int) $card('month_invoices')) }} facturas</span>
            </article>

            <article class="ivd-kpi">
                <div class="ivd-kpi__top">
                    <span class="ivd-kpi__label">Monto mes</span>
                    <span class="ivd-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M12 3v18M16.5 7.5c0-1.9-1.8-3.5-4.5-3.5S7.5 5.1 7.5 7c0 4.5 9 2.5 9 7 0 1.9-1.8 4-4.5 4S7.5 16.4 7.5 14.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </span>
                </div>
                <strong class="ivd-kpi__value">{{ $money($card('month_amount_total')) }}</strong>
                <span class="ivd-kpi__sub">{{ $money($card('month_amount_paid')) }} cobrado</span>
            </article>

            <article class="ivd-kpi">
                <div class="ivd-kpi__top">
                    <span class="ivd-kpi__label">Enviadas</span>
                    <span class="ivd-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                </div>
                <strong class="ivd-kpi__value">{{ number_format((int) $card('invoices_sent')) }}</strong>
                <span class="ivd-kpi__sub">con marca de envío</span>
            </article>

            <article class="ivd-kpi">
                <div class="ivd-kpi__top">
                    <span class="ivd-kpi__label">Errores</span>
                    <span class="ivd-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/>
                            <path d="M12 8v5M12 16h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </span>
                </div>
                <strong class="ivd-kpi__value">{{ number_format((int) $card('requests_error')) }}</strong>
                <span class="ivd-kpi__sub">solicitudes con error</span>
            </article>
        </section>
    </section>

    <section class="ivd-mid-grid">
        <article class="ivd-card">
            <div class="ivd-card__head">
                <h2 class="ivd-card__title">Actividad últimos 6 meses</h2>
                <p class="ivd-card__sub">Solicitudes y facturas por periodo.</p>
            </div>

            <div class="ivd-card__body">
                <div class="ivd-bars">
                    @foreach ($requestsChart as $i => $item)
                        <div class="ivd-bars__row">
                            <span class="ivd-bars__label">{{ $item['label'] }}</span>
                            <div class="ivd-bars__track">
                                <div class="ivd-bars__fill" style="width: {{ $reqMax > 0 ? round(((int) $item['value'] / $reqMax) * 100, 2) : 0 }}%;"></div>
                            </div>
                            <span class="ivd-bars__value">{{ number_format((int) $item['value']) }}</span>
                        </div>
                    @endforeach
                </div>

                <div style="height:12px;"></div>

                <div class="ivd-bars">
                    @foreach ($invoicesChart as $i => $item)
                        <div class="ivd-bars__row">
                            <span class="ivd-bars__label">{{ $item['label'] }}</span>
                            <div class="ivd-bars__track">
                                <div class="ivd-bars__fill" style="width: {{ $invMax > 0 ? round(((int) $item['value'] / $invMax) * 100, 2) : 0 }}%;"></div>
                            </div>
                            <span class="ivd-bars__value">{{ number_format((int) $item['value']) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </article>

        <article class="ivd-card">
            <div class="ivd-card__head">
                <h2 class="ivd-card__title">Distribución de facturas</h2>
                <p class="ivd-card__sub">Estatus acumulado del módulo.</p>
            </div>

            <div class="ivd-card__body">
                <div class="ivd-status-list">
                    @foreach ($donutItems as $item)
                        <div class="ivd-status {{ $item['class'] }}">
                            <div class="ivd-status__row">
                                <span class="ivd-status__name">{{ $item['label'] }}</span>
                                <span class="ivd-status__count">{{ number_format((int) $item['value']) }}</span>
                            </div>
                            <div class="ivd-status__bar">
                                <div class="ivd-status__fill" style="width: {{ $donutTotal > 0 ? round(((int) $item['value'] / $donutTotal) * 100, 2) : 0 }}%;"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </article>
    </section>

    <section class="ivd-bottom-grid">
        <article class="ivd-card">
            <div class="ivd-card__head">
                <h2 class="ivd-card__title">Solicitudes recientes</h2>
                <p class="ivd-card__sub">Últimos 5 registros.</p>
            </div>

            <div class="ivd-card__body">
                @if ($recentRequests->isEmpty())
                    <div class="ivd-empty">No hay solicitudes recientes.</div>
                @else
                    <div class="ivd-list">
                        @foreach ($recentRequests as $row)
                            @php $rowStatus = $requestStatusField($row); @endphp
                            <article class="ivd-list-item">
                                <div class="ivd-list-item__top">
                                    <div>
                                        <h3 class="ivd-list-item__title">#{{ data_get($row, 'id', '—') }} · {{ $requestNameField($row) }}</h3>
                                        <div class="ivd-list-item__meta">
                                            <span>{{ data_get($row, 'rfc', '—') }}</span>
                                            <span>{{ $requestPeriodField($row) }}</span>
                                            <span>{{ $fmtDate(data_get($row, 'created_at')) }}</span>
                                        </div>
                                    </div>

                                    <span class="ivd-pill {{ $requestStatusClass($rowStatus) }}">
                                        {{ $requestStatusLabel($rowStatus) }}
                                    </span>
                                </div>

                                @if(data_get($row, 'id'))
                                    <div>
                                        <a href="{{ route('admin.billing.invoicing.requests.show', data_get($row, 'id')) }}" class="ivd-link">Ver solicitud</a>
                                    </div>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
        </article>

        <article class="ivd-card">
            <div class="ivd-card__head">
                <h2 class="ivd-card__title">Facturas recientes</h2>
                <p class="ivd-card__sub">Últimos 5 registros.</p>
            </div>

            <div class="ivd-card__body">
                @if ($recentInvoices->isEmpty())
                    <div class="ivd-empty">No hay facturas recientes.</div>
                @else
                    <div class="ivd-list">
                        @foreach ($recentInvoices as $row)
                            @php
                                $rowStatus = data_get($row, 'status', '');
                                $invoiceId = data_get($row, 'id');
                            @endphp

                            <article class="ivd-list-item">
                                <div class="ivd-list-item__top">
                                    <div>
                                        <h3 class="ivd-list-item__title">#{{ $invoiceId ?: '—' }} · {{ $invoiceNameField($row) }}</h3>
                                        <div class="ivd-list-item__meta">
                                            <span>{{ $invoiceRfcField($row) }}</span>
                                            <span>{{ data_get($row, 'period', '—') }}</span>
                                            <span>{{ $money(data_get($row, 'display_total_mxn', 0)) }}</span>
                                        </div>
                                    </div>

                                    <span class="ivd-pill {{ $invoiceStatusClass($rowStatus) }}">
                                        {{ $invoiceStatusLabel($rowStatus) }}
                                    </span>
                                </div>

                                @if($invoiceId)
                                    <div>
                                        <a href="{{ route('admin.billing.invoicing.invoices.show', $invoiceId) }}" class="ivd-link">Ver factura</a>
                                    </div>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
        </article>
    </section>
</div>
@endsection