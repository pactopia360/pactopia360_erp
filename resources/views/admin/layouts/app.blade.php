{{-- resources/views/admin/layouts/app.blade.php --}}
{{-- Layout alias para compatibilidad con vistas antiguas que hacen @extends('admin.layouts.app') --}}
{{-- Reutiliza el layout base "layouts.admin" y pasa secciones de forma segura --}}

@extends('layouts.admin')

{{-- Título: si la vista hija define "title", úsalo; si no, usa el nombre de la app --}}
@section('title', View::yieldContent('title', config('app.name', 'PACTOPIA 360')))

{{-- Contenido principal tal cual --}}
@section('content')
  @yield('content')
@endsection

{{-- Si la vista hija define "breadcrumb", mapealo a "page-header" (usado en layouts.admin) --}}
@if (View::hasSection('breadcrumb'))
  @section('page-header')
    @yield('breadcrumb')
  @endsection
@endif
