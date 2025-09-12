{{-- resources/views/admin/layouts/app.blade.php --}}
@extends('layouts.admin')

@section('title')
  @hasSection('title') @yield('title') @else {{ config('app.name','PACTOPIA 360') }} @endif
@endsection

@section('content') @yield('content') @endsection

@hasSection('breadcrumb')
  @section('breadcrumb') @yield('breadcrumb') @endsection
@endif
