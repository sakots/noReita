<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{$board_title}}</title>
  <script>
    let resto = "{{$resto}}";
  </script>
  @if ($tool == 'chicken')
  <style>
    body { overscroll-behavior-x: none !important; }
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
  @endif
  @if ($tool == 'klecks')
  <style>
    :not(input) {
      -moz-user-select: none;
      -webkit-user-select: none;
      -ms-user-select: none;
      user-select: none;
    }
  </style>
  <script>
    //ブラウザデフォルトのキー操作をキャンセル
    document.addEventListener("keydown", (e) => {
      const keys = ["+", ";", "=", "-", "s", "h", "r", "o"];
      if ((e.ctrlKey || e.metaKey) && keys.includes(e.key.toLowerCase())) {
        // console.log("e.key",e.key);
        e.preventDefault();
      }
    });
    //ブラウザデフォルトのコンテキストメニューをキャンセル
    document.addEventListener("contextmenu", (e) => {
      e.preventDefault();
    });
  </script>
  @endif
  @if ($tool == 'tegaki')
  <script src="{{$tegaki_dir}}tegaki.js?{{$stime}}"></script>
  <link rel="stylesheet" href="{{$tegaki_dir}}tegaki.css?{{$stime}}">
  <style>
    :not(input) {
      -moz-user-select: none;
      -webkit-user-select: none;
      -ms-user-select: none;
      user-select: none;
    }
  </style>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      document.addEventListener('dblclick', (e) => {
        e.preventDefault()
      }, {
        passive: false
      });
    });
  </script>
  @endif
  @if ($tool == 'axnos')
		@include('components.axnos')
  @endif
</head>
<body>
  @if ($tool == 'chicken')
		@include('components.chicken')
  @endif
  @if ($tool == 'klecks')
    @include('components.klecks')
  @endif
  @if ($tool == 'tegaki')
		@include('components.tegaki')
  @endif
  @if ($tool == 'axnos')
  <div id="axnospaint_body"></div>
  @endif
</body>
</html>