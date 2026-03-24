// C:\wamp64\www\pactopia360_erp\public\assets\client\js\login.js
// P360 · Cliente Login JS (SOT) · v8.0
// FIX:
// - Toggle theme claro/oscuro funcional
// - Usa misma key que blade: p360_client_login_theme
// - Solo icono sol/luna
// - Detect correo/RFC, CapsLock, anti doble submit, RFC uppercase

(() => {
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    // ---------------------------------------------------
    // Tema (light/dark) — SOT aquí
    // ---------------------------------------------------
    const KEY = 'p360_client_login_theme';
    const root = document.documentElement;
    const body = document.body;
    const btnTheme = document.getElementById('themeToggle');
    const media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

    function getSavedTheme() {
      try {
        const saved = localStorage.getItem(KEY);
        if (saved === 'dark' || saved === 'light') return saved;
      } catch (e) {}
      return null;
    }

    function getSystemTheme() {
      return media && media.matches ? 'dark' : 'light';
    }

    function applyTheme(theme, persist = false) {
      const isDark = theme === 'dark';

      body.classList.toggle('theme-dark', isDark);
      body.classList.toggle('theme-light', !isDark);
      root.setAttribute('data-login-theme', isDark ? 'dark' : 'light');

      if (btnTheme) {
        btnTheme.setAttribute('aria-pressed', isDark ? 'true' : 'false');
        btnTheme.setAttribute('aria-label', isDark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro');
        btnTheme.setAttribute('title', isDark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro');
      }

      if (persist) {
        try {
          localStorage.setItem(KEY, isDark ? 'dark' : 'light');
        } catch (e) {}
      }
    }

    applyTheme(getSavedTheme() || getSystemTheme(), false);

    if (btnTheme) {
      btnTheme.addEventListener('click', (e) => {
        e.preventDefault();
        const next = body.classList.contains('theme-dark') ? 'light' : 'dark';
        applyTheme(next, true);
      });
    }

    if (media && typeof media.addEventListener === 'function') {
      media.addEventListener('change', (e) => {
        const saved = getSavedTheme();
        if (!saved) {
          applyTheme(e.matches ? 'dark' : 'light', false);
        }
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

      if (login.value) {
        renderDetect(login.value);
      }

      login.addEventListener('blur', () => {
        const v = (login.value || '').trim();
        if (/^[a-z0-9&ñ]{3,4}\d{6}[a-z0-9]{3}$/i.test(v)) {
          login.value = v.toUpperCase();
        }
      });
    }

    // ---------------------------------------------------
    // Mostrar/ocultar contraseña
    // ---------------------------------------------------
    const pwd = document.getElementById('password');
    const btnPwd = document.getElementById('pwdToggle');

    if (btnPwd && pwd) {
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
      pwd.addEventListener('blur', () => {
        capsEl.style.display = 'none';
      });
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

        if (pwd) {
          pwd.value = (pwd.value || '').trim();
        }
      });
    }
  });
})();