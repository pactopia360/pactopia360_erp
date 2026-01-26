{{-- resources/views/admin/billing/sat/discounts/index.blade.php --}}
@extends('layouts.admin')

@section('title','SAT · Códigos de descuento')

@section('content')
@php
  $q      = $q      ?? request('q','');
  $active = $active ?? request('active','');
  $scope  = $scope  ?? request('scope','');

  // Flash compat: soporta success (nuevo) y ok (legacy)
  $flashOk    = session('success') ?? session('ok');
  $flashError = session('error');
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

  .sat-form{ padding:12px; display:flex; gap:10px; align-items:center; flex-wrap:wrap; border-bottom:1px solid color-mix(in oklab, var(--sx-ink, #0f172a) 10%, transparent); }
  .sat-inp, .sat-sel{
    border:1px solid color-mix(in oklab, var(--sx-ink, #0f172a) 14%, transparent);
    background: color-mix(in oklab, #fff 92%, transparent);
    color:var(--sx-ink, #0f172a);
    border-radius:12px;
    padding:10px 12px;
    font-weight:750;
    font-size:13px;
    outline:none;
    min-height:40px;
  }
  html.theme-dark .sat-inp, html.theme-dark .sat-sel{
    background: color-mix(in oklab, #0b1220 82%, transparent);
  }
  .sat-inp{ flex:1 1 280px; min-width:240px; }
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

  .sat-rowtitle{ font-weight:950; letter-spacing:.02em; }
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
      <h1 class="sat-title">SAT · Códigos de descuento</h1>
      <div class="sat-sub">Cupones globales, por cuenta o por socio/distribuidor.</div>
    </div>

    <div class="sat-actions">
      <a class="sat-btn" href="{{ route('admin.sat.prices.index') }}">📦 Lista de precios</a>
      <a class="sat-btn primary" href="{{ route('admin.sat.discounts.create') }}">➕ Nuevo código</a>
    </div>
  </div>

  @if($flashOk)
    <div class="sat-card" style="margin-top:14px; padding:12px; border-color:color-mix(in oklab, #16a34a 30%, transparent);">
      <div style="font-weight:900;">✅ {{ $flashOk }}</div>
    </div>
  @endif

  @if($flashError)
    <div class="sat-card" style="margin-top:14px; padding:12px; border-color:color-mix(in oklab, #ef4444 34%, transparent);">
      <div style="font-weight:900;">⚠️ {{ $flashError }}</div>
    </div>
  @endif

  <div class="sat-card" style="margin-top:14px;">
    <form class="sat-form" method="GET" action="{{ route('admin.sat.discounts.index') }}">
      <select class="sat-sel" name="active" style="width:190px;">
        <option value=""  {{ ($active===null || $active==='') ? 'selected' : '' }}>Estado: Todos</option>
        <option value="1" {{ (string)$active==='1' ? 'selected' : '' }}>Activos</option>
        <option value="0" {{ (string)$active==='0' ? 'selected' : '' }}>Inactivos</option>
      </select>

      <select class="sat-sel" name="scope" style="width:210px;">
        <option value=""        {{ (string)$scope==='' ? 'selected' : '' }}>Scope: Todos</option>
        <option value="global"  {{ (string)$scope==='global' ? 'selected' : '' }}>Global</option>
        <option value="account" {{ (string)$scope==='account' ? 'selected' : '' }}>Por cuenta</option>
        <option value="partner" {{ (string)$scope==='partner' ? 'selected' : '' }}>Socio/Distribuidor</option>
      </select>

      <input class="sat-inp" name="q" value="{{ $q }}" placeholder="Buscar: code, label, account_id, partner_id…">

      <button class="sat-btn" type="submit" style="padding:10px 14px;">Buscar</button>
      <a class="sat-btn" href="{{ route('admin.sat.discounts.index') }}" style="padding:10px 14px;">Limpiar</a>

      <div class="sat-meta">
        {{ $rows->total() }} códigos · {{ $rows->perPage() }}/pág
      </div>
    </form>

    <div style="overflow:auto;">
      <table class="sat-table">
        <thead>
          <tr>
            <th style="width:74px;">ID</th>
            <th style="width:240px;">Código</th>
            <th>Descripción</th>
            <th style="width:160px;">Tipo</th>
            <th style="width:140px;">Scope</th>
            <th style="width:190px;">Vigencia</th>
            <th style="width:140px;">Estado</th>
            <th style="width:280px;" class="sat-right">Acciones</th>
          </tr>
        </thead>
        <tbody>
        @forelse($rows as $r)
          <tr>
            <td style="color:var(--sx-mut,#64748b); font-weight:800;">#{{ $r->id }}</td>

            <td>
              <div class="sat-rowtitle">{{ $r->code }}</div>
              <div class="sat-small">
                @if($r->scope === 'partner')
                  {{ ($r->partner_type ?? 'socio') }} · {{ $r->partner_id ?? '—' }}
                @elseif($r->scope === 'account')
                  Cuenta: {{ $r->account_id ?? '—' }}
                @else
                  Global
                @endif
              </div>
            </td>

            <td>
              <div style="font-weight:900;">{{ $r->label ?: '—' }}</div>
              <div class="sat-small">
                @if($r->type === 'fixed')
                  Descuento fijo: ${{ number_format((float)$r->amount_mxn,2) }} MXN
                @else
                  Descuento: {{ (int)($r->pct ?? 0) }}%
                @endif
              </div>
              <div class="sat-small">
                Usos: {{ (int)($r->uses_count ?? 0) }}{{ $r->max_uses !== null ? (' / ' . (int)$r->max_uses) : '' }}
              </div>
            </td>

            <td>
              <span class="sat-badge info">{{ $r->type === 'fixed' ? 'Fijo (MXN)' : 'Porcentaje' }}</span>
            </td>

            <td>
              <span class="sat-badge off">{{ $r->scope }}</span>
            </td>

            <td class="sat-small">
              <div>Inicio: {{ $r->starts_at ? \Illuminate\Support\Carbon::parse($r->starts_at)->format('Y-m-d') : '—' }}</div>
              <div>Fin: {{ $r->ends_at ? \Illuminate\Support\Carbon::parse($r->ends_at)->format('Y-m-d') : '—' }}</div>
            </td>

            <td>
              @if((int)$r->active === 1)
                <span class="sat-badge ok">● Activo</span>
              @else
                <span class="sat-badge off">● Inactivo</span>
              @endif
            </td>

            <td class="sat-right">
              <div class="sat-acts">
                <a class="sat-actbtn" href="{{ route('admin.sat.discounts.edit',$r->id) }}">Editar</a>

                <form method="POST" action="{{ route('admin.sat.discounts.toggle',$r->id) }}" onsubmit="return confirm('¿Cambiar estado?');" style="display:inline;">
                  @csrf
                  <button class="sat-actbtn" type="submit">{{ (int)$r->active === 1 ? 'Desactivar' : 'Activar' }}</button>
                </form>

                <form method="POST" action="{{ route('admin.sat.discounts.destroy',$r->id) }}" onsubmit="return confirm('¿Eliminar código? Esta acción no se puede deshacer.');" style="display:inline;">
                  @csrf
                  @method('DELETE')
                  <button class="sat-actbtn danger" type="submit">Eliminar</button>
                </form>
              </div>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="8" style="text-align:center; padding:22px; color:var(--sx-mut,#64748b); font-weight:800;">
              No hay códigos. Crea el primero con “Nuevo código”.
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
