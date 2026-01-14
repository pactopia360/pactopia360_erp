{{-- C:\wamp64\www\pactopia360_erp\resources\views\layouts\partials\sidebar.blade.php --}}
{{-- resources/views/layouts/partials/sidebar.blade.php — Nebula Sidebar v6.3 (Synced widths + Perfect content offset) --}}

@php
  use Illuminate\Support\Facades\Route;
  use Illuminate\Support\Str;

  /** ================== OPCIONES ==================
   *  ⚠️ IMPORTANTE: estos anchos DEBEN coincidir con el layout admin.blade.php
   *  (vars: --sidebar-w / --sidebar-w-collapsed)
   */
  $FAVORITES_ENABLED   = true;
  $RECENTS_ENABLED     = true;

  // ✅ Sync con el layout (lo que corregimos): 260 / 72
  $COLLAPSED_WIDTH_PX  = 72;   // ancho colapsado
  $EXPANDED_WIDTH_PX   = 260;  // ancho expandido
  $MOBILE_MAX_WVW      = 80;   // % ancho en móvil

  /** ================== RESOLVER ROBUSTO ================== */
  function p360_try_url(string $name, array $params = []) {
      try { return route($name, $params); }
      catch (\Throwable $e) {
          try { if (Route::has($name)) return route($name, $params); }
          catch (\Throwable $e2) {}
      }
      return '#';
  }

  function p360_link(array $it) {
      $url='#'; $exists=true; $isRoute=false;
      if (!empty($it['route'])) {
          $isRoute = true;
          $url = p360_try_url($it['route'], $it['params'] ?? []);
          $exists = $url !== '#';
      } elseif (!empty($it['href'])) {
          $url = (string)$it['href'];
      }

      $active=false;
      if ($isRoute && $exists) {
          $r = $it['route'];
          $active = request()->routeIs($r) ||
                    (Str::contains($r,'.') && request()->routeIs(Str::beforeLast($r,'.').'.*'));
      }
      foreach (($it['active_when'] ?? []) as $pat) { if (request()->routeIs($pat)) { $active=true; break; } }

      $external = !empty($it['href']) && empty($it['route']);

      return (object)[
        'url'=>$url, 'active'=>$active, 'exists'=>$exists || $external,
        'target'=>$external?'_blank':null, 'rel'=>$external?'noopener':null
      ];
  }

  /** ================== MENÚ CURADO ================== */
  $menu = [
    [
      'section' => null,
      'items'   => [
        ['text'=>'Home','icon'=>'🏠','route'=>'admin.home','active_when'=>['admin.dashboard','admin.root']],
      ],
    ],
    [
      'section'=>'Empresas',
      'items'=>[
        [
          'text'=>'Pactopia360','icon'=>'🏢','id'=>'emp-p360','route'=>'admin.empresas.pactopia360.dashboard','active_when'=>['admin.empresas.pactopia360.*'],
          'children'=>[
            ['text'=>'CRM','icon'=>'📇','children'=>[
              ['text'=>'Carritos','route'=>'admin.empresas.pactopia360.crm.carritos.index'],
              ['text'=>'Comunicaciones','route'=>'admin.empresas.pactopia360.crm.comunicaciones.index'],
              ['text'=>'Contactos','route'=>'admin.empresas.pactopia360.crm.contactos.index'],
              ['text'=>'Correos','route'=>'admin.empresas.pactopia360.crm.correos.index'],
              ['text'=>'Empresas','route'=>'admin.empresas.pactopia360.crm.empresas.index'],
              ['text'=>'Contratos','route'=>'admin.empresas.pactopia360.crm.contratos.index'],
              ['text'=>'Cotizaciones','route'=>'admin.empresas.pactopia360.crm.cotizaciones.index'],
              ['text'=>'Facturas','route'=>'admin.empresas.pactopia360.crm.facturas.index'],
              ['text'=>'Estados de cuenta','route'=>'admin.empresas.pactopia360.crm.estados.index'],
              ['text'=>'Negocios','route'=>'admin.empresas.pactopia360.crm.negocios.index'],
              ['text'=>'Notas','route'=>'admin.empresas.pactopia360.crm.notas.index'],
              ['text'=>'Suscripciones','route'=>'admin.empresas.pactopia360.crm.suscripciones.index'],
              ['text'=>'Robots','route'=>'admin.empresas.pactopia360.crm.robots.index'],
            ]],
            ['text'=>'Cuentas por pagar','icon'=>'📉','children'=>[
              ['text'=>'Gastos','route'=>'admin.empresas.pactopia360.cxp.gastos.index'],
              ['text'=>'Proveedores','route'=>'admin.empresas.pactopia360.cxp.proveedores.index'],
              ['text'=>'Viáticos','route'=>'admin.empresas.pactopia360.cxp.viaticos.index'],
              ['text'=>'Robots','route'=>'admin.empresas.pactopia360.cxp.robots.index'],
            ]],
            ['text'=>'Cuentas por cobrar','icon'=>'📈','children'=>[
              ['text'=>'Ventas','route'=>'admin.empresas.pactopia360.cxc.ventas.index'],
              ['text'=>'Facturación y cobranza','route'=>'admin.empresas.pactopia360.cxc.facturacion.index'],
              ['text'=>'Robots','route'=>'admin.empresas.pactopia360.cxc.robots.index'],
            ]],
            ['text'=>'Contabilidad','icon'=>'🧮','children'=>[
              ['text'=>'Robots','route'=>'admin.empresas.pactopia360.conta.robots.index'],
            ]],
            ['text'=>'Nómina','icon'=>'🧾','children'=>[
              ['text'=>'Robots','route'=>'admin.empresas.pactopia360.nomina.robots.index'],
            ]],
            ['text'=>'Facturación','icon'=>'🧾','children'=>[
              ['text'=>'Timbres / HITS','route'=>'admin.empresas.pactopia360.facturacion.timbres.index'],
              ['text'=>'Cancelaciones','route'=>'admin.empresas.pactopia360.facturacion.cancel.index'],
              ['text'=>'Resguardo 6 meses','route'=>'admin.empresas.pactopia360.facturacion.resguardo.index'],
              ['text'=>'Robots','route'=>'admin.empresas.pactopia360.facturacion.robots.index'],
            ]],
            ['text'=>'Documentación','icon'=>'📂','children'=>[
              ['text'=>'Gestor / Plantillas','route'=>'admin.empresas.pactopia360.docs.index'],
              ['text'=>'Robots','route'=>'admin.empresas.pactopia360.docs.robots.index'],
            ]],
            ['text'=>'Punto de venta','icon'=>'🧾','children'=>[
              ['text'=>'Cajas','route'=>'admin.empresas.pactopia360.pv.cajas.index'],
              ['text'=>'Tickets','route'=>'admin.empresas.pactopia360.pv.tickets.index'],
              ['text'=>'Arqueos','route'=>'admin.empresas.pactopia360.pv.arqueos.index'],
              ['text'=>'Robots','route'=>'admin.empresas.pactopia360.pv.robots.index'],
            ]],
            ['text'=>'Bancos','icon'=>'🏦','children'=>[
              ['text'=>'Cuentas','route'=>'admin.empresas.pactopia360.bancos.cuentas.index'],
              ['text'=>'Conciliación','route'=>'admin.empresas.pactopia360.bancos.concilia.index'],
              ['text'=>'Robots','route'=>'admin.empresas.pactopia360.bancos.robots.index'],
            ]],
          ],
        ],
        [
          'text'=>'Pactopia','icon'=>'🏢','id'=>'emp-pactopia','route'=>'admin.empresas.pactopia.dashboard','active_when'=>['admin.empresas.pactopia.*'],
          'children'=>[['text'=>'CRM','icon'=>'📇','children'=>[
            ['text'=>'Contactos','route'=>'admin.empresas.pactopia.crm.contactos.index'],
            ['text'=>'Robots','route'=>'admin.empresas.pactopia.crm.robots.index'],
          ]]],
        ],
        [
          'text'=>'Waretek México','icon'=>'🏢','id'=>'emp-waretek-mx','route'=>'admin.empresas.waretek-mx.dashboard','active_when'=>['admin.empresas.waretek-mx.*'],
          'children'=>[['text'=>'CRM','icon'=>'📇','children'=>[
            ['text'=>'Contactos','route'=>'admin.empresas.waretek-mx.crm.contactos.index'],
            ['text'=>'Robots','route'=>'admin.empresas.waretek-mx.crm.robots.index'],
          ]]],
        ],
      ],
    ],
    [
      'section'=>'Administración',
      'items'=>[
        ['text'=>'Usuarios','icon'=>'👥','children'=>[
          // ✅ rutas reales
          ['text'=>'Administrativos','route'=>'admin.usuarios.administrativos.index','active_when'=>['admin.usuarios.administrativos.*']],
          ['text'=>'Clientes','route'=>'admin.usuarios.clientes.index','active_when'=>['admin.usuarios.clientes.*']],
          // ⚠️ este no lo vimos en tu route:list; lo dejo como placeholder seguro
          ['text'=>'Robots','route'=>'#'],
        ]],

        [
          'text'=>'Billing SaaS','icon'=>'💳','id'=>'billing-saas',
          'active_when'=>['admin.billing.*','admin.config.param.stripe_prices.*','admin.config.param.licencias.*'],
          'children'=>[
            ['text'=>'Cuentas (Licencias)','icon'=>'👤','route'=>'admin.billing.accounts.index','active_when'=>['admin.billing.accounts.*']],
            ['text'=>'Estados de cuenta','icon'=>'🧾','route'=>'admin.billing.statements.index','active_when'=>['admin.billing.statements.*']],
            ['text'=>'Pagos','icon'=>'💰','route'=>'admin.billing.payments.index','active_when'=>['admin.billing.payments.*']],
            ['text'=>'Solicitudes de factura','icon'=>'🧷','route'=>'admin.billing.invoices.requests.index','active_when'=>['admin.billing.invoices.*']],
            ['text'=>'Precios Stripe (catálogo)','icon'=>'🏷️','route'=>'admin.config.param.stripe_prices.index','active_when'=>['admin.config.param.stripe_prices.*']],
            ['text'=>'Licencias por cuenta (meta)','icon'=>'🧩','route'=>'admin.config.param.licencias.index','active_when'=>['admin.config.param.licencias.*']],
          ],
        ],

        ['text'=>'Soporte','icon'=>'🧰','children'=>[
          ['text'=>'Tickets','route'=>'admin.soporte.tickets.index'],
          ['text'=>'SLA / Asignación','route'=>'admin.soporte.sla.index'],
          ['text'=>'Comunicaciones','route'=>'admin.soporte.comms.index'],
          ['text'=>'Robots','route'=>'admin.soporte.robots.index'],
        ]],
      ],
    ],

    [
      'section'=>'Auditoría',
      'items'=>[
        ['text'=>'Logs de acceso','icon'=>'🛡️','route'=>'admin.auditoria.accesos.index'],
        ['text'=>'Bitácora cambios','icon'=>'📝','route'=>'admin.auditoria.cambios.index'],
        ['text'=>'Integridad','icon'=>'🧩','route'=>'admin.auditoria.integridad.index'],
        ['text'=>'Robots','icon'=>'🤖','route'=>'admin.auditoria.robots.index'],
      ],
    ],
    [
      'section'=>'Configuración',
      'items'=>[
        ['text'=>'Plataforma','icon'=>'⚙️','children'=>[
          ['text'=>'Mantenimiento','route'=>'admin.config.mantenimiento'],
          ['text'=>'Optimización/Limpieza demo','route'=>'admin.config.limpieza'],
          ['text'=>'Backups / Restore','route'=>'admin.config.backups'],
          ['text'=>'Robots','route'=>'admin.config.robots'],
        ]],
        ['text'=>'Integraciones','icon'=>'🔌','children'=>[
          ['text'=>'PAC(s)','route'=>'admin.config.int.pacs'],
          ['text'=>'Mailgun/MailerLite','route'=>'admin.config.int.mail'],
          ['text'=>'API Keys / Webhooks','route'=>'admin.config.int.api'],
          ['text'=>'Stripe / Conekta','route'=>'admin.config.int.pay'],
          ['text'=>'Robots','route'=>'admin.config.int.robots'],
        ]],
        ['text'=>'Parámetros','icon'=>'🧭','children'=>[
          ['text'=>'Planes & Precios','route'=>'admin.config.param.precios'],
          ['text'=>'Descuentos / Cupones','route'=>'admin.config.param.cupones'],
          ['text'=>'Límites por plan','route'=>'admin.config.param.limites'],
          ['text'=>'Robots','route'=>'admin.config.param.robots'],
        ]],
      ],
    ],
    [
      'section'=>'Perfil',
      'items'=>[
        ['text'=>'Mi cuenta','icon'=>'👤','route'=>'admin.perfil'],
        ['text'=>'Editar perfil','icon'=>'📝','route'=>'admin.perfil.edit'],
        ['text'=>'Perfiles/Permisos','icon'=>'🧩','route'=>'admin.perfiles.index'],
        ['text'=>'Preferencias','icon'=>'🎛️','route'=>'admin.perfil.preferencias'],
        ['text'=>'Robots','icon'=>'🤖','route'=>'admin.perfil.robots'],
      ],
    ],
    [
      'section'=>'Reportes',
      'items'=>[
        ['text'=>'KPIs / BI','icon'=>'📊','route'=>'admin.reportes.index'],
        ['text'=>'CRM','icon'=>'📇','route'=>'admin.reportes.crm'],
        ['text'=>'Cuentas por pagar','icon'=>'📉','route'=>'admin.reportes.cxp'],
        ['text'=>'Cuentas por cobrar','icon'=>'📈','route'=>'admin.reportes.cxc'],
        ['text'=>'Contabilidad','icon'=>'🧮','route'=>'admin.reportes.conta'],
        ['text'=>'Nómina','icon'=>'🧾','route'=>'admin.reportes.nomina'],
        ['text'=>'Facturación','icon'=>'🧾','route'=>'admin.reportes.facturacion'],
        ['text'=>'Descargas','icon'=>'⬇️','route'=>'admin.reportes.descargas'],
        ['text'=>'Robots','icon'=>'🤖','route'=>'admin.reportes.robots'],
      ],
    ],
  ];

  /** ================== AUTO-EXPLORER (todos los admin.*) ================== */
  $autoTree = [];
  try {
      $routes = Route::getRoutes();
      foreach ($routes as $r) {
          $name = $r->getName();
          if (!$name || !Str::startsWith($name,'admin.')) continue;

          $methods = $r->methods();
          if (!in_array('GET', $methods) && !in_array('HEAD', $methods)) continue;

          $parts = explode('.', $name);
          $node  =& $autoTree;
          foreach ($parts as $i => $part) {
              $key = $part;
              if (!isset($node[$key])) $node[$key] = ['__children'=>[], '__route'=>null, '__label'=>Str::headline(str_replace('-', ' ', $part))];
              if ($i === count($parts) - 1) {
                  $node[$key]['__route'] = $name;
              }
              $node =& $node[$key]['__children'];
          }
      }
  } catch (\Throwable $e) {}
@endphp

<aside id="nebula-sidebar" role="navigation" aria-label="Navegación principal">
  <div class="ns-wrap">
    <div class="ns-tools">
      <div class="ns-tabs" role="tablist" aria-label="Cambiar vista">
        <button class="ns-tab active" data-tab="curated" aria-selected="true" role="tab">Módulos</button>
        <button class="ns-tab" data-tab="auto" role="tab" aria-selected="false">Explorar</button>
      </div>
      <div class="ns-row">
        <input id="nsSearch" class="ns-input" type="search" placeholder="Buscar… (Ctrl/Cmd+K)" aria-label="Buscar en el menú" enterkeyhint="search">
        @if($FAVORITES_ENABLED)
          <button id="nsFavsToggle" class="ns-chip" type="button" title="Ver solo favoritos" aria-pressed="false">★</button>
        @endif
        <button id="nsToggle" class="ns-chip" type="button" title="Expandir/Colapsar" data-sidebar-toggle>⇔</button>
      </div>
      <div class="ns-actions">
        <button id="nsExpandAll"   class="ns-btn" type="button">Expandir todo</button>
        <button id="nsCollapseAll" class="ns-btn" type="button">Colapsar todo</button>
      </div>
    </div>

    <nav id="nsFavs" class="ns-favs" aria-label="Favoritos" hidden></nav>

    <div class="ns-scroll">
      <nav id="nsMenuCurated" class="ns-menu" aria-label="Menú (curado)" data-tab="curated">
        @foreach ($menu as $group)
          @php $section = $group['section'] ?? null; $items = $group['items'] ?? []; @endphp
          @if($section) <div class="ns-section">{{ Str::upper($section) }}</div> @endif

          @php
            $renderCur = function(array $items, $level=0) use (&$renderCur) {
              $html = '';
              foreach ($items as $it) {
                $text = $it['text'] ?? 'Item';
                $icon = $it['icon'] ?? '•';
                $children = $it['children'] ?? null;

                $ld = p360_link($it);

                if ($children && count($children)) {
                  $idKey = $it['id'] ?? Str::slug('grp-'.$text.'-'.$level, '-');

                  $anyActive = $ld->active;
                  if(!$anyActive){
                    foreach ($children as $c1) {
                      $ldc = p360_link($c1);
                      if ($ldc?->active) { $anyActive = true; break; }
                      foreach (($c1['children'] ?? []) as $c2) { if ((p360_link($c2)?->active) ?? false) { $anyActive = true; break 2; } }
                    }
                  }

                  $open = $anyActive ? ' open' : '';
                  $sumCls = 'ns-summary level-'.$level.($anyActive ? ' is-active' : '');

                  $html .= '<details class="ns-group level-'.$level.'" data-key="'.e($idKey).'"'.$open.' data-txt="'.e(Str::lower($text)).'">';
                  $html .=   '<summary class="'.$sumCls.'" aria-expanded="'.($anyActive?'true':'false').'">'.
                             '<span class="ico" aria-hidden="true">'.$icon.'</span>'.
                             '<span class="txt">'.$text.'</span>'.
                             '<span class="car" aria-hidden="true">▸</span>'.
                             '</summary>';
                  $html .=   '<div class="ns-children">'.$renderCur($children, $level+1).'</div>';
                  $html .= '</details>';
                } else {
                  $cls  = 'ns-link level-'.$level.($ld->active?' active':'');
                  $aria = $ld->active ? ' aria-current="page"' : '';
                  $fav  = '<button class="fav" type="button" aria-label="Favorito" title="Agregar a favoritos">☆</button>';

                  $html .= '<div class="ns-item level-'.$level.'">'.
                           '<a class="'.$cls.'" href="'.e($ld->url).'"'.($ld->target?' target="'.$ld->target.'" rel="'.$ld->rel.'"':'').' data-txt="'.e(Str::lower($text)).'" data-title="'.e($text).'"'.$aria.'>'.
                           '<span class="ico" aria-hidden="true">'.$icon.'</span>'.
                           '<span class="txt">'.$text.'</span>'.
                           '</a>'.
                           ($ld->target ? '' : $fav).
                           '</div>';
                }
              }
              return $html;
            };
          @endphp

          {!! $renderCur($items, 0) !!}
        @endforeach
      </nav>

      <nav id="nsMenuAuto" class="ns-menu" aria-label="Menú (auto)" data-tab="auto" hidden>
        <div class="ns-section">TODOS LOS MÓDULOS (routes admin.*)</div>
        @php
          $renderAuto = function ($tree, $prefix = '', $level = 0) use (&$renderAuto) {
            $html = '';
            ksort($tree);
            foreach ($tree as $key => $data) {
              if (!is_array($data)) continue;
              $label = $data['__label'] ?? Str::headline($key);
              $route = $data['__route'] ?? null;
              $children = $data['__children'] ?? [];
              $txtKey = Str::lower($label);

              if ($children) {
                $idKey = Str::slug(($prefix? $prefix.'.':'').$key, '-');
                $html .= '<details class="ns-group level-'.$level.'" data-key="'.e($idKey).'" data-txt="'.e($txtKey).'">';
                $html .=   '<summary class="ns-summary level-'.$level.'" aria-expanded="false">'.
                           '<span class="ico" aria-hidden="true">📁</span>'.
                           '<span class="txt">'.$label.'</span>'.
                           '<span class="car" aria-hidden="true">▸</span>'.
                           '</summary>';
                $html .=   '<div class="ns-children">'.$renderAuto($children, ($prefix? $prefix.'.':'').$key, $level+1).'</div>';
                $html .= '</details>';
              } else {
                $url = $route ? p360_try_url($route) : '#';
                $cls = 'ns-link level-'.$level;
                $fav = '<button class="fav" type="button" aria-label="Favorito" title="Agregar a favoritos">☆</button>';

                $html .= '<div class="ns-item level-'.$level.'">'.
                         '<a class="'.$cls.'" href="'.e($url).'" data-txt="'.e($txtKey).'" data-title="'.e($label).'">'.
                         '<span class="ico" aria-hidden="true">🔗</span>'.
                         '<span class="txt">'.$label.'</span>'.
                         '</a>'.
                         $fav.
                         '</div>';
              }
            }
            return $html;
          };
        @endphp

        {!! $renderAuto($autoTree, 'admin', 0) !!}
      </nav>
    </div>
  </div>

  <div id="ns-backdrop" class="ns-backdrop" aria-hidden="true" hidden></div>
</aside>

<style>
  :root{
    /* ✅ Sincronía con el layout (admin.blade.php) */
    --sidebar-w: {{ (int)$EXPANDED_WIDTH_PX }}px;
    --sidebar-w-collapsed: {{ (int)$COLLAPSED_WIDTH_PX }}px;

    /* Nebula widths consumen lo global */
    --ns-w: var(--sidebar-w);
    --ns-wc: var(--sidebar-w-collapsed);

    /* superficie */
    --ns-fg: var(--ink,#0f172a);
    --ns-bg: var(--card,#ffffff);
    --ns-bd: color-mix(in oklab, var(--ns-fg) 10%, transparent);
    --ns-mu: color-mix(in oklab, var(--ns-fg) 55%, transparent);

    /* interacción */
    --ns-hover: color-mix(in oklab, var(--ns-fg) 6%, transparent);
    --ns-focus: 0 0 0 3px color-mix(in oklab, #6366f1 28%, transparent);

    /* activo */
    --ns-accent: var(--brand-red, #E11D48);
    --ns-active-bg: color-mix(in oklab, var(--ns-accent) 12%, transparent);
    --ns-active-br: color-mix(in oklab, var(--ns-accent) 22%, transparent);

    /* layout */
    --ns-radius: 14px;
    --ns-pad-x: 12px;
    --ns-row-h: 44px;

    --safe-bottom: env(safe-area-inset-bottom, 0px);
  }

  html.theme-dark{
    --ns-fg:#e5e7eb;
    --ns-bg: rgba(17,24,39,.72);
    --ns-bd: rgba(255,255,255,.12);
    --ns-mu: rgba(255,255,255,.55);
    --ns-hover: color-mix(in oklab, #fff 10%, transparent);
    --ns-active-bg: color-mix(in oklab, var(--ns-accent) 20%, transparent);
    --ns-active-br: color-mix(in oklab, var(--ns-accent) 30%, transparent);
  }

  #nebula-sidebar{
    position:fixed;
    left:0;
    top: calc(var(--header-h) - var(--p360-rail-h, 2px));
    bottom:0;
    width:var(--ns-w);
    background:var(--ns-bg);
    color:var(--ns-fg);
    border-right:1px solid var(--ns-bd);
    z-index:1045;
    overflow:auto;
    overscroll-behavior:contain;
    -webkit-overflow-scrolling: touch;
    contain: layout paint style;
  }

  .ns-wrap{min-height:100%; display:flex; flex-direction:column}
  .ns-tools{
    position:sticky; top:0; z-index:5;
    background:inherit;
    padding:10px;
    border-bottom:1px solid var(--ns-bd);
    backdrop-filter:saturate(140%) blur(6px);
  }

  .ns-tabs{display:flex; gap:6px; margin-bottom:8px}
  .ns-tab{
    border:1px solid var(--ns-bd);
    background:transparent;
    padding:8px 12px;
    border-radius:12px;
    cursor:pointer;
    font-weight:850;
    color:inherit;
    min-height:36px;
  }
  .ns-tab.active{ background: color-mix(in oklab, var(--ns-fg) 6%, transparent); }

  .ns-row{display:flex; align-items:center; gap:8px; flex-wrap:nowrap}
  .ns-input{
    flex:1 1 auto;
    border:1px solid var(--ns-bd);
    border-radius:14px;
    padding:10px 12px;
    background: color-mix(in oklab, #fff 92%, transparent);
    color:inherit;
    min-width:0;
  }
  html.theme-dark .ns-input{ background: color-mix(in oklab, #0b1220 86%, transparent); }
  .ns-input:focus{ outline:none; box-shadow: var(--ns-focus); }

  .ns-chip{
    border:1px dashed var(--ns-bd);
    background:transparent;
    border-radius:12px;
    padding:9px 12px;
    cursor:pointer;
    font-weight:850;
    color:inherit;
    min-height:36px;
  }

  .ns-actions{display:flex; gap:6px; flex-wrap:wrap; margin-top:8px}
  .ns-btn{
    border:1px solid var(--ns-bd);
    border-radius:10px;
    padding:9px 12px;
    background:transparent;
    cursor:pointer;
    color:inherit;
    font-weight:750;
    min-height:36px;
  }
  .ns-btn:focus, .ns-chip:focus, .ns-tab:focus{ outline:none; box-shadow: var(--ns-focus); }

  .ns-scroll{flex:1 1 auto; overflow:auto; padding:10px 10px calc(18px + var(--safe-bottom));}
  .ns-menu{min-height:100%}

  .ns-section{
    margin:14px 10px 8px;
    font:900 11px/1 system-ui;
    color:var(--ns-mu);
    letter-spacing:.10em;
  }

  .ns-item{display:flex; align-items:center; gap:6px; padding:2px}

  .ns-link, .ns-summary{
    display:flex;
    align-items:center;
    gap:12px;
    text-decoration:none;
    color:inherit;
    min-height:var(--ns-row-h);
    padding:8px var(--ns-pad-x);
    border-radius:var(--ns-radius);
    flex:1 1 auto;
    position:relative;
  }

  .ico{
    width:24px;
    min-width:24px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    font-size:18px;
    line-height:1;
    opacity:.95;
  }

  .txt{
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
    font:650 14px/1.1 system-ui;
    letter-spacing:.01em;
  }

  .ns-link:hover, .ns-summary:hover{ background:var(--ns-hover); }

  .car{
    margin-left:auto;
    opacity:.75;
    transition:transform .18s ease, opacity .18s ease;
    font-weight:900;
  }
  .ns-group[open] .car{ transform:rotate(90deg); opacity:1; }

  .level-1{ padding-left: calc(var(--ns-pad-x) + 10px) !important; }
  .level-2{ padding-left: calc(var(--ns-pad-x) + 22px) !important; }
  .level-3{ padding-left: calc(var(--ns-pad-x) + 34px) !important; }
  .level-4{ padding-left: calc(var(--ns-pad-x) + 46px) !important; }

  .ns-children{
    margin:4px 0 6px;
    padding-left:10px;
    position:relative;
  }
  .ns-children::before{
    content:'';
    position:absolute;
    left:18px;
    top:6px;
    bottom:10px;
    width:1px;
    background: color-mix(in oklab, var(--ns-fg) 14%, transparent);
    opacity:.8;
  }
  html.theme-dark .ns-children::before{
    background: color-mix(in oklab, #fff 18%, transparent);
  }

  .ns-link.active{
    background: var(--ns-active-bg);
    border: 1px solid var(--ns-active-br);
    font-weight:900;
  }
  .ns-link.active::before{
    content:'';
    position:absolute;
    left:8px; top:10px; bottom:10px;
    width:3px;
    border-radius:3px;
    background: var(--ns-accent);
  }

  .ns-summary.is-active{
    background: color-mix(in oklab, var(--ns-accent) 8%, transparent);
    border: 1px solid color-mix(in oklab, var(--ns-accent) 14%, transparent);
  }
  .ns-summary.is-active::before{
    content:'';
    position:absolute;
    left:8px; top:10px; bottom:10px;
    width:3px;
    border-radius:3px;
    background: color-mix(in oklab, var(--ns-accent) 70%, transparent);
  }

  .fav{
    border:0;
    background:transparent;
    cursor:pointer;
    font-size:17px;
    line-height:1;
    padding:2px 6px;
    opacity:.75;
  }
  .fav:hover{opacity:1}
  .fav.active{color:#f59e0b}

  .ns-group{margin:2px 0}
  .ns-summary{list-style:none; cursor:pointer; user-select:none}
  .ns-summary::-webkit-details-marker{display:none}

  #nsFavs[hidden]{display:none !important}
  #nsFavs .ns-section{margin-top:10px}

  @media (min-width:1024px){
    html.sidebar-collapsed #nebula-sidebar{ width:var(--ns-wc) }
    html.sidebar-collapsed #nebula-sidebar .ns-tools,
    html.sidebar-collapsed #nebula-sidebar .ns-section,
    html.sidebar-collapsed #nebula-sidebar .ns-children{ display:none !important }
    html.sidebar-collapsed #nebula-sidebar .txt,
    html.sidebar-collapsed #nebula-sidebar .car,
    html.sidebar-collapsed #nebula-sidebar .fav{ display:none !important }
    html.sidebar-collapsed #nebula-sidebar .ns-link,
    html.sidebar-collapsed #nebula-sidebar .ns-summary{ justify-content:center; padding-inline:10px }
    html.sidebar-collapsed #nebula-sidebar .ico{ width:26px; min-width:26px; font-size:19px }
  }

  @media (max-width:1023.98px){
    #nebula-sidebar{
      transform:translateX(-100%);
      transition: transform .22s ease;
      width:{{ (int)$MOBILE_MAX_WVW }}vw;
      max-width:340px;
    }
    body.sidebar-open #nebula-sidebar{ transform:translateX(0) }
    .ns-backdrop{
      position:fixed;
      left:0; right:0;
      top:var(--header-h);
      bottom:0;
      background:rgba(0,0,0,.35);
    }
    body.sidebar-open .ns-backdrop{ display:block }
    .ns-input, button, .ns-btn, .ns-chip{ font-size:16px }
  }
</style>

<script>
(function(w,d){
  'use strict';
  w.P360 = w.P360 || {};
  const SB = d.getElementById('nebula-sidebar');
  const M1 = d.getElementById('nsMenuCurated');
  const M2 = d.getElementById('nsMenuAuto');
  if(!SB || !M1 || !M2) return;

  const KEY_MODE   = 'p360.sidebar.mode';
  const KEY_OPEN   = 'p360.sidebar.open';
  const KEY_GROUPS = 'p360.sidebar.groups.v6.3';
  const KEY_PINS   = 'p360.sidebar.pins.v6.3';
  const KEY_TAB    = 'p360.sidebar.tab.v6.3';

  const isDesktop = ()=> w.matchMedia('(min-width:1024px)').matches;
  const getMode = ()=> { try{return localStorage.getItem(KEY_MODE)||'expanded'}catch{return'expanded'} };
  const setMode = v => { try{localStorage.setItem(KEY_MODE,v)}catch{} };
  const getOpen = ()=> { try{return localStorage.getItem(KEY_OPEN)==='1'}catch{return false} };
  const setOpen = v => { try{localStorage.setItem(KEY_OPEN, v?'1':'0')}catch{} };

  function syncOffset(){
    const html = d.documentElement;
    const collapsed = html.classList.contains('sidebar-collapsed') && isDesktop();
    const cs = getComputedStyle(html);
    const expanded = (cs.getPropertyValue('--sidebar-w') || '{{ (int)$EXPANDED_WIDTH_PX }}px').trim();
    const colw     = (cs.getPropertyValue('--sidebar-w-collapsed') || '{{ (int)$COLLAPSED_WIDTH_PX }}px').trim();
    html.style.setProperty('--sidebar-offset', collapsed ? colw : expanded);
  }

  function reflect(){
    const html=d.documentElement, body=d.body;

    html.classList.remove('sidebar-collapsed');
    body.classList.remove('sidebar-collapsed','sidebar-open');

    if(isDesktop()){
      const col=(getMode()==='collapsed');
      body.classList.toggle('sidebar-collapsed', col);
      html.classList.toggle('sidebar-collapsed', col);
    } else {
      body.classList.toggle('sidebar-open', getOpen());
    }

    syncOffset();
  }

  w.P360.sidebar = {
    isCollapsed(){ return isDesktop() ? (getMode()==='collapsed') : false; },
    setCollapsed(v){ if(isDesktop()) setMode(v?'collapsed':'expanded'); else setOpen(!v); reflect(); },
    toggle(){ if(isDesktop()) this.setCollapsed(!this.isCollapsed()); else this.openMobile(!getOpen()); },
    openMobile(flag=true){ if(!isDesktop()){ setOpen(!!flag); reflect(); } },
    closeMobile(){ this.openMobile(false); },
    reset(){ setMode('expanded'); setOpen(false); reflect(); }
  };

  reflect();
  w.matchMedia('(min-width:1024px)').addEventListener?.('change', reflect);
  w.addEventListener('resize', reflect);

  // Tabs
  const tabs = d.querySelectorAll('.ns-tab');
  let currentTab = (function(){ try{return localStorage.getItem(KEY_TAB)||'curated'}catch{return'curated'} })();
  function showTab(tab){
    currentTab = tab; try{ localStorage.setItem(KEY_TAB, tab); }catch(_){}
    tabs.forEach(t=> t.classList.toggle('active', t.dataset.tab===tab));
    tabs.forEach(t=> t.setAttribute('aria-selected', String(t.dataset.tab===tab)));
    M1.hidden = tab!=='curated'; M2.hidden = tab!=='auto';
  }
  tabs.forEach(t=> t.addEventListener('click', ()=> showTab(t.dataset.tab)));
  showTab(currentTab);

  // Persistencia de <details>
  const menus = [M1,M2];
  let groups={}; try{groups=JSON.parse(localStorage.getItem(KEY_GROUPS)||'{}')||{}}catch{ groups={} }
  menus.forEach(menu=>{
    menu.querySelectorAll('.ns-group[data-key]').forEach(det=>{
      const k=(menu.dataset.tab||'curated')+':'+(det.getAttribute('data-key')||'');
      if(!det.hasAttribute('open') && (k in groups)) det.open = !!groups[k];
      const sum = det.querySelector('.ns-summary');
      sum?.setAttribute('aria-expanded', String(det.open));
      det.addEventListener('toggle', ()=>{
        groups[k]=det.open?1:0;
        sum?.setAttribute('aria-expanded', String(det.open));
        try{localStorage.setItem(KEY_GROUPS, JSON.stringify(groups))}catch{}
      });
    });
  });

  // Búsqueda + favoritos
  const search = d.getElementById('nsSearch');
  const favsToggle = d.getElementById('nsFavsToggle');
  const favWrap = d.getElementById('nsFavs');
  let onlyFavs = false;

  function getPins(){ try{return JSON.parse(localStorage.getItem(KEY_PINS)||'[]')||[]}catch{return[]} }
  function setPins(arr){ try{localStorage.setItem(KEY_PINS, JSON.stringify(arr))}catch{} }

  function renderPins(){
    const pins=getPins(); favWrap.innerHTML=''; favWrap.hidden = !pins.length;
    if(!pins.length) return;
    const head = d.createElement('div'); head.className='ns-section'; head.textContent='FAVORITOS'; favWrap.appendChild(head);
    pins.forEach(p=>{
      const a=d.createElement('a'); a.className='ns-link level-0'; a.href=p.url; a.setAttribute('data-title', p.t||p.url);
      a.innerHTML = '<span class="ico" aria-hidden="true">★</span><span class="txt">'+(p.t||p.url)+'</span>';
      favWrap.appendChild(a);
    });
  }
  renderPins();

  function applyFilter(q){
    const term=(q||'').trim().toLowerCase();
    const pinUrls = new Set(getPins().map(p=>p.url));
    const activeMenu = (currentTab==='auto') ? M2 : M1;

    activeMenu.querySelectorAll('.ns-item, .ns-group, .ns-section').forEach(el=>{
      if(el.classList.contains('ns-item')){
        const a = el.querySelector('.ns-link');
        const txt = (a?.getAttribute('data-txt')||'');
        const url = a?.getAttribute('href')||'';
        const vis = (!term || txt.includes(term)) && (!onlyFavs || pinUrls.has(url));
        el.style.display = vis ? '' : 'none';
      } else if(el.classList.contains('ns-group')){
        const any = Array.from(el.querySelectorAll(':scope .ns-item')).some(li=> li.style.display!=='none');
        el.style.display = any ? '' : 'none';
        if(term && any) el.open = true;
      }
    });

    Array.from(activeMenu.querySelectorAll('.ns-section')).forEach(sec=>{
      let sib = sec.nextElementSibling, ok=false;
      while(sib && !sib.classList.contains('ns-section')){
        if(sib.style.display!=='none'){ ok=true; break; }
        sib=sib.nextElementSibling;
      }
      sec.style.display = ok ? '' : 'none';
    });
  }

  search?.addEventListener('input', e=> applyFilter(e.target.value));
  w.addEventListener('keydown', e=>{
    const ctrl=(e.ctrlKey||e.metaKey)&&!e.shiftKey&&!e.altKey;
    if(ctrl && e.key.toLowerCase()==='k'){ e.preventDefault(); search?.focus(); search?.select(); }
  });

  favsToggle?.addEventListener('click', ()=>{
    onlyFavs = !onlyFavs;
    favsToggle.setAttribute('aria-pressed', onlyFavs?'true':'false');
    applyFilter(search?.value||'');
  });

  // Expandir/Colapsar todo
  d.getElementById('nsExpandAll')?.addEventListener('click', ()=>{
    const activeMenu = (currentTab==='auto') ? M2 : M1;
    activeMenu.querySelectorAll('.ns-group').forEach(det=>{
      det.open=true;
      const key=(activeMenu.dataset.tab||'curated')+':'+(det.dataset.key||'');
      groups[key]=1;
      det.querySelector('.ns-summary')?.setAttribute('aria-expanded','true');
    });
    try{localStorage.setItem(KEY_GROUPS, JSON.stringify(groups))}catch{}
  });

  d.getElementById('nsCollapseAll')?.addEventListener('click', ()=>{
    const activeMenu = (currentTab==='auto') ? M2 : M1;
    activeMenu.querySelectorAll('.ns-group').forEach(det=>{
      det.open=false;
      const key=(activeMenu.dataset.tab||'curated')+':'+(det.dataset.key||'');
      groups[key]=0;
      det.querySelector('.ns-summary')?.setAttribute('aria-expanded','false');
    });
    try{localStorage.setItem(KEY_GROUPS, JSON.stringify(groups))}catch{}
  });

  // Toggle ancho
  d.getElementById('nsToggle')?.addEventListener('click', ()=> w.P360.sidebar.toggle());

  // Backdrop móvil
  d.getElementById('ns-backdrop')?.addEventListener('click', ()=> w.P360.sidebar.closeMobile(), {passive:true});

  // Favoritos: toggle
  [M1, M2].forEach(menu=>{
    menu.addEventListener('click', e=>{
      const btn = e.target.closest('.fav'); if(!btn) return;
      const row = btn.closest('.ns-item'); const a=row.querySelector('.ns-link');
      const t=a.getAttribute('data-title') || a.textContent.trim();
      const url=a.getAttribute('href')||'#';
      let pins=getPins();
      const i=pins.findIndex(p=>p.url===url);

      if(i>=0){
        pins.splice(i,1);
        btn.classList.remove('active'); btn.textContent='☆';
        btn.setAttribute('aria-label','Favorito'); btn.setAttribute('title','Agregar a favoritos');
      } else {
        pins.unshift({t,url});
        btn.classList.add('active'); btn.textContent='★';
        btn.setAttribute('aria-label','Quitar de favoritos'); btn.setAttribute('title','Quitar de favoritos');
      }
      setPins(pins);
      renderPins(); applyFilter(search?.value||'');
    });
  });

  // Inicializa favoritos visualmente
  const pinSet = new Set((()=>{try{return JSON.parse(localStorage.getItem(KEY_PINS)||'[]').map(p=>p.url)}catch{return []}})());
  [M1,M2].forEach(menu=>{
    menu.querySelectorAll('.ns-item').forEach(row=>{
      const a=row.querySelector('.ns-link'); const url=a?.getAttribute('href')||'';
      const b=row.querySelector('.fav'); if(!b) return;
      if(pinSet.has(url)){
        b.classList.add('active'); b.textContent='★';
        b.setAttribute('aria-label','Quitar de favoritos'); b.setAttribute('title','Quitar de favoritos');
      }
    });
  });

  w.addEventListener('load', ()=>{ try{ syncOffset(); }catch(_){ } }, {once:true});
})(window,document);
</script>
