{{-- resources/views/cliente/sat/_partials/connections.blade.php (v2 · UI cleanup · NO inline styles · external RFC card modern) --}}
@php
  $externalRfc = strtoupper(trim((string)($externalRfc ?? '')));
  $externalOk  = (bool)($externalVerified ?? false);
@endphp

<section class="sat-section" id="block-connections">
  <div class="sat-card sat-mod-card">
    <div class="sat-mod-head">
      <div class="sat-mod-title">
        <span class="sat-mod-icon" aria-hidden="true">🔐</span>
        <div>
          <div class="sat-mod-kicker">SAT · CONEXIONES</div>
          <h3 class="sat-mod-h3">RFCs y credenciales (CSD)</h3>
          <div class="sat-mod-sub">
            Administra RFCs, razón social, CSD y validaciones para poder solicitar descargas.
          </div>
        </div>
      </div>
    </div>

    {{-- RFC EXTERNO (integrado) --}}
    @if($externalRfc !== '')
      <div class="sat-subcard sat-subcard-soft sat-external-rfc-card">
        <div class="sat-subhead">
          <div class="sat-subtitle">RFC proveniente de registro externo</div>
          <div class="sat-subnote">
            Este RFC proviene del registro externo (Admin). Para poder usarlo en solicitudes SAT debe registrarse/validarse como credencial SAT.
          </div>
        </div>

        <div class="sat-external-rfc-body">
          <div class="sat-external-rfc-left">
            <div class="sat-external-rfc-kicker">RFC</div>
            <div class="sat-external-rfc-value mono">{{ $externalRfc }}</div>
          </div>

          <div class="sat-external-rfc-right">
            <div class="sat-external-rfc-item">
              <div class="sat-external-rfc-label">Estatus registro</div>
              <div class="sat-external-rfc-val">
                <span class="sat-badge sat-badge-{{ $externalOk ? 'ok' : 'pending' }}">
                  {{ $externalOk ? 'Registro verificado' : 'Registro capturado' }}
                </span>
              </div>
            </div>

            <div class="sat-external-rfc-item">
              <div class="sat-external-rfc-label">Estatus SAT</div>
              <div class="sat-external-rfc-val">
                <span class="sat-badge sat-badge-warn">Pendiente validación SAT</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    @endif

    {{-- Mis RFCs (se mantiene el partial para no romper JS) --}}
    <div class="sat-subcard sat-rfcs-wrap">
      @include('cliente.sat._partials.rfcs', [
        'credList'    => $credList ?? collect(),
        'plan'        => $plan ?? 'FREE',
        'rtCsdStore'  => $rtCsdStore ?? '#',
        'rtAlias'     => $rtAlias ?? '#',
        'rtRfcReg'    => $rtRfcReg ?? '#',
        'rtRfcDelete' => $rtRfcDelete ?? '#',
      ])
    </div>

    <div class="sat-mod-foot">
      <div class="sat-mod-foot-note">
        Recomendación: valida al menos 1 RFC para habilitar solicitudes SAT.
      </div>
    </div>
  </div>
</section>
