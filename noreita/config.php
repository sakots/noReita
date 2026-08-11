<?php
// noReita v4 default configuration.
// このファイルは更新時に上書きされます。設置者固有の値はconfig.local.phpへ記述してください。

return [
  // 設定ファイル形式のバージョンです。アプリケーションが内部で使うため変更しないでください。
  '_version' => 4,

  // 管理画面と管理者表示に関する設定です。
  'admin' => [
    // 管理画面へログインするときのパスワードです。config.local.phpで必ず変更してください。
    'password' => 'admin_pass',
    // 管理者が投稿したときに表示する名前です。
    'name' => '管理人',
    // 管理者名の後ろに付け、一般投稿者による管理者名の使用と区別する文字列です。
    'cap' => '(ではない)',
    // 管理者ログインを無操作で維持する時間です（秒）。
    'session_lifetime' => 1800,
    // 管理画面への連続ログイン失敗を制限する設定です。
    'login' => [
      // 判定期間内に許可するログイン失敗回数です。
      'max_failures' => 5,
      // ログイン失敗回数を集計する期間です（秒）。
      'window' => 900,
      // 上限に達した接続元からのログインを拒否する時間です（秒）。
      'lockout' => 900,
    ],
    // 管理画面の投稿一覧で1ページに表示するスレッド数です。
    'threads_per_page' => 50,
    // 管理画面の一時画像一覧で1ページに表示する画像数です。
    'temporary_images_per_page' => 50,
  ],

  // 掲示板のURL、名称、言語などの基本情報です。
  'site' => [
    // 掲示板を設置した公開URLです。httpまたはhttpsで始め、末尾に「/」を付けてください。
    'base_url' => 'https://example.com/noreita/',
    // ページタイトルや見出しに表示する掲示板名です。
    'title' => 'お絵かき掲示板',
    // 掲示板から「ホーム」へ戻るリンク先です。相対URLまたは絶対URLを指定できます。
    'home_url' => '../',
    // PHPのマルチバイト文字処理で使用する言語名です。
    'language' => 'Japanese',
    // 日付表示に使用するPHPのタイムゾーン識別子です。
    'timezone' => 'Asia/Tokyo',
    // 掲示板本体のエントリーポイント名です。通常は変更しません。
    'script_name' => 'index.php',
  ],

  // SQLiteデータベースに関する設定です。
  'database' => [
    // データベースファイルの名前です。「.db」を付けずに指定します。
    'name' => 'reita',
    // SQLiteがロック中だった場合に処理を待つ最大時間です（ミリ秒）。
    'busy_timeout' => 5000,
  ],

  // スレッド、投稿、一覧表示に関する設定です。
  'board' => [
    // 保存するスレッド数の上限です。上限を超える古いスレッドは整理対象になります。
    'max_threads' => 1000,
    // 通常のスレッド一覧で1ページに表示するスレッド数です。
    'page_size' => 10,
    // スレッド一覧で各スレッドに展開して表示する最新返信数です。
    'replies_shown' => 7,
    // 保存スレッド数が上限の何パーセントに達したら警告を表示するかを指定します。
    'log_warning_percent' => 94,
    // カタログ画面で1ページに表示するスレッド数です。
    'catalog_size' => 30,
    // 投稿者名などをブラウザのCookieに保存する日数です。
    'cookie_days' => 7,
    // 投稿日時の表示形式です。PHPのdate()で使える形式を指定します。
    'date_format' => 'Y/m/d H:i:s',
    // この返信数に達したスレッドでsageを強制するための互換設定です。
    'force_sage_replies' => 20,
    // 最終投稿からこの日数が経過したスレッドへの返信を制限します。0で制限しません。
    'elapsed_reply_days' => 365,
    // 投稿者による記事削除を許可するための互換設定です。
    'user_delete' => true,
    // 名前が未入力だった場合に表示する名前です。
    'default_name' => '名無しさん',
    // 本文が未入力だった場合に補う本文です。
    'default_comment' => '本文無し',
    // 件名が未入力だった場合に補う件名です。
    'default_subject' => '無題',
    // 掲示板上部の案内欄へ表示するHTMLです。配列の各要素を1行として表示します。
    'additional_info' => [
      '<a href="https://github.com/sakots/noReita">ソースはこちら</a>',
      'まだまだ開発中…バグがあったら教えてね。',
    ],
  ],

  // 掲示板の各機能を有効または無効にする設定です。trueで有効、falseで無効です。
  'features' => [
    // Litachix（ChickenPaint）のお絵かきアプリを選択可能にします。
    'chickenpaint' => true,
    // Klecksのお絵かきアプリを選択可能にします。
    'klecks' => true,
    // Tegakiのお絵かきアプリを選択可能にします。
    'tegaki' => true,
    // Axnos Paintのお絵かきアプリを選択可能にします。
    'axnos' => true,
    // 返信フォームからお絵かき投稿できるようにします。
    'oekaki_reply' => true,
    // ブラウザから画像ファイルを直接アップロードして投稿できるようにします。
    'image_upload' => false,
    // 日記モードでは新規投稿を、管理者ログイン中の利用者だけに限定します。
    'diary_mode' => false,
    // 日記モードで一般利用者からの返信を許可します。falseにすると返信も管理者限定です。
    'diary_allow_public_replies' => true,
    // 投稿を外部サービスへ共有するボタンを表示します。
    'share_button' => true,
    // 共有先を選択する詳細メニューを表示します。
    'share_details' => true,
    // Misskeyへノートするためのボタンを表示します。
    'misskey_note' => true,
    // 本文中の外部画像URLをサムネイル表示します。
    'external_image_thumbnail' => true,
    // NSFW指定と、該当画像を隠して表示する機能を有効にします。
    'nsfw' => true,
    // 投稿者IDを生成して投稿に表示します。
    'display_id' => true,
    // 日本語を含まない投稿をスパムとして拒否します。
    'japanese_filter' => true,
    // 本文にURLが含まれる投稿を拒否します。
    'deny_comment_urls' => false,
    // 本文中のURLを自動的にリンクへ変換します。
    'autolink' => true,
    // 投稿時の名前入力を必須にします。
    'require_name' => false,
    // 投稿時の本文入力を必須にします。
    'require_comment' => false,
    // 投稿時の件名入力を必須にします。
    'require_subject' => false,
    // 返信フォームにも件名入力欄を表示します。
    'reply_subject' => true,
    // ハッシュタグのリンク化と検索を有効にします。
    'hashtag' => true,
    // お絵かきにかかった時間を投稿に表示します。
    'display_paint_time' => true,
    // お絵かき画面でパレットを選択可能にします。
    'select_palettes' => true,
    // お絵かきアニメーションデータの保存機能を有効にします。
    'animation' => true,
    // 新規お絵かき時にアニメーション保存を初期選択にします。
    'animation_default' => true,
    // 投稿済み画像から続きを描く機能を有効にします。
    'continue_drawing' => true,
    // 続きを描く際に元投稿の削除パスワードを必須にします。
    'continue_password' => false,
    // 投稿フォームや管理操作でCSRF対策トークンを検証します。
    'csrf' => true,
  ],

  // 投稿者IDの生成方法に関する設定です。
  'identity' => [
    // 投稿者IDの推測を難しくするために混ぜる秘密文字列です。
    'seed' => 'IDの種',
    // 同じIDを維持する期間です（0:固定、1:日、2:週、3:月、4:年）。
    'cycle' => 2,
  ],

  // SNS共有先と共有ウィンドウに関する設定です。
  'social' => [
    // 共有先の一覧です。各要素を［表示名, サービスのURL］の順で指定します。
    'servers' => [
      ['X', 'https://x.com'],
      ['Bluesky', 'https://bsky.app'],
      ['Threads', 'https://www.threads.net'],
      ['pawoo.net', 'https://pawoo.net'],
      ['fedibird.com', 'https://fedibird.com'],
      ['misskey.io', 'https://misskey.io'],
      ['xissmie.xfolio.jp', 'https://xissmie.xfolio.jp'],
      ['misskey.design', 'https://misskey.design'],
      ['nijimiss.moe', 'https://nijimiss.moe'],
      ['sushi.ski', 'https://sushi.ski'],
    ],
    // 共有用ポップアップウィンドウの幅です（ピクセル）。
    'window_width' => 600,
    // 共有用ポップアップウィンドウの高さです（ピクセル）。
    'window_height' => 600,
    // Misskey共有先の一覧です。各要素を［表示名, インスタンスURL］の順で指定します。
    'misskey_servers' => [
      ['misskey.io', 'https://misskey.io'],
      ['xissmie.xfolio.jp', 'https://xissmie.xfolio.jp'],
      ['misskey.design', 'https://misskey.design'],
      ['nijimiss.moe', 'https://nijimiss.moe'],
      ['misskey.art', 'https://misskey.art'],
      ['oekakiskey.com', 'https://oekakiskey.com'],
      ['misskey.gamelore.fun', 'https://misskey.gamelore.fun'],
      ['novelskey.tarbin.net', 'https://novelskey.tarbin.net'],
      ['tyazzkey.work', 'https://tyazzkey.work'],
      ['sushi.ski', 'https://sushi.ski'],
      ['misskey.delmulin.com', 'https://misskey.delmulin.com'],
      ['side.misskey.productions', 'https://side.misskey.productions'],
      ['mk.shrimpia.network', 'https://mk.shrimpia.network'],
    ],
  ],

  // 投稿内容や接続元によるスパム判定の設定です。文字列は正規表現として扱われます。
  'spam' => [
    // 本文にいずれかが一致した時点で投稿を拒否する文字列です。
    'bad_strings' => ['irc.s16.xrea.com', '著作権の侵害', '未承諾広告'],
    // 投稿者名にいずれかが一致した時点で投稿を拒否する文字列です。
    'bad_names' => ['ブランド', '通販', '販売', '口コミ'],
    // bad_strings_bと組み合わせ、両方のグループに一致した投稿を拒否する文字列です。
    'bad_strings_a' => ['激安', '低価', 'コピー', '品質を?重視', '大量入荷'],
    // bad_strings_aと組み合わせ、両方のグループに一致した投稿を拒否する文字列です。
    'bad_strings_b' => ['シャネル', 'シュプリーム', 'バレンシアガ', 'ブランド'],
    // 拒否するアップロードファイル名を列挙するための互換設定です。
    'bad_files' => ['dummy', 'dummy2'],
    // 投稿を拒否するホスト名またはIPアドレスのパターンです。
    'bad_hosts' => ['dummy.example.com', '198.51.100.0'],
  ],

  // セッションとお絵かきアプリの投稿認証に関する設定です。
  'security' => [
    // お絵かきアプリとサーバー間で共有する投稿用パスワードです。config.local.phpで変更してください。
    'paint_password' => '0qYzf1x6nyN4gS1',
    // PHPセッションCookieの名前です。同一ドメインに複数設置する場合は別々の名前にします。
    'session_name' => 'noreita_session',
    // サーバーに保存されるセッションファイルの有効期間です（秒）。
    'session_file_lifetime' => 86400,
    // お絵かき投稿までに必要な最小クリック数です。空文字列で判定を無効にします。
    'click_count' => '',
    // お絵かき画面を開いてから投稿までに必要な最小時間です（秒）。空文字列で無効です。
    'timer' => '',
    // お絵かき投稿のセキュリティ判定に失敗した場合の転送先URLです。
    'failure_url' => './security_c.html',
    // 続きを描く場合に必要な最小クリック数です。空文字列で判定を無効にします。
    'continue_click_count' => '',
    // 続きを描く画面を開いてから投稿までに必要な最小時間です（秒）。空文字列で無効です。
    'continue_timer' => '',
  ],

  // テーマ、アプリ、画像などを配置するパスです。ディレクトリは末尾に「/」を付けます。
  'paths' => [
    // 使用するテーマのディレクトリ名です。
    'theme' => 'eda',
    // PaintBBS NEOを配置するディレクトリです。
    'neo' => 'https://oekakibbs.moe/apps/neo/',
    // ChickenPaintを配置するディレクトリです。
    'chickenpaint' => 'https://oekakibbs.moe/apps/chickenpaint/',
    // Klecksを配置するディレクトリです。
    'klecks' => 'https://oekakibbs.moe/apps/klecks/',
    // Tegakiを配置するディレクトリです。
    'tegaki' => 'https://oekakibbs.moe/apps/tegaki/',
    // Axnos Paintを配置するディレクトリです。
    'axnos' => 'https://oekakibbs.moe/apps/axnos/',
    // 投稿画像を保存するディレクトリです。
    'images' => 'img/',
    // 投稿画像のサムネイルを保存するディレクトリです。
    'thumbnails' => 'thumbnail/',
    // 投稿前のお絵かき画像など、一時ファイルを保存するディレクトリです。
    'temporary' => 'tmp/',
    // 標準パレット定義ファイルのパスです。
    'palette' => 'palette.txt',
  ],

  // 投稿データ、画像、お絵かきキャンバスの上限値です。
  'limits' => [
    // 外部画像サムネイルを保存する日数です。0以下で自動削除を無効にします。
    'external_thumbnail_days' => 30,
    // 直接アップロードする画像の容量上限です（KB）。
    'upload_kb' => 10000,
    // 投稿画像として受け付ける最大幅です（ピクセル）。
    'image_width' => 4000,
    // 投稿画像として受け付ける最大高さです（ピクセル）。
    'image_height' => 4000,
    // 投稿者名の最大長です（半角文字換算）。
    'name_length' => 100,
    // メール欄の最大長です（半角文字換算）。
    'email_length' => 100,
    // 件名の最大長です（半角文字換算）。
    'subject_length' => 100,
    // URL欄の最大長です（半角文字換算）。
    'url_length' => 100,
    // 本文の最大長です（半角文字換算）。
    'comment_length' => 1000,
    // 一時ファイルを保存する日数です。
    'temporary_days' => 14,
    // お絵かきキャンバスに指定できる最大幅です（ピクセル）。
    'paint_max_width' => 800,
    // お絵かきキャンバスに指定できる最大高さです（ピクセル）。
    'paint_max_height' => 800,
    // お絵かきキャンバスの初期幅です（ピクセル）。
    'paint_default_width' => 400,
    // お絵かきキャンバスの初期高さです（ピクセル）。
    'paint_default_height' => 400,
    // お絵かきアプリが保持する「元に戻す」履歴の最大数です。
    'undo' => 90,
    // 「元に戻す」履歴をまとめて管理するグループ数です。
    'undo_group' => 45,
  ],

  // お絵かきパレットとアニメーション再生に関する設定です。
  'drawing' => [
    // 選択可能なパレットです。各要素を［表示名, パレットファイル］の順で指定します。
    'palettes' => [
      ['標準', 'palette.txt'],
      ['PCCS_HSL', 'p_PCCS.txt'],
      ['マンセルHV/C', 'p_munsellHVC.txt'],
      ['マンセル(V2)', 'p_munsell_V2.txt'],
    ],
    // アニメーションの再生速度です（-1:最速、0:標準、10/100/1000:遅延ミリ秒）。
    'animation_speed' => 0,
  ],

  // noReitaが新しく作成するファイルとディレクトリのアクセス権です（8進数）。
  'permissions' => [
    // 公開してよい画像などのファイルに設定するアクセス権です。
    'public_file' => 0644,
    // DBやログなど、外部公開しないファイルに設定するアクセス権です。
    'private_file' => 0600,
    // 公開ファイルを格納するディレクトリに設定するアクセス権です。
    'public_directory' => 0755,
    // DBやログを格納する非公開ディレクトリに設定するアクセス権です。
    'private_directory' => 0700,
  ],

  // PHPエラーログの保存とローテーションに関する設定です。
  'error_log' => [
    // エラーログを保存する日数です。
    'retention_days' => 30,
    // 1個のエラーログファイルの最大サイズです（バイト）。
    'max_bytes' => 5242880,
    // 1日に作成するローテーション済みログファイルの最大数です。
    'max_files_per_day' => 5,
  ],

  // 管理画面で行う定期的な整理処理の設定です。
  'maintenance' => [
    // 削除した投稿を隔離領域に保持する日数です。
    'delete_quarantine_days' => 30,
  ],
];
