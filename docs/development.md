# 開発

## ローカルでの文法テストなど

Linux/WSL上では、プロジェクトのルートディレクトリで以下を実行してください。

```bash
./scripts/lint-php.sh
./scripts/smoke-test.sh
./scripts/integration-test.sh
```

別名のPHPコマンドでテストする場合は、`PHP_BIN`を指定します。例えばPHP 8.1のコマンドが`php81`の場合は次のように実行できます。

```bash
PHP_BIN=php81 ./scripts/lint-php.sh
PHP_BIN=php81 ./scripts/smoke-test.sh
PHP_BIN=php81 ./scripts/integration-test.sh
```

`./scripts/lint-php.sh`ではPHP構文チェック、
`./scripts/smoke-test.sh`ではスモークテスト、
`./scripts/integration-test.sh`ではHTTP結合テストが行なえます。

成功すると最後に概ね以下のように表示されます。

```txt
Smoke tests: 99 passed, 0 failed.
Integration tests: 116 passed, 0 failed.
```

レンタルサーバーではなく、PHPと必要な拡張機能をインストールしたローカル開発環境またはCIで実行する想定です。

## Sassのコンパイル

配布テーマのスタイルはSassで管理しています。`noreita/theme/eda/css/` と
`noreita/theme/monoreita/css/` 以下の `.scss` を変更した場合は、生成されるCSSも更新してください。

初回のみ、プロジェクトルートでNode.jsの開発依存をインストールします。

```bash
npm ci
```

一度だけ全Sassをコンパイルする場合は、次を実行します。

```bash
npm run sass:build
```

開発中に監視して自動コンパイルする場合は、次を実行したままにします。

```bash
npm run sass
```

ビルドスクリプトは、リポジトリ配下にある先頭が`_`ではないすべての`.scss`を対象にします。各入力ファイルと同じディレクトリへ、以下を出力します。

- 展開済みCSS: `ファイル名.css`
- 圧縮版CSS: `ファイル名.min.css`
- それぞれのsource map: `.css.map` と `.min.css.map`

`_color.scss`や`_eda_conf.scss`のように先頭が`_`のファイルはpartialです。単体のCSSは生成されませんが、それらを`@use`するエントリーポイントを再コンパイルしてください。生成済みの`.css`、`.min.css`、source mapは直接編集せず、Sassを修正してからビルド結果をコミットします。

テーマを変更した後は、Sassビルドに加えて両テーマの診断と差分確認を実行してください。

```bash
php plugins/check-theme.php --root=noreita --theme=eda
php plugins/check-theme.php --root=noreita --theme=monoreita
git diff --check
```
