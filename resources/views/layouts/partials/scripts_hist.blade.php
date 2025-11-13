<script>
  /* ===========================
     Utilidades base
     =========================== */
  const MX_TZ_OFFSET = (new Date()).getTimezoneOffset(); // solo referencia

  const $  = (sel, ctx=document) => ctx.querySelector(sel);
  const $$ = (sel, ctx=document) => Array.from(ctx.querySelectorAll(sel));

  function fmt(d){
    // Retorna YYYY-MM-DD en local
    const y = d.getFullYear();
    const m = String(d.getMonth()+1).padStart(2,'0');
    const day = String(d.getDate()).padStart(2,'0');
    return `${y}-${m}-${day}`;
  }

  async function copyText(text){
    const val = text || '';
    try {
      if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(val);
        return true;
      }
    } catch(e){}
    // Fallback
    const ta = document.createElement('textarea');
    ta.value = val; ta.style.position = 'fixed'; ta.style.opacity = '0';
    document.body.appendChild(ta); ta.select();
    try { document.execCommand('copy'); } catch(_){}
    document.body.removeChild(ta);
    return true;
  }

  // RFC uppercase (si existen)
  ['rfc','rq_rfc'].forEach(id=>{
    const el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('input',()=>{ el.value = el.value.toUpperCase(); });
    el.setAttribute('autocomplete','off');
    el.setAttribute('spellcheck','false');
  });

  /* ===========================
     Fechas default (Solicitar)
     =========================== */
  (function initDefaultDates(){
    const from = $('#rq_from'), to = $('#rq_to');
    if (!from || !to) return;

    // Si no hay valores, último 7 días (incluye hoy)
    if (!from.value || !to.value) {
      const t  = new Date();
      const d7 = new Date(t.getTime() - 6*24*60*60*1000);
      from.value = fmt(d7);
      to.value   = fmt(t);
    }

    // Asegura from <= to
    function clamp(){
      const a = new Date(from.value || new Date());
      const b = new Date(to.value   || new Date());
      if (a > b) {
        // si se invierte, mueve 'to' al mismo día que 'from'
        to.value = fmt(a);
      }
    }
    from.addEventListener('change', clamp);
    to.addEventListener('change', clamp);
  })();

  /* ===========================
     Rangos rápidos
     =========================== */
  (function(){
    const from = $('#rq_from'), to = $('#rq_to');
    if (!from || !to) return;

    function setRange(a, b){ from.value = fmt(a); to.value = fmt(b); }

    $$.call(document, '[data-range]').forEach(btn=>{
      btn.addEventListener('click',()=>{
        const r = btn.dataset.range;
        const now = new Date();

        // Normalizaciones
        const startOfMonth = new Date(now.getFullYear(), now.getMonth(), 1);
        const startOfYear  = new Date(now.getFullYear(), 0, 1);
        let a = new Date(now), b = new Date(now);

        switch(r){
          case 'hoy':   a = now; b = now; break;
          case 'ayer':  a = new Date(now.getFullYear(), now.getMonth(), now.getDate()-1);
                        b = new Date(a); break;
          case '7d':    a = new Date(now.getTime()-6*24*60*60*1000); b = now; break;
          case '30d':   a = new Date(now.getTime()-29*24*60*60*1000); b = now; break;
          case 'mes':   a = startOfMonth; b = now; break;
          case 'anio':  a = startOfYear;  b = now; break;
          case '2022':  a = new Date(2022,0,1); b = new Date(2022,11,31); break;
          default:      a = startOfMonth; b = now; break;
        }
        setRange(a,b);
        // disparamos change para persistencia/URL
        from.dispatchEvent(new Event('change')); to.dispatchEvent(new Event('change'));
      });
    });
  })();

  /* ===========================
     Persistencia filtros (Solicitar) + URL sync
     =========================== */
  (function persistSolicitar(){
    const K = 'p360.sat.request';
    const rfc  = $('#rq_rfc');
    const f    = $('#rq_from');
    const t    = $('#rq_to');
    const tipo = $('#rq_tipo');

    // Carga desde storage y querystring (?rfc=...&from=...&to=...&tipo=...)
    try {
      const p = new URLSearchParams(location.search);
      const qs = {
        rfc:  p.get('rfc')  || '',
        from: p.get('from') || '',
        to:   p.get('to')   || '',
        tipo: p.get('tipo') || ''
      };
      const st = JSON.parse(localStorage.getItem(K)||'{}');

      const src = (qs.rfc || qs.from || qs.to || qs.tipo) ? qs : st;

      if (src.rfc  && rfc)  rfc.value  = src.rfc;
      if (src.from && f)    f.value    = src.from;
      if (src.to   && t)    t.value    = src.to;
      if (src.tipo && tipo) tipo.value = src.tipo;
    } catch(e){}

    function saveAndSync(){
      const v = {
        rfc:  rfc?.value || '',
        from: f?.value   || '',
        to:   t?.value   || '',
        tipo: tipo?.value|| 'recibidos'
      };
      try { localStorage.setItem(K, JSON.stringify(v)); } catch(e){}
      // Actualiza querystring (sin recargar)
      try {
        const url = new URL(location.href);
        Object.entries(v).forEach(([k,val])=>{
          if (val) url.searchParams.set(k, val);
          else url.searchParams.delete(k);
        });
        history.replaceState(null, '', url);
      } catch(e){}
    }

    ['change','input'].forEach(ev=>{
      rfc?.addEventListener(ev, saveAndSync);
      f  ?.addEventListener(ev, saveAndSync);
      t  ?.addEventListener(ev, saveAndSync);
      tipo?.addEventListener(ev, saveAndSync);
    });
  })();

  /* ===========================
     Exportar CSV/XLS (solo filas visibles)
     =========================== */
  function getVisibleRows(){
    const rows = [];
    $$('#tbDescargas tr').forEach(tr=>{
      if (tr.style.display === 'none') return;
      const tds = $$('.//td'.replace('//','td'), tr).map(td=> td.innerText.trim());
      rows.push(tds);
    });
    return rows;
  }

  $('#btnExportCsv')?.addEventListener('click', ()=>{
    const head = ['ID','RFC','Desde','Hasta','Tipo','Status','RequestID','PackageID','Zip'];
    const rows = [head];

    getVisibleRows().forEach(tds=>{
      // tds[2] => "YYYY-MM-DD → YYYY-MM-DD"
      const periodo = (tds[2] || '');
      const parts = periodo.split('→');
      const desde = (parts[0]||'').trim();
      const hasta = (parts[1]||'').trim();
      rows.push([
        tds[0]||'', tds[1]||'', desde, hasta,
        tds[3]||'', tds[4]||'', tds[5]||'', tds[6]||'', tds[7]||''
      ]);
    });

    const csv = rows.map(r => r.map(f => `"${String(f).replaceAll('"','""')}"`).join(',')).join('\r\n');
    const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'descargas_sat.csv';
    a.click();
    URL.revokeObjectURL(a.href);
  });

  $('#btnExportXls')?.addEventListener('click', ()=>{
    const table = $('#tblHist')?.cloneNode(true);
    if (!table) return;
    // Limpia filas ocultas
    $$('.//tbody tr'.replace('//',''), table).forEach(tr=>{
      if (tr.style.display === 'none') tr.remove();
      // Quita botones/inputs para exportación “limpia”
      $$('.btn,button,input,form', tr).forEach(el=> el.remove());
    });
    const html = `
      <html><head><meta charset="UTF-8"></head>
      <body>${table.outerHTML}</body></html>
    `;
    const blob = new Blob([html], {type:'application/vnd.ms-excel'});
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'descargas_sat.xls';
    a.click();
    URL.revokeObjectURL(a.href);
  });

  /* ===========================
     Filtros del Histórico (cliente)
     =========================== */
  (function initFilters(){
    const fSearch = $('#fSearch');
    const fTipo   = $('#fTipo');
    const fStatus = $('#fStatus');

    // Debounce para búsqueda libre
    function debounce(fn, ms){ let h; return (...args)=>{ clearTimeout(h); h=setTimeout(()=>fn(...args), ms); }; }
    function normalize(s){ return (s||'').toLowerCase(); }

    const applyFilters = ()=>{
      const q   = normalize(fSearch?.value);
      const tip = fTipo?.value || '';
      const st  = fStatus?.value || '';

      $$('#tbDescargas tr').forEach(tr=>{
        const txt   = normalize(tr.innerText);
        const passQ = !q   || txt.includes(q);
        const passT = !tip || (tr.dataset.tipo === tip);
        const passS = !st  || (tr.dataset.status === st);
        tr.style.display = (passQ && passT && passS) ? '' : 'none';
      });
    };
    const applyFiltersDebounced = debounce(applyFilters, 120);

    fSearch?.addEventListener('input', applyFiltersDebounced);
    fTipo  ?.addEventListener('change', applyFilters);
    fStatus?.addEventListener('change', applyFilters);

    // Aplica una vez por si llegaron con querystring
    applyFilters();
  })();

  /* ===========================
     Botones de submit: bloqueo breve
     =========================== */
  function block(btnId, label='Procesando…'){
    const b = document.getElementById(btnId);
    if (!b) return;
    const t = b.innerText;
    b.disabled = true;
    b.dataset.t = t;
    b.innerText = label;
    // Rehabilita por si el backend aún no respondió; evita bloquear UX
    setTimeout(()=>{ try { b.disabled = false; b.innerText = t; } catch(_){} }, 6000);
  }
  $('#btnSaveCred')?.addEventListener('click', ()=>block('btnSaveCred'));
  $('#btnRequest') ?.addEventListener('click', ()=>block('btnRequest'));
  $('#btnDownload')?.addEventListener('click', ()=>block('btnDownload'));
  $('#btnRefresh') ?.addEventListener('click', ()=>location.reload());


  // ===== Limpiar filtros (Histórico + Solicitar) y resetear URL + storage =====
  (function(){
    const K = 'p360.sat.request';
    const btn = document.getElementById('btnClearFilters');
    if (!btn || btn.dataset.bound) return;
    btn.dataset.bound = '1';

    btn.addEventListener('click', () => {
      // --- 1) Limpia filtros de Histórico (tabla)
      const fSearch = document.getElementById('fSearch');
      const fTipo   = document.getElementById('fTipo');
      const fStatus = document.getElementById('fStatus');
      if (fSearch){ fSearch.value = ''; fSearch.dispatchEvent(new Event('input')); }
      if (fTipo){   fTipo.value   = ''; fTipo.dispatchEvent(new Event('change'));  }
      if (fStatus){ fStatus.value = ''; fStatus.dispatchEvent(new Event('change'));}

      // --- 2) Resetea filtros de "Solicitar"
      const rq_rfc  = document.getElementById('rq_rfc');
      const rq_from = document.getElementById('rq_from');
      const rq_to   = document.getElementById('rq_to');
      const rq_tipo = document.getElementById('rq_tipo');

      // Default: últimos 7 días (incluye hoy)
      const now = new Date();
      const d7  = new Date(now.getTime() - 6*24*60*60*1000);
      if (rq_rfc)  rq_rfc.value  = '';
      if (rq_from) rq_from.value = `${now.getFullYear()}-${String(d7.getMonth()+1).padStart(2,'0')}-${String(d7.getDate()).padStart(2,'0')}`; // lo sobreescribimos abajo con fmt
      if (rq_to)   rq_to.value   = `${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}-${String(now.getDate()).padStart(2,'0')}`;
      // Usa fmt para asegurar 0-padding correcto
      if (typeof fmt === 'function') {
        if (rq_from) rq_from.value = fmt(d7);
        if (rq_to)   rq_to.value   = fmt(now);
      }
      if (rq_tipo) rq_tipo.value = 'recibidos';

      // Dispara eventos para que cualquier listener (persistencia/validación) reaccione
      [rq_rfc, rq_from, rq_to, rq_tipo].forEach(el=>{
        if (!el) return;
        el.dispatchEvent(new Event('input'));
        el.dispatchEvent(new Event('change'));
      });

      // --- 3) Limpia storage
      try { localStorage.removeItem(K); } catch(e){}

      // --- 4) Limpia querystring en URL (sin recargar)
      try {
        const url = new URL(location.href);
        ['rfc','from','to','tipo'].forEach(p => url.searchParams.delete(p));
        history.replaceState(null, '', url);
      } catch(e){}

      // --- 5) Feedback opcional (no bloqueante)
      try {
        if (window.P360?.toast) P360.toast('Filtros restaurados');
      } catch(_){}
    });
  })();

  // Exponer copyText global si ya lo usan en otras vistas
  window.copyText = window.copyText || copyText;


  /* =========================================================
     NUEVO: Exportar IMAGEN (PNG/JPG) de la pantalla solicitada
     - Escucha #btnExportPng y #btnExportJpg si existen
     - Captura el bloque con data-shot, o si no, la tarjeta del
       histórico .sat-card que contenga #tblHist; fallback al body
     - Inserta un encabezado con logo + título + fecha/hora
     - No rompe nada si los botones no existen
     ========================================================= */

  // Carga perezosa de html2canvas desde CDN (una sola vez)
  let _h2cPromise = null;
  function ensureHtml2Canvas(){
    if (window.html2canvas) return Promise.resolve(window.html2canvas);
    if (_h2cPromise) return _h2cPromise;
    _h2cPromise = new Promise((resolve, reject)=>{
      const s = document.createElement('script');
      s.src = 'https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js';
      s.async = true;
      s.onload = ()=> resolve(window.html2canvas);
      s.onerror = ()=> reject(new Error('No se pudo cargar html2canvas'));
      document.head.appendChild(s);
    });
    return _h2cPromise;
  }

  function nowStamp(){
    const d = new Date();
    const y = d.getFullYear();
    const m = String(d.getMonth()+1).padStart(2,'0');
    const day = String(d.getDate()).padStart(2,'0');
    const hh = String(d.getHours()).padStart(2,'0');
    const mm = String(d.getMinutes()).padStart(2,'0');
    return `${y}-${m}-${day} ${hh}:${mm}`;
  }

  function findShotNode(){
    // prioridad: [data-shot]
    let node = $('[data-shot]');
    if (node) return node;

    // luego: la card que contiene la tabla del histórico
    const tbl = $('#tblHist');
    if (tbl){
      const card = tbl.closest('.sat-card') || tbl.closest('.card');
      if (card) return card;
      return tbl;
    }

    // fallback: todo el body
    return document.body;
  }

  function getBrandAssets(){
    const isDark = document.documentElement.classList.contains('theme-dark');
    // Cliente: rutas estándar
    const logoLight = '/assets/client/P360 BLACK.png';
    const logoDark  = '/assets/client/P360 WHITE.png';
    return {
      isDark,
      logoUrl: isDark ? logoDark : logoLight,
      title: 'Histórico de descargas · Pactopia360'
    };
  }

  async function captureWithHeader(node, {type='png', scale=2}={}){
    const h2c = await ensureHtml2Canvas();

    // Captura del nodo
    const canvas = await h2c(node, {
      backgroundColor: window.getComputedStyle(document.body).backgroundColor || '#fff',
      scale: scale
    });

    // Construye un canvas con header
    const pad = 16 * scale;
    const headerH = 64 * scale;
    const out = document.createElement('canvas');
    out.width  = canvas.width;
    out.height = canvas.height + headerH + pad;
    const ctx = out.getContext('2d');

    // Header background
    const {isDark, logoUrl, title} = getBrandAssets();
    ctx.fillStyle = isDark ? '#0b1220' : '#ffffff';
    ctx.fillRect(0, 0, out.width, headerH + pad);

    // Línea divisoria
    ctx.fillStyle = isDark ? 'rgba(255,255,255,.12)' : 'rgba(0,0,0,.08)';
    ctx.fillRect(0, headerH + pad - 1*scale, out.width, 1*scale);

    // Logo + textos
    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.src = logoUrl;
    await new Promise(res => { img.onload = res; img.onerror = res; });

    const logoH = 36 * scale;
    const ratio = img.naturalWidth && img.naturalHeight ? (img.naturalWidth / img.naturalHeight) : (180/36);
    const logoW = Math.round(logoH * ratio);
    const x0 = pad, y0 = Math.round((headerH - logoH)/2);
    if (img.naturalWidth) ctx.drawImage(img, x0, y0, logoW, logoH);

    ctx.fillStyle = isDark ? '#e5e7eb' : '#0f172a';
    ctx.font = `600 ${14*scale}px system-ui, -apple-system, Segoe UI, Roboto, Arial`;
    ctx.fillText(title, x0 + logoW + 12*scale, y0 + 18*scale);

    ctx.fillStyle = isDark ? '#9aa3af' : '#6b7280';
    ctx.font = `500 ${12*scale}px system-ui, -apple-system, Segoe UI, Roboto, Arial`;
    ctx.fillText(`Generado: ${nowStamp()}`, x0 + logoW + 12*scale, y0 + 36*scale);

    // Contenido capturado
    ctx.drawImage(canvas, 0, headerH + pad);

    // Salida
    return await new Promise(resolve=>{
      out.toBlob(blob=>{
        resolve(blob);
      }, type === 'jpg' ? 'image/jpeg' : 'image/png', 0.92);
    });
  }

  async function exportShot(kind='png'){
    try{
      const node = findShotNode();
      const blob = await captureWithHeader(node, {type: kind, scale: 2});
      const a = document.createElement('a');
      const ext = kind === 'jpg' ? 'jpg' : 'png';
      a.href = URL.createObjectURL(blob);
      a.download = `pactopia360_historial_${fmt(new Date())}.${ext}`;
      a.click();
      URL.revokeObjectURL(a.href);
      try { window.P360?.toast?.('Imagen exportada'); } catch(_){}
    }catch(e){
      console.error(e);
      try { window.P360?.toast?.('No se pudo exportar la imagen'); } catch(_){}
    }
  }

  // Enlaza si existen los botones
  $('#btnExportPng')?.addEventListener('click', ()=> exportShot('png'));
  $('#btnExportJpg')?.addEventListener('click', ()=> exportShot('jpg'));
</script>
