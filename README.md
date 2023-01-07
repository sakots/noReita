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
[chickenpaint](https://github.com/thenickdude/chickenpaint)
が使えます。
あと[偽しぃペインターNEO](https://github.com/sakots/Shi-PainterNEO)
も。

データベースを使ってるので検索が強いです。ハッシュタグも使えます。

## ROISとの互換性

ないです。

BladeOneとSQLtieは使っていますが、データベースの形式を変えました。

## 設置

[リリース](https://github.com/sakots/noReita/releases/latest) からダウンロードして、
FTPソフトをつかってサーバーにアップロードするだけです。簡単。

`config.php`の管理者パスワードをテキストエディタ(VSCodeなど)で編集してください。
初期設定のままだと動かないようにしています。

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

### [2023/01/07] v1.4.9

- 最初のスレッドへの最初のリプライのときにでるごく軽い警告をなくした([issue #5](https://github.com/sakots/noReita/issues/5))
  - あまりに軽微なためリリースはありません。

### [2023/01/07] v1.4.8

- PHP8.2環境下において掲示板を新規作成するとき、bladeのキャッシュフォルダがない場合のエラーを修正

### [2023/01/05] changelog.md

[changelog.md](changelog.md)作成

### [2023/01/02] v1.4.7

- パスワードが設定されていない場合の軽微なエラーを修正
- 同梱のneoのバージョンアップ(v1.5.16)
- PHP8.2での動作確認

### [2022/12/01] v1.4.6

- 大きなnsfw画像のときに文字の位置がずれる問題を修正
- BladeOneをバージョンアップ(4.7.1)

### [2022/10/04] v1.4.5

- Chicken Paintで描いた画像にNEOで続きから描いた場合のバグを修正
- Chicken Paintをバージョンアップ(0.4.1)

### [2022/09/08] v1.4.4

- カタログモードでパレットが選択できなかったの、それに付随するエラー修正
- カタログモードでページ遷移すると通常モードになるの修正
- カタログモードでのnsfw廃止
- BladeOneをバージョンアップ(4.6)
