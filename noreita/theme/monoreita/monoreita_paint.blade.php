<!DOCTYPE html>
<html lang="ja">
  <head>
    <meta charset="utf-8">
    <title>{{$board_title}}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('components.monoreita_headCss')
    @if ($tool == 'neo')
    <link rel="stylesheet" href="{{$neo_dir}}neo.css?{{$stime}}" type="text/css">
    <script src="{{$neo_dir}}neo.js?{{$stime}}" charset="utf-8"></script>
    <!-- アプレットフィット -->
    <script>
      const originalWidth = {{$w}};
      const originalHeight = {{$h}};
    </script>
    <script src="theme/{{$theme_dir}}/js/appFit.js?{{$stime}}" charset="utf-8"></script>
    <!-- アプレットフィットここまで -->
    @endif
    @if ($tool == 'shi')
    <!-- CheerpJ -->
    <script src="{{$cheerpj_url}}"></script>
    @endif
  </head>
  <body id="paintmode">
    <header>
      <h1><a href="{{$self}}">{{$board_title}}</a></h1>
      <div>
        <a href="{{$home}}" target="_top">[ホーム]</a>
        <a href="{{$self}}?mode=admin_in">[管理モード]</a>
      </div>
      <hr>
      <section>
        <p class="top menu">
          <a href="{{$self}}">[トップ]</a>
        </p>
      </section>
      <hr>
      <h2 class="oekaki">OEKAKI MODE</h2>
      <hr>
    </header>
    <main>
      @if ($tool == 'neo' || $tool == 'shi')
      <!-- 動的パレットスクリプト -->
      <script>
        // パレットデータの初期化
        var Palettes = new Array();
        @if ($palettes)
          {!!$palettes!!}
        @endif
      </script>
      <script src="theme/{{$theme_dir}}/js/dynamicPalette.js?{{$stime}}" charset="utf-8"></script>
      <script>
        // パレットデータをマネージャーに設定
        document.addEventListener('DOMContentLoaded', function() {
          // 少し遅延させて確実にマネージャーが初期化されるのを待つ
          setTimeout(function() {
            if (window.dynamicPaletteManager && window.Palettes) {
              window.dynamicPaletteManager.setPaletteData(window.Palettes);
              // 初期化後にパレットリストの色を設定
              if (window.dynamicPaletteManager.DynamicColor) {
                window.dynamicPaletteManager.PaletteListSetColor();
              }
            }
          }, 100);
        });
      </script>
      <!-- 動的パレットスクリプトここまで -->
      <section id="appstage">
        <div class="app" id="apps">
          @if ($tool == 'neo')
          <applet-dummy code="pbbs.PaintBBS.class" archive="./PaintBBS.jar" name="paintbbs" width="{{$w}}" height="{{$h}}" mayscript>
          @elseif ($tool == 'shi')
          <applet code="c.ShiPainter.class" archive="./{{$shi_painter_dir}}spainter_all.jar" name="paintbbs" width="{{$w}}" height="{{$h}}" mayscript>
          <param name=dir_resource value="./{{$shi_painter_dir}}">
          <param name="tt.zip" value="tt_def.zip">
          <param name="res.zip" value="res.zip">
          @endif
          <param name="image_width" value="{{$picw}}">
          <param name="image_height" value="{{$pich}}">
          <param name="undo" value="{{$undo}}">
          <param name="undo_in_mg" value="{{$undo_in_mg}}">
          @if ($tool == 'neo')
          <param name="url_save" value="{{$self}}?mode=saveimage&amp;tool=neo">
          @elseif ($tool == 'shi')
          <param name="url_save" value="picpost.php">
          @endif
          @if (isset($resto))
          <param name="url_exit" value="{{$self}}?mode={{$mode}}&amp;stime={{$stime}}&amp;resto={{$resto}}">
          @else
          <param name="url_exit" value="{{$self}}?mode={{$mode}}&amp;stime={{$stime}}">
          @endif
          @if (isset($imgfile))<param name="image_canvas" value="{{$imgfile}}">@endif
          @if (isset($pchfile))<param name="pch_file" value="{{$pchfile}}">@endif
          <param name="poo" value="false">
          <param name="send_advance" value="true">
          <param name="send_header" value="usercode={{$usercode}}">
          <param name="thumbnail_width" value="100%">
          <param name="thumbnail_height" value="100%">
          <param name="tool_advance" value="true">
          @if ($anime)<param name="thumbnail_type" value="animation">@endif
          @if (isset($security))
          @if (isset($security_click))<param name="security_click" value="{{$security_click}}">@endif
          @if (isset($security_timer))<param name="security_timer" value="{{$security_timer}}">@endif
          <param name="security_url" value="{{$security_url}}">
          <param name="security_post" value="false">
          @endif
          @if ($tool == 'neo')
          <param name="neo_confirm_unload" value="true">
          <param name="neo_show_right_button" value="true">
          <param name="neo_send_with_formdata" value="true">
          @endif
          @if ($tool == 'shi')
          </applet>
          <script>
            cheerpjInit();
          </script>
          @elseif ($tool == 'neo')
          </applet-dummy>
          @endif
        </div>
        <div class="palette" id="dyntools">
          @include('components.monoreita_dynamicPalette') <!-- 動的パレット -->
        </div>
      </section>
      <section>
        <div class="thread">
          <hr>
          @include('components.monoreita_timer') <!-- タイマー -->
        </div>
      </section>
      <section>
        @include('components.monoreita_siiHelp') <!-- ヘルプ -->
      </section>
      @endif
    </main>
    <footer id="footer">
      @include('components.monoreita_footerCopy')
    </footer>
  </body>
</html>
