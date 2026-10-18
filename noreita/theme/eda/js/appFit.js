// FIT owns page layout; NEO owns zoom, rendering and Float.
class AppFitManager {
  constructor() {
    this.stage = document.getElementById("appstage");
    this.host = document.getElementById("apps");
    this.mode = "normal";
    this.restore = null;
    this.observer = null;
    this.resizeFrame = null;
    this.lastWidth = 0;
    this.lastHeight = 0;

    if (!this.stage || !this.host) return;

    this.syncUI();
    this.stage.addEventListener("click", (event) => {
      if (!(event.target instanceof Element)) return;
      const control = event.target.closest("[data-fit-start], [data-fit-exit]");
      if (!control || !this.stage.contains(control)) return;
      event.preventDefault();
      if (control.hasAttribute("data-fit-start")) {
        this.enter();
      } else {
        this.exit();
      }
    });
    this.stage.addEventListener("keydown", (event) => {
      if (event.key === "Tab" && this.mode === "fit") {
        // Native navigation stays inside the non-inert work area.
        event.stopPropagation();
        return;
      }
      if (event.key !== "Enter" && event.key !== " ") return;
      if (!(event.target instanceof Element)) return;
      const control = event.target.closest("[data-fit-start], [data-fit-exit]");
      if (control && this.stage.contains(control)) {
        // Keep native button activation; NEO's document handler blurs buttons.
        event.stopPropagation();
      }
    });
    document.addEventListener("neo:fullscreenchange", (event) => {
      if ((event.detail && event.detail.fullscreen) || (window.Neo && window.Neo.fullScreen)) {
        // NEO has already moved and resized its container. Do not undo that work.
        this.release();
      }
    });
  }

  readyNeo() {
    const neo = window.Neo;
    const pageView = document.getElementById("neo-pageView");
    if (!this.stage || !this.stage.isConnected || !this.host || !this.host.isConnected ||
        !neo || neo.fullScreen || !neo.painter || !neo.painter.destCanvas ||
        !neo.canvas || !neo.container || !neo.container.isConnected ||
        !pageView || !this.host.contains(pageView) || !pageView.contains(neo.container) ||
        typeof neo.resizeCanvas !== "function") {
      return null;
    }
    return neo;
  }

  syncUI() {
    const fit = this.mode === "fit";
    this.stage.classList.toggle("is-fit", fit);
    this.stage.querySelectorAll("[data-fit-start]").forEach((control) => {
      control.setAttribute("aria-expanded", String(fit));
    });
    this.stage.querySelectorAll(".fit-toolbar").forEach((toolbar) => {
      toolbar.setAttribute("aria-hidden", String(!fit));
    });
  }

  capturePage() {
    const background = [];
    // Only siblings of the stage's ancestry are background, never its ancestors.
    for (let branch = this.stage; branch && branch !== document.body; branch = branch.parentElement) {
      if (!branch.parentElement) break;
      for (const element of branch.parentElement.children) {
        if (element !== branch) {
          background.push({ element, inert: element.getAttribute("inert") });
        }
      }
    }
    const overflow = [document.documentElement, document.body].map((element) => ({
      element,
      properties: ["overflow-x", "overflow-y"].map((name) => ({
        name,
        value: element.style.getPropertyValue(name),
        priority: element.style.getPropertyPriority(name)
      }))
    }));
    return {
      background,
      overflow,
      focus: document.activeElement,
      scrollX: window.scrollX,
      scrollY: window.scrollY
    };
  }

  suppressBackground(saved) {
    saved.background.forEach(({ element }) => element.setAttribute("inert", ""));
    saved.overflow.forEach(({ element, properties }) => {
      properties.forEach(({ name }) => element.style.setProperty(name, "hidden", "important"));
    });
  }

  enter() {
    if (this.mode !== "normal" || this.restore || typeof ResizeObserver !== "function") return;
    const neo = this.readyNeo();
    if (!neo || !this.stage.querySelector("[data-fit-exit]")) return;

    this.lastWidth = this.host.clientWidth;
    this.lastHeight = this.host.clientHeight;
    const saved = this.capturePage();
    this.restore = saved;
    try {
      this.suppressBackground(saved);
      this.stage.classList.add("is-fit");
      // Reading the host flushes the new CSS layout before NEO is notified.
      if (!this.resizeHost(neo)) {
        this.rollback();
        return;
      }
      this.mode = "fit";
      this.syncUI();
      this.observer = new ResizeObserver(() => this.scheduleResize(saved));
      this.observer.observe(this.host);
      this.stage.querySelector("[data-fit-exit]").focus({ preventScroll: true });
    } catch (error) {
      this.rollback();
      throw error;
    }
  }

  resizeHost(neo) {
    const width = this.host.clientWidth;
    const height = this.host.clientHeight;
    if (width <= 0 || height <= 0) return false;
    if (width !== this.lastWidth || height !== this.lastHeight) {
      neo.resizeCanvas();
      this.lastWidth = width;
      this.lastHeight = height;
    }
    return true;
  }

  scheduleResize(saved) {
    if (this.mode !== "fit" || this.restore !== saved || this.resizeFrame !== null) return;
    this.resizeFrame = window.requestAnimationFrame(() => {
      this.resizeFrame = null;
      if (this.mode !== "fit" || this.restore !== saved) return;
      const neo = this.readyNeo();
      if (!neo) {
        this.rollback();
        return;
      }
      try {
        this.resizeHost(neo);
      } catch (error) {
        this.rollback();
        throw error;
      }
    });
  }

  rollback() {
    const saved = this.release();
    if (saved && !(window.Neo && window.Neo.fullScreen)) {
      this.restorePage(saved);
    }
  }

  release() {
    if (this.observer) {
      this.observer.disconnect();
      this.observer = null;
    }
    if (this.resizeFrame !== null) {
      window.cancelAnimationFrame(this.resizeFrame);
      this.resizeFrame = null;
    }
    const saved = this.restore;
    this.restore = null;
    this.mode = "normal";
    if (this.stage) this.syncUI();
    if (!saved) return null;

    saved.background.forEach(({ element, inert }) => {
      if (inert === null) {
        element.removeAttribute("inert");
      } else {
        element.setAttribute("inert", inert);
      }
    });
    saved.overflow.forEach(({ element, properties }) => {
      properties.forEach(({ name, value, priority }) => {
        if (value) {
          element.style.setProperty(name, value, priority);
        } else {
          element.style.removeProperty(name);
        }
      });
    });
    return saved;
  }

  exit() {
    if (this.mode !== "fit" || !this.restore) return;
    // A pending Float transition must never receive a FIT resize or focus change.
    if (window.Neo && window.Neo.fullScreen) {
      this.release();
      return;
    }
    const saved = this.release();
    try {
      const neo = this.readyNeo();
      if (neo) this.resizeHost(neo);
    } finally {
      this.restorePage(saved);
    }
  }

  restorePage(saved) {
    const previous = saved.focus;
    const fallback = this.stage.querySelector("[data-fit-start]");
    const canFocus = (element) => element && element.isConnected &&
      typeof element.focus === "function" && !element.disabled &&
      !element.closest("[inert]") && element.getClientRects().length > 0;
    const previousAvailable = canFocus(previous);
    if (previousAvailable) previous.focus({ preventScroll: true });
    if ((!previousAvailable || document.activeElement !== previous) && canFocus(fallback)) {
      fallback.focus({ preventScroll: true });
    }
    const root = document.scrollingElement || document.documentElement;
    const x = Math.max(0, Math.min(saved.scrollX, root.scrollWidth - root.clientWidth));
    const y = Math.max(0, Math.min(saved.scrollY, root.scrollHeight - root.clientHeight));
    window.scrollTo({ left: x, top: y, behavior: "instant" });
  }
}

const initializeAppFit = () => {
  window.appFitManager = new AppFitManager();
};
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", initializeAppFit, { once: true });
} else {
  initializeAppFit();
}
