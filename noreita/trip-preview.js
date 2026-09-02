// トリップの計算規則は PHP の generate_trip() に集約している。
// JavaScript で crypt() / Shift_JIS 変換を再実装すると投稿時の結果とずれるため、
// このファイルは同一オリジンの preview API を呼び出して結果を表示するだけにする。
(() => {
  'use strict';

  const loader = document.currentScript
    || Array.from(document.scripts).find((script) => script.src.includes('trip-preview.js'));
  if (!loader || !loader.dataset.endpoint) return;

  // テンプレートが data-endpoint に "index.php?mode=trip_preview" を設定する。
  // POST を使うので、トリップのキーは URL、履歴、Referer に含まれない。
  const endpoint = loader.dataset.endpoint;
  document.querySelectorAll('[data-trip-preview-input]').forEach((input) => {
    const preview = input.parentElement && input.parentElement.querySelector('[data-trip-preview]');
    if (!preview) return;

    // 入力ごとに通信するとリクエストが増えすぎるため、200ms 入力が止まってから送信する。
    let timer = 0;
    // 新しい入力時には古い通信を中断し、古い結果が後から表示されないようにする。
    let request = null;
    const clear = () => {
      if (request) request.abort();
      request = null;
      window.clearTimeout(timer);
      preview.textContent = '';
      preview.hidden = true;
    };
    const update = async () => {
      const name = input.value;
      if (!name.includes('#')) {
        clear();
        return;
      }
      if (request) request.abort();
      request = new AbortController();
      const activeRequest = request;
      try {
        // `name` は application/x-www-form-urlencoded の POST 本文にのみ入る。
        const body = new URLSearchParams({ name });
        const response = await fetch(endpoint, {
          method: 'POST', credentials: 'same-origin',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
          body, signal: activeRequest.signal,
        });
        // サーバー側は generate_trip(name) を { preview: "..." } として返す。
        const payload = await response.json();
        if (!response.ok || typeof payload.preview !== 'string' || request !== activeRequest) return;
        preview.textContent = `トリップ: ${payload.preview}`;
        preview.hidden = false;
      } catch (error) {
        if (error.name !== 'AbortError' && request === activeRequest) clear();
      }
    };

    input.addEventListener('input', () => {
      window.clearTimeout(timer);
      timer = window.setTimeout(update, 200);
    });
    if (input.value.includes('#')) update();
  });
})();
