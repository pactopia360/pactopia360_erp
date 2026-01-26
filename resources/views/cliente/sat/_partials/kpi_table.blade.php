{{-- resources/views/cliente/sat/_partials/kpi_table.blade.php (v2 · KPI cards · modern · NO table · no inline styles) --}}
@php
  // Valores esperados:
  // $filesPeriod, $filesTotal, $rfcsValidated, $rfcsPending, $reqDone, $reqStart, $reqPeriod, $reqAvailable
  // $isProPlan (bool), $modeLabel (string), $externalRfc (string), $externalVerified (bool)
  // $vault (array), $vaultActive (bool), $vaultQuotaGb, $vaultUsedGb, $vaultAvailableGb, $vaultUsedPct

  $modeLabelSafe = $modeLabel ?? 'FUENTE: PRODUCCIÓN';

  $planIsPro = !empty($isProPlan);
  $planLabel = $planIsPro ? 'PRO' : 'FREE';

  $externalRfcSafe = strtoupper(trim((string)($externalRfc ?? '')));
  $externalOk      = !empty($externalVerified);

  $filesPeriodV  = (int)($filesPeriod ?? 0);
  $filesTotalV   = (int)($filesTotal ?? 0);
  $rfcsValV      = (int)($rfcsValidated ?? 0);
  $rfcsPendV     = (int)($rfcsPending ?? 0);

  $reqDoneV      = (int)($reqDone ?? 0);
  $reqStartV     = (int)($reqStart ?? 0);
  $reqPeriodV    = (int)($reqPeriod ?? 0);
  $reqAvailV     = (int)($reqAvailable ?? 0);

  $vaultIsOn     = !empty($vaultActive) && (float)($vaultQuotaGb ?? 0) > 0;
  $vaultQuotaV   = (float)($vaultQuotaGb ?? 0);
  $vaultUsedV    = (float)($vaultUsedGb ?? 0);
  $vaultFreeV    = (float)($vaultAvailableGb ?? 0);
  $vaultPctV     = (float)($vaultUsedPct ?? 0);

  $vaultMainLabel = $vaultIsOn
    ? (number_format($vaultUsedV, 3) . ' / ' . number_format($vaultQuotaV, 0) . ' Gb')
    : '—';

  $vaultSubLabel = $vaultIsOn
    ? ('Libre: ' . number_format($vaultFreeV, 3) . ' Gb · Uso: ' . number_format($vaultPctV, 2) . '%')
    : 'Sin bóveda activa';
@endphp

<div class="sat-card sat-kpi-card sat-kpi-cards">
  <div class="sat-kpi-head sat-kpi-head-modern">
    <div class="sat-kpi-title">
      <div class="sat-kpi-kicker">RESUMEN</div>
      <h3 class="sat-kpi-h3">Indicadores del módulo SAT</h3>
      <div class="sat-kpi-sub">{{ $modeLabelSafe }}</div>
    </div>

    <div class="sat-kpi-meta sat-kpi-meta-modern">
      @if($externalRfcSafe !== '')
        <div class="sat-kpi-chip">
          <span class="sat-kpi-chip-label">RFC externo</span>
          <span class="sat-kpi-chip-val mono">{{ $externalRfcSafe }}</span>
          <span class="sat-badge sat-badge-{{ $externalOk ? 'ok' : 'pending' }}">
            {{ $externalOk ? 'Verificado' : 'Capturado' }}
          </span>
        </div>
      @endif

      <div class="sat-kpi-chip">
        <span class="sat-kpi-chip-label">Plan</span>
        <span class="sat-badge sat-badge-{{ $planIsPro ? 'ok' : 'warn' }}">{{ $planLabel }}</span>
      </div>
    </div>
  </div>

  <div class="sat-kpi-grid">
    {{-- Archivos (periodo) --}}
    <div class="sat-kpi-tile">
      <div class="sat-kpi-tile-top">
        <div class="sat-kpi-tile-label">Archivos (periodo)</div>
        <div class="sat-kpi-tile-hint">Rango reciente (30 días / según summary)</div>
      </div>
      <div class="sat-kpi-tile-val mono">{{ number_format($filesPeriodV) }}</div>
      <div class="sat-kpi-tile-sub">Descargas registradas en el periodo</div>
    </div>

    {{-- Archivos (totales) --}}
    <div class="sat-kpi-tile">
      <div class="sat-kpi-tile-top">
        <div class="sat-kpi-tile-label">Archivos (totales)</div>
        <div class="sat-kpi-tile-hint">Histórico del módulo</div>
      </div>
      <div class="sat-kpi-tile-val mono">{{ number_format($filesTotalV) }}</div>
      <div class="sat-kpi-tile-sub">Total acumulado</div>
    </div>

    {{-- RFCs validados --}}
    <div class="sat-kpi-tile">
      <div class="sat-kpi-tile-top">
        <div class="sat-kpi-tile-label">RFCs validados</div>
        <div class="sat-kpi-tile-hint">Credencial SAT validada</div>
      </div>
      <div class="sat-kpi-tile-val mono">{{ number_format($rfcsValV) }}</div>
      <div class="sat-kpi-tile-sub">Listos para solicitar</div>
    </div>

    {{-- RFCs por validar --}}
    <div class="sat-kpi-tile">
      <div class="sat-kpi-tile-top">
        <div class="sat-kpi-tile-label">RFCs por validar</div>
        <div class="sat-kpi-tile-hint">Registrados sin validación</div>
      </div>
      <div class="sat-kpi-tile-val mono">{{ number_format($rfcsPendV) }}</div>
      <div class="sat-kpi-tile-sub">Requieren validación SAT</div>
    </div>

    {{-- Peticiones realizadas --}}
    <div class="sat-kpi-tile sat-kpi-tile-wide">
      <div class="sat-kpi-tile-top">
        <div class="sat-kpi-tile-label">Peticiones realizadas</div>
        <div class="sat-kpi-tile-hint">Solicitudes registradas / ejecutadas</div>
      </div>

      <div class="sat-kpi-tile-row">
        <div class="sat-kpi-tile-val mono">{{ number_format($reqDoneV) }}</div>
        <div class="sat-kpi-tile-pills">
          <span class="sat-kpi-pill">En periodo: <span class="mono">{{ number_format($reqPeriodV) }}</span></span>
        </div>
      </div>

      <div class="sat-kpi-tile-sub">Histórico de solicitudes</div>
    </div>

    {{-- Peticiones disponibles --}}
    <div class="sat-kpi-tile sat-kpi-tile-wide">
      <div class="sat-kpi-tile-top">
        <div class="sat-kpi-tile-label">Peticiones disponibles</div>
        <div class="sat-kpi-tile-hint">Asignadas - realizadas</div>
      </div>

      <div class="sat-kpi-tile-row">
        <div class="sat-kpi-tile-val mono">{{ number_format($reqAvailV) }}</div>
        <div class="sat-kpi-tile-pills">
          <span class="sat-kpi-pill">Asignadas: <span class="mono">{{ number_format($reqStartV) }}</span></span>
        </div>
      </div>

      <div class="sat-kpi-tile-sub">Capacidad restante</div>
    </div>

    {{-- Bóveda fiscal --}}
    <div class="sat-kpi-tile sat-kpi-tile-wide">
      <div class="sat-kpi-tile-top">
        <div class="sat-kpi-tile-label">Bóveda fiscal</div>
        <div class="sat-kpi-tile-hint">Capacidad y uso del resguardo</div>
      </div>

      <div class="sat-kpi-tile-row">
        <div class="sat-kpi-tile-val mono">{{ $vaultMainLabel }}</div>
        <div class="sat-kpi-tile-pills">
          @if($vaultIsOn)
            <span class="sat-kpi-pill">Libre: <span class="mono">{{ number_format($vaultFreeV, 3) }} Gb</span></span>
            <span class="sat-kpi-pill">Uso: <span class="mono">{{ number_format($vaultPctV, 2) }}%</span></span>
          @else
            <span class="sat-kpi-muted">{{ $vaultSubLabel }}</span>
          @endif
        </div>
      </div>

      <div class="sat-kpi-tile-sub">{{ $vaultIsOn ? 'Bóveda activa' : 'Bóveda no activa' }}</div>
    </div>
  </div>

  <div class="sat-kpi-foot sat-kpi-foot-modern">
    <div class="sat-kpi-foot-left">
      <span class="sat-kpi-muted">Indicadores principales para operación y control.</span>
    </div>
    <div class="sat-kpi-foot-right">
      <a href="#block-manual-downloads" class="link-soft sat-link-soft">Ir a descargas manuales</a>
    </div>
  </div>
</div>
