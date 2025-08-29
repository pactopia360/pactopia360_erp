@extends('layouts.admin')

@php
  use Illuminate\Support\Facades\Route as R;

  $title = $titles['index'] ?? 'Perfiles';
  $F   = $filters ?? [];
  $q   = $F['q']   ?? '';
  $ac  = $F['ac']  ?? '';
  $sort= $F['sort']?? 'created_at';
  $dir = $F['dir'] ?? 'desc';
  $pp  = (int)($F['pp'] ?? 20);

  if (!function_exists('p360_safe_route')) {
    function p360_safe_route(?string $name, array $params = []) {
      return $name && \Illuminate\Support\Facades\Route::has($name) ? route($name, $params) : null;
    }
  }

  if (!function_exists('prf_sort_link')) {
    function prf_sort_link($col, $label, $routeBase, $sort, $dir) {
      $next = ($sort===$col && $dir==='asc') ? 'desc' : 'asc';
      $params = array_merge(request()->query(), ['sort'=>$col, 'dir'=>$next]);
      $url = route($routeBase.'.index', $params);
      $indicator = $sort===$col ? ($dir==='asc'?'▲':'▼') : '';
      return '<a href="'.$url.'" class="prf-sort">'.$label.' '.$indicator.'</a>';
    }
  }

    // Endpoints opcionales (sin inspect para no confundir)
  $PRF_EP = [
    'bulk'       => p360_safe_route($routeBase.'.bulk'),
    'toggle'     => p360_safe_route($routeBase.'.toggle'),
    'export'     => p360_safe_route($routeBase.'.export', request()->query()),
    'perm_get'   => p360_safe_route($routeBase.'.permissions'),       // GET ?id=
    'perm_save'  => p360_safe_route($routeBase.'.permissions.save'),  // POST {id, perms[]}
  ];

@endphp

@section('title', $title.' · Pactopia360')

@push('styles')
@php
  $PRF_CSS = asset('assets/admin/css/perfiles.css');
  try { $abs = public_path('assets/admin/css/perfiles.css'); if (is_file($abs)) { $PRF_CSS .= (str_contains($PRF_CSS,'?')?'&v=':'?v=').filemtime($abs); } } catch (\Throwable $e) {}
@endphp
<link id="css-perfiles" rel="stylesheet" href="{{ $PRF_CSS }}">
@endpush

@section('page-header')
<div class="prf-page">
  <div class="prf-header">
    <div class="prf-head-left">
      <h1 class="prf-title">{{ $title }}</h1>
      <div class="prf-counters">
        @php $S = $stats ?? []; @endphp
        <span class="prf-kpi"><b>{{ $S['total'] ?? number_format($items->total()) }}</b> Total</span>
        <span class="prf-kpi kpi-green"><b>{{ $S['activos'] ?? '—' }}</b> Activos</span>
        <span class="prf-kpi kpi-amber"><b>{{ $S['promedio_permisos'] ?? '—' }}</b> Permisos/prom.</span>
      </div>
    </div>
    <div class="prf-head-right">
      @if (Route::has($routeBase.'.create'))
        <a class="btn btn-primary" href="{{ route($routeBase.'.create') }}">Nuevo</a>
      @endif
      @if ($PRF_EP['export'])
        <a class="btn" href="{{ $PRF_EP['export'] }}">Exportar</a>
      @endif
      <button class="btn btn-ghost" id="prfBotOpen" type="button">🤖 Asistente</button>
    </div>
  </div>

  {{-- FILTROS --}}
  <form id="prfFilters" class="prf-filters" method="GET" action="{{ route($routeBase.'.index') }}">
    <div class="prf-filter-row">
      <label class="prf-search">
        <input type="search" name="q" value="{{ $q }}" placeholder="Buscar por clave o nombre…" aria-label="Buscar">
      </label>
      <label>
        <span class="lbl">Activo</span>
        <select name="ac">
          <option value=""  @selected($ac==='')>Todos</option>
          <option value="1" @selected($ac==='1')>Sí</option>
          <option value="0" @selected($ac==='0')>No</option>
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

      <div class="prf-filters-actions">
        <button class="btn" type="submit">Aplicar</button>
        <a class="btn btn-ghost" href="{{ route($routeBase.'.index') }}">Limpiar</a>
      </div>
    </div>
  </form>
</div>
@endsection

@section('content')
<div class="prf-page">

  {{-- TOOLBAR SELECCIÓN MASIVA --}}
  <div class="prf-bulk" id="prfBulk" hidden>
    <div class="prf-bulk-left">
      <span class="prf-bulk-count"><b id="prfBulkCount">0</b> seleccionados</span>
      <button class="btn btn-secondary" data-bulk="activate">Activar</button>
      <button class="btn btn-secondary" data-bulk="deactivate">Desactivar</button>
      <button class="btn btn-danger" data-bulk="delete">Eliminar</button>
    </div>
    <div class="prf-bulk-right">
      <button class="btn btn-ghost" data-bulk="export">Exportar selección</button>
      <button class="btn" id="prfBulkClear">Limpiar selección</button>
    </div>
  </div>

  <div class="prf-card">
    <div class="prf-table-wrap">
      <table class="prf-table" id="prfTable">
        <thead>
          <tr>
            <th class="prf-col-select"><input type="checkbox" id="prfCheckAll" aria-label="Seleccionar todos"></th>
            <th>{!! prf_sort_link('clave', 'Clave', $routeBase, $sort, $dir) !!}</th>
            <th>{!! prf_sort_link('nombre','Nombre', $routeBase, $sort, $dir) !!}</th>
            <th>Descripción</th>
            <th>Activo</th>
            <th>Permisos</th>
            <th class="prf-actions">Acciones</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($items as $p)
            <tr data-id="{{ $p->id }}">
              <td class="prf-col-select"><input type="checkbox" class="prf-check" value="{{ $p->id }}"></td>
              <td><code class="prf-key" data-copy="{{ $p->clave }}">{{ $p->clave }}</code></td>
              <td class="prf-name">
                <div class="prf-name-wrap">
                  <span class="prf-name-text" data-open-edit="{{ Route::has($routeBase.'.edit') ? route($routeBase.'.edit',$p->id) : '' }}">{{ $p->nombre }}</span>
                  <small class="prf-muted">ID: {{ $p->id }}</small>
                </div>
              </td>
              <td class="prf-desc">{{ $p->descripcion ?: '—' }}</td>
              <td>
                <button class="chip-toggle {{ $p->activo ? 'on':'off' }}"
                        data-toggle="activo"
                        data-value="{{ $p->activo ? 1:0 }}"
                        @disabled(!$PRF_EP['toggle'])>
                  {{ $p->activo ? 'Sí':'No' }}
                </button>
              </td>
              <td>
                <button class="btn btn-small prf-perms-btn"
                        data-perms="{{ $p->id }}"
                        @disabled(!$PRF_EP['perm_get'])>
                  Gestionar ({{ $p->permisos_count ?? '—' }})
                </button>
              </td>
              <td class="prf-actions">
                @if (Route::has($routeBase.'.edit'))
                  <a class="btn btn-small" href="{{ route($routeBase.'.edit',$p->id) }}">Editar</a>
                @endif
                @if (Route::has($routeBase.'.destroy'))
                  <form method="post" action="{{ route($routeBase.'.destroy',$p->id) }}" data-confirm="¿Eliminar perfil '{{ $p->nombre }}'?" style="display:inline">
                    @csrf @method('DELETE')
                    <button class="btn btn-small btn-danger" type="submit">Eliminar</button>
                  </form>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="prf-empty">Sin registros</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="prf-table-foot">
      <div class="prf-total">Mostrando {{ $items->count() }} de {{ number_format($items->total()) }}</div>
      <div class="prf-paginate">{{ $items->links() }}</div>
    </div>
  </div>
</div>

{{-- MODAL: Editor de Permisos --}}
<div id="prfPermsModal" class="prf-modal" aria-hidden="true">
  <div class="prf-backdrop" data-close></div>
  <div class="prf-modal-card" role="dialog" aria-modal="true" aria-labelledby="prfPermsTitle">
    <div class="prf-modal-head">
      <div>
        <div class="prf-modal-title" id="prfPermsTitle">Permisos — <span id="prfPermsProfile">#</span></div>
        <div class="prf-modal-sub">Selecciona permisos por grupo o individualmente</div>
      </div>
      <button class="prf-modal-close" type="button" data-close aria-label="Cerrar">✕</button>
    </div>

    <div class="prf-perms-toolbar">
      <div class="search">
        <span aria-hidden="true">🔍</span>
        <input type="text" id="prfPermsSearch" placeholder="Buscar permiso…">
      </div>
      <div class="spacer"></div>
      <button class="btn" id="prfPermsAll">Seleccionar todo</button>
      <button class="btn" id="prfPermsNone">Quitar todo</button>
      <button class="btn btn-primary" id="prfPermsSave" disabled>Guardar</button>
    </div>

    <div class="prf-modal-body">
      <div id="prfPermsGroups" class="prf-groups">
        {{-- RENDER DINÁMICO (JS) --}}
      </div>
    </div>
  </div>
</div>

{{-- BOT lateral --}}
<aside id="prfBot" class="prf-bot" aria-hidden="true">
  <header class="bot-head">
    <div class="t">Asistente de Perfiles</div>
    <button class="bot-close" id="prfBotClose" aria-label="Cerrar">✕</button>
  </header>
  <div class="bot-body">
    <p class="bot-hint">Atajos:</p>
    <div class="bot-quick">
      <button class="bot-btn" data-bot="activos">Mostrar activos</button>
      <button class="bot-btn" data-bot="inactivos">Mostrar inactivos</button>
      <button class="bot-btn" data-bot="sin_permisos">Sin permisos</button>
      <button class="bot-btn" data-bot="exportar">Exportar</button>
    </div>
    <div class="bot-input">
      <input id="prfBotInput" type="text" placeholder="Escribe: “activos”, “buscar ventas”, “pp=50”…">
      <button id="prfBotSend">Ir</button>
    </div>
    <div id="prfBotLog" class="bot-log" aria-live="polite"></div>
  </div>
</aside>
@endsection

@push('scripts')
<script>
  window.PrfCfg = {
    endpoints: @json($PRF_EP, JSON_UNESCAPED_SLASHES),
    csrf: @json(csrf_token()),
    debug: @json(app()->environment('local')),
  };
</script>
<script defer src="{{ asset('assets/admin/js/perfiles.js') }}"></script>
<script>
  // Asegurar CSS al entrar por PJAX
  (function(){var id='css-perfiles',href=@json($PRF_CSS);function ensure(){if(!document.getElementById(id)){var l=document.createElement('link');l.id=id;l.rel='stylesheet';l.href=href;document.head.appendChild(l);}}ensure();addEventListener('p360:pjax:after',ensure);})();
</script>
@endpush
