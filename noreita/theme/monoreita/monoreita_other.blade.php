<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="utf-8">
  <title>{{$board_title}}</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  @include('components.monoreita_headCss')
  {{-- ok画面専用ヘッダ --}}
  @if ($othermode == 'ok')
  <meta http-equiv="refresh" content="1; URL={{$self}}">
  @endif
  @include('components.monoreita_customCss')
</head>

<body>
  <header>
    <h1><a href="{{$self}}">{{$board_title}}</a></h1>
    <div>
      <a href="{{$home}}" target="_top">[ホーム]</a>
      @include('components.monoreita_adminSession')
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
    @if ($othermode == 'picreplace')
      <form method="post" action="{{ $self }}">
        <input type="hidden" name="mode" value="picrep">
        <input type="hidden" name="no" value="{{ $replacement_no }}">
        <input type="hidden" name="repcode" value="{{ $replacement_code }}">
        <input type="hidden" name="stime" value="{{ $stime }}">
        <input type="hidden" name="token" value="{{ $token }}">
        <button type="submit">{{ $replacement_label }}</button>
      </form>
    @endif
    {{-- 記事編集モードスタート --}}
    @if ($othermode == 'edit')
      @include('components.monoreita_editMode')
    @endif
    {{-- 記事編集モードおわり --}}
    {{-- コンティニューモードin --}}
    @if ($othermode == 'incontinue')
      @include('components.monoreita_inContinueMode')
    @endif
    {{-- コンティニューモードin おわり --}}
    {{-- 管理モードin --}}
    @if ($othermode == 'admin_in')
      @include('components.monoreita_adminInMode')
    @endif
    {{-- 管理モードin おわり --}}
    @if ($othermode == 'admin_config')
      @include('components.monoreita_adminConfig')
    @endif
    {{-- ok画面 --}}
    @if ($othermode == 'ok')
      @include('components.monoreita_ok')
    @endif
    {{-- ok画面 おわり --}}
    {{-- エラー画面 --}}
    @if ($othermode == 'err')
      @include('components.monoreita_err')
    @endif
    {{-- エラー画面 おわり --}}
  </main>
  <footer id="footer">
    @include('components.monoreita_footerCopy')
  </footer>
</body>

</html>
