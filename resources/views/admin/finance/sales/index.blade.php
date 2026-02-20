@extends('layouts.admin')
@section('title','Finanzas · Ventas')

@section('content')
  @php
    /** @var \Illuminate\Support\Collection|\Illuminate\Support\Enumerable $rows */
    $rows = $rows ?? collect();
  @endphp

  <div class="card" style="border:1px solid rgba(0,0,0,.08);border-radius:14px;background:#fff;padding:16px">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
      <div>
        <h1 style="margin:0;font-size:18px;font-weight:900">Ventas</h1>
        <p style="margin:6px 0 0;color:#64748b">Ventas únicas / no recurrentes (base). En el Paso 2 se integra con Estado de cuenta + Pagos.</p>
      </div>
      <a class="btn btn-primary" href="{{ route('admin.finance.sales.create') }}" style="border-radius:10px;font-weight:900">Nueva venta</a>
    </div>

    <div style="margin-top:12px;overflow:auto;border:1px solid rgba(0,0,0,.08);border-radius:14px">
      <table style="width:100%;border-collapse:separate;border-spacing:0;min-width:1100px">
        <thead>
          <tr style="background:#f8fafc">
            <th style="padding:10px;border-bottom:1px solid rgba(0,0,0,.08);text-align:left;font-size:12px;color:#475569">ID</th>
            <th style="padding:10px;border-bottom:1px solid rgba(0,0,0,.08);text-align:left;font-size:12px;color:#475569">Folio</th>
            <th style="padding:10px;border-bottom:1px solid rgba(0,0,0,.08);text-align:left;font-size:12px;color:#475569">RFC receptor</th>
            <th style="padding:10px;border-bottom:1px solid rgba(0,0,0,.08);text-align:left;font-size:12px;color:#475569">Origen</th>
            <th style="padding:10px;border-bottom:1px solid rgba(0,0,0,.08);text-align:left;font-size:12px;color:#475569">Periodicidad</th>
            <th style="padding:10px;border-bottom:1px solid rgba(0,0,0,.08);text-align:right;font-size:12px;color:#475569">Total</th>
            <th style="padding:10px;border-bottom:1px solid rgba(0,0,0,.08);text-align:left;font-size:12px;color:#475569">Estatus (Edo Cta)</th>
            <th style="padding:10px;border-bottom:1px solid rgba(0,0,0,.08);text-align:left;font-size:12px;color:#475569">Estatus (Factura)</th>
          </tr>
        </thead>
        <tbody>
          @if($rows->isEmpty())
            <tr>
              <td colspan="8" style="padding:14px;border-bottom:1px solid rgba(0,0,0,.06);color:#64748b">
                Aún no hay ventas registradas.
              </td>
            </tr>
          @else
            @foreach($rows as $r)
              <tr>
                <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06)">{{ $r->id }}</td>
                <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06)">{{ $r->sale_code ?? '-' }}</td>
                <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06)">{{ $r->receiver_rfc ?? '-' }}</td>
                <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06)">{{ $r->origin ?? '-' }}</td>
                <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06)">{{ $r->periodicity ?? '-' }}</td>
                <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);text-align:right;font-weight:900">
                  ${{ number_format((float)($r->total ?? 0), 2) }}
                </td>
                <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06)">{{ $r->statement_status ?? '-' }}</td>
                <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06)">{{ $r->invoice_status ?? '-' }}</td>
              </tr>
            @endforeach
          @endif
        </tbody>
      </table>
    </div>
  </div>
@endsection
