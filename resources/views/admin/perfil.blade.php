@extends('layouts.admin')

@section('title', 'Mi perfil · Pactopia360')

@section('page-header')
  <h1 class="kpi-value" style="font-size:22px;margin:0">Mi perfil</h1>
  <p class="muted" style="margin:6px 0 0">Preferencias de administrador</p>
@endsection

@section('content')
@php
  $u = auth('admin')->user();
@endphp
  <div class="cards" style="display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:12px">
    {{-- Datos del usuario --}}
    <div class="card" style="grid-column: span 6">
      <h3 style="margin:0 0 8px">Datos</h3>
      <div class="row" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px">
        <label class="field">
          <span class="label">Nombre</span>
          <input type="text" value="{{ $u->name ?? $u->nombre ?? '' }}" readonly>
        </label>
        <label class="field">
          <span class="label">Email</span>
          <input type="email" value="{{ $u->email ?? '' }}" readonly>
        </label>
        <label class="field">
          <span class="label">Último acceso</span>
          <input type="text" value="{{ optional($u->last_login_at)->format('Y-m-d H:i') ?? '—' }}" readonly>
        </label>
        <label class="field">
          <span class="label">IP de acceso</span>
          <input type="text" value="{{ $u->last_login_ip ?? request()->ip() }}" readonly>
        </label>
      </div>
    </div>

    {{-- Preferencias de interfaz --}}
    <div class="card" style="grid-column: span 6">
      <h3 style="margin:0 0 8px">Apariencia</h3>
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <button class="btn" type="button" data-theme="light">Modo claro</button>
        <button class="btn" type="button" data-theme="dark">Modo oscuro</button>
        <button class="btn" type="button" id="btnToggleSidebar">Alternar sidebar</button>
        <small class="muted">El tema y el colapso del menú se guardan en tu navegador.</small>
      </div>
    </div>

    {{-- Diagnóstico UI --}}
    <div class="card" style="grid-column: span 12">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
        <h3 style="margin:0">Diagnóstico de interfaz</h3>
        <div style="display:flex;gap:8px">
          <button class="btn" type="button" id="btnUiDiag">Probar /admin/ui/diag</button>
          <button class="btn" type="button" id="btnUiBeacon">Enviar beacon a logs</button>
          <button class="btn" type="button" id="btnResetUi">Restablecer UI</button>
        </div>
      </div>
      <pre id="uiDiagOut" style="margin-top:10px;white-space:pre-wrap;background:rgba(0,0,0,.05);padding:10px;border-radius:8px;min-height:60px"></pre>
    </div>
  </div>
@endsection

@push('scripts')
<script>
(function(){
  'use strict';
  const $ = (s,ctx=document)=>ctx.querySelector(s);
  const $$= (s,ctx=document)=>Array.from((ctx||document).querySelectorAll(s));

  // Tema rápido
  $$('.btn[data-theme]').forEach(b=>{
    if (b.dataset.bound) return;
    b.dataset.bound='1';
    b.addEventListener('click', ()=>{
      const mode = b.dataset.theme;
      try{
        document.documentElement.classList.toggle('theme-dark', mode==='dark');
        document.documentElement.classList.toggle('theme-light', mode!=='dark');
        document.documentElement.setAttribute('data-theme', mode);
        document.body.classList.toggle('theme-dark', mode==='dark');
        document.body.classList.toggle('theme-light', mode!=='dark');
        localStorage.setItem('p360-theme', mode);
        window.dispatchEvent(new CustomEvent('p360:theme',{detail:{mode, dark:mode==='dark'}}));
        P360.toast?.info('Tema: '+mode);
      }catch(e){}
    });
  });

  // Alternar sidebar (usa el mismo mecanismo del header/manager)
  const btnSb = $('#btnToggleSidebar');
  if (btnSb && !btnSb.dataset.bound){
    btnSb.dataset.bound='1';
    btnSb.addEventListener('click', ()=>{
      const collapsed = document.documentElement.classList.contains('sidebar-collapsed') ||
                        document.body.classList.contains('sidebar-collapsed') ||
                        localStorage.getItem('p360.sidebar.collapsed') === '1' ||
                        localStorage.getItem('p360-sidebar') === '1';
      const next = !collapsed;
      try{
        localStorage.setItem('p360.sidebar.collapsed', next ? '1' : '0');
        localStorage.setItem('p360-sidebar', next ? '1' : '0'); // legacy
      }catch(e){}
      document.documentElement.classList.toggle('sidebar-collapsed', next);
      document.body.classList.toggle('sidebar-collapsed', next);
      window.dispatchEvent(new CustomEvent('p360:sidebar',{detail:{collapsed:next}}));
    });
  }

  // Diagnóstico UI
  const out = $('#uiDiagOut');
  $('#btnUiDiag')?.addEventListener('click', async ()=>{
    out.textContent = 'Consultando...';
    try{
      const res = await fetch('{{ route('admin.ui.diag') }}', { credentials:'same-origin' });
      out.textContent = res.ok ? JSON.stringify(await res.json(), null, 2) : ('HTTP '+res.status);
    }catch(e){ out.textContent = 'Error: '+e; }
  });

  // Beacon a logs
  $('#btnUiBeacon')?.addEventListener('click', async ()=>{
    try{
      await fetch('{{ route('admin.ui.log') }}', {
        method:'POST',
        headers:{
          'Content-Type':'application/json',
          'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]')||{}).content || ''
        },
        credentials:'same-origin',
        body: JSON.stringify({
          level:'info',
          event:'perfil.beacon',
          source:'perfil',
          theme: localStorage.getItem('p360-theme') || 'unknown',
          collapsed: localStorage.getItem('p360.sidebar.collapsed') || '0'
        })
      });
      P360.toast?.info('Beacon enviado');
    }catch(e){ P360.toast?.error('Beacon falló'); }
  });

  // Restablecer UI
  $('#btnResetUi')?.addEventListener('click', ()=>{
    ['p360-theme','p360.sidebar.collapsed','p360-sidebar'].forEach(k=>{ try{ localStorage.removeItem(k); }catch(_){ } });
    P360.toast?.info('Preferencias restablecidas. Recarga la página.');
  });
})();
</script>
@endpush
