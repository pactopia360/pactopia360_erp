@extends('layouts.admin')

@section('title','Usuarios · Administrativos')
@section('pageClass','p360-admin-usuarios-admin')
@section('contentLayout','full')

@push('styles')
  @php
    $CSS_ABS = public_path('assets/admin/css/usuarios-admin.css');
    $CSS_URL = asset('assets/admin/css/usuarios-admin.css') . (is_file($CSS_ABS) ? ('?v='.filemtime($CSS_ABS)) : '');
  @endphp
  <link rel="stylesheet" href="{{ $CSS_URL }}">
@endpush

@section('page-header')
  <div class="p360-ph">
    <div>
      <div class="p360-ph-kicker">PACTOPIA 360</div>
      <div class="p360-ph-title">Usuarios · Administrativos</div>
      <div class="p360-ph-sub">Altas, edición, permisos y control de acceso.</div>

      @php $debug = $debug ?? null; @endphp
      @if(is_array($debug))
        <div class="p360-debug">
          <span class="chip">conn: {{ $debug['conn'] ?? '—' }}</span>
          <span class="chip">db: {{ $debug['db'] ?? '—' }}</span>
          <span class="chip ok">total usuarios_admin: {{ $debug['total'] ?? '—' }}</span>

          @if(isset($debug['mysql_total']) || isset($debug['mysql_admin_total']))
            <span class="chip">mysql: {{ $debug['mysql_total'] ?? '—' }}</span>
            <span class="chip">mysql_admin: {{ $debug['mysql_admin_total'] ?? '—' }}</span>
          @endif
        </div>
      @endif
    </div>

    <div class="p360-ph-right">
      {{-- ✅ real: va a create --}}
      <a class="btnx primary" href="{{ route('admin.usuarios.administrativos.create') }}">+ Nuevo</a>
    </div>
  </div>
@endsection

@section('content')
  <div class="p360-card p360-card-wide">

    {{-- Flash --}}
    @if(session('ok'))
      <div class="p360-flash ok">{{ session('ok') }}</div>
    @endif
    @if(session('err'))
      <div class="p360-flash err">{{ session('err') }}</div>
    @endif

    <form method="GET" action="{{ route('admin.usuarios.administrativos.index') }}" class="p360-toolbar">
      <div class="field" style="min-width:320px">
        <label>Buscar</label>
        <input name="q" value="{{ $filters['q'] }}" placeholder="Nombre o email...">
      </div>

      <div class="field sm">
        <label>Rol</label>
        <select name="rol">
          <option value="todos" @selected($filters['rol']==='todos')>Todos</option>
          @foreach($roles as $r)
            <option value="{{ $r }}" @selected($filters['rol']===$r)>{{ $r }}</option>
          @endforeach
        </select>
      </div>

      <div class="field sm">
        <label>Estado</label>
        <select name="estado">
          <option value=""  @selected($filters['estado']==='')>Todos</option>
          <option value="1" @selected($filters['estado']==='1')>Activos</option>
          <option value="0" @selected($filters['estado']==='0')>Inactivos</option>
        </select>
      </div>

      <div class="field sm">
        <label>Por página</label>
        <select name="perPage">
          @foreach([25,50,100,250,500] as $n)
            <option value="{{ $n }}" @selected((int)$filters['perPage']===$n)>{{ $n }}</option>
          @endforeach
        </select>
      </div>

      <div class="actions">
        <button class="btnx primary" type="submit">Aplicar</button>
        <a class="btnx ghost" href="{{ route('admin.usuarios.administrativos.index') }}">Limpiar</a>
      </div>
    </form>

    <div class="p360-table-wrap">
      <table class="p360-table">
        <thead>
          <tr>
            <th style="width:110px">ID</th>
            <th style="min-width:240px">Nombre</th>
            <th style="min-width:280px">Email</th>
            <th style="width:180px">Rol</th>
            <th style="width:140px">Estado</th>
            <th style="width:190px">Último login</th>
            <th style="width:240px">Acciones</th>
          </tr>
        </thead>
        <tbody>
          @forelse($rows as $u)
            <tr>
              <td class="mono">{{ $u->id }}</td>
              <td>
                <div class="title">
                  {{ $u->nombre ?? '(Sin nombre)' }}
                  @if((int)($u->es_superadmin ?? 0) === 1)
                    <span class="chip ok" style="margin-left:8px">Super</span>
                  @endif
                </div>
                <div class="muted">IP: {{ $u->last_login_ip ?? '—' }}</div>
              </td>
              <td class="mono">{{ $u->email }}</td>
              <td><span class="chip">{{ $u->rol ?: '—' }}</span></td>
              <td>
                @if((int)($u->activo ?? 0) === 1)
                  <span class="chip ok">Activo</span>
                @else
                  <span class="chip off">Inactivo</span>
                @endif
              </td>
              <td class="mono">
                {{ $u->last_login_at ? \Illuminate\Support\Carbon::parse($u->last_login_at)->format('Y-m-d H:i') : '—' }}
              </td>
              <td>
                <div class="row-actions" style="gap:8px; flex-wrap:wrap">
                  {{-- ✅ real: editar --}}
                  <a class="btnx sm" href="{{ route('admin.usuarios.administrativos.edit', (int)$u->id) }}">Editar</a>

                  {{-- ✅ “Permisos”: por ahora manda al mismo edit y hace foco visual (anchor) --}}
                  <a class="btnx sm warn" href="{{ route('admin.usuarios.administrativos.edit', (int)$u->id) }}#permisos">Permisos</a>

                  {{-- ✅ toggle activo/inactivo --}}
                  <form method="POST" action="{{ route('admin.usuarios.administrativos.toggle', (int)$u->id) }}" class="inline"
                        onsubmit="return confirm('¿Seguro que deseas {{ (int)($u->activo ?? 0)===1 ? 'desactivar' : 'activar' }} a este usuario?');">
                    @csrf
                    <button class="btnx sm {{ (int)($u->activo ?? 0)===1 ? 'danger' : '' }}" type="submit">
                      {{ (int)($u->activo ?? 0)===1 ? 'Desactivar' : 'Activar' }}
                    </button>
                  </form>

                  {{-- ✅ eliminar (opcional) --}}
                  <form method="POST" action="{{ route('admin.usuarios.administrativos.destroy', (int)$u->id) }}" class="inline"
                        onsubmit="return confirm('¿Eliminar usuario #{{ (int)$u->id }}? Esta acción no se puede deshacer.');">
                    @csrf
                    @method('DELETE')
                    <button class="btnx sm" type="submit">Eliminar</button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="empty">Sin resultados.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="p360-foot">
      <div class="muted">Mostrando {{ $rows->count() }} de {{ $rows->total() }}</div>
      <div>{{ $rows->links() }}</div>
    </div>

  </div>

  <style>
    /* Flash básico si no existe en CSS */
    .p360-flash{margin:0 0 12px;padding:10px 12px;border-radius:12px;font-weight:600}
    .p360-flash.ok{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
    .p360-flash.err{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
    form.inline{display:inline}
  </style>
@endsection
