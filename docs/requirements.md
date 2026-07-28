# 必要環境 / requirements

noReitaが動作するサーバー条件

## 対応PHPバージョン

- PHP 7.4以上

## 必須拡張

- curl、gd、mbstring、pdo_sqlite
- （おそらく多くのレンタルサーバーには入っています）

## Composer依存ライブラリ

BladeOne v4.19.1をComposerで管理しています。ソースコードから設置・開発する場合は、事前に次を実行してください。

```bash
composer install --working-dir=noreita --no-dev --prefer-dist
```

`vendor/`を含む配布用パッケージを利用する場合、レンタルサーバー上でComposerを実行する必要はありません。

## Apacheで必要な設定

`.htaccess`が有効なApacheまたはApache互換サーバーを想定しています。

ルートの`.htaccess`は`config.php`、DB、ログ、メタデータへのHTTPアクセスを拒否します。`noreita/session/.htaccess`、`noreita/cache/.htaccess`、`noreita/backup/.htaccess`、`noreita/errorlog/.htaccess`は各ディレクトリ全体を拒否します。いずれもApache 2.4以降の`Require all denied`と、Apache 2.2互換の`Deny from all`の両方を収録しています。

FTPソフトによっては、名前が`.`で始まるファイルを表示・転送しないことがあります。アップロード後にルート、`session/`、`cache/`、`backup/`、`errorlog/`の各`.htaccess`が存在することを確認してください。これらを削除したり、非公開ファイルだけを別の公開ディレクトリへ移動したりしないでください。

`.htaccess`が禁止されているサーバーでは、サーバー管理画面またはApache本体の設定で設定ファイル、DB、`session/`、`cache/`、`backup/`、`errorlog/`へのアクセスを拒否する必要があります。

## nginxを使う場合のDB・設定ファイル保護

nginxは`.htaccess`を使用しません。`session/`、`cache/`、`backup/`、`errorlog/`、データベース、`config.php`へのアクセス拒否をnginx側で設定する必要があります。

## 必要な書き込み権限

初期設定では、ブラウザから配信する画像・動画ファイルを`0644`、公開ディレクトリを`0755`に設定します。PHPが設置ユーザーの権限で動作する一般的なレンタルサーバーを想定しています。

`session/`、`cache/`、`backup/`、`errorlog/`は`0700`、DB・ログ・DBバックアップは`0600`で管理します。これらを`0777`や`0666`に変更しないでください。

`session/`内のPHPセッションファイルは、最終更新から`SESSION_FILE_LIFETIME`を過ぎるとアクセス時に確率的に削除されます。既定の保持時間は24時間で、現在使用中またはロック中のセッションは削除対象外です。

サーバー固有の実行方式により書き込めない場合は、サーバー事業者が指定する範囲で`PERMISSION_FOR_DIR`などを調整してください。グループまたはその他のユーザーに書き込みを許可する設定は、初期化処理が安全でない設定として拒否します。

noReitaのルートディレクトリ全体をPHPから書き込み可能にする必要はありません。DBと上記の実行時ディレクトリだけに書き込み権限を与えてください。既存の権限が設定値と異なる場合にだけ`chmod()`を試行します。レンタルサーバーが`chmod()`を禁止していても、現在の権限が設定値より狭く、PHPから読み書き可能なら起動を継続します。設定値にない権限が付いていて安全性を確保できない場合は起動を拒否します。

v3.7.0以前の`config.php`にあった`0606`と`0707`は安全な初期値ではないため廃止しました。更新時は`config.example.php`を基に設定し直してください。

## GDが対応する画像形式

## PHPのアップロード容量・メモリ上限の目安
