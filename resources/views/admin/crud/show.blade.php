@extends('layouts.admin')

@isset($moduleCss)
  @push('styles')
    <link rel="stylesheet" href="{{ asset($moduleCss) }}">
  @endpush
@endisset
@isset($moduleJs)
  @push('scripts')
    <script defer src="{{ asset($moduleJs) }}"></script>
  @endpush
@endisset

@php
  $title = ($titles['show'] ?? $titles['index'] ?? 'Detalle');
@endphp

@section('title', $title.' · Pactopia360')

@section('page-header')
  <div style="display:flex;align-items:center;justify-content:space-between;gap:12px">
    <h1 class="kpi-value" style="font-size:22px;margin:0">{{ $title }}</h1>
    <div>
      <a class="btn" href="{{ route($routeBase.'.index') }}">Volver</a>
      @if (Route::has($routeBase.'.edit'))
        <a class="btn" href="{{ route($routeBase.'.edit', $item->getKey()) }}">Editar</a>
      @endif
    </div>
  </div>
@endsection

@section('content')
  <div class="cards">
    <div class="card" style="grid-column: span 12">
      <div style="overflow:auto">
        <table class="table">
          <tbody>
          @foreach ($fields as $f)
            @php
              $name = $f['name'];
              $label = $f['label'] ?? $name;
              $val = $item->{$name} ?? null;
              if (($f['type'] ?? '')==='switch') $val = $val ? 'Sí' : 'No';
              if ($val instanceof \Carbon\Carbon) $val = $val->format('Y-m-d H:i');
            @endphp
            <tr>
              <th style="width:260px">{{ $label }}</th>
              <td>
                @if (is_scalar($val) || is_null($val))
                  <code>{{ (string)($val ?? '') }}</code>
                @else
                  <pre class="mb-0">{{ json_encode($val, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) }}</pre>
                @endif
              </td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
@endsection
