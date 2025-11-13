@extends('layouts.cliente')

@section('title','Crear tu contraseña')
@section('hide-brand','1')

@section('content')
<main class="p360-main" role="main">
  <div class="p360-container">
    <div class="card first-card">
      <h2>Establece tu nueva contraseña</h2>
      <p class="muted">Por seguridad debes definir una contraseña nueva para continuar.</p>

      @if ($errors->any())
        <div class="alert err">
          <strong>Revisa:</strong>
          <ul>
            @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
          </ul>
        </div>
      @endif

      @if (session('status'))
        <div class="alert ok">{{ session('status') }}</div>
      @endif

      <form method="POST" action="{{ route('cliente.password.first.store') }}" class="form">
        @csrf
        <label>Nueva contraseña
          <input type="password" name="password" required minlength="8" autocomplete="new-password">
        </label>

        <label>Confirmar contraseña
          <input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
        </label>

        <div class="btn-row">
          <button class="btn primary" type="submit">Guardar y continuar</button>
          <a class="btn ghost" href="{{ route('cliente.home') }}">Cancelar</a>
        </div>
      </form>
    </div>
  </div>
</main>

<footer class="p360-footer">
  <div class="p360-footer-in">
    <span>© {{ date('Y') }} Pactopia360 · Todos los derechos reservados</span>
    <div class="fx">
      <a href="https://pactopia.com" target="_blank" rel="noopener">Sitio</a>
      <span class="dot">•</span>
      @if (Route::has('cliente.terminos'))
        <a href="{{ route('cliente.terminos') }}">Términos</a>
      @endif
    </div>
  </div>
</footer>
@endsection

@push('styles')
<style>
  :root{
    --header-h: 64px;
    --sb-w: 260px;
    --sb-mini: 68px;
    --footer-h: 56px;
    --max-w: 560px;
  }

  .p360-main{
    min-height: calc(100dvh - var(--header-h) - var(--footer-h));
    padding: 32px 18px;
    margin-left: var(--sb-w);
    transition: margin-left .2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .p360-container{ width:100%; max-width:var(--max-w); }

  .card.first-card{
    background:var(--card,#fff);
    border:1px solid var(--bd,#e5e7eb);
    border-radius:14px;
    padding:24px 28px;
    box-shadow:0 4px 18px rgba(0,0,0,.05);
  }
  .card.first-card h2{margin:0 0 8px;font-weight:800;font-size:1.2rem}
  .muted{color:var(--muted,#6b7280);font-size:.95rem;margin-bottom:16px}

  .form{display:grid;gap:14px}
  label{font-size:.9rem;font-weight:600;display:grid;gap:4px}
  input{
    padding:10px 12px;border:1px solid #e5e7eb;border-radius:10px;
    background:#fff;outline:none;transition:border-color .15s;
  }
  input:focus{border-color:#2563eb;box-shadow:0 0 0 2px rgba(37,99,235,.15)}

  .btn-row{display:flex;gap:10px;margin-top:6px;flex-wrap:wrap}
  .btn{display:inline-flex;align-items:center;gap:8px;border-radius:10px;padding:10px 16px;font-weight:600;cursor:pointer;text-decoration:none;border:1px solid transparent}
  .btn.primary{background:#E11D48;color:#fff}
  .btn.primary:hover{background:#BE123C}
  .btn.ghost{background:#f9fafb;color:#111827;border-color:#e5e7eb}
  .btn.ghost:hover{background:#f3f4f6}

  .alert{border-radius:10px;padding:10px 14px;font-size:.9rem;margin-bottom:12px}
  .alert.err{background:#fee2e2;border:1px solid #fecaca;color:#991b1b}
  .alert.ok{background:#dcfce7;border:1px solid #86efac;color:#166534}

  .p360-footer{
    position:sticky;bottom:0;z-index:9;
    background:color-mix(in oklab,var(--card,#fff) 92%,transparent);
    border-top:1px solid var(--bd,#e5e7eb);
    height:var(--footer-h);
    display:flex;align-items:center;
    margin-left:var(--sb-w);
    transition:margin-left .2s ease;
    backdrop-filter:saturate(140%) blur(6px);
  }
  html.theme-dark .p360-footer{
    background:color-mix(in oklab,#0b1220 86%,transparent);
    border-top-color:rgba(255,255,255,.12);
  }
  .p360-footer-in{
    width:100%;max-width:var(--max-w);margin:0 auto;
    display:flex;align-items:center;justify-content:space-between;gap:10px;
    padding:0 18px;font:500 13px/1.2 system-ui;color:color-mix(in oklab,var(--ink,#0f172a) 75%,transparent);
  }
  .p360-footer a{color:inherit;text-decoration:none}
  .p360-footer a:hover{text-decoration:underline}
  .p360-footer .fx{display:flex;align-items:center;gap:8px}
  .p360-footer .dot{opacity:.6}

  @media(max-width:1120px){
    :root{--sb-w:0}
    .p360-main{margin-left:0;padding:24px 16px}
    .p360-footer{margin-left:0}
  }
</style>
@endpush

@push('scripts')
<script>
(function(){
  const sb=document.getElementById('clientSidebar')||document.querySelector('.sidebar');
  if(!sb)return;
  const mql=matchMedia('(max-width:1120px)');
  function syncSidebar(){
    if(mql.matches){document.documentElement.style.setProperty('--sb-w','0px');return;}
    const state=sb.getAttribute('data-state')||'expanded';
    document.documentElement.style.setProperty('--sb-w',state==='collapsed'?'var(--sb-mini)':'260px');
  }
  new MutationObserver(syncSidebar).observe(sb,{attributes:true,attributeFilter:['data-state','class']});
  mql.addEventListener?.('change',syncSidebar);
  syncSidebar();
})();
</script>
@endpush
