(function () {
  // -----------------------------
  // Toggle de tema (oscuro/ligero)
  // -----------------------------
  const STORAGE_KEY = 'p360_theme_client';
  const body = document.body;
  const btnTheme = document.getElementById('themeToggle');

  function paintLabel() {
    const isLight = body.classList.contains('theme-light');
    if (btnTheme) {
      const iconEl  = btnTheme.querySelector('.icon');
      const labelEl = btnTheme.querySelector('.label');
      if (iconEl)  iconEl.textContent  = isLight ? '🌙' : '☀️';
      if (labelEl) labelEl.textContent = isLight ? 'Modo oscuro' : 'Modo claro';
      btnTheme.setAttribute('aria-pressed', (!isLight).toString());
    }
  }

  function applyTheme(theme) {
    if (theme === 'light') body.classList.add('theme-light');
    else body.classList.remove('theme-light');
    paintLabel();
  }

  const saved = localStorage.getItem(STORAGE_KEY) || 'light';
  applyTheme(saved);

  if (btnTheme) {
    btnTheme.addEventListener('click', function () {
      const next = body.classList.contains('theme-light') ? 'dark' : 'light';
      localStorage.setItem(STORAGE_KEY, next);
      applyTheme(next);
    });
  }

  // -----------------------------
  // Mostrar/ocultar contraseña
  // -----------------------------
  const pwd    = document.getElementById('password');
  const btnPwd = document.querySelector('.toggle');
  if (btnPwd && pwd) {
    btnPwd.addEventListener('click', function () {
      const show = (pwd.type === 'password');
      pwd.type = show ? 'text' : 'password';
      btnPwd.textContent = show ? 'Ocultar' : 'Mostrar';
    });
  }

  // -----------------------------
  // Evitar doble submit
  // -----------------------------
  const form = document.getElementById('loginForm');
  const btnSubmit = document.getElementById('btnSubmit');
  if (form && btnSubmit) {
    form.addEventListener('submit', function () {
      btnSubmit.disabled = true;
      btnSubmit.textContent = 'Entrando…';
    });
  }

  // -----------------------------
  // Normalizar RFC en el campo correcto (#login)
  // -----------------------------
  const loginInput = document.getElementById('login');
  if (loginInput) {
    loginInput.addEventListener('blur', ()=> {
      const v = (loginInput.value || '').trim();
      if (/^[a-z0-9&ñ]{3,4}\d{6}[a-z0-9]{3}$/i.test(v)) {
        loginInput.value = v.toUpperCase();
      }
    });
  }
})();
