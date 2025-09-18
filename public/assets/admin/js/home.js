/* Loader robusto del módulo /home/index.js */
(function () {
  var here = document.currentScript && document.currentScript.src ? document.currentScript.src : '';
  var baseFromHere = '';
  try {
    var u = new URL(here, window.location.origin);
    baseFromHere = u.pathname.replace(/home\.js(?:\?.*)?$/,'home/');
  } catch(_) {}

  function join(a,b){
    if (!a) a = '/assets/admin/js/home/';
    if (!/\/$/.test(a)) a += '/';
    return a + (b || '');
  }

  var base = window.P360_HOME_BASE || baseFromHere || '/assets/admin/js/home/';
  var m = document.createElement('script');
  m.type = 'module';
  m.src = join(base, 'index.js');
  document.head.appendChild(m);

  try { console.log('[Home] index.js cargado (module):', m.src); } catch(_){}
})();
