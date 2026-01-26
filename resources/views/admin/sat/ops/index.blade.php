{{-- C:\wamp64\www\pactopia360_erp\resources\views\admin\sat\ops\index.blade.php --}}
{{-- P360 · Admin · SAT Ops · Index (v1.0) --}}

@extends('layouts.admin')

@section('title','SAT · Operaciones')

{{-- opcional: usa contained si quieres limitar ancho --}}
{{-- @section('contentLayout','contained') --}}

@section('pageClass','page-admin-sat-ops')

@php
  use Illuminate\Support\Facades\Route;

  $links = [
    [
      'title' => 'Credenciales SAT',
      'desc'  => 'Gestiona RFCs, e.firma/CSD, estatus y accesos.',
      'icon'  => '🔐',
      'route' => 'admin.sat.ops.credentials.index',
      'tone'  => 'red',
    ],
    [
      'title' => 'Descargas',
      'desc'  => 'Historial, lotes, ZIPs, estados, reintentos y trazas.',
      'icon'  => '⬇️',
      'route' => 'admin.sat.ops.downloads.index',
      'tone'  => 'blue',
    ],
    [
      'title' => 'Solicitudes manuales',
      'desc'  => 'Carga/validación de entregables manuales y seguimiento.',
      'icon'  => '🧾',
      'route' => 'admin.sat.ops.manual.index',
      'tone'  => 'amber',
    ],
    [
      'title' => 'Pagos SAT',
      'desc'  => 'Pagos, facturación, conciliación y eventos.',
      'icon'  => '💳',
      'route' => 'admin.sat.ops.payments.index',
      'tone'  => 'emerald',
    ],
  ];

  foreach ($links as &$it) {
    $it['exists'] = Route::has($it['route']);
    $it['url']    = $it['exists'] ? route($it['route']) : '#';
  }
  unset($it);
@endphp

@section('page-header')
  <div class="p360-ph">
    <div class="p360-ph-left">
      <div class="p360-ph-kicker">ADMIN · SAT</div>
      <h1 class="p360-ph-title">Operaciones</h1>
      <div class="p360-ph-sub">Panel operativo para credenciales, descargas, solicitudes manuales y pagos.</div>
    </div>

    <div class="p360-ph-right">
      <div class="p360-ph-chip" title="Rutas registradas">
        <span class="dot"></span>
        <span class="txt">admin.sat.ops.*</span>
      </div>

      <button type="button" class="p360-btn" onclick="try{window.P360 && window.P360.focusSearch && window.P360.focusSearch();}catch(e){}">
        Buscar (Ctrl/Cmd+K)
      </button>
    </div>
  </div>
@endsection

@section('content')

  <div class="ops-wrap">

    <div class="ops-grid">
      @foreach($links as $it)
        <a class="ops-card tone-{{ $it['tone'] }} {{ $it['exists'] ? '' : 'is-disabled' }}"
           href="{{ $it['url'] }}"
           @if(!$it['exists']) aria-disabled="true" tabindex="-1" @endif>
          <div class="ops-card-top">
            <div class="ops-ico" aria-hidden="true">{{ $it['icon'] }}</div>
            <div class="ops-title">{{ $it['title'] }}</div>
          </div>

          <div class="ops-desc">{{ $it['desc'] }}</div>

          <div class="ops-meta">
            @if($it['exists'])
              <span class="ops-pill ok">Disponible</span>
              <span class="ops-go">Entrar →</span>
            @else
              <span class="ops-pill off">Ruta no registrada</span>
              <span class="ops-go">—</span>
            @endif
          </div>
        </a>
      @endforeach
    </div>

    <div class="ops-panel">
      <div class="ops-panel-head">
        <div class="ops-panel-title">Atajos</div>
        <div class="ops-panel-sub">Acciones rápidas y diagnóstico.</div>
      </div>

      <div class="ops-actions">
        <a class="ops-action" href="{{ Route::has('admin.ui.diag') ? route('admin.ui.diag') : '#' }}">
          <span class="i">🩺</span>
          <span class="t">UI · Diagnóstico</span>
          <span class="s">/admin/ui/diag</span>
        </a>

        <a class="ops-action" href="{{ Route::has('admin.cfg') ? route('admin.cfg') : '#' }}">
          <span class="i">⚙️</span>
          <span class="t">Admin · Contexto</span>
          <span class="s">/admin/_cfg</span>
        </a>

        <button class="ops-action" type="button" onclick="location.reload()">
          <span class="i">🔄</span>
          <span class="t">Refrescar</span>
          <span class="s">Recarga la vista</span>
        </button>
      </div>

      <div class="ops-note">
        <div class="ops-note-title">Nota</div>
        <div class="ops-note-text">
          Este panel es “entry point”. Cada tarjeta apunta a un submódulo.
          Si alguna ruta aparece como “no registrada”, revisa que exista en <code>routes/admin.php</code>
          y que el controlador/método estén en la ruta PSR-4 correcta.
        </div>
      </div>
    </div>

  </div>

@endsection

@push('styles')
<style>
  /* ===== SAT OPS (scoped) ===== */
  .page-admin-sat-ops .page-container{ padding-top: 14px; }

  .p360-ph{
    display:flex; align-items:flex-end; justify-content:space-between;
    gap:14px; padding: 14px 16px;
  }
  .p360-ph-kicker{
    font: 900 11px/1 system-ui; letter-spacing:.12em;
    color: var(--muted);
  }
  .p360-ph-title{
    margin: 6px 0 0;
    font: 950 22px/1.15 system-ui;
    letter-spacing:-.01em;
  }
  .p360-ph-sub{
    margin-top: 6px;
    color: var(--muted);
    font: 600 13px/1.45 system-ui;
  }
  .p360-ph-right{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
  .p360-ph-chip{
    display:inline-flex; align-items:center; gap:8px;
    border:1px solid var(--bd);
    background: color-mix(in oklab, var(--card-bg) 86%, transparent);
    padding:8px 10px; border-radius:999px;
    font: 800 12px/1 system-ui;
  }
  .p360-ph-chip .dot{ width:8px; height:8px; border-radius:999px; background: var(--brand-red); box-shadow: 0 0 0 4px color-mix(in oklab, var(--brand-red) 18%, transparent); }
  .p360-btn{
    border:1px solid var(--bd);
    background: color-mix(in oklab, var(--card-bg) 88%, transparent);
    color: var(--text);
    padding:10px 12px;
    border-radius: 12px;
    font: 850 13px/1 system-ui;
    cursor:pointer;
  }
  .p360-btn:hover{ background: color-mix(in oklab, var(--card-bg) 78%, transparent); }

  .ops-wrap{ display:grid; gap:14px; }
  .ops-grid{
    display:grid;
    grid-template-columns: repeat(12, 1fr);
    gap: 12px;
  }
  .ops-card{
    grid-column: span 6;
    display:flex; flex-direction:column; gap:10px;
    padding: 14px;
    border-radius: 16px;
    border:1px solid var(--bd);
    background: var(--card-bg);
    box-shadow: var(--shadow-1);
    text-decoration:none;
    color: inherit;
    position:relative;
    overflow:hidden;
    min-height: 118px;
  }
  .ops-card:hover{ transform: translateY(-1px); transition: transform .12s ease; }
  .ops-card.is-disabled{ opacity:.55; filter: grayscale(.25); pointer-events:none; }

  .ops-card::before{
    content:'';
    position:absolute; inset:-40px -40px auto auto;
    width: 140px; height: 140px;
    border-radius: 999px;
    opacity:.22;
    background: var(--brand-red);
    filter: blur(0px);
  }
  .ops-card.tone-blue::before{ background: #3b82f6; }
  .ops-card.tone-amber::before{ background: #f59e0b; }
  .ops-card.tone-emerald::before{ background: #10b981; }

  .ops-card-top{ display:flex; align-items:center; gap:10px; }
  .ops-ico{
    width: 38px; height: 38px; border-radius: 14px;
    display:grid; place-items:center;
    border:1px solid var(--bd);
    background: color-mix(in oklab, var(--panel-bg) 70%, transparent);
    font-size: 18px;
  }
  .ops-title{ font: 950 15px/1.1 system-ui; letter-spacing:-.01em; }
  .ops-desc{ color: var(--muted); font: 650 13px/1.45 system-ui; }
  .ops-meta{ display:flex; justify-content:space-between; align-items:center; margin-top:auto; gap:10px; }
  .ops-pill{
    display:inline-flex; align-items:center; gap:8px;
    padding: 7px 10px;
    border-radius: 999px;
    font: 850 12px/1 system-ui;
    border: 1px solid var(--bd);
    background: color-mix(in oklab, var(--panel-bg) 72%, transparent);
  }
  .ops-pill.ok{ border-color: color-mix(in oklab, #10b981 35%, var(--bd)); }
  .ops-pill.off{ border-color: color-mix(in oklab, #ef4444 35%, var(--bd)); }
  .ops-go{ font: 900 12px/1 system-ui; opacity:.85; }

  .ops-panel{
    border:1px solid var(--bd);
    border-radius: 16px;
    background: color-mix(in oklab, var(--card-bg) 92%, transparent);
    box-shadow: var(--shadow-1);
    padding: 14px;
  }
  .ops-panel-head{ display:flex; align-items:flex-end; justify-content:space-between; gap:10px; margin-bottom: 10px; }
  .ops-panel-title{ font: 950 14px/1.1 system-ui; }
  .ops-panel-sub{ color: var(--muted); font: 650 12px/1.35 system-ui; }

  .ops-actions{
    display:grid;
    grid-template-columns: repeat(12, 1fr);
    gap: 10px;
  }
  .ops-action{
    grid-column: span 4;
    display:flex; flex-direction:column; gap:6px;
    padding: 12px;
    border-radius: 14px;
    border:1px solid var(--bd);
    background: var(--card-bg);
    text-decoration:none;
    color: inherit;
    cursor:pointer;
    text-align:left;
  }
  .ops-action:hover{ background: color-mix(in oklab, var(--card-bg) 86%, transparent); }
  .ops-action .i{ font-size: 18px; }
  .ops-action .t{ font: 900 13px/1.2 system-ui; }
  .ops-action .s{ color: var(--muted); font: 650 12px/1.2 ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }

  .ops-note{
    margin-top: 12px;
    border:1px dashed color-mix(in oklab, var(--text) 20%, transparent);
    background: color-mix(in oklab, var(--panel-bg) 70%, transparent);
    border-radius: 14px;
    padding: 12px;
  }
  .ops-note-title{ font: 900 12px/1 system-ui; margin-bottom: 6px; }
  .ops-note-text{ color: var(--muted); font: 650 12px/1.5 system-ui; }
  .ops-note-text code{
    font: 750 12px/1.2 ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    color: var(--text);
  }

  @media (max-width: 1100px){
    .ops-card{ grid-column: span 12; }
    .ops-action{ grid-column: span 12; }
    .p360-ph{ align-items:flex-start; }
  }
</style>
@endpush
