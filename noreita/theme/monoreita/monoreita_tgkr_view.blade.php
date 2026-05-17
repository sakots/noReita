<!DOCTYPE html>
<html lang="ja">

<head>
	<meta charset="utf-8">
	<title>{{$board_title}}</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	@include('components.monoreita_headCss')
	<script src="{{$tegaki_dir}}tegaki.js?{{$a_stime}}"></script>
  <link rel="stylesheet" href="{{$tegaki_dir}}tegaki.css?{{$a_stime}}" type="text/css">
	<script src="loadcookie.js"></script>
  <style>
    :not(input){
      -moz-user-select: none;
      -webkit-user-select: none;
      -ms-user-select: none;
      user-select: none;
    }
    #tegaki-cursor-layer{
      pointer-events:none;
      touch-action: auto;
    }
</style>
</head>

<body>
  <script>
    Tegaki.open({
      replayMode: true,
      replayURL: '{{$path}}{{$pchfile}}' // Store replay files preferably with the .tgkr extension
    });
  </script>
</body>

</html>