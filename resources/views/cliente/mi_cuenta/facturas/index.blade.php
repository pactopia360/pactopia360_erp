{{-- resources/views/cliente/mi_cuenta/facturas/index.blade.php (v2.0 · SOT: admin.invoice_requests) --}}
@extends('layouts.cliente')

@section('title','Facturas · Mi cuenta · Pactopia360')
@section('pageClass','page-mi-cuenta page-mi-cuenta-facturas')

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/client/css/mi-cuenta.css') }}?v=3.0">
@endpush

@section('content')
@php
  $rows    = $rows ?? collect();
  $q       = $q ?? '';
  $status  = $status ?? '';
  $perPage = $perPage ?? 10;

  $rtBack  = route('cliente.mi_cuenta.index');
  $rtStore = route('cliente.mi_cuenta.facturas.store');

  // Descarga ZIP (por periodo) — ruta existente en billing
  $canDownloadRoute = \Illuminate\Support\Facades\Route::has('cliente.billing.factura.download');

  $statusMap = [
    'requested'    => ['Solicitud enviada', 'mc-pill--neutral'],
    'in_progress'  => ['En proceso', 'mc-pill--neutral'],
    'done'         => ['Lista para descargar', 'mc-pill--ok'],
    'rejected'     => ['Rechazada', 'mc-pill--bad'],
    'cancelled'    => ['Cancelada', 'mc-pill--bad'],
  ];

  $pillRightText = 'Solicitudes';
@endphp

<div class="mc-wrap">
  <div class="mc-page">

    {{-- Header --}}
    <section class="mc-card mc-header">
      <div class="mc-header-left">
        <div class="mc-title-icon" aria-hidden="true" style="background:linear-gradient(180deg, rgba(16,185,129,.18), rgba(16,185,129,.10)); color:#065f46;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <path d="M7 2h10a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="2"/>
            <path d="M8 7h8M8 11h8M8 15h6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </div>
        <div style="min-width:0">
          <h1 class="mc-title-main">Facturas</h1>
          <div class="mc-title-sub">
            Aquí se enlistan tus solicitudes de factura. Cuando el admin suba el ZIP, podrás descargarlo.
          </div>
        </div>
      </div>

      <div class="mc-header-right">
        <a class="mc-btn" href="{{ $rtBack }}" style="text-decoration:none;">Volver a Mi cuenta</a>
      </div>
    </section>

    {{-- Sección --}}
    <section class="mc-section" data-mc-section="fx1" data-open="1">
      <div class="mc-sec-head">
        <div class="mc-sec-ico" aria-hidden="true">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" stroke="currentColor" stroke-width="2"/>
            <path d="M14 2v6h6" stroke="currentColor" stroke-width="2"/>
          </svg>
        </div>

        <div class="mc-sec-meta">
          <div class="mc-sec-kicker">SECCIÓN</div>
          <h2 class="mc-sec-title">Solicitudes y descargas</h2>
          <div class="mc-sec-sub">La solicitud está sujeta a la ventana del mes de pago.</div>
        </div>

        <div style="margin-left:auto; display:flex; align-items:center; gap:10px;">
          <span class="mc-badge" style="padding:.35rem .7rem; border-radius:999px;">
            {{ $pillRightText }}
          </span>
        </div>
      </div>

      <div class="mc-sec-body">

        {{-- Mensajes --}}
        @if(session('success'))
          <div style="margin-bottom:12px;padding:.65rem .85rem;border-radius:14px;border:1px solid rgba(34,197,94,.25);background:rgba(34,197,94,.08);font-weight:800;">
            {{ session('success') }}
          </div>
        @endif
        @if(session('ok'))
          <div style="margin-bottom:12px;padding:.65rem .85rem;border-radius:14px;border:1px solid rgba(34,197,94,.25);background:rgba(34,197,94,.08);font-weight:800;">
            {{ session('ok') }}
          </div>
        @endif
        @if(session('warning'))
          <div style="margin-bottom:12px;padding:.65rem .85rem;border-radius:14px;border:1px solid rgba(245,158,11,.25);background:rgba(245,158,11,.10);font-weight:800;">
            {{ session('warning') }}
          </div>
        @endif
        @if(session('invoice_window_error_msg'))
          <div style="margin-bottom:12px;padding:.65rem .85rem;border-radius:14px;border:1px solid rgba(239,68,68,.25);background:rgba(239,68,68,.08);font-weight:800;">
            {{ session('invoice_window_error_msg') }}
          </div>
        @endif
        @if($errors->any())
          <div style="margin-bottom:12px;padding:.65rem .85rem;border-radius:14px;border:1px solid rgba(239,68,68,.25);background:rgba(239,68,68,.08);font-weight:800;">
            Revisa los campos: {{ $errors->first() }}
          </div>
        @endif

        {{-- Solicitar --}}
        <div class="mc-card" style="box-shadow:none; border:1px solid var(--mc-line, rgba(15,23,42,.10)); border-radius:16px; padding:14px;">
          <form method="POST" action="{{ $rtStore }}" style="display:grid; grid-template-columns: 220px 1fr auto; gap:12px; align-items:end;">
            @csrf

            <div class="mc-field" style="margin:0;">
              <label class="mc-label">Periodo (YYYY-MM)</label>
              <input class="mc-input" name="period" value="{{ old('period', now()->format('Y-m')) }}" placeholder="2025-12" required>
            </div>

            <div class="mc-field" style="margin:0;">
              <label class="mc-label">Notas (opcional)</label>
              <input class="mc-input" name="notes" value="{{ old('notes') }}" placeholder="Ej. RFC / uso CFDI / observaciones">
            </div>

            <div style="display:flex; gap:10px; justify-content:flex-end;">
              <button class="mc-btn mc-btn-primary" type="submit">Solicitar factura</button>
            </div>
          </form>
        </div>

        {{-- Filtros --}}
        <div class="mc-card" style="margin-top:12px; box-shadow:none; border:1px solid var(--mc-line, rgba(15,23,42,.10)); border-radius:16px; padding:14px;">
          <form method="GET" action="{{ url()->current() }}" style="display:flex; gap:10px; flex-wrap:wrap; align-items:end;">
            <div class="mc-field" style="margin:0;">
              <label class="mc-label">Buscar</label>
              <input class="mc-input" name="q" value="{{ $q }}" placeholder="ID o periodo">
            </div>

            <div class="mc-field" style="margin:0;">
              <label class="mc-label">Estatus</label>
              <select class="mc-input mc-select" name="status">
                <option value="all" @selected(($status==='' || $status==='all'))>Todos</option>
                <option value="requested" @selected($status==='requested')>Solicitud enviada</option>
                <option value="in_progress" @selected($status==='in_progress')>En proceso</option>
                <option value="done" @selected($status==='done')>Lista</option>
                <option value="rejected" @selected($status==='rejected')>Rechazada</option>
              </select>
            </div>

            <div class="mc-field" style="margin:0;">
              <label class="mc-label">Por pág.</label>
              <select class="mc-input mc-select" name="per_page">
                @foreach([10,15,20,30,50] as $n)
                  <option value="{{ $n }}" @selected((int)$perPage===$n)>{{ $n }}</option>
                @endforeach
              </select>
            </div>

            <div style="display:flex; gap:10px;">
              <button class="mc-btn mc-btn-primary" type="submit">Aplicar</button>
              <a class="mc-btn" href="{{ url()->current() }}" style="text-decoration:none;">Limpiar</a>
            </div>
          </form>
        </div>

        {{-- Lista --}}
        <div style="margin-top:12px;">
          @if(method_exists($rows,'count') ? $rows->count() === 0 : (is_countable($rows) && count($rows)===0))
            <div style="padding:14px; border-radius:16px; border:1px dashed rgba(15,23,42,.18); color:var(--mc-muted,#6b7280); font-weight:800;">
              No hay solicitudes de factura todavía.
            </div>
          @else
            <div style="overflow:auto;">
              <table style="width:100%; border-collapse:separate; border-spacing:0 10px; min-width:860px;">
                <thead>
                  <tr style="text-align:left; color:var(--mc-muted,#6b7280); font-weight:950; font-size:.78rem;">
                    <th style="padding:0 10px;">ID</th>
                    <th style="padding:0 10px;">Periodo</th>
                    <th style="padding:0 10px;">Estatus</th>
                    <th style="padding:0 10px;">Notas</th>
                    <th style="padding:0 10px; text-align:right;">Acción</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($rows as $r)
                    @php
                      $st = strtolower((string)($r->status ?? 'requested'));
                      [$stLabel, $stClass] = $statusMap[$st] ?? ['Solicitud', 'mc-pill--neutral'];

                      $canDownload = ($st === 'done') && !empty($r->zip_path) && $canDownloadRoute;
                      $rtDl = $canDownloadRoute ? route('cliente.billing.factura.download', ['period' => $r->period]) : '#';
                    @endphp

                    <tr style="background:var(--mc-card,#fff); border:1px solid var(--mc-line, rgba(15,23,42,.10)); border-radius:14px; box-shadow:0 10px 22px rgba(2,6,23,.05);">
                      <td style="padding:14px 10px; font-weight:950;">#{{ $r->id }}</td>
                      <td style="padding:14px 10px; font-weight:900;">{{ $r->period }}</td>
                      <td style="padding:14px 10px;">
                        <span class="mc-pill {{ $stClass }}" style="display:inline-flex; gap:8px; align-items:center;">
                          {{ $stLabel }}
                        </span>
                      </td>
                      <td style="padding:14px 10px; color:var(--mc-muted,#6b7280); font-weight:700;">
                        {{ \Illuminate\Support\Str::limit((string)($r->notes ?? ''), 80) ?: '—' }}
                      </td>
                      <td style="padding:14px 10px; text-align:right;">
                        @if($canDownload)
                          <a class="mc-btn mc-btn-primary" href="{{ $rtDl }}" style="text-decoration:none;" target="_blank" rel="noopener">Descargar ZIP</a>
                        @else
                          <span class="mc-badge">Pendiente</span>
                        @endif
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>

            {{-- Paginación --}}
            @if(method_exists($rows,'links'))
              <div style="margin-top:10px;">
                {{ $rows->links() }}
              </div>
            @endif
          @endif
        </div>

      </div>
    </section>

  </div>
</div>
@endsection
