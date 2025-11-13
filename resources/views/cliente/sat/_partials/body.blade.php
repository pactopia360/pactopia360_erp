{{-- resources/views/cliente/sat/_partials/body.blade.php (v3.2 · seguros) --}}
@php
  $rows   = collect($initialRows ?? []);
  $sprite = asset('assets/client/icons.svg');

  $get = function($r, $key, $def = null) {
      if (is_array($r))  return $r[$key] ?? $def;
      if (is_object($r)) return $r->{$key} ?? $def;
      return $def;
  };
  $getCant = function($r) use ($get) {
      $v = $get($r,'cant', null);
      if ($v === null) $v = $get($r,'count', null);
      if ($v === null) $v = $get($r,'total', null);
      return (int) ($v ?? 0);
  };
  $estadoClass = function($estado) {
      $e = strtolower((string)$estado);
      return str_contains($e,'done') || str_contains($e,'ready') || str_contains($e,'listo') ? 'ok'
           : (str_contains($e,'error') || str_contains($e,'fail') ? 'err' : 'warn');
  };

  $rtZip   = \Route::has('cliente.sat.zip')       ? 'cliente.sat.zip'       : null;
  $rtDown  = \Route::has('cliente.sat.download')  ? 'cliente.sat.download'  : null;
@endphp

<div class="table-wrap" style="overflow:auto">
  <table class="table" aria-label="Últimas solicitudes SAT">
    <thead>
      <tr>
        <th>ID</th>
        <th>Request</th>
        <th>Tipo</th>
        <th>Desde</th>
        <th>Hasta</th>
        <th>#</th>
        <th>Estado</th>
        <th>Paquete</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      @forelse($rows as $r)
        @php
          $dlid      = (string) $get($r,'dlid','');
          $requestId = (string) $get($r,'request_id','');
          $tipo      = ucfirst((string) $get($r,'tipo',''));
          $desde     = (string) $get($r,'desde','');
          $hasta     = (string) $get($r,'hasta','');
          $cant      = $getCant($r);
          $estado    = (string) $get($r,'estado','');
          $pkg       = (string) $get($r,'package_id','');
          $badgeClass= $estadoClass($estado);
          $isReady   = in_array(strtolower($estado), ['done','ready','listo'], true) && $pkg !== '';
        @endphp
        <tr>
          <td>{{ $dlid ?: '—' }}</td>
          <td style="font-family:ui-monospace">{{ $requestId ?: '—' }}</td>
          <td>{{ $tipo ?: '—' }}</td>
          <td>{{ $desde ?: '—' }}</td>
          <td>{{ $hasta ?: '—' }}</td>
          <td>{{ $cant }}</td>
          <td><span class="badge {{ $badgeClass }}">{{ $estado ?: '—' }}</span></td>
          <td style="font-family:ui-monospace">{{ $pkg ?: '—' }}</td>
          <td>
            @if($isReady && $rtZip)
              <a class="btn sm" href="{{ route($rtZip, ['id' => $dlid]) }}">ZIP</a>
            @elseif($rtDown)
              <form method="post" action="{{ route($rtDown) }}" style="display:inline">
                @csrf
                <input type="hidden" name="download_id" value="{{ $dlid }}">
                <button class="btn sm">Intentar</button>
              </form>
            @else
              <span class="muted">—</span>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="9" style="text-align:center;color:var(--mut);font-weight:700">Sin registros aún.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<svg aria-hidden="true" style="position:absolute;width:0;height:0;overflow:hidden">
  <use href="{{ $sprite }}#file-spreadsheet"></use>
  <use href="{{ $sprite }}#plus"></use>
  <use href="{{ $sprite }}#refresh-cw"></use>
  <use href="{{ $sprite }}#download-cloud"></use>
</svg>
