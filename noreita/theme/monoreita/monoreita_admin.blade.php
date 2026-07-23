<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="utf-8">
  <title>{{$board_title}}</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  @include('components.monoreita_headCss')
</head>

<body>
  <header id="header">
    <h1><a href="{{$self}}">{{$board_title}}</a></h1>
    <div>
      <a href="{{$home}}" target="_top">[ホーム]</a>
      <a href="{{$self}}?mode=admin_in">[管理モード]</a>
    </div>
    <hr>
    <div>
      <section>
        <p class="top menu">
          <a href="{{$self}}">[トップ]</a>
          <a href="{{$self}}?mode=catalog">[カタログ]</a>
          <a href="{{$self}}">[通常モード]</a>
          <a href="{{$self}}?mode=pictmp">[投稿途中の絵]</a>
          <a href="#footer">[↓]</a>
        </p>
      </section>
      <section>
        <p class="sysmsg">{{$message ?? ''}}</p>
      </section>
    </div>
    <hr>
    <div>
      <section class="epost">
      <p>ADMIN MODE</p>
      <form action="{{$self}}?mode=admin_logout" method="post">
        <input type="hidden" name="token" value="{{$token}}">
        <input class="button" type="submit" value="ログアウト">
      </form>
      </section>
      <hr>
    </div>
  </header>
  <main>
    <section class="thread">
      <form action="{{$self}}" method="get">
        <p>検索</p>
        <input type="hidden" name="mode" value="admin">
        <p>
          <label>記事No. <input class="form" type="number" min="1" name="id" value="{{$admin_filters['id']}}" size="8"></label>
          <label>件名・本文 <input class="form" type="search" name="q" value="{{$admin_filters['q']}}" maxlength="200"></label>
          <label>名前 <input class="form" type="search" name="name" value="{{$admin_filters['name']}}" maxlength="200"></label>
          <label>ホスト <input class="form" type="search" name="host" value="{{$admin_filters['host']}}" maxlength="200"></label>
        </p>
        <p>
          <label>開始日 <input class="form" type="date" name="date_from" value="{{$admin_filters['date_from']}}"></label>
          <label>終了日 <input class="form" type="date" name="date_to" value="{{$admin_filters['date_to']}}"></label>
          <label>種類
            <select class="form" name="type">
              <option value="all" @if ($admin_filters['type'] === 'all') selected @endif>すべて</option>
              <option value="thread" @if ($admin_filters['type'] === 'thread') selected @endif>親記事</option>
              <option value="reply" @if ($admin_filters['type'] === 'reply') selected @endif>レス</option>
            </select>
          </label>
          <label>画像
            <select class="form" name="image">
              <option value="all" @if ($admin_filters['image'] === 'all') selected @endif>すべて</option>
              <option value="with" @if ($admin_filters['image'] === 'with') selected @endif>画像あり</option>
              <option value="without" @if ($admin_filters['image'] === 'without') selected @endif>画像なし</option>
            </select>
          </label>
          <label>NSFW
            <select class="form" name="nsfw">
              <option value="all" @if ($admin_filters['nsfw'] === 'all') selected @endif>すべて</option>
              <option value="yes" @if ($admin_filters['nsfw'] === 'yes') selected @endif>NSFW</option>
              <option value="no" @if ($admin_filters['nsfw'] === 'no') selected @endif>非NSFW</option>
            </select>
          </label>
        </p>
        <p>
          <label>表示状態
            <select class="form" name="visibility">
              <option value="all" @if ($admin_filters['visibility'] === 'all') selected @endif>すべて</option>
              <option value="visible" @if ($admin_filters['visibility'] === 'visible') selected @endif>表示中</option>
              <option value="hidden" @if ($admin_filters['visibility'] === 'hidden') selected @endif>非表示</option>
            </select>
          </label>
          <label>投稿者種別
            <select class="form" name="isAdministrator">
              <option value="all" @if ($admin_filters['isAdministrator'] === 'all') selected @endif>すべて</option>
              <option value="yes" @if ($admin_filters['isAdministrator'] === 'yes') selected @endif>管理者投稿</option>
              <option value="no" @if ($admin_filters['isAdministrator'] === 'no') selected @endif>一般投稿</option>
            </select>
          </label>
          <button class="button" type="submit">検索</button>
          <a href="{{$self}}?mode=admin">[条件をクリア]</a>
        </p>
      </form>
    </section>
    <nav class="thread" aria-label="管理画面ページ">
      <p>
        @if ($admin_filter_active) 検索結果 @else 全投稿 @endif {{$admin_total_posts}}件 /
        対象スレッド {{$admin_range_start}}～{{$admin_range_end}}件（全{{$admin_total_threads}}件）/
        このページ {{$admin_page_posts}}件
      </p>
      <p>
        @if ($admin_page > 1)
          <a href="{{$self}}?mode=admin{{$admin_filter_query}}&amp;page={{$admin_page - 1}}">[前へ]</a>
        @endif
        {{$admin_page}} / {{$admin_total_pages}}
        @if ($admin_page < $admin_total_pages)
          <a href="{{$self}}?mode=admin{{$admin_filter_query}}&amp;page={{$admin_page + 1}}">[次へ]</a>
        @endif
      </p>
    </nav>
    <form action="{{$self}}?mode=admin_manage" method="post">
      <input type="hidden" name="token" value="{{$token}}">
    <div>
      <div class="thread">
        <section class="delf">
          <button class="button" type="submit" name="operation" value="hide">選択した記事を非表示</button>
          <button class="button" type="submit" name="operation" value="show">選択した記事を再表示</button>
          <button class="button" type="submit" name="operation" value="delete"
            onclick="return confirm('選択した記事と関連ファイルを完全に削除します。この操作は元に戻せません。よろしいですか？');">選択した記事を完全削除</button>
        </section>
      </div>
      <section class="thread">
        <table class="delfo">
          <tr>
            <th><input type="checkbox" id="admin-select-all" aria-label="すべて選択"></th>
            <th>ID</th>
            <th>name</th>
            <th>date</th>
            <th>sub</th>
            <th>pic</th>
            <th>com</th>
            <th>host</th>
            <th>表示状態</th>
          </tr>
          @if (!empty($oya))
            @foreach ($oya as $bbsline)
              <tr>
                <td>
                  @if ($bbsline['_admin_matched'])
                    <input type="checkbox" name="delno[]" value="{{$bbsline['tid']}}" aria-label="記事{{$bbsline['tid']}}を選択">
                  @else
                    <span title="検索結果の親記事">－</span>
                  @endif
                </td>
                <td><a href="{{$self}}?mode=admin_post&amp;id={{$bbsline['tid']}}">{{$bbsline['tid']}}</a></td>
                <td>{{$bbsline['a_name']}}</td>
                <td>{{$bbsline['modified']}}</td>
                <td>{!! mb_substr($bbsline['sub'], 0, 6) !!}</td>
                <td>
                  @if ($bbsline['picfile'] == true)
                    <a href="{{$path}}{{$bbsline['picfile']}}" target="_brank">{{$bbsline['picfile']}}</a>
                  @endif
                </td>
                <td>{!! mb_substr($bbsline['com'], 0, 10) !!}</td>
                <td>{{$bbsline['host']}}</td>
                <td>@if ($bbsline['invz']) 非表示 @else 表示中 @endif</td>
              </tr>
              @if (!empty($ko[$bbsline['tid']]))
                @foreach ($ko[$bbsline['tid']] as $res)
                  <tr>
                    <td>
                      @if ($res['_admin_matched'])
                        <input type="checkbox" name="delno[]" value="{{$res['tid']}}" aria-label="記事{{$res['tid']}}を選択">
                      @else
                        <span title="検索結果の関連レス">－</span>
                      @endif
                    </td>
                    <td>└<a href="{{$self}}?mode=admin_post&amp;id={{$res['tid']}}">{{$res['tid']}}</a></td>
                    <td>{{$res['a_name']}}</td>
                    <td>{{$res['modified']}}</td>
                    <td>{!! mb_substr($res['sub'], 0, 6) !!}</td>
                    <td>
                      @if ($res['picfile'] == true)
                        <a href="{{$path}}{{$res['picfile']}}" target="_brank">{{$res['picfile']}}</a>
                      @endif
                    </td>
                    <td>{!! mb_substr($res['com'], 0, 10) !!}</td>
                    <td>{{$res['host']}}</td>
                    <td>@if ($res['invz']) 非表示 @else 表示中 @endif</td>
                  </tr>
                @endforeach
              @endif
            @endforeach
          @endif
        </table>
      </section>
    </div>
    <div class="thread">
      <section class="delf">
        <button class="button" type="submit" name="operation" value="hide">選択した記事を非表示</button>
        <button class="button" type="submit" name="operation" value="show">選択した記事を再表示</button>
        <button class="button" type="submit" name="operation" value="delete"
          onclick="return confirm('選択した記事と関連ファイルを完全に削除します。この操作は元に戻せません。よろしいですか？');">選択した記事を完全削除</button>
      </section>
    </div>
    </form>
    <nav class="thread" aria-label="管理画面ページ">
      <p>
        @if ($admin_page > 1)
          <a href="{{$self}}?mode=admin{{$admin_filter_query}}&amp;page={{$admin_page - 1}}">[前へ]</a>
        @endif
        {{$admin_page}} / {{$admin_total_pages}}
        @if ($admin_page < $admin_total_pages)
          <a href="{{$self}}?mode=admin{{$admin_filter_query}}&amp;page={{$admin_page + 1}}">[次へ]</a>
        @endif
      </p>
    </nav>
  </main>
  <footer id="footer">
    @include('components.monoreita_footerCopy') <!-- コピーライト -->
  </footer>
  <script>
    document.getElementById('admin-select-all')?.addEventListener('change', function () {
      document.querySelectorAll('input[name="delno[]"]').forEach((checkbox) => {
        checkbox.checked = this.checked;
      });
    });
  </script>
</body>

</html>
