@extends('layouts.admin')
@section('title','Finanzas · Ventas')

@section('content')
  @php
    $rows    = $rows ?? collect();
    $kpis    = $kpis ?? ['total'=>0,'pending'=>0,'emitido'=>0,'pagado'=>0];
    $filters = $filters ?? ['period'=>'','origin'=>'','vendor_id'=>'','q'=>''];
    $vendors = $vendors ?? collect();

    $money = fn($n) => '$'.number_format((float)($n??0), 2);

    $badge = function(string $s){
      $s = strtolower(trim($s));
      $map = [
        'pagado'  => ['#dcfce7','#166534','Pagado'],
        'emitido' => ['#e0f2fe','#075985','Emitido'],
        'pending' => ['#fff7ed','#9a3412','Pending'],
        'vencido' => ['#fee2e2','#991b1b','Vencido'],
      ];
      $v = $map[$s] ?? ['#f1f5f9','#334155', strtoupper($s ?: '—')];
      return '<span style="display:inline-flex;align-items:center;gap:6px;padding:3px 10px;border-radius:999px;background:'.$v[0].';color:'.$v[1].';font-weight:900;font-size:12px;letter-spacing:.2px">'.$v[2].'</span>';
    };

    $invBadge = function(?string $s){
      $s = strtolower(trim((string)$s));
      $map = [
        'sin_solicitud' => ['#f1f5f9','#334155','Sin solicitud'],
        'solicitada'    => ['#fff7ed','#9a3412','Solicitada'],
        'en_proceso'    => ['#e0f2fe','#075985','En proceso'],
        'facturada'     => ['#dcfce7','#166534','Facturada'],
        'rechazada'     => ['#fee2e2','#991b1b','Rechazada'],
      ];
      $v = $map[$s] ?? ['#f1f5f9','#334155', strtoupper($s ?: '—')];
      return '<span style="display:inline-flex;align-items:center;gap:6px;padding:3px 10px;border-radius:999px;background:'.$v[0].';color:'.$v[1].';font-weight:900;font-size:12px;letter-spacing:.2px">'.$v[2].'</span>';
    };

    $yn = function($d){
      if (empty($d)) return '—';
      try { return \Illuminate\Support\Carbon::parse($d)->format('Y-m-d'); }
      catch (\Throwable $e) { return '—'; }
    };
  @endphp

  <div style="display:flex;flex-direction:column;gap:14px">

    {{-- Header / KPIs / Filtros --}}
    <div class="card" style="border:1px solid rgba(0,0,0,.08);border-radius:16px;background:#fff;padding:16px">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap">
        <div>
          <h1 style="margin:0;font-size:18px;font-weight:900">Ventas</h1>
          <p style="margin:6px 0 0;color:#64748b">
            Ventas (principalmente únicas/no recurrentes). Aquí registras lo que nace en el mes y luego se refleja en Ingresos.
          </p>
        </div>

        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
          <a class="btn btn-primary" href="{{ route('admin.finance.sales.create') }}" style="border-radius:12px;font-weight:900">
            Nueva venta
          </a>
          <a href="{{ route('admin.finance.income.index') }}" class="btn btn-light"
             style="border-radius:12px;font-weight:900;border:1px solid rgba(0,0,0,.12)">
            Ir a Ingresos
          </a>
        </div>
      </div>

      <form method="GET" action="{{ route('admin.finance.sales.index') }}"
            style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:12px">

        <input name="period" value="{{ $filters['period'] ?? '' }}" placeholder="Periodo (YYYY-MM)"
               style="width:160px;padding:8px 10px;border:1px solid rgba(0,0,0,.12);border-radius:10px"/>

        <select name="origin" style="padding:8px 10px;border:1px solid rgba(0,0,0,.12);border-radius:10px">
          <option value="" @selected(($filters['origin'] ?? '')==='')>Origen (todos)</option>
          <option value="unico" @selected(($filters['origin'] ?? '')==='unico')>Único</option>
          <option value="no_recurrente" @selected(($filters['origin'] ?? '')==='no_recurrente')>No recurrente</option>
          <option value="recurrente" @selected(($filters['origin'] ?? '')==='recurrente')>Recurrente</option>
        </select>

        <select name="vendor_id" style="padding:8px 10px;border:1px solid rgba(0,0,0,.12);border-radius:10px">
          <option value="" @selected(($filters['vendor_id'] ?? '')==='')>Vendedor (todos)</option>
          @foreach($vendors as $v)
            <option value="{{ $v->id }}" @selected((string)($filters['vendor_id'] ?? '') === (string)$v->id)>
              {{ $v->name }}{{ !empty($v->is_active) ? '' : ' (inactivo)' }}
            </option>
          @endforeach
        </select>

        <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Buscar (folio, RFC, UUID, vendedor, notas)..."
               style="min-width:260px;flex:1;padding:8px 10px;border:1px solid rgba(0,0,0,.12);border-radius:10px"/>

        <button type="submit" style="padding:9px 12px;border-radius:12px;border:0;background:#0f172a;color:#fff;font-weight:900">
          Filtrar
        </button>

        <a href="{{ route('admin.finance.sales.index') }}"
           style="padding:9px 12px;border-radius:12px;border:1px solid rgba(0,0,0,.12);background:#fff;color:#0f172a;font-weight:900;text-decoration:none">
          Limpiar
        </a>
      </form>

      <div style="display:grid;grid-template-columns:repeat(4,minmax(180px,1fr));gap:10px;margin-top:14px">
        @foreach([
          ['Total', $kpis['total'] ?? 0],
          ['Pending', $kpis['pending'] ?? 0],
          ['Emitido', $kpis['emitido'] ?? 0],
          ['Pagado', $kpis['pagado'] ?? 0],
        ] as [$label,$amt])
          <div style="border:1px solid rgba(0,0,0,.08);border-radius:14px;padding:12px;background:#fff">
            <div style="font-size:12px;color:#64748b;font-weight:800">{{ $label }}</div>
            <div style="margin-top:6px;font-size:18px;font-weight:950;color:#0f172a">{{ $money($amt) }}</div>
          </div>
        @endforeach
      </div>
    </div>

    {{-- Tabla --}}
    <div class="card" style="border:1px solid rgba(0,0,0,.08);border-radius:16px;background:#fff;padding:0;overflow:hidden">
      <div style="overflow:auto">
        <table style="width:1900px;border-collapse:separate;border-spacing:0">
          <thead>
            <tr>
              @foreach([
                'ID','Periodo','Empresa','Folio',
                'Vendedor','Origen','Periodicidad',
                'RFC receptor','Forma pago',
                'F Mov','F Cta','Fecha Factura','Fecha Pago','Target E.Cta',
                'Subtotal','IVA','Total',
                'Estatus (Edo Cta)','Estatus (Factura)','UUID CFDI',
                'Incluye en E.Cta','Acciones'
              ] as $h)
                <th style="position:sticky;top:0;background:#0f172a;color:#fff;text-align:left;padding:10px 10px;font-size:12px;font-weight:900;white-space:nowrap;border-bottom:1px solid rgba(255,255,255,.15)">
                  {{ $h }}
                </th>
              @endforeach
            </tr>
          </thead>
          <tbody>
            @if($rows->isEmpty())
              <tr>
                <td colspan="22" style="padding:16px;color:#64748b">Aún no hay ventas registradas.</td>
              </tr>
            @else
              @foreach($rows as $r)
                @php
                  $per = (string)($r->period ?? '');
                  $y = strlen($per) >= 4 ? (int)substr($per,0,4) : now()->year;
                  $m = strlen($per) >= 7 ? substr($per,5,2) : '01';

                  $incomeUrl = route('admin.finance.income.index', [
                    'year'   => $y,
                    'month'  => $m,
                    'origin' => 'unico',
                    'q'      => (string)($r->account_id ?? ''),
                  ]);
                @endphp

                <tr>
                  <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06)">{{ $r->id }}</td>
                  <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap;font-weight:900">
                    {{ $r->period ?? '-' }}
                  </td>
                  <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);min-width:280px">
                    <div style="font-weight:900;color:#0f172a">{{ $r->company ?? ('Cuenta '.$r->account_id) }}</div>
                    @if(!empty($r->rfc_emisor))
                      <div style="margin-top:2px;font-size:12px;color:#64748b">RFC: {{ $r->rfc_emisor }}</div>
                    @endif
                    <div style="margin-top:6px;display:flex;gap:8px;flex-wrap:wrap">
                      <a href="{{ $incomeUrl }}"
                         style="display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;border:1px solid rgba(0,0,0,.12);background:#fff;text-decoration:none;color:#0f172a;font-weight:900;font-size:12px">
                        Ver en Ingresos
                      </a>
                    </div>
                  </td>
                  <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06)">{{ $r->sale_code ?? '-' }}</td>

                  <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06)">{{ $r->vendor_name ?? '-' }}</td>
                  <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06)">{{ $r->origin ?? '-' }}</td>
                  <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06)">{{ $r->periodicity ?? '-' }}</td>

                  <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06)">{{ $r->receiver_rfc ?? '-' }}</td>
                  <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06)">{{ $r->pay_method ?? '-' }}</td>

                  <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap">{{ $yn($r->f_mov ?? $r->sale_date ?? $r->created_at ?? null) }}</td>
                  <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap">{{ $yn($r->f_cta ?? null) }}</td>
                  <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap">{{ $yn($r->invoice_date ?? null) }}</td>
                  <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap">{{ $yn($r->paid_date ?? null) }}</td>
                  <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap">{{ $r->statement_period_target ?? '—' }}</td>

                  <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);text-align:right;font-weight:900">{{ $money($r->subtotal ?? 0) }}</td>
                  <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);text-align:right;font-weight:900">{{ $money($r->iva ?? 0) }}</td>
                  <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);text-align:right;font-weight:950">{{ $money($r->total ?? 0) }}</td>

                  <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap">{!! $badge((string)($r->statement_status ?? 'pending')) !!}</td>
                  <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap">{!! $invBadge($r->invoice_status ?? null) !!}</td>
                  <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap;color:#334155">{{ $r->cfdi_uuid ?? '—' }}</td>

                  <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap;font-weight:900">
                    {{ !empty($r->include_in_statement) ? 'Sí' : 'No' }}
                  </td>

                  <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap">
                    <a href="{{ $incomeUrl }}"
                       style="display:inline-flex;align-items:center;gap:6px;padding:8px 10px;border-radius:10px;border:1px solid rgba(0,0,0,.12);background:#fff;text-decoration:none;color:#0f172a;font-weight:900;font-size:12px">
                      Abrir en Ingresos
                    </a>
                  </td>
                </tr>
              @endforeach
            @endif
          </tbody>
        </table>
      </div>

      <div style="padding:12px 14px;border-top:1px solid rgba(0,0,0,.06);display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center">
        <div style="color:#64748b;font-weight:800">
          Filas: <span style="color:#0f172a">{{ $rows->count() }}</span>
        </div>
        <div style="color:#64748b;font-size:12px">
          Nota: “Abrir en Ingresos” filtra por <code>year/month</code> del periodo y busca por <code>account_id</code> con origen “único”.
        </div>
      </div>
    </div>

  </div>
@endsection