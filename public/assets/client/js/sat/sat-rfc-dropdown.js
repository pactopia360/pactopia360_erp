// public/assets/client/js/sat-rfc-dropdown.js
// PACTOPIA360 · SAT · Dashboard (cliente) · Dropdown RFCs (checkboxes)

(() => {
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    const dd = document.getElementById('satRfcDropdown');
    const hidden = document.getElementById('satRfcs');
    if (!dd || !hidden) return;

    const trigger = dd.querySelector('#satRfcTrigger');
    const menu = dd.querySelector('#satRfcMenu');
    const allCb = dd.querySelector('#satRfcAll');
    const itemCbs = Array.from(dd.querySelectorAll('.satRfcItem'));
    const summary = dd.querySelector('#satRfcSummary');

    function updateHiddenAndSummary() {
      const selected = itemCbs.filter(cb => cb.checked).map(cb => cb.value);

      hidden.innerHTML = '';
      selected.forEach(val => {
        const opt = document.createElement('option');
        opt.value = val;
        opt.selected = true;
        hidden.appendChild(opt);
      });

      if (!summary) return;

      if (selected.length === 0) summary.textContent = '(Ningún RFC seleccionado)';
      else if (selected.length === itemCbs.length) {
        summary.textContent = '(Todos los RFCs)';
        if (allCb) allCb.checked = true;
      } else if (selected.length === 1) {
        const cb = itemCbs.find(c => c.value === selected[0]);
        summary.textContent = cb ? cb.parentElement.textContent.trim() : '(1 RFC seleccionado)';
        if (allCb) allCb.checked = false;
      } else {
        summary.textContent = selected.length + ' RFCs seleccionados';
        if (allCb) allCb.checked = false;
      }
    }

    if (trigger && menu) {
      trigger.addEventListener('click', () => menu.classList.toggle('is-open'));
      document.addEventListener('click', (e) => { if (!dd.contains(e.target)) menu.classList.remove('is-open'); });
    }

    if (allCb) {
      allCb.addEventListener('change', () => {
        const checked = allCb.checked;
        itemCbs.forEach(cb => (cb.checked = checked));
        updateHiddenAndSummary();
      });
    }

    itemCbs.forEach(cb => {
      cb.addEventListener('change', () => {
        if (allCb) allCb.checked = itemCbs.every(c => c.checked);
        updateHiddenAndSummary();
      });
    });

    // Defaults: todos seleccionados
    if (allCb) allCb.checked = true;
    itemCbs.forEach(cb => (cb.checked = true));
    updateHiddenAndSummary();
  });
})();
