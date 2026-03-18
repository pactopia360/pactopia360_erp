@extends('layouts.admin')

@section('title', 'Facturación · Receptores')
@section('contentLayout', 'full')
@section('pageClass', 'billing-receptores-index-page')

@php
    $rows = $rows ?? collect();
    $q = (string) ($q ?? '');
    $totalRows = method_exists($rows, 'total')
        ? (int) $rows->total()
        : (is_countable($rows) ? count($rows) : 0);
@endphp

@push('styles')
<style>
  .billing-receptores-index-page .page-container{
    padding: clamp(10px, 1.6vw, 18px);
  }

  .billing-receptores-index-page .page-shell{
    width:100%;
    max-width:100% !important;
    margin:0 !important;
  }

  .br-wrap{
    display:grid;
    gap:18px;
    width:100%;
    min-width:0;
    align-content:start;
  }

  .br-hero,
  .br-card{
    width:100%;
    min-width:0;
    border:1px solid var(--card-border);
    background:var(--card-bg);
    border-radius:22px;
    box-shadow:0 12px 30px rgba(15,23,42,.05);
  }

  .br-hero{
    padding:clamp(18px, 2.1vw, 28px);
    overflow:hidden;
  }

  .br-hero-grid{
    display:grid;
    grid-template-columns:minmax(0, 1fr) auto;
    gap:18px;
    align-items:end;
  }

  .br-title{
    margin:0;
    font-size:clamp(28px, 3vw, 40px);
    line-height:1.02;
    font-weight:900;
    letter-spacing:-.03em;
    color:var(--text);
  }

  .br-sub{
    margin:10px 0 0;
    max-width:1050px;
    color:var(--muted);
    font-size:14px;
    line-height:1.65;
  }

  .br-kpis{
    display:grid;
    grid-template-columns:repeat(2, minmax(120px, 1fr));
    gap:10px;
    min-width:min(100%, 300px);
  }

  .br-kpi{
    border:1px solid var(--card-border);
    background:var(--panel-bg);
    border-radius:18px;
    padding:14px 16px;
  }

  .br-kpi-label{
    display:block;
    font-size:11px;
    line-height:1;
    text-transform:uppercase;
    letter-spacing:.08em;
    color:var(--muted);
    font-weight:900;
    margin-bottom:8px;
  }

  .br-kpi-value{
    display:block;
    font-size:24px;
    line-height:1;
    font-weight:900;
    color:var(--text);
  }

  .br-actions{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    margin-top:18px;
  }

  .br-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    min-height:42px;
    padding:10px 14px;
    border-radius:12px;
    border:1px solid var(--card-border);
    background:var(--panel-bg);
    color:var(--text);
    text-decoration:none;
    font-weight:800;
    cursor:pointer;
    transition:.18s ease;
    white-space:nowrap;
  }

  .br-btn:hover{
    transform:translateY(-1px);
    box-shadow:0 10px 24px rgba(15,23,42,.08);
    border-color:color-mix(in oklab, var(--accent) 30%, var(--card-border));
  }

  .br-btn-primary{
    background:linear-gradient(180deg,#103a51,#0f2f42);
    color:#fff;
    border-color:transparent;
  }

  .br-btn-sync{
    background:linear-gradient(180deg,#166534,#14532d);
    color:#fff;
    border-color:transparent;
  }

  .br-btn-disabled,
  .br-btn[disabled]{
    opacity:.55;
    cursor:not-allowed;
    pointer-events:none;
  }

  .br-card-head,
  .br-card-body{
    padding:clamp(16px, 1.7vw, 22px);
  }

  .br-card-head{
    border-bottom:1px solid var(--card-border);
    display:grid;
    grid-template-columns:minmax(0, 1fr) minmax(320px, 520px);
    gap:16px;
    align-items:start;
  }

  .br-card-title{
    margin:0;
    font-size:18px;
    font-weight:900;
    color:var(--text);
    letter-spacing:-.02em;
  }

  .br-card-sub{
    margin:6px 0 0;
    color:var(--muted);
    font-size:13px;
    line-height:1.65;
    max-width:900px;
  }

  .br-form{
    display:grid;
    grid-template-columns:minmax(0, 1fr) auto auto;
    gap:10px;
    align-items:center;
    width:100%;
  }

  .br-input{
    width:100%;
    min-height:44px;
    border:1px solid var(--card-border);
    border-radius:12px;
    background:var(--panel-bg);
    color:var(--text);
    padding:10px 12px;
    outline:none;
  }

  .br-input:focus{
    border-color:color-mix(in oklab, var(--accent) 45%, var(--card-border));
    box-shadow:0 0 0 4px color-mix(in oklab, var(--accent) 12%, transparent);
    background:var(--card-bg);
  }

  .br-alert{
    padding:14px 16px;
    border-radius:16px;
    border:1px solid var(--card-border);
  }

  .br-ok{
    background:rgba(22,163,74,.08);
    border-color:rgba(22,163,74,.18);
    color:#166534;
  }

  .br-bad{
    background:rgba(220,38,38,.08);
    border-color:rgba(220,38,38,.18);
    color:#991b1b;
  }

  .br-toolbar{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    align-items:center;
    justify-content:space-between;
    margin-bottom:16px;
  }

  .br-toolbar-left,
  .br-toolbar-right{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    align-items:center;
  }

  .br-chip{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:34px;
    padding:6px 12px;
    border-radius:999px;
    border:1px solid var(--card-border);
    background:var(--panel-bg);
    color:var(--text);
    font-size:12px;
    font-weight:900;
  }

  .br-table-card{
    border:1px solid var(--card-border);
    border-radius:18px;
    overflow:hidden;
    background:color-mix(in oklab, var(--card-bg) 96%, var(--panel-bg));
  }

  .br-table-wrap{
    width:100%;
    overflow:auto;
    overscroll-behavior-x:contain;
  }

  .br-table{
    width:100%;
    min-width:1180px;
    border-collapse:separate;
    border-spacing:0;
  }

  .br-table th,
  .br-table td{
    padding:14px 12px;
    border-bottom:1px solid var(--card-border);
    vertical-align:top;
    background:transparent;
  }

  .br-table thead th{
    background:color-mix(in oklab, var(--card-bg) 96%, var(--panel-bg));
    font-size:11px;
    text-transform:uppercase;
    letter-spacing:.08em;
    color:var(--muted);
    text-align:left;
    font-weight:900;
    white-space:nowrap;
  }

  .br-table tbody tr:hover td{
    background:color-mix(in oklab, var(--panel-bg) 72%, transparent);
  }

  .br-table tbody tr:last-child td{
    border-bottom:0;
  }

  .br-badge{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:28px;
    padding:4px 10px;
    border-radius:999px;
    font-size:12px;
    font-weight:900;
    border:1px solid transparent;
    white-space:nowrap;
  }

  .br-badge-manual{
    background:rgba(15,23,42,.06);
    color:var(--text);
    border-color:var(--card-border);
  }

  .br-badge-mirror{
    background:rgba(59,130,246,.10);
    color:#1d4ed8;
    border-color:rgba(59,130,246,.18);
  }

  html.theme-dark .br-badge-mirror{
    color:#93c5fd;
    border-color:rgba(147,197,253,.18);
    background:rgba(59,130,246,.16);
  }

  .br-meta{
    display:grid;
    gap:4px;
    font-size:13px;
    color:var(--muted);
  }

  .br-main{
    color:var(--text);
    font-weight:800;
  }

  .br-note{
    margin-top:6px;
    font-size:12px;
    color:var(--muted);
    line-height:1.45;
  }

  .br-empty{
    padding:34px 18px;
    text-align:center;
    color:var(--muted);
  }

  .br-actions-row{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
  }

  .br-table-note{
    padding:10px 12px 0;
    color:var(--muted);
    font-size:12px;
  }

  .br-pagination{
    margin-top:16px;
  }

    .br-pagination{
    margin-top:16px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    flex-wrap:wrap;
  }

  .br-pagination-meta{
    color:var(--muted);
    font-size:13px;
    line-height:1.4;
  }

  .br-pagination-nav{
    display:flex;
    align-items:center;
    gap:8px;
    flex-wrap:wrap;
  }

  .br-pagination-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:38px;
    padding:8px 14px;
    border-radius:12px;
    border:1px solid var(--card-border);
    background:var(--panel-bg);
    color:var(--text);
    text-decoration:none;
    font-weight:800;
    line-height:1;
    transition:.18s ease;
  }

  .br-pagination-btn:hover{
    transform:translateY(-1px);
    box-shadow:0 10px 24px rgba(15,23,42,.08);
    border-color:color-mix(in oklab, var(--accent) 30%, var(--card-border));
  }

  .br-pagination-btn.is-disabled{
    opacity:.5;
    pointer-events:none;
    cursor:default;
  }

  .br-sync-form{
    display:inline-flex;
  }

  .br-sync-form.is-submitting .br-btn{
    opacity:.7;
    pointer-events:none;
  }

  .br-sync-spinner{
    width:14px;
    height:14px;
    border-radius:999px;
    border:2px solid currentColor;
    border-right-color:transparent;
    display:inline-block;
    animation:br-spin .8s linear infinite;
  }

  @keyframes br-spin{
    to{transform:rotate(360deg)}
  }

  @media (max-width: 1380px){
    .br-table{
      min-width:1080px;
    }
  }

  @media (max-width: 1180px){
    .br-hero-grid{
      grid-template-columns:1fr;
      align-items:start;
    }

    .br-kpis{
      grid-template-columns:repeat(2, minmax(140px, 1fr));
      max-width:420px;
    }

    .br-card-head{
      grid-template-columns:1fr;
    }
  }

  @media (max-width: 980px){
    .br-form{
      grid-template-columns:1fr;
    }

    .br-toolbar{
      align-items:stretch;
    }

    .br-toolbar-left,
    .br-toolbar-right{
      width:100%;
    }

    .br-toolbar-right .br-btn{
      width:100%;
    }

    .br-table{
      min-width:940px;
    }
  }

  @media (max-width: 640px){
    .billing-receptores-index-page .page-container{
      padding:10px;
    }

    .br-hero,
    .br-card{
      border-radius:18px;
    }

    .br-actions{
      display:grid;
      grid-template-columns:1fr;
    }

    .br-actions .br-btn{
      width:100%;
    }

    .br-kpis{
      grid-template-columns:1fr;
      max-width:none;
    }

    .br-card-head,
    .br-card-body{
      padding:14px;
    }

    .br-title{
      font-size:30px;
    }

    .br-table{
      min-width:900px;
    }
  }
</style>
@endpush

@section('content')
<div class="br-wrap" id="billingReceptoresPage">
  <section class="br-hero">
    <div class="br-hero-grid">
      <div>
        <h1 class="br-title">Receptores</h1>
        <p class="br-sub">
          Aquí se muestran los receptores manuales, los receptores espejo del perfil fiscal del cliente y los receptores jalados automáticamente desde Facturotopía.
        </p>
      </div>

      <div class="br-kpis">
        <div class="br-kpi">
          <span class="br-kpi-label">Total</span>
          <span class="br-kpi-value">{{ number_format($totalRows) }}</span>
        </div>

        <div class="br-kpi">
          <span class="br-kpi-label">Filtro actual</span>
          <span class="br-kpi-value" style="font-size:14px;line-height:1.3;">
            {{ $q !== '' ? $q : 'Sin filtro' }}
          </span>
        </div>
      </div>
    </div>

    <div class="br-actions">
      <a href="{{ route('admin.billing.invoicing.dashboard') }}" class="br-btn">Dashboard</a>
      <a href="{{ route('admin.billing.invoicing.emisores.index') }}" class="br-btn">Emisores</a>

      <span class="br-btn br-btn-sync" style="cursor:default;pointer-events:none;opacity:.92;">
        Sincronización automática activa
      </span>

      <a href="{{ route('admin.billing.invoicing.receptores.create') }}" class="br-btn br-btn-primary">Nuevo receptor</a>
    </div>
  </section>

  @if(session('ok'))
    <div class="br-alert br-ok">{{ session('ok') }}</div>
  @endif

  @if($errors->any())
    <div class="br-alert br-bad">{{ $errors->first() }}</div>
  @endif

  <section class="br-card">
    <div class="br-card-head">
      <div>
        <h2 class="br-card-title">Listado de receptores</h2>
        <div class="br-card-sub">
          Revisa receptores manuales y receptores espejo del perfil fiscal del cliente. Los registros espejo se regeneran automáticamente y se mantienen sincronizados con la información fiscal del cliente.
        </div>
      </div>

      <form method="GET" class="br-form">
        <input
          type="text"
          name="q"
          class="br-input"
          value="{{ $q }}"
          placeholder="RFC, razón social, email, uso CFDI, régimen, cuenta..."
        >
        <button type="submit" class="br-btn">Buscar</button>
        <a href="{{ route('admin.billing.invoicing.receptores.index') }}" class="br-btn">Limpiar</a>
      </form>
    </div>

    <div class="br-card-body">
      <div class="br-toolbar">
        <div class="br-toolbar-left">
          <span class="br-chip">Total: {{ number_format($totalRows) }}</span>

          @if($q !== '')
            <span class="br-chip">Filtro: {{ $q }}</span>
          @endif
        </div>

        <div class="br-toolbar-right">
          <a href="{{ route('admin.billing.invoicing.receptores.create') }}" class="br-btn">Agregar receptor</a>
        </div>
      </div>

      <div class="br-table-card">
        <div class="br-table-wrap">
          <table class="br-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Origen</th>
                <th>Cuenta</th>
                <th>RFC</th>
                <th>Razón social</th>
                <th>Nombre comercial</th>
                <th>Uso CFDI</th>
                <th>Email</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              @forelse($rows as $row)
                @php
                    $isMirror         = (bool) ($row->is_mirror ?? false);
                    $origenText       = $isMirror ? 'Espejo perfil cliente' : 'Manual';

                    $cuentaLabel      = $row->cuenta_label ?? ($row->cuenta_id ?? '—');
                    $cuentaId         = $row->cuenta_id ?? null;

                    $rfc              = $row->rfc ?? '—';
                    $razonSocial      = $row->razon_social ?? '—';
                    $nombreComercial  = $row->nombre_comercial ?? '—';
                    $usoCfdi          = $row->uso_cfdi ?? '—';
                    $email            = $row->email ?? '—';
                @endphp
                <tr>
                  <td>
                    <span style="font-weight:900;">#{{ $row->id }}</span>
                  </td>

                  <td>
                    <span class="br-badge {{ $isMirror ? 'br-badge-mirror' : 'br-badge-manual' }}">
                      {{ $origenText }}
                    </span>

                    @if($isMirror)
                      <div class="br-note">
                        Se sincroniza desde los datos fiscales del cliente.
                      </div>
                    @endif
                  </td>

                  <td>
                    <div class="br-meta">
                      <span class="br-main">{{ $cuentaLabel }}</span>

                      @if(!empty($cuentaId))
                        <span>ID cuenta: {{ $cuentaId }}</span>
                      @endif
                    </div>
                  </td>

                  <td>{{ $rfc }}</td>
                  <td>{{ $razonSocial }}</td>
                  <td>{{ $nombreComercial }}</td>
                  <td>{{ $usoCfdi }}</td>
                  <td>{{ $email }}</td>

                  <td>
                    <div class="br-actions-row">
                      @if($isMirror)
                        <button type="button" class="br-btn br-btn-disabled" disabled>Editar</button>
                        <button type="button" class="br-btn br-btn-disabled" disabled>Eliminar</button>
                      @else
                        <a href="{{ route('admin.billing.invoicing.receptores.edit', $row->id) }}" class="br-btn">Editar</a>

                        <form method="POST"
                              action="{{ route('admin.billing.invoicing.receptores.destroy', $row->id) }}"
                              onsubmit="return confirm('¿Eliminar este receptor?');">
                          @csrf
                          @method('DELETE')
                          <button type="submit" class="br-btn">Eliminar</button>
                        </form>
                      @endif
                    </div>

                    @if($isMirror)
                      <div class="br-note">
                        Para modificar este receptor, actualiza el perfil fiscal del cliente. Este registro se regenera automáticamente como espejo.
                      </div>
                    @endif
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="9">
                    <div class="br-empty">No hay receptores registrados.</div>
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        <div class="br-table-note">
          Desliza horizontalmente la tabla en pantallas pequeñas para revisar todas las columnas sin romper el diseño.
        </div>
      </div>

           @if(method_exists($rows, 'hasPages') && $rows->hasPages())
        <div class="br-pagination">
          <div class="br-pagination-meta">
            Mostrando
            {{ $rows->firstItem() ?? 0 }}
            a
            {{ $rows->lastItem() ?? 0 }}
            de
            {{ $rows->total() ?? 0 }}
            resultados
          </div>

          <div class="br-pagination-nav">
            @if($rows->onFirstPage())
              <span class="br-pagination-btn is-disabled">Anterior</span>
            @else
              <a href="{{ $rows->previousPageUrl() }}" class="br-pagination-btn">Anterior</a>
            @endif

            @if($rows->hasMorePages())
              <a href="{{ $rows->nextPageUrl() }}" class="br-pagination-btn">Siguiente</a>
            @else
              <span class="br-pagination-btn is-disabled">Siguiente</span>
            @endif
          </div>
        </div>
      @elseif(method_exists($rows, 'total'))
        <div class="br-pagination">
          <div class="br-pagination-meta">
            Mostrando
            {{ $rows->firstItem() ?? 0 }}
            a
            {{ $rows->lastItem() ?? 0 }}
            de
            {{ $rows->total() ?? 0 }}
            resultados
          </div>
        </div>
      @endif
      
    </div>
  </section>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const page = document.getElementById('billingReceptoresPage');

  if (page && !window.location.hash) {
    try {
      if ('scrollRestoration' in history) {
        history.scrollRestoration = 'manual';
      }
    } catch (e) {}

    window.scrollTo(0, 0);
  }

  const syncForm = document.querySelector('[data-sync-form="receptores"]');
  if (!syncForm) return;

  const submitBtn = syncForm.querySelector('[data-sync-submit]');
  const label = syncForm.querySelector('[data-sync-label]');
  if (!submitBtn || !label) return;

  let submitted = false;
  const defaultText = submitBtn.getAttribute('data-default-text') || 'Sincronizar con Facturotopia';
  const loadingText = submitBtn.getAttribute('data-loading-text') || 'Sincronizando...';

  syncForm.addEventListener('submit', function (e) {
    if (submitted) {
      e.preventDefault();
      return false;
    }

    const ok = window.confirm('¿Sincronizar receptores con Facturotopia? Se actualizarán los existentes y solo se crearán remotos cuando no exista coincidencia.');
    if (!ok) {
      e.preventDefault();
      return false;
    }

    submitted = true;
    syncForm.classList.add('is-submitting');
    submitBtn.disabled = true;
    submitBtn.classList.add('br-btn-disabled');
    submitBtn.setAttribute('aria-busy', 'true');
    label.innerHTML = '<span class="br-sync-spinner" aria-hidden="true"></span> ' + loadingText;

    window.addEventListener('beforeunload', function () {
      submitBtn.disabled = true;
    }, { once: true });

    return true;
  });

  window.addEventListener('pageshow', function () {
    if (!submitted) {
      submitBtn.disabled = false;
      submitBtn.classList.remove('br-btn-disabled');
      submitBtn.removeAttribute('aria-busy');
      label.textContent = defaultText;
      syncForm.classList.remove('is-submitting');
    }
  });
});
</script>
@endpush