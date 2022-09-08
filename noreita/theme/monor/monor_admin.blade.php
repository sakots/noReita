<!DOCTYPE html>
<html lang="ja">

<head>
	<meta charset="utf-8">
	<title>{{$btitle}}</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	@include('monor_headcss')
</head>

<body>
	<header id="header">
		<h1><a href="{{$self}}">{{$btitle}}</a></h1>
		<div>
			<a href="{{$home}}" target="_top">[ホーム]</a>
			<a href="{{$self}}?mode=admin_in">[管理モード]</a>
		</div>
		<hr>
		<div>
			<section>
				<p class="top menu">
					<a href="{{$self}}">[トップ]</a>
					<a href="{{$self}}?mode=catalog">[カタログ]</a>
					<a href="{{$self}}">[通常モード]</a>
					<a href="{{$self}}?mode=pictmp">[投稿途中の絵]</a>
					<a href="#footer">[↓]</a>
				</p>
			</section>
			<section>
				<p class="sysmsg">{{$message}}</p>
			</section>
		</div>
		<hr>
		<div>
			<section class="epost">
				<p>ADMIN MODE</p>
			</section>
			<hr>
		</div>
	</header>
	<main>
		<div>
			<div class="thread">
				<section class="delf">
					<form action="{{$self}}?mode=del" method="post">
						<p>
							<select name="delt">
								<option value="1">レス</option>
								<option value="0">親</option>
							</select>
							No <input class="form" type="text" name="delno" value="" autocomplete="section-no">
							Pass <input class="form" type="password" name="pwd" value="" autocomplete="new-password">
							<input class="button" type="submit" value=" 削除 ">
							<input type="hidden" name="admindel" value="admindel">
						</p>
					</form>
				</section>
			</div>
			<section class="thread">
				<table class="delfo">
					<tr>
						<th>ID</th>
						<th>name</th>
						<th>date</th>
						<th>sub</th>
						<th>pic</th>
						<th>com</th>
						<th>host</th>
						<th>invz</th>
					</tr>
					@if (!empty($oya))
					@foreach ($oya as $bbsline)
					<tr>
						<td>{{$bbsline['tid']}}</td>
						<td>{{$bbsline['a_name']}}</td>
						<td>{{$bbsline['modified']}}</td>
						<td>{!! mb_substr($bbsline['sub'], 0, 6)!!}</td>
						<td>
							@if ($bbsline['picfile'] == true)
							<a href="{{$path}}{{$bbsline['picfile']}}" target="_brank">{{$bbsline['picfile']}}</a>
							@endif
						</td>
						<td>{!! mb_substr($bbsline['com'], 0, 10)!!}</td>
						<td>{{$bbsline['host']}}</td>
						<td>@if ($bbsline['invz']) invz @endif</td>
					</tr>
					@if (!empty($ko))
					@foreach ($ko as $res)
					@if ($bbsline['tid'] == $res['parent'])
					<tr>
						<td>└{{$res['tid']}}</td>
						<td>{{$res['a_name']}}</td>
						<td>{{$res['modified']}}</td>
						<td>{!! mb_substr($res['sub'], 0, 6)!!}</td>
						<td>{{$res['picfile']}}</td>
						<td>{!! mb_substr($res['com'], 0, 10)!!}</td>
						<td>{{$res['host']}}</td>
						<td>@if ($res['invz']) invz @endif</td>
					</tr>
					@endif
					@endforeach
					@endif
					@endforeach
					@endif
				</table>
			</section>
		</div>
		<script src="loadcookie.js"></script>
		<script>
			l(); //LoadCookie
		</script>
		<div class="thread">
			<section class="delf">
				<form action="{{$self}}?mode=del" method="post">
					<p>
						<select name="delt">
							<option value="1">レス</option>
							<option value="0">親</option>
						</select>
						No <input class="form" type="text" name="delno" value="" autocomplete="section-no">
						Pass <input class="form" type="password" name="pwd" value="" autocomplete="new-password">
						<input class="button" type="submit" value=" 削除 ">
						<input type="hidden" name="admindel" value="admindel">
					</p>
				</form>
			</section>
		</div>
	</main>
	<footer id="footer">
		@include('monor_footercopy')
	</footer>
</body>

</html>