# noReita

![php](https://img.shields.io/badge/php->5.6-green.svg)
![php](https://img.shields.io/badge/php-7.x-green.svg)
![php](https://img.shields.io/badge/php-8.0-green.svg)

![Last commit](https://img.shields.io/github/last-commit/sakots/noReita)
![version](https://img.shields.io/github/v/release/sakots/noReita)
![Downloads](https://img.shields.io/github/downloads/sakots/noReita/total)
![Licence](https://img.shields.io/github/license/sakots/noReita)

## 概要

Reactでお絵描き掲示板を作ろうとしたけど、あれ、jsxの中身に配列を送ってコンパイルして…って無理じゃね？
ってなったので諦めて、[ROIS](https://github.com/sakots/ROIS) から改良したものがこちらになります。

Reactで絵板を作れなかったので、noReita。

[PaintBBS NEO](https://github.com/funige/neo/)
と
[chickenpaint](https://github.com/thenickdude/chickenpaint)
が使えます。

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

まだない

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

## ソースコードからのダウンロードについて

ソースコードには、PaintBBSNEO、chickenpaint、BladeOneは含まれません。（わたしのものではないので）

リリース以外からのダウンロードの場合は、これらは自力でダウンロードをお願いします。

## 履歴

### [2021/11/30] v1.0.0

- 初版。うごく。
