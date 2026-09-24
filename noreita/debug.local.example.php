<?php
// 壊れたconfig.local.phpの診断をWeb上で確認するための最小設定です。
// debug.local.phpとして保存します。通常は存在させる必要はありません。
// config.local.phpが壊れている場合にも読み込まれるため、許可IP以外には絶対に公開しないでください。

return [
  // 障害調査の間だけtrueにします。
  'enabled' => false,
  // 詳細な設定エラーを表示してよい接続元IPアドレスです。
  'allowed_ips' => ['127.0.0.1'],
  // リバースプロキシ配下の場合だけ、直近のプロキシIPを指定します。
  // 診断用設定ではCIDRを使わず、個別のIPアドレスを指定してください。
  'trusted_proxies' => [],
];
