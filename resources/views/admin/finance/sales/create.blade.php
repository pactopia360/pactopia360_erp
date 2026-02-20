@extends('layouts.admin')
@section('title','Finanzas · Nueva venta')

@section('content')
  @php
    $vendors  = $vendors ?? collect();
    $accounts = $accounts ?? collect();
  @endphp

  <div class="card" style="border:1px solid rgba(0,0,0,.08);border-radius:14px;background:#fff;padding:16px;max-width:980px">
    <h1 style="margin:0 0 10px;font-size:18px;font-weight:900">Nueva venta</h1>

    <form method="POST" action="{{ route('admin.finance.sales.store') }}" style="display:grid;gap:10px">
      @csrf

      <label>
        <div style="font-size:12px;color:#64748b;margin:0 0 6px">Cuenta (cliente)</div>
        <select name="account_id" required style="width:100%;padding:10px;border:1px solid rgba(0,0,0,.12);border-radius:12px">
          <option value="">Selecciona una cuenta…</option>
          @foreach($accounts as $a)
            @php
              $label = ($a->nombre_comercial ?: $a->razon_social) ?: ('Cuenta '.$a->id);
              $rfc   = $a->rfc_padre ? (' · RFC: '.$a->rfc_padre) : '';
            @endphp
            <option value="{{ $a->id }}">{{ $label }}{{ $rfc }}</option>
          @endforeach
        </select>
      </label>

      <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px">
        <label>
          <div style="font-size:12px;color:#64748b;margin:0 0 6px">Folio</div>
          <input name="sale_code" style="width:100%;padding:10px;border:1px solid rgba(0,0,0,.12);border-radius:12px">
        </label>

        <label>
          <div style="font-size:12px;color:#64748b;margin:0 0 6px">Vendedor</div>
          <select name="vendor_id" style="width:100%;padding:10px;border:1px solid rgba(0,0,0,.12);border-radius:12px">
            <option value="">(Sin asignar)</option>
            @foreach($vendors as $v)
              <option value="{{ $v->id }}">{{ $v->name }}</option>
            @endforeach
          </select>
        </label>
      </div>

      <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px">
        <label>
          <div style="font-size:12px;color:#64748b;margin:0 0 6px">Periodo (YYYY-MM)</div>
          <input name="period" required placeholder="2026-02" value="{{ now()->format('Y-m') }}"
                 style="width:100%;padding:10px;border:1px solid rgba(0,0,0,.12);border-radius:12px">
        </label>

        <label>
          <div style="font-size:12px;color:#64748b;margin:0 0 6px">Origen</div>
          <select name="origin" required style="width:100%;padding:10px;border:1px solid rgba(0,0,0,.12);border-radius:12px">
            <option value="no_recurrente" selected>No recurrente</option>
            <option value="recurrente">Recurrente</option>
            <option value="unico">Único</option>
          </select>
        </label>

        <label>
          <div style="font-size:12px;color:#64748b;margin:0 0 6px">Periodicidad</div>
          <select name="periodicity" required style="width:100%;padding:10px;border:1px solid rgba(0,0,0,.12);border-radius:12px">
            <option value="unico" selected>Único</option>
            <option value="mensual">Mensual</option>
            <option value="anual">Anual</option>
          </select>
        </label>
      </div>

      <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px">
        <label>
          <div style="font-size:12px;color:#64748b;margin:0 0 6px">RFC receptor</div>
          <input name="receiver_rfc" style="width:100%;padding:10px;border:1px solid rgba(0,0,0,.12);border-radius:12px">
        </label>
        <label>
          <div style="font-size:12px;color:#64748b;margin:0 0 6px">Forma de pago (CFDI)</div>
          <input name="pay_method" placeholder="Transferencia / Tarjeta / Efectivo / etc."
                 style="width:100%;padding:10px;border:1px solid rgba(0,0,0,.12);border-radius:12px">
        </label>
      </div>

      <div style="display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px">
        <label><div style="font-size:12px;color:#64748b;margin:0 0 6px">F Venta</div><input type="date" name="sale_date" style="width:100%;padding:10px;border:1px solid rgba(0,0,0,.12);border-radius:12px"></label>
        <label><div style="font-size:12px;color:#64748b;margin:0 0 6px">F Cta</div><input type="date" name="f_cta" style="width:100%;padding:10px;border:1px solid rgba(0,0,0,.12);border-radius:12px"></label>
        <label><div style="font-size:12px;color:#64748b;margin:0 0 6px">F Mov</div><input type="date" name="f_mov" style="width:100%;padding:10px;border:1px solid rgba(0,0,0,.12);border-radius:12px"></label>
        <label><div style="font-size:12px;color:#64748b;margin:0 0 6px">F Factura</div><input type="date" name="invoice_date" style="width:100%;padding:10px;border:1px solid rgba(0,0,0,.12);border-radius:12px"></label>
        <label><div style="font-size:12px;color:#64748b;margin:0 0 6px">F Pago</div><input type="date" name="paid_date" style="width:100%;padding:10px;border:1px solid rgba(0,0,0,.12);border-radius:12px"></label>
      </div>

      <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px">
        <label>
          <div style="font-size:12px;color:#64748b;margin:0 0 6px">Subtotal</div>
          <input type="number" step="0.01" name="subtotal" required value="0"
                 style="width:100%;padding:10px;border:1px solid rgba(0,0,0,.12);border-radius:12px">
          <div style="margin-top:6px;font-size:12px;color:#64748b">
            IVA 16% y Total se calculan automáticamente al guardar.
          </div>
        </label>

        <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px">
          <label>
            <div style="font-size:12px;color:#64748b;margin:0 0 6px">Estatus Edo Cta</div>
            <select name="statement_status" required style="width:100%;padding:10px;border:1px solid rgba(0,0,0,.12);border-radius:12px">
              <option value="pending" selected>pending</option>
              <option value="emitido">emitido</option>
              <option value="pagado">pagado</option>
              <option value="vencido">vencido</option>
            </select>
          </label>

          <label>
            <div style="font-size:12px;color:#64748b;margin:0 0 6px">Estatus factura</div>
            <select name="invoice_status" required style="width:100%;padding:10px;border:1px solid rgba(0,0,0,.12);border-radius:12px">
              <option value="sin_solicitud" selected>sin_solicitud</option>
              <option value="solicitada">solicitada</option>
              <option value="en_proceso">en_proceso</option>
              <option value="facturada">facturada</option>
              <option value="rechazada">rechazada</option>
            </select>
          </label>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px">
        <label>
          <div style="font-size:12px;color:#64748b;margin:0 0 6px">UUID CFDI (si ya existe)</div>
          <input name="cfdi_uuid" style="width:100%;padding:10px;border:1px solid rgba(0,0,0,.12);border-radius:12px">
        </label>

        <label style="display:flex;gap:10px;align-items:center;margin-top:24px">
          <input type="checkbox" name="include_in_statement" value="1">
          <span style="font-weight:900">Agregar al estado de cuenta (Paso 2)</span>
        </label>
      </div>

      <label>
        <div style="font-size:12px;color:#64748b;margin:0 0 6px">Notas</div>
        <textarea name="notes" rows="3" style="width:100%;padding:10px;border:1px solid rgba(0,0,0,.12);border-radius:12px"></textarea>
      </label>

      <div style="display:flex;gap:10px">
        <a class="btn btn-light" href="{{ route('admin.finance.sales.index') }}" style="border-radius:10px;font-weight:900">Volver</a>
        <button class="btn btn-primary" style="border-radius:10px;font-weight:900">Guardar</button>
      </div>

    </form>
  </div>
@endsection