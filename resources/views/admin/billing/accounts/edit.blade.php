@extends('layouts.admin')

@section('title', 'Facturación · Configurar cuenta')

@section('content')
<div style="padding:18px;max-width:980px;">
  <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;">
    <div>
      <h2 style="margin:0;font-size:18px;font-weight:900;color:#0f172a;">Configurar facturación</h2>
      <div style="margin-top:4px;color:#64748b;font-weight:800;font-size:12px;">
        Cuenta #{{ $acc->id }} · {{ $acc->email }} · RFC {{ $acc->rfc }}
      </div>
    </div>

    <div style="display:flex;gap:10px;align-items:center;">
      <a href="{{ route('admin.billing.accounts.index') }}"
         style="display:inline-block;padding:10px 12px;border-radius:12px;border:1px solid rgba(15,23,42,.12);background:#fff;color:#0f172a;font-weight:900;text-decoration:none;">
        Volver
      </a>
      <a href="{{ route('admin.billing.accounts.statement', $acc->id) }}"
         style="display:inline-block;padding:10px 12px;border-radius:12px;border:1px solid rgba(15,23,42,.12);background:#0f172a;color:#fff;font-weight:900;text-decoration:none;">
        Estado de cuenta
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

  <div style="margin-top:14px;background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:14px;">
    <form method="POST" action="{{ route('admin.billing.accounts.update', $acc->id) }}" style="display:grid;gap:12px;">
      @csrf
      @method('PUT')

      <div style="display:grid;grid-template-columns: 1fr 220px;gap:12px;align-items:end;">
        <div>
          <div style="font-weight:900;color:#0f172a;margin-bottom:6px;">Stripe price_key</div>
          <select name="price_key" style="width:100%;padding:10px 12px;border:1px solid #e5e7eb;border-radius:12px;font-weight:800;">
            @foreach($prices as $p)
              @php
                $lbl = $p->price_key . ' · ' . $p->plan . ' · ' . $p->billing_cycle . ' · $' . number_format((float)$p->display_amount, 2) . ' ' . $p->currency;
              @endphp
              <option value="{{ $p->price_key }}" @selected((string)old('price_key', $billing['price_key']) === (string)$p->price_key)>
                {{ $lbl }}
              </option>
            @endforeach
          </select>
        </div>

        <div>
          <div style="font-weight:900;color:#0f172a;margin-bottom:6px;">Ciclo</div>
          <select name="billing_cycle" style="width:100%;padding:10px 12px;border:1px solid #e5e7eb;border-radius:12px;font-weight:800;">
            <option value="mensual" @selected(old('billing_cycle', $billing['billing_cycle']) === 'mensual')>mensual</option>
            <option value="anual" @selected(old('billing_cycle', $billing['billing_cycle']) === 'anual')>anual</option>
          </select>
        </div>
      </div>

      <div>
        <div style="font-weight:900;color:#0f172a;margin-bottom:6px;">Concepto en estado de cuenta</div>
        <input type="text" name="concept" value="{{ old('concept', $billing['concept']) }}"
               placeholder="Ej: Licencia Pactopia360 PRO (Mensual)"
               style="width:100%;padding:10px 12px;border:1px solid #e5e7eb;border-radius:12px;font-weight:800;">
      </div>

      <button type="submit"
              style="justify-self:start;padding:11px 14px;border-radius:12px;border:1px solid rgba(15,23,42,.12);background:#22c55e;color:#0b1220;font-weight:950;">
        Guardar billing
      </button>

      <div style="margin-top:6px;color:#64748b;font-weight:800;font-size:12px;line-height:1.5;">
        Esto escribe en <code>accounts.meta.billing.*</code> usando <code>JSON_MERGE_PATCH</code> (MariaDB-safe).
      </div>
    </form>
  </div>
</div>
@endsection
