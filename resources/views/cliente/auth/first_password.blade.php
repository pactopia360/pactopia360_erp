@extends('layouts.cliente')

@section('title','Establece tu nueva contraseña')

@push('styles')
<style>
  /* ===== Shell local para centrar y adaptar al sidebar ===== */
  :root{
    --header-h: var(--header-h, 64px);
    --sb-w: 260px;      /* ancho expandido (debe coincidir con <x-client.sidebar>) */
    --sb-mini: 68px;    /* ancho colapsado */
    --footer-h: 56px;
    --max-w: 720px;     /* ancho de esta pantalla en particular */
  }

  .p360-main{
    min-height: calc(100dvh - var(--header-h) - var(--footer-h));
    padding: 18px 18px 24px;
    margin-left: var(--sb-w);
    transition: margin-left .18s ease;
  }
  .p360-container{ max-width: var(--max-w); margin-inline: auto; }

  .p360-footer{
    position: sticky; bottom: 0; z-index: 9;
    background: color-mix(in oklab, var(--card,#fff) 92%, transparent);
    border-top: 1px solid var(--bd,#e5e7eb);
    height: var(--footer-h);
    display: flex; align-items: center;
    margin-left: var(--sb-w);
    transition: margin-left .18s ease, background .18s ease, border-color .18s ease;
    backdrop-filter: saturate(140%) blur(6px);
  }
  html.theme-dark .p360-footer{
    background: color-mix(in oklab, #0b1220 86%, transparent);
    border-top-color: rgba(255,255,255,.12);
  }
  .p360-footer-in{
    width: 100%; max-width: var(--max-w); margin: 0 auto;
    display:flex; align-items:center; justify-content:space-between; gap:10px;
    padding: 0 18px; font: 500 13px/1.2 system-ui; color: color-mix(in oklab, var(--ink,#0f172a) 75%, transparent);
  }
  .p360-footer a{ color:inherit; text-decoration:none } .p360-footer a:hover{ text-decoration:underline }

  /* ===== Estilos propios del card ===== */
  .card{background:var(--card,#fff);border:1px solid var(--bd,#e5e7eb);border-radius:12px}
  .card .hd{padding:14px 16px;font-weight:800;border-bottom:1px solid var(--bd,#e5e7eb)}
  .card .bd{padding:16px}
  .muted{color:var(--muted,#6b7280);font-size:.92rem}
  .btn{display:inline-flex;align-items:center;gap:8px;border-radius:10px;padding:10px 14px;border:1px solid transparent;text-decoration:none}
  .btn-primary{background:#2563eb;color:#fff}
  .btn-light{background:#f3f4f6;color:#111827;border-color:#e5e7eb}
  .grid{display:grid;gap:12px}
  .field label{display:block;font-size:.9rem;margin-bottom:6px}
  .field input{width:100%;padding:10px 12px;border:1px solid #e5e7eb;border-radius:10px;background:#fff}
  .alert{background:#fee2e2;border:1px solid #fecaca;color:#991b1b;border-radius:10px;padding:10px 12px}

  @media (max-width:1120px){
    :root{ --sb-w: 0px; }
    .p360-main{ margin-left: 0; }
    .p360-footer{ margin-left: 0; }
  }
</style>
@endpush

@push('scripts')
<script>
/* Tema claro forzado en esta vista (como ya lo tenías) */
document.addEventListener('DOMContentLoaded', function () {
  const html = document.documentElement;
  html.setAttribute('data-theme','light');
  html.classList.remove('theme-dark');
  html.classList.add('theme-light');
});

/* Sincroniza el margen del contenido/footer con el estado del sidebar */
(function(){
  const sb = document.getElementById('clientSidebar') || document.querySelector('.sidebar');
  if(!sb) return;

  const mql = matchMedia('(max-width:1120px)');
  function applySidebarWidth(){
    if (mql.matches){ document.documentElement.style.setProperty('--sb-w', '0px'); return; }
    const state = sb.getAttribute('data-state') || 'expanded';
    document.documentElement.style.setProperty('--sb-w', state === 'collapsed' ? 'var(--sb-mini)' : '260px');
  }
  new MutationObserver(applySidebarWidth).observe(sb, { attributes:true, attributeFilter:['data-state','class'] });
  mql.addEventListener?.('change', applySidebarWidth);
  applySidebarWidth();
})();
</script>
@endpush

@section('content')
  <main class="p360-main" role="main">
    <div class="p360-container first-wrap">
      <div class="card">
        <div class="hd">Establece tu nueva contraseña</div>
        <div class="bd">
          <p class="muted">Por seguridad debes definir una contraseña nueva para continuar.</p>

          @if ($errors->any())
            <div class="alert" style="margin:12px 0">
              <ul class="list-disc ml-5">
                @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
              </ul>
            </div>
          @endif

          <form class="grid" method="POST" action="{{ route('cliente.password.first.store') }}">
            @csrf
            <div class="field">
              <label>Nueva contraseña</label>
              <input name="password" type="password" required minlength="8" autocomplete="new-password">
            </div>
            <div class="field">
              <label>Confirmar contraseña</label>
              <input name="password_confirmation" type="password" required minlength="8" autocomplete="new-password">
            </div>
            <div style="display:flex;gap:10px;margin-top:6px">
              <button class="btn btn-primary" type="submit">Guardar y continuar</button>
              <a class="btn btn-light" href="{{ route('cliente.home') }}">Cancelar</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </main>

  <footer class="p360-footer" role="contentinfo">
    <div class="p360-footer-in">
      <span>© {{ date('Y') }} Pactopia360 · Todos los derechos reservados</span>
      <div class="fx">
        <a href="https://pactopia.com" target="_blank" rel="noopener">Sitio</a>
        <span class="dot">•</span>
        @if (\Illuminate\Support\Facades\Route::has('cliente.terminos'))
          <a href="{{ route('cliente.terminos') }}">Términos</a>
        @endif
      </div>
    </div>
  </footer>
@endsection
