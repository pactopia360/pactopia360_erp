{{-- resources/views/cliente/sat/_partials/rfcs.blade.php
     v43 · RFCs separado en parciales (table + invite modal + scripts)
--}}

@php
  /**
   * Espera:
   *  - $credList    (array|Collection de RFCs)
   *  - $plan        ('FREE'|'PRO'|'EMPRESA'...)
   *  - $rtCsdStore  (ruta para subir .cer/.key/password)
   *  - $rtAlias     (ruta para guardar razón social / alias)
   *  - $rtRfcReg    (ruta para actualizar RFC)
   *  - $rtRfcDelete (ruta para eliminar RFC) opcional
   */
  $credList    = collect($credList ?? []);
  $planCode    = strtoupper((string)($plan ?? 'FREE'));
  $isProPlan   = in_array($planCode, ['PRO','PREMIUM','EMPRESA','BUSINESS','BUSINESS_PRO'], true);
  $rtRfcDelete = $rtRfcDelete ?? '#';
@endphp

<div class="rfcs-neo">
  <div class="rfcs-neo-card">

    {{-- HEADER --}}
    <div class="rfcs-neo-hd">
      <div class="rfcs-neo-title-row">
        <span class="rfcs-neo-icon" aria-hidden="true">🧩</span>
        <div>
          <div class="rfcs-neo-kicker">SAT · Conexiones</div>
          <h3 class="rfcs-neo-title">Mis RFCs</h3>
          <div class="rfcs-neo-desc">
            Administra RFCs, razón social y certificados (CSD). Puedes invitar a un emisor externo para registrar su RFC.
          </div>
        </div>
      </div>

      <div class="rfcs-neo-hd-actions" role="group" aria-label="Acciones RFC">
        <button type="button"
                class="btn-rfcs icon ghost"
                id="btnRfcPageRefresh"
                data-tip="Actualizar pantalla">🔄</button>

        <button type="button"
                class="btn-rfcs btn-rfcs--secondary"
                id="btnExternalRfcInviteOpen"
                data-tip="Enviar liga para que un emisor externo registre su RFC">
          <span aria-hidden="true">✉️</span>
          <span>Registro externo</span>
        </button>

        <button type="button"
                class="btn-rfcs btn-rfcs--primary"
                data-open="add-rfc"
                data-tip="Agregar nuevo RFC">
          <span aria-hidden="true">＋</span>
          <span>Agregar RFC</span>
        </button>
      </div>
    </div>

    {{-- FILTROS --}}
    <div class="sat-rfcs-filters">
      <div class="sat-rfcs-filters-left">
        <input type="text"
               id="rfcFilterSearch"
               class="sat-rfcs-filter-input"
               placeholder="Buscar RFC / razón social…"
               aria-label="Buscar RFC / razón social">
      </div>

      <div class="sat-rfcs-filters-right">
        <select id="rfcFilterStatus"
                class="sat-rfcs-filter-select"
                aria-label="Filtrar por estatus">
          <option value="">Todos</option>
          <option value="ok">Validados</option>
          <option value="warn">Por validar</option>
        </select>

        <select id="rfcFilterCsd"
                class="sat-rfcs-filter-select"
                aria-label="Filtrar por CSD">
          <option value="">CSD: todos</option>
          <option value="1">Con CSD</option>
          <option value="0">Sin CSD</option>
        </select>
      </div>
    </div>

    {{-- TABLA --}}
    @include('cliente.sat._partials.rfcs.table', [
      'credList'    => $credList,
      'plan'        => $planCode,
      'isProPlan'   => $isProPlan,
      'rtCsdStore'  => $rtCsdStore ?? '#',
      'rtAlias'     => $rtAlias ?? '#',
      'rtRfcReg'    => $rtRfcReg ?? '#',
      'rtRfcDelete' => $rtRfcDelete,
    ])

  </div>
</div>

{{-- MODAL: REGISTRO EXTERNO --}}
@include('cliente.sat._partials.rfcs.invite_modal')

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/client/css/sat/sat-rfcs.css') }}">
@endpush

{{-- JS --}}
@include('cliente.sat._partials.rfcs.scripts')
