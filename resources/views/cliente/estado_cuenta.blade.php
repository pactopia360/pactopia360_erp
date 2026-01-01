<?php
// C:\wamp64\www\pactopia360_erp\resources\views\cliente\estado_cuenta.blade.php
?>
{{-- resources/views/cliente/estado_cuenta.blade.php (Estados de cuenta v7 · UI consistente + bundle modal centrado + “Cubierto” + monthly fallback) --}}
@extends('layouts.cliente')
@section('title','Estados de cuenta · Pactopia360')

@push('styles')
  @php
    $css1 = public_path('assets/client/css/estado-cuenta.css');
    $css2 = public_path('assets/cliente/css/estado-cuenta.css');
    $cssAbs = is_file($css1) ? $css1 : (is_file($css2) ? $css2 : null);

    $cssUrl = null;
    if($cssAbs){
      $cssUrl = asset(str_contains($cssAbs,'assets/client')
        ? 'assets/client/css/estado-cuenta.css'
        : 'assets/cliente/css/estado-cuenta.css'
      ).'?v='.filemtime($cssAbs);
    }
  @endphp

  @if($cssUrl)<link rel="stylesheet" href="{{ $cssUrl }}">@endif

  @if(!$cssUrl)
    <style>
      body{font-family:system-ui}
      .p360-stm{padding:18px}
      .p360-card{border:1px solid #e5e7eb;border-radius:16px;padding:14px;margin:10px 0}
    </style>
  @endif
@endpush

@section('content')
@php
  use Illuminate\Support\Carbon;
  use Illuminate\Support\Str;
  use Illuminate\Support\Facades\Route;

  $acc = $account ?? [];
  $s   = $summary ?? [];

  $razon     = (string)($acc['razon_social'] ?? ($s['razon'] ?? 'Tu cuenta'));
  $rfcAcc    = (string)($acc['rfc'] ?? '');
  $accountId = (string)($acc['account_id'] ?? ($acc['id'] ?? ''));

  $planKey   = strtoupper((string)($s['plan'] ?? 'FREE'));
  $cycleKey  = strtoupper((string)($s['cycle'] ?? 'MENSUAL'));
  $balance   = (float)($s['balance'] ?? 0);
  $isBlocked = (bool)($s['blocked'] ?? false);

  // MONTO MENSUAL FIJO (fallback para UI cuando total venga 0 pero realmente sí hay mensualidad)
  $monthlyPrice = (float)($s['monthly_price'] ?? $s['monto_mensual'] ?? $s['price_monthly'] ?? 0);

  $rows = $statements ?? collect();
  if(is_array($rows)) $rows = collect($rows);

  $fmtMoney = fn($n)=> number_format((float)($n ?? 0), 2);

  $fmtRange = function($a,$b){
    try{
      $sa = $a ? Carbon::parse($a)->format('d/m/Y') : '—';
      $sb = $b ? Carbon::parse($b)->format('d/m/Y') : '—';
      return "{$sa} - {$sb}";
    }catch(\Throwable $e){
      return '—';
    }
  };

  $monthFromYm = function($ym){
    if(!$ym || !preg_match('/^\d{4}\-\d{2}$/', $ym)) return null;
    try{
      $m = Carbon::createFromFormat('Y-m', $ym)->locale('es')->translatedFormat('F Y');
      return Str::title($m);
    }catch(\Throwable $e){
      return null;
    }
  };

  $statusPill = function($s){
    $s = strtolower((string)$s);

    if(Str::contains($s, ['paid','pagado'])) return ['is-ok','Pagado'];
    if(Str::contains($s, ['overdue','vencido'])) return ['is-bad','Vencido'];
    if(Str::contains($s, ['pending','pendiente','unpaid','por pagar'])) return ['is-warn','Pendiente'];
    if(Str::contains($s, ['empty','sin_generar','no_generado','sin generar'])) return ['is-empty','Sin generar'];

    return ['is-warn', $s ?: 'Pendiente'];
  };

  $rtMiCuenta = null;
  if(Route::has('cliente.mi_cuenta')) $rtMiCuenta = route('cliente.mi_cuenta');
  elseif(Route::has('cliente.micuenta')) $rtMiCuenta = route('cliente.micuenta');
  elseif(Route::has('cliente.cuenta')) $rtMiCuenta = route('cliente.cuenta');
  elseif(Route::has('cliente.account')) $rtMiCuenta = route('cliente.account');

  $rtPayPending = Route::has('cliente.billing.payPending') ? route('cliente.billing.payPending') : null;
  $rtPayBundle  = Route::has('cliente.billing.pay_bundle') ? route('cliente.billing.pay_bundle') : null;

  // UI periods (desde periodo vigente del contrato hacia adelante)
  $uiPeriods = [];
  try{
    $startYm = (string)($s['current_period_ym'] ?? '');
    if(!$startYm && !empty($s['period_start'])){
      try{ $startYm = Carbon::parse($s['period_start'])->format('Y-m'); }catch(\Throwable $e){}
    }
    if(!$startYm || !preg_match('/^\d{4}\-\d{2}$/', $startYm)){
      $startYm = now()->format('Y-m');
    }

    $base = Carbon::createFromFormat('Y-m', $startYm)->startOfMonth();
    $monthsAhead = 12;
    for($i=0; $i <= $monthsAhead; $i++){
      $uiPeriods[] = $base->copy()->addMonths($i)->format('Y-m');
    }
  }catch(\Throwable $e){
    $uiPeriods = [now()->format('Y-m')];
  }

  // Mapa status/total/pdf por periodo (para bundle y para inferencias)
  $statMap = [];
  foreach ($rows as $r) {
    $p = (string)data_get($r,'period_ym','');
    if(!$p) continue;
    $statMap[$p] = [
      'total'   => (float)data_get($r,'total',0),
      'status'  => (string)data_get($r,'status','pending'),
      'pdf_url' => (string)data_get($r,'pdf_url',''),
    ];
  }

  // Covered: solo si paid, o si total=0 (sin cargo) PERO OJO: si hay monthlyPrice, entonces 0 no significa cubierto
  $isCoveredPeriod = function($status, $total) use ($monthlyPrice){
    $st = strtolower((string)$status);
    if(Str::contains($st, ['paid','pagado'])) return true;

    // Si hay mensualidad fija (>0), NO consideres "cubierto" solo por traer 0.00
    if($monthlyPrice > 0.00001) return false;

    if(((float)$total) <= 0.00001) return true;
    return false;
  };

  $printedAt = now();
@endphp

<div class="p360-stm">
  <div class="p360-stm__wrap">

    {{-- HERO / HEADER --}}
    <div class="p360-stm__hero">
      <div class="p360-stm__heroTop">
        <div class="p360-stm__heroLeft">
          <div class="p360-kicker">Pagos</div>
          <h1 class="p360-stm__title">Estados de cuenta</h1>
          <div class="p360-stm__subtitle">
            {{ $razon }}
            @if($rfcAcc) · <span class="p360-mono">{{ $rfcAcc }}</span>@endif
            @if($accountId) · ID Cuenta: <span class="p360-mono">{{ $accountId }}</span>@endif
          </div>
        </div>

        <div class="p360-stm__heroRight">
          <div class="p360-pills">
            <span class="p360-pill rose">{{ $planKey }}</span>
            <span class="p360-pill">{{ $cycleKey }}</span>
            <span class="p360-pill {{ $isBlocked ? 'warn' : 'good' }}">
              {{ $isBlocked ? 'BLOQUEADA' : 'ACTIVA' }}
            </span>
          </div>

          <div class="p360-heroActions">
            @if($rtMiCuenta)
              <a class="p360-btn p360-btn--ghost p360-btn--back" href="{{ $rtMiCuenta }}">← Mi cuenta</a>
            @else
              <button class="p360-btn p360-btn--ghost p360-btn--back" type="button" onclick="history.back()">← Regresar</button>
            @endif

            <input id="p360EcSearch"
                   class="p360-search"
                   type="search"
                   placeholder="Buscar (mes, RFC, alias, estatus, monto)..."
                   autocomplete="off">
          </div>
        </div>
      </div>

      {{-- SUMMARY --}}
      <div class="p360-stm__summary">
        <div class="p360-sumCard">
          <div class="p360-sumLbl">Saldo global pendiente</div>
          <div class="p360-sumVal p360-mono">MXN ${{ $fmtMoney($balance) }}</div>
          <div class="p360-sumMeta">Actualizado: {{ $printedAt->format('d/m/Y H:i') }}</div>
        </div>

        <div class="p360-sumCard is-soft">
          <div class="p360-sumLbl">Acciones rápidas</div>

          <div class="p360-sumBtns">
            @if($rtPayPending)
              <form method="POST" action="{{ $rtPayPending }}" style="display:inline;">
                @csrf
                <button class="p360-btn p360-btn--orange" type="submit" {{ $balance <= 0.00001 ? 'disabled' : '' }}>
                  Pagar saldo
                </button>
              </form>
            @else
              <button class="p360-btn p360-btn--orange" type="button" disabled>Pagar saldo</button>
            @endif

            @if($rtPayBundle)
              <button class="p360-btn p360-btn--ghost" type="button" onclick="P360EC.openBundle()">
                Adelantar meses
              </button>
            @else
              <button class="p360-btn p360-btn--ghost" type="button" disabled>Adelantar meses</button>
            @endif
          </div>

          <div class="p360-sumHint">
            Selecciona meses pendientes. Meses ya cubiertos se muestran como “Cubierto” y no son seleccionables.
            @if($monthlyPrice > 0.00001)
              <span class="p360-sumHint__sep">·</span>
              <span class="p360-sumHint__strong">Mensualidad:</span> <span class="p360-mono">MXN ${{ $fmtMoney($monthlyPrice) }}</span>
            @endif
          </div>
        </div>
      </div>
    </div>

    {{-- LISTADO --}}
    <div class="p360-stm__list" id="p360EcList">
      @forelse($rows as $st)
        @php
          $ym = (string) data_get($st,'period_ym','');
          $monthLabel = (string) data_get($st,'month_label')
                     ?: (string) data_get($st,'period_label')
                     ?: ($monthFromYm($ym) ?: ($ym ?: '—'));

          $range = $fmtRange(data_get($st,'period_start'), data_get($st,'period_end'));

          $rfcRow = (string) data_get($st,'rfc', $rfcAcc ?: '—');
          $alias  = (string) data_get($st,'alias','') ?: '—';

          $totalRaw = (float) data_get($st,'total', 0);
          $cur      = (string) data_get($st,'currency','MXN');

          $statusRaw = (string) data_get($st,'status','pending');
          [$pillMod,$pillText] = $statusPill($statusRaw);

          $pdfUrl = (string) data_get($st,'pdf_url','');
          $payUrl = (string) data_get($st,'pay_url','');

          $invPdf = (string) data_get($st,'invoice.pdf_url','');
          $invXml = (string) data_get($st,'invoice.xml_url','');
          $invZip = (string) data_get($st,'invoice.zip_url','');

          $isPaid  = Str::contains(strtolower($statusRaw), ['paid','pagado']);
          $isEmpty = Str::contains(strtolower($statusRaw), ['empty','sin_generar','no_generado','sin generar']);

          // FALLBACK: si total viene 0 pero es "pendiente" y hay mensualidad fija, mostramos mensualidad
          $total = $totalRaw;
          if(!$isPaid && !$isEmpty && $totalRaw <= 0.00001 && $monthlyPrice > 0.00001){
            $total = $monthlyPrice;
          }

          $searchBlob = Str::lower($monthLabel.' '.$range.' '.$rfcRow.' '.$alias.' '.$pillText.' '.$cur.' '.$fmtMoney($total));
        @endphp

        <article class="p360-card p360-card--row" data-search="{{ $searchBlob }}">
          <div class="p360-card__inner">

            <div class="p360-card__left">
              <div class="p360-card__top">
                <div class="p360-month">{{ $monthLabel }}</div>
                <span class="p360-status {{ $pillMod }}">
                  <span class="p360-status__dot"></span>
                  {{ strtoupper($pillText) }}
                </span>
              </div>

              <div class="p360-meta">
                <span class="p360-chip"><b>Periodo:</b> {{ $range }}</span>
                <span class="p360-chip"><b>RFC:</b> <span class="p360-mono">{{ $rfcRow }}</span></span>
                <span class="p360-chip"><b>Alias:</b> {{ $alias }}</span>
              </div>
            </div>

            <div class="p360-card__mid">
              <div class="p360-amount">
                <span class="p360-amount__lbl">{{ $isPaid ? 'Pagado' : ($isEmpty ? '—' : 'Por pagar') }}</span>
                <span class="p360-amount__val">{{ $cur }} ${{ $fmtMoney($total) }}</span>
              </div>
            </div>

            <div class="p360-card__right">
              <div class="p360-actions">

                <button type="button"
                        class="p360-btn p360-btn--green"
                        data-open-viewer="1"
                        data-viewer-title="Estado de cuenta · {{ $monthLabel }}"
                        data-viewer-url="{{ $pdfUrl }}"
                        @if($isEmpty) disabled @endif>
                  Visualizar
                </button>

                @if($pdfUrl && !$isEmpty)
                  <a class="p360-btn p360-btn--green" href="{{ $pdfUrl }}" target="_blank" rel="noopener">Descargar</a>
                @else
                  <button type="button" class="p360-btn p360-btn--green" @if($isEmpty) disabled @endif onclick="alert('Aún no hay PDF para este periodo.');">
                    Descargar
                  </button>
                @endif

                @if($isPaid)
                  @if($invPdf || $invXml || $invZip)
                    <a class="p360-btn p360-btn--ghost p360-btn--sm" href="{{ $invPdf ?: ($invXml ?: $invZip) }}" target="_blank" rel="noopener">Factura</a>
                    @if($invPdf)<a class="p360-btn p360-btn--ghost p360-btn--sm" href="{{ $invPdf }}" target="_blank" rel="noopener">PDF</a>@endif
                    @if($invXml)<a class="p360-btn p360-btn--ghost p360-btn--sm" href="{{ $invXml }}" target="_blank" rel="noopener">XML</a>@endif
                    @if($invZip)<a class="p360-btn p360-btn--ghost p360-btn--sm" href="{{ $invZip }}" target="_blank" rel="noopener">ZIP</a>@endif
                  @else
                    <button type="button" class="p360-btn p360-btn--ghost" onclick="alert('Pago aplicado. Factura no disponible (o no aplica).');">Facturar</button>
                  @endif
                @else
                  @if($isEmpty)
                    <button type="button" class="p360-btn p360-btn--orange" disabled>Pagar ahora</button>
                  @else
                    @if(Route::has('cliente.billing.pay'))
                      <form method="POST" action="{{ route('cliente.billing.pay', ['period' => $ym]) }}" style="display:inline;">
                        @csrf
                        <button class="p360-btn p360-btn--orange" type="submit">Pagar ahora</button>
                      </form>
                    @elseif($payUrl)
                      <a class="p360-btn p360-btn--orange" href="{{ $payUrl }}">Pagar ahora</a>
                    @else
                      <button type="button" class="p360-btn p360-btn--orange" onclick="alert('Ruta de pago no configurada.');">Pagar ahora</button>
                    @endif
                  @endif
                @endif

                <button type="button" class="p360-btn p360-btn--icon" title="Ocultar" onclick="this.closest('.p360-card').remove();">✕</button>

              </div>
            </div>

          </div>
        </article>

      @empty
        <div class="p360-empty">
          <div class="p360-empty__card">
            <div class="p360-empty__ttl">Sin estados de cuenta</div>
            <div class="p360-empty__sub">Aún no hay periodos generados para tu cuenta.</div>
          </div>
        </div>
      @endforelse
    </div>

    <div class="p360-footnote">
      Pagos con tarjeta se reflejan al confirmarse en Stripe (webhook). Si el pago es manual, Admin lo registra y se refleja aquí.
    </div>

  </div>

  {{-- MODAL visor PDF --}}
  <div id="p360EcModal" class="p360-modal" aria-hidden="true">
    <div class="p360-modal__panel" role="dialog" aria-modal="true" aria-label="Visor de estado de cuenta">
      <div class="p360-modal__bar">
        <div class="p360-modal__title" id="p360EcModalTitle">Estado de cuenta</div>
        <div class="p360-modal__right">
          <a id="p360EcModalDownload" class="p360-btn p360-btn--green p360-btn--sm" href="#" target="_blank" rel="noopener" style="display:none">Descargar</a>
          <button type="button" class="p360-btn p360-btn--ghost p360-btn--sm" data-close-viewer="1">Cerrar</button>
        </div>
      </div>
      <iframe id="p360EcModalFrame" src="about:blank" title="Estado de cuenta PDF"></iframe>
    </div>
  </div>

  {{-- MODAL Adelantar (bundle) --}}
  <div id="p360BundleModal" class="p360-modal2" aria-hidden="true">
    <div class="p360-modal2__panel" role="dialog" aria-modal="true" aria-label="Adelantar pagos">
      <div class="p360-modal2__bar">
        <div>
          <div class="p360-modal2__ttl">Adelantar pagos</div>
          <div class="p360-modal2__sub">
            Selecciona meses pendientes. Meses ya cubiertos se muestran como “Cubierto” y no se pueden seleccionar.
          </div>
        </div>
        <button type="button" class="p360-btn p360-btn--ghost p360-btn--sm" onclick="P360EC.closeBundle()">Cerrar</button>
      </div>

      <div class="p360-modal2__body">
        @if($rtPayBundle)
          <form method="POST" action="{{ $rtPayBundle }}" id="p360BundleForm">
            @csrf

            <div class="p360-period-grid">
              @foreach($uiPeriods as $p)
                @php
                  $lbl = $monthFromYm($p) ?: $p;

                  $hasStatement = array_key_exists($p, $statMap);

                  // si no hay statement (futuro), forzamos pending + mensualidad
                  $st  = $hasStatement ? $statMap[$p] : ['total' => $monthlyPrice, 'status' => 'pending', 'pdf_url' => ''];

                  $tot = (float)($st['total'] ?? 0);
                  $stt = (string)($st['status'] ?? 'pending');

                  // Solo bloquear si EXISTE statement y está cubierto.
                  $covered = $hasStatement ? $isCoveredPeriod($stt, $tot) : false;

                  $tagText  = $covered ? 'Cubierto' : 'Pendiente';
                  $tagClass = $covered ? 'covered' : 'warn';

                  // display total: si viene 0 pero hay mensualidad fija, mostrar mensualidad
                  $totUi = $tot;
                  if(!$covered && $totUi <= 0.00001 && $monthlyPrice > 0.00001){
                    $totUi = $monthlyPrice;
                  }
                @endphp

                <label class="p360-period-item {{ $covered ? 'is-covered' : '' }}"
                       title="{{ $covered ? 'Este mes ya está cubierto y no se puede seleccionar.' : 'Selecciona para adelantar el pago.' }}">
                  <input type="checkbox" name="periods[]" value="{{ $p }}" {{ $covered ? 'disabled' : '' }}>
                  <div class="p360-period-meta">
                    <div class="p360-period-name">{{ $lbl }}</div>
                    <div class="p360-period-sub">
                      <span class="p360-mono">{{ $p }}</span>
                      · <span class="p360-mono">MXN ${{ number_format($totUi, 2) }}</span>
                      · <span class="p360-tag {{ $tagClass }}">{{ $tagText }}</span>
                    </div>
                  </div>
                </label>
              @endforeach
            </div>

            <div class="p360-modal2__ft">
              <button type="button" class="p360-btn p360-btn--ghost" onclick="P360EC.closeBundle()">Cancelar</button>
              <button type="submit" class="p360-btn p360-btn--orange" id="p360BundlePayBtn" disabled>Pagar meses seleccionados</button>
            </div>
          </form>
        @else
          <div class="p360-empty__sub">La ruta de bundle no está configurada.</div>
        @endif
      </div>
    </div>
  </div>

</div>
@endsection

@push('scripts')
<script>
(function(){
  const q  = (s,r=document)=> r.querySelector(s);
  const qa = (s,r=document)=> Array.from(r.querySelectorAll(s));

  // filtro
  const input = q('#p360EcSearch');
  const list  = q('#p360EcList');
  if(input && list){
    input.addEventListener('input', () => {
      const term = (input.value || '').trim().toLowerCase();
      qa('.p360-card', list).forEach(card => {
        const hay = (card.getAttribute('data-search') || '');
        card.style.display = (!term || hay.includes(term)) ? '' : 'none';
      });
    });
  }

  // visor
  const modal = q('#p360EcModal');
  const frame = q('#p360EcModalFrame');
  const title = q('#p360EcModalTitle');
  const dl    = q('#p360EcModalDownload');

  function openViewer(url, ttl){
    if(!url){
      alert('Aún no hay PDF disponible para este periodo.');
      return;
    }
    title.textContent = ttl || 'Estado de cuenta';
    frame.src = url;
    dl.href = url;
    dl.style.display = 'inline-flex';
    modal.classList.add('open');
    modal.setAttribute('aria-hidden','false');
    document.addEventListener('keydown', onEsc);
  }

  function closeViewer(){
    if(!modal) return;
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden','true');
    if(frame) frame.src = 'about:blank';
    if(dl) dl.style.display = 'none';
  }

  // Bundle modal
  const b = q('#p360BundleModal');
  const bundleForm = q('#p360BundleForm');
  const bundleBtn  = q('#p360BundlePayBtn');

  function syncBundleBtn(){
    if(!bundleForm || !bundleBtn) return;
    const any = qa('input[type="checkbox"][name="periods[]"]', bundleForm).some(i => !i.disabled && i.checked);
    bundleBtn.disabled = !any;
  }

  function openBundle(){
    if(!b) return;
    b.classList.add('open');
    b.setAttribute('aria-hidden','false');
    syncBundleBtn();
    document.addEventListener('keydown', onEsc);
  }

  function closeBundle(){
    if(!b) return;
    b.classList.remove('open');
    b.setAttribute('aria-hidden','true');
    // limpiar checks seleccionados (solo enabled)
    if(bundleForm){
      qa('input[type="checkbox"]', bundleForm).forEach(i => { if(!i.disabled) i.checked = false; });
    }
    syncBundleBtn();
  }

  function onEsc(e){
    if(e.key === 'Escape') { closeViewer(); closeBundle(); }
  }

  document.addEventListener('click', (e) => {
    const openBtn = e.target.closest('[data-open-viewer]');
    if(openBtn){
      if(openBtn.disabled) return;
      e.preventDefault();
      openViewer(openBtn.getAttribute('data-viewer-url'), openBtn.getAttribute('data-viewer-title'));
      return;
    }
    const closeBtn = e.target.closest('[data-close-viewer]');
    if(closeBtn){
      e.preventDefault();
      closeViewer();
      return;
    }
  });

  if(modal){
    modal.addEventListener('click', (e) => {
      if(e.target === modal) closeViewer();
    });
  }

  if(b){
    b.addEventListener('click', (e) => {
      if(e.target === b) closeBundle();
    });
  }

  if(bundleForm){
    bundleForm.addEventListener('change', (e) => {
      if(e.target && e.target.matches('input[type="checkbox"][name="periods[]"]')){
        syncBundleBtn();
      }
    });
  }

  window.P360EC = { openBundle, closeBundle };
})();
</script>
@endpush
