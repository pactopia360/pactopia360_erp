{{-- resources/views/admin/empresas/waretek-mx/dashboard.blade.php --}}
@php
  // Detecta automáticamente un layout base disponible
  $__candidates = [
    'admin.layouts.app',
    'layouts.app',
    'layouts.admin',
    'layouts.master',
    'layouts.main',
    'admin.layout',
  ];
  $__layout = null;
  foreach ($__candidates as $__c) { if (view()->exists($__c)) { $__layout = $__c; break; } }
@endphp

@extends($__layout ?? 'admin.layouts.app')
@section('title', 'Waretek México · Dashboard')

@php use Illuminate\Support\Facades\Route; @endphp

@section('content')
<link rel="stylesheet" href="{{ asset('assets/admin/css/modules/empresas.css') }}">

<div class="page mod-empresa waretek-mx" data-module="empresa" data-empresa="waretek-mx">
  <div class="hero">
    <span class="mod-ribbon">Empresa</span>
    <div class="title">{{ $empresa['name'] ?? 'Waretek México' }}</div>
    <div class="desc">{{ $empresa['desc'] ?? 'Backoffice y finanzas de Waretek MX.' }}</div>
    <div class="chips">
      {{-- CRM (si existe la ruta de contactos) --}}
      @if (Route::has('admin.empresas.waretek-mx.crm.contactos.index'))
        <a href="{{ route('admin.empresas.waretek-mx.crm.contactos.index') }}" class="btn-ghost">CRM</a>
      @else
        <span class="btn-ghost is-disabled" title="Próximamente">CRM</span>
      @endif

      <span class="btn-ghost is-disabled" title="Próximamente">Contabilidad</span>
      <span class="btn-ghost is-disabled" title="Próximamente">Bancos</span>
      <span class="btn-ghost is-disabled" title="Próximamente">CxP</span>
      <span class="btn-ghost is-disabled" title="Próximamente">CxC</span>
      <button class="btn-ghost" type="button" onclick="try{NovaBot.open()}catch{}">Robots</button>
    </div>
  </div>

  <div class="grid">
    <div class="card span-4">
      <h3>Proveedores activos</h3>
      <div class="kpi"><div class="v">—</div><div class="s">Últimos 30 días</div></div>
    </div>
    <div class="card span-4">
      <h3>Flujo bancos (mes)</h3>
      <div class="kpi"><div class="v">$ —</div><div class="s">MXN</div></div>
    </div>
    <div class="card span-4">
      <h3>Gastos del mes</h3>
      <div class="kpi"><div class="v">$ —</div><div class="s">MXN</div></div>
    </div>
  </div>
</div>
@endsection
