<!DOCTYPE html>
<html lang="ja">
	<head>
		<meta charset="utf-8">
		<title>{{$btitle}}</title>
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		@include('monoreita_headcss')
		@if ($tool == 'neo')
		<link rel="stylesheet" href="{{$neo_dir}}neo.css?{{$stime}}" type="text/css">
		<script src="{{$neo_dir}}neo.js?{{$stime}}" charset="utf-8"></script>
		<script src="theme/{{$themedir}}/fix_neo/fix.js?{{$stime}}" charset="utf-8"></script>
		<!-- アプレットフィット -->
		<script>
			const originalWidth = {{$w}};
			const originalHeight = {{$h}};
		</script>
		<script src="theme/{{$themedir}}/js/appFit.js" charset="utf-8"></script>
		<!-- アプレットフィットここまで -->
		@endif
		@if ($tool == 'shi')
		<!-- CheerpJ -->
		<script src="{{$cheerpj_url}}"></script>
		@endif
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
			<h2 class="oekaki">OEKAKI MODE</h2>
			<hr>
		</header>
		<main>
			@if ($tool == 'neo' || $tool == 'shi')
			<!-- 動的パレットスクリプト -->
			<script>
				// パレットデータの初期化
				var Palettes = new Array();
				@if ($palettes)
					{!!$palettes!!}
				@endif
			</script>
			<script src="theme/{{$themedir}}/js/dynamicPalette.js?{{$stime}}" charset="utf-8"></script>
			<script>
				// パレットデータをマネージャーに設定
				document.addEventListener('DOMContentLoaded', function() {
					// 少し遅延させて確実にマネージャーが初期化されるのを待つ
					setTimeout(function() {
						if (window.dynamicPaletteManager && window.Palettes) {
							window.dynamicPaletteManager.setPaletteData(window.Palettes);
							// 初期化後にパレットリストの色を設定
							if (window.dynamicPaletteManager.DynamicColor) {
								window.dynamicPaletteManager.PaletteListSetColor();
							}
						}
					}, 100);
				});
			</script>
			<!-- 動的パレットスクリプトここまで -->
			<section id="appstage">
				<div class="app" id="apps">
					@if ($tool == 'neo')
					<applet-dummy code="pbbs.PaintBBS.class" archive="./PaintBBS.jar" name="paintbbs" width="{{$w}}" height="{{$h}}" mayscript>
					@elseif ($tool == 'shi')
					<applet code="c.ShiPainter.class" archive="./{{$shi_painter_dir}}spainter_all.jar" name="paintbbs" width="{{$w}}" height="{{$h}}" mayscript>
					<param name=dir_resource value="./{{$shi_painter_dir}}">
					<param name="tt.zip" value="tt_def.zip">
					<param name="res.zip" value="res.zip">
					@endif
					<param name="image_width" value="{{$picw}}">
					<param name="image_height" value="{{$pich}}">
					<param name="undo" value="{{$undo}}">
					<param name="undo_in_mg" value="{{$undo_in_mg}}">
					@if ($tool == 'neo')
					<param name="url_save" value="saveneo.php">
					@elseif ($tool == 'shi')
					<param name="url_save" value="picpost.php">
					@endif
					<param name="url_exit" value="{{$self}}?mode={{$mode}}&amp;stime={{$stime}}">
					@if (isset($imgfile))<param name="image_canvas" value="{{$imgfile}}">@endif
					@if (isset($pchfile))<param name="pch_file" value="{{$pchfile}}">@endif
					<param name="poo" value="false">
					<param name="send_advance" value="true">
					<param name="send_header" value="usercode={{$usercode}}">
					<param name="thumbnail_width" value="100%">
					<param name="thumbnail_height" value="100%">
					<param name="tool_advance" value="true">
					@if ($anime)<param name="thumbnail_type" value="animation">@endif
					@if (isset($security))
						@if (isset($security_click))<param name="security_click" value="{{$security_click}}">@endif
						@if (isset($security_timer))<param name="security_timer" value="{{$security_timer}}">@endif
						<param name="security_url" value="{{$security_url}}">
						<param name="security_post" value="false">
					@endif
					@if ($tool == 'neo')
					<param name="neo_confirm_unload" value="true">
					<param name="neo_show_right_button" value="true">
					<param name="neo_send_with_formdata" value="true">
					@endif
					@if ($tool == 'shi')
					</applet>
					<script>
      			cheerpjInit();
    			</script>
					@elseif ($tool == 'neo')
					</applet-dummy>
					@endif
				</div>
				<div class="palette" id="dyntools">
					<form name="Palette">
						@if ($tool == 'neo')
						<fieldset id="fit_exp">
							<legend>FIT!</legend>
							<input class="button" type="button" value="← FIT →" onclick="appfit(0)">
						</fieldset>
						<fieldset id="fit_comp" style="display: none;">
							<legend>FIT!</legend>
							<input class="button" type="button" value="→ FIT ←" onclick="appfit(1)">
						</fieldset>
						<fieldset>
							<legend>TOOL</legend>
							<input class="button" type="button" value="左" onclick="Neo.setToolSide(true)">
							<input class="button" type="button" value="右" onclick="Neo.setToolSide(false)">
						</fieldset>
						@endif
						<fieldset>
							<legend>PALETTE</legend>
							<select class="form palette_set" name="select" size="13" onChange="setPalette()" id="palnames">
								<option>一時パレット</option>
								@if ($dynp)
									{!!$dynp!!}
								@endif
							</select><br>
							<input class="button" type="button" value="一時保存" onClick="PaletteSave()"><br>
							<input class="button" type="button" value="作成" onClick="PaletteNew()">
							<input class="button" type="button" value="変更" onClick="PaletteRenew()">
							<input class="button" type="button" value="削除" onClick="PaletteDel()"><br>
							<input class="button" type="button" value="明＋" onClick="P_Effect(10)">
							<input class="button" type="button" value="明－" onClick="P_Effect(-10)">
							<input class="button" type="button" value="反転" onClick="P_Effect(255)">
						</fieldset>
						<fieldset>
							<legend>MATRIX</legend>
							<form>
							<select class="form" name="m_m">
								<option value="0">全体</option>
								<option value="1">現在</option>
								<option value="2">追加</option>
							</select>
							<input type="button" class="button" name="m_g" value="GET" onclick="PaletteMatrixGet()">
							<input type="button" class="button" name="m_h" value="SET" onclick="PaletteMatrixSet()">
							<input type="button" class="button" name="1" value=" ? " onclick="PaletteMatrixHelp()"><br>
							<textarea class="form" name="setr" rows="1" cols="13" onmouseover="this.select()"></textarea>
							</form>
						</fieldset>
						<fieldset>
							<legend>GRADATION</legend>
							<form name="grad">
								<input type="checkbox" name="view" onclick="showHideLayer()">
								<input type="button" class="button" value=" OK " onclick="ChangeGrad()">
								<input type="color">
								<br>
								<select class="form" name="p_st" onchange="GetPalette()">
									<option>1</option>
									<option>2</option>
									<option>3</option>
									<option>4</option>
									<option>5</option>
									<option>6</option>
									<option>7</option>
									<option>8</option>
									<option>9</option>
									<option>10</option>
									<option>11</option>
									<option>12</option>
									<option>13</option>
									<option>14</option>
								</select>
								<input class="form "type="text" name="pst" size="8" onkeypress="Change_()" onchange="Change_()"><br>
								<select class="form" name="p_ed" onchange="GetPalette()">
									<option>1</option>
									<option>2</option>
									<option>3</option>
									<option>4</option>
									<option>5</option>
									<option>6</option>
									<option>7</option>
									<option>8</option>
									<option>9</option>
									<option>10</option>
									<option>11</option>
									<option selected>12</option>
									<option>13</option>
									<option>14</option>
								</select>
								<input class="form" type="text" name="ped" size="8" onkeypress="Change_()" onchange="Change_()"><div id="psft" style="position:absolute;width:100px;height:30px;z-index:1;left:5px;top:10px;"></div>
							</form>
						</fieldset>
						<p class="c">DynamicPalette &copy;NoraNeko</p>
					</form>
				</div>
			</section>
			<section>
				<div class="thread">
					<hr>
					<div class="timeid">
						<form class="watch" action="index.html" name="watch">
							<p>
								PaintTime :
								<input type="text" size="24" name="count">
							</p>
							<script type="text/javascript">
								timerID = 10;
								stime = new Date();
								function SetTimeCount() {
									now = new Date();
									s = Math.floor((now.getTime() - stime.getTime())/1000);
									disp = '';
									if(s >= 86400){
										d = Math.floor(s/86400);
										disp += d+"day ";
										s -= d*86400;
									}
									if(s >= 3600){
										h = Math.floor(s/3600);
										disp += h+"hr ";
										s -= h*3600;
									}
									if(s >= 60){
										m = Math.floor(s/60);
										disp += m+"min ";
										s -= m*60;
									}
									document.watch.count.value = disp+s+"sec";
									clearTimeout(timerID);
									timerID = setTimeout('SetTimeCount()',250);
								}
								SetTimeCount();
								if (window.dynamicPaletteManager && window.dynamicPaletteManager.DynamicColor) {
									window.dynamicPaletteManager.PaletteListSetColor();
								}
							</script>
						</form>
					<hr>
					</div>
				</div>
			</section>
			<section>
				<div class="thread siihelp">
					<p>
						ミスしてページを変えたりウインドウを消してしまったりした場合は落ちついて同じキャンバスの幅で編集ページを開きなおしてみて下さい。大抵は残っています。
					</p>
					<h2>基本の動作(恐らくこれだけは覚えておいた方が良い機能)</h2>
					<section>
						<h3>基本</h3>
						<section>
							<p>
								PaintBBSでは右クリック、ctrl+クリック、alt+クリックは同じ動作をします。<br>
								基本的に操作は一回のクリックか右クリックで動作が完了します。(ベジエやコピー使用時を除く)
							</p>
						</section>
						<h3>ツールバー</h3>
						<section>
							<p>
								ツールバーの殆どのボタンは複数回クリックして機能を切り替える事が出来ます。<br>
								右クリックで逆周り。その他パレットの色、マスクの色、一字保存ツールに現在の状態を登録、レイヤ表示非表示切り替え等全て右クリックです。<br>
								逆にクリックでパレットの色と一時保存ツールに保存しておいた状態を取り出せます。
							</p>
						</section>
						<h3>キャンバス部分</h3>
						<p>
							右クリックで色をスポイトします。<br>
							ベジエやコピー等の処理の途中で右クリックを押すとリセットします。
						</p>
					</section>
					<h2>特殊動作(使う必要は無いが慣れれば便利な機能)</h2>
					<section>
						<h3>ツールバー</h3>
						<section>
							<p>
								値を変更するバーはドラッグ時バーの外に出した場合変化が緩やかになりますのでそれを利用して細かく変更する事が出来ます。パレットはShift+クリックで色をデフォルトの状態に戻します。
							</p>
						</section>
						<h3>キーボードのショートカット</h3>
						<section>
							<ul>
								<li>+で拡大-で縮小。</li>
								<li>Ctrl+ZかCtrl+Uで元に戻す、Ctrl+Alt+ZかCtrl+Yでやり直し。</li>
								<li>Escでコピーやベジエのリセット。（右クリックでも同じ） </li>
								<li>スペースキーを押しながらキャンバスをドラッグするとスクロールの自由移動。</li>
								<li>Ctrl+Alt+ドラッグで線の幅を変更。</li>
							</ul>
						</section>
						<h3>コピーツールの特殊な利用方法</h3>
						<section>
							<p>
								レイヤー間の移動は現時点ではコピーとレイヤー結合のみです。コピーでの移動方法は、まず移動したいレイヤ上の長方形を選択後、移動させたいレイヤを選択後に通常のコピーの作業を続けます。そうする事によりレイヤ間の移動が可能になります。
							</p>
						</section>
						<h2>ツールバーのボタンと特殊な機能の簡単な説明</h2>
						<section>
							<dl>
								<dt>ペン先(通常ペン,水彩ペン,テキスト)</dt>
								<dd>
									メインのフリーライン系のペンとテキスト
								</dd>
								<dt>ペン先2(トーン,ぼかし,他)</dt>
								<dd>
									特殊な効果を出すフリーライン系のペン
								</dd>
								<dt>図形(円や長方形)</dt>
								<dd>
									長方形や円等の図形
								</dd>
								<dt>特殊(コピーやレイヤー結合,反転等)</dt>
								<dd>
									コピーは一度選択後、ドラッグして移動、コピーさせるツールです。
								</dd>
								<dt>マスクモード指定(通常,マスク,逆マスク）</dt>
								<dd>
									マスクで登録されている色を描写不可にします。逆マスクはその逆。<br>
									通常でマスク無し。また右クリックでマスクカラーの変更が可能。
								</dd>
								<dt>消しゴム(消しペン,消し四角,全消し)</dt>
								<dd>
									透過レイヤー上を白で塗り潰した場合、下のレイヤーが見えなくなりますので上位レイヤーの線を消す時にはこのツールで消す様にして下さい。<br>
									全消しはすべてを透過ピクセル化させるツールです。<br>
									全けしを利用する場合はこのツールを選択後キャンバスをクリックでOK。
								</dd>
								<dt>描写方法の指定。(手書き,直線,ベジエ曲線)</dt>
								<dd>
									ペン先,描写機能指定ではありません。<br>
									また適用されるのはフリーライン系のツールのみです。
								</dd>
								<dt>カラーパレット郡</dt>
								<dd>
									クリックで色取得。右クリックで色の登録。Shift+クリックでデフォルト値。
								</dd>
								<dt>RGBバーとalphaバー</dt>
								<dd>
									細かい色の変更と透過度の変更。Rは赤,Gは緑,Bは青,Aは透過度を指します。<br>
									トーンはAlphaバーで値を変更する事で密度の変更が可能です。
								</dd>
								<dt>線幅変更ツール</dt>
								<dd>
									水彩ペンを選択時に線幅を変更した時、デフォルトの値がalpha値に代入されます。
								</dd>
								<dt>線一時保存ツール</dt>
								<dd>
									クリックでデータ取得。右クリックでデータの登録。(マスク値は登録しません)
								</dd>
								<dt>レイヤーツール</dt>
								<dd>
									PaintBBSは透明なキャンバスを二枚重ねたような構造になっています。<br>
									つまり主線を上に書き、色を下に描くと言う事も可能になるツールです。<br>
									通常レイヤーと言う種類の物ですので鉛筆で描いたような線もキッチリ透過します。<br>
									クリックでレイヤー入れ替え。右クリックで選択されているレイヤの表示、非表示切り替え。
								</dd>
							</dl>
						</section>
						<h2>投稿に関して</h2>
						<section>
							<p>
								絵が完成したら投稿ボタンで投稿します。絵の投稿が成功した場合は指定されたURLへジャンプします。失敗した場合は失敗したと報告するのみでどこにも飛びません。単に重かっただけである場合少し間を置いた後、再度投稿を試みて下さい。この際二重で投稿される場合があるかもしれませんが、それはWebサーバーかPHP側の処理ですのであしからず。
							</p>
						</section>
					</section>
				</div>
			</section>
			@endif
		</main>
		<footer id="footer">
			@include('monoreita_footercopy')
		</footer>
	</body>
</html>