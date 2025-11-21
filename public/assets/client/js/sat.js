// PACTOPIA360 · SAT · módulo descargas masivas CFDI
(() => {
  const cfg = window.P360_SAT || {};
  const SAT_ROUTES        = cfg.routes || {};
  const SAT_ZIP_PATTERN   = cfg.zipPattern || '#';
  const SAT_DOWNLOAD_ROWS = Array.isArray(cfg.downloadRows) ? cfg.downloadRows : [];
  const SAT_CSRF          = cfg.csrf || '';
  const IS_PRO_PLAN       = !!cfg.isProPlan;
  const STORAGE           = cfg.storage || {};
  const BILLING           = cfg.billing || {};

  /* ===== Helpers ===== */

  function normalizeStatus(s){
    s = (s || '').toString().toLowerCase();
    if (['ready','done','listo'].includes(s)) return 'done';
    if (['error','failed','fail'].includes(s)) return 'error';
    return s;
  }

  // Tabla de tarifas de descarga (por XML)
  // 1–5000 a $1
  // 5001–25000 a $0.08
  // 25001–40000 a $0.05
  // 50000–100000 a $0.03
  // luego bloques fijos:
  // <= 500,000 -> 12,500
  // <= 1,000,000 -> 18,500
  // <= 2,000,000 -> 25,000
  // <= 3,000,000 -> 31,000
  // > 3,000,000 -> 0.01 por XML
  function computeDownloadCost(numXml) {
    const n = Number(numXml || 0);
    if (!n || n <= 0) return 0;

    if (n <= 5000)   return n * 1;
    if (n <= 25000)  return n * 0.08;
    if (n <= 40000)  return n * 0.05;
    if (n <= 100000) return n * 0.03;

    if (n <= 500000)  return 12500;
    if (n <= 1000000) return 18500;
    if (n <= 2000000) return 25000;
    if (n <= 3000000) return 31000;

    return n * 0.01;
  }

  function formatMoney(n) {
    const v = Number(n || 0);
    if (!v) return '—';
    try {
      return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        maximumFractionDigits: 2,
      }).format(v);
    } catch (_) {
      return '$' + v.toFixed(2);
    }
  }

  /* ===== LISTADO DESCARGAS: filtros + acciones + costo ===== */
  (() => {
    const rows      = SAT_DOWNLOAD_ROWS;
    const body      = document.getElementById('satDlBody');
    const qInput    = document.getElementById('satDlSearch');
    const tipoSel   = document.getElementById('satDlTipo');
    const statusSel = document.getElementById('satDlStatus');

    function render(){
      if (!body) return;
      const q      = (qInput?.value || '').toLowerCase();
      const ftipo  = (tipoSel?.value || '').toLowerCase();
      const fstat  = (statusSel?.value || '').toLowerCase();

      body.innerHTML = '';

      if (!rows.length){
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = 10;
        td.className = 'empty';
        td.textContent = 'Aún no has generado solicitudes de descarga.';
        tr.appendChild(td);
        body.appendChild(tr);
        return;
      }

      rows.forEach(r => {
        const id        = (r.dlid || r.id || '').toString();
        const rfc       = (r.rfc || '').toString();
        const razon     = (r.razon || r.razon_social || '').toString();
        const tipo      = (r.tipo || '').toString().toLowerCase();
        const statusRaw = (r.estado || r.status || '').toString();
        const status    = normalizeStatus(statusRaw);
        const packageId = (r.package_id || '').toString();
        const xmlCount  = Number(r.xml_count || r.total_xml || 0);
        // Si backend ya manda costo calculado lo respetamos, si no lo calculamos
        const costoCalc = ('costo' in r && r.costo != null)
          ? Number(r.costo)
          : computeDownloadCost(xmlCount);

        if (q) {
          const qq = q.toLowerCase();
          if (
            !id.toLowerCase().includes(qq) &&
            !rfc.toLowerCase().includes(qq) &&
            !(razon && razon.toLowerCase().includes(qq))
          ) {
            return;
          }
        }
        if (ftipo && tipo !== ftipo) return;
        if (fstat && status !== fstat) return;

        const tr = document.createElement('tr');

        const periodo = (r.desde || '') && (r.hasta || '')
          ? `${r.desde} → ${r.hasta}`
          : '';

        const labelTipo = tipo
          ? ('• ' + tipo.charAt(0).toUpperCase() + tipo.slice(1))
          : '';

        let badgeClass = '';
        if (status === 'done')      badgeClass = 'done';
        else if (status === 'error')badgeClass = 'error';
        else if (status)           badgeClass = status;

        const costoLabel = costoCalc ? formatMoney(costoCalc) : '—';
        const xmlLabel   = xmlCount || '—';

        // Acciones según estado
        let actionsHtml = '';

        // Estado DONE: ZIP + XML + PDF + botón pagar
        if (status === 'done') {
          actionsHtml += `
            <button
              class="sat-dl-btn-download"
              data-id="${id}"
              data-status="${status}"
              data-kind="zip"
              title="Descargar ZIP"
            >ZIP</button>
            <button
              class="sat-dl-btn-download"
              data-id="${id}"
              data-status="${status}"
              data-kind="xml"
              title="Descargar XML (cuando aplique)"
            >XML</button>
            <button
              class="sat-dl-btn-download"
              data-id="${id}"
              data-status="${status}"
              data-kind="pdf"
              title="Descargar PDF (cuando aplique)"
            >PDF</button>
            <button
              class="btn primary sat-btn-pay"
              data-id="${id}"
              data-xml="${xmlCount}"
              data-cost="${costoCalc}"
              title="Pagar descarga y habilitar en bóveda"
            >Pagar descarga</button>
          `;
        }
        else if (status === 'error') {
          // Errores: TXT con detalles
          actionsHtml += `
            <button
              class="sat-dl-btn-download"
              data-id="${id}"
              data-status="${status}"
              data-kind="error"
              title="Descargar TXT con detalle del error"
            >TXT error</button>
          `;
        }
        else {
          // Pending / processing: solo botón para preparar ZIP (como antes)
          actionsHtml += `
            <button
              class="sat-dl-btn-download"
              data-id="${id}"
              data-status="${status}"
              data-kind="zip"
              title="Preparar descarga"
            >Prep.</button>
          `;
        }

        tr.innerHTML = `
          <td class="mono">${id}</td>
          <td class="mono">${rfc}</td>
          <td>${razon || '—'}</td>
          <td>${labelTipo}</td>
          <td>${periodo}</td>
          <td><span class="sat-badge-status ${badgeClass}">${status || ''}</span></td>
          <td class="mono">${packageId}</td>
          <td class="t-right">${xmlLabel}</td>
          <td class="t-right">${costoLabel}</td>
          <td>
            <div class="sat-dl-actions">
              ${actionsHtml}
            </div>
          </td>
        `;
        body.appendChild(tr);
      });
    }

    if (qInput)    qInput.addEventListener('input',  render);
    if (tipoSel)   tipoSel.addEventListener('change', render);
    if (statusSel) statusSel.addEventListener('change', render);

    if (body) {
      body.addEventListener('click', async (ev) => {
        const btnPay = ev.target.closest('.sat-btn-pay');
        if (btnPay) {
          const id   = btnPay.dataset.id;
          const xml  = Number(btnPay.dataset.xml || 0);
          const cost = Number(btnPay.dataset.cost || 0);
          // Timer de 2 horas y flujo de pago real
          if (!SAT_ROUTES.payDownload) {
            alert(
              `Pagar descarga\n\nID: ${id}\nXML: ${xml}\nMonto estimado: ${formatMoney(cost)}\n\n` +
              'Configura la ruta cliente.sat.pay en el backend para completar el flujo de pago y el timer de 2 horas.'
            );
            return;
          }
          // Ejemplo de POST; el backend deberá manejar el timer de 2h, estatus de pago, etc.
          try {
            btnPay.disabled = true;
            const fd = new FormData();
            fd.append('download_id', id);
            const res = await fetch(SAT_ROUTES.payDownload, {
              method:'POST',
              headers:{
                'X-Requested-With':'XMLHttpRequest',
                'X-CSRF-TOKEN': SAT_CSRF,
              },
              body: fd,
            });
            const j = await res.json().catch(()=>({}));
            if (!res.ok || j.ok === false) {
              alert(j.msg || 'No se pudo procesar el pago de la descarga.');
              btnPay.disabled = false;
              return;
            }
            alert('Descarga pagada correctamente. Podrás verla en la bóveda fiscal.');
            location.reload();
          } catch (e) {
            console.error(e);
            alert('Error de conexión al procesar el pago.');
            btnPay.disabled = false;
          }
          return;
        }

        const btn = ev.target.closest('.sat-dl-btn-download');
        if (!btn) return;

        const id     = btn.dataset.id;
        const status = (btn.dataset.status || '').toLowerCase();
        const kind   = (btn.dataset.kind || 'zip').toLowerCase();

        if (!id) return;

        // Por ahora sólo tenemos ZIP en backend. Otros tipos (xml, pdf, error)
        // se conectarán a nuevos endpoints en su momento.
        if (kind === 'zip' && status === 'done' && SAT_ZIP_PATTERN && SAT_ZIP_PATTERN !== '#') {
          const zipUrl = SAT_ZIP_PATTERN.replace('__ID__', id);
          window.location.href = zipUrl;
          return;
        }

        if (!SAT_ROUTES.download || SAT_ROUTES.download === '#') {
          alert('Ruta de descarga no configurada.');
          return;
        }

        btn.disabled = true;

        try{
          const fd = new FormData();
          fd.append('download_id', id);
          fd.append('kind', kind); // para que el backend sepa si es zip/xml/pdf/txt

          const res = await fetch(SAT_ROUTES.download, {
            method:'POST',
            headers:{
              'X-Requested-With':'XMLHttpRequest',
              'X-CSRF-TOKEN': SAT_CSRF,
            },
            body: fd,
          });

          const ct = res.headers.get('content-type') || '';
          let json = null;
          if (ct.includes('application/json')) {
            json = await res.json().catch(()=>null);
          }

          if (!res.ok) {
            alert(json?.msg || 'No se pudo preparar la descarga.');
            return;
          }

          if (json && json.url) {
            window.location.href = json.url;
          } else if (json && json.zip_url) {
            window.location.href = json.zip_url;
          } else {
            location.reload();
          }
        }catch(e){
          console.error(e);
          alert('Error de conexión al descargar.');
        }finally{
          btn.disabled = false;
        }
      });
    }

    render();
  })();

  /* ===== Actualizar pantalla ===== */
  (() => {
    const btn = document.getElementById('btnRefresh');
    if(!btn) return;
    btn.addEventListener('click', () => {
      location.reload();
    });
  })();

  /* ===== Verificar solicitudes (POST) ===== */
  (() => {
    const btn = document.getElementById('btnSatVerify');
    if(!btn || !SAT_ROUTES.verify) return;
    btn.addEventListener('click', async () => {
      btn.disabled = true;
      try{
        const r = await fetch(SAT_ROUTES.verify, {
          method:'POST',
          headers:{
            'X-Requested-With':'XMLHttpRequest',
            'X-CSRF-TOKEN':SAT_CSRF
          }
        });
        const j = await r.json().catch(()=>({}));
        alert(`Pendientes: ${j.pending ?? 0} · Listos: ${j.ready ?? 0}`);
        location.reload();
      }catch(e){
        console.error(e);
        alert('No se pudo verificar');
      }finally{
        btn.disabled = false;
      }
    });
  })();

  /* ===== Regla FREE: ≤ 1 mes ===== */
  (() => {
    const planIsPro = IS_PRO_PLAN;
    const f = document.getElementById('reqForm');
    if(!f || planIsPro) return;
    f.addEventListener('submit', ev=>{
      const d = new FormData(f);
      const a = new Date(d.get('from'));
      const b = new Date(d.get('to'));
      if(isNaN(+a) || isNaN(+b)) return;
      if((b - a) > 32*24*3600*1000){
        ev.preventDefault();
        alert('En FREE sólo puedes solicitar hasta 1 mes.');
      }
    });
  })();

  /* ===== Modal RFC (abrir / cerrar / submit) ===== */
  (() => {
    const modal = document.getElementById('modalRfc');
    const form  = document.getElementById('formRfc');
    if(!modal || !form) return;

    const open  = () => modal.classList.add('is-open');
    const close = () => modal.classList.remove('is-open');

    document.addEventListener('click',(ev)=>{
      const openBtn  = ev.target.closest('[data-open="add-rfc"]');
      const closeBtn = ev.target.closest('[data-close="modal-rfc"]');
      if(openBtn){
        ev.preventDefault();
        open();
      }
      if(closeBtn || ev.target === modal){
        ev.preventDefault();
        close();
      }
    });

    window.addEventListener('sat-open-add-rfc', open);

    form.addEventListener('submit', async (ev)=>{
      ev.preventDefault();
      const submitBtn = form.querySelector('button[type="submit"]');
      if(submitBtn) submitBtn.disabled = true;

      const fd = new FormData(form);
      const hasCer = fd.get('cer') instanceof File && fd.get('cer').name;
      const hasKey = fd.get('key') instanceof File && fd.get('key').name;
      const pwd    = (fd.get('key_password') || '').toString().trim();
      const useCsd = (hasCer || hasKey || pwd !== '');
      const url    = useCsd ? SAT_ROUTES.csdStore : SAT_ROUTES.rfcReg;

      if(!url || url === '#'){
        alert('Ruta de guardado de RFC no configurada.');
        if(submitBtn) submitBtn.disabled = false;
        return;
      }

      try{
        const res = await fetch(url, {
          method:'POST',
          headers:{'X-Requested-With':'XMLHttpRequest'},
          body: fd
        });
        let data = {};
        try{ data = await res.json(); }catch(_){}
        if(!res.ok || (data.ok === false)){
          const msg = data.msg || 'No se pudo guardar el RFC / CSD';
          alert(msg);
          if(submitBtn) submitBtn.disabled = false;
          return;
        }
        close();
        location.reload();
      }catch(e){
        console.error(e);
        alert('Error enviando datos');
      }finally{
        if(submitBtn) submitBtn.disabled = false;
      }
    });
  })();

  /* ===== Charts: almacenamiento (pastel) ===== */
  (() => {
    const el = document.getElementById('storageChart');
    if (!el || typeof Chart === 'undefined') return;

    const used  = Number(STORAGE.used_gb || 0);
    const free  = Number(STORAGE.free_gb || 0);

    new Chart(el, {
      type: 'doughnut',
      data: {
        labels: ['Consumido', 'Disponible'],
        datasets: [{
          data: [used, free],
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false }
        },
        cutout: '65%'
      }
    });
  })();

  /* ===== Charts: tendencias (4 gráficas) ===== */
  (() => {
    if(!SAT_ROUTES.charts || typeof Chart === 'undefined') return;

    function mkLineChart(id){
      const el = document.getElementById(id);
      if(!el) return null;
      return new Chart(el, {
        type:'line',
        data:{labels:[],datasets:[{label:'',data:[],borderWidth:2,tension:.35}]},
        options:{
          responsive:true,
          maintainAspectRatio:false,
          plugins:{legend:{display:false}},
          scales:{
            x:{grid:{display:false}},
            y:{grid:{color:'rgba(148,163,184,.25)'}}
          }
        }
      });
    }

    const chartCount     = mkLineChart('chartCount');
    const chartEmitidos  = mkLineChart('chartEmitidos');
    const chartRecibidos = mkLineChart('chartRecibidos');
    const chartImpuestos = mkLineChart('chartImpuestos');
    if(!chartCount || !chartEmitidos || !chartRecibidos || !chartImpuestos) return;

    const loadScope = async (scope) => {
      try{
        const url = SAT_ROUTES.charts + '?scope=' + encodeURIComponent(scope || 'emitidos');
        const res = await fetch(url, {headers:{'X-Requested-With':'XMLHttpRequest'}});
        if(!res.ok) throw new Error('HTTP '+res.status);
        const j = await res.json();
        const labels = j.labels || [];
        const s      = j.series || {};

        chartCount.data.labels     = labels;
        chartEmitidos.data.labels  = labels;
        chartRecibidos.data.labels = labels;
        chartImpuestos.data.labels = labels;

        // El backend puede mandar estos campos opcionales.
        chartCount.data.datasets[0].data     = s.counts      || s.cfdi_count  || [];
        chartCount.data.datasets[0].label    = s.label_count || '# CFDI';

        chartEmitidos.data.datasets[0].data  = s.emitidos    || s.amounts_emitidos || [];
        chartEmitidos.data.datasets[0].label = s.label_emitidos || 'Importe emitidos';

        chartRecibidos.data.datasets[0].data  = s.recibidos  || s.amounts_recibidos || [];
        chartRecibidos.data.datasets[0].label = s.label_recibidos || 'Importe recibidos';

        chartImpuestos.data.datasets[0].data  = s.impuestos  || [];
        chartImpuestos.data.datasets[0].label = s.label_impuestos || 'Impuestos';

        chartCount.update();
        chartEmitidos.update();
        chartRecibidos.update();
        chartImpuestos.update();
      }catch(e){
        console.error(e);
      }
    };

    loadScope('emitidos');

    document.querySelectorAll('.tabs-scope .tab').forEach(t=>{
      t.addEventListener('click',()=>{
        document.querySelectorAll('.tabs-scope .tab').forEach(x=>x.classList.remove('is-active'));
        t.classList.add('is-active');
        loadScope(t.dataset.scope || 'emitidos');
      });
    });
  })();

  /* ===== Buscador de RFCs en select múltiple ===== */
  (() => {
    const input  = document.getElementById('rfcSearch');
    const select = document.getElementById('satRfcs');
    if (!input || !select) return;

    const original = Array.from(select.options).map(o => ({
      value: o.value,
      text:  o.text,
      selected: o.selected,
    }));

    input.addEventListener('input', () => {
      const q = input.value.toLowerCase();
      select.innerHTML = '';

      original.forEach(o => {
        if (!q || o.text.toLowerCase().includes(q) || o.value.toLowerCase().includes(q)) {
          const opt = document.createElement('option');
          opt.value    = o.value;
          opt.text     = o.text;
          opt.selected = o.selected;
          select.appendChild(opt);
        }
      });
    });
  })();

})();
