<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="utf-8">
  <title>{{$board_title}}</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  @include('monoreita_headcss')
  {{-- ok画面専用ヘッダ --}}
  @if ($othermode == 'ok')
  <meta http-equiv="refresh" content="1; URL={{$self}}">
  @endif
</head>

<body>
  <header>
    <h1><a href="{{$self}}">{{$board_title}}</a></h1>
    <div>
      <a href="{{$home}}" target="_top">[ホーム]</a>
      <a href="{{$self}}?mode=admin_in">[管理モード]</a>
    </div>
    <hr>
    <section>
      <p class="menu">
        <a href="{{$self}}">[通常モード]</a>
      </p>
    </section>
    <section>
      <p class="sysmsg">{{$message}}</p>
    </section>
    <hr>
  </header>
  <main>
    {{-- 記事編集モードスタート --}}
    @if ($othermode == 'edit')
      @include('components.editMode')
    @endif
    {{-- 記事編集モードおわり --}}
    {{-- コンティニューモードin --}}
    @if ($othermode == 'incontinue')
      @include('components.inContinueMode')
    @endif
    {{-- コンティニューモードin おわり --}}
    {{-- 管理モードin --}}
    @if ($othermode == 'admin_in')
      @include('components.inAdminMode')
    @endif
    {{-- 管理モードin おわり --}}
    {{-- ok画面 --}}
    @if ($othermode == 'ok')
      @include('components.ok')
    @endif
    {{-- ok画面 おわり --}}
    {{-- エラー画面 --}}
    @if ($othermode == 'err')
      @include('components.err')
    @endif
    {{-- エラー画面 おわり --}}
    {{-- 画像差し替え失敗専用エラー --}}
    @if ($othermode == 'err2')
      @include('components.err2')
    @endif
    {{-- 画像差し替え失敗専用エラー おわり --}}
  </main>
  <footer id="footer">
    @include('components.footerCopy')
  </footer>
</body>

</html>
