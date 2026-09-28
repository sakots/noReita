<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>{{$board_title}}</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  @include('components.monoreita_customCss')
  <script defer src="theme/react/assets/react-board.js?v={{$theme_active_asset_version}}"></script>
</head>
<body>
  <main class="react-board-shell">
    <header class="react-board-header">
      <h1><a href="{{$self}}">{{$board_title}}</a></h1>
      <nav aria-label="掲示板メニュー">
        <a href="{{$self}}?mode=piccom">投稿する</a>
        <a href="{{$self}}?mode=catalog">カタログ</a>
        <a href="{{$self}}?mode=pictmp">投稿途中の絵</a>
      </nav>
    </header>
    <div id="react-board" data-api-url="api.php">
      <p>読み込み中…</p>
    </div>
    <noscript>
      <p>このテーマの一覧表示には JavaScript が必要です。<a href="{{$self}}?mode=catalog">カタログ</a>をご利用ください。</p>
    </noscript>
  </main>
</body>
</html>
