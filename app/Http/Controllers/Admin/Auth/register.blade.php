{{-- resources/views/cliente/sat/external/register.blade.php
     SAT · Registro externo (pantalla mínima para enlace firmado)
--}}

@extends('layouts.cliente')
@section('title','SAT · Registro externo')

@section('pageClass','page-sat')

@php
  $email     = (string) request()->query('email','');
  $note      = (string) request()->query('note','');
  $expiresTs = (string) request()->query('expires','');
@endphp

@section('content')
  <div class="container" style="max-width:980px; padding: 18px 14px;">
    <div class="card" style="border:1px solid rgba(0,0,0,.08); border-radius:16px; padding:18px; background:rgba(255,255,255,.92);">
      <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap;">
        <div>
          <div style="font-size:11px; font-weight:800; letter-spacing:.14em; text-transform:uppercase; opacity:.65;">
            SAT · Registro externo
          </div>
          <h2 style="margin:6px 0 0; font:900 22px/1.2 'Poppins',system-ui;">
            Registro de RFC para descargas
          </h2>
          <p style="margin:10px 0 0; opacity:.85;">
            Esta liga fue generada por Pactopia360 para que el emisor registre su RFC y continúe con el flujo de configuración.
          </p>
        </div>

        <div style="min-width:260px; text-align:right;">
          @if($expiresTs !== '')
            <div style="font-size:12px; opacity:.7;">Expira: <b>{{ $expiresTs }}</b></div>
          @endif
        </div>
      </div>

      <hr style="margin:14px 0; border:0; border-top:1px solid rgba(0,0,0,.08);">

      <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
        <div style="border:1px dashed rgba(0,0,0,.12); border-radius:14px; padding:12px;">
          <div style="font-weight:800; font-size:12px; opacity:.7;">Correo</div>
          <div style="font:800 14px/1.2 ui-monospace,Menlo,Consolas,monospace; margin-top:6px;">
            {{ $email !== '' ? $email : '—' }}
          </div>
        </div>

        <div style="border:1px dashed rgba(0,0,0,.12); border-radius:14px; padding:12px;">
          <div style="font-weight:800; font-size:12px; opacity:.7;">Nota</div>
          <div style="margin-top:6px; font-weight:700;">
            {{ $note !== '' ? $note : '—' }}
          </div>
        </div>
      </div>

      <div style="margin-top:14px; padding:12px; border-radius:14px; background:rgba(0,0,0,.035);">
        <div style="font-weight:900; margin-bottom:6px;">Siguiente paso</div>
        <div style="opacity:.85;">
          Este módulo está listo para mostrar la pantalla del enlace firmado. El siguiente paso es conectar aquí el formulario real
          (RFC + carga e.firma/CSD o el flujo que definamos para “registro externo”).
        </div>
      </div>

      <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:14px; flex-wrap:wrap;">
        <a class="btn"
           href="{{ route('cliente.sat.index') }}"
           style="display:inline-flex; align-items:center; justify-content:center; gap:8px; padding:10px 14px; border-radius:999px; border:1px solid rgba(0,0,0,.14); text-decoration:none; font-weight:900;">
          Volver a SAT
        </a>
      </div>
    </div>
  </div>
@endsection
