<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="utf-8">
  <title>エラーログ - {{$board_title}}</title>
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
      <p>ADMIN MODE / エラーログ</p>
      <form action="{{$self}}?mode=admin_logout" method="post">
        <input type="hidden" name="token" value="{{$token}}">
        <input class="button" type="submit" value="ログアウト">
      </form>
    </section>
  </header>

  <main>
    <section class="thread">
      <h2>管理者向けエラーログ</h2>
      <p>ログファイル自体は公開せず、この画面から最新 {{$admin_errorlog_limit}} 件までを確認できます。</p>
      <form action="{{$self}}" method="get">
        <input type="hidden" name="mode" value="admin_errorlog">
        <p>
          <label>日付
            <select class="form" name="log_date">
              @foreach ($admin_errorlog_dates as $date)
                <option value="{{$date}}" @if ($date === $admin_errorlog_date) selected @endif>{{$date}}</option>
              @endforeach
            </select>
          </label>
          <label>種別
            <select class="form" name="log_type">
              <option value="all" @if ($admin_errorlog_type === 'all') selected @endif>すべて</option>
              @foreach ($admin_errorlog_types as $type)
                <option value="{{$type}}" @if ($type === $admin_errorlog_type) selected @endif>{{$type}}</option>
              @endforeach
            </select>
          </label>
          <label>HTTP状態
            <select class="form" name="log_status">
              <option value="all" @if ($admin_errorlog_status === 'all') selected @endif>すべて</option>
              <option value="4xx" @if ($admin_errorlog_status === '4xx') selected @endif>4xx</option>
              <option value="5xx" @if ($admin_errorlog_status === '5xx') selected @endif>5xx</option>
            </select>
          </label>
          <button class="button" type="submit">絞り込む</button>
        </p>
      </form>
    </section>

    <section class="thread">
      @if ($admin_errorlog_date === '')
        <p>表示できるエラーログはありません。</p>
      @else
        <p>{{$admin_errorlog_date}} の該当 {{$admin_errorlog_total}} 件のうち、新しい順に {{count($admin_errorlog_records)}} 件を表示しています。</p>
        @if (empty($admin_errorlog_records))
          <p>条件に一致する記録はありません。</p>
        @else
          <table class="delfo">
            <tr><th>日時 / ID</th><th>種別</th><th>HTTP</th><th>リクエスト</th><th>内容</th></tr>
            @foreach ($admin_errorlog_records as $record)
              <tr>
                <td>{{$record['timestamp']}}@if ($record['error_id'] !== '')<br>{{$record['error_id']}}@endif</td>
                <td>{{$record['type']}}</td>
                <td>@if ($record['http_status'] > 0){{$record['http_status']}}@else － @endif</td>
                <td>{{$record['request_method']}} {{$record['request_path']}}</td>
                <td style="white-space:pre-wrap;overflow-wrap:anywhere;">{{$record['message']}}</td>
              </tr>
            @endforeach
          </table>
        @endif
      @endif
    </section>
  </main>

  <footer id="footer">
    @include('components.monoreita_footerCopy')
  </footer>
</body>

</html>
