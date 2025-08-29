@extends('layouts.admin')

@section('title','Búsqueda')

@section('content')
  <div class="container">
    <h1>Búsqueda</h1>
    <form class="mb-3" method="get" action="{{ route('admin.search') }}">
      <input class="form-control" type="search" name="q" value="{{ $q }}" placeholder="Busca clientes, pagos o CFDI…">
    </form>

    @if($q==='') <p class="text-muted">Escribe algo para buscar.</p> @endif

    @if(!empty($results['clientes']))
      <h3>Clientes</h3>
      <ul>
        @foreach($results['clientes'] as $r)
          <li>#{{ $r->id }} — {{ $r->razon_social ?? $r->nombre ?? $r->nombre_comercial ?? 'Cliente' }}</li>
        @endforeach
      </ul>
    @endif

    @if(!empty($results['pagos']))
      <h3>Pagos</h3>
      <ul>
        @foreach($results['pagos'] as $r)
          <li>#{{ $r->id }} — {{ $r->fecha ?? '' }} — ${{ number_format($r->monto ?? 0,2) }}</li>
        @endforeach
      </ul>
    @endif

    @if(!empty($results['cfdi']))
      <h3>CFDI</h3>
      <ul>
        @foreach($results['cfdi'] as $r)
          <li>#{{ $r->id }} — {{ $r->uuid ?? ($r->serie ?? '').($r->folio ?? '') }}</li>
        @endforeach
      </ul>
    @endif
  </div>
@endsection
