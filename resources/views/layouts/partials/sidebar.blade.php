{{-- resources/views/layouts/partials/sidebar.blade.php --}}
@php
  use Illuminate\Support\Facades\Route;
  use Illuminate\Support\Facades\Gate;
  use Illuminate\Support\Str;

  // ==========================
  // Permisos (no bloquea en local/dev/testing)
  // ==========================
  $hasPermAbility = Gate::has('perm') && !app()->environment(['local','development','testing']);
  $canShow = function (array $it) use ($hasPermAbility) {
    if (!$hasPermAbility) return true;
    $req = $it['perm'] ?? null;
    if (!$req) return true;
    return auth('admin')->user()?->can('perm', $req) ?? false;
  };

  // ==========================
  // Link data (url / active / target)
  // ==========================
  $linkData = function (array $it) {
    $url = '#'; $isRoute = false; $exists = false;

    if (!empty($it['route'])) {
      $exists = Route::has($it['route']);
      if ($exists) { $url = route($it['route'], $it['params'] ?? []); $isRoute = true; }
    } elseif (!empty($it['href'])) {
      $url = (string) $it['href']; $exists = true;
    }

    // Activo por route y patrones extra
    $active = false;
    if ($isRoute && $exists) {
      $r = $it['route'];
      $active = request()->routeIs($r);
      if (!$active) {
        $root = Str::contains($r, '.') ? Str::beforeLast($r, '.') : $r;
        if ($root) $active = request()->routeIs("$root.*");
      }
    }
    foreach (($it['active_when'] ?? []) as $pat) {
      if (request()->routeIs($pat)) { $active = true; break; }
    }

    $external = !empty($it['href']) && empty($it['route']);
    $target = $external ? '_blank' : null;
    $rel    = $external ? 'noopener' : null;

    return compact('url','active','target','rel','exists');
  };

  // ==========================
  // Menú (estructura)
  // ==========================
  $menu = [
    [
      'section' => null,
      'items' => [
        ['text'=>'Home','icon'=>'🏠','route'=>'admin.home','active_when'=>['admin.home','admin.dashboard','admin.root']],
      ],
    ],
    [
      'section'=>'Empresas',
      'items'=>[
        [
          'text'=>'Pactopia360','icon'=>'🏢','id'=>'emp-p360',
          'route'=>'admin.empresas.pactopia360.dashboard',
          'active_when'=>['admin.empresas.pactopia360.*'],
          'children'=>[
            ['text'=>'CRM','icon'=>'📇','children'=>[
              ['text'=>'Carritos','route'=>'admin.empresas.pactopia360.crm.carritos.index','perm'=>'crm.ver p360'],
              ['text'=>'Comunicaciones','route'=>'admin.empresas.pactopia360.crm.comunicaciones.index','perm'=>'crm.ver p360'],
              ['text'=>'Contactos','route'=>'admin.empresas.pactopia360.crm.contactos.index','perm'=>'crm.ver p360'],
              ['text'=>'Correos','route'=>'admin.empresas.pactopia360.crm.correos.index','perm'=>'crm.ver p360'],
              ['text'=>'Empresas','route'=>'admin.empresas.pactopia360.crm.empresas.index','perm'=>'crm.ver p360'],
              ['text'=>'Contratos','route'=>'admin.empresas.pactopia360.crm.contratos.index','perm'=>'crm.ver p360'],
              ['text'=>'Cotizaciones','route'=>'admin.empresas.pactopia360.crm.cotizaciones.index','perm'=>'crm.ver p360'],
              ['text'=>'Facturas','route'=>'admin.empresas.pactopia360.crm.facturas.index','perm'=>'crm.ver p360'],
              ['text'=>'Estados de cuenta','route'=>'admin.empresas.pactopia360.crm.estados.index','perm'=>'crm.ver p360'],
              ['text'=>'Negocios','route'=>'admin.empresas.pactopia360.crm.negocios.index','perm'=>'crm.ver p360'],
              ['text'=>'Notas','route'=>'admin.empresas.pactopia360.crm.notas.index','perm'=>'crm.ver p360'],
              ['text'=>'Suscripciones','route'=>'admin.empresas.pactopia360.crm.suscripciones.index','perm'=>'crm.ver p360'],
              ['text'=>'Robots','route'=>'admin.empresas.pactopia360.crm.robots.index','perm'=>'crm.robots p360'],
            ]],
            ['text'=>'Cuentas por pagar','icon'=>'📉','children'=>[
              ['text'=>'Gastos','route'=>'admin.empresas.pactopia360.cxp.gastos.index','perm'=>'cxp.ver p360'],
              ['text'=>'Proveedores','route'=>'admin.empresas.pactopia360.cxp.proveedores.index','perm'=>'cxp.ver p360'],
              ['text'=>'Viáticos','route'=>'admin.empresas.pactopia360.cxp.viaticos.index','perm'=>'cxp.ver p360'],
              ['text'=>'Robots','route'=>'admin.empresas.pactopia360.cxp.robots.index','perm'=>'cxp.robots p360'],
            ]],
            ['text'=>'Cuentas por cobrar','icon'=>'📈','children'=>[
              ['text'=>'Ventas','route'=>'admin.empresas.pactopia360.cxc.ventas.index','perm'=>'cxc.ver p360'],
              ['text'=>'Facturación y cobranza','route'=>'admin.empresas.pactopia360.cxc.facturacion.index','perm'=>'cxc.ver p360'],
              ['text'=>'Robots','route'=>'admin.empresas.pactopia360.cxc.robots.index','perm'=>'cxc.robots p360'],
            ]],
            ['text'=>'Contabilidad','icon'=>'🧮','children'=>[
              ['text'=>'Robots','route'=>'admin.empresas.pactopia360.conta.robots.index','perm'=>'conta.robots p360'],
            ]],
            ['text'=>'Nómina','icon'=>'🧾','children'=>[
              ['text'=>'Robots','route'=>'admin.empresas.pactopia360.nomina.robots.index','perm'=>'nomina.robots p360'],
            ]],
            ['text'=>'Facturación','icon'=>'🧾','children'=>[
              ['text'=>'Timbres / HITS','route'=>'admin.empresas.pactopia360.facturacion.timbres.index','perm'=>'factu.ver p360'],
              ['text'=>'Cancelaciones','route'=>'admin.empresas.pactopia360.facturacion.cancel.index','perm'=>'factu.ver p360'],
              ['text'=>'Resguardo 6 meses','route'=>'admin.empresas.pactopia360.facturacion.resguardo.index','perm'=>'factu.ver p360'],
              ['text'=>'Robots','route'=>'admin.empresas.pactopia360.facturacion.robots.index','perm'=>'factu.robots p360'],
            ]],
            ['text'=>'Documentación','icon'=>'📂','children'=>[
              ['text'=>'Gestor / Plantillas','route'=>'admin.empresas.pactopia360.docs.index','perm'=>'docs.ver p360'],
              ['text'=>'Robots','route'=>'admin.empresas.pactopia360.docs.robots.index','perm'=>'docs.robots p360'],
            ]],
            ['text'=>'Punto de venta','icon'=>'🧾','children'=>[
              ['text'=>'Cajas','route'=>'admin.empresas.pactopia360.pv.cajas.index','perm'=>'pv.ver p360'],
              ['text'=>'Tickets','route'=>'admin.empresas.pactopia360.pv.tickets.index','perm'=>'pv.ver p360'],
              ['text'=>'Arqueos','route'=>'admin.empresas.pactopia360.pv.arqueos.index','perm'=>'pv.ver p360'],
              ['text'=>'Robots','route'=>'admin.empresas.pactopia360.pv.robots.index','perm'=>'pv.robots p360'],
            ]],
            ['text'=>'Bancos','icon'=>'🏦','children'=>[
              ['text'=>'Cuentas','route'=>'admin.empresas.pactopia360.bancos.cuentas.index','perm'=>'bancos.ver p360'],
              ['text'=>'Conciliación','route'=>'admin.empresas.pactopia360.bancos.concilia.index','perm'=>'bancos.ver p360'],
              ['text'=>'Robots','route'=>'admin.empresas.pactopia360.bancos.robots.index','perm'=>'bancos.robots p360'],
            ]],
          ],
        ],
        [
          'text'=>'Pactopia','icon'=>'🏢','id'=>'emp-pactopia',
          'route'=>'admin.empresas.pactopia.dashboard','active_when'=>['admin.empresas.pactopia.*'],
          'children'=>[['text'=>'CRM','icon'=>'📇','children'=>[
            ['text'=>'Contactos','route'=>'admin.empresas.pactopia.crm.contactos.index','perm'=>'crm.ver pactopia'],
            ['text'=>'Robots','route'=>'admin.empresas.pactopia.crm.robots.index','perm'=>'crm.robots pactopia'],
          ]]],
        ],
        [
          'text'=>'Waretek México','icon'=>'🏢','id'=>'emp-waretek-mx',
          'route'=>'admin.empresas.waretek-mx.dashboard','active_when'=>['admin.empresas.waretek-mx.*'],
          'children'=>[['text'=>'CRM','icon'=>'📇','children'=>[
            ['text'=>'Contactos','route'=>'admin.empresas.waretek-mx.crm.contactos.index','perm'=>'crm.ver waretek'],
            ['text'=>'Robots','route'=>'admin.empresas.waretek-mx.crm.robots.index','perm'=>'crm.robots waretek'],
          ]]],
        ],
      ],
    ],
    [
      'section'=>'Administración',
      'items'=>[
        ['text'=>'Usuarios','icon'=>'👥','children'=>[
          ['text'=>'Administrativos','route'=>'admin.usuarios.index','perm'=>'usuarios_admin.ver'],
          ['text'=>'Clientes','route'=>'admin.clientes.index','perm'=>'clientes.ver'],
          ['text'=>'Robots','route'=>'admin.usuarios.robots.index','perm'=>'usuarios.robots'],
        ]],
        ['text'=>'Soporte','icon'=>'🧰','children'=>[
          ['text'=>'Tickets','route'=>'admin.soporte.tickets.index','perm'=>'soporte.ver'],
          ['text'=>'SLA / Asignación','route'=>'admin.soporte.sla.index','perm'=>'soporte.ver'],
          ['text'=>'Comunicaciones','route'=>'admin.soporte.comms.index','perm'=>'soporte.ver'],
          ['text'=>'Robots','route'=>'admin.soporte.robots.index','perm'=>'soporte.robots'],
        ]],
      ],
    ],
    [
      'section'=>'Auditoría',
      'items'=>[
        ['text'=>'Logs de acceso','icon'=>'🛡️','route'=>'admin.auditoria.accesos.index','perm'=>'auditoria.ver'],
        ['text'=>'Bitácora cambios','icon'=>'📝','route'=>'admin.auditoria.cambios.index','perm'=>'auditoria.ver'],
        ['text'=>'Integridad','icon'=>'🧩','route'=>'admin.auditoria.integridad.index','perm'=>'auditoria.ver'],
        ['text'=>'Robots','icon'=>'🤖','route'=>'admin.auditoria.robots.index','perm'=>'auditoria.robots'],
      ],
    ],
    [
      'section'=>'Configuración',
      'items'=>[
        ['text'=>'Plataforma','icon'=>'⚙️','children'=>[
          ['text'=>'Mantenimiento','route'=>'admin.config.mantenimiento','perm'=>'config.platform'],
          ['text'=>'Optimización/Limpieza demo','route'=>'admin.config.limpieza','perm'=>'config.platform'],
          ['text'=>'Backups / Restore','route'=>'admin.config.backups','perm'=>'config.platform'],
          ['text'=>'Robots','route'=>'admin.config.robots','perm'=>'config.platform'],
        ]],
        ['text'=>'Integraciones','icon'=>'🔌','children'=>[
          ['text'=>'PAC(s)','route'=>'admin.config.int.pacs','perm'=>'config.integrations'],
          ['text'=>'Mailgun/MailerLite','route'=>'admin.config.int.mail','perm'=>'config.integrations'],
          ['text'=>'API Keys / Webhooks','route'=>'admin.config.int.api','perm'=>'config.integrations'],
          ['text'=>'Stripe / Conekta','route'=>'admin.config.int.pay','perm'=>'config.integrations'],
          ['text'=>'Robots','route'=>'admin.config.int.robots','perm'=>'config.integrations'],
        ]],
        ['text'=>'Parámetros','icon'=>'🧭','children'=>[
          ['text'=>'Planes & Precios','route'=>'admin.config.param.precios','perm'=>'config.params'],
          ['text'=>'Descuentos / Cupones','route'=>'admin.config.param.cupones','perm'=>'config.params'],
          ['text'=>'Límites por plan','route'=>'admin.config.param.limites','perm'=>'config.params'],
          ['text'=>'Robots','route'=>'admin.config.param.robots','perm'=>'config.params'],
        ]],
      ],
    ],
    [
      'section'=>'Perfil',
      'items'=>[
        ['text'=>'Mi cuenta','icon'=>'👤','route'=>'admin.perfil','perm'=>null],
        ['text'=>'Editar perfil','icon'=>'📝','route'=>'admin.perfil.edit','perm'=>null],
        ['text'=>'Perfiles/Permisos','icon'=>'🧩','route'=>'admin.perfiles.index','perm'=>'perfiles.ver'],
        ['text'=>'Preferencias','icon'=>'🎛️','route'=>'admin.perfil.preferencias','perm'=>null],
        ['text'=>'Robots','icon'=>'🤖','route'=>'admin.perfil.robots','perm'=>null],
      ],
    ],
    [
      'section'=>'Reportes',
      'items'=>[
        ['text'=>'KPIs / BI','icon'=>'📊','route'=>'admin.reportes.index','perm'=>'reportes.ver'],
        ['text'=>'CRM','icon'=>'📇','route'=>'admin.reportes.crm','perm'=>'reportes.ver'],
        ['text'=>'Cuentas por pagar','icon'=>'📉','route'=>'admin.reportes.cxp','perm'=>'reportes.ver'],
        ['text'=>'Cuentas por cobrar','icon'=>'📈','route'=>'admin.reportes.cxc','perm'=>'reportes.ver'],
        ['text'=>'Contabilidad','icon'=>'🧮','route'=>'admin.reportes.conta','perm'=>'reportes.ver'],
        ['text'=>'Nómina','icon'=>'🧾','route'=>'admin.reportes.nomina','perm'=>'reportes.ver'],
        ['text'=>'Facturación','icon'=>'🧾','route'=>'admin.reportes.facturacion','perm'=>'reportes.ver'],
        ['text'=>'Descargas','icon'=>'⬇️','route'=>'admin.reportes.descargas','perm'=>'reportes.ver'],
        ['text'=>'Robots','icon'=>'🤖','route'=>'admin.reportes.robots','perm'=>'reportes.ver'],
      ],
    ],
  ];
@endphp

{{-- ===== Bootstrap síncrono de P360.sidebar (API CANÓNICA) ===== --}}
<script>
(function (w, d) {
  'use strict';
  w.P360 = w.P360 || {};
  // Reemplazamos/normalizamos SIEMPRE para garantizar métodos requeridos
  const KEY_MODE = 'p360.sidebar.mode';   // desktop: 'expanded' | 'collapsed'
  const KEY_OPEN = 'p360.sidebar.open';   // móvil: '1'|'0'
  const isDesktop = () => w.matchMedia('(min-width: 1024px)').matches;

  const getMode = () => { try { return localStorage.getItem(KEY_MODE) || 'expanded'; } catch { return 'expanded'; } };
  const setMode = (m) => { try { localStorage.setItem(KEY_MODE, m); } catch {} };
  const getOpen = () => { try { return localStorage.getItem(KEY_OPEN) === '1'; } catch { return false; } };
  const setOpen = (v) => { try { localStorage.setItem(KEY_OPEN, v ? '1' : '0'); } catch {} };

  const reflectDOM = () => {
    const html = d.documentElement, body = d.body;
    // limpiamos y aplicamos según contexto
    html.classList.remove('sidebar-collapsed');
    body.classList.remove('sidebar-collapsed','sidebar-open');

    if (isDesktop()) {
      const collapsed = (getMode() === 'collapsed');
      html.classList.toggle('sidebar-collapsed', collapsed);
      body.classList.toggle('sidebar-collapsed', collapsed);
    } else {
      body.classList.toggle('sidebar-open', getOpen());
    }

    // Sincroniza ARIA del botón si existe
    const btn = d.getElementById('sidebarBtn');
    if (btn) {
      const expanded = isDesktop() ? (getMode() !== 'collapsed') : getOpen();
      btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
      btn.setAttribute('aria-pressed', expanded ? 'true' : 'false');
      btn.title = expanded ? 'Cerrar menú' : 'Abrir menú';
    }
  };

  w.P360.sidebar = {
    isCollapsed(){ return isDesktop() ? (getMode() === 'collapsed') : false; },
    setCollapsed(v){
      if (isDesktop()) setMode(v ? 'collapsed' : 'expanded');
      else setOpen(!v);
      reflectDOM();
      w.dispatchEvent(new CustomEvent('p360:sidebar:state', { detail:{ collapsed: this.isCollapsed(), mobile: !isDesktop(), open: getOpen() } }));
    },
    toggle(){
      if (isDesktop()) this.setCollapsed(!this.isCollapsed());
      else this.openMobile(!getOpen());
    },
    openMobile(open=true){ if (!isDesktop()) { setOpen(!!open); reflectDOM(); } },
    closeMobile(){ this.openMobile(false); },
    reset(){ setMode('expanded'); setOpen(false); reflectDOM(); }
  };

  // Refleja al cargar y en cambios de breakpoint
  reflectDOM();
  w.matchMedia('(min-width: 1024px)').addEventListener?.('change', reflectDOM);
  w.addEventListener('resize', reflectDOM);
})(window, document);
</script>

<aside id="sidebar" role="navigation" aria-label="Navegación principal"
       data-breakpoint="1024" data-collapsible="1" aria-hidden="false">
  <div class="nav">
    <nav class="menu" aria-label="Menú lateral">
      @foreach ($menu as $group)
        @php
          $section = $group['section'] ?? null;
          $items   = $group['items']   ?? [];
          // Filtra visibilidad sin perder grupos con nietos visibles
          $visible = array_filter($items, function($it) use ($canShow){
            if (!isset($it['children'])) return $canShow($it);
            foreach (($it['children'] ?? []) as $ch) {
              if (isset($ch['children'])) {
                foreach (($ch['children'] ?? []) as $gch) { if ($canShow($gch)) return true; }
                if ($canShow($ch)) return true;
              } else if ($canShow($ch)) return true;
            }
            return $canShow($it);
          });
          if (empty($visible)) continue;
        @endphp

        @if ($section)
          <div class="menu-section">{{ Str::upper($section) }}</div>
        @endif

        @php
          // Render recursivo inline para capturar $visible actual
          $renderItems = function(array $items, $level = 0) use (&$renderItems, $canShow, $linkData) {
            $html = '';
            foreach ($items as $it) {
              if (!$canShow($it)) continue;
              $children = $it['children'] ?? null;
              $ld = $linkData($it);
              $hasChildren = is_array($children) && count(array_filter($children, $canShow)) > 0;
              $icon = $it['icon'] ?? '•';
              $text = $it['text'] ?? 'Item';
              $idKey = $it['id'] ?? (Str::slug(($it['section'] ?? '').'-'.$text.'-'.$level, '-'));
              $active = (bool) $ld['active'];
              $exists = (bool) $ld['exists'];

              if ($hasChildren) {
                $anyChildActive = false;
                foreach ($children as $ch) {
                  $ldc = $linkData($ch);
                  if ($ldc['active']) { $anyChildActive = true; break; }
                  if (!empty($ch['children'])) {
                    foreach ($ch['children'] as $gch) { if ($linkData($gch)['active']) { $anyChildActive = true; break 2; } }
                  }
                }
                $openAttr = $anyChildActive ? ' open' : '';
                $html .= '<details class="menu-group level-'.(int)$level.'" data-key="'.e($idKey).'"'.$openAttr.'>';
                $html .=   '<summary class="menu-summary" title="'.e($text).'"><span class="ico" aria-hidden="true">'.e($icon).'</span><span class="label">'.e($text).'</span><span class="car" aria-hidden="true">▸</span></summary>';
                $html .=   '<div class="menu-children">'.$renderItems($children, $level+1).'</div>';
                $html .= '</details>';
              } else {
                $cls = 'menu-item level-'.$level.($active ? ' active' : '').(!$exists ? ' disabled' : '');
                $title = e($text);
                $url = $exists ? e($ld['url']) : '#';
                $targetRel = $ld['target'] ? ' target="'.e($ld['target']).'" rel="'.e($ld['rel']).'"' : '';
                $aria = ($active ? ' aria-current="page"' : '').(!$exists ? ' aria-disabled="true"' : '');
                $html .= '<a class="'.$cls.'" href="'.$url.'"'.$targetRel.$aria.' title="'.$title.'">'.
                         '<span class="ico" aria-hidden="true">'.e($icon).'</span>'.
                         '<span class="label">'.$title.'</span>'.
                         (!$exists ? '<span class="tag muted" title="Próximamente">PR</span>' : '').
                         '</a>';
              }
            }
            return $html;
          };
        @endphp

        {!! $renderItems($visible, 0) !!}
      @endforeach
    </nav>
  </div>

  {{-- Backdrop móvil dentro del aside --}}
  <div id="sidebar-backdrop" class="sidebar-backdrop" aria-hidden="true" hidden></div>
</aside>

{{-- ===== Fallback CSS mínimo (si no carga /assets/admin/css/sidebar.css) ===== --}}
<style>
  :root{ --header-h:56px; --nav-fg:#0f172a; }
  #sidebar{ position:fixed; left:0; top:var(--header-h); bottom:0; width:260px; background:var(--sb-bg,#fff);
            border-right:1px solid var(--sb-border,rgba(0,0,0,.08)); z-index:1045; overflow:auto; }
  html.theme-dark #sidebar{ background:rgba(17,24,39,.72); backdrop-filter:saturate(160%) blur(6px); }
  #sidebar .nav{ padding:10px; }
  #sidebar .menu-section{ margin:14px 10px 6px; font:700 11.5px/1 system-ui; color: color-mix(in oklab, var(--nav-fg) 60%, transparent); letter-spacing:.04em; }
  .menu-item,.menu-summary{ display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:12px; text-decoration:none; color:inherit; min-height:40px; }
  .menu .ico{ width:22px; min-width:22px; display:inline-flex; align-items:center; justify-content:center; font-size:18px; line-height:1; }
  .menu .label{ overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font:500 14px/1.15 system-ui; color:var(--nav-fg); }
  html.theme-dark .menu .label{ color:#e5e7eb; }
  .menu-item:hover,.menu-summary:hover{ background: color-mix(in oklab, var(--nav-fg) 8%, transparent); }
  html.theme-dark .menu-item:hover,html.theme-dark .menu-summary:hover{ background: color-mix(in oklab, #fff 10%, transparent); }
  .menu-item.active{ background: color-mix(in oklab, var(--nav-fg) 12%, transparent); font-weight:600; }
  .menu-item.disabled{ opacity:.6; cursor:not-allowed; }
  .menu-summary{ list-style:none; cursor:pointer; user-select:none; }
  .menu-summary::-webkit-details-marker{ display:none; }
  .menu-children{ padding-left:8px; display:block; }
  .menu .car{ margin-left:auto; transition:transform .2s ease; }
  .menu-group[open]>.menu-summary .car{ transform:rotate(90deg) }
  @media (min-width:1024px){
    html.sidebar-collapsed #sidebar{ width:70px; }
    html.sidebar-collapsed #sidebar .menu-children{ display:none !important; }
    html.sidebar-collapsed #sidebar .menu .car{ display:none !important; }
    html.sidebar-collapsed #sidebar .menu .label{ display:none !important; }
    html.sidebar-collapsed #sidebar .menu-item{ justify-content:center; padding:10px 6px; }
    html.sidebar-collapsed #sidebar .menu .ico{ width:26px; min-width:26px; font-size:19px; }
    #sidebar .menu-section{ transition:opacity .15s; }
    html.sidebar-collapsed #sidebar .menu-section{ opacity:0; height:0; margin:0; overflow:hidden; }
  }
  @media (max-width:1023.98px){
    #sidebar{ transform:translateX(-100%); transition: transform .22s ease; width:78vw; max-width:320px; }
    body.sidebar-open #sidebar{ transform:translateX(0); }
    #sidebar .sidebar-backdrop{ position:fixed; left:0; right:0; top:var(--header-h); bottom:0; background:rgba(0,0,0,.35); }
    body.sidebar-open #sidebar .sidebar-backdrop{ display:block; }
  }
  .menu-item:focus-visible,.menu-summary:focus-visible{ outline:2px solid #6366f1; outline-offset:2px; box-shadow:0 0 0 2px color-mix(in oklab, #6366f1 35%, transparent); }
</style>

{{-- ===== Lógica de grupos (persistencia/open por hijo activo) ===== --}}
<script>
(function(){
  'use strict';
  const d=document, w=window;
  const SB   = d.getElementById('sidebar');
  const BD   = d.getElementById('sidebar-backdrop');
  const KEYG = 'p360.sidebar.groups';   // estado de <details>

  if(!SB) return;

  // Calcular --header-h desde #topbar
  const setHeaderVar = () => {
    const topbar = d.getElementById('topbar');
    const h = topbar ? topbar.offsetHeight : 56;
    d.documentElement.style.setProperty('--header-h', h+'px');
  };
  setHeaderVar(); w.addEventListener('resize', setHeaderVar);

  // Persistencia de <details>
  let groups = {};
  try { groups = JSON.parse(localStorage.getItem(KEYG) || '{}') || {}; } catch {}
  d.querySelectorAll('.menu-group[data-key]').forEach(det=>{
    const k = det.getAttribute('data-key'); if (!k) return;
    if (!det.hasAttribute('open') && (k in groups)) det.open = !!groups[k];
    if (!det.dataset.bound){
      det.dataset.bound='1';
      det.addEventListener('toggle', ()=>{ groups[k] = det.open ? 1 : 0; try { localStorage.setItem(KEYG, JSON.stringify(groups)); } catch {} });
    }
  });

  // Cerrar menus al clic fuera
  d.addEventListener('click', (e)=>{
    d.querySelectorAll('details[open].menu-group').forEach(det=>{
      if (!det.contains(e.target)) det.removeAttribute('open');
    });
  }, {capture:true});

  // Backdrop móvil
  if (BD && !BD.dataset.bound){
    BD.dataset.bound='1';
    BD.addEventListener('click', ()=> w.P360?.sidebar?.closeMobile(), {passive:true});
  }
})();
</script>
