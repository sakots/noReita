@section('snsShare')
<script>
  //shareするSNSのserver一覧を開く
  let snsWindow = null; // グローバル変数としてウィンドウオブジェクトを保存する

  function open_sns_server_window(event, width = 600, height = 600) {
    event.preventDefault(); // デフォルトのリンクの挙動を中断

    // 幅と高さが数値であることを確認
    // 幅と高さが正の値であることを確認
    if (isNaN(width) || width <= 350 || isNaN(height) || height <= 400) {
      width = 350; // デフォルト値
      height = 400; // デフォルト値
    }
    let url = event.currentTarget.href;
    let windowFeatures = "width=" + width + ",height=" + height; // ウィンドウのサイズを指定

    if (snsWindow && !snsWindow.closed) {
      snsWindow.focus(); // 既に開かれているウィンドウがあればフォーカスする
    } else {
      snsWindow = window.open(url, "_blank", windowFeatures); // 新しいウィンドウを開く
    }
    // ウィンドウがフォーカスを失った時の処理
    snsWindow.addEventListener("blur", function () {
      if (snsWindow.location.href === url) {
        snsWindow.close(); // URLが変更されていない場合はウィンドウを閉じる
      }
    });
  }
</script>
@show
