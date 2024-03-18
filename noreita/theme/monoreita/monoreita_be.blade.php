<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{$btitle}}</title>
  <style>
    body{ overscroll-behavior-x: none !important; }
    :not(input),div#chickenpaint-parent :not(input){
      -moz-user-select: none;
      -webkit-user-select: none;
      -ms-user-select: none;
      user-select: none;
    }
  </style>
  <script>
    document.addEventListener('DOMContentLoaded',()=>{
      document.addEventListener('dblclick', (e)=>{ e.preventDefault()}, { passive: false });
      const chicken=document.querySelector('#chickenpaint-parent');
      chicken.addEventListener('contextmenu', (e)=>{
        e.preventDefault();
        e.stopPropagation();
      }, { passive: false });
    });
  </script>
  <script src="{{$chicken_dir}}js/chickenpaint.min.js?{{$stime}}"></script>
  <link rel="stylesheet" type="text/css" href="{{$chicken_dir}}css/chickenpaint.css?{{$stime}}">
</head>
<body>
  <section id="cp">
    <div id="chickenpaint-parent"></div>
    <p></p>
    <script>
      document.addEventListener("DOMContentLoaded", function() {
        new ChickenPaint({
          uiElem: document.getElementById("chickenpaint-parent"),
          canvasWidth: {{$picw}},
          canvasHeight: {{$pich}},

        @if (isset($imgfile)) loadImageUrl: "{{$imgfile}}", @endif
        @if (isset($pchfile)) loadChibiFileUrl: "{{$pchfile}}", @endif
        saveUrl: "save.php?usercode={!!$usercode!!}",
        postUrl: "{{$self}}?mode={!!$mode!!}&stime={{$stime}}",
        exitUrl: "{{$self}}",

          allowDownload: true,
          resourcesRoot: "{{$chicken_dir}}",
          disableBootstrapAPI: true,
          fullScreenMode: "force"

        });
      })
    </script>
  </section>
</body>
</html>