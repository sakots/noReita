/* eda theme color manager: server-stored color overrides for noReita. */
(function () {
  'use strict';

  const styleId = 'eda-theme-color-overrides';
  const colors = {
    pageBackground: { variable: '--eda-page-background', default: '#cccccc' },
    pageBackgroundEnd: { variable: '--eda-page-background-end', default: '#cccccc' },
    text: { variable: '--eda-text', default: '#000000' },
    link: { variable: '--eda-link', default: '#003366' },
    linkVisited: { variable: '--eda-link-visited', default: '#666666' },
    linkAction: { variable: '--eda-link-action', default: '#ff0000' },
    surface: { variable: '--eda-surface', default: '#eeeeee' },
    border: { variable: '--eda-border', default: '#003366' },
    button: { variable: '--eda-button', default: '#336699' },
    buttonText: { variable: '--eda-button-text', default: '#ffffff' },
    inputBackground: { variable: '--eda-input-background', default: '#ffffff' },
    inputText: { variable: '--eda-input-text', default: '#000000' },
    threadBackground: { variable: '--eda-thread-background', default: '#99ccff' },
    threadText: { variable: '--eda-thread-text', default: '#000066' },
    noticeBackground: { variable: '--eda-notice-background', default: '#ffcccc' },
    replyText: { variable: '--eda-reply-text', default: '#114411' }
  };

  const overrideCss = `
body { background: linear-gradient(var(--eda-page-background, #ccc), var(--eda-page-background-end, #ccc)) fixed !important; color: var(--eda-text, #000) !important; }
body svg { fill: var(--eda-text, #000) !important; }
a, a:link { color: var(--eda-link, #036) !important; }
a:visited, h1 a:visited { color: var(--eda-link-visited, #666) !important; }
a:active, a:hover, h1 a:hover { color: var(--eda-link-action, #f00) !important; }
hr, header, .thread, footer, .res, .res section { border-color: var(--eda-border, #036) !important; }
header, .thread, footer, figure, .thread img.image, footer img.image { background-color: var(--eda-surface, #eee) !important; }
input, textarea, select, #appstage .palette .palette_set { color: var(--eda-input-text, #000) !important; background-color: var(--eda-input-background, #fff) !important; }
input, textarea, select { border-color: var(--eda-page-background, #ccc) var(--eda-border, #036) var(--eda-border, #036) var(--eda-page-background, #ccc) !important; }
input[type=submit], input[name=upfile], button, .button { color: var(--eda-button-text, #fff) !important; background-color: var(--eda-button, #369) !important; border-color: var(--eda-border, #036) var(--eda-page-background, #ccc) var(--eda-page-background, #ccc) var(--eda-border, #036) !important; }
.button a, .button a svg { color: var(--eda-button-text, #fff) !important; fill: var(--eda-button-text, #fff) !important; }
.oyaname, .resname { border-color: var(--eda-button, #369) !important; }
.oyaname:hover, .resname:hover, .resma { background-color: var(--eda-page-background, #ccc) !important; }
.resma { color: var(--eda-reply-text, #141) !important; }
.limit { background-color: var(--eda-notice-background, #fcc) !important; }
.oyat { background: var(--eda-thread-background, #9cf) !important; color: var(--eda-thread-text, #006) !important; }
.delfo th, .delfo td { border-color: var(--eda-page-background, #ccc) !important; }
.delfo tr:nth-of-type(2n) td { background-color: var(--eda-thread-background, #9cf) !important; }
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

  function presets() {
    const raw = window.EDA_THEME_COLOR_PRESETS;
    if (!raw || typeof raw !== 'object' || Array.isArray(raw)) return { mono: defaults() };
    return Object.fromEntries(Object.entries(raw).flatMap(([name, values]) => {
      if (typeof name !== 'string' || !values || typeof values !== 'object' || Array.isArray(values)) return [];
      const sanitized = Object.fromEntries(Object.entries(values).filter(([key, value]) => colors[key] && normalize(value)));
      return Object.keys(sanitized).length === Object.keys(colors).length ? [[name, sanitized]] : [];
    }));
  }

  const saved = serverColors();
  apply(saved);
  window.EdaThemeColors = { defaults, presets, get: () => ({ ...defaults(), ...saved }), preview: apply };

  document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('eda-theme-color-form');
    if (!form) return;
    const fields = Array.from(form.querySelectorAll('input[data-eda-theme-color]'));
    const presetSelect = document.getElementById('eda-theme-color-preset');
    const presetLoad = document.getElementById('eda-theme-color-preset-load');
    const current = window.EdaThemeColors.get();
    fields.forEach((field) => {
      field.value = current[field.dataset.edaThemeColor] || field.value;
      field.addEventListener('input', () => window.EdaThemeColors.preview(readFields()));
    });

    function readFields() {
      return Object.fromEntries(fields.map((field) => [field.dataset.edaThemeColor, field.value]));
    }

    presetLoad?.addEventListener('click', () => {
      const selected = presetSelect?.value;
      const values = selected ? window.EdaThemeColors.presets()[selected] : null;
      if (!values) return;
      fields.forEach((field) => {
        field.value = values[field.dataset.edaThemeColor] || field.value;
      });
      window.EdaThemeColors.preview(readFields());
    });

  });
})();
