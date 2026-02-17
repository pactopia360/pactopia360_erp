// C:\wamp64\www\pactopia360_erp\public\assets\admin\js\payments-center.js
(function () {
  const $ = (sel, root=document) => root.querySelector(sel);
  const $$ = (sel, root=document) => Array.from(root.querySelectorAll(sel));

  function openModal(id){
    const el = document.getElementById(id);
    if(!el) return;
    el.setAttribute('aria-hidden','false');
    document.body.classList.add('p360-modal-open');
  }

  function closeModal(id){
    const el = document.getElementById(id);
    if(!el) return;
    el.setAttribute('aria-hidden','true');
    document.body.classList.remove('p360-modal-open');
  }

  // modal open/close
  $$('[data-open]').forEach(btn=>{
    btn.addEventListener('click', ()=> openModal(btn.getAttribute('data-open')));
  });
  $$('[data-close]').forEach(btn=>{
    btn.addEventListener('click', ()=> closeModal(btn.getAttribute('data-close')));
  });

  // EDIT button => fill modal
  $$('.p360-table [data-edit="1"]').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const tr = btn.closest('tr');
      if(!tr) return;
      let pay = null;
      try {
        const raw = tr.getAttribute('data-pay') || '{}';
        // por si el navegador devuelve entidades HTML, normalizamos
        const txt = raw.replace(/&quot;/g,'"').replace(/&#039;/g,"'").replace(/&amp;/g,"&");
        pay = JSON.parse(txt);
        } catch(e){
        pay = {};
        }

      const id = pay.id;
      if(!id) return;

      const form = $('#p360EditForm');
      form.action = form.action.replace(/\/\d+$/, '') || form.action; // noop if empty
      // Build URL by replacing placeholder: we'll set directly using current location base + id
      // Blade generates real route on server? Not here. We just set relative:
      form.action = `/admin/billing/payments/${id}`;

      $('#p360_e_id').value = `#${id}`;
      $('#p360_e_account').value = pay.account_id ?? '';

      $('#p360_e_status').value = (pay.status ?? 'paid');
      $('#p360_e_amount').value = (pay.amount_pesos ?? '');
      $('#p360_e_currency').value = (pay.currency ?? 'MXN');

      // paid_at => datetime-local (YYYY-MM-DDTHH:MM)
      const pa = (pay.paid_at ?? '') + '';
      if(pa && pa !== 'null'){
        const v = pa.replace(' ', 'T').slice(0,16);
        $('#p360_e_paid_at').value = v;
      } else {
        $('#p360_e_paid_at').value = '';
      }

      $('#p360_e_period').value = (pay.period ?? '');
      $('#p360_e_concept').value = (pay.concept ?? '');
      $('#p360_e_method').value = (pay.method ?? '');
      $('#p360_e_provider').value = (pay.provider ?? '');
      $('#p360_e_reference').value = (pay.reference ?? '');

      openModal('p360-modal-edit');
    });
  });

  // charts
  const data = window.P360_PAYMENTS_CHART || {};
  const line = data.line || {labels:[], data:[]};
  const donut = data.donut || {labels:[], data:[]};

  const lineEl = document.getElementById('p360Line');
  if(lineEl && window.Chart){
    new Chart(lineEl, {
      type: 'line',
      data: {
        labels: line.labels,
        datasets: [{
          label: 'MXN',
          data: line.data,
          tension: 0.25
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: { y: { beginAtZero: true } }
      }
    });
  }

  const donutEl = document.getElementById('p360Donut');
  if(donutEl && window.Chart){
    new Chart(donutEl, {
      type: 'doughnut',
      data: {
        labels: donut.labels,
        datasets: [{
          data: donut.data
        }]
      },
      options: { responsive: true, maintainAspectRatio: false }
    });
  }
})();
