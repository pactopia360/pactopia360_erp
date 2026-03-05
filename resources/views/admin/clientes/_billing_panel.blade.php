{{-- resources/views/admin/clientes/_billing_panel.blade.php --}}
@php
  $plan   = strtolower(trim((string)($acc->plan ?? '')));
  $cycle  = strtolower(trim((string)($acc->billing_cycle ?? '')));
  $status = strtolower(trim((string)($acc->billing_status ?? '')));
  $blocked = (int)($acc->is_blocked ?? 0) === 1;

  $niceCycle = $cycle !== '' ? $cycle : '—';
  $nicePlan  = $plan !== '' ? strtoupper($plan) : '—';
  $niceStatus= $status !== '' ? $status : '—';

  $next = $acc->next_invoice_date ?? null;
@endphp

<div class="p360-billing-panel card" style="border:1px solid rgba(148,163,184,.25); border-radius:14px; padding:14px; background:rgba(2,6,23,.35)">
  <div style="display:flex; gap:12px; align-items:flex-start; justify-content:space-between; flex-wrap:wrap">
    <div>
      <div style="font-weight:800; font-size:14px">Facturación · Cuenta Billing</div>
      <div style="color:#94a3b8; font-size:12px; margin-top:2px">
        Account ID: <b>{{ $accountId }}</b>
        @if($rfcReal) · RFC: <b>{{ $rfcReal }}</b>@endif
      </div>
    </div>

    <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap">
      <a class="btn btn-sm" href="{{ $urls['billing_accounts_show'] }}" style="text-decoration:none; border:1px solid rgba(148,163,184,.35); border-radius:10px; padding:8px 10px; background:#111827; color:#e5e7eb">
        Abrir Billing
      </a>

      @if(!empty($urls['billing_statements_index']))
        <a class="btn btn-sm" href="{{ $urls['billing_statements_index'] }}" style="text-decoration:none; border:1px solid rgba(148,163,184,.35); border-radius:10px; padding:8px 10px; background:#0b1228; color:#e5e7eb">
          Estados de cuenta
        </a>
      @endif
    </div>
  </div>

  <div style="display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; margin-top:12px">
    <div style="background:rgba(15,23,42,.6); border:1px solid rgba(148,163,184,.18); border-radius:12px; padding:10px">
      <div style="color:#94a3b8; font-size:11px">Plan</div>
      <div style="font-weight:700">{{ $nicePlan }}</div>
    </div>
    <div style="background:rgba(15,23,42,.6); border:1px solid rgba(148,163,184,.18); border-radius:12px; padding:10px">
      <div style="color:#94a3b8; font-size:11px">Ciclo</div>
      <div style="font-weight:700">{{ $niceCycle }}</div>
    </div>
    <div style="background:rgba(15,23,42,.6); border:1px solid rgba(148,163,184,.18); border-radius:12px; padding:10px">
      <div style="color:#94a3b8; font-size:11px">Status</div>
      <div style="font-weight:700; text-transform:capitalize">{{ $niceStatus }}</div>
    </div>
    <div style="background:rgba(15,23,42,.6); border:1px solid rgba(148,163,184,.18); border-radius:12px; padding:10px">
      <div style="color:#94a3b8; font-size:11px">Pagando (MXN)</div>
      <div style="font-weight:800">
        @if($licenseAmount === null)
          —
        @else
          ${{ number_format((float)$licenseAmount, 2) }}
        @endif
      </div>
    </div>
  </div>

  <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-top:12px">
    <span style="display:inline-flex; align-items:center; gap:8px; padding:6px 10px; border-radius:999px; border:1px solid rgba(148,163,184,.25); background:rgba(2,6,23,.2)">
      <span style="width:8px; height:8px; border-radius:999px; background:{{ $blocked ? '#ef4444' : '#22c55e' }}"></span>
      <span style="font-size:12px; color:#cbd5e1">{{ $blocked ? 'Bloqueado' : 'Activo' }}</span>
    </span>

    @if($next)
      <span style="font-size:12px; color:#94a3b8">Próxima factura: <b style="color:#e5e7eb">{{ $next }}</b></span>
    @endif
  </div>

  {{-- Acciones integradas (reusa endpoints existentes) --}}
  <div style="margin-top:12px; display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:10px">
    <form method="POST" action="{{ $urls['billing_accounts_license'] }}" style="background:rgba(15,23,42,.5); border:1px solid rgba(148,163,184,.18); border-radius:12px; padding:10px">
      @csrf
      <div style="font-weight:700; font-size:12px">Licencia</div>
      <div style="color:#94a3b8; font-size:11px; margin:4px 0 8px">Actualiza licencia/ciclo desde aquí.</div>
      <button type="submit" style="width:100%; border-radius:10px; padding:8px 10px; border:1px solid rgba(148,163,184,.35); background:#0b1228; color:#e5e7eb; font-weight:700">
        Guardar licencia
      </button>
    </form>

    <form method="POST" action="{{ $urls['billing_accounts_modules'] }}" style="background:rgba(15,23,42,.5); border:1px solid rgba(148,163,184,.18); border-radius:12px; padding:10px">
      @csrf
      <div style="font-weight:700; font-size:12px">Módulos</div>
      <div style="color:#94a3b8; font-size:11px; margin:4px 0 8px">Activa/desactiva módulos.</div>
      <button type="submit" style="width:100%; border-radius:10px; padding:8px 10px; border:1px solid rgba(148,163,184,.35); background:#0b1228; color:#e5e7eb; font-weight:700">
        Guardar módulos
      </button>
    </form>

    <form method="POST" action="{{ $urls['billing_accounts_override'] }}" style="background:rgba(15,23,42,.5); border:1px solid rgba(148,163,184,.18); border-radius:12px; padding:10px">
      @csrf
      <div style="font-weight:700; font-size:12px">Override</div>
      <div style="color:#94a3b8; font-size:11px; margin:4px 0 8px">Fija monto override (si aplica).</div>
      <button type="submit" style="width:100%; border-radius:10px; padding:8px 10px; border:1px solid rgba(148,163,184,.35); background:#111827; color:#e5e7eb; font-weight:700">
        Guardar override
      </button>
    </form>
  </div>

  {{-- Recipients quick view --}}
  <div style="margin-top:12px; background:rgba(2,6,23,.25); border:1px solid rgba(148,163,184,.18); border-radius:12px; padding:10px">
    <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap">
      <div>
        <div style="font-weight:800; font-size:12px">Destinatarios (statement)</div>
        <div style="color:#94a3b8; font-size:11px">Emails usados para enviar estados de cuenta.</div>
      </div>
      <a href="{{ $urls['billing_accounts_show'] }}" style="font-size:12px; color:#a78bfa; text-decoration:none">Gestionar →</a>
    </div>

    <div style="margin-top:8px; display:flex; flex-wrap:wrap; gap:6px">
      @forelse($recipients as $e)
        <span style="border:1px solid rgba(148,163,184,.25); background:rgba(15,23,42,.6); color:#e5e7eb; border-radius:999px; padding:4px 8px; font-size:12px">
          {{ $e }}
        </span>
      @empty
        <span style="color:#94a3b8; font-size:12px">— Sin destinatarios configurados</span>
      @endforelse
    </div>
  </div>

  <style>
    @media (max-width: 980px){
      .p360-billing-panel > div[style*="grid-template-columns:repeat(4"]{ grid-template-columns:repeat(2,minmax(0,1fr)) !important; }
      .p360-billing-panel > div[style*="grid-template-columns:repeat(3"]{ grid-template-columns:1fr !important; }
    }
  </style>
</div>