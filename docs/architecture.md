# 内部構成

## HTTP・画面制御

`index.php`はリクエストの受け取りと画面遷移を担当します。投稿入力の取得と検証は`post.inc.php`の`PostValidator`へ追加してください。DB接続や画像ファイル操作を追加する場合は、直接実装せず以下の層へ追加してください。

`PostValidator`は必須項目、文字数、NGワード、日本語フィルター、コメントURL、拒否ホストを画面描画から独立して検証します。

`PostService`は新規投稿の準備、二重投稿判定、スレッド・返信作成、age更新、投稿者・管理者パスワードの認証、投稿編集、削除、管理者による非表示化を担当します。投稿に関するDB更新を`index.php`へ直接追加しないでください。

`PostInput`は投稿種別など、複数のHTTP入力元に対応する値の取得と正規化を担当します。

## SNS共有

`share.inc.php`の`ShareService`が共有先一覧、直接入力URLの検証、SNS別エンドポイント、共有URL生成を担当します。フォーム描画、Cookie、リダイレクトは`index.php`が担当します。

## リクエストセキュリティ

`request_security.inc.php`の`RequestSecurity`がセッションの安全な開始、CSRFトークン生成、POST・同一オリジン・ユーザーコード・CSRFトークンの検証を担当します。検証失敗は`RequestSecurityException`として画面制御側へ返します。

## アプリケーション初期化

`initialization.inc.php`の`ApplicationInitializer`がセキュリティヘッダー、実行時ディレクトリの準備、DBマイグレーション、DBファイルの権限設定を担当します。起動時のファイル環境処理は`index.php`へ直接追加せず、このクラスへ追加してください。

## データベース

`database.inc.php`がSQLite接続、投稿リポジトリ、スキーママイグレーションを担当します。

- `Database::connect()`：共通のPDO接続とWAL設定
- `BoardRepository`：投稿の取得、検索、削除、非表示化
- `DatabaseMigrator`：スキーマ作成、バックアップ、マイグレーション

新規投稿、一覧、カタログ、返信、編集、管理一覧、ログ上限削除を含む投稿クエリは`BoardRepository`へ集約しています。新しい投稿クエリも`index.php`へ直接SQLを書かず、`BoardRepository`へ追加します。

## 画像

`image.inc.php`の`ImageService`が画像を扱います。

- アップロード画像の検証
- サムネイル生成
- 投稿に関連する画像・動画ファイルの一括削除

画像形式や関連ファイルを追加するときは、`index.php`ではなく`ImageService`を更新します。

`thumbnail.inc.php`はGDを使った画像変換処理を担当し、`ImageService`と`ExternalImageService`から利用されます。

`external_image.inc.php`の`ExternalImageService`は、本文中の外部画像URLの抽出、サムネイルキャッシュの生成と表示、安全な外部画像取得を担当します。外部取得ではTLS検証、公開IPだけへの接続、リダイレクト先の再検証、容量・画像形式・画像寸法の制限を行います。
