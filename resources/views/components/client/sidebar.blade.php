{{-- resources/views/components/client/sidebar.blade.php (v2.7 – P360 Brand Glow + A11y/UX + colapsado solo iconos) --}}
@php
  use Illuminate\Support\Facades\Route;
  use Illuminate\Support\Str;

  $id        = $id        ?? 'sidebar';
  $isOpen    = (bool)($isOpen ?? false);
  $ariaLabel = $ariaLabel ?? 'Menú principal';
  $inst      = $inst      ?? Str::lower(Str::ulid());

  // Rutas (solo si existen)
  $rtHome       = Route::has('cliente.home')                 ? route('cliente.home')                 : url('/cliente');
  $rtFact       = Route::has('cliente.facturacion.index')    ? route('cliente.facturacion.index')    : null;
  $rtFactNew    = Route::has('cliente.facturacion.nuevo')    ? route('cliente.facturacion.nuevo')    : null;
  $rtEstado     = Route::has('cliente.estado_cuenta')        ? route('cliente.estado_cuenta')        : null;
  $rtBilling    = Route::has('cliente.billing.statement')    ? route('cliente.billing.statement')    : null;
  $rtPerfil     = Route::has('cliente.perfil')               ? route('cliente.perfil')               : url('cliente/perfil');
  $rtSat        = Route::has('cliente.sat.index')            ? route('cliente.sat.index')            : null;
  $rtDescargas  = Route::has('cliente.sat.descargas.index')  ? route('cliente.sat.descargas.index')  : null;
  $rtLogout     = Route::has('cliente.logout')               ? route('cliente.logout')               : url('cliente/logout');

  // Activos
  $isHome     = request()->routeIs('cliente.home');
  $isFact     = request()->routeIs('cliente.facturacion.*');
  $isEstado   = request()->routeIs('cliente.estado_cuenta');
  $isBilling  = request()->routeIs('cliente.billing.*');
  $isPerfil   = request()->routeIs('cliente.perfil') || request()->is('cliente/perfil*');
  $isSat      = request()->routeIs('cliente.sat.*');
  $isDown     = request()->routeIs('cliente.sat.descargas.*');

  $dataState = $isOpen ? 'expanded' : 'collapsed';
@endphp

<aside class="sidebar skin-brand-rail" id="{{ $id }}" aria-label="{{ $ariaLabel }}" data-state="{{ $dataState }}">
  <div class="sidebar-head">
    <strong class="sb-title">MENÚ</strong>
    <button
      id="sbToggle"
      class="sb-toggle"
      type="button"
      aria-label="Expandir/Colapsar"
      aria-expanded="{{ $isOpen ? 'true':'false' }}"
      title="Expandir/Colapsar (Ctrl+B)"></button>
  </div>

  <div class="sidebar-scroll" role="navigation" aria-label="Secciones">
    <nav class="nav">

      {{-- ===== Inicio ===== --}}
      <div class="nav-group" aria-labelledby="nav-title-ini">
        <div class="nav-title" id="nav-title-ini"><span class="pill">Inicio</span></div>

        <a href="{{ $rtHome }}" class="tip {{ $isHome ? 'active' : '' }}" @if($isHome) aria-current="page" @endif title="Inicio">
          <svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 3.1 2 12h3v8h6v-6h2v6h6v-8h3z"/></svg>
          <span class="tx">Inicio</span>
        </a>
      </div>

      {{-- ===== Módulos ===== --}}
      <div class="nav-group" aria-labelledby="nav-title-mod">
        <div class="nav-title" id="nav-title-mod"><span class="pill">Módulos</span></div>

        @if ($rtFact)
          <a href="{{ $rtFact }}" class="tip {{ $isFact ? 'active' : '' }}" @if($isFact) aria-current="page" @endif title="Facturación">
            <svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M4 3h14l2 3v15H4zM6 7h8v2H6zm0 4h12v2H6zm0 4h12v2H6z"/></svg>
            <span class="tx">Facturación</span>
          </a>
        @endif

        @if ($rtEstado)
          <a href="{{ $rtEstado }}" class="tip {{ $isEstado ? 'active' : '' }}" @if($isEstado) aria-current="page" @endif title="Estado de cuenta">
            <svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M3 6h18v2H3zm2 5h14v9H5z"/></svg>
            <span class="tx">Estado de cuenta</span>
          </a>
        @endif

        @if ($rtBilling)
          <a href="{{ $rtBilling }}" class="tip {{ $isBilling ? 'active' : '' }}" @if($isBilling) aria-current="page" @endif title="Pagos">
            <svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M2 7h20v10H2zm2 2v6h16V9zM5 12h4v2H5z"/></svg>
            <span class="tx">Pagos</span>
          </a>
        @endif

        @if ($rtSat)
          <a href="{{ $rtSat }}" class="tip {{ $isSat && !$isDown ? 'active' : '' }}" @if($isSat && !$isDown) aria-current="page" @endif title="SAT · Descarga">
            <svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="m21 7-6 6-4-4L3 17v2h18V7zM7 13a2 2 0 1 0-.001-4.001A2 2 0 0 0 7 13z"/></svg>
            <span class="tx">SAT (Descarga)</span>
          </a>
        @endif

        @if ($rtDescargas)
          <a href="{{ $rtDescargas }}" class="tip {{ $isDown ? 'active' : '' }}" @if($isDown) aria-current="page" @endif title="Descargas">
            <svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 3v10.586l3.293-3.293l1.414 1.414L12 17.414l-4.707-4.707l1.414-1.414L12 13.586V3zM5 19h14v2H5z"/></svg>
            <span class="tx">Descargas</span>
          </a>
        @endif

        @if ($rtFactNew)
          <a href="{{ $rtFactNew }}" class="tip" title="Nuevo CFDI">
            <svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M11 5h2v6h6v2H11v6H9v-6H5v-2h4V5z"/></svg>
            <span class="tx">Nuevo CFDI</span>
          </a>
        @endif
      </div>

      {{-- ===== Configuración ===== --}}
      <div class="nav-group" aria-labelledby="nav-title-cfg">
        <div class="nav-title" id="nav-title-cfg"><span class="pill">Configuración</span></div>

        <a href="{{ $rtPerfil }}" class="tip {{ $isPerfil ? 'active' : '' }}" @if($isPerfil) aria-current="page" @endif title="Perfil">
          <svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 12a5 5 0 1 0-5-5a5 5 0 0 0 5 5Zm-8 9a8 8 0 1 1 16 0Z"/></svg>
          <span class="tx">Perfil</span>
        </a>

        <form method="POST" action="{{ $rtLogout }}" id="logoutForm-{{ $id }}-{{ $inst }}">
          @csrf
          <button type="submit" class="tip danger" title="Cerrar sesión">
            <svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M10 17v-2h4v2h-4zM4 12h10l-3-3l1.41-1.41L18.83 12l-6.42 6.41L11 17l3-3H4z"/></svg>
            <span class="tx">Cerrar sesión</span>
          </button>
        </form>
      </div>

    </nav>
  </div>
</aside>

<style>
  /* ================= BRAND P360 ================= */
  .sidebar{
    --brand: var(--p360-brand, #E11D48);
    --brand-600: var(--p360-brand-600, #BE123C);
    --brand-700: #9F1239;
    --brand-soft: color-mix(in oklab, var(--brand) 12%, transparent);
    --ink: var(--c-fg, #0f172a);
    --ink-weak: color-mix(in oklab, var(--ink) 45%, transparent);
    --card: var(--card, #fff);
    --bd: var(--bd, #e5e7eb);
    --shadow-lg: 0 10px 30px rgba(0,0,0,.15);
  }
  html.theme-dark .sidebar, html[data-theme="dark"] .sidebar{
    --ink:#e5e7eb; --ink-weak: color-mix(in oklab, #fff 55%, transparent);
    --card: color-mix(in oklab, #0b1220 86%, transparent);
    --bd: rgba(255,255,255,.12);
    --brand-soft: color-mix(in oklab, var(--brand) 22%, transparent);
  }

  /* ==== Layout base ==== */
  .sidebar{
    --w:260px; --w-mini:68px;
    background:
      radial-gradient(140px 60px at -20% 0%, color-mix(in oklab, var(--brand) 10%, transparent), transparent),
      linear-gradient(180deg, transparent, transparent);
    border-right:1px solid var(--bd);
    width:var(--w);min-width:var(--w);
    /* AJUSTE CLAVE: restamos el grosor del rail (2px por defecto) */
    top: calc(var(--header-h, 64px) - var(--p360-rail-h, 2px));
    height: calc(100dvh - var(--header-h, 64px) + var(--p360-rail-h, 2px));
    position:sticky;
    transition:width .18s ease;
    display:flex; flex-direction:column;
  }
  .sidebar[data-state="collapsed"]{width:var(--w-mini);min-width:var(--w-mini)}

  .sidebar-head{
    display:flex; align-items:center; justify-content:space-between;
    height:50px; padding:10px 12px; border-bottom:1px solid var(--bd);
    background: linear-gradient(180deg, color-mix(in oklab, var(--brand) 6%, transparent), transparent);
    position:relative;
  }
  .sidebar-head::after{
    content:''; position:absolute; left:10px; right:10px; bottom:-1px; height:1px;
    background: linear-gradient(90deg, transparent, color-mix(in oklab, var(--brand) 40%, transparent), transparent);
  }
  .sb-title{font:900 12px/1 Poppins, system-ui; letter-spacing:.08em; color:var(--ink-weak)}
  .sb-toggle{
    width:36px; height:32px; border:1px solid var(--bd); border-radius:10px; background:var(--card); cursor:pointer;
    display:flex; align-items:center; justify-content:center;
    box-shadow:0 2px 10px color-mix(in oklab, var(--brand) 12%, transparent);
  }
  /* Siempre ícono hamburguesa (≡), sin flecha en modo colapsado */
  .sb-toggle::before{ content:'≡'; font-size:18px; line-height:1; font-weight:900; transform:translateY(-1px); color:var(--ink) }

  .sidebar-scroll{height:calc(100% - 50px);overflow:auto;padding:14px}
  .nav{display:flex;flex-direction:column;gap:18px}
  .nav-group{display:grid;gap:8px}
  .nav-title{font-weight:700;font-size:12px;letter-spacing:.04em;text-transform:uppercase;color:var(--ink-weak); padding:0 6px}
  .nav-title .pill{
    display:inline-block; padding:6px 10px; border-radius:999px;
    background: linear-gradient(90deg, color-mix(in oklab, var(--brand) 16%, transparent), transparent);
    border:1px solid color-mix(in oklab, var(--brand) 18%, var(--bd));
  }

  /* ==== Ítems ==== */
  .sidebar .tip{
    display:flex !important; align-items:center !important; gap:12px !important;
    padding:12px 12px !important; border-radius:14px !important;
    color:var(--ink) !important; text-decoration:none !important; border:1px solid transparent !important;
    background: linear-gradient(180deg, color-mix(in oklab, var(--brand) 0%, transparent), transparent) !important;
    outline:0 !important; min-width:0; posición:relative; isolation:isolate;
    transition:background .18s ease, border-color .18s ease, box-shadow .18s ease, transform .12s ease;
  }
  .sidebar .tip::before{
    content:''; position:absolute; left:6px; top:9px; bottom:9px; width:3px; border-radius:6px;
    background: linear-gradient(180deg, var(--brand), var(--brand-600));
    box-shadow:0 0 0 transparent; opacity:0; transform:translateX(-4px);
    transition:opacity .18s ease, transform .18s ease, box-shadow .18s ease;
  }
  .sidebar .tip:hover{
    background: linear-gradient(180deg, color-mix(in oklab, var(--brand) 8%, transparent), transparent) !important;
    border-color: color-mix(in oklab, var(--brand) 22%, var(--bd)) !important;
    box-shadow: 0 6px 18px color-mix(in oklab, var(--brand) 12%, transparent);
    transform: translateY(-1px);
  }
  .sidebar .tip:hover::before{ opacity:1; transform:none; box-shadow:0 0 16px color-mix(in oklab, var(--brand) 40%, transparent) }

  .sidebar .tip.active{
    background:
      radial-gradient(200px 60px at 0% 0%, color-mix(in oklab, var(--brand) 18%, transparent), transparent),
      linear-gradient(180deg, color-mix(in oklab, var(--brand) 16%, transparent), transparent) !important;
    border-color: color-mix(in oklab, var(--brand) 35%, var(--bd)) !important;
    box-shadow: 0 10px 26px color-mix(in oklab, var(--brand) 18%, transparent);
    font-weight:800;
  }
  .sidebar .tip.active .ico{ color:var(--brand) }
  .sidebar .tip.active::before{ opacity:1; transform:none; box-shadow:0 0 18px color-mix(in oklab, var(--brand) 42%, transparent) }

  .sidebar .tip.danger{ color:#b91c1c }
  .sidebar .tip.danger:hover{ border-color: color-mix(in oklab, #b91c1c 30%, var(--bd)) }

  .sidebar .ico{ width:20px; height:20px; color:currentColor; flex:0 0 20px !important; display:inline-block;
    filter: drop-shadow(0 0 0 transparent); transition: color .18s ease, filter .18s ease, transform .18s ease; }
  .sidebar .tip:hover .ico{ transform: translateY(-1px); filter: drop-shadow(0 4px 10px color-mix(in oklab, var(--brand) 20%, transparent)); }

  .sidebar .tx{
    display:inline-block !important; flex:1 1 auto !important; min-width:0 !important;
    white-space:nowrap !important; overflow:hidden !important; text-overflow:ellipsis !important;
    font:600 14px/1.15 Poppins, system-ui;
  }

  /* Oculta labels y títulos SOLO cuando está colapsado en desktop */
  .sidebar:not(.is-mobile)[data-state="collapsed"] .tx{ display:none !important }
  .sidebar:not(.is-mobile)[data-state="collapsed"] .tip{ justify-content:center !important }
  .sidebar:not(.is-mobile)[data-state="collapsed"] .tip.active{ box-shadow:none }
  .sidebar:not(.is-mobile)[data-state="collapsed"] .sb-title{ display:none !important; }
  .sidebar:not(.is-mobile)[data-state="collapsed"] .nav-title{ display:none !important; }

  /* ===== Responsive (móvil) ===== */
  @media (max-width:1120px){
    .sidebar{
      position:fixed;left:0;
      /* AJUSTE CLAVE EN MÓVIL: mismo cálculo */
      top: calc(var(--header-h, 64px) - var(--p360-rail-h, 2px));
      height: calc(100dvh - var(--header-h, 64px) + var(--p360-rail-h, 2px));
      z-index:40;width:var(--w);min-width:var(--w);
      transform:translateX(-100%);transition:transform .22s ease;
      box-shadow:var(--shadow-lg);
    }
    .sidebar.open{transform:translateX(0)}
    .sidebar[data-state="collapsed"] .tx{display:inline-block !important}
    .sidebar[data-state="collapsed"] .tip{justify-content:flex-start !important}
    .sidebar[data-state="collapsed"]{width:var(--w);min-width:var(--w)}
  }

  /* ===== Overrides: Sidebar Cliente (flat + active rojo) ===== */
.sidebar{
  background:#fff; /* sin gradientes */
}
.sidebar-head{
  background:#fff;
  border-bottom:1px solid var(--bd);
}
.sidebar-head::after{ content:none; }

/* Tokens rojo (usa el rojo de marca si viene de core-ui) */
.sidebar{
  --hi: var(--brand-red, #E11D48);
  --hi-10:  color-mix(in oklab, var(--hi) 10%, #fff);
  --hi-16:  color-mix(in oklab, var(--hi) 16%, #fff);
  --hi-22:  color-mix(in oklab, var(--hi) 22%, #fff);
  --hi-30:  color-mix(in oklab, var(--hi) 30%, transparent);
  --hi-40:  color-mix(in oklab, var(--hi) 40%, transparent);
}

/* Títulos y pills planas */
.nav-title .pill{
  background:#fff;
  border:1px solid var(--bd);
}

/* Item base totalmente plano */
.sidebar .tip{
  background:#fff !important;
  border:1px solid transparent !important;
  box-shadow:none !important;
  transform:none !important;
}
.sidebar .tip::before{ /* barra izquierda solo para activo */
  content:none;
}

/* Hover sutil */
.sidebar .tip:hover{
  background: color-mix(in oklab, var(--hi) 6%, #fff) !important;
  border-color: var(--bd) !important;
}

/* ACTIVO: sin rellenos llamativos, solo contorno + barrita */
.sidebar .tip.active{
  background:#fff !important;
  border-color: var(--hi-30) !important;            /* borde rojo tenue */
  box-shadow: inset 0 0 0 2px var(--hi-16) !important; /* doble realce suave */
  font-weight:800;
  position:relative;
}
.sidebar .tip.active .ico{ color: var(--hi); }
.sidebar .tip.active::after{
  content:''; position:absolute; left:6px; top:8px; bottom:8px; width:3px; border-radius:3px;
  background: var(--hi);
}

/* ===== Separador lateral: hairline rojo (Opción F) ===== */
.sidebar.skin-brand-rail{ position:relative; }
.sidebar.skin-brand-rail::after{
  content:""; position:absolute; top:0; right:0; bottom:0; width:2px;
  background: url("/assets/client/img/ui/sidebar-brand-rail.svg") repeat-y right top;
  pointer-events:none;
}

/* Dark mode: baja un poco la fuerza para no “brillar” de más */
html.theme-dark .sidebar.skin-brand-rail::after,
html[data-theme="dark"] .sidebar.skin-brand-rail::after{
  filter: opacity(.8);
}


/* Dark mode: mantén lectura sin brillos */
html.theme-dark .sidebar,
html[data-theme="dark"] .sidebar{
  background: #0b1220;
}
html.theme-dark .sidebar .tip,
html[data-theme="dark"] .sidebar .tip{
  background: transparent !important;
}
html.theme-dark .sidebar .tip:hover,
html[data-theme="dark"] .sidebar .tip:hover{
  background: color-mix(in oklab, #fff 6%, transparent) !important;
}
html.theme-dark .sidebar .tip.active,
html[data-theme="dark"] .sidebar .tip.active{
  border-color: color-mix(in oklab, var(--hi) 40%, transparent) !important;
  box-shadow: inset 0 0 0 2px color-mix(in oklab, var(--hi) 24%, transparent) !important;
}

</style>

<script>
(function(){
  const sb   = document.getElementById(@json($id));
  if(!sb) return;
  const KEY = 'p360.client.sidebar.state';
  const mql = window.matchMedia('(max-width: 1120px)');

  function setState(state, persist = true){
    sb.setAttribute('data-state', state);
    const btn = sb.querySelector('#sbToggle');
    if(btn) btn.setAttribute('aria-expanded', state === 'expanded' ? 'true' : 'false');
    if(persist && !mql.matches){
      try{ localStorage.setItem(KEY, state); }catch(e){}
    }
  }
  function applyMobileClass(){
    if(mql.matches){
      sb.classList.add('is-mobile');
      // En móvil mostramos expandido para evitar labels ocultos por persistencia
      setState('expanded', false);
    }else{
      sb.classList.remove('is-mobile');
      try {
        const saved = localStorage.getItem(KEY);
        if(saved === 'collapsed' || saved === 'expanded'){
          sb.setAttribute('data-state', saved);
          const btn = sb.querySelector('#sbToggle');
          if(btn) btn.setAttribute('aria-expanded', saved === 'expanded' ? 'true' : 'false');
        }
      } catch(e){}
    }
  }

  // Init
  applyMobileClass();

  // Toggle click
  const btn  = sb.querySelector('#sbToggle');
  btn?.addEventListener('click', ()=>{
    const cur = sb.getAttribute('data-state') || 'expanded';
    setState(cur === 'collapsed' ? 'expanded' : 'collapsed');
  });

  // Hotkey: Ctrl+B
  window.addEventListener('keydown', (e)=>{
    if ((e.ctrlKey || e.metaKey) && (e.key.toLowerCase() === 'b')) {
      e.preventDefault();
      const cur = sb.getAttribute('data-state') || 'expanded';
      setState(cur === 'collapsed' ? 'expanded' : 'collapsed');
    }
  }, {passive:false});

  // Viewport changes
  mql.addEventListener?.('change', applyMobileClass);

  // Gesto desde borde (opcional)
  let touchX = null;
  window.addEventListener('touchstart', (e)=>{ touchX = e.touches?.[0]?.clientX ?? null; }, {passive:true});
  window.addEventListener('touchend', (e)=>{
    if(touchX !== null && touchX < 20){
      sb.classList.add('open');
      setTimeout(()=> sb.classList.remove('open'), 320);
    }
    touchX = null;
  }, {passive:true});
})();
</script>
