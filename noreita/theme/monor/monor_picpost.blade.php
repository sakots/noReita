<!DOCTYPE html>
<html lang="ja">
	<head>
		<meta charset="utf-8">
		<title>{{$btitle}}</title>
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		@include('monor_headcss')
	</head>
	<body>
		<header>
			<h1><a href="{{$self}}">{{$btitle}}</a></h1>
			<div>
				<a href="{{$home}}" target="_top">[ホーム]</a>
				<a href="{{$self}}?mode=admin_in">[管理モード]</a>
			</div>
			<hr>
			<section>
				<p class="menu">
					<a href="{{$self}}">[トップ]</a>
				</p>
			</section>
			<section>
				<p class="sysmsg">{{$message}}</p>
			</section>
			<hr>
		</header>
		<main>
			<section>
				<div class="thread">
					<h2 class="oekaki">投稿フォーム</h2>
					<div class="tmpimg">
						@if (isset($temp))
						<div>
							@foreach ($temp as $tmp)
								<div class="imgs">
								@if (isset($tmp['src']) && isset($tmp['srcname']))
									<figure>
										<img src="{{$tmp['src']}}">
										<figcaption>{{$tmp['srcname']}}[{{$tmp['date']}}] 描画時間{{$tmp['utime']}}</figcaption>
									</figure>
								@endif
								</div>
							@endforeach
						</div>
						@else
						<p>Not OEKAKI image</p>
						@endif
					</div>
					@if (isset($temp))
					<form class="ppost postform" action="{{$self}}?mode=regist" method="post" enctype="multipart/form-data">
						<table>
							<tr>
								<td>name @if ($use_name) * @endif</td>
								<td><input type="text" name="name" size="28" autocomplete="username" @if ($use_name) required @endif maxlength="{{$max_name}}"></td>
							</tr>
							<tr>
								<td>mail</td>
								<td><input type="text" name="mail" size="28" value="" autocomplete="email" maxlength="{{$max_email}}"></td>
							</tr>
							<tr>
								<td>URL</td>
								<td><input type="text" name="url" size="28" value="" autocomplete="url" maxlength="{{$max_url}}"></td>
							</tr>
							<tr>
								<td>subject @if ($use_sub) * @endif</td>
								<td>
									<input type="text" name="sub" size="35" autocomplete="section-sub" @if ($use_sub) required @endif maxlength="{{$max_sub}}">
								</td>
							</tr>
							<tr>
								<td>comment @if ($use_com) * @endif </td>
								<td><textarea name="com" cols="48" rows="5" wrap="soft" onkeydown="if(event.ctrlKey&&event.keyCode==13){document.getElementById('submit').click();return false};" @if ($use_com) required @endif maxlength="{{$max_com}}"></textarea></td>
							</tr>
							@if (isset($temp))
							<tr>
								<td>imgs</td>
								<td>
									<select name="picfile">
									@foreach ($temp as $tmp)
										@if (isset($tmp['srcname'])) <option value="{{$tmp['srcname']}}">{{$tmp['srcname']}}</option>
										@endif
									@endforeach
								</select>
								</td>
							</tr>
							@endif
							<tr>
								<td>pass</td>
								<td>
									<input type="password" name="pwd" size="8" value="" autocomplete="current-password"  onkeydown="if(event.ctrlKey&&event.keyCode==13){document.getElementById('submit').click();return false};">
									(記事の編集削除用。英数字で)
								</td>
							</tr>
							@if ($use_nsfw === 1)
							<tr>
								<td>NSFW</td>
								<td>
									<input type="checkbox" name="nsfw" value="1" id="nsfw_button">
									<label for="nsfw_button">(画像をNSFWとして投稿できます。)</label>
								</td>
							</tr>
							@else
							<input type="hidden" name="nsfw" value="0">
							@endif
							<tr>
								<td>
									<input type="submit" id="submit" name="send" value="書き込む">
									<input type="hidden" name="parent" value="{{$parent}}">
									<input type="hidden" name="invz" value="0">
									<input type="hidden" name="img_w" value="0">
									<input type="hidden" name="img_h" value="0">
									<input type="hidden" name="exid" value="0">
									@if ($token != null)
									<input type="hidden" name="token" value="{{$token}}">
									@else
									<input type="hidden" name="token" value="">
									@endif
								</td>
								<td>
									(PCならCtrl + Enterでも書き込めます)
								</td>
							</tr>
						</table>
					</form>
					@endif
				</div>
			</section>
			<script src="loadcookie.js"></script>
			<script>
				l(); //LoadCookie
			</script>
		</main>
		<footer id="footer">
			@include('monor_footercopy')
		</footer>
	</body>
</html>
