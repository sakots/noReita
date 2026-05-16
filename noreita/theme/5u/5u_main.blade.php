<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>{{$board_title}}</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="theme/{{$theme_dir}}/luminous/luminous-basic.min.css">
  @include('components.5u_headCss')
</head>
<body>
  <header id="header">
    <h1><a href="{{$self}}">{{$board_title}}</a></h1>
    <div>
      <section>
        <a href="{{$home}}" target="_top">[ホーム]</a>
        <a href="{{$self}}?mode=admin_in">[管理モード]</a>
      </section>
    </div>
    <hr>
    <div>
      <section>
        <p class="top menu">
          <a href="{{$self}}?mode=catalog">[カタログ]</a>
          <a href="{{$self}}?mode=pictmp">[投稿途中の絵]</a>
          <a href="#footer">[↓]</a>
        </p>
      </section>
      <section>
        <p class="sysmsg">{{$message ?? ''}}</p> <!-- システムメッセージ -->
      </section>
    </div>
    <hr>
    <div>
      <section class="epost">
        @include('components.5u_picpostForm') <!-- 絵の投稿フォーム -->
        @include('components.5u_info') <!-- お知らせ -->
      </section>
      <hr>
      <section class="paging">
        @include('components.5u_paging') <!-- ページング -->
      </section>
    </div>
  </header>
  <main id="main">
    <div>
      @if (!empty($oya))
        @foreach ($oya as $bbsline)
          <section class="thread @if ($bbsline['will_delete']) will-delete-thread @endif">
            @include('components.5u_threadTitle', ['bbsline' => $bbsline]) <!-- スレッドタイトル -->
            @include('components.5u_thread', ['bbsline' => $bbsline]) <!-- スレッド内容 -->
          </section>
        @endforeach
      @endif
    </div>
    <div>
      <section class="thread">
        <section class="paging">
          @include('components.5u_paging') <!-- ページング -->
        </section>
        <hr>
        @include('components.5u_searchForm') <!-- 検索フォーム -->
        @include('components.5u_deleteForm') <!-- 削除フォームとcssスイッチ -->
      </section>
    </div>
  </main>
  <footer id="footer">
    @include('components.5u_footerCopy') <!-- コピーライト -->
  </footer>
  <!-- scripts -->
  <script src="theme/{{$theme_dir}}/js/sodane.js"></script>
  @include('components.5u_togglePaletteVisibility')
  @include('components.5u_luminous')
  @include('components.5u_snsShare')
</html>