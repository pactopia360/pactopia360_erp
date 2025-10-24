{{-- resources/views/components/client/sidebar.blade.php (v1.0) --}}
@php
  use Illuminate\Support\Facades\Route;

  // Props opcionales
  $id        = $id        ?? 'sidebar';
  $isOpen    = (bool)($isOpen ?? false);    // abre en desktop de inicio (false = colapsado)
  $ariaLabel = $ariaLabel ?? 'Menú principal';

  // Helpers de rutas (mejor esfuerzo)
  $rtHome     = Route::has('cliente.home')                 ? route('cliente.home')                 : '#';
  $rtFact     = Route::has('cliente.facturacion.index')    ? route('cliente.facturacion.index')    : null;
  $rtFactNew  = Route::has('cliente.facturacion.nuevo')    ? route('cliente.facturacion.nuevo')    : null;
  $rtEstado   = Route::has('cliente.estado_cuenta')        ? route('cliente.estado_cuenta')        : null;
  $rtBilling  = Route::has('cliente.billing.statement')    ? route('cliente.billing.statement')    : null;
  $rtPerfil   = url('cliente/perfil');

  // Activos
  $isHome     = request()->routeIs('cliente.home');
  $isFact     = request()->routeIs('cliente.facturacion.*');
  $isEstado   = request()->routeIs('cliente.estado_cuenta');
  $isBilling  = request()->routeIs('cliente.billing.*');
  $isPerfil   = request()->is('cliente/perfil*');

  // Data-state inicial
  $dataState = $isOpen ? 'expanded' : 'collapsed';
@endphp

<aside class="sidebar {{ $isOpen ? 'open' : '' }}" id="{{ $id }}" aria-label="{{ $ariaLabel }}" data-state="{{ $dataState }}">
  <div class="sidebar-scroll">
    <nav class="nav">
      {{-- ===== Grupo: Navegación ===== --}}
      <div class="nav-group">
        <div class="nav-title">Navegación</div>

        <a href="{{ $rtHome }}" class="tip {{ $isHome ? 'active' : '' }}" data-tip="Inicio">
          {{-- Home (inline path para no depender de sprite) --}}
          <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
            <path fill="currentColor" d="M12 3.1 2 12h3v8h6v-6h2v6h6v-8h3z"/>
          </svg>
          <span>Inicio</span>
        </a>

        @if ($rtFact)
          <a href="{{ $rtFact }}" class="tip {{ $isFact ? 'active' : '' }}" data-tip="Facturación">
            <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
              <path fill="currentColor" d="M4 3h14l2 3v15H4zM6 7h8v2H6zm0 4h12v2H6zm0 4h12v2H6z"/>
            </svg>
            <span>Facturación</span>
            <span class="kbd">F</span>
          </a>
        @endif

        @if ($rtEstado)
          <a href="{{ $rtEstado }}" class="tip {{ $isEstado ? 'active' : '' }}" data-tip="Estado de cuenta">
            <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
              <path fill="currentColor" d="M3 6h18v2H3zm2 5h14v9H5z"/>
            </svg>
            <span>Estado de cuenta</span>
          </a>
        @endif

        @if ($rtBilling)
          <a href="{{ $rtBilling }}" class="tip {{ $isBilling ? 'active' : '' }}" data-tip="Pagos">
            <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
              <path fill="currentColor" d="M2 7h20v10H2zm2 2v6h16V9zM5 12h4v2H5z"/>
            </svg>
            <span>Pagos</span>
          </a>
        @endif

        <a href="{{ $rtPerfil }}" class="tip {{ $isPerfil ? 'active' : '' }}" data-tip="Perfil">
          <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
            <path fill="currentColor" d="M12 12a5 5 0 1 0-5-5a5 5 0 0 0 5 5Zm-8 9a8 8 0 1 1 16 0Z"/>
          </svg>
          <span>Perfil</span>
        </a>
      </div>

      {{-- ===== Grupo: Accesos rápidos ===== --}}
      <div class="nav-group">
        <div class="nav-title">Accesos rápidos</div>
        @if ($rtFactNew)
          <a href="{{ $rtFactNew }}" class="tip" data-tip="Nuevo CFDI">
            <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
              <path fill="currentColor" d="M11 5h2v6h6v2h-6v6h-2v-6H5v-2h6z"/>
            </svg>
            <span>Nuevo CFDI</span>
          </a>
        @endif
      </div>
    </nav>
  </div>
</aside>
