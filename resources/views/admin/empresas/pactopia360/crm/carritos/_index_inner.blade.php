{{-- resources/views/admin/empresas/pactopia360/crm/carritos/_index_inner.blade.php --}}

@php
  // Compatibilidad con nombres que pudieran venir del controlador
  $rows    = $rows    ?? $carritos    ?? null;
  $q       = isset($q) ? (string)$q : (string) request('q', '');
  $est     = $est     ?? ($estado ?? (string) request('estado', ''));
  $estados = $estados ?? (\App\Models\Empresas\Pactopia360\CRM\Carrito::ESTADOS ?? []);

  // Si $estados llega como lista simple, la convertimos a [valor => Label]
  $estadoMap = [];
  foreach ($estados as $e) { $estadoMap[$e] = ucfirst($e); }
@endphp

@if(session('ok'))
  <div class="alert alert-success">{{ session('ok') }}</div>
@endif

<div class="toolbar" style="display:flex;gap:10px;align-items:center;justify-content:space-between;margin-bottom:12px">
  <form method="get" action="{{ route('admin.empresas.pactopia360.crm.carritos.index') }}" style="display:flex;gap:8px">
    <input type="search" name="q" value="{{ $q }}" placeholder="Buscar…" />
    <select name="estado">
      <option value="">-- Estado --</option>
      @foreach($estadoMap as $k => $v)
        <option value="{{ $k }}" @selected($k === $est)>{{ $v }}</option>
      @endforeach
    </select>
    <button type="submit">Filtrar</button>
    @if(request()->hasAny(['q','estado']))
      <a href="{{ route('admin.empresas.pactopia360.crm.carritos.index') }}">Limpiar</a>
    @endif
  </form>

  <a href="{{ route('admin.empresas.pactopia360.crm.carritos.create') }}" class="btn">+ Nuevo carrito</a>
</div>

<div class="table-wrap" style="overflow:auto">
  <table border="1" cellpadding="6" cellspacing="0" style="width:100%;border-collapse:collapse">
    <thead>
      <tr>
        <th style="width:70px">ID</th>
        <th>Título</th>
        <th style="width:130px">Estado</th>
        <th style="width:140px">Total</th>
        <th style="width:90px">Moneda</th>
        <th style="width:160px">Creado</th>
        <th style="width:160px"></th>
      </tr>
    </thead>
    <tbody>
    @forelse($rows as $r)
      <tr>
        <td>{{ $r->id }}</td>
        <td>
          <a href="{{ route('admin.empresas.pactopia360.crm.carritos.show', $r->id) }}">
            {{ $r->titulo ?? $r->display_title ?? ('Carrito #'.$r->id) }}
          </a>
          @if(!empty($r->cliente))
            <div style="font-size:12px;color:#64748b">{{ $r->cliente }}</div>
          @endif
        </td>
        <td>{{ ucfirst($r->estado) }}</td>
        <td>${{ number_format((float)$r->total, 2) }}</td>
        <td>{{ $r->moneda }}</td>
        <td>{{ optional($r->created_at)->format('Y-m-d H:i') }}</td>
        <td style="white-space:nowrap">
          <a href="{{ route('admin.empresas.pactopia360.crm.carritos.edit', $r->id) }}">Editar</a>
          <form action="{{ route('admin.empresas.pactopia360.crm.carritos.destroy', $r->id) }}"
                method="post" style="display:inline"
                onsubmit="return confirm('¿Eliminar carrito?')">
            @csrf @method('DELETE')
            <button type="submit">Eliminar</button>
          </form>
        </td>
      </tr>
    @empty
      <tr><td colspan="7">Sin resultados.</td></tr>
    @endforelse
    </tbody>
  </table>
</div>

@if(method_exists($rows, 'links'))
  <div class="pager" style="margin-top:10px">
    {{ $rows->links() }}
  </div>
@endif
