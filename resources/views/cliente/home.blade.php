{{-- C:\wamp64\www\pactopia360_erp\resources\views\cliente\home.blade.php --}}
@extends('layouts.cliente')
@section('title','Inicio · Pactopia360')

@php
  // ===== Preferir plan desde summary (admin) =====
  $summaryPlan = null;
  if (isset($summary) && is_array($summary) && !empty($summary['plan'])) {
      $summaryPlan = strtoupper((string)$summary['plan']);
  }

  $planBase = $plan ?? 'FREE';
  $plan     = $summaryPlan ?? strtoupper((string)$planBase);
  $planKey  = strtolower((string)($planKey ?? $plan));

  $razonV   = (string)($razon ?? (auth('web')->user()->nombre ?? auth('web')->user()->email ?? 'Cliente'));
  $timbresV = (int)($timbres ?? 0);
  $saldoV   = (float)($saldo ?? 0.0);

  $kEmit    = (float)($kpis['emitidos']   ?? 0);
  $kCanc    = (float)($kpis['cancelados'] ?? 0);
  $kTotal   = (float)($kpis['total']      ?? 0);
  $kDelta   = (float)($kpis['delta']      ?? 0);

  $periodFrom = (string) data_get($kpis, 'period.from', '');
  $periodTo   = (string) data_get($kpis, 'period.to', '');

  $labelsV  = $series['labels'] ?? [];
  $lineV    = data_get($series, 'series.line_facturacion', data_get($series, 'series.emitidos_total', []));
  $lineCanc = data_get($series, 'series.line_cancelados', []);
  $barsV    = data_get($series, 'series.bar_q', [0,0,0,0]);

  $rtKpisJs   = \Illuminate\Support\Facades\Route::has('cliente.home.kpis')
                  ? route('cliente.home.kpis')
                  : (\Illuminate\Support\Facades\Route::has('cliente.kpis') ? route('cliente.kpis') : '');
  $rtSeriesJs = \Illuminate\Support\Facades\Route::has('cliente.home.series')
                  ? route('cliente.home.series')
                  : (\Illuminate\Support\Facades\Route::has('cliente.series') ? route('cliente.series') : '');

  $dataSource = ($dataSource ?? 'db'); // 'db' | 'demo'
  $isLocal    = (bool) ($isLocal ?? false);

  // Summary (admin): estado de cuenta / plan / espacio / bloqueo
  $sum = is_array($summary ?? null) ? $summary : [];
  $sumPlan    = strtoupper((string)($sum['plan'] ?? $plan));
  $sumCycle   = (string)($sum['cycle'] ?? '');
  $sumEstado  = (string)($sum['estado'] ?? '');
  $sumBlocked = (bool)($sum['blocked'] ?? false);

  // ============================================================
  // ✅ Licencia (gobernada por Admin)
  // - Base: billing.amount_mxn (o amount_mxn)
  // - Override: billing.override.amount_mxn (o override_amount_mxn)
  // ============================================================
  $licBase = (int) (
    data_get($sum, 'billing.amount_mxn')
    ?? data_get($sum, 'amount_mxn')
    ?? data_get($sum, 'license.amount_mxn')
    ?? 0
  );

  $licOv = data_get($sum, 'billing.override.amount_mxn');
  if (!is_numeric($licOv)) $licOv = data_get($sum, 'billing.override_amount_mxn');
  if (!is_numeric($licOv)) $licOv = data_get($sum, 'override.amount_mxn');
  if (!is_numeric($licOv)) $licOv = data_get($sum, 'override_amount_mxn');

  $licHasOverride = is_numeric($licOv);
  $licAmount = (int) ($licHasOverride ? (int)$licOv : $licBase);

  // Label para hint (PRO · MENSUAL / ANUAL)
  $licHint = trim(
    ($sumPlan ?: 'FREE')
    . ($sumCycle ? (' · ' . strtoupper($sumCycle)) : '')
    . ($licHasOverride ? ' · PERSONALIZADO' : '')
  );

  // Espacio
  $spaceTotal = (float)($sum['space_total'] ?? 0);
  $spaceUsed  = (float)($sum['space_used'] ?? 0);
  $spacePct   = (float)($sum['space_pct'] ?? 0);

  // Accesos
  $rtSat = \Illuminate\Support\Facades\Route::has('cliente.sat.descargas.index')
      ? route('cliente.sat.descargas.index')
      : (\Illuminate\Support\Facades\Route::has('cliente.sat.index') ? route('cliente.sat.index') : '#');

  $rtFact = \Illuminate\Support\Facades\Route::has('cliente.facturacion.index') ? route('cliente.facturacion.index') : '#';
  $rtPerf = \Illuminate\Support\Facades\Route::has('cliente.perfil') ? route('cliente.perfil') : url('/cliente/perfil');

  // Últimos CFDI (si vienen)
  $recentRows = collect($recent ?? []);
@endphp

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/client/css/pages/home.css') }}">
@endpush

@section('content')
<div class="p360-home">
  {{-- HERO --}}
  <section class="p360-hero card p360-card">
    <div class="p360-hero__top">
      <div class="p360-hero__title">
        <div class="p360-hero__hello">Bienvenido</div>
        <div class="p360-hero__name">{{ $razonV }}</div>
      </div>

      <div class="p360-hero__meta">
        @if($isLocal)
          <span class="p360-pill p360-pill--{{ $dataSource === 'db' ? 'ok' : 'warn' }}"
                title="Fuente de datos del dashboard (solo visible en local)">
            Fuente: {{ $dataSource === 'db' ? 'Base de datos' : 'DEMO' }}
          </span>
        @endif

        <span class="p360-pill" title="Plan">
          {{ $sumPlan }}@if($sumCycle) · {{ strtoupper($sumCycle) }}@endif
        </span>

        @if($sumEstado)
          <span class="p360-pill" title="Estado de cuenta">{{ strtoupper($sumEstado) }}</span>
        @endif

        @if($sumBlocked)
          <span class="p360-pill p360-pill--danger" title="Cuenta bloqueada">BLOQUEADA</span>
        @endif
      </div>
    </div>

    <div class="p360-hero__bottom">
      <div class="p360-hero__kpiRow">
        {{-- ✅ KPI principal: LICENCIA (precio vigente admin) --}}
        <div class="p360-kpi p360-kpi--brand">
          <div class="p360-kpi__label">Licencia</div>
          <div class="p360-kpi__value" id="kpi-licencia">
            ${{ number_format((float)$licAmount, 0) }}
          </div>
          <div class="p360-kpi__hint">
            {{ $licHint !== '' ? $licHint : 'MXN' }}
          </div>
        </div>

        <div class="p360-kpi">
          <div class="p360-kpi__label">Timbres disponibles</div>
          <div class="p360-kpi__value" id="kpi-timbres">{{ number_format($timbresV) }}</div>
          <div class="p360-kpi__hint">Disponibles</div>
        </div>

        <div class="p360-kpi">
          <div class="p360-kpi__label">Facturación mes</div>
          <div class="p360-kpi__value" id="kpi-em">${{ number_format($kEmit, 2) }}</div>
          <div class="p360-kpi__hint">Emitidos</div>
        </div>

        <div class="p360-kpi">
          <div class="p360-kpi__label">Cancelados</div>
          <div class="p360-kpi__value" id="kpi-ca">${{ number_format($kCanc, 2) }}</div>
          <div class="p360-kpi__hint">Mes actual</div>
        </div>

        <div class="p360-kpi">
          <div class="p360-kpi__label">Total mensual</div>
          <div class="p360-kpi__value" id="kpi-to">${{ number_format($kTotal, 2) }}</div>
          <div class="p360-kpi__hint">
            Variación:
            <span id="kpi-delta" class="p360-delta {{ $kDelta >= 0 ? 'up' : 'down' }}">
              {{ $kDelta >= 0 ? '+' : '' }}{{ number_format($kDelta, 2) }}%
            </span>
          </div>
        </div>
      </div>

      {{-- Bóveda / Espacio (si existe en summary) --}}
      <div class="p360-hero__storage">
        <div class="p360-storage card p360-card">
          <div class="p360-storage__head">
            <div class="p360-storage__title">Bóveda / almacenamiento</div>
            <div class="p360-storage__meta">
              @if($spaceTotal > 0)
                <span>{{ number_format($spaceUsed, 0) }} / {{ number_format($spaceTotal, 0) }} MB</span>
              @else
                <span class="muted">Sin información de cuota</span>
              @endif
            </div>
          </div>
          <div class="p360-progress" role="progressbar" aria-valuenow="{{ (int)$spacePct }}" aria-valuemin="0" aria-valuemax="100">
            <span style="width: {{ (float)$spacePct }}%"></span>
          </div>
          <div class="p360-storage__foot">
            <span class="muted">Uso estimado</span>
            <strong>{{ number_format($spacePct, 1) }}%</strong>
          </div>
        </div>

        <div class="p360-actions card p360-card">
          <div class="p360-actions__title">Accesos rápidos</div>
          <div class="p360-actions__grid">
            <a class="p360-action" href="{{ $rtSat }}">
              <div class="p360-action__t">Descargas SAT</div>
              <div class="p360-action__d">Descarga, procesa y envía a bóveda.</div>
            </a>
            <a class="p360-action" href="{{ $rtFact }}">
              <div class="p360-action__t">Facturación</div>
              <div class="p360-action__d">Emite y administra CFDI.</div>
            </a>
            <a class="p360-action" href="{{ $rtPerf }}">
              <div class="p360-action__t">Perfil</div>
              <div class="p360-action__d">Configura tu cuenta y seguridad.</div>
            </a>
          </div>
        </div>
      </div>
    </div>

    <div class="p360-hero__period muted">
      @if($periodFrom && $periodTo)
        Periodo: {{ \Carbon\Carbon::parse($periodFrom)->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($periodTo)->format('d/m/Y') }}
      @endif
    </div>
  </section>

  {{-- GRID PRINCIPAL --}}
  <section class="p360-grid">
    <div class="p360-col">
      <div class="card p360-card">
        <div class="p360-card__head">
          <div class="p360-card__title">Facturación del mes</div>
          <div class="p360-card__sub muted">Serie diaria (emitidos/cancelados)</div>
        </div>
        <div id="chart-lines" class="p360-chart" aria-label="Gráfica de facturación del mes"></div>
      </div>

      <div class="card p360-card">
        <div class="p360-card__head">
          <div class="p360-card__title">Comparativo semanal</div>
          <div class="p360-card__sub muted">Acumulado aproximado por semana</div>
        </div>
        <div id="chart-bars" class="p360-chart" aria-label="Gráfica comparativo semanal"></div>
      </div>
    </div>

    <div class="p360-col">
      <div class="card p360-card">
        <div class="p360-card__head">
          <div class="p360-card__title">Actividad reciente</div>
          <div class="p360-card__sub muted">Últimos CFDI detectados</div>
        </div>

        @if($recentRows->count() > 0)
          <div class="p360-tableWrap">
            <table class="p360-table">
              <thead>
                <tr>
                  <th>Fecha</th>
                  <th>UUID</th>
                  <th class="tr">Total</th>
                  <th class="tc">Estatus</th>
                </tr>
              </thead>
              <tbody>
                @foreach($recentRows as $r)
                  @php
                    $uuid = (string)($r->uuid ?? '—');
                    $uuidShort = $uuid !== '—' ? (substr($uuid,0,8).'…'.substr($uuid,-6)) : '—';
                    $st = strtolower((string)($r->estatus ?? ''));
                    $stClass = $st === 'cancelado' ? 'danger' : ($st === 'emitido' ? 'ok' : 'mut');
                    $dt = $r->fecha ? \Carbon\Carbon::parse($r->fecha)->format('d/m/Y') : '—';
                  @endphp
                  <tr>
                    <td>{{ $dt }}</td>
                    <td title="{{ $uuid }}">{{ $uuidShort }}</td>
                    <td class="tr">${{ number_format((float)($r->total ?? 0), 2) }}</td>
                    <td class="tc">
                      <span class="p360-tag p360-tag--{{ $stClass }}">{{ strtoupper($st ?: 'N/D') }}</span>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @else
          <div class="p360-empty">
            <div class="p360-empty__t">Sin CFDI recientes</div>
            <div class="p360-empty__d">Cuando existan CFDI en la cuenta, aparecerán aquí automáticamente.</div>
          </div>
        @endif
      </div>

      <div class="card p360-card">
        <div class="p360-card__head">
          <div class="p360-card__title">Estado operativo</div>
          <div class="p360-card__sub muted">Validación rápida de la cuenta</div>
        </div>

        <div class="p360-status">
          <div class="p360-status__row">
            <span class="muted">Plan</span>
            <strong>{{ $sumPlan }}</strong>
          </div>

          {{-- ✅ Cambia "Saldo" por "Licencia" (precio vigente admin) --}}
          <div class="p360-status__row">
            <span class="muted">Licencia</span>
            <strong>${{ number_format((float)$licAmount, 0) }} MXN</strong>
          </div>

          <div class="p360-status__row">
            <span class="muted">Timbres</span>
            <strong>{{ number_format($timbresV) }}</strong>
          </div>
          <div class="p360-status__row">
            <span class="muted">Bloqueo</span>
            <strong class="{{ $sumBlocked ? 'p360-txt-danger' : 'p360-txt-ok' }}">{{ $sumBlocked ? 'Sí' : 'No' }}</strong>
          </div>
          @if($sumEstado)
            <div class="p360-status__row">
              <span class="muted">Estado cuenta</span>
              <strong>{{ strtoupper($sumEstado) }}</strong>
            </div>
          @endif
        </div>
      </div>
    </div>
  </section>
</div>
@endsection

@push('scripts')
  <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
  <script>
  document.addEventListener('DOMContentLoaded', ()=>{

    // ===== Helpers de tema/tokens (sin tocar header/footer/menu) =====
    const rootStyle = getComputedStyle(document.documentElement);
    const getVar = (name, fallback) => (rootStyle.getPropertyValue(name) || '').trim() || fallback;

    const fore  = getVar('--ink', (document.documentElement.getAttribute('data-theme') === 'dark' ? '#e5e7eb' : '#0f172a'));
    const gridC = getVar('--bd',  (document.documentElement.getAttribute('data-theme') === 'dark' ? 'rgba(255,255,255,.12)' : 'rgba(0,0,0,.08)'));

    const brand = getVar('--brand', '#E11D48');
    const accent= getVar('--accent','#0EA5E9');

    const labels   = @json($labelsV);
    const lineData = @json($lineV);
    const cancData = @json($lineCanc);
    const barsData = @json($barsV);

    function money(v){ return '$' + (Number(v||0)).toFixed(2); }

    // ===== Charts =====
    if (window.ApexCharts) {
      window.chartLines = new ApexCharts(document.querySelector('#chart-lines'), {
        chart:{ type:'area', height:340, foreColor:fore, toolbar:{show:false}, animations:{enabled:true} },
        stroke:{ curve:'smooth', width:3 },
        series:[
          { name:'Emitidos', data: lineData },
          { name:'Cancelados', data: cancData }
        ],
        xaxis:{ categories: labels, labels:{ rotate:-35 }, tickPlacement:'on' },
        yaxis:{ labels:{ formatter:(v)=> money(v) } },
        tooltip:{ y:{ formatter:(v)=> money(v) } },
        colors:[accent, brand],
        fill:{ type:'gradient', gradient:{shadeIntensity:.55,opacityFrom:.42,opacityTo:.08,stops:[0,90,100]} },
        grid:{ borderColor:gridC }
      });
      chartLines.render();

      window.chartBars = new ApexCharts(document.querySelector('#chart-bars'), {
        chart:{ type:'bar', height:340, foreColor:fore, toolbar:{show:false}, animations:{enabled:true} },
        plotOptions:{ bar:{ columnWidth:'45%', borderRadius:10 } },
        series:[{ name:'Emitidos por semana', data: barsData }],
        xaxis:{ categories:['Sem 1','Sem 2','Sem 3','Sem 4'] },
        yaxis:{ labels:{ formatter:(v)=> money(v) } },
        tooltip:{ y:{ formatter:(v)=> money(v) } },
        colors:[brand],
        grid:{ borderColor:gridC }
      });
      chartBars.render();
    }

    // ===== Auto refresh (30s): KPIs + series (mantiene tu lógica real/demo) =====
    const rtKpis   = @json($rtKpisJs);
    const rtSeries = @json($rtSeriesJs);
    const csrf     = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const isLocal  = @json($isLocal);

    async function safeJson(res){
      const ct = res.headers.get('content-type') || '';
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      if (!ct.includes('application/json')) {
        const txt = await res.text(); throw new Error(`Respuesta no JSON: ${txt.slice(0,120)}…`);
      }
      return res.json();
    }

    function setText(id, val){
      const el = document.getElementById(id);
      if (el) el.textContent = val;
    }

    async function refreshData(){
      try{
        if(!rtKpis || !rtSeries) return;

        const [kpiRes, serieRes] = await Promise.all([
          fetch(rtKpis,   { headers:{'X-CSRF-TOKEN': csrf} }),
          fetch(rtSeries, { headers:{'X-CSRF-TOKEN': csrf} })
        ]);

        const k = await safeJson(kpiRes);
        const s = await safeJson(serieRes);

        setText('kpi-em', money(k.emitidos||0));
        setText('kpi-ca', money(k.cancelados||0));
        setText('kpi-to', money(k.total||0));

        const delta = Number(k.delta||0);
        const deltaEl = document.getElementById('kpi-delta');
        if (deltaEl){
          deltaEl.textContent = (delta >= 0 ? '+' : '') + delta.toFixed(2) + '%';
          deltaEl.classList.toggle('up', delta >= 0);
          deltaEl.classList.toggle('down', delta < 0);
        }

        const line = (s.series && (s.series.line_facturacion || s.series.emitidos_total))
          ? (s.series.line_facturacion || s.series.emitidos_total)
          : [];
        const canc = (s.series && s.series.line_cancelados) ? s.series.line_cancelados : [];
        const bars = (s.series && s.series.bar_q) ? s.series.bar_q : [0,0,0,0];

        if(window.chartLines && window.chartBars){
          chartLines.updateOptions({ xaxis:{ categories: s.labels || [] } });
          chartLines.updateSeries([
            { name:'Emitidos', data: line },
            { name:'Cancelados', data: canc }
          ]);
          chartBars.updateSeries([{ name:'Emitidos por semana', data: bars }]);
        }

        if (isLocal) {
          console.info('[P360][HOME] Fuente KPIs:', k.source || 'db', 'rows:', k.row_count ?? 'n/a');
          console.info('[P360][HOME] Fuente Series:', s.source || 'db', 'rows:', s.row_count ?? 'n/a');
        }
      }catch(e){
        console.warn('AutoRefresh error:', e);
      }
    }

    setInterval(refreshData, 30000);
  });
  </script>
@endpush
