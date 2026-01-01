/* public/assets/client/js/sidebar.js (P360 Sidebar – v1.2 FIX: breakpoint 1100px + sync + edge-open safe) */
(function () {
  function initSidebar(sb) {
    if (!sb || sb.dataset.p360Ready === "1") return;
    sb.dataset.p360Ready = "1";

    const KEY = `p360.client.sidebar.state.${sb.id || "sidebar"}`;

    /**
     * IMPORTANT:
     * - Desktop >= 1100px
     * - Mobile  < 1100px
     */
    const mql = window.matchMedia("(max-width: 1099.98px)");

    function emitSync(reason) {
      try {
        window.dispatchEvent(
          new CustomEvent("p360:sidebar:sync", {
            detail: {
              id: sb.id || "sidebar",
              state: sb.getAttribute("data-state") || null,
              reason: reason || "unknown",
              isMobile: !!mql.matches
            }
          })
        );
      } catch (e) {}
    }

    function setState(state, persist = true, reason = "setState") {
      sb.setAttribute("data-state", state);

      const btn = sb.querySelector('[data-sb-toggle="1"]');
      if (btn) btn.setAttribute("aria-expanded", state === "expanded" ? "true" : "false");

      // Persistencia SOLO en desktop
      if (persist && !mql.matches) {
        try { localStorage.setItem(KEY, state); } catch (e) {}
      }

      emitSync(reason);
    }

    function applyMobileClass(reason = "applyMobileClass") {
      if (mql.matches) {
        sb.classList.add("is-mobile");

        // En móvil forzamos expandido (pero no persistimos)
        setState("expanded", false, reason + ":mobile");
      } else {
        sb.classList.remove("is-mobile");

        // En desktop aplica estado guardado (si existe), si no deja el actual
        try {
          const saved = localStorage.getItem(KEY);
          if (saved === "collapsed" || saved === "expanded") {
            sb.setAttribute("data-state", saved);
            const btn = sb.querySelector('[data-sb-toggle="1"]');
            if (btn) btn.setAttribute("aria-expanded", saved === "expanded" ? "true" : "false");
          }
        } catch (e) {}

        emitSync(reason + ":desktop");
      }
    }

    // Inicializa estado según viewport
    applyMobileClass("boot");

    // Click toggle
    const btn = sb.querySelector('[data-sb-toggle="1"]');
    if (btn) {
      btn.addEventListener("click", function () {
        const cur = sb.getAttribute("data-state") || "expanded";
        setState(cur === "collapsed" ? "expanded" : "collapsed", true, "toggle:click");
      });
    }

    // Ctrl+B / Cmd+B
    window.addEventListener(
      "keydown",
      function (e) {
        const key = (e.key || "").toLowerCase();
        if ((e.ctrlKey || e.metaKey) && key === "b") {
          e.preventDefault();

          const first = document.querySelector('.sidebar[data-component="p360-sidebar"]') || sb;
          if (!first) return;

          const cur = first.getAttribute("data-state") || "expanded";
          const next = cur === "collapsed" ? "expanded" : "collapsed";

          const firstId = first.id || "sidebar";
          const firstKey = `p360.client.sidebar.state.${firstId}`;

          first.setAttribute("data-state", next);

          const firstBtn = first.querySelector('[data-sb-toggle="1"]');
          if (firstBtn) firstBtn.setAttribute("aria-expanded", next === "expanded" ? "true" : "false");

          if (!mql.matches) {
            try { localStorage.setItem(firstKey, next); } catch (err) {}
          }

          try {
            window.dispatchEvent(
              new CustomEvent("p360:sidebar:sync", {
                detail: { id: firstId, state: next, reason: "toggle:hotkey", isMobile: !!mql.matches }
              })
            );
          } catch (e2) {}
        }
      },
      { passive: false }
    );

    // Cambio de breakpoint
    if (mql.addEventListener) mql.addEventListener("change", function () { applyMobileClass("mql:change"); });
    else if (mql.addListener) mql.addListener(function () { applyMobileClass("mql:change"); });

    // Swipe “edge open” en móvil (solo efecto visual)
    let touchX = null;
    window.addEventListener(
      "touchstart",
      function (e) {
        touchX = (e.touches && e.touches[0] && e.touches[0].clientX) ? e.touches[0].clientX : null;
      },
      { passive: true }
    );

    window.addEventListener(
      "touchend",
      function () {
        if (touchX !== null && touchX < 20) {
          sb.classList.add("open");

          // No cambiamos data-state; solo animación
          setTimeout(function () {
            sb.classList.remove("open");
            emitSync("mobile:edge-open");
          }, 320);
        }
        touchX = null;
      },
      { passive: true }
    );
  }

  function boot() {
    document.querySelectorAll('.sidebar[data-component="p360-sidebar"]').forEach(initSidebar);

    try {
      window.dispatchEvent(new CustomEvent("p360:sidebar:sync", { detail: { reason: "boot:global" } }));
    } catch (e) {}
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot, { once: true });
  } else {
    boot();
  }
})();
