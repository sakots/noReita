<?php
//--------------------------------------------------
//  おえかきけいじばん「noReita」の最新画像を呼び出すphp
//  by sakots & OekakiBBS reDev.Team  https://oekakibbs.moe/
//--------------------------------------------------

//  noreita_newimg.php (c)sakots 2021 lot.211130.1
//  The MIT License

// 使い方
// noReitaのindex.phpと同じディレクトリにアップロードして
// HTMLファイルに画像を表示する時のように
// noreita_newimg.php ←このファイルの名前をurlで指定します。

// 例）
// <img src="https://example.com/bbs/noreita_newimg.php" alt="" width="300">
// ↑
// この例では横幅300px、高さの指定なし。

//---------------- 設定 ----------------

// 画像がない時に表示する画像を指定
$default='';
// 例
// $default='https://example.com/image.png';
// 設定しないなら初期値の
// $default='';
// で。

//--------- 説明と設定ここまで ---------

require_once __DIR__ . '/bootstrap.php';
ApplicationBootstrap::boot(__DIR__);
$db_name = Config::string('database.name');
$image_dir = Config::string('paths.images');
$db_pdo = 'sqlite:' . __DIR__ . '/' . $db_name . '.db';

// db接続の前にdbがなかったらそもそも処理しない
// これを入れないとテーブルも何もないdbが作られていろいろ困る
if (!is_file(__DIR__ . '/' . $db_name . '.db')) {
  $filename = $default;
} else {
  try {
    // db接続
    $db = new PDO($db_pdo);
    // ORDER BY picfile で picfile名（画像の最終更新）の順、DESCで大きい順を指定
    // LIMIT 1 で1行だけ取り出すので最新のものだけになる
    $sql ="SELECT picfile FROM tlog WHERE invz=0 ORDER BY picfile DESC LIMIT 1";
    $msgs = $db->prepare($sql);
    $msgs->execute();
    $msg = $msgs->fetch(); //取り出せた
    // 配列$msg内のpicfileに格納されている
    // 配列がカラならデフォ画像
    if (empty($msg)) {
      $filename = $default;
    } else {
      $filename = __DIR__ . '/' . $image_dir . $msg["picfile"];
    }
    $db = null;// db切断
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
