# noReita

![php](https://img.shields.io/badge/php-7.4-green.svg)
![php](https://img.shields.io/badge/php-8.x-green.svg)

![Last commit](https://img.shields.io/github/last-commit/sakots/noReita)
![version](https://img.shields.io/github/v/release/sakots/noReita)
![Downloads](https://img.shields.io/github/downloads/sakots/noReita/total)
![License](https://img.shields.io/github/license/sakots/noReita)

## 概要

[Reactでお絵描き掲示板を作ろうとした](https://github.com/sakots/Reita)けど、あれ、jsxの中身に配列を送ってコンパイルして…って無理じゃね？
ってなったので諦めて、[ROIS](https://github.com/sakots/ROIS) から改良したものがこちらになります。

Reactで絵板を作れなかったので、noReita。

[PaintBBS NEO](https://github.com/funige/neo/)
と
[chickenPaint Be](https://github.com/satopian/ChickenPaint_Be)
が使えます。

データベースを使ってるので検索が強いです。ハッシュタグも使えます。

## ROISとの互換性

ないです。

BladeOneとSQLiteは使っていますが、データベースの形式を変えました。

## noReita v2以前との互換

- データベースの形式が違いますが、移行スクリプトがあります。

## 設置

`config.example.php`を`config.php`に名称変更し、その管理者パスワードをテキストエディタ(VSCodeなど)で編集してください。
初期設定のままだと動かないようにしています。

[リリース](https://github.com/sakots/noReita/releases/latest) からダウンロードして、
FTPソフトをつかってサーバーにアップロードするだけです。簡単。

動作の確認が取れたら、他の項目も変更してみてください。上から数行は必須項目です。

## サンプル/サポート

[SABRINA NO REITA](https://oekakibbs.moe)

## テーマ

テーマ機能で見た目を変えることができます。作り方とかまたこんど書きたい。

## 同梱のパレットについて

`p_PCCS.txt`(PCCS:日本色研配色体系パレット)は、[色彩とイメージの情報サイト IROUE](https://tee-room.info/color/database.html) を参考に、
`p_munsellHVC.txt`(マンセルHV/Cパレット)は、[マンセル表色系とRGB値](http://k-ichikawa.blog.enjoy.jp/etc/HP/js/Munsell/MSL2RGB0.html) を参照して作成いたしました。

再配布等自由にしていただいて構いません。ただの文字列なので著作権の主張はしませんが、書くのにそれなりの苦労はしましたので、再配布の際はどこかに私の名前を書いていただければと思います。

## 同梱していないパレットについて

[こちらで「やこうさんパレット」が配布されています](https://github.com/satopian/potiboard_plugin)

使用する場合は、`config.php`内の`$pallets_dat`の列に、

```config.php
$pallets_dat = array(['標準','palette.txt'],['PCCS_HSL','p_PCCS.txt'],['マンセルHV/C','p_munsellHVC.txt'],['やこうさん','palette.dat']);
```

などと加えてください。

## 履歴

[すべての履歴はこちら](changelog.md)

### [2026/04/06] v3.0.1

- 明るいタイプのテーマの文字色が白かったの修正
- サムネイルが生成されない場合のエラー修正

### [2026/04/06] v3.0.0

- エラーメッセージをすべて本体で記述した
- 投稿にUUIDを付加
- データベースの形式を変更
- 本文中に画像URLがあった場合サムネイルを作成し表示
  - config.php内に使う/使わないの設定等あります
- LitaChix、Neo更新

### [2026/04/04] v2.3.3

- トリップ生成機能追加
  - 名前欄に`#文字列`でトリップが生成されます（5ch互換のはず）
- 画像チェックにavif追加

### [2026/04/03] plugin

- webpとavif対応

### [2026/04/02] v2.3.2

- エラーメッセージの英語表記追加
- 更新履歴の一部を過去ログ化

### [2026/03/28] v2.3.1.1

- テーマ更新
  - カタログモードで下部リンクが間違っていたの修正
  - flexBox採用

### [2026/03/27] v2.3.1

- LitaChix、neo、Tegaki更新
- テーマ更新
  - 文字回り込みあたり

### [2026/03/14] v2.3.0

- save.phpを廃止
- klecks更新

### [2026/03/14] v2.2.11

- neo更新
- モード選択をswitchに戻した

### [2026/03/13] v2.2.10

- LitaChix、axons更新

### [2026/02/19] v2.2.9

- LitaChix更新
- データベース作成時のコメントを整理

### [2026/01/26] v2.2.8

- LitaChix更新

### [2026/01/26] v2.2.7

- LitaChix、klecks更新

### [2026/01/19] v2.2.6

- NEO、LitaChix更新

### [2026/01/03] v2.2.5

- LitaChix、klecks更新

### [2025/12/23] v2.2.4

- LitaChix更新

### [2025/12/14] v2.2.3

- テーマ更新（svgアイコンをcssに埋め込んだ）

### [2025/12/13] v2.2.2

- LitaChix更新

### [2025/12/09] v2.2.1

- WALモードを有効にした

### [2025/12/07] v2.2.0

- すべてのアプリを使ってリプライでお絵かきできるようにした
- save.incバージョンアップ
- klecks、litaChixバージョンアップ
- litaChitがlitaChixに改名したので対応

### [2025/12/02] v2.1.1

- php8.5対応、動作確認
- 管理パスのデフォルト値をkanri_passからadmin_passに変更できていなかったので修正
- PaintBBS NEOバージョンアップ（v1.6.21）

### [2025/11/23] v2.1.0

- ChickenPaintBeがlitaChitに改名したので対応
- litaChitで続きから描くのに対応
- axnosバージョンアップ
- しぃちゃん以外ではお絵かき前のパレット選択を表示しないようにした

### [2025/11/19] v2.0.0

- リプライでお絵かき（しいちゃん）をできるようにした
- 管理パスの初期値をadmin_passに
