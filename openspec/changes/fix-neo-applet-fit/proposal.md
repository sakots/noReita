# Proposal

## Why

NEO v1.8.7 では `Neo.resizeCanvas` が外部公開されておらず、noReita の FIT 操作が `TypeError` で中断する。枠を縮小しても表示用キャンバスが大きいまま残るため、両テーマのアプレットフィットを修正する。

## What Changes

- eda と monoreita の FIT 拡大・縮小で、枠と表示用キャンバスの寸法を同期する。
- 存在しない `Neo.resizeCanvas()` への依存を除き、NEO の既存の倍率・描画処理と表示用バッファの更新を組み合わせる。
- FIT 時に倍率を 1 倍へ戻す既存動作を維持し、画像の原寸・レイヤーの画素・描画履歴を変更しない。
- 非同期で NEO が初期化される既存の読み込み経路を維持する。
- 実際の NEO v1.8.7 を使って、両テーマで拡大後のズーム操作と FIT 縮小、反復操作、描画内容の保持を検証する。

対象外は NEO 本体の改造・配信方法の変更・旧版向け互換分岐・ウィンドウサイズ変更への自動追従・他のお絵かきアプリ・投稿保存経路の変更とする。

## Capabilities

### New Capabilities

- `neo-applet-fit`: NEO の描画画面で FIT による表示領域の拡大・縮小を行い、描画データを保ったまま表示と操作部品を同期する。既存機能を初めて仕様に登録するもので、新しい FIT ボタンを追加するものではない。

### Modified Capabilities

なし。既存の OpenSpec 仕様は未登録。

## Impact

- 実装対象: `noreita/theme/eda/js/appFit.js` と `noreita/theme/monoreita/js/appFit.js`。
- 検証対象: 両テーマの描画画面、表示用キャンバス、ズーム・スクロール・ツール配置、非同期初期化。
- 既存の `tests/smoke.php` の FIT テストはソース文字列を検査しており、今回の表示不整合を検出できない。実装時にこのテストを削除し、実ブラウザーの動作検証で受け入れ条件を確認する。
- 資料: 実装時に `docs/development.md` の検証手順と `README.md`・`changelog.md` の更新履歴を更新する。
- 新しい本番依存・設定・DB 変更・データ移行は不要。今回の提案段階ではアプリ本体を変更しない。
