{{-- resources/views/admin/billing/invoices/requests.blade.php (v2.3 · HUB-first · UI CLARO · ZIP real + email-ready real) --}}
@extends('layouts.admin')

@section('title', 'Facturación · Solicitudes de factura')
@section('pageClass', 'p360-invoice-requests')

@php
  $mode   = $mode ?? 'hub';      // hub|legacy|missing
  $q      = $q ?? request('q','');
  $status = $status ?? request('status','');
  $period = $period ?? request('period','');

  // Status UI por modo:
  // legacy: requested|in_progress|done|rejected
  // hub:    requested|in_progress|issued|rejected  (si quieres usar issued)
  $statusOptions = $mode === 'legacy'
      ? ['requested','in_progress','done','rejected']
      : ['requested','in_progress','issued','rejected'];

  // Para filtrar: si viene "invoiced" viejo, lo convertimos a done/issued solo para UI.
  if ($status === 'invoiced') $status = ($mode === 'legacy') ? 'done' : 'issued';
@endphp

@push('styles')
<style>
/* ===== Pactopia360 · Admin Billing · Invoice Requests (v2.3 LIGHT) ===== */
.p360-invoice-requests{
  --bg:#f6f7fb;
  --bg2:#ffffff;

  --card:#ffffff;
  --card2:#f8fafc;

  --ink:#0f172a;
  --mut:#64748b;

  --line:rgba(15,23,42,.10);
  --line2:rgba(15,23,42,.14);

  --shadow:0 18px 50px rgba(15,23,42,.10);
  --radius:18px;
  --radius-sm:14px;

  --ok:#10b981;
  --warn:#f59e0b;
  --bad:#ef4444;
  --info:#2563eb;

  --pill-bg:rgba(15,23,42,.04);
  --btn-bg:rgba(15,23,42,.06);
  --btn-bg2:rgba(15,23,42,.10);
  --focus:rgba(37,99,235,.18);

  background:
    radial-gradient(1200px 600px at 15% -10%, rgba(16,185,129,.12), transparent 58%),
    radial-gradient(900px 500px at 85% 0%, rgba(37,99,235,.10), transparent 58%),
    radial-gradient(700px 500px at 50% 110%, rgba(244,63,94,.10), transparent 58%),
    var(--bg);
  color:var(--ink);
}

.p360-invoice-requests .p360-wrap{
  width: min(1400px, calc(100vw - 48px));
  margin: 22px auto 40px;
}

.p360-invoice-requests .p360-top{
  display:flex;
  align-items:flex-end;
  justify-content:space-between;
  gap:16px;
  margin-bottom:14px;
}

.p360-invoice-requests .p360-title{ display:flex; flex-direction:column; gap:4px; }
.p360-invoice-requests h1{ margin:0; font-size:22px; letter-spacing:.2px; }
.p360-invoice-requests .sub{ color:var(--mut); font-size:13px; }

.p360-invoice-requests .p360-card{
  background: linear-gradient(180deg, rgba(255,255,255,.92), rgba(255,255,255,.82));
  border:1px solid var(--line);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  overflow:hidden;
}

.p360-invoice-requests .p360-toolbar{
  padding:14px;
  display:flex;
  flex-wrap:wrap;
  gap:10px;
  align-items:center;
  justify-content:space-between;
  border-bottom:1px solid var(--line);
  background: rgba(255,255,255,.68);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
}

.p360-invoice-requests .filters{
  display:flex;
  flex-wrap:wrap;
  gap:10px;
  align-items:center;
}

.p360-invoice-requests .field{ display:flex; flex-direction:column; gap:6px; }
.p360-invoice-requests .label{ color:var(--mut); font-size:12px; }

.p360-invoice-requests input,
.p360-invoice-requests select{
  height:42px;
  padding:10px 12px;
  border-radius: 12px;
  border:1px solid var(--line2);
  background: rgba(255,255,255,.92);
  color: var(--ink);
  outline:none;
}
.p360-invoice-requests input::placeholder{ color: rgba(100,116,139,.85); }
.p360-invoice-requests input:focus,
.p360-invoice-requests select:focus{
  border-color: rgba(37,99,235,.50);
  box-shadow: 0 0 0 4px var(--focus);
}

.p360-invoice-requests .btn{
  height:42px;
  padding:0 14px;
  border-radius: 12px;
  border:1px solid var(--line2);
  background: var(--btn-bg);
  color: var(--ink);
  cursor:pointer;
  transition: transform .08s ease, background .15s ease, border-color .15s ease;
  font-weight:700;
  letter-spacing:.2px;
}
.p360-invoice-requests .btn:hover{ background: var(--btn-bg2); }
.p360-invoice-requests .btn:active{ transform: translateY(1px); }
.p360-invoice-requests .btn.primary{
  background: rgba(16,185,129,.14);
  border-color: rgba(16,185,129,.32);
}
.p360-invoice-requests .btn.primary:hover{ background: rgba(16,185,129,.18); }
.p360-invoice-requests .btn.ghost{ background: transparent; }
.p360-invoice-requests .btn.danger{ border-color: rgba(239,68,68,.35); background: rgba(239,68,68,.10); }
.p360-invoice-requests .btn.info{ border-color: rgba(37,99,235,.28); background: rgba(37,99,235,.10); }

.p360-invoice-requests .badges{
  display:flex;
  gap:8px;
  align-items:center;
  flex-wrap:wrap;
}

.p360-invoice-requests .pill{
  display:inline-flex;
  align-items:center;
  gap:8px;
  padding:8px 10px;
  border-radius:999px;
  border:1px solid var(--line);
  background: var(--pill-bg);
  color: var(--ink);
  font-size:12px;
}

.p360-invoice-requests .dot{
  width:8px;height:8px;border-radius:99px;display:inline-block;
  background: var(--info);
}
.p360-invoice-requests .dot.ok{ background: var(--ok); }
.p360-invoice-requests .dot.warn{ background: var(--warn); }
.p360-invoice-requests .dot.bad{ background: var(--bad); }

.p360-invoice-requests .alerts{
  padding: 12px 14px;
  display:grid;
  gap:10px;
}
.p360-invoice-requests .alert{
  border-radius: 14px;
  padding: 10px 12px;
  border: 1px solid var(--line);
  background: rgba(15,23,42,.04);
  color: var(--ink);
  font-size: 13px;
}
.p360-invoice-requests .alert.ok{ border-color: rgba(16,185,129,.35); background: rgba(16,185,129,.10); }
.p360-invoice-requests .alert.warn{ border-color: rgba(245,158,11,.40); background: rgba(245,158,11,.12); }
.p360-invoice-requests .alert.bad{ border-color: rgba(239,68,68,.40); background: rgba(239,68,68,.10); }

.p360-invoice-requests .tablewrap{ overflow:auto; }
.p360-invoice-requests table{
  width:100%;
  border-collapse: separate;
  border-spacing: 0;
  min-width: 1180px;
}

.p360-invoice-requests thead th{
  text-align:left;
  font-size:12px;
  letter-spacing:.3px;
  text-transform:uppercase;
  color: var(--mut);
  padding: 12px 14px;
  border-bottom: 1px solid var(--line);
  background: rgba(248,250,252,.92);
  position: sticky;
  top: 0;
  z-index: 2;
}

.p360-invoice-requests tbody td{
  padding: 12px 14px;
  border-bottom: 1px solid var(--line);
  vertical-align: top;
}
.p360-invoice-requests tbody tr:hover td{ background: rgba(15,23,42,.03); }

.p360-invoice-requests .mono{
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono","Courier New", monospace;
  font-size: 12px;
}
.p360-invoice-requests .mut{ color: var(--mut); font-size: 12px; }

.p360-invoice-requests .statusPill{
  display:inline-flex;
  align-items:center;
  gap:8px;
  padding:7px 10px;
  border-radius:999px;
  border:1px solid var(--line);
  background: rgba(15,23,42,.04);
  font-size:12px;
}
.p360-invoice-requests .statusPill .dot{ width:7px; height:7px; }

/* Colores por estado (normalizados para UI) */
.p360-invoice-requests .statusPill.requested{ border-color: rgba(245,158,11,.35); background: rgba(245,158,11,.10); }
.p360-invoice-requests .statusPill.in_progress{ border-color: rgba(37,99,235,.30); background: rgba(37,99,235,.08); }
.p360-invoice-requests .statusPill.done,
.p360-invoice-requests .statusPill.issued{ border-color: rgba(16,185,129,.32); background: rgba(16,185,129,.10); }
.p360-invoice-requests .statusPill.rejected{ border-color: rgba(239,68,68,.34); background: rgba(239,68,68,.09); }

.p360-invoice-requests .actions{
  display:flex;
  flex-wrap:wrap;
  gap:10px;
  align-items:flex-start;
}
.p360-invoice-requests .actions form{
  display:flex;
  gap:8px;
  align-items:center;
  flex-wrap:wrap;
  margin:0;
}

.p360-invoice-requests .actions .mini{
  height:38px;
  padding: 0 12px;
  border-radius: 12px;
}

.p360-invoice-requests .actions input,
.p360-invoice-requests .actions select{
  height:38px;
  border-radius: 12px;
  padding: 8px 10px;
  background: rgba(255,255,255,.92);
}

.p360-invoice-requests .actions input.uuid{ min-width: 240px; }
.p360-invoice-requests .actions input.notes{ min-width: 280px; }
.p360-invoice-requests .actions input.to{ min-width: 260px; }
.p360-invoice-requests .actions input.file{
  min-width: 280px;
  padding: 6px 10px !important;
}

.p360-invoice-requests .notesCell{
  max-width: 420px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.p360-invoice-requests .footer{
  padding: 12px 14px;
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:12px;
  border-top: 1px solid var(--line);
  background: rgba(248,250,252,.92);
  color: var(--mut);
  font-size: 12px;
}

.p360-invoice-requests .pagination{ padding: 12px 14px; }
.p360-invoice-requests .pagination nav{ display:flex; justify-content:center; }

@media (max-width: 880px){
  .p360-invoice-requests .p360-wrap{ width: calc(100vw - 28px); }
  .p360-invoice-requests .p360-top{ align-items:flex-start; flex-direction:column; }
  .p360-invoice-requests table{ min-width: 1140px; }
}
</style>
@endpush

@section('content')
<div class="p360-wrap">

  <div class="p360-top">
    <div class="p360-title">
      <h1>Solicitudes de factura</h1>
      <div class="sub">
        Administración de solicitudes (HUB-first). Modo actual:
        <span class="mono">{{ $mode }}</span>
      </div>
    </div>

    <div class="badges">
      <span class="pill">
        <span class="dot {{ $mode==='hub' ? 'ok' : ($mode==='legacy' ? 'warn' : 'bad') }}"></span>
        <span class="mono">
          {{ $mode==='hub' ? 'billing_invoice_requests' : ($mode==='legacy' ? 'invoice_requests' : 'sin tabla') }}
        </span>
      </span>

      <a class="btn ghost" href="{{ url()->current() }}">Limpiar</a>
    </div>
  </div>

  <div class="p360-card">

    <div class="p360-toolbar">
      <form class="filters" method="GET" action="{{ url()->current() }}">
        <div class="field">
          <div class="label">Buscar</div>
          <input name="q" value="{{ $q }}" placeholder="Cuenta, RFC, UUID, notas, periodo…">
        </div>

        <div class="field">
          <div class="label">Periodo</div>
          <input name="period" value="{{ $period }}" placeholder="YYYY-MM">
        </div>

        <div class="field">
          <div class="label">Estatus</div>
          <select name="status">
            <option value="" @selected($status==='')>Todos</option>
            @foreach($statusOptions as $s)
              <option value="{{ $s }}" @selected($status===$s)>{{ $s }}</option>
            @endforeach
          </select>
        </div>

        <div class="field" style="justify-content:flex-end">
          <div class="label">&nbsp;</div>
          <button class="btn primary" type="submit">Filtrar</button>
        </div>
      </form>

      <div class="badges">
        @php
          $total = 0;
          if (is_object($rows) && method_exists($rows,'total')) $total = (int)$rows->total();
          elseif (is_iterable($rows)) $total = is_countable($rows) ? count($rows) : 0;
        @endphp
        <span class="pill">
          <span class="dot"></span>
          <span>Total: <span class="mono">{{ $total }}</span></span>
        </span>
      </div>
    </div>

    <div class="alerts">
      @if(!empty($error))
        <div class="alert warn">{{ $error }}</div>
      @endif

      @if(session('ok'))
        <div class="alert ok">{{ session('ok') }}</div>
      @endif

      @if(session('warn'))
        <div class="alert warn">{{ session('warn') }}</div>
      @endif

      @if(session('bad'))
        <div class="alert bad">{{ session('bad') }}</div>
      @endif

      @if($errors->any())
        <div class="alert bad">{{ $errors->first() }}</div>
      @endif
    </div>

    @if(method_exists($rows,'links') || is_iterable($rows))
      <div class="tablewrap">
        <table>
          <thead>
            <tr>
              <th style="width:90px">ID</th>
              <th style="width:260px">Cuenta</th>
              <th style="width:120px">Periodo</th>
              <th style="width:170px">Estatus</th>
              <th>Detalle</th>
              <th style="width:560px">Acciones</th>
            </tr>
          </thead>

          <tbody>
          @forelse($rows as $r)
            @php
              $stRaw = (string)($r->status ?? '');
              if ($stRaw === '') $stRaw = 'requested';

              // normalización UI:
              // legacy: requested|in_progress|done|rejected
              // hub:    requested|in_progress|issued|rejected
              $stUi = strtolower(trim($stRaw));
              if ($stUi === 'invoiced') $stUi = ($mode==='legacy') ? 'done' : 'issued';
              if (!in_array($stUi, $statusOptions, true)) $stUi = 'requested';

              $name = trim((string)($r->account_name ?? ''));
              $rfc  = trim((string)($r->account_rfc ?? ''));
              $mail = trim((string)($r->account_email ?? ''));
              $acct = (string)($r->account_id ?? '—');

              $uuid  = (string)($r->cfdi_uuid ?? '');
              $notes = trim((string)($r->notes ?? ''));
              $per   = (string)($r->period ?? '—');

              $zipPath = (string)($r->zip_path ?? '');
              $zipDisk = (string)($r->zip_disk ?? '');
              $hasZip  = trim($zipPath) !== '';

              $zipLabel = $hasZip ? 'ZIP listo' : 'ZIP no adjunto';
              $zipDot   = $hasZip ? 'ok' : 'warn';

              $dotForStatus = ($stUi==='rejected') ? 'bad' : (($stUi==='requested') ? 'warn' : 'ok');
            @endphp

            <tr>
              <td class="mono">{{ $r->id }}</td>

              <td>
                <div class="mono">{{ $acct }}</div>
                <div class="mut">
                  {{ $name !== '' ? e($name) : '—' }}
                  @if($rfc !== '') · <span class="mono">{{ $rfc }}</span>@endif
                </div>
                @if($mail !== '')
                  <div class="mut">Email: <span class="mono">{{ $mail }}</span></div>
                @endif
              </td>

              <td class="mono">{{ $per }}</td>

              <td>
                <span class="statusPill {{ $stUi }}">
                  <span class="dot {{ $dotForStatus }}"></span>
                  <span class="mono">{{ $stUi }}</span>
                </span>

                <div class="mut" style="margin-top:8px">
                  <span class="pill" style="padding:6px 10px">
                    <span class="dot {{ $zipDot }}"></span>
                    <span class="mono">{{ $zipLabel }}</span>
                  </span>
                </div>
              </td>

              <td>
                <div class="mut">UUID</div>
                <div class="mono">{{ $uuid !== '' ? $uuid : '—' }}</div>

                <div class="mut" style="margin-top:8px">Notas</div>
                <div class="notesCell">{{ $notes !== '' ? e($notes) : '—' }}</div>

                @if($hasZip)
                  <div class="mut" style="margin-top:8px">ZIP</div>
                  <div class="mono" style="word-break:break-all">
                    {{ $zipDisk !== '' ? ($zipDisk.':') : '' }}{{ $zipPath }}
                  </div>
                @endif
              </td>

              <td>
                <div class="actions">

                  {{-- A) Guardar status/uuid/notas + subir ZIP (ESTO SÍ guarda el ZIP) --}}
                  <form method="POST"
                        action="{{ route('admin.billing.invoices.requests.status', $r->id) }}"
                        enctype="multipart/form-data">
                    @csrf

                    <select name="status" style="height:38px">
                      @foreach($statusOptions as $s)
                        <option value="{{ $s }}" @selected($stUi===$s)>{{ $s }}</option>
                      @endforeach
                    </select>

                    <input class="uuid" name="cfdi_uuid" placeholder="UUID (opcional)" value="{{ $uuid }}">
                    <input class="notes" name="notes" placeholder="Notas (opcional)" value="{{ $notes }}">

                    <input class="file" type="file" name="zip" accept=".zip,application/zip,application/x-zip-compressed">

                    <button class="btn mini" type="submit">Guardar</button>
                  </form>

                  {{-- B) Atajos (solo status) --}}
                  <form method="POST" action="{{ route('admin.billing.invoices.requests.status', $r->id) }}">
                    @csrf
                    <input type="hidden" name="status" value="in_progress">
                    <button class="btn mini info" type="submit">En proceso</button>
                  </form>

                  <form method="POST" action="{{ route('admin.billing.invoices.requests.status', $r->id) }}">
                    @csrf
                    <input type="hidden" name="status" value="{{ $mode==='legacy' ? 'done' : 'issued' }}">
                    <button class="btn mini primary" type="submit">Emitida</button>
                  </form>

                  <form method="POST" action="{{ route('admin.billing.invoices.requests.status', $r->id) }}">
                    @csrf
                    <input type="hidden" name="status" value="rejected">
                    <button class="btn mini danger" type="submit">Rechazar</button>
                  </form>

                  {{-- C) Email READY (NO sube ZIP aquí; usa el ZIP guardado en DB) --}}
                  <form method="POST" action="{{ route('admin.billing.invoices.requests.email_ready', $r->id) }}">
                    @csrf
                    <input class="to" name="to" placeholder="correo destino (vacío=del cliente)" value="">
                    <button class="btn mini primary" type="submit">Enviar “Factura lista”</button>
                  </form>

                </div>

                <div class="mut" style="margin-top:10px">
                  Flujo recomendado: 1) “Guardar” para subir ZIP/UUID/notas y marcar estatus; 2) “Enviar Factura lista” para notificar al cliente y adjuntar ZIP si existe.
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="mut" style="padding:18px">
                No hay solicitudes con los filtros actuales.
              </td>
            </tr>
          @endforelse
          </tbody>
        </table>
      </div>
    @endif

    @if(method_exists($rows,'links'))
      <div class="pagination">{!! $rows->links() !!}</div>
    @endif

    <div class="footer">
      <div>
        @if($mode==='hub')
          Operando sobre <span class="mono">billing_invoice_requests</span>.
        @elseif($mode==='legacy')
          Operando sobre <span class="mono">invoice_requests</span> (legacy).
        @else
          No hay tabla disponible.
        @endif
      </div>
      <div class="mono">Pactopia360 · Admin Billing</div>
    </div>

  </div>
</div>
@endsection
