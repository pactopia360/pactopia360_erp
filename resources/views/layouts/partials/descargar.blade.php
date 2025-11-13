{{-- resources/views/layouts/partials/descargar.blade.php (v3) --}}
@php
  // UID único por instancia (solo el wrapper lleva id, útil si incluyes varias tarjetas en la misma vista)
  $uid = isset($uid) && $uid !== ''
      ? preg_replace('/[^A-Za-z0-9_-]/','',$uid)
      : ('dl'.substr(md5(uniqid('',true)),0,6));
@endphp

<div class="card sat-card" id="{{ $uid }}">
  <div class="hd">3) Descargar paquete</div>

  <div class="bd">
    <form method="post"
          action="{{ route('cliente.sat.download') }}"
          class="grid"
          style="display:grid; gap:12px"
          data-role="form"
          onsubmit="return p360DlSubmit(event)">
      @csrf

      <div>
        <label class="form-label">ID de solicitud (<code>sat_downloads.id</code>)</label>
        <input type="number"
               name="download_id"
               class="form-control"
               inputmode="numeric"
               min="1"
               step="1"
               required
               data-role="dl-id">
        <div class="muted">Corresponde al ID interno de la solicitud en tu base.</div>
      </div>

      <div>
        <label class="form-label">Package ID (SAT) <span class="muted">(opcional)</span></label>
        <div class="input-group" style="display:flex; gap:8px; align-items:center">
          <input type="text"
                 name="package_id"
                 class="form-control mono"
                 placeholder="UUID del paquete SAT"
                 data-role="dl-pkg"
                 autocomplete="off"
                 spellcheck="false"
                 inputmode="latin">
          <button type="button" class="btn btn-outline" data-action="paste" title="Pegar desde portapapeles">Pegar</button>
          <button type="button" class="btn btn-outline" data-action="copy"  title="Copiar al portapapeles">Copiar</button>
        </div>
        <div class="muted">
          Si lo dejas vacío, se usará el <em>package_id</em> guardado en la solicitud.
          Acepta UUID (36/32 chars) o el identificador devuelto por el SAT.
        </div>
      </div>

      <div style="display:flex; gap:10px; align-items:center">
        <button class="btn btn-success" id="btnDlZip">
          <span class="btn-txt">Descargar ZIP</span>
        </button>
        <span class="muted">Se descargará el ZIP con XML/PDF si el paquete está listo.</span>
      </div>
    </form>
  </div>
</div>

{{-- Estilos mínimos scoped (funciona con o sin Bootstrap) --}}
<style>
  .sat-card .hd{ font-weight:800; padding:10px 0 }
  .sat-card .bd{ padding: 0 }
  .sat-card .mono{ font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace }
  .sat-card .muted{ color: var(--muted,#6b7280); font-size:12px }
</style>

@pushOnce('scripts', 'sat-descargar')
<script>
(function(){
  // Mejora progresiva para cada tarjeta con id único
  document.querySelectorAll('.sat-card[id] [data-role="form"]').forEach(function(form){
    const root = form.closest('.sat-card[id]');
    if (!root || root.dataset.enhanced === '1') return;
    root.dataset.enhanced = '1';

    const q   = sel => root.querySelector(sel);
    const idI = q('[data-role="dl-id"]');
    const pkg = q('[data-role="dl-pkg"]');

    // Normaliza al teclear: recorta, mayúsculas
    pkg?.addEventListener('input', () => {
      pkg.value = (pkg.value || '').trim().toUpperCase();
    });

    // Pegado / Copiado
    q('[data-action="paste"]')?.addEventListener('click', async () => {
      try {
        const text = (await navigator.clipboard.readText() || '').trim();
        if (text) { pkg.value = text.toUpperCase(); }
      } catch(e){ /* permiso denegado o no disponible */ }
    });
    q('[data-action="copy"]')?.addEventListener('click', async () => {
      try { await navigator.clipboard.writeText(pkg.value || ''); } catch(e){}
    });

    // Enter en inputs → submit
    [idI, pkg].forEach(el => el?.addEventListener('keydown', e=>{
      if(e.key === 'Enter'){ form.requestSubmit?.(); }
    }));
  });
})();

function p360DlSubmit(ev){
  const form = ev.target;
  const btn  = form.querySelector('#btnDlZip');
  const txt  = btn?.querySelector('.btn-txt');
  const idI  = form.querySelector('[data-role="dl-id"]');
  const pkg  = form.querySelector('[data-role="dl-pkg"]');

  // Validaciones ligeras
  const idVal = Number(idI?.value || 0);
  if (!idVal || idVal < 1 || !Number.isFinite(idVal)) {
    alert('Ingresa un ID de solicitud válido (número entero).');
    ev.preventDefault(); return false;
  }
  if (pkg && pkg.value) {
    const v = pkg.value.trim().toUpperCase();
    // Acepta UUID con o sin guiones (36 o 32) u otros IDs SAT largos
    const looksUuid = /^[0-9A-F]{8}-?[0-9A-F]{4}-?[0-9A-F]{4}-?[0-9A-F]{4}-?[0-9A-F]{12}$/.test(v);
    if (!looksUuid && v.length < 20) {
      if(!confirm('El Package ID no parece un UUID. ¿Deseas continuar de todos modos?')){
        ev.preventDefault(); return false;
      }
    }
    pkg.value = v;
  }

  // Evita doble envío
  if (btn){
    btn.disabled = true;
    if (txt) txt.textContent = 'Preparando descarga…';
    // Rehabilita el botón si la navegación no ocurre (p.ej. error de servidor)
    setTimeout(()=>{ try{ btn.disabled = false; if(txt) txt.textContent='Descargar ZIP'; }catch(_){ } }, 8000);
  }

  return true;
}
</script>
@endPushOnce
