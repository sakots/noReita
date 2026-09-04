<?php

return [
  'format' => 1,
  'id' => 'eda',
  'name' => 'eda',
  'version' => 'lot.260904.0',
  'engine' => 'twig',
  'requires' => [
    'php' => '8.1.0',
    'noreita' => '4.5.0',
  ],
  'templates' => [
    'main' => 'eda_main', 'response' => 'eda_res', 'paint' => 'eda_paint',
    'paint_backend' => 'eda_be', 'animation' => 'eda_anime', 'tegaki_animation' => 'eda_tgkr_view',
    'image_post' => 'eda_picpost', 'catalog' => 'eda_catalog', 'admin' => 'eda_admin',
    'admin_post' => 'eda_admin_post', 'admin_errorlog' => 'eda_admin_errorlog', 'admin_temporary_images' => 'eda_admin_temporary_images', 'share_server' => 'eda_sns_share',
    'misskey_note' => 'eda_misskey_note', 'other' => 'eda_other',
  ],
  'assets' => [
    'css' => ['css/eda_index.min.css', 'css/eda_admin.css', 'luminous/luminous-basic.min.css'],
    'javascript' => ['js/appFit.js', 'js/dynamicPalette.js', 'js/imageClipboard.js', 'js/sodane.js', 'js/themeColorManager.js', 'luminous/luminous.min.js'],
  ],
];
