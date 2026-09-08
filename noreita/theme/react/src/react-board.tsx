import {useEffect, useState} from 'react';
import {createRoot} from 'react-dom/client';

type ImageData = {
  thumbnail_url: string;
};

type Post = {
  id: number;
  author: string;
  subject: string;
  comment: string;
  url: string;
  created_at: string;
  image: ImageData | null;
  replies: Post[];
};

type Pagination = {
  page: number;
  total: number;
  total_pages: number;
};

type ThreadsResponse = {
  items: Post[];
  pagination: Pagination;
};

const mount = document.getElementById('react-board');

/** Images are always local noReita assets; keep them on the page's origin. */
function localImageUrl(url: string): string {
  const parsed = new URL(url, window.location.href);
  return `${parsed.pathname}${parsed.search}${parsed.hash}`;
}

function isThreadsResponse(value: unknown): value is ThreadsResponse {
  if (typeof value !== 'object' || value === null) return false;
  const response = value as Partial<ThreadsResponse>;
  return Array.isArray(response.items)
    && typeof response.pagination === 'object' && response.pagination !== null;
}

function Reply({reply}: {reply: Post}) {
  return <article className="react-reply">
    <div className="react-reply-meta">#{reply.id} {reply.author} · {reply.created_at}</div>
    <div className="react-comment">{reply.comment}</div>
    {reply.image !== null && <a href={reply.url}><img className="react-thumbnail" src={localImageUrl(reply.image.thumbnail_url)} alt="" loading="lazy" /></a>}
  </article>;
}

function Thread({thread}: {thread: Post}) {
  const title = thread.subject || `No.${thread.id}`;
  return <article className="react-thread">
    <div>
      <h2><a href={thread.url}>{title}</a></h2>
      <div className="react-thread-meta">#{thread.id} {thread.author} · {thread.created_at}</div>
      <div className="react-comment">{thread.comment}</div>
    </div>
    {thread.image !== null && <a href={thread.url}><img className="react-thumbnail" src={localImageUrl(thread.image.thumbnail_url)} alt="" loading="lazy" /></a>}
    {thread.replies.length > 0 && <section className="react-replies" aria-label="返信">
      {thread.replies.map((reply) => <Reply key={reply.id} reply={reply} />)}
    </section>}
  </article>;
}

function App({apiUrl}: {apiUrl: string}) {
  const [page, setPage] = useState(1);
  const [result, setResult] = useState<ThreadsResponse | null>(null);
  const [error, setError] = useState('');

  useEffect(() => {
    const controller = new AbortController();
    setError('');
    setResult(null);
    fetch(`${apiUrl}?mode=threads&page=${page}`, {
      credentials: 'same-origin', signal: controller.signal, headers: {Accept: 'application/json'},
    })
      .then((response) => response.ok ? response.json() : Promise.reject(new Error(`HTTP ${response.status}`)))
      .then((data: unknown) => {
        if (!isThreadsResponse(data)) throw new Error('Invalid API response');
        setResult(data);
      })
      .catch((reason: unknown) => {
        if (!(reason instanceof DOMException && reason.name === 'AbortError')) {
          setError('一覧を読み込めませんでした。');
        }
      });
    return () => controller.abort();
  }, [apiUrl, page]);

  if (error !== '') return <p className="react-error" role="alert">{error}</p>;
  if (result === null) return <p>読み込み中…</p>;
  const {items, pagination} = result;
  return <>
    <p>{pagination.total}件のスレッド</p>
    <section className="react-board-list">{items.map((thread) => <Thread key={thread.id} thread={thread} />)}</section>
    <nav className="react-pagination" aria-label="ページ移動">
      <button type="button" disabled={pagination.page <= 1} onClick={() => setPage(page - 1)}>前へ</button>
      <span>{pagination.page} / {pagination.total_pages}</span>
      <button type="button" disabled={pagination.page >= pagination.total_pages} onClick={() => setPage(page + 1)}>次へ</button>
    </nav>
  </>;
}

if (mount !== null && mount.dataset.apiUrl !== undefined) {
  createRoot(mount).render(<App apiUrl={mount.dataset.apiUrl} />);
}
