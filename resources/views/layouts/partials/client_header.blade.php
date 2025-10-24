{{-- Header cliente (orden: burger → search → theme → bell → chat → cart → account → profile) --}}
<header class="c-header">
  <div class="c-left">
    {{-- botón retráctil (sidebar) --}}
    <button class="c-icon c-burger" id="btnSidebar" type="button" aria-label="Abrir menú">
      <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M3 6h18v2H3zm0 5h18v2H3zm0 5h18v2H3z"/></svg>
    </button>

    {{-- logo --}}
    <a href="{{ route('cliente.home') }}" class="c-logo">
      <img src="{{ asset('images/p360-logo.svg') }}" alt="P360" height="22">
    </a>

    {{-- buscador --}}
    <form class="c-search" method="GET" action="{{ route('cliente.facturacion.index') }}" role="search">
      <span class="c-search-icon" aria-hidden="true">
        <svg width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" d="M10 2a8 8 0 105.293 14.293l4.707 4.707l-1.414 1.414l-4.707-4.707A8 8 0 1010 2m0 2a6 6 0 110 12a6 6 0 010-12"/></svg>
      </span>
      <input type="text" name="q" placeholder="Buscar en tu cuenta (CFDI, receptor, folio…)" autocomplete="off" />
    </form>
  </div>

  <div class="c-right">
    {{-- tema --}}
    <button class="c-icon" id="btnTheme" type="button" title="Cambiar tema">
      <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 3a9 9 0 0 0 0 18a9 9 0 0 0 0-18m0 2a7 7 0 0 1 0 14a7 7 0 0 1 0-14"/></svg>
    </button>

    {{-- alertas --}}
    <a class="c-icon c-badge" href="{{ route('cliente.alertas') }}" title="Alertas">
      <svg width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="M12 22a2 2 0 0 0 2-2h-4a2 2 0 0 0 2 2m6-6V11a6 6 0 1 0-12 0v5l-2 2v1h16v-1z"/></svg>
      @if(($notifCount ?? 0) > 0)<span class="c-dot">{{ $notifCount }}</span>@endif
    </a>

    {{-- chat --}}
    <a class="c-icon c-badge" href="{{ route('cliente.soporte.chat') }}" title="Soporte">
      <svg width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="M4 2h16a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H7l-5 5V4a2 2 0 0 1 2-2"/></svg>
      @if(($chatCount ?? 0) > 0)<span class="c-dot">{{ $chatCount }}</span>@endif
    </a>

    {{-- carrito --}}
    <a class="c-icon c-badge" href="{{ route('cliente.marketplace') }}" title="Marketplace">
      <svg width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="M7 18a2 2 0 1 0 0 4a2 2 0 1 0 0-4m10 0a2 2 0 1 0 0 4a2 2 0 1 0 0-4M7.2 14h9.45a2 2 0 0 0 1.94-1.55L20.5 6H6.21L5.27 2H2v2h2l3.6 12.59l-.9 1.63A2 2 0 0 0 6.6 20H20v-2H6.6l1-1.8z"/></svg>
      @if(($cartCount ?? 0) > 0)<span class="c-dot">{{ $cartCount }}</span>@endif
    </a>

    {{-- datos de cuenta --}}
    @php
      $cu = auth('web')->user();
      $razon = $cu?->cuenta?->razon_social ?? $cu?->cuenta?->nombre_fiscal ?? ($cu?->nombre ?? $cu?->email ?? '—');
      $plan  = strtoupper((string)($cu?->cuenta?->plan_actual ?? 'FREE'));
      $ini   = strtoupper(mb_substr(trim((string)($cu?->nombre ?? $razon)),0,1));
    @endphp
    <div class="c-account">
      <div class="c-account-name">{{ $razon }}</div>
      <div class="c-account-plan">{{ $plan }}</div>
    </div>

    {{-- perfil --}}
    <div class="c-profile">
      <button class="c-avatar" id="btnProfile" type="button" aria-haspopup="menu" aria-expanded="false">{{ $ini }}</button>
      <div class="c-menu" id="menuProfile" role="menu">
        <a href="{{ route('cliente.perfil') }}" role="menuitem">Perfil</a>
        <form method="POST" action="{{ route('cliente.logout') }}" role="menuitem">
          @csrf
          <button type="submit" class="c-logout">Cerrar sesión</button>
        </form>
      </div>
    </div>
  </div>
</header>
