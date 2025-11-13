(function(){
  const evName = 'p360:mode-change';

  function isDemo(key){
    try{
      const u = new URL(window.location.href);
      const qp = u.searchParams.get('demo');
      if (qp === '1') return true;
      if (qp === '0') return false;
    }catch(_){}
    return localStorage.getItem(key || 'p360_demo_mode') === '1';
  }

  function setDemo(on, key){
    try { localStorage.setItem(key || 'p360_demo_mode', on ? '1' : '0'); } catch(_){}
    window.dispatchEvent(new CustomEvent(evName, { detail: { demo: !!on, storageKey: key||'p360_demo_mode' }}));
  }

  window.P360Demo = { isDemo, setDemo };
})();
