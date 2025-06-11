<!DOCTYPE html>
<html lang="ja">

<head>
	<meta charset="utf-8">
	<title>{{$btitle}}</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="stylesheet" href="theme/{{$themedir}}/luminous/luminous-basic.min.css">
	@include('monoreita_headcss')
	<script src="theme/{{$themedir}}/js/sodane.js"></script>
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
					<a href="{{$self}}?mode=catalog">[カタログ]</a>
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
						<label>幅：<input class="form" type="number" min="300" max="{{$pmaxw}}" name="picw" value="{{$pdefw}}" required></label>
						<label>高さ：<input class="form" type="number" min="300" max="{{$pmaxh}}" name="pich" value="{{$pdefh}}" required></label>
						<input type="hidden" name="mode" value="paint">
						<label for="tools">ツール</label>
						<select name="tools" id="tools">
							<option value="neo">PaintBBS NEO</option>
							@if ($use_shi_painter)<option value="shi">しぃペインター</option> @endif
							@if ($use_chicken)<option value="chicken">ChickenPaint</option> @endif
						</select>
						<label for="palettes">パレット</label>
						@if ($select_palettes)
						<select name="palettes" id="palettes">
							@foreach ($pallets_dat as $palette)
							<option value="{{$pallets_dat[$loop->index][1]}}" id="{{$loop->index}}">{{$pallets_dat[$loop->index][0]}}</option>
							@endforeach
						</select>
						@else
						<select name="palettes" id="palettes">
							<option value="neo" id="0">標準</option>
						</select>
						@endif
						@if ($useanime)
						<label><input type="checkbox" value="true" name="anime" title="動画記録" @if ($defanime) checked @endif>アニメーション記録</label>
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
		</div>
	</header>
	<main>
		<div>
			@if (isset($will_delete_count) && $will_delete_count > 0)
			<div class="thread">
				<h3 class="oyat">⚠️ 注意</h3>
				<p class="limit">
					現在 {{$th_cnt}} スレッド中、{{$will_delete_count}} スレッドがそろそろ削除されます。<br>
					({{$log_limit}}%を超えた古いスレッド)
				</p>
			</div>
			@endif
			@if (!empty($oya))
			@foreach ($oya as $bbsline)
			<section class="thread @if ($bbsline['will_delete']) will-delete-thread @endif">
				<h3 class="oyat">
					[{{$bbsline['tid']}}] {{$bbsline['sub']}}
					@if ($bbsline['will_delete'])
					<span class="will-delete" title="このスレッドはそろそろ削除されます">⚠️ このスレッドはそろそろ削除されます</span>
					@endif
				</h3>
				<section>
					<h4 class="oya">
						<span class="oyaname"><a href="{{$self}}?mode=search&amp;bubun=kanzen&amp;search={{$bbsline['a_name']}}">{{$bbsline['a_name']}}</a></span>
						@if ($bbsline['admins'] == 1)
						<svg viewBox="0 0 640 512">
							<use href="./theme/{{$themedir}}/icons/user-check.svg#admin_badge">
						</svg>
						@endif
						@if ($bbsline['modified'] == $bbsline['created'])
						{{$bbsline['modified']}}
						@else
						{{$bbsline['created']}} {{$updatemark}} {{$bbsline['modified']}}
						@endif
						@if ($bbsline['mail'])
						<span class="mail"><a href="mailto:{{$bbsline['mail']}}">[mail]</a></span>
						@endif
						@if ($bbsline['a_url'])
						<span class="url"><a href="{{$bbsline['a_url']}}" target="_blank" rel="nofollow noopener noreferrer">[URL]</a></span>
						@endif
						@if ($dispid)
						<span class="id">ID：{{$bbsline['id']}}</span>
						@endif
						<span class="sodane"><a href="{{$self}}?mode=sodane&amp;resto={{$bbsline['tid']}}">{{$sodane}}
								@if ($bbsline['exid'] != 0)
								x{{$bbsline['exid']}}
								@else
								+
								@endif
							</a></span>
					</h4>
					@if ($bbsline['picfile'])
					@if ($dptime)
					<h5>
						{{$bbsline['tool']}} ({{$bbsline['img_w']}}x{{$bbsline['img_h']}})
						@if ($bbsline['psec'] != null)
						描画時間：{{$bbsline['utime']}}
						@endif
						@if ($bbsline['ext01'] == 1)
						★NSFW
						@endif
					</h5>
					@endif
					<h5><a target="_blank" href="{{$path}}{{$bbsline['picfile']}}">{{$bbsline['picfile']}}</a>
						@if ($bbsline['pchfile'] && $bbsline['tool'] !== "Chicken Paint")
						<a href="{{$self}}?mode=anime&amp;pch={{$bbsline['pchfile']}}" target="_blank">●動画</a>
						@endif
						@if ($use_continue)
						<a href="{{$self}}?mode=continue&amp;no={{$bbsline['picfile']}}">●続きを描く</a>
						@endif
					</h5>
					@if ($bbsline['ext01'] == 1)
					<a class="luminous" href="{{$path}}{{$bbsline['picfile']}}"><span class="nsfw"><img src="{{$path}}{{$bbsline['picfile']}}" alt="{{$bbsline['picfile']}}" loading="lazy" class="image"></span></a>
					@else
					<a class="luminous" href="{{$path}}{{$bbsline['picfile']}}"><img src="{{$path}}{{$bbsline['picfile']}}" alt="{{$bbsline['picfile']}}" loading="lazy" class="image"></a>
					@endif
					@endif
					<p class="comment oya">{!! $bbsline['com'] !!}</p>
					@if ($bbsline['rflag'])
					<div class="res">
						<p class="limit">
							レス{{$bbsline['res_d_su']}}件省略。すべて見るには
							<a href="{{$self}}?mode=res&amp;res={{$bbsline['tid']}}">
								@if ($elapsed_time === 0 || $nowtime - $bbsline['past'] < $elapsed_time) 返信 @else すべて見る @endif </a>
									を押してください。
						</p>
					</div>
					@endif
					@if (!empty($bbsline['res']))
					@foreach ($bbsline['res'] as $res)
					@if ($res['resno'] <= $bbsline['res_d_su']) @else 
					<section class="res">
						<h3>[{{$res['tid']}}] {{$res['sub']}}</h3>
						<h4>
							名前：<span class="resname">{{$res['a_name']}}
								@if ($res['admins'] == 1)
								<svg viewBox="0 0 640 512">
									<use href="./theme/{{$themedir}}/icons/user-check.svg#admin_badge">
								</svg>
								@endif
							</span>：
							@if ($res['modified'] == $res['created'])
							{{$res['modified']}}
							@else
							{{$res['created']}} {{$updatemark}} {{$res['modified']}}
							@endif
							@if ($res['mail'])
							<span class="mail"><a href="mailto:{{$res['mail']}}">[mail]</a></span>
							@endif
							@if ($res['a_url'])
							<span class="url"><a href="{{$res['a_url']}}" target="_blank" rel="nofollow noopener noreferrer">[URL]</a></span>
							@endif
							@if ($dispid)
							<span class="id">ID：{{$res['id']}}</span>
							@endif
							<span class="sodane"><a href="{{$self}}?mode=sodane&amp;resto={{$res['tid']}}">{{$sodane}}
								@if ($res['exid'] != 0)
								x{{$res['exid']}}
								@else
								+
								@endif
							</a></span>
						</h4>
						<p class="comment">{!! $res['com'] !!}</p>
					</section>
					@endif
					@endforeach
					@endif
					<div class="thfoot">
						@if ($share_button)
						@if ($use_misskey_note)
						<span class="button"><a href="{{$self}}?mode=before_misskey_note&amp;no={{$bbsline['tid']}}">
							<svg>
								<use href="./theme/{{$themedir}}/icons/misskey.svg#misskey"></use>
							</svg> Misskeyにノート</a>
						</span>
						@endif
						@if ($switch_sns)
						<span class="button"><a href="{{$self}}?mode=set_share_server&amp;encoded_t={{$bbsline['encoded_t']}}&amp;encoded_u={{$bbsline['encoded_u']}}" onClick="open_sns_server_window(event,600,600)">
							<svg viewBox="0 0 512 512">
								<use href="./theme/{{$themedir}}/icons/share.svg#share">
							</svg> SNSで共有する</a>
						</span>
						@else
						<span class="button"><a href="https://x.com/intent/tweet?&amp;text=%5B{{$bbsline['tid']}}%5D%20{{$bbsline['sub']}}%20by%20{{$bbsline['a_name']}}%20-%20{{$btitle}}&amp;url={{$base}}{{$self}}?mode=res%26res={{$bbsline['tid']}}" target="_blank">
							<svg viewBox="0 0 512 512">
								<use href="./theme/{{$themedir}}/icons/twitter.svg#twitter">
							</svg> tweet</a>
						</span>
						@endif
						@endif
						@if ($elapsed_time === 0 || $nowtime - $bbsline['past'] < $elapsed_time)
						<span class="button"><a href="{{$self}}?mode=res&amp;res={{$bbsline['tid']}}"><svg viewBox="0 0 512 512">
							<use href="./theme/{{$themedir}}/icons/rep.svg#rep"></svg> 返信</a>
						</span>
						@else
						このスレは古いので返信できません…
						@endif
						<a href="#header">[↑]</a>
						<hr>
					</div>
				</section>
			</section>
			@endforeach
			@endif
		</div>
		<div>
			<section class="thread">
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
						No <input class="form" type="number" min="1" name="delno" value="" autocomplete="off" required>
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
				</form>
				<script>
					colorIdx = GetCookie('_monoreita_colorIdx');
					document.getElementById("mystyle").selectedIndex = colorIdx;
				</script>
			</section>
		</div>
		<script src="loadcookie.js"></script>
		<script>
			l(); //LoadCookie
		</script>
		<!-- Luminous -->
		<script src="theme/{{$themedir}}/luminous/luminous.min.js"></script>
		<script>
			new LuminousGallery(document.querySelectorAll('.luminous'), {closeTrigger: "click", closeWithEscape: true});
		</script>
		<script>
      //shareするSNSのserver一覧を開く
      let snsWindow = null; // グローバル変数としてウィンドウオブジェクトを保存する

      function open_sns_server_window(event, width = 600, height = 600) {
        event.preventDefault(); // デフォルトのリンクの挙動を中断

        // 幅と高さが数値であることを確認
        // 幅と高さが正の値であることを確認
        if (isNaN(width) || width <= 350 || isNaN(height) || height <= 400) {
          width = 350; // デフォルト値
          height = 400; // デフォルト値
        }
        let url = event.currentTarget.href;
        let windowFeatures = "width=" + width + ",height=" + height; // ウィンドウのサイズを指定

        if (snsWindow && !snsWindow.closed) {
          snsWindow.focus(); // 既に開かれているウィンドウがあればフォーカスする
        } else {
          snsWindow = window.open(url, "_blank", windowFeatures); // 新しいウィンドウを開く
        }
        // ウィンドウがフォーカスを失った時の処理
        snsWindow.addEventListener("blur", function () {
          if (snsWindow.location.href === url) {
            snsWindow.close(); // URLが変更されていない場合はウィンドウを閉じる
          }
        });
      }
    </script>
	</main>
	<footer id="footer">
		@include('monoreita_footercopy')
	</footer>
</body>

</html>