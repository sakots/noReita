# noReita_plugin

お絵かき掲示板noReitaのためのプラグインです。

## noreita_newimg.php

データベースの最新画像を表示します。

## noreita_rndimg.php

データベースからランダムに1枚、画像を表示します。

## 設置方法

1. noReitaを設置します。
2. 各プラグインのphpファイルを index.phpと同じディレクトリにアップロードします。

## 使い方

1. 画像と同じようにこのphpのファイルをimgタグで呼び出します。（phpファイル自体が画像として振る舞います）
2. HTMLファイルにimgタグで画像を呼び出すのと同じように、 `<img src="https://example.com/bbs/noreita_newimg.php" alt="" width="300">`、`<img src="https://example.com/bbs/noreita_rndimg.php" alt="" width="300">` などと書きます。

- 画像が無い時にデフォルト画像を表示させる事もできます。
- 画像を生成して画像になるphpなので、cssに画像として埋め込むこともできます。
