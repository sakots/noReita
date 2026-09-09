<section class="thread">
  <h2>基本設定</h2>
  <p>すべての設定項目を<code>config.php</code>と同じPHP配列形式で編集できます。<code>config.local.php</code>に未設定の項目も、現在有効な値として表示されます。</p>
  <p>保存時は既定値との差分だけを<code>config.local.php</code>へ保存します。設定ファイル内のコメントやPHPコードは保持されず、管理画面用の形式へ置き換わります。</p>
  @if (!empty($config_message))<p class="sysmsg">{{$config_message}}</p>@endif
  <form action="{{$self}}?mode=admin_config_save" method="post">
    <input type="hidden" name="token" value="{{$token}}">
    <p><label for="configuration">設定PHP配列</label></p>
    <p><textarea class="form" id="configuration" name="configuration" rows="36" cols="100" spellcheck="false">{{$config_php}}</textarea></p>
    <p><button class="button" type="submit" onclick="return confirm('config.local.phpを上書き保存します。よろしいですか？');">設定を保存</button>
      <a href="{{$self}}?mode=admin">[管理画面へ戻る]</a></p>
  </form>
</section>
