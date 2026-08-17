# noReitaのテーマ作成

noReita v4.2以降では、既存テーマを親に指定する簡易テーマを推奨します。
PHPスクリプトの設置者が配色を変更するだけなら、必要なファイルは`theme.php`と
`theme.css`の2つだけです。

## 最小テーマ

`noreita/theme/starter/`を別名でコピーします。例えば`mytheme`を作る場合は、
次の構成になります。

```text
noreita/theme/mytheme/
├── theme.php
└── theme.css
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

## CSSだけ変更する

`theme.css`は親テーマのCSSより後に自動で読み込まれます。変更したい規則だけを記述します。
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

親テーマのマニフェスト、必須テンプレートとアセット、子テーマのTwig構文、
コンポーネント参照、`theme.css`を検査します。エラーがある場合は終了コード1、
診断自体に失敗した場合は終了コード2です。

## 従来形式との互換性

edaとmonoreitaは、`theme_conf.php`と`theme_manifest.php`を持つ完全テーマです。
完全テーマを新規開発する場合は、テンプレートエンジン、全画面のテンプレート名、
必須アセット、対応バージョンをこれらへ記述できます。

既存の完全テーマはv4.2でもそのまま動作します。通常の配色変更や部分的なレイアウト変更では、
完全テーマを複製せず簡易テーマを使用してください。

ルートの`.htaccess`が`theme.php`、`theme_conf.php`、`theme_manifest.php`、Twig、BladeOneの
テンプレートへの直接HTTPアクセスを拒否します。簡易テーマごとに`.htaccess`を作る必要はありません。
