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
Smoke tests: 41 passed, 0 failed.
Integration tests: 44 passed, 0 failed.
```

レンタルサーバーではなく、PHPと必要な拡張機能をインストールしたローカル開発環境またはCIで実行する想定です。
