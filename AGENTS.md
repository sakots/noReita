# AGENTS.md

## プロジェクト概要

noReita は PHP 8.1 以上で動作する、お絵かき掲示板です。SQLite、GD、Twig または
BladeOne のテーマ、PaintBBS NEO・Klecks・Tegaki.js などの描画アプリを扱います。

- アプリケーション本体: `noreita/`
- 入口: `noreita/index.php`
- 既定設定: `noreita/config.php`
- 設置者固有設定: `noreita/config.local.php`（Git 管理・更新上書きの対象外）
- テーマ: `noreita/theme/eda/`（Twig）と `noreita/theme/monoreita/`（BladeOne）

## 実装方針

- PHP 8.1 との互換性を維持する。新しい PHP 専用の構文や関数は導入しない。
- 新しい処理は、可能な限り `ApplicationContext`、サービス、コントローラーへ分離する。
  新規の `global`、`$GLOBALS`、静的なリクエスト状態は追加しない。
- HTML はテーマテンプレートで生成し、表示用データは `ApplicationContext->data` 経由で渡す。
- テーマを変更する場合は eda と monoreita の両方を同等に更新する。Twig は `.twig`、
  BladeOne は `.blade.php` を使う。
- ユーザー入力・ファイル名・URL・パスは信頼しない。既存の検証、エスケープ、
  `ImageService`、`RequestSecurity` を再利用する。
- `config.php` は配布既定値である。運用固有の値を追加・変更する際は
  `config.local.example.php` も更新し、設定ローダーで型・範囲・構造を検証する。

## 画像・共有・OGP

- 投稿画像と関連ファイル（サムネイル、PCH、PSD など）は `ImageService` でまとめて
  扱う。削除・置換時に関連ファイルを取りこぼさない。
- 共有用のスレッド URL は `?resno=<投稿番号>` を正規形式とする。旧来の
  `?mode=res&res=<投稿番号>` も受け付ける。
- SNS/OGP を変更する場合は、画像なし・通常画像・NSFW画像を考慮する。NSFW では
  原寸画像を OGP に出さず、ぼかし済みサムネイルを使う。
- ブラウザーコードで `document.write()` を新規に使わない。

## テストと診断

変更内容に応じて、少なくとも次を実行する。

```bash
php tests/smoke.php
php plugins/check-theme.php --root=noreita --theme=eda
php plugins/check-theme.php --root=noreita --theme=monoreita
git diff --check
```

- HTTP 経路・投稿・データベースに変更がある場合は、可能なら
  `bash scripts/integration-test.sh` も実行する。
- PHP ファイルを変更した場合は `php -l <file>`、または
  `bash scripts/lint-php.sh` を実行する。
- テーマ変更後は両テーマの診断を実行する。テンプレート内の条件式を属性値へ直接
  埋め込むと BladeOne の構文解析に失敗することがあるため、事前に表示用の値を作る。

## リリース

- リリース前にテスト・テーマ診断・差分チェックを通す。
- バージョンを更新する際は、少なくとも以下を同じ版へそろえる。
  - `noreita/index.php` の `REITA_VER`
  - `noreita/bootstrap.php` の `NOREITA_VERSION`
  - `README.md` と `changelog.md` の履歴
- テーマ側の互換性要件を変更した場合は、各テーマの `theme_manifest.php` と
  `theme_conf.php` も確認する。

## 作業上の注意

- 作業ツリーにあるユーザーの未コミット変更を消したり、無関係な形式変更をしない。
- `config.local.php`、データベース、投稿画像、実運用データをテスト目的で変更・削除
  しない。テストには一時ディレクトリを使う。
- 既存のエラー処理・セッション・CSRF・管理者認証の経路を迂回しない。
