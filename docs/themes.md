# noReitaのテーマ作成

noReita v4.2以降では、既存テーマを親に指定する簡易テーマを推奨します。
簡易テーマで必須のファイルは`theme.php`だけです。配色も変更する場合は、任意の
`theme.css`を追加します。

## 最小テーマ

例えば`mytheme`を最小構成で作る場合は、次のように`theme.php`だけを配置します。
`noreita/theme/starter/`をコピーして始める場合、配色を変更しなければ同梱の
`theme.css`は削除できます。

```text
noreita/theme/mytheme/
└── theme.php
```

`theme.php`は親テーマだけ指定すれば動作します。`name`と`version`は省略でき、
それぞれディレクトリ名と`1.0.0`が使われます。

```php
<?php
return [
  'extends' => 'eda',
];
```

明示する場合は次のようにします。`extends`には`eda`または`monoreita`など、
継承するテーマのディレクトリ名を指定します。

```php
<?php
return [
  'name' => 'My Theme',
  'version' => '1.0.0',
  'extends' => 'eda',
];
```

`config.local.php`の`paths.theme`を作成したディレクトリ名へ変更すると有効になります。

```php
'paths' => [
  'theme' => 'mytheme',
],
```

## 安全上の注意

`theme.php`は設定データではなく、noReita本体と同じ権限で実行されるPHPコードです。
テーマ一式は、配布元と内容を信頼できるものだけを設置してください。テーマからは設定、
データベース、ファイルなどへアクセスでき、HTTPヘッダーやCookieを変更することもできます。

`theme.php`は配列を返す処理だけを記述し、`echo`、HTML、BOM、PHPタグ外の空白、
`header()`、`setcookie()`、セッション操作などの副作用を含めないでください。読み込み時に出力すると、
noReitaが後から送るセキュリティヘッダー、Cookie、リダイレクトが
`headers already sent`によって機能しなくなる場合があります。

## CSSも変更する

テーマのディレクトリへ任意の`theme.css`を追加すると、親テーマ、管理画面、描画アプリなどの
ページ固有CSSより後に自動で読み込まれます。
変更したい規則だけを記述します。
内容からキャッシュ用識別子を自動生成するため、CSSを編集するたびにバージョンを変更する必要はありません。

```css
body {
  background: #f5f5f5;
}

a {
  color: #004488;
}
```

簡易テーマ同士も継承できます。継承が循環している場合、親が存在しない場合、
または9階層以上の場合は安全のため起動しません。

## テンプレートの一部だけ変更する

親テーマと同じ相対パス・ファイル名でテンプレートを置くと、そのファイルだけが優先されます。
ほかのテンプレートとコンポーネントは親から読み込まれます。

例えばedaのスレッド部品だけ変更する場合は、親の
`components/eda_thread.twig`を次の場所へコピーして編集します。

```text
noreita/theme/mytheme/components/eda_thread.twig
```

edaを親にしたテーマはTwig、monoreitaを親にしたテーマはBladeOneを自動的に使用します。
子テーマ側でテンプレートエンジンや全テンプレート一覧を重複指定する必要はありません。

テンプレートから子テーマ内の独自画像などを参照する場合は`theme_active_dir`を使用できます。
親テーマの標準アセットを参照する従来の`theme_dir`は、親テーマのディレクトリ名です。

```twig
<img src="theme/{{ theme_active_dir }}/images/logo.png" alt="">
```

## 自己診断

テーマの検査は読み取り専用です。ローカル環境で実行します。

```sh
php plugins/check-theme.php --root=noreita --theme=mytheme
php plugins/check-theme.php --root=noreita --theme=mytheme --json
```

親テーマのマニフェスト、必須テンプレートとアセット、子テーマのTwig／BladeOne構文、
コンポーネント参照、配置されている`theme.css`を検査します。BladeOneはコンパイル後のPHPも
構文解析します。エラーがある場合は終了コード1、
診断自体に失敗した場合は終了コード2です。

## 従来形式との互換性

edaとmonoreitaは、`theme_conf.php`と`theme_manifest.php`を持つ完全テーマです。
完全テーマを新規開発する場合は、テンプレートエンジン、全画面のテンプレート名、
必須アセット、対応バージョンをこれらへ記述できます。

既存の完全テーマはv4.2でもそのまま動作します。通常の配色変更や部分的なレイアウト変更では、
完全テーマを複製せず簡易テーマを使用してください。

ルートの`.htaccess`が`theme.php`、`theme_conf.php`、`theme_manifest.php`、Twig、BladeOneの
テンプレートへの直接HTTPアクセスを拒否します。簡易テーマごとに`.htaccess`を作る必要はありません。
nginxでは`.htaccess`が動作しないため、`docs/requirements.md`に従って同じ拒否規則を
サーバー設定へ追加してください。
