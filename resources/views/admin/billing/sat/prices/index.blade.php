{{-- resources/views/admin/billing/sat/prices/index.blade.php --}}
@extends('layouts.admin')

@section('title','SAT · Lista de precios')

@section('content')
@php
  // Normaliza active para evitar comparaciones inconsistentes (null/'1'/'0')
  $rawActive = $active ?? request()->query('active', null);
  if ($rawActive === '' || $rawActive === null) {
    $active = null;
  } else {
    $active = ((string)$rawActive === '1') ? '1' : (((string)$rawActive === '0') ? '0' : null);
  }

  $total   = method_exists($rows, 'total') ? (int)$rows->total() : (is_iterable($rows) ? count($rows) : 0);
  $perPage = method_exists($rows, 'perPage') ? (int)$rows->perPage() : 0;

  // Mensaje estándar (tu controller usa 'success')
  $flashOk = session('ok') ?? session('success');
@endphp

<style>
  .sat-wrap{ max-width:1200px; margin:0 auto; padding: 18px 18px 40px; }
  .sat-head{ display:flex; gap:14px; align-items:flex-end; justify-content:space-between; flex-wrap:wrap; }
  .sat-title{ margin:0; font-weight:950; letter-spacing:-.02em; color:var(--sx-ink, #0f172a); }
  .sat-sub{ margin-top:6px; color:var(--sx-mut, #64748b); font-size:13px; }
  .sat-actions{ display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
  .sat-btn{ appearance:none; border:1px solid color-mix(in oklab, var(--sx-ink, #0f172a) 18%, transparent);
    background:transparent; color:var(--sx-ink, #0f172a); padding:10px 12px; border-radius:12px; font-weight:850;
    text-decoration:none; display:inline-flex; gap:8px; align-items:center; }
  .sat-btn:hover{ background: color-mix(in oklab, var(--sx-ink, #0f172a) 6%, transparent); }
  .sat-btn.primary{ background: color-mix(in oklab, var(--sx-brand, #e11d48) 16%, transparent);
    border-color: color-mix(in oklab, var(--sx-brand, #e11d48) 35%, transparent); }
  .sat-btn.primary:hover{ background: color-mix(in oklab, var(--sx-brand, #e11d48) 22%, transparent); }

  .sat-card{ background: var(--card, #fff); border:1px solid color-mix(in oklab, var(--sx-ink, #0f172a) 12%, transparent);
    border-radius:16px; box-shadow: 0 10px 28px rgba(2,6,23,.06); overflow:hidden; }
  .sat-bar{ padding:12px; display:flex; gap:8px; align-items:center; flex-wrap:wrap; border-bottom:1px solid color-mix(in oklab, var(--sx-ink, #0f172a) 10%, transparent); }
  .sat-chip{ border:1px solid color-mix(in oklab, var(--sx-ink, #0f172a) 14%, transparent);
    padding:7px 10px; border-radius:999px; text-decoration:none; font-weight:850; color:var(--sx-ink, #0f172a); font-size:12px; }
  .sat-chip.on{ background: color-mix(in oklab, var(--sx-ink, #0f172a) 6%, transparent); }
  .sat-chip.ok.on{ background: color-mix(in oklab, #16a34a 14%, transparent); border-color: color-mix(in oklab, #16a34a 28%, transparent); }
  .sat-chip.warn.on{ background: color-mix(in oklab, #f59e0b 16%, transparent); border-color: color-mix(in oklab, #f59e0b 30%, transparent); }
  .sat-meta{ margin-left:auto; color:var(--sx-mut, #64748b); font-size:12px; font-weight:700; }

  .sat-table{ width:100%; border-collapse:separate; border-spacing:0; }
  .sat-table th{ text-align:left; font-size:12px; letter-spacing:.08em; text-transform:uppercase; color:var(--sx-mut, #64748b);
    padding:12px; border-bottom:1px solid color-mix(in oklab, var(--sx-ink, #0f172a) 10%, transparent); background: color-mix(in oklab, var(--sx-ink, #0f172a) 3%, transparent); }
  .sat-table td{ padding:12px; border-bottom:1px solid color-mix(in oklab, var(--sx-ink, #0f172a) 8%, transparent); vertical-align:middle; color:var(--sx-ink, #0f172a); font-size:13px; }
  .sat-table tr:hover td{ background: color-mix(in oklab, var(--sx-ink, #0f172a) 3%, transparent); }
  .sat-right{ text-align:right; }
  .sat-badge{ display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:900;
    border:1px solid color-mix(in oklab, var(--sx-ink, #0f172a) 14%, transparent); }
  .sat-badge.ok{ background: color-mix(in oklab, #16a34a 12%, transparent); border-color: color-mix(in oklab, #16a34a 28%, transparent); }
  .sat-badge.off{ background: color-mix(in oklab, #64748b 10%, transparent); border-color: color-mix(in oklab, #64748b 24%, transparent); }
  .sat-badge.info{ background: color-mix(in oklab, #0ea5e9 12%, transparent); border-color: color-mix(in oklab, #0ea5e9 28%, transparent); }

  .sat-rowtitle{ font-weight:950; }
  .sat-small{ color:var(--sx-mut, #64748b); font-size:12px; margin-top:3px; }

  .sat-acts{ display:flex; gap:8px; justify-content:flex-end; flex-wrap:wrap; }
  .sat-actbtn{ border:1px solid color-mix(in oklab, var(--sx-ink, #0f172a) 16%, transparent);
    background:transparent; padding:8px 10px; border-radius:12px; font-weight:900; cursor:pointer; text-decoration:none; color:var(--sx-ink, #0f172a); }
  .sat-actbtn:hover{ background: color-mix(in oklab, var(--sx-ink, #0f172a) 6%, transparent); }
  .sat-actbtn.danger{ border-color: color-mix(in oklab, #ef4444 34%, transparent); }
  .sat-actbtn.danger:hover{ background: color-mix(in oklab, #ef4444 10%, transparent); }
</style>

<div class="sat-wrap">
  <div class="sat-head">
    <div>
      <h1 class="sat-title">SAT · Lista de precios</h1>
      <div class="sat-sub">Tabla por volumen: <b>Hasta</b> · <b>Precio</b> · <b>Unitario</b>.</div>
    </div>

    <div class="sat-actions">
      <a class="sat-btn" href="{{ route('admin.sat.discounts.index') }}">🎟️ Códigos de descuento</a>
      <a class="sat-btn primary" href="{{ route('admin.sat.prices.create') }}">➕ Nueva regla</a>
    </div>
  </div>

  @if($flashOk)
    <div class="sat-card" style="margin-top:14px; padding:12px; border-color:color-mix(in oklab, #16a34a 30%, transparent);">
      <div style="font-weight:900;">✅ {{ $flashOk }}</div>
    </div>
  @endif

  @if(session('error'))
    <div class="sat-card" style="margin-top:14px; padding:12px; border-color:color-mix(in oklab, #ef4444 34%, transparent);">
      <div style="font-weight:900;">⚠️ {{ session('error') }}</div>
    </div>
  @endif

  <div class="sat-card" style="margin-top:14px;">
    <div class="sat-bar">
      <a class="sat-chip {{ ($active === null) ? 'on' : '' }}" href="{{ route('admin.sat.prices.index') }}">Todas</a>
      <a class="sat-chip ok {{ ($active === '1') ? 'on' : '' }}" href="{{ route('admin.sat.prices.index',['active'=>1]) }}">Activas</a>
      <a class="sat-chip warn {{ ($active === '0') ? 'on' : '' }}" href="{{ route('admin.sat.prices.index',['active'=>0]) }}">Inactivas</a>

      <div class="sat-meta">
        {{ number_format($total) }} reglas @if($perPage>0) · {{ $perPage }}/pág @endif
      </div>
    </div>

    <div style="overflow:auto;">
      <table class="sat-table">
        <thead>
          <tr>
            <th style="width:74px;">ID</th>
            <th>Nombre</th>
            <th style="width:130px;">Estado</th>
            <th style="width:150px;">Tipo</th>
            <th style="width:140px;" class="sat-right">Hasta</th>
            <th style="width:160px;" class="sat-right">Precio</th>
            <th style="width:160px;" class="sat-right">Unitario</th>
            <th style="width:90px;" class="sat-right">Sort</th>
            <th style="width:280px;" class="sat-right">Acciones</th>
          </tr>
        </thead>
        <tbody>
        @forelse($rows as $r)
          @php
            $unit = (string)($r->unit ?? 'range_per_xml');

            $hasta = $r->max_xml === null ? null : (int)$r->max_xml;

            // ✅ Precio según tipo:
            // - flat         => usa flat_price (precio total)
            // - range_per_xml=> usa price_per_xml (precio unitario por XML)
            $priceFlat   = $r->flat_price !== null ? (float)$r->flat_price : null;
            $pricePerXml = $r->price_per_xml !== null ? (float)$r->price_per_xml : null;

            $precio = null;
            $unitario = null;

            if ($unit === 'flat') {
              $precio = $priceFlat;
              $unitario = ($hasta && $hasta > 0 && $precio !== null) ? ($precio / $hasta) : null;
            } else {
              $precio = $pricePerXml;
              $unitario = $pricePerXml; // ya es “por XML”
            }

            $precioLabel = ($unit === 'flat') ? 'Precio' : 'Precio/XML';
            $unitLabel   = ($unit === 'flat') ? 'Unitario' : 'Unitario/XML';
          @endphp
          <tr>
            <td style="color:var(--sx-mut,#64748b); font-weight:800;">#{{ $r->id }}</td>

            <td>
              <div class="sat-rowtitle">{{ $r->name }}</div>
              <div class="sat-small">
                Moneda: {{ $r->currency ?? 'MXN' }}
                · Rango: {{ (int)$r->min_xml }} – {{ $r->max_xml === null ? '—' : (int)$r->max_xml }}
                · {{ $precioLabel }}: {{ $precio === null ? '—' : '$'.number_format($precio, 2) }}
              </div>
            </td>

            <td>
              @if((int)$r->active === 1)
                <span class="sat-badge ok">● Activa</span>
              @else
                <span class="sat-badge off">● Inactiva</span>
              @endif
            </td>

            <td>
              <span class="sat-badge info">{{ $unit === 'flat' ? 'Volumen (flat)' : 'Rango/XML' }}</span>
            </td>

            <td class="sat-right" style="font-weight:950;">
              {{ $hasta === null ? '—' : number_format($hasta, 0) }}
            </td>

            <td class="sat-right" style="font-weight:950;">
              {{ $precio === null ? '—' : '$'.number_format($precio, 2) }}
              <div class="sat-small" style="margin-top:4px;">{{ $precioLabel }}</div>
            </td>

            <td class="sat-right" style="font-weight:950;">
              {{ $unitario === null ? '—' : '$'.number_format($unitario, 2) }}
              <div class="sat-small" style="margin-top:4px;">{{ $unitLabel }}</div>
            </td>

            <td class="sat-right" style="color:var(--sx-mut,#64748b); font-weight:900;">{{ (int)$r->sort }}</td>

            <td class="sat-right">
              <div class="sat-acts">
                <a class="sat-actbtn" href="{{ route('admin.sat.prices.edit',$r->id) }}">Editar</a>

                <form method="POST" action="{{ route('admin.sat.prices.toggle',$r->id) }}" onsubmit="return confirm('¿Cambiar estado?');" style="display:inline;">
                  @csrf
                  <button class="sat-actbtn" type="submit">{{ (int)$r->active === 1 ? 'Desactivar' : 'Activar' }}</button>
                </form>

                <form method="POST" action="{{ route('admin.sat.prices.destroy',$r->id) }}" onsubmit="return confirm('¿Eliminar regla? Esta acción no se puede deshacer.');" style="display:inline;">
                  @csrf
                  @method('DELETE')
                  <button class="sat-actbtn danger" type="submit">Eliminar</button>
                </form>
              </div>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="9" style="text-align:center; padding:22px; color:var(--sx-mut,#64748b); font-weight:800;">
              No hay reglas. Crea la primera con “Nueva regla”.
            </td>
          </tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div style="margin-top:14px;">
    {{ $rows->links() }}
  </div>
</div>
@endsection
