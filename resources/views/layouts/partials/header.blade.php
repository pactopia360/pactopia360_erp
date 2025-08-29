@php
  use Illuminate\Support\Facades\Route;

  $user = auth('admin')->user();
  $userName   = $user?->name ?? $user?->nombre ?? 'Admin';
  $userEmail  = $user?->email ?? '';
  $brandUrl   = Route::has('admin.home') ? route('admin.home') : url('/');

  $logoLight  = asset('assets/admin/img/logo-pactopia360-dark.png');
  $logoDark   = asset('assets/admin/img/logo-pactopia360-white.png');

  $urlPerfil  = Route::has('admin.perfil') ? route('admin.perfil') : (Route::has('admin.profile') ? route('admin.profile') : '#');
  $urlConfig  = Route::has('admin.config.index') ? route('admin.config.index') : (Route::has('admin.configuracion.index') ? route('admin.configuracion.index') : '#');
  $logoutRoute= Route::has('admin.logout') ? route('admin.logout') : (Route::has('logout') ? route('logout') : '#');

  $searchUrl  = Route::has('admin.search') ? route('admin.search') : '#';

  $notifCountUrl = Route::has('admin.notificaciones.count') ? route('admin.notificaciones.count') : null;
  $notifListUrl  = Route::has('admin.notificaciones.list')  ? route('admin.notificaciones.list')  : (Route::has('admin.notificaciones') ? route('admin.notificaciones') : null);

  $heartbeatUrl  = Route::has('admin.ui.heartbeat') ? route('admin.ui.heartbeat') : null;
  $envName       = app()->environment();

  $unread = (int) (session('admin_unread_notifications', 0));
@endphp

<header id="topbar" class="header" role="banner" aria-label="Barra superior"
        data-search-url="{{ $searchUrl }}"
        @if($notifCountUrl) data-notif-count-url="{{ $notifCountUrl }}" @endif
        @if($notifListUrl)  data-notif-list-url="{{ $notifListUrl }}"   @endif
        @if($heartbeatUrl)  data-heartbeat-url="{{ $heartbeatUrl }}"    @endif
        data-env="{{ $envName }}">

  <div class="header-left">
    <button id="sidebarBtn" class="btn-sidebar-pro" type="button"
            aria-label="Alternar menú" aria-controls="sidebar"
            aria-pressed="false" aria-expanded="true" title="Menú">
      <span class="btn-bars" aria-hidden="true"></span>
      <span class="btn-bars" aria-hidden="true"></span>
      <span class="btn-bars" aria-hidden="true"></span>
      <span class="btn-glow" aria-hidden="true"></span>
    </button>

    <a class="brand-link" href="{{ $brandUrl }}" aria-label="Inicio">
      <img class="brand-logo brand-light" src="{{ $logoLight }}" alt="PACTOPIA 360" decoding="async" fetchpriority="high">
      <img class="brand-logo brand-dark"  src="{{ $logoDark  }}" alt="PACTOPIA 360" decoding="async">
      <span class="brand-name">PACTOPIA 360</span>
    </a>

    <span class="env-badge" title="Entorno de ejecución">
      {{ strtoupper($envName) }}
      <span id="hbDot" class="hb-dot" aria-label="Estado del servidor" title="Estado"></span>
    </span>
  </div>

  <div class="header-search">
    <form class="search-wrap" role="search" method="get"
          action="{{ $searchUrl }}"
          data-pjax-form
          onsubmit="return this.q.value.trim().length>0">
      <span class="search-icon" aria-hidden="true">🔎</span>
      <input id="globalSearch" name="q" type="search" placeholder="Buscar en el panel… (Ctrl + K o /)" autocomplete="off" aria-label="Buscar">
      <kbd class="kbd">Ctrl + K</kbd>
    </form>
  </div>

  <div class="header-right">
    <details class="notif-menu" data-menu="quick">
      <summary class="notif-btn" title="Acciones rápidas" aria-label="Acciones rápidas">⚡</summary>
      <div class="dropdown" style="min-width:260px">
        <div class="dropdown-header">Acciones rápidas</div>
        <div class="dropdown-body">
          <nav class="menu-vert">
            @if(Route::has('admin.pagos.create'))    <a href="{{ route('admin.pagos.create') }}">Nuevo pago</a> @endif
            @if(Route::has('admin.clientes.create')) <a href="{{ route('admin.clientes.create') }}">Nuevo cliente</a> @endif
            @if(Route::has('admin.reportes.index'))  <a href="{{ route('admin.reportes.index') }}">Reportes</a> @endif
            @if(Route::has('admin.home'))            <a href="{{ route('admin.home') }}">Home</a> @endif
            @if(Route::has('admin.dashboard'))       <a href="{{ route('admin.dashboard') }}">Dashboard</a> @endif
          </nav>
        </div>
      </div>
    </details>

    <button id="btnTheme" class="theme-btn" type="button" aria-label="Cambiar tema" title="Cambiar tema" aria-pressed="false">
      <span class="ico" aria-hidden="true">🌓</span>
      <span id="themeLabel" aria-live="polite">Modo claro</span>
    </button>

    <details class="notif-menu" data-menu="notifications">
      <summary class="notif-btn" title="Notificaciones" aria-label="Notificaciones">
        🔔
        <span class="badge" id="notifBadge" aria-label="{{ $unread }} sin leer" @if($unread<=0) hidden @endif>{{ $unread>99?'99+':$unread }}</span>
      </summary>
      <div class="dropdown" style="min-width:320px">
        <div class="dropdown-header">Notificaciones</div>
        <div class="dropdown-body" id="p360NotifBody" data-state="idle">
          @if($unread>0)
            <p>Tienes {{ $unread }} notificaciones pendientes.</p>
            @if(Route::has('admin.notificaciones'))
              <a class="logout-btn" href="{{ route('admin.notificaciones') }}">Ver todas</a>
            @endif
          @else
            <p class="muted">Sin notificaciones.</p>
          @endif
        </div>
      </div>
    </details>

    <button id="btnNovaBot" class="theme-btn" type="button" title="Abrir asistente" aria-label="Abrir asistente">
      🤖 <span class="sr-only">Asistente</span>
    </button>

    <details class="avatar-menu" data-menu="profile">
      <summary class="theme-btn" aria-label="Menú de usuario" style="gap:10px">
        <img class="avatar-img" src="{{ $user?->avatar_url ?? 'https://ui-avatars.com/api/?name='.urlencode($userName).'&background=0D8ABC&color=fff' }}" alt="">
        <span class="who" style="display:flex;flex-direction:column;line-height:1">
          <strong style="font-size:12px">{{ $userName }}</strong>
          <small class="muted" style="font-size:11px">Panel Administrativo</small>
        </span>
        <span aria-hidden="true">▾</span>
      </summary>
      <div class="dropdown">
        <div class="dropdown-header">{{ $userName }}</div>
        <div class="dropdown-body">
          <div class="dd-sub muted">{{ $userEmail }}</div>
          <nav class="menu-vert">
            @if($urlPerfil !== '#') <a href="{{ $urlPerfil }}">Mi perfil</a> @endif
            @if($urlConfig !== '#') <a href="{{ $urlConfig }}">Configuración</a> @endif
            <hr>
            @if($logoutRoute !== '#')
              <form method="post" action="{{ $logoutRoute }}" id="logoutForm">@csrf
                <button class="logout-btn w-100" type="submit" id="logoutBtn">Cerrar sesión</button>
              </form>
            @endif
          </nav>
        </div>
      </div>
    </details>
  </div>
</header>

<div id="sidebarBackdrop" class="sidebar-backdrop" aria-hidden="true"></div>

<style>
  :root{ --header-h:56px; }
  .header{
    position:sticky; top:0; z-index:1050;
    height:var(--header-h);
    display:flex; align-items:center; gap:12px;
    padding:8px 12px;
    background:var(--sb-bg, #fff); color:inherit;
    border-bottom:1px solid var(--sb-border, rgba(0,0,0,.08));
    backdrop-filter:saturate(180%) blur(6px);
  }
  .header-left{display:flex;align-items:center;gap:10px;min-width:240px}
  .header-right{display:flex;align-items:center;gap:10px}
  .brand-link{display:inline-flex;align-items:center;gap:8px;color:inherit;text-decoration:none;font-weight:700}
  .brand-logo{height:26px}
  .brand-logo[hidden]{display:none!important}
  .brand-name{display:none}
  @media (min-width:992px){ .brand-name{display:inline} }

  .header-search{flex:1;display:flex;justify-content:center}
  .search-wrap{display:flex;align-items:center;gap:8px;background:rgba(0,0,0,.04);border:1px solid rgba(0,0,0,.08);
    padding:6px 10px;border-radius:12px;width:100%;max-width:560px}
  html.theme-dark .search-wrap{background:rgba(255,255,255,.06);border-color:rgba(255,255,255,.08)}
  .search-wrap input{flex:1;border:0;outline:0;background:transparent}
  .kbd{font:600 10px/1 system-ui;background:rgba(0,0,0,.08);padding:2px 6px;border-radius:6px}
  html.theme-dark .kbd{background:rgba(255,255,255,.12)}

  .notif-menu{position:relative}
  .notif-menu>summary{list-style:none;cursor:pointer}
  .notif-menu>summary::-webkit-details-marker{display:none}
  .notif-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;height:38px;min-width:38px;
    padding:0 8px;border-radius:10px;border:1px solid rgba(0,0,0,.08);background:transparent}
  html.theme-dark .notif-btn{border-color:rgba(255,255,255,.12)}
  .dropdown{position:absolute;right:0;top:calc(100% + 8px);background:var(--sb-bg,#fff);border:1px solid var(--sb-border,rgba(0,0,0,.08));
    border-radius:12px;box-shadow:0 12px 30px rgba(0,0,0,.12);padding:8px;min-width:220px;z-index:100}
  .dropdown-header{font:700 12px/1 system-ui;margin:6px 4px;color:#64748b;text-transform:uppercase;letter-spacing:.04em}
  .menu-vert a,.menu-vert .linklike{display:block;padding:6px 8px;border-radius:8px;text-decoration:none;color:inherit}
  .menu-vert a:hover,.menu-vert .linklike:hover{background:rgba(0,0,0,.06)}
  html.theme-dark .menu-vert a:hover,html.theme-dark .menu-vert .linklike:hover{background:rgba(255,255,255,.08)}
  .badge{display:inline-flex;min-width:18px;height:18px;padding:0 5px;border-radius:9px;background:#ef4444;color:#fff;font:700 10px/18px system-ui}
  .avatar-img{width:24px;height:24px;border-radius:9999px;object-fit:cover}

  .theme-btn{display:inline-flex;align-items:center;gap:8px;height:38px;padding:0 10px;border-radius:10px;border:1px solid rgba(0,0,0,.08);background:transparent}
  html.theme-dark .theme-btn{border-color:rgba(255,255,255,.12)}
  .linklike{appearance:none;background:none;border:0;padding:6px 0;text-align:left;cursor:pointer;width:100%}
  .toggle{display:flex;align-items:center;gap:.5rem}
  .env-badge{display:inline-flex;align-items:center;gap:6px;font:600 11px/1 system-ui;padding:4px 8px;margin-left:10px;border-radius:9999px;background:rgba(0,0,0,.06)}
  html.theme-dark .env-badge{background:rgba(255,255,255,.08)}
  .hb-dot{width:8px;height:8px;border-radius:9999px;display:inline-block;background:#9ca3af;box-shadow:0 0 0 0 rgba(16,185,129,.0)}
  .hb-dot.ok{background:#10b981;box-shadow:0 0 0 6px rgba(16,185,129,.18)}
  .hb-dot.warn{background:#f59e0b;box-shadow:0 0 0 6px rgba(245,158,11,.18)}
  .hb-dot.fail{background:#ef4444;box-shadow:0 0 0 6px rgba(239,68,68,.18)}

  .btn-sidebar-pro{
    position:relative;display:inline-flex;flex-direction:column;justify-content:center;align-items:center;
    width:42px;height:42px;border-radius:12px;cursor:pointer;overflow:hidden;
    border:1px solid rgba(0,0,0,.1);background:linear-gradient(180deg, rgba(0,0,0,.03), transparent);
    transition: transform .12s ease, background .2s ease, border-color .2s ease;
  }
  .btn-sidebar-pro .btn-bars{width:20px;height:2px;background:currentColor;border-radius:2px;margin:2.5px 0;transition: transform .25s ease, opacity .2s ease, width .2s ease}
  .btn-sidebar-pro .btn-glow{position:absolute;inset:-30%;background:radial-gradient(120px 120px at var(--mx,50%) var(--my,50%), rgba(99,102,241,.18), transparent 60%);pointer-events:none;opacity:0;transition:opacity .25s ease}
  .btn-sidebar-pro:hover{border-color:rgba(99,102,241,.35)}
  .btn-sidebar-pro:hover .btn-glow{opacity:1}
  .btn-sidebar-pro:active{transform:scale(.96)}
  html.sidebar-collapsed .btn-sidebar-pro .btn-bars:nth-child(1){transform:translateY(4px) rotate(35deg);width:14px}
  html.sidebar-collapsed .btn-sidebar-pro .btn-bars:nth-child(2){opacity:0}
  html.sidebar-collapsed .btn-sidebar-pro .btn-bars:nth-child(3){transform:translateY(-4px) rotate(-35deg);width:14px}

  .sidebar-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:1040;display:none}
  body.sidebar-open .sidebar-backdrop{display:block}
</style>

<script>
(function(){
  'use strict';
  const html=document.documentElement, body=document.body;
  const $=(s,c=document)=>c.querySelector(s);
  const $$=(s,c=document)=>Array.from(c.querySelectorAll(s));
  const BTN = $('#sidebarBtn');

  /* ========= Fallback de P360.debug → /admin/ui/log ========= */
  (function(){
    window.P360 = window.P360 || {};
    if (!window.P360.debug) {
      const LOG_URL = "{{ Route::has('admin.ui.log') ? route('admin.ui.log') : url('/admin/ui/log') }}";
      window.P360.debug = {
        async send(tag, data){
          try{
            const body = { tag, ...(data && typeof data==='object' ? { data } : {}) };
            await fetch(LOG_URL, {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': (document.querySelector('meta[name=\"csrf-token\"]')?.content || '')
              },
              credentials: 'same-origin',
              body: JSON.stringify(body)
            });
          }catch(_){}
        }
      };
    }
  })();

  /* ========= Tema ========= */
  (function(){
    const KEY='p360-theme'; const btn=$('#btnTheme'); const label=$('#themeLabel');
    const logoL=$('.brand-logo.brand-light'); const logoD=$('.brand-logo.brand-dark');
    const reflect=(dark)=>{ try{ if(logoL) logoL.hidden=!!dark; if(logoD) logoD.hidden=!dark; }catch{} };
    const apply=(m)=>{ const d=(m==='dark'); html.classList.toggle('theme-dark',d); html.classList.toggle('theme-light',!d);
      html.setAttribute('data-theme', d?'dark':'light'); body.classList.toggle('theme-dark',d); body.classList.toggle('theme-light',!d);
      try{localStorage.setItem(KEY,m)}catch{}; if(label) label.textContent=d?'Modo claro':'Modo oscuro'; if(btn) btn.setAttribute('aria-pressed',d?'true':'false'); reflect(d); };
    const current=()=>{ try{const v=localStorage.getItem(KEY); if(v==='dark'||v==='light') return v;}catch{} return html.classList.contains('theme-dark')?'dark':'light'; };
    apply(current()); if(btn && !btn.dataset.bound){ btn.dataset.bound='1'; btn.addEventListener('click',()=>apply(current()==='dark'?'light':'dark')); }
    addEventListener('storage',e=>{ if(e.key===KEY && e.newValue) apply(e.newValue); });
  })();

  /* ========= Botón sidebar ========= */
  if (BTN && !BTN.dataset.bound){
    BTN.dataset.bound='1';
    BTN.addEventListener('mousemove', e=>{
      const r=BTN.getBoundingClientRect();
      BTN.style.setProperty('--mx', (e.clientX-r.left)+'px');
      BTN.style.setProperty('--my', (e.clientY-r.top)+'px');
    });
    BTN.addEventListener('click', ()=>{ try{ window.P360?.sidebar?.toggle(); }catch{} });
    BTN.addEventListener('dblclick', ()=>{ try{ P360.sidebar?.reset(); }catch{} });
  }

  /* ========= Atajos de búsqueda (Ctrl+K y /) ========= */
  (function(){
    const input = $('#globalSearch');
    if(!input) return;
    document.addEventListener('keydown', (e)=>{
      const isSlash = (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey);
      const isCtrlK = ((e.key === 'k' || e.key === 'K') && (e.ctrlKey || e.metaKey));
      if (isSlash || isCtrlK){
        e.preventDefault();
        input.focus();
        input.select();
      }
    });
  })();

  /* ========= Heartbeat ========= */
  (function(){
    const root=$('#topbar'); const dot=$('#hbDot'); const url=root?.dataset.heartbeatUrl;
    if(!dot || !url) return;
    async function ping(){
      try{
        const t0=performance.now();
        const res=await fetch(url,{headers:{'X-Requested-With':'XMLHttpRequest'},cache:'no-store',credentials:'same-origin'});
        const t1=performance.now(); const ms=Math.round(t1-t0);
        if(res.ok){ dot.className='hb-dot ok'; dot.title='OK · '+ms+' ms'; }
        else{ dot.className='hb-dot warn'; dot.title='Warn · '+ms+' ms (HTTP '+res.status+')'; }
      }catch(_){ dot.className='hb-dot fail'; dot.title='Sin respuesta'; }
    }
    ping(); setInterval(ping, 30000);
  })();

  /* ========= Notificaciones ========= */
  (function(){
    const root=$('#topbar'), badge=$('#notifBadge'), bodyEl=$('#p360NotifBody');
    const countUrl=root?.dataset.notifCountUrl||null, listUrl=root?.dataset.notifListUrl||null;
    const fetchJSON=async url=>{ try{
      const res=await fetch(url,{headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},credentials:'same-origin'});
      const ct=res.headers.get('content-type')||''; if(!ct.includes('application/json')) return null;
      return await res.json();
    }catch{return null;} };
    const updateBadge=async ()=>{ if(!countUrl||!badge) return;
      const data=await fetchJSON(countUrl); const n=Math.max(0, parseInt((data&&(data.count??data.unread??data.total))||0,10));
      if(n>0){ badge.innerText=n>99?'99+':String(n); badge.hidden=false; badge.setAttribute('aria-label', n+' sin leer'); }
      else{ badge.hidden=true; badge.innerText=''; badge.setAttribute('aria-label','0 sin leer'); }
    };
    const loadListOnOpen=async ()=>{ if(!listUrl||!bodyEl||bodyEl.dataset.state!=='idle') return;
      bodyEl.dataset.state='loading'; bodyEl.innerHTML='<p class="muted">Cargando…</p>';
      const data=await fetchJSON(listUrl);
      if(Array.isArray(data?.items)&&data.items.length){
        const ul=document.createElement('ul'); ul.className='notif-list';
        data.items.slice(0,10).forEach(it=>{
          const li=document.createElement('li');
          const t=(it.title||it.text||'Notificación'); const d=(it.date||it.time||'');
          li.innerHTML=`<div class="notif-title">${t}</div><div class="notif-date muted">${d}</div>`;
          ul.appendChild(li);
        });
        bodyEl.innerHTML=''; bodyEl.appendChild(ul);
        if(data.more_url){ const a=document.createElement('a'); a.href=data.more_url; a.className='logout-btn'; a.textContent='Ver todas'; bodyEl.appendChild(a); }
      }else{
        @if(Route::has('admin.notificaciones'))
          bodyEl.innerHTML = `<p class="muted">Sin notificaciones.</p><a class="logout-btn" href="{{ route('admin.notificaciones') }}">Ver todas</a>`;
        @else
          bodyEl.innerHTML = `<p class="muted">Sin notificaciones.</p>`;
        @endif
      }
    };
    $$('details[data-menu="notifications"]').forEach(d=>{
      if(!d.dataset.bound){ d.dataset.bound='1'; d.addEventListener('toggle', ()=>{ if(d.open) loadListOnOpen(); }); }
    });
    const allMenus=$$('details[data-menu]'); allMenus.forEach(d=>{
      if(!d.dataset.closeothers){ d.dataset.closeothers='1'; d.addEventListener('toggle', ()=>{ if(d.open) allMenus.forEach(x=>{ if(x!==d) x.open=false; }); }); }
    });
    if(countUrl){ updateBadge(); setInterval(updateBadge,60000); }
  })();

  /* ========= NovaBot ========= */
  (function(){
    const btn=$('#btnNovaBot'); if(!btn || btn.dataset.bound) return; btn.dataset.bound='1';
    btn.addEventListener('click', ()=>{
      try{
        if (window.NovaBot && typeof window.NovaBot.open === 'function'){ window.NovaBot.open(); }
        else { P360.toast?.info('Cargando asistente…'); window.dispatchEvent(new CustomEvent('p360:bot:open')); }
      }catch{ P360.toast?.error('No se pudo abrir el asistente'); }
    });
  })();

  /* ========= Logout + backdrop móvil ========= */
  (function(){
    const b=$('#logoutBtn'), f=$('#logoutForm');
    if(b&&f&&!b.dataset.bound){ b.dataset.bound='1';
      b.addEventListener('click', ()=>{ if(b.disabled) return false; b.disabled=true; b.textContent='Cerrando…'; setTimeout(()=>{ try{f.submit();}catch{ b.disabled=false; } },10); });
    }
    const backdrop=$('#sidebarBackdrop'); if(backdrop){ backdrop.addEventListener('click', ()=>{ try{ P360.sidebar?.openMobile(false); }catch{} }); }
  })();
})();
</script>
