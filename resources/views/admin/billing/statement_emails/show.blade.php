@extends('layouts.admin')

@section('title', 'Billing · Email Estados · Detalle')
@section('layout', 'full')
@section('contentLayout', 'full')

@section('content')
@php
  $fmtDate = function ($v) {
      if (!$v) return '—';
      try { return \Illuminate\Support\Carbon::parse($v)->format('Y-m-d H:i:s'); }
      catch (\Throwable $e) { return (string) $v; }
  };

  $uiTo = trim((string)($row->ui_to ?? $row->email ?? ''));
  $uiError = trim((string)($row->ui_error ?? ''));
  $toListValue = trim((string)($row->to_list ?? ''));
  $defaultRecipients = $toListValue !== '' ? $toListValue : $uiTo;

  $statusColor = match (strtolower((string)($row->status ?? ''))) {
      'sent' => '#16a34a',
      'queued' => '#d97706',
      'failed' => '#dc2626',
      default => '#475569',
  };
@endphp

<div style="padding:12px;">
  <div style="background:#fff;border:1px solid #e5e7eb;border-radius:22px;overflow:hidden;box-shadow:0 14px 38px rgba(15,23,42,.08);">

    {{-- HEADER --}}
    <div style="padding:20px;border-bottom:1px solid #eef2f7;background:linear-gradient(180deg,#f8fbff 0%,#ffffff 100%);display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;">
      <div>
        <div style="font-size:24px;font-weight:900;color:#0f172a;letter-spacing:-.02em;">Detalle Email Estado</div>
        <div style="margin-top:6px;font-size:13px;color:#64748b;font-weight:700;line-height:1.6;">
          Log #{{ $row->id }} · Email ID {{ $row->email_id ?: '—' }}
        </div>
      </div>

      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="{{ route('admin.billing.statement_emails.index') }}"
           style="display:inline-flex;align-items:center;justify-content:center;padding:8px 12px;border-radius:10px;border:1px solid #dbe2ea;background:#fff;color:#0f172a;text-decoration:none;font-weight:900;font-size:12px;">
          Volver
        </a>

        <a href="{{ route('admin.billing.statement_emails.preview', $row->id) }}"
           target="_blank"
           style="display:inline-flex;align-items:center;justify-content:center;padding:8px 12px;border-radius:10px;border:1px solid #dbe2ea;background:#fff;color:#0f172a;text-decoration:none;font-weight:900;font-size:12px;">
          Ver correo
        </a>

        <button type="button"
                onclick="openResendModal('{{ $row->id }}', @js($defaultRecipients), @js($row->subject ?? ''))"
                style="display:inline-flex;align-items:center;justify-content:center;padding:8px 12px;border-radius:10px;border:1px solid #c7d2fe;background:#eef2ff;color:#3730a3;font-weight:900;font-size:12px;cursor:pointer;">
          Reenviar
        </button>
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

    <div style="padding:16px;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;">

      {{-- DATOS BASE --}}
      <div style="border:1px solid #e5e7eb;border-radius:16px;padding:15px;background:linear-gradient(180deg,#ffffff 0%,#fafcff 100%);">
        <div style="font-size:12px;color:#64748b;font-weight:900;text-transform:uppercase;letter-spacing:.05em;">Datos base</div>

        <div style="margin-top:12px;font-size:14px;line-height:1.8;color:#0f172a;">
          <div><b>Cuenta:</b> {{ $row->account_id ?: '—' }}</div>
          <div><b>Periodo:</b> {{ $row->period ?: '—' }}</div>
          <div><b>Destinatario principal:</b> {{ $uiTo !== '' ? $uiTo : '—' }}</div>
          <div><b>Lista de correos:</b> {{ $toListValue !== '' ? $toListValue : '—' }}</div>
          <div><b>BCC fijo:</b> {{ $bccMonitor }}</div>
          <div><b>Asunto:</b> {{ $row->subject ?: '—' }}</div>
          <div><b>Template:</b> {{ $row->template ?: '—' }}</div>
          <div>
            <b>Estatus:</b>
            <span style="display:inline-flex;align-items:center;padding:4px 9px;border-radius:999px;background:{{ $statusColor }}15;border:1px solid {{ $statusColor }}40;color:{{ $statusColor }};font-size:11px;font-weight:900;text-transform:uppercase;vertical-align:middle;">
              {{ $row->status ?: '—' }}
            </span>
          </div>
          <div><b>Provider:</b> {{ $row->provider ?: '—' }}</div>
          <div><b>Provider Message ID:</b> {{ $row->provider_message_id ?: '—' }}</div>

          @if($uiError !== '')
            <div style="margin-top:10px;padding:10px 12px;border-radius:12px;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;font-size:13px;line-height:1.55;">
              <b>Error:</b> {{ $uiError }}
            </div>
          @endif
        </div>
      </div>

      {{-- TRACKING / FECHAS --}}
      <div style="border:1px solid #e5e7eb;border-radius:16px;padding:15px;background:linear-gradient(180deg,#ffffff 0%,#fafcff 100%);">
        <div style="font-size:12px;color:#64748b;font-weight:900;text-transform:uppercase;letter-spacing:.05em;">Tracking / fechas</div>

        <div style="margin-top:12px;font-size:14px;line-height:1.8;color:#0f172a;">
          <div><b>Queued:</b> {{ $fmtDate($row->queued_at ?? null) }}</div>
          <div><b>Sent:</b> {{ $fmtDate($row->sent_at ?? null) }}</div>
          <div><b>Failed:</b> {{ $fmtDate($row->failed_at ?? null) }}</div>
          <div><b>First open:</b> {{ $fmtDate($row->first_open_at ?? $row->first_opened_at ?? null) }}</div>
          <div><b>Last open:</b> {{ $fmtDate($row->last_open_at ?? $row->last_opened_at ?? null) }}</div>
          <div><b>Open count:</b> {{ (int)($row->open_count ?? 0) }}</div>
          <div><b>First click:</b> {{ $fmtDate($row->first_click_at ?? $row->first_clicked_at ?? null) }}</div>
          <div><b>Last click:</b> {{ $fmtDate($row->last_click_at ?? $row->last_clicked_at ?? null) }}</div>
          <div><b>Click count:</b> {{ (int)($row->click_count ?? 0) }}</div>
          <div><b>Actualizado:</b> {{ $fmtDate($row->updated_at ?? null) }}</div>
          <div><b>Creado:</b> {{ $fmtDate($row->created_at ?? null) }}</div>
        </div>
      </div>

      {{-- PAYLOAD --}}
      <div style="border:1px solid #e5e7eb;border-radius:16px;padding:15px;background:#fff;">
        <div style="font-size:12px;color:#64748b;font-weight:900;text-transform:uppercase;letter-spacing:.05em;">Payload</div>
        <pre style="margin-top:12px;background:#0f172a;color:#e2e8f0;padding:14px;border-radius:14px;overflow:auto;font-size:12px;line-height:1.5;min-height:320px;">{{ json_encode($payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) }}</pre>
      </div>

      {{-- META --}}
      <div style="border:1px solid #e5e7eb;border-radius:16px;padding:15px;background:#fff;">
        <div style="font-size:12px;color:#64748b;font-weight:900;text-transform:uppercase;letter-spacing:.05em;">Meta</div>
        <pre style="margin-top:12px;background:#0f172a;color:#e2e8f0;padding:14px;border-radius:14px;overflow:auto;font-size:12px;line-height:1.5;min-height:320px;">{{ json_encode($meta, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) }}</pre>
      </div>
    </div>
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