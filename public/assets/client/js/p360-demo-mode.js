(function(){
  const evName = 'p360:mode-change';
  const storageKeyDefault = 'p360_demo_mode';
  const endpoint = '/cliente/ui/demo-mode';

  function getCsrf(){
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
  }

  function isDemo(key){
    const k = key || storageKeyDefault;

    // Query param tiene prioridad (si viene)
    try{
      const u = new URL(window.location.href);
      const qp = u.searchParams.get('demo');
      if (qp === '1') return true;
      if (qp === '0') return false;
    }catch(_){}

    try{
      return localStorage.getItem(k) === '1';
    }catch(_){
      return false;
    }
  }

  function persistLocal(on, key){
    const k = key || storageKeyDefault;
    try { localStorage.setItem(k, on ? '1' : '0'); } catch(_){}
  }

  async function syncServer(on){
    // Si no hay CSRF (raro) o no hay fetch, no bloqueamos UX
    try{
      const csrf = getCsrf();
      if (!csrf || !window.fetch) return;

      await fetch(endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf,
          'Accept': 'application/json'
        },
        body: JSON.stringify({ demo: !!on })
      });
    }catch(_){
      // Silencioso: en prod regresará 403; en local debe funcionar
    }
  }

  function emit(on, key){
    window.dispatchEvent(new CustomEvent(evName, {
      detail: { demo: !!on, storageKey: key || storageKeyDefault }
    }));
  }

  /**
   * Set DEMO:
   * - Persiste en localStorage
   * - Sincroniza a sesión vía POST (para que PHP lo lea)
   * - Emite evento global
   */
  async function setDemo(on, key){
    const k = key || storageKeyDefault;
    persistLocal(!!on, k);
    await syncServer(!!on);
    emit(!!on, k);
  }

  /**
   * Al cargar:
   * - Determina el estado desde query/localStorage
   * - Si viene query (?demo=1/0), lo persiste en localStorage
   * - Siempre sincroniza a sesión (solo aplica en local; en prod será 403 y no pasa nada)
   */
  async function boot(){
    const k = storageKeyDefault;

    // Si viene query param, lo persistimos local para consistencia
    try{
      const u = new URL(window.location.href);
      const qp = u.searchParams.get('demo');
      if (qp === '1') persistLocal(true, k);
      if (qp === '0') persistLocal(false, k);
    }catch(_){}

    const current = isDemo(k);
    await syncServer(current);
    emit(current, k);
  }

  window.P360Demo = { isDemo, setDemo };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once:true });
  } else {
    boot();
  }
})();
