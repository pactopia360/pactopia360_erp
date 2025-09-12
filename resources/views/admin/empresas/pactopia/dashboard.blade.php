{{-- resources/views/admin/empresas/pactopia/dashboard.blade.php --}}
@extends('layouts.app')
@section('title', 'Pactopia · Dashboard')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/admin/css/modules/empresas.css') }}">

<style>
  /* Tema rápido para Pactopia (azules) sin romper tu CSS global */
  .page.mod-empresa.pactopia .hero {
    --brand: #0ea5e9;
    --brand-2: #0284c7;
    background: linear-gradient(180deg, rgba(14,165,233,.08), transparent 60%);
    border-radius: 18px;
    padding: 22px 20px;
    margin: 6px 0 16px;
  }
  .page.mod-empresa .mod-ribbon{
    display:inline-block;font:700 11px/1 system-ui;letter-spacing:.04em;color:#0f172a;
    background:rgba(2,132,199,.16);padding:6px 10px;border-radius:999px;margin-bottom:8px
  }
  html.theme-dark .page.mod-empresa .mod-ribbon{color:#e2e8f0;background:rgba(14,165,233,.18)}
  .page.mod-empresa .title{font:700 26px/1.15 ui-sans-serif,system-ui;margin:2px 0 6px}
  .page.mod-empresa .desc{color:#475569;margin-bottom:14px}
  html.theme-dark .page.mod-empresa .desc{color:#cbd5e1}
  .page.mod-empresa .chips{display:flex;flex-wrap:wrap;gap:8px}
  .btn-ghost{appearance:none;border:1px solid rgba(2,132,199,.35);padding:8px 12px;border-radius:10px;
    font:600 13px/1 system-ui;cursor:pointer;background:rgba(14,165,233,.06)}
  .btn-ghost:hover{background:rgba(14,165,233,.12)}
  .btn-ghost.is-disabled{opacity:.55;cursor:not-allowed}
  .grid-cards{display:grid;grid-template-columns:repeat(12,1fr);gap:14px;margin-top:14px}
  .card{grid-column:span 12;background:rgba(2,132,199,.06);border:1px solid rgba(2,132,199,.25);
    border-radius:14px;padding:14px}
  .card h3{font:700 14px/1.2 system-ui;margin-bottom:10px}
  @media (min-width: 900px){
    .card.span-6{grid-column:span 6}
    .card.span-4{grid-column:span 4}
  }
  .quick-links{display:flex;flex-wrap:wrap;gap:8px}
  .quick-links a{display:inline-flex;align-items:center;gap:8px;text-decoration:none}
  .quick-links .tag{font:700 10px/1 system-ui;background:rgba(14,165,233,.15);padding:4px 8px;border-radius:999px}
  .muted{color:#64748b}
  html.theme-dark .muted{color:#94a3b8}
</style>

<div class="page mod-empresa pactopia" data-module="empresa" data-empresa="pactopia">
  <div class="hero">
    <span class="mod-ribbon">Empresa</span>
    <div class="title">{{ $empresa['name'] ?? 'Pactopia' }}</div>
    <div class="desc">{{ $empresa['desc'] ?? 'Operación interna y comercial de Pactopia.' }}</div>
    <div class="chips">
      {{-- Rutas mínimas registradas en routes/admin.php para Pactopia --}}
      <a class="btn-ghost" href="{{ route('admin.empresas.pactopia.crm.contactos.index') }}">CRM · Contactos</a>
      <a class="btn-ghost" href="{{ route('admin.empresas.pactopia.crm.robots.index') }}">Robots</a>

      {{-- Placeholders por si quieres mostrar los demás pero inhabilitados --}}
      <span class="btn-ghost is-disabled" title="Próximamente">CxP</span>
      <span class="btn-ghost is-disabled" title="Próximamente">CxC</span>
      <span class="btn-ghost is-disabled" title="Próximamente">Reportes</span>

      <button class="btn-ghost" type="button" onclick="try{NovaBot.open()}catch(e){}">🤖 Bot</button>
    </div>
  </div>

  <div class="grid-cards">
    <div class="card span-6">
      <h3>Accesos rápidos</h3>
      <div class="quick-links">
        <a href="{{ route('admin.empresas.pactopia.crm.contactos.index') }}">
          <span class="tag">CRM</span><span>Contactos</span>
        </a>
        <a href="{{ route('admin.empresas.pactopia.crm.robots.index') }}">
          <span class="tag">🤖</span><span>Robots</span>
        </a>
      </div>
    </div>

    <div class="card span-6">
      <h3>Estado</h3>
      <div class="muted">Módulos adicionales (CxP/CxC/Reportes) se habilitarán conforme los vayas creando.</div>
    </div>
  </div>
</div>
@endsection
