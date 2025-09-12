{{-- resources/views/admin/empresas/pactopia360/crm/layout.blade.php --}}
@php
  // Detecta automáticamente un layout base disponible en tu proyecto
  $__candidates = [
    'admin.layouts.app',
    'layouts.app',
    'layouts.admin',
    'layouts.master',
    'layouts.main',
    'admin.layout',
  ];
  $__layout = null;
  foreach ($__candidates as $__c) {
    if (view()->exists($__c)) { $__layout = $__c; break; }
  }
@endphp

@extends($__layout ?? 'admin.layouts.app')

@section('title', trim($__env->yieldContent('title', 'Pactopia360 · CRM')))

@section('content')
  <link rel="stylesheet" href="{{ asset('assets/admin/css/modules/crm.css') }}">

  <div class="page crm-wrap" data-module="crm" data-empresa="pactopia360">
    <div class="crm-hero">
      <div class="t">CRM · Pactopia360</div>
      <div class="s">Gestión de contactos y relaciones</div>
    </div>

    {{-- Zona que rellenan las vistas hijas (index/create/edit/etc.) --}}
    @yield('crm')
  </div>
@endsection
