@extends('layouts.admin')

@php
  // ======= Config/estado =======
  $title = $titles['index'] ?? 'Usuarios';
  $F     = $filters ?? [];
  $q     = $F['q']       ?? '';
  $rol   = $F['rol']     ?? '';
  $sa    = $F['sa']      ?? '';
  // aceptar ambos nombres para no perder selección
  $ac    = $F['activo']  ?? ($F['ac'] ?? '');
  $sort  = $F['sort']    ?? 'created_at';
  $dir   = $F['dir']     ?? 'desc';
  $pp    = (int)($F['pp'] ?? 20);

  // HREF del CSS del módulo (cache-busting usable en styles y en ensure)
  $USR_CSS = asset('assets/admin/css/usuarios.css');
  try {
    $abs = public_path('assets/admin/css/usuarios.css');
    if (is_file($abs)) {
      $USR_CSS .= (str_contains($USR_CSS,'?')?'&v=':'?v=').filemtime($abs);
    }
  } catch (\Throwable $e) {}

  if (!function_exists('usr_sort_link')) {
    function usr_sort_link($col, $label, $routeBase, $sort, $dir) {
      $next = ($sort===$col && $dir==='asc') ? 'desc' : 'asc';
      $params = array_merge(request()->query(), ['sort'=>$col, 'dir'=>$next]);
      $url = route($routeBase.'.index', $params);
      $indicator = $sort===$col ? ($dir==='asc'?'▲':'▼') : '';
      return '<a href="'.$url.'" class="usr-sort">'.$label.' '.$indicator.'</a>';
    }
  }

  if (!function_exists('p360_safe_route')) {
    function p360_safe_route(?string $name, array $params = []) {
      return $name && \Illuminate\Support\Facades\Route::has($name) ? route($name, $params) : null;
    }
  }

  // Endpoints opcionales (si no existen, el JS degrada sin romper)
  $USR_EP = [
    'bulk'    => p360_safe_route($routeBase.'.bulk'),                         // POST {ids[], action}
    'toggle'  => p360_safe_route($routeBase.'.toggle'),                       // POST {id, field, value} (si lo tuvieras)
    'export'  => p360_safe_route($routeBase.'.export', request()->query()),   // GET descarga
    'inspect' => p360_safe_route($routeBase.'.inspect'),                      // GET ?id= (opcional)
  ];
@endphp

@section('title', $title.' · Pactopia360')

@push('styles')
<link id="css-usuarios" rel="stylesheet" href="{{ $USR_CSS }}">
@endpush

@section('page-header')
<div class="usr-page">
  <div class="usr-header">
    <div class="usr-head-left">
      <h1 class="usr-title">{{ $title }}</h1>
      <div class="usr-counters">
        @php $S = $stats ?? []; @endphp
        <span class="usr-kpi"><b>{{ $S['total']        ?? number_format($items->total()) }}</b> Total</span>
        <span class="usr-kpi kpi-green"><b>{{ $S['activos']     ?? '—' }}</b> Activos</span>
        <span class="usr-kpi kpi-red"><b>{{ $S['inactivos']   ?? '—' }}</b> Inactivos</span>
        <span class="usr-kpi kpi-violet"><b>{{ $S['soporte']     ?? '—' }}</b> Soporte</span>
        <span class="usr-kpi kpi-cyan"><b>{{ $S['ventas']      ?? '—' }}</b> Ventas</span>
        <span class="usr-kpi kpi-amber"><b>{{ $S['superadmins'] ?? '—' }}</b> Superadmins</span>
      </div>
    </div>
    <div class="usr-head-right">
      @if (Route::has($routeBase.'.create'))
        <a class="btn btn-primary" href="{{ route($routeBase.'.create') }}">Nuevo</a>
      @endif
      @if ($USR_EP['export'])
        <a class="btn" href="{{ $USR_EP['export'] }}">Exportar</a>
      @endif
      <button class="btn btn-ghost" id="usrBotOpen" type="button">🤖 Asistente</button>
    </div>
  </div>

  {{-- FILTROS --}}
  <form class="usr-filters" method="GET" action="{{ route($routeBase.'.index') }}" id="usrFilters">
    <div class="usr-filter-row">
      <label class="usr-search">
        <input type="search" name="q" value="{{ $q }}" placeholder="Buscar por nombre o correo…" aria-label="Buscar">
      </label>

      <label>
        <span class="lbl">Rol</span>
        <select name="rol">
          <option value="">Todos</option>
          @foreach (($roles ?? []) as $r)
            <option value="{{ $r['value'] }}" @selected($rol===$r['value'])>{{ $r['label'] }}</option>
          @endforeach
        </select>
      </label>

      <label>
        <span class="lbl">Activo</span>
        {{-- aceptamos activo|ac para retrocompatibilidad; enviamos "activo" --}}
        <select name="activo">
          <option value=""  @selected($ac==='')>Todos</option>
          <option value="1" @selected($ac==='1')>Sí</option>
          <option value="0" @selected($ac==='0')>No</option>
        </select>
      </label>

      <label>
        <span class="lbl">Superadmin</span>
        <select name="sa">
          <option value=""  @selected($sa==='')>Todos</option>
          <option value="1" @selected($sa==='1')>Sí</option>
          <option value="0" @selected($sa==='0')>No</option>
        </select>
      </label>

      <label>
        <span class="lbl">Por página</span>
        <select name="pp">
          @foreach ([10,20,50,100] as $n)
            <option value="{{ $n }}" @selected($pp===$n)>{{ $n }}</option>
          @endforeach
        </select>
      </label>

      <input type="hidden" name="sort" value="{{ $sort }}">
      <input type="hidden" name="dir"  value="{{ $dir }}">

      <div class="usr-filters-actions">
        <button class="btn" type="submit">Aplicar</button>
        <a class="btn btn-ghost" href="{{ route($routeBase.'.index') }}">Limpiar</a>
      </div>
    </div>
  </form>
</div>
@endsection

@section('content')
<div class="usr-page">

  {{-- TOOLBAR DE SELECCIÓN MASIVA --}}
  <div class="usr-bulk" id="usrBulk" hidden>
    <div class="usr-bulk-left">
      <span class="usr-bulk-count"><b id="usrBulkCount">0</b> seleccionados</span>
      <button class="btn btn-secondary" data-bulk="activate">Activar</button>
      <button class="btn btn-secondary" data-bulk="deactivate">Desactivar</button>
      <button class="btn btn-secondary" data-bulk="force_on">Forzar cambio: Sí</button>
      <button class="btn btn-secondary" data-bulk="force_off">Forzar cambio: No</button>
      <button class="btn btn-danger" data-bulk="delete">Eliminar</button>
    </div>
    <div class="usr-bulk-right">
      <button class="btn btn-ghost" data-bulk="export">Exportar selección</button>
      <button class="btn" id="usrBulkClear">Limpiar selección</button>
    </div>
  </div>

  <div class="usr-card">
    <div class="usr-table-wrap">
      <table class="usr-table" id="usrTable">
        <thead>
          <tr>
            <th class="usr-col-select">
              <input type="checkbox" id="usrCheckAll" aria-label="Seleccionar todos">
            </th>
            <th>{!! usr_sort_link('nombre', 'Nombre', $routeBase, $sort, $dir) !!}</th>
            <th>{!! usr_sort_link('email',  'Correo', $routeBase, $sort, $dir) !!}</th>
            <th>{!! usr_sort_link('rol',    'Rol', $routeBase, $sort, $dir) !!}</th>
            <th>Superadmin</th>
            <th>Activo</th>
            <th>Forzar cambio</th>
            <th class="usr-actions">Acciones</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($items as $u)
            @php
              $role = strtolower((string) ($u->rol ?: 'admin'));
              $roleLabel = $role === 'ventas' ? 'Ventas' : ($role === 'soporte' ? 'Soporte' : 'Admin');
            @endphp
            <tr data-id="{{ $u->id }}">
              <td class="usr-col-select">
                <input type="checkbox" class="usr-check" value="{{ $u->id }}" aria-label="Seleccionar">
              </td>
              <td class="usr-name">
                <div class="usr-name-wrap">
                  <span class="usr-name-text" data-open-edit="{{ Route::has($routeBase.'.edit') ? route($routeBase.'.edit',$u->id) : '' }}">{{ $u->nombre }}</span>
                  <small class="usr-muted">ID: {{ $u->id }}</small>
                </div>
              </td>
              <td><code class="usr-email" data-copy="{{ $u->email }}" title="Copiar">{{ $u->email }}</code></td>
              <td><span class="usr-badge usr-role-{{ $role }}">{{ $roleLabel }}</span></td>

              {{-- Toggleables (si hay endpoint .toggle) --}}
              <td>
                <button class="chip-toggle {{ $u->es_superadmin ? 'on' : 'off' }}"
                        data-toggle="es_superadmin"
                        data-value="{{ $u->es_superadmin ? 1 : 0 }}"
                        @disabled(!$USR_EP['toggle'])>
                  {{ $u->es_superadmin ? 'Sí' : 'No' }}
                </button>
              </td>
              <td>
                <button class="chip-toggle {{ $u->activo ? 'on' : 'off' }}"
                        data-toggle="activo"
                        data-value="{{ $u->activo ? 1 : 0 }}"
                        @disabled(!$USR_EP['toggle'])>
                  {{ $u->activo ? 'Sí' : 'No' }}
                </button>
              </td>
              <td>
                <button class="chip-toggle warn {{ $u->force_password_change ? 'on' : 'off' }}"
                        data-toggle="force_password_change"
                        data-value="{{ $u->force_password_change ? 1 : 0 }}"
                        @disabled(!$USR_EP['toggle'])>
                  {{ $u->force_password_change ? 'Sí' : 'No' }}
                </button>
              </td>

              <td class="usr-actions">
                @if (Route::has($routeBase.'.edit'))
                  <a class="btn btn-small" href="{{ route($routeBase.'.edit',$u->id) }}">Editar</a>
                @endif
                @if (Route::has($routeBase.'.destroy'))
                  <form method="post" action="{{ route($routeBase.'.destroy',$u->id) }}" data-confirm="¿Eliminar usuario '{{ $u->email }}'?" style="display:inline">
                    @csrf @method('DELETE')
                    <button class="btn btn-small btn-danger" type="submit">Eliminar</button>
                  </form>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="8" class="usr-empty">Sin registros</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="usr-table-foot">
      <div class="usr-total">Mostrando {{ $items->count() }} de {{ number_format($items->total()) }}</div>
      <div class="usr-paginate">{{ $items->links() }}</div>
    </div>
  </div>
</div>

{{-- ASISTENTE / BOT lateral --}}
<aside id="usrBot" class="usr-bot" aria-hidden="true">
  <header class="bot-head">
    <div class="t">Asistente de Usuarios</div>
    <button class="bot-close" id="usrBotClose" aria-label="Cerrar">✕</button>
  </header>
  <div class="bot-body">
    <p class="bot-hint">Atajos rápidos:</p>
    <div class="bot-quick">
      <button class="bot-btn" data-bot="activos">Mostrar activos</button>
      <button class="bot-btn" data-bot="inactivos">Mostrar inactivos</button>
      <button class="bot-btn" data-bot="superadmins">Sólo superadmins</button>
      <button class="bot-btn" data-bot="ventas">Rol: Ventas</button>
      <button class="bot-btn" data-bot="soporte">Rol: Soporte</button>
      <button class="bot-btn" data-bot="exportar">Exportar resultado</button>
    </div>

    <div class="bot-input">
      <input id="usrBotInput" type="text" placeholder="Escribe: “activos”, “rol ventas”, “forzar=si”…">
      <button id="usrBotSend">Ir</button>
    </div>

    <div id="usrBotLog" class="bot-log" aria-live="polite"></div>
  </div>
</aside>
@endsection

@push('scripts')
<script>
  window.UsrCfg = {
    endpoints: @json($USR_EP, JSON_UNESCAPED_SLASHES),
    csrf: @json(csrf_token()),
    debug: @json(app()->environment('local')),
  };
</script>
<script defer src="{{ asset('assets/admin/js/usuarios.js') }}"></script>
<script>
/* Inyección CSS si se entra por PJAX */
(function(){
  var id='css-usuarios', href=@json($USR_CSS);
  function ensure(){ if(!document.getElementById(id)){ var l=document.createElement('link'); l.id=id;l.rel='stylesheet';l.href=href; document.head.appendChild(l);} }
  ensure(); addEventListener('p360:pjax:after', ensure);
})();
</script>
@endpush
