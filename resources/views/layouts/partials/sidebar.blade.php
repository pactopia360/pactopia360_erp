{{-- C:\wamp64\www\pactopia360_erp\resources\views\layouts\partials\sidebar.blade.php --}}
{{-- P360 Admin Sidebar · Studio / Rail + Panel · claro/oscuro · colapsado/expandido --}}

@php
  use Illuminate\Support\Facades\Route;
  use Illuminate\Support\Str;

  if (!function_exists('p360_try_url')) {
    function p360_try_url(string $name, array $params = []): string {
      try { return route($name, $params); }
      catch (\Throwable $e) {
        try { return Route::has($name) ? route($name, $params) : '#'; }
        catch (\Throwable $e2) { return '#'; }
      }
    }
  }

  if (!function_exists('p360_link')) {
    function p360_link(array $it): object {
      $url = '#';
      $exists = true;
      $isRoute = false;

      if (!empty($it['route'])) {
        $isRoute = true;
        $url = p360_try_url((string) $it['route'], $it['params'] ?? []);
        $exists = $url !== '#';
      } elseif (!empty($it['href'])) {
        $url = (string) $it['href'];
      }

      $active = false;
      if ($isRoute && $exists) {
        $r = (string) $it['route'];
        $active = request()->routeIs($r)
          || (Str::contains($r, '.') && request()->routeIs(Str::beforeLast($r, '.') . '.*'));
      }

      foreach (($it['active_when'] ?? []) as $pat) {
        if (request()->routeIs($pat)) {
          $active = true;
          break;
        }
      }

      $external = !empty($it['href']) && empty($it['route']);

      return (object) [
        'url'    => $url,
        'active' => $active,
        'exists' => $exists || $external,
        'target' => $external ? '_blank' : null,
        'rel'    => $external ? 'noopener' : null,
      ];
    }
  }

  if (!function_exists('p360_has_active_desc')) {
    function p360_has_active_desc(array $items): bool {
      foreach ($items as $it) {
        if (p360_link($it)->active) return true;
        foreach (($it['children'] ?? []) as $c1) {
          if (p360_link($c1)->active) return true;
          foreach (($c1['children'] ?? []) as $c2) {
            if (p360_link($c2)->active) return true;
            foreach (($c2['children'] ?? []) as $c3) {
              if (p360_link($c3)->active) return true;
            }
          }
        }
      }
      return false;
    }
  }

  $user = auth('admin')->user();
  $userName  = $user?->name ?? $user?->nombre ?? 'Admin';
  $userEmail = $user?->email ?? '';

  $profileUrl = Route::has('admin.perfil') ? route('admin.perfil')
    : (Route::has('admin.profile') ? route('admin.profile') : '#');

  $settingsUrl = Route::has('admin.config.index') ? route('admin.config.index')
    : (Route::has('admin.configuracion.index') ? route('admin.configuracion.index') : '#');

  $modules = [
    [
      'module'  => 'home',
      'label'   => 'Inicio',
      'icon'    => '⌂',
      'summary' => 'Panel y accesos rápidos',
      'blocks'  => [
        [
          'section' => 'General',
          'items'   => [
            ['text'=>'Home','icon'=>'⌂','route'=>'admin.home','active_when'=>['admin.home','admin.dashboard','admin.root']],
          ],
        ],
      ],
    ],

    [
      'module'  => 'empresas',
      'label'   => 'Empresas',
      'icon'    => '□',
      'summary' => 'Empresas y estructura corporativa',
      'blocks'  => [
        [
          'section'=>'Empresas',
          'items'=>[
            [
              'text'=>'Pactopia360','icon'=>'▣','id'=>'emp-p360','route'=>'admin.empresas.pactopia360.dashboard','active_when'=>['admin.empresas.pactopia360.*'],
              'children'=>[
                ['text'=>'CRM','icon'=>'◌','children'=>[
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
                ['text'=>'Cuentas por pagar','icon'=>'◌','children'=>[
                  ['text'=>'Gastos','route'=>'admin.empresas.pactopia360.cxp.gastos.index'],
                  ['text'=>'Proveedores','route'=>'admin.empresas.pactopia360.cxp.proveedores.index'],
                  ['text'=>'Viáticos','route'=>'admin.empresas.pactopia360.cxp.viaticos.index'],
                  ['text'=>'Robots','route'=>'admin.empresas.pactopia360.cxp.robots.index'],
                ]],
                ['text'=>'Cuentas por cobrar','icon'=>'◌','children'=>[
                  ['text'=>'Ventas','route'=>'admin.empresas.pactopia360.cxc.ventas.index'],
                  ['text'=>'Facturación y cobranza','route'=>'admin.empresas.pactopia360.cxc.facturacion.index'],
                  ['text'=>'Robots','route'=>'admin.empresas.pactopia360.cxc.robots.index'],
                ]],
                ['text'=>'Contabilidad','icon'=>'◌','children'=>[
                  ['text'=>'Robots','route'=>'admin.empresas.pactopia360.conta.robots.index'],
                ]],
                ['text'=>'Nómina','icon'=>'◌','children'=>[
                  ['text'=>'Robots','route'=>'admin.empresas.pactopia360.nomina.robots.index'],
                ]],
                ['text'=>'Facturación','icon'=>'◌','children'=>[
                  ['text'=>'Timbres / HITS','route'=>'admin.empresas.pactopia360.facturacion.timbres.index'],
                  ['text'=>'Cancelaciones','route'=>'admin.empresas.pactopia360.facturacion.cancel.index'],
                  ['text'=>'Resguardo 6 meses','route'=>'admin.empresas.pactopia360.facturacion.resguardo.index'],
                  ['text'=>'Robots','route'=>'admin.empresas.pactopia360.facturacion.robots.index'],
                ]],
                ['text'=>'Documentación','icon'=>'◌','children'=>[
                  ['text'=>'Gestor / Plantillas','route'=>'admin.empresas.pactopia360.docs.index'],
                  ['text'=>'Robots','route'=>'admin.empresas.pactopia360.docs.robots.index'],
                ]],
                ['text'=>'Punto de venta','icon'=>'◌','children'=>[
                  ['text'=>'Cajas','route'=>'admin.empresas.pactopia360.pv.cajas.index'],
                  ['text'=>'Tickets','route'=>'admin.empresas.pactopia360.pv.tickets.index'],
                  ['text'=>'Arqueos','route'=>'admin.empresas.pactopia360.pv.arqueos.index'],
                  ['text'=>'Robots','route'=>'admin.empresas.pactopia360.pv.robots.index'],
                ]],
                ['text'=>'Bancos','icon'=>'◌','children'=>[
                  ['text'=>'Cuentas','route'=>'admin.empresas.pactopia360.bancos.cuentas.index'],
                  ['text'=>'Conciliación','route'=>'admin.empresas.pactopia360.bancos.concilia.index'],
                  ['text'=>'Robots','route'=>'admin.empresas.pactopia360.bancos.robots.index'],
                ]],
              ],
            ],
            [
              'text'=>'Pactopia','icon'=>'▣','id'=>'emp-pactopia','route'=>'admin.empresas.pactopia.dashboard','active_when'=>['admin.empresas.pactopia.*'],
              'children'=>[
                ['text'=>'CRM','icon'=>'◌','children'=>[
                  ['text'=>'Contactos','route'=>'admin.empresas.pactopia.crm.contactos.index'],
                  ['text'=>'Robots','route'=>'admin.empresas.pactopia.crm.robots.index'],
                ]],
              ],
            ],
            [
              'text'=>'Waretek México','icon'=>'▣','id'=>'emp-waretek-mx','route'=>'admin.empresas.waretek-mx.dashboard','active_when'=>['admin.empresas.waretek-mx.*'],
              'children'=>[
                ['text'=>'CRM','icon'=>'◌','children'=>[
                  ['text'=>'Contactos','route'=>'admin.empresas.waretek-mx.crm.contactos.index'],
                  ['text'=>'Robots','route'=>'admin.empresas.waretek-mx.crm.robots.index'],
                ]],
              ],
            ],
          ],
        ],
      ],
    ],

    [
      'module'  => 'operacion',
      'label'   => 'Operación',
      'icon'    => '◎',
      'summary' => 'Usuarios, billing, finanzas y soporte',
      'blocks'  => [
        [
          'section'=>'Administración',
          'items'=>[
            ['text'=>'Usuarios','icon'=>'◌','children'=>[
              ['text'=>'Administrativos','route'=>'admin.usuarios.administrativos.index','active_when'=>['admin.usuarios.administrativos.*']],
              ['text'=>'Clientes','route'=>'admin.clientes.index','active_when'=>['admin.clientes.*']],
              ['text'=>'Robots','route'=>'#'],
            ]],
          ],
        ],
        [
          'section'=>'Billing',
          'items'=>[
            [
              'text'=>'Billing SaaS','icon'=>'◌','id'=>'billing-saas',
              'active_when'=>[
                'admin.billing.*',
                'admin.config.param.stripe_prices.*',
                'admin.config.param.licencias.*',
                'admin.sat.*'
              ],
              'children'=>[
                ['text'=>'Estados de cuenta','route'=>'admin.billing.statements.index','active_when'=>['admin.billing.statements.*']],
                ['text'=>'Email Estados','route'=>'admin.billing.statement_emails.index','active_when'=>['admin.billing.statement_emails.*']],
                ['text'=>'Pagos','route'=>'admin.billing.payments.index','active_when'=>['admin.billing.payments.*']],
                ['text'=>'Solicitudes de factura','route'=>'admin.billing.invoices.requests.index','active_when'=>['admin.billing.invoices.requests.*']],
                [
                  'text'=>'Facturación Admin',
                  'id'=>'billing-invoicing-admin',
                  'active_when'=>['admin.billing.invoicing.*'],
                  'children'=>[
                    ['text'=>'Dashboard','route'=>'admin.billing.invoicing.dashboard','active_when'=>['admin.billing.invoicing.dashboard']],
                    ['text'=>'Solicitudes','route'=>'admin.billing.invoicing.requests.index','active_when'=>['admin.billing.invoicing.requests.*']],
                    ['text'=>'Facturas emitidas','route'=>'admin.billing.invoicing.invoices.index','active_when'=>['admin.billing.invoicing.invoices.*']],
                    ['text'=>'Emisores','route'=>'admin.billing.invoicing.emisores.index','active_when'=>['admin.billing.invoicing.emisores.*']],
                    ['text'=>'Receptores','route'=>'admin.billing.invoicing.receptores.index','active_when'=>['admin.billing.invoicing.receptores.*']],
                    ['text'=>'Configuración','route'=>'admin.billing.invoicing.settings.index','active_when'=>['admin.billing.invoicing.settings.*']],
                    ['text'=>'Logs','route'=>'admin.billing.invoicing.logs.index','active_when'=>['admin.billing.invoicing.logs.*']],
                  ],
                ],
                ['text'=>'Precios Stripe (catálogo)','route'=>'admin.config.param.stripe_prices.index','active_when'=>['admin.config.param.stripe_prices.*']],
                ['text'=>'Licencias por cuenta (meta)','route'=>'admin.config.param.licencias.index','active_when'=>['admin.config.param.licencias.*']],
                ['text'=>'SAT · Lista de precios','route'=>'admin.sat.prices.index','active_when'=>['admin.sat.prices.*']],
                ['text'=>'SAT · Códigos descuento','route'=>'admin.sat.discounts.index','active_when'=>['admin.sat.discounts.*']],
                ['text'=>'SAT · Operación','route'=>'admin.sat.ops.index','active_when'=>['admin.sat.ops.*']],
              ],
            ],
          ],
        ],
        [
          'section'=>'Finanzas',
          'items'=>[
            [
              'text'=>'Finanzas',
              'icon'=>'◌',
              'id'=>'finanzas',
              'active_when'=>['admin.finance.*','admin.finanzas.*'],
              'children'=>[
                ['text'=>'Centro de costos','route'=>'admin.finance.cost_centers.index','active_when'=>['admin.finance.cost_centers.*']],
                ['text'=>'Ingresos (Resumen)','route'=>'admin.finance.income.index','active_when'=>['admin.finance.income.*']],
                ['text'=>'Egresos','route'=>'admin.finance.expenses.index','active_when'=>['admin.finance.expenses.*']],
                ['text'=>'Ventas (CRUD)','route'=>'admin.finance.sales.index','active_when'=>['admin.finance.sales.*']],
                ['text'=>'Vendedores','route'=>'admin.finance.vendors.index','active_when'=>['admin.finance.vendors.*']],
                ['text'=>'Comisiones','route'=>'admin.finance.commissions.index','active_when'=>['admin.finance.commissions.*']],
                ['text'=>'Proyecciones','route'=>'admin.finance.projections.index','active_when'=>['admin.finance.projections.*']],
              ],
            ],
          ],
        ],
        [
          'section'=>'Soporte',
          'items'=>[
            ['text'=>'Soporte','icon'=>'◌','children'=>[
              ['text'=>'Tickets','route'=>'admin.soporte.tickets.index'],
              ['text'=>'SLA / Asignación','route'=>'admin.soporte.sla.index'],
              ['text'=>'Comunicaciones','route'=>'admin.soporte.comms.index'],
              ['text'=>'Robots','route'=>'admin.soporte.robots.index'],
            ]],
          ],
        ],
      ],
    ],

    [
      'module'  => 'control',
      'label'   => 'Control',
      'icon'    => '◇',
      'summary' => 'Auditoría y configuración',
      'blocks'  => [
        [
          'section'=>'Auditoría',
          'items'=>[
            ['text'=>'Logs de acceso','icon'=>'◌','route'=>'admin.auditoria.accesos.index'],
            ['text'=>'Bitácora cambios','icon'=>'◌','route'=>'admin.auditoria.cambios.index'],
            ['text'=>'Integridad','icon'=>'◌','route'=>'admin.auditoria.integridad.index'],
            ['text'=>'Robots','icon'=>'◌','route'=>'admin.auditoria.robots.index'],
          ],
        ],
        [
          'section'=>'Configuración',
          'items'=>[
            ['text'=>'Plataforma','icon'=>'◌','children'=>[
              ['text'=>'Mantenimiento','route'=>'admin.config.mantenimiento'],
              ['text'=>'Optimización/Limpieza demo','route'=>'admin.config.limpieza'],
              ['text'=>'Backups / Restore','route'=>'admin.config.backups'],
              ['text'=>'Robots','route'=>'admin.config.robots'],
            ]],
            ['text'=>'Integraciones','icon'=>'◌','children'=>[
              ['text'=>'PAC(s)','route'=>'admin.config.int.pacs'],
              ['text'=>'Mailgun/MailerLite','route'=>'admin.config.int.mail'],
              ['text'=>'API Keys / Webhooks','route'=>'admin.config.int.api'],
              ['text'=>'Stripe / Conekta','route'=>'admin.config.int.pay'],
              ['text'=>'Robots','route'=>'admin.config.int.robots'],
            ]],
            ['text'=>'Parámetros','icon'=>'◌','children'=>[
              ['text'=>'Planes & Precios','route'=>'admin.config.param.precios'],
              ['text'=>'Descuentos / Cupones','route'=>'admin.config.param.cupones'],
              ['text'=>'Límites por plan','route'=>'admin.config.param.limites'],
              ['text'=>'Robots','route'=>'admin.config.param.robots'],
            ]],
          ],
        ],
      ],
    ],

    [
      'module'  => 'perfil',
      'label'   => 'Perfil',
      'icon'    => '◐',
      'summary' => 'Cuenta y preferencias',
      'blocks'  => [
        [
          'section'=>'Mi cuenta',
          'items'=>[
            ['text'=>'Mi cuenta','icon'=>'◌','route'=>'admin.perfil'],
            ['text'=>'Editar perfil','icon'=>'◌','route'=>'admin.perfil.edit'],
            ['text'=>'Perfiles/Permisos','icon'=>'◌','route'=>'admin.perfiles.index'],
            ['text'=>'Preferencias','icon'=>'◌','route'=>'admin.perfil.preferencias'],
            ['text'=>'Robots','icon'=>'◌','route'=>'admin.perfil.robots'],
          ],
        ],
      ],
    ],

    [
      'module'  => 'reportes',
      'label'   => 'Reportes',
      'icon'    => '▤',
      'summary' => 'KPIs, BI y reportes',
      'blocks'  => [
        [
          'section'=>'Reportes',
          'items'=>[
            ['text'=>'KPIs / BI','icon'=>'◌','route'=>'admin.reportes.index'],
            ['text'=>'CRM','icon'=>'◌','route'=>'admin.reportes.crm'],
            ['text'=>'Cuentas por pagar','icon'=>'◌','route'=>'admin.reportes.cxp'],
            ['text'=>'Cuentas por cobrar','icon'=>'◌','route'=>'admin.reportes.cxc'],
            ['text'=>'Contabilidad','icon'=>'◌','route'=>'admin.reportes.conta'],
            ['text'=>'Nómina','icon'=>'◌','route'=>'admin.reportes.nomina'],
            ['text'=>'Facturación','icon'=>'◌','route'=>'admin.reportes.facturacion'],
            ['text'=>'Descargas','icon'=>'◌','route'=>'admin.reportes.descargas'],
            ['text'=>'Robots','icon'=>'◌','route'=>'admin.reportes.robots'],
          ],
        ],
      ],
    ],
  ];

  foreach ($modules as &$module) {
    $module['active'] = false;
    foreach (($module['blocks'] ?? []) as $block) {
      if (p360_has_active_desc($block['items'] ?? [])) {
        $module['active'] = true;
        break;
      }
    }
  }
  unset($module);

  $renderTree = function(array $items, int $level = 0) use (&$renderTree) {
    $html = '';

    foreach ($items as $it) {
      $text     = (string)($it['text'] ?? 'Item');
      $icon     = (string)($it['icon'] ?? '•');
      $children = $it['children'] ?? [];
      $ld       = p360_link($it);

      if (!empty($children)) {
        $idKey = $it['id'] ?? Str::slug('grp-'.$text.'-'.$level, '-');
        $anyActive = $ld->active || p360_has_active_desc($children);
        $open = $anyActive ? ' open' : '';

        $html .= '<details class="ns-group level-'.$level.'" data-key="'.e($idKey).'"'.$open.' data-txt="'.e(Str::lower($text)).'">';
        $html .=   '<summary class="ns-sum level-'.$level.($anyActive ? ' is-active' : '').'" aria-expanded="'.($anyActive ? 'true' : 'false').'">';
        $html .=     '<span class="ns-ico" aria-hidden="true">'.$icon.'</span>';
        $html .=     '<span class="ns-txt">'.$text.'</span>';
        $html .=     '<span class="ns-car" aria-hidden="true">›</span>';
        $html .=   '</summary>';
        $html .=   '<div class="ns-children">'.$renderTree($children, $level + 1).'</div>';
        $html .= '</details>';
      } else {
        $cls  = 'ns-link level-'.$level.($ld->active ? ' is-active' : '');
        $aria = $ld->active ? ' aria-current="page"' : '';

        $html .= '<div class="ns-item level-'.$level.'">';
        $html .=   '<a class="'.$cls.'" href="'.e($ld->url).'"'
                 .($ld->target ? ' target="'.$ld->target.'" rel="'.$ld->rel.'"' : '')
                 .' data-txt="'.e(Str::lower($text)).'" data-title="'.e($text).'"'.$aria.'>';
        $html .=     '<span class="ns-ico" aria-hidden="true">'.$icon.'</span>';
        $html .=     '<span class="ns-txt">'.$text.'</span>';
        $html .=   '</a>';
        $html .= '</div>';
      }
    }

    return $html;
  };
@endphp

<aside id="nebula-sidebar" class="ns-wrap" role="navigation" aria-label="Navegación principal">
  <div class="ns-shell">
    <div class="ns-rail">
      <div class="ns-rail-top">
        <a href="{{ Route::has('admin.home') ? route('admin.home') : '#' }}" class="ns-brand" aria-label="Inicio Pactopia360">
          <span class="ns-brand-mark">P</span>
        </a>

        <nav class="ns-rail-nav" aria-label="Módulos principales">
          @foreach($modules as $module)
            <button
              type="button"
              class="ns-rail-btn {{ !empty($module['active']) ? 'is-active' : '' }}"
              data-module="{{ $module['module'] }}"
              data-label="{{ $module['label'] }}"
              aria-label="{{ $module['label'] }}"
              title="{{ $module['label'] }}"
            >
              <span class="ns-rail-ico" aria-hidden="true">{{ $module['icon'] }}</span>
            </button>
          @endforeach
        </nav>
      </div>

      <div class="ns-rail-bottom">
        <button type="button" class="ns-rail-tool" id="nsToggle" title="Colapsar / Expandir" aria-label="Colapsar / Expandir">
          <span aria-hidden="true">⇆</span>
        </button>

        <a href="{{ $profileUrl !== '#' ? $profileUrl : '#' }}" class="ns-rail-avatar" title="{{ $userName }}" aria-label="{{ $userName }}">
          <span>{{ Str::substr($userName, 0, 1) }}</span>
        </a>
      </div>
    </div>

    <div class="ns-panel">
      <div class="ns-panel-head">
        <div class="ns-eyebrow">PACTOPIA360 ADMIN</div>
        <div class="ns-head-row">
          <div class="ns-head-copy">
            <h2 id="nsPanelTitle" class="ns-panel-title">Inicio</h2>
            <p id="nsPanelSubtitle" class="ns-panel-subtitle">Panel y accesos rápidos</p>
          </div>
        </div>

        <div class="ns-search-wrap">
          <span class="ns-search-ico" aria-hidden="true">⌕</span>
          <input id="nsSearch" class="ns-search" type="search" placeholder="Buscar módulo o pantalla..." aria-label="Buscar en el menú">
        </div>
      </div>

      <div class="ns-panel-body">
        @foreach($modules as $module)
          <section
            class="ns-module {{ !empty($module['active']) ? 'is-active is-route-active' : '' }}"
            data-module="{{ $module['module'] }}"
            data-label="{{ $module['label'] }}"
            data-summary="{{ $module['summary'] }}"
          >
            @foreach(($module['blocks'] ?? []) as $block)
              @if(!empty($block['section']))
                <div class="ns-section">{{ Str::upper($block['section']) }}</div>
              @endif

              {!! $renderTree($block['items'] ?? [], 0) !!}
            @endforeach
          </section>
        @endforeach
      </div>

      <div class="ns-panel-foot">
        <a href="{{ $profileUrl !== '#' ? $profileUrl : '#' }}" class="ns-user-card">
          <div class="ns-user-avatar">{{ Str::substr($userName, 0, 1) }}</div>
          <div class="ns-user-copy">
            <strong class="ns-user-name">{{ $userName }}</strong>
            <span class="ns-user-email">{{ $userEmail }}</span>
          </div>
        </a>

        <div class="ns-foot-links">
          @if($settingsUrl !== '#')
            <a href="{{ $settingsUrl }}">Configuración</a>
          @endif
          @if($profileUrl !== '#')
            <a href="{{ $profileUrl }}">Mi perfil</a>
          @endif
        </div>
      </div>
    </div>
  </div>

  <div id="nsBackdrop" class="ns-backdrop" aria-hidden="true"></div>
</aside>

<style>
   :root{
    --ns-rail-w: 64px;
    --ns-panel-w: 320px;
    --ns-w: calc(var(--ns-rail-w) + var(--ns-panel-w));
    --ns-wc: var(--sidebar-w-collapsed, 64px);

    --ns-app: #f3f4f6;
    --ns-rail: #fcfcfd;
    --ns-panel: rgba(255,255,255,.38);
    --ns-panel-2: rgba(248,250,252,.28);

    --ns-text: #111827;
    --ns-muted: #6b7280;
    --ns-soft: #9ca3af;
    --ns-border: rgba(15,23,42,.08);
    --ns-border-soft: rgba(15,23,42,.05);
    --ns-hover: rgba(15,23,42,.05);
    --ns-active: rgba(109,78,255,.10);
    --ns-active-border: rgba(109,78,255,.20);
    --ns-active-text: #5b3df0;
    --ns-pill: rgba(243,244,246,.88);
    --ns-focus: 0 0 0 3px rgba(99,102,241,.20);

    --ns-brand-1: #6d4eff;
    --ns-brand-2: #8b6cff;
    --ns-brand-3: #a78bfa;

    --ns-radius-xl: 22px;
    --ns-radius-lg: 16px;
    --ns-radius-md: 12px;
    --ns-radius-sm: 10px;
  }

  html.theme-dark{
    --ns-app: #0b1220;
    --ns-rail: #0d1628;
    --ns-panel: rgba(16,25,43,.42);
    --ns-panel-2: rgba(13,23,40,.34);

    --ns-text: #e8edf5;
    --ns-muted: #94a3b8;
    --ns-soft: #64748b;
    --ns-border: rgba(255,255,255,.08);
    --ns-border-soft: rgba(255,255,255,.05);
    --ns-hover: rgba(255,255,255,.06);
    --ns-active: rgba(109,78,255,.16);
    --ns-active-border: rgba(139,108,255,.24);
    --ns-active-text: #e4dbff;
    --ns-pill: rgba(255,255,255,.06);
  }

  #nebula-sidebar.ns-wrap{
    width: var(--ns-wc) !important;
    min-width: var(--ns-wc) !important;
    max-width: var(--ns-wc) !important;
    background: transparent;
    color: var(--ns-text);
    overflow: visible !important;
    border-right: 0;
    box-sizing: border-box;
    isolation: isolate;
  }

  .ns-shell{
    position: relative;
    display:flex;
    width: var(--ns-wc);
    min-width: var(--ns-wc);
    max-width: var(--ns-wc);
    height:100%;
    min-height:100%;
    background: transparent;
    overflow: visible !important;
    border-right: 0;
  }

  .ns-rail{
    position: relative;
    z-index: 3;
    width:var(--ns-rail-w);
    min-width:var(--ns-rail-w);
    max-width:var(--ns-rail-w);
    background: color-mix(in oklab, var(--ns-rail) 94%, transparent);
    backdrop-filter: blur(14px) saturate(140%);
    -webkit-backdrop-filter: blur(14px) saturate(140%);
    border-right:1px solid var(--ns-border-soft);
    display:flex;
    flex-direction:column;
    justify-content:space-between;
    padding:10px 8px;
    flex:0 0 var(--ns-rail-w);
    box-shadow: 0 10px 28px rgba(15,23,42,.08);
  }

  .ns-rail-top,
  .ns-rail-bottom{
    display:flex;
    flex-direction:column;
    align-items:center;
    gap:8px;
    width:100%;
  }

  .ns-brand{
    width:40px;
    height:40px;
    border-radius:12px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    text-decoration:none;
    color:#fff;
    background:linear-gradient(135deg, var(--ns-brand-1) 0%, var(--ns-brand-2) 65%, var(--ns-brand-3) 100%);
    box-shadow:0 10px 22px rgba(109,78,255,.20);
  }

  .ns-brand-mark{
    font:900 13px/1 system-ui, sans-serif;
    letter-spacing:.06em;
  }

  .ns-rail-nav{
    display:flex;
    flex-direction:column;
    gap:6px;
    width:100%;
  }

  .ns-rail-btn,
  .ns-rail-tool{
    width:100%;
    height:40px;
    border:0;
    border-radius:12px;
    background:transparent;
    color:var(--ns-muted);
    display:flex;
    align-items:center;
    justify-content:center;
    cursor:pointer;
    transition:background .18s ease, color .18s ease, transform .18s ease, box-shadow .18s ease;
  }

  .ns-rail-btn:hover,
  .ns-rail-tool:hover{
    background:var(--ns-hover);
    color:var(--ns-text);
  }

  .ns-rail-btn.is-active{
    background:linear-gradient(135deg, var(--ns-brand-1) 0%, var(--ns-brand-2) 100%);
    color:#fff;
    box-shadow:0 10px 22px rgba(109,78,255,.18);
  }

  .ns-rail-ico{
    font:900 14px/1 system-ui, sans-serif;
  }

  .ns-rail-avatar{
    width:34px;
    height:34px;
    border-radius:10px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    text-decoration:none;
    background:var(--ns-pill);
    color:var(--ns-text);
    border:1px solid var(--ns-border);
    font:800 12px/1 system-ui, sans-serif;
  }

   .ns-panel{
    position: absolute;
    top: 0;
    left: var(--ns-rail-w);
    z-index: 20;
    width: var(--ns-panel-w);
    min-width: var(--ns-panel-w);
    max-width: var(--ns-panel-w);
    height: 100%;
    background:
      linear-gradient(180deg, var(--ns-panel) 0%, var(--ns-panel-2) 100%);
    backdrop-filter: blur(18px) saturate(150%);
    -webkit-backdrop-filter: blur(18px) saturate(150%);
    display:flex;
    flex-direction:column;
    overflow:hidden;
    border:1px solid rgba(255,255,255,.20);
    border-left:0;
    box-shadow:
      0 24px 54px rgba(15,23,42,.22),
      0 10px 24px rgba(15,23,42,.12);
    opacity: 1;
    visibility: visible;
    transform: translate3d(0,0,0);
    pointer-events: auto;
    transition:
      opacity .20s ease,
      transform .22s ease,
      visibility .22s ease,
      box-shadow .22s ease;
  }

  .ns-panel-head{
    padding:14px 14px 12px;
    border-bottom:1px solid var(--ns-border-soft);
    background:color-mix(in oklab, var(--ns-panel) 96%, transparent);
    backdrop-filter:blur(10px) saturate(140%);
  }

  .ns-eyebrow{
    font:900 9px/1 system-ui, sans-serif;
    letter-spacing:.12em;
    color:var(--ns-soft);
    margin-bottom:8px;
  }

  .ns-head-row{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:10px;
    margin-bottom:12px;
  }

  .ns-head-copy{
    min-width:0;
  }

  .ns-panel-title{
    margin:0;
    font:900 16px/1.08 system-ui, sans-serif;
    letter-spacing:-.02em;
    color:var(--ns-text);
  }

  .ns-panel-subtitle{
    margin:6px 0 0;
    font:600 12px/1.35 system-ui, sans-serif;
    color:var(--ns-muted);
  }

  .ns-search-wrap{
    display:flex;
    align-items:center;
    gap:8px;
    width:100%;
    height:38px;
    border-radius:12px;
    border:1px solid var(--ns-border);
    background:var(--ns-pill);
    padding:0 12px;
  }

  .ns-search-ico{
    color:var(--ns-soft);
    font-size:13px;
  }

  .ns-search{
    flex:1 1 auto;
    min-width:0;
    border:0;
    outline:0;
    background:transparent;
    color:var(--ns-text);
    font:600 12px/1 system-ui, sans-serif;
  }

  .ns-search::placeholder{
    color:var(--ns-soft);
  }

  .ns-panel-body{
    flex:1 1 auto;
    min-height:0;
    overflow:auto;
    padding:10px 10px 12px;
  }

  .ns-module{
    display:none;
  }

  .ns-module.is-active,
  .ns-module.is-route-active{
    display:block;
  }

  .ns-section{
    margin:10px 8px 6px;
    color:var(--ns-soft);
    font:900 9px/1 system-ui, sans-serif;
    letter-spacing:.12em;
  }

  .ns-item{
    display:flex;
    align-items:center;
    gap:4px;
    padding:1px;
  }

  .ns-link,
  .ns-sum{
    position:relative;
    display:flex;
    align-items:center;
    gap:10px;
    min-height:36px;
    flex:1 1 auto;
    min-width:0;
    padding:8px 10px;
    border-radius:11px;
    border:1px solid transparent;
    color:inherit;
    text-decoration:none;
    transition:background .18s ease, border-color .18s ease, transform .18s ease;
  }

  .ns-link:hover,
  .ns-sum:hover{
    background:var(--ns-hover);
  }

  .ns-link.is-active,
  .ns-sum.is-active,
  .ns-link[aria-current="page"]{
    background:var(--ns-active);
    border-color:var(--ns-active-border);
    color:var(--ns-active-text);
  }

  .ns-link.is-active::before,
  .ns-link[aria-current="page"]::before{
    content:"";
    position:absolute;
    left:7px;
    top:7px;
    bottom:7px;
    width:3px;
    border-radius:999px;
    background:linear-gradient(180deg, var(--ns-brand-1) 0%, var(--ns-brand-2) 100%);
  }

  .ns-ico{
    width:14px;
    min-width:14px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    font:900 11px/1 system-ui, sans-serif;
    color:inherit;
  }

  .ns-txt{
    min-width:0;
    flex: 1 1 auto;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
    font:700 12px/1.1 system-ui, sans-serif;
  }

  .ns-car{
    margin-left:auto;
    font-size:15px;
    line-height:1;
    color:var(--ns-soft);
    transition:transform .18s ease, opacity .18s ease;
  }

  details.ns-group{
    margin:2px 0;
  }

  summary.ns-sum{
    list-style:none;
    cursor:pointer;
    user-select:none;
  }

  summary.ns-sum::-webkit-details-marker{
    display:none;
  }

  details[open] > summary .ns-car{
    transform:rotate(90deg);
  }

  .ns-children{
    margin:4px 0 6px;
    padding-left:8px;
    position:relative;
  }

  .ns-children::before{
    content:"";
    position:absolute;
    left:15px;
    top:6px;
    bottom:8px;
    width:1px;
    background:color-mix(in oklab, var(--ns-text) 12%, transparent);
  }

  .level-1{ padding-left:18px !important; }
  .level-2{ padding-left:28px !important; }
  .level-3{ padding-left:38px !important; }
  .level-4{ padding-left:48px !important; }

  .ns-panel-foot{
    padding:10px 12px 12px;
    border-top:1px solid var(--ns-border-soft);
    background:color-mix(in oklab, var(--ns-panel) 96%, transparent);
  }

  .ns-user-card{
    display:flex;
    align-items:center;
    gap:10px;
    width:100%;
    text-decoration:none;
    color:inherit;
    padding:10px;
    border-radius:14px;
    border:1px solid var(--ns-border);
    background:var(--ns-pill);
  }

  .ns-user-avatar{
    width:34px;
    height:34px;
    border-radius:10px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:linear-gradient(135deg, var(--ns-brand-1) 0%, var(--ns-brand-2) 100%);
    color:#fff;
    font:800 12px/1 system-ui, sans-serif;
    flex:0 0 34px;
  }

  .ns-user-copy{
    min-width:0;
    display:flex;
    flex-direction:column;
    gap:3px;
  }

  .ns-user-name{
    font:800 12px/1.1 system-ui, sans-serif;
    color:var(--ns-text);
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
  }

  .ns-user-email{
    font:600 11px/1.2 system-ui, sans-serif;
    color:var(--ns-muted);
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
  }

  .ns-foot-links{
    display:flex;
    flex-direction:column;
    gap:6px;
    margin-top:10px;
  }

  .ns-foot-links a{
    color:var(--ns-muted);
    text-decoration:none;
    font:700 12px/1.2 system-ui, sans-serif;
    padding-left:2px;
  }

  .ns-foot-links a:hover{
    color:var(--ns-text);
  }

  .ns-rail-btn:focus-visible,
  .ns-rail-tool:focus-visible,
  .ns-link:focus-visible,
  .ns-sum:focus-visible,
  .ns-search:focus-visible,
  .ns-user-card:focus-visible,
  .ns-foot-links a:focus-visible{
    outline:none;
    box-shadow:var(--ns-focus);
  }

  .ns-backdrop{
    display:none;
  }

  @media (min-width:1024px){
    html.sidebar-collapsed #nebula-sidebar{
      width:var(--ns-wc) !important;
      min-width:var(--ns-wc) !important;
      max-width:var(--ns-wc) !important;
    }

    html.sidebar-collapsed #nebula-sidebar .ns-panel{
      opacity: 0;
      visibility: hidden;
      transform: translate3d(-18px,0,0);
      pointer-events: none;
      box-shadow: none;
    }

    html:not(.sidebar-collapsed) #nebula-sidebar .ns-panel{
      opacity: 1;
      visibility: visible;
      transform: translateX(0);
      pointer-events: auto;
    }

    html.sidebar-collapsed #nebula-sidebar .ns-rail-btn,
    html.sidebar-collapsed #nebula-sidebar .ns-rail-tool,
    html.sidebar-collapsed #nebula-sidebar .ns-rail-avatar{
      position:relative;
    }

    html.sidebar-collapsed #nebula-sidebar .ns-rail-btn:hover::after,
    html.sidebar-collapsed #nebula-sidebar .ns-rail-tool:hover::after,
    html.sidebar-collapsed #nebula-sidebar .ns-rail-avatar:hover::after{
      content: attr(title);
      position:absolute;
      left:calc(100% + 10px);
      top:50%;
      transform:translateY(-50%);
      white-space:nowrap;
      background:#111827;
      color:#fff;
      border-radius:9px;
      padding:7px 9px;
      font:700 11px/1 system-ui, sans-serif;
      box-shadow:0 10px 24px rgba(0,0,0,.18);
      z-index:50;
      pointer-events:none;
    }

    html.theme-dark.sidebar-collapsed #nebula-sidebar .ns-rail-btn:hover::after,
    html.theme-dark.sidebar-collapsed #nebula-sidebar .ns-rail-tool:hover::after,
    html.theme-dark.sidebar-collapsed #nebula-sidebar .ns-rail-avatar:hover::after{
      background:#0b1220;
      border:1px solid rgba(255,255,255,.08);
    }

    html:not(.sidebar-collapsed) #nebula-sidebar{
      overflow: visible !important;
    }

    html:not(.sidebar-collapsed) #nebula-sidebar .ns-shell{
      overflow: visible !important;
    }

    html:not(.sidebar-collapsed) #nebula-sidebar .ns-panel{
      outline: 1px solid rgba(255,255,255,.10);
    }

    html.theme-dark:not(.sidebar-collapsed) #nebula-sidebar .ns-panel{
      outline: 1px solid rgba(255,255,255,.06);
    }
  }

  @media (max-width:1023.98px){
    #nebula-sidebar.ns-wrap{
      width:min(86vw, 320px) !important;
      min-width:min(86vw, 320px) !important;
      max-width:min(86vw, 320px) !important;
      transform:translateX(-100%);
      transition:transform .22s ease;
      z-index:1600 !important;
      box-shadow:0 20px 40px rgba(0,0,0,.18);
    }

    body.sidebar-open #nebula-sidebar{
      transform:translateX(0);
    }

    .ns-shell{
      overflow:hidden;
      background: color-mix(in oklab, var(--ns-app) 90%, transparent);
    }

    .ns-panel{
      position: relative;
      left: 0;
      width:calc(100% - var(--ns-rail-w));
      min-width:calc(100% - var(--ns-rail-w));
      max-width:calc(100% - var(--ns-rail-w));
      height: auto;
      flex:1 1 auto;
      opacity: 1 !important;
      visibility: visible !important;
      transform:none !important;
      pointer-events:auto !important;
      box-shadow:none;
      border-left:0;
      border-top:0;
      border-bottom:0;
      border-right:0;
    }

    .ns-backdrop{
      position:fixed;
      left:0;
      right:0;
      top:var(--header-h, 56px);
      bottom:0;
      background:rgba(0,0,0,.38);
      display:none;
      z-index:1500;
    }

    body.sidebar-open .ns-backdrop{
      display:block;
    }
  }
</style>

<script>
(function(w,d){
  'use strict';

  w.P360 = w.P360 || {};

  const SB       = d.getElementById('nebula-sidebar');
  const BCK      = d.getElementById('nsBackdrop');
  const TOGGLE   = d.getElementById('nsToggle');
  const SEARCH   = d.getElementById('nsSearch');
  const HBTN     = d.getElementById('btnSidebar');
  const root     = d.documentElement;
  const body     = d.body;

  if (!SB) return;

  const KEY_MODE = 'p360.sidebar.mode.studio';
  const KEY_OPEN = 'p360.sidebar.open.studio';
  const KEY_MOD  = 'p360.sidebar.module.studio';
  const KEY_GRP  = 'p360.sidebar.groups.studio';

  const railButtons = Array.from(SB.querySelectorAll('.ns-rail-btn[data-module]'));
  const modules     = Array.from(SB.querySelectorAll('.ns-module[data-module]'));
  const details     = Array.from(SB.querySelectorAll('details.ns-group[data-key]'));
  const panelTitle  = d.getElementById('nsPanelTitle');
  const panelSub    = d.getElementById('nsPanelSubtitle');

  function isDesktop() {
    return w.matchMedia('(min-width:1024px)').matches;
  }

  function load(key, fallback) {
    try {
      const v = localStorage.getItem(key);
      return v === null ? fallback : v;
    } catch (_) {
      return fallback;
    }
  }

  function save(key, value) {
    try { localStorage.setItem(key, value); } catch (_) {}
  }

  function parseJSON(value, fallback) {
    try { return JSON.parse(value); } catch (_) { return fallback; }
  }

  function currentCollapsedWidth() {
    const v = getComputedStyle(root).getPropertyValue('--sidebar-w-collapsed').trim();
    return v || '64px';
  }

  function syncOffset() {
    if (!isDesktop()) {
      root.style.setProperty('--sidebar-offset', '0px');
      return;
    }

    root.style.setProperty('--sidebar-offset', currentCollapsedWidth());
  }

  function isDesktopCollapsed() {
    return root.classList.contains('sidebar-collapsed');
  }

  function isMobileOpen() {
    return body.classList.contains('sidebar-open');
  }

  function reflectShell() {
    const mode = load(KEY_MODE, 'expanded');
    const open = load(KEY_OPEN, '0') === '1';

    if (isDesktop()) {
      body.classList.remove('sidebar-open');
      root.classList.toggle('sidebar-collapsed', mode === 'collapsed');
      root.setAttribute('data-sidebar', mode === 'collapsed' ? 'collapsed' : 'expanded');
    } else {
      root.classList.remove('sidebar-collapsed');
      root.setAttribute('data-sidebar', open ? 'expanded' : 'collapsed');
      body.classList.toggle('sidebar-open', open);
    }

    syncOffset();

    if (HBTN) {
      HBTN.setAttribute('aria-expanded', body.classList.contains('sidebar-open') ? 'true' : 'false');
    }
  }

  function expandDesktop() {
    if (!isDesktop()) return;
    save(KEY_MODE, 'expanded');
    reflectShell();
  }

  function collapseDesktop() {
    if (!isDesktop()) return;
    save(KEY_MODE, 'collapsed');
    reflectShell();
  }

  function getActiveModuleFromRoute() {
    const routeMod = modules.find((m) => m.classList.contains('is-route-active'));
    if (routeMod) return routeMod.getAttribute('data-module') || '';

    const activeLink =
      SB.querySelector('.ns-link[aria-current="page"]') ||
      SB.querySelector('.ns-link.is-active');

    if (!activeLink) return '';

    const host = activeLink.closest('.ns-module[data-module]');
    return host ? (host.getAttribute('data-module') || '') : '';
  }

  function getModuleMeta(moduleName) {
    const mod = modules.find((m) => (m.getAttribute('data-module') || '') === moduleName);
    if (!mod) {
      return { label: 'Inicio', summary: 'Panel y accesos rápidos' };
    }

    return {
      label: mod.getAttribute('data-label') || 'Módulo',
      summary: mod.getAttribute('data-summary') || ''
    };
  }

  function setPanelHeader(moduleName) {
    const meta = getModuleMeta(moduleName);
    if (panelTitle) panelTitle.textContent = meta.label;
    if (panelSub) panelSub.textContent = meta.summary;
  }

  function setActiveModule(moduleName) {
    if (!moduleName) return;

    save(KEY_MOD, moduleName);

    railButtons.forEach((btn) => {
      btn.classList.toggle('is-active', (btn.getAttribute('data-module') || '') === moduleName);
    });

    modules.forEach((mod) => {
      mod.classList.toggle('is-active', (mod.getAttribute('data-module') || '') === moduleName);
    });

    setPanelHeader(moduleName);
    applyFilter(SEARCH ? SEARCH.value : '');
  }

  function applySavedGroups() {
    const saved = parseJSON(load(KEY_GRP, '{}'), {});

    details.forEach((det) => {
      const key = det.getAttribute('data-key') || '';
      const summary = det.querySelector(':scope > summary');

      if (Object.prototype.hasOwnProperty.call(saved, key)) {
        det.open = !!saved[key];
      }

      if (summary) {
        summary.setAttribute('aria-expanded', det.open ? 'true' : 'false');
      }

      det.addEventListener('toggle', () => {
        const next = parseJSON(load(KEY_GRP, '{}'), {});
        next[key] = det.open ? 1 : 0;
        save(KEY_GRP, JSON.stringify(next));
        if (summary) {
          summary.setAttribute('aria-expanded', det.open ? 'true' : 'false');
        }
      });
    });
  }

  function applyFilter(query) {
    const term = String(query || '').trim().toLowerCase();
    const activeModule = modules.find((m) => m.classList.contains('is-active')) || modules[0];
    if (!activeModule) return;

    activeModule.querySelectorAll('.ns-item').forEach((row) => {
      const link = row.querySelector('.ns-link');
      if (!link) return;

      const txt = (link.getAttribute('data-txt') || '').toLowerCase();
      const title = (link.getAttribute('data-title') || '').toLowerCase();
      const visible = !term || txt.includes(term) || title.includes(term);

      row.style.display = visible ? '' : 'none';
    });

    activeModule.querySelectorAll('details.ns-group').forEach((det) => {
      const itemVisible = Array.from(det.querySelectorAll(':scope > .ns-children .ns-item')).some((item) => item.style.display !== 'none');
      const groupVisible = Array.from(det.querySelectorAll(':scope > .ns-children > details.ns-group')).some((group) => group.style.display !== 'none');
      const visible = itemVisible || groupVisible || !term;

      det.style.display = visible ? '' : 'none';
      if (term && visible) det.open = true;
    });

    activeModule.querySelectorAll('.ns-section').forEach((sec) => {
      let next = sec.nextElementSibling;
      let hasVisible = false;

      while (next && !next.classList.contains('ns-section')) {
        if (next.style.display !== 'none') {
          hasVisible = true;
          break;
        }
        next = next.nextElementSibling;
      }

      sec.style.display = hasVisible ? '' : 'none';
    });
  }

  w.P360.sidebar = {
    isCollapsed() {
      return isDesktop() ? isDesktopCollapsed() : false;
    },
    setCollapsed(flag) {
      if (isDesktop()) {
        save(KEY_MODE, flag ? 'collapsed' : 'expanded');
        reflectShell();
      } else {
        save(KEY_OPEN, flag ? '0' : '1');
        reflectShell();
      }
    },
    toggle() {
      if (isDesktop()) {
        this.setCollapsed(!this.isCollapsed());
      } else {
        this.openMobile(load(KEY_OPEN, '0') !== '1');
      }
    },
    openMobile(flag = true) {
      if (!isDesktop()) {
        save(KEY_OPEN, flag ? '1' : '0');
        reflectShell();
      }
    },
    closeMobile() {
      this.openMobile(false);
    },
    expandDesktop,
    collapseDesktop
  };

  railButtons.forEach((btn) => {
    btn.addEventListener('click', () => {
      const moduleName = btn.getAttribute('data-module') || '';
      if (!moduleName) return;

      setActiveModule(moduleName);

      if (SEARCH) {
        SEARCH.value = '';
        applyFilter('');
      }

      if (isDesktop()) {
        expandDesktop();
      } else {
        save(KEY_OPEN, '1');
        reflectShell();
      }
    });
  });

  TOGGLE?.addEventListener('click', (e) => {
    e.stopPropagation();
    w.P360.sidebar.toggle();
  });

  SEARCH?.addEventListener('input', (e) => {
    applyFilter(e.target.value || '');
  });

  SEARCH?.addEventListener('click', (e) => {
    e.stopPropagation();
  });

  BCK?.addEventListener('click', () => {
    w.P360.sidebar.closeMobile();
  }, { passive: true });

  if (HBTN) {
    HBTN.addEventListener('click', () => {
      if (!isDesktop()) {
        save(KEY_OPEN, load(KEY_OPEN, '0') === '1' ? '0' : '1');
        reflectShell();
      }
    });
  }

  d.addEventListener('click', (e) => {
    const target = e.target;
    if (!(target instanceof Element)) return;

    if (isDesktop()) {
      if (isDesktopCollapsed()) return;

      const clickedInsideSidebar = !!target.closest('#nebula-sidebar');
      const clickedToggleButton  = !!target.closest('#btnSidebar');

      if (!clickedInsideSidebar && !clickedToggleButton) {
        collapseDesktop();
      }
    } else {
      if (!isMobileOpen()) return;

      const clickedInsideSidebar = !!target.closest('#nebula-sidebar');
      const clickedToggleButton  = !!target.closest('#btnSidebar');

      if (!clickedInsideSidebar && !clickedToggleButton) {
        save(KEY_OPEN, '0');
        reflectShell();
      }
    }
  });

  const mq = w.matchMedia('(min-width:1024px)');
  mq.addEventListener?.('change', reflectShell);
  w.addEventListener('resize', reflectShell);

  w.addEventListener('keydown', (e) => {
    const ctrl = (e.ctrlKey || e.metaKey) && !e.shiftKey && !e.altKey;

    if (ctrl && e.key.toLowerCase() === 'k') {
      e.preventDefault();
      if (isDesktop() && isDesktopCollapsed()) {
        expandDesktop();
      }
      SEARCH?.focus();
      SEARCH?.select?.();
    }

    if (e.key === 'Escape') {
      if (isDesktop()) {
        collapseDesktop();
      } else {
        save(KEY_OPEN, '0');
        reflectShell();
      }
    }
  });

  applySavedGroups();

  const initialModule =
    getActiveModuleFromRoute() ||
    load(KEY_MOD, '') ||
    (railButtons[0] ? (railButtons[0].getAttribute('data-module') || 'home') : 'home');

  reflectShell();
  setActiveModule(initialModule);
  applyFilter('');
})(window, document);
</script>