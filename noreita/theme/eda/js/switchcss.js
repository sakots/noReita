// eda theme's preset stylesheet switcher.
class EdaCssSwitcher {
  constructor() {
    this.cookieName = '_eda_colorIdx';
    this.init();
  }

  init() {
    const colorIndex = this.getCookie(this.cookieName);
    if (colorIndex !== '') this.enableCss(Number(colorIndex));
  }

  enableCss(index) {
    const stylesheet = document.getElementById(`css${index}`);
    if (stylesheet) stylesheet.removeAttribute('disabled');
  }

  setCss(selectElement) {
    this.setCookie(this.cookieName, selectElement.selectedIndex);
    window.location.reload();
  }

  getCookie(name) {
    const parts = (`; ${document.cookie}`).split(`; ${name}=`);
    return parts.length === 2 ? decodeURIComponent(parts.pop().split(';').shift()) : '';
  }

  setCookie(name, value) {
    document.cookie = `${name}=${encodeURIComponent(value)};max-age=${365 * 24 * 60 * 60};path=/`;
  }
}

const edaCssSwitcher = new EdaCssSwitcher();

// Existing theme markup calls these functions directly.
function SetCss(obj) {
  edaCssSwitcher.setCss(obj);
}

function GetCookie(key) {
  return edaCssSwitcher.getCookie(key);
}

function SetCookie(key, value) {
  edaCssSwitcher.setCookie(key, value);
}
