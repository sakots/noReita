<?php
// noReita v4 default configuration.
// このファイルは更新時に上書きされます。設置者固有の値はconfig.local.phpへ記述してください。

return [
  '_version' => 4,

  'admin' => [
    'password' => 'admin_pass',
    'name' => '管理人',
    'cap' => '(ではない)',
    'session_lifetime' => 1800,
    'login' => [
      'max_failures' => 5,
      'window' => 900,
      'lockout' => 900,
    ],
    'threads_per_page' => 50,
  ],

  'site' => [
    'base_url' => 'https://example.com/noreita/',
    'title' => 'お絵かき掲示板',
    'home_url' => '../',
    'language' => 'Japanese',
    'timezone' => 'Asia/Tokyo',
    'script_name' => 'index.php',
  ],

  'database' => [
    'name' => 'reita',
    'busy_timeout' => 5000,
  ],

  'board' => [
    'max_threads' => 1000,
    'page_size' => 10,
    'replies_shown' => 7,
    'log_warning_percent' => 94,
    'catalog_size' => 30,
    'cookie_days' => 7,
    'date_format' => 'Y/m/d H:i:s',
    'force_sage_replies' => 20,
    'elapsed_reply_days' => 365,
    'user_delete' => true,
    'default_name' => '名無しさん',
    'default_comment' => '本文無し',
    'default_subject' => '無題',
    'additional_info' => [
      '<a href="https://github.com/sakots/noReita">ソースはこちら</a>',
      'まだまだ開発中…バグがあったら教えてね。',
    ],
  ],

  'features' => [
    'chickenpaint' => true,
    'klecks' => true,
    'tegaki' => true,
    'axnos' => true,
    'oekaki_reply' => true,
    'share_button' => true,
    'share_details' => true,
    'misskey_note' => true,
    'external_image_thumbnail' => true,
    'nsfw' => true,
    'display_id' => true,
    'japanese_filter' => true,
    'deny_comment_urls' => false,
    'autolink' => true,
    'require_name' => false,
    'require_comment' => false,
    'require_subject' => false,
    'reply_subject' => true,
    'hashtag' => true,
    'display_paint_time' => true,
    'select_palettes' => true,
    'animation' => true,
    'animation_default' => true,
    'continue_drawing' => true,
    'continue_password' => false,
    'csrf' => true,
  ],

  'identity' => [
    'seed' => 'IDの種',
    'cycle' => 2,
  ],

  'social' => [
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
    'window_width' => 600,
    'window_height' => 600,
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

  'spam' => [
    'bad_strings' => ['irc.s16.xrea.com', '著作権の侵害', '未承諾広告'],
    'bad_names' => ['ブランド', '通販', '販売', '口コミ'],
    'bad_strings_a' => ['激安', '低価', 'コピー', '品質を?重視', '大量入荷'],
    'bad_strings_b' => ['シャネル', 'シュプリーム', 'バレンシアガ', 'ブランド'],
    'bad_files' => ['dummy', 'dummy2'],
    'bad_hosts' => ['dummy.example.com', '198.51.100.0'],
  ],

  'security' => [
    'paint_password' => '0qYzf1x6nyN4gS1',
    'session_name' => 'noreita_session',
    'session_file_lifetime' => 86400,
    'click_count' => '',
    'timer' => '',
    'failure_url' => './security_c.html',
    'continue_click_count' => '',
    'continue_timer' => '',
  ],

  'paths' => [
    'theme' => 'monoreita',
    'neo' => 'app/neo/',
    'chickenpaint' => 'app/chickenpaint/',
    'klecks' => 'app/klecks/',
    'tegaki' => 'app/tegaki/',
    'axnos' => 'app/axnos/',
    'images' => 'img/',
    'thumbnails' => 'thumbnail/',
    'temporary' => 'tmp/',
    'palette' => 'palette.txt',
  ],

  'limits' => [
    'external_thumbnail_days' => 30,
    'upload_kb' => 2000,
    'image_width' => 800,
    'image_height' => 800,
    'name_length' => 100,
    'email_length' => 100,
    'subject_length' => 100,
    'url_length' => 100,
    'comment_length' => 1000,
    'temporary_days' => 14,
    'paint_max_width' => 800,
    'paint_max_height' => 800,
    'paint_default_width' => 400,
    'paint_default_height' => 400,
    'undo' => 90,
    'undo_group' => 45,
  ],

  'drawing' => [
    'palettes' => [
      ['標準', 'palette.txt'],
      ['PCCS_HSL', 'p_PCCS.txt'],
      ['マンセルHV/C', 'p_munsellHVC.txt'],
      ['マンセル(V2)', 'p_munsell_V2.txt'],
    ],
    'animation_speed' => 0,
  ],

  'permissions' => [
    'public_file' => 0644,
    'private_file' => 0600,
    'public_directory' => 0755,
    'private_directory' => 0700,
  ],

  'error_log' => [
    'retention_days' => 30,
    'max_bytes' => 5242880,
    'max_files_per_day' => 5,
  ],

  'maintenance' => [
    'delete_quarantine_days' => 30,
  ],
];
