{{-- resources/views/admin/empresas/pactopia360/crm/carritos/_show_inner.blade.php --}}
@php
  // Normaliza nombre de variable ($row o $carrito)
  $row = $row ?? $carrito ?? null;

  // Normaliza etiquetas/meta (pueden venir como JSON string o null)
  $tags = $row?->etiquetas ?? [];
  if (is_string($tags)) { $tags = json_decode($tags, true) ?: ($tags ? [$tags] : []); }

  $meta = $row?->meta ?? $row?->metadata ?? null;
  if (is_string($meta)) { $meta = json_decode($meta, true); }
@endphp

@if(!$row)
  <div class="alert alert-danger">No se recibió el carrito a mostrar.</div>
@else
  <h2 style="margin:0 0 10px">Carrito #{{ $row->id }}</h2>

  <dl class="def">
    <div><dt>ID</dt><dd>{{ $row->id }}</dd></div>
    <div><dt>Título</dt><dd>{{ e($row->titulo) }}</dd></div>
    <div><dt>Estado</dt><dd>{{ ucfirst($row->estado) }}</dd></div>
    <div><dt>Total</dt><dd>${{ number_format((float)($row->total ?? 0), 2) }} {{ $row->moneda }}</dd></div>

    @if(!empty($row->cliente))
      <div><dt>Cliente</dt><dd>{{ e($row->cliente) }}</dd></div>
    @endif
    @if(!empty($row->email))
      <div><dt>Email</dt><dd>{{ e($row->email) }}</dd></div>
    @endif
    @if(!empty($row->telefono))
      <div><dt>Teléfono</dt><dd>{{ e($row->telefono) }}</dd></div>
    @endif
    @if(!empty($row->origen))
      <div><dt>Origen</dt><dd>{{ e($row->origen) }}</dd></div>
    @endif

    @if(!empty($tags))
      <div style="grid-column:1/-1"><dt>Etiquetas</dt><dd>{{ implode(', ', (array)$tags) }}</dd></div>
    @endif

    @if(!empty($row->notas))
      <div style="grid-column:1/-1"><dt>Notas</dt><dd>{!! nl2br(e($row->notas)) !!}</dd></div>
    @endif

    <div><dt>Creado</dt><dd>{{ optional($row->created_at)->format('Y-m-d H:i') }}</dd></div>
    <div><dt>Actualizado</dt><dd>{{ optional($row->updated_at)->format('Y-m-d H:i') }}</dd></div>

    @if(!empty($meta))
      <div style="grid-column:1/-1">
        <dt>Meta</dt>
        <dd><pre class="pre">{{ json_encode($meta, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></dd>
      </div>
    @endif
  </dl>

  <div style="margin-top:12px;display:flex;gap:8px">
    <a class="btn" href="{{ route('admin.empresas.pactopia360.crm.carritos.edit', $row->id) }}">Editar</a>
    <a class="btn-ghost" href="{{ route('admin.empresas.pactopia360.crm.carritos.index') }}">Volver</a>
  </div>
@endif

<style>
  .def{display:grid;grid-template-columns:160px 1fr;gap:6px 12px;margin:10px 0}
  .def dt{font-weight:600;color:#475569}
  .def .pre{padding:10px;border-radius:8px;background:rgba(0,0,0,.05);overflow:auto}
  html.theme-dark .def .pre{background:rgba(255,255,255,.08)}
</style>
