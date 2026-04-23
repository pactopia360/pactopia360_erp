window.addEventListener('DOMContentLoaded', () => {
  const html     = document.documentElement;
  const root     = document.getElementById('p360-client');
  const btnTheme = document.getElementById('btnTheme');
  const logo     = document.getElementById('brandLogo');
  const btnProf  = document.getElementById('btnProfile');
  const ddProf   = document.getElementById('menuProfile');

  if (!root) return;

  const route      = root.getAttribute('data-theme-switch') || '';
  const lightSrc   = root.getAttribute('data-logo-light') || '';
  const darkSrc    = root.getAttribute('data-logo-dark')  || '';
  const sprite     = root.getAttribute('data-icon-sprite') || '';
  const csrf       = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  const forceLight = root.getAttribute('data-force-light') === '1';

  const hasInlineSprite = !!document.querySelector('svg[aria-hidden="true"]');
  const useHref = (id) => (hasInlineSprite ? `#${id}` : `${sprite}#${id}`);

  function setLogoForTheme(next) {
    if (!logo || !lightSrc || !darkSrc) return;
    logo.src = next === 'dark' ? darkSrc : lightSrc;
  }

  function setTheme(next) {
    html.setAttribute('data-theme', next);
    html.classList.remove('theme-dark', 'theme-light');
    html.classList.add(next === 'dark' ? 'theme-dark' : 'theme-light');

    if (btnTheme) {
      btnTheme.setAttribute('aria-pressed', next === 'dark' ? 'true' : 'false');
      btnTheme.innerHTML = next === 'dark'
        ? `<svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><use href="${useHref('moon')}"/></svg>`
        : `<svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><use href="${useHref('sun')}"/></svg>`;
    }

    setLogoForTheme(next);
  }

  const initial = forceLight ? 'light' : (html.getAttribute('data-theme') || 'light');
  setTheme(initial);

  btnTheme?.addEventListener('click', async () => {
    if (forceLight) return;

    const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    setTheme(next);

    if (route) {
      try {
        await fetch(route, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf
          },
          body: JSON.stringify({ theme: next })
        });
      } catch (e) {}
    }
  });

  btnProf?.addEventListener('click', (e) => {
    e.preventDefault();
    const expanded = btnProf.getAttribute('aria-expanded') === 'true';
    btnProf.setAttribute('aria-expanded', expanded ? 'false' : 'true');
    if (ddProf) ddProf.hidden = expanded;
  });

  document.addEventListener('click', (e) => {
    if (!ddProf || !btnProf) return;
    if (ddProf.hidden) return;
    if (ddProf.contains(e.target) || btnProf.contains(e.target)) return;
    ddProf.hidden = true;
    btnProf.setAttribute('aria-expanded', 'false');
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && ddProf && btnProf && !ddProf.hidden) {
      ddProf.hidden = true;
      btnProf.setAttribute('aria-expanded', 'false');
    }
  });
});