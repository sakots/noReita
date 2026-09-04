// 投稿画像をPNGへ変換してクリップボードにコピーする。
document.addEventListener('click', async (event) => {
  const link = event.target.closest('.copy-image');
  if (!link || !navigator.clipboard || typeof ClipboardItem === 'undefined'
    || typeof createImageBitmap !== 'function') return;

  event.preventDefault();
  const originalLabel = link.textContent;
  link.setAttribute('aria-busy', 'true');
  link.textContent = '●コピー中…';
  try {
    const response = await fetch(link.dataset.imageUrl, {credentials: 'same-origin'});
    if (!response.ok) throw new Error('Image request failed.');
    const source = await response.blob();
    const bitmap = await createImageBitmap(source);
    const canvas = document.createElement('canvas');
    canvas.width = bitmap.width;
    canvas.height = bitmap.height;
    canvas.getContext('2d').drawImage(bitmap, 0, 0);
    bitmap.close();
    const png = await new Promise((resolve, reject) => {
      canvas.toBlob((blob) => blob ? resolve(blob) : reject(new Error('PNG conversion failed.')), 'image/png');
    });
    await navigator.clipboard.write([new ClipboardItem({'image/png': png})]);
    link.textContent = '●コピーしました';
  } catch (error) {
    link.textContent = '●コピーできませんでした';
  } finally {
    link.removeAttribute('aria-busy');
    window.setTimeout(() => { link.textContent = originalLabel; }, 1800);
  }
});
