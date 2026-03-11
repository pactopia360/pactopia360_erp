{{-- C:\wamp64\www\pactopia360_erp\resources\views\admin\billing\invoicing\dashboard.blade.php --}}
@extends('layouts.admin')

@section('title', 'Facturación · Panel')
@section('contentLayout', 'contained')
@section('pageClass', 'billing-invoicing-dashboard-page')

@php
    $cards          = is_array($cards ?? null) ? $cards : [];
    $recentRequests = $recentRequests ?? collect();
    $recentInvoices = $recentInvoices ?? collect();
    $requestsMode   = (string) ($requestsMode ?? 'missing');
    $requestsTable  = $requestsTable ?? null;

    $card = static function (string $key, $default = 0) use ($cards) {
        return data_get($cards, $key, $default);
    };

    $money = static function ($value): string {
        return '$' . number_format((float) $value, 2);
    };

    $fmtDate = static function ($value): string {
        if (empty($value)) {
            return '—';
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->format('Y-m-d H:i');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    };

    $requestStatusLabel = static function ($value): string {
        $v = strtolower(trim((string) $value));

        return match ($v) {
            'requested'   => 'Solicitada',
            'in_progress' => 'En proceso',
            'issued'      => 'Emitida',
            'done'        => 'Emitida',
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
            default     => $value !== '' ? ucfirst((string) $value) : '—',
        };
    };

    $invoiceStatusClass = static function ($value): string {
        $v = strtolower(trim((string) $value));

        return match ($v) {
            'draft'     => 'is-muted',
            'pending'   => 'is-pending',
            'generated' => 'is-progress',
            'stamped', 'sent', 'paid' => 'is-success',
            'cancelled', 'canceled' => 'is-muted',
            'error'     => 'is-error',
            default     => 'is-default',
        };
    };

    $requestStatusField = static function ($row) {
        if (isset($row->status) && $row->status !== null && $row->status !== '') {
            return $row->status;
        }

        if (isset($row->estatus) && $row->estatus !== null && $row->estatus !== '') {
            return $row->estatus;
        }

        return '';
    };

    $requestPeriodField = static function ($row) {
        if (isset($row->period) && $row->period !== null && $row->period !== '') {
            return $row->period;
        }

        if (isset($row->periodo) && $row->periodo !== null && $row->periodo !== '') {
            return $row->periodo;
        }

        return '—';
    };

    $requestRfcField = static function ($row) {
        if (isset($row->rfc) && $row->rfc !== null && $row->rfc !== '') {
            return $row->rfc;
        }

        return '—';
    };

    $requestNameField = static function ($row) {
        foreach (['razon_social', 'nombre_fiscal', 'business_name', 'name'] as $field) {
            if (isset($row->{$field}) && $row->{$field} !== null && $row->{$field} !== '') {
                return $row->{$field};
            }
        }

        return '—';
    };

    $invoiceNameField = static function ($row) {
        foreach (['razon_social', 'account_name', 'name'] as $field) {
            if (isset($row->{$field}) && $row->{$field} !== null && $row->{$field} !== '') {
                return $row->{$field};
            }
        }

        return '—';
    };

    $invoiceRfcField = static function ($row) {
        foreach (['rfc', 'account_rfc'] as $field) {
            if (isset($row->{$field}) && $row->{$field} !== null && $row->{$field} !== '') {
                return $row->{$field};
            }
        }

        return '—';
    };

    $invoiceTotalField = static function ($row) {
        foreach (['total', 'subtotal'] as $field) {
            if (isset($row->{$field}) && $row->{$field} !== null && $row->{$field} !== '') {
                return (float) $row->{$field};
            }
        }

        return 0.0;
    };
@endphp

@push('styles')
<style>
  .bid-page{
    display:grid;
    gap:18px;
  }

  .bid-hero{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:16px;
    flex-wrap:wrap;
    padding:22px 24px;
    border:1px solid var(--card-border);
    background:linear-gradient(180deg, color-mix(in oklab, var(--card-bg) 96%, white 4%), var(--card-bg));
    border-radius:20px;
    box-shadow:0 12px 30px rgba(15,23,42,.06);
  }

  .bid-title{
    margin:0;
    font-size:clamp(30px, 3.2vw, 42px);
    line-height:1.05;
    font-weight:800;
    color:var(--text);
    letter-spacing:-.03em;
  }

  .bid-subtitle{
    margin:10px 0 0;
    color:var(--muted);
    font-size:14px;
  }

  .bid-actions{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
  }

  .bid-btn{
    appearance:none;
    border:1px solid var(--card-border);
    background:var(--card-bg);
    color:var(--text);
    border-radius:12px;
    padding:10px 14px;
    font-size:14px;
    font-weight:700;
    text-decoration:none;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    cursor:pointer;
    transition:.18s ease;
  }

  .bid-btn:hover{
    transform:translateY(-1px);
    border-color:color-mix(in oklab, var(--accent) 25%, var(--card-border));
    box-shadow:0 8px 20px rgba(15,23,42,.08);
  }

  .bid-btn-primary{
    background:linear-gradient(180deg, color-mix(in oklab, var(--accent) 92%, white 8%), var(--accent));
    color:#fff;
    border-color:transparent;
  }

  .bid-info{
    border:1px solid var(--card-border);
    background:var(--card-bg);
    border-radius:16px;
    padding:14px 16px;
    color:var(--muted);
    font-size:14px;
  }

  .bid-grid-cards{
    display:grid;
    grid-template-columns:repeat(5, minmax(0, 1fr));
    gap:14px;
  }

  .bid-stat{
    border:1px solid var(--card-border);
    background:var(--card-bg);
    border-radius:18px;
    padding:18px;
    box-shadow:0 10px 28px rgba(15,23,42,.05);
  }

  .bid-stat-label{
    font-size:12px;
    text-transform:uppercase;
    letter-spacing:.04em;
    color:var(--muted);
    font-weight:800;
    margin-bottom:8px;
  }

  .bid-stat-value{
    font-size:30px;
    font-weight:900;
    letter-spacing:-.03em;
    color:var(--text);
    line-height:1;
  }

  .bid-stat-sub{
    margin-top:10px;
    color:var(--muted);
    font-size:13px;
  }

  .bid-panels{
    display:grid;
    grid-template-columns:repeat(2, minmax(0, 1fr));
    gap:18px;
    align-items:start;
  }

  .bid-card{
    border:1px solid var(--card-border);
    background:var(--card-bg);
    border-radius:20px;
    box-shadow:0 10px 28px rgba(15,23,42,.05);
    overflow:hidden;
  }

  .bid-card-head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    flex-wrap:wrap;
    padding:18px 20px;
    border-bottom:1px solid var(--card-border);
  }

  .bid-card-title{
    margin:0;
    font-size:16px;
    font-weight:800;
    color:var(--text);
  }

  .bid-card-sub{
    color:var(--muted);
    font-size:13px;
    margin-top:4px;
  }

  .bid-body{
    padding:0;
  }

  .bid-table-wrap{
    overflow:auto;
  }

  .bid-table{
    width:100%;
    border-collapse:separate;
    border-spacing:0;
    min-width:760px;
  }

  .bid-table thead th{
    background:color-mix(in oklab, var(--panel-bg) 92%, white 8%);
    color:var(--muted);
    font-size:12px;
    text-transform:uppercase;
    letter-spacing:.04em;
    text-align:left;
    padding:14px 16px;
    border-bottom:1px solid var(--card-border);
    white-space:nowrap;
  }

  .bid-table tbody td{
    padding:15px 16px;
    border-bottom:1px solid color-mix(in oklab, var(--card-border) 78%, transparent);
    vertical-align:top;
    color:var(--text);
    font-size:14px;
  }

  .bid-table tbody tr:hover{
    background:color-mix(in oklab, var(--panel-bg) 58%, transparent);
  }

  .bid-badge{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:28px;
    border-radius:999px;
    padding:0 10px;
    font-size:12px;
    font-weight:800;
    white-space:nowrap;
    border:1px solid transparent;
    background:rgba(148,163,184,.10);
    color:var(--text);
  }

  .bid-badge.is-pending{ background:rgba(245,158,11,.12); color:#92400e; }
  .bid-badge.is-progress{ background:rgba(59,130,246,.12); color:#1d4ed8; }
  .bid-badge.is-success{ background:rgba(22,163,74,.12); color:#166534; }
  .bid-badge.is-error{ background:rgba(220,38,38,.12); color:#991b1b; }
  .bid-badge.is-muted{ background:rgba(107,114,128,.14); color:#374151; }
  .bid-badge.is-default{ background:rgba(148,163,184,.10); color:var(--text); }

  .bid-muted{
    color:var(--muted);
  }

  .bid-strong{
    font-weight:800;
  }

  .bid-actions-row{
    display:flex;
    justify-content:flex-end;
    gap:8px;
    flex-wrap:wrap;
  }

  .bid-empty{
    padding:28px 20px;
    text-align:center;
    color:var(--muted);
  }

  @media (max-width: 1380px){
    .bid-grid-cards{
      grid-template-columns:repeat(3, minmax(0, 1fr));
    }
  }

  @media (max-width: 1100px){
    .bid-panels{
      grid-template-columns:1fr;
    }

    .bid-grid-cards{
      grid-template-columns:repeat(2, minmax(0, 1fr));
    }
  }

  @media (max-width: 720px){
    .bid-grid-cards{
      grid-template-columns:1fr;
    }
  }
</style>
@endpush

@section('content')
<div class="bid-page">
  <section class="bid-hero">
    <div>
      <h1 class="bid-title">Panel de facturación</h1>
      <p class="bid-subtitle">Resumen operativo del módulo de solicitudes, facturas emitidas y actividad mensual.</p>
    </div>

    <div class="bid-actions">
      <a href="{{ route('admin.billing.invoicing.requests.index') }}" class="bid-btn bid-btn-primary">Solicitudes</a>
      <a href="{{ route('admin.billing.invoicing.invoices.index') }}" class="bid-btn">Facturas</a>
      <a href="{{ route('admin.billing.invoicing.settings.index') }}" class="bid-btn">Configuración</a>
      <a href="{{ route('admin.billing.invoicing.logs.index') }}" class="bid-btn">Logs</a>
    </div>
  </section>

  <div class="bid-info">
    <strong>Origen de solicitudes:</strong>
    {{ $requestsTable ? $requestsTable : 'No disponible' }}
    &nbsp;·&nbsp;
    <strong>Modo:</strong>
    {{ $requestsMode !== '' ? $requestsMode : 'missing' }}
  </div>

  <section class="bid-grid-cards">
    <article class="bid-stat">
      <div class="bid-stat-label">Solicitudes totales</div>
      <div class="bid-stat-value">{{ number_format((int) $card('requests_total')) }}</div>
      <div class="bid-stat-sub">Total histórico de solicitudes registradas.</div>
    </article>

    <article class="bid-stat">
      <div class="bid-stat-label">Solicitudes pendientes</div>
      <div class="bid-stat-value">{{ number_format((int) $card('requests_pending')) }}</div>
      <div class="bid-stat-sub">Solicitudes en estado solicitado o en proceso.</div>
    </article>

    <article class="bid-stat">
      <div class="bid-stat-label">Solicitudes emitidas</div>
      <div class="bid-stat-value">{{ number_format((int) $card('requests_issued')) }}</div>
      <div class="bid-stat-sub">Solicitudes ya facturadas o completadas.</div>
    </article>

    <article class="bid-stat">
      <div class="bid-stat-label">Solicitudes con error</div>
      <div class="bid-stat-value">{{ number_format((int) $card('requests_error')) }}</div>
      <div class="bid-stat-sub">Registros rechazados o con error.</div>
    </article>

    <article class="bid-stat">
      <div class="bid-stat-label">Facturas totales</div>
      <div class="bid-stat-value">{{ number_format((int) $card('invoices_total')) }}</div>
      <div class="bid-stat-sub">Total histórico de facturas emitidas.</div>
    </article>

    <article class="bid-stat">
      <div class="bid-stat-label">Facturas enviadas</div>
      <div class="bid-stat-value">{{ number_format((int) $card('invoices_sent')) }}</div>
      <div class="bid-stat-sub">Facturas con marca de envío.</div>
    </article>

    <article class="bid-stat">
      <div class="bid-stat-label">Facturas no enviadas</div>
      <div class="bid-stat-value">{{ number_format((int) $card('invoices_unsent')) }}</div>
      <div class="bid-stat-sub">Facturas pendientes de envío.</div>
    </article>

    <article class="bid-stat">
      <div class="bid-stat-label">Actividad mensual</div>
      <div class="bid-stat-value">{{ number_format((int) $card('month_total')) }}</div>
      <div class="bid-stat-sub">Suma de solicitudes y facturas del mes actual.</div>
    </article>

    <article class="bid-stat">
      <div class="bid-stat-label">Solicitudes del mes</div>
      <div class="bid-stat-value">{{ number_format((int) $card('month_requests')) }}</div>
      <div class="bid-stat-sub">Solicitudes con periodo del mes actual.</div>
    </article>

    <article class="bid-stat">
      <div class="bid-stat-label">Facturas del mes</div>
      <div class="bid-stat-value">{{ number_format((int) $card('month_invoices')) }}</div>
      <div class="bid-stat-sub">Facturas con periodo del mes actual.</div>
    </article>
  </section>

  <section class="bid-panels">
    <article class="bid-card">
      <div class="bid-card-head">
        <div>
          <h2 class="bid-card-title">Solicitudes recientes</h2>
          <div class="bid-card-sub">Últimos 10 registros detectados en la tabla de solicitudes.</div>
        </div>
      </div>

      <div class="bid-body">
        @if (collect($recentRequests)->isEmpty())
          <div class="bid-empty">No hay solicitudes recientes para mostrar.</div>
        @else
          <div class="bid-table-wrap">
            <table class="bid-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Cliente</th>
                  <th>RFC</th>
                  <th>Periodo</th>
                  <th>Estado</th>
                  <th>Fecha</th>
                  <th style="text-align:right">Acciones</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($recentRequests as $row)
                  @php
                    $rowStatus = $requestStatusField($row);
                  @endphp
                  <tr>
                    <td><span class="bid-strong">#{{ data_get($row, 'id', '—') }}</span></td>
                    <td>{{ $requestNameField($row) }}</td>
                    <td>{{ $requestRfcField($row) }}</td>
                    <td>{{ $requestPeriodField($row) }}</td>
                    <td>
                      <span class="bid-badge {{ $requestStatusClass($rowStatus) }}">
                        {{ $requestStatusLabel($rowStatus) }}
                      </span>
                    </td>
                    <td>{{ $fmtDate(data_get($row, 'created_at')) }}</td>
                    <td>
                      <div class="bid-actions-row">
                        @if(data_get($row, 'id'))
                          <a href="{{ route('admin.billing.invoicing.requests.show', data_get($row, 'id')) }}" class="bid-btn">Ver</a>
                        @endif
                      </div>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>
    </article>

    <article class="bid-card">
      <div class="bid-card-head">
        <div>
          <h2 class="bid-card-title">Facturas recientes</h2>
          <div class="bid-card-sub">Últimas 10 facturas registradas en <code>billing_invoices</code>.</div>
        </div>
      </div>

      <div class="bid-body">
        @if (collect($recentInvoices)->isEmpty())
          <div class="bid-empty">No hay facturas recientes para mostrar.</div>
        @else
          <div class="bid-table-wrap">
            <table class="bid-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Cliente</th>
                  <th>RFC</th>
                  <th>Periodo</th>
                  <th>Estado</th>
                  <th>Total</th>
                  <th style="text-align:right">Acciones</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($recentInvoices as $row)
                  @php
                    $rowStatus = data_get($row, 'status', '');
                    $invoiceId = data_get($row, 'id');
                  @endphp
                  <tr>
                    <td><span class="bid-strong">#{{ $invoiceId ?: '—' }}</span></td>
                    <td>{{ $invoiceNameField($row) }}</td>
                    <td>{{ $invoiceRfcField($row) }}</td>
                    <td>{{ data_get($row, 'period', '—') }}</td>
                    <td>
                      <span class="bid-badge {{ $invoiceStatusClass($rowStatus) }}">
                        {{ $invoiceStatusLabel($rowStatus) }}
                      </span>
                    </td>
                    <td>{{ $money($invoiceTotalField($row)) }}</td>
                    <td>
                      <div class="bid-actions-row">
                        @if($invoiceId)
                          <a href="{{ route('admin.billing.invoicing.invoices.show', $invoiceId) }}" class="bid-btn">Ver</a>
                        @endif
                      </div>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>
    </article>
  </section>
</div>
@endsection