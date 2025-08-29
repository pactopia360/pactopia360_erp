/* P360 · Usuarios */
(function(){
  'use strict';

  const $ = (s,sc)=> (sc||document).querySelector(s);
  const $$= (s,sc)=> [...(sc||document).querySelectorAll(s)];
  const on = (el,ev,fn,opt)=> el && el.addEventListener(ev,fn,opt||{});
  const cfg = window.UsrCfg || { endpoints:{}, csrf:'' };

  // ---------- Selección masiva ----------
  const bulkBar  = $('#usrBulk');
  const chkAll   = $('#usrCheckAll');
  const checks   = $$('.usr-check');
  const countEl  = $('#usrBulkCount');
  function sel(){ return checks.filter(c=>c.checked).map(c=>c.value); }
  function syncBulk(){
    const n = sel().length;
    countEl.textContent = n;
    bulkBar.hidden = n===0;
    if (chkAll) chkAll.checked = n>0 && n===checks.length;
  }
  on(chkAll,'change',()=>{ checks.forEach(c=> c.checked = chkAll.checked); syncBulk(); });
  checks.forEach(c=> on(c,'change',syncBulk));
  on($('#usrBulkClear'),'click',()=>{ checks.forEach(c=> c.checked=false); syncBulk(); });

  // Acciones bulk (requiere endpoint .bulk; si no existe, muestra aviso)
  async function bulk(action){
    const ids = sel();
    if (!ids.length) return;
    if (!cfg.endpoints.bulk) return toast('No hay endpoint de acciones masivas','err');
    if (action==='delete' && !confirm(`¿Eliminar ${ids.length} usuarios?`)) return;

    try{
      const res = await fetch(cfg.endpoints.bulk, {
        method:'POST',
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':cfg.csrf,'X-Requested-With':'XMLHttpRequest'},
        body: JSON.stringify({ ids, action })
      });
      if (!res.ok) throw new Error('HTTP '+res.status);
      toast('Acción aplicada','ok'); location.reload();
    }catch(e){ console.error(e); toast('Error aplicando acción','err'); }
  }
  $$('#usrBulk [data-bulk]').forEach(b=> on(b,'click',()=> bulk(b.dataset.bulk)));

  // ---------- Toggle inline (activo / superadmin / force_password_change) ----------
  $$('.chip-toggle').forEach(btn=>{
    on(btn,'click', async ()=>{
      if (!cfg.endpoints.toggle){ toast('No hay endpoint de toggle','warn'); return; }
      const tr = btn.closest('tr'); const id = tr?.dataset.id;
      const field = btn.dataset.toggle; let value = btn.dataset.value === '1' ? 0 : 1;
      try{
        btn.disabled = true;
        const res = await fetch(cfg.endpoints.toggle, {
          method:'POST',
          headers:{'Content-Type':'application/json','X-CSRF-TOKEN':cfg.csrf,'X-Requested-With':'XMLHttpRequest'},
          body: JSON.stringify({ id, field, value })
        });
        if (!res.ok) throw new Error('HTTP '+res.status);
        btn.dataset.value = String(value);
        btn.textContent = value ? 'Sí' : 'No';
        btn.classList.toggle('on', !!value);
        btn.classList.toggle('off', !value);
        toast('Guardado','ok');
      }catch(e){ console.error(e); toast('Error','err'); }
      finally{ btn.disabled = false; }
    });
  });

  // ---------- Copiar email ----------
  $$('.usr-email').forEach(el=>{
    on(el,'click', async ()=>{
      try{ await navigator.clipboard.writeText(el.textContent.trim()); toast('Copiado','ok'); }catch(_){ }
    });
  });

  // ---------- Abrir edición al clicar nombre ----------
  $$('.usr-name-text').forEach(el=>{
    const href = el.dataset.openEdit; if(!href) return;
    on(el,'click',()=> location.href = href);
  });

  // ---------- Búsqueda con Enter / Ctrl+K ----------
  on(document,'keydown', (e)=>{
    const k = (e.key||'').toLowerCase();
    if ((e.ctrlKey||e.metaKey) && k==='k'){ e.preventDefault(); const i = $('#usrFilters input[name="q"]'); i?.focus(); i?.select?.(); }
  });

  // ---------- Confirmación de formularios delete ----------
  $$('form[data-confirm]').forEach(f=>{
    on(f,'submit', (e)=>{ const msg = f.getAttribute('data-confirm'); if(msg && !confirm(msg)){ e.preventDefault(); } });
  });

  // ---------- Mini-bot ----------
  const bot      = $('#usrBot');
  const botOpen  = $('#usrBotOpen');
  const botClose = $('#usrBotClose');
  const botIn    = $('#usrBotInput');
  const botLog   = $('#usrBotLog');

  const cmds = {
    activos:   ()=> applyFilter({activo:'1'}),
    inactivos: ()=> applyFilter({activo:'0'}),
    superadmins:()=> applyFilter({sa:'1'}),
    ventas:    ()=> applyFilter({rol:'ventas'}),
    soporte:   ()=> applyFilter({rol:'soporte'}),
    exportar:  ()=> { const a = document.createElement('a'); a.href = cfg.endpoints.export || '#'; a.click(); log('Exportando…','info'); }
  };

  function log(t,cls){ const p=document.createElement('div'); p.textContent=t; p.className=cls||'info'; botLog.appendChild(p); botLog.scrollTop=botLog.scrollHeight; }
  function openBot(){ bot.setAttribute('aria-hidden','false'); botIn?.focus(); }
  function closeBot(){ bot.setAttribute('aria-hidden','true'); }
  on(botOpen,'click', openBot);
  on(botClose,'click', closeBot);

  $$('.bot-btn').forEach(b=> on(b,'click',()=> execBot(b.dataset.bot)));
  on($('#usrBotSend'),'click',()=> execBot((botIn.value||'').trim()));
  on(botIn,'keydown',(e)=>{ if(e.key==='Enter'){ e.preventDefault(); execBot((botIn.value||'').trim()); }});

  function execBot(input){
    const q = String(input||'').toLowerCase();
    if (!q) return;
    if (cmds[q]){ cmds[q](); log('OK: '+q,'ok'); return; }
    // Parsing simple: "rol ventas", "forzar=si", "activo=no"
    if (q.startsWith('rol ')) return applyFilter({rol: q.split(' ')[1]||''});
    if (q.startsWith('forzar=')) return applyFilter({force: (q.includes('si')?'1': (q.includes('no')?'0':''))});
    if (q.startsWith('activo=')) return applyFilter({activo: (q.includes('si')?'1': (q.includes('no')?'0':''))});
    // Fallback: buscar texto
    const inp = $('#usrFilters input[name="q"]'); if (inp){ inp.value = input; $('#usrFilters').submit(); }
  }

  function applyFilter(map){
    const form = $('#usrFilters'); if(!form) return;
    Object.entries(map).forEach(([k,v])=>{
      let el = form.querySelector(`[name="${k}"]`);
      if (!el){ el = document.createElement('input'); el.type='hidden'; el.name=k; form.appendChild(el); }
      el.value = v;
    });
    form.submit();
  }

  // ---------- Toast mínimo ----------
  function toast(msg, cls){
    try{
      const host = document.getElementById('alerts') || (function(){ const h=document.createElement('div'); h.id='alerts'; document.body.appendChild(h); return h; })();
      const el = document.createElement('div');
      el.className = 'alert ' + (cls==='ok'?'alert-success':cls==='err'?'alert-error':cls==='warn'?'alert-warning':'alert-info');
      el.style.cssText = 'margin:6px 12px;background:#111827;color:#fff;padding:10px 12px;border-radius:10px;box-shadow:0 6px 18px rgba(0,0,0,.2)';
      el.textContent = String(msg||'');
      host.appendChild(el);
      setTimeout(()=> el.remove(), 3000);
    }catch(_){}
  }

  // Sincronizar barra al inicio
  syncBulk();
})();
