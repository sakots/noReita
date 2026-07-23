<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="utf-8">
  <title>投稿詳細 No.{{$admin_post['tid']}} - {{$board_title}}</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  @include('components.monoreita_headCss')
</head>

<body>
  <header id="header">
    <h1><a href="{{$self}}">{{$board_title}}</a></h1>
    <p class="top menu">
      <a href="{{$self}}?mode=admin">[管理画面へ戻る]</a>
      <a href="{{$self}}">[掲示板]</a>
    </p>
    <hr>
    <section class="epost">
      <p>ADMIN MODE / 投稿詳細 No.{{$admin_post['tid']}}</p>
      <form action="{{$self}}?mode=admin_logout" method="post">
        <input type="hidden" name="token" value="{{$token}}">
        <input class="button" type="submit" value="ログアウト">
      </form>
    </section>
  </header>

  <main>
    <section class="thread">
      <h2>投稿情報</h2>
      <table class="delfo">
        <tr><th>記事番号</th><td>{{$admin_post['tid']}}</td></tr>
        <tr>
          <th>種類</th>
          <td>
            @if ((int)$admin_post['thread'] === 1)
              親記事
            @else
              レス
              @if ($admin_parent)
                （親記事: <a href="{{$self}}?mode=admin_post&amp;id={{$admin_parent['tid']}}">No.{{$admin_parent['tid']}}</a>）
              @else
                （親記事なし）
              @endif
            @endif
          </td>
        </tr>
        <tr><th>投稿者</th><td>{{$admin_post['a_name']}}</td></tr>
        <tr><th>メール</th><td>{{$admin_post['mail']}}</td></tr>
        <tr><th>URL</th><td>{{$admin_post['a_url']}}</td></tr>
        <tr><th>件名</th><td>{{$admin_post['sub']}}</td></tr>
        <tr><th>本文</th><td>{!! $admin_post['com_html'] !!}</td></tr>
        <tr><th>投稿日</th><td>{{$admin_post['created']}}</td></tr>
        <tr><th>更新日</th><td>{{$admin_post['modified']}}</td></tr>
        <tr><th>IP・ホスト</th><td>{{$admin_post['host']}}</td></tr>
        <tr><th>投稿者ID</th><td>{{$admin_post['id']}}</td></tr>
        <tr><th>管理者投稿</th><td>@if ((int)$admin_post['admins'] === 1) はい @else いいえ @endif</td></tr>
        <tr><th>表示状態</th><td>@if ((int)$admin_post['invz'] === 1) 非表示 @else 表示中 @endif</td></tr>
        <tr><th>NSFW</th><td>@if ((int)$admin_post['nsfw'] === 1) NSFW @else 非NSFW @endif</td></tr>
        <tr><th>そうだね</th><td>{{$admin_post['sodane']}}</td></tr>
        <tr><th>描画ツール</th><td>{{$admin_post['tool']}}</td></tr>
        <tr><th>描画時間</th><td>{{$admin_post['utime']}}</td></tr>
        <tr><th>画像サイズ</th><td>{{$admin_post['img_w']}} × {{$admin_post['img_h']}}</td></tr>
        <tr><th>画像ファイル</th><td>{{$admin_post['picfile']}}</td></tr>
        <tr><th>サムネイル</th><td>{{$admin_post['thumbnail']}}</td></tr>
        <tr><th>動画ファイル</th><td>{{$admin_post['pchfile']}}</td></tr>
        <tr><th>UUID</th><td>{{$admin_post['uuid']}}</td></tr>
      </table>

      @if ($admin_pic_url !== '')
        <p><a href="{{$admin_pic_url}}" target="_blank" rel="noopener"><img src="{{$admin_thumbnail_url !== '' ? $admin_thumbnail_url : $admin_pic_url}}" alt="投稿画像 No.{{$admin_post['tid']}}" style="max-width:100%;height:auto;"></a></p>
      @endif
      @if ($admin_pch_playback_url !== '')
        <p><a href="{{$admin_pch_playback_url}}" target="_blank" rel="noopener">動画を再生する</a></p>
      @endif
    </section>

    <section class="thread">
      <h2>管理操作</h2>
      <p><a class="button" href="{{$self}}?mode=admin_edit&amp;id={{$admin_post['tid']}}">コメントを編集</a></p>
      <form action="{{$self}}?mode=admin_manage" method="post">
        <input type="hidden" name="token" value="{{$token}}">
        <input type="hidden" name="delno[]" value="{{$admin_post['tid']}}">
        @if ((int)$admin_post['invz'] === 1)
          <button class="button" type="submit" name="operation" value="show">この記事を再表示</button>
        @else
          <button class="button" type="submit" name="operation" value="hide">この記事を非表示</button>
        @endif
        <button class="button" type="submit" name="operation" value="delete"
          onclick="return confirm('この記事と関連ファイルを完全に削除します。この操作は元に戻せません。よろしいですか？');">この記事を完全削除</button>
      </form>
    </section>

    @if ((int)$admin_post['thread'] === 1)
      <section class="thread">
        <h2>レス（{{count($admin_replies)}}件）</h2>
        @if (empty($admin_replies))
          <p>レスはありません。</p>
        @else
          <ul>
            @foreach ($admin_replies as $reply)
              <li>
                <a href="{{$self}}?mode=admin_post&amp;id={{$reply['tid']}}">No.{{$reply['tid']}}</a>
                {{$reply['a_name']}} / {{$reply['modified']}}
                @if ((int)$reply['invz'] === 1)（非表示）@endif
              </li>
            @endforeach
          </ul>
        @endif
      </section>
    @endif
  </main>

  <footer id="footer">
    @include('components.monoreita_footerCopy')
  </footer>
</body>

</html>
