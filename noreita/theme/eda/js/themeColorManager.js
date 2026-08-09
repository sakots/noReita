/* eda theme color manager: server-stored color overrides for noReita. */
(function () {
  'use strict';

  const styleId = 'eda-theme-color-overrides';
  const colors = {
    pageBackground: { variable: '--eda-page-background', default: '#222244' },
    pageBackgroundEnd: { variable: '--eda-page-background-end', default: '#000000' },
    text: { variable: '--eda-text', default: '#eeeeee' },
    link: { variable: '--eda-link', default: '#eeeeee' },
    linkVisited: { variable: '--eda-link-visited', default: '#999999' },
    linkAction: { variable: '--eda-link-action', default: '#cc0000' },
    surface: { variable: '--eda-surface', default: '#112222' },
    border: { variable: '--eda-border', default: '#992222' },
    button: { variable: '--eda-button', default: '#333355' },
    buttonText: { variable: '--eda-button-text', default: '#ffffff' },
    inputBackground: { variable: '--eda-input-background', default: '#eeeeee' },
    inputText: { variable: '--eda-input-text', default: '#000000' },
    threadBackground: { variable: '--eda-thread-background', default: '#001122' },
    threadText: { variable: '--eda-thread-text', default: '#ddffee' },
    noticeBackground: { variable: '--eda-notice-background', default: '#554433' },
    replyText: { variable: '--eda-reply-text', default: '#cc88cc' }
  };

  const overrideCss = `
body { background: linear-gradient(var(--eda-page-background, #224), var(--eda-page-background-end, #000)) fixed !important; color: var(--eda-text, #eee) !important; }
body svg { fill: var(--eda-text, #eee) !important; }
a, a:link { color: var(--eda-link, #eee) !important; }
a:visited, h1 a:visited { color: var(--eda-link-visited, #999) !important; }
a:active, a:hover, h1 a:hover { color: var(--eda-link-action, #c00) !important; }
hr, header, .thread, footer, .res, .res section { border-color: var(--eda-border, #922) !important; }
header, .thread, footer, figure, .thread img.image, footer img.image { background-color: var(--eda-surface, #122) !important; }
input, textarea, select, #appstage .palette .palette_set { color: var(--eda-input-text, #000) !important; background-color: var(--eda-input-background, #eee) !important; }
input, textarea, select { border-color: var(--eda-page-background, #224) var(--eda-border, #446) var(--eda-border, #446) var(--eda-page-background, #224) !important; }
input[type=submit], input[name=upfile], button, .button { color: var(--eda-button-text, #fff) !important; background-color: var(--eda-button, #335) !important; border-color: var(--eda-border, #446) var(--eda-page-background, #224) var(--eda-page-background, #224) var(--eda-border, #446) !important; }
.button a, .button a svg { color: var(--eda-button-text, #fff) !important; fill: var(--eda-button-text, #fff) !important; }
.oyaname, .resname { border-color: var(--eda-button, #335) !important; }
.oyaname:hover, .resname:hover, .resma { background-color: var(--eda-page-background, #224) !important; }
.resma { color: var(--eda-reply-text, #c8c) !important; }
.limit { background-color: var(--eda-notice-background, #543) !important; }
.oyat { background: var(--eda-thread-background, #012) !important; color: var(--eda-thread-text, #dfe) !important; }
.delfo th, .delfo td { border-color: var(--eda-page-background, #224) !important; }
.delfo tr:nth-of-type(2n) td { background-color: var(--eda-thread-background, #012) !important; }
`;

  function normalize(value) {
    if (typeof value !== 'string') return null;
    const match = value.trim().match(/^#([0-9a-f]{3}|[0-9a-f]{6})$/i);
    if (!match) return null;
    const hex = match[1];
    return '#' + (hex.length === 3 ? hex.split('').map((part) => part + part).join('') : hex).toLowerCase();
  }

  function defaults() {
    return Object.fromEntries(Object.entries(colors).map(([key, definition]) => [key, definition.default]));
  }

  function apply(values) {
    let style = document.getElementById(styleId);
    const root = document.documentElement;
    Object.values(colors).forEach((definition) => root.style.removeProperty(definition.variable));
    const sanitized = {};
    Object.entries(values || {}).forEach(([key, value]) => {
      const color = colors[key] && normalize(value);
      if (!color) return;
      sanitized[key] = color;
      root.style.setProperty(colors[key].variable, color);
    });
    if (Object.keys(sanitized).length === 0) {
      style?.remove();
      return {};
    }
    if (!style) {
      style = document.createElement('style');
      style.id = styleId;
      document.head.appendChild(style);
    }
    style.textContent = overrideCss;
    return sanitized;
  }

  function serverColors() {
    const raw = window.EDA_THEME_COLORS;
    if (!raw || typeof raw !== 'object' || Array.isArray(raw)) return {};
    return Object.fromEntries(Object.entries(raw).filter(([key, value]) => colors[key] && normalize(value)));
  }

  const saved = serverColors();
  apply(saved);
  window.EdaThemeColors = { defaults, get: () => ({ ...defaults(), ...saved }), preview: apply };

  document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('eda-theme-color-form');
    if (!form) return;
    const fields = Array.from(form.querySelectorAll('input[data-eda-theme-color]'));
    const current = window.EdaThemeColors.get();
    fields.forEach((field) => {
      field.value = current[field.dataset.edaThemeColor] || field.value;
      field.addEventListener('input', () => window.EdaThemeColors.preview(readFields()));
    });

    function readFields() {
      return Object.fromEntries(fields.map((field) => [field.dataset.edaThemeColor, field.value]));
    }

  });
})();
