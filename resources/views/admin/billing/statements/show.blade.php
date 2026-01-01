{{-- resources/views/admin/billing/statements/show.blade.php (vS2 · FIX: rutas robustas + modal awareness + total/saldo correctos) --}}
@extends('layouts.admin')

@section('title', 'Estado de cuenta · '.$period)
@section('layout', 'full')

@php
  use Illuminate\Support\Facades\Route;

  $isModal = (bool)($isModal ?? request()->boolean('modal'));
  $period  = (string)($period ?? request('period', now()->format('Y-m')));

  $routeBack = Route::has('admin.billing.statements.index')
    ? route('admin.billing.statements.index', ['period'=>$period])
    : url('/admin/billing/statements?period='.urlencode($period));

  $routePdf = Route::has('admin.billing.statements.pdf')
    ? route('admin.billing.statements.pdf', ['accountId'=>$account->id, 'period'=>$period])
    : url('/admin/billing/statements/'.$account->id.'/'.$period.'/pdf');
@endphp

@push('styles')
<style>
  .st-wrap{ padding: 0; }
  .st-card{
    background:var(--card-bg);
    border:1px solid var(--card-border);
    border-radius:16px;
    box-shadow: var(--shadow-1);
    overflow:hidden;
  }
  .st-head{
    padding:16px;
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:12px;
    flex-wrap:wrap;
    border-bottom:1px solid rgba(15,23,42,.08);
  }
  .st-title{ margin:0; font-size:18px; font-weight:950; color:var(--text); }
  .st-sub{ margin-top:6px; color:var(--muted); font-weight:850; font-size:12px; }
  .st-actions{ display:flex; gap:8px; align-items:center; }
  .st-btn{
    display:inline-flex; align-items:center; gap:8px;
    padding:10px 12px; border-radius:12px;
    border:1px solid rgba(15,23,42,.12);
    background: color-mix(in oklab, var(--card-bg) 92%, transparent);
    color:var(--text);
    font-weight:950; text-decoration:none;
    cursor:pointer; white-space:nowrap;
  }
  .st-btn.primary{ background:var(--text); color:#fff; border-color:rgba(15,23,42,.22); }
  html[data-theme="dark"] .st-btn.primary{ background:#111827; border-color:rgba(255,255,255,.12); }

  .st-grid{
    display:grid;
    grid-template-columns: 1fr 360px;
    gap:14px;
    padding:16px;
  }
  @media(max-width: 980px){
    .st-grid{ grid-template-columns:1fr; }
  }

  .st-box{
    background: color-mix(in oklab, var(--card-bg) 92%, transparent);
    border:1px solid rgba(15,23,42,.10);
    border-radius:16px;
    padding:14px;
  }

  .pill{
    display:inline-block; padding:6px 10px; border-radius:999px;
    font-weight:950; font-size:12px; border:1px solid transparent; white-space:nowrap;
  }
  .pill.ok{ background:#dcfce7; color:#166534; border-color:#bbf7d0; }
  .pill.warn{ background:#fef3c7; color:#92400e; border-color:#fde68a; }
  .pill.dim{ background:rgba(15,23,42,.06); color:var(--muted); border-color:rgba(15,23,42,.10); }
  .mono{ font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas,"Liberation Mono","Courier New", monospace; font-weight:900; }
  .mut{ color:var(--muted); font-weight:850; font-size:12px; }

  table.st-t{ width:100%; border-collapse:collapse; }
  .st-t th{
    text-align:left; padding:10px 12px; font-size:12px;
    color:var(--muted); font-weight:950; text-transform:uppercase; letter-spacing:.04em;
    border-bottom:1px solid rgba(15,23,42,.10);
  }
  .st-t td{
    padding:12px; border-bottom:1px solid rgba(15,23,42,.07);
    vertical-align:top; color:var(--text); font-weight:800;
  }
  .tright{ text-align:right; }

  /* si esto se abre dentro de iframe modal, quita padding extra */
  .p360-page{ padding:0 !important; }
</style>
@endpush

@section('content')
<div class="st-wrap">
  <div class="st-card">

    @php
      $accName = trim((string)(($account->razon_social ?? '') ?: ($account->name ?? '') ?: ($account->email ?? '—')));
      $accRfc  = (string)($account->rfc ?? '—');
      $accMail = (string)($account->email ?? '—');

      // En el controller:
      // - $total = Total del periodo mostrado (cargo real si existe, si no expected)
      // - $saldo = saldo pendiente (total - abono)
      $statusPill = 'dim';
      $statusText = 'SIN MOVIMIENTOS';
      if (((float)($total ?? 0)) > 0.00001) {
        if (((float)($saldo ?? 0)) <= 0.00001) { $statusPill='ok'; $statusText='PAGADO'; }
        else { $statusPill='warn'; $statusText='PENDIENTE'; }
      }
    @endphp

    <div class="st-head">
      <div>
        <h2 class="st-title">Estado de cuenta · <span class="mono">{{ $period }}</span></h2>
        <div class="st-sub">
          {{ $accName }} · RFC: <span class="mono">{{ $accRfc }}</span> · Email: <span class="mono">{{ $accMail }}</span><br>
          Periodo: <b>{{ $period_label ?? $period }}</b> · <span class="pill {{ $statusPill }}">{{ $statusText }}</span>
        </div>
      </div>

      <div class="st-actions">
        @if(!$isModal)
          <a class="st-btn" href="{{ $routeBack }}">Volver</a>
        @endif
        <a class="st-btn primary" href="{{ $routePdf }}" target="_blank" rel="noopener">Descargar PDF</a>
      </div>
    </div>

    @if(session('ok'))
      <div style="padding:12px 16px;">
        <div class="pill ok">{{ session('ok') }}</div>
      </div>
    @endif
    @if($errors->any())
      <div style="padding:12px 16px;">
        <div class="pill warn">{{ $errors->first() }}</div>
      </div>
    @endif

    <div class="st-grid">
      <div class="st-box">
        <table class="st-t">
          <thead>
            <tr>
              <th style="width:160px">Concepto</th>
              <th>Detalle</th>
              <th class="tright" style="width:140px">Cargo</th>
              <th class="tright" style="width:140px">Abono</th>
            </tr>
          </thead>
          <tbody>
            @forelse($items as $it)
              <tr>
                <td style="font-weight:950;">{{ $it->concepto ?? '—' }}</td>
                <td class="mut">{{ $it->detalle ?? '—' }}</td>
                <td class="tright" style="font-weight:950;">${{ number_format((float)($it->cargo ?? 0), 2) }}</td>
                <td class="tright" style="font-weight:950;">${{ number_format((float)($it->abono ?? 0), 2) }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="4" class="mut" style="padding:14px;">Sin movimientos en este periodo.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div class="st-box">
        <div style="display:grid; gap:10px;">
          <div>
            <div class="mut">Total esperado (licencia)</div>
            <div style="font-size:20px;font-weight:950;">${{ number_format((float)($expected_total ?? 0), 2) }}</div>
            <div class="mut">Tarifa: <span class="mono">{{ $tarifa_label ?? '—' }}</span></div>
          </div>

          <div style="border-top:1px solid rgba(15,23,42,.08); padding-top:10px;">
            <div class="mut">Cargo real (movimientos)</div>
            <div style="font-size:18px;font-weight:950;">${{ number_format((float)($cargo_real ?? 0), 2) }}</div>
          </div>

          <div style="border-top:1px solid rgba(15,23,42,.08); padding-top:10px;">
            <div class="mut">Pagado</div>
            <div style="font-size:18px;font-weight:950;">${{ number_format((float)($abono ?? 0), 2) }}</div>
          </div>

          <div style="border-top:1px solid rgba(15,23,42,.08); padding-top:10px;">
            <div class="mut">Total del periodo (mostrado)</div>
            <div style="font-size:18px;font-weight:950;">${{ number_format((float)($total ?? 0), 2) }}</div>
          </div>

          <div style="border-top:1px solid rgba(15,23,42,.08); padding-top:10px;">
            <div class="mut">Saldo pendiente</div>
            <div>
              <span class="pill {{ ((float)($saldo ?? 0)) > 0 ? 'warn' : 'ok' }}">
                ${{ number_format((float)($saldo ?? 0), 2) }}
              </span>
            </div>
          </div>

          {{-- Form para agregar movimiento --}}
          <div style="border-top:1px solid rgba(15,23,42,.08); padding-top:12px;">
            <div style="font-weight:950;margin-bottom:8px;">Agregar movimiento</div>

            @php
              $routeAdd = Route::has('admin.billing.statements.items.add')
                ? route('admin.billing.statements.items.add', ['accountId'=>$account->id, 'period'=>$period])
                : url('/admin/billing/statements/'.$account->id.'/'.$period.'/items/add');
            @endphp

            <form method="POST" action="{{ $routeAdd }}">
              @csrf

              <div style="display:grid; gap:8px;">
                <input name="concepto" required maxlength="255"
                       placeholder="Concepto (ej. Licencia, Ajuste, Pago)"
                       style="padding:10px 12px;border-radius:12px;border:1px solid rgba(15,23,42,.12);font-weight:850;background:transparent;color:var(--text);">

                <textarea name="detalle" maxlength="2000" rows="3"
                          placeholder="Detalle (opcional)"
                          style="padding:10px 12px;border-radius:12px;border:1px solid rgba(15,23,42,.12);font-weight:850;background:transparent;color:var(--text);"></textarea>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                  <input name="cargo" type="number" step="0.01" min="0"
                         placeholder="Cargo"
                         style="padding:10px 12px;border-radius:12px;border:1px solid rgba(15,23,42,.12);font-weight:850;background:transparent;color:var(--text);">
                  <input name="abono" type="number" step="0.01" min="0"
                         placeholder="Abono"
                         style="padding:10px 12px;border-radius:12px;border:1px solid rgba(15,23,42,.12);font-weight:850;background:transparent;color:var(--text);">
                </div>

                <label style="display:flex; gap:8px; align-items:center; font-weight:900; color:var(--muted);">
                  <input type="checkbox" name="send_email" value="1">
                  Enviar por correo (incluye liga de pago si hay saldo)
                </label>

                <input name="to" type="email" maxlength="150"
                       placeholder="Enviar a (opcional, si va vacío usa el email de la cuenta)"
                       style="padding:10px 12px;border-radius:12px;border:1px solid rgba(15,23,42,.12);font-weight:850;background:transparent;color:var(--text);">

                <button class="st-btn primary" type="submit" style="justify-content:center;">Guardar movimiento</button>
              </div>
            </form>
          </div>

        </div>
      </div>
    </div>

  </div>
</div>
@endsection
