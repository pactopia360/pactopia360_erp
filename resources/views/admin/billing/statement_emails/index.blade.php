@extends('layouts.admin')

@section('title', 'Billing · Email Estados')
@section('layout', 'full')
@section('contentLayout', 'full')

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

<div style="padding:12px;">
  <div style="background:#fff;border:1px solid #e5e7eb;border-radius:22px;overflow:hidden;box-shadow:0 14px 38px rgba(15,23,42,.08);">

    {{-- HEADER --}}
    <div style="padding:20px 20px 18px;border-bottom:1px solid #eef2f7;background:linear-gradient(180deg,#f8fbff 0%,#ffffff 100%);">
      <div style="display:flex;justify-content:space-between;gap:14px;flex-wrap:wrap;align-items:flex-start;">
        <div>
          <div style="font-size:24px;font-weight:900;color:#0f172a;letter-spacing:-.02em;">Email Estados</div>
          <div style="margin-top:7px;font-size:13px;color:#64748b;font-weight:700;max-width:860px;line-height:1.6;">
            Revisa envíos reales de estados de cuenta, aperturas, clics, errores, historial y reenvíos manuales a uno o varios correos.
          </div>

          <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
            <span style="display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:999px;background:#eff6ff;border:1px solid #bfdbfe;font-size:12px;font-weight:900;color:#1d4ed8;">
              BCC fijo
              <span style="font-family:ui-monospace, SFMono-Regular, Menlo, monospace;">{{ $bccMonitor }}</span>
            </span>

            <a href="{{ route('admin.billing.statements.index') }}"
               style="display:inline-flex;align-items:center;justify-content:center;padding:8px 12px;border-radius:12px;border:1px solid #dbe2ea;background:#fff;color:#0f172a;text-decoration:none;font-weight:900;font-size:12px;">
              Volver a Estados de cuenta
            </a>
          </div>
        </div>
      </div>
    </div>

    {{-- FILTROS --}}
    <div style="padding:16px 16px 14px;border-bottom:1px solid #eef2f7;background:#fcfdff;">
      <form method="GET" action="{{ $routeIndex }}"
            style="display:grid;grid-template-columns:minmax(220px,2fr) repeat(2,minmax(150px,1fr)) minmax(130px,1fr) minmax(130px,1fr) minmax(100px,.8fr) auto auto;gap:10px;align-items:end;">

        <div>
          <label style="display:block;margin-bottom:6px;font-size:11px;font-weight:900;color:#64748b;letter-spacing:.03em;">Buscar</label>
          <input
            name="q"
            value="{{ $q ?? '' }}"
            placeholder="Asunto, email, to_list, account_id, email_id..."
            style="width:100%;height:42px;border-radius:13px;border:1px solid #dbe2ea;padding:0 13px;background:#fff;outline:none;"
          >
        </div>

        <div>
          <label style="display:block;margin-bottom:6px;font-size:11px;font-weight:900;color:#64748b;letter-spacing:.03em;">Fecha desde</label>
          <input
            type="date"
            name="date_from"
            value="{{ $dateFrom ?? '' }}"
            style="width:100%;height:42px;border-radius:13px;border:1px solid #dbe2ea;padding:0 13px;background:#fff;outline:none;"
          >
        </div>

        <div>
          <label style="display:block;margin-bottom:6px;font-size:11px;font-weight:900;color:#64748b;letter-spacing:.03em;">Fecha hasta</label>
          <input
            type="date"
            name="date_to"
            value="{{ $dateTo ?? '' }}"
            style="width:100%;height:42px;border-radius:13px;border:1px solid #dbe2ea;padding:0 13px;background:#fff;outline:none;"
          >
        </div>

        <div>
          <label style="display:block;margin-bottom:6px;font-size:11px;font-weight:900;color:#64748b;letter-spacing:.03em;">Cuenta</label>
          <input
            name="accountId"
            value="{{ $accountId ?? '' }}"
            placeholder="ID exacto"
            style="width:100%;height:42px;border-radius:13px;border:1px solid #dbe2ea;padding:0 13px;background:#fff;outline:none;"
          >
        </div>

        <div>
          <label style="display:block;margin-bottom:6px;font-size:11px;font-weight:900;color:#64748b;letter-spacing:.03em;">Estatus</label>
          <select
            name="status"
            style="width:100%;height:42px;border-radius:13px;border:1px solid #dbe2ea;padding:0 13px;background:#fff;outline:none;"
          >
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

        <div>
          <label style="display:block;margin-bottom:6px;font-size:11px;font-weight:900;color:#64748b;letter-spacing:.03em;">Por página</label>
          <select
            name="perPage"
            style="width:100%;height:42px;border-radius:13px;border:1px solid #dbe2ea;padding:0 13px;background:#fff;outline:none;"
          >
            @foreach([10,25,50,100,200] as $n)
              <option value="{{ $n }}" {{ (int)($perPage ?? 25) === $n ? 'selected' : '' }}>{{ $n }}</option>
            @endforeach
          </select>
        </div>

        <div style="display:flex;align-items:flex-end;">
          <button type="submit"
                  style="height:42px;padding:0 16px;border:1px solid #4f46e5;background:#4f46e5;color:#fff;border-radius:13px;font-weight:900;cursor:pointer;box-shadow:0 8px 18px rgba(79,70,229,.18);">
            Filtrar
          </button>
        </div>

        <div style="display:flex;align-items:flex-end;">
          <a href="{{ $routeIndex }}"
             style="height:42px;padding:0 16px;display:inline-flex;align-items:center;justify-content:center;border:1px solid #dbe2ea;background:#fff;color:#0f172a;border-radius:13px;font-weight:900;text-decoration:none;">
            Limpiar
          </a>
        </div>
      </form>
    </div>

    {{-- KPIS --}}
    <div style="padding:16px;background:#fff;border-bottom:1px solid #eef2f7;">
      <div style="display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:10px;">
        @php
          $cards = [
            ['label' => 'Total logs', 'value' => $kpis['total'] ?? 0],
            ['label' => 'Enviados', 'value' => $kpis['sent'] ?? 0],
            ['label' => 'En cola', 'value' => $kpis['queued'] ?? 0],
            ['label' => 'Fallidos', 'value' => $kpis['failed'] ?? 0],
            ['label' => 'Abiertos', 'value' => $kpis['opened'] ?? 0],
            ['label' => 'Clics', 'value' => $kpis['clicked'] ?? 0],
            ['label' => '1° día mes actual', 'value' => $kpis['first_day_sent'] ?? 0],
          ];
        @endphp

        @foreach($cards as $card)
          <div style="border:1px solid #e5e7eb;border-radius:16px;padding:13px;background:linear-gradient(180deg,#fafcff 0%,#ffffff 100%);">
            <div style="font-size:11px;color:#64748b;font-weight:900;text-transform:uppercase;letter-spacing:.05em;">{{ $card['label'] }}</div>
            <div style="margin-top:7px;font-size:24px;font-weight:950;color:#0f172a;letter-spacing:-.03em;">{{ $card['value'] }}</div>
          </div>
        @endforeach
      </div>

      <div style="margin-top:12px;display:flex;gap:12px;flex-wrap:wrap;font-size:12px;color:#64748b;font-weight:800;">
        <span>Mes actual: <b>{{ $kpis['current_month'] ?? now()->format('Y-m') }}</b></span>
        <span>Enviados mes actual: <b>{{ $kpis['current_month_sent'] ?? 0 }}</b></span>
        <span>Última actividad: <b>{{ $fmtDate($kpis['last_activity_at'] ?? null) }}</b></span>
      </div>
    </div>

    @if(session('ok'))
      <div style="margin:16px 16px 0;padding:12px 14px;border-radius:14px;background:#ecfdf5;border:1px solid #bbf7d0;color:#166534;font-weight:800;">
        {{ session('ok') }}
      </div>
    @endif

    @if($errors->any())
      <div style="margin:16px 16px 0;padding:12px 14px;border-radius:14px;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;font-weight:800;line-height:1.6;">
        @foreach($errors->all() as $err)
          <div>{{ $err }}</div>
        @endforeach
      </div>
    @endif

    @if(!empty($error))
      <div style="margin:16px;padding:12px 14px;border-radius:14px;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;font-weight:800;">
        {{ $error }}
      </div>
    @endif

    {{-- TABLA --}}
    <div style="overflow:auto;">
      <table style="width:100%;min-width:1500px;border-collapse:separate;border-spacing:0;">
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
                <div style="font-weight:900;color:#0f172a;">#{{ $r->id }}</div>
                <div style="margin-top:4px;font-size:12px;color:#64748b;font-family:ui-monospace, SFMono-Regular, Menlo, monospace;">
                  {{ $r->email_id ?: '—' }}
                </div>
              </td>

              <td style="padding:14px 12px;border-bottom:1px solid #eef2f7;vertical-align:top;">
                <div style="font-weight:900;color:#0f172a;">{{ $r->period ?: '—' }}</div>
                <div style="margin-top:4px;font-size:12px;color:#64748b;">Cuenta: <b>{{ $r->account_id ?: '—' }}</b></div>
                <div style="margin-top:4px;font-size:12px;color:#64748b;">Statement ID: <b>{{ $r->statement_id ?: '—' }}</b></div>
              </td>

              <td style="padding:14px 12px;border-bottom:1px solid #eef2f7;vertical-align:top;">
                <div style="font-weight:800;color:#0f172a;">{{ $recipientValue !== '' ? $recipientValue : '—' }}</div>

                @if($toListValue !== '')
                  <div style="margin-top:6px;font-size:12px;color:#64748b;line-height:1.6;max-width:320px;word-break:break-word;">
                    Lista: {{ $toListValue }}
                  </div>
                @endif

                <div style="margin-top:8px;display:inline-flex;align-items:center;padding:4px 8px;border-radius:999px;background:#eff6ff;border:1px solid #bfdbfe;font-size:11px;font-weight:900;color:#1d4ed8;">
                  BCC: {{ $bccMonitor }}
                </div>
              </td>

              <td style="padding:14px 12px;border-bottom:1px solid #eef2f7;vertical-align:top;">
                <div style="font-weight:900;color:#0f172a;line-height:1.45;">{{ $r->subject ?: '—' }}</div>

                <div style="margin-top:6px;font-size:12px;color:#64748b;">
                  Template: <b>{{ $r->template ?: '—' }}</b>
                </div>

                @if(!empty($r->provider))
                  <div style="margin-top:4px;font-size:12px;color:#64748b;">
                    Provider: <b>{{ $r->provider }}</b>
                  </div>
                @endif
              </td>

              <td style="padding:14px 12px;border-bottom:1px solid #eef2f7;vertical-align:top;">
                <span style="display:inline-flex;align-items:center;padding:6px 10px;border-radius:999px;background:{{ $statusColor }}15;border:1px solid {{ $statusColor }}40;color:{{ $statusColor }};font-size:11px;font-weight:900;text-transform:uppercase;">
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
                <div style="display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap;">
                  <a href="{{ route('admin.billing.statement_emails.show', $r->id) }}"
                     style="display:inline-flex;align-items:center;justify-content:center;padding:8px 12px;border-radius:10px;border:1px solid #dbe2ea;background:#fff;color:#0f172a;text-decoration:none;font-weight:900;font-size:12px;">
                    Ver detalle
                  </a>

                  <a href="{{ route('admin.billing.statement_emails.preview', $r->id) }}"
                     target="_blank"
                     style="display:inline-flex;align-items:center;justify-content:center;padding:8px 12px;border-radius:10px;border:1px solid #dbe2ea;background:#fff;color:#0f172a;text-decoration:none;font-weight:900;font-size:12px;">
                    Ver correo
                  </a>

                  <button
                    type="button"
                    onclick="openResendModal('{{ $r->id }}', @js($defaultRecipients), @js($r->subject ?? ''))"
                    style="display:inline-flex;align-items:center;justify-content:center;padding:8px 12px;border-radius:10px;border:1px solid #c7d2fe;background:#eef2ff;color:#3730a3;font-weight:900;font-size:12px;cursor:pointer;">
                    Reenviar
                  </button>
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" style="padding:22px;text-align:center;color:#64748b;font-weight:900;">
                No hay registros para el filtro actual.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if(is_object($rows) && method_exists($rows, 'links'))
      <div style="padding:16px;border-top:1px solid #eef2f7;">
        {{ $rows->links() }}
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