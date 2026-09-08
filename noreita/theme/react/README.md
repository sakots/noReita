# React board theme

`monoreita` を親に持つ、閲覧用 React テーマです。トップページだけを React
で描画し、投稿・描画・管理・カタログ・個別スレッドは親テーマの安全な画面を使います。

## 設置

`theme/react/` を配置し、`config.local.php` の `paths.theme` を `react` にします。
React の実行時依存は `assets/react-board.js` に同梱されるため、設置先で npm は不要です。

## 開発と配布

編集対象は型定義済みの `src/react-board.tsx` です。リポジトリ直下で次を実行して
型検査と `assets/react-board.js` の再生成を行い、`theme/react/` ディレクトリ全体を配布します。

```sh
npm run react:build
npm run react:check
```

公開一覧は、現在の設置先から解決する同一オリジンの `api.php?mode=threads` のみを
読みます。`site.base_url` のホスト名やプロキシ設定に影響されず、投稿などの状態変更
API は使用しません。
