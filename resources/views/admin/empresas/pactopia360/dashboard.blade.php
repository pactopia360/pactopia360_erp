{{-- resources/views/admin/empresas/pactopia360/dashboard.blade.php --}}
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
@section('title', 'Pactopia360 · Dashboard')

@php use Illuminate\Support\Facades\Route; @endphp

@section('content')
<link rel="stylesheet" href="{{ asset('assets/admin/css/modules/empresas.css') }}">

<div class="page mod-empresa pactopia360" data-module="empresa" data-empresa="pactopia360">
  <div class="hero">
    <span class="mod-ribbon">Empresa</span>
    <div class="title">{{ $empresa['name'] ?? 'Pactopia360' }}</div>
    <div class="desc">{{ $empresa['desc'] ?? 'Panel administrativo central de Pactopia360.' }}</div>
    <div class="chips">
      {{-- CRM (lista de contactos) --}}
      @if (Route::has('admin.empresas.pactopia360.crm.contactos.index'))
        <a href="{{ route('admin.empresas.pactopia360.crm.contactos.index') }}" class="btn-ghost">CRM</a>
      @else
        <span class="btn-ghost is-disabled" title="Próximamente">CRM</span>
      @endif

      {{-- Carritos (CRM) --}}
      @if (Route::has('admin.empresas.pactopia360.crm.carritos.index'))
        <a href="{{ route('admin.empresas.pactopia360.crm.carritos.index') }}" class="btn-ghost">Carritos</a>
      @endif

      <span class="btn-ghost is-disabled" title="Próximamente">CxP</span>
      <span class="btn-ghost is-disabled" title="Próximamente">CxC</span>
      <span class="btn-ghost is-disabled" title="Próximamente">Facturación</span>
      <span class="btn-ghost is-disabled" title="Próximamente">Nómina</span>
      <button class="btn-ghost" type="button" onclick="try{NovaBot.open()}catch{}">Robots</button>
    </div>
  </div>

  <div class="grid">
    <div class="card span-4">
      <h3>Clientes activos</h3>
      <div class="kpi">
        <div class="v">—</div>
        <div class="s">Últimos 30 días</div>
      </div>
    </div>
    <div class="card span-4">
      <h3>Ingresos del mes</h3>
      <div class="kpi">
        <div class="v">$ —</div>
        <div class="s">MXN</div>
      </div>
    </div>
    <div class="card span-4">
      <h3>Timbres / HITS</h3>
      <div class="kpi">
        <div class="v">—</div>
        <div class="s">Disponibles</div>
      </div>
    </div>
  </div>
</div>
@endsection
