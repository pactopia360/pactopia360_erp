@php
    $row = $row ?? null;
    $cuentas = $cuentas ?? [];

    $selectedCuenta = old('cuenta_id', $row->cuenta_id ?? '');
    $direccionJson = old('direccion_json', isset($row->direccion) ? json_encode(json_decode((string) $row->direccion, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '');
    $certificadosJson = old('certificados_json', isset($row->certificados) ? json_encode(json_decode((string) $row->certificados, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '');
    $seriesJson = old('series_json', isset($row->series) ? json_encode(json_decode((string) $row->series, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '');

    $statusValue = old('status', $row->status ?? 'active');
@endphp

@push('styles')
<style>
  .bef-wrap{display:grid;gap:18px}
  .bef-card{
    border:1px solid var(--card-border);
    background:var(--card-bg);
    border-radius:24px;
    box-shadow:0 14px 34px rgba(15,23,42,.06)
  }
  .bef-head,.bef-body{padding:22px}
  .bef-head{
    border-bottom:1px solid var(--card-border);
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:18px;
    flex-wrap:wrap;
  }
  .bef-title{margin:0;font-size:30px;font-weight:900;color:var(--text);line-height:1.05}
  .bef-sub{margin:8px 0 0;color:var(--muted);max-width:860px}
  .bef-status{
    display:grid;
    gap:8px;
    min-width:280px;
    padding:14px 16px;
    border-radius:18px;
    border:1px solid var(--card-border);
    background:var(--panel-bg);
  }
  .bef-status-title{
    font-size:12px;
    text-transform:uppercase;
    font-weight:900;
    color:var(--muted);
    letter-spacing:.04em;
  }
  .bef-status-pill{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    width:max-content;
    min-height:30px;
    padding:4px 12px;
    border-radius:999px;
    font-size:12px;
    font-weight:900;
    border:1px solid transparent;
  }
  .bef-status-pill.ok{
    color:#166534;
    background:rgba(22,163,74,.10);
    border-color:rgba(22,163,74,.18);
  }
  .bef-status-pill.warn{
    color:#b45309;
    background:rgba(245,158,11,.12);
    border-color:rgba(245,158,11,.18);
  }
  .bef-status-list{
    margin:0;
    padding-left:18px;
    color:var(--muted);
    font-size:12px;
    line-height:1.45;
  }

  .bef-grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:14px}
  .bef-col-12{grid-column:span 12}
  .bef-col-8{grid-column:span 8}
  .bef-col-6{grid-column:span 6}
  .bef-col-4{grid-column:span 4}
  .bef-col-3{grid-column:span 3}

  .bef-field{display:grid;gap:8px}
  .bef-label-row{display:flex;align-items:center;justify-content:space-between;gap:10px}
  .bef-label{font-size:12px;text-transform:uppercase;font-weight:900;color:var(--muted)}
  .bef-required{
    display:inline-flex;align-items:center;justify-content:center;
    min-width:20px;height:20px;padding:0 6px;border-radius:999px;
    font-size:10px;font-weight:900;
    color:#b91c1c;background:rgba(220,38,38,.08);border:1px solid rgba(220,38,38,.14);
  }

  .bef-input,.bef-select,.bef-textarea{
    width:100%;
    min-height:46px;
    border:1px solid var(--card-border);
    border-radius:14px;
    background:var(--panel-bg);
    color:var(--text);
    padding:11px 13px;
    outline:none;
    transition:.18s ease;
  }
  .bef-textarea{
    min-height:180px;
    resize:vertical;
    font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;
    font-size:12px;
    line-height:1.55;
  }
  .bef-input:focus,.bef-select:focus,.bef-textarea:focus{
    border-color:color-mix(in oklab, var(--accent) 35%, var(--card-border));
    box-shadow:0 0 0 4px color-mix(in oklab, var(--accent) 10%, transparent);
  }

  .bef-help{
    font-size:12px;
    color:var(--muted);
    line-height:1.45;
  }

  .bef-divider{
    grid-column:span 12;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    margin-top:4px;
    padding-top:12px;
    border-top:1px dashed var(--card-border);
    flex-wrap:wrap;
  }
  .bef-divider-title{
    font-size:12px;
    text-transform:uppercase;
    font-weight:900;
    color:var(--muted);
    letter-spacing:.04em;
  }

  .bef-summary{
    grid-column:span 12;
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:12px;
    margin-top:4px;
  }
  .bef-mini{
    border:1px solid var(--card-border);
    background:var(--panel-bg);
    border-radius:16px;
    padding:12px 14px;
    display:grid;
    gap:4px;
  }
  .bef-mini-label{
    font-size:11px;
    text-transform:uppercase;
    color:var(--muted);
    font-weight:900;
  }
  .bef-mini-value{
    color:var(--text);
    font-weight:800;
    line-height:1.35;
    word-break:break-word;
  }

  .bef-actions{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    align-items:center;
  }
  .bef-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:44px;
    padding:10px 16px;
    border-radius:14px;
    border:1px solid var(--card-border);
    background:var(--panel-bg);
    color:var(--text);
    text-decoration:none;
    font-weight:800;
    cursor:pointer;
    transition:.18s ease;
  }
  .bef-btn:hover{transform:translateY(-1px)}
  .bef-btn-primary{
    background:linear-gradient(180deg,#103a51,#0f2f42);
    color:#fff;
    border-color:transparent
  }

  .bef-alert{
    padding:14px 16px;
    border-radius:16px;
    border:1px solid rgba(220,38,38,.18);
    background:rgba(220,38,38,.08);
    color:#991b1b
  }

  @media (max-width:1200px){
    .bef-summary{grid-template-columns:repeat(2,minmax(0,1fr))}
  }
  @media (max-width:1100px){
    .bef-col-8,.bef-col-6,.bef-col-4,.bef-col-3{grid-column:span 12}
  }
  @media (max-width:720px){
    .bef-summary{grid-template-columns:1fr}
  }
</style>
@endpush

<div class="bef-wrap">
  <section class="bef-card">
    <div class="bef-head">
      <div>
        <h1 class="bef-title">{{ $titleText }}</h1>
        <p class="bef-sub">
          Captura el emisor con su información fiscal, dirección, certificados y series para dejarlo listo para sandbox o producción en Facturotopía.
        </p>
      </div>

      <aside class="bef-status" id="syncStatusCard">
        <div class="bef-status-title">Estado de captura</div>
        <span class="bef-status-pill warn" id="syncStatusPill">Faltan datos</span>
        <ul class="bef-status-list" id="syncStatusList">
          <li>RFC</li>
          <li>Razón social</li>
          <li>Régimen fiscal</li>
          <li>Email</li>
          <li>Dirección con CP</li>
        </ul>
      </aside>
    </div>

    <div class="bef-body">
      @if($errors->any())
        <div class="bef-alert" style="margin-bottom:16px;">
          {{ $errors->first() }}
        </div>
      @endif

      <form method="POST" action="{{ $actionUrl }}" id="emisorForm">
        @csrf
        @if($mode === 'edit')
          @method('PUT')
        @endif

        <div class="bef-grid">
          <div class="bef-field bef-col-4">
            <label class="bef-label" for="cuenta_id">Cuenta</label>
            <select name="cuenta_id" id="cuenta_id" class="bef-select">
              <option value="">Sin cuenta</option>
              @foreach($cuentas as $c)
                <option
                  value="{{ $c['value'] }}"
                  data-meta='@json($c["meta"] ?? [])'
                  @selected((string) $selectedCuenta === (string) $c['value'])
                >
                  {{ $c['label'] }}
                </option>
              @endforeach
            </select>
          </div>

          <div class="bef-field bef-col-4">
            <div class="bef-label-row">
              <label class="bef-label" for="rfc">RFC</label>
              <span class="bef-required">Obligatorio</span>
            </div>
            <input type="text" name="rfc" id="rfc" class="bef-input" value="{{ old('rfc', $row->rfc ?? '') }}" maxlength="13" required>
          </div>

          <div class="bef-field bef-col-4">
            <div class="bef-label-row">
              <label class="bef-label" for="razon_social">Razón social</label>
              <span class="bef-required">Obligatorio</span>
            </div>
            <input type="text" name="razon_social" id="razon_social" class="bef-input" value="{{ old('razon_social', $row->razon_social ?? '') }}" required>
          </div>

          <div class="bef-field bef-col-4">
            <label class="bef-label" for="nombre_comercial">Nombre comercial</label>
            <input type="text" name="nombre_comercial" id="nombre_comercial" class="bef-input" value="{{ old('nombre_comercial', $row->nombre_comercial ?? '') }}">
          </div>

          <div class="bef-field bef-col-4">
            <div class="bef-label-row">
              <label class="bef-label" for="email">Email</label>
              <span class="bef-required">Obligatorio API</span>
            </div>
            <input type="email" name="email" id="email" class="bef-input" value="{{ old('email', $row->email ?? '') }}">
          </div>

          <div class="bef-field bef-col-4">
            <div class="bef-label-row">
              <label class="bef-label" for="regimen_fiscal">Régimen fiscal</label>
              <span class="bef-required">Obligatorio API</span>
            </div>
            <input type="text" name="regimen_fiscal" id="regimen_fiscal" class="bef-input" value="{{ old('regimen_fiscal', $row->regimen_fiscal ?? '') }}" maxlength="10" placeholder="601">
          </div>

          <div class="bef-divider">
            <div class="bef-divider-title">Configuración general</div>
          </div>

          <div class="bef-field bef-col-3">
            <label class="bef-label" for="grupo">Grupo</label>
            <input type="text" name="grupo" id="grupo" class="bef-input" value="{{ old('grupo', $row->grupo ?? 'pactopia360') }}" maxlength="50">
          </div>

          <div class="bef-field bef-col-3">
            <label class="bef-label" for="status">Status local</label>
            <select name="status" id="status" class="bef-select">
              <option value="active" @selected((string) $statusValue === 'active')>active</option>
              <option value="inactive" @selected((string) $statusValue === 'inactive')>inactive</option>
              <option value="pending" @selected((string) $statusValue === 'pending')>pending</option>
            </select>
          </div>

          <div class="bef-field bef-col-3">
            <label class="bef-label" for="ext_id">Ext ID</label>
            <input type="text" name="ext_id" id="ext_id" class="bef-input" value="{{ old('ext_id', $row->ext_id ?? '') }}" maxlength="36" placeholder="Se genera automático si lo dejas vacío">
          </div>

          <div class="bef-field bef-col-3">
            <label class="bef-label" for="csd_serie">CSD serie</label>
            <input type="text" name="csd_serie" id="csd_serie" class="bef-input" value="{{ old('csd_serie', $row->csd_serie ?? '') }}" maxlength="100">
          </div>

          <div class="bef-field bef-col-3">
            <label class="bef-label" for="csd_vigencia_hasta">CSD vigencia hasta</label>
            <input type="datetime-local" name="csd_vigencia_hasta" id="csd_vigencia_hasta" class="bef-input" value="{{ old('csd_vigencia_hasta', isset($row->csd_vigencia_hasta) && $row->csd_vigencia_hasta ? \Illuminate\Support\Carbon::parse($row->csd_vigencia_hasta)->format('Y-m-d\TH:i') : '') }}">
          </div>

          <div class="bef-divider">
            <div class="bef-divider-title">JSON de dirección</div>
          </div>

          <div class="bef-field bef-col-12">
            <label class="bef-label" for="direccion_json">Dirección JSON</label>
            <textarea name="direccion_json" id="direccion_json" class="bef-textarea" placeholder='{
  "cp": "01289",
  "direccion": "Calle 1, interior 2, colonia Centro",
  "ciudad": "Álvaro Obregón",
  "estado": "Ciudad de México"
}'>{{ $direccionJson }}</textarea>
            <div class="bef-help">
              Obligatorio para alta en Facturotopía: debe incluir al menos <code>cp</code>. Puedes usar el botón de cuenta para autollenar.
            </div>
          </div>

          <div class="bef-divider">
            <div class="bef-divider-title">JSON de certificados</div>
          </div>

          <div class="bef-field bef-col-12">
            <label class="bef-label" for="certificados_json">Certificados JSON</label>
            <textarea name="certificados_json" id="certificados_json" class="bef-textarea" placeholder='{
  "csd_key": "BASE64_KEY",
  "csd_cer": "BASE64_CER",
  "csd_password": "12345678a",
  "fiel_key": "BASE64_FIEL_KEY",
  "fiel_cer": "BASE64_FIEL_CER",
  "fiel_password": "12345678a"
}'>{{ $certificadosJson }}</textarea>
            <div class="bef-help">
              En alta API normalmente son obligatorios. En edición solo envíalos si deseas mantener o reemplazar esos datos.
            </div>
          </div>

          <div class="bef-divider">
            <div class="bef-divider-title">JSON de series</div>
          </div>

          <div class="bef-field bef-col-12">
            <label class="bef-label" for="series_json">Series JSON</label>
            <textarea name="series_json" id="series_json" class="bef-textarea" placeholder='[
  {
    "tipo": "I",
    "serie": "A",
    "folio": 1
  }
]'>{{ $seriesJson }}</textarea>
          </div>

          <div class="bef-summary">
            <div class="bef-mini">
              <div class="bef-mini-label">Cuenta ligada</div>
              <div class="bef-mini-value" id="summaryCuenta">{{ $selectedCuenta !== '' ? 'Sí' : 'No' }}</div>
            </div>
            <div class="bef-mini">
              <div class="bef-mini-label">Status</div>
              <div class="bef-mini-value" id="summaryStatus">{{ $statusValue ?: '—' }}</div>
            </div>
            <div class="bef-mini">
              <div class="bef-mini-label">Ext ID</div>
              <div class="bef-mini-value" id="summaryExtId">{{ old('ext_id', $row->ext_id ?? '') ?: 'Automático' }}</div>
            </div>
            <div class="bef-mini">
              <div class="bef-mini-label">Dirección</div>
              <div class="bef-mini-value" id="summaryDireccion">Pendiente</div>
            </div>
          </div>

          <div class="bef-field bef-col-12">
            <div class="bef-actions">
              <button type="submit" class="bef-btn bef-btn-primary">{{ $submitText }}</button>
              <a href="{{ route('admin.billing.invoicing.emisores.index') }}" class="bef-btn">Volver</a>
            </div>
          </div>
        </div>
      </form>
    </div>
  </section>
</div>

<script>
(() => {
  const form = document.getElementById('emisorForm');
  if (!form) return;

  const el = {
    cuenta: document.getElementById('cuenta_id'),
    rfc: document.getElementById('rfc'),
    razon: document.getElementById('razon_social'),
    nombre: document.getElementById('nombre_comercial'),
    email: document.getElementById('email'),
    regimen: document.getElementById('regimen_fiscal'),
    grupo: document.getElementById('grupo'),
    status: document.getElementById('status'),
    extId: document.getElementById('ext_id'),
    direccionJson: document.getElementById('direccion_json'),
    syncPill: document.getElementById('syncStatusPill'),
    syncList: document.getElementById('syncStatusList'),
    summaryCuenta: document.getElementById('summaryCuenta'),
    summaryStatus: document.getElementById('summaryStatus'),
    summaryExtId: document.getElementById('summaryExtId'),
    summaryDireccion: document.getElementById('summaryDireccion'),
  };

  const normalizeRfc = (value) => (value || '').toUpperCase().replace(/\s+/g, '').trim();

  const safeParse = (value) => {
    try {
      const parsed = JSON.parse(value || '{}');
      return typeof parsed === 'object' && parsed !== null ? parsed : {};
    } catch (e) {
      return null;
    }
  };

  const prettyJson = (value) => {
    try {
      return JSON.stringify(value, null, 2);
    } catch (e) {
      return '';
    }
  };

  const selectedCuentaMeta = () => {
    const opt = el.cuenta.options[el.cuenta.selectedIndex];
    if (!opt) return null;
    const raw = opt.getAttribute('data-meta');
    if (!raw) return null;
    try { return JSON.parse(raw); } catch (e) { return null; }
  };

  const setIfEmpty = (input, value) => {
    const current = (input.value || '').trim();
    const next = (value || '').toString().trim();
    if (current === '' && next !== '') input.value = next;
  };

  const autofillFromCuenta = () => {
    const meta = selectedCuentaMeta();
    if (!meta) {
      refreshAll();
      return;
    }

    setIfEmpty(el.rfc, meta.rfc);
    setIfEmpty(el.razon, meta.razon_social);
    setIfEmpty(el.nombre, meta.nombre_comercial);
    setIfEmpty(el.email, meta.email);
    setIfEmpty(el.regimen, meta.regimen_fiscal);

    const currentDireccion = safeParse(el.direccionJson.value);
    const isDireccionEmpty = !currentDireccion || Object.keys(currentDireccion).length === 0;

    if (isDireccionEmpty) {
      el.direccionJson.value = prettyJson({
        cp: meta.cp || '',
        direccion: [meta.calle || '', meta.no_ext || '', meta.no_int || '', meta.colonia || ''].filter(Boolean).join(', '),
        ciudad: meta.municipio || '',
        estado: meta.estado || '',
        pais: meta.pais || 'MEX',
      });
    }

    refreshAll();
  };

  const updateSummary = () => {
    el.summaryCuenta.textContent = el.cuenta.value ? 'Sí' : 'No';
    el.summaryStatus.textContent = el.status.value || '—';
    el.summaryExtId.textContent = (el.extId.value || '').trim() !== '' ? el.extId.value.trim() : 'Automático';

    const direccion = safeParse(el.direccionJson.value);
    if (direccion && (direccion.cp || direccion.direccion || direccion.ciudad || direccion.estado)) {
      el.summaryDireccion.textContent = [
        direccion.cp ? 'CP ' + direccion.cp : '',
        direccion.ciudad || '',
        direccion.estado || ''
      ].filter(Boolean).join(' · ');
    } else {
      el.summaryDireccion.textContent = 'Pendiente';
    }
  };

  const updateSyncState = () => {
    const missing = [];

    if (normalizeRfc(el.rfc.value) === '') missing.push('RFC');
    if ((el.razon.value || '').trim() === '') missing.push('Razón social');
    if ((el.regimen.value || '').trim() === '') missing.push('Régimen fiscal');
    if ((el.email.value || '').trim() === '') missing.push('Email');

    const direccion = safeParse(el.direccionJson.value);
    if (!direccion) {
      missing.push('Dirección JSON válida');
    } else if (!(direccion.cp || '').toString().trim()) {
      missing.push('Dirección con CP');
    }

    if (missing.length === 0) {
      el.syncPill.textContent = 'Listo para sincronizar';
      el.syncPill.classList.remove('warn');
      el.syncPill.classList.add('ok');
      el.syncList.innerHTML = '<li>Los datos mínimos requeridos para Facturotopía están completos.</li>';
    } else {
      el.syncPill.textContent = 'Faltan datos';
      el.syncPill.classList.remove('ok');
      el.syncPill.classList.add('warn');
      el.syncList.innerHTML = missing.map(item => `<li>${item}</li>`).join('');
    }
  };

  const refreshAll = () => {
    updateSummary();
    updateSyncState();
  };

  el.cuenta?.addEventListener('change', autofillFromCuenta);
  el.rfc?.addEventListener('input', refreshAll);
  el.razon?.addEventListener('input', refreshAll);
  el.email?.addEventListener('input', refreshAll);
  el.regimen?.addEventListener('input', refreshAll);
  el.status?.addEventListener('change', refreshAll);
  el.extId?.addEventListener('input', refreshAll);
  el.direccionJson?.addEventListener('input', refreshAll);

  refreshAll();
})();
</script>