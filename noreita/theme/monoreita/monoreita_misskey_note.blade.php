<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{$btitle}}</title>
	@include('monoreita_headcss')
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
					<a href="#footer">[↓]</a>
				</p>
			</section>
			<section>
				<hr>
				<p>Note to Misskey</p>
				<p class="sysmsg">{{$message}}</p>
			</section>
		</div>
		<hr>
	</header>
  @if ($misskey_mode == 'before')
  <main>
    <div>
      <section class="thread">
        <h3 class="oyat">この画像をMisskeyにノートします。パスワードを入力してください。</h3>
        <hr>
        <div class="thfoot">
          <form action="./" method="POST" id="before_delete" onsubmit="return res_form_submit(event,'before_delete')">
            <span class="non"><input type="text" value="" autocomplete="username"></span>
            <input type="password" name="pwd" value="{{$pwdc}}" autocomplete="current-password">
            <input type="hidden" name="mode" value="misskey_note_edit_form">
            <button type="submit">
              <svg viewBox="0 0 512 512">
                <use href="./theme/{{$themedir}}/icons/misskey.svg#misskey">
              </svg> ノート
            </button>
          </form>
        </div>
      </section>
    </div>
  </main>
  @endif
  @if ($misskey_mode == 'note_edit_form')
  <main>
    <div>
      
    </div>
  </main>
  @endif
  <footer id="footer">
		@include('monoreita_footercopy')
  </footer>
  <script>
    colorIdx = GetCookie('_monoreita_colorIdx');
    document.getElementById("mystyle").selectedIndex = colorIdx;
  </script>
  <script src="loadcookie.js"></script>
  <script>
    l(); //LoadCookie
  </script>
</body>
</html>