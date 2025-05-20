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
        @if ($dat[0]['check_elapsed_days'] || $admin_post || $admin_del)
        @if (!($admin_post || $admin_del) || $verified !=='admin_post')
        <h3>この画像をMisskeyにノートします。パスワードを入力してください。</h3>
        @else
        <h3>この画像をMisskeyにノートします。</h3>
        @endif
        @php
        <form action="./" method="POST" id="before_delete" onsubmit="return res_form_submit(event,'before_delete')">
        @endphp
          @if (!($admin_post || $admin_del) || $verified !=='admin_post')
          <span class="non"><input type="text" value="" autocomplete="username"></span>
          <input type="password" name="pwd" value="{{$pwdc}}" autocomplete="current-password">
          @endif
          <input type="hidden" name="mode" value="misskey_note_edit_form">
          <span class="button">
            <svg viewBox="0 0 512 512">
							<use xlink:href="./theme/{{$themedir}}/icons/misskey.svg#misskey">
						</svg> <input type="submit" value="ノート">
          </span>
        </form>
        @else
        <h3>このスレッドは閉じられています。</h3>
        @endif
      </section>
    </div>
  </main>
  @endif
  <footer id="footer">
		@include('monoreita_footercopy')
  </footer>
</body>
</html>