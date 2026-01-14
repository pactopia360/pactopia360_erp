{{-- resources/views/admin/billing/statements/show_v10.blade.php --}}
{{-- UI v10+ Â· Admin Â· Estado de cuenta (Detalle) Â· DATA-SAFE: usa $rows/$total/$abono/$saldo del controller. Fallback legacy si no hay items nuevos. --}}
@extends('layouts.admin')

@section('title', 'Estado de cuenta Â· ' . ($period ?? request('period','')))
@section('layout','full')

@php
  use Illuminate\Support\Facades\Route;

  $accountId   = (string)($accountId ?? request()->route('accountId') ?? '');
  $period      = (string)($period ?? request()->route('period') ?? now()->format('Y-m'));
  $periodLabel = (string)($period_label ?? $period);

  $rows        = $rows ?? collect();

  $total       = (float)($total ?? 0);
  $abono       = (float)($abono ?? 0);
  $saldo       = (float)($saldo ?? 0);

  $abonoEdo    = (float)($abono_edo ?? 0);
  $abonoPay    = (float)($abono_pay ?? 0);

  $expected    = (float)($expected_total ?? 0);
  $cargoReal   = (float)($cargo_real ?? 0);

  $statusPago  = strtolower((string)($status_pago ?? 'pendiente'));
  $source      = (string)($source ?? 'new');
  $statementId = (int)($statement_id ?? 0);

  $hasIndex = Route::has('admin.billing.statements.index');
  $hasPdf   = Route::has('admin.billing.statements.pdf');
  $hasEmail = Route::has('admin.billing.statements.email');

  $indexUrl = $hasIndex ? route('admin.billing.statements.index', ['period'=>$period]) : url()->previous();
  $pdfUrl   = ($hasPdf && $accountId) ? route('admin.billing.statements.pdf', ['accountId'=>$accountId,'period'=>$period]) : null;

  // âœ… Endpoints existentes (fallback URL si el name no existe)
  $addItemUrl = Route::has('admin.billing.statements.items')
    ? route('admin.billing.statements.items', ['accountId'=>$accountId,'period'=>$period])
    : (url('/admin/billing/statements/'.$accountId.'/'.$period.'/items'));

  $emailUrl = ($hasEmail && $accountId)
    ? route('admin.billing.statements.email', ['accountId'=>$accountId,'period'=>$period])
    : (url('/admin/billing/statements/'.$accountId.'/'.$period.'/email'));

  $fmtMoney = fn($n) => '$' . number_format((float)$n, 2);

  $statusLbl = match($statusPago){
    'pagado'  => 'PAGADO',
    'parcial' => 'PARCIAL',
    'vencido' => 'VENCIDO',
    'sin_mov' => 'SIN MOV',
    default   => 'PENDIENTE',
  };

  $statusCls = 'sx-pill sx-pill--warn';
  if($statusPago==='pagado') $statusCls='sx-pill sx-pill--ok';
  elseif($statusPago==='vencido') $statusCls='sx-pill sx-pill--bad';
  elseif($statusPago==='sin_mov') $statusCls='sx-pill sx-pill--dim';

  $acc = $account ?? null;
  $razon = trim((string)($acc->razon_social ?? $acc->name ?? ('Cuenta #' . $accountId)));
  $rfc   = trim((string)($acc->rfc ?? ''));
  $email = trim((string)($acc->email ?? ''));

  // âœ… recipients sugeridos por backend
  $recipients = $recipients ?? [];
  if (is_string($recipients)) {
    $recipients = preg_split('/[,\s;]+/', $recipients, -1, PREG_SPLIT_NO_EMPTY) ?: [];
  }
  if ($recipients instanceof \Illuminate\Support\Collection) {
    $recipients = $recipients->values()->all();
  }
  $toDefault = '';
  if (is_array($recipients) && count($recipients) > 0) {
    $toDefault = implode(", ", array_values(array_filter(array_map('trim', $recipients))));
  } elseif ($email) {
    $toDefault = $email;
  }

@endphp

@push('styles')
<style>
  .p360-page{ padding:0 !important; }
  :root{
    --v10-ink: var(--text, #0f172a);
    --v10-mut: var(--muted, #64748b);
    --v10-line: color-mix(in oklab, var(--v10-ink) 12%, transparent);
    --v10-line2: color-mix(in oklab, var(--v10-ink) 8%, transparent);
    --v10-card: var(--card-bg, #fff);
    --v10-bg: color-mix(in oklab, var(--v10-card) 86%, #f6f7fb);
    --v10-shadow: var(--shadow-1, 0 18px 40px rgba(15,23,42,.08));
    --v10-r: 22px;
    --v10-r2: 16px;
    --v10-accent:#7c3aed;
    --v10-ok:#16a34a;
    --v10-warn:#f59e0b;
    --v10-bad:#ef4444;
  }

  .v10-wrap{ padding:16px; }
  .v10-card{
    background:var(--v10-card);
    border:1px solid var(--v10-line);
    border-radius:var(--v10-r);
    box-shadow:var(--v10-shadow);
    overflow:hidden;
  }

  .v10-head{
    padding:16px 18px;
    display:flex;
    justify-content:space-between;
    gap:12px;
    flex-wrap:wrap;
    border-bottom:1px solid var(--v10-line);
    background:
      radial-gradient(1100px 260px at 10% 0%, color-mix(in oklab, var(--v10-accent) 12%, transparent), transparent 60%),
      linear-gradient(180deg, color-mix(in oklab, var(--v10-card) 94%, transparent), color-mix(in oklab, var(--v10-card) 98%, transparent));
  }
  .v10-title{ margin:0; font-size:18px; font-weight:950; letter-spacing:-.01em; color:var(--v10-ink); }
  .v10-sub{ margin-top:6px; color:var(--v10-mut); font-weight:850; font-size:12px; line-height:1.35; max-width:1100px; }
  .v10-actions{ display:flex; gap:8px; flex-wrap:wrap; align-items:center; }

  .sx-btn{
    padding:10px 12px;
    border-radius:14px;
    border:1px solid var(--v10-line);
    font-weight:950;
    cursor:pointer;
    text-decoration:none;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    white-space:nowrap;
    user-select:none;
    background:transparent;
    color:var(--v10-ink);
  }
  .sx-btn--primary{
    background:var(--v10-ink);
    color:#fff;
    border-color: color-mix(in oklab, var(--v10-ink) 35%, var(--v10-line));
  }
  html[data-theme="dark"] .sx-btn--primary{
    background:#111827;
    border-color: rgba(255,255,255,.14);
  }
  .sx-btn--soft{
    background: color-mix(in oklab, var(--v10-card) 92%, transparent);
  }
  .sx-btn--danger{
    background: color-mix(in oklab, var(--v10-bad) 12%, transparent);
    border-color: color-mix(in oklab, var(--v10-bad) 22%, var(--v10-line));
    color: color-mix(in oklab, var(--v10-bad) 60%, var(--v10-ink));
  }
  .sx-btn:disabled{ opacity:.55; cursor:not-allowed; }

  .v10-badges{
    margin-top:10px;
    display:flex;
    gap:8px;
    flex-wrap:wrap;
    align-items:center;
  }
  .sx-badge{
    border:1px solid var(--v10-line);
    background: color-mix(in oklab, var(--v10-card) 92%, transparent);
    border-radius:999px;
    padding:7px 10px;
    font-weight:950;
    color:var(--v10-ink);
    font-size:12px;
    display:inline-flex;
    gap:8px;
    align-items:center;
  }
  .sx-badge .k{ color:var(--v10-mut); font-weight:950; }
  .sx-badge .v{ font-weight:950; }

  .sx-pill{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:7px 10px;
    border-radius:999px;
    border:1px solid transparent;
    font-weight:950;
    font-size:12px;
    white-space:nowrap;
  }
  .sx-pill .dot{ width:8px; height:8px; border-radius:999px; }
  .sx-pill--ok{ background:#dcfce7; color:#166534; border-color:#bbf7d0; }
  .sx-pill--ok .dot{ background:var(--v10-ok); }
  .sx-pill--warn{ background:#fef3c7; color:#92400e; border-color:#fde68a; }
  .sx-pill--warn .dot{ background:var(--v10-warn); }
  .sx-pill--bad{ background:#fee2e2; color:#991b1b; border-color:#fecaca; }
  .sx-pill--bad .dot{ background:var(--v10-bad); }
  .sx-pill--dim{ background: color-mix(in oklab, var(--v10-ink) 6%, transparent); color:var(--v10-mut); border-color:var(--v10-line); }
  .sx-pill--dim .dot{ background: color-mix(in oklab, var(--v10-ink) 30%, transparent); }

  .v10-kpis{
    padding:14px 18px 16px;
    border-bottom:1px solid var(--v10-line);
    display:grid;
    grid-template-columns: repeat(4, minmax(220px, 1fr));
    gap:10px;
    background: linear-gradient(180deg, color-mix(in oklab, var(--v10-card) 94%, transparent), transparent);
  }
  @media(max-width: 1200px){ .v10-kpis{ grid-template-columns: repeat(2, minmax(220px, 1fr)); } }
  @media(max-width: 720px){ .v10-kpis{ grid-template-columns: 1fr; } }

  .v10-kpi{
    border:1px solid var(--v10-line2);
    border-radius:18px;
    padding:12px;
    background:
      radial-gradient(120px 120px at 10% 0%, color-mix(in oklab, var(--v10-accent) 14%, transparent), transparent 60%),
      color-mix(in oklab, var(--v10-card) 92%, transparent);
  }
  .v10-kpi .k{ font-size:12px; color:var(--v10-mut); font-weight:950; text-transform:uppercase; letter-spacing:.05em; }
  .v10-kpi .v{ margin-top:6px; font-size:18px; font-weight:950; color:var(--v10-ink); }
  .v10-kpi .s{ margin-top:6px; font-size:12px; color:var(--v10-mut); font-weight:850; line-height:1.35; }
  .mono{ font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas,"Liberation Mono","Courier New", monospace; font-weight:900; }

  .v10-body{ padding:16px 18px 18px; background: var(--v10-bg); }
  .v10-grid{
    display:grid;
    grid-template-columns: 1.6fr 0.9fr;
    gap:12px;
    align-items:start;
  }
  @media(max-width: 1200px){ .v10-grid{ grid-template-columns: 1fr; } }

  .v10-panel{
    background:var(--v10-card);
    border:1px solid var(--v10-line);
    border-radius:20px;
    overflow:hidden;
  }
  .v10-panelHead{
    padding:12px 14px;
    border-bottom:1px solid var(--v10-line);
    display:flex;
    justify-content:space-between;
    gap:10px;
    flex-wrap:wrap;
    align-items:center;
    background: color-mix(in oklab, var(--v10-card) 96%, transparent);
  }
  .v10-panelTitle{ font-weight:950; color:var(--v10-ink); }
  .v10-panelDesc{ font-size:12px; color:var(--v10-mut); font-weight:850; }
  .v10-panelBody{ padding:14px; }

  .v10-table{ width:100%; border-collapse:collapse; }
  .v10-table th{
    padding:10px 12px;
    text-align:left;
    font-size:12px;
    color:var(--v10-mut);
    font-weight:950;
    text-transform:uppercase;
    letter-spacing:.05em;
    border-bottom:1px solid var(--v10-line);
    background: color-mix(in oklab, var(--v10-card) 96%, transparent);
  }
  .v10-table td{
    padding:12px 12px;
    border-bottom:1px solid var(--v10-line2);
    color:var(--v10-ink);
    font-weight:850;
    vertical-align:top;
  }
  .ta-r{ text-align:right; }
  .mut{ color:var(--v10-mut); font-weight:850; font-size:12px; line-height:1.35; margin-top:4px; }

  .v10-empty{
    padding:14px;
    color:var(--v10-mut);
    font-weight:900;
  }

  .v10-sideStack{ display:grid; gap:12px; }
  .v10-kv{ display:grid; gap:8px; }
  .v10-kvRow{
    display:flex; justify-content:space-between; gap:10px;
    border-bottom:1px dashed var(--v10-line2);
    padding-bottom:8px;
  }
  .v10-kvRow:last-child{ border-bottom:0; padding-bottom:0; }
  .v10-kvRow .k{ color:var(--v10-mut); font-weight:950; font-size:12px; }
  .v10-kvRow .v{ color:var(--v10-ink); font-weight:950; font-size:12px; text-align:right; }

  .v10-note{
    margin-top:10px;
    padding:10px 12px;
    border-radius:14px;
    border:1px solid var(--v10-line);
    background: color-mix(in oklab, var(--v10-card) 92%, transparent);
    color:var(--v10-mut);
    font-weight:900;
    font-size:12px;
    line-height:1.35;
  }

  /* ===== Modal simple (sin dependencias) ===== */
  .sx-modal{ position:fixed; inset:0; display:none; z-index:9999; }
  .sx-modal.is-open{ display:block; }
  .sx-modal__bg{ position:absolute; inset:0; background:rgba(15,23,42,.55); backdrop-filter: blur(2px); }
  .sx-modal__card{
    position:relative;
    margin:6vh auto;
    width:min(720px, calc(100% - 28px));
    background:var(--v10-card);
    border:1px solid var(--v10-line);
    border-radius:20px;
    box-shadow: var(--v10-shadow);
    overflow:hidden;
  }
  .sx-modal__head{ padding:14px 16px; border-bottom:1px solid var(--v10-line); display:flex; justify-content:space-between; gap:10px; align-items:center; }
  .sx-modal__title{ font-weight:950; color:var(--v10-ink); }
  .sx-modal__body{ padding:14px 16px; background: var(--v10-bg); }
  .sx-modal__foot{ padding:12px 16px; border-top:1px solid var(--v10-line); display:flex; justify-content:flex-end; gap:10px; flex-wrap:wrap; }

  .sx-field{ display:grid; gap:6px; margin-bottom:12px; }
  .sx-label{ font-size:12px; color:var(--v10-mut); font-weight:950; text-transform:uppercase; letter-spacing:.05em; }
  .sx-input, .sx-select, .sx-textarea{
    width:100%;
    border:1px solid var(--v10-line);
    border-radius:14px;
    padding:10px 12px;
    background: color-mix(in oklab, var(--v10-card) 94%, transparent);
    color:var(--v10-ink);
    font-weight:850;
    outline:none;
  }
  .sx-textarea{ min-height:88px; resize:vertical; }
  .sx-help{ font-size:12px; color:var(--v10-mut); font-weight:850; line-height:1.35; }
  .sx-row2{ display:grid; grid-template-columns: 1fr 1fr; gap:10px; }
  @media(max-width:720px){ .sx-row2{ grid-template-columns: 1fr; } }
</style>
@endpush

@section('content')
<div class="v10-wrap">
  <div class="v10-card">

    {{-- Header --}}
    <div class="v10-head">
      <div>
        <div class="v10-title">Estado de cuenta Â· <span class="mono">{{ $period }}</span></div>
        <div class="v10-sub">
          <strong style="color:var(--v10-ink)">{{ $razon }}</strong>
          @if($rfc) <span class="mono" style="margin-left:8px;">RFC: {{ $rfc }}</span>@endif
          @if($email) <span class="mono" style="margin-left:8px;">{{ $email }}</span>@endif
        </div>

        <div class="v10-badges">
          <span class="sx-badge"><span class="k">Periodo</span> <span class="v">{{ $periodLabel }}</span></span>
          <span class="sx-badge"><span class="k">Cuenta</span> <span class="v mono">#{{ $accountId }}</span></span>
          <span class="sx-badge"><span class="k">Statement</span> <span class="v mono">{{ $statementId ?: 'â€”' }}</span></span>
          <span class="{{ $statusCls }}"><span class="dot"></span>{{ $statusLbl }}</span>
          <span class="sx-badge"><span class="k">Fuente</span> <span class="v mono">{{ strtoupper($source) }}</span></span>
          <span class="sx-badge"><span class="k">Movs</span> <span class="v mono">{{ $rows->count() }}</span></span>
        </div>
      </div>

      <div class="v10-actions">
        <a class="sx-btn sx-btn--soft" href="{{ $indexUrl }}">Volver</a>
        <button class="sx-btn sx-btn--soft" type="button" data-open-modal="#modalAddMove">Agregar movimiento</button>

        @if($pdfUrl)
          <a class="sx-btn sx-btn--primary" href="{{ $pdfUrl }}">Descargar PDF</a>
        @else
          <button class="sx-btn sx-btn--primary" type="button" disabled>Descargar PDF</button>
        @endif
      </div>
    </div>

    {{-- KPIs --}}
    <div class="v10-kpis">
      <div class="v10-kpi">
        <div class="k">Total</div>
        <div class="v">{{ $fmtMoney($total) }}</div>
        <div class="s">Si no hay cargos, se muestra el esperado por licencia ({{ $fmtMoney($expected) }}).</div>
      </div>
      <div class="v10-kpi">
        <div class="k">Cargos capturados</div>
        <div class="v">{{ $fmtMoney($cargoReal) }}</div>
        <div class="s">Suma de cargos de movimientos (new o legacy).</div>
      </div>
      <div class="v10-kpi">
        <div class="k">Pagado</div>
        <div class="v">{{ $fmtMoney($abono) }}</div>
        <div class="s">Abonos mov: <span class="mono">{{ $fmtMoney($abonoEdo) }}</span> Â· Payments: <span class="mono">{{ $fmtMoney($abonoPay) }}</span></div>
      </div>
      <div class="v10-kpi">
        <div class="k">Saldo</div>
        <div class="v">{{ $fmtMoney($saldo) }}</div>
        <div class="s">Saldo calculado = total - pagado.</div>
      </div>
    </div>

    {{-- Body --}}
    <div class="v10-body">
      <div class="v10-grid">

        {{-- Movimientos --}}
        <div class="v10-panel">
          <div class="v10-panelHead">
            <div>
              <div class="v10-panelTitle">Movimientos</div>
              <div class="v10-panelDesc">
                @if($source === 'legacy')
                  EstÃ¡s viendo fallback legacy de <span class="mono">estados_cuenta</span>. Al agregar un movimiento, el sistema empezarÃ¡ a poblar <span class="mono">billing_statement_items</span>.
                @else
                  EstÃ¡s operando sobre <span class="mono">billing_statement_items</span> (nuevo).
                @endif
              </div>
            </div>

            <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
              <span class="sx-badge"><span class="k">Total</span> <span class="v mono">{{ $fmtMoney($total) }}</span></span>
              <span class="sx-badge"><span class="k">Pagado</span> <span class="v mono">{{ $fmtMoney($abono) }}</span></span>
              <span class="sx-badge"><span class="k">Saldo</span> <span class="v mono">{{ $fmtMoney($saldo) }}</span></span>
            </div>
          </div>

          @if($rows->count() <= 0)
            <div class="v10-empty">Sin movimientos para este periodo.</div>
          @else
            <div class="v10-panelBody" style="padding:0;">
              <table class="v10-table">
                <thead>
                  <tr>
                    <th>Concepto</th>
                    <th style="width:160px;">Fecha</th>
                    <th class="ta-r" style="width:140px;">Cargo</th>
                    <th class="ta-r" style="width:140px;">Abono</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($rows as $r)
                    @php
                      $concepto = (string)($r->concepto ?? 'â€”');
                      $detalle  = (string)($r->detalle ?? '');
                      $cargoIt  = (float)($r->cargo ?? 0);
                      $abonoIt  = (float)($r->abono ?? 0);
                      $created  = (string)($r->created_at ?? '');
                    @endphp
                    <tr>
                      <td>
                        <div style="font-weight:950;">{{ $concepto }}</div>
                        @if($detalle)
                          <div class="mut">{{ $detalle }}</div>
                        @endif
                      </td>
                      <td class="mono">{{ $created ?: 'â€”' }}</td>
                      <td class="ta-r mono">{{ $cargoIt > 0 ? $fmtMoney($cargoIt) : 'â€”' }}</td>
                      <td class="ta-r mono">{{ $abonoIt > 0 ? $fmtMoney($abonoIt) : 'â€”' }}</td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @endif

          <div class="v10-note">
            Tip: Si quieres operar 100% en NEW, migramos legacy a items (Paso siguiente). Ahorita puedes agregar movimientos y el sistema ya recalcula KPIs.
          </div>
        </div>

        {{-- Sidebar --}}
        <div class="v10-sideStack">

          {{-- Enviar por correo --}}
          <div class="v10-panel">
            <div class="v10-panelHead">
              <div>
                <div class="v10-panelTitle">Enviar por correo</div>
                <div class="v10-panelDesc">Acepta mÃºltiples destinatarios. Separador: coma, espacio o salto de lÃ­nea.</div>
              </div>
            </div>
            <div class="v10-panelBody">
              <form method="POST" action="{{ $emailUrl }}">
                @csrf

                <div class="sx-field">
                  <div class="sx-label">Para</div>
                  <textarea class="sx-textarea" name="to" placeholder="correo1@dominio.com, correo2@dominio.com">{{ old('to', $toDefault) }}</textarea>
                  <div class="sx-help">
                    Si lo dejas vacÃ­o, enviarÃ¡ a los destinatarios configurados de la cuenta (meta) y/o al email principal.
                  </div>
                </div>

                <div style="display:flex; gap:10px; flex-wrap:wrap; justify-content:flex-end;">
                  <button class="sx-btn sx-btn--soft" type="button" data-open-modal="#modalAddMove">Agregar movimiento</button>
                  <button class="sx-btn sx-btn--primary" type="submit">Enviar estado de cuenta</button>
                </div>
              </form>

              @if(is_array($recipients) && count($recipients) > 0)
                <div class="v10-note">
                  Sugeridos (config): <span class="mono">{{ implode(', ', $recipients) }}</span>
                </div>
              @endif
            </div>
          </div>

          {{-- Resumen tÃ©cnico --}}
          <div class="v10-panel">
            <div class="v10-panelHead">
              <div>
                <div class="v10-panelTitle">Resumen tÃ©cnico</div>
                <div class="v10-panelDesc">Para soporte y auditorÃ­a.</div>
              </div>
            </div>
            <div class="v10-panelBody">
              <div class="v10-kv">
                <div class="v10-kvRow"><span class="k">Cuenta</span><span class="v mono">#{{ $accountId }}</span></div>
                <div class="v10-kvRow"><span class="k">Periodo</span><span class="v mono">{{ $period }}</span></div>
                <div class="v10-kvRow"><span class="k">Statement ID</span><span class="v mono">{{ $statementId ?: 'â€”' }}</span></div>
                <div class="v10-kvRow"><span class="k">Fuente</span><span class="v mono">{{ strtoupper($source) }}</span></div>
                <div class="v10-kvRow"><span class="k">Movimientos</span><span class="v mono">{{ $rows->count() }}</span></div>
              </div>
            </div>
          </div>

          {{-- Importante --}}
          <div class="v10-panel">
            <div class="v10-panelHead">
              <div>
                <div class="v10-panelTitle">Importante</div>
                <div class="v10-panelDesc">CÃ³mo queda el sistema a partir de hoy.</div>
              </div>
            </div>
            <div class="v10-panelBody">
              <div class="v10-note" style="margin-top:0;">
                - Hoy tu INDEX suma desde <span class="mono">estados_cuenta</span> (legacy).<br>
                - Tu SHOW opera NEW si existen items; si no, muestra legacy como fallback.<br>
                - Al agregar movimientos aquÃ­, empiezas a poblar NEW y ya podrÃ¡s controlar todo desde <span class="mono">billing_statement_items</span>.
              </div>
            </div>
          </div>

        </div>

      </div>
    </div>

  </div>
</div>

{{-- Modal: Agregar movimiento --}}
<div class="sx-modal" id="modalAddMove" aria-hidden="true">
  <div class="sx-modal__bg" data-close-modal></div>
  <div class="sx-modal__card" role="dialog" aria-modal="true" aria-label="Agregar movimiento">
    <div class="sx-modal__head">
      <div class="sx-modal__title">Agregar movimiento</div>
      <button class="sx-btn sx-btn--soft" type="button" data-close-modal>Cerrar</button>
    </div>

    <form method="POST" action="{{ $addItemUrl }}">
      @csrf

      <div class="sx-modal__body">
        <div class="sx-row2">
          <div class="sx-field">
            <div class="sx-label">Tipo</div>
            <select class="sx-select" name="tipo" required>
              <option value="cargo">Cargo</option>
              <option value="abono">Abono</option>
            </select>
            <div class="sx-help">â€œAbonoâ€ crea un amount negativo en items (como ya lo manejas en el controller).</div>
          </div>

          <div class="sx-field">
            <div class="sx-label">Monto</div>
            <input class="sx-input" type="number" step="0.01" min="0" name="monto" required placeholder="0.00">
          </div>
        </div>

        <div class="sx-field">
          <div class="sx-label">Concepto</div>
          <input class="sx-input" type="text" name="concepto" maxlength="255" required placeholder="Licencia, Ajuste, BonificaciÃ³n, etc.">
        </div>

        <div class="sx-field">
          <div class="sx-label">Detalle (opcional)</div>
          <textarea class="sx-textarea" name="detalle" maxlength="2000" placeholder="Notas del movimiento"></textarea>
        </div>

        <div class="sx-row2">
          <div class="sx-field">
            <div class="sx-label">Enviar correo al guardar</div>
            <select class="sx-select" name="send_email">
              <option value="0" selected>No</option>
              <option value="1">SÃ­</option>
            </select>
            <div class="sx-help">Si eliges â€œSÃ­â€, se dispara tu envÃ­o existente con PayLink/QR.</div>
          </div>

          <div class="sx-field">
            <div class="sx-label">Destinatarios (opcional)</div>
            <textarea class="sx-textarea" name="to" placeholder="correo1@dominio.com, correo2@dominio.com">{{ $toDefault }}</textarea>
            <div class="sx-help">Si lo dejas vacÃ­o, usa los destinatarios configurados para la cuenta.</div>
          </div>
        </div>
      </div>

      <div class="sx-modal__foot">
        <button class="sx-btn sx-btn--soft" type="button" data-close-modal>Cancelar</button>
        <button class="sx-btn sx-btn--primary" type="submit">Guardar movimiento</button>
      </div>
    </form>
  </div>
</div>

@endsection

@push('scripts')
<script>
(function(){
  function qs(sel, root){ return (root||document).querySelector(sel); }
  function qsa(sel, root){ return Array.prototype.slice.call((root||document).querySelectorAll(sel)); }

  function openModal(modal){
    if(!modal) return;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden','false');
    document.body.style.overflow = 'hidden';
  }

  function closeModal(modal){
    if(!modal) return;
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden','true');
    document.body.style.overflow = '';
  }

  qsa('[data-open-modal]').forEach(function(btn){
    btn.addEventListener('click', function(){
      var id = btn.getAttribute('data-open-modal');
      openModal(qs(id));
    });
  });

  qsa('[data-close-modal]').forEach(function(btn){
    btn.addEventListener('click', function(){
      var modal = btn.closest('.sx-modal');
      closeModal(modal);
    });
  });

  qsa('.sx-modal').forEach(function(modal){
    modal.addEventListener('click', function(e){
      if(e.target && e.target.hasAttribute('data-close-modal')) return;
      if(e.target && e.target.hasAttribute('data-close-modal-bg')) return;
    });
    var bg = qs('.sx-modal__bg', modal);
    if(bg){
      bg.addEventListener('click', function(){ closeModal(modal); });
    }
  });

  document.addEventListener('keydown', function(e){
    if(e.key === 'Escape'){
      qsa('.sx-modal.is-open').forEach(function(m){ closeModal(m); });
    }
  });
})();
</script>
@endpush
