@extends('layouts.admin')
@section('title','Finanzas · Nuevo vendedor')

@section('content')
  <div class="card" style="border:1px solid rgba(0,0,0,.08);border-radius:14px;background:#fff;padding:16px;max-width:720px">
    <h1 style="margin:0 0 10px;font-size:18px;font-weight:900">Nuevo vendedor</h1>

    <form method="POST" action="{{ route('admin.finance.vendors.store') }}" style="display:grid;gap:10px">
      @csrf

      <label>
        <div style="font-size:12px;color:#64748b;margin:0 0 6px">Nombre</div>
        <input name="name" required style="width:100%;padding:10px;border:1px solid rgba(0,0,0,.12);border-radius:12px">
      </label>

      <label>
        <div style="font-size:12px;color:#64748b;margin:0 0 6px">Email</div>
        <input name="email" style="width:100%;padding:10px;border:1px solid rgba(0,0,0,.12);border-radius:12px">
      </label>

      <label>
        <div style="font-size:12px;color:#64748b;margin:0 0 6px">% comisión default</div>
        <input name="default_commission_pct" type="number" step="0.001" min="0" max="100"
               style="width:100%;padding:10px;border:1px solid rgba(0,0,0,.12);border-radius:12px">
      </label>

      <label style="display:flex;gap:10px;align-items:center">
        <input type="checkbox" name="is_active" value="1" checked>
        <span style="font-weight:800">Activo</span>
      </label>

      <div style="display:flex;gap:10px">
        <a class="btn btn-light" href="{{ route('admin.finance.vendors.index') }}" style="border-radius:10px;font-weight:900">Volver</a>
        <button class="btn btn-primary" style="border-radius:10px;font-weight:900">Guardar</button>
      </div>
    </form>
  </div>
@endsection
