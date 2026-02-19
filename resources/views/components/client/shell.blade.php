{{-- resources/views/components/client/shell.blade.php --}}
{{-- P360 Client Layout Shell (header + sidebar + main + footer) --}}
@props([
  // Ancho expandido/colapsado del sidebar (debe coincidir con <x-client.sidebar/>)
  'sidebarWide'  => 260,
  'sidebarMini'  => 68,
  // Ancho máximo centrado del contenido
  'maxWidth'     => 1220,
])

@php
  $sbWide = (int) $sidebarWide;
  $sbMini = (int) $sidebarMini;
  $maxW   = (int) $maxWidth;

  $hasTerms = \Illuminate\Support\Facades\Route::has('cliente.terminos');
@endphp

{{-- ====== HEAD (header fijo superior) ====== --}}
@include('layouts.partials.client_header')

{{-- ====== SIDEBAR (sticky izquierda) ====== --}}
<x-client.sidebar id="clientSidebar" :isOpen="true" aria-label="Navegación" />

{{-- ====== MAIN WRAPPER ====== --}}
<main id="p360-main" class="p360-main" role="main" tabindex="-1">
  <div class="p360-container">
    {{ $slot ?? '' }}
    @yield('content')
  </div>
</main>

{{-- ====== FOOTER (sticky al final, no tapa contenido) ====== --}}
<footer class="p360-footer" role="contentinfo">
  <div class="p360-footer-in">
    <span>© {{ date('Y') }} Pactopia360 · Todos los derechos reservados</span>
    <div class="fx">
      <a href="{{ config('p360.public.site_url') }}" target="_blank" rel="noopener">Sitio</a>
      <span class="dot">•</span>
      @if($hasTerms)
        <a href="{{ route('cliente.terminos') }}">Términos</a>
      @else
        <a href="#" aria-disabled="true">Términos</a>
      @endif
    </div>
  </div>
</footer>

{{-- ====== ESTILOS GLOBALES DEL SHELL ====== --}}
<style>
  :root{
    /* El header ya define --header-h; proveemos fallback */
    --header-h: var(--header-h, 64px);
    --sb-w: {{ $sbWide }}px;       /* se sincroniza por JS al colapsar */
    --sb-mini: {{ $sbMini }}px;
    --footer-h: 56px;
    --max-w: {{ $maxW }}px;

    /* Fallback de tokens si no existen en el tema */
    --page: var(--page, #fff);
    --card: var(--card, #fff);
    --bd:   var(--bd, #e5e7eb);
    --ink:  var(--ink, #0f172a);
  }

  /* Contenedor general: header fijo, contenido ocupa mínimo el viewport, footer al final */
  body.p360-shell{ min-height:100dvh; background:var(--page); }

  /* MAIN ocupa el espacio entre header/footer y deja margen al sidebar en desktop */
  .p360-main{
    min-height: calc(100dvh - var(--header-h) - var(--footer-h));
    padding: 18px 18px 24px;
    /* separa contenido del header fijo real */
    padding-top: calc(18px + var(--header-h));
    margin-left: var(--sb-w);
    transition: margin-left .18s ease;
    outline: none; /* para enfoque programático */
  }

  /* Contenido centrado */
  .p360-container{
    max-width: var(--max-w);
    margin-inline: auto;
  }

  /* FOOTER sticky (no fixed): empuja con el flujo y siempre al final */
  .p360-footer{
    position: sticky;
    bottom: 0;
    z-index: 9;
    background: color-mix(in oklab, var(--card) 92%, transparent);
    border-top: 1px solid var(--bd);
    height: var(--footer-h);
    display: flex;
    align-items: center;
    margin-left: var(--sb-w);
    transition: margin-left .18s ease, background .18s ease, border-color .18s ease;
    backdrop-filter: saturate(140%) blur(6px);
  }
  html.theme-dark .p360-footer,
  html[data-theme="dark"] .p360-footer{
    background: color-mix(in oklab, #0b1220 86%, transparent);
    border-top-color: rgba(255,255,255,.12);
  }
  .p360-footer-in{
    width: 100%;
    max-width: var(--max-w);
    margin: 0 auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 0 18px;
    font: 500 13px/1.2 system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
    color: color-mix(in oklab, var(--ink) 75%, transparent);
  }
  .p360-footer a{ color: inherit; text-decoration: none }
  .p360-footer a:hover{ text-decoration: underline }
  .p360-footer .fx{ display:flex; align-items:center; gap:8px }
  .p360-footer .dot{ opacity:.6 }

  /* ====== MÓVIL: el sidebar es overlay, el contenido va a ancho completo ====== */
  @media (max-width: 1120px){
    :root{ --sb-w: 0px; }
    .p360-main{ margin-left: 0 }
    .p360-footer{ margin-left: 0 }
  }
</style>

{{-- ====== SINCRONIZACIÓN SHELL ⇆ SIDEBAR (colapsado/expandido) ====== --}}
<script>
(function(){
  // Marca el body para que apliquen estilos del shell
  document.body.classList.add('p360-shell');

  const sb = document.getElementById('clientSidebar') || document.querySelector('.sidebar');
  if(!sb) return;

  const mq = matchMedia('(max-width:1120px)');
  const isMobile = () => mq.matches;

  function applySidebarWidth(){
    // En móvil no reservamos ancho
    if (isMobile()){
      document.documentElement.style.setProperty('--sb-w', '0px');
      return;
    }
    const state = sb.getAttribute('data-state') || 'expanded';
    document.documentElement.style.setProperty('--sb-w', (state === 'collapsed') ? '{{ $sbMini }}px' : '{{ $sbWide }}px');
  }

  // Observa cambios en el atributo data-state del sidebar
  const ob = new MutationObserver(applySidebarWidth);
  ob.observe(sb, { attributes: true, attributeFilter: ['data-state', 'class'] });

  // Recalcula al cambiar viewport (fallback a addListener para Safari viejito)
  if (mq.addEventListener) mq.addEventListener('change', applySidebarWidth);
  else if (mq.addListener) mq.addListener(applySidebarWidth);

  // Primera ejecución
  applySidebarWidth();

  // Mejora de accesibilidad: enfocar main tras pulsar atajos que cambien de ruta
  window.addEventListener('hashchange', ()=>document.getElementById('p360-main')?.focus(), {passive:true});
})();
</script>
