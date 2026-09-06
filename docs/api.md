# 公開JSON API（api.php）

`noreita/api.php` は、Reactなどのクライアントから公開投稿を取得するためのAPIです。
投稿・編集・削除はできません。APIのバージョンは、成功レスポンスの `api_version: "v1"` で確認できます。

## 接続方法

掲示板を `https://bbs.example.com/noreita/` に設置した場合、APIのURLは次のとおりです。

```text
https://bbs.example.com/noreita/api.php
```

- HTTPメソッドは `GET` のみです。パラメーターはクエリ文字列で指定します。
- 通常のレスポンスは `application/json; charset=UTF-8` です。
- 認証・APIキーは不要で、ユーザーセッションも開始しません。
- アプリケーション側ではCORS許可ヘッダーを出していません。ブラウザーからの利用は同一オリジンを想定しています。

```bash
curl 'https://bbs.example.com/noreita/api.php?mode=threads&per_page=10'
```

## モード

| `mode` | 内容 | 固有の必須パラメーター |
| --- | --- | --- |
| `threads`（省略時） | 公開スレッドの親投稿一覧。返信は含まない | なし |
| `thread` | 公開スレッドの親投稿と公開返信一覧 | `id`：親投稿の番号 |
| `catalog` | 公開画像投稿の一覧。画像付き返信も含む | なし |
| `search` | 公開投稿を検索 | なし |

`threads` は `tree` の降順、`catalog` は `age`・`tree` の降順です。
`thread` の返信は `comid` の昇順です。投稿日時だけで並べているわけではありません。

```text
api.php?mode=threads&page=1&per_page=10
api.php?mode=thread&id=123
api.php?mode=catalog&page=2&per_page=20
api.php?mode=search&q=風景&target=comment&image=with
```

`thread` に返信の番号を渡しても親スレッドには変換されず、404になります。
このAPIの取得パラメーターは `id` です。画面共有用の `?resno=123` とは異なります。

## ページ送り

`threads`・`catalog`・`search` で使用できます。`thread` は返信をまとめて返し、ページ送りはありません。

| パラメーター | 内容 | 既定値 |
| --- | --- | --- |
| `page` | 1から始まるページ番号 | `1` |
| `per_page` | 1ページの件数。100を超える指定は100に補正 | `threads` は `board.page_size`、その他は `board.catalog_size`（上限100） |

整数指定に `0`・負数・小数・先頭ゼロ付きの文字列は使えません。
最終ページより大きい `page` は最終ページに補正されます。
0件の場合も `page` と `total_pages` は `1` で、`items` は空配列です。

## 検索パラメーター

`mode=search` で、ページ送りに加えて次の条件を指定できます。

| パラメーター | 選択肢・内容 | 既定値 |
| --- | --- | --- |
| `q` | 検索語。前後の空白を除いて最大100文字 | 空文字列 |
| `search` | `q` を省略した場合の代替パラメーター | 空文字列 |
| `target` | `author`（作者名）、`subject`（題名）、`comment`（本文）、`all` | `author` |
| `match` | `exact`（完全一致）、`partial`（部分一致） | `partial` |
| `post_type` | `all`、`thread`、`reply` | `all` |
| `image` | `any`、`with`（画像あり）、`without`（画像なし） | `any` |
| `nsfw` | `any`、`safe`、`nsfw` | `any` |
| `sort` | `newest`（`age`・`tree` 降順）、`oldest`（同昇順） | `newest` |

選択肢にない値は、その項目の既定値に置き換わります。
レスポンスの `criteria` に、実際に使用した条件が入ります。検索語のキーは `q` ではなく `query` です。

## レスポンス

一覧系（`threads`・`catalog`・`search`）の例です。URLや投稿内容は説明用の架空データです。

```json
{
  "api_version": "v1",
  "mode": "threads",
  "items": [
    {
      "id": 123,
      "thread_id": 123,
      "post_type": "thread",
      "author": "作者",
      "subject": "風景",
      "comment": "描いてみました。",
      "url": "https://bbs.example.com/noreita/index.php?resno=123",
      "created_at": "2026-09-06 12:00:00",
      "modified_at": "2026-09-06 12:00:00",
      "sodane": 0,
      "image": {
        "url": "https://bbs.example.com/noreita/img/example.png",
        "thumbnail_url": "https://bbs.example.com/noreita/img/example_thumb.webp",
        "width": 640,
        "height": 480,
        "nsfw": false
      }
    }
  ],
  "pagination": {
    "page": 1,
    "per_page": 10,
    "total": 1,
    "total_pages": 1
  }
}
```

`mode=thread` では `items`・`pagination` の代わりに `thread`（親投稿オブジェクト）と
`replies`（返信配列）を返します。各投稿の構造は一覧系と共通です。

### 投稿データの注意点

- `id` は投稿番号、`thread_id` は所属する親投稿の番号です。返信の `url` も親スレッドのURLです。
- `author`・`subject`・`comment` は文字列です。本文のリンク化やHTML生成は行いません。
  表示時はReactの通常の文字列描画や `textContent` を使い、HTMLとして直接挿入しないでください。
- `created_at`・`modified_at` はDBの日時文字列です。タイムゾーンオフセット付きのISO 8601形式ではありません。
- `image` は画像がない場合 `null` です。`width`・`height` は元画像のピクセル寸法です。
- `thumbnail_url` はサムネイル未登録時に元画像URLへフォールバックします。
- NSFW画像でも `image.url` に元画像URLが含まれます。OGPとは異なりAPI自体は原寸URLを隠しません。
  クライアントは `image.nsfw` を確認して表示を制御してください。
- パスワードハッシュ、接続元ホスト、メールアドレスなどの内部フィールドは返しません。
- 非表示判定は各投稿の `invz` に基づきます。カタログ・検索では親投稿の非表示状態まで照合していません。

## エラー

| HTTPステータス | `error.code` | 主な原因 |
| --- | --- | --- |
| 400 | `invalid_request` | `id`・`page`・`per_page` が不正 |
| 404 | `invalid_request` | 未対応の `mode`、存在しない・非表示のスレッド、返信番号の指定 |
| 405 | `method_not_allowed` | GET以外のメソッド。`Allow: GET` ヘッダーも返す |
| 500 | `server_error` | 初期化やDB処理などの例外 |

```json
{
  "error": {
    "code": "invalid_request",
    "message": "Thread not found."
  }
}
```

500では詳細な例外メッセージの代わりに `error.id` を返します。
現状、100文字を超える検索語も500の `server_error` になるため、送信前に長さを確認してください。
設定読み込みなどAPIの例外処理より前で失敗した場合は、このJSON形式とは限りません。

## JavaScriptからの取得例

掲示板と同じディレクトリにあるページからスレッドを取得する例です。

```js
async function fetchThread(id) {
  const query = new URLSearchParams({ mode: 'thread', id: String(id) });
  const response = await fetch(`./api.php?${query}`);
  if (!response.ok) {
    throw new Error(`投稿の取得に失敗しました（HTTP ${response.status}）`);
  }
  return response.json();
}

const { thread, replies } = await fetchThread(123);
document.querySelector('#subject').textContent = thread.subject;
```

## 実装の参照先

- [api.php](../noreita/api.php)：HTTPメソッド判定、初期化、JSON応答、例外処理
- [api.inc.php](../noreita/api.inc.php)：モード振り分け、パラメーター、公開フィールドの整形
- [database.inc.php](../noreita/database.inc.php)：`BoardRepository` と `PublicPostSearch`

投稿を変更するAPIではありませんが、GET時にも保存ディレクトリの準備、DBマイグレーション、
DBファイルの権限設定を実行します。設置・更新時の初期化を含む点には留意してください。
