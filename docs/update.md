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

## SSHで複数の設置先へアップロード

配布アーカイブを展開した `noReita/noreita/` を転送元に指定します。
ローカルと転送先に `rsync` が必要です。転送先のディレクトリはあらかじめ作成し、
SSHホスト鍵を確認して `known_hosts` に登録してください。

```bash
bash scripts/upload-ssh.sh deploy_key /path/to/noReita/noreita \
  user@server1:/var/www/board/ board2:/home/user/public_html/board/
```

鍵は `~/.ssh/deploy_key` を使用します。`board2` は `~/.ssh/config` の `Host` 名も指定できます。
最初は変更内容を表示するだけです。確認後、実際に転送するには `upload-ssh.sh` の直後に
`--apply` を追加します。転送先の余分なファイルは削除せず、ローカル設定、DB、既定の
運用データ用ディレクトリは転送しません。配布用の `.htaccess` は転送します。

鍵のファイル名と転送先を繰り返し指定する場合は、リポジトリ外のテキストファイル
（例: `~/.ssh/noreita-upload.txt`）に保存できます。最初の有効行は鍵のファイル名、
以降は1行に1つの転送先です。空行と `#` で始まる行は無視します。
秘密鍵の中身は書かないでください。

```text
deploy_key
user@server1:/var/www/board/
board2:/home/user/public_html/board/
```

```bash
bash scripts/upload-ssh.sh --config ~/.ssh/noreita-upload.txt /path/to/noReita/noreita
```

設定ファイルを使う場合も、実行には `--config` の前に `--apply` を付けます。

## v4.14.2への更新

この版には、パスワード未設定の投稿を第三者が編集・削除できる問題の修正が含まれます。
既存サイトもプログラム本体を更新してください。設定変更や手動のDB移行は不要です。

更新後、パスワード未設定の投稿は、投稿者として編集・削除できなくなります。
必要な操作は、管理者ログイン後に管理画面から行ってください。
パスワードが設定されている投稿の所有者認証と、管理者の操作は従来どおり利用できます。

今回の3件の修正内容は[更新履歴](../changelog.md)を参照してください。

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

v3以降のデータベースにはスキーマバージョンが記録され、noReita起動時に必要な更新が順番に適用されます。更新が必要な場合は、処理前に`backup/`ディレクトリへSQLiteデータベースのバックアップが自動作成されます。たとえば、画像の説明を保存する`image_alt`列の追加も起動時に自動適用されるため、掲示板管理者がSQLを実行する必要はありません。

- 移行中にエラーが発生した処理はロールバックされます
- 現在のnoReitaより新しいスキーマのDBは変更されません
- 自動バックアップがあっても、更新前にはサイト全体を手動でバックアップしてください

## 更新後の構文チェックとスモークテスト

以下のテスト用スクリプトはソースリポジトリに含まれます。配布アーカイブのみで更新する
場合は、更新後に掲示板の表示・投稿・編集・削除と、利用中のお絵かき機能を確認してください。

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

更新後に`noreita/errorlog/`、`noreita/auditlog/`、`noreita/tmp/`へブラウザーでアクセスし、403になることも確認してください。
PHPエラーの調査方法は[エラー調査手順](errors.md)を参照してください。

GitHub Actionsでは、PHP 8.2、8.3、8.4、8.5の各環境で`composer.lock`から依存ライブラリを配置し、このHTTP結合テストを自動実行します。

## 不具合時の元バージョンへの戻し方

githubのリリースより旧バージョンをダウンロードしてください
上書きしてはいけないものは上記に準じます
