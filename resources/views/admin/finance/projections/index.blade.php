@extends('layouts.admin')
@section('title','Finanzas · Proyecciones')

@section('content')
  <div class="card" style="border:1px solid rgba(0,0,0,.08);border-radius:14px;background:#fff;padding:16px">
    <h1 style="margin:0;font-size:18px;font-weight:900">Proyecciones</h1>
    <p style="margin:6px 0 0;color:#64748b">Placeholder (Paso 1). Aquí saldrá el reporte mensual/anual + PDF (Paso 2).</p>

    <form method="GET" style="margin-top:12px;display:flex;gap:10px;align-items:end;flex-wrap:wrap">
      <div>
        <div style="font-size:12px;color:#475569;font-weight:800;margin-bottom:6px">Desde (YYYY-MM)</div>
        <input name="from" value="{{ $from }}" style="border:1px solid rgba(0,0,0,.12);border-radius:10px;padding:10px 12px;min-width:160px">
      </div>
      <div>
        <div style="font-size:12px;color:#475569;font-weight:800;margin-bottom:6px">Hasta (YYYY-MM)</div>
        <input name="to" value="{{ $to }}" style="border:1px solid rgba(0,0,0,.12);border-radius:10px;padding:10px 12px;min-width:160px">
      </div>
      <button class="btn btn-primary" style="border-radius:10px;font-weight:900">Aplicar</button>
    </form>

    <div style="margin-top:12px;overflow:auto;border:1px solid rgba(0,0,0,.08);border-radius:14px">
      <table style="width:100%;border-collapse:separate;border-spacing:0;min-width:700px">
        <thead>
          <tr style="background:#f8fafc">
            <th style="padding:10px;text-align:left;font-size:12px;color:#475569">Periodo</th>
            <th style="padding:10px;text-align:right;font-size:12px;color:#475569">Ventas</th>
            <th style="padding:10px;text-align:right;font-size:12px;color:#475569">Total</th>
          </tr>
        </thead>
        <tbody>
          @forelse($byMonth as $p => $m)
            <tr>
              <td style="padding:10px;border-top:1px solid rgba(0,0,0,.06)">{{ $p }}</td>
              <td style="padding:10px;border-top:1px solid rgba(0,0,0,.06);text-align:right">{{ $m['count'] }}</td>
              <td style="padding:10px;border-top:1px solid rgba(0,0,0,.06);text-align:right;font-weight:900">${{ number_format((float)$m['total'],2) }}</td>
            </tr>
          @empty
            <tr><td colspan="3" style="padding:12px;color:#64748b">Sin datos en el rango.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection
