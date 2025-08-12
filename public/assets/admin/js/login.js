(function () {
  // -----------------------------
  // Toggle de tema (oscuro/ligero)
  // -----------------------------
  const STORAGE_KEY = 'p360_theme';
  const body = document.body;
  const btnTheme = document.getElementById('themeToggle');

  function applyTheme(theme) {
    if (theme === 'light') {
      body.classList.add('theme-light');
      if (btnTheme) {
        btnTheme.querySelector('.icon').textContent = '🌞';
        btnTheme.querySelector('.label').textContent = 'Modo claro';
      }
    } else {
      body.classList.remove('theme-light');
      if (btnTheme) {
        btnTheme.querySelector('.icon').textContent = '🌙';
        btnTheme.querySelector('.label').textContent = 'Modo oscuro';
      }
    }
  }

  // Inicial: lee preferencia o usa oscuro
  const saved = localStorage.getItem(STORAGE_KEY) || 'dark';
  applyTheme(saved);

  // Click: alterna y guarda
  if (btnTheme) {
    btnTheme.addEventListener('click', function () {
      const next = body.classList.contains('theme-light') ? 'dark' : 'light';
      localStorage.setItem(STORAGE_KEY, next);
      applyTheme(next);
    });
  }

  // -----------------------------
  // UX login: mostrar/ocultar pass
  // -----------------------------
  const pwd = document.getElementById('password');
  const btnPwd = document.querySelector('.toggle');
  if (btnPwd && pwd) {
    btnPwd.addEventListener('click', function () {
      if (pwd.type === 'password') { pwd.type = 'text'; btnPwd.textContent = 'Ocultar'; }
      else { pwd.type = 'password'; btnPwd.textContent = 'Mostrar'; }
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
})();
