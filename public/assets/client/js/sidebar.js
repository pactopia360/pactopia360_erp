/* public/assets/client/js/sidebar.js (P360 Sidebar – v1.4 FINAL: desktop collapse + mobile offcanvas + restore + sync) */
(function () {
  "use strict";

  function qs(sel, root) { return (root || document).querySelector(sel); }
  function qsa(sel, root) { return Array.from((root || document).querySelectorAll(sel)); }

  function ensureBackdrop() {
    let bd = document.getElementById("p360-sb-backdrop");
    if (!bd) {
      bd = document.createElement("div");
      bd.id = "p360-sb-backdrop";
      bd.setAttribute("aria-hidden", "true");
      bd.style.cssText = [
        "position:fixed",
        "inset:0",
        "background:rgba(2,6,23,.45)",
        "backdrop-filter:saturate(1.2) blur(2px)",
        "opacity:0",
        "pointer-events:none",
        "transition:opacity .18s ease",
        "z-index:59" // sidebar mobile z-index = 60 (layout/css)
      ].join(";");
      document.body.appendChild(bd);
    }
    return bd;
  }

  function initSidebar(sb) {
    if (!sb || sb.dataset.p360Ready === "1") return;
    sb.dataset.p360Ready = "1";

    const id = sb.id || "sidebar";
    const KEY = `p360.client.sidebar.state.${id}`;
    const mql = window.matchMedia("(max-width: 1099.98px)");

    const btn = qs('[data-sb-toggle="1"]', sb);
    const backdrop = ensureBackdrop();

    // ===== helpers =====
    function emitSync(reason) {
      try {
        window.dispatchEvent(
          new CustomEvent("p360:sidebar:sync", {
            detail: {
              id,
              state: sb.getAttribute("data-state") || null,
              reason: reason || "unknown",
              isMobile: !!mql.matches
            }
          })
        );
      } catch (e) {}
    }

    function setAria(state) {
      if (btn) btn.setAttribute("aria-expanded", state === "expanded" ? "true" : "false");
    }

    function setState(state, persist, reason) {
      const next = (state === "collapsed") ? "collapsed" : "expanded";
      sb.setAttribute("data-state", next);
      setAria(next);

      // Persistencia SOLO en desktop
      if (persist && !mql.matches) {
        try { localStorage.setItem(KEY, next); } catch (e) {}
      }

      emitSync(reason || "setState");
    }

    function openMobile(reason) {
      sb.classList.add("open");
      backdrop.style.opacity = "1";
      backdrop.style.pointerEvents = "auto";
      emitSync(reason || "mobile:open");
    }

    function closeMobile(reason) {
      sb.classList.remove("open");
      backdrop.style.opacity = "0";
      backdrop.style.pointerEvents = "none";
      emitSync(reason || "mobile:close");
    }

    function applyViewport(reason) {
      // Importante:
      // - Mobile: data-state SIEMPRE "expanded" para que CSS muestre texto/labels
      // - Desktop: usar localStorage si existe; si no, usar el atributo HTML actual
      const currentAttr = sb.getAttribute("data-state") || "expanded";

      if (mql.matches) {
        sb.classList.add("is-mobile");

        // En móvil siempre expanded (no persistir)
        setState("expanded", false, (reason || "apply") + ":mobile");

        // No abrir automático (offcanvas); se abre con click / edge swipe / hotkey
        closeMobile((reason || "apply") + ":mobile");
      } else {
        sb.classList.remove("is-mobile");

        let saved = null;
        try { saved = localStorage.getItem(KEY); } catch (e) {}

        if (saved === "collapsed" || saved === "expanded") {
          setState(saved, false, (reason || "apply") + ":desktop:saved");
        } else {
          setState(currentAttr === "collapsed" ? "collapsed" : "expanded", false, (reason || "apply") + ":desktop:attr");
        }

        // En desktop nunca debe quedar "open"
        closeMobile((reason || "apply") + ":desktop");
      }
    }

    // ===== BOOT =====
    applyViewport("boot");

    // ===== CLICK toggle =====
    if (btn) {
      btn.addEventListener("click", function (e) {
        e.preventDefault();

        if (mql.matches) {
          // Mobile: offcanvas open/close
          if (sb.classList.contains("open")) closeMobile("toggle:click");
          else openMobile("toggle:click");
          return;
        }

        // Desktop: collapse/expand
        const cur = sb.getAttribute("data-state") || "expanded";
        const next = (cur === "collapsed") ? "expanded" : "collapsed";
        setState(next, true, "toggle:click");
      });
    }

    // ===== BACKDROP click closes mobile =====
    backdrop.addEventListener("click", function () {
      if (mql.matches) closeMobile("backdrop:click");
    });

    // ===== ESC closes mobile =====
    window.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && mql.matches && sb.classList.contains("open")) {
        closeMobile("kbd:esc");
      }
    }, { passive: true });

    // ===== Ctrl+B / Cmd+B =====
    window.addEventListener("keydown", function (e) {
      const key = (e.key || "").toLowerCase();
      if (!((e.ctrlKey || e.metaKey) && key === "b")) return;

      e.preventDefault();

      const first = qs('.sidebar[data-component="p360-sidebar"]') || sb;
      if (!first) return;

      if (mql.matches) {
        // Mobile: open/close panel
        if (first.classList.contains("open")) closeMobile("toggle:hotkey");
        else openMobile("toggle:hotkey");
        return;
      }

      // Desktop: collapse/expand + persist
      const cur = first.getAttribute("data-state") || "expanded";
      const next = (cur === "collapsed") ? "expanded" : "collapsed";

      first.setAttribute("data-state", next);
      const firstBtn = qs('[data-sb-toggle="1"]', first);
      if (firstBtn) firstBtn.setAttribute("aria-expanded", next === "expanded" ? "true" : "false");

      const firstId = first.id || "sidebar";
      const firstKey = `p360.client.sidebar.state.${firstId}`;
      try { localStorage.setItem(firstKey, next); } catch (err) {}

      try {
        window.dispatchEvent(
          new CustomEvent("p360:sidebar:sync", {
            detail: { id: firstId, state: next, reason: "toggle:hotkey", isMobile: false }
          })
        );
      } catch (e2) {}
    }, { passive: false });

    // ===== Breakpoint change =====
    if (mql.addEventListener) mql.addEventListener("change", function () { applyViewport("mql:change"); });
    else if (mql.addListener) mql.addListener(function () { applyViewport("mql:change"); });

    // ===== Edge swipe open (mobile) =====
    let touchX = null;

    window.addEventListener("touchstart", function (e) {
      touchX = (e.touches && e.touches[0] && typeof e.touches[0].clientX === "number")
        ? e.touches[0].clientX
        : null;
    }, { passive: true });

    window.addEventListener("touchend", function () {
      if (mql.matches && touchX !== null && touchX < 20) openMobile("mobile:edge-open");
      touchX = null;
    }, { passive: true });
  }

  function boot() {
    qsa('.sidebar[data-component="p360-sidebar"]').forEach(initSidebar);
    try { window.dispatchEvent(new CustomEvent("p360:sidebar:sync", { detail: { reason: "boot:global" } })); } catch (e) {}
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", boot, { once: true });
  else boot();
})();
