{{-- resources/views/admin/generic/_placeholder_body.blade.php --}}
@php
  $t  = $title ?? 'Módulo';
  $co = $company ?? 'PACTOPIA 360';
@endphp
<style>
  .ph-wrap{display:grid;gap:16px}
  .ph-hero{background:linear-gradient(180deg,rgba(99,102,241,.12),transparent);border:1px solid rgba(0,0,0,.08);border-radius:16px;padding:18px}
  html.theme-dark .ph-hero{border-color:rgba(255,255,255,.12)}
  .ph-hero .k{display:inline-flex;gap:8px;align-items:center;font-weight:700}
  .ph-hero .t{font-size:22px;font-weight:800;margin-top:8px}
  .ph-grid{display:grid;grid-template-columns:repeat(12,1fr);gap:14px}
  .ph-card{grid-column:span 12;background:var(--sb-bg,#fff);border:1px solid rgba(0,0,0,.08);border-radius:14px;padding:16px}
  html.theme-dark .ph-card{border-color:rgba(255,255,255,.12)}
  @media(min-width:900px){.ph-card.sm-4{grid-column:span 4}}
  .muted{color:#64748b} html.theme-dark .muted{color:#94a3b8}
  .btn{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:10px;border:1px solid rgba(0,0,0,.12);background:transparent;text-decoration:none}
  html.theme-dark .btn{border-color:rgba(255,255,255,.15)}
</style>

<div class="ph-wrap">
  <div class="ph-hero">
    <div class="k"><span>🏗️</span> <span>{{ $co }}</span></div>
    <div class="t">{{ $t }}</div>
    <div class="muted">Módulo en preparación. Puedes navegar; el enlace ya es funcional.</div>
  </div>

  <div class="ph-grid">
    <div class="ph-card sm-4">
      <h4>Siguiente paso</h4>
      <div class="muted">Sustituye este placeholder por el controlador definitivo cuando esté listo.</div>
    </div>
    <div class="ph-card sm-4">
      <h4>Comprobación rápida</h4>
      <a class="btn" href="{{ route('admin.home') }}">Ir al Home</a>
    </div>
    <div class="ph-card sm-4">
      <h4>Robots</h4>
      <button class="btn" type="button" onclick="try{NovaBot.open()}catch{}">Abrir NovaBot</button>
    </div>
  </div>
</div>
