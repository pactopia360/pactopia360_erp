{{-- Card de solicitudes: asignadas / consumidas / restantes + barra + CTA --}}
@php
  $assigned = (int)($assigned ?? ($cuenta->sat_quota_assigned ?? ($plan==='PRO'?12:1)));
  $used     = (int)($used     ?? ($cuenta->sat_quota_used     ?? 0));
  $left     = max(0, $assigned - $used);
  $pct      = $assigned>0 ? round(($used/$assigned)*100) : 0;
@endphp

<div class="section">
  <h3>Panel de solicitudes</h3>

  <div class="kpis">
    <div class="kpi"><small>Asignadas</small><b>{{ $assigned }}</b></div>
    <div class="kpi"><small>Consumidas</small><b>{{ $used }}</b></div>
    <div class="kpi"><small>Restantes</small><b>{{ $left }}</b></div>
    <div class="kpi">
      <small>Uso</small>
      <b>{{ $pct }}%</b>
      <div class="bar">
        <span style="width: {{ $pct }}%"></span>
      </div>
    </div>
  </div>

  <div class="sol-cta">
    <div class="mut">FREE: 1 solicitud activa ≤ 1 mes. · PRO: 12 por RFC (compra extra disponible).</div>
    <a href="{{ $rtBuy ?? '#' }}" class="btn">Comprar más</a>
  </div>

  {{-- Sub-sección Automatizadas (bloqueadas en FREE) --}}
  <div class="auto-grid" style="margin-top:12px">
    <div class="auto-item">
      <div class="auto-hd">
        <b>Descarga diaria por RFC</b>
        @if(($plan ?? 'FREE')!=='PRO') <span class="badge-free">Sólo PRO</span>@endif
      </div>
      <div class="mut">Corre 00:30, periodo deslizante de 7 días, consume solicitudes.</div>
      <button class="btn" {{ ($plan ?? 'FREE')!=='PRO' ? 'disabled' : '' }}>Configurar</button>
    </div>

    <div class="auto-item">
      <div class="auto-hd">
        <b>Auto-verificación de paquetes</b>
        @if(($plan ?? 'FREE')!=='PRO') <span class="badge-free">Sólo PRO</span>@endif
      </div>
      <div class="mut">Revisa pendientes cada hora y descarga cuando estén listos.</div>
      <button class="btn" {{ ($plan ?? 'FREE')!=='PRO' ? 'disabled' : '' }}>Activar</button>
    </div>
  </div>
</div>
