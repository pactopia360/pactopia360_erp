{{-- resources/views/errors/vault_blocked.blade.php --}}
{{-- P360 · Cliente · Vault blocked (v1) --}}

@extends('layouts.cliente')

@section('title', 'Bóveda fiscal no activa')
@section('pageClass', 'page-vault-blocked')

@php
  $msg = $message ?? session('error') ?? 'Bóveda no activa. Completa la activación o contacta a soporte.';
  $backSat = \Illuminate\Support\Facades\Route::has('cliente.sat.index') ? route('cliente.sat.index') : url('/cliente/sat');
  $backHome = \Illuminate\Support\Facades\Route::has('cliente.home') ? route('cliente.home') : url('/cliente/home');
@endphp

@section('content')
<div class="container py-4" style="max-width: 980px;">
  <div class="card shadow-sm border-0">
    <div class="card-body p-4 p-md-5">
      <div class="d-flex align-items-start gap-3">
        <div class="rounded-circle d-inline-flex align-items-center justify-content-center"
             style="width:46px;height:46px;background:rgba(220,53,69,.12);">
          <span style="font-size:22px;line-height:1;">🔒</span>
        </div>

        <div class="flex-grow-1">
          <h1 class="h4 mb-2">Bóveda fiscal no activa</h1>
          <p class="text-muted mb-3">
            {{ $msg }}
          </p>

          <div class="alert alert-warning mb-4" role="alert">
            <strong>¿Qué puedes hacer?</strong>
            <div class="mt-2">
              <ul class="mb-0">
                <li>Verifica si tu plan incluye la Bóveda fiscal.</li>
                <li>Si ya pagaste la activación, intenta cerrar sesión y entrar de nuevo.</li>
                <li>Si necesitas ayuda, contacta a soporte.</li>
              </ul>
            </div>
          </div>

          <div class="d-flex flex-wrap gap-2">
            <a href="{{ $backSat }}" class="btn btn-primary">
              Volver a SAT
            </a>

            <a href="{{ $backHome }}" class="btn btn-outline-secondary">
              Ir a Inicio
            </a>

            <a href="mailto:soporte@pactopia.com?subject=P360%20-%20Activaci%C3%B3n%20B%C3%B3veda%20Fiscal"
               class="btn btn-outline-danger">
              Contactar soporte
            </a>
          </div>

          @if(session('trace_id'))
            <div class="mt-4 small text-muted">
              Trace: <code>{{ session('trace_id') }}</code>
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
