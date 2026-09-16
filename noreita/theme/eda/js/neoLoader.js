/* Load PaintBBS NEO from GitHub's Contents API, with the configured mirror as fallback. */
(function () {
  'use strict';

  const apiBase = 'https://api.github.com/repos/funige/neo/contents/dist/';
  const requestTimeoutMs = 5000;

  function fetchWithTimeout(url) {
    const controller = window.AbortController ? new AbortController() : null;
    const options = { headers: { Accept: 'application/vnd.github+json' } };
    if (controller) options.signal = controller.signal;
    let timeoutId;
    const request = new Promise(function (resolve, reject) {
      timeoutId = window.setTimeout(function () {
        if (controller) controller.abort();
        reject(new Error('GitHub API request timed out'));
      }, requestTimeoutMs);
      fetch(url, options).then(resolve, reject);
    });
    return request.then(function (response) {
      window.clearTimeout(timeoutId);
      return response;
    }, function (error) {
      window.clearTimeout(timeoutId);
      throw error;
    });
  }

  function fetchSource(filename) {
    return fetchWithTimeout(apiBase + filename + '?ref=master').then(function (response) {
      if (!response.ok) throw new Error('GitHub API request failed');
      return response.json();
    }).then(function (response) {
      if (response.encoding !== 'base64' || typeof response.content !== 'string') {
        throw new Error('GitHub API response is not base64 content');
      }
      const bytes = Uint8Array.from(atob(response.content.replace(/\s/g, '')), function (character) {
        return character.charCodeAt(0);
      });
      return new TextDecoder('utf-8').decode(bytes);
    });
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

  function finishLoad(onReady) {
    if (typeof onReady === 'function') onReady();
    startNeoIfDocumentIsReady();
  }

  function loadFallback(baseUrl, onReady) {
    appendStylesheet(baseUrl + 'neo.css');
    appendScript(baseUrl + 'neo.js', function () { finishLoad(onReady); });
  }

  window.loadPaintBbsNeo = function (fallbackBaseUrl, useGithubApi, onReady) {
    if (useGithubApi !== false && window.fetch && window.Promise && window.TextDecoder) {
      return Promise.all([fetchSource('neo.css'), fetchSource('neo.js')]).then(function (sources) {
        const css = sources[0];
        const javascript = sources[1];
        appendStyle(css);
        appendScript(javascript);
        finishLoad(onReady);
      }).catch(function () {
        // APIの障害、CORS制限、レート制限時は従来の読み込み先へ戻す。
        loadFallback(fallbackBaseUrl, onReady);
      });
    }
    loadFallback(fallbackBaseUrl, onReady);
    return null;
  };
}());
