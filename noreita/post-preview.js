// Client-side post preview for noReita. External pages are never fetched here.
(() => {
  'use strict';

  const urlPattern = /https?:\/\/[^\s<>"']+/gi;

  function externalLinks(text) {
    const links = [];
    const seen = new Set();
    for (const candidate of text.match(urlPattern) || []) {
      const value = candidate.replace(/[.,!?;:]+$/, '');
      try {
        const url = new URL(value);
        if (!['http:', 'https:'].includes(url.protocol) || seen.has(url.href)) continue;
        seen.add(url.href);
        links.push(url.href);
      } catch (_) {
        // The server validates links again when the post is submitted.
      }
    }
    return links;
  }

  function hasSelectedImage(form) {
    return Array.from(form.querySelectorAll('[name="image_upload"], [data-animation-upload-file]')).some((input) =>
      !input.disabled && input.files && input.files.length > 0
    ) || Array.from(form.querySelectorAll('[name="picfile"]')).some((input) => !input.disabled && input.value !== '');
  }

  function render(form) {
    const row = form.querySelector('[data-post-preview-row]');
    const output = form.querySelector('[data-post-preview-content]');
    const comment = form.querySelector('textarea[name="com"]');
    if (!row || !output || !comment) return;

    const text = comment.value;
    const links = externalLinks(text);
    const imageSelected = hasSelectedImage(form);
    row.hidden = text.trim() === '' && links.length === 0 && !imageSelected;
    output.replaceChildren();
    if (text.trim() !== '') {
      const heading = document.createElement('strong');
      heading.textContent = '本文';
      const body = document.createElement('p');
      body.style.whiteSpace = 'pre-wrap';
      body.textContent = text;
      output.append(heading, body);
    }
    if (links.length > 0) {
      const heading = document.createElement('strong');
      heading.textContent = '外部リンク（投稿後にリンクカードを生成できる場合があります）';
      const list = document.createElement('ul');
      links.forEach((href) => {
        const item = document.createElement('li');
        const link = document.createElement('a');
        link.href = href;
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        link.textContent = href;
        item.appendChild(link);
        list.appendChild(item);
      });
      output.append(heading, list);
    }
  }

  document.querySelectorAll('form').forEach((form) => {
    if (!form.querySelector('[data-post-preview-row]')) return;
    form.addEventListener('input', () => render(form));
    form.addEventListener('change', () => render(form));
    render(form);
  });
})();
