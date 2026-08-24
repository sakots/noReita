/* Load PaintBBS NEO from GitHub's Contents API, with the configured mirror as fallback. */
(function () {
  'use strict';

  const apiBase = 'https://api.github.com/repos/funige/neo/contents/dist/';

  function fetchSource(filename) {
    const request = new XMLHttpRequest();
    request.open('GET', apiBase + filename + '?ref=master', false);
    request.setRequestHeader('Accept', 'application/vnd.github+json');
    request.send();
    if (request.status !== 200) throw new Error('GitHub API request failed');

    const response = JSON.parse(request.responseText);
    if (response.encoding !== 'base64' || typeof response.content !== 'string') {
      throw new Error('GitHub API response is not base64 content');
    }
    const bytes = Uint8Array.from(atob(response.content.replace(/\s/g, '')), function (character) {
      return character.charCodeAt(0);
    });
    return new TextDecoder('utf-8').decode(bytes);
  }

  function appendStyle(css) {
    const style = document.createElement('style');
    style.textContent = css;
    document.head.appendChild(style);
  }

  function appendStylesheet(url) {
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = url;
    document.head.appendChild(link);
  }

  function startNeoIfDocumentIsReady() {
    if (document.readyState === 'loading' || !window.Neo || typeof window.Neo.init !== 'function') return;
    if (window.Neo.init() && typeof window.Neo.start === 'function') window.Neo.start();
  }

  function appendScript(source, onload) {
    const script = document.createElement('script');
    script.charset = 'utf-8';
    if (onload) script.addEventListener('load', onload, { once: true });
    if (source.startsWith('http://') || source.startsWith('https://')) {
      script.src = source;
      script.async = false;
    } else {
      script.text = source;
    }
    document.head.appendChild(script);
  }

  function loadFallback(baseUrl) {
    appendStylesheet(baseUrl + 'neo.css');
    appendScript(baseUrl + 'neo.js', startNeoIfDocumentIsReady);
  }

  window.loadPaintBbsNeo = function (fallbackBaseUrl) {
    try {
      const css = fetchSource('neo.css');
      const javascript = fetchSource('neo.js');
      appendStyle(css);
      appendScript(javascript);
    } catch (error) {
      loadFallback(fallbackBaseUrl);
    }
  };
}());
