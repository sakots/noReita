<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="utf-8">
  <title>{{$board_title}}</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="theme/{{$theme_dir}}/luminous/luminous-basic.min.css">
  @include('components.5u_headCss')
  @if (!empty($oya))
    @foreach ($oya as $bbsline)
    <meta name="twitter:card" content="summary">
    <meta property="og:title" content="[{{$bbsline['tid']}}] {{$bbsline['sub']}} by {{$bbsline['a_name']}} - {{$board_title}}">
    <meta property="og:type" content="article">
    <meta property="og:url" content="{{$base}}{{$self}}?mode=res&amp;res={{$resno}}">
    @if (isset($bbsline['picfile']))
      <meta property="og:image" content="{{$base}}{{$path}}{{$bbsline['picfile']}}">
    @endif
    <meta property="og:site_name" content="">
    <meta property="og:description" content="{{$bbsline['com']}}">
    @endforeach
  @endif
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
          <a href="{{$self}}">[トップ]</a>
          <a href="#footer">[↓]</a>
        </p>
      </section>
      <section>
        <hr>
        <p>RES MODE</p>
        <p class="sysmsg">{{$message}}</p>
      </section>
    </div>
    <hr>
  </header>
  <main>
    <div class="thread">
      @if (!empty($oya))
      @foreach ($oya as $bbsline)
      @if (isset($bbsline['com']))
      <section>
        <h3 class="oyat">
          <span class="oyano">[{{$bbsline['tid']}}]</span>
          {{$bbsline['sub']}}
        </h3>
        <section>
          @include('components.5u_threadOyaName', ['bbsline' => $bbsline])
          @if ($bbsline['picfile'])
          <h5>
            {{$bbsline['tool']}} ({{$bbsline['img_w']}}x{{$bbsline['img_h']}})
            @if ($bbsline['psec'] != null)
              描画時間：{{$bbsline['utime']}}
            @endif
          </h5>
          <h5>
            <a href="{{$path}}{{$bbsline['picfile']}}" target="_blank">{{$bbsline['picfile']}}</a>
            @if ($bbsline['pchfile'] != null && $bbsline['pchfile'] !== '' && pathinfo($bbsline['pchfile'], PATHINFO_EXTENSION) !== '' && (!isset($bbsline['ctype']) || $bbsline['ctype'] !== 'img'))
              <a href="{{$self}}?mode=anime&amp;pch={{$bbsline['pchfile']}}">●動画</a>
            @endif
            @if ($use_continue)
              <a href="{{$self}}?mode=continue&amp;no={{$bbsline['picfile']}}">●続きを描く</a>
            @endif
          </h5>
            <div class="item_image">
              <a class="luminous" href="{{$path}}{{$bbsline['picfile']}}">
                @if ($bbsline['thumb'])
                  <img src="{{$path}}{{$bbsline['thumb']}}" alt="{{$bbsline['picfile']}}" loading="lazy" class="image">
                @else
                  <img src="{{$path}}{{$bbsline['picfile']}}" alt="{{$bbsline['picfile']}}" loading="lazy" class="image">
                @endif
              </a>
            </div>
            @endif
            <div class="item_comment">
              <p class="comment oya">{!! $bbsline['com'] !!}</p>
              @if (!empty($ko))
                @foreach ($ko as $res)
                  <section class="res">
                  @include('components.5u_threadRepName', ['res' => $res])
                    @if ($res['picfile'])
                      @include('components.5u_threadRepPicfile', ['res' => $res])
                    @endif
                    <p class="comment">{!! $res['com'] !!}</p>
                  </section>
                @endforeach
              @endif
            </div>
          </section>
        <hr>
      </section>
      @if ($share_button)
        <div class="thfoot">
          @include('components.5u_resThreadFoot', ['bbsline' => $bbsline])
        </div>
      @endif
      @endif
      <div>
        @foreach ($oya as $bbsline)
          @if (!empty($bbsline['com']))
            <section>
              @if ($bbsline['parent'] < 1)
                <h3 class="oekaki">このスレにリプライ</h3>
                @if ($use_oekaki_reply)
                  <hr>
                  <section class="epost">
                    @include('components.5u_picpostForm', ['resno' => $bbsline['tid']])
                  </section>
                  <hr>
                @endif
                <script>
                  function add_to_com() {
                    document.getElementById("p_input_com").value += "{{$resname}}さん";
                  }
                </script>
                @if ($elapsed_time === 0 || $nowtime - $bbsline['past'] < $elapsed_time)
                  <p>
                    <button class="copy_button" onclick="add_to_com()">投稿者名をコピー</button>
                    （投稿者名をコピぺできます）
                  </p>
                  @include('components.5u_resForm', ['resno' => $bbsline['tid']])
                @else
                  <p>このスレは古いので返信できません</p>
                @endif
              @else
                <p><a href="{{$self}}?mode=res&amp;res={{$bbsline['parent']}}">このスレッドへ</a></p>
              @endif
            </section>
          @endif
        @endforeach
      </div>
      <div class="thfoot">
        <a href="#header">[↑]</a>
      </div>
    @endforeach
  @else
    <section>
      <h3 class="oyat">エラー</h3>
      <h4>none</h4>
      <p>そんなスレッドないです。</p>
    </section>
  @endif
</div>
  </main>
    <footer id="footer">
      @include('components.5u_footerCopy')
    </footer>
    <!-- scripts -->
    <script src="theme/{{$theme_dir}}/js/sodane.js"></script>
    @include('components.5u_togglePaletteVisibility')
    @include('components.5u_luminous')
    @include('components.5u_snsShare')
  </body>
</html>
