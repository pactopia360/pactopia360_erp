{{-- resources/views/layouts/partials/sidebar.blade.php --}}
@php
  use Illuminate\Support\Facades\Route;
  use Illuminate\Support\Facades\Gate;
  use Illuminate\Support\Str;

  $menu = [
    ['section' => null, 'items' => [
      ['text'=>'Home', 'icon'=>'🏠', 'route'=>'admin.home', 'active_when'=>['admin.home','admin.dashboard','admin.root']],
    ]],
    ['section' => 'Usuarios', 'items' => [
      ['text'=>'Usuarios Admin',      'icon'=>'👤', 'route'=>'admin.usuarios.index',   'perm'=>'usuarios_admin.ver'],
      ['text'=>'Perfiles & Permisos', 'icon'=>'🧩', 'route'=>'admin.perfiles.index',   'perm'=>'perfiles.ver'],
    ]],
    ['section' => 'Catálogos', 'items' => [
      ['text'=>'Clientes',            'icon'=>'🧾', 'route'=>'admin.clientes.index',   'perm'=>'clientes.ver'],
      ['text'=>'Planes',              'icon'=>'📦', 'route'=>'admin.planes.index',     'perm'=>'planes.ver'],
    ]],
    ['section' => 'Operación', 'items' => [
      ['text'=>'Pagos',               'icon'=>'💳', 'route'=>'admin.pagos.index',      'perm'=>'pagos.ver'],
      ['text'=>'Facturación',         'icon'=>'🧮', 'route'=>'admin.facturacion.index','perm'=>'facturacion.ver'],
    ]],
    ['section' => 'Control', 'items' => [
      ['text'=>'Auditoría',           'icon'=>'🛡️', 'route'=>'admin.auditoria.index',  'perm'=>'auditoria.ver'],
      ['text'=>'Reportes',            'icon'=>'📈', 'route'=>'admin.reportes.index',   'perm'=>'reportes.ver'],
      ['text'=>'Configuración',       'icon'=>'⚙️', 'route'=>'admin.config.index',     'perm'=>'configuracion.ver'],
    ]],
  ];

  $hasPermAbility = Gate::has('perm');
  $canShow = function (array $it) use ($hasPermAbility) {
    if (!$hasPermAbility) return true;
    $req = $it['perm'] ?? null; if (!$req) return true;
    return auth('admin')->user()?->can('perm', $req) ?? false;
  };

  // Activo: exacto + prefijo de recurso + patrones extra
  $linkData = function (array $it) {
    $url = '#'; $isRoute = false;

    if (!empty($it['route']) && Route::has($it['route'])) {
      $url = route($it['route']); $isRoute = true;
    } elseif (!empty($it['href'])) {
      $url = (string) $it['href'];
    }

    $active = false;
    if ($isRoute) {
      $r = $it['route'];
      $active = request()->routeIs($r);
      if (!$active) {
        $root = \Illuminate\Support\Str::contains($r, '.') ? \Illuminate\Support\Str::beforeLast($r, '.') : $r;
        if ($root) $active = request()->routeIs("$root.*");
      }
    }
    foreach (($it['active_when'] ?? []) as $pat) {
      if (request()->routeIs($pat)) { $active = true; break; }
    }

    $external = !empty($it['href']) && empty($it['route']);
    $target = $external ? '_blank' : null;
    $rel    = $external ? 'noopener' : null;

    return compact('url','active','target','rel');
  };
@endphp

<aside id="sidebar" role="navigation" aria-label="Navegación principal">
  <div class="sidebar-scroll">
    <nav class="menu" aria-label="Menú lateral">
      @foreach ($menu as $group)
        @php
          $section = $group['section'] ?? null;
          $items   = $group['items']   ?? [];
          $visible = array_filter($items, $canShow);
          if (empty($visible)) continue;
        @endphp

        @if ($section)
          <div class="menu-section">{{ \Illuminate\Support\Str::upper($section) }}</div>
        @endif

        @foreach ($visible as $it)
          @php $ld = $linkData($it); @endphp
          <a class="menu-item {{ $ld['active'] ? 'active' : '' }}"
             href="{{ $ld['url'] }}"
             @if($ld['target']) target="{{ $ld['target'] }}" rel="{{ $ld['rel'] }}" @endif
             @if($ld['active']) aria-current="page" @endif
             title="{{ $it['text'] }}">
            <span class="ico" aria-hidden="true">{{ $it['icon'] ?? '•' }}</span>
            <span class="label">{{ $it['text'] }}</span>
          </a>
        @endforeach
      @endforeach
    </nav>
  </div>
</aside>

{{-- Backdrop para móvil (tu CSS lo espera con este ID, fuera del <aside>) --}}
<div id="sidebar-backdrop" aria-hidden="true"></div>

<script>
  // Cerrar drawer móvil con ESC y al navegar por PJAX
  (function(){
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') document.body.classList.remove('sidebar-open');
    }, {passive:true});
    addEventListener('p360:pjax:before', () => document.body.classList.remove('sidebar-open'));
  })();
</script>
