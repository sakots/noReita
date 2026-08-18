// Browser-side replay conversion for noReita.
(() => {
  'use strict';

  const loader = document.currentScript
    || Array.from(document.scripts).find((script) => script.src.includes('animation-upload.js'));
  if (!loader) return;
  const settings = {
    endpoint: loader.dataset.endpoint || '',
    neoDir: loader.dataset.neoDir || '',
    tegakiDir: loader.dataset.tegakiDir || '',
    tegakiEnabled: loader.dataset.tegakiEnabled === '1',
    maxWorkBytes: Number(loader.dataset.maxWorkBytes || 0),
    maxWidth: Number(loader.dataset.maxWidth || 0),
    maxHeight: Number(loader.dataset.maxHeight || 0),
  };
  let engineUsed = false;

  const assetUrl = (directory, filename) => `${directory.replace(/\/?$/, '/')}${filename}`;
  const wait = (milliseconds) => new Promise((resolve) => window.setTimeout(resolve, milliseconds));

  function loadScript(url, id) {
    const existing = document.getElementById(id);
    if (existing) {
      return existing.dataset.loaded === '1'
        ? Promise.resolve()
        : new Promise((resolve, reject) => {
            existing.addEventListener('load', resolve, { once: true });
            existing.addEventListener('error', reject, { once: true });
          });
    }
    return new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.id = id;
      script.src = url;
      script.addEventListener('load', () => {
        script.dataset.loaded = '1';
        resolve();
      }, { once: true });
      script.addEventListener('error', () => reject(new Error('変換プログラムを読み込めませんでした。')), { once: true });
      document.head.appendChild(script);
    });
  }

  function loadStylesheet(url, id) {
    if (document.getElementById(id)) return;
    const link = document.createElement('link');
    link.id = id;
    link.rel = 'stylesheet';
    link.href = url;
    document.head.appendChild(link);
  }

  function makeNeoStage(status) {
    const overlay = document.createElement('div');
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-label', 'PCH変換');
    Object.assign(overlay.style, {
      position: 'fixed', inset: '0', zIndex: '2147483000', overflow: 'auto',
      padding: '1rem', background: 'rgba(0, 0, 0, .75)'
    });
    const panel = document.createElement('div');
    Object.assign(panel.style, {
      width: 'fit-content', maxWidth: '100%', margin: '0 auto', padding: '1rem',
      background: '#fff', color: '#111'
    });
    const message = document.createElement('p');
    message.textContent = status;
    const stage = document.createElement('div');
    panel.append(message, stage);
    overlay.appendChild(panel);
    document.body.appendChild(overlay);
    return { overlay, stage, message };
  }

  function canvasBlob(canvas) {
    return new Promise((resolve, reject) => {
      canvas.toBlob((blob) => {
        if (blob && blob.type === 'image/png') resolve(blob);
        else reject(new Error('最終画像をPNGに変換できませんでした。'));
      }, 'image/png');
    });
  }

  async function convertPch(buffer) {
    const bytes = new Uint8Array(buffer);
    if (bytes.length < 13 || bytes[0] !== 0x4e || bytes[1] !== 0x45 || bytes[2] !== 0x4f) {
      throw new Error('PaintBBS NEO形式のPCHファイルではありません。');
    }
    const headerWidth = bytes[4] + bytes[5] * 256;
    const headerHeight = bytes[6] + bytes[7] * 256;
    if (headerWidth < 1 || headerHeight < 1
      || headerWidth > settings.maxWidth || headerHeight > settings.maxHeight) {
      throw new Error('PCHのキャンバスサイズが上限を超えています。');
    }

    loadStylesheet(assetUrl(settings.neoDir, 'neo.css'), 'noreita-animation-neo-css');
    await loadScript(assetUrl(settings.neoDir, 'neo.js'), 'noreita-animation-neo-js');
    if (!window.Neo || typeof window.Neo.decodePCH !== 'function') {
      throw new Error('PaintBBS NEOを利用できません。');
    }
    const pch = window.Neo.decodePCH(buffer);
    if (!pch || pch.width !== headerWidth || pch.height !== headerHeight
      || !Array.isArray(pch.data) || pch.data.length < 1) {
      throw new Error('PCHデータを解析できませんでした。');
    }

    const viewer = makeNeoStage('PCHを最終状態まで再生しています…');
    const applet = document.createElement('div');
    applet.className = 'neo-applet-pch';
    applet.dataset.width = String(Math.max(300, pch.width));
    applet.dataset.height = String(Math.max(326, pch.height + 26));
    viewer.stage.appendChild(applet);
    const neo = window.Neo;
    neo.params = null;
    neo.param = {
      pch: {
        image_width: String(pch.width), image_height: String(pch.height), speed: '-1',
        buffer_progress: 'false', buffer_canvas: 'false', neo_enable_zoom_out: true,
      }
    };
    neo.applet = applet;
    neo.viewer = true;
    neo.initConfig(applet);
    neo.createViewer(applet);
    neo.config.width = pch.width;
    neo.config.height = pch.height;
    neo.initViewer(pch);
    if (neo.painter) neo.painter.busySkipped = true;

    const deadline = Date.now() + 120000;
    while (Date.now() < deadline) {
      await wait(50);
      if (neo.painter && neo.getLineCount() > 0
        && neo.getSeek() >= neo.getLineCount() && !neo.painter.busy) {
        const blob = neo.painter.getPNG();
        if (!(blob instanceof Blob) || blob.type !== 'image/png') {
          throw new Error('PCHの最終画像を取得できませんでした。');
        }
        viewer.overlay.remove();
        return blob;
      }
    }
    viewer.overlay.remove();
    throw new Error('PCHの変換が時間内に完了しませんでした。');
  }

  async function convertTgkr(buffer) {
    if (!settings.tegakiEnabled) throw new Error('Tegaki.jsは無効です。');
    const bytes = new Uint8Array(buffer);
    if (bytes.length < 13 || bytes[0] !== 0x54 || bytes[1] !== 0x47 || bytes[2] !== 0x4b) {
      throw new Error('Tegaki.js形式のTGKRファイルではありません。');
    }
    const dataSize = new DataView(buffer).getUint32(4);
    if ((bytes[3] !== 0 && bytes[3] !== 1) || dataSize < 1 || dataSize > 134217728) {
      throw new Error('TGKRのデータサイズが不正です。');
    }
    loadStylesheet(assetUrl(settings.tegakiDir, 'tegaki.css'), 'noreita-animation-tegaki-css');
    await loadScript(assetUrl(settings.tegakiDir, 'tegaki.js'), 'noreita-animation-tegaki-js');
    if (!window.Tegaki || typeof window.Tegaki.open !== 'function') {
      throw new Error('Tegaki.jsを利用できません。');
    }

    const tegaki = window.Tegaki;
    tegaki.open({ replayMode: true });
    try {
      const replay = tegaki.replayViewer;
      replay.loadFromBuffer(buffer);
      if (!replay.loaded || replay.canvasWidth < 1 || replay.canvasHeight < 1
        || replay.canvasWidth > settings.maxWidth || replay.canvasHeight > settings.maxHeight) {
        throw new Error('TGKRのキャンバスサイズが不正です。');
      }
      tegaki.initFromReplay();
      tegaki.init();
      tegaki.setTool(tegaki.defaultTool);
      replay.currentPos = replay.duration;
      const deadline = Date.now() + 120000;
      let chunks = 0;
      while (replay.eventIndex < replay.events.length) {
        replay.step(0);
        if (++chunks % 20 === 0) await wait(0);
        if (Date.now() >= deadline) throw new Error('TGKRの変換が時間内に完了しませんでした。');
      }
      const blob = await canvasBlob(tegaki.flatten());
      tegaki.destroy();
      return blob;
    } catch (error) {
      if (tegaki.bg) tegaki.destroy();
      throw error;
    }
  }

  function setFormImage(form, filename, previewUrl) {
    form.querySelectorAll('[name="picfile"]').forEach((field) => {
      field.disabled = true;
    });
    form.querySelectorAll('[data-animation-generated]').forEach((field) => field.remove());
    const hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = 'picfile';
    hidden.value = filename;
    hidden.dataset.animationGenerated = '1';
    form.appendChild(hidden);
    const directUpload = form.querySelector('[name="image_upload"]');
    if (directUpload) {
      directUpload.value = '';
      directUpload.disabled = true;
    }
    const preview = form.querySelector('[data-animation-upload-preview]');
    if (preview) {
      preview.replaceChildren();
      const image = document.createElement('img');
      image.src = previewUrl;
      image.alt = '動画から生成した投稿画像';
      image.style.maxWidth = '240px';
      image.style.maxHeight = '240px';
      preview.appendChild(image);
    }
  }

  async function uploadConverted(form, file, picture) {
    const data = new FormData();
    const token = form.querySelector('[name="token"]');
    const resto = form.querySelector('[name="resto"]');
    data.append('token', token ? token.value : '');
    data.append('resto', resto ? resto.value : '');
    data.append('animation', file, file.name);
    data.append('picture', picture, 'animation.png');
    const response = await fetch(settings.endpoint, {
      method: 'POST', mode: 'same-origin', credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: data,
    });
    const text = await response.text();
    const lines = text.split(/\r?\n/);
    if (!response.ok || lines[0] !== 'ok' || !lines[1] || !lines[2]) {
      throw new Error(lines[0] === 'error' && lines[1] ? lines.slice(1).join('\n') : '動画ファイルを保存できませんでした。');
    }
    return { filename: lines[1], previewUrl: lines[2] };
  }

  document.querySelectorAll('[data-animation-upload-file]').forEach((input) => {
    const form = input.closest('form');
    const status = form && form.querySelector('[data-animation-upload-status]');
    if (!form || !status) return;
    let processing = false;

    input.addEventListener('change', () => {
      status.textContent = input.files && input.files[0]
        ? '「書き込む」を押すと、動画を確認して投稿します。'
        : '';
    });

    form.addEventListener('submit', async (event) => {
      const file = input.files && input.files[0];
      if (!file || input.disabled) return;
      event.preventDefault();
      if (processing) return;

      const directUpload = form.querySelector('[name="image_upload"]');
      if (directUpload && directUpload.files && directUpload.files[0]) {
        status.textContent = '画像と動画は同時に選択できません。どちらか一方を選択してください。';
        return;
      }
      const extension = file.name.toLowerCase().split('.').pop();
      if (extension !== 'pch' && extension !== 'tgkr') {
        status.textContent = 'PCHまたはTGKRファイルを選択してください。';
        return;
      }
      if (extension === 'tgkr' && !settings.tegakiEnabled) {
        status.textContent = 'Tegaki.jsが無効なためTGKRは利用できません。';
        return;
      }
      if (settings.maxWorkBytes < 1 || file.size < 1 || file.size > settings.maxWorkBytes) {
        status.textContent = '動画ファイルの容量が上限を超えています。';
        return;
      }
      if (engineUsed) {
        status.textContent = '別の動画へ変更する場合はページを再読み込みしてください。';
        return;
      }
      processing = true;
      engineUsed = true;
      form.querySelectorAll('[type="submit"]').forEach((submit) => { submit.disabled = true; });
      status.textContent = '動画を確認しています。投稿が完了するまでお待ちください…';
      try {
        const buffer = await file.arrayBuffer();
        const picture = extension === 'pch'
          ? await convertPch(buffer)
          : await convertTgkr(buffer);
        status.textContent = '生成した画像と動画を保存しています…';
        const result = await uploadConverted(form, file, picture);
        setFormImage(form, result.filename, result.previewUrl);
        input.value = '';
        input.disabled = true;
        status.textContent = '動画の確認が完了しました。掲示板へ書き込んでいます…';
        processing = false;
        form.querySelectorAll('[type="submit"]').forEach((submit) => { submit.disabled = false; });
        const submitter = event.submitter instanceof HTMLElement
          ? event.submitter
          : form.querySelector('[type="submit"]');
        if (typeof form.requestSubmit === 'function') {
          form.requestSubmit(submitter || undefined);
        } else {
          const send = document.createElement('input');
          send.type = 'hidden';
          send.name = 'send';
          send.value = '1';
          form.appendChild(send);
          form.submit();
        }
      } catch (error) {
        document.querySelectorAll('[aria-label="PCH変換"]').forEach((overlay) => overlay.remove());
        engineUsed = false;
        processing = false;
        status.textContent = error instanceof Error ? error.message : String(error);
        form.querySelectorAll('[type="submit"]').forEach((submit) => { submit.disabled = false; });
      }
    });
  });
})();
