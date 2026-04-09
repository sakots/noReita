<?php
//--------------------------------------------------
//  おえかきけいじばん「noReita3」の画像をランダムに呼び出すphp
//  by sakots & OekakiBBS reDev.Team  https://oekakibbs.moe/
//--------------------------------------------------

//  noreita3_rndimg.php (c)sakots 2026 lot.260405.0
//  The MIT License

// 使い方
// noReita3のindex.phpと同じディレクトリにアップロードして
// HTMLファイルに画像を表示する時のように
// noReita3_rndimg.php ←このファイルの名前をurlで指定します。

// 例）
// <img src="https://exsample.com/bbs/noreita3_rndimg.php" alt="" width="300">
// ↑
// この例では横幅300px、高さの指定なし。

//---------------- 設定 ----------------

// 画像がない時に表示する画像を指定
$default = '';
// 例
// $default='https://exsample.com/image.png';
// 設定しないなら初期値の
// $default='';
// で。

//--------- 説明と設定ここまで ---------

include(__DIR__.'/config.php'); // config.phpの設定を読み込む

// データベース接続PDO
const DB_PDO = 'sqlite:'.DB_NAME.'.db';

// db接続の前にdbがなかったらそもそも処理しない
// これを入れないとテーブルも何もないdbが作られていろいろ困る
if (!is_file(DB_NAME.'.db')) {
  $filename = $default;
} else {
  try {
    // db接続
    $db = new PDO(DB_PDO);
    // LIMIT 1 で取り出す画像が1枚だけ決まる。
    // 紆余曲折を経てこの文に行き着いた →
    // https://www.it-swarm-ja.com/ja/sql/SQLiteでランダムな行を選択します/970867568/
    $sql = "SELECT picfile FROM board_log WHERE thread = 1 LIMIT 1 OFFSET abs(random() % (SELECT SUM(thread) FROM board_log))";
    $msgs = $db->prepare($sql);
    $msgs->execute();
    $msg = $msgs->fetch(); // 取り出せた
    // 配列がカラならデフォ画像
    if (empty($msg)) {
      $filename = $default;
    } else {
      $filename = IMG_DIR.$msg["picfile"];
    }
    $db = null; // db切断
  } catch (PDOException $e) {
    echo "DB接続エラー:" .$e->getMessage();
  }
}

// 画像を出力

$img_type = mime_content_type($filename);

switch ($img_type):
  case 'image/png':
    header('Cache-Control: no-cache');
    header('Content-Type: image/png');
  break;
  case 'image/webp':
    header('Cache-Control: no-cache');
    header('Content-Type: image/webp');
  break;
  case 'image/avif':
    header('Cache-Control: no-cache');
    header('Content-Type: image/avif');
  break;
  case 'image/jpeg':
    header('Cache-Control: no-cache');
    header('Content-Type: image/jpeg');
  break;
  case 'image/gif':
    header('Cache-Control: no-cache');
    header('Content-Type: image/gif');
  break;
  default :
    header('Cache-Control: no-cache');
    header('Content-Type: image/png');
  endswitch;

readfile($filename);
