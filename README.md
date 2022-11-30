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

### [2022/09/01]

- gitにいろいろ同梱はやめた

### [2022/07/26] v1.4.3

- bladeOneをバージョンアップ
- いろいろ同梱
- ソース整形

### [2022/06/20] v1.4.2

- bladeOneをバージョンアップ

### [2022/04/10] v1.4.1

- funigeさんが偽しぃペインターNEOを動くようにしてくれた
- sessionの場所でエラーが出ていたのでサーバー内に変更

### [2022/04/10] v1.4.0

- 「偽しぃペインターNEO」対応（まだまともに動かないけど）
- ついでにNEOを同梱

### [2022/03/21] v1.3.2

- BladeOneを4.5にバージョンアップ
- ChickenPaintを0.4.0にバージョンアップ
  - ソース内にはありません。リリースには同梱されています。
- ChickenPaintでドラッグしたままになることがあるのを修正

### [2022/02/10] v1.3.1

- 続きから書くと動画データ及び画像が喪失するバグ修正
- テーマ微修正

### [2022/01/23] theme nee-ex

- ボタン微調整

### [2022/01/22] v1.3.0

**configに大きな変更があります。再設定をお願いします。**

- 画像のNSFW設定機能を追加
- neoとChickenPaintのディレクトリを設定可能にした
  - 複数の掲示板を管理しているときに役に立ちます。
  - neoも`neo`ディレクトリに入れるようにしました。
- phpのキャッシュでテーマが変更しきれないことがあるため、テーマディレクトリ内のファイル名を変更

### [2022/01/22] theme nee-ex

- フォントサイズ等修正
- カタログモードでパレットが選択できなかったの修正

### [2022/01/21] v1.2.1a

- テーマ「nee-ex」追加
- その他テーマ微修正
- 同梱のchickenPaintをバージョンアップ

### [2022/01/19] v1.2.1

- 続きから書けなかったり記事編集できなかったりを修正
  - GETとPOSTまちがえてた。

### [2022/01/07] v1.2.0

- 2ページ目以降が表示されないとんでもないバグ修正
- 同梱のBladeOneをv4.2にバージョンアップ
- theme
  - ツイートボタンで名前のエラーが出るのを修正

### [2022/01/05] v1.1.7

- PHP8.1環境で起こりうるエラーを減らした

### [2022/01/03] v1.1.6a

- theme
  - レス境界のボーダーの長さ修正
  - 返信ボタンの上部に余裕をもたせた

※v1.1.6のリリースのファイルを変更しています。

### [2022/01/03] v1.1.6

- テーマを大幅に編集
  - figureタグ廃止など

### [2021/12/27] v1.1.5

- theme
  - スマホ時に画像がはみ出ることがあるのを修正

### [2021/12/20]

- php8.1での動作を確認

### [2021/12/06] v1.1.4

- ChickenPaintのパレットを長押しした時に不要なコンテキストメニュー(名前を付けて保存ほか)が開く問題に対応
- ChickenPaintのパレットをペンで長押しした時に、不要なマウスの右クリックメニューが開いてしまう問題に対応
- PaintBBS NEOで、コピーやレイヤー結合を行う時に画面が上下に動く問題に対応

windows inkや、Apple Pencil使用時に発生とのことで

### [2021/12/02] v1.1.3a

- 同梱のneo更新

### [2021/12/02] v1.1.3

- 文字数制限や入力必須項目について、画面遷移前にエラーメッセージを出すようにした。

### [2021/12/01] v1.1.2

- 管理者名を管理者パス以外で使うと名前にそう出るようにした
- テーマの管理画面に入るところで最新版のバージョン確認ができるようにした

### [2021/12/01] v1.1.1

- 名前の後の管理者マークについて、設定した名前と同じ場合にのみつくように修正
- プラグイン修正

### [2021/12/01] noreita_rndimg

- 文法ミス修正

### [2021/11/30] v1.1.0

- 古いスレッドの省略レスが見れなかったの修正
- 投稿時のパスワードが管理者パスと同じ場合、名前の後に管理者マークがつくようにした
- テーマ微修正
- 毎度おなじみ最新画像/ランダム画像プラグイン作成

### [2021/11/30] v1.0.0

- 初版。うごく。
