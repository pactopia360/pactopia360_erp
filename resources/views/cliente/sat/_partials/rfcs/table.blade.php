{{-- resources/views/cliente/sat/_partials/rfcs/_table.blade.php --}}
{{-- RFCs · Tabla v48 (floating labels + menos texto + look moderno) --}}

@php
  $credList = collect($credList ?? []);
@endphp

<div class="sat-subblock rfcs-v48">
  <div class="rfcs-v48-head">
    <div class="rfcs-v48-title"><span class="dot"></span>RFCs</div>
    <div class="rfcs-v48-hint">Valida SAT</div>
  </div>

  <div class="sat-dl-table-wrap rfcs-v48-wrap">
    <table class="sat-dl-table rfcs-v48-table">
      <thead>
        <tr>
          <th class="c-idx">#</th>
          <th class="c-rfc">RFC</th>
          <th class="c-name">Razón social</th>
          <th class="c-status">SAT</th>
          <th class="c-actions">Acciones</th>
        </tr>
      </thead>

      <tbody>
      @forelse($credList as $i => $c)
        @php
          $rowId = 'rfc_'.$i;
          $rfc   = strtoupper($c['rfc'] ?? $c->rfc ?? '');
          $alias = trim((string)($c['razon_social'] ?? $c->razon_social ?? $c['alias'] ?? $c->alias ?? ''));

          // Origen (externo) - best effort
          $originRaw = strtolower((string)(
              $c['origin'] ?? $c->origin ??
              $c['origen'] ?? $c->origen ??
              $c['source'] ?? $c->source ??
              $c['source_type'] ?? $c->source_type ??
              $c['tipo_origen'] ?? $c->tipo_origen ??
              ''
          ));

          $isExternal = !empty($c['external'] ?? null)
                     || !empty($c['is_external'] ?? null)
                     || !empty($c['registro_externo'] ?? null)
                     || !empty($c['external_token'] ?? null)
                     || !empty($c['invite_token'] ?? null)
                     || in_array($originRaw, ['external','externo','registro_externo','invited','invite','externa'], true);

          // e.firma/csd presente
          $hasCsdFlag =
              !empty($c['has_csd'] ?? null)
              || !empty($c['has_files'] ?? null)
              || !empty($c['cer_path'] ?? null)
              || !empty($c['key_path'] ?? null)
              || !empty($c['fiel_cer_path'] ?? null)
              || !empty($c['fiel_key_path'] ?? null)
              || !empty($c['efirma_cer_path'] ?? null)
              || !empty($c['efirma_key_path'] ?? null)
              || !empty($c['sat_cer_path'] ?? null)
              || !empty($c['sat_key_path'] ?? null)
              || !empty($c['cer_uploaded_at'] ?? null)
              || !empty($c['key_uploaded_at'] ?? null)
              || !empty($c['fiel_uploaded_at'] ?? null)
              || !empty($c['efirma_uploaded_at'] ?? null);

          // SAT validado - robusto
          $satStatusRaw = strtolower((string)(
              $c['sat_status'] ?? $c->sat_status ??
              $c['estatus_sat'] ?? $c->estatus_sat ??
              $c['sat_estatus'] ?? $c->sat_estatus ??
              ''
          ));

          $satOkFlag = !empty($c['validado'] ?? null)
                    || !empty($c['validated_at'] ?? null)
                    || !empty($c['sat_validated_at'] ?? null)
                    || !empty($c['sat_ok'] ?? null)
                    || in_array(strtolower((string)($c['estatus'] ?? $c->estatus ?? '')), ['ok','valido','válido','validado','sat_ok'], true)
                    || in_array($satStatusRaw, ['ok','valido','válido','validado','active','activo','success'], true);

          // Badge SAT
          if ($satOkFlag) {
            $badgeText  = 'OK';
            $badgeClass = 'ok';
          } else {
            if ($hasCsdFlag) {
              $badgeText  = 'PEND';
              $badgeClass = 'warn';
            } else {
              $badgeText  = 'SIN';
              $badgeClass = 'bad';
            }
          }

          // Chips
          $chipOriginText  = $isExternal ? 'EXTERNO' : 'INTERNO';
          $chipOriginClass = $isExternal ? 'chip-ext' : 'chip-int';

          $chipCsdText  = $hasCsdFlag ? 'E.FIRMA' : 'SIN';
          $chipCsdClass = $hasCsdFlag ? 'chip-csd' : 'chip-miss';

          // El escudo SIEMPRE debe abrir el panel para configurar/validar.
          // Si NO hay CSD => el panel sirve para CARGAR e.firma.
          // Si SÍ hay CSD => el panel sirve para VALIDAR/REVALIDAR (solo password).
          $canOpenPanel = true;
          $panelMode    = $isExternal ? 'external' : 'internal';

          $shieldTitle = $hasCsdFlag ? 'Validar SAT' : 'Cargar e.firma';


        @endphp

        <tr class="rfcs-v48-row"
            data-row="{{ $rowId }}"
            data-rfc="{{ $rfc }}"
            data-alias="{{ $alias }}"
            data-has-csd="{{ $hasCsdFlag ? '1':'0' }}"
            data-external="{{ $isExternal ? '1':'0' }}">

          <td class="t-center mono rfcs-v48-idx">{{ str_pad($i+1,2,'0',STR_PAD_LEFT) }}</td>

          <td class="rfcs-v48-cell-rfc">
            <form class="js-rfc-form" data-url="{{ $rtRfcReg }}" onsubmit="return false;">
              @csrf
              <div class="rfcs-fl">
                <label class="rfcs-lb">RFC</label>
                <input
                  class="rfcs-in mono js-rfc-upper"
                  name="rfc"
                  value="{{ $rfc }}"
                  maxlength="13"
                  inputmode="text"
                  autocomplete="off"
                  aria-label="RFC"
                >
              </div>
            </form>
          </td>


          <td class="rfcs-v48-cell-name">
            <div class="rfcs-name-row">
              <form class="js-alias-form" data-url="{{ $rtAlias }}" onsubmit="return false;">
                @csrf
                <input type="hidden" name="rfc" value="{{ $rfc }}">
                <input type="hidden" name="alias" value="{{ $alias }}">
                <input type="hidden" name="nombre" value="{{ $alias }}">
                <input type="hidden" name="razon_social" value="{{ $alias }}">

                <div class="rfcs-fl">
                  <label class="rfcs-lb">Razón social</label>
                  <input class="rfcs-in"
                         data-field="alias-visible"
                         value="{{ $alias }}"
                         autocomplete="off"
                         aria-label="Razón social">
                  <button class="rfcs-icon" type="submit" title="Guardar">✏️</button>
                </div>
              </form>

              <div class="rfcs-chips" aria-hidden="true">
                <span class="rfcs-chip {{ $chipOriginClass }}">{{ $chipOriginText }}</span>
                <span class="rfcs-chip {{ $chipCsdClass }}">{{ $chipCsdText }}</span>
              </div>
            </div>
          </td>

          <td class="t-center rfcs-v48-cell-status">
            <span class="rfcs-badge b-{{ $badgeClass }}"
                  title="{{ $satOkFlag ? 'SAT validado' : ($hasCsdFlag ? 'Pendiente validación SAT' : 'Sin e.firma') }}">
              {{ $badgeText }}
            </span>
          </td>

          <td class="t-center rfcs-v48-cell-actions">
            <div class="rfcs-actions">
              <button type="button"
                      class="rfcs-btn icon ghost js-rfc-apply"
                      title="Aplicar RFC">⟳</button>

             <button type="button"
                      class="rfcs-btn icon primary"
                      data-open-panel="{{ $rowId }}_panel"
                      title="{{ $shieldTitle }}">🛡️</button>


              @if($rtRfcDelete !== '#')
                <button type="button"
                        class="rfcs-btn icon ghost js-rfc-delete"
                        title="Eliminar"
                        data-url="{{ $rtRfcDelete }}"
                        data-rfc="{{ $rfc }}">🗑️</button>
              @endif
            </div>
          </td>
        </tr>

        {{-- PANEL compacto --}}
        <tr id="{{ $rowId }}_panel"
            class="rfcs-panel-row"
            hidden
            aria-hidden="true"
            data-mode="{{ $panelMode }}">
          <td colspan="5">
            <div class="rfcs-panel">
              <div class="rfcs-panel-h">
                <div class="rfcs-panel-t">{{ $isExternal ? 'SAT · Externo' : 'SAT' }}</div>
                <button type="button" class="rfcs-btn sm ghost" data-close-panel="{{ $rowId }}_panel">Cerrar</button>
              </div>

              <form method="post"
                    action="{{ $rtCsdStore }}"
                    enctype="multipart/form-data"
                    class="rfcs-panel-form js-csd-form"
                    data-rfc="{{ $rfc }}"
                    data-external="{{ $isExternal ? '1' : '0' }}"
                    data-has-csd="{{ $hasCsdFlag ? '1' : '0' }}">
                @csrf
                <input type="hidden" name="rfc" value="{{ $rfc }}">

                {{-- Indicadores para backend (si ya los soporta) --}}
                <input type="hidden" name="external" value="{{ $isExternal ? '1' : '0' }}">
                <input type="hidden" name="use_existing" value="1">
                <input type="hidden" name="validate_only" value="1">

                {{-- EXTERNO/INTERNO: misma regla.
                  - Si YA hay CSD: solo pide contraseña para validar/revalidar.
                  - Si NO hay CSD: pide cer+key+password (carga inicial).
                --}}
                @if($hasCsdFlag)
                  <div class="rfcs-panel-mini">
                    <div class="mini-kpi">
                      <div class="rfcs-fl">
                        <label class="rfcs-lb">e.firma</label>
                        <input class="rfcs-in" value="Lista" readonly>
                      </div>
                    </div>

                    <div class="mini-kpi">
                      <div class="rfcs-fl">
                        <label class="rfcs-lb">Contraseña</label>
                        <input type="password" name="key_password" class="rfcs-in" placeholder=" " required>
                      </div>
                    </div>

                    <div class="mini-kpi">
                      <div class="rfcs-fl">
                        <label class="rfcs-lb">Acción</label>
                        <input class="rfcs-in" value="{{ $satOkFlag ? 'Revalidar' : 'Validar' }}" readonly>
                      </div>
                    </div>
                  </div>
                @else
                  <div class="rfcs-panel-mini">
                    <div class="mini-kpi">
                      <div class="rfcs-fl">
                        <label class="rfcs-lb">Certificado (.cer)</label>
                        <input type="file" name="cer" accept=".cer" class="rfcs-file" required>
                      </div>
                    </div>

                    <div class="mini-kpi">
                      <div class="rfcs-fl">
                        <label class="rfcs-lb">Llave (.key)</label>
                        <input type="file" name="key" accept=".key" class="rfcs-file" required>
                      </div>
                    </div>

                    <div class="mini-kpi">
                      <div class="rfcs-fl">
                        <label class="rfcs-lb">Contraseña</label>
                        <input type="password" name="key_password" class="rfcs-in" placeholder=" " required>
                      </div>
                    </div>
                  </div>

                  @if($isExternal)
                    <div style="margin-top:8px; font-size:12px; color:var(--mut);">
                      Este RFC es <b>EXTERNO</b>, pero igual requiere cargar e.firma (CSD) para validar SAT.
                    </div>
                  @endif
                @endif


                <div class="rfcs-panel-actions">
                  <button type="submit" class="rfcs-btn primary js-csd-validate">
                    {{ $satOkFlag ? 'Revalidar' : 'Validar' }}
                  </button>

                  <span class="rfcs-panel-live" hidden aria-hidden="true">
                    <span class="dot"></span><span>Validando…</span>
                  </span>
                </div>
              </form>
            </div>
          </td>
        </tr>

      @empty
        <tr>
          <td colspan="5" class="sat-dl-empty">Sin RFCs.</td>
        </tr>
      @endforelse
      </tbody>
    </table>
  </div>
</div>

@push('scripts')
<script>
(function(){
  // ======================================================
  //  Helpers
  // ======================================================
  function csrfToken(){
    return (window.P360_SAT && window.P360_SAT.csrf) ? window.P360_SAT.csrf : '{{ csrf_token() }}';
  }
  function satToast(msg, kind='info') {
    try {
      if (window.P360 && typeof window.P360.toast === 'function') {
        if (kind === 'error' && window.P360.toast.error) return window.P360.toast.error(msg);
        if (kind === 'success' && window.P360.toast.success) return window.P360.toast.success(msg);
        return window.P360.toast(msg);
      }
    } catch(e) {}
    alert(msg);
  }
  function hardRefresh(){
    try{
      const u = new URL(window.location.href);
      u.searchParams.set('_ts', String(Date.now()));
      window.location.href = u.toString();
    } catch(e){
      window.location.reload();
    }
  }

  // ======================================================
  //  (A) FIX CRÍTICO: NO permitir que handlers legacy
  //  abran el modal "Agregar RFC" cuando el click viene
  //  del flujo de RFCs (panel/validar/escudo)
  //  -> CAPTURE TRUE para ganarle al legacy
  // ======================================================
  document.addEventListener('click', function(e){
    const t = e.target;

    // Si el click viene dentro del bloque RFCs v48
    const inRfcsV48 = !!t.closest('.rfcs-v48');
    if (!inRfcsV48) return;

    // Elementos del flujo RFC v48 que NO deben disparar legacy
    const isRfcAction =
      !!t.closest('[data-open-panel]') ||
      !!t.closest('[data-close-panel]') ||
      !!t.closest('.js-csd-validate') ||
      !!t.closest('.js-csd-form') ||
      !!t.closest('.js-rfc-apply') ||
      !!t.closest('.js-rfc-delete') ||
      !!t.closest('.js-rfc-form') ||
      !!t.closest('.js-alias-form');

    if (!isRfcAction) return;

    // Corta TODO para que no llegue al handler legacy global
    e.stopPropagation();
    if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
  }, true);

  // ======================================================
  //  (B) Toggle panel RFC v48 (robusto)
  // ======================================================
  function openPanel(panel){
    panel.removeAttribute('hidden');
    panel.setAttribute('aria-hidden','false');
  }
  function closePanel(panel){
    panel.setAttribute('hidden','');
    panel.setAttribute('aria-hidden','true');
  }

  document.addEventListener('click', function(e){
    const btn = e.target.closest('[data-open-panel]');
    if (!btn) return;

    e.preventDefault();
    e.stopPropagation();
    if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();

    const panelId = btn.getAttribute('data-open-panel');
    const panel = panelId ? document.getElementById(panelId) : null;
    if (!panel) return;

    const isOpen = !panel.hasAttribute('hidden');
    if (isOpen) closePanel(panel); else openPanel(panel);

    try { panel.scrollIntoView({behavior:'smooth', block:'nearest'}); } catch(_){}
  }, true);

  document.addEventListener('click', function(e){
    const btn = e.target.closest('[data-close-panel]');
    if (!btn) return;

    e.preventDefault();
    e.stopPropagation();
    if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();

    const panelId = btn.getAttribute('data-close-panel');
    const panel = panelId ? document.getElementById(panelId) : null;
    if (panel) closePanel(panel);
  }, true);

  // ======================================================
  //  (C) FIX: Modal "Agregar RFC" se queda atrapado
  //  -> Cierre forzado (X/Cancelar/backdrop/ESC)
  //  Nota: esto NO depende del markup exacto; intenta
  //        cerrar con varias heurísticas
  // ======================================================
  function findAddRfcModal(){
    // ids/clases más comunes en el módulo SAT
    return document.getElementById('satRfcModal')
        || document.getElementById('modalAddRfc')
        || document.querySelector('[data-modal="sat-rfc"]')
        || document.querySelector('.sat-modal.is-open')
        || document.querySelector('.sat-modal.open')
        || document.querySelector('.sat-modal.show')
        || document.querySelector('.sat-modal');
  }

  function forceCloseModal(modal){
    if (!modal) return;

    // Si hay API UI propia, úsala
    try {
      if (window.P360_SAT_UI && typeof window.P360_SAT_UI.closeAddRfcModal === 'function') {
        window.P360_SAT_UI.closeAddRfcModal();
        return;
      }
    } catch(_){}

    try { modal.classList.remove('is-open','open','show'); } catch(_){}
    try { modal.setAttribute('aria-hidden','true'); } catch(_){}
    try { modal.hidden = true; } catch(_){}
    try { modal.style.display = 'none'; } catch(_){}

    // limpia body/backdrops típicos
    try { document.body.classList.remove('modal-open','sat-modal-open'); } catch(_){}
    document.querySelectorAll('.modal-backdrop,.sat-modal-backdrop,[data-backdrop]').forEach(el=>{
      try { el.remove(); } catch(_){}
    });
  }

  // Captura clicks dentro del modal: permitir cerrar aunque haya legacy
  document.addEventListener('click', function(e){
    const modal = findAddRfcModal();
    if (!modal) return;

    const t = e.target;

    // Si click cae FUERA del panel pero dentro del overlay (backdrop)
    // y el modal está visible, cerrarlo.
    const isVisible = !modal.hidden && modal.style.display !== 'none';
    if (!isVisible) return;

    const clickedInside = modal.contains(t);

    // Botones típicos de cerrar/cancelar
    const btn = t.closest('button,a');
    const label = (btn && (btn.getAttribute('aria-label') || btn.title || '') || '').toLowerCase();
    const text  = (btn && (btn.textContent || '').trim().toLowerCase() || '');

    const isCloseBtn =
      (label.includes('cerrar') || label.includes('close') || label === 'x') ||
      (text === 'cancelar' || text === 'cerrar' || text === 'x') ||
      !!t.closest('[data-close],[data-dismiss],[data-modal-close],[data-close-modal]') ||
      !!t.closest('.sat-modal-close,.modal-close');

    // X o Cancelar
    if (isCloseBtn) {
      e.preventDefault();
      e.stopPropagation();
      if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
      forceCloseModal(modal);
      return;
    }

    // Click en backdrop (fuera del contenido)
    // Si no podemos distinguir contenedor interno, al menos:
    // - si el target es el propio modal/overlay y NO un input/button, cerramos
    if (!clickedInside) {
      // si el overlay no contiene el click, no hacemos nada
      return;
    }

    // Heurística: si el modal es overlay y el click fue directamente sobre el overlay (no sobre inputs)
    if (t === modal && !t.closest('input,select,textarea,button,a')) {
      e.preventDefault();
      e.stopPropagation();
      if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
      forceCloseModal(modal);
      return;
    }
  }, true);

  // ESC para cerrar
  document.addEventListener('keydown', function(e){
    if (e.key !== 'Escape') return;
    const modal = findAddRfcModal();
    if (!modal) return;

    const isVisible = !modal.hidden && modal.style.display !== 'none';
    if (!isVisible) return;

    e.preventDefault();
    e.stopPropagation();
    if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
    forceCloseModal(modal);
  }, true);

  // ======================================================
  //  (D) Submit AJAX de validación (con guardrails)
  // ======================================================
  document.querySelectorAll('.rfcs-v48 .js-csd-form').forEach((form) => {
    // evita registrar doble si este partial se reinyecta
    if (form.dataset.v48Bound === '1') return;
    form.dataset.v48Bound = '1';

    form.addEventListener('submit', async (ev) => {
      if (!window.fetch) return;

      // evita dobles handlers (legacy)
      ev.preventDefault();
      ev.stopPropagation();
      if (typeof ev.stopImmediatePropagation === 'function') ev.stopImmediatePropagation();

      const url = String(form.getAttribute('action') || '').trim();
      if (!url) return satToast('Ruta de validación no configurada.', 'error');

      const btn  = form.querySelector('.js-csd-validate');
      const live = form.querySelector('.rfcs-panel-live');

      const setBusy = (busy) => {
        try { if (btn) btn.disabled = !!busy; } catch (_) {}
        try {
          if (live) {
            live.hidden = !busy;
            live.setAttribute('aria-hidden', busy ? 'false' : 'true');
          }
        } catch (_) {}
      };

      const fd = new FormData(form);

      // Guardrail: si NO hay CSD exige cer+key+pwd; si hay CSD sólo pwd
      const hasCsd = String(form.dataset.hasCsd || '0') === '1';

      const cer = fd.get('cer');
      const key = fd.get('key');
      const pwd = String(fd.get('key_password') || '').trim();

      const cerOk = (cer instanceof File) && !!cer.name;
      const keyOk = (key instanceof File) && !!key.name;

      if (!hasCsd) {
        if (!cerOk || !keyOk) {
          satToast('Adjunta ambos archivos: .cer y .key para registrar la e.firma.', 'error');
          setBusy(false);
          return;
        }
        if (!pwd) {
          satToast('Escribe la contraseña de la e.firma.', 'error');
          setBusy(false);
          return;
        }
      } else {
        if (!pwd) {
          satToast('Escribe la contraseña para validar/revalidar SAT.', 'error');
          setBusy(false);
          return;
        }
        // si hay CSD, NO mandar cer/key
        try { fd.delete('cer'); } catch (_) {}
        try { fd.delete('key'); } catch (_) {}
      }

      setBusy(true);

      try{
        const res = await fetch(url, {
          method: 'POST',
          headers: {
            'X-Requested-With':'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept':'application/json',
          },
          body: fd,
        });

        const raw = await res.text();
        let data = null;
        try { data = JSON.parse(raw); } catch(_) { data = null; }

        // Si backend devuelve HTML/redirect, caer a submit normal
        if (!data) {
          setBusy(false);
          form.submit();
          return;
        }

        if (!res.ok || data.ok === false) {
          throw new Error(data.msg || data.message || 'No se pudo validar.');
        }

        satToast(data.msg || 'Validación enviada.', 'success');

        // Cierra panel
        try {
          const panelRow = form.closest('.rfcs-panel-row');
          if (panelRow) {
            panelRow.setAttribute('hidden','');
            panelRow.setAttribute('aria-hidden','true');
          }
        } catch(_){}

        hardRefresh();
      } catch (e) {
        satToast((e && e.message) ? e.message : 'Error al validar.', 'error');
      } finally {
        setBusy(false);
      }
    }, true); // CAPTURE TRUE
  });

})();
</script>
@endpush

