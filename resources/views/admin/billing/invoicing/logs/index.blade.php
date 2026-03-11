{{-- C:\wamp64\www\pactopia360_erp\resources\views\admin\billing\invoicing\logs\index.blade.php --}}
@extends('layouts.admin')

@section('title', 'Facturación · Logs')
@section('contentLayout', 'contained')
@section('pageClass', 'billing-invoicing-logs-page')

@php
    $rows   = $rows ?? collect();
    $table  = $table ?? null;
    $error  = $error ?? null;
    $q      = (string) ($q ?? '');
    $status = (string) ($status ?? '');
    $source = (string) ($source ?? '');
    $period = (string) ($period ?? '');

    $prop = static function ($row, string $key, $default = null) {
        return data_get($row, $key, $default);
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

    $money = static function ($value): string {
        if ($value === null || $value === '') {
            return '—';
        }

        return '$' . number_format((float) $value, 2);
    };

    $statusLabel = static function ($value): string {
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

    $statusClass = static function ($value): string {
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

    $totalRows = method_exists($rows, 'total')
        ? (int) $rows->total()
        : (int) collect($rows)->count();
@endphp

@push('styles')
<style>
  .bil-page{
    display:grid;
    gap:18px;
  }

  .bil-hero{
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

  .bil-title{
    margin:0;
    font-size:clamp(28px, 3vw, 40px);
    line-height:1.05;
    font-weight:800;
    color:var(--text);
    letter-spacing:-.03em;
  }

  .bil-subtitle{
    margin:10px 0 0;
    color:var(--muted);
    font-size:14px;
  }

  .bil-actions{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
  }

  .bil-btn{
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

  .bil-btn:hover{
    transform:translateY(-1px);
    border-color:color-mix(in oklab, var(--accent) 25%, var(--card-border));
    box-shadow:0 8px 20px rgba(15,23,42,.08);
  }

  .bil-btn-primary{
    background:linear-gradient(180deg, color-mix(in oklab, var(--accent) 92%, white 8%), var(--accent));
    color:#fff;
    border-color:transparent;
  }

  .bil-alert{
    border:1px solid var(--card-border);
    background:var(--card-bg);
    border-radius:16px;
    padding:14px 16px;
    font-size:14px;
  }

  .bil-alert-warning{
    border-color:rgba(245,158,11,.18);
    background:rgba(245,158,11,.08);
    color:#92400e;
  }

  .bil-card{
    border:1px solid var(--card-border);
    background:var(--card-bg);
    border-radius:20px;
    box-shadow:0 10px 28px rgba(15,23,42,.05);
    overflow:hidden;
  }

  .bil-card-head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    flex-wrap:wrap;
    padding:18px 20px;
    border-bottom:1px solid var(--card-border);
  }

  .bil-card-title{
    margin:0;
    font-size:16px;
    font-weight:800;
    color:var(--text);
  }

  .bil-card-sub{
    color:var(--muted);
    font-size:13px;
    margin-top:4px;
  }

  .bil-body{
    padding:20px;
  }

  .bil-filters{
    display:grid;
    grid-template-columns:repeat(12, minmax(0, 1fr));
    gap:14px;
  }

  .bil-field{
    display:grid;
    gap:8px;
  }

  .bil-field.col-4{ grid-column:span 4; }
  .bil-field.col-3{ grid-column:span 3; }
  .bil-field.col-2{ grid-column:span 2; }

  .bil-label{
    font-size:12px;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.04em;
    color:var(--muted);
  }

  .bil-input{
    width:100%;
    min-height:44px;
    border:1px solid var(--card-border);
    border-radius:12px;
    background:var(--panel-bg);
    color:var(--text);
    padding:10px 12px;
    outline:none;
    transition:.18s ease;
  }

  .bil-input:focus{
    border-color:color-mix(in oklab, var(--accent) 45%, var(--card-border));
    box-shadow:0 0 0 4px color-mix(in oklab, var(--accent) 12%, transparent);
    background:var(--card-bg);
  }

  .bil-filter-actions{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    align-items:flex-end;
  }

  .bil-meta{
    display:grid;
    grid-template-columns:repeat(3, minmax(0, 1fr));
    gap:14px;
  }

  .bil-kpi{
    border:1px solid var(--card-border);
    background:var(--panel-bg);
    border-radius:16px;
    padding:16px;
  }

  .bil-kpi-label{
    font-size:12px;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.04em;
    color:var(--muted);
    margin-bottom:6px;
  }

  .bil-kpi-value{
    color:var(--text);
    font-size:15px;
    font-weight:800;
    line-height:1.4;
    word-break:break-word;
  }

  .bil-table-wrap{
    overflow:auto;
  }

  .bil-table{
    width:100%;
    border-collapse:separate;
    border-spacing:0;
    min-width:1320px;
  }

  .bil-table thead th{
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

  .bil-table tbody td{
    padding:15px 16px;
    border-bottom:1px solid color-mix(in oklab, var(--card-border) 78%, transparent);
    vertical-align:top;
    color:var(--text);
    font-size:14px;
  }

  .bil-table tbody tr:hover{
    background:color-mix(in oklab, var(--panel-bg) 58%, transparent);
  }

  .bil-badge{
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

  .bil-badge.is-pending{ background:rgba(245,158,11,.12); color:#92400e; }
  .bil-badge.is-progress{ background:rgba(59,130,246,.12); color:#1d4ed8; }
  .bil-badge.is-success{ background:rgba(22,163,74,.12); color:#166534; }
  .bil-badge.is-error{ background:rgba(220,38,38,.12); color:#991b1b; }
  .bil-badge.is-muted{ background:rgba(107,114,128,.14); color:#374151; }
  .bil-badge.is-default{ background:rgba(148,163,184,.10); color:var(--text); }

  .bil-muted{
    color:var(--muted);
  }

  .bil-strong{
    font-weight:800;
  }

  .bil-notes{
    max-width:360px;
    white-space:pre-wrap;
    line-height:1.45;
  }

  .bil-actions-row{
    display:flex;
    justify-content:flex-end;
    gap:8px;
    flex-wrap:wrap;
  }

  .bil-empty{
    padding:30px 20px;
    text-align:center;
    color:var(--muted);
  }

  .bil-pagination{
    padding:18px 20px;
    border-top:1px solid var(--card-border);
  }

  @media (max-width: 1150px){
    .bil-field.col-4,
    .bil-field.col-3,
    .bil-field.col-2{
      grid-column:span 12;
    }

    .bil-meta{
      grid-template-columns:1fr;
    }
  }
</style>
@endpush

@section('content')
<div class="bil-page">
  <section class="bil-hero">
    <div>
      <h1 class="bil-title">Logs de facturación</h1>
      <p class="bil-subtitle">Bitácora operativa basada en los registros de <code>billing_invoices</code>.</p>
    </div>

    <div class="bil-actions">
      <a href="{{ route('admin.billing.invoicing.dashboard') }}" class="bil-btn">Panel</a>
      <a href="{{ route('admin.billing.invoicing.invoices.index') }}" class="bil-btn">Facturas</a>
      <a href="{{ route('admin.billing.invoicing.requests.index') }}" class="bil-btn">Solicitudes</a>
      <a href="{{ route('admin.billing.invoicing.settings.index') }}" class="bil-btn bil-btn-primary">Configuración</a>
    </div>
  </section>

  @if (!empty($error))
    <div class="bil-alert bil-alert-warning">
      {{ $error }}
    </div>
  @endif

  <section class="bil-card">
    <div class="bil-card-head">
      <div>
        <h2 class="bil-card-title">Filtros</h2>
        <div class="bil-card-sub">Consulta por estado, fuente, periodo o búsqueda libre.</div>
      </div>
    </div>

    <div class="bil-body">
      <form method="GET" action="{{ route('admin.billing.invoicing.logs.index') }}">
        <div class="bil-filters">
          <div class="bil-field col-4">
            <label for="q" class="bil-label">Buscar</label>
            <input
              type="text"
              name="q"
              id="q"
              class="bil-input"
              value="{{ $q }}"
              placeholder="ID, request_id, RFC, UUID, razón social, notas..."
            >
          </div>

          <div class="bil-field col-2">
            <label for="status" class="bil-label">Estado</label>
            <input
              type="text"
              name="status"
              id="status"
              class="bil-input"
              value="{{ $status }}"
              placeholder="sent, paid, error..."
            >
          </div>

          <div class="bil-field col-3">
            <label for="source" class="bil-label">Source</label>
            <input
              type="text"
              name="source"
              id="source"
              class="bil-input"
              value="{{ $source }}"
              placeholder="hub, manual, api..."
            >
          </div>

          <div class="bil-field col-3">
            <label for="period" class="bil-label">Periodo</label>
            <input
              type="text"
              name="period"
              id="period"
              class="bil-input"
              value="{{ $period }}"
              placeholder="2026-03"
            >
          </div>
        </div>

        <div class="bil-filter-actions" style="margin-top:14px;">
          <button type="submit" class="bil-btn bil-btn-primary">Aplicar filtros</button>
          <a href="{{ route('admin.billing.invoicing.logs.index') }}" class="bil-btn">Limpiar</a>
        </div>
      </form>
    </div>
  </section>

  <section class="bil-meta">
    <article class="bil-kpi">
      <div class="bil-kpi-label">Tabla origen</div>
      <div class="bil-kpi-value">{{ $table ?: 'No detectada' }}</div>
    </article>

    <article class="bil-kpi">
      <div class="bil-kpi-label">Registros encontrados</div>
      <div class="bil-kpi-value">{{ number_format($totalRows) }}</div>
    </article>

    <article class="bil-kpi">
      <div class="bil-kpi-label">Filtros activos</div>
      <div class="bil-kpi-value">
        {{ ($q !== '' || $status !== '' || $source !== '' || $period !== '') ? 'Sí' : 'No' }}
      </div>
    </article>
  </section>

  <section class="bil-card">
    <div class="bil-card-head">
      <div>
        <h2 class="bil-card-title">Resultados</h2>
        <div class="bil-card-sub">Vista operativa de eventos y estados de facturación.</div>
      </div>
    </div>

    @if (collect($rows)->count() === 0)
      <div class="bil-empty">
        No hay registros para mostrar con los filtros actuales.
      </div>
    @else
      <div class="bil-table-wrap">
        <table class="bil-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Request</th>
              <th>Cuenta</th>
              <th>Cliente</th>
              <th>RFC</th>
              <th>Periodo</th>
              <th>UUID</th>
              <th>Estado</th>
              <th>Source</th>
              <th>Total</th>
              <th>Notas</th>
              <th>Fecha</th>
              <th style="text-align:right">Acciones</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($rows as $row)
              @php
                $invoiceId    = $prop($row, 'id');
                $requestId    = $prop($row, 'request_id');
                $accountId    = $prop($row, 'account_id');
                $accountName  = $prop($row, 'account_name', $prop($row, 'razon_social', '—'));
                $accountEmail = $prop($row, 'account_email');
                $accountRfc   = $prop($row, 'account_rfc', $prop($row, 'rfc'));
                $uuid         = $prop($row, 'cfdi_uuid');
                $rowStatus    = $prop($row, 'status');
                $rowSource    = $prop($row, 'source');
                $rowPeriod    = $prop($row, 'period');
                $rowTotal     = $prop($row, 'total', $prop($row, 'subtotal'));
                $rowNotes     = $prop($row, 'notes');
                $rowDate      = $prop($row, 'updated_at', $prop($row, 'created_at'));
              @endphp

              <tr>
                <td><span class="bil-strong">#{{ $invoiceId ?: '—' }}</span></td>
                <td>{{ $requestId ?: '—' }}</td>
                <td>{{ $accountId ?: '—' }}</td>

                <td>
                  <div class="bil-strong">{{ $accountName ?: '—' }}</div>
                  <div class="bil-muted">{{ $accountEmail ?: '—' }}</div>
                </td>

                <td>{{ $accountRfc ?: '—' }}</td>
                <td>{{ $rowPeriod ?: '—' }}</td>
                <td><span class="bil-muted">{{ $uuid ?: '—' }}</span></td>

                <td>
                  <span class="bil-badge {{ $statusClass($rowStatus) }}">
                    {{ $statusLabel($rowStatus) }}
                  </span>
                </td>

                <td>{{ $rowSource ?: '—' }}</td>
                <td>{{ $money($rowTotal) }}</td>

                <td>
                  <div class="bil-notes">{{ $rowNotes ?: '—' }}</div>
                </td>

                <td>{{ $fmtDate($rowDate) }}</td>

                <td>
                  <div class="bil-actions-row">
                    @if($invoiceId)
                      <a href="{{ route('admin.billing.invoicing.invoices.show', $invoiceId) }}" class="bil-btn">Ver</a>
                    @endif

                    @if($invoiceId && !empty($prop($row, 'pdf_path')))
                      <a href="{{ route('admin.billing.invoicing.invoices.download', [$invoiceId, 'pdf']) }}" class="bil-btn">PDF</a>
                    @endif

                    @if($invoiceId && !empty($prop($row, 'xml_path')))
                      <a href="{{ route('admin.billing.invoicing.invoices.download', [$invoiceId, 'xml']) }}" class="bil-btn">XML</a>
                    @endif
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif

    @if(method_exists($rows, 'links'))
      <div class="bil-pagination">
        {{ $rows->links() }}
      </div>
    @endif
  </section>
</div>
@endsection