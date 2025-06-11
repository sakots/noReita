<!DOCTYPE html>
<html lang="ja">

<head>
	<meta charset="utf-8">
	<title>{{$btitle}}</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	@include('nee-ex_headcss')
	@if ($tool == ('neo' || 'sneo'))
	<link rel="stylesheet" href="{{$neo_dir}}neo.css?{{$a_stime}}" type="text/css">
	<script src="{{$neo_dir}}neo.js?{{$a_stime}}" charset="utf-8"></script>
	@endif
	<script src="loadcookie.js"></script>
</head>

<body id="paintmode">
	<header>
		<div class="titlebox">
			<h1><a href="{{$self}}">{{$btitle}}</a></h1>
			<hr>
			<section>
				<p class="top menu">
					<a href="{{$self}}">[トップ]</a>
				</p>
			</section>
			<hr>
			<h2 class="oekaki">PCH MODE</h2>
			<hr>
		</div>
		<div class="th_head">
			<a href="{{$home}}" target="_top">[ホーム]</a>
			<a href="{{$self}}?mode=admin_in">[管理モード]</a>
		</div>
	</header>
	<main>
		<section id="appstage">
			<div class="app">
				@if ($tool == 'neo')
				<applet-dummy name="pch" code="pch.PCHViewer.class" archive="PCHViewer.jar,PaintBBS.jar" width="{{$w}}" height="{{$h}}" mayscript>
				@elseif ($tool == 'shi')
				<applet code="pch2.PCHViewer.class" archive="{{$shi_painter_dir}}PCHViewer.jar,{{$shi_painter_dir}}PaintBBS.jar" name="pch" width="{{$w}}" height="{{$h}}" codebase="./" mayscript>
					<param name="res.zip" value="{{$shi_painter_dir}}res.zip">
					<param name="tt.zip" value="{{$shi_painter_dir}}tt_def.zip">
					<param name="tt_size" value="31">
				@endif
					<param name="image_width" value="{{$picw}}">
					<param name="image_height" value="{{$pich}}">
					<param name="pch_file" value="{{$path}}{{$pchfile}}">
					<param name="speed" value="{{$speed}}">
					<param name="buffer_progress" value="false">
					<param name="buffer_canvas" value="false">
				@if ($tool == 'shi')
				</applet>
				<script>
					cheerpjInit();
				</script>
				@elseif ($tool == 'neo')
				</applet-dummy>
				@endif
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
		@include('nee-ex_footercopy')
	</footer>
</body>

</html>