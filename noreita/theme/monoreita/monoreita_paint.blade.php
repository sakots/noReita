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
			function appfit(f) {
				const d = document;
				const client = d.compatMode && d.compatMode != "BackCompat" ? d.documentElement : d.body;
				const chei = client.clientHeight - 10;
				const neo = d.getElementById("NEO");
				const target = d.getElementById("pageView");
				if (f == 0) { //ひろげる
					const cwid = d.getElementById("appstage").scrollWidth - 360;
					if (cwid > target.clientWidth) { target.style.width = cwid + "px"; }
					if (chei > target.clientHeight) { target.style.height = chei + "px"; }
					document.getElementById("fit_exp").style.display="none";
					document.getElementById("fit_comp").style.display="block";
				} else if (f == 1) { //もどす
				target.style.width = {{$w}} + "px";
				target.style.height = {{$h}} + "px";
				document.getElementById("fit_exp").style.display="block";
				document.getElementById("fit_comp").style.display="none";
				}
			//ツールの縦の位置をキャンバス中央に修正
			d.getElementById("toolsWrapper").style.top = (target.clientHeight - d.getElementById("toolsWrapper").clientHeight)/2 + "px";
			//ズームをリセット
			Neo.painter.setZoom(1);
			Neo.resizeCanvas();
			Neo.painter.updateDestCanvas();
			}
		</script>
		@endif
		@if ($tool == 'chicken')
		<script src="{{$chicken_dir}}js/chickenpaint.min.js?{{$stime}}"></script>
		<script src="theme/{{$themedir}}/fix_chiken/fix.js?{{$stime}}" charset="utf-8"></script>
		<link rel="stylesheet" type="text/css" href="{{$chicken_dir}}css/chickenpaint.css?{{$stime}}">
		<link rel="stylesheet" href="theme/{{$themedir}}/fix_chiken/fix.css?{{$stime}}" type="text/css">
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
			@if ($tool == 'chicken')
			<p><a href="#cp">ChickenPaintへ</a></p>
			@endif
		</header>
		<main>
			@if ($tool != 'chicken')
			<!-- 動的パレットスクリプト -->
			<script>
				var DynamicColor = 1;	// パレットリストに色表示
				var Palettes = new Array();
				@if ($palettes)
					{!!$palettes!!}
				@endif
				function setPalette(){
					d = document
					d.paintbbs.setColors(Palettes[d.Palette.select.selectedIndex])
					if(! d.grad.view.checked){return}
					GetPalette();
				}
				function PaletteSave(){
					Palettes[0] = String(document.paintbbs.getColors())
				}
				var cutomP = 0;
				function PaletteNew(){
					d = document;
					p = String(d.paintbbs.getColors());
					s = d.Palette.select;
					Palettes[s.length] = p;
					cutomP++;
					str = prompt("パレット名","パレット " + cutomP);
					if(str == null || str == ""){cutomP--;return}
					s.options[s.length] = new Option(str)
					if(30 > s.length) s.size = s.length
					PaletteListSetColor()
				}
				function PaletteRenew(){
					d = document
					Palettes[d.Palette.select.selectedIndex] = String(d.paintbbs.getColors())
					PaletteListSetColor()
				}
				function PaletteDel(){
					p = Palettes.length
					s = document.Palette.select
					i = s.selectedIndex
					if(i == -1)return
					flag = confirm("「"+s.options[i].text + "」を削除してよろしいですか？")
					if(!flag) return
					s.options[i] = null
					while(p>i){
						Palettes[i] = Palettes[i+1]
						i++
					}
					if(30 > s.length) s.size = s.length
				}
				function P_Effect(v){
					v=parseInt(v)
					x = 1
					if(v==255)x=-1
					d = document.paintbbs
					p=String(d.getColors()).split("\n")
					l = p.length
					var s = ""
					for(n=0;l>n;n++){
						R = v+(parseInt("0x" + p[n].substring(1,3))*x)
						G = v+(parseInt("0x" + p[n].substring(3,5))*x)
						B = v+(parseInt("0x" + p[n].substring(5,7))*x)
						if(R > 255){ R = 255}
						else if(0 > R){ R = 0}
						if(G > 255){ G = 255}
						else if(0 > G){ G = 0}
						if(B > 255){ B = 255}
						else if(0 > B){ B = 0}
						s += "#"+Hex(R)+Hex(G)+Hex(B)+"\n"
					}
					d.setColors(s)
					PaletteListSetColor()
				}
				function PaletteMatrixGet(){
					d = document.Palette
					p = Palettes.length
					s = d.select
					m = d.m_m.selectedIndex
					t = d.setr
					switch(m){
					case 0:case 2:default:
					t.value = ""
						n=0;c=0
						while(p>n){
							if(s.options[n] != null){ t.value = t.value + "\n!"+ s.options[n].text +"\n" + Palettes[n];c++}
							n++
						}
						alert ("パレット数："+c+"\nパレットマトリクスを取得しました");break
					case 1:
					t.value = "!Palette\n"+String(document.paintbbs.getColors())
						alert("現在使用されているパレット情報を取得しました");break
					}
						t.value = t.value.trim() + "\n!Matrix"
				}
				function PalleteMatrixSet(){
					m = document.Palette.m_m.selectedIndex;
					str = "パレットマトリクスをセットします。";
					switch(m){
					case 0:default:
						flag = confirm(str+"\n現在の全パレット情報は失われますがよろしいですか？");
						break;
					case 1:
						flag = confirm(str+"\n現在使用しているパレットと置き換えますがよろしいですか？");
						break;
					case 2:
						flag = confirm(str+"\n現在のパレット情報に追加しますがよろしいですか？");
						break;
					}
						if (!flag) return;
					PaletteSet()
					if(s.length < 30){ s.size = s.length}else{s.size=30}
					if(DynamicColor) PaletteListSetColor()
				}
				function PalleteMatrixHelp(){
					alert("★PALETTE MATRIX\nパレットマトリクスとはパレット情報を列挙したテキストを用いる事により\n自由なパレット設定を使用する事が出来ます。\n\n□マトリクスの取得\n1)「取得」ボタンよりパレットマトリクスを取得します。\n2)取得された情報が下のテキストエリアに出ます、これを全てコピーします。\n3)このマトリクス情報をテキストとしてファイルに保存しておくなりしましょう。\n\n□マトリクスのセット\n1）コピーしたマトリクスを下のテキストエリアに貼り付け(ペースト)します。\n2)ファイルに保存してある場合は、それをコピーし貼り付けます。\n3)「セット」ボタンを押せば保存されたパレットが使用できます。\n\n余分な情報があるとパレットが正しくセットされませんのでご注意下さい。");
				}
				function PaletteSet(){
					d = document.Palette
					se = d.setr.value;
					s = d.select;
					m = d.m_m.selectedIndex;
					l = se.length
					if(l<1){
						alert("マトリクス情報がありません。");return
					}
						n = 0;o = 0;e = 0
					switch(m){
					case 0:default:
						n = s.length
						while(n > 0){
							n--
							s.options[n] = null
						}
					case 2:
						i=s.options.length
						n = se.indexOf("!",0)+1
						if(n == 0)return
							Matrix1 = 1
							Matrix2 = -1
						while(n<l){
							e = se.indexOf("\n#",n)
							if(e == -1)return
							
							pn = se.substring(n,e+Matrix1)
							o = se.indexOf("!",e)
							if(o == -1)return
							pa = se.substring(e+1,o+Matrix2)
							if (pn != "Palette"){
							if(i >= 0)s.options[i] = new Option(pn)
							
							Palettes[i] = pa
							i++
							}else{document.paintbbs.setColors(pa)}
							
							n=o+1
						}
						break
					case 1:
						n = se.indexOf("!",0)+1
						if(n == 0)return
						e = se.indexOf("\n#",n)
						o = se.indexOf("!",e)
							if(e >= 0){
								pa = se.substring(e+1,o-1)
							}
						document.paintbbs.setColors(pa)
					}
					PaletteListSetColor()
				}
				function PaletteListSetColor(){
					var s = document.Palette.select;
					for(i = 1; s.options.length > i; i ++) {
						var c = Palettes[i].split("\n");
						s.options[i].style.background = c[4];
						s.options[i].style.color = GetBright(c[4]);
				}
				}
				function GetBright(c){
					r=parseInt("0x"+c.substring(1,3)),
					g=parseInt("0x"+c.substring(3,5)),
					b=parseInt("0x"+c.substring(5,7));
					c=(r>=g)?(r>=b)?r:b:(g>=b)?g:b;
					return 128>c?"#FFFFFF":"#000000";
				}
				function Chenge_(){
					var st = document.grad.pst.value
					var ed = document.grad.ped.value
					
					if(isNaN(parseInt("0x" + st)))return
					if(isNaN(parseInt("0x" + ed)))return
					GradView("#"+st,"#"+ed);
				}
				function ChengeGrad(){
					var d =document
					var st = d.grad.pst.value
					var ed = d.grad.ped.value
					Chenge_()
					var degi_R = parseInt("0x" + st.substring(0,2))
					var degi_G = parseInt("0x" + st.substring(2,4))
					var degi_B = parseInt("0x" + st.substring(4,6))
					var R = parseInt((degi_R - parseInt("0x" + ed.substring(0,2)))/15)
					var G = parseInt((degi_G - parseInt("0x" + ed.substring(2,4)))/15)
					var B = parseInt((degi_B - parseInt("0x" + ed.substring(4,6)))/15)
					if(isNaN(R)) R = 1
					if(isNaN(G)) G = 1
					if(isNaN(B)) B = 1
					var p = new String()
					for(cnt=0,m1=degi_R,m2=degi_G,m3=degi_B; 14>cnt; cnt++,m1-=R,m2-=G,m3-=B){
						if((m1 > 255)||(0 > m1)){ R *= -1;m1-=R}
						if((m2 > 255)||(0 > m2)){ G *= -1;m2-=G}
						if((m3 > 255)||(0 > m3)){ B *= -1;m2-=B}
						p += "#"+Hex(m1)+Hex(m2)+Hex(m3)+"\n"
					}
					d.paintbbs.setColors(p);
				}
				function Hex(n){
					n = parseInt(n);if(0 > n) n *=-1;
					var hex = new String()
					var m
					var k
					while(n > 16){
					m = n
					if(n >16){
						n = parseInt(n/16)
						m -= (n * 16)
					}
						k = Hex_(m)
						hex = k + hex
					}
						k = Hex_(n)
						hex = k + hex
					while(2 > hex.length){hex="0" + hex}
					return hex
				}
				function Hex_(n){
					if(! isNaN(n)){
						if(n == 10){n="A"}
						else if(n == 11){n="B"}
						else if(n == 12){n="C"}
						else if(n == 13){n="D"}
						else if(n == 14){n="E"}
						else if(n == 15){n="F"}
					}else{n=""}
					return n
				}
				function GetPalette(){
					d = document;
					p = String(d.paintbbs.getColors());
					if(p == "null" || p == ""){return};
					ps = p.split("\n");
					st = d.grad.p_st.selectedIndex
					ed = d.grad.p_ed.selectedIndex
					d.grad.pst.value = ps[st].substring(1,7)
					d.grad.ped.value = ps[ed].substring(1,7)
					GradSelC()
					GradView(ps[st],ps[ed])
					PaletteListSetColor()
				}
				function GradSelC(){
					if(! d.grad.view.checked)return
					d = document.grad
					l = ps.length
					pe=""
					for(n=0;l>n;n++){
						R = 255+(parseInt("0x" + ps[n].substring(1,3))*-1)
						G = 255+(parseInt("0x" + ps[n].substring(3,5))*-1)
						B = 255+(parseInt("0x" + ps[n].substring(5,7))*-1)
						if(R > 255){ R = 255}
						else if(0 > R){ R = 0}
						if(G > 255){ G = 255}
						else if(0 > G){ G = 0}
						if(B > 255){ B = 255}
						else if(0 > B){ B = 0}
						pe += "#"+Hex(R)+Hex(G)+Hex(B)+"\n"
					}
					pe = pe.split("\n");
					for(n=0;l>n;n++){
						d.p_st.options[n].style.background = ps[n];
						d.p_st.options[n].style.color = pe[n];
						d.p_ed.options[n].style.background = ps[n];
						d.p_ed.options[n].style.color = pe[n];
					}
				}
				function GradView(st,ed){
					d = document
					if(! d.grad.view.checked)return
				}
				function showHideLayer() { //v3.0
					d = document
					var l
					if(d.layers) {
						l = d.layers["psft"]
					}else{
						l = d.all("psft").style
					}
					if(! d.grad.view.checked){
						l.visibility = "hidden"
					}
					if(d.grad.view.checked){
						l.visibility = "visible";
						GetPalette();
					}
				}
			</script>
			<section id="appstage">
				<div class="app" id="apps">
					<applet-dummy code="pbbs.PaintBBS.class" archive="./PaintBBS.jar" name="paintbbs" width="{{$w}}" height="{{$h}}" mayscript>
					<param name="image_width" value="{{$picw}}">
					<param name="image_height" value="{{$pich}}">
					<param name="undo" value="{{$undo}}">
					<param name="undo_in_mg" value="{{$undo_in_mg}}">
					<param name="url_save" value="saveneo.php">
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
					<param name="neo_confirm_unload" value="true">
					<param name="neo_show_right_button" value="true">
					<param name="neo_send_with_formdata" value="true">
					</applet-dummy>
				</div>
				<div class="palette" id="dyntools">
					<form name="Palette">
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
						<fieldset>
							<legend>PALETTE</legend>
							<select class="form palette_set" name="select" size="13" onChange="setPalette()" id="palnames">
								<option>一時パレット</option>
								@if ($dynp)
									{!!$dynp!!}
								@endif
							</select><br>
							<input class="button" type="button" value="一時保存" onclick="PaletteSave()"><br>
							<input class="button" type="button" value="作成" onclick="PaletteNew()">
							<input class="button" type="button" value="変更" onclick="PaletteRenew()">
							<input class="button" type="button" value="削除" onclick="PaletteDel()"><br>
							<input class="button" type="button" value="明＋" onclick="P_Effect(10)">
							<input class="button" type="button" value="明－" onclick="P_Effect(-10)">
							<input class="button" type="button" value="反転" onclick="P_Effect(255)">
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
							<input type="button" class="button" name="m_h" value="SET" onclick="PalleteMatrixSet()">
							<input type="button" class="button" name="1" value=" ? " onclick="PalleteMatrixHelp()"><br>
							<textarea class="form" name="setr" rows="1" cols="13" onmouseover="this.select()"></textarea>
							</form>
						</fieldset>
						<fieldset>
							<legend>GRADATION</legend>
							<form name="grad">
								<input type="checkbox" name="view" onclick="showHideLayer()">
								<input type="button" class="button" value=" OK " onclick="ChengeGrad()">
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
								<input class="form "type="text" name="pst" size="8" onkeypress="Chenge_()" onchange="Chenge_()"><br>
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
								<input class="form" type="text" name="ped" size="8" onkeypress="Chenge_()" onchange="Chenge_()"><div id="psft" style="position:absolute;width:100px;height:30px;z-index:1;left:5px;top:10px;"></div>
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
								if (DynamicColor) PaletteListSetColor();
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
			@else
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
							fullScreenMode: "auto"

						});
					})
				</script>
			</section>
			@endif
		</main>
		<footer id="footer">
			@include('monoreita_footercopy')
		</footer>
	</body>
</html>