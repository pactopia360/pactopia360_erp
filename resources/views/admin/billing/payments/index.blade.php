{{-- C:\wamp64\www\pactopia360_erp\resources\views\admin\billing\payments\index.blade.php --}}
@extends('layouts.admin')

@section('title','Facturación · Pagos')
@section('layout','full')
@section('contentLayout','full')

@php
  $q        = (string)($q ?? '');
  $status   = (string)($status ?? '');
  $method   = (string)($method ?? '');
  $provider = (string)($provider ?? '');
  $from     = (string)($from ?? '');
  $to       = (string)($to ?? '');
  $methods  = $methods ?? [];
  $providers= $providers ?? [];
  $kpis     = $kpis ?? ['today'=>0,'month'=>0,'pending'=>0,'paid'=>0,'avg_paid'=>0];
  $chart    = $chart ?? ['line'=>['labels'=>[],'data'=>[]],'donut'=>['labels'=>[],'data'=>[]]];
@endphp

@section('content')
@php
  $p360CssPath = public_path('assets/admin/css/payments-center.css');
  $p360Css = is_file($p360CssPath) ? file_get_contents($p360CssPath) : '';
@endphp

{{-- Intento normal (si el layout soporta @stack('styles')) --}}
@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/admin/css/payments-center.css') }}">
@endpush

{{-- Fallback HARD: garantiza estilos aunque el layout no imprima stacks/head --}}
@if($p360Css !== '')
  <style>{!! $p360Css !!}</style>
@endif

<div class="p360-payments">
  <div class="p360-payments__head">
    <div>
      <h1 class="p360-payments__ttl">Pagos</h1>
      <div class="p360-payments__sub">Centro de pagos (Admin) · CRUD · KPIs · Gráficas</div>
    </div>

    <div class="p360-payments__actions">
      <button type="button" class="p360-btn p360-btn--primary" data-open="p360-modal-create">
        + Nuevo pago
      </button>
    </div>
  </div>

  @if(!empty($error))
    <div class="p360-alert p360-alert--danger">{{ $error }}</div>
  @endif
  @if(session('ok'))
    <div class="p360-alert p360-alert--ok">{{ session('ok') }}</div>
  @endif
  @if($errors->any())
    <div class="p360-alert p360-alert--danger">{{ $errors->first() }}</div>
  @endif

  {{-- KPI cards --}}
  <div class="p360-kpis">
    <div class="p360-kpi">
      <div class="k">Cobrado hoy</div>
      <div class="v">${{ number_format((float)$kpis['today'],2) }}</div>
    </div>
    <div class="p360-kpi">
      <div class="k">Cobrado mes</div>
      <div class="v">${{ number_format((float)$kpis['month'],2) }}</div>
    </div>
    <div class="p360-kpi">
      <div class="k">Pendientes</div>
      <div class="v">{{ (int)$kpis['pending'] }}</div>
    </div>
    <div class="p360-kpi">
      <div class="k">Pagados (total)</div>
      <div class="v">{{ (int)$kpis['paid'] }}</div>
    </div>
    <div class="p360-kpi">
      <div class="k">Ticket promedio</div>
      <div class="v">${{ number_format((float)$kpis['avg_paid'],2) }}</div>
    </div>
  </div>

  {{-- Filters --}}
  <div class="p360-card">
    <div class="p360-card__head">
      <div class="p360-card__ttl">Filtros</div>
      <div class="p360-chiprow">
        <a class="p360-chip" href="{{ route('admin.billing.payments.index', array_merge(request()->except(['from','to']), ['from'=>now()->toDateString(),'to'=>now()->toDateString()])) }}">Hoy</a>
        <a class="p360-chip" href="{{ route('admin.billing.payments.index', array_merge(request()->except(['from','to']), ['from'=>now()->subDays(6)->toDateString(),'to'=>now()->toDateString()])) }}">Últimos 7 días</a>
        <a class="p360-chip" href="{{ route('admin.billing.payments.index', array_merge(request()->except('status'), ['status'=>'pending'])) }}">Pendientes</a>
        <a class="p360-chip" href="{{ route('admin.billing.payments.index', array_merge(request()->except('status'), ['status'=>'paid'])) }}">Pagados</a>
        <a class="p360-chip p360-chip--ghost" href="{{ route('admin.billing.payments.index') }}">Limpiar</a>
      </div>
    </div>

    <form method="GET" class="p360-form p360-form--grid">
      <div class="p360-field">
        <label>Buscar</label>
        <input name="q" value="{{ $q }}" placeholder="account_id · stripe_* · referencia">
      </div>

      <div class="p360-field">
        <label>Status</label>
        <select name="status">
          <option value="">— Todos —</option>
          @foreach(['pending','paid','failed','canceled','cancelled','refunded'] as $s)
            <option value="{{ $s }}" @selected($status===$s)>{{ $s }}</option>
          @endforeach
        </select>
      </div>

      <div class="p360-field">
        <label>Método</label>
        <select name="method">
          <option value="">— Todos —</option>
          @foreach($methods as $m)
            <option value="{{ $m }}" @selected($method===$m)>{{ $m }}</option>
          @endforeach
        </select>
      </div>

      <div class="p360-field">
        <label>Proveedor</label>
        <select name="provider">
          <option value="">— Todos —</option>
          @foreach($providers as $p)
            <option value="{{ $p }}" @selected($provider===$p)>{{ $p }}</option>
          @endforeach
        </select>
      </div>

      <div class="p360-field">
        <label>Desde</label>
        <input type="date" name="from" value="{{ $from }}">
      </div>

      <div class="p360-field">
        <label>Hasta</label>
        <input type="date" name="to" value="{{ $to }}">
      </div>

      <div class="p360-field p360-field--actions">
        <label>&nbsp;</label>
        <button class="p360-btn p360-btn--primary" type="submit">Filtrar</button>
      </div>
    </form>
  </div>

  {{-- Charts --}}
  <div class="p360-grid2">
    <div class="p360-card">
      <div class="p360-card__head">
        <div class="p360-card__ttl">Cobros últimos 30 días</div>
      </div>
      <div class="p360-chart">
        <canvas id="p360Line"></canvas>
      </div>
    </div>

    <div class="p360-card">
      <div class="p360-card__head">
        <div class="p360-card__ttl">Distribución por status</div>
      </div>
      <div class="p360-chart">
        <canvas id="p360Donut"></canvas>
      </div>
    </div>
  </div>

  {{-- Table --}}
  <div class="p360-card">
    <div class="p360-card__head">
      <div class="p360-card__ttl">Listado</div>
      <div class="p360-card__meta">{{ method_exists($rows,'total') ? number_format($rows->total()) : '' }} registros</div>
    </div>

    <div class="p360-tablewrap">
      <table class="p360-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Cuenta</th>
            <th>RFC</th>
            <th>Cliente</th>
            <th>Status</th>
            <th>Monto</th>
            <th>paid_at</th>
            <th class="t-right">Acciones</th>
          </tr>
        </thead>
        <tbody>
        @foreach($rows as $r)
          @php
            $amt = round(((int)($r->amount ?? 0))/100,2);
            $cur = (string)($r->currency ?? 'MXN');
          @endphp
          @php
            $payPayload = [
              'id'          => $r->id,
              'account_id'  => $r->account_id,
              'status'      => $r->status,
              'amount_pesos'=> $amt,
              'currency'    => $cur,
              'paid_at'     => ($r->paid_at ?? null),
              'period'      => ($r->period ?? null),
              'concept'     => ($r->concept ?? null),
              'method'      => ($r->method ?? null),
              'provider'    => ($r->provider ?? null),
              'reference'   => ($r->reference ?? null),
            ];
          @endphp

          <tr data-pay="{{ e(json_encode($payPayload, JSON_UNESCAPED_UNICODE)) }}">
            <td class="mono">#{{ $r->id }}</td>
            <td class="mono">{{ $r->account_id }}</td>
            <td>{{ $r->account_rfc ?? '—' }}</td>
            <td>{{ $r->account_name ?? '—' }}</td>
            <td><span class="p360-pill p360-pill--{{ $r->status }}">{{ $r->status }}</span></td>
            <td class="mono">${{ number_format($amt,2) }} {{ $cur }}</td>
            <td class="mono">{{ $r->paid_at ?? '—' }}</td>
            <td class="t-right">
              <div class="p360-rowactions">
                <button type="button" class="p360-btn p360-btn--sm" data-edit="1">Editar</button>

                <form method="POST" action="{{ route('admin.billing.payments.destroy', $r->id) }}" onsubmit="return confirm('¿Eliminar pago #{{ $r->id }}? Esto NO revierte estados_cuenta.');">
                  @csrf
                  @method('DELETE')
                  <button class="p360-btn p360-btn--sm p360-btn--danger" type="submit">Eliminar</button>
                </form>

                <form method="POST" action="{{ route('admin.billing.payments.email', $r->id) }}">
                  @csrf
                  <input name="to" class="p360-input p360-input--sm" placeholder="correo (vacío=del cliente)" value="">
                  <button class="p360-btn p360-btn--sm" type="submit">Reenviar</button>
                </form>
              </div>
            </td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>

    @if(method_exists($rows,'links'))
      <div class="p360-pager">{!! $rows->links() !!}</div>
    @endif
  </div>
</div>

{{-- Modal: Crear --}}
<div class="p360-modal" id="p360-modal-create" aria-hidden="true">
  <div class="p360-modal__backdrop" data-close="p360-modal-create"></div>
  <div class="p360-modal__panel">
    <div class="p360-modal__head">
      <div class="p360-modal__ttl">Nuevo pago (manual)</div>
      <button class="p360-x" type="button" data-close="p360-modal-create">×</button>
    </div>

    <form method="POST" action="{{ route('admin.billing.payments.manual') }}" class="p360-modal__body">
      @csrf

      <div class="p360-form p360-form--grid">
        <div class="p360-field">
          <label>account_id</label>
          <input name="account_id" required placeholder="ej. 18">
        </div>

        <div class="p360-field">
          <label>Monto (MXN)</label>
          <input name="amount_pesos" required placeholder="ej. 990.00">
        </div>

        <div class="p360-field">
          <label>Periodo (YYYY-MM)</label>
          <input name="period" placeholder="2026-02">
        </div>

        <div class="p360-field">
          <label>Concepto</label>
          <input name="concept" placeholder="Pago recibido (manual)">
        </div>

        <div class="p360-field p360-field--full">
          <label class="p360-check">
            <input type="checkbox" name="also_apply_statement" value="1">
            <span>También aplicar al estado de cuenta (estados_cuenta.abono)</span>
          </label>
        </div>
      </div>

      <div class="p360-modal__foot">
        <button class="p360-btn" type="button" data-close="p360-modal-create">Cancelar</button>
        <button class="p360-btn p360-btn--primary" type="submit">Guardar</button>
      </div>
    </form>
  </div>
</div>

{{-- Modal: Editar --}}
<div class="p360-modal" id="p360-modal-edit" aria-hidden="true">
  <div class="p360-modal__backdrop" data-close="p360-modal-edit"></div>
  <div class="p360-modal__panel">
    <div class="p360-modal__head">
      <div class="p360-modal__ttl">Editar pago</div>
      <button class="p360-x" type="button" data-close="p360-modal-edit">×</button>
    </div>

    <form method="POST" action="#" id="p360EditForm" class="p360-modal__body">
      @csrf
      @method('PUT')

      <div class="p360-form p360-form--grid">
        <div class="p360-field">
          <label>ID</label>
          <input id="p360_e_id" disabled>
        </div>

        <div class="p360-field">
          <label>account_id</label>
          <input id="p360_e_account" disabled>
        </div>

        <div class="p360-field">
          <label>Status</label>
          <select name="status" id="p360_e_status" required>
            @foreach(['pending','paid','failed','canceled','cancelled','refunded'] as $s)
              <option value="{{ $s }}">{{ $s }}</option>
            @endforeach
          </select>
        </div>

        <div class="p360-field">
          <label>Monto (pesos)</label>
          <input name="amount_pesos" id="p360_e_amount" required>
        </div>

        <div class="p360-field">
          <label>Moneda</label>
          <input name="currency" id="p360_e_currency" placeholder="MXN">
        </div>

        <div class="p360-field">
          <label>paid_at</label>
          <input type="datetime-local" name="paid_at" id="p360_e_paid_at">
        </div>

        <div class="p360-field">
          <label>Periodo</label>
          <input name="period" id="p360_e_period" placeholder="YYYY-MM">
        </div>

        <div class="p360-field">
          <label>Concepto</label>
          <input name="concept" id="p360_e_concept">
        </div>

        <div class="p360-field">
          <label>Método</label>
          <input name="method" id="p360_e_method">
        </div>

        <div class="p360-field">
          <label>Proveedor</label>
          <input name="provider" id="p360_e_provider">
        </div>

        <div class="p360-field p360-field--full">
          <label>Referencia</label>
          <input name="reference" id="p360_e_reference">
        </div>
      </div>

      <div class="p360-modal__foot">
        <button class="p360-btn" type="button" data-close="p360-modal-edit">Cancelar</button>
        <button class="p360-btn p360-btn--primary" type="submit">Guardar cambios</button>
      </div>
    </form>
  </div>
</div>

{{-- datasets charts --}}
<script>
  window.P360_PAYMENTS_CHART = @json($chart);
</script>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="{{ asset('assets/admin/js/payments-center.js') }}"></script>
@endsection