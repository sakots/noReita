<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="utf-8">
  <title>{{$board_title}}</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="theme/{{$theme_dir}}/luminous/luminous-basic.min.css">
  @include('components.monoreita_headCss')
  <link rel="stylesheet" href="theme/{{$theme_dir}}/luminous/noreita-luminous.css">
  @if (!empty($og_url))
    <meta name="twitter:card" content="{{$og_twitter_card}}">
    <meta property="og:title" content="{{$og_title}}">
    <meta property="og:type" content="article">
    <meta property="og:url" content="{{$og_url}}">
    @if (!empty($og_image))
      <meta property="og:image" content="{{$og_image}}">
      <meta property="og:image:alt" content="{{$og_image_alt}}">
      <meta name="twitter:image" content="{{$og_image}}">
    @endif
    <meta property="og:site_name" content="{{$board_title}}">
    <meta property="og:description" content="{{$og_description ?? ''}}">
  @endif
  @include('components.monoreita_customCss')
</head>

<body>
  <header id="header">
    <h1><a href="{{$self}}">{{$board_title}}</a></h1>
    <div>
      <a href="{{$home}}" target="_top">[ホーム]</a>
      @include('components.monoreita_adminSession')
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
          @include('components.monoreita_threadOyaName', ['bbsline' => $bbsline]) <!-- スレ主の名前 -->
          @if ($bbsline['picfile'])
          @include('components.monoreita_threadOyaPicfile', ['bbsline' => $bbsline]) <!-- スレ主の画像 -->
            @endif
            <div class="item_comment">
              <p class="comment oya">{!! $bbsline['com'] !!}</p>
              @if (!empty($ko))
                @foreach ($ko as $res)
                  <section class="res">
                  @include('components.monoreita_threadRepName', ['res' => $res]) <!-- レスの名前 -->
                    @if ($res['picfile'])
                      @include('components.monoreita_threadRepPicfile', ['res' => $res]) <!-- レスの画像 -->
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
          @include('components.monoreita_resThreadFoot', ['bbsline' => $bbsline]) <!-- スレッドフッタ（SNSシェアボタンなど） -->
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
                    @include('components.monoreita_picpostForm', ['resno' => $bbsline['tid']]) <!-- お絵かき返信フォーム -->
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
                  @include('components.monoreita_resForm', ['resno' => $bbsline['tid']]) <!-- 返信フォーム -->
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
      @include('components.monoreita_footerCopy')
    </footer>
    <!-- scripts -->
    <script src="theme/{{$theme_dir}}/js/sodane.js"></script>
    @if ($use_image_upload)
    <script src="animation-upload.js?v={{$animation_upload_version}}" data-endpoint="{{$self}}?mode=animation_upload"
      data-neo-dir="{{$neo_dir}}" data-tegaki-dir="{{$tegaki_dir}}"
      data-tegaki-enabled="{{$use_tegaki ? '1' : '0'}}"
      data-max-work-bytes="{{$animation_upload_max_bytes}}"
      data-max-width="{{$pmax_w}}" data-max-height="{{$pmax_h}}"></script>
    @endif
    @include('components.monoreita_togglePaletteVisibility')
    @include('components.monoreita_luminous')
    @include('components.monoreita_snsShare')
  </body>
</html>
