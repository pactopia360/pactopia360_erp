{{-- resources/views/cliente/sat/_partials/rfcs.blade.php
     v41 · RFCs con tabla tipo SAT Descargas (sat-dl-table)
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
  $isProPlan   = in_array($planCode, ['PRO','PREMIUM','EMPRESA','BUSINESS','BUSINESS_PRO']);
  $rtRfcDelete = $rtRfcDelete ?? '#';
@endphp

<div class="rfcs-neo">
  {{-- HEADER: título + botón --}}
  <div class="rfcs-neo-hd">
    <div class="rfcs-neo-title-row">
      <span class="rfcs-neo-icon" aria-hidden="true">🧩</span>
      <div>
        <div class="rfcs-neo-kicker">SAT · Conexiones</div>
        <h3 class="rfcs-neo-title">Mis RFCs</h3>
      </div>
    </div>

    <div class="rfcs-neo-hd-actions">
      <button type="button"
              class="btn-rfcs primary"
              data-open="add-rfc"
              data-tip="Agregar nuevo RFC">
        <span aria-hidden="true">＋</span>
        <span>Agregar RFC</span>
      </button>
    </div>
  </div>

  {{-- FILTROS (mismo estilo que filtros de descargas) --}}
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

  {{-- TABLA RFCs · usa la misma base que Listado descargas --}}
  <div class="sat-dl-table-wrap sat-rfcs-table-wrap">
    <table class="sat-dl-table sat-rfcs-table">
      <thead>
        <tr>
          <th class="sat-rfcs-th-idx">ID</th>
          <th class="sat-rfcs-th-rfc">RFC</th>
          <th class="sat-rfcs-th-name">Nombre o razón social</th>
          <th class="sat-rfcs-th-status">Estatus</th>
          <th class="sat-rfcs-th-actions">Acciones</th>
        </tr>
      </thead>
      <tbody>
      @forelse($credList as $i => $c)
        @php
          $rowId = 'rfc_'.$i;
          $rfc   = strtoupper($c['rfc'] ?? $c->rfc ?? '');
          $alias = trim((string)($c['razon_social'] ?? $c->razon_social ?? $c['alias'] ?? $c->alias ?? ''));

          $okFlag = !empty($c['validado'] ?? null)
                 || !empty($c['validated_at'] ?? null)
                 || !empty($c['has_files'] ?? null);

          $estatusRaw = strtolower((string)($c['estatus'] ?? $c->estatus ?? ''));
          $hasCsdFlag = $okFlag
                     || !empty($c['has_csd'] ?? null)
                     || !empty($c['cer_path'] ?? null)
                     || !empty($c['key_path'] ?? null);

          if ($okFlag || in_array($estatusRaw,['ok','valido','válido','validado'])) {
              $statusText  = 'Validado';
              $statusClass = 'rfc-ok';
              $statusCode  = 'ok';
          } else {
              $statusText  = $estatusRaw !== '' ? ucfirst($estatusRaw) : 'Por validar';
              $statusClass = 'rfc-warn';
              $statusCode  = 'warn';
          }
        @endphp

        {{-- FILA PRINCIPAL --}}
        <tr class="sat-rfcs-row"
            data-row="{{ $rowId }}"
            data-status="{{ $statusCode }}"
            data-has-csd="{{ $hasCsdFlag ? '1' : '0' }}"
            data-rfc="{{ $rfc }}"
            data-alias="{{ $alias }}">
          {{-- ID --}}
          <td class="t-center mono">{{ str_pad($i+1,2,'0',STR_PAD_LEFT) }}</td>

          {{-- RFC editable (AJAX, sin navegación) --}}
          <td>
            <form
                class="pill-form js-alias-form"
                data-url="{{ $rtAlias }}"
              >
                @csrf
                <input type="hidden" name="rfc"          value="{{ $rfc }}">
                <input type="hidden" name="alias"        value="{{ $alias }}">
                <input type="hidden" name="nombre"       value="{{ $alias }}">
                <input type="hidden" name="razon_social" value="{{ $alias }}">

                <input class="pill-input pill-alias"
                      data-field="alias-visible"
                      value="{{ $alias }}"
                      placeholder="Razón social / Alias"
                      data-tip="Editar nombre / alias">

                <button class="pill-icon"
                        type="submit"
                        data-tip="Guardar cambios de nombre">
                  ✏️
                </button>
              </form>

          </td>

          {{-- Nombre / razón social editable (AJAX, sin navegación) --}}
          <td>
            <form
              class="pill-form js-alias-form"
              data-url="{{ $rtAlias }}"
              onsubmit="return false;"
            >
              @csrf
              <input type="hidden" name="rfc"          value="{{ $rfc }}">
              <input type="hidden" name="alias"        value="{{ $alias }}">
              <input type="hidden" name="nombre"       value="{{ $alias }}">
              <input type="hidden" name="razon_social" value="{{ $alias }}">

              <input class="pill-input pill-alias"
                     data-field="alias-visible"
                     value="{{ $alias }}"
                     placeholder="Razón social / Alias"
                     data-tip="Editar nombre / alias">

              <button class="pill-icon"
                      type="submit"
                      data-tip="Guardar cambios de nombre">
                ✏️
              </button>
            </form>
          </td>

          {{-- Estatus --}}
          <td class="t-center">
            <span class="rfc-badge {{ $statusClass }}">{{ $statusText }}</span>
          </td>

          {{-- Acciones --}}
          <td class="t-center">
            <div class="sat-rfcs-actions">
              <button type="button"
                      class="btn-rfcs icon ghost js-rfc-apply"
                      data-tip="Aplicar cambios de RFC">
                ⟳
              </button>

              <button type="button"
                      class="btn-rfcs icon ghost"
                      data-tip="Validar / revalidar certificados"
                      data-open-panel="{{ $rowId }}_panel">
                🛡️
              </button>

              @if($rtRfcDelete !== '#')
                <button type="button"
                        class="btn-rfcs icon ghost js-rfc-delete"
                        data-tip="Eliminar RFC"
                        data-url="{{ $rtRfcDelete }}"
                        data-rfc="{{ $rfc }}">
                  🗑️
                </button>
              @endif
            </div>
          </td>
        </tr>

        {{-- PANEL CSD como fila expandida --}}
        <tr id="{{ $rowId }}_panel"
            class="sat-rfcs-panel-row"
            hidden
            aria-hidden="true">
          <td colspan="5">
            <form method="post"
                  action="{{ $rtCsdStore }}"
                  enctype="multipart/form-data"
                  class="rfcs-neo-panel-grid">
              @csrf
              <input type="hidden" name="rfc" value="{{ $rfc }}">

              <label class="fld">
                <span>Certificado (.cer)</span>
                <input type="file"
                       name="cer"
                       accept=".cer"
                       class="input"
                       {{ $okFlag ? '' : 'required' }}>
              </label>

              <label class="fld">
                <span>Llave privada (.key)</span>
                <input type="file"
                       name="key"
                       accept=".key"
                       class="input"
                       {{ $okFlag ? '' : 'required' }}>
              </label>

              <label class="fld">
                <span>Contraseña</span>
                <input type="password"
                      name="key_password"
                      class="input"
                      placeholder="••••••••"
                      {{ $okFlag ? '' : 'required' }}>
              </label>


              <div class="panel-actions">
                <div class="panel-actions-left">
                  <button type="submit"
                          name="solo_guardar"
                          value="1"
                          class="btn-rfcs ghost"
                          data-tip="Guardar archivos sin validar">
                    💾 Guardar
                  </button>

                  <button type="submit"
                          class="btn-rfcs primary"
                          data-tip="{{ $okFlag ? 'Revalidar CSD' : 'Validar CSD' }}">
                    {{ $okFlag ? 'Revalidar' : 'Validar' }}
                  </button>
                </div>

                <span class="note">
                  @if($isProPlan)
                    Listo para <b>solicitudes</b> y <b>automatizaciones</b>.
                  @else
                    Listo para <b>solicitudes</b>. Automatizadas: <b>solo PRO</b>.
                  @endif
                </span>
              </div>
            </form>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="5" class="sat-dl-empty">
            Sin RFCs registrados. Usa <b>“Agregar RFC”</b> para comenzar.
          </td>
        </tr>
      @endforelse
      </tbody>
    </table>
  </div>
</div>

@push('styles')
<style>
  .rfcs-neo{
    --bd:#e5e7eb;
    --ink:#0f172a;
    --mut:#6b7280;
  }

  .rfcs-neo-hd{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:10px;
    margin-bottom:10px;
  }
  .rfcs-neo-title-row{
    display:flex;
    align-items:center;
    gap:10px;
  }
  .rfcs-neo-icon{
    width:34px;
    height:34px;
    border-radius:12px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:#0f172a;
    color:#f9fafb;
    font-size:18px;
  }
  html[data-theme="dark"] .rfcs-neo-icon{
    background:#f9fafb;
    color:#0f172a;
  }
  .rfcs-neo-kicker{
    font-size:10px;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:.16em;
    color:var(--mut);
    margin-bottom:2px;
  }
  .rfcs-neo-title{
    margin:0;
    font:900 18px/1.2 'Poppins',system-ui;
    color:var(--ink);
  }
  .rfcs-neo-hd-actions{
    display:flex;
    flex-wrap:wrap;
    gap:8px;
  }

  .btn-rfcs{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:6px;
    padding:7px 11px;
    border-radius:999px;
    border:1px solid var(--bd);
    background:#f9fafb;
    color:var(--ink);
    font:800 12px/1 'Poppins',system-ui;
    cursor:pointer;
    min-height:30px;
  }
  .btn-rfcs.primary{
    background:#111827;
    border-color:#111827;
    color:#f9fafb;
  }
  .btn-rfcs.icon{
    width:30px;
    height:30px;
    padding:0;
  }

  /* Filtros arriba de la tabla (similar a sat-dl-filters) */
  .sat-rfcs-filters{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    margin-bottom:6px;
    font-family:'Poppins',system-ui,sans-serif;
  }
  .sat-rfcs-filters-left{
    flex:1 1 auto;
  }
  .sat-rfcs-filters-right{
    display:flex;
    flex-wrap:wrap;
    gap:6px;
  }
  .sat-rfcs-filter-input,
  .sat-rfcs-filter-select{
    border-radius:999px;
    border:1px solid #e5e7eb;
    padding:6px 10px;
    font-size:11px;
    font-family:'Poppins',system-ui,sans-serif;
    background:#f9fafb;
  }
  .sat-rfcs-filter-input{
    width:100%;
  }
  .sat-rfcs-filter-input::placeholder{
    color:#9ca3af;
  }
  .sat-rfcs-filter-select{
    min-width:130px;
  }

  /* Ajustes tabla RFCs encima de la base .sat-dl-table */
  .sat-rfcs-table-wrap{
    margin-top:4px;
  }

   /* ===== Mis RFCs: ocupar ancho completo SIN scroll horizontal en pantallas grandes ===== */
  @media (min-width:1280px){
    /* El wrapper NO muestra scroll horizontal en este bloque */
    .rfcs-neo .sat-dl-table-wrap{
      overflow-x:hidden !important;
    }

    /* La tabla de Mis RFCs se adapta al ancho disponible */
    .rfcs-neo .sat-dl-table{
      min-width:0 !important;   /* anula el min-width global de 1200px */
      width:100%;
      table-layout:auto;
    }

    /* Permitimos que las celdas rompan un poco la línea si es necesario */
    .rfcs-neo .sat-rfcs-table thead th,
    .rfcs-neo .sat-rfcs-table tbody td{
      white-space:normal;
    }
  }

  .sat-rfcs-table thead th,
  .sat-rfcs-table tbody td{
    white-space:nowrap;
  }
  .sat-rfcs-th-idx{
    width:60px;
    text-align:center;
  }
  .sat-rfcs-th-rfc{
    width:180px;
  }
  .sat-rfcs-th-name{
    min-width:260px;
  }
  .sat-rfcs-th-status{
    width:130px;
    text-align:center;
  }
  .sat-rfcs-th-actions{
    width:150px;
    text-align:center;
  }

  .sat-rfcs-actions{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:6px;
  }

  /* Inputs pill (RFC y Alias) */
  .pill-form{
    position:relative;
    display:flex;
    align-items:center;
    width:100%;
  }
  .pill-input{
    width:100%;
    border-radius:999px;
    border:1px solid #e5e7eb;
    background:#f9fafb;
    padding:7px 28px 7px 10px;
    font:800 12px/1 'Poppins',system-ui;
    color:#111827;
  }
  .pill-input.mono{
    font-family:ui-monospace,Menlo,Consolas,monospace;
  }
  .pill-alias::placeholder{
    color:#9ca3af;
  }
  .pill-input:focus{
    outline:none;
    border-color:#0ea5e9;
    background:#ffffff;
  }
  .pill-icon{
    position:absolute;
    right:6px;
    width:20px;
    height:20px;
    border-radius:999px;
    border:0;
    background:#ffffff;
    cursor:pointer;
    font-size:11px;
    display:flex;
    align-items:center;
    justify-content:center;
    box-shadow:0 2px 6px rgba(15,23,42,.12);
  }

  /* Estatus */
  .rfc-badge{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:4px 10px;
    border-radius:999px;
    font:900 10.5px/1 'Poppins',system-ui;
    text-transform:uppercase;
    letter-spacing:.08em;
  }
  .rfc-ok{
    background:#dcfce7;
    color:#166534;
    border:1px solid #86efac;
  }
  .rfc-warn{
    background:#fff7ed;
    color:#92400e;
    border:1px solid #fed7aa;
  }

  /* Panel CSD como fila expandida */
  .sat-rfcs-panel-row td{
    background:#f9fafb !important;
  }

  .rfcs-neo-panel-grid{
    display:grid;
    grid-template-columns:repeat(3,minmax(180px,1fr));
    gap:12px 16px;
  }
  .rfcs-neo-panel-grid .input{
    width:100%;
    border-radius:999px;
    border:1px dashed #d1d5db;
    padding:7px 11px;
    font:600 12px/1 'Poppins',system-ui;
    background:#ffffff;
  }
  .fld span{
    display:block;
    font:800 11px/1 'Poppins',system-ui;
    color:#6b7280;
    margin-bottom:4px;
  }
  .panel-actions{
    grid-column:1 / -1;
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:10px;
    margin-top:4px;
  }
  .panel-actions-left{
    display:flex;
    flex-wrap:wrap;
    gap:8px;
  }
  .note{
    color:#6b7280;
    font-weight:600;
    font-size:11px;
  }

  @media(max-width:900px){
    .rfcs-neo-panel-grid{
      grid-template-columns:1fr;
    }
  }
</style>
@endpush

@push('scripts')
<script>
(function(){
  console.log('[SAT:RFC] init manejadores RFC/Alias/Panel/Eliminar');

  // RFC siempre en mayúsculas
  document.querySelectorAll('.rfcs-neo .js-rfc-upper').forEach(input => {
    input.addEventListener('input', () => {
      input.value = (input.value || '').toUpperCase();
    });
  });

  // Alias visible -> inputs ocultos alias/nombre/razon_social
  document.querySelectorAll('.rfcs-neo .js-alias-form').forEach(form => {
    const visible = form.querySelector('[data-field="alias-visible"]');
    if(!visible) return;
    const syncHidden = () => {
      const v = visible.value || '';
      form.querySelectorAll('input[name="alias"],input[name="nombre"],input[name="razon_social"]').forEach(h => {
        h.value = v;
      });
    };
    visible.addEventListener('input', syncHidden);
    syncHidden();
  });

  /* =====================================================
   *  ESCUDO -> ABRIR / CERRAR PANEL CSD (DELEGADO)
   * ===================================================== */
  document.addEventListener('click', function(e){
    const btn = e.target.closest('.rfcs-neo [data-open-panel]');
    if (!btn) return;

    const panelId = btn.dataset.openPanel;
    console.log('[SAT:RFC] toggle panel CSD', { panelId });

    if (!panelId) return;
    const panel = document.getElementById(panelId);
    if (!panel) {
      console.warn('[SAT:RFC] panel no encontrado', { panelId });
      return;
    }

    const isOpen = !panel.hasAttribute('hidden');
    if (isOpen) {
      panel.setAttribute('hidden','');
      panel.setAttribute('aria-hidden','true');
    } else {
      panel.removeAttribute('hidden');
      panel.setAttribute('aria-hidden','false');
    }
  });

  /* =====================================================
   *  BOTÓN ⟳ -> DISPARA SUBMIT DEL FORM DE RFC
   * ===================================================== */
  document.querySelectorAll('.rfcs-neo .js-rfc-apply').forEach(btn => {
    btn.addEventListener('click', (ev) => {
      ev.preventDefault();
      const row = btn.closest('.sat-rfcs-row');
      const f   = row ? row.querySelector('.js-rfc-form') : null;
      if (f) {
        console.log('[SAT:RFC] aplicar cambios RFC (submit form)', {
          rfc: f.querySelector('input[name="rfc"]')?.value || null
        });
        f.submit();
      }
    });
  });

  /* =====================================================
   *  ELIMINAR RFC (POST/DELETE VIA FETCH)
   * ===================================================== */
  document.querySelectorAll('.rfcs-neo .js-rfc-delete').forEach(btn => {
    btn.addEventListener('click', async () => {
      const url = btn.dataset.url || '#';
      const rfc = btn.dataset.rfc || '';
      console.log('[SAT:RFC] click eliminar RFC', { url, rfc });

      if (!url || url === '#') return;
      if (!confirm('¿Eliminar el RFC '+ rfc +'?\n\nEsta acción no se puede deshacer.')) return;

      try{
        btn.disabled = true;
        const fd = new FormData();
        // IMPORTANTE: seguimos mandando RFC porque tu deleteRfc actual
        // está borrando por rfc (según el log que mostraste)
        fd.append('rfc', rfc);
        fd.append('_method','DELETE');

        const res = await fetch(url, {
          method:'POST',
          headers:{
            'X-Requested-With':'XMLHttpRequest',
            'X-CSRF-TOKEN': (typeof SAT_CSRF !== 'undefined' ? SAT_CSRF : '{{ csrf_token() }}'),
          },
          body: fd,
        });

        const rawText = await res.text();
        let j = {};
        try { j = JSON.parse(rawText); } catch(_) {}

        console.log('[SAT:RFC] respuesta DELETE', {
          status: res.status,
          ok: res.ok,
          json: j,
          raw: rawText
        });

        if (!res.ok || j.ok === false){
          alert(j.msg || 'No se pudo eliminar el RFC.');
          btn.disabled = false;
          return;
        }

        const row = btn.closest('.sat-rfcs-row');
        if (row){
          const rowId   = row.dataset.row || '';
          const panelId = rowId ? rowId + '_panel' : null;
          if (panelId){
            const panel = document.getElementById(panelId);
            if (panel) panel.remove();
          }
          row.remove();
        }

      }catch(e){
        console.error(e);
        alert('Error de conexión al eliminar el RFC.');
        btn.disabled = false;
      }
    });
  });

  /* =====================================================
   *  FILTROS RFCs
   * ===================================================== */
  const searchInput = document.getElementById('rfcFilterSearch');
  const selStatus   = document.getElementById('rfcFilterStatus');
  const selCsd      = document.getElementById('rfcFilterCsd');
  const rows        = Array.from(document.querySelectorAll('.sat-rfcs-row'));

  function applyFilters() {
    const tipoVal  = (fTipo && fTipo.value) ? fTipo.value.toLowerCase() : 'ambos';
    const rfcVal   = ((fRfc && fRfc.value) || '').trim().toUpperCase();
    const qVal     = ((fQuery && fQuery.value) || '').trim().toUpperCase();

    const minVal   = (fMin && fMin.value !== '') ? Number(fMin.value) : null;
    const maxVal   = (fMax && fMax.value !== '') ? Number(fMax.value) : null;

    const dDesde   = (fDesde && fDesde.value) ? new Date(fDesde.value) : null;
    const dHasta   = (fHasta && fHasta.value) ? new Date(fHasta.value) : null;
    if (dHasta) dHasta.setDate(dHasta.getDate() + 1);

    let count = 0, sumSub = 0, sumIva = 0, sumTot = 0;
    const out = [];

    for (const r of allRows) {
        const tipoRow = String(r.tipo || '').toLowerCase();
        if (tipoVal !== 'ambos' && tipoRow !== tipoVal) continue;

        const fRow = getRowDate(r);
        if (dDesde && (!fRow || fRow < dDesde)) continue;
        if (dHasta && (!fRow || fRow >= dHasta)) continue;

        const info  = getRfcAndRazon(r);
        const rfc   = info.rfc;
        const razon = info.razon;

        if (rfcVal && rfc !== rfcVal) continue;

        if (qVal) {
            const hay = (rfc + ' ' + razon + ' ' + (r.uuid || '')).toUpperCase();
            if (!hay.includes(qVal)) continue;
        }

        // ===== Montos robustos =====
        let subtotal = toNum(r.subtotal ?? r.subtotal_mxn ?? 0);
        let iva      = toNum(r.iva      ?? r.iva_mxn      ?? 0);

        // Total robusto (usa campos alternos)
        let total    = toNum(getRowTotal(r));

        // Normalizar
        if (!Number.isFinite(subtotal) || subtotal < 0) subtotal = 0;
        if (!Number.isFinite(iva)      || iva < 0)      iva = 0;
        if (!Number.isFinite(total)    || total < 0)    total = 0;

        // Fallbacks consistentes
        if (total <= 0 && (subtotal > 0 || iva > 0)) total = subtotal + iva;

        if (iva <= 0 && subtotal > 0 && total > subtotal) {
            const diff = total - subtotal;
            iva = diff > 0.00001 ? diff : 0;
        }

        if (subtotal <= 0 && total > 0 && iva > 0 && total >= iva) {
            const diff = total - iva;
            subtotal = diff > 0.00001 ? diff : 0;
        }

        // % IVA
        let ivaPct = 0;
        if (subtotal > 0 && iva > 0) {
            ivaPct = (iva / subtotal) * 100;
            if (!Number.isFinite(ivaPct) || ivaPct < 0) ivaPct = 0;
        }

        // ===== AQUI va el filtro por rango (ANTES de meterlo a out) =====
        if (minVal !== null && total < minVal) continue;
        if (maxVal !== null && total > maxVal) continue;

        // Persistir display (para tabla/métricas/charts)
        r.__display = { subtotal, iva, total, rfc, razon, ivapct: ivaPct };

        out.push(r);

        count++;
        sumSub += subtotal;
        sumIva += iva;
        sumTot += total;
    }

    state.totals = { count, sub: sumSub, iva: sumIva, tot: sumTot };
    return out;
}


  if (searchInput) searchInput.addEventListener('input', applyFilters);
  if (selStatus)   selStatus.addEventListener('change', applyFilters);
  if (selCsd)      selCsd.addEventListener('change', applyFilters);
})();
</script>
@endpush



