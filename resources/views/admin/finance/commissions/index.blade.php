@extends('layouts.admin')
@section('title','Finanzas · Comisiones')

@section('content')
  <div class="card" style="border:1px solid rgba(0,0,0,.08);border-radius:14px;background:#fff;padding:16px">
    <h1 style="margin:0;font-size:18px;font-weight:900">Comisiones</h1>
    <p style="margin:6px 0 0;color:#64748b">Placeholder (Paso 1). Aquí calcularemos comisiones por vendedor/periodo.</p>

    <div style="margin-top:12px;display:grid;grid-template-columns:1fr;gap:12px">
      <div style="border:1px solid rgba(0,0,0,.08);border-radius:14px;overflow:auto">
        <div style="padding:10px;background:#f8fafc;border-bottom:1px solid rgba(0,0,0,.08);font-weight:900">Vendedores</div>
        <table style="width:100%;border-collapse:separate;border-spacing:0;min-width:700px">
          <thead>
            <tr style="background:#fff">
              <th style="padding:10px;text-align:left;font-size:12px;color:#475569">ID</th>
              <th style="padding:10px;text-align:left;font-size:12px;color:#475569">Nombre</th>
              <th style="padding:10px;text-align:left;font-size:12px;color:#475569">Rate</th>
              <th style="padding:10px;text-align:left;font-size:12px;color:#475569">Activo</th>
            </tr>
          </thead>
          <tbody>
            @forelse($vendors as $v)
              <tr>
                <td style="padding:10px;border-top:1px solid rgba(0,0,0,.06)">{{ $v->id }}</td>
                <td style="padding:10px;border-top:1px solid rgba(0,0,0,.06)">{{ $v->name }}</td>
                <td style="padding:10px;border-top:1px solid rgba(0,0,0,.06)">{{ $v->commission_rate ?? '-' }}</td>
                <td style="padding:10px;border-top:1px solid rgba(0,0,0,.06)">{{ (int)$v->is_active === 1 ? 'Sí' : 'No' }}</td>
              </tr>
            @empty
              <tr><td colspan="4" style="padding:12px;color:#64748b">Sin vendedores.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div style="border:1px solid rgba(0,0,0,.08);border-radius:14px;overflow:auto">
        <div style="padding:10px;background:#f8fafc;border-bottom:1px solid rgba(0,0,0,.08);font-weight:900">Ventas recientes (para comisiones)</div>
        <table style="width:100%;border-collapse:separate;border-spacing:0;min-width:900px">
          <thead>
            <tr style="background:#fff">
              <th style="padding:10px;text-align:left;font-size:12px;color:#475569">ID</th>
              <th style="padding:10px;text-align:left;font-size:12px;color:#475569">Folio</th>
              <th style="padding:10px;text-align:left;font-size:12px;color:#475569">Periodo</th>
              <th style="padding:10px;text-align:left;font-size:12px;color:#475569">Vendedor</th>
              <th style="padding:10px;text-align:right;font-size:12px;color:#475569">Total</th>
            </tr>
          </thead>
          <tbody>
            @forelse($sales as $s)
              <tr>
                <td style="padding:10px;border-top:1px solid rgba(0,0,0,.06)">{{ $s->id }}</td>
                <td style="padding:10px;border-top:1px solid rgba(0,0,0,.06)">{{ $s->sale_code ?? '-' }}</td>
                <td style="padding:10px;border-top:1px solid rgba(0,0,0,.06)">{{ $s->period ?? '-' }}</td>
                <td style="padding:10px;border-top:1px solid rgba(0,0,0,.06)">{{ $s->vendor_name ?? '-' }}</td>
                <td style="padding:10px;border-top:1px solid rgba(0,0,0,.06);text-align:right;font-weight:900">${{ number_format((float)$s->total,2) }}</td>
              </tr>
            @empty
              <tr><td colspan="5" style="padding:12px;color:#64748b">Sin ventas.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
@endsection
