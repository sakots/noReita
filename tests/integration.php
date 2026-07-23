<?php
declare(strict_types=1);

if (!extension_loaded('curl') || !extension_loaded('pdo_sqlite')) {
  fwrite(STDERR, "curl and pdo_sqlite extensions are required.\n");
  exit(1);
}

$source = dirname(__DIR__) . '/noreita';
if (!is_file($source . '/vendor/autoload.php')) {
  fwrite(STDERR, "Composer dependencies are not installed. Run composer install --working-dir=noreita.\n");
  exit(1);
}

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'noreita_integration_' . bin2hex(random_bytes(8));
$webroot = $root . DIRECTORY_SEPARATOR . 'noreita';
$cookie_jar = $root . DIRECTORY_SEPARATOR . 'cookies.txt';
$server_log = $root . DIRECTORY_SEPARATOR . 'server.log';
$process = null;
$passed = 0;
$failed = 0;

function integration_test(string $name, callable $test): void {
  global $passed, $failed;
  try {
    if ($test() !== true) {
      throw new RuntimeException('test returned false');
    }
    echo "PASS: {$name}\n";
    $passed++;
  } catch (Throwable $e) {
    echo "FAIL: {$name} ({$e->getMessage()})\n";
    $failed++;
  }
}

function copy_tree(string $source, string $destination): void {
  if (!is_dir($destination) && !mkdir($destination, 0700, true) && !is_dir($destination)) {
    throw new RuntimeException("Could not create {$destination}");
  }
  $skip = ['backup', 'cache', 'errorlog', 'img', 'session', 'temp', 'thumb', 'thumbnail', 'tmp'];
  foreach (new DirectoryIterator($source) as $item) {
    if ($item->isDot() || in_array($item->getFilename(), $skip, true) || $item->getFilename() === 'config.php') {
      continue;
    }
    $target = $destination . DIRECTORY_SEPARATOR . $item->getFilename();
    if ($item->isDir()) {
      copy_tree($item->getPathname(), $target);
    } elseif (!copy($item->getPathname(), $target)) {
      throw new RuntimeException("Could not copy {$item->getPathname()}");
    }
  }
}

function remove_tree(string $path): void {
  if (!is_dir($path)) return;
  $iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
  );
  foreach ($iterator as $item) {
    $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
  }
  rmdir($path);
}

function http_request(string $url, string $cookie_jar, ?array $post = null, string $forwarded_for = '127.0.0.1'): array {
  $curl = curl_init($url);
  curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_COOKIEJAR => $cookie_jar,
    CURLOPT_COOKIEFILE => $cookie_jar,
    CURLOPT_HTTPHEADER => [
      'Host: localhost', 'Origin: http://localhost',
      'Client-IP: ' . $forwarded_for, 'X-Forwarded-For: ' . $forwarded_for,
    ],
  ]);
  if ($post !== null) {
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post));
  }
  $body = curl_exec($curl);
  $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
  $redirect_url = (string)curl_getinfo($curl, CURLINFO_REDIRECT_URL);
  $error = curl_error($curl);
  if ($body === false) {
    throw new RuntimeException("HTTP request failed: {$error}");
  }
  return [$status, $body, $redirect_url];
}

function cookie_value(string $cookie_jar, string $name): ?string {
  foreach (file($cookie_jar, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    if (str_starts_with($line, '#HttpOnly_')) {
      $line = substr($line, strlen('#HttpOnly_'));
    } elseif ($line[0] === '#') {
      continue;
    }
    $fields = explode("\t", $line);
    if (count($fields) >= 7 && $fields[5] === $name) return $fields[6];
  }
  return null;
}

try {
  copy_tree($source, $webroot);
  $config = file_get_contents($webroot . '/config.example.php');
  if ($config === false) throw new RuntimeException('Could not read config.example.php');
  $config = str_replace("\$admin_pass = 'admin_pass';", "\$admin_pass = 'integration-admin-pass';", $config);
  $config = str_replace("const BASE = 'https://example.com/noreita/';", "const BASE = 'http://localhost/';", $config);
  $config = str_replace('const EXTERNAL_IMAGE_THUMB = 1;', 'const EXTERNAL_IMAGE_THUMB = 0;', $config);
  $config = str_replace('const USE_MISSKEY_NOTE = 1;', 'const USE_MISSKEY_NOTE = 0;', $config);
  if (file_put_contents($webroot . '/config.php', $config) === false) {
    throw new RuntimeException('Could not create test config.php');
  }

  $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error_message);
  if ($socket === false) throw new RuntimeException("Could not reserve port: {$error_message}");
  $address = stream_socket_get_name($socket, false);
  fclose($socket);
  $port = (int)substr(strrchr((string)$address, ':'), 1);
  $base_url = "http://127.0.0.1:{$port}/index.php";

  $log = fopen($server_log, 'ab');
  if ($log === false) throw new RuntimeException('Could not create server log');
  $process = proc_open([PHP_BINARY, '-S', "127.0.0.1:{$port}", '-t', $webroot], [STDIN, $log, $log], $pipes, $webroot);
  if (!is_resource($process)) throw new RuntimeException('Could not start PHP server');

  $ready = false;
  for ($attempt = 0; $attempt < 50; $attempt++) {
    usleep(100000);
    try {
      [$status] = http_request($base_url, $cookie_jar);
      if ($status === 200) {
        $ready = true;
        break;
      }
    } catch (Throwable $ignored) {
    }
  }
  if (!$ready) throw new RuntimeException('PHP server did not become ready');

  integration_test('new board creates versioned database', static function () use ($webroot): bool {
    $db = new PDO('sqlite:' . $webroot . '/reita.db');
    return (int)$db->query('PRAGMA user_version')->fetchColumn() === 1
      && (int)$db->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='board_log'")->fetchColumn() === 1;
  });

  [$missing_continue_status, $missing_continue_body] = http_request($base_url . '?mode=continue&no=1784', $cookie_jar);
  integration_test('missing continuation image shows a normal error page', static function () use ($missing_continue_status, $missing_continue_body): bool {
    return $missing_continue_status === 404
      && str_contains($missing_continue_body, 'The image does not exist.')
      && !str_contains($missing_continue_body, 'Undefined variable')
      && !str_contains($missing_continue_body, 'foreach() argument must be of type');
  });

  // pictmp initializes the CSRF token in the same session used for posting.
  [$status] = http_request($base_url . '?mode=pictmp', $cookie_jar);
  $session_id = cookie_value($cookie_jar, 'noreita_session');
  $token = $session_id === null ? '' : hash('sha256', $session_id);

  [$admin_unauthorized_status] = http_request($base_url . '?mode=admin', $cookie_jar);
  integration_test('administration screen requires a login session', static function () use ($admin_unauthorized_status): bool {
    return $admin_unauthorized_status === 403;
  });

  [$admin_login_form_status, $admin_login_form_body] = http_request($base_url . '?mode=admin_in', $cookie_jar);
  integration_test('administrator login form contains a CSRF token', static function () use ($admin_login_form_status, $admin_login_form_body, $token): bool {
    return $admin_login_form_status === 200
      && str_contains($admin_login_form_body, 'mode=admin_login')
      && str_contains($admin_login_form_body, 'name="token" value="' . $token . '"');
  });

  [$admin_wrong_password_status] = http_request($base_url . '?mode=admin_login', $cookie_jar, [
    'adminpass' => 'wrong-admin-pass', 'token' => $token,
  ]);
  integration_test('administrator login rejects a wrong password', static function () use ($admin_wrong_password_status): bool {
    return $admin_wrong_password_status === 403;
  });

  [$admin_login_status] = http_request($base_url . '?mode=admin_login', $cookie_jar, [
    'adminpass' => 'integration-admin-pass', 'token' => $token,
  ]);
  $admin_session_id = cookie_value($cookie_jar, 'noreita_session');
  $token = $admin_session_id === null ? '' : hash('sha256', $admin_session_id);
  [$admin_status, $admin_body] = http_request($base_url . '?mode=admin', $cookie_jar);
  integration_test('administrator login persists in the session', static function () use ($admin_login_status, $admin_status, $admin_body): bool {
    return $admin_login_status === 302 && $admin_status === 200
      && str_contains($admin_body, 'ADMIN MODE')
      && str_contains($admin_body, 'mode=admin_logout')
      && str_contains($admin_body, 'mode=admin_delete');
  });

  [$admin_empty_delete_status] = http_request($base_url . '?mode=admin_delete', $cookie_jar, ['token' => $token]);
  integration_test('administrator bulk delete requires a selection', static function () use ($admin_empty_delete_status): bool {
    return $admin_empty_delete_status === 400;
  });

  $share_title = '共有テスト';
  $share_target = 'https://example.com/post/1';
  [$share_form_status, $share_form_body] = http_request(
    $base_url . '?mode=set_share_server&encoded_t=' . rawurlencode($share_title) . '&encoded_u=' . rawurlencode($share_target),
    $cookie_jar
  );
  integration_test('share destination form is rendered through HTTP', static function () use ($share_form_status, $share_form_body, $token): bool {
    return $share_form_status === 200 && str_contains($share_form_body, 'sns_server_radio')
      && str_contains($share_form_body, 'name="token" value="' . $token . '"');
  });

  [$share_status, , $share_redirect] = http_request($base_url, $cookie_jar, [
    'mode' => 'post_share_server', 'sns_server_radio' => 'https://bsky.app',
    'sns_server_direct_input' => '', 'encoded_t' => $share_title, 'encoded_u' => $share_target,
    'token' => $token,
  ]);
  integration_test('share destination redirects with CSRF validation', static function () use ($share_status, $share_redirect, $share_title, $share_target): bool {
    return $share_status === 302
      && $share_redirect === 'https://bsky.app/intent/compose?text=' . rawurlencode($share_title . ' ' . $share_target);
  });

  [$invalid_csrf_status, $invalid_csrf_body] = http_request($base_url, $cookie_jar, [
    'mode' => 'post_share_server', 'sns_server_radio' => 'https://bsky.app',
    'sns_server_direct_input' => '', 'encoded_t' => $share_title, 'encoded_u' => $share_target,
    'token' => 'invalid-token',
  ]);
  integration_test('invalid CSRF token is rejected through HTTP', static function () use ($invalid_csrf_status, $invalid_csrf_body): bool {
    return $invalid_csrf_status === 403 && str_contains($invalid_csrf_body, 'CSRF token mismatch');
  });

  $marker = 'integration-' . bin2hex(random_bytes(6));
  [$post_status, $post_body] = http_request($base_url . '?mode=regist', $cookie_jar, [
    'mode' => 'regist', 'send' => '1', 'name' => "Integration O'Brien", 'mail' => '', 'url' => '',
    'sub' => "Integration's subject", 'com' => "結合テスト user's {$marker}", 'pwd' => 'delete-pass',
    'invz' => '0', 'img_w' => '0', 'img_h' => '0', 'sodane' => '0', 'nsfw' => '0', 'token' => $token,
  ]);

  $db = new PDO('sqlite:' . $webroot . '/reita.db');
  $row = $db->query('SELECT tid, a_name, sub, com FROM board_log ORDER BY tid DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
  integration_test('post is stored through HTTP', static function () use ($status, $post_status, $row, $marker): bool {
    return $status === 200 && $post_status === 200 && is_array($row)
      && $row['a_name'] === "Integration O'Brien" && $row['sub'] === "Integration's subject"
      && $row['com'] === "結合テスト user's {$marker}";
  });

  $search_term = "user's {$marker}";
  [$search_status, $search_body] = http_request($base_url . '?mode=search&tag=tag&search=' . rawurlencode($search_term), $cookie_jar);
  integration_test('search finds the posted comment', static function () use ($search_status, $search_body, $marker): bool {
    return $search_status === 200 && str_contains($search_body, $marker) && str_contains($search_body, '1件');
  });

  $post_id = (int)($row['tid'] ?? 0);
  $password_hash_before_edit = (string)$db->query('SELECT pwd FROM board_log WHERE tid = ' . $post_id)->fetchColumn();
  [$rejected_edit_status] = http_request($base_url . '?mode=editexec', $cookie_jar, [
    'mode' => 'editexec', 'e_no' => (string)$post_id, 'name' => 'Attacker', 'mail' => '', 'url' => '',
    'sub' => 'Unauthorized edit', 'com' => "不正な編集 {$marker}", 'pwd' => 'wrong-pass',
    'sodane' => '0', 'token' => $token,
  ]);
  $after_rejected_edit = $db->query('SELECT sub, com, pwd FROM board_log WHERE tid = ' . $post_id)->fetch(PDO::FETCH_ASSOC);
  integration_test('edit rejects an invalid password without changing the post', static function () use ($rejected_edit_status, $after_rejected_edit, $password_hash_before_edit): bool {
    return $rejected_edit_status === 403 && is_array($after_rejected_edit)
      && $after_rejected_edit['sub'] === "Integration's subject"
      && $after_rejected_edit['pwd'] === $password_hash_before_edit;
  });

  [$edit_status] = http_request($base_url . '?mode=editexec', $cookie_jar, [
    'mode' => 'editexec', 'e_no' => (string)$post_id, 'name' => "Edited O'Brien", 'mail' => '', 'url' => '',
    'sub' => "Edited user's subject", 'com' => "編集後 user's 結合テスト {$marker}", 'pwd' => 'delete-pass',
    'sodane' => '0', 'token' => $token,
  ]);
  $edited = $db->query('SELECT sub, com, pwd FROM board_log WHERE tid = ' . $post_id)->fetch(PDO::FETCH_ASSOC);
  integration_test('authorized edit is validated and stored through HTTP', static function () use ($edit_status, $edited, $marker, $password_hash_before_edit): bool {
    return $edit_status === 200 && is_array($edited) && $edited['sub'] === "Edited user's subject"
      && $edited['com'] === "編集後 user's 結合テスト {$marker}"
      && $edited['pwd'] === $password_hash_before_edit;
  });

  [$admin_edit_status] = http_request($base_url . '?mode=editexec', $cookie_jar, [
    'mode' => 'editexec', 'e_no' => (string)$post_id, 'name' => 'Administrator', 'mail' => '', 'url' => '',
    'sub' => "Administrator's edit", 'com' => "管理者編集 user's {$marker}", 'pwd' => 'integration-admin-pass',
    'sodane' => '0', 'token' => $token,
  ]);
  $admin_edited = $db->query('SELECT sub, com, pwd FROM board_log WHERE tid = ' . $post_id)->fetch(PDO::FETCH_ASSOC);
  integration_test('administrator can edit without replacing the post password', static function () use ($admin_edit_status, $admin_edited, $marker, $password_hash_before_edit): bool {
    return $admin_edit_status === 200 && is_array($admin_edited)
      && $admin_edited['sub'] === "Administrator's edit"
      && $admin_edited['com'] === "管理者編集 user's {$marker}"
      && $admin_edited['pwd'] === $password_hash_before_edit;
  });

  $count_before_rejections = (int)$db->query('SELECT COUNT(*) FROM board_log')->fetchColumn();
  [$ng_status, $ng_body] = http_request($base_url . '?mode=regist', $cookie_jar, [
    'mode' => 'regist', 'send' => '1', 'name' => 'NG test', 'mail' => '', 'url' => '',
    'sub' => 'NG subject', 'com' => '著作権の侵害を含む本文です', 'pwd' => 'ng-pass',
    'invz' => '0', 'sodane' => '0', 'nsfw' => '0', 'token' => $token,
  ]);
  $count_after_ng = (int)$db->query('SELECT COUNT(*) FROM board_log')->fetchColumn();
  integration_test('NG word is rejected through HTTP', static function () use ($ng_status, $ng_body, $count_before_rejections, $count_after_ng): bool {
    return $ng_status === 400 && $count_after_ng === $count_before_rejections
      && str_contains($ng_body, 'Invalid characters');
  });

  [$blocked_status, $blocked_body] = http_request($base_url . '?mode=regist', $cookie_jar, [
    'mode' => 'regist', 'send' => '1', 'name' => 'Blocked host', 'mail' => '', 'url' => '',
    'sub' => 'Blocked subject', 'com' => '拒否ホストからの本文です', 'pwd' => 'blocked-pass',
    'invz' => '0', 'sodane' => '0', 'nsfw' => '0', 'token' => $token,
  ], '198.51.100.0');
  $count_after_blocked = (int)$db->query('SELECT COUNT(*) FROM board_log')->fetchColumn();
  integration_test('blocked host is rejected through HTTP', static function () use ($blocked_status, $blocked_body, $count_before_rejections, $count_after_blocked): bool {
    return $blocked_status === 403 && $count_after_blocked === $count_before_rejections
      && str_contains($blocked_body, 'host is blocked');
  });

  [$duplicate_status, $duplicate_body] = http_request($base_url . '?mode=regist', $cookie_jar, [
    'mode' => 'regist', 'send' => '1', 'name' => 'Duplicate test', 'mail' => '', 'url' => '',
    'sub' => "Administrator's edit", 'com' => "管理者編集 user's {$marker}", 'pwd' => 'duplicate-pass',
    'invz' => '0', 'sodane' => '0', 'nsfw' => '0', 'token' => $token,
  ]);
  $count_after_duplicate = (int)$db->query('SELECT COUNT(*) FROM board_log')->fetchColumn();
  integration_test('duplicate post is rejected through HTTP', static function () use ($duplicate_status, $duplicate_body, $count_before_rejections, $count_after_duplicate): bool {
    return $duplicate_status === 409 && $count_after_duplicate === $count_before_rejections
      && str_contains($duplicate_body, 'Duplicate post');
  });

  $image_base = 'image-' . bin2hex(random_bytes(6));
  $image_name = $image_base . '.png';
  $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
  if ($png === false) throw new RuntimeException('Could not decode integration PNG');
  file_put_contents($webroot . '/tmp/' . $image_name, $png);
  file_put_contents($webroot . '/tmp/' . $image_base . '.dat', "127.0.0.1\tlocalhost\tagent\t.png\tcode\trep\t100\t160\t0\tneo");
  file_put_contents($webroot . '/tmp/' . $image_base . '.pch', 'NEO animation');
  [$image_status] = http_request($base_url . '?mode=regist', $cookie_jar, [
    'mode' => 'regist', 'send' => '1', 'name' => 'Image test', 'mail' => '', 'url' => '',
    'sub' => 'Image subject', 'com' => "画像付き投稿の本文です\n二行目です", 'pwd' => 'image-pass',
    'picfile' => $image_name, 'ctype' => 'new', 'invz' => '0', 'sodane' => '0', 'nsfw' => '0',
    'token' => $token,
  ]);
  $image_row = $db->query("SELECT tid, picfile, pchfile, img_w, img_h, psec, tool, nsfw, thumbnail FROM board_log WHERE sub = 'Image subject' ORDER BY tid DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
  integration_test('image and animation post is stored through HTTP', static function () use ($image_status, $image_row, $webroot, $image_name, $image_base): bool {
    return $image_status === 200 && is_array($image_row)
      && $image_row['picfile'] === $image_name && $image_row['pchfile'] === $image_base . '.pch'
      && (int)$image_row['img_w'] === 1 && (int)$image_row['img_h'] === 1
      && (int)$image_row['psec'] === 60 && $image_row['tool'] === 'PaintBBS NEO'
      && is_file($webroot . '/img/' . $image_name)
      && is_file($webroot . '/img/' . $image_base . '.pch')
      && !is_file($webroot . '/tmp/' . $image_base . '.dat');
  });

  $image_post_id = (int)($image_row['tid'] ?? 0);
  [$image_edit_form_status, $image_edit_form_body] = http_request($base_url, $cookie_jar, [
    'mode' => 'edit', 'delno' => (string)$image_post_id, 'pwd' => 'image-pass',
  ]);
  integration_test('image edit form includes the current NSFW setting', static function () use ($image_edit_form_status, $image_edit_form_body): bool {
    return $image_edit_form_status === 200
      && str_contains($image_edit_form_body, 'id="edit_nsfw"')
      && str_contains($image_edit_form_body, 'src="img/')
      && str_contains($image_edit_form_body, "画像付き投稿の本文です\n二行目です")
      && !str_contains($image_edit_form_body, '&lt;br')
      && !str_contains($image_edit_form_body, '&NewLine;')
      && !str_contains($image_edit_form_body, 'checked="checked"');
  });

  [$image_nsfw_status] = http_request($base_url . '?mode=editexec', $cookie_jar, [
    'mode' => 'editexec', 'e_no' => (string)$image_post_id, 'name' => 'Image test', 'mail' => '', 'url' => '',
    'sub' => 'Image subject', 'com' => "画像付き投稿の本文です\n二行目です", 'pwd' => 'image-pass',
    'sodane' => '0', 'nsfw' => '1', 'token' => $token,
  ]);
  $nsfw_image_row = $db->query('SELECT nsfw, thumbnail FROM board_log WHERE tid = ' . $image_post_id)->fetch(PDO::FETCH_ASSOC);
  $nsfw_thumbnail = (string)($nsfw_image_row['thumbnail'] ?? '');
  integration_test('comment edit can enable NSFW and refresh the thumbnail', static function () use ($image_nsfw_status, $nsfw_image_row, $nsfw_thumbnail, $webroot): bool {
    return $image_nsfw_status === 200 && (int)$nsfw_image_row['nsfw'] === 1
      && $nsfw_thumbnail !== '' && is_file($webroot . '/img/' . $nsfw_thumbnail);
  });

  [, $checked_edit_form_body] = http_request($base_url, $cookie_jar, [
    'mode' => 'edit', 'delno' => (string)$image_post_id, 'pwd' => 'image-pass',
  ]);
  integration_test('image edit form shows an enabled NSFW setting', static function () use ($checked_edit_form_body): bool {
    return preg_match('/id="edit_nsfw"[^>]*checked="checked"/', $checked_edit_form_body) === 1;
  });

  [$image_safe_status] = http_request($base_url . '?mode=editexec', $cookie_jar, [
    'mode' => 'editexec', 'e_no' => (string)$image_post_id, 'name' => 'Image test', 'mail' => '', 'url' => '',
    'sub' => 'Image subject', 'com' => "画像付き投稿の本文です\n二行目です", 'pwd' => 'image-pass',
    'sodane' => '0', 'nsfw' => '0', 'token' => $token,
  ]);
  $safe_image_row = $db->query('SELECT nsfw, thumbnail FROM board_log WHERE tid = ' . $image_post_id)->fetch(PDO::FETCH_ASSOC);
  clearstatcache(true, $webroot . '/img/' . $nsfw_thumbnail);
  integration_test('comment edit can disable NSFW and remove an obsolete thumbnail', static function () use ($image_safe_status, $safe_image_row, $webroot, $nsfw_thumbnail): bool {
    return $image_safe_status === 200 && (int)$safe_image_row['nsfw'] === 0
      && (string)$safe_image_row['thumbnail'] === ''
      && !is_file($webroot . '/img/' . $nsfw_thumbnail);
  });

  http_request($base_url . '?mode=editexec', $cookie_jar, [
    'mode' => 'editexec', 'e_no' => (string)$image_post_id, 'name' => 'Image test', 'mail' => '', 'url' => '',
    'sub' => 'Image subject', 'com' => "画像付き投稿の本文です\n二行目です", 'pwd' => 'image-pass',
    'sodane' => '0', 'nsfw' => '1', 'token' => $token,
  ]);
  $continued_from_thumbnail = (string)$db->query('SELECT thumbnail FROM board_log WHERE tid = ' . $image_post_id)->fetchColumn();

  $replacement_base = 'replacement-' . bin2hex(random_bytes(6));
  $replacement_code = 'replace-code-' . bin2hex(random_bytes(4));
  file_put_contents($webroot . '/tmp/' . $replacement_base . '.png', $png);
  file_put_contents(
    $webroot . '/tmp/' . $replacement_base . '.dat',
    "127.0.0.1\tlocalhost\tagent\t.png\tcode\t{$replacement_code}\t200\t260\t0\tneo"
  );
  file_put_contents($webroot . '/tmp/' . $replacement_base . '.pch', 'replacement animation');
  $encrypted_password = openssl_encrypt(
    'image-pass', 'aes-128-cbc', '0qYzf1x6nyN4gS1', OPENSSL_RAW_DATA, 'T3pkYxNyjN7Wz3pu'
  );
  if ($encrypted_password === false) throw new RuntimeException('Could not encrypt replacement password');
  [$replacement_status, $replacement_body] = http_request(
    $base_url . '?mode=picrep&no=' . $image_post_id . '&repcode=' . rawurlencode($replacement_code)
      . '&pwd=' . bin2hex($encrypted_password) . '&stime=300',
    $cookie_jar,
    ['nsfw' => '0']
  );
  $replaced_image_row = $db->query('SELECT picfile, pchfile, nsfw, thumbnail FROM board_log WHERE tid = ' . $image_post_id)->fetch(PDO::FETCH_ASSOC);
  $replacement_thumbnail = (string)($replaced_image_row['thumbnail'] ?? '');
  clearstatcache(true, $webroot . '/img/' . $continued_from_thumbnail);
  integration_test('continued NSFW drawing can become safe with a fresh thumbnail', static function () use (
    $replacement_status, $replacement_body, $replaced_image_row, $replacement_base,
    $replacement_thumbnail, $continued_from_thumbnail, $webroot
  ): bool {
    return $replacement_status === 200 && is_array($replaced_image_row)
      && $replaced_image_row['picfile'] === $replacement_base . '.png'
      && $replaced_image_row['pchfile'] === $replacement_base . '.pch'
      && (int)$replaced_image_row['nsfw'] === 0
      && $replacement_thumbnail !== ''
      && str_starts_with($replacement_thumbnail, $replacement_base . '_thumb_safe_')
      && is_file($webroot . '/img/' . $replacement_thumbnail)
      && !is_file($webroot . '/img/' . $continued_from_thumbnail)
      && str_contains($replacement_body, 'action="index.php?mode=editexec"')
      && str_contains($replacement_body, 'src="img/' . $replacement_thumbnail . '"')
      && str_contains($replacement_body, 'id="edit_nsfw"');
  });

  [$image_delete_status] = http_request($base_url, $cookie_jar, [
    'mode' => 'del', 'delno' => (string)$image_post_id, 'pwd' => 'image-pass',
  ]);
  clearstatcache(true, $webroot . '/img/' . $image_name);
  clearstatcache(true, $webroot . '/img/' . $image_base . '.pch');
  integration_test('deleting image post removes related files', static function () use ($image_delete_status, $db, $image_post_id, $webroot, $image_name, $image_base): bool {
    return $image_delete_status === 200
      && (int)$db->query('SELECT COUNT(*) FROM board_log WHERE tid = ' . $image_post_id)->fetchColumn() === 0
      && !is_file($webroot . '/img/' . $image_name)
      && !is_file($webroot . '/img/' . $image_base . '.pch');
  });

  [$admin_with_posts_status, $admin_with_posts_body] = http_request($base_url . '?mode=admin', $cookie_jar);
  integration_test('administration screen renders a checkbox for each post', static function () use ($admin_with_posts_status, $admin_with_posts_body, $post_id): bool {
    return $admin_with_posts_status === 200
      && str_contains($admin_with_posts_body, 'name="delno[]" value="' . $post_id . '"')
      && !str_contains($admin_with_posts_body, 'name="adminpass"');
  });

  [$delete_status] = http_request($base_url . '?mode=admin_delete', $cookie_jar, [
    'delno' => [(string)$post_id], 'token' => $token,
  ]);
  $remaining = (int)$db->query('SELECT COUNT(*) FROM board_log WHERE tid = ' . $post_id)->fetchColumn();
  integration_test('administrator can delete checked posts without resending the password', static function () use ($delete_status, $remaining): bool {
    return $delete_status === 302 && $remaining === 0;
  });

  [$empty_status, $empty_body] = http_request($base_url . '?mode=search&tag=tag&search=' . rawurlencode($search_term), $cookie_jar);
  integration_test('deleted post disappears from search', static function () use ($empty_status, $empty_body): bool {
    return $empty_status === 200 && str_contains($empty_body, '0件');
  });

  [$admin_logout_status] = http_request($base_url . '?mode=admin_logout', $cookie_jar, ['token' => $token]);
  [$admin_after_logout_status] = http_request($base_url . '?mode=admin', $cookie_jar);
  integration_test('administrator logout destroys the login session', static function () use ($admin_logout_status, $admin_after_logout_status): bool {
    return $admin_logout_status === 302 && $admin_after_logout_status === 403;
  });
} catch (Throwable $e) {
  echo "FAIL: integration setup ({$e->getMessage()})\n";
  $failed++;
  if (is_file($server_log)) {
    echo "--- server log ---\n" . file_get_contents($server_log) . "\n";
  }
} finally {
  if ($failed > 0 && is_file($server_log)) {
    echo "--- server log ---\n" . file_get_contents($server_log) . "\n";
  }
  if (is_resource($process)) {
    proc_terminate($process);
    proc_close($process);
  }
  remove_tree($root);
}

echo "\nIntegration tests: {$passed} passed, {$failed} failed.\n";
exit($failed === 0 ? 0 : 1);
