{{-- resources/views/layouts/partials/solicitar.blade.php --}}
<div class="card sat-card" id="cardSolicitar" data-shot="area">
  <div class="card-header d-flex align-items-center justify-content-between gap-2">
    <div class="d-flex align-items-center gap-2">
      <span class="me-1">2) Solicitar paquetes (lista)</span>
      <span class="badge text-bg-light border">CFDI 4.0</span>
    </div>

    {{-- Toolbar de captura de imagen (PNG/JPG) para esta tarjeta --}}
    <div class="d-flex align-items-center gap-1" role="group" aria-label="Exportar captura">
      <button type="button"
              class="btn btn-sm btn-outline-secondary"
              title="Exportar esta sección como PNG"
              data-shot="png"
              data-shot-target="#cardSolicitar"
              data-no-pjax>
        🖼️ PNG
      </button>
      <button type="button"
              class="btn btn-sm btn-outline-secondary"
              title="Exportar esta sección como JPG"
              data-shot="jpg"
              data-shot-target="#cardSolicitar"
              data-no-pjax>
        JPG
      </button>
    </div>
  </div>

  <div class="card-body">
    <form method="post"
          action="{{ route('cliente.sat.request') }}"
          id="formRequest"
          class="row g-3"
          autocomplete="off">
      @csrf

      <div class="col-md-3">
        <label for="rq_rfc" class="form-label">RFC</label>
        <input type="text"
               name="rfc"
               id="rq_rfc"
               class="form-control mono"
               maxlength="13"
               inputmode="latin"
               placeholder="XAXX010101000"
               value="{{ old('rfc', $cred->rfc ?? '') }}"
               required>
        <div class="form-text">Usa el RFC del contribuyente (mayúsculas).</div>
      </div>

      <div class="col-md-3">
        <label for="rq_from" class="form-label">Desde</label>
        <input type="date"
               name="date_from"
               id="rq_from"
               class="form-control"
               required>
      </div>

      <div class="col-md-3">
        <label for="rq_to" class="form-label">Hasta</label>
        <input type="date"
               name="date_to"
               id="rq_to"
               class="form-control"
               required>
      </div>

      <div class="col-md-3">
        <label for="rq_tipo" class="form-label">Tipo</label>
        <select name="tipo" id="rq_tipo" class="form-select">
          <option value="recibidos">Recibidos</option>
          <option value="emitidos">Emitidos</option>
        </select>
      </div>

      {{-- Rangos rápidos --}}
      <div class="col-12 d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-outline-secondary btn-chip" data-range="hoy">Hoy</button>
        <button type="button" class="btn btn-outline-secondary btn-chip" data-range="7d">Últimos 7 días</button>
        <button type="button" class="btn btn-outline-secondary btn-chip" data-range="mes">Mes actual</button>
        <button type="button" class="btn btn-outline-secondary btn-chip" data-range="anio">Año actual</button>
        <button type="button" class="btn btn-outline-secondary btn-chip" data-range="2022">Año 2022</button>
      </div>

      <div class="col-12 d-flex align-items-center gap-2">
        <button class="btn btn-secondary" id="btnRequest">
          Solicitar
        </button>
        <span class="small-muted">
          El SAT puede tardar; cada lote listo tendrá un
          <span class="mono">package_id</span>.
        </span>
      </div>
    </form>
  </div>
</div>

{{-- Estilo sutil para chips/toolbar (scope local) --}}
<style>
  #cardSolicitar .btn-chip{ --bs-btn-padding-y:.35rem; --bs-btn-padding-x:.65rem; --bs-btn-font-weight:600 }
  #cardSolicitar .card-header{ background: var(--sub, #f8fafc) }
  html.theme-dark #cardSolicitar .card-header{ background: color-mix(in oklab, #fff 8%, transparent) }
</style>
