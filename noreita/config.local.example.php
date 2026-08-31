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

  'features' => [
    // ブラウザからの画像アップロードを無効にする場合はfalseにします。
    'image_upload' => false,
    // 日記モードでは新規投稿を管理者ログイン中の利用者だけに限定します。
    'diary_mode' => false,
    // 日記モード中に一般利用者からの返信を許可する場合はtrueにします。
    'diary_allow_public_replies' => true,
    // 本文中の外部リンクをOGPリンクカードとして表示します。
    'external_link_preview' => true,
  ],

  'spam' => [
    // 本文で一致した規則の点数を合計し、threshold以上なら投稿を拒否します。0なら無効です。
    'comment_score_rules' => [
      ['未承諾広告', 2],
      // / は正規表現の区切り文字なので \ でエスケープします。
      ['https?:\\/\\/', 2],
    ],
    'comment_score_threshold' => 3,
  ],

  'limits' => [
    // 直接アップロードする画像の容量、幅、高さの上限です。
    'upload_kb' => 10000,
    // お絵描き保存APIのPNG、作業データ、リクエスト全体の上限です（KB）。
    // いずれもルート.htaccessの上限に合わせて32768以下にしてください。
    'paint_image_kb' => 10240,
    'paint_work_kb' => 20480,
    'paint_request_kb' => 32768,
    'image_width' => 4000,
    'image_height' => 4000,
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

    // リバースプロキシ配下の場合だけ、その直近のプロキシIPまたはCIDRを指定します。
    // 未設定時はX-Forwarded-ForとClient-IPを信頼しません。
    'trusted_proxies' => [],
  ],

];
