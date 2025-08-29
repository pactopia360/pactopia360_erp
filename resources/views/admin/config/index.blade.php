@extends('layouts.admin')

@section('title', 'Configuración · Pactopia360')

@section('page-header')
  <h1 class="kpi-value" style="font-size:22px;margin:0">Configuración</h1>
@endsection

@section('content')
  <div class="cards" style="display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:12px">
    {{-- Preferencias de apariencia --}}
    <div class="card" style="grid-column: span 6">
      <h3 style="margin:0 0 8px">Apariencia</h3>
      <label style="display:flex;align-items:center;gap:10px;margin:8px 0">
        <input type="checkbox" id="cfgDarkDefault">
        <span>Preferir modo oscuro por defecto</span>
      </label>
      <label style="display:flex;align-items:center;gap:10px;margin:8px 0">
        <input type="checkbox" id="cfgSidebarCollapsed">
        <span>Arrancar con el sidebar colapsado</span>
      </label>
      <div style="margin-top:10px;display:flex;gap:8px">
        <button class="btn" id="btnApplyCfg" type="button">Aplicar</button>
        <button class="btn" id="btnResetCfg" type="button">Restablecer</button>
      </div>
      <p class="muted" style="margin-top:8px">
        Se guardan en tu navegador (localStorage). No afecta a otros usuarios.
      </p>
    </div>

    {{-- Info --}}
    <div class="card" style="grid-column: span 6">
      <h3 style="margin:0 0 8px">Acerca de</h3>
      <p class="muted" style="margin:0">
        Panel de configuración en construcción. El modo claro/oscuro y el estado del menú se replican de forma global.
      </p>
    </div>
  </div>
@endsection

@push('scripts')
<script>
(function(){
  'use strict';
  const qs=(s,ctx=document)=>ctx.querySelector(s);
  const dark = qs('#cfgDarkDefault');
  const sbc  = qs('#cfgSidebarCollapsed');

  // Carga inicial
  (function init(){
    try{
      const th = localStorage.getItem('p360-theme');
      dark.checked = (th === 'dark');
      const sc = localStorage.getItem('p360.sidebar.collapsed') === '1' || localStorage.getItem('p360-sidebar') === '1';
      sbc.checked = sc;
    }catch(_){}
  })();

  // Aplicar
  qs('#btnApplyCfg')?.addEventListener('click', ()=>{
    try{
      const theme = dark.checked ? 'dark' : 'light';
      localStorage.setItem('p360-theme', theme);
      document.documentElement.classList.toggle('theme-dark', theme==='dark');
      document.documentElement.classList.toggle('theme-light', theme!=='dark');
      document.documentElement.setAttribute('data-theme', theme);
      document.body.classList.toggle('theme-dark', theme==='dark');
      document.body.classList.toggle('theme-light', theme!=='dark');
      window.dispatchEvent(new CustomEvent('p360:theme',{detail:{mode:theme,dark:theme==='dark'}}));

      const collapsed = sbc.checked;
      localStorage.setItem('p360.sidebar.collapsed', collapsed ? '1':'0');
      localStorage.setItem('p360-sidebar', collapsed ? '1':'0'); // legacy
      document.documentElement.classList.toggle('sidebar-collapsed', collapsed);
      document.body.classList.toggle('sidebar-collapsed', collapsed);
      window.dispatchEvent(new CustomEvent('p360:sidebar',{detail:{collapsed}}));

      P360.toast?.info('Preferencias aplicadas');
    }catch(e){ P360.toast?.error('No se pudo aplicar'); }
  });

  // Reset
  qs('#btnResetCfg')?.addEventListener('click', ()=>{
    ['p360-theme','p360.sidebar.collapsed','p360-sidebar'].forEach(k=>{ try{ localStorage.removeItem(k); }catch(_){ } });
    P360.toast?.info('Preferencias restablecidas. Recarga la página.');
  });
})();
</script>
@endpush
