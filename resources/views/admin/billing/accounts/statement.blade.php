@extends('layouts.admin')

@section('title', 'Facturación · Estado de cuenta')

@section('content')
<div style="padding:18px;max-width:1100px;">
  <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;">
    <div>
      <h2 style="margin:0;font-size:18px;font-weight:900;color:#0f172a;">Estado de cuenta (Admin)</h2>
      <div style="margin-top:4px;color:#64748b;font-weight:800;font-size:12px;">
        Cuenta #{{ $acc->id }} · {{ $acc->email }} · RFC {{ $acc->rfc }}
      </div>
    </div>

    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
      <a href="{{ route('admin.billing.accounts.edit', $acc->id) }}"
         style="display:inline-block;padding:10px 12px;border-radius:12px;border:1px solid rgba(15,23,42,.12);background:#fff;color:#0f172a;font-weight:900;text-decoration:none;">
        Configurar billing
      </a>
      <a href="{{ route('admin.billing.accounts.index') }}"
         style="display:inline-block;padding:10px 12px;border-radius:12px;border:1px solid rgba(15,23,42,.12);background:#0f172a;color:#fff;font-weight:900;text-decoration:none;">
        Volver a cuentas
      </a>
    </div>
  </div>

  @if(session('ok'))
    <div style="margin-top:12px;padding:10px 12px;border:1px solid #bbf7d0;background:#dcfce7;color:#166534;border-radius:12px;font-weight:900;">
      {{ session('ok') }}
    </div>
  @endif
  @if($errors->any())
    <div style="margin-top:12px;padding:10px 12px;border:1px solid #fecaca;background:#fef2f2;color:#991b1b;border-radius:12px;font-weight:900;">
      {{ $errors->first() }}
    </div>
  @endif

  <div style="margin-top:14px;display:grid;grid-template-columns: 1fr 360px;gap:12px;align-items:start;">
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:16px;overflow:hidden;">
      <div style="padding:12px 14px;border-bottom:1px solid #eef2f7;display:flex;justify-content:space-between;align-items:center;gap:10px;">
        <form method="GET" action="{{ route('admin.billing.accounts.statement', $acc->id) }}" style="display:flex;gap:8px;align-items:center;">
          <div style="font-weight:900;color:#0f172a;">Periodo</div>
          <input type="text" name="period" value="{{ $period }}" placeholder="YYYY-MM"
                 style="width:110px;padding:9px 10px;border:1px solid #e5e7eb;border-radius:12px;font-weight:900;">
          <button type="submit" style="padding:9px 10px;border-radius:12px;border:1px solid rgba(15,23,42,.12);background:#fff;color:#0f172a;font-weight:900;">
            Ver
          </button>
        </form>

        <div style="display:flex;gap:10px;align-items:center;">
          <div style="display:grid;gap:2px;text-align:right;">
            <div style="font-size:11px;color:#64748b;font-weight:900;">Saldo</div>
            <div style="font-size:18px;color:#0f172a;font-weight:950;">${{ number_format((float)$saldo, 2) }}</div>
          </div>
        </div>
      </div>

      <table style="width:100%;border-collapse:collapse;">
        <thead>
          <tr style="background:#f8fafc;border-bottom:1px solid #e5e7eb;">
            <th style="text-align:left;padding:12px;font-size:12px;color:#64748b;font-weight:900;">#</th>
            <th style="text-align:left;padding:12px;font-size:12px;color:#64748b;font-weight:900;">Concepto</th>
            <th style="text-align:right;padding:12px;font-size:12px;color:#64748b;font-weight:900;">Cargo</th>
            <th style="text-align:right;padding:12px;font-size:12px;color:#64748b;font-weight:900;">Abono</th>
            <th style="text-align:left;padding:12px;font-size:12px;color:#64748b;font-weight:900;">Source/Ref</th>
          </tr>
        </thead>
        <tbody>
          @forelse($movs as $m)
            <tr style="border-bottom:1px solid #eef2f7;">
              <td style="padding:12px;font-weight:900;color:#0f172a;">{{ $m->id }}</td>
              <td style="padding:12px;">
                <div style="font-weight:900;color:#0f172a;">{{ $m->concepto }}</div>
                @if(!empty($m->detalle))
                  <div style="margin-top:2px;color:#64748b;font-weight:800;font-size:12px;">{{ \Illuminate\Support\Str::limit($m->detalle, 120) }}</div>
                @endif
              </td>
              <td style="padding:12px;text-align:right;font-weight:900;color:#0f172a;">
                {{ number_format((float)$m->cargo, 2) }}
              </td>
              <td style="padding:12px;text-align:right;font-weight:900;color:#0f172a;">
                {{ number_format((float)$m->abono, 2) }}
              </td>
              <td style="padding:12px;">
                <div style="font-weight:900;color:#0f172a;">{{ $m->source }}</div>
                <div style="margin-top:2px;color:#64748b;font-weight:800;font-size:12px;word-break:break-all;">{{ $m->ref }}</div>
              </td>
            </tr>
          @empty
            <tr><td colspan="5" style="padding:14px;color:#64748b;font-weight:800;">Sin movimientos para este periodo.</td></tr>
          @endforelse
        </tbody>
      </table>

      <div style="padding:12px 14px;border-top:1px solid #eef2f7;display:flex;justify-content:flex-end;gap:12px;flex-wrap:wrap;">
        <div style="font-weight:900;color:#64748b;">Cargo: <span style="color:#0f172a;">${{ number_format((float)($sum->cargo ?? 0), 2) }}</span></div>
        <div style="font-weight:900;color:#64748b;">Abono: <span style="color:#0f172a;">${{ number_format((float)($sum->abono ?? 0), 2) }}</span></div>
        <div style="font-weight:950;color:#0f172a;">Saldo: ${{ number_format((float)($sum->saldo ?? 0), 2) }}</div>
      </div>
    </div>

    <div style="display:grid;gap:12px;">
      <div style="background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:14px;">
        <div style="font-weight:950;color:#0f172a;">Acciones</div>
        <div style="margin-top:8px;display:grid;gap:10px;">
          <form method="POST" action="{{ route('admin.billing.accounts.statement.send', $acc->id) }}" style="display:grid;gap:8px;">
            @csrf
            <input type="hidden" name="period" value="{{ $period }}">
            <input type="email" name="email" value="{{ $acc->email }}" placeholder="Correo destino"
                   style="width:100%;padding:10px 12px;border:1px solid #e5e7eb;border-radius:12px;font-weight:800;">
            <button type="submit"
                    style="padding:11px 12px;border-radius:12px;border:1px solid rgba(15,23,42,.12);background:#0f172a;color:#fff;font-weight:950;">
              Enviar estado de cuenta por correo
            </button>
          </form>

          <a href="{{ $portalUrl }}" target="_blank"
             style="display:block;text-align:center;padding:11px 12px;border-radius:12px;border:1px solid rgba(15,23,42,.12);background:#fff;color:#0f172a;font-weight:950;text-decoration:none;">
            Abrir portal cliente
          </a>
        </div>
      </div>

      <div style="background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:14px;">
        <div style="font-weight:950;color:#0f172a;">Registrar abono manual</div>
        <div style="margin-top:6px;color:#64748b;font-weight:800;font-size:12px;line-height:1.45;">
          Úsalo para transferencias: agrega un movimiento <b>abono</b> con <code>source=manual</code>.
        </div>

        <form method="POST" action="{{ route('admin.billing.accounts.statement.manual_payment', $acc->id) }}" style="margin-top:10px;display:grid;gap:10px;">
          @csrf
          <input type="hidden" name="period" value="{{ $period }}">

          <div>
            <div style="font-weight:900;color:#0f172a;margin-bottom:6px;">Monto</div>
            <input type="number" step="0.01" name="amount" value="" placeholder="0.00"
                   style="width:100%;padding:10px 12px;border:1px solid #e5e7eb;border-radius:12px;font-weight:900;">
          </div>

          <div>
            <div style="font-weight:900;color:#0f172a;margin-bottom:6px;">Referencia (opcional)</div>
            <input type="text" name="ref" value="" placeholder="Ej: SPEI-123456"
                   style="width:100%;padding:10px 12px;border:1px solid #e5e7eb;border-radius:12px;font-weight:800;">
          </div>

          <div>
            <div style="font-weight:900;color:#0f172a;margin-bottom:6px;">Detalle (opcional)</div>
            <textarea name="detalle" rows="3" placeholder="Ej: Pago por transferencia del 23/12/2025"
                      style="width:100%;padding:10px 12px;border:1px solid #e5e7eb;border-radius:12px;font-weight:800;"></textarea>
          </div>

          <button type="submit"
                  style="padding:11px 12px;border-radius:12px;border:1px solid rgba(15,23,42,.12);background:#22c55e;color:#0b1220;font-weight:950;">
            Registrar abono
          </button>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
