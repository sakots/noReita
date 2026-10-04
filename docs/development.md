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

HTTP結合テストは一時ディレクトリ内の設定を切り替えて検証します。テスト用PHP開発サーバーでは`opcache.enable=0`を指定し、変更前の設定がキャッシュから読み込まれないようにします。CLI用の`opcache.enable_cli=0`だけでは、開発サーバーのOPcacheは無効になりません。実運用のOPcache設定は変更しません。

成功すると最後に概ね以下のように表示されます。

```txt
Smoke tests: 106 passed, 0 failed.
Integration tests: 129 passed, 0 failed.
```

レンタルサーバーではなく、PHPと必要な拡張機能をインストールしたローカル開発環境またはCIで実行する想定です。

## 不要コードの整理

未使用コードを削除する前に、アプリケーション本体、テスト、開発ツール、資料を検索し、呼び出し元がないことを確認します。外部向けの設定、URL、テーマの拡張点、データ移行処理は、参照が見つからないだけでは削除しません。テーマの分岐を削除する場合は、eda と monoreita の呼び出し元と部品を同時に確認します。削除後は既存の構文チェックと回帰テストを実行し、関連する資料の記述も残さないようにします。

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
