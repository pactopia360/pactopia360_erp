{{-- C:\wamp64\www\pactopia360_erp\resources\views\admin\billing\statements\show.blade.php --}}
{{-- UI v8.1 · Admin Estado de cuenta — layout nuevo + CSS externo + FIX para admin shell (page-container padding + sticky en scroll container) --}}
@extends('layouts.admin')

@section('title', 'Estado de cuenta · ' . ($period ?? request('period','')))
@section('layout','full')

{{-- CRÍTICO: esta clase permite overrides solo para esta pantalla --}}
@section('pageClass','page-admin-statement-show')

@php
  use Illuminate\Support\Facades\Route;

  // ========= PARAMS =========
  $accountId = $accountId ?? (string)(request()->route('accountId') ?? request('accountId',''));
  $period    = $period ?? (string)(request()->route('period') ?? request('period', now()->format('Y-m')));

  // ========= DATA =========
  $account   = $account ?? null;
  $rows      = $rows ?? ($items ?? collect());
  $summary   = $summary ?? null;

  $statementCfg = $statement_cfg ?? null;
  $recips       = $recipients ?? [];

  // ========= FORMATTERS =========
  $fmtMoney  = fn($n) => '$' . number_format((float)$n, 2);

  // ========= TOTALS (fallback) =========
  $sumCargo = 0.0;
  $sumAbono = 0.0;
  foreach($rows as $r){
    $sumCargo += (float)($r->cargo ?? 0);
    $sumAbono += (float)($r->abono ?? 0);
  }

  if(!is_array($summary)){
    $summary = [
      'cargo'  => $sumCargo,
      'abono'  => $sumAbono,
      'saldo'  => max(0, $sumCargo - $sumAbono),
      'status' => (max(0, $sumCargo - $sumAbono) <= 0.00001 && $sumCargo > 0.00001) ? 'pagado' : 'pendiente',
      'mode'   => null,
    ];
  }

  $cargo = (float)($summary['cargo'] ?? $sumCargo);
  $abono = (float)($summary['abono'] ?? $sumAbono);
  $saldo = (float)($summary['saldo'] ?? max(0, $cargo - $abono));

  // ========= STATUS UI =========
  $statusRaw = strtolower((string)($summary['status'] ?? ($saldo<=0 && $cargo>0 ? 'pagado' : 'pendiente')));
  if(in_array($statusRaw, ['paid','succeeded','success','completed'], true)) $statusRaw = 'pagado';

  $statusLbl = $statusRaw==='pagado' ? 'PAGADO'
            : ($statusRaw==='parcial' ? 'PARCIAL'
            : ($statusRaw==='vencido' ? 'VENCIDO'
            : ($statusRaw==='sin_mov' ? 'SIN MOV' : 'PENDIENTE')));

  $statusCls = 'sx-pill sx-pill--dim';
  if($statusRaw==='pagado') $statusCls='sx-pill sx-pill--ok';
  elseif($statusRaw==='vencido') $statusCls='sx-pill sx-pill--bad';
  elseif(in_array($statusRaw, ['pendiente','parcial'], true)) $statusCls='sx-pill sx-pill--warn';

  // ========= ACCOUNT DISPLAY =========
  $razon = trim((string)(data_get($account,'razon_social') ?? data_get($account,'name') ?? ('Cuenta #' . $accountId)));
  $rfc   = (string)(data_get($account,'rfc') ?? data_get($account,'codigo') ?? '');
  $email = (string)(data_get($account,'email') ?? data_get($account,'correo') ?? '');
  $plan  = (string)(data_get($account,'plan') ?? data_get($account,'plan_name') ?? data_get($account,'license_plan') ?? '');
  $modo  = (string)(data_get($account,'modo_cobro') ?? data_get($account,'billing_mode') ?? '');

  // ========= PERIOD LABEL =========
  $periodLabel = $period;
  try{
    $dt = \Illuminate\Support\Carbon::createFromFormat('Y-m', $period);
    $periodLabel = $dt->translatedFormat('F Y');
  }catch(\Throwable $e){}

  // ========= ROUTES =========
  $hasIndex = Route::has('admin.billing.statements.index');
  $hasPdf   = Route::has('admin.billing.statements.pdf');

  $hasLineStore  = Route::has('admin.billing.statements.lines.store');
  $hasLineUpdate = Route::has('admin.billing.statements.lines.update');
  $hasLineDelete = Route::has('admin.billing.statements.lines.delete');

  $hasStatementSave = Route::has('admin.billing.statements.save');

  $hasSendNow   = Route::has('admin.billing.statements_hub.send_email');
  $hasSchedule  = Route::has('admin.billing.statements_hub.schedule');
  $hasPreview   = Route::has('admin.billing.statements_hub.preview_email');

  $indexUrl = $hasIndex ? route('admin.billing.statements.index', ['period'=>$period]) : url()->previous();
  $pdfUrl   = ($hasPdf && $accountId) ? route('admin.billing.statements.pdf', ['accountId'=>$accountId,'period'=>$period]) : null;

  // ========= STATEMENT SETTINGS =========
  $cfgModeCore = (string)(data_get($statementCfg,'mode') ?? '');
  $statementMode = $cfgModeCore === 'unique' ? 'unica' : 'mensual';
  $notesValue = (string)(data_get($statementCfg,'notes') ?? '');

  $defaultTo = '';
  if(is_array($recips) && count($recips) > 0){
    $defaultTo = implode(', ', $recips);
  }else{
    $defaultTo = trim((string)($email ?: ''));
  }

  $itemsCount = is_countable($rows) ? count($rows) : 0;
@endphp

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/admin/css/admin-billing-statement-show.css') }}?v={{ filemtime(public_path('assets/admin/css/admin-billing-statement-show.css')) }}">
@endpush

@section('content')
<div class="sx-page">
  <div class="sx-wrap">

    {{-- HEADER (sticky dentro del scroll container .admin-content) --}}
    <div class="sx-header">
      <div class="sx-header__left">
        <div class="sx-h1">
          Estado de cuenta <span class="sx-dot">·</span>
          <span class="sx-mono">{{ $period }}</span>
        </div>

        <div class="sx-hsub">
          <span class="sx-strong">{{ $razon }}</span>
          @if($rfc) <span class="sx-dot">·</span> RFC: <span class="sx-mono">{{ $rfc }}</span>@endif
          @if($email) <span class="sx-dot">·</span> Correo: <span class="sx-mono">{{ $email }}</span>@endif
        </div>

        <div class="sx-badges">
          <span class="sx-badge"><span class="k">Periodo</span> <span class="v">{{ $periodLabel }}</span></span>
          <span class="sx-badge"><span class="k">Cuenta</span> <span class="v sx-mono">#{{ $accountId }}</span></span>
          <span class="{{ $statusCls }}"><span class="dot"></span>{{ $statusLbl }}</span>
          @if($plan) <span class="sx-badge"><span class="k">Plan</span> <span class="v">{{ strtoupper($plan) }}</span></span>@endif
          @if($modo) <span class="sx-badge"><span class="k">Cobro</span> <span class="v">{{ $modo }}</span></span>@endif
          <span class="sx-badge"><span class="k">Items</span> <span class="v sx-mono">{{ $itemsCount }}</span></span>
        </div>
      </div>

      <div class="sx-header__right">
        <a class="sx-btn sx-btn--soft" href="{{ $indexUrl }}">Volver</a>
        @if($pdfUrl)
          <a class="sx-btn sx-btn--primary" href="{{ $pdfUrl }}">Descargar PDF</a>
        @else
          <button class="sx-btn sx-btn--primary" type="button" disabled title="Falta ruta PDF">Descargar PDF</button>
        @endif
      </div>
    </div>

    {{-- KPIs --}}
    <div class="sx-kpis">
      <div class="sx-kpi">
        <div class="k">Total cargos</div>
        <div class="v">{{ $fmtMoney($cargo) }}</div>
        <div class="s">Suma de cargos del periodo.</div>
      </div>
      <div class="sx-kpi">
        <div class="k">Total abonos</div>
        <div class="v">{{ $fmtMoney($abono) }}</div>
        <div class="s">Pagos registrados (Stripe / EdoCta).</div>
      </div>
      <div class="sx-kpi">
        <div class="k">Saldo</div>
        <div class="v">{{ $fmtMoney($saldo) }}</div>
        <div class="s">Si saldo = 0, se considera pagado.</div>
      </div>
    </div>

    {{-- GRID --}}
    <div class="sx-grid">

      {{-- MAIN: LÍNEAS --}}
      <div class="sx-card">
        <div class="sx-card__head">
          <div>
            <div class="sx-card__title">Líneas del estado de cuenta</div>
            <div class="sx-card__desc">
              Administra cargos/abonos. Referencias largas no rompen el layout.
            </div>
          </div>
          <div class="sx-card__meta">
            Aplica a: <span class="sx-mono">account_id</span> + <span class="sx-mono">period</span>
          </div>
        </div>

        <div class="sx-card__body">
          @if(!$hasLineStore || !$hasLineUpdate || !$hasLineDelete)
            <div class="sx-alert">
              <b>Nota:</b> CRUD se muestra, pero quedará <b>deshabilitado</b> si faltan rutas:
              <span class="sx-mono">lines.store / lines.update / lines.delete</span>.
            </div>
          @endif

          {{-- Add line --}}
          <div class="sx-panel">
            <div class="sx-panel__head">
              <div class="sx-panel__title">Agregar línea</div>
              <div class="sx-panel__hint">Captura rápida.</div>
            </div>

            <form class="sx-form"
                  method="POST"
                  action="{{ $hasLineStore ? route('admin.billing.statements.lines.store') : '#' }}"
                  @if(!$hasLineStore) onsubmit="return false;" @endif>
              @csrf
              <input type="hidden" name="account_id" value="{{ $accountId }}">
              <input type="hidden" name="period" value="{{ $period }}">

              <div class="sx-form__grid">
                <div class="sx-ctl">
                  <label>Concepto</label>
                  <input class="sx-in" name="concepto" placeholder="Ej. Servicio mensual (PRO)" required>
                </div>

                <div class="sx-ctl">
                  <label>Tipo</label>
                  <select class="sx-in" name="tipo" required>
                    <option value="cargo">Cargo</option>
                    <option value="abono">Abono</option>
                  </select>
                </div>

                <div class="sx-ctl">
                  <label>Monto</label>
                  <input class="sx-in" type="number" step="0.01" min="0" name="monto" placeholder="Ej. 999.00" required>
                </div>
              </div>

              <div class="sx-ctl">
                <label>Detalle / referencia</label>
                <input class="sx-in" name="detalle" placeholder="Opcional: sesión Stripe, nota interna, etc.">
              </div>

              <div class="sx-form__actions">
                <button class="sx-btn sx-btn--primary" type="submit"
                        @if(!$hasLineStore) disabled title="Falta implementar lines.store" @endif>
                  Agregar línea
                </button>
              </div>
            </form>
          </div>

          {{-- Lines --}}
          <div class="sx-lines">
            <div class="sx-lines__head">
              <div class="sx-lines__title">Movimientos</div>
              <div class="sx-lines__hint">Desktop = tabla; móvil = tarjetas.</div>
            </div>

            <div class="sx-tablewrap" role="region" aria-label="Tabla de movimientos" tabindex="0">
              <table class="sx-table">
                <thead>
                  <tr>
                    <th>Concepto</th>
                    <th>Detalle / Ref</th>
                    <th class="ta-r">Cargo</th>
                    <th class="ta-r">Abono</th>
                    <th class="ta-r">Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  @forelse($rows as $r)
                    @php
                      $id = (string)($r->id ?? '');
                      $concepto = (string)($r->concepto ?? $r->concept ?? $r->title ?? '—');
                      $detalle  = (string)($r->detalle ?? $r->detail ?? $r->descripcion ?? '');
                      $cargoIt  = (float)($r->cargo ?? 0);
                      $abonoIt  = (float)($r->abono ?? 0);
                      $ref = (string)($r->stripe_session_id ?? $r->reference ?? $r->ref ?? '');
                      $created = (string)($r->created_at ?? $r->fecha ?? '');
                    @endphp

                    <tr>
                      <td data-label="Concepto">
                        <div class="sx-tdTitle">{{ $concepto }}</div>
                        <div class="sx-tdSub">
                          @if($created) Fecha: <span class="sx-mono">{{ $created }}</span>@endif
                        </div>
                      </td>

                      <td data-label="Detalle / Ref">
                        <div class="sx-tdBody">{{ $detalle ?: '—' }}</div>
                        <div class="sx-tdSub">
                          @if($ref) Ref: <span class="sx-mono sx-break">{{ $ref }}</span>@endif
                        </div>
                      </td>

                      <td class="ta-r sx-mono" data-label="Cargo">{{ $fmtMoney($cargoIt) }}</td>
                      <td class="ta-r sx-mono" data-label="Abono">{{ $fmtMoney($abonoIt) }}</td>

                      <td class="ta-r" data-label="Acciones">
                        <div class="sx-rowActions">
                          <button class="sx-btn sx-btn--soft sx-btn--sm" type="button"
                                  onclick="sxOpenEdit(@js([
                                    'id' => $id,
                                    'concepto' => $concepto,
                                    'detalle' => $detalle,
                                    'cargo' => $cargoIt,
                                    'abono' => $abonoIt,
                                  ]))">
                            Editar
                          </button>

                          <form method="POST" action="{{ $hasLineDelete ? route('admin.billing.statements.lines.delete') : '#' }}"
                                @if(!$hasLineDelete) onsubmit="return false;" @endif>
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="id" value="{{ $id }}">
                            <input type="hidden" name="account_id" value="{{ $accountId }}">
                            <input type="hidden" name="period" value="{{ $period }}">
                            <button class="sx-btn sx-btn--danger sx-btn--sm" type="submit"
                                    @if(!$hasLineDelete) disabled title="Falta implementar lines.delete" @endif
                                    onclick="return confirm('¿Eliminar esta línea?');">
                              Eliminar
                            </button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  @empty
                    <tr>
                      <td colspan="5" class="sx-empty">Sin movimientos para este periodo.</td>
                    </tr>
                  @endforelse
                </tbody>
              </table>
            </div>
          </div>

        </div>
      </div>

      {{-- SIDEBAR --}}
      <div class="sx-side">
        <div class="sx-card sx-card--sticky">
          <div class="sx-card__head">
            <div>
              <div class="sx-card__title">Acciones</div>
              <div class="sx-card__desc">Configura modo, guarda y envía por correo.</div>
            </div>
          </div>

          <div class="sx-card__body sx-stack">

            {{-- Config --}}
            <div class="sx-box">
              <div class="sx-box__head">
                <div class="sx-box__title">Configuración</div>
                <div class="sx-box__desc">Define comportamiento del estado.</div>
              </div>

              <form class="sx-box__body"
                    method="POST"
                    action="{{ $hasStatementSave ? route('admin.billing.statements.save') : '#' }}"
                    @if(!$hasStatementSave) onsubmit="return false;" @endif
                    onsubmit="return sxBindRecipients(this);">
                @csrf
                <input type="hidden" name="account_id" value="{{ $accountId }}">
                <input type="hidden" name="period" value="{{ $period }}">
                <input type="hidden" name="recipients" value="">

                <div class="sx-ctl">
                  <label>Tipo de estado</label>
                  <select class="sx-in" name="mode">
                    <option value="mensual" {{ $statementMode==='mensual'?'selected':'' }}>Mensual (se agrega por periodo)</option>
                    <option value="unica" {{ $statementMode==='unica'?'selected':'' }}>Única (no recurrente)</option>
                  </select>
                </div>

                <div class="sx-ctl">
                  <label>Notas internas</label>
                  <input class="sx-in" name="notes" value="{{ $notesValue }}" placeholder="Reglas, ajustes, observaciones">
                </div>

                <button class="sx-btn sx-btn--primary w-100" type="submit"
                        @if(!$hasStatementSave) disabled title="Falta implementar statements.save" @endif>
                  Guardar cambios
                </button>
              </form>
            </div>

            {{-- Recipients --}}
            <div class="sx-box">
              <div class="sx-box__head">
                <div class="sx-box__title">Destinatarios</div>
                <div class="sx-box__desc">Separar por comas.</div>
              </div>
              <div class="sx-box__body">
                <div class="sx-ctl">
                  <label>To</label>
                  <input class="sx-in" id="sxTo" value="{{ $defaultTo }}" placeholder="a@x.com, b@y.com">
                </div>
                <div class="sx-tip">Tip: agrega correos operativos para copias internas.</div>
              </div>
            </div>

            {{-- Send --}}
            <div class="sx-box">
              <div class="sx-box__head">
                <div class="sx-box__title">Enviar por correo</div>
                <div class="sx-box__desc">Inmediato o programado.</div>
              </div>

              <div class="sx-box__body sx-stack">
                <form method="POST"
                      action="{{ $hasSendNow ? route('admin.billing.statements_hub.send_email') : '#' }}"
                      @if(!$hasSendNow) onsubmit="return false;" @endif
                      onsubmit="return sxBindTo(this);">
                  @csrf
                  <input type="hidden" name="account_id" value="{{ $accountId }}">
                  <input type="hidden" name="period" value="{{ $period }}">
                  <input type="hidden" name="to" value="">
                  <button class="sx-btn sx-btn--primary w-100" type="submit"
                          @if(!$hasSendNow) disabled title="Falta implementar statements_hub.send_email" @endif>
                    Enviar ahora
                  </button>
                </form>

                <form method="POST"
                      action="{{ $hasSchedule ? route('admin.billing.statements_hub.schedule') : '#' }}"
                      @if(!$hasSchedule) onsubmit="return false;" @endif
                      onsubmit="return sxBindTo(this);">
                  @csrf
                  <input type="hidden" name="account_id" value="{{ $accountId }}">
                  <input type="hidden" name="period" value="{{ $period }}">
                  <input type="hidden" name="to" value="">

                  <div class="sx-ctl">
                    <label>Programar (queued_at)</label>
                    <input class="sx-in" name="queued_at" value="{{ now()->addMinutes(10)->format('Y-m-d H:i:s') }}" placeholder="YYYY-MM-DD HH:MM:SS">
                  </div>

                  <button class="sx-btn sx-btn--soft w-100" type="submit"
                          @if(!$hasSchedule) disabled title="Falta implementar statements_hub.schedule" @endif>
                    Programar envío
                  </button>

                  <div class="sx-tip">
                    Cron: <span class="sx-mono">php artisan p360:billing:process-scheduled-emails</span>
                  </div>
                </form>

                <form method="GET"
                      action="{{ $hasPreview ? route('admin.billing.statements_hub.preview_email') : '#' }}"
                      target="_blank"
                      @if(!$hasPreview) onsubmit="return false;" @endif
                      onsubmit="return sxBindTo(this);">
                  <input type="hidden" name="account_id" value="{{ $accountId }}">
                  <input type="hidden" name="period" value="{{ $period }}">
                  <input type="hidden" name="to" value="">
                  <button class="sx-btn sx-btn--soft w-100" type="submit"
                          @if(!$hasPreview) disabled title="Falta implementar statements_hub.preview_email" @endif>
                    Vista previa del correo
                  </button>
                </form>
              </div>
            </div>

            {{-- Account --}}
            <div class="sx-box">
              <div class="sx-box__head">
                <div class="sx-box__title">Datos de cuenta</div>
                <div class="sx-box__desc">Referencia rápida.</div>
              </div>

              <div class="sx-box__body">
                <div class="sx-kv">
                  <div class="sx-kv__row"><span class="k">Cuenta</span><span class="v sx-mono">#{{ $accountId }}</span></div>
                  @if($rfc)   <div class="sx-kv__row"><span class="k">RFC</span><span class="v sx-mono">{{ $rfc }}</span></div>@endif
                  @if($email) <div class="sx-kv__row"><span class="k">Correo</span><span class="v sx-mono sx-break">{{ $email }}</span></div>@endif
                  @if($plan)  <div class="sx-kv__row"><span class="k">Plan</span><span class="v">{{ strtoupper($plan) }}</span></div>@endif
                  @if($modo)  <div class="sx-kv__row"><span class="k">Cobro</span><span class="v">{{ $modo }}</span></div>@endif
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>

    </div>
  </div>
</div>

{{-- MODAL EDIT --}}
<div id="sxModal" class="sx-modal" aria-hidden="true">
  <div class="sx-modal__card" role="dialog" aria-modal="true" aria-label="Editar línea">
    <div class="sx-modal__head">
      <div class="t">Editar línea</div>
      <button class="sx-btn sx-btn--soft sx-btn--sm" type="button" onclick="sxCloseEdit()">Cerrar</button>
    </div>

    <div class="sx-modal__body">
      <form id="sxEditForm" class="sx-form"
            method="POST"
            action="{{ $hasLineUpdate ? route('admin.billing.statements.lines.update') : '#' }}"
            @if(!$hasLineUpdate) onsubmit="return false;" @endif>
        @csrf
        @method('PUT')

        <input type="hidden" name="id" id="sxEditId">
        <input type="hidden" name="account_id" value="{{ $accountId }}">
        <input type="hidden" name="period" value="{{ $period }}">

        <div class="sx-ctl">
          <label>Concepto</label>
          <input class="sx-in" name="concepto" id="sxEditConcepto" required>
        </div>

        <div class="sx-ctl">
          <label>Detalle / referencia</label>
          <input class="sx-in" name="detalle" id="sxEditDetalle">
        </div>

        <div class="sx-form__grid sx-form__grid--edit">
          <div class="sx-ctl">
            <label>Tipo</label>
            <select class="sx-in" name="tipo" id="sxEditTipo" required>
              <option value="cargo">Cargo</option>
              <option value="abono">Abono</option>
            </select>
          </div>

          <div class="sx-ctl">
            <label>Monto</label>
            <input class="sx-in" type="number" step="0.01" min="0" name="monto" id="sxEditMonto" required>
          </div>

          <div class="sx-ctl">
            <label>&nbsp;</label>
            <button class="sx-btn sx-btn--primary w-100" type="submit"
                    @if(!$hasLineUpdate) disabled title="Falta implementar lines.update" @endif>
              Guardar
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
  function sxBindTo(form){
    const to = (document.getElementById('sxTo')?.value || '').trim();
    const hidden = form.querySelector('input[name="to"]');
    if(hidden) hidden.value = to;
    return true;
  }

  function sxBindRecipients(form){
    const to = (document.getElementById('sxTo')?.value || '').trim();
    const hidden = form.querySelector('input[name="recipients"]');
    if(hidden) hidden.value = to;
    return true;
  }

  function sxOpenEdit(data){
    const modal = document.getElementById('sxModal');
    if(!modal) return;

    const id = (data && data.id) ? String(data.id) : '';
    const concepto = (data && data.concepto) ? String(data.concepto) : '';
    const detalle  = (data && data.detalle) ? String(data.detalle) : '';

    const cargo = Number(data && data.cargo ? data.cargo : 0);
    const abono = Number(data && data.abono ? data.abono : 0);

    let tipo = (abono > 0) ? 'abono' : 'cargo';
    let monto = (abono > 0) ? abono : cargo;

    document.getElementById('sxEditId').value = id;
    document.getElementById('sxEditConcepto').value = concepto;
    document.getElementById('sxEditDetalle').value  = detalle;
    document.getElementById('sxEditTipo').value     = tipo;
    document.getElementById('sxEditMonto').value    = (isFinite(monto) ? String(monto) : '0');

    modal.style.display = 'block';
    modal.setAttribute('aria-hidden','false');
  }

  function sxCloseEdit(){
    const modal = document.getElementById('sxModal');
    if(!modal) return;
    modal.style.display = 'none';
    modal.setAttribute('aria-hidden','true');
  }

  document.addEventListener('keydown', (e) => {
    if(e.key === 'Escape') sxCloseEdit();
  });

  document.addEventListener('click', (e) => {
    const modal = document.getElementById('sxModal');
    if(!modal || modal.style.display !== 'block') return;
    if(e.target === modal) sxCloseEdit();
  });
</script>
@endpush
