<!-- Config global vía <meta> (evita Blade dentro de <script>) -->
<meta name="p360-csrf"  content="{{ csrf_token() }}">
<meta name="p360-base"  content="{{ url('/') }}">
<meta name="p360-admin" content="{{ url('/admin') }}">
<meta name="p360-env"   content="{{ app()->environment() }}">

<script>
  // Lee config desde metatags para no usar directivas Blade dentro del script
  (function () {
    function m(name){ var el=document.querySelector('meta[name="'+name+'"]'); return el ? el.content : ''; }
    window.P360 = {
      csrf:      m('p360-csrf'),
      baseUrl:   m('p360-base'),
      adminBase: m('p360-admin'),
      env:       m('p360-env'),
    };
  })();
</script>

<!-- JS del panel (siempre presente) -->
<script defer src="{{ asset('assets/admin/js/dashboard.js') }}"></script>

<!-- NovaBot: carga perezosa para no bloquear la pintura inicial -->
<script>
  (function () {
    function loadNova() {
      var s = document.createElement('script');
      s.src = "{{ asset('assets/admin/js/novabot.js') }}";
      s.defer = true;
      document.head.appendChild(s);
    }
    if ('requestIdleCallback' in window) {
      requestIdleCallback(loadNova, { timeout: 1500 });
    } else {
      setTimeout(loadNova, 600);
    }
  })();
</script>

<!-- IMPORTANTE: Chart.js no se carga globalmente.
     Cárgalo SOLO en las vistas que lo necesiten (p. ej. home.blade.php):
     @push('scripts')
       <script src="https://cdn.jsdelivr.net/npm/chart.js@4" defer></script>
       <script defer src="{{ asset('assets/admin/js/home.js') }}"></script>
     @endpush
-->
