@php
  use Illuminate\Support\Facades\Route;
  use Illuminate\Support\Facades\Gate;

  // Definición del menú (secciones + ítems)
  // Ajusta los 'route' a tus nombres reales cuando los tengas creados.
  $menu = [
    [
      'section' => null,
      'items' => [
        ['text' => 'Home', 'icon' => '🏠', 'route' => 'admin.home'],
      ],
    ],
    [
      'section' => 'Usuarios',
      'items' => [
        ['text' => 'Usuarios Admin',      'icon' => '👤', 'route' => 'admin.usuarios.index', 'perm' => 'usuarios_admin.ver'],
        ['text' => 'Perfiles & Permisos', 'icon' => '🧩', 'route' => 'admin.perfiles.index', 'perm' => 'perfiles.ver'],
      ],
    ],
    [
      'section' => 'Catálogos',
      'items' => [
        ['text' => 'Clientes', 'icon' => '🧾', 'route' => 'admin.clientes.index', 'perm' => 'clientes.ver'],
        ['text' => 'Planes',   'icon' => '📦', 'route' => 'admin.planes.index',   'perm' => 'planes.ver'],
      ],
    ],
    [
      'section' => 'Operación',
      'items' => [
        ['text' => 'Pagos',       'icon' => '💳', 'route' => 'admin.pagos.index',       'perm' => 'pagos.ver'],
        ['text' => 'Facturación', 'icon' => '🧮', 'route' => 'admin.facturacion.index', 'perm' => 'facturacion.ver'],
      ],
    ],
    [
      'section' => 'Control',
      'items' => [
        ['text' => 'Auditoría',     'icon' => '🛡️', 'route' => 'admin.auditoria.index', 'perm' => 'auditoria.ver'],
        ['text' => 'Reportes',      'icon' => '📈', 'route' => 'admin.reportes.index',  'perm' => 'reportes.ver'],
        ['text' => 'Configuración', 'icon' => '⚙️', 'route' => 'admin.config.index',    'perm' => 'configuracion.ver'],
      ],
    ],
  ];

  // Helpers: url seguro y clase activa
  $linkData = function (string $routeName) {
      $has = Route::has($routeName);
      return [
        'url'    => $has ? route($routeName) : '#',
        'active' => $has ? (request()->routeIs($routeName) || request()->routeIs($routeName.'.*')) : false,
      ];
  };

  // ¿Tenemos Gate 'perm'? Si no, no ocultamos nada por permisos.
  $hasPermGate = Gate::has('perm');
@endphp

<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <img class="logo-img logo-dark" src="{{ asset('assets/admin/img/logo-pactopia360-white.png') }}" alt="Pactopia360">
    <img class="logo-img logo-light" src="{{ asset('assets/admin/img/logo-pactopia360-dark.png') }}" alt="Pactopia360">
  </div>

  <nav class="nav">
    @foreach ($menu as $block)
      @if (!empty($block['section']))
        <div class="nav-section">{{ $block['section'] }}</div>
      @endif

      @foreach ($block['items'] as $item)
        @php
          // Regla de visibilidad por permiso (solo si el Gate 'perm' existe)
          $show = true;
          if ($hasPermGate && !empty($item['perm'])) {
            $user = auth('admin')->user();
            $show = $user ? $user->can('perm', $item['perm']) : false;
          }

          $link = $linkData($item['route'] ?? '#');
          $classes = 'nav-link' . ($link['active'] ? ' active' : '');
        @endphp

        @if ($show)
          <a class="{{ $classes }}" href="{{ $link['url'] }}" @if($link['active']) aria-current="page" @endif>
            <span class="ico">{{ $item['icon'] ?? '•' }}</span>
            <span class="txt">{{ $item['text'] }}</span>
          </a>
        @endif
      @endforeach
    @endforeach
  </nav>

  <button class="collapse-btn" id="sidebarToggle" title="Colapsar menú">⟨⟨</button>
</aside>
