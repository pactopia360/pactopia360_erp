@extends('layouts.admin')

@section('title', 'Billing · Email Estados')
@section('layout', 'full')
@section('contentLayout', 'full')

@push('styles')
@php
  $p360PagerCssPath = public_path('assets/admin/css/p360-pagination.css');
  $p360PagerCssVer  = @filemtime($p360PagerCssPath) ?: time();
@endphp
<link rel="stylesheet" href="{{ asset('assets/admin/css/p360-pagination.css') }}?v={{ $p360PagerCssVer }}">

<style>
  .be-wrap{
    width:100%;
    max-width:100%;
    padding:10px 12px 18px;
  }

  .be-card{
    width:100%;
    max-width:none;
    background:linear-gradient(180deg,#ffffff 0%,#fcfdff 100%);
    border:1px solid #e6ebf2;
    border-radius:24px;
    overflow:hidden;
    box-shadow:0 18px 44px rgba(15,23,42,.08);
  }

  .be-head{
    padding:22px 22px 18px;
    border-bottom:1px solid #eef2f7;
    background:
      radial-gradient(circle at top right, rgba(79,70,229,.08), transparent 24%),
      linear-gradient(180deg,#f8fbff 0%,#ffffff 100%);
  }

  .be-head-top{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:16px;
    flex-wrap:wrap;
  }

  .be-title{
    font-size:28px;
    font-weight:950;
    color:#0f172a;
    letter-spacing:-.03em;
    line-height:1.05;
  }

  .be-sub{
    margin-top:8px;
    max-width:920px;
    font-size:13px;
    color:#64748b;
    font-weight:700;
    line-height:1.7;
  }

  .be-head-actions{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    align-items:center;
    justify-content:flex-end;
  }

  .be-chip{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:8px 12px;
    border-radius:999px;
    background:#eff6ff;
    border:1px solid #bfdbfe;
    font-size:12px;
    font-weight:900;
    color:#1d4ed8;
  }

  .be-chip code{
    font-family:ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size:11px;
    background:rgba(255,255,255,.65);
    padding:2px 8px;
    border-radius:999px;
  }

  .be-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:38px;
    padding:8px 12px;
    border-radius:12px;
    border:1px solid #dbe2ea;
    background:#fff;
    color:#0f172a;
    text-decoration:none;
    font-weight:900;
    font-size:12px;
    line-height:1.2;
    cursor:pointer;
    white-space:nowrap;
    transition:.18s ease;
  }

  .be-btn:hover{
    background:#f8fafc;
    border-color:#cfd8e3;
    transform:translateY(-1px);
  }

  .be-btn-primary{
    border-color:#4f46e5;
    background:#4f46e5;
    color:#fff;
    box-shadow:0 10px 22px rgba(79,70,229,.18);
  }

  .be-btn-primary:hover{
    background:#4338ca;
    border-color:#4338ca;
  }

  .be-btn-soft{
    border-color:#c7d2fe;
    background:#eef2ff;
    color:#3730a3;
  }

  .be-btn-soft:hover{
    background:#e4e8ff;
    border-color:#bcc6ff;
  }

  .be-toolbar{
    padding:16px 18px;
    border-bottom:1px solid #eef2f7;
    background:#fcfdff;
  }

  .be-filters{
    display:grid;
    grid-template-columns:minmax(240px,2fr) repeat(2,minmax(150px,1fr)) minmax(130px,1fr) minmax(130px,1fr) minmax(100px,.8fr) auto auto;
    gap:10px;
    align-items:end;
  }

  .be-field label{
    display:block;
    margin-bottom:6px;
    font-size:11px;
    font-weight:900;
    color:#64748b;
    letter-spacing:.03em;
  }

  .be-input,
  .be-select{
    width:100%;
    height:42px;
    border-radius:14px;
    border:1px solid #dbe2ea;
    padding:0 13px;
    background:#fff;
    outline:none;
    box-shadow:0 1px 0 rgba(15,23,42,.02) inset;
  }

  .be-input:focus,
  .be-select:focus{
    border-color:#a5b4fc;
    box-shadow:0 0 0 4px rgba(79,70,229,.10);
  }

  .be-kpis{
    padding:18px;
    display:grid;
    grid-template-columns:repeat(7,minmax(0,1fr));
    gap:12px;
    background:#fff;
    border-bottom:1px solid #eef2f7;
  }

  .be-kpi{
    border:1px solid #e7ecf3;
    border-radius:18px;
    padding:14px 14px 12px;
    background:linear-gradient(180deg,#fbfdff 0%,#ffffff 100%);
    box-shadow:0 6px 16px rgba(15,23,42,.04);
  }

  .be-kpi-k{
    font-size:11px;
    color:#64748b;
    font-weight:900;
    text-transform:uppercase;
    letter-spacing:.05em;
  }

  .be-kpi-v{
    margin-top:8px;
    font-size:26px;
    font-weight:950;
    color:#0f172a;
    letter-spacing:-.04em;
    line-height:1;
  }

  .be-kpi-foot{
    margin-top:8px;
    font-size:11px;
    color:#64748b;
    font-weight:800;
    line-height:1.5;
  }

  .be-statusbar{
    padding:0 18px 18px;
    display:flex;
    gap:12px;
    flex-wrap:wrap;
    font-size:12px;
    color:#64748b;
    font-weight:800;
    background:#fff;
    border-bottom:1px solid #eef2f7;
  }

  .be-statusbar span b{
    color:#0f172a;
  }

  .be-table-wrap{
    overflow:auto;
    background:#fff;
  }

  .be-table{
    width:100%;
    min-width:1280px;
    border-collapse:separate;
    border-spacing:0;
  }

  .be-table thead th{
    position:sticky;
    top:0;
    z-index:2;
    background:#f8fafc;
    padding:12px;
    border-bottom:1px solid #e5e7eb;
    text-align:left;
    font-size:12px;
    color:#64748b;
    font-weight:900;
    white-space:nowrap;
  }

  .be-table td{
    padding:16px 12px;
    border-bottom:1px solid #eef2f7;
    vertical-align:top;
    background:#fff;
  }

  .be-table tbody tr:hover td{
    background:#fafcff;
  }

  .be-id{
    font-weight:950;
    color:#0f172a;
  }

  .be-mono{
    font-family:ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size:12px;
    color:#64748b;
    word-break:break-word;
  }

  .be-main{
    font-weight:900;
    color:#0f172a;
    line-height:1.5;
  }

  .be-subline{
    margin-top:5px;
    font-size:12px;
    color:#64748b;
    line-height:1.55;
  }

  .be-email-list{
    margin-top:6px;
    font-size:12px;
    color:#64748b;
    line-height:1.65;
    max-width:340px;
    word-break:break-word;
  }

  .be-pill{
    display:inline-flex;
    align-items:center;
    padding:6px 10px;
    border-radius:999px;
    font-size:11px;
    font-weight:900;
    text-transform:uppercase;
    border:1px solid transparent;
  }

  .be-meta-chip{
    margin-top:8px;
    display:inline-flex;
    align-items:center;
    padding:4px 8px;
    border-radius:999px;
    background:#eff6ff;
    border:1px solid #bfdbfe;
    font-size:11px;
    font-weight:900;
    color:#1d4ed8;
  }

  .be-actions{
    display:flex;
    justify-content:flex-end;
    gap:8px;
    flex-wrap:wrap;
  }

  .be-pager{
    padding:16px 18px;
    border-top:1px solid #eef2f7;
    background:#fff;
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    flex-wrap:wrap;
  }

  .be-pager-meta{
    color:#64748b;
    font-size:12px;
    font-weight:800;
  }

  .be-pager-links{
    display:flex;
    align-items:center;
    justify-content:flex-end;
    gap:8px;
    flex-wrap:wrap;
  }

  .be-empty{
    padding:28px 18px;
    text-align:center;
    color:#64748b;
    font-weight:900;
    background:#fff;
  }

   @media (max-width: 1400px){
    .be-filters{
      grid-template-columns:repeat(4,minmax(0,1fr));
    }

    .be-kpis{
      grid-template-columns:repeat(4,minmax(0,1fr));
    }

    .be-table{
      min-width:1180px;
    }
  }

  @media (max-width: 1100px){
    .be-wrap{
      padding:8px 8px 16px;
    }

    .be-filters{
      grid-template-columns:repeat(3,minmax(0,1fr));
    }

    .be-kpis{
      grid-template-columns:repeat(3,minmax(0,1fr));
    }

    .be-table{
      min-width:1040px;
    }
  }

  @media (max-width: 820px){
    .be-wrap{
      padding:8px 6px 14px;
    }

    .be-head{
      padding:18px 16px 16px;
      border-radius:18px 18px 0 0;
    }

    .be-toolbar,
    .be-kpis,
    .be-statusbar{
      padding-left:16px;
      padding-right:16px;
    }

    .be-head-top{
      flex-direction:column;
      align-items:stretch;
    }

    .be-head-actions{
      width:100%;
      justify-content:flex-start;
    }

    .be-filters{
      grid-template-columns:1fr 1fr;
    }

    .be-kpis{
      grid-template-columns:1fr 1fr;
    }

    .be-table{
      min-width:920px;
    }

    .be-actions{
      justify-content:flex-start;
    }

    .be-pager{
      flex-direction:column;
      align-items:flex-start;
    }
  }

  @media (max-width: 560px){
    .be-wrap{
      padding:6px 4px 12px;
    }

    .be-card{
      border-radius:18px;
    }

    .be-title{
      font-size:24px;
    }

    .be-sub{
      font-size:12px;
    }

    .be-filters{
      grid-template-columns:1fr;
    }

    .be-kpis{
      grid-template-columns:1fr;
    }

    .be-table{
      min-width:860px;
    }

    .be-btn,
    .be-btn-primary,
    .be-btn-soft{
      width:100%;
    }
  }
</style>
@endpush

@section('content')
@php
  $rows = $rows ?? collect();
  $kpis = $kpis ?? [];
  $bccMonitor = $bccMonitor ?? 'notificaciones@pactopia.com';

  $fmtDate = function ($v) {
      if (!$v) return '—';
      try { return \Illuminate\Support\Carbon::parse($v)->format('Y-m-d H:i'); }
      catch (\Throwable $e) { return (string) $v; }
  };

  $routeIndex = route('admin.billing.statement_emails.index');
@endphp

<div class="be-wrap">
  <div class="be-card">

    {{-- HEADER --}}
    <div class="be-head">
      <div class="be-head-top">
        <div>
          <div class="be-title">Email Estados</div>
          <div class="be-sub">
            Revisa envíos reales de estados de cuenta, aperturas, clics, errores, historial y reenvíos manuales. Esta vista está enfocada en seguimiento operativo y control de entregabilidad.
          </div>
        </div>

        <div class="be-head-actions">
          <span class="be-chip">
            BCC fijo
            <code>{{ $bccMonitor }}</code>
          </span>

          <a href="{{ route('admin.billing.statements.index') }}" class="be-btn">
            Volver a Estados de cuenta
          </a>
        </div>
      </div>
    </div>

        {{-- FILTROS --}}
    <div class="be-toolbar">
      <form method="GET" action="{{ $routeIndex }}" class="be-filters">
        <div class="be-field">
          <label>Buscar</label>
          <input
            class="be-input"
            name="q"
            value="{{ $q ?? '' }}"
            placeholder="Asunto, email, to_list, account_id, email_id..."
          >
        </div>

        <div class="be-field">
          <label>Fecha desde</label>
          <input
            class="be-input"
            type="date"
            name="date_from"
            value="{{ $dateFrom ?? '' }}"
          >
        </div>

        <div class="be-field">
          <label>Fecha hasta</label>
          <input
            class="be-input"
            type="date"
            name="date_to"
            value="{{ $dateTo ?? '' }}"
          >
        </div>

        <div class="be-field">
          <label>Cuenta</label>
          <input
            class="be-input"
            name="accountId"
            value="{{ $accountId ?? '' }}"
            placeholder="ID exacto"
          >
        </div>

        <div class="be-field">
          <label>Estatus</label>
          <select class="be-select" name="status">
            @foreach([
              'all' => 'Todos',
              'queued' => 'Queued',
              'sent' => 'Sent',
              'failed' => 'Failed',
              'opened' => 'Opened',
              'clicked' => 'Clicked',
            ] as $k => $lbl)
              <option value="{{ $k }}" {{ ($status ?? 'all') === $k ? 'selected' : '' }}>{{ $lbl }}</option>
            @endforeach
          </select>
        </div>

        <div class="be-field">
          <label>Por página</label>
          <select class="be-select" name="perPage">
            @foreach([10,25,50,100,200] as $n)
              <option value="{{ $n }}" {{ (int)($perPage ?? 25) === $n ? 'selected' : '' }}>{{ $n }}</option>
            @endforeach
          </select>
        </div>

        <div style="display:flex;align-items:flex-end;">
          <button type="submit" class="be-btn be-btn-primary">Filtrar</button>
        </div>

        <div style="display:flex;align-items:flex-end;">
          <a href="{{ $routeIndex }}" class="be-btn">Limpiar</a>
        </div>
      </form>
    </div>

        {{-- KPIS --}}
    <div class="be-kpis">
      @php
        $cards = [
          ['label' => 'Total logs', 'value' => $kpis['total'] ?? 0, 'foot' => 'Registros visibles bajo el filtro actual'],
          ['label' => 'Enviados', 'value' => $kpis['sent'] ?? 0, 'foot' => 'Correos con envío exitoso'],
          ['label' => 'En cola', 'value' => $kpis['queued'] ?? 0, 'foot' => 'Pendientes o recién encolados'],
          ['label' => 'Fallidos', 'value' => $kpis['failed'] ?? 0, 'foot' => 'Errores detectados en entrega'],
          ['label' => 'Abiertos', 'value' => $kpis['opened'] ?? 0, 'foot' => 'Logs con al menos una apertura'],
          ['label' => 'Clics', 'value' => $kpis['clicked'] ?? 0, 'foot' => 'Logs con interacción de clic'],
          ['label' => '1° día mes actual', 'value' => $kpis['first_day_sent'] ?? 0, 'foot' => 'Envíos del primer día del mes en curso'],
        ];
      @endphp

      @foreach($cards as $card)
        <div class="be-kpi">
          <div class="be-kpi-k">{{ $card['label'] }}</div>
          <div class="be-kpi-v">{{ $card['value'] }}</div>
          <div class="be-kpi-foot">{{ $card['foot'] }}</div>
        </div>
      @endforeach
    </div>

    <div class="be-statusbar">
      <span>Mes actual: <b>{{ $kpis['current_month'] ?? now()->format('Y-m') }}</b></span>
      <span>Enviados mes actual: <b>{{ $kpis['current_month_sent'] ?? 0 }}</b></span>
      <span>Última actividad: <b>{{ $fmtDate($kpis['last_activity_at'] ?? null) }}</b></span>
    </div>

    {{-- TABLA --}}
      <div class="be-table-wrap">
        <table class="be-table">
        <thead>
          <tr style="background:#f8fafc;">
            <th style="padding:12px;border-bottom:1px solid #e5e7eb;text-align:left;font-size:12px;color:#64748b;">ID</th>
            <th style="padding:12px;border-bottom:1px solid #e5e7eb;text-align:left;font-size:12px;color:#64748b;">Periodo / Cuenta</th>
            <th style="padding:12px;border-bottom:1px solid #e5e7eb;text-align:left;font-size:12px;color:#64748b;">Destinatarios</th>
            <th style="padding:12px;border-bottom:1px solid #e5e7eb;text-align:left;font-size:12px;color:#64748b;">Asunto</th>
            <th style="padding:12px;border-bottom:1px solid #e5e7eb;text-align:left;font-size:12px;color:#64748b;">Estatus</th>
            <th style="padding:12px;border-bottom:1px solid #e5e7eb;text-align:left;font-size:12px;color:#64748b;">Tracking</th>
            <th style="padding:12px;border-bottom:1px solid #e5e7eb;text-align:left;font-size:12px;color:#64748b;">Fechas</th>
            <th style="padding:12px;border-bottom:1px solid #e5e7eb;text-align:right;font-size:12px;color:#64748b;">Acciones</th>
          </tr>
        </thead>
        <tbody>
          @forelse($rows as $r)
            @php
              $statusColor = match (strtolower((string)($r->status ?? ''))) {
                'sent' => '#16a34a',
                'queued' => '#d97706',
                'failed' => '#dc2626',
                default => '#475569',
              };

              $recipientValue = trim((string)($r->ui_to ?? $r->email ?? ''));
              $toListValue = trim((string)($r->to_list ?? ''));
              $uiError = trim((string)($r->ui_error ?? ''));
              $defaultRecipients = $toListValue !== '' ? $toListValue : $recipientValue;
            @endphp

            <tr style="background:#fff;">
              <td style="padding:14px 12px;border-bottom:1px solid #eef2f7;vertical-align:top;">
                <div class="be-id">#{{ $r->id }}</div>
                  <div class="be-mono" style="margin-top:4px;">
                    {{ $r->email_id ?: '—' }}
                  </div>
              </td>

              <td style="padding:14px 12px;border-bottom:1px solid #eef2f7;vertical-align:top;">
                <div class="be-main">{{ $r->period ?: '—' }}</div>
                <div class="be-subline">Cuenta: <b>{{ $r->account_id ?: '—' }}</b></div>
                <div class="be-subline">Statement ID: <b>{{ $r->statement_id ?: '—' }}</b></div>
              </td>

              <td style="padding:14px 12px;border-bottom:1px solid #eef2f7;vertical-align:top;">
                <div class="be-main">{{ $recipientValue !== '' ? $recipientValue : '—' }}</div>

                @if($toListValue !== '')
                  <div class="be-email-list">
                    Lista: {{ $toListValue }}
                  </div>
                @endif

                <div class="be-meta-chip">
                  BCC: {{ $bccMonitor }}
                </div>
              </td>

              <td style="padding:14px 12px;border-bottom:1px solid #eef2f7;vertical-align:top;">
                <div class="be-main">{{ $r->subject ?: '—' }}</div>
                  <div class="be-subline">
                    Template: <b>{{ $r->template ?: '—' }}</b>
                  </div>

                @if(!empty($r->provider))
                  <div class="be-subline">
                    Provider: <b>{{ $r->provider }}</b>
                  </div>
                @endif
              </td>

              <td style="padding:14px 12px;border-bottom:1px solid #eef2f7;vertical-align:top;">
                <span class="be-pill" style="background:{{ $statusColor }}15;border-color:{{ $statusColor }}40;color:{{ $statusColor }};">
                  {{ $r->status ?: '—' }}
                </span>

                @if($uiError !== '')
                  <div style="margin-top:8px;font-size:12px;color:#b91c1c;line-height:1.55;max-width:260px;word-break:break-word;">
                    {{ $uiError }}
                  </div>
                @endif
              </td>

              <td style="padding:14px 12px;border-bottom:1px solid #eef2f7;vertical-align:top;">
                <div style="font-size:12px;color:#0f172a;font-weight:900;">Opens: {{ (int)($r->open_count ?? 0) }}</div>
                <div style="margin-top:4px;font-size:12px;color:#0f172a;font-weight:900;">Clicks: {{ (int)($r->click_count ?? 0) }}</div>
                <div style="margin-top:6px;font-size:12px;color:#64748b;">Primera apertura: {{ $fmtDate($r->first_open_any ?? null) }}</div>
                <div style="margin-top:4px;font-size:12px;color:#64748b;">Última apertura: {{ $fmtDate($r->last_open_any ?? null) }}</div>
              </td>

              <td style="padding:14px 12px;border-bottom:1px solid #eef2f7;vertical-align:top;">
                <div style="font-size:12px;color:#64748b;">Queued: <b>{{ $fmtDate($r->queued_at ?? null) }}</b></div>
                <div style="margin-top:4px;font-size:12px;color:#64748b;">Sent: <b>{{ $fmtDate($r->sent_at ?? null) }}</b></div>
                <div style="margin-top:4px;font-size:12px;color:#64748b;">Failed: <b>{{ $fmtDate($r->failed_at ?? null) }}</b></div>
                <div style="margin-top:4px;font-size:12px;color:#64748b;">Actualizado: <b>{{ $fmtDate($r->updated_at ?? null) }}</b></div>
              </td>

              <td style="padding:14px 12px;border-bottom:1px solid #eef2f7;vertical-align:top;text-align:right;">
                <div class="be-actions">
                  <a href="{{ route('admin.billing.statement_emails.show', $r->id) }}" class="be-btn">
                    Ver detalle
                  </a>

                  <a href="{{ route('admin.billing.statement_emails.preview', $r->id) }}"
                    target="_blank"
                    class="be-btn">
                    Ver correo
                  </a>

                  <button
                    type="button"
                    onclick="openResendModal('{{ $r->id }}', @js($defaultRecipients), @js($r->subject ?? ''))"
                    class="be-btn be-btn-soft">
                    Reenviar
                  </button>
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="be-empty">
                No hay registros para el filtro actual.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

        @if(is_object($rows) && method_exists($rows, 'links'))
      <div class="be-pager">
        <div class="be-pager-meta">
          Mostrando
          <b>{{ (int)($rows->firstItem() ?? 0) }}</b>
          –
          <b>{{ (int)($rows->lastItem() ?? 0) }}</b>
          de
          <b>{{ (int)($rows->total() ?? 0) }}</b>
          registros
        </div>

        <div class="be-pager-links">
          {!! $rows->onEachSide(1)->appends(request()->query())->links('vendor.pagination.p360') !!}
        </div>
      </div>
    @endif
  </div>
</div>

{{-- MODAL REENVIAR --}}
<div id="resendEmailModal"
     style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(15,23,42,.56);padding:20px;align-items:center;justify-content:center;">
  <div style="width:100%;max-width:620px;background:#fff;border-radius:22px;box-shadow:0 18px 48px rgba(15,23,42,.25);overflow:hidden;">
    <div style="padding:18px 20px;border-bottom:1px solid #eef2f7;background:linear-gradient(180deg,#f8fbff 0%,#ffffff 100%);display:flex;justify-content:space-between;gap:12px;align-items:flex-start;">
      <div>
        <div style="font-size:22px;font-weight:900;color:#0f172a;">Reenviar correo</div>
        <div id="resendEmailModalSubject"
             style="margin-top:6px;font-size:13px;color:#64748b;font-weight:700;line-height:1.6;">
          Captura uno o varios correos separados por coma.
        </div>
      </div>

      <button type="button"
              onclick="closeResendModal()"
              style="width:36px;height:36px;border-radius:999px;border:1px solid #dbe2ea;background:#fff;color:#334155;font-size:18px;font-weight:900;cursor:pointer;">
        ×
      </button>
    </div>

    <form id="resendEmailForm" method="POST" action="" style="padding:20px;">
      @csrf

      <div style="display:grid;gap:14px;">
        <div style="padding:12px 14px;border-radius:14px;background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;font-size:12px;font-weight:800;line-height:1.65;">
          Puedes enviar a un correo o a varios correos.<br>
          Ejemplo: cliente@correo.com, cobranza@empresa.com, direccion@empresa.com
        </div>

        <div>
          <label style="display:block;margin-bottom:7px;font-size:12px;font-weight:900;color:#475569;letter-spacing:.03em;">
            Destinatarios
          </label>
          <textarea
            id="resendEmailRecipients"
            name="recipients"
            rows="5"
            placeholder="correo1@empresa.com, correo2@empresa.com"
            style="width:100%;border-radius:16px;border:1px solid #dbe2ea;padding:14px;background:#fff;resize:vertical;outline:none;font-size:14px;line-height:1.6;min-height:130px;"
          ></textarea>
        </div>
      </div>

      <div style="margin-top:18px;display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;">
        <button type="button"
                onclick="closeResendModal()"
                style="height:42px;padding:0 16px;border-radius:13px;border:1px solid #dbe2ea;background:#fff;color:#0f172a;font-weight:900;cursor:pointer;">
          Cancelar
        </button>

        <button type="submit"
                style="height:42px;padding:0 18px;border-radius:13px;border:1px solid #4f46e5;background:#4f46e5;color:#fff;font-weight:900;cursor:pointer;box-shadow:0 8px 18px rgba(79,70,229,.18);">
          Enviar reenvío
        </button>
      </div>
    </form>
  </div>
</div>

<script>
  function openResendModal(id, recipients, subject) {
    var modal = document.getElementById('resendEmailModal');
    var form = document.getElementById('resendEmailForm');
    var input = document.getElementById('resendEmailRecipients');
    var subjectBox = document.getElementById('resendEmailModalSubject');

    form.action = "{{ url('/admin/billing/statement-emails') }}/" + id + "/resend";
    input.value = recipients || '';
    subjectBox.textContent = subject
      ? ('Asunto: ' + subject)
      : 'Captura uno o varios correos separados por coma.';

    modal.style.display = 'flex';
    setTimeout(function () {
      input.focus();
      input.setSelectionRange(input.value.length, input.value.length);
    }, 20);
  }

  function closeResendModal() {
    var modal = document.getElementById('resendEmailModal');
    modal.style.display = 'none';
  }

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      closeResendModal();
    }
  });

  document.getElementById('resendEmailModal').addEventListener('click', function (e) {
    if (e.target === this) {
      closeResendModal();
    }
  });
</script>
@endsection