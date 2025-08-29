@extends('layouts.admin')

@section('title', $title)

@section('page-header')
  <h1 class="h4 m-0">{{ $title }}</h1>
@endsection

@section('content')
  <div class="alert alert-warning">
    {{ $message ?? 'Pendiente de implementar.' }}
  </div>
@endsection
