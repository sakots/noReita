<!DOCTYPE html>
<html lang="ja">
	<head>
		<meta charset="utf-8">
		<title>{{$btitle}}</title>
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		@include('headcss')
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
					<form action="{{$self}}" method="post" enctype="multipart/form-data">
						<p>
							<label>幅：<input class="form" type="number" min="{{$pdefw}}" name="picw" value="{{$pdefw}}"></label>
							<label>高さ：<input class="form" type="number" min="{{$pdefh}}" name="pich" value="{{$pdefh}}"></label>
							<input type="hidden" name="mode" value="paint">
							<label for="tools">ツール</label>
								<select name="tools">
									<option value="neo">PaintBBS NEO</option>
									@if ($use_chicken)<option value="chicken">ChickenPaint</option> @endif
								</select>
							@if ($useanime)
							<label><input type="checkbox" value="true" name="anime" title="動画記録"@if ($defanime) checked @endif>アニメーション記録</label>
							@endif
							<input class="button" type="submit" value="お絵かき">
						</p>
					</form>
					<ul>
						<li>iPadやスマートフォンでも描けるお絵かき掲示板です。</li>
						<li>お絵かきできるサイズは幅300～{{$pmaxw}}px、高さ300～{{$pmaxh}}pxです。</li>
						@foreach ($addinfo as $info) @if (!empty($info[$loop->index]))
							<li>{!! $addinfo[$loop->index] !!}</li>
						@endif @endforeach
					</ul>
                </section>
				<hr>
				@if ($catalogmode == 'catalog')
					<p>カタログモード</p>
				@endif
				@if ($catalogmode == 'search')
				<p>検索モード -「{{$author}}」の作品 - {{$s_result}}件</p>
				@endif
				@if ($catalogmode == 'hashsearch')
				<p>本文検索 -「{{$tag}}」- {{$s_result}}件</p>
				@endif
				@if ($catalogmode == 'catalog')
				<hr>
				<section class="paging">
					<p>
						@if ($back === 0)
							<span class="se">[START]</span>
						@else
							<span class="se">&lt;&lt;<a href="{{$self}}?page={{$back}}">[BACK]</a></span>
						@endif
						@foreach ($paging as $pp)
							@if ($pp['p'] == $nowpage)
								<em class="thispage">[{{$pp['p']}}]</em>
							@else
								<a href="{{$self}}?page={{$pp['p']}}">[{{$pp['p']}}]</a>
							@endif
						@endforeach
						@if ($next == ($max_page + 1))
							<span class="se">[END]</span>
						@else
							<span class="se"><a href="{{$self}}?page={{$next}}">[NEXT]</a>&gt;&gt;</span>
						@endif
					</p>
				</section>
				@endif
			</div>
		</header>
		<main>
			<div class="thread" id="catalog">
				@if (!empty($oya))
					@foreach ($oya as $bbsline)
					<div>
						<div>
							@if ($bbsline['picfile'] == true)
							<p>
								<a href="{{$self}}?mode=res&amp;res={{$bbsline['tid']}}" title="{{$bbsline['sub']}} (by {{$bbsline['a_name']}})"><img src="{{$path}}{{$bbsline['picfile']}}" alt="{{$bbsline['sub']}} (by {{$bbsline['a_name']}})" loading="lazy"></a>
							</p>
							@else
							<p>
								<a href="{{$self}}?mode=res&amp;res={{$bbsline['tid']}}" title="{{$bbsline['sub']}} (by {{$bbsline['a_name']}})">{{$bbsline['sub']}} (by {{$bbsline['a_name']}})</a>
							</p>
							@endif
							<p>
								[{{$bbsline['tid']}}]
							</p>
						</div>
					</div>
					@endforeach
				@endif
				@if ($catalogmode == 'hashsearch')
					@if (!empty($ko))
						@foreach ($ko as $res)
							<div>
								<div>
									<p>
										<a href="{{$self}}?mode=res&amp;res={{$res['parent']}}" title="{{$res['sub']}} (by {{$res['a_name']}})">{!! mb_substr($res['com'], 0, 30)!!}</a>
									</p>
									<p>
										[{{$res['tid']}}]({{$res['tid']}})
									</p>
								</div>
							</div>
						@endforeach
					@endif
				@endif
			</div>
			<div>
				<section class="thread">
					@if ($catalogmode == 'catalog')
					<section class="paging">
						<p>
							@if ($back === 0)
								<span class="se">[START]</span>
							@else
								<span class="se">&lt;&lt;<a href="{{$self}}?page={{$back}}">[BACK]</a></span>
							@endif
							@foreach ($paging as $pp)
								@if ($pp['p'] == $nowpage)
									<em class="thispage">[{{$pp['p']}}]</em>
								@else
									<a href="{{$self}}?page={{$pp['p']}}">[{{$pp['p']}}]</a>
								@endif
							@endforeach
							@if ($next == ($max_page + 1))
								<span class="se">[END]</span>
							@else
								<span class="se"><a href="{{$self}}?page={{$next}}">[NEXT]</a>&gt;&gt;</span>
							@endif
						</p>
					</section>
					<hr>
					@endif
					<p>作者名/本文(ハッシュタグ)検索</p>
					<form class="search" method="GET" action="{{$self}}">
						<input type="hidden" name="mode" value="search">
						<label><input type="radio" name="bubun" value="bubun">部分一致</label>
						<label><input type="radio" name="bubun" value="kanzen">完全一致</label>
						<label><input type="radio" name="tag" value="tag">本文(ハッシュタグ)</label>
						<br>
						<input type="text" name="search" placeholder="検索" size="20">
						<input type="submit" value=" 検索 ">
					</form>
					<form class="delf" action="{{$self}}" method="post">
						<p>
							No <input class="form" type="number" min="1" name="delno" value="" autocomplete="off">
							Pass <input class="form" type="password" name="pwd" value="" autocomplete="current-password">
							<select class="form" name="mode">
								<option value="edit">編集</option>
								<option value="del">削除</option>
							</select>
							<input class="button" type="submit" value=" OK ">
							<label for="mystyle">Color</label>
							<span class="stylechanger">
								<select class="form" name="select" id="mystyle" onchange="SetCss(this);">
									<option value="reita/mono.min.css">REITA</option>
									<option value="red/mono.min.css">RED</option>
									<option value="main/mono.min.css">MONO</option>
									<option value="dark/mono.min.css">dark</option>
									<option value="deep/mono.min.css">deep</option>
									<option value="mayo/mono.min.css">MAYO</option>
									<option value="dev/mono.min.css">DEV</option>
									<option value="sql/mono.min.css">SQL</option>
									<option value="pop/mono.min.css">POP</option>
								</select>
							</span>
						</p>
						<script>
							colorIdx = GetCookie('_monored_colorIdx');
							document.getElementById("mystyle").selectedIndex = colorIdx;
						</script>
					</form>
				</section>
			</div>
		</main>
		<footer id="footer">
			@include('footercopy')
		</footer>
	</body>
</html>