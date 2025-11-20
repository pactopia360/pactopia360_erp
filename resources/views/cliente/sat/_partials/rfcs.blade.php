{{-- resources/views/cliente/sat/_partials/rfcs.blade.php
     v25 · RFCs: listado limpio + panel CSD
     Usa el modal global de index.blade.php (data-open="add-rfc") --}}

@php
  /**
   * Espera:
   *  - $credList   (array|Collection de RFCs)
   *  - $plan       ('FREE'|'PRO'|'EMPRESA'...)
   *  - $rtCsdStore (ruta para subir .cer/.key/password)
   *  - $rtAlias    (ruta para guardar razón social / alias)
   *  - $rtRfcReg   (ruta para actualizar RFC)
   */
  $credList = collect($credList ?? []);
@endphp

<div class="rfcs-neo">
  <div class="rfcs-neo-hd">
    <div>
      <h3>1) RFCs registrados</h3>
      <p class="rfcs-neo-sub">RFCs y certificados CSD registrados</p>
    </div>

    {{-- Usa el modal global del SAT (index) --}}
    <button type="button"
            class="btn-rfcs primary"
            data-open="add-rfc"
            data-tip="Agregar nuevo RFC">
      <span aria-hidden="true">＋</span>
      <span>Agregar RFC</span>
    </button>
  </div>

  {{-- Encabezado de columnas --}}
  <div class="rfcs-neo-head">
    <div class="c-chevron"></div>
    <div class="c-rfc">RFC</div>
    <div class="c-name">Nombre o razón social</div>
    <div class="c-status">Estatus</div>
    <div class="c-actions">Acciones</div>
  </div>

  @forelse($credList as $i => $c)
    @php
      $rowId = 'rfc_'.$i;
      $rfc   = strtoupper($c['rfc'] ?? $c->rfc ?? '');
      $alias = trim((string)($c['razon_social'] ?? $c->razon_social ?? $c['alias'] ?? $c->alias ?? ''));

      $okFlag = !empty($c['validado'] ?? null)
             || !empty($c['validated_at'] ?? null)
             || !empty($c['has_files'] ?? null);

      $estatusRaw = strtolower((string)($c['estatus'] ?? $c->estatus ?? ''));

      if ($okFlag || in_array($estatusRaw,['ok','valido','válido','validado'])) {
          $statusText  = 'Validado';
          $statusClass = 'rfc-ok';
      } else {
          $statusText  = $estatusRaw !== '' ? ucfirst($estatusRaw) : 'Por validar';
          $statusClass = 'rfc-warn';
      }
    @endphp

    {{-- FILA PRINCIPAL --}}
    <div class="rfcs-neo-row" data-row="{{ $rowId }}">
      {{-- Chevron --}}
      <div class="c-chevron">
        <button class="chev"
                type="button"
                data-tip="Datos fiscales"
                aria-expanded="false"
                aria-controls="{{ $rowId }}_panel">
          ▾
        </button>
      </div>

      {{-- RFC editable (POST clásico) --}}
      <div class="c-rfc">
        <form method="post"
              action="{{ $rtRfcReg }}"
              class="pill-form js-rfc-form">
          @csrf
          <input type="hidden" name="old_rfc"      value="{{ $rfc }}">
          <input type="hidden" name="rfc_old"      value="{{ $rfc }}">
          <input type="hidden" name="rfc_original" value="{{ $rfc }}">

          <input class="pill-input mono js-rfc-upper"
                 name="rfc"
                 maxlength="13"
                 value="{{ $rfc }}"
                 autocomplete="off"
                 data-tip="Editar RFC">

          <button class="pill-icon"
                  type="submit"
                  data-tip="Guardar cambios de RFC">
            ✏️
          </button>
        </form>
      </div>

      {{-- Nombre / razón social editable --}}
      <div class="c-name">
        <form method="post"
              action="{{ $rtAlias }}"
              class="pill-form js-alias-form">
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
      </div>

      {{-- Estatus --}}
      <div class="c-status">
        <span class="rfc-badge {{ $statusClass }}">{{ $statusText }}</span>
      </div>

      {{-- Acciones --}}
      <div class="c-actions">
        {{-- Aplica el form de RFC (práctico si el usuario solo cambió el texto) --}}
        <button type="button"
                class="btn-rfcs icon ghost js-rfc-apply"
                data-tip="Aplicar cambios de RFC">
          ⟳
        </button>

        {{-- Abre/cierra el panel de CSD --}}
        <button type="button"
                class="btn-rfcs icon ghost"
                data-tip="Validar / revalidar certificados"
                data-open-panel="{{ $rowId }}_panel">
          🛡️
        </button>
      </div>
    </div>

    {{-- PANEL DATOS FISCALES (CSD / FIEL) --}}
    <div id="{{ $rowId }}_panel"
         class="rfcs-neo-panel"
         hidden
         aria-hidden="true">
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
                 name="password"
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
            @if(($plan ?? 'FREE') === 'FREE')
              Listo para <b>solicitudes</b>. Automatizadas: <b>solo PRO</b>.
            @else
              Listo para <b>solicitudes</b> y <b>automatizaciones</b>.
            @endif
          </span>
        </div>
      </form>
    </div>
  @empty
    <div class="rfcs-neo-empty">
      Sin RFCs registrados. Usa <b>“Agregar RFC”</b> para comenzar.
    </div>
  @endforelse

  <div class="rfcs-neo-foot">
    <small>Para validar un RFC sube el <b>.cer</b>, la <b>.key</b> y la <b>contraseña</b>, luego pulsa <b>Validar</b>.</small>
  </div>
</div>

@push('styles')
<style>
/* =========================================================
   LISTADO RFCs (usa tokens de .sat-ui)
   ========================================================= */
.rfcs-neo{
  --bd:var(--bd, #e5e7eb);
  --card:var(--card, #ffffff);
  --ink:var(--ink, #0f172a);
  --mut:var(--mut, #6b7280);
  --shadow:0 8px 22px rgba(15,23,42,.04);
  border-radius:16px;
}
.rfcs-neo-hd{
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  margin-bottom:12px;
  gap:10px;
}
.rfcs-neo-hd h3{
  margin:0;
  font:900 18px/1.2 'Poppins',system-ui;
  color:var(--ink);
}
.rfcs-neo-sub{
  margin:2px 0 0;
  font-size:11px;
  font-weight:600;
  color:var(--mut);
}

/* Botones propios del bloque */
.btn-rfcs{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  gap:6px;
  padding:7px 11px;
  border-radius:999px;
  border:1px solid var(--bd);
  background:var(--card);
  color:var(--ink);
  font:800 12px/1 'Poppins';
  cursor:pointer;
  min-height:32px;
  transition:background .12s ease, box-shadow .12s ease, transform .12s ease;
}
.btn-rfcs span[aria-hidden="true"]{font-size:15px}
.btn-rfcs.primary{
  border-color:#fb7185;
  background:#e11d48;
  color:#fff;
  box-shadow:var(--shadow);
}
.btn-rfcs.primary:hover{
  background:#be123c;
  transform:translateY(-1px);
}
.btn-rfcs.icon{
  width:30px;
  height:30px;
  padding:0;
  border-radius:999px;
}
.btn-rfcs.ghost{
  background:#f9fafb;
  border-color:#d1d5db;
}
.btn-rfcs.icon:hover{
  background:#f3f4f6;
}

/* Encabezado y filas */
.rfcs-neo-head,
.rfcs-neo-row{
  display:grid;
  grid-template-columns:46px 220px minmax(320px,1fr) 140px 140px;
  align-items:center;
}
.rfcs-neo-head{
  padding:8px 14px;
  border-radius:14px;
  border:1px solid var(--bd);
  background:#f9fafb;
  color:#64748b;
  font:900 11px/1 'Poppins';
  text-transform:uppercase;
  letter-spacing:.08em;
}
.rfcs-neo-head > div{
  padding:0 4px;
  display:flex;
  align-items:center;
}
.rfcs-neo-head .c-status,
.rfcs-neo-head .c-actions{
  justify-content:center;
}
.c-chevron{justify-content:center}
.c-status{justify-content:center}
.c-actions{justify-content:center;gap:8px}

.rfcs-neo-row{
  margin-top:8px;
  border-radius:16px;
  border:1px solid var(--bd);
  background:#ffffff;
  box-shadow:var(--shadow);
}
html[data-theme="dark"] .rfcs-neo-row{
  background:#020617;
}
.rfcs-neo-row > div{
  padding:10px 14px;
  display:flex;
  align-items:center;
  gap:10px;
}

/* Chevron */
.chev{
  width:26px;
  height:26px;
  border-radius:999px;
  border:1px solid var(--bd);
  background:#f9fafb;
  cursor:pointer;
  font-size:12px;
}
.chev:hover{
  background:#e5e7eb;
}

/* Pills RFC / Alias */
.pill-form{
  position:relative;
  display:flex;
  align-items:center;
  width:100%;
}
.pill-input{
  width:100%;
  border-radius:999px;
  border:1px solid var(--bd);
  background:#f9fafb;
  padding:7px 30px 7px 12px;
  font:800 12.5px/1 'Poppins';
  color:#111827;
}
.pill-input.mono{
  font-family:ui-monospace,Menlo,Consolas,monospace;
}
.pill-alias::placeholder{
  color:#9ca3af;
  font-weight:500;
}
.pill-input:focus{
  outline:none;
  border-color:#fb7185;
  background:#fff;
}
.pill-icon{
  position:absolute;
  right:8px;
  width:20px;
  height:20px;
  border-radius:999px;
  border:0;
  background:#ffffff;
  cursor:pointer;
  font-size:12px;
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
  padding:4px 12px;
  border-radius:999px;
  font:900 11px/1 'Poppins';
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

/* Panel CSD debajo de la fila */
.rfcs-neo-panel{
  margin:10px 0 0 46px;
  border-radius:18px;
  border:1px solid rgba(148,163,184,.25);
  padding:14px 18px 12px;
  background:linear-gradient(180deg,#f9fafb,#ffffff);
  box-shadow:0 10px 26px rgba(15,23,42,.05);
}
html[data-theme="dark"] .rfcs-neo-panel{
  background:linear-gradient(180deg,#020617,#020617);
}
.rfcs-neo-panel-grid{
  display:grid;
  grid-template-columns:repeat(3,minmax(180px,1fr));
  column-gap:18px;
  row-gap:10px;
  align-items:flex-start;
}
.rfcs-neo-panel-grid .input{
  width:100%;
  border-radius:999px;
  border:1px dashed #d1d5db;
  padding:7px 12px;
  font:600 12px/1 'Poppins';
  background:#f9fafb;
  color:#111827;
}
.fld span{
  display:block;
  font:800 11.5px/1 'Poppins';
  color:#6b7280;
  margin-bottom:4px;
}

/* Botones + nota panel CSD */
.panel-actions{
  grid-column:1 / -1;
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:10px;
  margin-top:8px;
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

/* Vacíos / pie */
.rfcs-neo-empty{
  margin-top:10px;
  padding:14px;
  text-align:center;
  color:#6b7280;
  font-weight:800;
}
.rfcs-neo-foot{
  margin-top:8px;
  font-size:11px;
  color:#6b7280;
}

/* Responsive */
@media(max-width:1100px){
  .rfcs-neo-head,
  .rfcs-neo-row{
    grid-template-columns:40px 150px minmax(200px,1fr) 110px 100px;
  }
  .rfcs-neo-panel{
    margin-left:0;
    margin-top:6px;
  }
  .rfcs-neo-panel-grid{
    grid-template-columns:1fr;
  }
  .panel-actions{
    flex-direction:column;
    align-items:flex-start;
  }
}
</style>
@endpush

@push('scripts')
<script>
(function(){
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

  // Chevron / botón escudo -> abrir/cerrar panel CSD
  document.querySelectorAll('.rfcs-neo .chev, .rfcs-neo [data-open-panel]').forEach(btn => {
    btn.addEventListener('click', () => {
      const row = btn.closest('[data-row]');
      const panelId = btn.dataset.openPanel || (row ? row.dataset.row + '_panel' : null);
      if(!panelId) return;
      const panel = document.getElementById(panelId);
      if(!panel) return;
      const isOpen = !panel.hasAttribute('hidden');

      if(isOpen){
        panel.setAttribute('hidden','');
        panel.setAttribute('aria-hidden','true');
      }else{
        panel.removeAttribute('hidden');
        panel.setAttribute('aria-hidden','false');
      }
    });
  });

  // Botón ⟳ -> dispara submit del form de RFC
  document.querySelectorAll('.rfcs-neo .js-rfc-apply').forEach(btn => {
    btn.addEventListener('click', () => {
      const row = btn.closest('.rfcs-neo-row');
      const f   = row ? row.querySelector('.js-rfc-form') : null;
      if(f) f.submit();
    });
  });

})();
</script>
@endpush
