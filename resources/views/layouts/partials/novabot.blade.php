{{-- resources/views/layouts/partials/novabot.blade.php (v3 · accesible + draggable + persistencia) --}}
@php
  // UID para evitar colisiones si se incluye más de una vez
  $novaUid = 'nova_' . substr(md5(uniqid('',true)),0,6);
@endphp

{{-- Lanzador flotante --}}
<button id="{{ $novaUid }}_toggle"
        class="nova-fab"
        type="button"
        aria-label="Abrir asistente"
        aria-controls="{{ $novaUid }}_panel"
        aria-expanded="false"
        title="Abrir asistente (Alt + /)">
  🤖
  <span class="nova-dot" aria-hidden="true"></span>
</button>

{{-- Backdrop --}}
<div id="{{ $novaUid }}_backdrop" class="nova-backdrop" hidden></div>

{{-- Panel del asistente --}}
<aside id="{{ $novaUid }}_panel"
       class="nova-panel"
       role="dialog"
       aria-modal="true"
       aria-hidden="true"
       aria-labelledby="{{ $novaUid }}_title"
       tabindex="-1"
       hidden>
  <header class="nova-header" data-draggable="true">
    <div class="nova-head-left">
      <div id="{{ $novaUid }}_title" class="nova-title">NovaBot · Ayuda rápida</div>
      <span class="nova-sub">Soporte básico y atajos</span>
    </div>
    <div class="nova-head-actions">
      <button id="{{ $novaUid }}_min" class="nova-icon" type="button" aria-label="Minimizar">▁</button>
      <button id="{{ $novaUid }}_close" class="nova-icon" type="button" aria-label="Cerrar">✕</button>
    </div>
  </header>

  <div class="nova-body">
    <p class="nova-hint">Preguntas rápidas:</p>
    <div class="nova-quick" role="list">
      <button class="nova-btn" role="listitem" data-q="¿Cómo generar una nómina?">Nómina</button>
      <button class="nova-btn" role="listitem" data-q="¿Dónde están los empleados?">Empleados</button>
      <button class="nova-btn" role="listitem" data-q="¿Cómo subo archivos del checador?">Checador</button>
      <button class="nova-btn" role="listitem" data-q="¿Cómo descargo mis XML del SAT?">Descargas SAT</button>
    </div>

    <div class="nova-log" id="{{ $novaUid }}_log" aria-live="polite" aria-relevant="additions text">
      {{-- Mensaje de bienvenida --}}
      <div class="msg bot"><div class="b">
        ¡Hola! Soy <strong>NovaBot</strong>. Escríbeme tu pregunta o elige un atajo.
      </div></div>
    </div>

    <form class="nova-input" id="{{ $novaUid }}_form" autocomplete="off">
      <input id="{{ $novaUid }}_input" type="text" placeholder="Escribe tu pregunta…" aria-label="Pregunta para NovaBot">
      <button id="{{ $novaUid }}_send" class="nova-send" type="submit" aria-label="Enviar">Enviar</button>
    </form>
  </div>
</aside>

@pushOnce('styles','novabot-css')
<style>
  :root{
    --nova-z: 1055;
    --nova-bg: var(--card, #fff);
    --nova-fg: var(--ink, #0f172a);
    --nova-bd: var(--bd, #e5e7eb);
    --nova-brand: var(--p360-brand, #E11D48);
    --nova-brand-600: var(--p360-brand-600, #BE123C);
  }
  html.theme-dark{
    --nova-bg: color-mix(in oklab, #0b1220 92%, transparent);
    --nova-fg: #e5e7eb;
    --nova-bd: rgba(255,255,255,.12);
  }

  /* FAB */
  .nova-fab{
    position: fixed; right: 18px; bottom: 18px; z-index: var(--nova-z);
    width: 52px; height: 52px; border-radius: 16px; border:1px solid var(--nova-bd);
    background: linear-gradient(180deg, color-mix(in oklab, var(--nova-brand) 12%, transparent), transparent);
    box-shadow: 0 12px 30px rgba(0,0,0,.18);
    display:flex; align-items:center; justify-content:center; gap:6px;
    font-size: 22px; cursor: pointer;
    transition: transform .12s ease, box-shadow .2s ease, background .2s ease;
  }
  .nova-fab:hover{ transform: translateY(-1px); box-shadow: 0 16px 36px rgba(0,0,0,.22); }
  .nova-fab[aria-expanded="true"]{ box-shadow: 0 0 0 3px color-mix(in oklab, var(--nova-brand) 30%, transparent); }
  .nova-fab .nova-dot{
    width:8px;height:8px;border-radius:999px;background:#10b981; box-shadow:0 0 0 6px rgba(16,185,129,.15);
    position:absolute; top:8px; right:8px;
  }

  /* Backdrop */
  .nova-backdrop{
    position: fixed; inset: 0; z-index: calc(var(--nova-z) - 1);
    background: rgba(2,6,23,.35); backdrop-filter: blur(2px);
  }

  /* Panel */
  .nova-panel{
    position: fixed; right: 18px; bottom: 86px; z-index: var(--nova-z);
    width: min(420px, 92vw); max-height: min(70vh, 640px);
    background: var(--nova-bg); color: var(--nova-fg);
    border:1px solid var(--nova-bd); border-radius: 16px;
    box-shadow: 0 18px 50px rgba(0,0,0,.24);
    display:flex; flex-direction:column; overflow: hidden;
    transform: translateY(10px); opacity: 0; pointer-events: none;
    transition: transform .18s ease, opacity .18s ease;
  }
  .nova-panel[aria-hidden="false"]{
    transform: translateY(0); opacity: 1; pointer-events: auto;
  }
  .nova-header{
    display:flex; align-items:center; justify-content:space-between; gap:10px;
    padding: 10px 12px; border-bottom:1px solid var(--nova-bd);
    background:
      linear-gradient(180deg, color-mix(in oklab, var(--nova-brand) 10%, transparent), transparent);
    cursor: default;
  }
  .nova-head-left{ display:flex; flex-direction:column; line-height:1.15 }
  .nova-title{ font: 800 14px/1.1 Poppins, system-ui; letter-spacing:.02em }
  .nova-sub{ font: 600 11px/1 system-ui; color: color-mix(in oklab, var(--nova-fg) 60%, transparent) }
  .nova-head-actions{ display:flex; align-items:center; gap:6px }
  .nova-icon{
    width:32px;height:28px;border-radius:8px;border:1px solid var(--nova-bd);
    background: transparent; cursor: pointer; font-weight:900;
  }

  .nova-body{ display:flex; flex-direction:column; gap:10px; padding: 10px; min-height: 220px }
  .nova-hint{ margin:0; font: 700 12px/1.2 system-ui; color: color-mix(in oklab, var(--nova-fg) 60%, transparent) }
  .nova-quick{ display:flex; flex-wrap:wrap; gap:6px }
  .nova-btn{
    border:1px solid var(--nova-bd); border-radius:999px; padding:6px 10px; font:700 12px/1 system-ui;
    background: color-mix(in oklab, var(--nova-bg) 94%, transparent); cursor: pointer;
  }
  .nova-btn:hover{ border-color: color-mix(in oklab, var(--nova-brand) 30%, var(--nova-bd)) }

  .nova-log{
    flex:1; overflow:auto; border:1px solid var(--nova-bd); border-radius:12px; padding:10px; display:grid; gap:8px;
    background: color-mix(in oklab, var(--nova-bg) 96%, transparent);
  }
  .msg{ display:flex }
  .msg .b{
    max-width: 86%; padding:8px 10px; border-radius:12px; font: 600 13px/1.4 system-ui;
    border:1px solid var(--nova-bd);
  }
  .msg.bot{ justify-content:flex-start }
  .msg.bot .b{
    background: linear-gradient(180deg, color-mix(in oklab, var(--nova-brand) 8%, transparent), transparent);
  }
  .msg.user{ justify-content:flex-end }
  .msg.user .b{ background: color-mix(in oklab, var(--nova-brand) 10%, transparent) }

  .nova-input{ display:flex; gap:8px; align-items:center }
  .nova-input input{
    flex:1; border:1px solid var(--nova-bd); background: color-mix(in oklab, var(--nova-bg) 96%, transparent);
    border-radius:10px; padding:10px 12px; font-weight:700; color:inherit;
  }
  .nova-send{
    border:0; border-radius:10px; padding:10px 12px; font-weight:900; cursor:pointer; color:#fff;
    background: linear-gradient(180deg, var(--nova-brand), var(--nova-brand-600));
    min-width:92px;
  }

  @media (max-width: 520px){
    .nova-panel{ right: 10px; left: 10px; width: auto; bottom: 96px; }
    .nova-fab{ right: 10px; bottom: 10px }
  }

  @media (prefers-reduced-motion: reduce){
    .nova-panel, .nova-fab{ transition: none }
  }
</style>
@endPushOnce

@pushOnce('scripts','novabot-js')
<script>
(function(){
  const uid   = @json($novaUid);
  const $     = (id) => document.getElementById(id);
  const root  = $(uid + '_panel');
  const fab   = $(uid + '_toggle');
  const bd    = $(uid + '_backdrop');
  const close = $(uid + '_close');
  const min   = $(uid + '_min');
  const form  = $(uid + '_form');
  const input = $(uid + '_input');
  const log   = $(uid + '_log');
  const send  = $(uid + '_send');
  const LSKEY = 'p360.novabot.state';

  if(!root || !fab) return;

  function isOpen(){ return root.getAttribute('aria-hidden') === 'false'; }
  function openPanel(focusInput = true){
    root.hidden = false; bd.hidden = false;
    root.setAttribute('aria-hidden','false'); fab.setAttribute('aria-expanded','true');
    document.body.style.setProperty('overflow','hidden');
    if(focusInput) setTimeout(()=> input?.focus(), 10);
    persist();
  }
  function closePanel(){
    root.setAttribute('aria-hidden','true'); fab.setAttribute('aria-expanded','false');
    root.hidden = true; bd.hidden = true;
    document.body.style.removeProperty('overflow');
    persist();
  }
  function persist(){
    try{ localStorage.setItem(LSKEY, JSON.stringify({ open: isOpen() })); }catch(_){}
  }
  function restore(){
    try{
      const st = JSON.parse(localStorage.getItem(LSKEY)||'{}');
      if(st.open) openPanel(false);
    }catch(_){}
  }

  function pushMsg(text, who='user'){
    if(!text) return;
    const div = document.createElement('div');
    div.className = 'msg ' + (who==='user'?'user':'bot');
    div.innerHTML = '<div class="b"></div>';
    div.querySelector('.b').textContent = text;
    log.appendChild(div);
    log.scrollTop = log.scrollHeight;
  }

  async function askNova(text){
    // Hook de integración:
    // Si existiera un cliente global => window.NovaBot.ask(text) debe devolver { ok, text } o lanzar error
    if (window.NovaBot && typeof window.NovaBot.ask === 'function'){
      try{
        const res = await window.NovaBot.ask(text);
        pushMsg(res?.text || 'Listo.', 'bot');
      }catch(e){
        pushMsg('No pude procesar tu solicitud. Intenta de nuevo.', 'bot');
      }
      return;
    }

    // Fallback simulado (para desarrollo)
    await new Promise(r=>setTimeout(r, 350));
    // Respuestas rápidas simples (keywords)
    const t = text.toLowerCase();
    if (t.includes('nómina')) {
      pushMsg('Para generar nómina: Ve a Módulos → Nómina → Nueva nómina, carga empleados y timbra. ¿Deseas que te guíe paso a paso?', 'bot');
    } else if (t.includes('emplead')) {
      pushMsg('Empleados: Configuración → Recursos Humanos → Empleados. Ahí puedes crear, importar y asignar puestos.', 'bot');
    } else if (t.includes('checador')) {
      pushMsg('Checador: Módulos → Checador → Importar archivo (CSV/Excel). Asegura columnas: id_empleado, fecha, entrada, salida.', 'bot');
    } else if (t.includes('sat') || t.includes('xml')) {
      pushMsg('Descargas SAT: SAT (Descarga) → Solicitar paquete → Verificar → Descargar ZIP. ¿Quieres abrir el módulo ahora?', 'bot');
    } else {
      pushMsg('Gracias. Estoy pensando… (conéctame a tu backend para respuestas en vivo).', 'bot');
    }
  }

  // Envío
  form?.addEventListener('submit', async (e)=>{
    e.preventDefault();
    const val = (input?.value||'').trim();
    if(!val) return;
    pushMsg(val, 'user');
    input.value = '';
    send.disabled = true;
    await askNova(val);
    send.disabled = false;
  });

  // Quick buttons
  root.querySelectorAll('.nova-btn[data-q]')?.forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const q = btn.getAttribute('data-q')||'';
      if(q){ pushMsg(q,'user'); askNova(q); openPanel(true); }
    });
  });

  // Toggle FAB
  fab.addEventListener('click', ()=>{
    isOpen() ? closePanel() : openPanel(true);
  });
  close?.addEventListener('click', closePanel);
  bd?.addEventListener('click', closePanel);

  // Minimizar (reduce a 60% de alto)
  let minimized = false;
  min?.addEventListener('click', ()=>{
    minimized = !minimized;
    root.style.maxHeight = minimized ? '46vh' : 'min(70vh, 640px)';
    min.textContent = minimized ? '▢' : '▁';
  });

  // Atajos de teclado: Alt + / abre; Esc cierra
  document.addEventListener('keydown', (e)=>{
    if (e.key === '/' && (e.altKey || e.metaKey)) { e.preventDefault(); openPanel(true); }
    if (e.key === 'Escape' && isOpen()) closePanel();
  });

  // Cerrar si pierde foco explícito (opcional)
  // root.addEventListener('focusout', (e)=>{ if(!root.contains(e.relatedTarget)) closePanel(); });

  // Drag (solo desktop/si header tiene data-draggable)
  (function enableDrag(){
    const header = root.querySelector('[data-draggable="true"]');
    if(!header) return;
    let dragging=false, sx=0, sy=0, ox=0, oy=0;

    function onDown(ev){
      if (matchMedia('(max-width: 900px)').matches) return; // evita en móvil
      dragging = true;
      const r = root.getBoundingClientRect();
      ox = r.left; oy = r.top;
      sx = ev.clientX; sy = ev.clientY;
      document.addEventListener('mousemove', onMove);
      document.addEventListener('mouseup', onUp);
      header.style.cursor='grabbing';
    }
    function onMove(ev){
      if(!dragging) return;
      const dx = ev.clientX - sx;
      const dy = ev.clientY - sy;
      const nx = Math.max(8, Math.min(window.innerWidth - root.offsetWidth - 8, ox + dx));
      const ny = Math.max(8, Math.min(window.innerHeight - root.offsetHeight - 8, oy + dy));
      root.style.left = nx + 'px';
      root.style.right = 'auto';
      root.style.bottom = 'auto';
      root.style.top = ny + 'px';
      root.style.position = 'fixed';
    }
    function onUp(){
      dragging=false;
      document.removeEventListener('mousemove', onMove);
      document.removeEventListener('mouseup', onUp);
      header.style.cursor='default';
    }
    header.addEventListener('mousedown', onDown);
  })();

  // API pública simple
  window.NovaBot = window.NovaBot || {};
  if (!window.NovaBot.open)    window.NovaBot.open  = ()=> openPanel(true);
  if (!window.NovaBot.close)   window.NovaBot.close = ()=> closePanel();
  if (!window.NovaBot.push)    window.NovaBot.push  = (txt,who='bot')=> pushMsg(txt,who);

  // Restaurar estado
  restore();

  // Evento global opcional para abrir desde otros módulos
  window.addEventListener('p360:bot:open', ()=> openPanel(true), { passive:true });
})();
</script>
@endPushOnce
