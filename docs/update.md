# 更新手順 / update

新しいリリースへ安全に更新する方法です

## 更新前にバックアップするもの・上書きしてはいけないもの

- `config.local.php`
- SQLiteデータベース（*.dbファイル）
- 投稿画像
- サムネイル
- セッションなど

`config.php`はv4から配布既定値となり、更新時に上書きします。設置者固有の設定は
`config.local.php`だけに記述してください。新しい既定項目は更新された`config.php`から
自動的に取り込まれ、localにある項目だけが上書きされます。

プログラム本体を更新するときは`.htaccess`もアップロードしてください。FTPソフトで
隠しファイルを転送しない設定になっている場合は個別に確認してください。

## v3の`config.php`からv4へ移行

更新前に現在のv3 `config.php`を掲示板ディレクトリ外へバックアップし、v4の変換ツールを
ローカルまたはCLIが使えるサーバーで実行します。

```bash
php scripts/migrate-config-v3.php \
  --source=/path/to/backup/config.php \
  --output=/path/to/v4/noreita/config.local.php
```

先に結果だけ確認する場合は`--dry-run`を使用します。変換元は信頼済みPHPとして実行されます。
生成された`config.local.php`を確認し、v4の`config.php`、`bootstrap.php`、
`config_loader.inc.php`と一緒に配置してください。既存ファイルは`--force`なしでは上書きしません。

Gitで更新する場合、旧版で未追跡だった`noreita/config.php`が残っているとv4の追跡ファイルと
衝突します。変換・バックアップ後に旧ファイルを設置場所から退避してから更新してください。

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

このテストは一時ディレクトリと一時SQLite DBを使用し、設置済み掲示板の`config.local.php`やDBは変更しません。

更新後に`noreita/errorlog/`と`noreita/tmp/`へブラウザーでアクセスし、403になることも確認してください。
PHPエラーの調査方法は[エラー調査手順](errors.md)を参照してください。

GitHub Actionsでは、PHP 8.1、8.2、8.3、8.4、8.5の各環境で`composer.lock`から依存ライブラリを配置し、このHTTP結合テストを自動実行します。

## 不具合時の元バージョンへの戻し方

githubのリリースより旧バージョンをダウンロードしてください
上書きしてはいけないものは上記に準じます
