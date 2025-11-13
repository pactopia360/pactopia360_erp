{{-- resources/views/cliente/marketplace.blade.php (v4 visual Pactopia360) --}}
@extends('layouts.client')
@section('title','Marketplace · Pactopia360')

@push('styles')
<style>
/* ============================================================
   PACTOPIA360 · Marketplace (visual v4 unificado)
   ============================================================ */
.market{
  font-family:'Poppins',system-ui,sans-serif;
  --rose:#E11D48;--rose-dark:#BE123C;
  --mut:#6b7280;--card:#fff;--border:#f3d5dc;--bg:#fff8f9;
  display:grid;gap:20px;padding:20px;color:#0f172a;
}
html[data-theme="dark"] .market{
  --card:#0b1220;--border:#2b2f36;--bg:#0e172a;--mut:#a5adbb;color:#e5e7eb;
}

/* Header */
.page-header{
  background:linear-gradient(90deg,#E11D48,#BE123C);
  color:#fff;padding:20px 24px;border-radius:16px;
  box-shadow:0 8px 22px rgba(225,29,72,.25);
  display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;
}
.page-title{margin:0;font-weight:900;font-size:22px;}
.page-header .muted{color:rgba(255,255,255,.85);font-size:13px;}

/* Grid */
.grid{
  display:grid;gap:18px;
  grid-template-columns:repeat(auto-fit,minmax(260px,1fr));
}

/* Cards */
.card{
  position:relative;
  background:linear-gradient(180deg,rgba(255,255,255,.96),rgba(255,255,255,.9));
  border:1px solid var(--border);border-radius:18px;
  padding:20px 22px;box-shadow:0 8px 28px rgba(225,29,72,.08);
  overflow:hidden;transition:.25s transform ease, .25s box-shadow ease;
}
.card::before{
  content:"";position:absolute;inset:-1px;border-radius:19px;padding:1px;
  background:linear-gradient(145deg,#E11D48,#BE123C);
  -webkit-mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);
  -webkit-mask-composite:xor;mask-composite:exclude;opacity:.25;pointer-events:none;
}
.card:hover{transform:translateY(-4px);box-shadow:0 10px 32px rgba(225,29,72,.12);}
.card h3{margin:0 0 8px;font-size:16px;font-weight:900;color:#E11D48;}
.card p{margin:0 0 12px;font-size:14px;color:var(--mut);line-height:1.45;}
.card .price{font-size:22px;font-weight:900;color:#BE123C;margin-bottom:8px;}

/* Buttons */
.btn{
  display:inline-flex;align-items:center;justify-content:center;gap:8px;
  padding:10px 16px;border-radius:12px;font-weight:800;font-size:14px;
  border:0;cursor:pointer;text-decoration:none;
  background:linear-gradient(90deg,#E11D48,#BE123C);color:#fff;
  box-shadow:0 6px 18px rgba(225,29,72,.25);transition:.2s filter ease;
}
.btn:hover{filter:brightness(.96);}
.btn.secondary{
  background:#fff;border:1px solid var(--border);color:#E11D48;
}
.btn.secondary:hover{background:#fff0f3;}
</style>
@endpush

@section('content')
<div class="market">
  <div class="page-header">
    <div>
      <h1 class="page-title">Marketplace</h1>
      <div class="muted">Complementos y servicios adicionales para potenciar tu cuenta.</div>
    </div>
  </div>

  <div class="grid">
    {{-- TIMBRES --}}
    <div class="card">
      <h3>Timbres adicionales</h3>
      <p>Adquiere paquetes de timbres sin caducidad para tus CFDI 4.0.</p>
      <div class="price">Desde $99 MXN</div>
      <a href="#" class="btn">Ver paquetes</a>
    </div>

    {{-- SOPORTE PRO --}}
    <div class="card">
      <h3>Soporte PRO</h3>
      <p>Atención prioritaria, asistencia técnica avanzada y guía en tus procesos SAT.</p>
      <div class="price">$299 /mes</div>
      <a href="#" class="btn">Más información</a>
    </div>

    {{-- DESCARGAS SAT PRO --}}
    <div class="card">
      <h3>Descargas SAT Pro</h3>
      <p>Automatiza la descarga de tus CFDI emitidos y recibidos directamente del SAT.</p>
      <div class="price">Desde $2,500 MXN</div>
      <a href="#" class="btn">Solicitar acceso</a>
    </div>

    {{-- API REST CFDI --}}
    <div class="card">
      <h3>API REST CFDI 4.0</h3>
      <p>Integra la facturación y cancelación directamente con tu sistema.</p>
      <div class="price">Desde $0.08 por timbre</div>
      <a href="#" class="btn">Ver documentación</a>
    </div>
  </div>
</div>
@endsection
