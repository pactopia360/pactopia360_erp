{{-- C:\wamp64\www\pactopia360_erp\resources\views\admin\billing\invoicing\invoices\index.blade.php --}}
@extends('layouts.admin')

@section('title', 'Facturación · Facturas emitidas')
@section('contentLayout', 'contained')
@section('pageClass', 'billing-invoices-index-page')

@php
    $rows   = $rows ?? collect();
    $error  = $error ?? null;
    $status = (string) ($status ?? '');
    $period = (string) ($period ?? '');
    $q      = (string) ($q ?? '');

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
            'active'    => 'Activa',
            'issued'    => 'Emitida',
            default     => $value !== null && $value !== '' ? ucfirst((string) $value) : '—',
        };
    };

    $statusClass = static function ($value): string {
        $v = strtolower(trim((string) $value));

        return match ($v) {
            'draft'     => 'is-draft',
            'pending'   => 'is-pending',
            'generated' => 'is-generated',
            'stamped'   => 'is-stamped',
            'sent'      => 'is-sent',
            'paid'      => 'is-paid',
            'cancelled', 'canceled' => 'is-cancelled',
            'error'     => 'is-error',
            'active'    => 'is-active',
            'issued'    => 'is-issued',
            default     => 'is-default',
        };
    };

    $money = static function ($value): string {
        if ($value === null || $value === '') {
            return '—';
        }

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

    $prop = static function ($row, string $key, $default = null) {
        return data_get($row, $key, $default);
    };

    $totalRows = method_exists($rows, 'total')
        ? (int) $rows->total()
        : (int) collect($rows)->count();

    $rowsCollection = collect(method_exists($rows, 'items') ? $rows->items() : $rows);

    $totalAmount = $rowsCollection->sum(function ($row) use ($prop) {
        $total = $prop($row, 'display_total_mxn');
        if ($total === null || $total === '') $total = $prop($row, 'amount_mxn');
        if ($total === null || $total === '') $total = $prop($row, 'monto_mxn');
        if ($total === null || $total === '') $total = $prop($row, 'total');
        if ($total === null || $total === '') $total = $prop($row, 'subtotal');
        if (($total === null || $total === '') && !empty($prop($row, 'amount'))) $total = ((float) $prop($row, 'amount')) / 100;
        if (($total === null || $total === '') && !empty($prop($row, 'amount_cents'))) $total = ((float) $prop($row, 'amount_cents')) / 100;
        return is_numeric($total) ? (float) $total : 0;
    });

    $issuedCount = $rowsCollection->filter(function ($row) use ($prop) {
        return strtolower(trim((string) $prop($row, 'status'))) === 'issued';
    })->count();

    $sentCount = $rowsCollection->filter(function ($row) use ($prop) {
        return strtolower(trim((string) $prop($row, 'status'))) === 'sent';
    })->count();

    $paidCount = $rowsCollection->filter(function ($row) use ($prop) {
        return strtolower(trim((string) $prop($row, 'status'))) === 'paid';
    })->count();

    $cancelledCount = $rowsCollection->filter(function ($row) use ($prop) {
        $v = strtolower(trim((string) $prop($row, 'status')));
        return in_array($v, ['cancelled', 'canceled'], true);
    })->count();

    $routeIndex      = route('admin.billing.invoicing.invoices.index');
    $routeDashboard  = route('admin.billing.invoicing.dashboard');
    $routeRequests   = route('admin.billing.invoicing.requests.index');
    $routeStoreOne   = route('admin.billing.invoicing.invoices.store_manual');
    $routeStoreBulk  = route('admin.billing.invoicing.invoices.bulk_store_manual');
    $routeBulkSend   = route('admin.billing.invoicing.invoices.bulk_send');

    $bulkOldAccountId   = old('account_id', []);
    $bulkOldPeriod      = old('period', []);
    $bulkOldCfdiUuid    = old('cfdi_uuid', []);
    $bulkOldSerie       = old('serie', []);
    $bulkOldFolio       = old('folio', []);
    $bulkOldStatus      = old('status', []);
    $bulkOldAmountMxn   = old('amount_mxn', []);
    $bulkOldIssuedAt    = old('issued_at', []);
    $bulkOldIssuedDate  = old('issued_date', []);
    $bulkOldSource      = old('source', []);
    $bulkOldNotes       = old('notes', []);

    $bulkRowsCount = max(
        count(is_array($bulkOldAccountId) ? $bulkOldAccountId : []),
        count(is_array($bulkOldPeriod) ? $bulkOldPeriod : []),
        3
    );

    $currentDateFrom = request('date_from', now()->startOfMonth()->format('Y-m-d'));
    $currentDateTo   = request('date_to', now()->format('Y-m-d'));

    $hasAnyErrors = $errors->any();
@endphp

@push('styles')
<style>
  .bii-page{
    display:grid;
    gap:18px;
  }

  .bii-shell{
    display:grid;
    gap:18px;
  }

  .bii-hero{
    position:relative;
    overflow:hidden;
    display:grid;
    grid-template-columns:minmax(0, 1.2fr) auto;
    gap:18px;
    align-items:start;
    padding:26px;
    border:1px solid var(--card-border);
    border-radius:28px;
    background:
      radial-gradient(900px 260px at 0% 0%, rgba(15,118,110,.08), transparent 55%),
      radial-gradient(900px 260px at 100% 0%, rgba(59,130,246,.08), transparent 55%),
      linear-gradient(180deg, color-mix(in oklab, var(--card-bg) 97%, white 3%), var(--card-bg));
    box-shadow:0 18px 50px rgba(15,23,42,.06);
  }

  .bii-hero::after{
    content:"";
    position:absolute;
    right:-80px;
    top:-80px;
    width:220px;
    height:220px;
    border-radius:999px;
    background:radial-gradient(circle, rgba(15,118,110,.08), transparent 65%);
    pointer-events:none;
  }

  .bii-title{
    margin:0;
    font-size:clamp(30px, 3vw, 42px);
    line-height:1.02;
    letter-spacing:-.04em;
    font-weight:900;
    color:var(--text);
  }

  .bii-subtitle{
    margin:10px 0 0;
    max-width:900px;
    font-size:14px;
    line-height:1.6;
    color:var(--muted);
  }

  .bii-hero-actions{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    justify-content:flex-end;
    align-items:flex-start;
  }

  .bii-btn{
    appearance:none;
    border:1px solid var(--card-border);
    background:var(--card-bg);
    color:var(--text);
    border-radius:14px;
    padding:10px 14px;
    min-height:42px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    text-decoration:none;
    font-size:14px;
    font-weight:800;
    line-height:1;
    white-space:nowrap;
    cursor:pointer;
    transition:
      transform .18s ease,
      box-shadow .18s ease,
      border-color .18s ease,
      background .18s ease,
      color .18s ease;
  }

  .bii-btn:hover{
    transform:translateY(-1px);
    border-color:color-mix(in oklab, var(--accent) 28%, var(--card-border));
    box-shadow:0 12px 24px rgba(15,23,42,.08);
  }

  .bii-btn-primary{
    border-color:transparent;
    color:#fff;
    background:linear-gradient(180deg, #103a51, #0f2f42);
  }

  .bii-btn-soft{
    background:var(--panel-bg);
  }

  .bii-btn-success{
    color:#166534;
    border-color:rgba(22,163,74,.18);
    background:rgba(22,163,74,.08);
  }

  .bii-btn-warn{
    color:#92400e;
    border-color:rgba(245,158,11,.20);
    background:rgba(245,158,11,.10);
  }

  .bii-btn-danger{
    color:#9f1239;
    border-color:rgba(225,29,72,.18);
    background:rgba(225,29,72,.06);
  }

  .bii-btn[disabled]{
    opacity:.55;
    cursor:not-allowed;
    pointer-events:none;
    transform:none;
    box-shadow:none;
  }

  .bii-alert{
    border:1px solid var(--card-border);
    background:var(--card-bg);
    border-radius:18px;
    padding:14px 16px;
    font-size:14px;
  }

  .bii-alert-success{
    border-color:rgba(22,163,74,.22);
    background:rgba(22,163,74,.08);
    color:#166534;
  }

  .bii-alert-danger{
    border-color:rgba(220,38,38,.18);
    background:rgba(220,38,38,.08);
    color:#991b1b;
  }

  .bii-alert-warning{
    border-color:rgba(245,158,11,.18);
    background:rgba(245,158,11,.10);
    color:#92400e;
  }

  .bii-errors{
    margin:8px 0 0;
    padding-left:18px;
  }

  .bii-card{
    border:1px solid var(--card-border);
    background:var(--card-bg);
    border-radius:24px;
    box-shadow:0 12px 32px rgba(15,23,42,.05);
    overflow:hidden;
  }

  .bii-card-head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    flex-wrap:wrap;
    padding:18px 20px;
    border-bottom:1px solid var(--card-border);
    background:linear-gradient(180deg, #fff, color-mix(in oklab, var(--panel-bg) 92%, white 8%));
  }

  .bii-card-title{
    margin:0;
    font-size:18px;
    line-height:1.15;
    font-weight:900;
    color:var(--text);
    letter-spacing:-.02em;
  }

  .bii-card-sub{
    margin-top:4px;
    color:var(--muted);
    font-size:13px;
  }

  .bii-body{
    padding:20px;
  }

  .bii-kpis{
    display:grid;
    grid-template-columns:repeat(5, minmax(0,1fr));
    gap:14px;
  }

  .bii-kpi{
    border:1px solid var(--card-border);
    border-radius:18px;
    background:linear-gradient(180deg, color-mix(in oklab, var(--panel-bg) 94%, white 6%), var(--panel-bg));
    padding:16px;
    display:grid;
    gap:6px;
  }

  .bii-kpi-label{
    font-size:11px;
    text-transform:uppercase;
    letter-spacing:.06em;
    color:var(--muted);
    font-weight:900;
  }

  .bii-kpi-value{
    font-size:25px;
    line-height:1.05;
    color:var(--text);
    font-weight:900;
  }

  .bii-kpi-value.sm{
    font-size:18px;
  }

  .bii-filter-grid{
    display:grid;
    grid-template-columns:1.2fr 1.2fr auto;
    gap:14px;
    align-items:end;
  }

  .bii-searchbox{
    display:grid;
    grid-template-columns:1fr auto;
    min-height:48px;
    border:1px solid var(--card-border);
    border-radius:16px;
    overflow:hidden;
    background:var(--panel-bg);
  }

  .bii-searchbox input{
    border:0;
    background:transparent;
    color:var(--text);
    outline:none;
    padding:0 14px;
    min-height:48px;
  }

  .bii-searchbox button{
    border:0;
    border-left:1px solid var(--card-border);
    background:transparent;
    color:var(--text);
    font-weight:900;
    padding:0 16px;
    cursor:pointer;
  }

  .bii-toolbar-grid{
    display:grid;
    grid-template-columns:repeat(12, minmax(0,1fr));
    gap:14px;
    margin-top:14px;
    align-items:end;
  }

  .bii-field{
    display:grid;
    gap:8px;
  }

  .bii-field.col-12{ grid-column:span 12; }
  .bii-field.col-8{ grid-column:span 8; }
  .bii-field.col-6{ grid-column:span 6; }
  .bii-field.col-5{ grid-column:span 5; }
  .bii-field.col-4{ grid-column:span 4; }
  .bii-field.col-3{ grid-column:span 3; }
  .bii-field.col-2{ grid-column:span 2; }
  .bii-field.col-1{ grid-column:span 1; }

  .bii-label{
    font-size:12px;
    font-weight:900;
    text-transform:uppercase;
    letter-spacing:.05em;
    color:var(--muted);
  }

  .bii-input,
  .bii-select,
  .bii-textarea,
  .bii-mini-input,
  .bii-bulk-input{
    width:100%;
    min-height:44px;
    border:1px solid var(--card-border);
    background:var(--panel-bg);
    color:var(--text);
    border-radius:14px;
    padding:10px 12px;
    outline:none;
    transition:border-color .18s ease, box-shadow .18s ease, background .18s ease;
  }

  .bii-mini-input{
    min-height:40px;
    border-radius:12px;
    font-size:13px;
  }

  .bii-bulk-input{
    min-height:40px;
    border-radius:10px;
    font-size:13px;
    padding:8px 10px;
  }

  .bii-textarea{
    min-height:100px;
    resize:vertical;
  }

  .bii-input:focus,
  .bii-select:focus,
  .bii-textarea:focus,
  .bii-mini-input:focus,
  .bii-bulk-input:focus{
    border-color:color-mix(in oklab, var(--accent) 45%, var(--card-border));
    box-shadow:0 0 0 4px color-mix(in oklab, var(--accent) 12%, transparent);
    background:var(--card-bg);
  }

  .bii-chipbar{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
  }

  .bii-chip{
    display:inline-flex;
    align-items:center;
    gap:8px;
    min-height:34px;
    padding:0 12px;
    border-radius:999px;
    border:1px solid var(--card-border);
    background:var(--panel-bg);
    color:var(--text);
    font-size:12px;
    font-weight:800;
  }

  .bii-chip b{
    font-weight:900;
  }

  .bii-help{
    font-size:12px;
    line-height:1.6;
    color:var(--muted);
  }

  .bii-divider{
    height:1px;
    background:var(--card-border);
    margin:18px 0;
  }

  .bii-panels{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:18px;
  }

  .bii-accordion-toggle{
    width:100%;
    border:0;
    border-bottom:1px solid var(--card-border);
    background:linear-gradient(180deg, #fff, color-mix(in oklab, var(--panel-bg) 92%, white 8%));
    padding:18px 20px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    cursor:pointer;
    text-align:left;
    color:var(--text);
    font-weight:900;
    font-size:16px;
  }

  .bii-accordion-meta{
    display:grid;
    gap:4px;
  }

  .bii-accordion-title{
    font-size:16px;
    font-weight:900;
    letter-spacing:-.01em;
  }

  .bii-accordion-sub{
    font-size:12px;
    color:var(--muted);
    font-weight:700;
  }

  .bii-accordion-icon{
    width:34px;
    height:34px;
    border-radius:999px;
    display:grid;
    place-items:center;
    border:1px solid var(--card-border);
    background:var(--panel-bg);
    font-size:20px;
    line-height:1;
  }

  .bii-accordion-body{
    padding:18px 20px 20px;
  }

  .bii-grid{
    display:grid;
    grid-template-columns:repeat(12, minmax(0,1fr));
    gap:14px;
  }

  .bii-check{
    width:18px;
    height:18px;
    accent-color:var(--accent);
  }

  .bii-toolbar{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    flex-wrap:wrap;
    padding:16px 20px;
    border-bottom:1px solid var(--card-border);
    background:linear-gradient(180deg, color-mix(in oklab, var(--panel-bg) 92%, white 8%), var(--panel-bg));
  }

  .bii-toolbar-left,
  .bii-toolbar-right{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    align-items:center;
  }

  .bii-table-wrap{
    overflow:auto;
  }

  .bii-table{
    width:100%;
    min-width:1700px;
    border-collapse:separate;
    border-spacing:0;
  }

  .bii-table thead th{
    position:sticky;
    top:0;
    z-index:1;
    background:color-mix(in oklab, var(--panel-bg) 94%, white 6%);
    color:var(--muted);
    font-size:12px;
    text-transform:uppercase;
    letter-spacing:.05em;
    text-align:left;
    padding:14px 16px;
    border-bottom:1px solid var(--card-border);
    white-space:nowrap;
  }

  .bii-table tbody td{
    padding:14px 16px;
    vertical-align:top;
    border-bottom:1px solid color-mix(in oklab, var(--card-border) 78%, transparent);
    color:var(--text);
    font-size:14px;
  }

  .bii-table tbody tr:hover{
    background:color-mix(in oklab, var(--panel-bg) 62%, transparent);
  }

  .bii-id{
    font-size:15px;
    font-weight:900;
    color:var(--text);
  }

  .bii-muted{
    color:var(--muted);
  }

  .bii-stack{
    display:grid;
    gap:4px;
  }

  .bii-stack.tight{
    gap:2px;
  }

  .bii-badge{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:28px;
    border-radius:999px;
    padding:0 10px;
    font-size:12px;
    font-weight:900;
    white-space:nowrap;
    border:1px solid transparent;
  }

  .bii-badge-soft{
    background:var(--panel-bg);
    color:var(--text);
    border-color:var(--card-border);
  }

  .bii-status{
    background:rgba(148,163,184,.10);
    color:var(--text);
    border:1px solid rgba(148,163,184,.16);
  }

  .bii-status.is-draft{ background:rgba(100,116,139,.12); color:#475569; }
  .bii-status.is-pending{ background:rgba(245,158,11,.12); color:#92400e; }
  .bii-status.is-generated{ background:rgba(59,130,246,.12); color:#1d4ed8; }
  .bii-status.is-stamped{ background:rgba(14,165,233,.12); color:#0369a1; }
  .bii-status.is-sent{ background:rgba(16,185,129,.12); color:#047857; }
  .bii-status.is-paid{ background:rgba(22,163,74,.12); color:#166534; }
  .bii-status.is-cancelled{ background:rgba(107,114,128,.14); color:#374151; }
  .bii-status.is-error{ background:rgba(220,38,38,.12); color:#991b1b; }
  .bii-status.is-active{ background:rgba(59,130,246,.12); color:#1d4ed8; }
  .bii-status.is-issued{ background:rgba(16,185,129,.12); color:#047857; }
  .bii-status.is-default{ background:rgba(148,163,184,.10); color:var(--text); }

  .bii-docs{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
  }

  .bii-doc{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:32px;
    padding:0 12px;
    border-radius:999px;
    border:1px solid var(--card-border);
    background:var(--panel-bg);
    color:var(--text);
    text-decoration:none;
    font-size:12px;
    font-weight:900;
  }

  .bii-actions-col{
    display:grid;
    gap:8px;
    min-width:260px;
  }

  .bii-actions-row{
    display:flex;
    justify-content:flex-end;
    gap:8px;
    flex-wrap:wrap;
  }

  .bii-mini-form{
    display:grid;
    gap:8px;
  }

  .bii-mini-row{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
    align-items:center;
    justify-content:flex-end;
  }

  .bii-mini-help{
    font-size:11px;
    color:var(--muted);
    text-align:right;
  }

  .bii-bulk-table{
    width:100%;
    min-width:1120px;
    border-collapse:separate;
    border-spacing:0;
  }

  .bii-bulk-table th,
  .bii-bulk-table td{
    padding:10px;
    border-bottom:1px solid var(--card-border);
    vertical-align:top;
  }

  .bii-bulk-table th{
    background:color-mix(in oklab, var(--panel-bg) 94%, white 6%);
    color:var(--muted);
    font-size:12px;
    text-transform:uppercase;
    letter-spacing:.04em;
    text-align:left;
  }

  .bii-bulk-actions{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    margin-top:14px;
  }

  .bii-empty{
    padding:34px 20px;
    text-align:center;
    color:var(--muted);
  }

  .bii-pagination{
    padding:18px 20px;
    border-top:1px solid var(--card-border);
  }

  .bii-mobile-cards{
    display:none;
    padding:16px;
    gap:14px;
  }

  .bii-mobile-card{
    border:1px solid var(--card-border);
    border-radius:20px;
    background:var(--card-bg);
    padding:14px;
    display:grid;
    gap:12px;
  }

  .bii-mobile-head{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:10px;
  }

  .bii-mobile-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:10px;
  }

  .bii-mobile-item{
    display:grid;
    gap:4px;
  }

  .bii-mobile-label{
    font-size:11px;
    text-transform:uppercase;
    letter-spacing:.05em;
    color:var(--muted);
    font-weight:900;
  }

  .bii-mobile-value{
    font-size:13px;
    color:var(--text);
    font-weight:700;
    word-break:break-word;
  }

  @media (max-width: 1320px){
    .bii-kpis{
      grid-template-columns:repeat(3, minmax(0,1fr));
    }

    .bii-panels{
      grid-template-columns:1fr;
    }
  }

  @media (max-width: 1100px){
    .bii-hero{
      grid-template-columns:1fr;
    }

    .bii-filter-grid{
      grid-template-columns:1fr;
    }

    .bii-field.col-12,
    .bii-field.col-8,
    .bii-field.col-6,
    .bii-field.col-5,
    .bii-field.col-4,
    .bii-field.col-3,
    .bii-field.col-2,
    .bii-field.col-1{
      grid-column:span 12;
    }

    .bii-kpis{
      grid-template-columns:1fr 1fr;
    }
  }

  @media (max-width: 780px){
    .bii-desktop-table{
      display:none;
    }

    .bii-mobile-cards{
      display:grid;
    }
  }

  @media (max-width: 680px){
    .bii-kpis{
      grid-template-columns:1fr;
    }

    .bii-mobile-grid{
      grid-template-columns:1fr;
    }

    .bii-toolbar,
    .bii-card-head,
    .bii-body,
    .bii-accordion-body{
      padding-left:14px;
      padding-right:14px;
    }

    .bii-accordion-toggle{
      padding-left:14px;
      padding-right:14px;
    }

    .bii-actions-row,
    .bii-mini-row,
    .bii-bulk-actions{
      justify-content:flex-start;
    }

    .bii-btn{
      width:100%;
    }

    .bii-hero-actions{
      width:100%;
    }

    .bii-hero-actions .bii-btn{
      flex:1 1 180px;
    }
  }
</style>
@endpush

@section('content')
<div class="bii-page">
  <div class="bii-shell">

    <section class="bii-hero">
      <div>
        <h1 class="bii-title">Facturas emitidas</h1>
        <p class="bii-subtitle">
          Módulo administrativo de facturación con enfoque más limpio y profesional, manteniendo tu flujo real:
          listado, filtros, alta manual, carga masiva, selección múltiple, envío, reenvío, descarga y cancelación.
        </p>
      </div>

      <div class="bii-hero-actions">
        <a href="{{ $routeDashboard }}" class="bii-btn">Dashboard</a>
        <a href="{{ $routeRequests }}" class="bii-btn">Solicitudes</a>
        <button type="button" class="bii-btn bii-btn-primary" data-bii-open="singleForm">+ Factura</button>
        <button type="button" class="bii-btn" data-bii-open="bulkForm">Carga masiva</button>
      </div>
    </section>

    @if (session('ok'))
      <div class="bii-alert bii-alert-success">
        {{ session('ok') }}
      </div>
    @endif

    @if ($errors->any())
      <div class="bii-alert bii-alert-danger">
        <strong>Se encontraron errores:</strong>
        <ul class="bii-errors">
          @foreach ($errors->all() as $err)
            <li>{{ $err }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    @if (!empty($error))
      <div class="bii-alert bii-alert-warning">
        {{ $error }}
      </div>
    @endif

    <section class="bii-card">
      <div class="bii-card-head">
        <div>
          <h2 class="bii-card-title">Resumen</h2>
          <div class="bii-card-sub">Métricas rápidas del listado visible.</div>
        </div>
      </div>

      <div class="bii-body">
        <div class="bii-kpis">
          <div class="bii-kpi">
            <div class="bii-kpi-label">Total visibles</div>
            <div class="bii-kpi-value">{{ number_format($totalRows) }}</div>
          </div>

          <div class="bii-kpi">
            <div class="bii-kpi-label">Monto visible</div>
            <div class="bii-kpi-value sm">{{ $money($totalAmount) }}</div>
          </div>

          <div class="bii-kpi">
            <div class="bii-kpi-label">Emitidas</div>
            <div class="bii-kpi-value">{{ number_format($issuedCount) }}</div>
          </div>

          <div class="bii-kpi">
            <div class="bii-kpi-label">Enviadas</div>
            <div class="bii-kpi-value">{{ number_format($sentCount) }}</div>
          </div>

          <div class="bii-kpi">
            <div class="bii-kpi-label">Pagadas / canceladas</div>
            <div class="bii-kpi-value sm">{{ number_format($paidCount) }} / {{ number_format($cancelledCount) }}</div>
          </div>
        </div>
      </div>
    </section>

    <section class="bii-card">
      <div class="bii-card-head">
        <div>
          <h2 class="bii-card-title">Búsqueda y filtros</h2>
          <div class="bii-card-sub">Consulta rápida, filtros operativos y rango visual.</div>
        </div>
      </div>

      <div class="bii-body">
        <form method="GET" action="{{ $routeIndex }}">
          <div class="bii-filter-grid">
            <div class="bii-field">
              <label class="bii-label" for="bii_date_from">Rango visual</label>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                <input id="bii_date_from" type="date" name="date_from" class="bii-input" value="{{ $currentDateFrom }}">
                <input id="bii_date_to" type="date" name="date_to" class="bii-input" value="{{ $currentDateTo }}">
              </div>
            </div>

            <div class="bii-field">
              <label class="bii-label" for="q">Omnisearch</label>
              <div class="bii-searchbox">
                <input
                  id="q"
                  type="text"
                  name="q"
                  value="{{ $q }}"
                  placeholder="UUID, cuenta, RFC, razón social, folio..."
                >
                <button type="submit">Buscar</button>
              </div>
            </div>

            <div class="bii-field">
              <label class="bii-label">&nbsp;</label>
              <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <button type="submit" class="bii-btn bii-btn-primary">Aplicar</button>
                <a href="{{ $routeIndex }}" class="bii-btn">Limpiar</a>
              </div>
            </div>
          </div>

          <div class="bii-toolbar-grid">
            <div class="bii-field col-3">
              <label class="bii-label" for="status">Estado</label>
              <select id="status" name="status" class="bii-select">
                <option value="">Todos</option>
                @foreach (['draft','pending','generated','stamped','issued','active','sent','paid','cancelled','error'] as $opt)
                  <option value="{{ $opt }}" @selected($status === $opt)>{{ $statusLabel($opt) }}</option>
                @endforeach
              </select>
            </div>

            <div class="bii-field col-3">
              <label class="bii-label" for="period">Periodo</label>
              <input
                type="text"
                name="period"
                id="period"
                class="bii-input"
                value="{{ $period }}"
                placeholder="YYYY-MM"
              >
            </div>

            <div class="bii-field col-3">
              <label class="bii-label">Vista actual</label>
              <div class="bii-chipbar">
                <span class="bii-chip"><b>{{ $status !== '' ? $statusLabel($status) : 'Todos' }}</b></span>
                <span class="bii-chip"><b>{{ $period !== '' ? $period : 'Todos los periodos' }}</b></span>
              </div>
            </div>

            <div class="bii-field col-3">
              <label class="bii-label">Accesos rápidos</label>
              <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <button type="button" class="bii-btn" data-bii-open="singleForm">Alta manual</button>
                <button type="button" class="bii-btn" data-bii-open="bulkForm">Lote manual</button>
              </div>
            </div>
          </div>
        </form>
      </div>
    </section>

    <div class="bii-panels">
      <section class="bii-card">
        <button type="button" class="bii-accordion-toggle" data-bii-accordion="singleForm" aria-expanded="{{ $hasAnyErrors ? 'true' : 'false' }}">
          <span class="bii-accordion-meta">
            <span class="bii-accordion-title">Alta manual unitaria</span>
            <span class="bii-accordion-sub">Registrar una sola factura manual con PDF/XML y envío opcional.</span>
          </span>
          <span class="bii-accordion-icon">＋</span>
        </button>

        <div class="bii-accordion-body" data-bii-panel="singleForm" @if(!$hasAnyErrors) hidden @endif>
          <form method="POST" action="{{ $routeStoreOne }}" enctype="multipart/form-data">
            @csrf

            <div class="bii-grid">
              <div class="bii-field col-3">
                <label class="bii-label" for="manual_account_id">Cuenta</label>
                <input id="manual_account_id" type="text" name="account_id" class="bii-input" value="{{ old('account_id') }}" placeholder="ID cuenta" required>
              </div>

              <div class="bii-field col-3">
                <label class="bii-label" for="manual_period">Periodo</label>
                <input id="manual_period" type="text" name="period" class="bii-input" value="{{ old('period', now()->format('Y-m')) }}" placeholder="YYYY-MM" required>
              </div>

              <div class="bii-field col-3">
                <label class="bii-label" for="manual_amount_mxn">Monto MXN</label>
                <input id="manual_amount_mxn" type="number" step="0.01" min="0" name="amount_mxn" class="bii-input" value="{{ old('amount_mxn') }}" placeholder="0.00">
              </div>

              <div class="bii-field col-3">
                <label class="bii-label" for="manual_status">Estado</label>
                <select id="manual_status" name="status" class="bii-select">
                  <option value="issued" {{ old('status', 'issued') === 'issued' ? 'selected' : '' }}>Emitida</option>
                  <option value="active" {{ old('status') === 'active' ? 'selected' : '' }}>Activa</option>
                  <option value="sent" {{ old('status') === 'sent' ? 'selected' : '' }}>Enviada</option>
                  <option value="paid" {{ old('status') === 'paid' ? 'selected' : '' }}>Pagada</option>
                  <option value="pending" {{ old('status') === 'pending' ? 'selected' : '' }}>Pendiente</option>
                </select>
              </div>

              <div class="bii-field col-3">
                <label class="bii-label" for="manual_issued_at">Fecha y hora</label>
                <input id="manual_issued_at" type="datetime-local" name="issued_at" class="bii-input" value="{{ old('issued_at') }}">
              </div>

              <div class="bii-field col-3">
                <label class="bii-label" for="manual_issued_date">Solo fecha</label>
                <input id="manual_issued_date" type="date" name="issued_date" class="bii-input" value="{{ old('issued_date') }}">
              </div>

              <div class="bii-field col-3">
                <label class="bii-label" for="manual_serie">Serie</label>
                <input id="manual_serie" type="text" name="serie" class="bii-input" value="{{ old('serie') }}" placeholder="A">
              </div>

              <div class="bii-field col-3">
                <label class="bii-label" for="manual_folio">Folio</label>
                <input id="manual_folio" type="text" name="folio" class="bii-input" value="{{ old('folio') }}" placeholder="1001">
              </div>

              <div class="bii-field col-6">
                <label class="bii-label" for="manual_cfdi_uuid">UUID</label>
                <input id="manual_cfdi_uuid" type="text" name="cfdi_uuid" class="bii-input" value="{{ old('cfdi_uuid') }}" placeholder="UUID CFDI">
              </div>

              <div class="bii-field col-6">
                <label class="bii-label" for="manual_source">Origen</label>
                <input id="manual_source" type="text" name="source" class="bii-input" value="{{ old('source', 'manual_admin') }}" placeholder="manual_admin">
              </div>

              <div class="bii-field col-6">
                <label class="bii-label" for="manual_to">Enviar a correos</label>
                <input id="manual_to" type="text" name="to" class="bii-input" value="{{ old('to') }}" placeholder="uno@correo.com,dos@correo.com">
              </div>

              <div class="bii-field col-6">
                <div class="bii-help" style="padding-top:30px;">
                  Si lo dejas vacío, el controlador resolverá destinatarios desde la cuenta y/o historial de la factura.
                </div>
              </div>

              <div class="bii-field col-6">
                <label class="bii-label" for="manual_pdf">PDF</label>
                <input id="manual_pdf" type="file" name="pdf" class="bii-input" accept="application/pdf">
              </div>

              <div class="bii-field col-6">
                <label class="bii-label" for="manual_xml">XML</label>
                <input id="manual_xml" type="file" name="xml" class="bii-input" accept=".xml,application/xml,text/xml,.txt,text/plain">
              </div>

              <div class="bii-field col-12">
                <label class="bii-label" for="manual_notes">Notas</label>
                <textarea id="manual_notes" name="notes" class="bii-textarea" placeholder="Observaciones internas, referencia de venta, origen, etc.">{{ old('notes') }}</textarea>
              </div>

              <div class="bii-field col-12">
                <label style="display:flex;align-items:center;gap:10px;font-weight:700;color:var(--text);">
                  <input type="checkbox" name="send_now" value="1" class="bii-check" {{ old('send_now') ? 'checked' : '' }}>
                  Enviar por correo inmediatamente después de guardar
                </label>
              </div>

              <div class="bii-field col-12">
                <button type="submit" class="bii-btn bii-btn-primary">Guardar factura manual</button>
              </div>
            </div>
          </form>
        </div>
      </section>

      <section class="bii-card">
        <button type="button" class="bii-accordion-toggle" data-bii-accordion="bulkForm" aria-expanded="false">
          <span class="bii-accordion-meta">
            <span class="bii-accordion-title">Alta manual masiva</span>
            <span class="bii-accordion-sub">Registrar varias facturas en un solo envío, conservando tu flujo actual.</span>
          </span>
          <span class="bii-accordion-icon">＋</span>
        </button>

        <div class="bii-accordion-body" data-bii-panel="bulkForm" hidden>
          <form method="POST" action="{{ $routeStoreBulk }}" enctype="multipart/form-data">
            @csrf

            <div class="bii-table-wrap">
              <table class="bii-bulk-table" id="biiBulkTable">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Cuenta</th>
                    <th>Periodo</th>
                    <th>UUID</th>
                    <th>Serie</th>
                    <th>Folio</th>
                    <th>Estado</th>
                    <th>Monto</th>
                    <th>Fecha/hora</th>
                    <th>Fecha</th>
                    <th>Origen</th>
                    <th>Notas</th>
                    <th>PDF</th>
                    <th>XML</th>
                  </tr>
                </thead>
                <tbody>
                  @for ($i = 0; $i < $bulkRowsCount; $i++)
                    <tr>
                      <td>{{ $i + 1 }}</td>
                      <td><input type="text" name="account_id[]" class="bii-bulk-input" value="{{ $bulkOldAccountId[$i] ?? '' }}" placeholder="Cuenta"></td>
                      <td><input type="text" name="period[]" class="bii-bulk-input" value="{{ $bulkOldPeriod[$i] ?? '' }}" placeholder="YYYY-MM"></td>
                      <td><input type="text" name="cfdi_uuid[]" class="bii-bulk-input" value="{{ $bulkOldCfdiUuid[$i] ?? '' }}" placeholder="UUID"></td>
                      <td><input type="text" name="serie[]" class="bii-bulk-input" value="{{ $bulkOldSerie[$i] ?? '' }}" placeholder="Serie"></td>
                      <td><input type="text" name="folio[]" class="bii-bulk-input" value="{{ $bulkOldFolio[$i] ?? '' }}" placeholder="Folio"></td>
                      <td><input type="text" name="status[]" class="bii-bulk-input" value="{{ $bulkOldStatus[$i] ?? 'issued' }}" placeholder="issued"></td>
                      <td><input type="text" name="amount_mxn[]" class="bii-bulk-input" value="{{ $bulkOldAmountMxn[$i] ?? '' }}" placeholder="0.00"></td>
                      <td><input type="text" name="issued_at[]" class="bii-bulk-input" value="{{ $bulkOldIssuedAt[$i] ?? '' }}" placeholder="2026-03-11 10:00:00"></td>
                      <td><input type="text" name="issued_date[]" class="bii-bulk-input" value="{{ $bulkOldIssuedDate[$i] ?? '' }}" placeholder="2026-03-11"></td>
                      <td><input type="text" name="source[]" class="bii-bulk-input" value="{{ $bulkOldSource[$i] ?? 'manual_bulk_admin' }}" placeholder="manual_bulk_admin"></td>
                      <td><input type="text" name="notes[]" class="bii-bulk-input" value="{{ $bulkOldNotes[$i] ?? '' }}" placeholder="Notas"></td>
                      <td><input type="file" name="pdf_files[{{ $i }}]" class="bii-bulk-input" accept="application/pdf"></td>
                      <td><input type="file" name="xml_files[{{ $i }}]" class="bii-bulk-input" accept=".xml,application/xml,text/xml,.txt,text/plain"></td>
                    </tr>
                  @endfor
                </tbody>
              </table>
            </div>

            <div class="bii-bulk-actions">
              <button type="button" class="bii-btn bii-btn-soft" id="biiAddBulkRow">Agregar fila</button>
            </div>

            <div class="bii-divider"></div>

            <div class="bii-grid">
              <div class="bii-field col-12">
                <label class="bii-label" for="bulk_manual_to">Correos destino para envío inmediato</label>
                <input id="bulk_manual_to" type="text" name="to" class="bii-input" value="{{ old('to') }}" placeholder="uno@correo.com,dos@correo.com">
              </div>

              <div class="bii-field col-12">
                <label style="display:flex;align-items:center;gap:10px;font-weight:700;color:var(--text);">
                  <input type="checkbox" name="send_now" value="1" class="bii-check" {{ old('send_now') ? 'checked' : '' }}>
                  Intentar envío por correo para cada factura guardada
                </label>
              </div>

              <div class="bii-field col-12">
                <div class="bii-help">
                  Esta carga masiva ya está alineada con <strong>bulkStoreManual()</strong> y usa
                  <strong>account_id[]</strong>, <strong>period[]</strong>, <strong>cfdi_uuid[]</strong>,
                  <strong>pdf_files[index]</strong> y <strong>xml_files[index]</strong>.
                </div>
              </div>

              <div class="bii-field col-12">
                <button type="submit" class="bii-btn bii-btn-primary">Registrar lote manual</button>
              </div>
            </div>
          </form>
        </div>
      </section>
    </div>

    <section class="bii-card">
      <div class="bii-toolbar">
        <div class="bii-toolbar-left">
          <strong>Envío masivo</strong>
          <span class="bii-muted">Selecciona facturas visibles y envíalas a uno o varios correos.</span>
        </div>

        <div class="bii-toolbar-right">
          <button type="button" class="bii-btn" id="biiSelectAllBtn">Seleccionar visibles</button>
          <button type="button" class="bii-btn" id="biiClearAllBtn">Limpiar selección</button>
        </div>
      </div>

      <div class="bii-body">
        <form method="POST" action="{{ $routeBulkSend }}" id="biiBulkSendForm">
          @csrf

          <div class="bii-grid">
            <div class="bii-field col-12">
              <label class="bii-label" for="bulk_to">Correos destino</label>
              <input id="bulk_to" type="text" name="to" class="bii-input" value="{{ old('to') }}" placeholder="uno@correo.com,dos@correo.com">
            </div>

            <div class="bii-field col-12">
              <input type="hidden" name="invoice_ids" id="biiBulkInvoiceIds" value="">
              <div class="bii-help">
                Si dejas los correos vacíos, el controlador intentará resolverlos desde la cuenta, meta o historial de envío de cada factura.
              </div>
            </div>

            <div class="bii-field col-12">
              <button type="submit" class="bii-btn bii-btn-success" id="biiBulkSendBtn" disabled>
                Enviar selección
              </button>
            </div>
          </div>
        </form>
      </div>
    </section>

    <section class="bii-card">
      <div class="bii-card-head">
        <div>
          <h2 class="bii-card-title">Listado de facturas</h2>
          <div class="bii-card-sub">Vista principal del módulo con selección múltiple, envío y acciones por fila.</div>
        </div>

        <div class="bii-chipbar">
          <span class="bii-chip"><b>{{ number_format($totalRows) }}</b> resultados</span>
          <span class="bii-chip"><b>{{ $money($totalAmount) }}</b> visible</span>
        </div>
      </div>

      @if (collect($rows)->count() === 0)
        <div class="bii-empty">
          No hay facturas para mostrar con los filtros actuales.
        </div>
      @else
        <div class="bii-desktop-table">
          <div class="bii-table-wrap">
            <table class="bii-table">
              <thead>
                <tr>
                  <th style="width:44px;">
                    <input type="checkbox" class="bii-check" id="biiCheckAllHead">
                  </th>
                  <th>ID</th>
                  <th>Fecha</th>
                  <th>Cuenta</th>
                  <th>Cliente</th>
                  <th>RFC / UUID</th>
                  <th>Periodo</th>
                  <th>Estado</th>
                  <th>Total</th>
                  <th>Docs</th>
                  <th>Destinos / envío</th>
                  <th style="text-align:right">Acciones</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($rows as $row)
                  @php
                    $invoiceId    = $prop($row, 'id');
                    $accountId    = $prop($row, 'account_id');
                    $accountName  = $prop($row, 'account_name');
                    $accountEmail = $prop($row, 'account_email');
                    $accountRfc   = $prop($row, 'account_rfc', $prop($row, 'rfc'));
                    $uuid         = $prop($row, 'cfdi_uuid');
                    $rowStatus    = $prop($row, 'status');
                    $periodRow    = $prop($row, 'period');
                    $rowStatusNormalized = strtolower(trim((string) $rowStatus));

                    $total = $prop($row, 'display_total_mxn');
                    if ($total === null || $total === '') $total = $prop($row, 'amount_mxn');
                    if ($total === null || $total === '') $total = $prop($row, 'monto_mxn');
                    if ($total === null || $total === '') $total = $prop($row, 'total');
                    if ($total === null || $total === '') $total = $prop($row, 'subtotal');
                    if (($total === null || $total === '') && !empty($prop($row, 'amount'))) $total = ((float) $prop($row, 'amount')) / 100;
                    if (($total === null || $total === '') && !empty($prop($row, 'amount_cents'))) $total = ((float) $prop($row, 'amount_cents')) / 100;

                    $createdAt = $prop($row, 'issued_at', $prop($row, 'created_at'));

                    $recipientList = $prop($row, 'recipient_list', []);
                    if (!is_array($recipientList)) $recipientList = [];

                    $defaultTo = old('to_' . $invoiceId);
                    if ($defaultTo === null || $defaultTo === '') {
                        $defaultTo = !empty($recipientList)
                            ? implode(',', $recipientList)
                            : (string) ($accountEmail ?: '');
                    }

                    $hasPdf = !empty($prop($row, 'pdf_path'));
                    $hasXml = !empty($prop($row, 'xml_path'));
                  @endphp

                  <tr>
                    <td>
                      <input
                        type="checkbox"
                        class="bii-check bii-row-check"
                        value="{{ $invoiceId }}"
                        data-invoice-id="{{ $invoiceId }}"
                      >
                    </td>

                    <td>
                      <div class="bii-stack tight">
                        <div class="bii-id">#{{ $invoiceId }}</div>
                        <div class="bii-muted">{{ $prop($row, 'folio') ?: '—' }}</div>
                      </div>
                    </td>

                    <td>
                      <div class="bii-stack tight">
                        <strong>{{ $fmtDate($createdAt) }}</strong>
                        <span class="bii-muted">{{ $prop($row, 'source') ?: '—' }}</span>
                      </div>
                    </td>

                    <td>
                      @if($accountId)
                        <span class="bii-badge bii-badge-soft">{{ $accountId }}</span>
                      @else
                        <span class="bii-muted">—</span>
                      @endif
                    </td>

                    <td>
                      <div class="bii-stack">
                        <strong>{{ $accountName ?: '—' }}</strong>
                        <span class="bii-muted">{{ $accountEmail ?: '—' }}</span>
                      </div>
                    </td>

                    <td>
                      <div class="bii-stack tight">
                        <span>{{ $accountRfc ?: '—' }}</span>
                        <span class="bii-muted">{{ $uuid ?: '—' }}</span>
                      </div>
                    </td>

                    <td>
                      <span class="bii-badge bii-badge-soft">{{ $periodRow ?: '—' }}</span>
                    </td>

                    <td>
                      <span class="bii-badge bii-status {{ $statusClass($rowStatus) }}">
                        {{ $statusLabel($rowStatus) }}
                      </span>
                    </td>

                    <td>
                      <strong>{{ $money($total) }}</strong>
                    </td>

                    <td>
                      <div class="bii-docs">
                        @if($hasPdf)
                          <a href="{{ route('admin.billing.invoicing.invoices.download', [$invoiceId, 'pdf']) }}" class="bii-doc">PDF</a>
                        @endif

                        @if($hasXml)
                          <a href="{{ route('admin.billing.invoicing.invoices.download', [$invoiceId, 'xml']) }}" class="bii-doc">XML</a>
                        @endif

                        @if(!$hasPdf && !$hasXml)
                          <span class="bii-muted">Sin archivos</span>
                        @endif
                      </div>
                    </td>

                    <td>
                      <div class="bii-actions-col">
                        <form method="POST" action="{{ route('admin.billing.invoicing.invoices.send', $invoiceId) }}" class="bii-mini-form">
                          @csrf

                          <input
                            type="text"
                            name="to"
                            class="bii-mini-input"
                            value="{{ $defaultTo }}"
                            placeholder="uno@correo.com,dos@correo.com"
                          >

                          <div class="bii-mini-row">
                            <button type="submit" class="bii-btn bii-btn-success">Enviar</button>
                            <button
                              type="submit"
                              formaction="{{ route('admin.billing.invoicing.invoices.resend', $invoiceId) }}"
                              class="bii-btn bii-btn-warn"
                            >
                              Reenviar
                            </button>
                          </div>

                          <div class="bii-mini-help">
                            Uno o varios correos separados por coma.
                          </div>
                        </form>
                      </div>
                    </td>

                    <td>
                      <div class="bii-actions-row">
                        <a href="{{ route('admin.billing.invoicing.invoices.show', $invoiceId) }}" class="bii-btn">
                          Ver
                        </a>

                        @if(!in_array($rowStatusNormalized, ['cancelled', 'canceled'], true))
                          <form
                            method="POST"
                            action="{{ route('admin.billing.invoicing.invoices.cancel', $invoiceId) }}"
                            onsubmit="return confirm('¿Seguro que deseas cancelar esta factura?');"
                          >
                            @csrf
                            <button type="submit" class="bii-btn bii-btn-danger">Cancelar</button>
                          </form>
                        @endif
                      </div>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>

        <div class="bii-mobile-cards">
          @foreach ($rows as $row)
            @php
              $invoiceId    = $prop($row, 'id');
              $accountId    = $prop($row, 'account_id');
              $accountName  = $prop($row, 'account_name');
              $accountEmail = $prop($row, 'account_email');
              $accountRfc   = $prop($row, 'account_rfc', $prop($row, 'rfc'));
              $uuid         = $prop($row, 'cfdi_uuid');
              $rowStatus    = $prop($row, 'status');
              $periodRow    = $prop($row, 'period');
              $createdAt    = $prop($row, 'issued_at', $prop($row, 'created_at'));
              $rowStatusNormalized = strtolower(trim((string) $rowStatus));

              $total = $prop($row, 'display_total_mxn');
              if ($total === null || $total === '') $total = $prop($row, 'amount_mxn');
              if ($total === null || $total === '') $total = $prop($row, 'monto_mxn');
              if ($total === null || $total === '') $total = $prop($row, 'total');
              if ($total === null || $total === '') $total = $prop($row, 'subtotal');
              if (($total === null || $total === '') && !empty($prop($row, 'amount'))) $total = ((float) $prop($row, 'amount')) / 100;
              if (($total === null || $total === '') && !empty($prop($row, 'amount_cents'))) $total = ((float) $prop($row, 'amount_cents')) / 100;

              $recipientList = $prop($row, 'recipient_list', []);
              if (!is_array($recipientList)) $recipientList = [];
              $defaultTo = !empty($recipientList) ? implode(',', $recipientList) : (string) ($accountEmail ?: '');
            @endphp

            <div class="bii-mobile-card">
              <div class="bii-mobile-head">
                <div>
                  <div class="bii-id">#{{ $invoiceId }}</div>
                  <div class="bii-muted">{{ $fmtDate($createdAt) }}</div>
                </div>

                <span class="bii-badge bii-status {{ $statusClass($rowStatus) }}">
                  {{ $statusLabel($rowStatus) }}
                </span>
              </div>

              <div class="bii-mobile-grid">
                <div class="bii-mobile-item">
                  <div class="bii-mobile-label">Cuenta</div>
                  <div class="bii-mobile-value">{{ $accountId ?: '—' }}</div>
                </div>

                <div class="bii-mobile-item">
                  <div class="bii-mobile-label">Periodo</div>
                  <div class="bii-mobile-value">{{ $periodRow ?: '—' }}</div>
                </div>

                <div class="bii-mobile-item">
                  <div class="bii-mobile-label">Cliente</div>
                  <div class="bii-mobile-value">{{ $accountName ?: '—' }}</div>
                </div>

                <div class="bii-mobile-item">
                  <div class="bii-mobile-label">Monto</div>
                  <div class="bii-mobile-value">{{ $money($total) }}</div>
                </div>

                <div class="bii-mobile-item">
                  <div class="bii-mobile-label">RFC</div>
                  <div class="bii-mobile-value">{{ $accountRfc ?: '—' }}</div>
                </div>

                <div class="bii-mobile-item">
                  <div class="bii-mobile-label">UUID</div>
                  <div class="bii-mobile-value">{{ $uuid ?: '—' }}</div>
                </div>
              </div>

              <form method="POST" action="{{ route('admin.billing.invoicing.invoices.send', $invoiceId) }}" class="bii-mini-form">
                @csrf

                <input
                  type="text"
                  name="to"
                  class="bii-mini-input"
                  value="{{ $defaultTo }}"
                  placeholder="uno@correo.com,dos@correo.com"
                >

                <div class="bii-actions-row" style="justify-content:flex-start;">
                  <button type="submit" class="bii-btn bii-btn-success">Enviar</button>

                  <button
                    type="submit"
                    formaction="{{ route('admin.billing.invoicing.invoices.resend', $invoiceId) }}"
                    class="bii-btn bii-btn-warn"
                  >
                    Reenviar
                  </button>

                  <a href="{{ route('admin.billing.invoicing.invoices.show', $invoiceId) }}" class="bii-btn">Ver</a>

                  @if(!in_array($rowStatusNormalized, ['cancelled', 'canceled'], true))
                    <button
                      type="submit"
                      formaction="{{ route('admin.billing.invoicing.invoices.cancel', $invoiceId) }}"
                      formmethod="POST"
                      onclick="return confirm('¿Seguro que deseas cancelar esta factura?');"
                      class="bii-btn bii-btn-danger"
                    >
                      Cancelar
                    </button>
                  @endif
                </div>
              </form>
            </div>
          @endforeach
        </div>
      @endif

      @if(method_exists($rows, 'links'))
        <div class="bii-pagination">
          {{ $rows->links() }}
        </div>
      @endif
    </section>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    const bulkForm = document.getElementById('biiBulkSendForm');
    const bulkIdsInput = document.getElementById('biiBulkInvoiceIds');
    const bulkSendBtn = document.getElementById('biiBulkSendBtn');
    const checkAllHead = document.getElementById('biiCheckAllHead');
    const selectAllBtn = document.getElementById('biiSelectAllBtn');
    const clearAllBtn = document.getElementById('biiClearAllBtn');
    const addBulkRowBtn = document.getElementById('biiAddBulkRow');
    const bulkTableBody = document.querySelector('#biiBulkTable tbody');

    function getChecks() {
        return Array.from(document.querySelectorAll('.bii-row-check'));
    }

    function getSelectedIds() {
        return getChecks()
            .filter((el) => el.checked)
            .map((el) => String(el.value).trim())
            .filter((v) => v !== '');
    }

    function syncBulkState() {
        const ids = getSelectedIds();

        if (bulkIdsInput) {
            bulkIdsInput.value = ids.join(',');
        }

        if (bulkSendBtn) {
            bulkSendBtn.disabled = ids.length === 0;
            bulkSendBtn.textContent = ids.length > 0
                ? ('Enviar selección (' + ids.length + ')')
                : 'Enviar selección';
        }

        if (checkAllHead) {
            const checks = getChecks();
            const total = checks.length;
            const checked = ids.length;

            checkAllHead.checked = total > 0 && checked === total;
            checkAllHead.indeterminate = checked > 0 && checked < total;
        }
    }

    if (checkAllHead) {
        checkAllHead.addEventListener('change', function () {
            const checked = !!this.checked;
            getChecks().forEach((el) => {
                el.checked = checked;
            });
            syncBulkState();
        });
    }

    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function () {
            getChecks().forEach((el) => {
                el.checked = true;
            });
            syncBulkState();
        });
    }

    if (clearAllBtn) {
        clearAllBtn.addEventListener('click', function () {
            getChecks().forEach((el) => {
                el.checked = false;
            });
            syncBulkState();
        });
    }

    getChecks().forEach((el) => {
        el.addEventListener('change', syncBulkState);
    });

    if (bulkForm) {
        bulkForm.addEventListener('submit', function (e) {
            const ids = getSelectedIds();

            if (!ids.length) {
                e.preventDefault();
                alert('Selecciona al menos una factura para envío masivo.');
                return;
            }

            if (bulkIdsInput) {
                bulkIdsInput.value = ids.join(',');
            }
        });
    }

    if (addBulkRowBtn && bulkTableBody) {
        addBulkRowBtn.addEventListener('click', function () {
            const rowCount = bulkTableBody.querySelectorAll('tr').length;
            const i = rowCount;

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${i + 1}</td>
                <td><input type="text" name="account_id[]" class="bii-bulk-input" placeholder="Cuenta"></td>
                <td><input type="text" name="period[]" class="bii-bulk-input" placeholder="YYYY-MM"></td>
                <td><input type="text" name="cfdi_uuid[]" class="bii-bulk-input" placeholder="UUID"></td>
                <td><input type="text" name="serie[]" class="bii-bulk-input" placeholder="Serie"></td>
                <td><input type="text" name="folio[]" class="bii-bulk-input" placeholder="Folio"></td>
                <td><input type="text" name="status[]" class="bii-bulk-input" placeholder="issued" value="issued"></td>
                <td><input type="text" name="amount_mxn[]" class="bii-bulk-input" placeholder="0.00"></td>
                <td><input type="text" name="issued_at[]" class="bii-bulk-input" placeholder="2026-03-11 10:00:00"></td>
                <td><input type="text" name="issued_date[]" class="bii-bulk-input" placeholder="2026-03-11"></td>
                <td><input type="text" name="source[]" class="bii-bulk-input" placeholder="manual_bulk_admin" value="manual_bulk_admin"></td>
                <td><input type="text" name="notes[]" class="bii-bulk-input" placeholder="Notas"></td>
                <td><input type="file" name="pdf_files[${i}]" class="bii-bulk-input" accept="application/pdf"></td>
                <td><input type="file" name="xml_files[${i}]" class="bii-bulk-input" accept=".xml,application/xml,text/xml,.txt,text/plain"></td>
            `;
            bulkTableBody.appendChild(tr);
        });
    }

    const accordionButtons = Array.from(document.querySelectorAll('[data-bii-accordion]'));
    const openButtons = Array.from(document.querySelectorAll('[data-bii-open]'));

    function setPanelState(name, open) {
        const btn = document.querySelector('[data-bii-accordion="' + name + '"]');
        const panel = document.querySelector('[data-bii-panel="' + name + '"]');
        if (!btn || !panel) return;

        btn.setAttribute('aria-expanded', open ? 'true' : 'false');

        const icon = btn.querySelector('.bii-accordion-icon');
        if (icon) {
            icon.textContent = open ? '−' : '＋';
        }

        if (open) {
            panel.removeAttribute('hidden');
        } else {
            panel.setAttribute('hidden', 'hidden');
        }
    }

    accordionButtons.forEach((btn) => {
        btn.addEventListener('click', function () {
            const name = this.getAttribute('data-bii-accordion');
            const open = this.getAttribute('aria-expanded') === 'true';
            setPanelState(name, !open);
        });
    });

    openButtons.forEach((btn) => {
        btn.addEventListener('click', function () {
            const name = this.getAttribute('data-bii-open');
            setPanelState(name, true);

            const panel = document.querySelector('[data-bii-panel="' + name + '"]');
            if (panel) {
                panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    syncBulkState();
})();
</script>
@endpush