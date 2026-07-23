# 開発

## ローカルでの文法テストなど

Linux/WSL上では、プロジェクトのルートディレクトリで以下を実行してください。

```bash
./scripts/lint-php.sh
./scripts/smoke-test.sh
./scripts/integration-test.sh
```

`./scripts/lint-php.sh`ではPHP構文チェック、
`./scripts/smoke-test.sh`ではスモークテスト、
`./scripts/integration-test.sh`ではHTTP結合テストが行なえます。

成功すると最後に概ね以下のように表示されます。

```txt
Smoke tests: 31 passed, 0 failed.
Integration tests: 36 passed, 0 failed.
```

レンタルサーバーではなく、PHPと必要な拡張機能をインストールしたローカル開発環境またはCIで実行する想定です。
