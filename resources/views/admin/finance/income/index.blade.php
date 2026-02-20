@extends('layouts.admin')

@section('title','Finanzas · Ingresos (Ventas)')

@php
  $f = $filters ?? ['year'=>now()->format('Y'),'month'=>'all','origin'=>'all','st'=>'all','invSt'=>'all','q'=>''];
  $k = $kpis ?? [];
  $rows = $rows ?? collect();

  // Normalizaciones compat (si UI vieja manda origin=no_recurrente)
  if (($f['origin'] ?? 'all') === 'no_recurrente') $f['origin'] = 'unico';

  $money = function($n){
    $x = (float) ($n ?? 0);
    return '$' . number_format($x, 2);
  };

  $fmtDate = function($d){
    if (empty($d)) return '—';
    try { return \Illuminate\Support\Carbon::parse($d)->format('Y-m-d'); }
    catch (\Throwable $e) { return '—'; }
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
    return '<span style="display:inline-flex;align-items:center;gap:6px;padding:3px 10px;border-radius:999px;background:'.$v[0].';color:'.$v[1].';font-weight:900;font-size:12px;letter-spacing:.2px">'.$v[2].'</span>';
  };

  // Row background similar a Excel (semáforo)
  $rowBg = function(string $s){
    $s = strtolower(trim($s));
    return match($s){
      'pagado'  => '#dcfce7', // green-100
      'emitido' => '#dbeafe', // blue-100
      'pending' => '#fef3c7', // amber-100
      'vencido' => '#fee2e2', // red-100
      default   => '#ffffff',
    };
  };

  $months = [
    '01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril','05'=>'Mayo','06'=>'Junio',
    '07'=>'Julio','08'=>'Agosto','09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'
  ];

  $routeBase = route('admin.finance.income.index');
  $yearSel = (int) ($f['year'] ?? now()->year);
  $monthSel = (string) ($f['month'] ?? 'all');
@endphp

@section('content')
  <div style="display:flex;flex-direction:column;gap:14px">

    {{-- Header / KPIs --}}
    <div class="card" style="border:1px solid rgba(0,0,0,.08);border-radius:16px;background:#fff;padding:16px">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap">
        <div>
          <h1 style="margin:0;font-size:18px;font-weight:950">Ingresos (Ventas)</h1>
          <p style="margin:6px 0 0;color:#64748b">
            Vista tipo Excel: ingresos esperados por mes, con estatus de Estado de Cuenta y Estatus de Facturas.
          </p>
        </div>

        {{-- Filtros --}}
        <form method="GET" action="{{ $routeBase }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
          <select name="year" style="padding:8px 10px;border:1px solid rgba(0,0,0,.12);border-radius:10px">
            @for($y = now()->year - 2; $y <= now()->year + 2; $y++)
              <option value="{{ $y }}" @selected((int)$yearSel===$y)>{{ $y }}</option>
            @endfor
          </select>

          {{-- Select month (compat) --}}
          <select name="month" style="padding:8px 10px;border:1px solid rgba(0,0,0,.12);border-radius:10px">
            <option value="all" @selected($monthSel==='all')>Todos</option>
            @foreach($months as $mm=>$name)
              <option value="{{ $mm }}" @selected($monthSel===$mm)>{{ $mm }} · {{ $name }}</option>
            @endforeach
          </select>

          <select name="origin" style="padding:8px 10px;border:1px solid rgba(0,0,0,.12);border-radius:10px">
            <option value="all" @selected(($f['origin'] ?? 'all')==='all')>Origen (todos)</option>
            <option value="recurrente" @selected(($f['origin'] ?? 'all')==='recurrente')>Recurrente</option>
            <option value="unico" @selected(($f['origin'] ?? 'all')==='unico')>Único</option>
          </select>

          <select name="status" style="padding:8px 10px;border:1px solid rgba(0,0,0,.12);border-radius:10px">
            <option value="all" @selected(($f['st'] ?? 'all')==='all')>Estatus E.Cta (todos)</option>
            <option value="pending" @selected(($f['st'] ?? 'all')==='pending')>Pending</option>
            <option value="emitido" @selected(($f['st'] ?? 'all')==='emitido')>Emitido</option>
            <option value="pagado" @selected(($f['st'] ?? 'all')==='pagado')>Pagado</option>
            <option value="vencido" @selected(($f['st'] ?? 'all')==='vencido')>Vencido</option>
          </select>

          <select name="invoice_status" style="padding:8px 10px;border:1px solid rgba(0,0,0,.12);border-radius:10px">
            <option value="all" @selected(($f['invSt'] ?? 'all')==='all')>Estatus Factura (todos)</option>
            <option value="pending" @selected(($f['invSt'] ?? 'all')==='pending')>Pending</option>
            <option value="requested" @selected(($f['invSt'] ?? 'all')==='requested')>Solicitada</option>
            <option value="ready" @selected(($f['invSt'] ?? 'all')==='ready')>Lista</option>
            <option value="issued" @selected(($f['invSt'] ?? 'all')==='issued')>Emitida</option>
            <option value="cancelled" @selected(($f['invSt'] ?? 'all')==='cancelled')>Cancelada</option>
          </select>

          <input name="q" value="{{ $f['q'] ?? '' }}" placeholder="Buscar (cliente, RFC, UUID, cuenta)..."
                 style="min-width:260px;flex:1;padding:8px 10px;border:1px solid rgba(0,0,0,.12);border-radius:10px"/>

          <button type="submit" style="padding:9px 12px;border-radius:12px;border:0;background:#0f172a;color:#fff;font-weight:950">
            Filtrar
          </button>

          <a href="{{ $routeBase }}"
             style="padding:9px 12px;border-radius:12px;border:1px solid rgba(0,0,0,.12);background:#fff;color:#0f172a;font-weight:950;text-decoration:none">
            Limpiar
          </a>
        </form>
      </div>

      {{-- Botones por mes tipo Excel --}}
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:12px">
        <div style="display:inline-flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid rgba(0,0,0,.08);border-radius:12px;background:#f8fafc">
          <div style="font-weight:950;color:#0f172a;min-width:52px;text-align:center">{{ $yearSel }}</div>
        </div>

        <div style="display:flex;gap:6px;flex-wrap:wrap">
          {{-- TODOS --}}
          @php
            $qsBase = array_merge($f, ['year'=>$yearSel, 'month'=>'all']);
            $urlAll = $routeBase.'?'.http_build_query($qsBase);
            $isAll  = ($monthSel === 'all');
          @endphp
          <a href="{{ $urlAll }}"
             style="padding:8px 10px;border-radius:10px;border:1px solid rgba(0,0,0,.10);text-decoration:none;
                    font-weight:950;font-size:12px;white-space:nowrap;
                    background:{{ $isAll ? '#0f172a' : '#fff' }};
                    color:{{ $isAll ? '#fff' : '#0f172a' }}">
            Todos
          </a>

          @foreach($months as $mm=>$name)
            @php
              $qs = array_merge($f, ['year'=>$yearSel, 'month'=>$mm]);
              $url = $routeBase.'?'.http_build_query($qs);
              $active = ($monthSel === $mm);
            @endphp
            <a href="{{ $url }}"
               title="{{ $name }}"
               style="padding:8px 10px;border-radius:10px;border:1px solid rgba(0,0,0,.10);text-decoration:none;
                      font-weight:950;font-size:12px;white-space:nowrap;
                      background:{{ $active ? '#0f172a' : '#fff' }};
                      color:{{ $active ? '#fff' : '#0f172a' }}">
              {{ $name }}
            </a>
          @endforeach
        </div>
      </div>

      {{-- KPIs --}}
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
                <div style="font-size:12px;color:#64748b;font-weight:900">{{ $label }}</div>
                <div style="margin-top:6px;font-size:18px;font-weight:950;color:#0f172a">{{ $money(data_get($k, $key.'.amount', 0)) }}</div>
              </div>
              <div style="padding:6px 10px;border-radius:999px;background:#f1f5f9;color:#0f172a;font-weight:950;font-size:12px">
                {{ (int) data_get($k, $key.'.count', 0) }}
              </div>
            </div>
          </div>
        @endforeach
      </div>
    </div>

    {{-- Tabla tipo Excel --}}
    <div class="card" style="border:1px solid rgba(0,0,0,.08);border-radius:16px;background:#fff;padding:0;overflow:hidden">
      <div style="overflow:auto">
        <table style="width:1900px;border-collapse:separate;border-spacing:0">
          <thead>
            <tr>
              @php
                $ths = [
                  'Año','Mes','Vendedor','Cliente','Descripción',
                  'Origen','Periodicidad',
                  'Subtotal','IVA','Total',
                  'F Emisión','F Pago','Estatus E.Cta',
                  'RFC Receptor','Forma Pago',
                  'F Cta','F Mov',
                  'Estatus Factura','F Factura','UUID',
                  'Método Pago (real)','Estatus Pago (real)',
                ];
              @endphp
              @foreach($ths as $h)
                <th style="position:sticky;top:0;background:#0f172a;color:#fff;text-align:left;padding:10px 10px;font-size:12px;font-weight:950;white-space:nowrap;border-bottom:1px solid rgba(255,255,255,.15)">
                  {{ $h }}
                </th>
              @endforeach
            </tr>
          </thead>
          <tbody>
            @forelse($rows as $r)
              @php
                $bg = $rowBg((string)($r->ec_status ?? ''));
                $originLabel = strtolower((string)($r->origin ?? ''));
                if ($originLabel === 'no_recurrente') $originLabel = 'unico';
                $originLabel = $originLabel === 'unico' ? 'Único' : ucfirst($originLabel ?: '—');
              @endphp
              <tr style="background:{{ $bg }}">
                <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap;font-weight:950">{{ $r->year ?? substr($r->period ?? '',0,4) }}</td>
                <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap;color:#0f172a;font-weight:900">{{ $r->month_name ?? ($r->month_num ?? '—') }}</td>

                <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap;color:#334155">
                  {{ $r->vendor ?? '—' }}
                </td>

                <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);min-width:240px">
                  <div style="font-weight:950;color:#0f172a">{{ $r->client ?? $r->company ?? '—' }}</div>
                  @if(!empty($r->rfc_emisor))
                    <div style="margin-top:2px;font-size:12px;color:#64748b">RFC: {{ $r->rfc_emisor }}</div>
                  @endif
                </td>

                <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);min-width:280px;color:#0f172a">
                  <div style="font-weight:900">{{ $r->description ?? '—' }}</div>
                  <div style="margin-top:2px;font-size:12px;color:#64748b">Cuenta: {{ $r->account_id ?? '—' }} · Periodo: {{ $r->period ?? '—' }}</div>
                </td>

                <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap">
                  <span style="font-weight:950">{{ $originLabel }}</span>
                </td>
                <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap;color:#334155">{{ $r->periodicity ?? '—' }}</td>

                <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap;font-weight:950">{{ $money($r->subtotal ?? 0) }}</td>
                <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap;font-weight:950">{{ $money($r->iva ?? 0) }}</td>
                <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap;font-weight:950">{{ $money($r->total ?? 0) }}</td>

                <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap;color:#334155">{{ $fmtDate($r->f_emision ?? $r->sent_at ?? null) }}</td>
                <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap;color:#334155">{{ $fmtDate($r->f_pago ?? $r->paid_at ?? null) }}</td>

                <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap">{!! $badge((string)($r->ec_status ?? '')) !!}</td>

                <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap;color:#334155">{{ $r->rfc_receptor ?: '—' }}</td>
                <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap;color:#334155">{{ $r->forma_pago ?: '—' }}</td>

                <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap;color:#334155">{{ $fmtDate($r->f_cta ?? null) }}</td>
                <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap;color:#334155">{{ $fmtDate($r->f_mov ?? null) }}</td>

                <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap;color:#334155">{{ $r->invoice_status ?: '—' }}</td>
                <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap;color:#334155">{{ $fmtDate($r->f_factura ?? $r->invoice_date ?? null) }}</td>
                <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap;color:#334155">{{ $r->cfdi_uuid ?: '—' }}</td>

                <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap;color:#334155">{{ $r->payment_method ?: '—' }}</td>
                <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);white-space:nowrap;color:#334155">{{ $r->payment_status ?: '—' }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="22" style="padding:18px;color:#64748b">
                  No hay registros con los filtros actuales.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div style="padding:12px 14px;border-top:1px solid rgba(0,0,0,.06);display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center">
        <div style="color:#64748b;font-weight:900">
          Filas: <span style="color:#0f172a">{{ $rows->count() }}</span>
        </div>

        <div style="color:#64748b;font-size:12px">
          Nota: “F Cta” se toma de <code>billing_statements.sent_at</code>.
          “F Mov” se llenará desde el módulo de Ventas (al registrar ventas/compras dentro del mes).
        </div>
      </div>
    </div>

  </div>
@endsection