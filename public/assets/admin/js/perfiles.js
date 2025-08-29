/* P360 · Perfiles & Permisos */
(function(){
  'use strict';
  const $ = (s,sc)=> (sc||document).querySelector(s);
  const $$= (s,sc)=> [...(sc||document).querySelectorAll(s)];
  const on= (el,ev,fn,opt)=> el && el.addEventListener(ev,fn,opt||{});
  const cfg = window.PrfCfg || { endpoints:{}, csrf:'' };

  // ---------- Bulk selection ----------
  const bulkBar = $('#prfBulk'), chkAll = $('#prfCheckAll'), checks = $$('.prf-check'), countEl = $('#prfBulkCount');
  const selected = ()=> checks.filter(c=>c.checked).map(c=>c.value);
  function syncBulk(){ const n=selected().length; countEl.textContent=n; bulkBar.hidden = n===0; if(chkAll) chkAll.checked = n>0 && n===checks.length; }
  on(chkAll,'change',()=>{ checks.forEach(c=> c.checked = chkAll.checked); syncBulk(); });
  checks.forEach(c=> on(c,'change',syncBulk));
  on($('#prfBulkClear'),'click',()=>{ checks.forEach(c=> c.checked=false); syncBulk(); });

  async function doBulk(action){
    const ids = selected(); if (!ids.length) return;
    if (!cfg.endpoints.bulk) return toast('Endpoint bulk no disponible','warn');
    if (action==='delete' && !confirm(`¿Eliminar ${ids.length} perfiles?`)) return;
    try{
      const res = await fetch(cfg.endpoints.bulk, { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':cfg.csrf,'X-Requested-With':'XMLHttpRequest'}, body: JSON.stringify({ ids, action }) });
      if(!res.ok) throw new Error('HTTP '+res.status);
      toast('Acción aplicada','ok'); location.reload();
    }catch(e){ console.error(e); toast('Error aplicando acción','err'); }
  }
  $$('#prfBulk [data-bulk]').forEach(b=> on(b,'click',()=> doBulk(b.dataset.bulk)));

  // ---------- Toggles inline ----------
  $$('.chip-toggle').forEach(btn=>{
    on(btn,'click', async ()=>{
      if (!cfg.endpoints.toggle){ toast('Endpoint toggle no disponible','warn'); return; }
      const tr = btn.closest('tr'); const id=tr?.dataset.id; const field=btn.dataset.toggle; let value = btn.dataset.value==='1'?0:1;
      try{
        btn.disabled=true;
        const res = await fetch(cfg.endpoints.toggle, { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':cfg.csrf,'X-Requested-With':'XMLHttpRequest'}, body: JSON.stringify({ id, field, value }) });
        if(!res.ok) throw new Error('HTTP '+res.status);
        btn.dataset.value=String(value); btn.textContent=value?'Sí':'No'; btn.classList.toggle('on',!!value); btn.classList.toggle('off',!value);
        toast('Guardado','ok');
      }catch(e){ console.error(e); toast('Error','err'); }
      finally{ btn.disabled=false; }
    });
  });

  // ---------- Copiar clave ----------
  $$('.prf-key').forEach(el=> on(el,'click', async ()=>{ try{ await navigator.clipboard.writeText(el.textContent.trim()); toast('Copiado','ok'); }catch(_){}}));

  // ---------- Ir a editar al hacer click en nombre ----------
  $$('.prf-name-text').forEach(el=>{ const href=el.dataset.openEdit; if(href) on(el,'click',()=> location.href=href); });

  // ---------- Confirmación en delete ----------
  $$('form[data-confirm]').forEach(f=> on(f,'submit',(e)=>{ const m=f.getAttribute('data-confirm'); if(m && !confirm(m)){ e.preventDefault(); } }));

  // ---------- Modal Permisos ----------
  const modal = $('#prfPermsModal'), groupsWrap = $('#prfPermsGroups'), titleId = $('#prfPermsProfile');
  const btnSave = $('#prfPermsSave'), btnAll = $('#prfPermsAll'), btnNone = $('#prfPermsNone'), inpSearch = $('#prfPermsSearch');
  let currentId = null, currentData = null, dirty=false;

  function openModal(){ modal.setAttribute('aria-hidden','false'); }
  function closeModal(){ modal.setAttribute('aria-hidden','true'); currentId=null; currentData=null; groupsWrap.innerHTML=''; btnSave.disabled=true; dirty=false; inpSearch.value=''; }
  $$('.prf-modal [data-close], .prf-backdrop').forEach(el=> on(el,'click', closeModal));

  // Render dinámico: data = { groups: [{key, title, items:[{id,key,label}] }], assigned: [permId,...] }
  function renderPerms(data){
    currentData = data;
    groupsWrap.innerHTML='';
    const assigned = new Set(data.assigned||[]);
    (data.groups||[]).forEach(g=>{
      const box = document.createElement('div'); box.className='prf-group'; box.dataset.key=g.key;
      const head = document.createElement('div'); head.className='prf-group-head';
      head.innerHTML = `<span>${g.title||g.key}</span><button class="btn btn-small" data-group="${g.key}">Todo</button>`;
      const list = document.createElement('div'); list.className='prf-group-list';
      (g.items||[]).forEach(p=>{
        const row = document.createElement('label'); row.className='prf-perm'; row.dataset.key = (p.key||'').toLowerCase();
        const chk = document.createElement('input'); chk.type='checkbox'; chk.value=p.id; chk.checked = assigned.has(p.id);
        const txt = document.createElement('span'); txt.textContent = p.label || p.key;
        row.appendChild(chk); row.appendChild(txt); list.appendChild(row);
      });
      box.appendChild(head); box.appendChild(list); groupsWrap.appendChild(box);
    });

    // Listeners
    $$('.prf-group-head [data-group]', groupsWrap).forEach(b=>{
      on(b,'click', ()=>{
        const parent = b.closest('.prf-group'); $$('input[type=checkbox]', parent).forEach(ch=> ch.checked=true);
        setDirty(true);
      });
    });
    $$('input[type=checkbox]', groupsWrap).forEach(ch=> on(ch,'change', ()=> setDirty(true)));

    // Filtrado por texto
    on(inpSearch, 'input', ()=>{
      const q = (inpSearch.value||'').toLowerCase();
      $$('.prf-group', groupsWrap).forEach(g=>{
        let any=false;
        $$('label.prf-perm', g).forEach(row=>{
          const show = !q || row.dataset.key.includes(q);
          row.style.display = show ? '' : 'none';
          if(show) any=true;
        });
        g.style.display = any ? '' : 'none';
      });
    });
  }

  function setDirty(v){ dirty = v; btnSave.disabled = !dirty; }

  async function fetchPerms(id){
    if (!cfg.endpoints.perm_get){ toast('Endpoint permisos no disponible','warn'); return; }
    try{
      const res = await fetch(cfg.endpoints.perm_get + (cfg.endpoints.perm_get.includes('?')?'&':'?') + 'id=' + encodeURIComponent(id), { headers:{'X-Requested-With':'XMLHttpRequest'} });
      if(!res.ok) throw new Error('HTTP '+res.status);
      return await res.json();
    }catch(e){ console.error(e); toast('Error cargando permisos','err'); }
  }

  async function savePerms(){
    if (!cfg.endpoints.perm_save) return toast('Endpoint guardado no disponible','warn');
    const ids = $$('input[type=checkbox]', groupsWrap).filter(ch=> ch.checked).map(ch=> ch.value);
    try{
      btnSave.disabled=true;
      const res = await fetch(cfg.endpoints.perm_save, {
        method:'POST',
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':cfg.csrf,'X-Requested-With':'XMLHttpRequest'},
        body: JSON.stringify({ id: currentId, perms: ids })
      });
      if(!res.ok) throw new Error('HTTP '+res.status);
      toast('Permisos guardados','ok'); closeModal(); location.reload();
    }catch(e){ console.error(e); toast('Error al guardar','err'); btnSave.disabled=false; }
  }

  on(btnSave,'click', savePerms);
  on(btnAll,'click', ()=>{ $$('input[type=checkbox]', groupsWrap).forEach(ch=> ch.checked=true); setDirty(true); });
  on(btnNone,'click', ()=>{ $$('input[type=checkbox]', groupsWrap).forEach(ch=> ch.checked=false); setDirty(true); });

  // Abrir editor de permisos
  $$('.prf-perms-btn').forEach(b=>{
    on(b,'click', async ()=>{
      const id = b.dataset.perms; if(!id) return;
      titleId.textContent = '#'+id; currentId=id; openModal();
      const data = await fetchPerms(id); if (data) renderPerms(data);
    });
  });

  // ---------- Mini-bot ----------
  const bot = $('#prfBot'), botOpen = $('#prfBotOpen'), botClose = $('#prfBotClose'), botIn = $('#prfBotInput'), botLog = $('#prfBotLog');
  function openBot(){ bot.setAttribute('aria-hidden','false'); botIn?.focus(); }
  function closeBot(){ bot.setAttribute('aria-hidden','true'); }
  on(botOpen,'click', openBot); on(botClose,'click', closeBot);
  function log(t,cls){ const p=document.createElement('div'); p.textContent=t; p.className=cls||'info'; botLog.appendChild(p); botLog.scrollTop=botLog.scrollHeight; }
  const cmds = {
    activos:   ()=> applyFilter({ac:'1'}),
    inactivos: ()=> applyFilter({ac:'0'}),
    'sin_permisos': ()=> applyFilter({noperms:'1'}), // si implementas este filtro en el controlador
    exportar:  ()=> { if(cfg.endpoints.export){ const a=document.createElement('a'); a.href=cfg.endpoints.export; a.click(); } }
  };
  function execBot(input){
    const q = String(input||'').toLowerCase().trim(); if(!q) return;
    if (cmds[q]){ cmds[q](); log('OK: '+q,'ok'); return; }
    if (q.startsWith('buscar ')){ const t=q.slice(7); applyFilter({q:t}); return; }
    if (q.startsWith('pp=')){ const n=q.slice(3); applyFilter({pp:n}); return; }
    // fallback: buscar texto
    applyFilter({q:input});
  }
  function applyFilter(map){
    const form = $('#prfFilters'); if(!form) return;
    Object.entries(map).forEach(([k,v])=>{
      let el = form.querySelector(`[name="${k}"]`); if(!el){ el=document.createElement('input'); el.type='hidden'; el.name=k; form.appendChild(el); }
      el.value = v;
    });
    form.submit();
  }
  on($('#prfBotSend'),'click',()=> execBot((botIn.value||'').trim()));
  on(botIn,'keydown',(e)=>{ if(e.key==='Enter'){ e.preventDefault(); execBot((botIn.value||'').trim()); }});

  // ---------- Atajos ----------
  on(document,'keydown', (e)=>{ const k=(e.key||'').toLowerCase(); if((e.ctrlKey||e.metaKey) && k==='k'){ e.preventDefault(); const i=$('#prfFilters input[name="q"]'); i?.focus(); i?.select?.(); } });

  // ---------- Toast mínimo ----------
  function toast(msg, cls){
    try{
      const host = document.getElementById('alerts') || (function(){ const h=document.createElement('div'); h.id='alerts'; document.body.appendChild(h); return h; })();
      const el = document.createElement('div');
      el.className = 'alert ' + (cls==='ok'?'alert-success':cls==='err'?'alert-error':cls==='warn'?'alert-warning':'alert-info');
      el.style.cssText='margin:6px 12px;background:#111827;color:#fff;padding:10px 12px;border-radius:10px;box-shadow:0 6px 18px rgba(0,0,0,.2)';
      el.textContent = String(msg||''); host.appendChild(el); setTimeout(()=> el.remove(), 3000);
    }catch(_){}
  }

  syncBulk();
})();
