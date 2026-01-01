{{-- resources/views/admin/billing/statements/index.blade.php (vD5.3 · perPage + status ALL + paid breakdown + overdue/parcial) --}}
@extends('layouts.admin')

@section('title', 'Facturación · Estados de cuenta')
@section('layout', 'full')
@section('pageClass', 'p360-billing-statements')

@php
  use Illuminate\Support\Carbon;
  use Illuminate\Support\Str;
  use Illuminate\Support\Facades\Route;

  $q         = $q ?? request('q','');
  $period    = $period ?? request('period', now()->format('Y-m'));
  $accountId = $accountId ?? request('accountId','');
  $status    = $status ?? request('status','all');
  $perPage   = (int)($perPage ?? request('perPage', 25));
  $error     = $error ?? null;

  $allowedPerPage = [25,50,100,250,500,1000];
  if(!in_array($perPage, $allowedPerPage, true)) $perPage = 25;

  $allowedStatus = ['all','pendiente','pagado','parcial','vencido','sin_mov'];
  if(!in_array($status, $allowedStatus, true)) $status = 'all';

  // Paginación o colección
  $isPaginator = $rows instanceof \Illuminate\Pagination\AbstractPaginator;
  $collection  = $isPaginator ? $rows->getCollection() : (collect($rows) ?? collect());

  // KPIs: si el controller los manda, se respetan; si no, se calculan aquí.
  if (!isset($kpis) || !is_array($kpis)) {
    $kCargo = 0.0; $kAbono = 0.0; $kSaldo = 0.0; $kAcc = 0; $kEdo = 0.0; $kPay = 0.0;
    foreach ($collection as $r) {
      $cargo = (float)($r->cargo ?? 0);
      $abono = (float)($r->abono ?? 0);
      $expected = (float)($r->expected_total ?? 0);
      $totalShown = $cargo > 0 ? $cargo : $expected;

      $kCargo += $totalShown;
      $kAbono += $abono;
      $kSaldo += max(0, $totalShown - $abono);
      $kAcc++;

      $kEdo += (float)($r->abono_edo ?? 0);
      $kPay += (float)($r->abono_pay ?? 0);
    }
    $kpis = [
      'cargo'    => round($kCargo,2),
      'abono'    => round($kAbono,2),
      'saldo'    => round($kSaldo,2),
      'accounts' => $kAcc,
      'paid_edo' => round($kEdo,2),
      'paid_pay' => round($kPay,2),
    ];
  }

  // Rutas (robustas)
  $routeIndex = Route::has('admin.billing.statements.index')
    ? route('admin.billing.statements.index')
    : url('/admin/billing/statements');

  $baseShow = function(string $aid, string $p) {
    return \Illuminate\Support\Facades\Route::has('admin.billing.statements.show')
      ? route('admin.billing.statements.show', ['accountId'=>$aid,'period'=>$p])
      : url('/admin/billing/statements/'.$aid.'/'.$p);
  };

  $basePdf = function(string $aid, string $p) {
    return \Illuminate\Support\Facades\Route::has('admin.billing.statements.pdf')
      ? route('admin.billing.statements.pdf', ['accountId'=>$aid,'period'=>$p])
      : url('/admin/billing/statements/'.$aid.'/'.$p.'/pdf');
  };

  $baseEmail = function(string $aid, string $p) {
    return \Illuminate\Support\Facades\Route::has('admin.billing.statements.email')
      ? route('admin.billing.statements.email', ['accountId'=>$aid,'period'=>$p])
      : url('/admin/billing/statements/'.$aid.'/'.$p.'/email');
  };
@endphp

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/admin/css/billing-statements-hub.css') }}?v=1.2">

  {{-- ✅ FULL WIDTH REAL: rompe el max-width del layout SOLO en esta pantalla --}}
  <style>
    /* A) quita padding del shell si existe */
    .p360-billing-statements .p360-page,
    .p360-billing-statements .page,
    .p360-billing-statements .content,
    .p360-billing-statements .content-wrapper,
    .p360-billing-statements main{
      padding: 0 !important;
      max-width: none !important;
      width: 100% !important;
    }

    /* B) rompe contenedores típicos (Bootstrap / custom) */
    .p360-billing-statements .container,
    .p360-billing-statements .container-fluid,
    .p360-billing-statements [class*="container"]{
      max-width: none !important;
      width: 100% !important;
    }

    /* C) si tu layout usa algo como "inner" / "wrap" */
    .p360-billing-statements .wrap,
    .p360-billing-statements .inner,
    .p360-billing-statements .page-inner,
    .p360-billing-statements .app-inner{
      max-width: none !important;
      width: 100% !important;
    }

    /* D) mini select alineado */
    .p360-hub-filterrow .sel{
      height: 40px;
      border-radius: 12px;
      border: 1px solid rgba(148,163,184,.35);
      padding: 0 12px;
      background: #fff;
      outline: none;
      min-width: 140px;
    }

    /* E) mejor readability en breakdown de pagado */
    .p360-paid-break{
      margin-top: 6px;
      font-size: 12px;
      color: rgba(100,116,139,1);
      line-height: 1.2;
    }
  </style>
@endpush

@section('content')
<div class="p360-hub">

  {{-- HEADER --}}
  <div class="p360-hub-head">
    <div>
      <div class="ttl">Facturación · Estados de cuenta</div>
      <div class="sub">
        Periodo: <span class="mono">{{ $period }}</span> · Total/Pagado/Saldo por cuenta.
        <span class="hint">Total = cargo del periodo. Si no hay movimientos, se muestra “total esperado” por licencia para que coincida con lo asignado en Cuentas.</span>
      </div>
    </div>

    <div class="rh">
      <form method="GET" action="{{ $routeIndex }}" class="p360-hub-filterrow">
        <div class="ctl">
          <label>Buscar</label>
          <input class="in" type="text" name="q" value="{{ $q }}" placeholder="ID, RFC, email, razón social">
        </div>

        <div class="ctl">
          <label>Periodo</label>
          <input class="in" type="text" name="period" value="{{ $period }}" placeholder="YYYY-MM">
        </div>

        <div class="ctl">
          <label>Cuenta (ID)</label>
          <input class="in" type="text" name="accountId" value="{{ $accountId }}" placeholder="ID exacto">
        </div>

        <div class="ctl">
          <label>Estatus</label>
          <select class="sel" name="status">
            <option value="all"      @selected($status==='all')>Todos</option>
            <option value="pendiente"@selected($status==='pendiente')>Pendiente</option>
            <option value="parcial"  @selected($status==='parcial')>Parcial</option>
            <option value="vencido"  @selected($status==='vencido')>Vencido</option>
            <option value="pagado"   @selected($status==='pagado')>Pagado</option>
            <option value="sin_mov"  @selected($status==='sin_mov')>Sin mov</option>
          </select>
        </div>

        <div class="ctl">
          <label>Por página</label>
          <select class="sel" name="perPage">
            @foreach([25,50,100,250,500,1000] as $pp)
              <option value="{{ $pp }}" @selected((int)$perPage===(int)$pp)>{{ $pp }}</option>
            @endforeach
          </select>
        </div>

        <div class="ctl">
          <label>&nbsp;</label>
          <button class="btn btn-dark" type="submit">Filtrar</button>
        </div>
      </form>
    </div>
  </div>

  {{-- KPIs --}}
  <div class="p360-kpis">
    <div class="kpi">
      <div class="k">Total</div>
      <div class="v">${{ number_format((float)($kpis['cargo'] ?? 0), 2) }}</div>
    </div>
    <div class="kpi">
      <div class="k">Pagado</div>
      <div class="v">${{ number_format((float)($kpis['abono'] ?? 0), 2) }}</div>
      <div class="p360-paid-break">
        EdoCta: ${{ number_format((float)($kpis['paid_edo'] ?? 0), 2) }} · Payments: ${{ number_format((float)($kpis['paid_pay'] ?? 0), 2) }}
      </div>
    </div>
    <div class="kpi">
      <div class="k">Saldo</div>
      <div class="v">${{ number_format((float)($kpis['saldo'] ?? 0), 2) }}</div>
    </div>
    <div class="kpi">
      <div class="k">Cuentas</div>
      <div class="v">{{ (int)($kpis['accounts'] ?? 0) }}</div>
    </div>

    {{-- Acciones masivas --}}
    <div class="kpi kpi-actions">
      <div class="k">Seleccionadas</div>
      <div class="v" id="bsSelCount">0</div>
      <div class="kpi-actions-row">
        <button class="btn btn-light" type="button" id="bsAll">Todo</button>
        <button class="btn btn-light" type="button" id="bsNone">Nada</button>
        <button class="btn btn-dark" type="button" id="bsBulkEmail">Enviar correo</button>
      </div>
    </div>
  </div>

  @if($error)
    <div class="p360-msg err">{{ $error }}</div>
  @endif
  @if(session('ok'))
    <div class="p360-msg ok">{{ session('ok') }}</div>
  @endif

  {{-- TABLE GRID (HUB) --}}
  <div class="p360-card">
    <div class="p360-table">

      <div class="thead">
        <div class="th th-chk">
          <label class="chk">
            <input type="checkbox" id="bsMaster">
            <span></span>
          </label>
        </div>
        <div class="th">Cuenta</div>
        <div class="th">Cliente</div>
        <div class="th">Email</div>
        <div class="th" style="text-align:right;">Total</div>
        <div class="th" style="text-align:right;">Pagado</div>
        <div class="th" style="text-align:right;">Saldo</div>
        <div class="th" style="text-align:right;">Estatus</div>
        <div class="th" style="text-align:right;">Acciones</div>
      </div>

      @forelse($collection as $r)
        @php
          $aid = (string)($r->id ?? '');
          $mail = (string)($r->email ?? '—');
          $rfc  = (string)($r->rfc ?? '—');

          $name = trim((string)(($r->razon_social ?? '') ?: ($r->name ?? '') ?: ($mail ?: '—')));

          $cargoReal = (float)($r->cargo ?? 0);
          $expectedTotal = (float)($r->expected_total ?? 0);
          $totalShown    = $cargoReal > 0 ? $cargoReal : $expectedTotal;

          $paidTotal = (float)($r->abono ?? 0);
          $paidEdo   = (float)($r->abono_edo ?? 0);
          $paidPay   = (float)($r->abono_pay ?? 0);

          $saldo = max(0, $totalShown - $paidTotal);

          $tarifaLabel = (string)($r->tarifa_label ?? '—');
          $tarifaPill  = (string)($r->tarifa_pill ?? 'pill-dim'); // ok|warn|bad|dim|info

          $planActual = strtoupper((string)($r->plan_actual ?? $r->plan ?? ''));
          $isPro = str_contains($planActual, 'PRO');

          $statusPago = (string)($r->status_pago ?? '');
          if ($statusPago === '') {
            if ($totalShown <= 0.00001) $statusPago = 'sin_mov';
            else if ($saldo <= 0.00001) $statusPago = 'pagado';
            else if ($paidTotal > 0.00001 && $paidTotal < ($totalShown - 0.00001)) $statusPago = 'parcial';
            else $statusPago = 'pendiente';
          }

          if ($statusPago === 'pagado') { $pagoPill='pill-ok';   $pagoLabel='PAGADO'; }
          elseif ($statusPago === 'sin_mov') { $pagoPill='pill-dim';  $pagoLabel='SIN MOV'; }
          elseif ($statusPago === 'parcial') { $pagoPill='pill-info'; $pagoLabel='PARCIAL'; }
          elseif ($statusPago === 'vencido') { $pagoPill='pill-bad';  $pagoLabel='VENCIDO'; }
          else { $pagoPill='pill-warn'; $pagoLabel='PENDIENTE'; }

          $saldoPill = $saldo > 0 ? ($statusPago==='vencido' ? 'pill-bad' : 'pill-warn') : 'pill-ok';

          $urlShowModal = $baseShow($aid, $period) . '?modal=1';
          $urlPdf = $basePdf($aid, $period);
          $urlEmail = $baseEmail($aid, $period);

          $lastPaidAt = $r->pay_last_paid_at ?? null;
          $dueDate    = $r->pay_due_date ?? null;
          $prov       = trim((string)($r->pay_provider ?? ''));
          $meth       = trim((string)($r->pay_method ?? ''));
        @endphp

        <div class="tr" data-aid="{{ $aid }}" data-email="{{ e($mail) }}">
          <div class="td td-chk">
            <label class="chk">
              <input type="checkbox" class="bsRow" value="{{ $aid }}">
              <span></span>
            </label>
          </div>

          <div class="td td-id">
            <div class="id">#{{ $aid }}</div>
            <div class="mut">RFC: <span class="mono">{{ $rfc }}</span></div>
          </div>

          <div class="td">
            <div class="title">{{ $name }}</div>
            <div class="chips">
              <span class="chip {{ $isPro ? 'chip-soft' : '' }}">{{ $isPro ? 'PRO' : 'FREE' }}</span>
              <span class="pill {{ $tarifaPill }}">{{ $tarifaLabel }}</span>
            </div>
          </div>

          <div class="td">
            <div class="mail">{{ $mail }}</div>
            <div class="mut">Periodo: <span class="mono">{{ $period }}</span></div>
            @if($prov !== '' || $meth !== '')
              <div class="mut">Pago: {{ $prov !== '' ? strtoupper($prov) : '—' }}{{ $meth !== '' ? ' · '.strtoupper($meth) : '' }}</div>
            @endif
          </div>

          <div class="td" style="text-align:right;">
            <div class="amt">${{ number_format($totalShown, 2) }}</div>
            @if($cargoReal <= 0.00001 && $expectedTotal > 0.00001)
              <div class="mut">(esperado por licencia)</div>
            @endif
          </div>

          <div class="td" style="text-align:right;">
            <div class="amt">${{ number_format($paidTotal, 2) }}</div>
            <div class="mut" style="margin-top:6px;">
              EdoCta: ${{ number_format($paidEdo, 2) }} · Payments: ${{ number_format($paidPay, 2) }}
            </div>
            @if($lastPaidAt)
              <div class="mut">Últ: {{ \Illuminate\Support\Carbon::parse($lastPaidAt)->format('Y-m-d') }}</div>
            @endif
          </div>

          <div class="td" style="text-align:right;">
            <span class="pill {{ $saldoPill }}">${{ number_format($saldo, 2) }}</span>
            @if($dueDate && $saldo > 0.00001)
              <div class="mut" style="margin-top:6px;">Vence: {{ \Illuminate\Support\Carbon::parse($dueDate)->format('Y-m-d') }}</div>
            @endif
          </div>

          <div class="td" style="text-align:right;">
            <span class="pill {{ $pagoPill }}">{{ $pagoLabel }}</span>
          </div>

          <div class="td" style="text-align:right;">
            <div class="actions">
              <button
                type="button"
                class="btn btn-dark"
                data-drawer-open
                data-url="{{ $urlShowModal }}"
                data-title="Estado de cuenta · {{ $period }}"
                data-sub="{{ e($name) }} · RFC {{ e($rfc) }}"
                data-width="860px"
              >Ver detalle</button>

              <a class="btn btn-light" href="{{ $urlPdf }}" target="_blank" rel="noopener">PDF</a>

              <form method="POST" action="{{ $urlEmail }}" class="inline">
                @csrf
                <input type="hidden" name="to" value="">
                <button class="btn btn-light" type="submit">Enviar</button>
              </form>
            </div>
          </div>
        </div>

      @empty
        <div class="empty">Sin resultados.</div>
      @endforelse
    </div>

    <div class="p360-pager">
      @if($isPaginator)
        {{ $rows->links() }}
      @else
        <span class="mut">—</span>
      @endif
    </div>
  </div>

</div>

{{-- MODAL / DRAWER --}}
<div class="p360-modal" id="bsModal" aria-hidden="true">
  <div class="p360-modal-card" id="bsModalCard" role="dialog" aria-modal="true">
    <div class="mh">
      <div>
        <div class="t" id="bsModalTitle">Detalle</div>
        <div class="hint" id="bsModalSub"></div>
      </div>
      <button type="button" class="x" data-drawer-close>✕</button>
    </div>

    <div class="mb" style="padding:0;">
      <iframe id="bsModalFrame" class="bs-iframe" src="about:blank" title="Detalle" style="width:100%;height:70vh;border:0;background:#fff;"></iframe>
    </div>

    <div class="mf">
      <button type="button" class="btn btn-light" data-drawer-close>Cerrar</button>
    </div>
  </div>
</div>

{{-- Bulk email POST “silencioso” --}}
<form id="bsBulkForm" method="POST" style="display:none;">
  @csrf
  <input type="hidden" name="to" value="">
</form>

@push('scripts')
<script>
(function(){
  const $ = (s, ctx=document) => ctx.querySelector(s);
  const $$ = (s, ctx=document) => Array.from(ctx.querySelectorAll(s));

  // Modal
  const modal = $('#bsModal');
  const modalCard = $('#bsModalCard');
  const frame = $('#bsModalFrame');
  const ttl = $('#bsModalTitle');
  const sub = $('#bsModalSub');

  function openModal(url, title, subtitle, width){
    ttl.textContent = title || 'Detalle';
    sub.textContent = subtitle || '';
    modal.classList.add('is-on');
    modal.setAttribute('aria-hidden','false');
    document.body.classList.add('p360-modal-open');

    if(width) modalCard.style.width = width;
    frame.src = url || 'about:blank';
  }

  function closeModal(){
    modal.classList.remove('is-on');
    modal.setAttribute('aria-hidden','true');
    document.body.classList.remove('p360-modal-open');
    frame.src = 'about:blank';
  }

  // Open buttons
  $$('[data-drawer-open]').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      openModal(
        btn.getAttribute('data-url'),
        btn.getAttribute('data-title'),
        btn.getAttribute('data-sub'),
        btn.getAttribute('data-width') || '860px'
      );
    });
  });

  // Close buttons + overlay click
  $$('[data-drawer-close]').forEach(btn=>btn.addEventListener('click', closeModal));
  modal.addEventListener('click', (e)=>{ if(e.target === modal) closeModal(); });
  document.addEventListener('keydown', (e)=>{ if(e.key === 'Escape' && modal.classList.contains('is-on')) closeModal(); });

  // Bulk selection
  const master = $('#bsMaster');
  const rows = $$('.bsRow');
  const selCount = $('#bsSelCount');

  function countSelected(){
    const n = rows.filter(x=>x.checked).length;
    selCount.textContent = String(n);
    if(master){
      const all = rows.length > 0 && rows.every(x=>x.checked);
      const any = rows.some(x=>x.checked);
      master.indeterminate = any && !all;
      master.checked = all;
    }
  }

  rows.forEach(r=>r.addEventListener('change', countSelected));

  master?.addEventListener('change', ()=>{
    rows.forEach(r=>r.checked = master.checked);
    countSelected();
  });

  $('#bsAll')?.addEventListener('click', ()=>{ rows.forEach(r=>r.checked=true); countSelected(); });
  $('#bsNone')?.addEventListener('click', ()=>{ rows.forEach(r=>r.checked=false); countSelected(); });

  // Bulk email: POST a /email por cada seleccionado
  const bulkBtn = $('#bsBulkEmail');

  bulkBtn?.addEventListener('click', async ()=>{
    const ids = rows.filter(x=>x.checked).map(x=>x.value);
    if(!ids.length) return;

    const period = @json($period);
    const baseUrl = @json(url('/admin/billing/statements'));

    let ok = 0;
    for(const id of ids){
      const url = baseUrl + '/' + encodeURIComponent(id) + '/' + encodeURIComponent(period) + '/email';
      try{
        await fetch(url, {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': @json(csrf_token()),
            'Accept': 'application/json'
          },
          body: new URLSearchParams({ to: '' })
        });
        ok++;
      }catch(e){}
      await new Promise(r=>setTimeout(r, 220));
    }

    alert('Envío masivo disparado: ' + ok + ' de ' + ids.length + ' cuentas.');
  });

  countSelected();
})();
</script>
@endpush

@endsection
