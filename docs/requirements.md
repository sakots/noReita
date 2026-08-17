# 必要環境 / requirements

noReitaが動作するサーバー条件

## 対応PHPバージョン

- PHP 8.1以上

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

ルートの`.htaccess`は`config.php`、`config.local.php`に加え、`config.local.php.bak`、`config.php.old`、`config.local.php~`など`config`から始まる設定バックアップ名、テーマ設定PHP、Twig・BladeOneテンプレート、DB、ログ、メタデータへのHTTPアクセスを拒否します。`noreita/session/.htaccess`、`noreita/cache/.htaccess`、`noreita/backup/.htaccess`、`noreita/errorlog/.htaccess`、`noreita/auditlog/.htaccess`、`noreita/tmp/.htaccess`は各ディレクトリ全体を拒否します。`tmp/`の投稿前画像は、認可を確認する`index.php?mode=temporary_image`経由でだけ表示されます。いずれもApache 2.4以降の`Require all denied`と、Apache 2.2互換の`Deny from all`の両方を収録しています。

FTPソフトによっては、名前が`.`で始まるファイルを表示・転送しないことがあります。アップロード後にルート、`session/`、`cache/`、`backup/`、`errorlog/`、`auditlog/`、`tmp/`の各`.htaccess`が存在することを確認してください。これらを削除したり、非公開ファイルだけを別の公開ディレクトリへ移動したりしないでください。

`.htaccess`が禁止されているサーバーでは、サーバー管理画面またはApache本体の設定で設定ファイル、DB、`session/`、`cache/`、`backup/`、`errorlog/`、`auditlog/`、`tmp/`へのアクセスを拒否する必要があります。特に`tmp/`は一時画像を直接公開してはいけません。

## リバースプロキシ経由の接続元IP

既定では、接続元IPの判定にWebサーバーが設定した`REMOTE_ADDR`だけを使用し、利用者が送信できる`X-Forwarded-For`と`Client-IP`は信頼しません。通常のレンタルサーバーでは設定変更は不要です。

CDNやリバースプロキシを自分で構成し、Webサーバーから見た`REMOTE_ADDR`が常にそのプロキシになる場合だけ、直近のプロキシIPまたはCIDRを`config.local.php`へ指定します。利用者側のIP範囲を指定してはいけません。

```php
'security' => [
  'trusted_proxies' => ['192.0.2.10', '2001:db8:1234::/48'],
],
```

信頼済みプロキシから接続された場合に限り`X-Forwarded-For`を右から検証し、信頼済みプロキシ群の手前にある最初のIPを接続元として扱います。不正な値が1つでも含まれる場合はヘッダー全体を無視します。非標準の`Client-IP`は設定の有無にかかわらず使用しません。

## nginxを使う場合のDB・設定ファイル保護

nginxは`.htaccess`を使用しません。`session/`、`cache/`、`backup/`、`errorlog/`、`auditlog/`、`tmp/`、データベース、`config.php`、`config.local.php`とそのバックアップ名へのアクセス拒否をnginx側で設定する必要があります。

## 必要な書き込み権限

初期設定では、ブラウザから配信する画像・動画ファイルを`0644`、公開ディレクトリを`0755`に設定します。PHPが設置ユーザーの権限で動作する一般的なレンタルサーバーを想定しています。

Windowsでは`chmod()`と`fileperms()`の数値がPOSIX権限を正確に表さないため、noReitaは数値モードの一致検査を行いません。代わりに必要なディレクトリとDBが読み書き可能かを確認します。公開範囲は使用するWebサーバーのアクセス制御とWindows ACLで管理してください。

`session/`、`cache/`、`backup/`、`errorlog/`、`auditlog/`は`0700`、DB・ログ・DBバックアップは`0600`で管理します。これらを`0777`や`0666`に変更しないでください。

`session/`内のPHPセッションファイルは、最終更新から`security.session_file_lifetime`を過ぎるとアクセス時に確率的に削除されます。既定の保持時間は24時間で、現在使用中またはロック中のセッションは削除対象外です。

サーバー固有の実行方式により書き込めない場合は、サーバー事業者が指定する範囲で`permissions.public_directory`などを調整してください。グループまたはその他のユーザーに書き込みを許可する設定は、初期化処理が安全でない設定として拒否します。

noReitaのルートディレクトリ全体をPHPから書き込み可能にする必要はありません。DBと上記の実行時ディレクトリだけに書き込み権限を与えてください。既存の権限が設定値と異なる場合にだけ`chmod()`を試行します。レンタルサーバーが`chmod()`を禁止していても、現在の権限が設定値より狭く、PHPから読み書き可能なら起動を継続します。設定値にない権限が付いていて安全性を確保できない場合は起動を拒否します。

v3.7.0以前の`config.php`にあった`0606`と`0707`は安全な初期値ではないため廃止しました。v3からの移行時は`scripts/migrate-config-v3.php`を使用してください。

## GDが対応する画像形式

## PHPのアップロード容量・メモリ上限の目安

お絵描き保存APIは、PNG画像を既定10MiB、動画・PSDなどの作業データを既定20MiB、multipartリクエスト全体を32MiBまで受け付けます。上限は`config.local.php`の次の項目で、32MiB以下の範囲に調整できます。

```php
'limits' => [
  'paint_image_kb' => 10240,
  'paint_work_kb' => 20480,
  'paint_request_kb' => 32768,
],
```

アプリは`Content-Length`、PHPが解析した実ファイル容量、POSTデータとの合計、PNGの幅・高さを段階的に検証し、超過時はHTTP 413を返します。ルート`.htaccess`にも`LimitRequestBody 33554432`を設定し、ApacheではPHPがmultipartデータを一時ファイルへ展開する前に32MiBを超える要求を拒否します。

PHP側は`upload_max_filesize = 20M`以上、`post_max_size = 32M`以上を目安にしてください。PHP側の値を小さくした場合は、その値が実際の上限になります。nginxでは`.htaccess`が効かないため、同等の上限として`client_max_body_size 32m;`を設定してください。

サーバーのメモリ上限は、GDがPNGを展開する領域も考慮して設定してください。保存APIは設定されたキャンバス最大幅・高さに加えて、2,000万画素を超えるPNGを拒否します。
