<?php
//--------------------------------------------------
//  おえかきけいじばん「noReita3」のデータベース移行php
//  by sakots & OekakiBBS reDev.Team  https://oekakibbs.moe/
//--------------------------------------------------

// noreita_db2_to_3.php (c)sakots 2026 lot.260405.0
// The MIT License

// 使い方
// noReita3のindex.phpと同じディレクトリにアップロードして
// ブラウザでこのファイルを開くと、noReita3のデータベースが移行されます。
// 例）
// https://example.com/bbs/noreita_db2_to_3.php
// これを開くと、noReita3のデータベースが移行されます。
// 移行が完了したら、セキュリティのためにこのファイルは削除してください。

include(__DIR__.'/config.php'); // config.phpの設定を読み込む

// データベース接続PDO
const DB_PDO = 'sqlite:'.DB_NAME.'.db';

try {
  // db接続
  $db = new PDO(DB_PDO);
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // board_logテーブルが存在しなければ作成
  $sql = "CREATE TABLE IF NOT EXISTS board_log (
    tid integer primary key autoincrement, --ID
    created TIMESTAMP, --描いた日時
    modified TIMESTAMP, --修正日時
    thread VARCHAR(1), --スレ親orレス
    parent INT, --親スレ
    comid BIGINT, --コメントID
    tree BIGINT, --スレ構造ID
    a_name TEXT, --名前
    mail TEXT, --メール
    sub TEXT, --タイトル
    com TEXT, --本文
    a_url TEXT, --url
    host TEXT, --ホスト
    sodane TEXT, --そうだね
    id TEXT, --投稿者ID
    pwd TEXT, --パスワード
    psec INT, --絵の時間(内部)
    utime TEXT, --絵の時間
    picfile TEXT, --絵のurl
    pchfile TEXT, --pchのurl
    img_w INT, --絵の幅
    img_h INT, --絵の高さ
    age INT, --age/sage記憶
    invz VARCHAR(1), --表示/非表示（管理者削除）
    tool TEXT, --絵のツール
    admins VARCHAR(1), --認証マーク
    shd VARCHAR(1), --そろそろ消える
    nsfw TEXT, --nsfw
    ctype TEXT, --?
    uuid TEXT, --uuid(v7)
    thumbnail TEXT --サムネイル
  )";
  $stmt = $db->prepare($sql);
  $stmt->execute();

  // tlogテーブルをboard_logテーブルに移行する
  $sql = "INSERT INTO board_log (tid, created, modified, thread, parent, comid, tree, a_name, mail, sub, com, a_url, host, sodane, id, pwd, psec, utime, picfile, pchfile, img_w, img_h, age, invz, tool, admins, shd, nsfw, ctype, uuid, thumbnail) SELECT tid, created, modified, thread, parent, comid, tree, a_name, mail, sub, com, a_url, host, exid, id, pwd, psec, utime, picfile, pchfile, img_w, img_h, age, invz, tool, admins, shd, ext01, ext02, ext03, ext04 FROM tlog";
  $stmt = $db->prepare($sql);
  $stmt->execute();
  // tlogテーブルを削除する
  $sql = "DROP TABLE tlog";
  $stmt = $db->prepare($sql);
  $stmt->execute();
  echo "データベースの移行が完了しました。";
} catch (PDOException $e) {
  echo "DB接続エラー:" .$e->getMessage();
}