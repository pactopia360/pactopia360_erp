{{-- resources/views/admin/partials/home_boot.blade.php --}}
@php
  use Illuminate\Support\Facades\Route as R;

  // Cache-busting simple (si prefieres filemtime, cámbialo por assetv(...) en las vistas que cargan los JS)
  $v = time();

  // Helper tolerante para rutas
  if (!function_exists('p360_safe_route')) {
    function p360_safe_route(?string $name, array $params = []): ?string {
      if (!$name) return null;
      return \Illuminate\Support\Facades\Route::has($name) ? route($name, $params) : null;
    }
  }

  $ym   = now()->format('Y-m');
  $year = now()->year;

  // Endpoints (solo si existen). Conservamos alias legacy 'incomeByMonth'
  $routes = [
    'stats'           => p360_safe_route('admin.home.stats'),
    'incomeMonth'     => p360_safe_route('admin.home.incomeMonth',   ['ym' => $ym]),
    'incomeByMonth'   => p360_safe_route('admin.home.incomeMonth',   ['ym' => $ym]), // alias legacy
    'compareDaily'    => p360_safe_route('admin.home.compare',       ['ym' => $ym]),
    'ytd'             => p360_safe_route('admin.home.ytd',           ['year' => $year]),
    'hitsHeatmap'     => p360_safe_route('admin.home.hitsHeatmap',   ['weeks' => 6]),
    'modulesTop'      => p360_safe_route('admin.home.modulesTop',    ['months' => 6]),
    'plansBreakdown'  => p360_safe_route('admin.home.plansBreakdown',['months' => 6]),
    'export'          => p360_safe_route('admin.home.export'),
  ];

  // Si quieres filtrar las null para que no aparezcan en el JSON:
  $routes = array_filter($routes, fn($v) => !is_null($v));
@endphp

<script>
  // Config global que consumen tus módulos de /assets/admin/js/home/*
  (function () {
    window.HomeCfg = {
      endpoints: @json($routes, JSON_UNESCAPED_SLASHES),
      csrf: @json(csrf_token()),
      debug: @json(app()->environment('local')),
      version: @json($v),
      theme: (document.documentElement.classList.contains('theme-dark') ? 'dark' : 'light')
    };
  })();
</script>
