{{-- resources/views/components/client/demo-toggle.blade.php --}}
@php
  // Props:
  // - $storageKey  clave localStorage (default: 'p360_demo_mode')
  // - $bannerId    id del banner informativo (opcional)
  // - $btnId       id del botón (opcional)
  // - $showBanner  true/false para renderizar el banner
  // - $compact     true/false para versión compacta
  // - $cookieName  nombre de cookie fallback (default 'sat_mode')
  $storageKey = $storageKey ?? 'p360_demo_mode';
  $bannerId   = $bannerId   ?? 'p360DemoBanner';
  $btnId      = $btnId      ?? 'p360DemoToggle';
  $showBanner = ($showBanner ?? true) ? true : false;
  $compact    = ($compact    ?? false) ? true : false;
  $cookieName = $cookieName ?? 'sat_mode';
@endphp

<div class="p360-demo-wrap" style="display:flex;gap:10px;align-items:center">
  <button type="button"
          id="{{ $btnId }}"
          class="p360-mode-pill"
          aria-pressed="false"
          title="Cambiar entre DEMO y Producción"
          data-storage="{{ $storageKey }}"
          data-cookie="{{ $cookieName }}">
    <span class="dot" aria-hidden="true"></span>
    <span class="label">PRODUCCIÓN</span>
    @unless($compact)
      <small>(click para cambiar)</small>
    @endunless
  </button>
</div>

@if($showBanner)
  <div id="{{ $bannerId }}" class="p360-demo-banner" style="display:none">
    <strong>MODO DEMO ACTIVADO:</strong> las acciones usan datos simulados para pruebas rápidas.
  </div>
@endif

<style>
  .p360-mode-pill{display:inline-flex;align-items:center;gap:8px;border:1px solid var(--bd, #e5e7eb);
    border-radius:999px;padding:6px 10px;font-weight:900;background:var(--card,#fff);cursor:pointer;transition:.18s}
  .p360-mode-pill .dot{width:8px;height:8px;border-radius:999px;background:#10b981}
  .p360-mode-pill.demo{border-color:color-mix(in oklab, var(--brand, #E11D48) 40%, var(--bd, #e5e7eb)); background:color-mix(in oklab, var(--brand,#E11D48) 6%, var(--card,#fff))}
  .p360-mode-pill.demo .dot{background:var(--brand,#E11D48)}
  .p360-mode-pill small{font-size:11px;color:var(--muted,#6b7280);font-weight:800}
  .p360-demo-banner{margin-top:8px;padding:10px 12px;border-radius:10px;border:1px dashed color-mix(in oklab, var(--brand,#E11D48) 45%, var(--bd,#e5e7eb));
    background:linear-gradient(180deg, color-mix(in oklab, var(--brand,#E11D48) 10%, transparent), transparent)}
</style>

<script>
(() => {
  // Evita re-ejecutar si se incluye múltiples veces
  if (window.__P360_DEMO_TOGGLE_INIT__) return;
  window.__P360_DEMO_TOGGLE_INIT__ = true;

  // --- Utils ---
  const getCookie = (name) => {
    const m = document.cookie.match('(^|;)\\s*' + (name||'').replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\s*=\\s*([^;]+)');
    return m ? decodeURIComponent(m.pop()) : null;
  };
  const setCookie = (name, val) => {
    if (!name) return;
    const maxAge = 60*60*24*365; // 1 año
    document.cookie = `${encodeURIComponent(name)}=${encodeURIComponent(val)}; path=/; max-age=${maxAge}`;
  };
  const normalize = (m) => (String(m||'').toLowerCase()==='demo' ? 'demo' : 'prod');

  // --- API global mínima (opcional) ---
  const listeners = new Set();
  window.P360DemoMode = {
    get(key='p360_demo_mode', cookieName='sat_mode') {
      try {
        const v = localStorage.getItem(key);
        if (v) return normalize(v);
      } catch(_) {}
      const c = getCookie(cookieName);
      return normalize(c);
    },
    set(mode, {key='p360_demo_mode', cookieName='sat_mode'}={}) {
      const v = normalize(mode);
      try { localStorage.setItem(key, v); } catch(_) {}
      setCookie(cookieName, v);
      // Notifica a listeners
      const detail = { mode:v, source:'api' };
      listeners.forEach(cb => { try{ cb(detail); }catch(_){} });
      document.dispatchEvent(new CustomEvent('p360:demo-mode-changed', { detail }));
      return v;
    },
    subscribe(cb){ if (typeof cb==='function') listeners.add(cb); return () => listeners.delete(cb); }
  };

  // --- Bootstrap por instancia ---
  const buttons = Array.from(document.querySelectorAll('.p360-mode-pill[id]'));
  buttons.forEach(btn => {
    const storageKey = btn.getAttribute('data-storage') || 'p360_demo_mode';
    const cookieName = btn.getAttribute('data-cookie')  || 'sat_mode';

    // banner relacionado (mismo sufijo si existe)
    const btnId = btn.id;
    // heurística: si existe un elemento .p360-demo-banner en el mismo bloque, úsalo
    let banner = btn.parentElement?.nextElementSibling;
    if (!(banner && banner.classList?.contains('p360-demo-banner'))) {
      // fallback: intenta por id común
      const guessId = btnId.replace(/Toggle$/,'Banner');
      banner = document.getElementById(guessId) || document.querySelector('.p360-demo-banner');
    }

    const applyUi = (mode) => {
      const isDemo = mode === 'demo';
      btn.classList.toggle('demo', isDemo);
      btn.setAttribute('aria-pressed', isDemo ? 'true' : 'false');
      const label = btn.querySelector('.label');
      if (label) label.textContent = isDemo ? 'DEMO' : 'PRODUCCIÓN';
      if (banner) banner.style.display = isDemo ? '' : 'none';
    };

    const readInitial = () => {
      // 1) localStorage; 2) cookie; 3) default 'prod'
      try {
        const ls = localStorage.getItem(storageKey);
        if (ls) return normalize(ls);
      } catch(_) {}
      const ck = getCookie(cookieName);
      if (ck) return normalize(ck);
      return 'prod';
    };

    let mode = readInitial();
    applyUi(mode);

    const commit = (next, source='toggle') => {
      next = normalize(next);
      if (next === mode) return;
      mode = next;

      // Persistencia
      try { localStorage.setItem(storageKey, next); } catch(_) {}
      setCookie(cookieName, next);

      // UI + evento
      applyUi(next);
      const detail = { mode: next, source, buttonId: btnId };
      document.dispatchEvent(new CustomEvent('p360:demo-mode-changed', { detail }));
      listeners.forEach(cb => { try{ cb(detail); }catch(_){} });
    };

    btn.addEventListener('click', () => {
      commit(mode === 'demo' ? 'prod' : 'demo', 'click');
    });

    // Sincroniza si otro componente cambia el modo vía API
    window.P360DemoMode.subscribe(({mode:updated}) => {
      applyUi(normalize(updated));
    });
  });
})();
</script>
