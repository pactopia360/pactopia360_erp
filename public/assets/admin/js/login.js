/* public/assets/admin/js/login.js
 * Pactopia360 Admin Login UX (v1.0)
 * - Toggle password robusto
 * - CapsLock tip
 * - Anti double-submit
 */

(function () {
  'use strict';

  function byId(id){ return document.getElementById(id); }

  function togglePwd(){
    const inp = byId('password');
    const btn = byId('btnTogglePassword');
    if (!inp || !btn) return;

    const t = (inp.getAttribute('type') || '').toLowerCase();
    const isPw = t === 'password' || t === '';
    inp.setAttribute('type', isPw ? 'text' : 'password');
    btn.textContent = isPw ? 'Ocultar' : 'Mostrar';
    btn.setAttribute('aria-pressed', String(isPw));
  }

  document.addEventListener('DOMContentLoaded', function(){
    const btn = byId('btnTogglePassword');
    const inp = byId('password');
    const tip = byId('capsTip');
    const form = byId('loginForm');
    const submit = byId('btnSubmit');

    if (btn) {
      btn.addEventListener('click', function(e){
        e.preventDefault();
        togglePwd();
      });
    }

    if (inp) {
      inp.addEventListener('keyup', function(e){
        try {
          const on = e.getModifierState && e.getModifierState('CapsLock');
          if (tip) tip.style.display = on ? 'block' : 'none';
        } catch (_) {}
      });

      inp.addEventListener('blur', function(){
        if (tip) tip.style.display = 'none';
      });
    }

    // Anti double submit
    if (form && submit) {
      form.addEventListener('submit', function(){
        submit.disabled = true;
        submit.setAttribute('aria-busy', 'true');
        // si algo falla en backend y recarga, se restablece por la navegación
      });
    }
  });
})();
