{{-- resources/views/layouts/partials/client_footer.blade.php (v4 slim) --}}
@php
  use Illuminate\Support\Facades\Route;

  $rtTerminos = Route::has('cliente.terminos') ? route('cliente.terminos') : url('/cliente/terminos');
  $rtSoporte  = Route::has('cliente.soporte')  ? route('cliente.soporte')  : (Route::has('cliente.soporte.chat') ? route('cliente.soporte.chat') : '#');

  $buildInfo  = trim((string)($__env->yieldContent('build_hash'))) ?: null;
  $envLabel   = app()->environment();
@endphp

<footer role="contentinfo" class="client-footer" aria-label="Pie de página del portal">
  <div class="footer-in">
    <div class="left">
      <small class="brand">© {{ date('Y') }} Pactopia360</small>
      <span class="sep" aria-hidden="true">·</span>
      <small class="muted">Hecho con ❤️ en México</small>
      @if($buildInfo)
        <span class="sep" aria-hidden="true">·</span>
        <small class="muted">Build: <code>{{ $buildInfo }}</code></small>
      @endif
      @if($envLabel !== 'production')
        <span class="sep" aria-hidden="true">·</span>
        <small class="badge-env" title="Entorno de ejecución">{{ strtoupper($envLabel) }}</small>
      @endif
    </div>

    <nav class="right" aria-label="Enlaces legales y soporte">
      <a class="lk" href="{{ $rtTerminos }}">Términos</a>
      <a class="lk" href="{{ $rtSoporte }}">Soporte</a>
    </nav>
  </div>
</footer>

<style>
  .client-footer{ --bd: var(--bd, #e5e7eb); --ink: var(--ink, #0f172a); --card: var(--card, #ffffff); }
  .client-footer .footer-in{ max-width: var(--max-w, 1200px); margin: 0 auto; padding: 10px 18px;
    color: var(--ink); display:flex; align-items:center; justify-content:space-between; gap:10px; }
  .client-footer .left,.client-footer .right{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
  .client-footer .lk{color:inherit;text-decoration:none;border:1px solid color-mix(in oklab, var(--ink) 10%, transparent);
    padding:6px 10px;border-radius:999px;font-weight:800}
  .client-footer .lk:hover{ text-decoration: underline }
  .client-footer .brand{ font-weight: 800 }
  .client-footer .muted{ color: var(--muted, #6b7280) }
  .client-footer .badge-env{ padding:4px 8px; border-radius:999px; border:1px dashed color-mix(in oklab, var(--brand, #E11D48) 40%, var(--bd));
    background: linear-gradient(180deg, color-mix(in oklab, var(--brand, #E11D48) 10%, transparent), transparent); font-weight:800 }
</style>
