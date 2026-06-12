<!DOCTYPE html>
<html lang="ja">
  <head>
    <meta charset="utf-8">
    <title>{{$board_title}}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('components.5u_headCss')
    <link rel="stylesheet" href="{{$neo_dir}}neo.css?{{$stime}}" type="text/css">
    <script src="{{$neo_dir}}neo.js?{{$stime}}" charset="utf-8"></script>
    <!-- アプレットフィット -->
    <script>
      const originalWidth = {{$w}};
      const originalHeight = {{$h}};
    </script>
    <script src="theme/{{$theme_dir}}/js/appFit.js?{{$stime}}" charset="utf-8"></script>
    <!-- アプレットフィットここまで -->
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
          <div class="neo-applet-paintbbs" data-width="{{$w}}" data-height="{{$h}}"></div>
          <script>
            Neo.params = {
              paintbbs: {
                image_width:{{$picw}},
                image_height:{{$pich}},
                undo:{{$undo}},
                undo_in_mg:{{$undo_in_mg}},
                neo_max_pch: 2048,
                neo_send_with_formdata:true,
                neo_validate_exact_ok_text_in_response:true,
                neo_confirm_layer_info_notsaved:true,
                neo_confirm_unload:true,
                neo_show_right_button:true,
                neo_animation_skip:true,
                neo_disable_grid_touch_move:true,
                neo_enable_zoom_out:true,
                neo_disable_turn_original_glitch:true,
                send_header_count:true,
                send_header_timer:true,
                
                thumbnail_width:"100%",
                thumbnail_height:"100%",
                url_save:"{{$self}}?mode=saveimage&tool=neo",
                @if (isset($resto))
                  url_exit:"{{$self}}?mode={!!$mode!!}&stime={{$stime}}&resto={{$resto}}",
                @else
                  url_exit:"{{$self}}?mode={!!$mode!!}&stime={{$stime}}",
                @endif
                @if (isset($imgfile)) image_canvas:"{{$imgfile}}", @endif
                @if (isset($pchfile)) pch_file:"{{$pchfile}}", @endif
                poo:false,
                send_advance:true,
                send_header:"usercode={{$usercode}}",
                @if ($anime) thumbnail_type:"animation", @endif
                @if (isset($security))
                  @if (isset($security_click)) security_click:"{{$security_click}}", @endif
                  @if (isset($security_timer)) security_timer:"{{$security_timer}}", @endif
                  security_url:"{{$security_url}}",
                  security_post:false
                @endif
              }
            }
          </script>
        </div>
        <div class="palette" id="dyntools">
          @include('components.5u_dynamicPalette') <!-- 動的パレット -->
        </div>
      </section>
      <section>
        <div class="thread">
          <hr>
          @include('components.5u_timer') <!-- タイマー -->
        </div>
      </section>
      <section>
        @include('components.5u_siiHelp') <!-- ヘルプ -->
      </section>
    </main>
    <footer id="footer">
      @include('components.5u_footerCopy')
    </footer>
  </body>
</html>
