# 内部構成

## HTTP・画面制御

`index.php`はリクエストの受け取り、入力値の検証、画面遷移を担当します。DB接続や画像ファイル操作を追加する場合は、直接実装せず以下の層へ追加してください。

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

`thumbnail.inc.php`はGDを使った画像変換処理を担当し、`ImageService`から利用されます。
