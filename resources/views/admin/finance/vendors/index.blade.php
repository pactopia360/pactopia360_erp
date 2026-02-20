@extends('layouts.admin')
@section('title','Finanzas · Vendedores')

@section('content')
  <div class="card" style="border:1px solid rgba(0,0,0,.08);border-radius:14px;background:#fff;padding:16px">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
      <div>
        <h1 style="margin:0;font-size:18px;font-weight:900">Vendedores</h1>
        <p style="margin:6px 0 0;color:#64748b">Base para comisiones y asignación por cuenta/venta.</p>
      </div>
      <a class="btn btn-primary" href="{{ route('admin.finance.vendors.create') }}" style="border-radius:10px;font-weight:900">Nuevo</a>
    </div>

    <div style="margin-top:12px;overflow:auto;border:1px solid rgba(0,0,0,.08);border-radius:14px">
      <table style="width:100%;border-collapse:separate;border-spacing:0;min-width:720px">
        <thead>
          <tr style="background:#f8fafc">
            <th style="padding:10px;border-bottom:1px solid rgba(0,0,0,.08);text-align:left;font-size:12px;color:#475569">ID</th>
            <th style="padding:10px;border-bottom:1px solid rgba(0,0,0,.08);text-align:left;font-size:12px;color:#475569">Nombre</th>
            <th style="padding:10px;border-bottom:1px solid rgba(0,0,0,.08);text-align:left;font-size:12px;color:#475569">Email</th>
            <th style="padding:10px;border-bottom:1px solid rgba(0,0,0,.08);text-align:left;font-size:12px;color:#475569">%</th>
            <th style="padding:10px;border-bottom:1px solid rgba(0,0,0,.08);text-align:left;font-size:12px;color:#475569">Activo</th>
          </tr>
        </thead>
        <tbody>
          @foreach($rows as $r)
            <tr>
              <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06)">{{ $r->id }}</td>
              <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06);font-weight:900">{{ $r->name }}</td>
              <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06)">{{ $r->email ?? '-' }}</td>
              <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06)">{{ $r->default_commission_pct ?? '-' }}</td>
              <td style="padding:10px;border-bottom:1px solid rgba(0,0,0,.06)">{{ $r->is_active ? 'Sí' : 'No' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
@endsection
