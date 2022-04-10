<!DOCTYPE html>
<html lang="ja">
	<head>
		<meta charset="utf-8">
		<title>{{$btitle}}</title>
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		@include('monoreita_headcss')
		@if ($tool == ('neo' || 'sneo'))
		<link rel="stylesheet" href="{{$neo_dir}}neo.css?{{$a_stime}}" type="text/css">
		<script src="{{$neo_dir}}neo.js?{{$a_stime}}" charset="utf-8"></script>
		@endif
		<script src="loadcookie.js"></script>
	</head>
	<body id="paintmode">
		<header>
			<h1><a href="{{$self}}">{{$btitle}}</a></h1>
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
					<applet-dummy name="pch" code="pch.PCHViewer.class" archive="PCHViewer.jar,PaintBBS.jar" width="{{$w}}" height="{{$h}}" mayscript>
					<param name="image_width" value="{{$picw}}">
					<param name="image_height" value="{{$pich}}">
					<param name="pch_file" value="{{$path}}{{$pchfile}}">
					<param name="speed" value="{{$speed}}">
					<param name="buffer_progress" value="false">
					<param name="buffer_canvas" value="false">
					</applet-dummy>
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
			@include('monoreita_footercopy')
		</footer>
	</body>
</html>
