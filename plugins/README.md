# noReita_plugin

お絵かき掲示板noReitaのためのプラグインです。

## check-image-consistency.php

DBに記録された画像・動画・サムネイルと、画像ディレクトリ内のファイルを照合するCLI専用の検査ツールです。ファイルやDBの変更・削除は行いません。

リポジトリのルートでは次のように実行します。

```console
php plugins/check-image-consistency.php
```

別の場所に設置したnoReitaを調べる場合は、`config.php`があるディレクトリを指定します。

```console
php plugins/check-image-consistency.php --root=/path/to/noreita
```

問題なしなら終了コード`0`、不整合ありなら`1`、設定や実行上のエラーなら`2`を返します。機械処理用の出力は`--json`で取得できます。

検査結果のうち安全に復旧できる項目は、`--repair`を明示して修復できます。

```console
php plugins/check-image-consistency.php --root=/path/to/noreita --repair
```

修復前に`backup/`へSQLiteデータベースのバックアップを作成し、次の処理を行います。

- 実画像に合わせてDBの縦横サイズを修正
- 存在しない動画ファイルのDB参照を解除
- 欠損・破損したサムネイルを再生成
- 孤立ファイルと置き換えられた破損サムネイルを`orphan/`へ隔離

元画像の欠損、読み取り不能な画像、危険なファイル名は自動変更せず、検査結果に残します。復旧処理は排他ロックされ、DB更新はトランザクション内で行われます。

## noreita3_newimg.php

データベースの最新画像を表示します。

## noreita3_rndimg.php

データベースからランダムに1枚、画像を表示します。

## 設置方法

1. noReitaを設置します。
2. 各プラグインのphpファイルを index.phpと同じディレクトリにアップロードします。

## 使い方

1. 画像と同じようにこのphpのファイルをimgタグで呼び出します。（phpファイル自体が画像として振る舞います）
2. HTMLファイルにimgタグで画像を呼び出すのと同じように、 `<img src="https://example.com/bbs/noreita3_newimg.php" alt="" width="300">`、`<img src="https://example.com/bbs/noreita3_rndimg.php" alt="" width="300">` などと書きます。

- 画像が無い時にデフォルト画像を表示させる事もできます。
- 画像を生成して画像になるphpなので、cssに画像として埋め込むこともできます。

### noreita_db2_to_3.php

noReita3のindex.phpと同じディレクトリにアップロードして
ブラウザでこのファイルを開くと、noReita3のデータベースが移行されます。
例）

`https://example.com/bbs/noreita_db2_to_3.php`

## 更新履歴

### [2026/07/22]

- ディレクトリ名変更
- `check-image-consistency.php`作成

### [2026/07/22] noreita3_newimg.php noreita3_rndimg.php

- 文法エラー修正

### [2026/04/05]

- noReita3対応

### [2026/04/03]

- webpとavif対応

### [2024/04/13]

- php8環境におけるキャッシュクリア問題に対処（できているかわからない）

### [2022/09/01]

- 作成
