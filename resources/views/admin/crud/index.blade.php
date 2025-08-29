@extends('layouts.admin')

@php
  // Si viene $moduleCss (ruta relativa pública), le hacemos cache-busting
  $MODULE_CSS_URL = null;
  if (!empty($moduleCss)) {
    $guess = public_path($moduleCss);
    $url   = asset($moduleCss);
    if (is_file($guess)) {
      $url .= (str_contains($url,'?')?'&v=':'?v=').filemtime($guess);
    }
    $MODULE_CSS_URL = $url;
  }
@endphp

@isset($MODULE_CSS_URL)
  @push('styles')
    <link id="css-crud-module" rel="stylesheet" href="{{ $MODULE_CSS_URL }}">
  @endpush
@endisset

@section('title', ($titles['index'] ?? 'Listado').' · Pactopia360')

@section('page-header')
  <h1 class="kpi-value" style="font-size:22px;margin:0">{{ $titles['index'] ?? 'Listado' }}</h1>
  @isset($routeBase)
    @if (Route::has($routeBase.'.create'))
      <a class="btn" href="{{ route($routeBase.'.create') }}" style="margin-top:8px">Nuevo</a>
    @endif
  @endisset
@endsection

@section('content')
  <div class="cards">
    <div class="card" style="grid-column: span 12">
      <div style="overflow:auto">
        <table class="table">
          <thead>
            <tr>
              @foreach ($fields as $f)
                @continue(in_array($f['type'] ?? '', ['password']))
                <th>{{ $f['label'] ?? $f['name'] }}</th>
              @endforeach
              @isset($routeBase)<th style="width:140px">Acciones</th>@endisset
            </tr>
          </thead>
          <tbody>
          @forelse ($items as $row)
            <tr>
              @foreach ($fields as $f)
                @php $n=$f['name']; @endphp
                @continue(in_array($f['type'] ?? '', ['password']))
                <td>
                  @php
                    $v = $row->{$n} ?? null;
                    if (($f['type'] ?? '')==='switch') $v = $v ? 'Sí' : 'No';
                    if ($v instanceof \Carbon\Carbon) $v = $v->format('Y-m-d H:i');
                  @endphp
                  {{ is_scalar($v) ? $v : json_encode($v) }}
                </td>
              @endforeach
              @isset($routeBase)
              <td>
                @if (Route::has($routeBase.'.edit'))
                  <a class="btn" href="{{ route($routeBase.'.edit',$row->id) }}">Editar</a>
                @endif
                @if (Route::has($routeBase.'.destroy'))
                  <form method="post" action="{{ route($routeBase.'.destroy',$row->id) }}" style="display:inline" onsubmit="return confirm('¿Eliminar?')">
                    @csrf @method('DELETE')
                    <button class="btn btn-danger" type="submit">Eliminar</button>
                  </form>
                @endif
              </td>
              @endisset
            </tr>
          @empty
            <tr><td colspan="{{ count($fields)+1 }}"><em class="muted">Sin registros</em></td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
      <div style="margin-top:8px">{{ $items->links() }}</div>
    </div>
  </div>
@endsection

@isset($MODULE_CSS_URL)
  @push('scripts')
  <script>
    // Ensure del CSS de módulo si navegas por PJAX
    (function(){
      var id='css-crud-module', href=@json($MODULE_CSS_URL);
      function ensure(){ if(!document.getElementById(id)){ var l=document.createElement('link'); l.id=id;l.rel='stylesheet';l.href=href; document.head.appendChild(l);} }
      ensure(); addEventListener('p360:pjax:after', ensure);
    })();
  </script>
  @endpush
@endisset
