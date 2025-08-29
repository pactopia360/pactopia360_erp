/* Loader clásico que inyecta el módulo ES de /home/index.js sin tocar la Blade */
(function () {
  var s = document.currentScript;
  var base = (s && s.src) ? s.src.replace(/home\.js(\?.*)?$/,'home/') : (window.P360_HOME_BASE || '/assets/admin/js/home/');
  var m = document.createElement('script');
  m.type = 'module';
  m.src = base + 'index.js';
  document.head.appendChild(m);
  console.log('[Home] index.js cargado (module)');
})();
