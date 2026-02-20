@extends('layouts.admin')

@section('title','Finanzas · Ingresos (Ventas)')

@php
  $f = $filters ?? ['year'=>now()->format('Y'),'month'=>'all','origin'=>'all','st'=>'all','invSt'=>'all','q'=>''];
  $k = $kpis ?? [];
  $rows = $rows ?? collect();

  $money = function($n){
    $x = (float) ($n ?? 0);
    return '$' . number_format($x, 2);
  };

  $badge = function(string $s){
    $s = strtolower(trim($s));
    $map = [
      'pagado'  => ['#dcfce7','#166534','Pagado'],
      'emitido' => ['#e0f2fe','#075985','Emitido'],
      'pending' => ['#fff7ed','#9a3412','Pending'],
      'vencido' => ['#fee2e2','#991b1b','Vencido'],
    ];
    $v = $map[$s] ?? ['#f1f5f9','#334155', strtoupper($s ?: '—')];
    return '<span style="display:inline-flex;align-items:center;gap:6px;padding:3px 10px;border-radius:999px;background:'.$v[0].';color:'.$v[1].';font-weight:800;font-size:12px;letter-spacing:.2px">'.$v[2].'</span>';
  };
@endphp

@section('content')
  <div style="display:flex;flex-direction:column;gap:14px">

    {{-- Header / KPIs --}}
    <div class="card" style="border:1px solid rgba(0,0,0,.08);border-radius:16px;background:#fff;padding:16px">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap">
        <div>
          <h1 style="margin:0;font-size:18px;font-weight:900">Ingresos (Ventas)</h1>
          <p style="margin:6px 0 0;color:#64748b">Vista tipo Excel: ingresos esperados por mes, con estatus de E.Cta y estatus de facturas.</p>
        </div>

        <form method="GET" action="{{ route('admin.finance.income.index') }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
          <select name="year" style="padding:8px 10px;border:1px solid rgba(0,0,0,.12);border-radius:10px">
            @for($y = now()->year - 2; $y <= now()->year + 2; $y++)
              <option value="{{ $y }}" @selected((int)$f['year']===$y)>{{ $y }}</option>
            @endfor
          </select>

          <select name="month" style="padding:8px 10px;border:1px solid rgba(0,0,0,.12);border-radius:10px">
            <option value="all" @selected(($f['month'] ?? 'all')==='all')>Todos los meses</option>
            @for($m=1;$m<=12;$m++)
              @php $mm=str_pad((string)$m,2,'0',STR_PAD_LEFT); @endphp
              <option value="{{ $mm }}" @selected(($f['month'] ?? 'all')===$mm)>{{ $mm }}</option>
            @endfor
          </select>

          <select name="origin" style="padding:8px 10px;border:1px solid rgba(0,0,0,.12);border-radius:10px">
            <option value="all" @selected(($f['origin'] ?? 'all')==='all')>Origen (todos)</option>
            <option value="recurrente" @selected(($f['origin'] ?? 'all')==='recurrente')>Recurrente</option>
            <option value="no_recurrente" @selected(($f['origin'] ?? 'all')==='no_recurrente')>No recurrente</option>
          </select>

          <select name="status" style="padding:8px 10px;border:1px solid rgba(0,0,0,.12);border-radius:10px">
            <option value="all" @selected(($f['st'] ?? 'all')==='all')>Estatus E.Cta (todos)</option>
            <option value="pending" @selected(($f['st'] ?? 'all')==='pending')>Pending</option>
            <option value="emitido" @selected(($f['st'] ?? 'all')==='emitido')>Emitido</option>
            <option value="pagado" @selected(($f['st'] ?? 'all')==='pagado')>Pagado</option>
            <option value="vencido" @selected(($f['st'] ?? 'all')==='vencido')>Vencido</option>
          </select>

          <input name="q" value="{{ $f['q'] ?? '' }}" placeholder="Buscar (empresa, RFC, UUID, cuenta)..."
                 style="min-width:260px;flex:1;padding:8px 10px;border:1px solid rgba(0,0,0,.12);border-radius:10px"/>

          <button type="submit" style="padding:9px 12px;border-radius:12px;border:0;background:#0f172a;color:#fff;font-weight:900">
            Filtrar
          </button>

          <a href="{{ route('admin.finance.income.index') }}"
             style="padding:9px 12px;border-radius:12px;border:1px solid rgba(0,0,0,.12);background:#fff;color:#0f172a;font-weight:900;text-decoration:none">
            Limpiar
          </a>
        </form>
      </div>

      <div style="display:grid;grid-template-columns:repeat(5,minmax(180px,1fr));gap:10px;margin-top:14px">
        @php
          $cards = [
            ['Total','total'],
            ['Pending','pending'],
            ['Emitido','emitido'],
            ['Pagado','pagado'],
            ['Vencido','vencido'],
          ];
        @endphp
        @foreach($cards as [$label,$key])
          <div style="border:1px solid rgba(0,0,0,.08);border-radius:14px;padding:12px;background:#fff">
            <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start">
              <div>
                <div style="font-size:12px;color:#64748b;font-weight:800">{{ $label }}</div>
                <div style="margin-top:6px;font-size:18px;font-weight:950;color:#0f172a">{{ $money(data_get($k, $key.'.amount', 0)) }}</div>
              </div>
              <div style="padding:6px 10px;border-radius:999px;background:#f1f5f9;color:#0f172a;font-weight:900;font-size:12px">
                {{ (int) data_get($k, $key.'.count', 0) }}
              </div>
            </div>
          </div>
        @endforeach
      </div>
    </div>

    {{-- Tabla Excel --}}
    <div class="card" style="border:1px solid rgba(0,0,0,.08);border-radius:16px;background:#fff;padding:0;overflow:hidden">
      <div style="overflow:auto">
        <table style="width:1600px;border-collapse:separate;border-spacing:0">
          <thead>
            <tr>
              @php
                $ths = [
                  'Periodo','Cuenta','Empresa',
                  'Origen','Periodicidad',
                  'Subtotal','IVA 16%','Total',
                  'Estatus E.Cta','F Cta','Vence','F Pago',
                  'RFC Receptor','Forma Pago (CFDI)','F Mov',
                  'Estatus Factura','Fecha Factura','UUID CFDI',
                  'Método Pago (real)','Estatus Pago (real)',
                ];
              @endphp
              @foreach($ths as $h)
                <th style="position:sticky;top:0;background:#0f172a;color:#fff;text-align:left;padding:10px 10px;font-size:12px;font-weight:900;white-space:nowrap;border-bottom:1px solid rgba(255,255,255,.15)">
                  {{ $h }}
                </th>
              @endforeach
            </tr>
          </thead>
          <tbody>
            @forelse($rows as $r)
              <tr>
                <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap;font-weight:900">{{ $r->period }}</td>
                <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap;color:#334155">{{ $r->account_id }}</td>
                <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);min-width:260px">
                  <div style="font-weight:900;color:#0f172a">{{ $r->company }}</div>
                  @if(!empty($r->rfc_emisor))
                    <div style="margin-top:2px;font-size:12px;color:#64748b">RFC: {{ $r->rfc_emisor }}</div>
                  @endif
                </td>

                <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap">
                  <span style="font-weight:900">{{ $r->origin }}</span>
                </td>
                <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap;color:#334155">{{ $r->periodicity }}</td>

                <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap;font-weight:900">{{ $money($r->subtotal) }}</td>
                <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap;font-weight:900">{{ $money($r->iva) }}</td>
                <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap;font-weight:950">{{ $money($r->total) }}</td>

                <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap">{!! $badge($r->ec_status) !!}</td>
                <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap;color:#334155">{{ $r->f_cta ? \Illuminate\Support\Carbon::parse($r->f_cta)->format('Y-m-d') : '—' }}</td>
                <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap;color:#334155">{{ $r->due_date ? \Illuminate\Support\Carbon::parse($r->due_date)->format('Y-m-d') : '—' }}</td>
                <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap;color:#334155">{{ $r->paid_at ? \Illuminate\Support\Carbon::parse($r->paid_at)->format('Y-m-d') : '—' }}</td>

                <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap;color:#334155">{{ $r->rfc_receptor ?: '—' }}</td>
                <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap;color:#334155">{{ $r->forma_pago ?: '—' }}</td>
                <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap;color:#334155">{{ $r->f_mov ? \Illuminate\Support\Carbon::parse($r->f_mov)->format('Y-m-d') : '—' }}</td>

                <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap;color:#334155">{{ $r->invoice_status ?: '—' }}</td>
                <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap;color:#334155">{{ $r->invoice_date ? \Illuminate\Support\Carbon::parse($r->invoice_date)->format('Y-m-d') : '—' }}</td>
                <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap;color:#334155">{{ $r->cfdi_uuid ?: '—' }}</td>

                <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap;color:#334155">{{ $r->payment_method ?: '—' }}</td>
                <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap;color:#334155">{{ $r->payment_status ?: '—' }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="20" style="padding:18px;color:#64748b">
                  No hay registros con los filtros actuales.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div style="padding:12px 14px;border-top:1px solid rgba(0,0,0,.06);display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center">
        <div style="color:#64748b;font-weight:800">
          Filas: <span style="color:#0f172a">{{ $rows->count() }}</span>
        </div>

        <div style="color:#64748b;font-size:12px">
          Nota: “F Cta” se toma de <code>billing_statements.sent_at</code>. “F Mov” se llenará desde el módulo de Ventas (cuando registremos compras/ventas dentro del mes).
        </div>
      </div>
    </div>

  </div>
@endsection
