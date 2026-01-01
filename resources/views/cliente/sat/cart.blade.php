{{-- resources/views/cliente/sat/cart.blade.php --}}
@extends('layouts.cliente')

@section('title', 'Carrito SAT · Descargas CFDI')
@section('pageClass','page-sat')

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/client/css/sat-cart.css') }}">
@endpush

@php
    /** @var \Illuminate\Support\Collection|\Illuminate\Contracts\Pagination\LengthAwarePaginator|array $items */
    $items = $items ?? $cartItems ?? [];
    $items = is_array($items) ? collect($items) : $items;

    $cartCount   = (int)($cartCount   ?? $items->count());
    $cartTotalMb = (float)($cartTotalMb ?? $items->sum(fn($r) => (float)(data_get($r,'peso_mb') ?? data_get($r,'peso') ?? 0)));
    $cartTotal   = (float)($cartTotal   ?? $items->sum(fn($r) => (float)(data_get($r,'costo_mxn') ?? data_get($r,'costo') ?? 0)));

    $fmtMoney = fn($v) => '$'.number_format((float)$v, 2);
    $fmtMb    = fn($v) => number_format((float)$v, 0).' MB';

    // Ruta para botón "Seguir agregando descargas SAT"
    $rtBackToSat = route('cliente.sat.index');
@endphp

@section('content')
<div class="sat-ui sat-cart-page" id="satCartPage">

  {{-- Grid principal tipo Amazon: lista izquierda + resumen derecha --}}
  <div class="sat-cart-grid">

    {{-- ======================================
         COLUMNA IZQUIERDA: LISTA DE PAQUETES
       ======================================= --}}
    <section class="sat-cart-main">

      {{-- Aviso superior --}}
      <div class="sat-cart-banner">
        <div class="sat-cart-banner-icon">
          <span aria-hidden="true">🧾</span>
        </div>
        <div class="sat-cart-banner-body">
          <h1 class="sat-cart-banner-title">Carrito de descargas SAT</h1>
          <p class="sat-cart-banner-sub">
            Revisa tus paquetes de descargas antes de proceder al pago.
            Cada fila corresponde a un paquete de XML listo para descarga.
          </p>
        </div>
      </div>

      {{-- Lista de paquetes --}}
      <div class="sat-cart-main-card">
        <div class="sat-cart-main-head">
          <h2>Carrito de descargas</h2>
          <p>Paquetes en tu carrito: <strong>{{ $cartCount }}</strong></p>
        </div>

        @forelse($items as $idx => $row)
          @php
            $num      = $idx + 1;
            $id       = data_get($row,'id') ?? data_get($row,'download_id');
            $tipo     = strtoupper((string)(data_get($row,'tipo') ?? data_get($row,'tipo_cfdi') ?? 'EMITIDOS'));
            $tipoLbl  = $tipo === 'RECIBIDOS' ? 'RECIBIDOS' : 'EMITIDOS';

            $periodo  = trim(
                          (string)(data_get($row,'desde') ?? data_get($row,'fecha_desde') ?? data_get($row,'periodo_desde') ?? '')
                          .' — '.
                          (string)(data_get($row,'hasta') ?? data_get($row,'fecha_hasta') ?? data_get($row,'periodo_hasta') ?? '')
                       );

            $rfc      = data_get($row,'rfc') ?? data_get($row,'rfc_consulta') ?? '-';
            $alias    = data_get($row,'alias') ?? data_get($row,'nombre') ?? $rfc;

            $pesoMb   = (float)(data_get($row,'peso_mb') ?? data_get($row,'peso') ?? 0);
            $costo    = (float)(data_get($row,'costo_mxn') ?? data_get($row,'costo') ?? 0);

            $disp     = data_get($row,'expires_at') ?? data_get($row,'disponible_hasta') ?? null;
            $dispTxt  = $disp ? \Illuminate\Support\Carbon::parse($disp)->format('d/m/Y H:i') : null;

            $estatus  = strtoupper((string)(data_get($row,'status_sat') ?? data_get($row,'estatus_sat') ?? 'DONE'));
          @endphp

          <article class="sat-cart-item" data-id="{{ $id }}">
            {{-- Columna izquierda del item --}}
            <div class="sat-cart-item-left">
              <div class="sat-cart-item-index">
                <span class="sat-cart-index-circle">{{ str_pad((string)$num, 2, '0', STR_PAD_LEFT) }}</span>
              </div>

              <div class="sat-cart-item-main">
                <div class="sat-cart-item-rfc mono">{{ $rfc }}</div>
                <div class="sat-cart-item-alias">{{ $alias }}</div>
                @if($periodo)
                  <div class="sat-cart-item-periodo">{{ $periodo }}</div>
                @endif

                <dl class="sat-cart-item-meta">
                  @if($dispTxt)
                    <div class="row">
                      <dt>Disponible hasta:</dt>
                      <dd>{{ $dispTxt }}</dd>
                    </div>
                  @endif
                  <div class="row">
                    <dt>Peso estimado:</dt>
                    <dd>{{ $fmtMb($pesoMb) }}</dd>
                  </div>
                  <div class="row">
                    <dt>Costo del paquete:</dt>
                    <dd>{{ $fmtMoney($costo) }}</dd>
                  </div>
                </dl>
              </div>
            </div>

            {{-- Columna derecha del item (chips + acción) --}}
            <div class="sat-cart-item-right">
              <div class="sat-cart-item-tags">
                <span class="sat-cart-tag sat-cart-tag--tipo">
                  {{ $tipoLbl }}
                </span>
                <span class="sat-cart-tag sat-cart-tag--status">
                  {{ $estatus }}
                </span>
              </div>

              {{-- Botón quitar: manda id en ruta + hidden, método POST --}}
              <form method="POST"
                    action="{{ route('cliente.sat.cart.remove', ['id' => $id]) }}"
                    class="sat-cart-remove-form">
                @csrf
                <input type="hidden" name="download_id" value="{{ $id }}">
                <button type="submit" class="sat-cart-remove-link">
                  Quitar
                </button>
              </form>
            </div>
          </article>
        @empty
          <div class="sat-cart-empty">
            <p>No tienes paquetes en el carrito.</p>
            <a href="{{ $rtBackToSat }}" class="sat-cart-link-back">
              ← Ir al módulo de descargas SAT
            </a>
          </div>
        @endforelse

        @if($cartCount > 0)
          <div class="sat-cart-footnote">
            <a href="{{ $rtBackToSat }}" class="sat-cart-link-back">
              ← Seguir agregando descargas SAT
            </a>
          </div>
        @endif
      </div>
    </section>

@php
    $cartCount   = (int)($cartSummary['count']        ?? $cartCount   ?? 0);
    $subtotalMxn = (float)($cartSummary['subtotal']   ?? $cartSubtotal ?? $cartTotal ?? 0);
    $pesoTotalMb = (float)($cartSummary['weight_mb']  ?? $cartWeight  ?? $cartTotalMb ?? 0);

    $ivaRate      = 0.16;
    $ivaAmount    = round($subtotalMxn * $ivaRate, 2);
    $totalWithIva = $subtotalMxn + $ivaAmount;

    $fmt = fn($v) => number_format($v, 2, '.', ',');
@endphp

    <div class="sat-cart-summary">
      <div class="sat-cart-summary-card">
        <h3 class="sat-cart-summary-title">RESUMEN CARRITO</h3>
        <p class="sat-cart-summary-sub">
          Verifica que los RFC y periodos sean correctos antes de pagar.
        </p>

        <dl class="sat-cart-summary-list">
          <div class="row">
            <dt>Paquetes en el carrito</dt>
            <dd>{{ $cartCount }}</dd>
          </div>
          <div class="row">
            <dt>Subtotal</dt>
            <dd>${{ $fmt($subtotalMxn) }}</dd>
          </div>
          <div class="row">
            <dt>IVA (16&nbsp;%)</dt>
            <dd>${{ $fmt($ivaAmount) }}</dd>
          </div>
          <div class="row sat-cart-summary-total-row">
            <dt>Total a pagar</dt>
            <dd>${{ $fmt($totalWithIva) }} MXN</dd>
          </div>
          <div class="row">
            <dt>Peso total estimado</dt>
            <dd>{{ $pesoTotalMb }} MB</dd>
          </div>
        </dl>

        <div class="sat-cart-total-box">
          <div class="sat-cart-total-label">
            Total del pedido ({{ $cartCount }} paquete{{ $cartCount === 1 ? '' : 's' }})
          </div>
          <div class="sat-cart-total-amount">
            <span class="main">${{ $fmt($totalWithIva) }}</span>
            <span class="mxn">MXN</span>
          </div>
          <div class="sat-cart-total-note">
            Incluye IVA al 16&nbsp;% de ${{ $fmt($ivaAmount) }}.
          </div>
        </div>

        @if($cartCount > 0)
          <form method="POST" action="{{ route('cliente.sat.cart.checkout') }}">
            @csrf
            <button
              type="submit"
              class="sat-cart-pay-btn">
              Pagar ahora
            </button>
          </form>
        @else
          <button
            type="button"
            class="sat-cart-pay-btn"
            disabled>
            Pagar ahora
          </button>
        @endif

        <p class="sat-cart-summary-foot">
          El pago se procesa de forma segura a través de Stripe. Una vez confirmado, tus paquetes
          seguirán disponibles para descarga dentro del tiempo de vigencia configurado.
        </p>
        <p class="sat-cart-summary-safe">
          🔒 Pago protegido y cifrado
        </p>
      </div>
    </div>

  </div> {{-- /.sat-cart-grid --}}
</div>   {{-- /.sat-ui --}}

@endsection
