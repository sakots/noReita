# noReita3_plugin

お絵かき掲示板noReita3のためのプラグインです。

## noreita3_newimg.php

データベースの最新画像を表示します。

## noreita3_rndimg.php

データベースからランダムに1枚、画像を表示します。

## 設置方法

1. noReita3を設置します。
2. 各プラグインのphpファイルを index.phpと同じディレクトリにアップロードします。

## 使い方

### noreita_db2_to_3.php

noReita3のindex.phpと同じディレクトリにアップロードして
ブラウザでこのファイルを開くと、noReita3のデータベースが移行されます。
例）

`https://example.com/bbs/noreita_db2_to_3.php`

これを開くと、noReita3のデータベースが移行されます。
移行が完了したら、セキュリティのためにこのファイルは削除してください。

### noreita3_newimg.php noreita3_rndimg.php

1. 画像と同じようにこのphpのファイルをimgタグで呼び出します。（phpファイル自体が画像として振る舞います）
2. HTMLファイルにimgタグで画像を呼び出すのと同じように、 `<img src="https://example.com/bbs/noreita3_newimg.php" alt="" width="300">`、`<img src="https://example.com/bbs/noreita3_rndimg.php" alt="" width="300">` などと書きます。

- 画像が無い時にデフォルト画像を表示させる事もできます。
- 画像を生成して画像になるphpなので、cssに画像として埋め込むこともできます。

## 更新履歴

### [2026/04/05]

- noReita3対応

### [2026/04/03]

- webpとavif対応

### [2024/04/13]

- php8環境におけるキャッシュクリア問題に対処（できているかわからない）

### [2022/09/01]

- 作成
