<?php
// noReita v4 設置者固有設定のサンプルです。
//
// このファイルをconfig.local.phpという名前でコピーし、各サンプル値を
// 実際の設置環境に合わせて変更してください。
// config.phpの既定値から変更したい項目だけを記述できます。
// 設定形式を表す_versionはconfig.local.phpへ記述しないでください。

return [
  'admin' => [
    // 管理画面へのログインに使用します。必ず推測されにくい値へ変更してください。
    'password' => 'replace-with-a-long-random-admin-password',

    // 管理者として投稿したときに表示する名前です。
    'name' => '管理人',
  ],

  'site' => [
    // 掲示板を設置した公開URLです。末尾の「/」を省略しないでください。
    'base_url' => 'https://bbs.example.com/noreita/',

    // ブラウザのタイトルや掲示板上部に表示する名前です。
    'title' => 'お絵かき掲示板',

    // 掲示板からホームへ戻るリンク先です。
    'home_url' => 'https://www.example.com/',

    // 投稿日時の基準となるタイムゾーンです。
    'timezone' => 'Asia/Tokyo',
  ],

  'database' => [
    // SQLiteデータベースのファイル名です。「.db」を付けずに指定します。
    'name' => 'reita',
  ],

  'identity' => [
    // 投稿者IDの生成に使う秘密文字列です。必ずランダムな値へ変更してください。
    'seed' => 'replace-with-a-long-random-id-seed',
  ],

  'security' => [
    // お絵かきアプリとサーバー間の投稿認証に使います。必ずランダムな値へ変更してください。
    'paint_password' => 'replace-with-a-long-random-paint-password',

    // 同一ドメインに複数のnoReitaを設置する場合は、設置ごとに異なる名前にします。
    'session_name' => 'noreita_session',
  ],
];
