# 内部構成

## HTTP・画面制御

`index.php`はリクエストの受け取りと画面遷移を担当します。投稿入力の取得と検証は`post.inc.php`の`PostValidator`へ追加してください。DB接続や画像ファイル操作を追加する場合は、直接実装せず以下の層へ追加してください。

外部PHPライブラリは`noreita/composer.json`と`composer.lock`で管理し、`vendor/autoload.php`から読み込みます。BladeOneはv4.19.1に固定しています。

`PostValidator`は必須項目、文字数、NGワード、日本語フィルター、コメントURL、拒否ホストを画面描画から独立して検証します。

`PostService`は新規投稿の準備、二重投稿判定、スレッド・返信作成、age更新、投稿者・管理者パスワードの認証、投稿編集、削除、管理者による非表示化を担当します。投稿に関するDB更新を`index.php`へ直接追加しないでください。画像付き投稿の削除では関連ファイルと復元用マニフェストを`backup/delete-staging/`へ一時退避し、DBトランザクション失敗時に復元します。DB削除の確定後にだけ退避ファイルを完全削除します。PHPの強制終了などで処理が残った場合は、次回起動時にDBの記事と画像名を照合して復元または完全削除を再開します。操作中の退避領域はファイルロックされ、別リクエストの復旧処理から除外されます。

`PostInput`は投稿種別など、複数のHTTP入力元に対応する値の取得と正規化を担当します。

## SNS共有

`share.inc.php`の`ShareService`が共有先一覧、直接入力URLの検証、SNS別エンドポイント、共有URL生成を担当します。フォーム描画、Cookie、リダイレクトは`index.php`が担当します。

## リクエストセキュリティ

`request_security.inc.php`の`RequestSecurity`がセッションの安全な開始、CSRFトークン生成、POST・同一オリジン・ユーザーコード・CSRFトークンの検証を担当します。検証失敗は`RequestSecurityException`として画面制御側へ返します。`SessionFileCleaner`は期限切れの`session/sess_*`だけを確率的かつ1回100件まで削除します。現在のセッション、別プロセスがロック中のセッション、管理者ログイン試行記録、`.htaccess`は削除しません。

同ファイルの`AdminAuth`が管理者ログイン状態、管理パス変更時の失効、無操作タイムアウト、ログアウトを担当します。管理操作では管理パスをフォームで持ち回らず、`AdminAuth::isAuthenticated()`とCSRF検証を使用してください。

`AdminLoginRateLimiter`は管理者ログインの失敗回数を接続元IP単位で制限します。生IPや管理パスは保存せず、管理パスを鍵にしたIPのHMACと、回数・時刻だけを`session/`内の`0600`ファイルへ保存します。既定では15分以内に5回失敗すると15分間HTTP 429で拒否し、ログイン成功時にそのIPの記録を削除します。期限切れの記録は、認証の成否にかかわらずログイン試行時に確率的かつ件数を制限して削除します。転送ヘッダーを偽装して制限を回避されないよう、判定には`REMOTE_ADDR`だけを使用します。

`request_info.inc.php`の`RequestInfo`がクライアントIPなど、HTTPリクエスト由来の情報の取得と正規化を担当します。

## エラー処理

`error_handler.inc.php`の`ApplicationErrorHandler`がPHPのWarning、未捕捉例外、Fatal errorを処理します。公開画面には照合用エラーIDだけを表示し、詳細はHTTPアクセスを拒否した`errorlog/`へ日付別JSON Lines形式で記録します。500応答を追加する場合は内部例外のメッセージを画面へ連結せず、`error()`の第3引数へ例外を渡してください。

## アプリケーション初期化

`initialization.inc.php`の`ApplicationInitializer`がセキュリティヘッダー、実行時ディレクトリの準備、DBマイグレーション、DBファイルの権限設定を担当します。起動時のファイル環境処理は`index.php`へ直接追加せず、このクラスへ追加してください。

## データベース

`database.inc.php`がSQLite接続、投稿リポジトリ、スキーママイグレーションを担当します。接続時に例外モード、WAL、`busy_timeout`を設定し、通常処理、DB移行、Misskey連携で同じ設定を使用します。既定では別処理の書き込みロックが解除されるまで最大5秒待ちます。待機時間は`config.php`の`DB_BUSY_TIMEOUT`で0～60000ミリ秒の範囲に変更できます。

- `Database::connect()`：共通のPDO接続とWAL・ロック待機設定
- `BoardRepository`：投稿の取得、検索、削除、非表示化
- `DatabaseMigrator`：スキーマ作成、バックアップ、マイグレーション

新規投稿、一覧、カタログ、返信、編集、管理一覧、ログ上限削除を含む投稿クエリは`BoardRepository`へ集約しています。新しい投稿クエリも`index.php`へ直接SQLを書かず、`BoardRepository`へ追加します。

管理一覧は`listAdminThreads()`で親スレッドをページ分割し、表示対象の親IDだけを`listAdminReplies()`へ渡します。レスを親と同じページに保つため、ページ境界は投稿単位ではなく親スレッド単位です。

管理画面の検索条件は`AdminPostFilter`で正規化し、同じ条件からSQL、PHP上の一致判定、ページリンク用クエリを生成します。レスが検索に一致した場合は親記事も文脈として表示しますが、削除チェックボックスは条件に一致した記事だけに表示します。

管理画面の非表示・再表示は`board_log.invz`を更新し、投稿本文と関連画像は保持します。`PostService::setVisibilityManyAsAdmin()`を経由し、完全削除とは別の操作として扱います。

管理画面の記事番号から`mode=admin_post`の投稿詳細を開けます。詳細画面は管理者セッションを必須とし、投稿情報、親子関係、画像・動画情報、公開状態を確認して、編集・非表示・再表示・完全削除へ進めます。

管理画面の基本統計は`BoardRepository::adminDashboardStats()`で1回のSQLに集約します。画像ディレクトリのファイル数と容量は走査負荷を抑えるため管理者セッションへ5分間キャッシュし、管理画面から完全削除した場合は破棄します。

## 画像

`image.inc.php`の`ImageService`が画像を扱います。

- アップロード画像の検証
- サムネイル生成
- 投稿に関連する画像・動画ファイルの一括削除

画像形式や関連ファイルを追加するときは、`index.php`ではなく`ImageService`を更新します。

`thumbnail.inc.php`はGDを使った画像変換処理を担当し、`ImageService`と`ExternalImageService`から利用されます。

`external_image.inc.php`の`ExternalImageService`は、本文中の外部画像URLの抽出、サムネイルキャッシュの生成と表示、安全な外部画像取得を担当します。外部取得ではTLS検証、公開IPだけへの接続、リダイレクト先の再検証、容量・画像形式・画像寸法の制限を行います。
