{{-- resources/views/cliente/sat/_partials/rfcs.blade.php
     v17 · RFCs: diseño limpio + guardado/validación por AJAX compatible --}}
@php
  /**
   * Espera:
   *  - $credList   (array|Collection de RFCs)
   *  - $plan       ('FREE'|'PRO')
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
    <button type="button" class="btn-rfcs primary" id="btnAddRfc" data-tip="Agregar nuevo RFC">
      <span aria-hidden="true">＋</span>
      <span>Agregar RFC</span>
    </button>
  </div>

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

      // Consideramos varias señales de "ok":
      // - campo booleano validado
      // - validated_at lleno
      // - has_files = true (ya tiene .cer y .key)
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

      {{-- RFC editable --}}
      <div class="c-rfc">
        <form method="post"
              action="{{ $rtRfcReg }}"
              class="pill-form js-rfc-form"
              data-kind="rfc">
          @csrf
          {{-- nombres alternos para máxima compatibilidad --}}
          <input type="hidden" name="old_rfc"      value="{{ $rfc }}">
          <input type="hidden" name="rfc_old"      value="{{ $rfc }}">
          <input type="hidden" name="rfc_original" value="{{ $rfc }}">

          <input class="pill-input mono"
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
              class="pill-form js-alias-form"
              data-kind="alias">
          @csrf
          <input type="hidden" name="rfc"   value="{{ $rfc }}">
          {{-- nombres alternos alias / razon_social --}}
          <input type="hidden" name="alias"         value="{{ $alias }}">
          <input type="hidden" name="nombre"        value="{{ $alias }}">
          <input type="hidden" name="razon_social"  value="{{ $alias }}">

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
      </div>
    </div>

    {{-- PANEL DATOS FISCALES (FIEL / CSD) --}}
    <div id="{{ $rowId }}_panel"
         class="rfcs-neo-panel"
         hidden
         aria-hidden="true">
      <form method="post"
            action="{{ $rtCsdStore }}"
            enctype="multipart/form-data"
            class="rfcs-neo-panel-grid js-csd-form"
            data-kind="csd">
        @csrf
        <input type="hidden" name="rfc" value="{{ $rfc }}">

        {{-- fila de inputs --}}
        <label class="fld">
          <span>.cer</span>
          <input type="file"
                 name="cer"
                 accept=".cer"
                 class="input"
                 {{ $okFlag ? '' : 'required' }}>
        </label>

        <label class="fld">
          <span>.key</span>
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

        {{-- fila de acciones / nota --}}
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
                    data-tip="{{ $okFlag ? 'Revalidar FIEL' : 'Validar FIEL' }}">
              {{ $okFlag ? 'Revalidar' : 'Validar' }}
            </button>
          </div>

          <span class="note">
            @if(($plan ?? 'FREE') === 'FREE')
              Listo para <b>solicitudes</b>. Automatizadas: <b>solo Pro</b>.
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
/* ===== RFCs · Neo clean ===== */
.rfcs-neo{
  --bd:var(--bd, #e5e7eb);
  --card:var(--card, #fff);
  --ink:var(--ink, #0f172a);
  --mut:var(--mut, #6b7280);
  --shadow:0 8px 22px rgba(15,23,42,.04);
  border-radius:16px;
}
.rfcs-neo-hd{
  display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:12px;gap:10px;
}
.rfcs-neo-hd h3{
  margin:0;font:900 18px/1.2 'Poppins',system-ui;color:var(--ink);
}
.rfcs-neo-sub{
  margin:2px 0 0;font-size:11px;font-weight:600;color:var(--mut);
}

/* Botones genéricos */
.btn-rfcs{
  display:inline-flex;align-items:center;justify-content:center;gap:6px;
  padding:7px 11px;
  border-radius:999px;
  border:1px solid var(--bd);
  background:var(--card);
  color:var(--ink);
  font:800 12px/1 'Poppins';
  cursor:pointer;
  min-height:32px;
}
.btn-rfcs span[aria-hidden="true"]{font-size:15px}
.btn-rfcs.primary{
  border-color:#fb7185;
  background:#e11d48;
  color:#fff;
  box-shadow:var(--shadow);
}
.btn-rfcs.icon{
  width:30px;height:30px;padding:0;
  border-radius:999px;
}
.btn-rfcs.ghost{
  background:#f9fafb;
  border-color:#d1d5db;
}
.btn-rfcs.icon:hover{
  background:#f3f4f6;
}

/* Head y filas – columnas alineadas */
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
.rfcs-neo-row > div{
  padding:10px 14px;
  display:flex;align-items:center;gap:10px;
}

/* Chevron */
.chev{
  width:26px;height:26px;border-radius:999px;
  border:1px solid var(--bd);background:#f9fafb;
  cursor:pointer;font-size:12px;
}

/* Pills RFC / Alias */
.pill-form{position:relative;display:flex;align-items:center;width:100%}
.pill-input{
  width:100%;
  border-radius:999px;
  border:1px solid var(--bd);
  background:#f9fafb;
  padding:7px 30px 7px 12px;
  font:800 12.5px/1 'Poppins';
  color:var(--ink);
}
.pill-input.mono{
  font-family:ui-monospace,Menlo,Consolas,monospace;
}
.pill-alias::placeholder{
  color:#9ca3af;font-weight:500;
}
.pill-input:focus{
  outline:none;border-color:#fb7185;background:#fff;
}
.pill-icon{
  position:absolute;right:8px;
  width:20px;height:20px;border-radius:999px;
  border:0;background:#ffffff;cursor:pointer;font-size:12px;
  display:flex;align-items:center;justify-content:center;
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

/* Panel de FIEL – versión más limpia */
.rfcs-neo-panel{
  margin:10px 0 0 46px;
  border-radius:18px;
  border:1px solid rgba(148,163,184,.25);
  padding:14px 18px 12px;
  background:linear-gradient(180deg,#f9fafb,#ffffff);
  box-shadow:0 10px 26px rgba(15,23,42,.05);
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
}
.fld span{
  display:block;font:800 11.5px/1 'Poppins';
  color:var(--mut);margin-bottom:4px;
}

/* Botones + nota */
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
.note{color:var(--mut);font-weight:600;font-size:11px}

/* Vacío / footer */
.rfcs-neo-empty{
  margin-top:10px;padding:14px;
  text-align:center;color:var(--mut);font-weight:800;
}
.rfcs-neo-foot{
  margin-top:8px;font-size:11px;color:var(--mut);
}

/* Responsive */
@media(max-width:1100px){
  .rfcs-neo-head,
  .rfcs-neo-row{
    grid-template-columns:40px 150px minmax(200px,1fr) 110px 100px;
  }
  .rfcs-neo-panel{
    margin-left:0;margin-top:6px;
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
  const csrf = '{{ csrf_token() }}';

  // Sincroniza alias visible con los campos ocultos alias/razon_social
  document.querySelectorAll('.rfcs-neo .js-alias-form').forEach(form=>{
    const visible = form.querySelector('[data-field="alias-visible"]');
    const syncHidden = () => {
      const v = visible.value || '';
      form.querySelectorAll('input[name="alias"],input[name="nombre"],input[name="razon_social"]').forEach(h=>{
        h.value = v;
      });
    };
    if(visible){
      visible.addEventListener('input', syncHidden);
      syncHidden();
    }
  });

  // Toggle panel desde chevron o botón con data-open-panel
  document.querySelectorAll('.rfcs-neo .chev, .rfcs-neo [data-open-panel]').forEach(btn=>{
    btn.addEventListener('click',()=>{
      const row = btn.closest('[data-row]');
      const panelId = btn.dataset.openPanel || (row ? row.dataset.row + '_panel' : null);
      if(!panelId) return;
      const panel = document.getElementById(panelId);
      if(!panel) return;
      const open = !panel.hasAttribute('hidden');
      if(open){
        panel.setAttribute('hidden','');
        panel.setAttribute('aria-hidden','true');
      }else{
        panel.removeAttribute('hidden');
        panel.setAttribute('aria-hidden','false');
      }
    });
  });

  // Botón "⟳" dispara submit del form RFC
  document.querySelectorAll('.rfcs-neo .js-rfc-apply').forEach(btn=>{
    btn.addEventListener('click',()=>{
      const row = btn.closest('.rfcs-neo-row');
      const f   = row?.querySelector('.js-rfc-form');
      if(f) f.dispatchEvent(new Event('submit', {cancelable:true,bubbles:true}));
    });
  });

  // Enviar formularios por AJAX
  document.querySelectorAll('.rfcs-neo form').forEach(form=>{
    form.addEventListener('submit', async (ev)=>{
      ev.preventDefault();

      const kind = form.dataset.kind || '';
      const btn  = form.querySelector('button[type="submit"], .pill-icon');

      if(btn) btn.disabled = true;

      try{
        let res;
        if(kind === 'csd'){
          // con archivos: usar FormData directo
          const fd = new FormData(form);
          res = await fetch(form.action, {
            method:'POST',
            headers:{
              'X-Requested-With':'XMLHttpRequest',
              'X-CSRF-TOKEN': csrf
            },
            body: fd
          });
        }else{
          // RFC / alias: enviamos JSON
          const fd = new FormData(form);
          const plain = {};
          fd.forEach((v,k)=>{ plain[k] = v; });
          res = await fetch(form.action, {
            method:'POST',
            headers:{
              'Content-Type':'application/json',
              'X-Requested-With':'XMLHttpRequest',
              'X-CSRF-TOKEN': csrf
            },
            body: JSON.stringify(plain)
          });
        }

        if(!res.ok){
          console.error('SAT RFCs error HTTP', res.status);
          alert('No se pudo guardar. Revisa la información.');
          return;
        }

        // Ver cambios (estatus, alias, etc.)
        window.location.reload();

      }catch(e){
        console.error('SAT RFCs error', e);
        alert('Error de conexión al guardar.');
      }finally{
        if(btn) btn.disabled = false;
      }
    });
  });

  // CTA Agregar RFC (placeholder)
  const add = document.getElementById('btnAddRfc');
  if(add){
    add.addEventListener('click',()=>{
      alert('Aquí irá el modal "Agregar RFC".');
    });
  }
})();
</script>
@endpush
