<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="utf-8">
  <title>{{$board_title}}</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  @include('components.monoreita_headCss')
  <script>
    document.paintBBSCallback = function (str) {
      console.log('paintBBSCallback', str)
      if (str == 'check') {
        return true;
      } else {
      return;
      }
    }
	</script>
  <link rel="stylesheet" href="{{$neo_dir}}neo.css?{{$a_stime}}" type="text/css">
  <script src="{{$neo_dir}}neo.js?{{$a_stime}}" charset="utf-8"></script>
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
    <h2 class="oekaki">PCH MODE</h2>
    <hr>
  </header>
  <main>
    <section id="appstage">
      <div class="app">
        <div class="neo-applet-pch" data-width="{{$w}}" data-height="{{$h}}"></div>
        <script>
          Neo.param = {
            pch:{
              image_width: "{{$picw}}",
              image_height: "{{$pich}}",
              pch_file: "{{$path}}{{$pchfile}}",
              speed: "{{$speed}}",
              buffer_progress: "false",
              buffer_canvas: "false",
              neo_enable_zoom_out:true,
              neo_viewer_buttonswrapper_top:true,
            }
          }
        </script>
      </div>
    </section>
    <section class="thread">
      <hr>
      <p>
        <a href="{{$path}}{{$pchfile}}" target="_blank">Download</a>
        @if (isset($datasize))
        - Datasize {{$datasize}} B
        @endif
      </p>
      <hr>
    </section>
  </main>
  <footer id="footer">
    @include('components.monoreita_footerCopy')
  </footer>
</body>

</html>