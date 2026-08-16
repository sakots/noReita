<?php

return [
  'format' => 1,
  'id' => 'monoreita',
  'name' => 'monoreita',
  'version' => 'lot.260816.0',
  'engine' => 'blade',
  'requires' => [
    'php' => '8.1.0',
    'noreita' => '4.0.0',
  ],
  'templates' => [
    'main' => 'monoreita_main', 'response' => 'monoreita_res', 'paint' => 'monoreita_paint',
    'paint_backend' => 'monoreita_be', 'animation' => 'monoreita_anime', 'tegaki_animation' => 'monoreita_tgkr_view',
    'image_post' => 'monoreita_picpost', 'catalog' => 'monoreita_catalog', 'admin' => 'monoreita_admin',
    'admin_post' => 'monoreita_admin_post', 'admin_errorlog' => 'monoreita_admin_errorlog', 'admin_temporary_images' => 'monoreita_admin_temporary_images', 'share_server' => 'monoreita_sns_share',
    'misskey_note' => 'monoreita_misskey_note', 'other' => 'monoreita_other',
  ],
  'assets' => [
    'css' => ['css/monoreita_index.min.css', 'luminous/luminous-basic.min.css'],
    'javascript' => ['js/appFit.js', 'js/dynamicPalette.js', 'js/sodane.js', 'js/switchcss.js', 'luminous/luminous.min.js'],
  ],
];
