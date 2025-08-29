@extends('layouts.admin')

@section('title','Dashboard · Pactopia360')

@section('page-header')
  <h1 class="kpi-value" style="font-size:22px;margin:0">Panel Administrativo</h1>
  <p class="muted" style="margin:6px 0 0">Resumen general y accesos rápidos</p>
@endsection

@section('content')
  <div class="cards" role="list" aria-label="Accesos rápidos">
    <a href="{{ route('admin.home') }}" class="card" role="listitem" style="text-decoration:none">
      <div style="font-weight:700;margin-bottom:6px">Ir a Home</div>
      <div class="muted">KPIs, gráficas y clientes</div>
    </a>

    @if(Route::has('admin.perfil'))
      <a href="{{ route('admin.perfil') }}" class="card" role="listitem" style="text-decoration:none">
        <div style="font-weight:700;margin-bottom:6px">Mi perfil</div>
        <div class="muted">Preferencias y datos del administrador</div>
      </a>
    @endif

    <div class="card" role="listitem">
      <div style="font-weight:700;margin-bottom:6px">Contenedor OK</div>
      <div class="muted">Este dashboard es el contenedor. Usa el menú o tarjetas para navegar.</div>
    </div>
  </div>
@endsection
