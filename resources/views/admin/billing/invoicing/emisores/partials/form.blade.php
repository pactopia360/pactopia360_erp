@php
    $row = $row ?? null;
    $cuentas = $cuentas ?? [];

    $decode = function ($value, $fallback = []) {
        if (is_array($value)) return $value;
        if (is_object($value)) return (array) $value;
        if (!is_string($value) || trim($value) === '') return $fallback;

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : $fallback;
        } catch (\Throwable $e) {
            return $fallback;
        }
    };

    $selectedCuenta = old('cuenta_id', $row->cuenta_id ?? '');

    $direccionArr = $decode(old('direccion_json', $row->direccion ?? null), []);
    $certArr = $decode(old('certificados_json', $row->certificados ?? null), []);
    $seriesArr = $decode(old('series_json', $row->series ?? null), []);

    if (empty($seriesArr)) {
        $seriesArr = [
            ['tipo' => 'I', 'serie' => 'A', 'folio' => 1],
        ];
    }

    $direccionJson = old('direccion_json', json_encode($direccionArr, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $certificadosJson = old('certificados_json', json_encode($certArr, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $seriesJson = old('series_json', json_encode($seriesArr, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    $statusValue = old('status', $row->status ?? 'active');

    $csdVigencia = old('csd_vigencia_hasta', isset($row->csd_vigencia_hasta) && $row->csd_vigencia_hasta
        ? \Illuminate\Support\Carbon::parse($row->csd_vigencia_hasta)->format('Y-m-d\TH:i')
        : ''
    );
@endphp

@push('styles')
<style>
  .bef-wrap{display:grid;gap:18px}
  .bef-card{border:1px solid var(--card-border);background:var(--card-bg);border-radius:24px;box-shadow:0 14px 34px rgba(15,23,42,.06);overflow:hidden}
  .bef-head,.bef-body{padding:22px}
  .bef-head{border-bottom:1px solid var(--card-border);display:flex;align-items:flex-start;justify-content:space-between;gap:18px;flex-wrap:wrap}
  .bef-title{margin:0;font-size:30px;font-weight:900;color:var(--text);line-height:1.05}
  .bef-sub{margin:8px 0 0;color:var(--muted);max-width:920px}
  .bef-status{display:grid;gap:8px;min-width:280px;padding:14px 16px;border-radius:18px;border:1px solid var(--card-border);background:var(--panel-bg)}
  .bef-status-title{font-size:12px;text-transform:uppercase;font-weight:900;color:var(--muted);letter-spacing:.04em}
  .bef-status-pill{display:inline-flex;align-items:center;justify-content:center;width:max-content;min-height:30px;padding:4px 12px;border-radius:999px;font-size:12px;font-weight:900;border:1px solid transparent}
  .bef-status-pill.ok{color:#166534;background:rgba(22,163,74,.10);border-color:rgba(22,163,74,.18)}
  .bef-status-pill.warn{color:#b45309;background:rgba(245,158,11,.12);border-color:rgba(245,158,11,.18)}
  .bef-status-pill.bad{color:#991b1b;background:rgba(220,38,38,.10);border-color:rgba(220,38,38,.18)}
  .bef-status-list{margin:0;padding-left:18px;color:var(--muted);font-size:12px;line-height:1.45}
  .bef-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px}
  .bef-tab{border:1px solid var(--card-border);background:var(--panel-bg);color:var(--text);border-radius:999px;padding:10px 14px;font-weight:900;cursor:pointer}
  .bef-tab.active{background:#103a51;color:#fff;border-color:#103a51}
  .bef-panel{display:none}
  .bef-panel.active{display:block}
  .bef-grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:14px}
  .bef-col-12{grid-column:span 12}.bef-col-8{grid-column:span 8}.bef-col-6{grid-column:span 6}.bef-col-4{grid-column:span 4}.bef-col-3{grid-column:span 3}
  .bef-field{display:grid;gap:8px}
  .bef-label-row{display:flex;align-items:center;justify-content:space-between;gap:10px}
  .bef-label{font-size:12px;text-transform:uppercase;font-weight:900;color:var(--muted)}
  .bef-required{display:inline-flex;align-items:center;justify-content:center;min-width:20px;height:20px;padding:0 6px;border-radius:999px;font-size:10px;font-weight:900;color:#b91c1c;background:rgba(220,38,38,.08);border:1px solid rgba(220,38,38,.14)}
  .bef-input,.bef-select,.bef-textarea{width:100%;min-height:46px;border:1px solid var(--card-border);border-radius:14px;background:var(--panel-bg);color:var(--text);padding:11px 13px;outline:none;transition:.18s ease}
  .bef-textarea{min-height:150px;resize:vertical;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;font-size:12px;line-height:1.55}
  .bef-input:focus,.bef-select:focus,.bef-textarea:focus{border-color:color-mix(in oklab,var(--accent) 35%,var(--card-border));box-shadow:0 0 0 4px color-mix(in oklab,var(--accent) 10%,transparent)}
  .bef-help{font-size:12px;color:var(--muted);line-height:1.45}
  .bef-divider{grid-column:span 12;display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:4px;padding-top:12px;border-top:1px dashed var(--card-border);flex-wrap:wrap}
  .bef-divider-title{font-size:12px;text-transform:uppercase;font-weight:900;color:var(--muted);letter-spacing:.04em}
  .bef-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
  .bef-btn{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:10px 16px;border-radius:14px;border:1px solid var(--card-border);background:var(--panel-bg);color:var(--text);text-decoration:none;font-weight:800;cursor:pointer;transition:.18s ease}
  .bef-btn:hover{transform:translateY(-1px)}
  .bef-btn-primary{background:linear-gradient(180deg,#103a51,#0f2f42);color:#fff;border-color:transparent}
  .bef-btn-soft{background:#eff6ff;color:#15356c;border-color:#bfdbfe}
  .bef-alert{padding:14px 16px;border-radius:16px;border:1px solid rgba(220,38,38,.18);background:rgba(220,38,38,.08);color:#991b1b}
  .bef-ai{border:1px solid #bfdbfe;background:#eff6ff;border-radius:18px;padding:16px;display:grid;gap:10px}
  .bef-ai-title{font-weight:950;color:#15356c;font-size:16px}
  .bef-ai-text{color:#334155;line-height:1.55;margin:0}
  .bef-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-top:18px}
  .bef-mini{border:1px solid var(--card-border);background:var(--panel-bg);border-radius:16px;padding:12px 14px;display:grid;gap:4px}
  .bef-mini-label{font-size:11px;text-transform:uppercase;color:var(--muted);font-weight:900}
  .bef-mini-value{color:var(--text);font-weight:800;line-height:1.35;word-break:break-word}
  .bef-hidden-json{display:none}
  .bef-file-note{font-size:11px;color:#64748b}
  @media (max-width:1200px){.bef-summary{grid-template-columns:repeat(2,minmax(0,1fr))}}
  @media (max-width:1100px){.bef-col-8,.bef-col-6,.bef-col-4,.bef-col-3{grid-column:span 12}}
  @media (max-width:720px){.bef-summary{grid-template-columns:1fr}}
</style>
@endpush

<div class="bef-wrap">
  <section class="bef-card">
    <div class="bef-head">
      <div>
        <h1 class="bef-title">{{ $titleText }}</h1>
        <p class="bef-sub">
          Alta completa de emisor para Pactopia360: datos fiscales, domicilio, CSD/FIEL, branding de PDF, correo, complementos y sincronización con Facturotopía.
        </p>
      </div>

      <aside class="bef-status">
        <div class="bef-status-title">Estado de captura</div>
        <span class="bef-status-pill warn" id="syncStatusPill">Faltan datos</span>
        <ul class="bef-status-list" id="syncStatusList"></ul>
      </aside>
    </div>

    <div class="bef-body">
      @if($errors->any())
        <div class="bef-alert" style="margin-bottom:16px;">
          {{ $errors->first() }}
        </div>
      @endif

      <form method="POST" action="{{ $actionUrl }}" id="emisorForm" enctype="multipart/form-data">
        @csrf
        @if($mode === 'edit')
          @method('PUT')
        @endif

        <textarea name="direccion_json" id="direccion_json" class="bef-hidden-json">{{ $direccionJson }}</textarea>
        <textarea name="certificados_json" id="certificados_json" class="bef-hidden-json">{{ $certificadosJson }}</textarea>
        <textarea name="series_json" id="series_json" class="bef-hidden-json">{{ $seriesJson }}</textarea>

        <div class="bef-tabs">
          <button type="button" class="bef-tab active" data-tab="fiscales">Datos fiscales</button>
          <button type="button" class="bef-tab" data-tab="domicilio">Domicilio</button>
          <button type="button" class="bef-tab" data-tab="certificados">CSD / FIEL</button>
          <button type="button" class="bef-tab" data-tab="pdf">PDF / marca</button>
          <button type="button" class="bef-tab" data-tab="correo">Correo</button>
          <button type="button" class="bef-tab" data-tab="complementos">Complementos</button>
          <button type="button" class="bef-tab" data-tab="auditoria">IA / auditoría</button>
        </div>

        <div class="bef-panel active" data-panel="fiscales">
          <div class="bef-grid">
            <div class="bef-field bef-col-4">
              <label class="bef-label" for="cuenta_id">Cuenta relacionada</label>
              <select name="cuenta_id" id="cuenta_id" class="bef-select">
                <option value="">Sin cuenta</option>
                @foreach($cuentas as $c)
                  <option value="{{ $c['value'] }}" data-meta='@json($c["meta"] ?? [])' @selected((string) $selectedCuenta === (string) $c['value'])>
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
              <label class="bef-label" for="regimen_fiscal">Régimen fiscal</label>
              <input type="text" name="regimen_fiscal" id="regimen_fiscal" class="bef-input" value="{{ old('regimen_fiscal', $row->regimen_fiscal ?? '') }}" maxlength="10" placeholder="601">
            </div>

            <div class="bef-field bef-col-4">
              <label class="bef-label" for="grupo">Grupo</label>
              <input type="text" name="grupo" id="grupo" class="bef-input" value="{{ old('grupo', $row->grupo ?? 'pactopia360') }}" maxlength="50">
            </div>

            <div class="bef-field bef-col-4">
              <label class="bef-label" for="status">Status local</label>
              <select name="status" id="status" class="bef-select">
                <option value="active" @selected((string) $statusValue === 'active')>active</option>
                <option value="inactive" @selected((string) $statusValue === 'inactive')>inactive</option>
                <option value="pending" @selected((string) $statusValue === 'pending')>pending</option>
              </select>
            </div>

            <div class="bef-field bef-col-4">
              <label class="bef-label" for="ext_id">Ext ID Facturotopía</label>
              <input type="text" name="ext_id" id="ext_id" class="bef-input" value="{{ old('ext_id', $row->ext_id ?? '') }}" maxlength="36" placeholder="Se genera automático si lo dejas vacío">
            </div>

            <div class="bef-field bef-col-4">
              <label class="bef-label" for="email">Email fiscal</label>
              <input type="email" name="email" id="email" class="bef-input" value="{{ old('email', $row->email ?? '') }}">
            </div>
          </div>
        </div>

        <div class="bef-panel" data-panel="domicilio">
          <div class="bef-grid">
            <div class="bef-field bef-col-3">
              <label class="bef-label" for="dir_cp">Código postal</label>
              <input type="text" id="dir_cp" class="bef-input" value="{{ $direccionArr['cp'] ?? '' }}">
            </div>
            <div class="bef-field bef-col-3">
              <label class="bef-label" for="dir_pais">País</label>
              <input type="text" id="dir_pais" class="bef-input" value="{{ $direccionArr['pais'] ?? 'MEX' }}">
            </div>
            <div class="bef-field bef-col-3">
              <label class="bef-label" for="dir_estado">Estado</label>
              <input type="text" id="dir_estado" class="bef-input" value="{{ $direccionArr['estado'] ?? '' }}">
            </div>
            <div class="bef-field bef-col-3">
              <label class="bef-label" for="dir_ciudad">Ciudad / municipio</label>
              <input type="text" id="dir_ciudad" class="bef-input" value="{{ $direccionArr['ciudad'] ?? ($direccionArr['municipio'] ?? '') }}">
            </div>
            <div class="bef-field bef-col-4">
              <label class="bef-label" for="dir_colonia">Colonia</label>
              <input type="text" id="dir_colonia" class="bef-input" value="{{ $direccionArr['colonia'] ?? '' }}">
            </div>
            <div class="bef-field bef-col-4">
              <label class="bef-label" for="dir_calle">Calle</label>
              <input type="text" id="dir_calle" class="bef-input" value="{{ $direccionArr['calle'] ?? '' }}">
            </div>
            <div class="bef-field bef-col-2">
              <label class="bef-label" for="dir_no_ext">No. ext</label>
              <input type="text" id="dir_no_ext" class="bef-input" value="{{ $direccionArr['no_ext'] ?? '' }}">
            </div>
            <div class="bef-field bef-col-2">
              <label class="bef-label" for="dir_no_int">No. int</label>
              <input type="text" id="dir_no_int" class="bef-input" value="{{ $direccionArr['no_int'] ?? '' }}">
            </div>
            <div class="bef-field bef-col-12">
              <label class="bef-label" for="dir_direccion">Dirección completa API</label>
              <input type="text" id="dir_direccion" class="bef-input" value="{{ $direccionArr['direccion'] ?? '' }}" placeholder="Calle, número, colonia">
            </div>
          </div>
        </div>

        <div class="bef-panel" data-panel="certificados">
          <div class="bef-grid">
            <div class="bef-field bef-col-3">
              <label class="bef-label" for="csd_serie">CSD serie</label>
              <input type="text" name="csd_serie" id="csd_serie" class="bef-input" value="{{ old('csd_serie', $row->csd_serie ?? '') }}" maxlength="100">
            </div>

            <div class="bef-field bef-col-3">
              <label class="bef-label" for="csd_vigencia_hasta">CSD vigencia hasta</label>
              <input type="datetime-local" name="csd_vigencia_hasta" id="csd_vigencia_hasta" class="bef-input" value="{{ $csdVigencia }}">
            </div>

            <div class="bef-field bef-col-3">
              <label class="bef-label" for="csd_password">Contraseña CSD</label>
              <input type="password" id="csd_password" class="bef-input" value="{{ $certArr['csd_password'] ?? '' }}" autocomplete="new-password">
            </div>

            <div class="bef-field bef-col-3">
              <label class="bef-label" for="fiel_password">Contraseña FIEL</label>
              <input type="password" id="fiel_password" class="bef-input" value="{{ $certArr['fiel_password'] ?? '' }}" autocomplete="new-password">
            </div>

            <div class="bef-field bef-col-6">
              <label class="bef-label" for="csd_cer_file">CSD .cer</label>
              <input type="file" name="csd_cer_file" id="csd_cer_file" class="bef-input" accept=".cer,.txt,.pem">
              <div class="bef-file-note">Se convierte a base64 y se guarda en certificados_json como csd_cer.</div>
            </div>

            <div class="bef-field bef-col-6">
              <label class="bef-label" for="csd_key_file">CSD .key</label>
              <input type="file" id="fiel_cer_file" class="bef-input" accept=".cer,.txt,.pem">
              <div class="bef-file-note">Se convierte a base64 y se guarda en certificados_json como csd_key.</div>
            </div>

            <div class="bef-field bef-col-6">
              <label class="bef-label" for="fiel_cer_file">FIEL .cer</label>
              <input type="file" name="fiel_cer_file" id="fiel_cer_file" class="bef-input" accept=".cer,.txt,.pem">
            </div>

            <div class="bef-field bef-col-6">
              <label class="bef-label" for="fiel_key_file">FIEL .key</label>
              <input type="file" name="fiel_key_file" id="fiel_key_file" class="bef-input" accept=".key,.txt,.pem">
            </div>
          </div>
        </div>

        <div class="bef-panel" data-panel="pdf">
          <div class="bef-grid">
            <div class="bef-field bef-col-4">
              <label class="bef-label" for="brand_logo_url">Logo URL</label>
              <input type="text" id="brand_logo_url" class="bef-input" value="{{ $certArr['brand']['logo_url'] ?? '' }}">
            </div>
            <div class="bef-field bef-col-4">
              <label class="bef-label" for="brand_primary">Color principal</label>
              <input type="text" id="brand_primary" class="bef-input" value="{{ $certArr['brand']['primary_color'] ?? '#103a51' }}">
            </div>
            <div class="bef-field bef-col-4">
              <label class="bef-label" for="brand_accent">Color acento</label>
              <input type="text" id="brand_accent" class="bef-input" value="{{ $certArr['brand']['accent_color'] ?? '#2563eb' }}">
            </div>
            <div class="bef-field bef-col-12">
              <label class="bef-label" for="brand_leyenda">Leyenda PDF</label>
              <textarea id="brand_leyenda" class="bef-textarea" style="min-height:100px;">{{ $certArr['brand']['leyenda_pdf'] ?? 'Documento generado por Pactopia360.' }}</textarea>
            </div>
          </div>
        </div>

        <div class="bef-panel" data-panel="correo">
          <div class="bef-grid">
            <div class="bef-field bef-col-6">
              <label class="bef-label" for="mail_from_name">Nombre remitente</label>
              <input type="text" id="mail_from_name" class="bef-input" value="{{ $certArr['mail']['from_name'] ?? 'Pactopia360 Facturación' }}">
            </div>
            <div class="bef-field bef-col-6">
              <label class="bef-label" for="mail_reply_to">Reply-to</label>
              <input type="email" id="mail_reply_to" class="bef-input" value="{{ $certArr['mail']['reply_to'] ?? '' }}">
            </div>
            <div class="bef-field bef-col-12">
              <label class="bef-label" for="mail_cc">CC / monitoreo</label>
              <input type="text" id="mail_cc" class="bef-input" value="{{ is_array($certArr['mail']['cc'] ?? null) ? implode(',', $certArr['mail']['cc']) : ($certArr['mail']['cc'] ?? '') }}" placeholder="correo1@dominio.com, correo2@dominio.com">
            </div>
          </div>
        </div>

        <div class="bef-panel" data-panel="complementos">
          <div class="bef-grid">
            <div class="bef-field bef-col-3">
              <label class="bef-label" for="serie_i">Serie ingreso</label>
              <input type="text" id="serie_i" class="bef-input" value="{{ $seriesArr[0]['serie'] ?? 'A' }}">
            </div>
            <div class="bef-field bef-col-3">
              <label class="bef-label" for="folio_i">Folio ingreso</label>
              <input type="number" id="folio_i" class="bef-input" value="{{ $seriesArr[0]['folio'] ?? 1 }}">
            </div>
            <div class="bef-field bef-col-3">
              <label class="bef-label" for="serie_p">Serie pagos</label>
              <input type="text" id="serie_p" class="bef-input" value="{{ $seriesArr[1]['serie'] ?? 'P' }}">
            </div>
            <div class="bef-field bef-col-3">
              <label class="bef-label" for="folio_p">Folio pagos</label>
              <input type="number" id="folio_p" class="bef-input" value="{{ $seriesArr[1]['folio'] ?? 1 }}">
            </div>
          </div>
        </div>

        <div class="bef-panel" data-panel="auditoria">
          <div class="bef-ai">
            <div class="bef-ai-title">Copiloto de emisor</div>
            <p class="bef-ai-text">
              Valida requisitos mínimos para registrar o actualizar el emisor: RFC, razón social, régimen, email, CP, certificados y series.
            </p>
            <div class="bef-actions">
              <button type="button" class="bef-btn bef-btn-soft" id="btnAutofillCuenta">Autollenar desde cuenta</button>
              <button type="button" class="bef-btn bef-btn-soft" id="btnPactopia">Preset Pactopia</button>
              <button type="button" class="bef-btn bef-btn-soft" id="btnBuildJson">Reconstruir JSON</button>
              <button type="button" class="bef-btn bef-btn-soft" id="btnValidate">Validar emisor</button>
            </div>
          </div>

          <div class="bef-summary">
            <div class="bef-mini">
              <div class="bef-mini-label">Cuenta ligada</div>
              <div class="bef-mini-value" id="summaryCuenta">—</div>
            </div>
            <div class="bef-mini">
              <div class="bef-mini-label">Status</div>
              <div class="bef-mini-value" id="summaryStatus">—</div>
            </div>
            <div class="bef-mini">
              <div class="bef-mini-label">Ext ID</div>
              <div class="bef-mini-value" id="summaryExtId">—</div>
            </div>
            <div class="bef-mini">
              <div class="bef-mini-label">Dirección</div>
              <div class="bef-mini-value" id="summaryDireccion">—</div>
            </div>
          </div>

          <div class="bef-field" style="margin-top:16px;">
            <label class="bef-label">Vista JSON técnica</label>
            <textarea id="jsonPreview" class="bef-textarea" readonly></textarea>
          </div>
        </div>

        <div class="bef-actions" style="margin-top:22px;">
          <button type="submit" class="bef-btn bef-btn-primary">{{ $submitText }}</button>
          <a href="{{ route('admin.billing.invoicing.emisores.index') }}" class="bef-btn">Volver</a>
        </div>
      </form>
    </div>
  </section>
</div>

<script>
(() => {
  const form = document.getElementById('emisorForm');
  if (!form) return;

  const $ = (id) => document.getElementById(id);

  const el = {
    cuenta: $('cuenta_id'),
    rfc: $('rfc'),
    razon: $('razon_social'),
    nombre: $('nombre_comercial'),
    email: $('email'),
    regimen: $('regimen_fiscal'),
    grupo: $('grupo'),
    status: $('status'),
    extId: $('ext_id'),
    direccionJson: $('direccion_json'),
    certificadosJson: $('certificados_json'),
    seriesJson: $('series_json'),
    syncPill: $('syncStatusPill'),
    syncList: $('syncStatusList'),
    summaryCuenta: $('summaryCuenta'),
    summaryStatus: $('summaryStatus'),
    summaryExtId: $('summaryExtId'),
    summaryDireccion: $('summaryDireccion'),
    jsonPreview: $('jsonPreview'),
  };

  const normalizeRfc = (value) => (value || '').toUpperCase().replace(/\s+/g, '').trim();

  const parseJson = (value, fallback = {}) => {
    try {
      const parsed = JSON.parse(value || '');
      return typeof parsed === 'object' && parsed !== null ? parsed : fallback;
    } catch (e) {
      return fallback;
    }
  };

  const pretty = (value) => JSON.stringify(value, null, 2);

  const selectedCuentaMeta = () => {
    const opt = el.cuenta?.options[el.cuenta.selectedIndex];
    if (!opt) return null;
    const raw = opt.getAttribute('data-meta');
    if (!raw) return null;
    try { return JSON.parse(raw); } catch (e) { return null; }
  };

  const setIfEmpty = (input, value) => {
    if (!input) return;
    const current = (input.value || '').trim();
    const next = (value || '').toString().trim();
    if (current === '' && next !== '') input.value = next;
  };

  const csvToArray = (value) => (value || '')
    .split(/[;,]+/)
    .map(v => v.trim())
    .filter(Boolean);

  const fileToBase64 = (file) => new Promise((resolve, reject) => {
    if (!file) return resolve('');
    const reader = new FileReader();
    reader.onload = () => {
      const raw = String(reader.result || '');
      resolve(raw.includes(',') ? raw.split(',').pop() : raw);
    };
    reader.onerror = reject;
    reader.readAsDataURL(file);
  });

  const fillFromCuenta = () => {
    const meta = selectedCuentaMeta();
    if (!meta) return;

    setIfEmpty(el.rfc, meta.rfc);
    setIfEmpty(el.razon, meta.razon_social);
    setIfEmpty(el.nombre, meta.nombre_comercial);
    setIfEmpty(el.email, meta.email);
    setIfEmpty(el.regimen, meta.regimen_fiscal);

    setIfEmpty($('dir_cp'), meta.cp);
    setIfEmpty($('dir_pais'), meta.pais || 'MEX');
    setIfEmpty($('dir_estado'), meta.estado);
    setIfEmpty($('dir_ciudad'), meta.municipio);
    setIfEmpty($('dir_colonia'), meta.colonia);
    setIfEmpty($('dir_calle'), meta.calle);
    setIfEmpty($('dir_no_ext'), meta.no_ext);
    setIfEmpty($('dir_no_int'), meta.no_int);

    buildJson();
  };

  const fillPactopia = () => {
    el.rfc.value = 'PAC251010CS1';
    el.razon.value = 'PACTOPIA S A P I DE CV';
    el.nombre.value = 'Pactopia360';
    el.regimen.value = el.regimen.value || '601';
    el.grupo.value = 'pactopia360';
    $('dir_pais').value = $('dir_pais').value || 'MEX';
    $('brand_primary').value = '#103a51';
    $('brand_accent').value = '#2563eb';
    buildJson();
  };

  const buildDireccion = () => {
    const calle = ($('dir_calle')?.value || '').trim();
    const noExt = ($('dir_no_ext')?.value || '').trim();
    const noInt = ($('dir_no_int')?.value || '').trim();
    const colonia = ($('dir_colonia')?.value || '').trim();

    const direccionManual = ($('dir_direccion')?.value || '').trim();
    const direccion = direccionManual || [calle, noExt, noInt, colonia].filter(Boolean).join(', ');

    return {
      cp: ($('dir_cp')?.value || '').trim(),
      pais: ($('dir_pais')?.value || 'MEX').trim(),
      estado: ($('dir_estado')?.value || '').trim(),
      ciudad: ($('dir_ciudad')?.value || '').trim(),
      municipio: ($('dir_ciudad')?.value || '').trim(),
      colonia,
      calle,
      no_ext: noExt,
      no_int: noInt,
      direccion,
    };
  };

  const buildCertificados = () => {
    const prev = parseJson(el.certificadosJson.value, {});
    return {
      ...prev,
      csd_password: ($('csd_password')?.value || '').trim(),
      fiel_password: ($('fiel_password')?.value || '').trim(),
      brand: {
        logo_url: ($('brand_logo_url')?.value || '').trim(),
        primary_color: ($('brand_primary')?.value || '#103a51').trim(),
        accent_color: ($('brand_accent')?.value || '#2563eb').trim(),
        leyenda_pdf: ($('brand_leyenda')?.value || '').trim(),
      },
      mail: {
        from_name: ($('mail_from_name')?.value || '').trim(),
        reply_to: ($('mail_reply_to')?.value || '').trim(),
        cc: csvToArray($('mail_cc')?.value || ''),
      },
    };
  };

  const buildSeries = () => [
    {
      tipo: 'I',
      serie: ($('serie_i')?.value || 'A').trim(),
      folio: parseInt($('folio_i')?.value || '1', 10) || 1,
    },
    {
      tipo: 'P',
      serie: ($('serie_p')?.value || 'P').trim(),
      folio: parseInt($('folio_p')?.value || '1', 10) || 1,
    },
  ];

  const buildJson = () => {
    el.rfc.value = normalizeRfc(el.rfc.value);

    const direccion = buildDireccion();
    const certificados = buildCertificados();
    const series = buildSeries();

    el.direccionJson.value = pretty(direccion);
    el.certificadosJson.value = pretty(certificados);
    el.seriesJson.value = pretty(series);

    refreshState();
  };

  const buildJsonWithFiles = async () => {
    buildJson();

    const cert = parseJson(el.certificadosJson.value, {});
    const fileMap = [
      ['csd_cer_file', 'csd_cer'],
      ['csd_key_file', 'csd_key'],
      ['fiel_cer_file', 'fiel_cer'],
      ['fiel_key_file', 'fiel_key'],
    ];

    for (const [inputId, key] of fileMap) {
      const input = $(inputId);
      const file = input?.files?.[0] || null;
      if (file) {
        cert[key] = await fileToBase64(file);
      }
    }

    el.certificadosJson.value = pretty(cert);
    refreshState();
  };

  const missingList = () => {
    const missing = [];
    const direccion = parseJson(el.direccionJson.value, {});
    const cert = parseJson(el.certificadosJson.value, {});

    if (normalizeRfc(el.rfc.value) === '') missing.push('RFC');
    if ((el.razon.value || '').trim() === '') missing.push('Razón social');
    if ((el.regimen.value || '').trim() === '') missing.push('Régimen fiscal');
    if ((el.email.value || '').trim() === '') missing.push('Email fiscal');
    if (!(direccion.cp || '').toString().trim()) missing.push('Código postal');

    if (!cert.csd_cer) missing.push('CSD .cer');
    if (!cert.csd_key) missing.push('CSD .key');
    if (!cert.csd_password) missing.push('Contraseña CSD');

    return missing;
  };

  const refreshState = () => {
    const direccion = parseJson(el.direccionJson.value, {});
    const missing = missingList();

    el.summaryCuenta.textContent = el.cuenta.value ? 'Sí' : 'No';
    el.summaryStatus.textContent = el.status.value || '—';
    el.summaryExtId.textContent = (el.extId.value || '').trim() || 'Automático';
    el.summaryDireccion.textContent = [
      direccion.cp ? 'CP ' + direccion.cp : '',
      direccion.ciudad || direccion.municipio || '',
      direccion.estado || '',
    ].filter(Boolean).join(' · ') || 'Pendiente';

    if (missing.length === 0) {
      el.syncPill.textContent = 'Listo para Facturotopía';
      el.syncPill.className = 'bef-status-pill ok';
      el.syncList.innerHTML = '<li>Datos mínimos completos.</li>';
    } else {
      el.syncPill.textContent = 'Faltan datos';
      el.syncPill.className = missing.length >= 5 ? 'bef-status-pill bad' : 'bef-status-pill warn';
      el.syncList.innerHTML = missing.map(item => `<li>${item}</li>`).join('');
    }

    el.jsonPreview.value = pretty({
      direccion: parseJson(el.direccionJson.value, {}),
      certificados: parseJson(el.certificadosJson.value, {}),
      series: parseJson(el.seriesJson.value, []),
    });
  };

  document.querySelectorAll('.bef-tab').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.bef-tab').forEach(x => x.classList.remove('active'));
      document.querySelectorAll('.bef-panel').forEach(x => x.classList.remove('active'));
      btn.classList.add('active');
      document.querySelector(`[data-panel="${btn.dataset.tab}"]`)?.classList.add('active');
    });
  });

  el.cuenta?.addEventListener('change', fillFromCuenta);
  document.querySelectorAll('input, select, textarea').forEach(input => {
    input.addEventListener('input', buildJson);
    input.addEventListener('change', buildJson);
  });

  $('btnAutofillCuenta')?.addEventListener('click', fillFromCuenta);
  $('btnPactopia')?.addEventListener('click', fillPactopia);
  $('btnBuildJson')?.addEventListener('click', buildJson);
  $('btnValidate')?.addEventListener('click', () => {
    buildJson();
    const missing = missingList();
    alert(missing.length ? 'Faltan:\n- ' + missing.join('\n- ') : 'Emisor listo para registrar/sincronizar.');
  });

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    await buildJsonWithFiles();
    form.submit();
  });

  buildJson();
})();
</script>