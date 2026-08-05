# 更新手順 / update

新しいリリースへ安全に更新する方法です

## 更新前にバックアップするもの・上書きしてはいけないもの

- config.php
- SQLiteデータベース（*.dbファイル）
- 投稿画像
- サムネイル
- セッションなど

プログラム本体を更新するときは、新規ファイルの`noreita/error_handler.inc.php`と
`noreita/errorlog/.htaccess`もアップロードしてください。FTPソフトで隠しファイルを
転送しない設定になっている場合は、`.htaccess`を個別に確認してください。

## `config.example.php`に設定項目が増えた場合の反映方法

新しく増えた設定を末尾等に追加してください

v3.7.4ではSQLiteのロック待機時間を指定する設定が追加されました。既存の`config.php`へ追加しなくても既定値の5000ミリ秒で動作します。変更する場合は次を追加してください。

```php
const DB_BUSY_TIMEOUT = 5000;
```

古いPHPセッションファイルの保持時間も設定できます。既存の`config.php`に追加しない場合は、既定値の86400秒（24時間）で動作します。

```php
const SESSION_FILE_LIFETIME = 86400;
```

v3.7.6ではPHPエラーログの保持期間と容量上限を設定できます。既存の`config.php`へ
追加しない場合も、30日、1ファイル5 MiB、1日5ファイルの既定値で動作します。

```php
const ERROR_LOG_RETENTION_DAYS = 30;
const ERROR_LOG_MAX_BYTES = 5242880;
const ERROR_LOG_MAX_FILES_PER_DAY = 5;
```

削除復旧データの隔離期間も設定できます。追加しない場合は30日です。

```php
const DELETE_QUARANTINE_RETENTION_DAYS = 30;
```

## DB移行スクリプトが必要なバージョン

v2からv3系統への更新では、先に`noreita_db2_to_3.php`を実行してください。

v3以降のデータベースにはスキーマバージョンが記録され、noReita起動時に必要な更新が順番に適用されます。更新が必要な場合は、処理前に`backup/`ディレクトリへSQLiteデータベースのバックアップが自動作成されます。

- 移行中にエラーが発生した処理はロールバックされます
- 現在のnoReitaより新しいスキーマのDBは変更されません
- 自動バックアップがあっても、更新前にはサイト全体を手動でバックアップしてください

## 更新後の構文チェックとスモークテスト

`scripts/lint-php.sh`、`scripts/smoke-test.sh`を実行してください

最初にComposer依存ライブラリをインストールしてください。BladeOne v4.19.1は`composer.lock`で固定されています。

```bash
composer install --working-dir=noreita --no-dev --prefer-dist
```

依存ライブラリを配置した環境では、投稿・検索・削除のHTTP結合テストも実行してください。

```bash
scripts/integration-test.sh
```

このテストは一時ディレクトリと一時SQLite DBを使用し、設置済み掲示板の`config.php`やDBは変更しません。

更新後に`noreita/errorlog/`へブラウザーでアクセスし、403になることも確認してください。
PHPエラーの調査方法は[エラー調査手順](errors.md)を参照してください。

GitHub Actionsでは、PHP 7.4、8.0、8.3の各環境で`composer.lock`から依存ライブラリを配置し、このHTTP結合テストを自動実行します。

## 不具合時の元バージョンへの戻し方

githubのリリースより旧バージョンをダウンロードしてください
上書きしてはいけないものは上記に準じます
