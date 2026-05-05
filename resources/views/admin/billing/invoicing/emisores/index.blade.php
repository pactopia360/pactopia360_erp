@extends('layouts.admin')

@section('title', 'Facturación · Emisores')
@section('contentLayout', 'full')
@section('pageClass', 'billing-emisores-index-page')

@php
    $rows = $rows ?? collect();
    $q = (string) ($q ?? '');
    $totalRows = method_exists($rows, 'total')
        ? (int) $rows->total()
        : (is_countable($rows) ? count($rows) : 0);
@endphp

@push('styles')
<style>
  .billing-emisores-index-page .page-container{
    padding: clamp(10px, 1.6vw, 18px);
  }

  .billing-emisores-index-page .page-shell{
    width:100%;
    max-width:100% !important;
    margin:0 !important;
  }

  .be-wrap{
    display:grid;
    gap:18px;
    width:100%;
    min-width:0;
  }

  .be-hero,
  .be-card{
    width:100%;
    min-width:0;
    border:1px solid var(--card-border);
    background:var(--card-bg);
    border-radius:22px;
    box-shadow:0 12px 30px rgba(15,23,42,.05);
  }

  .be-hero{
    padding:clamp(18px, 2.1vw, 28px);
    overflow:hidden;
  }

  .be-hero-grid{
    display:grid;
    grid-template-columns:minmax(0, 1fr) auto;
    gap:18px;
    align-items:end;
  }

  .be-title{
    margin:0;
    font-size:clamp(28px, 3vw, 40px);
    line-height:1.02;
    font-weight:900;
    letter-spacing:-.03em;
    color:var(--text);
  }

  .be-sub{
    margin:10px 0 0;
    max-width:1050px;
    color:var(--muted);
    font-size:14px;
    line-height:1.65;
  }

  .be-kpis{
    display:grid;
    grid-template-columns:repeat(2, minmax(120px, 1fr));
    gap:10px;
    min-width:min(100%, 280px);
  }

  .be-kpi{
    border:1px solid var(--card-border);
    background:var(--panel-bg);
    border-radius:18px;
    padding:14px 16px;
  }

  .be-kpi-label{
    display:block;
    font-size:11px;
    line-height:1;
    text-transform:uppercase;
    letter-spacing:.08em;
    color:var(--muted);
    font-weight:900;
    margin-bottom:8px;
  }

  .be-kpi-value{
    display:block;
    font-size:24px;
    line-height:1;
    font-weight:900;
    color:var(--text);
  }

  .be-actions{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    margin-top:18px;
  }

  .be-btn{
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

  .be-btn:hover{
    transform:translateY(-1px);
    box-shadow:0 10px 24px rgba(15,23,42,.08);
    border-color:color-mix(in oklab, var(--accent) 30%, var(--card-border));
  }

  .be-btn-primary{
    background:linear-gradient(180deg,#103a51,#0f2f42);
    color:#fff;
    border-color:transparent;
  }

  .be-btn-success{
    background:linear-gradient(180deg,#166534,#14532d);
    color:#fff;
    border-color:transparent;
  }

  .be-btn-danger{
    background:linear-gradient(180deg,#991b1b,#7f1d1d);
    color:#fff;
    border-color:transparent;
  }

  .be-card-head,
  .be-card-body{
    padding:clamp(16px, 1.7vw, 22px);
  }

  .be-card-head{
    border-bottom:1px solid var(--card-border);
    display:grid;
    grid-template-columns:minmax(0, 1fr) minmax(320px, 520px);
    gap:16px;
    align-items:start;
  }

  .be-card-title{
    margin:0;
    font-size:18px;
    font-weight:900;
    color:var(--text);
    letter-spacing:-.02em;
  }

  .be-card-sub{
    margin:6px 0 0;
    color:var(--muted);
    font-size:13px;
    line-height:1.65;
    max-width:900px;
  }

  .be-form{
    display:grid;
    grid-template-columns:minmax(0, 1fr) auto auto;
    gap:10px;
    align-items:center;
    width:100%;
  }

  .be-input{
    width:100%;
    min-height:44px;
    border:1px solid var(--card-border);
    border-radius:12px;
    background:var(--panel-bg);
    color:var(--text);
    padding:10px 12px;
    outline:none;
  }

  .be-input:focus{
    border-color:color-mix(in oklab, var(--accent) 45%, var(--card-border));
    box-shadow:0 0 0 4px color-mix(in oklab, var(--accent) 12%, transparent);
    background:var(--card-bg);
  }

  .be-alert{
    padding:14px 16px;
    border-radius:16px;
    border:1px solid var(--card-border);
  }

  .be-ok{
    background:rgba(22,163,74,.08);
    border-color:rgba(22,163,74,.18);
    color:#166534;
  }

  .be-bad{
    background:rgba(220,38,38,.08);
    border-color:rgba(220,38,38,.18);
    color:#991b1b;
  }

  .be-toolbar{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    align-items:center;
    justify-content:space-between;
    margin-bottom:16px;
  }

  .be-toolbar-left,
  .be-toolbar-right{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    align-items:center;
  }

  .be-chip{
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

  .be-table-card{
    border:1px solid var(--card-border);
    border-radius:18px;
    overflow:hidden;
    background:color-mix(in oklab, var(--card-bg) 96%, var(--panel-bg));
  }

  .be-table-wrap{
    width:100%;
    overflow:auto;
    overscroll-behavior-x:contain;
  }

  .be-table{
    width:100%;
    min-width:1280px;
    border-collapse:separate;
    border-spacing:0;
  }

  .be-table th,
  .be-table td{
    padding:14px 12px;
    border-bottom:1px solid var(--card-border);
    vertical-align:top;
    background:transparent;
  }

  .be-table thead th{
    position:sticky;
    top:0;
    z-index:1;
    background:color-mix(in oklab, var(--card-bg) 96%, var(--panel-bg));
    font-size:11px;
    text-transform:uppercase;
    letter-spacing:.08em;
    color:var(--muted);
    text-align:left;
    font-weight:900;
  }

  .be-table tbody tr:hover td{
    background:color-mix(in oklab, var(--panel-bg) 72%, transparent);
  }

  .be-table tbody tr:last-child td{
    border-bottom:0;
  }

  .be-table td{
    color:var(--text);
  }

  .be-strong{font-weight:900}
  .be-muted{color:var(--muted)}
  .be-mono{
    font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;
    font-size:12px;
  }

  .be-badge{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:5px 10px;
    border-radius:999px;
    font-size:12px;
    font-weight:900;
    background:var(--panel-bg);
    border:1px solid var(--card-border);
    white-space:nowrap;
  }

  .be-badge-active{
    background:rgba(22,163,74,.10);
    border-color:rgba(22,163,74,.20);
    color:#166534;
  }

  .be-badge-inactive{
    background:rgba(148,163,184,.12);
    border-color:rgba(148,163,184,.20);
    color:#475569;
  }

  .be-badge-warning{
    background:rgba(245,158,11,.10);
    border-color:rgba(245,158,11,.20);
    color:#b45309;
  }

  .be-badge-danger{
    background:rgba(220,38,38,.10);
    border-color:rgba(220,38,38,.20);
    color:#b91c1c;
  }

  .be-actions-row{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
  }

  .be-inline-form{
    display:inline-flex;
  }

  .be-empty{
    padding:34px 18px;
    text-align:center;
    color:var(--muted);
  }

  .be-table-note{
    padding:10px 12px 0;
    color:var(--muted);
    font-size:12px;
  }

  .be-pagination{
    margin-top:16px;
  }

  @media (max-width: 1380px){
    .be-table{
      min-width:1180px;
    }
  }

  @media (max-width: 1180px){
    .be-hero-grid{
      grid-template-columns:1fr;
      align-items:start;
    }

    .be-kpis{
      grid-template-columns:repeat(2, minmax(140px, 1fr));
      max-width:420px;
    }

    .be-card-head{
      grid-template-columns:1fr;
    }
  }

  @media (max-width: 980px){
    .be-form{
      grid-template-columns:1fr;
    }

    .be-toolbar{
      align-items:stretch;
    }

    .be-toolbar-left,
    .be-toolbar-right{
      width:100%;
    }

    .be-toolbar-right .be-btn{
      width:100%;
    }

    .be-table{
      min-width:980px;
    }
  }

  @media (max-width: 640px){
    .billing-emisores-index-page .page-container{
      padding:10px;
    }

    .be-hero,
    .be-card{
      border-radius:18px;
    }

    .be-actions{
      display:grid;
      grid-template-columns:1fr;
    }

    .be-actions .be-btn{
      width:100%;
    }

    .be-kpis{
      grid-template-columns:1fr;
      max-width:none;
    }

    .be-card-head,
    .be-card-body{
      padding:14px;
    }

    .be-title{
      font-size:30px;
    }

    .be-table{
      min-width:920px;
    }
  }
</style>
@endpush

@section('content')
<div class="be-wrap">
  <section class="be-hero">
    <div class="be-hero-grid">
      <div>
        <h1 class="be-title">Emisores</h1>
        <p class="be-sub">
          Visualiza, agrega, edita y elimina emisores. La sincronización con Facturotopía corre automáticamente al entrar al módulo y por tarea programada, manteniendo el espejo local actualizado.
        </p>
      </div>

      <div class="be-kpis">
        <div class="be-kpi">
          <span class="be-kpi-label">Total</span>
          <span class="be-kpi-value">{{ number_format($totalRows) }}</span>
        </div>

        <div class="be-kpi">
          <span class="be-kpi-label">Filtro actual</span>
          <span class="be-kpi-value" style="font-size:14px;line-height:1.3;">
            {{ $q !== '' ? $q : 'Sin filtro' }}
          </span>
        </div>
      </div>
    </div>

    <div class="be-actions">
      <a href="{{ route('admin.billing.invoicing.dashboard') }}" class="be-btn">Dashboard</a>
      <a href="{{ route('admin.billing.invoicing.receptores.index') }}" class="be-btn">Receptores</a>
      <a href="{{ route('admin.billing.invoicing.settings.index') }}" class="be-btn">Configuración</a>
      <a href="{{ route('admin.billing.invoicing.emisores.create') }}" class="be-btn be-btn-primary">Nuevo emisor</a>

      <form method="POST" action="{{ route('admin.billing.invoicing.emisores.sync_facturotopia') }}" style="display:inline-flex;margin:0;">
      @csrf
      <button type="submit" class="be-btn be-btn-success">
        Sincronizar Facturotopía
      </button>
    </form>
    </div>
  </section>

  @if(session('ok'))
    <div class="be-alert be-ok">{{ session('ok') }}</div>
  @endif

  @if($errors->any())
    <div class="be-alert be-bad">
      {{ $errors->first() }}
    </div>
  @endif

  <section class="be-card">
    <div class="be-card-head">
      <div>
        <h2 class="be-card-title">Listado de emisores</h2>
        <div class="be-card-sub">
          Consulta emisores locales y remotos, revisa su <strong>status</strong>, <strong>grupo</strong> y <strong>ext_id</strong>.
          El sistema sincroniza automáticamente con Facturotopía y mantiene la información operativa actualizada.
        </div>
      </div>

      <form method="GET" class="be-form">
        <input
          type="text"
          name="q"
          class="be-input"
          value="{{ $q }}"
          placeholder="RFC, razón social, email, grupo, status, cuenta o ext_id..."
        >
        <button type="submit" class="be-btn">Buscar</button>
        <a href="{{ route('admin.billing.invoicing.emisores.index') }}" class="be-btn">Limpiar</a>
      </form>
    </div>

    <div class="be-card-body">
      <div class="be-toolbar">
        <div class="be-toolbar-left">
          <span class="be-chip">Total: {{ number_format($totalRows) }}</span>

          @if($q !== '')
            <span class="be-chip">Filtro: {{ $q }}</span>
          @endif
        </div>

        <div class="be-toolbar-right">
          <a href="{{ route('admin.billing.invoicing.emisores.create') }}" class="be-btn">Agregar emisor</a>
        </div>
      </div>

      <div class="be-table-card">
        <div class="be-table-wrap">
          <table class="be-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Cuenta</th>
                <th>RFC</th>
                <th>Razón social</th>
                <th>Email</th>
                <th>Régimen</th>
                <th>Grupo</th>
                <th>Status</th>
                <th>Ext ID</th>
                <th>Dirección</th>
                <th>Series</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              @forelse($rows as $row)
                @php
                  $status = strtolower(trim((string) ($row->status ?? '')));
                  $statusClass =
                      $status === 'active' ? 'be-badge-active'
                      : ($status === 'inactive' ? 'be-badge-inactive'
                      : ($status === 'pending' ? 'be-badge-warning'
                      : ($status !== '' ? 'be-badge-danger' : '')));
                @endphp

                <tr>
                  <td>
                    <div class="be-strong">#{{ $row->id }}</div>
                  </td>

                  <td>
                    <div class="be-strong">{{ $row->cuenta_label ?: ($row->cuenta_id ?: '—') }}</div>
                    @if(!empty($row->cuenta_id))
                      <div class="be-muted be-mono">cuenta_id: {{ $row->cuenta_id }}</div>
                    @endif
                  </td>

                  <td class="be-mono">{{ $row->rfc ?: '—' }}</td>

                  <td>
                    <div class="be-strong">{{ $row->razon_social ?: '—' }}</div>
                    @if(!empty($row->nombre_comercial))
                      <div class="be-muted">{{ $row->nombre_comercial }}</div>
                    @endif
                  </td>

                  <td>{{ $row->email ?: '—' }}</td>

                  <td>{{ $row->regimen_fiscal ?: '—' }}</td>

                  <td>{{ $row->grupo ?: '—' }}</td>

                  <td>
                    <span class="be-badge {{ $statusClass }}">{{ $row->status ?: '—' }}</span>
                  </td>

                  <td class="be-mono">{{ $row->ext_id ?: '—' }}</td>

                  <td>
                    @if(!empty($row->direccion_decoded) && is_array($row->direccion_decoded))
                      <div>{{ $row->direccion_decoded['direccion'] ?? '—' }}</div>
                      <div class="be-muted">
                        {{ $row->direccion_decoded['ciudad'] ?? '—' }}
                        ·
                        {{ $row->direccion_decoded['estado'] ?? '—' }}
                      </div>
                      <div class="be-muted be-mono">CP: {{ $row->direccion_decoded['cp'] ?? '—' }}</div>
                    @else
                      —
                    @endif
                  </td>

                  <td>
                    @if(!empty($row->series_decoded) && is_array($row->series_decoded))
                      {{ count($row->series_decoded) }} serie(s)
                    @else
                      —
                    @endif
                  </td>

                  <td>
                    <div class="be-actions-row">
                      <a href="{{ route('admin.billing.invoicing.emisores.edit', $row->id) }}" class="be-btn">Editar</a>

                      <form method="POST"
                            action="{{ route('admin.billing.invoicing.emisores.destroy', $row->id) }}"
                            onsubmit="return confirm('¿Eliminar este emisor?');"
                            class="be-inline-form">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="be-btn be-btn-danger">Eliminar</button>
                      </form>
                    </div>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="12">
                    <div class="be-empty">No hay emisores registrados.</div>
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        <div class="be-table-note">
          Desliza horizontalmente la tabla en pantallas pequeñas para revisar todas las columnas sin romper el diseño.
        </div>
      </div>

      @if(method_exists($rows, 'links'))
        <div class="be-pagination">
          {{ $rows->links() }}
        </div>
      @endif
    </div>
  </section>
</div>
@endsection