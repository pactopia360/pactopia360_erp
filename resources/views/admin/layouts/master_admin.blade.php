<!DOCTYPE html>
<html lang="es">
<head>
  @include('layouts.partials.head')

  {{-- Estilos mínimos del layout (solo adaptación, sin cambiar diseño) --}}
  <style>
    :root{
      --header-h: 64px;                /* Ajusta si tu header tiene otra altura */
      --container-max: 1320px;
      --safe-bottom: env(safe-area-inset-bottom, 0px);
    }

    /* Alturas con fallback para móviles (iOS/Android) */
    html,body{height:100%}
    body{
      margin:0; min-height:100vh; min-height:100dvh;
      background:var(--page-bg,#f6f7fb); color:var(--page-fg,#0f172a);
      -webkit-tap-highlight-color: transparent;
      text-size-adjust: 100%;
      overflow-x:hidden; /* evita scroll horizontal accidental */
    }

    .app{
      display:flex; min-height:100vh; min-height:100dvh;
    }

    /* El sidebar define su propio ancho; mantenemos tu margen actual en desktop */
    .main{
      position:relative; flex:1 1 auto; min-width:0;
      min-height:calc(100vh - 0px); min-height:calc(100dvh - 0px);
      margin-left:var(--ns-w, 296px);  /* coincide con Nebula Sidebar v6 */
    }
    /* Cuando el sidebar está colapsado en desktop, el main ajusta el margen */
    html.sidebar-collapsed .main{ margin-left:var(--ns-wc, 84px) }

    /* Header pegado arriba (viene del partial) */
    .main > header{ position:sticky; top:0; z-index:1020 }

    /* Contenido: padding responsivo conservando tu densidad */
    main.content{
      padding:18px clamp(12px,2.2vw,22px) max(16px, env(safe-area-inset-right, 0px));
      padding-bottom: max(24px, 16px + var(--safe-bottom));
      min-height:calc(100vh - var(--header-h));
      min-height:calc(100dvh - var(--header-h));
    }

    /* Contenedor fluido con tope de ancho (misma estética) */
    .container{
      width:100%;
      max-width:var(--container-max);
      margin-inline:auto;
    }

    /* Asegura que imágenes/tablas no rompan el ancho en móvil */
    .container img{ max-width:100%; height:auto }
    .container .table-responsive{ width:100%; overflow:auto } /* usa esta clase donde tengas tablas anchas */

    /* Skip link accesible */
    .skip-link{
      position:absolute; left:-9999px; top:0; background:#111827; color:#fff; padding:8px 12px; border-radius:8px
    }
    .skip-link:focus{ left:8px; top:8px; outline:2px solid #6366f1 }

    /* Toasters rápidos (separados del notch con safe-area) */
    .toast-wrap{
      position:fixed; right:16px; bottom:calc(16px + var(--safe-bottom));
      z-index:2000; display:grid; gap:8px
    }
    .toast{
      background:#111827; color:#fff; border-radius:10px; padding:10px 12px; box-shadow:0 10px 30px rgba(0,0,0,.25)
    }
    html.theme-dark .toast{ background:#111 }

    /* Badge de notificaciones (si el header no lo provee, lo mostramos nosotros) */
    .header-badge{
      display:inline-grid; place-items:center; min-width:18px; height:18px; padding:0 5px;
      background:#ef4444; color:#fff; border-radius:999px; font:700 11px/1 system-ui
    }

    /* Responsive: en móvil el main ocupa todo; el sidebar entra como drawer */
    @media (max-width:1023.98px){
      .main{ margin-left:0 }
      body.sidebar-open{ overflow:hidden } /* evita scroll de fondo cuando drawer abierto */
      /* mejora de tacto en inputs/botones sin tocar estilos */
      input,button,select,textarea{ font-size:16px } /* evita zoom en iOS */
    }

    /* Foco visible sutil, sin alterar tu tema */
    :where(a,button,[tabindex]):focus-visible{
      outline:2px solid color-mix(in oklab, #6366f1 60%, transparent);
      outline-offset:2px;
    }
  </style>
  @stack('head')
</head>
<body>
  <a class="skip-link" href="#mainContent">Saltar al contenido</a>

  <div class="app" id="appRoot">
    {{-- Sidebar (Nebula v6) --}}
    @include('layouts.partials.sidebar')

    {{-- Contenedor principal --}}
    <div class="main" role="main" aria-label="Área principal de la aplicación">
      {{-- Header --}}
      @include('layouts.partials.header')

      {{-- Fallback del badge (si el header ya lo tiene con data-noti-badge, este no se muestra) --}}
      <div id="fallbackBadge" hidden style="position:absolute; right:16px; top:calc(var(--header-h) + 8px);">
        <span class="header-badge" data-noti-badge hidden>0</span>
      </div>

      {{-- Mensajes flash simples (opcional; tu theme puede tener su propio componente) --}}
      @if(session('ok') || session('error'))
        <div class="toast-wrap" id="flashToasts">
          @if(session('ok'))    <div class="toast" role="status">{{ session('ok') }}</div>@endif
          @if(session('error')) <div class="toast" role="alert">{{ session('error') }}</div>@endif
        </div>
        <script>
          setTimeout(()=>{ document.getElementById('flashToasts')?.remove(); }, 4800);
        </script>
      @endif

      {{-- Contenido --}}
      <main id="mainContent" class="content" tabindex="-1">
        <div class="container">
          @yield('content')
        </div>
      </main>
    </div>
  </div>

  {{-- NovaBot flotante --}}
  @include('layouts.partials.novabot')

  {{-- Scripts comunes + hooks por página --}}
  @include('layouts.partials.scripts')
  @stack('scripts')

  {{-- ========= UX mínima (sidebar móvil, Ctrl/Cmd+K, polling robusto) ========= --}}
  <script>
    (function(w,d){
      'use strict';

      // === Hook del botón que abre/cierra sidebar en móvil (si existe en el header) ===
      d.addEventListener('click', function(e){
        const t = e.target.closest('[data-sidebar-toggle]');
        if(!t) return;
        e.preventDefault();
        const open = !d.body.classList.contains('sidebar-open');
        if(open) d.body.classList.add('sidebar-open'); else d.body.classList.remove('sidebar-open');
      }, {passive:false});

      // Cierra el drawer al esc o al cambiar a desktop
      d.addEventListener('keydown', function(e){
        if(e.key === 'Escape' && d.body.classList.contains('sidebar-open')){
          d.body.classList.remove('sidebar-open');
        }
      });
      w.matchMedia('(min-width:1024px)').addEventListener?.('change', m=>{
        if(m.matches) d.body.classList.remove('sidebar-open');
      });

      // === Foco de búsqueda global (Ctrl/Cmd + K) -> input del sidebar (nsSearch) ===
      w.addEventListener('keydown', e=>{
        const ctrl=(e.ctrlKey||e.metaKey)&&!e.shiftKey&&!e.altKey;
        if(ctrl && e.key.toLowerCase()==='k'){
          e.preventDefault();
          const s = d.getElementById('nsSearch') || d.getElementById('sbSearch');
          if(s){ s.focus(); s.select?.(); }
        }
      });

      // === Si no existe un badge en el header, habilita el de fallback ===
      const badge = d.querySelector('[data-noti-badge]');
      if(!badge){ d.getElementById('fallbackBadge')?.removeAttribute('hidden'); }

      // === Sincroniza --header-h si el header cambia altura (evita solapamiento en móvil) ===
      const root = d.documentElement;
      const hdr  = d.querySelector('.main > header, #topbar, #p360-topbar');
      if(hdr && 'ResizeObserver' in w){
        new ResizeObserver(()=> root.style.setProperty('--header-h', Math.max(48, Math.round(hdr.getBoundingClientRect().height))+'px'))
          .observe(hdr);
      }
    })(window,document);
  </script>

  {{-- ==================== Polling robusto (silencia net::ERR_NETWORK_CHANGED) ==================== --}}
  <script>
  (function(w,d){
    'use strict';

    // Comprueba que las rutas existan antes de usar (si no existen, no hacemos polling)
    @php
      $hb = \Illuminate\Support\Facades\Route::has('admin.ui.heartbeat') ? route('admin.ui.heartbeat') : null;
      $ct = \Illuminate\Support\Facades\Route::has('admin.notificaciones.count') ? route('admin.notificaciones.count') : null;
    @endphp
    const HB_URL = {!! $hb ? json_encode($hb) : 'null' !!};
    const COUNT_URL = {!! $ct ? json_encode($ct) : 'null' !!};
    if(!HB_URL && !COUNT_URL) return;

    const badgeEl = d.querySelector('[data-noti-badge]');

    // Estado
    let visible = !d.hidden;
    let hbDelay=15000, ctDelay=20000;
    let hbTimer=null, ctTimer=null;
    let hbBackoff=1, ctBackoff=1, MAX_BACKOFF=6;

    async function safeGet(url, {timeout=8000}={}){
      const ctl = new AbortController();
      const t = setTimeout(()=>ctl.abort(new DOMException('Timeout','AbortError')), timeout);
      try{
        const res = await fetch(url, {method:'GET', credentials:'same-origin', signal: ctl.signal});
        if(!res.ok) throw new Error('HTTP '+res.status);
        try{ return await res.json(); }catch{ return null; }
      }finally{
        clearTimeout(t);
      }
    }

    async function tickHeartbeat(){
      if(!HB_URL) return;
      if (!visible || !navigator.onLine) return scheduleHB(true);
      try{
        await safeGet(HB_URL,{timeout:6000});
        hbBackoff = 1;
      }catch(e){
        // Silenciar errores de red transitorios
        hbBackoff = Math.min(hbBackoff*2, MAX_BACKOFF);
      }finally{
        scheduleHB();
      }
    }
    function scheduleHB(skip=false){
      clearTimeout(hbTimer);
      hbTimer = setTimeout(tickHeartbeat, skip ? hbDelay*hbBackoff : hbDelay);
    }

    async function tickCount(){
      if(!COUNT_URL) return;
      if (!visible || !navigator.onLine) return scheduleCT(true);
      try{
        const data = await safeGet(COUNT_URL,{timeout:6000});
        if(data && typeof data.count !== 'undefined' && badgeEl){
          const n = Number(data.count)||0;
          badgeEl.textContent = n>99 ? '99+' : n;
          badgeEl.hidden = n<=0;
        }
        ctBackoff = 1;
      }catch(e){
        ctBackoff = Math.min(ctBackoff*2, MAX_BACKOFF);
      }finally{
        scheduleCT();
      }
    }
    function scheduleCT(skip=false){
      clearTimeout(ctTimer);
      ctTimer = setTimeout(tickCount, skip ? ctDelay*ctBackoff : ctDelay);
    }

    d.addEventListener('visibilitychange', ()=>{ visible = !d.hidden; if(visible){ scheduleHB(true); scheduleCT(true); } });
    w.addEventListener('online',  ()=>{ scheduleHB(true); scheduleCT(true); });
    w.addEventListener('offline', ()=>{ clearTimeout(hbTimer); clearTimeout(ctTimer); });

    // Inicia
    scheduleHB();
    scheduleCT();
  })(window,document);
  </script>
</body>
</html>
