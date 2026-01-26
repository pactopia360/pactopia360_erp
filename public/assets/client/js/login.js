// C:\wamp64\www\pactopia360_erp\public\assets\client\js\login.js
// P360 · Cliente Login JS (SOT) · v7.0
// FIX:
// - Toggle password NO rompe SVG (no usa textContent)
// - Evita doble lógica (tema + password) vs inline
// - Unifica storage key: p360-theme-client
// - Detect correo/RFC, CapsLock, anti doble submit, RFC uppercase

(() => {
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {

    // ---------------------------------------------------
    // Tema (light/dark) — SOT aquí
    // ---------------------------------------------------
    const KEY = 'p360-theme-client';
    const body = document.body;
    const btnTheme = document.getElementById('themeToggle');

    const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    const saved = localStorage.getItem(KEY);
    const initial = saved || (prefersDark ? 'dark' : 'light');

    function applyTheme(theme) {
      const isDark = theme === 'dark';
      body.classList.toggle('theme-dark', isDark);
      body.classList.toggle('theme-light', !isDark);

      if (btnTheme) {
        btnTheme.setAttribute('aria-pressed', String(isDark));
        const iconEl = btnTheme.querySelector('.icon');
        const labelEl = btnTheme.querySelector('.label');
        if (iconEl) iconEl.textContent = isDark ? '🌞' : '🌙';
        if (labelEl) labelEl.textContent = isDark ? 'Modo claro' : 'Modo oscuro';
      }
    }

    applyTheme(initial);

    if (btnTheme) {
      btnTheme.addEventListener('click', () => {
        const toDark = !body.classList.contains('theme-dark');
        localStorage.setItem(KEY, toDark ? 'dark' : 'light');
        applyTheme(toDark ? 'dark' : 'light');
      });
    }

    // ---------------------------------------------------
    // Helpers: detectar email/RFC
    // ---------------------------------------------------
    const login = document.getElementById('login');
    const help = document.getElementById('loginHelp');
    const msg = document.getElementById('detectMsg');

    const looksEmail = (v) => /\S+@\S+\.\S+/.test(v || '');
    const sanitizeRfc = (v) => (v || '').toUpperCase().replace(/[^A-Z0-9&Ñ]/g, '');
    const looksRfc = (v) => /^[A-Z&Ñ]{3,4}\d{6}[A-Z0-9]{3}$/.test(sanitizeRfc(v || ''));

    function renderDetect(val) {
      if (!help || !msg) return;
      const v = (val || '').trim();
      let text = '';
      if (looksEmail(v)) text = 'Detectamos formato de correo electrónico.';
      else if (looksRfc(v)) text = 'Detectamos formato de RFC.';
      help.textContent = text;
      help.style.display = text ? 'block' : 'none';
      msg.textContent = text;
      msg.style.display = text ? 'inline' : 'none';
    }

    if (login) {
      login.addEventListener('input', (e) => renderDetect(e.target.value));
      if (login.value) renderDetect(login.value);

      // RFC uppercase al salir
      login.addEventListener('blur', () => {
        const v = (login.value || '').trim();
        if (/^[a-z0-9&ñ]{3,4}\d{6}[a-z0-9]{3}$/i.test(v)) {
          login.value = v.toUpperCase();
        }
      });
    }

    // ---------------------------------------------------
    // Mostrar/ocultar contraseña (sin romper SVG)
    // ---------------------------------------------------
    const pwd = document.getElementById('password');
    const btnPwd = document.getElementById('pwdToggle');

    if (btnPwd && pwd) {
      // Estado inicial consistente
      btnPwd.dataset.showing = 'false';
      btnPwd.setAttribute('aria-pressed', 'false');
      btnPwd.setAttribute('aria-label', 'Mostrar contraseña');

      btnPwd.addEventListener('click', (ev) => {
        ev.preventDefault();
        const show = pwd.type === 'password';
        pwd.type = show ? 'text' : 'password';

        btnPwd.dataset.showing = show ? 'true' : 'false';
        btnPwd.setAttribute('aria-pressed', show ? 'true' : 'false');
        btnPwd.setAttribute('aria-label', show ? 'Ocultar contraseña' : 'Mostrar contraseña');

        // Fallback: si algún día el botón NO trae SVG y es texto, actualiza texto sin destruir HTML
        const hasSvg = !!btnPwd.querySelector('svg');
        if (!hasSvg) {
          btnPwd.textContent = show ? 'Ocultar' : 'Mostrar';
        }
      });
    }

    // ---------------------------------------------------
    // CapsLock tip
    // ---------------------------------------------------
    const capsEl = document.getElementById('capsTip');
    if (pwd && capsEl) {
      const caps = (e) => {
        const on = e.getModifierState && e.getModifierState('CapsLock');
        capsEl.style.display = on ? 'block' : 'none';
      };
      pwd.addEventListener('keydown', caps);
      pwd.addEventListener('keyup', caps);
      pwd.addEventListener('blur', () => (capsEl.style.display = 'none'));
    }

    // ---------------------------------------------------
    // Evitar doble submit + normalize pwd
    // ---------------------------------------------------
    const form = document.getElementById('loginForm');
    const btnSubmit = document.getElementById('btnSubmit');
    if (form) {
      form.addEventListener('submit', () => {
        if (btnSubmit) {
          btnSubmit.disabled = true;
          btnSubmit.textContent = 'Entrando…';
        }
        if (pwd) pwd.value = (pwd.value || '').trim();
      });
    }
  });
})();
