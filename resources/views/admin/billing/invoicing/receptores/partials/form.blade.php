@php
  $row = $row ?? null;
  $cuentas = $cuentas ?? [];
  $catalogos = $catalogos ?? [];

  $regimenes = $catalogos['regimenes'] ?? [];
  $usosCfdi = $catalogos['usos_cfdi'] ?? [];
  $formasPago = $catalogos['formas_pago'] ?? [];
  $metodosPago = $catalogos['metodos_pago'] ?? [];

  $selectedCuenta = old('cuenta_id', $row->cuenta_id ?? '');
  $selectedRfc = old('rfc', $row->rfc ?? '');
@endphp

@push('styles')
<style>
  .rx-wrap{display:grid;gap:18px}
  .rx-card{
    border:1px solid var(--card-border);
    background:linear-gradient(180deg,color-mix(in oklab,var(--card-bg) 96%, white 4%),var(--card-bg));
    border-radius:28px;
    box-shadow:0 20px 50px rgba(15,23,42,.07);
    overflow:hidden;
  }
  .rx-head{
    position:relative;
    padding:28px 28px 22px;
    border-bottom:1px solid var(--card-border);
    background:
      radial-gradient(circle at top right, rgba(59,130,246,.12), transparent 28%),
      radial-gradient(circle at left top, rgba(168,85,247,.10), transparent 24%);
  }
  .rx-kicker{
    display:inline-flex;align-items:center;gap:8px;
    padding:7px 12px;border-radius:999px;
    background:rgba(59,130,246,.10);color:#1d4ed8;
    font-size:12px;font-weight:900;letter-spacing:.03em;text-transform:uppercase;
    border:1px solid rgba(59,130,246,.18);
  }
  .rx-title{
    margin:12px 0 6px;
    font-size:38px;
    line-height:1;
    font-weight:900;
    color:var(--text);
  }
  .rx-sub{
    margin:0;
    max-width:840px;
    color:var(--muted);
    font-size:15px;
    line-height:1.55;
  }

  .rx-body{padding:26px 28px 28px}
  .rx-alert{
    margin-bottom:18px;
    padding:14px 16px;
    border-radius:16px;
    border:1px solid rgba(220,38,38,.18);
    background:rgba(220,38,38,.08);
    color:#991b1b;
    font-weight:700;
  }

  .rx-grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:16px}
  .rx-col-12{grid-column:span 12}
  .rx-col-8{grid-column:span 8}
  .rx-col-6{grid-column:span 6}
  .rx-col-4{grid-column:span 4}
  .rx-col-3{grid-column:span 3}
  .rx-col-2{grid-column:span 2}

  .rx-box{
    grid-column:span 12;
    border:1px solid var(--card-border);
    background:color-mix(in oklab,var(--panel-bg) 92%, white 8%);
    border-radius:22px;
    padding:18px;
    display:grid;
    gap:16px;
  }
  .rx-box-head{
    display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;
    padding-bottom:10px;border-bottom:1px dashed var(--card-border);
  }
  .rx-box-title{
    margin:0;font-size:15px;font-weight:900;color:var(--text);
  }
  .rx-box-sub{
    margin:4px 0 0;color:var(--muted);font-size:13px;
  }

  .rx-field{display:grid;gap:8px}
  .rx-label{
    font-size:12px;
    text-transform:uppercase;
    letter-spacing:.04em;
    font-weight:900;
    color:var(--muted);
  }
  .rx-label .req{color:#dc2626}
  .rx-input,.rx-select{
    width:100%;
    min-height:50px;
    border:1px solid color-mix(in oklab,var(--card-border) 88%, #cbd5e1 12%);
    border-radius:16px;
    background:var(--panel-bg);
    color:var(--text);
    padding:12px 14px;
    outline:none;
    transition:.18s ease;
    box-shadow:inset 0 1px 0 rgba(255,255,255,.03);
  }
  .rx-input:focus,.rx-select:focus{
    border-color:color-mix(in oklab, var(--accent) 45%, var(--card-border));
    box-shadow:
      0 0 0 4px color-mix(in oklab, var(--accent) 12%, transparent),
      0 12px 24px rgba(15,23,42,.05);
  }
  .rx-help{
    font-size:12px;
    color:var(--muted);
    line-height:1.45;
  }

  .rx-badges{display:flex;gap:8px;flex-wrap:wrap}
  .rx-badge{
    display:inline-flex;align-items:center;justify-content:center;
    min-height:30px;padding:5px 10px;border-radius:999px;
    font-size:12px;font-weight:900;border:1px solid transparent;
  }
  .rx-badge-soft{
    background:rgba(15,23,42,.06);
    color:var(--text);
    border-color:var(--card-border);
  }
  .rx-badge-ok{
    background:rgba(22,163,74,.10);
    color:#166534;
    border-color:rgba(22,163,74,.18);
  }
  .rx-badge-warn{
    background:rgba(245,158,11,.10);
    color:#92400e;
    border-color:rgba(245,158,11,.20);
  }

  .rx-summary{
    display:grid;
    gap:10px;
    padding:16px;
    border-radius:18px;
    border:1px dashed var(--card-border);
    background:rgba(59,130,246,.05);
  }
  .rx-summary-title{
    margin:0;
    font-size:13px;
    font-weight:900;
    color:var(--text);
    text-transform:uppercase;
    letter-spacing:.04em;
  }
  .rx-summary-grid{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:12px;
  }
  .rx-summary-item{
    display:grid;gap:4px;
    padding:10px 12px;border-radius:14px;
    background:var(--panel-bg);
    border:1px solid var(--card-border);
  }
  .rx-summary-k{font-size:11px;color:var(--muted);text-transform:uppercase;font-weight:900}
  .rx-summary-v{font-size:13px;color:var(--text);font-weight:800;word-break:break-word}

  .rx-actions{
    display:flex;gap:10px;flex-wrap:wrap;align-items:center;
    padding-top:6px;
  }
  .rx-btn{
    display:inline-flex;align-items:center;justify-content:center;gap:8px;
    min-height:46px;padding:11px 16px;border-radius:14px;
    border:1px solid var(--card-border);
    background:var(--panel-bg);color:var(--text);
    text-decoration:none;font-weight:900;cursor:pointer;transition:.18s ease;
  }
  .rx-btn:hover{transform:translateY(-1px)}
  .rx-btn-primary{
    background:linear-gradient(180deg,#103a51,#0f2f42);
    color:#fff;border-color:transparent;
    box-shadow:0 18px 28px rgba(16,58,81,.24);
  }

  @media (max-width:1200px){
    .rx-summary-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
  }
  @media (max-width:980px){
    .rx-col-8,.rx-col-6,.rx-col-4,.rx-col-3,.rx-col-2{grid-column:span 12}
    .rx-title{font-size:32px}
  }
  @media (max-width:640px){
    .rx-head,.rx-body{padding-left:18px;padding-right:18px}
    .rx-summary-grid{grid-template-columns:1fr}
  }
</style>
@endpush

@section('content')
<div class="rx-wrap">
  <section class="rx-card">
    <div class="rx-head">
      <span class="rx-kicker">Facturación · Receptor</span>
      <h1 class="rx-title">{{ $titleText }}</h1>
      <p class="rx-sub">
        Captura completa del receptor fiscal para CFDI 4.0. El formulario usa catálogos SAT reales, filtra opciones compatibles y permite autollenar datos al seleccionar una cuenta.
      </p>
    </div>

    <div class="rx-body">
      @if($errors->any())
        <div class="rx-alert">
          {{ $errors->first() }}
        </div>
      @endif

      <form method="POST" action="{{ $actionUrl }}" id="receptorForm">
        @csrf
        @if($mode === 'edit')
          @method('PUT')
        @endif

        <div class="rx-grid">
          <section class="rx-box">
            <div class="rx-box-head">
              <div>
                <h2 class="rx-box-title">Vinculación y perfil</h2>
                <p class="rx-box-sub">Selecciona una cuenta para autollenar y después ajusta solo lo necesario.</p>
              </div>

              <div class="rx-badges">
                <span class="rx-badge rx-badge-ok">Sincronización automática</span>
                <span class="rx-badge rx-badge-soft">Facturotopia</span>
              </div>
            </div>

            <div class="rx-grid">
              <div class="rx-field rx-col-4">
                <label class="rx-label">Cuenta</label>
                <select name="cuenta_id" id="cuenta_id" class="rx-select">
                  <option value="">Sin cuenta</option>
                  @foreach($cuentas as $c)
                    <option
                      value="{{ $c['value'] }}"
                      data-rfc="{{ e($c['rfc'] ?? '') }}"
                      data-razon-social="{{ e($c['razon_social'] ?? '') }}"
                      data-nombre-comercial="{{ e($c['nombre_comercial'] ?? '') }}"
                      data-email="{{ e($c['email'] ?? '') }}"
                      data-telefono="{{ e($c['telefono'] ?? '') }}"
                      data-uso-cfdi="{{ e($c['uso_cfdi'] ?? '') }}"
                      data-regimen-fiscal="{{ e($c['regimen_fiscal'] ?? '') }}"
                      data-codigo-postal="{{ e($c['codigo_postal'] ?? '') }}"
                      data-forma-pago="{{ e($c['forma_pago'] ?? '') }}"
                      data-metodo-pago="{{ e($c['metodo_pago'] ?? '') }}"
                      data-pais="{{ e($c['pais'] ?? 'MEX') }}"
                      data-estado="{{ e($c['estado'] ?? '') }}"
                      data-municipio="{{ e($c['municipio'] ?? '') }}"
                      data-colonia="{{ e($c['colonia'] ?? '') }}"
                      data-calle="{{ e($c['calle'] ?? '') }}"
                      data-no-ext="{{ e($c['no_ext'] ?? '') }}"
                      data-no-int="{{ e($c['no_int'] ?? '') }}"
                      @selected((string) $selectedCuenta === (string) $c['value'])
                    >
                      {{ $c['label'] }}
                    </option>
                  @endforeach
                </select>
                <div class="rx-help">Opcional, pero recomendado para traer los datos fiscales del cliente.</div>
              </div>

              <div class="rx-field rx-col-4">
                <label class="rx-label">RFC <span class="req">*</span></label>
                <input
                  type="text"
                  name="rfc"
                  id="rfc"
                  class="rx-input"
                  value="{{ old('rfc', $row->rfc ?? '') }}"
                  maxlength="13"
                  required
                >
                <div class="rx-help">Al capturarlo se detecta automáticamente si es persona física o moral.</div>
              </div>

              <div class="rx-field rx-col-4">
                <label class="rx-label">Razón social <span class="req">*</span></label>
                <input
                  type="text"
                  name="razon_social"
                  id="razon_social"
                  class="rx-input"
                  value="{{ old('razon_social', $row->razon_social ?? '') }}"
                  required
                >
              </div>

              <div class="rx-field rx-col-4">
                <label class="rx-label">Nombre comercial</label>
                <input
                  type="text"
                  name="nombre_comercial"
                  id="nombre_comercial"
                  class="rx-input"
                  value="{{ old('nombre_comercial', $row->nombre_comercial ?? '') }}"
                >
              </div>

              <div class="rx-field rx-col-4">
                <label class="rx-label">Email</label>
                <input
                  type="email"
                  name="email"
                  id="email"
                  class="rx-input"
                  value="{{ old('email', $row->email ?? '') }}"
                >
              </div>

              <div class="rx-field rx-col-4">
                <label class="rx-label">Teléfono</label>
                <input
                  type="text"
                  name="telefono"
                  id="telefono"
                  class="rx-input"
                  value="{{ old('telefono', $row->telefono ?? '') }}"
                >
              </div>
            </div>
          </section>

          <section class="rx-box">
            <div class="rx-box-head">
              <div>
                <h2 class="rx-box-title">Configuración fiscal CFDI 4.0</h2>
                <p class="rx-box-sub">Se filtran opciones válidas según el tipo de RFC y el régimen fiscal.</p>
              </div>

              <div class="rx-badges">
                <span class="rx-badge rx-badge-warn" id="badgePersona">Tipo RFC: por definir</span>
              </div>
            </div>

            <div class="rx-summary">
              <h3 class="rx-summary-title">Resumen de validación rápida</h3>

              <div class="rx-summary-grid">
                <div class="rx-summary-item">
                  <span class="rx-summary-k">Tipo RFC</span>
                  <span class="rx-summary-v" id="summaryTipo">—</span>
                </div>
                <div class="rx-summary-item">
                  <span class="rx-summary-k">Régimen</span>
                  <span class="rx-summary-v" id="summaryRegimen">—</span>
                </div>
                <div class="rx-summary-item">
                  <span class="rx-summary-k">Uso CFDI</span>
                  <span class="rx-summary-v" id="summaryUso">—</span>
                </div>
                <div class="rx-summary-item">
                  <span class="rx-summary-k">CP</span>
                  <span class="rx-summary-v" id="summaryCp">—</span>
                </div>
              </div>
            </div>

            <div class="rx-grid">
              <div class="rx-field rx-col-3">
                <label class="rx-label">Régimen fiscal <span class="req">*</span></label>
                <select name="regimen_fiscal" id="regimen_fiscal" class="rx-select">
                  <option value="">Selecciona régimen fiscal</option>
                  @foreach($regimenes as $item)
                    <option
                      value="{{ $item['clave'] }}"
                      data-aplica-fisica="{{ $item['aplica_fisica'] ? '1' : '0' }}"
                      data-aplica-moral="{{ $item['aplica_moral'] ? '1' : '0' }}"
                      @selected(old('regimen_fiscal', $row->regimen_fiscal ?? '') === $item['clave'])
                    >
                      {{ $item['clave'] }} · {{ $item['descripcion'] }}
                    </option>
                  @endforeach
                </select>
              </div>

              <div class="rx-field rx-col-3">
                <label class="rx-label">Uso CFDI <span class="req">*</span></label>
                <select name="uso_cfdi" id="uso_cfdi" class="rx-select">
                  <option value="">Selecciona uso CFDI</option>
                  @foreach($usosCfdi as $item)
                    <option
                      value="{{ $item['clave'] }}"
                      data-aplica-fisica="{{ $item['aplica_fisica'] ? '1' : '0' }}"
                      data-aplica-moral="{{ $item['aplica_moral'] ? '1' : '0' }}"
                      data-regimenes='@json($item["regimenes_permitidos"] ?? [])'
                      @selected(old('uso_cfdi', $row->uso_cfdi ?? 'G03') === $item['clave'])
                    >
                      {{ $item['clave'] }} · {{ $item['descripcion'] }}
                    </option>
                  @endforeach
                </select>
              </div>

              <div class="rx-field rx-col-3">
                <label class="rx-label">Forma de pago</label>
                <select name="forma_pago" id="forma_pago" class="rx-select">
                  <option value="">Selecciona forma de pago</option>
                  @foreach($formasPago as $item)
                    <option
                      value="{{ $item['clave'] }}"
                      @selected(old('forma_pago', $row->forma_pago ?? '') === $item['clave'])
                    >
                      {{ $item['clave'] }} · {{ $item['descripcion'] }}
                    </option>
                  @endforeach
                </select>
              </div>

              <div class="rx-field rx-col-3">
                <label class="rx-label">Método de pago</label>
                <select name="metodo_pago" id="metodo_pago" class="rx-select">
                  <option value="">Selecciona método de pago</option>
                  @foreach($metodosPago as $item)
                    <option
                      value="{{ $item['clave'] }}"
                      @selected(old('metodo_pago', $row->metodo_pago ?? '') === $item['clave'])
                    >
                      {{ $item['clave'] }} · {{ $item['descripcion'] }}
                    </option>
                  @endforeach
                </select>
              </div>
            </div>
          </section>

          <section class="rx-box">
            <div class="rx-box-head">
              <div>
                <h2 class="rx-box-title">Domicilio fiscal</h2>
                <p class="rx-box-sub">Estos campos ayudan a que el receptor quede listo para timbrado y sincronización remota.</p>
              </div>
            </div>

            <div class="rx-grid">
              <div class="rx-field rx-col-3">
                <label class="rx-label">Código postal <span class="req">*</span></label>
                <input
                  type="text"
                  name="codigo_postal"
                  id="codigo_postal"
                  class="rx-input"
                  value="{{ old('codigo_postal', $row->codigo_postal ?? '') }}"
                  maxlength="10"
                >
              </div>

              <div class="rx-field rx-col-3">
                <label class="rx-label">País</label>
                <input
                  type="text"
                  name="pais"
                  id="pais"
                  class="rx-input"
                  value="{{ old('pais', $row->pais ?? 'MEX') }}"
                  maxlength="3"
                >
                <div class="rx-help">Usa MEX para México.</div>
              </div>

              <div class="rx-field rx-col-3">
                <label class="rx-label">Estado</label>
                <input
                  type="text"
                  name="estado"
                  id="estado"
                  class="rx-input"
                  value="{{ old('estado', $row->estado ?? '') }}"
                >
              </div>

              <div class="rx-field rx-col-3">
                <label class="rx-label">Municipio / Ciudad</label>
                <input
                  type="text"
                  name="municipio"
                  id="municipio"
                  class="rx-input"
                  value="{{ old('municipio', $row->municipio ?? '') }}"
                >
              </div>

              <div class="rx-field rx-col-4">
                <label class="rx-label">Colonia</label>
                <input
                  type="text"
                  name="colonia"
                  id="colonia"
                  class="rx-input"
                  value="{{ old('colonia', $row->colonia ?? '') }}"
                >
              </div>

              <div class="rx-field rx-col-4">
                <label class="rx-label">Calle / Dirección</label>
                <input
                  type="text"
                  name="calle"
                  id="calle"
                  class="rx-input"
                  value="{{ old('calle', $row->calle ?? '') }}"
                >
              </div>

              <div class="rx-field rx-col-2">
                <label class="rx-label">No. exterior</label>
                <input
                  type="text"
                  name="no_ext"
                  id="no_ext"
                  class="rx-input"
                  value="{{ old('no_ext', $row->no_ext ?? '') }}"
                >
              </div>

              <div class="rx-field rx-col-2">
                <label class="rx-label">No. interior</label>
                <input
                  type="text"
                  name="no_int"
                  id="no_int"
                  class="rx-input"
                  value="{{ old('no_int', $row->no_int ?? '') }}"
                >
              </div>
            </div>
          </section>

          <div class="rx-col-12">
            <div class="rx-actions">
              <button type="submit" class="rx-btn rx-btn-primary">{{ $submitText }}</button>
              <a href="{{ route('admin.billing.invoicing.receptores.index') }}" class="rx-btn">Volver</a>
            </div>
          </div>
        </div>
      </form>
    </div>
  </section>
</div>

<script>
(function () {
  const cuenta = document.getElementById('cuenta_id');
  const rfc = document.getElementById('rfc');
  const razonSocial = document.getElementById('razon_social');
  const nombreComercial = document.getElementById('nombre_comercial');
  const email = document.getElementById('email');
  const telefono = document.getElementById('telefono');
  const regimen = document.getElementById('regimen_fiscal');
  const usoCfdi = document.getElementById('uso_cfdi');
  const formaPago = document.getElementById('forma_pago');
  const metodoPago = document.getElementById('metodo_pago');
  const codigoPostal = document.getElementById('codigo_postal');
  const pais = document.getElementById('pais');
  const estado = document.getElementById('estado');
  const municipio = document.getElementById('municipio');
  const colonia = document.getElementById('colonia');
  const calle = document.getElementById('calle');
  const noExt = document.getElementById('no_ext');
  const noInt = document.getElementById('no_int');

  const badgePersona = document.getElementById('badgePersona');
  const summaryTipo = document.getElementById('summaryTipo');
  const summaryRegimen = document.getElementById('summaryRegimen');
  const summaryUso = document.getElementById('summaryUso');
  const summaryCp = document.getElementById('summaryCp');

  function normalizeRfc(value) {
    return String(value || '').toUpperCase().replace(/\s+/g, '').trim();
  }

  function detectTipoRfc(value) {
    const clean = normalizeRfc(value);
    if (clean.length === 13) return 'fisica';
    if (clean.length === 12) return 'moral';
    return '';
  }

  function fillIfEmpty(el, value) {
    if (!el) return;
    if ((el.value || '').trim() !== '') return;
    el.value = value || '';
  }

  function optionText(select) {
    if (!select) return '—';
    const opt = select.options[select.selectedIndex];
    return opt ? opt.text.trim() : '—';
  }

  function refreshSummary() {
    const tipo = detectTipoRfc(rfc.value);
    let tipoLabel = 'Por definir';
    let badgeText = 'Tipo RFC: por definir';

    if (tipo === 'fisica') {
      tipoLabel = 'Persona física';
      badgeText = 'Tipo RFC: persona física';
    } else if (tipo === 'moral') {
      tipoLabel = 'Persona moral';
      badgeText = 'Tipo RFC: persona moral';
    }

    summaryTipo.textContent = tipoLabel;
    summaryRegimen.textContent = regimen.value ? optionText(regimen) : '—';
    summaryUso.textContent = usoCfdi.value ? optionText(usoCfdi) : '—';
    summaryCp.textContent = (codigoPostal.value || '').trim() || '—';
    badgePersona.textContent = badgeText;
  }

  function filterRegimenes() {
    const tipo = detectTipoRfc(rfc.value);

    Array.from(regimen.options).forEach((opt, index) => {
      if (index === 0) {
        opt.hidden = false;
        return;
      }

      const fisica = opt.dataset.aplicaFisica === '1';
      const moral = opt.dataset.aplicaMoral === '1';

      let visible = true;
      if (tipo === 'fisica') visible = fisica;
      if (tipo === 'moral') visible = moral;

      opt.hidden = !visible;
    });

    const selected = regimen.options[regimen.selectedIndex];
    if (selected && selected.hidden) {
      regimen.value = '';
    }
  }

  function filterUsosCfdi() {
    const tipo = detectTipoRfc(rfc.value);
    const reg = regimen.value || '';

    Array.from(usoCfdi.options).forEach((opt, index) => {
      if (index === 0) {
        opt.hidden = false;
        return;
      }

      const fisica = opt.dataset.aplicaFisica === '1';
      const moral = opt.dataset.aplicaMoral === '1';

      let visibleByTipo = true;
      if (tipo === 'fisica') visibleByTipo = fisica;
      if (tipo === 'moral') visibleByTipo = moral;

      let visibleByRegimen = true;
      try {
        const permitidos = JSON.parse(opt.dataset.regimenes || '[]');
        if (reg && Array.isArray(permitidos) && permitidos.length > 0) {
          visibleByRegimen = permitidos.includes(reg);
        }
      } catch (e) {
        visibleByRegimen = true;
      }

      opt.hidden = !(visibleByTipo && visibleByRegimen);
    });

    const selected = usoCfdi.options[usoCfdi.selectedIndex];
    if (selected && selected.hidden) {
      usoCfdi.value = '';
    }
  }

  function applyCuentaData(force) {
    const opt = cuenta.options[cuenta.selectedIndex];
    if (!opt || !opt.value) return;

    if (force) {
      rfc.value = opt.dataset.rfc || rfc.value;
      razonSocial.value = opt.dataset.razonSocial || razonSocial.value;
      nombreComercial.value = opt.dataset.nombreComercial || nombreComercial.value;
      email.value = opt.dataset.email || email.value;
      telefono.value = opt.dataset.telefono || telefono.value;
      regimen.value = opt.dataset.regimenFiscal || regimen.value;
      codigoPostal.value = opt.dataset.codigoPostal || codigoPostal.value;
      formaPago.value = opt.dataset.formaPago || formaPago.value;
      metodoPago.value = opt.dataset.metodoPago || metodoPago.value;
      pais.value = opt.dataset.pais || pais.value || 'MEX';
      estado.value = opt.dataset.estado || estado.value;
      municipio.value = opt.dataset.municipio || municipio.value;
      colonia.value = opt.dataset.colonia || colonia.value;
      calle.value = opt.dataset.calle || calle.value;
      noExt.value = opt.dataset.noExt || noExt.value;
      noInt.value = opt.dataset.noInt || noInt.value;
      usoCfdi.value = opt.dataset.usoCfdi || usoCfdi.value;
      return;
    }

    fillIfEmpty(rfc, opt.dataset.rfc || '');
    fillIfEmpty(razonSocial, opt.dataset.razonSocial || '');
    fillIfEmpty(nombreComercial, opt.dataset.nombreComercial || '');
    fillIfEmpty(email, opt.dataset.email || '');
    fillIfEmpty(telefono, opt.dataset.telefono || '');
    fillIfEmpty(regimen, opt.dataset.regimenFiscal || '');
    fillIfEmpty(codigoPostal, opt.dataset.codigoPostal || '');
    fillIfEmpty(formaPago, opt.dataset.formaPago || '');
    fillIfEmpty(metodoPago, opt.dataset.metodoPago || '');
    fillIfEmpty(pais, opt.dataset.pais || 'MEX');
    fillIfEmpty(estado, opt.dataset.estado || '');
    fillIfEmpty(municipio, opt.dataset.municipio || '');
    fillIfEmpty(colonia, opt.dataset.colonia || '');
    fillIfEmpty(calle, opt.dataset.calle || '');
    fillIfEmpty(noExt, opt.dataset.noExt || '');
    fillIfEmpty(noInt, opt.dataset.noInt || '');
    fillIfEmpty(usoCfdi, opt.dataset.usoCfdi || '');
  }

  cuenta?.addEventListener('change', function () {
    applyCuentaData(true);
    filterRegimenes();
    filterUsosCfdi();
    refreshSummary();
  });

  rfc?.addEventListener('input', function () {
    this.value = normalizeRfc(this.value);
    filterRegimenes();
    filterUsosCfdi();
    refreshSummary();
  });

  regimen?.addEventListener('change', function () {
    filterUsosCfdi();
    refreshSummary();
  });

  usoCfdi?.addEventListener('change', refreshSummary);
  codigoPostal?.addEventListener('input', refreshSummary);

  applyCuentaData(false);
  filterRegimenes();
  filterUsosCfdi();
  refreshSummary();
})();
</script>
@endsection