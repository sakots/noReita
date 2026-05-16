<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>{{$board_title}}</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  @include('components.headCss')
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
        <p class="sysmsg">{{$message ?? ''}}</p>
      </section>
    </div>
    <hr>
    <div>
      <section class="epost">
        @include('components.picpostForm')
        @include('components.info')
      </section>
      <hr>
      <section class="paging">
        @include('components.paging')
      </section>
    </div>
  </header>
  <main id="main">
    <div>
      @if (!empty($oya))
        @foreach ($oya as $bbsline)
          <section class="thread @if ($bbsline['will_delete']) will-delete-thread @endif">
            @include('components.threadTitle', ['bbsline' => $bbsline])
            @include('components.thread', ['bbsline' => $bbsline])
          </section>
        @endforeach
      @endif
    </div>
    <div>
      <section class="thread">
        <section class="paging">
          @include('components.paging')
        </section>
        <hr>
        @include('components.searchForm')
        @include('components.deleteForm')
      </section>
    </div>
  </main>
  <footer id="footer">
    @include('components.footerCopy')
  </footer>
  <!-- scripts -->
  <script src="theme/{{$theme_dir}}/js/sodane.js"></script>
  @include('components.togglePaletteVisibility')
  @include('components.luminous')
  @include('components.snsShare')
</html>