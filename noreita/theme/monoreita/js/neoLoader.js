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

  function loadFallback(baseUrl) {
    document.write('<link rel="stylesheet" href="' + baseUrl + 'neo.css" type="text/css">');
    document.write('<script src="' + baseUrl + 'neo.js" charset="utf-8"></script>');
  }

  window.loadPaintBbsNeo = function (fallbackBaseUrl) {
    try {
      const css = fetchSource('neo.css');
      const javascript = fetchSource('neo.js');
      document.write('<style>' + css + '</style>');
      document.write('<script>' + javascript.replace(/<\/script/gi, '<\\/script') + '</script>');
    } catch (error) {
      loadFallback(fallbackBaseUrl);
    }
  };
}());
