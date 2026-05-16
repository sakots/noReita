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
      <a href="{{$home}}" target="_top">[ホーム]</a>
      <a href="{{$self}}?mode=admin_in">[管理モード]</a>
    </div>
    <hr>
    <div>
      <section>
        <p class="top menu">
          <a href="{{$self}}">[通常モード]</a>
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
      @if ($catalogmode == 'catalog')
        <p>カタログモード</p>
      @endif
      @if ($catalogmode == 'search')
        <p>検索モード -「{{$author}}」の作品 - {{$s_result}}件</p>
      @endif
      @if ($catalogmode == 'hashsearch')
        <p>本文検索 -「{{$tag}}」- {{$s_result}}件</p>
      @endif
      @if ($catalogmode == 'catalog')
      <hr>
      @include('components.pagingCatalogMode')
      @endif
    </div>
  </header>
  <main>
    <div class="thread" id="catalog">
      @if (!empty($oya))
        @foreach ($oya as $bbsline)
          <div>
            <div>
              @if ($bbsline['picfile'])
                <p>
                  <a href="{{$self}}?mode=res&amp;res={{$bbsline['tid']}}" title="{{$bbsline['sub']}} (by {{$bbsline['a_name']}})">@if ($bbsline['thumb'])<img src="{{$path}}{{$bbsline['thumb']}}" alt="{{$bbsline['sub']}} (by {{$bbsline['a_name']}})" loading="lazy">@else<img src="{{$path}}{{$bbsline['picfile']}}" alt="{{$bbsline['sub']}} (by {{$bbsline['a_name']}})" loading="lazy">@endif</a>
                </p>
              @else
                <p>
                  <a href="{{$self}}?mode=res&amp;res={{$bbsline['tid']}}" title="{{$bbsline['sub']}} (by {{$bbsline['a_name']}})">{{$bbsline['sub']}} (by {{$bbsline['a_name']}})</a>
                </p>
              @endif
              <p>
                [{{$bbsline['tid']}}]
              </p>
            </div>
          </div>
        @endforeach
      @endif
      @if ($catalogmode == 'hashsearch')
      @if (!empty($ko))
      @foreach ($ko as $res)
      <div>
        <div>
          @if ($res['picfile'])
          <p>
            <a href="{{$self}}?mode=res&amp;res={{$res['parent']}}" title="{{$res['sub']}} (by {{$res['a_name']}})">@if ($res['thumb'])<img src="{{$res['thumb']}}" alt="{{$res['sub']}} (by {{$res['a_name']}})" loading="lazy">@else<img src="{{$path}}{{$res['picfile']}}" alt="{{$res['sub']}} (by {{$res['a_name']}})" loading="lazy">@endif</a>
          </p>
          @else
          <p>
            <a href="{{$self}}?mode=res&amp;res={{$res['parent']}}" title="{{$res['sub']}} (by {{$res['a_name']}})">{!! mb_substr($res['com'], 0, 30)!!}</a>
          </p>
          @endif
          <p>
            [{{$res['tid']}}]({{$res['tid']}})
          </p>
        </div>
      </div>
      @endforeach
      @endif
      @endif
    </div>
    <div>
      <section class="thread">
        @if ($catalogmode == 'catalog')
          @include('components.pagingCatalogMode')
          <hr>
        @endif
        <p>作者名/本文(ハッシュタグ)検索</p>
        <form class="search" method="GET" action="{{$self}}">
          <input type="hidden" name="mode" value="search">
          <label><input type="radio" name="similar" value="similar">部分一致</label>
          <label><input type="radio" name="similar" value="exact">完全一致</label>
          <label><input type="radio" name="tag" value="tag">本文(ハッシュタグ)</label>
          <br>
          <input type="text" name="search" placeholder="検索" size="20">
          <input type="submit" value=" 検索 ">
        </form>
        @include('components.deleteForm')
      </section>
    </div>
  </main>
  <footer id="footer">
    @include('components.footerCopy')
  </footer>
</body>

</html>