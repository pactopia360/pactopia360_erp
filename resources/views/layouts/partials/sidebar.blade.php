{{-- resources/views/layouts/partials/sidebar.blade.php --}}
@php
  use Illuminate\Support\Facades\Route;
  use Illuminate\Support\Facades\Gate;
  use Illuminate\Support\Str;

  /** =========================================================
   * Permisos / rutas / activos
   *  - En local/dev/testing no aplicamos Gate para no “vaciar” el menú.
   * ======================================================= */
  $hasPermAbility = Gate::has('perm') && !app()->environment(['local','development','testing']);

  $canShow = function (array $it) use ($hasPermAbility) {
    if (!$hasPermAbility) return true;
    $req = $it['perm'] ?? null; if (!$req) return true;
    return auth('admin')->user()?->can('perm', $req) ?? false;
  };

  /**
   * Retorna: ['url','active','target','rel','exists']
   * - exists: true si la ruta existe (para habilitar o marcar disabled)
   */
  $linkData = function (array $it) {
    $url = '#'; $isRoute = false; $exists = false;

    if (!empty($it['route'])) {
      $exists = Route::has($it['route']);
      if ($exists) { $url = route($it['route'], $it['params'] ?? []); $isRoute = true; }
    } elseif (!empty($it['href'])) {
      $url = (string) $it['href'];
      $exists = true;
    }

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

  /** =========================================================
   * Definición del árbol del menú
   * ======================================================= */
  $menu = [
    [
      'section' => null,
      'items' => [
        [
          'text' => 'Home', 'icon' => '🏠',
          'route' => 'admin.home',
          'active_when' => ['admin.home','admin.dashboard','admin.root'],
        ],
      ],
    ],

    // ======================= EMPRESAS =======================
    [
      'section' => 'Empresas',
      'items' => [
        [
          'text' => 'Pactopia360', 'icon' => '🏢', 'id'=>'emp-p360',
          'route' => 'admin.empresas.pactopia360.dashboard',
          'active_when' => ['admin.empresas.pactopia360.*'],
          'children' => [
            [
              'text'=>'CRM','icon'=>'📇',
              'children'=>[
                ['text'=>'Carritos',            'route'=>'admin.empresas.pactopia360.crm.carritos.index',     'perm'=>'crm.ver p360'],
                ['text'=>'Comunicaciones',      'route'=>'admin.empresas.pactopia360.crm.comunicaciones.index','perm'=>'crm.ver p360'],
                ['text'=>'Contactos',           'route'=>'admin.empresas.pactopia360.crm.contactos.index',    'perm'=>'crm.ver p360'],
                ['text'=>'Correos',             'route'=>'admin.empresas.pactopia360.crm.correos.index',      'perm'=>'crm.ver p360'],
                ['text'=>'Empresas',            'route'=>'admin.empresas.pactopia360.crm.empresas.index',     'perm'=>'crm.ver p360'],
                ['text'=>'Contratos',           'route'=>'admin.empresas.pactopia360.crm.contratos.index',    'perm'=>'crm.ver p360'],
                ['text'=>'Cotizaciones',        'route'=>'admin.empresas.pactopia360.crm.cotizaciones.index', 'perm'=>'crm.ver p360'],
                ['text'=>'Facturas',            'route'=>'admin.empresas.pactopia360.crm.facturas.index',     'perm'=>'crm.ver p360'],
                ['text'=>'Estados de cuenta',   'route'=>'admin.empresas.pactopia360.crm.estados.index',      'perm'=>'crm.ver p360'],
                ['text'=>'Negocios',            'route'=>'admin.empresas.pactopia360.crm.negocios.index',     'perm'=>'crm.ver p360'],
                ['text'=>'Notas',               'route'=>'admin.empresas.pactopia360.crm.notas.index',        'perm'=>'crm.ver p360'],
                ['text'=>'Suscripciones',       'route'=>'admin.empresas.pactopia360.crm.suscripciones.index','perm'=>'crm.ver p360'],
                ['text'=>'Robots',              'route'=>'admin.empresas.pactopia360.crm.robots.index',       'perm'=>'crm.robots p360'],
              ],
            ],
            [
              'text'=>'Cuentas por pagar','icon'=>'📉',
              'children'=>[
                ['text'=>'Gastos',      'route'=>'admin.empresas.pactopia360.cxp.gastos.index',       'perm'=>'cxp.ver p360'],
                ['text'=>'Proveedores', 'route'=>'admin.empresas.pactopia360.cxp.proveedores.index',  'perm'=>'cxp.ver p360'],
                ['text'=>'Viáticos',    'route'=>'admin.empresas.pactopia360.cxp.viaticos.index',     'perm'=>'cxp.ver p360'],
                ['text'=>'Robots',      'route'=>'admin.empresas.pactopia360.cxp.robots.index',       'perm'=>'cxp.robots p360'],
              ],
            ],
            [
              'text'=>'Cuentas por cobrar','icon'=>'📈',
              'children'=>[
                ['text'=>'Ventas',                  'route'=>'admin.empresas.pactopia360.cxc.ventas.index',         'perm'=>'cxc.ver p360'],
                ['text'=>'Facturación y cobranza', 'route'=>'admin.empresas.pactopia360.cxc.facturacion.index',    'perm'=>'cxc.ver p360'],
                ['text'=>'Robots',                 'route'=>'admin.empresas.pactopia360.cxc.robots.index',         'perm'=>'cxc.robots p360'],
              ],
            ],
            [
              'text'=>'Contabilidad','icon'=>'🧮',
              'children'=>[
                ['text'=>'Robots', 'route'=>'admin.empresas.pactopia360.conta.robots.index', 'perm'=>'conta.robots p360'],
              ],
            ],
            [
              'text'=>'Nómina','icon'=>'🧾',
              'children'=>[
                ['text'=>'Robots','route'=>'admin.empresas.pactopia360.nomina.robots.index', 'perm'=>'nomina.robots p360'],
              ],
            ],
            [
              'text'=>'Facturación','icon'=>'🧾',
              'children'=>[
                ['text'=>'Timbres / HITS',    'route'=>'admin.empresas.pactopia360.facturacion.timbres.index', 'perm'=>'factu.ver p360'],
                ['text'=>'Cancelaciones',     'route'=>'admin.empresas.pactopia360.facturacion.cancel.index',  'perm'=>'factu.ver p360'],
                ['text'=>'Resguardo 6 meses', 'route'=>'admin.empresas.pactopia360.facturacion.resguardo.index','perm'=>'factu.ver p360'],
                ['text'=>'Robots',            'route'=>'admin.empresas.pactopia360.facturacion.robots.index',   'perm'=>'factu.robots p360'],
              ],
            ],
            [
              'text'=>'Documentación','icon'=>'📂',
              'children'=>[
                ['text'=>'Gestor / Plantillas','route'=>'admin.empresas.pactopia360.docs.index', 'perm'=>'docs.ver p360'],
                ['text'=>'Robots',             'route'=>'admin.empresas.pactopia360.docs.robots.index', 'perm'=>'docs.robots p360'],
              ],
            ],
            [
              'text'=>'Punto de venta','icon'=>'🧾',
              'children'=>[
                ['text'=>'Cajas',   'route'=>'admin.empresas.pactopia360.pv.cajas.index',   'perm'=>'pv.ver p360'],
                ['text'=>'Tickets', 'route'=>'admin.empresas.pactopia360.pv.tickets.index', 'perm'=>'pv.ver p360'],
                ['text'=>'Arqueos', 'route'=>'admin.empresas.pactopia360.pv.arqueos.index', 'perm'=>'pv.ver p360'],
                ['text'=>'Robots',  'route'=>'admin.empresas.pactopia360.pv.robots.index',  'perm'=>'pv.robots p360'],
              ],
            ],
            [
              'text'=>'Bancos','icon'=>'🏦',
              'children'=>[
                ['text'=>'Cuentas',      'route'=>'admin.empresas.pactopia360.bancos.cuentas.index', 'perm'=>'bancos.ver p360'],
                ['text'=>'Conciliación', 'route'=>'admin.empresas.pactopia360.bancos.concilia.index','perm'=>'bancos.ver p360'],
                ['text'=>'Robots',       'route'=>'admin.empresas.pactopia360.bancos.robots.index',  'perm'=>'bancos.robots p360'],
              ],
            ],
          ],
        ],

        [
          'text' => 'Pactopia', 'icon' => '🏢', 'id'=>'emp-pactopia',
          'route' => 'admin.empresas.pactopia.dashboard',
          'active_when' => ['admin.empresas.pactopia.*'],
          'children' => [
            ['text'=>'CRM','icon'=>'📇','children'=>[
              ['text'=>'Contactos', 'route'=>'admin.empresas.pactopia.crm.contactos.index', 'perm'=>'crm.ver pactopia'],
              ['text'=>'Robots',    'route'=>'admin.empresas.pactopia.crm.robots.index',    'perm'=>'crm.robots pactopia'],
            ]],
          ],
        ],

        [
          'text' => 'Waretek México', 'icon' => '🏢', 'id'=>'emp-waretek-mx',
          'route' => 'admin.empresas.waretek-mx.dashboard',
          'active_when' => ['admin.empresas.waretek-mx.*'],
          'children' => [
            ['text'=>'CRM','icon'=>'📇','children'=>[
              ['text'=>'Contactos', 'route'=>'admin.empresas.waretek-mx.crm.contactos.index', 'perm'=>'crm.ver waretek'],
              ['text'=>'Robots',    'route'=>'admin.empresas.waretek-mx.crm.robots.index',    'perm'=>'crm.robots waretek'],
            ]],
          ],
        ],
      ],
    ],

    // =================== ADMINISTRACIÓN =====================
    [
      'section' => 'Administración',
      'items' => [
        [
          'text'=>'Usuarios','icon'=>'👥',
          'children'=>[
            ['text'=>'Administrativos', 'route'=>'admin.usuarios.index',  'perm'=>'usuarios_admin.ver'],
            ['text'=>'Clientes',        'route'=>'admin.clientes.index',  'perm'=>'clientes.ver'],
            ['text'=>'Robots',          'route'=>'admin.usuarios.robots.index', 'perm'=>'usuarios.robots'],
          ],
        ],
        [
          'text'=>'Soporte','icon'=>'🧰',
          'children'=>[
            ['text'=>'Tickets',        'route'=>'admin.soporte.tickets.index',   'perm'=>'soporte.ver'],
            ['text'=>'SLA / Asignación','route'=>'admin.soporte.sla.index',      'perm'=>'soporte.ver'],
            ['text'=>'Comunicaciones', 'route'=>'admin.soporte.comms.index',     'perm'=>'soporte.ver'],
            ['text'=>'Robots',         'route'=>'admin.soporte.robots.index',    'perm'=>'soporte.robots'],
          ],
        ],
      ],
    ],

    // ====================== AUDITORÍA =======================
    [
      'section' => 'Auditoría',
      'items' => [
        ['text'=>'Logs de acceso', 'icon'=>'🛡️', 'route'=>'admin.auditoria.accesos.index', 'perm'=>'auditoria.ver'],
        ['text'=>'Bitácora cambios','icon'=>'📝', 'route'=>'admin.auditoria.cambios.index', 'perm'=>'auditoria.ver'],
        ['text'=>'Integridad',      'icon'=>'🧩', 'route'=>'admin.auditoria.integridad.index', 'perm'=>'auditoria.ver'],
        ['text'=>'Robots',          'icon'=>'🤖', 'route'=>'admin.auditoria.robots.index', 'perm'=>'auditoria.robots'],
      ],
    ],

    // ==================== CONFIGURACIÓN =====================
    [
      'section' => 'Configuración',
      'items' => [
        [
          'text'=>'Plataforma','icon'=>'⚙️',
          'children'=>[
            ['text'=>'Mantenimiento',     'route'=>'admin.config.mantenimiento', 'perm'=>'config.platform'],
            ['text'=>'Optimización/Limpieza demo','route'=>'admin.config.limpieza', 'perm'=>'config.platform'],
            ['text'=>'Backups / Restore', 'route'=>'admin.config.backups', 'perm'=>'config.platform'],
            ['text'=>'Robots',            'route'=>'admin.config.robots',  'perm'=>'config.platform'],
          ],
        ],
        [
          'text'=>'Integraciones','icon'=>'🔌',
          'children'=>[
            ['text'=>'PAC(s)',            'route'=>'admin.config.int.pacs',     'perm'=>'config.integrations'],
            ['text'=>'Mailgun/MailerLite','route'=>'admin.config.int.mail',     'perm'=>'config.integrations'],
            ['text'=>'API Keys / Webhooks','route'=>'admin.config.int.api',     'perm'=>'config.integrations'],
            ['text'=>'Stripe / Conekta',  'route'=>'admin.config.int.pay',      'perm'=>'config.integrations'],
            ['text'=>'Robots',            'route'=>'admin.config.int.robots',   'perm'=>'config.integrations'],
          ],
        ],
        [
          'text'=>'Parámetros','icon'=>'🧭',
          'children'=>[
            ['text'=>'Planes & Precios',    'route'=>'admin.config.param.precios', 'perm'=>'config.params'],
            ['text'=>'Descuentos / Cupones','route'=>'admin.config.param.cupones', 'perm'=>'config.params'],
            ['text'=>'Límites por plan',    'route'=>'admin.config.param.limites', 'perm'=>'config.params'],
            ['text'=>'Robots',              'route'=>'admin.config.param.robots',  'perm'=>'config.params'],
          ],
        ],
      ],
    ],

    // ======================== PERFIL ========================
    [
      'section' => 'Perfil',
      'items' => [
        ['text'=>'Mi cuenta',        'icon'=>'👤', 'route'=>'admin.perfil',               'perm'=>null],
        ['text'=>'Editar perfil',    'icon'=>'📝', 'route'=>'admin.perfil.edit',          'perm'=>null],
        ['text'=>'Perfiles/Permisos','icon'=>'🧩', 'route'=>'admin.perfiles.index',       'perm'=>'perfiles.ver'],
        ['text'=>'Preferencias',     'icon'=>'🎛️', 'route'=>'admin.perfil.preferencias', 'perm'=>null],
        ['text'=>'Robots',           'icon'=>'🤖', 'route'=>'admin.perfil.robots',        'perm'=>null],
      ],
    ],

    // ======================= REPORTES =======================
    [
      'section' => 'Reportes',
      'items' => [
        ['text'=>'KPIs / BI',           'icon'=>'📊', 'route'=>'admin.reportes.index',       'perm'=>'reportes.ver'],
        ['text'=>'CRM',                 'icon'=>'📇', 'route'=>'admin.reportes.crm',         'perm'=>'reportes.ver'],
        ['text'=>'Cuentas por pagar',   'icon'=>'📉', 'route'=>'admin.reportes.cxp',         'perm'=>'reportes.ver'],
        ['text'=>'Cuentas por cobrar',  'icon'=>'📈', 'route'=>'admin.reportes.cxc',         'perm'=>'reportes.ver'],
        ['text'=>'Contabilidad',        'icon'=>'🧮', 'route'=>'admin.reportes.conta',       'perm'=>'reportes.ver'],
        ['text'=>'Nómina',              'icon'=>'🧾', 'route'=>'admin.reportes.nomina',      'perm'=>'reportes.ver'],
        ['text'=>'Facturación',         'icon'=>'🧾', 'route'=>'admin.reportes.facturacion', 'perm'=>'reportes.ver'],
        ['text'=>'Descargas',           'icon'=>'⬇️', 'route'=>'admin.reportes.descargas',   'perm'=>'reportes.ver'],
        ['text'=>'Robots',              'icon'=>'🤖', 'route'=>'admin.reportes.robots',      'perm'=>'reportes.ver'],
      ],
    ],
  ];

  /** =========================================================
   * Render recursivo
   * ======================================================= */
  $renderItems = function(array $items, callable $canShow, callable $linkData, $level = 0) use (&$renderItems) {
    $html = '';
    foreach ($items as $it) {
      if (!$canShow($it)) continue;

      $children = $it['children'] ?? null;
      $ld = $linkData($it);
      $hasChildren = is_array($children) && count(array_filter($children, $canShow)) > 0;

      $icon = $it['icon'] ?? '•';
      $text = $it['text'] ?? 'Item';
      $idKey = $it['id'] ?? (Str::slug(($it['section'] ?? '').'-'.$text.'-'.$level, '-'));
      $active = $ld['active'];
      $exists = $ld['exists'];

      if ($hasChildren) {
        // abrir si un hijo está activo
        $anyChildActive = false;
        foreach ($children as $ch) {
          $ldc = $linkData($ch);
          if ($ldc['active']) { $anyChildActive = true; break; }
        }
        $openAttr = $anyChildActive ? ' open' : '';
        $html .= '<details class="menu-group level-'.$level.'" data-key="'.e($idKey).'"'.$openAttr.'>';
        $html .=   '<summary class="menu-summary" title="'.e($text).'"><span class="ico" aria-hidden="true">'.e($icon).'</span><span class="label">'.e($text).'</span><span class="car">▸</span></summary>';
        $html .=   '<div class="menu-children">';
        $html .=      $renderItems($children, $canShow, $linkData, $level+1);
        $html .=   '</div>';
        $html .= '</details>';
      } else {
        $cls = 'menu-item level-'.$level.($active ? ' active' : '').(!$exists ? ' disabled' : '');
        $html .= '<a class="'.$cls.'" href="'.($exists?e($ld['url']):'#').'"'.
                  ($ld['target'] ? ' target="'.e($ld['target']).'" rel="'.e($ld['rel']).'"' : '').
                  ($active ? ' aria-current="page"' : '').
                  ' title="'.e($text).'">'.
                 '<span class="ico" aria-hidden="true">'.e($icon).'</span>'.
                 '<span class="label">'.e($text).'</span>'.
                 (!$exists ? '<span class="tag muted" title="Próximamente">PR</span>' : '').
                 '</a>';
      }
    }
    return $html;
  };
@endphp

<aside id="sidebar" role="navigation" aria-label="Navegación principal">
  <div class="sidebar-scroll">
    <nav class="menu" aria-label="Menú lateral">
      @foreach ($menu as $group)
        @php
          $section = $group['section'] ?? null;
          $items   = $group['items']   ?? [];
          // Filtrar por permisos (a nivel hoja; los groups se muestran si tienen hijos visibles)
          $visible = array_filter($items, function($it) use ($canShow){
            if (!isset($it['children'])) return $canShow($it);
            // Si es grupo con hijos, basta que alguno sea visible
            foreach (($it['children'] ?? []) as $ch) {
              if (isset($ch['children'])) {
                foreach (($ch['children'] ?? []) as $gch) { if ($canShow($gch)) return true; }
                if ($canShow($ch)) return true;
              } else if ($canShow($ch)) return true;
            }
            // si no hay hijos visibles, podría tener route propia
            return $canShow($it);
          });
          if (empty($visible)) continue;
        @endphp

        @if ($section)
          <div class="menu-section">{{ Str::upper($section) }}</div>
        @endif

        {!! $renderItems($visible, $canShow, $linkData, 0) !!}
      @endforeach
    </nav>
  </div>

  {{-- Backdrop móvil DENTRO del <aside> para que el CSS de móvil lo controle --}}
  <div id="sidebar-backdrop" class="sidebar-backdrop" aria-hidden="true"></div>
</aside>

<style>
  /* Estilos livianos (compatibles con tu CSS externo). Mantienen usable el menú si falta el CSS. */
  .menu-section{margin:14px 10px 6px;font:700 11px/1 system-ui;color:#64748b;letter-spacing:.04em}
  .menu-item,.menu-summary{display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:10px;text-decoration:none;color:inherit}
  .menu-item:hover,.menu-summary:hover{background:rgba(0,0,0,.06)} html.theme-dark .menu-item:hover,html.theme-dark .menu-summary:hover{background:rgba(255,255,255,.08)}
  .menu-item.active{background:rgba(99,102,241,.14);font-weight:600} html.theme-dark .menu-item.active{background:rgba(99,102,241,.22)}
  .menu-item.disabled{opacity:.55;cursor:not-allowed}
  .menu-group{margin:2px 4px;border-radius:10px}
  .menu-group[open]>.menu-summary .car{transform:rotate(90deg)}
  .menu-summary{list-style:none;cursor:pointer;user-select:none}
  .menu-summary::-webkit-details-marker{display:none}
  .menu-children{padding-left:8px;display:block}
  .menu .ico{width:20px;text-align:center}
  .menu .car{margin-left:auto;transition:transform .2s ease}
  .menu .tag.muted{font:700 9px/1 system-ui;background:rgba(0,0,0,.08);color:#666;padding:2px 6px;border-radius:9999px;margin-left:auto}
  html.theme-dark .menu .tag.muted{background:rgba(255,255,255,.12);color:#cbd5e1}
  .menu .level-1{padding-left:30px} .menu .level-2{padding-left:45px} .menu .level-3{padding-left:60px}
</style>

<script>
  (function(){
    'use strict';
    // Persistencia de abierto/cerrado por grupo usando localStorage
    const KEY = 'p360.sidebar.groups';
    let state = {};
    try{ state = JSON.parse(localStorage.getItem(KEY) || '{}') || {}; }catch(_){ state = {}; }

    document.querySelectorAll('.menu-group[data-key]').forEach(d=>{
      const k = d.getAttribute('data-key');
      if (!k) return;
      // restaurar estado guardado si no hay activo en hijos
      const hasOpenAttr = d.hasAttribute('open');
      if (!hasOpenAttr && (k in state)) {
        if (state[k]) d.setAttribute('open','');
        else d.removeAttribute('open');
      }
      // guardar en cambios
      if (!d.dataset.bound){
        d.dataset.bound='1';
        d.addEventListener('toggle', ()=>{
          state[k] = d.open ? 1 : 0;
          try{ localStorage.setItem(KEY, JSON.stringify(state)); }catch(_){}
        });
      }
    });

    // Cerrar drawer móvil con ESC y al navegar por PJAX (si aplica)
    document.addEventListener('keydown', e => { if (e.key === 'Escape') document.body.classList.remove('sidebar-open'); }, {passive:true});
    addEventListener('p360:pjax:before', () => document.body.classList.remove('sidebar-open'));
  })();
</script>
