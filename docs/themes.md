# テーマのマニフェストと自己診断

noReita v4のテーマは、テーマディレクトリ内の`theme_conf.php`と
`theme_manifest.php`で構成します。

`theme_conf.php`は画面表示時に使用する設定です。`THEME_TEMPLATE_ENGINE`へ
`blade`または`twig`を指定します。Twigテーマでは、論理テンプレート名に対応する
`.twig`ファイルを配置します。

`theme_manifest.php`はテーマの配布情報と必須ファイルの一覧です。次の項目を記述します。

- `id`、`name`、`version`：テーマ識別子と表示情報
- `engine`：テーマが使用するテンプレートエンジン
- `requires`：必要なPHPとnoReitaのバージョン
- `templates`：画面ごとの必須テンプレート
- `assets`：必須CSS・JavaScript

マニフェストの値は`theme_conf.php`のテーマ名、バージョン、エンジン、テンプレート名と
一致している必要があります。起動時に不一致を検出すると、公開画面を表示せず設定エラーとして停止します。

## 自己診断

テーマの検査は読み取り専用です。通常は設置先の`noreita/`ディレクトリを指定します。

```sh
php plugins/check-theme.php --root=noreita
```

設定中のテーマではなく、任意のテーマを検査する場合は`--theme`を追加します。これは
`config.local.php`が未作成の開発ツリーでも実行できます。

```sh
php plugins/check-theme.php --root=noreita --theme=eda
php plugins/check-theme.php --root=noreita --theme=eda --json
```

診断ではマニフェストとテーマ設定の一致、必須テンプレート、コンポーネント参照、必須アセット、
Twig構文を確認します。エラーがある場合は終了コード1、実行自体に失敗した場合は終了コード2です。

古いテーマに`theme_manifest.php`がない場合は互換性のためBladeOneテーマとして起動しますが、
自己診断では警告を表示します。新規テーマにはマニフェストを必ず追加してください。
