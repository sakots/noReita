<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{$btitle}}</title>
  @include('monoreita_headcss')
  <style>
    .form-group {
      margin: 1em 0;
    }
    .form-group label {
      display: block;
      margin-bottom: 0.5em;
    }
    .form-group textarea,
    .form-group input[type="text"] {
      width: 100%;
      max-width: 500px;
      padding: 0.5em;
      border: 1px solid #ccc;
      border-radius: 4px;
    }
    .server-list {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 0.5em;
      margin: 1em 0;
    }
    .server-option {
      display: flex;
      align-items: center;
      gap: 0.5em;
    }
    .server-option input[type="text"] {
      margin-left: 1em;
    }
    .cw-input {
      margin-top: 0.5em;
      padding: 0.5em;
      background: #f5f5f5;
      border-radius: 4px;
    }
  </style>
</head>
<body>
  @if ($misskey_mode == 'note_edit_form')
  <main>
    <div>
      <section class="thread">
        <h3 class="oyat">Misskeyにノートする内容を設定してください。</h3>
        <hr>
        <div class="thfoot">
          <form action="./" method="POST" id="misskey_note_form" onsubmit="return res_form_submit(event,'misskey_note_form')">
            <input type="hidden" name="mode" value="create_misskey_note_sessiondata">
            <input type="hidden" name="no" value="{{$dat['tid']}}">
            <input type="hidden" name="src_image" value="{{$dat['picfile']}}">
            <input type="hidden" name="id_and_no" value="{{$dat['id']}},{{$dat['tid']}}">
            <input type="hidden" name="abbr_toolname" value="{{$dat['tool']}}">
            <input type="hidden" name="paintsec" value="{{$dat['utime']}}">
            <input type="hidden" name="token" value="{{$token}}">

            <div class="form-group">
              <label for="com">コメント</label>
              <textarea name="com" id="com" rows="5" cols="48" wrap="soft">{{$dat['com']}}</textarea>
            </div>

            <div class="form-group">
              <label>
                <input type="checkbox" name="show_painttime" value="1" checked>
                描画時間を表示
              </label>
            </div>

            <div class="form-group">
              <label>
                <input type="checkbox" name="article_url_link" value="1" checked>
                記事へのリンクを表示
              </label>
            </div>

            @if ($use_nsfw === 1)
            <div class="form-group">
              <label>
                <input type="checkbox" name="hide_thumbnail" value="1" @if ($dat[0]['admins'] === 'admin_post') checked @endif>
                センシティブな画像として投稿
              </label>
              <div class="cw-input" style="display: none;">
                <label for="cw">注釈</label>
                <input type="text" name="cw" id="cw" size="48" maxlength="100" placeholder="センシティブな画像の注釈を入力">
              </div>
            </div>
            @endif

            <div class="form-group">
              <label>Misskeyサーバーを選択</label>
              <div class="server-list">
                @foreach ($misskey_servers as $server)
                <div class="server-option">
                  <input type="radio" name="misskey_server_radio" value="{{$server[1]}}" id="server_{{$loop->index}}" @if ($loop->first) checked @endif>
                  <label for="server_{{$loop->index}}">{{$server[0]}}</label>
                </div>
                @endforeach
                <div class="server-option">
                  <input type="radio" name="misskey_server_radio" value="direct" id="server_direct">
                  <label for="server_direct">直接入力</label>
                  <input type="text" name="misskey_server_direct_input" placeholder="https://example.com">
                </div>
              </div>
            </div>

            <div class="form-group">
              <button type="submit">
                <svg viewBox="0 0 512 512">
                  <use href="./theme/{{$themedir}}/icons/misskey.svg#misskey">
                </svg>
                Misskeyに投稿
              </button>
            </div>
          </form>
        </div>
      </section>
    </div>
  </main>

  <script>
    // センシティブ設定の表示/非表示
    document.querySelector('input[name="hide_thumbnail"]').addEventListener('change', function() {
      document.querySelector('.cw-input').style.display = this.checked ? 'block' : 'none';
    });

    // 直接入力の表示/非表示
    document.querySelector('input[name="misskey_server_radio"]').addEventListener('change', function() {
      const directInput = document.querySelector('input[name="misskey_server_direct_input"]');
      directInput.style.display = this.value === 'direct' ? 'inline-block' : 'none';
    });
  </script>
  @endif

  @if ($misskey_mode == 'success')
  <main>
    <div>
      <section class="thread">
        <h3 class="oyat">Misskeyへの投稿が完了しました</h3>
        <hr>
        <div class="thfoot">
          <p>画像の投稿が完了しました。</p>
          <p>
            <a href="{{$self}}?resno={{$no}}">記事に戻る</a>
          </p>
          <p>
            <a href="{{$self}}">掲示板に戻る</a>
          </p>
        </div>
      </section>
    </div>
  </main>
  @endif

  @if ($misskey_mode == 'before')
  <main>
    <div>
      <section class="thread">
        <h3 class="oyat">この画像をMisskeyにノートします。パスワードを入力してください。</h3>
        <hr>
        @if (isset($post['picfile']))
        <div class="post">
          <h4>
            <span class="oyaname">{{$post['name']}}</span>
            {{$post['created']}}
          </h4>
          <div class="image">
            <a href="{{$path}}{{$post['picfile']}}" target="_blank">
              <img src="{{$path}}{{$post['picfile']}}" alt="{{$post['sub']}}" width="{{$post['w']}}" height="{{$post['h']}}">
            </a>
          </div>
          <p class="comment">{!! $post['com'] !!}</p>
        </div>
        <hr>
        @endif
        <div class="thfoot">
          <form action="./" method="POST" id="before_delete">
            <input type="hidden" name="no" value="{{$post['tid']}}">
            <input type="hidden" name="id_and_no" value="{{$post['id']}},{{$post['tid']}}">
            <input type="hidden" name="created" value="{{$post['created']}}">
            <input type="hidden" name="modified" value="{{$post['modified']}}">
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
</body>
</html>