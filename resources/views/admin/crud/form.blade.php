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

@php $isEdit = (bool) $item; @endphp
@section('title', ($isEdit ? ($titles['edit'] ?? 'Editar') : ($titles['create'] ?? 'Crear')) . ' · Pactopia360')

@section('page-header')
  <h1 class="kpi-value" style="font-size:22px;margin:0">
    {{ $isEdit ? ($titles['edit'] ?? 'Editar') : ($titles['create'] ?? 'Crear') }}
  </h1>
@endsection

@section('content')
  <div class="cards">
    <div class="card" style="grid-column: span 12">
      <form method="post" action="{{ $isEdit ? route($routeBase.'.update',$item->id) : route($routeBase.'.store') }}">
        @csrf
        @if($isEdit) @method('PUT') @endif

        <div class="row" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px">
          @foreach ($fields as $f)
            @php
              $name = $f['name']; $type = $f['type'] ?? 'text';
              if (($f['only'] ?? null)==='create' && $isEdit) continue;
              if (($f['only'] ?? null)==='edit' && !$isEdit) continue;
              $val = old($name, $item->{$name} ?? '');
            @endphp
            <label class="field" @if(($f['full'] ?? false)) style="grid-column: span 2" @endif>
              <span class="label">{{ $f['label'] ?? $name }}</span>

              @if (in_array($type, ['text','email','number','password','datetime']))
                <input
                  type="{{ $type==='datetime' ? 'datetime-local' : $type }}"
                  name="{{ $name }}"
                  value="{{ $type==='password' ? '' : ($type==='datetime' && $val ? \Illuminate\Support\Carbon::parse($val)->format('Y-m-d\TH:i') : $val) }}"
                  @if(isset($f['step'])) step="{{ $f['step'] }}" @endif
                >
              @elseif ($type === 'textarea')
                <textarea name="{{ $name }}" rows="3">{{ $val }}</textarea>
              @elseif ($type === 'switch')
                <input type="hidden" name="{{ $name }}" value="0">
                <label style="display:flex;align-items:center;gap:8px">
                  <input type="checkbox" name="{{ $name }}" value="1" @checked((bool)$val)>
                  <span>Activar</span>
                </label>
              @elseif ($type === 'select' || $type === 'multiselect')
                @php $opts = $f['options'] ?? []; @endphp
                <select name="{{ $name }}{{ $type==='multiselect' ? '[]' : '' }}" @if($type==='multiselect') multiple @endif>
                  @foreach ($opts as $op)
                    <option value="{{ $op['value'] }}" @selected(collect(old($name, $val))->contains($op['value']))>
                      {{ $op['label'] }}
                    </option>
                  @endforeach
                </select>
              @else
                <input type="text" name="{{ $name }}" value="{{ $val }}">
              @endif

              @error($name)
                <small class="text-danger">{{ $message }}</ }}</small>
              @enderror
            </label>
          @endforeach
        </div>

        <div style="margin-top:12px;display:flex;gap:8px">
          <button class="btn" type="submit">Guardar</button>
          <a class="btn" href="{{ route($routeBase.'.index') }}">Volver</a>
        </div>
      </form>
    </div>
  </div>
@endsection
